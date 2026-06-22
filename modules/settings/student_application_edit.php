<?php
/**
 * Student Application Review
 * Super Admin and Admin can edit, approve, or reject student applications.
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();

requireLogin();
$currentUser = getCurrentUser();
requireRole(['super_admin', 'admin']);

$currentSchoolId = getCurrentSchoolId();

$applicationId = intval($_GET['id'] ?? 0);
$userId = intval($_GET['user_id'] ?? 0);
$application = null;

if ($applicationId > 0) {
    $application = studentPortalGetApplication($applicationId);
} elseif ($userId > 0) {
    $pendingApplication = getStudentApplicationByUserId($userId);
    if ($pendingApplication) {
        $application = studentPortalGetApplication(intval($pendingApplication['application_id']));
    }
}

if (!$application) {
    $_SESSION['error_message'] = 'Student application not found.';
    header('Location: ' . APP_URL . '/modules/settings/student_portal.php');
    exit();
}

function studentApplicationEnsureSectionId($sectionId) {
    global $currentSchoolId;
    $sectionId = intval($sectionId);
    if ($sectionId > 0) {
        return $sectionId;
    }

    $query = "SELECT section_id FROM sections WHERE is_active = 1";
    $params = [];
    $types = '';
    if (intval($currentSchoolId) > 0) {
        $query .= " AND COALESCE(school_id, 0) = ?";
        $params[] = intval($currentSchoolId);
        $types .= 'i';
    }
    $query .= " ORDER BY section_name LIMIT 1";
    $defaultSection = empty($types) ? fetchOne($query) : fetchOne($query, $types, $params);
    return intval($defaultSection['section_id'] ?? 0);
}

function studentApplicationGenerateAndStorePassword(array $user) {
    $plainPassword = generatePassword(8);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $encryptedPassword = encryptAppValue($plainPassword);

    $result = executeQuery(
        "UPDATE users SET password = ?, password_encrypted = ?, updated_at = NOW() WHERE user_id = ?",
        'ssi',
        [$hashedPassword, $encryptedPassword, intval($user['user_id'])]
    );

    return [$plainPassword, $result !== false];
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $deleteResult = studentPortalDeleteApplication(intval($application['application_id']), intval($currentUser['user_id']));
        if ($deleteResult['success']) {
            $_SESSION['success_message'] = $deleteResult['message'];
            header('Location: ' . APP_URL . '/modules/settings/student_portal.php#applications');
            exit();
        }

        $_SESSION['error_message'] = $deleteResult['message'] ?? 'Unable to delete the application.';
        header('Location: ' . APP_URL . '/modules/settings/student_portal.php#applications');
        exit();
    }

    $studentName = sanitize($_POST['student_name'] ?? '');
    $fatherName = sanitize($_POST['father_name'] ?? '');
    $motherName = sanitize($_POST['mother_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'Other');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $classId = intval($_POST['class_id'] ?? 0);
    $sectionId = intval($_POST['section_id'] ?? 0);
    $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $gender = 'Other';
    }

    $errors = [];

    if ($studentName === '') {
        $errors[] = 'Student name is required.';
    }
    if ($fatherName === '') {
        $errors[] = 'Father name is required.';
    }
    if ($mobile === '' || !isValidMobile($mobile)) {
        $errors[] = 'A valid mobile number is required.';
    }
    if ($email === '' || !isValidEmail($email)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($dateOfBirth === '') {
        $errors[] = 'Date of birth is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if ($classId <= 0) {
        $errors[] = 'Class is required.';
    }

    $classRecord = null;
    if (empty($errors)) {
        $classRecord = fetchOne(
            "SELECT class_id, class_name FROM classes WHERE class_id = ? AND is_active = 1 LIMIT 1",
            'i',
            [$classId]
        );
        if (!$classRecord) {
            $errors[] = 'Please select a valid active class.';
        }
    }

    $newPhotoFilename = '';
    $uploadedDocuments = [];
    if (empty($errors) && isset($_FILES['profile_photo']) && intval($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $newPhotoFilename = studentPortalUploadApplicationPhoto($_FILES['profile_photo']);
        if ($newPhotoFilename === false) {
            $errors[] = 'Profile photo upload failed.';
        }
    }

    if (empty($errors) && isset($_FILES['documents']) && is_array($_FILES['documents']['name'] ?? null)) {
        $count = count($_FILES['documents']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($_FILES['documents']['name'][$i])) {
                continue;
            }

            if (intval($_FILES['documents']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the PDF uploads failed.';
                break;
            }

            $file = [
                'name' => $_FILES['documents']['name'][$i],
                'type' => $_FILES['documents']['type'][$i] ?? '',
                'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                'error' => $_FILES['documents']['error'][$i],
                'size' => $_FILES['documents']['size'][$i],
            ];

            $stored = studentPortalUploadApplicationDocument($file);
            if ($stored === false) {
                $errors[] = 'Only PDF documents can be uploaded.';
                break;
            }

            $uploadedDocuments[] = [
                'stored_name' => $stored,
                'original_name' => $file['name'],
            ];
        }
    }

    if (empty($errors)) {
        beginTransaction();
        $filesToCleanup = [];

        try {
            $freshApplication = studentPortalGetApplication(intval($application['application_id']));
            if (!$freshApplication) {
                throw new Exception('The student application could not be found.');
            }

            $targetSchoolId = intval($currentSchoolId);
            if ($targetSchoolId <= 0) {
                $targetSchoolId = intval($freshApplication['school_id'] ?? 0);
            }

            $existingDocuments = studentPortalGetApplicationDocuments($freshApplication['documents_json'] ?? '');
            $mergedDocuments = array_merge($existingDocuments, $uploadedDocuments);
            $documentsJson = !empty($mergedDocuments) ? json_encode($mergedDocuments) : null;

            $photoFilename = $freshApplication['profile_photo'] ?? '';
            if (!empty($newPhotoFilename)) {
                $photoFilename = $newPhotoFilename;
                $filesToCleanup[] = STUDENT_APPLICATION_PHOTO_PATH . $newPhotoFilename;
            }

            foreach ($uploadedDocuments as $document) {
                $filesToCleanup[] = STUDENT_APPLICATION_DOC_PATH . $document['stored_name'];
            }

            $saveQuery = "UPDATE student_applications SET
                student_name = ?,
                father_name = ?,
                mother_name = ?,
                gender = ?,
                mobile = ?,
                email = ?,
                date_of_birth = ?,
                address = ?,
                class_id = ?,
                section_id = ?,
                school_id = COALESCE(school_id, NULLIF(?, 0)),
                profile_photo = ?,
                documents_json = ?,
                status = ?,
                rejection_reason = ?,
                reviewed_by = ?,
                reviewed_at = ?,
                updated_at = NOW()
                WHERE application_id = ?";

            $saveStatus = $application['status'] ?? 'Pending';
            $reviewedAt = $application['reviewed_at'] ?? null;
            $reviewedBy = !empty($application['reviewed_by']) ? intval($application['reviewed_by']) : null;

            if ($action === 'reject') {
                $saveStatus = 'Rejected';
                if ($rejectionReason === '') {
                    $rejectionReason = 'Rejected by the approving authority.';
                }
                $reviewedAt = date('Y-m-d H:i:s');
                $reviewedBy = intval($currentUser['user_id']);
            } elseif ($action === 'approve') {
                $saveStatus = 'Approved';
                $reviewedAt = date('Y-m-d H:i:s');
                $reviewedBy = intval($currentUser['user_id']);
                $rejectionReason = '';
            }

            $result = executeQuery(
                $saveQuery,
                'ssssssssiiissssisi',
                [
                    $studentName,
                    $fatherName,
                    $motherName,
                    $gender,
                    $mobile,
                    $email,
                    $dateOfBirth,
                    $address,
                    $classId,
                    studentApplicationEnsureSectionId($sectionId),
                    $targetSchoolId,
                    $photoFilename,
                    $documentsJson,
                    $saveStatus,
                    $rejectionReason,
                    $reviewedBy,
                    $reviewedAt,
                    intval($freshApplication['application_id']),
                ]
            );

            if ($result === false) {
                throw new Exception('Unable to save the application changes.');
            }

            $userRecord = fetchOne("SELECT * FROM users WHERE user_id = ? LIMIT 1", 'i', [intval($freshApplication['user_id'])]);
            if (!$userRecord) {
                throw new Exception('Linked student login account not found.');
            }

            $updateUser = executeQuery(
                "UPDATE users SET
                    username = ?,
                    full_name = ?,
                    email = ?,
                    mobile = ?,
                    role = 'student',
                    is_active = ?,
                    student_id = ?,
                    updated_at = NOW()
                 WHERE user_id = ?",
                'ssssiii',
                [
                    $email,
                    $studentName,
                    $email,
                    $mobile,
                    $saveStatus === 'Approved' ? 1 : 0,
                    intval($freshApplication['linked_student_id'] ?? 0),
                    intval($userRecord['user_id']),
                ]
            );

            if ($updateUser === false) {
                throw new Exception('Unable to update the linked login account.');
            }

            $studentId = intval($freshApplication['linked_student_id'] ?? 0);
            $studentPhoto = '';
            $oldStudentPhoto = '';
            if ($saveStatus === 'Approved') {
                $sectionIdForStudent = studentApplicationEnsureSectionId($sectionId);
                if ($sectionIdForStudent <= 0) {
                    throw new Exception('Please select a valid section before approval.');
                }

                if (!empty($studentId)) {
                    $existingStudent = studentPortalGetStudentRecord($studentId);
                    if (!$existingStudent) {
                        $studentId = 0;
                    } else {
                        $oldStudentPhoto = trim((string)($existingStudent['photo'] ?? ''));
                    }
                }

                if ($studentId <= 0) {
                    if ($targetSchoolId > 0) {
                        $studentLimit = getSchoolStudentAddLimit($targetSchoolId);
                        $activeStudentCount = getSchoolActiveStudentCount($targetSchoolId);
                        if ($studentLimit > 0 && $activeStudentCount >= $studentLimit) {
                            throw new Exception(
                                'Student admission limit reached for this school (' .
                                number_format($activeStudentCount) . '/' . number_format($studentLimit) .
                                '). Please ask Super Admin to increase the limit before approving more students.'
                            );
                        }
                    }

                    $admissionNo = getNextAdmissionNumber();
                    $studentResult = executeQuery(
                        "INSERT INTO students (
                            school_id, admission_no, student_name, date_of_birth, gender,
                            class_id, section_id, roll_no, address,
                            father_name, mother_name, contact_no, email,
                            admission_date, photo, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'Active')",
                        'issssiisssssss',
                        [
                            $targetSchoolId,
                            $admissionNo,
                            $studentName,
                            $dateOfBirth,
                            $gender,
                            $classId,
                            $sectionIdForStudent,
                            $address,
                            $fatherName,
                            $motherName,
                            $mobile,
                            $email,
                            date('Y-m-d'),
                            $photoFilename ?: null,
                        ]
                    );

                    if ($studentResult === false) {
                        throw new Exception('Unable to create the student record.');
                    }

                    $studentId = intval($studentResult['insert_id']);
                    $studentRecord = studentPortalGetStudentRecord($studentId);
                } else {
                    $studentRecord = studentPortalGetStudentRecord($studentId);
                    $admissionNo = $studentRecord['admission_no'] ?? getNextAdmissionNumber();
                    $studentPhoto = trim((string)($studentRecord['photo'] ?? ''));

                    $studentUpdate = executeQuery(
                        "UPDATE students SET
                            school_id = COALESCE(school_id, ?),
                            student_name = ?,
                            date_of_birth = ?,
                            gender = ?,
                            class_id = ?,
                            section_id = ?,
                            address = ?,
                            father_name = ?,
                            mother_name = ?,
                            contact_no = ?,
                            email = ?,
                            photo = COALESCE(NULLIF(?, ''), photo),
                            status = 'Active',
                            updated_at = NOW()
                         WHERE student_id = ?",
                        'isssiiisssssi',
                        [
                            $targetSchoolId,
                            $studentName,
                            $dateOfBirth,
                            $gender,
                            $classId,
                            $sectionIdForStudent,
                            $address,
                            $fatherName,
                            $motherName,
                            $mobile,
                            $email,
                            $photoFilename,
                            $studentId,
                        ]
                    );

                    if ($studentUpdate === false) {
                        throw new Exception('Unable to update the student record.');
                    }
                }

                $storedPasswordEncrypted = (string)($userRecord['password_encrypted'] ?? '');

                $finalLinkResult = executeQuery(
                    "UPDATE users SET
                        username = ?,
                        full_name = ?,
                        email = ?,
                        mobile = ?,
                        student_id = ?,
                        password_encrypted = ?,
                        is_active = 1,
                        updated_at = NOW()
                     WHERE user_id = ?",
                    'ssssisi',
                    [
                        $admissionNo,
                        $studentName,
                        $email,
                        $mobile,
                        $studentId,
                        $storedPasswordEncrypted,
                        intval($userRecord['user_id']),
                    ]
                );

                if ($finalLinkResult === false) {
                    throw new Exception('Unable to finalize the login account link.');
                }

                if (!empty($photoFilename)) {
                    $sourcePhoto = STUDENT_APPLICATION_PHOTO_PATH . $photoFilename;
                    $destinationPhoto = STUDENT_PHOTO_PATH . $photoFilename;
                    if (file_exists($sourcePhoto)) {
                        ensureDirectoryExists(STUDENT_PHOTO_PATH);
                        @copy($sourcePhoto, $destinationPhoto);
                    }
                }

                $userPassword = decryptAppValue($storedPasswordEncrypted);
                if ($userPassword === '') {
                    [$userPassword, $passwordSaved] = studentApplicationGenerateAndStorePassword($userRecord);
                    if (!$passwordSaved) {
                        throw new Exception('Could not prepare the student password.');
                    }
                    $storedPasswordEncrypted = encryptAppValue($userPassword);
                }

                $linkResult = executeQuery(
                    "UPDATE users SET
                        username = ?,
                        full_name = ?,
                        email = ?,
                        mobile = ?,
                        student_id = ?,
                        password_encrypted = ?,
                        is_active = 1,
                        updated_at = NOW()
                     WHERE user_id = ?",
                    'ssssisi',
                    [
                        $admissionNo,
                        $studentName,
                        $email,
                        $mobile,
                        $studentId,
                        $storedPasswordEncrypted,
                        intval($userRecord['user_id']),
                    ]
                );

                if ($linkResult === false) {
                    throw new Exception('Unable to link the login account to the student record.');
                }

                $notification = studentPortalSendCredentialsNotification([
                    'full_name' => $studentName,
                    'username' => $admissionNo,
                    'mobile' => $mobile,
                    'email' => $email,
                ], $userPassword);

                if ($notification['sent']) {
                    executeQuery("UPDATE users SET password_encrypted = NULL, updated_at = NOW() WHERE user_id = ?", 'i', [intval($userRecord['user_id'])]);
                } else {
                    $message = 'The application was approved, but the login credentials could not be sent automatically. Temporary password: ' . $userPassword;
                }

                executeQuery(
                    "UPDATE student_applications SET linked_student_id = ?, reviewed_by = ?, reviewed_at = NOW(), status = 'Approved', rejection_reason = NULL, school_id = COALESCE(school_id, NULLIF(?, 0)) WHERE application_id = ?",
                    'iiii',
                    [
                        $studentId,
                        intval($currentUser['user_id']),
                        $currentSchoolId,
                        intval($freshApplication['application_id']),
                    ]
                );

                logActivity($currentUser['user_id'], 'Approve Student Application', 'student_applications', 'Approved application ID: ' . intval($freshApplication['application_id']));
                $message = $message ?: 'Student application approved successfully.';
            } elseif ($saveStatus === 'Rejected') {
                executeQuery(
                    "UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ?",
                    'i',
                    [intval($userRecord['user_id'])]
                );
                logActivity($currentUser['user_id'], 'Reject Student Application', 'student_applications', 'Rejected application ID: ' . intval($freshApplication['application_id']));
                $message = 'Student application rejected successfully.';
            } else {
                logActivity($currentUser['user_id'], 'Update Student Application', 'student_applications', 'Updated application ID: ' . intval($freshApplication['application_id']));
                $message = 'Student application updated successfully.';
            }

            commitTransaction();
            $_SESSION['success_message'] = $message;
            header('Location: student_portal.php');
            exit();
        } catch (Exception $e) {
            rollbackTransaction();
            foreach ($filesToCleanup as $path) {
                if (!empty($path) && file_exists($path)) {
                    @deleteFile($path);
                }
            }
            $error = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode(' ', $errors);
    }
}

$application = studentPortalGetApplication(intval($application['application_id']));
$classQuery = "SELECT class_id, class_name FROM classes WHERE is_active = 1";
$classParams = [];
$classTypes = '';
if ($currentSchoolId > 0) {
    $classQuery .= " AND COALESCE(school_id, 0) = ?";
    $classParams[] = $currentSchoolId;
    $classTypes .= 'i';
}
$classQuery .= " ORDER BY class_order";
$classOptions = empty($classTypes) ? fetchAll($classQuery) : fetchAll($classQuery, $classTypes, $classParams);

$sectionQuery = "SELECT section_id, section_name FROM sections WHERE is_active = 1";
$sectionParams = [];
$sectionTypes = '';
if ($currentSchoolId > 0) {
    $sectionQuery .= " AND COALESCE(school_id, 0) = ?";
    $sectionParams[] = $currentSchoolId;
    $sectionTypes .= 'i';
}
$sectionQuery .= " ORDER BY section_name";
$sectionOptions = empty($sectionTypes) ? fetchAll($sectionQuery) : fetchAll($sectionQuery, $sectionTypes, $sectionParams);
$documents = studentPortalGetApplicationDocuments($application['documents_json'] ?? '');
$selectedSectionId = intval($application['section_id'] ?? 0);
$linkedStudent = !empty($application['linked_student_id']) ? studentPortalGetStudentRecord(intval($application['linked_student_id'])) : null;
$isRejected = ($application['status'] ?? '') === 'Rejected';
$isApproved = ($application['status'] ?? '') === 'Approved';

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-0"><i class="bi bi-person-check"></i> Review Student Application</h2>
                <div class="text-muted">Correct the details, then approve or reject the request.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?php echo APP_URL; ?>/modules/settings/student_portal.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Student Portal
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Application ID</h5>
                <h3><?php echo intval($application['application_id']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-<?php echo $isApproved ? 'success' : ($isRejected ? 'danger' : 'info'); ?>">
            <div class="card-body text-center">
                <h5>Status</h5>
                <h3><?php echo htmlspecialchars($application['status'] ?? 'Pending'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h5>Class</h5>
                <h3 style="font-size: 1rem;"><?php echo htmlspecialchars(($application['class_name'] ?? '-') . ' ' . ($application['section_name'] ?? '')); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-dark">
            <div class="card-body text-center">
                <h5>Login</h5>
                <h3 style="font-size: 1rem;"><?php echo !empty($application['user_is_active']) ? 'Active' : 'Inactive'; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Application Details</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($isApproved && !empty($application['user_id'])): ?>
                        <a href="<?php echo APP_URL; ?>/modules/settings/student_portal.php" class="btn btn-light btn-sm">
                            <i class="bi bi-check-circle"></i> Approved
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" id="reviewAction" value="save">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center bg-light">
                                <img src="<?php echo htmlspecialchars(studentPortalGetApplicationPhotoUrl($application['profile_photo'] ?? '')); ?>" alt="Student Photo" class="img-fluid rounded border" id="applicationPhotoPreview" style="max-width: 180px; max-height: 220px; object-fit: cover;">
                                <div class="mt-2">
                                    <label class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-upload"></i> Replace Photo
                                        <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/jpg,image/png" class="d-none">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Student Name</label>
                                    <input type="text" class="form-control" name="student_name" value="<?php echo htmlspecialchars($application['student_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Father Name</label>
                                    <input type="text" class="form-control" name="father_name" value="<?php echo htmlspecialchars($application['father_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mother Name</label>
                                    <input type="text" class="form-control" name="mother_name" value="<?php echo htmlspecialchars($application['mother_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <?php foreach (['Male', 'Female', 'Other'] as $genderOption): ?>
                                            <option value="<?php echo $genderOption; ?>" <?php echo (($application['gender'] ?? 'Other') === $genderOption) ? 'selected' : ''; ?>>
                                                <?php echo $genderOption; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" class="form-control" name="mobile" value="<?php echo htmlspecialchars($application['mobile'] ?? ''); ?>" maxlength="10" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($application['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($application['date_of_birth'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Class</label>
                                    <select class="form-select" name="class_id" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classOptions as $classOption): ?>
                                            <option value="<?php echo intval($classOption['class_id']); ?>" <?php echo intval($application['class_id'] ?? 0) === intval($classOption['class_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($classOption['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Section</label>
                                    <select class="form-select" name="section_id">
                                        <option value="">Select Section</option>
                                        <?php foreach ($sectionOptions as $sectionOption): ?>
                                            <option value="<?php echo intval($sectionOption['section_id']); ?>" <?php echo $selectedSectionId === intval($sectionOption['section_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sectionOption['section_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($application['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="mb-2"><i class="bi bi-paperclip"></i> PDF Documents</h6>
                                    <?php if (!empty($documents)): ?>
                                        <div class="mb-2">
                                            <?php foreach ($documents as $doc): ?>
                                                <div class="small">
                                                    <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" rel="noopener">
                                                        <?php echo htmlspecialchars($doc['original_name']); ?>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small mb-2">No PDF documents uploaded yet.</div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="documents[]" accept="application/pdf,.pdf" multiple>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="mb-2"><i class="bi bi-chat-square-text"></i> Review Notes</h6>
                                    <textarea class="form-control mb-3" name="rejection_reason" rows="4" placeholder="Enter rejection reason or correction notes..."><?php echo htmlspecialchars($application['rejection_reason'] ?? ''); ?></textarea>
                                    <div class="small text-muted">
                                        Use this space to explain a rejection or note the correction that was made.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-4">
                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('reviewAction').value='save'">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <button type="submit" class="btn btn-success" onclick="document.getElementById('reviewAction').value='approve'" <?php echo $isApproved ? 'disabled' : ''; ?>>
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('reviewAction').value='reject'">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <?php if (studentPortalCanDeleteApplication($application)): ?>
                            <button type="submit"
                                    class="btn btn-outline-danger"
                                    onclick="document.getElementById('reviewAction').value='delete'; return confirm('Delete this <?php echo htmlspecialchars(strtolower((string)($application['status'] ?? 'pending'))); ?> application and its login account? This cannot be undone.');">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo APP_URL; ?>/modules/settings/student_portal.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancel
                        </a>
                    </div>
                </form>

                <div class="row g-3 mt-4">
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <h6 class="mb-2">Login Details</h6>
                            <div><strong>Username:</strong> <?php echo htmlspecialchars($application['username'] ?? '-'); ?></div>
                            <div><strong>Email:</strong> <?php echo htmlspecialchars($application['login_email'] ?? $application['email'] ?? '-'); ?></div>
                            <div><strong>Mobile:</strong> <?php echo htmlspecialchars($application['login_mobile'] ?? $application['mobile'] ?? '-'); ?></div>
                            <div><strong>Login:</strong> <?php echo !empty($application['user_is_active']) ? 'Active' : 'Inactive'; ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <h6 class="mb-2">Linked Student</h6>
                            <?php if ($linkedStudent): ?>
                                <div><strong>Name:</strong> <?php echo htmlspecialchars($linkedStudent['student_name'] ?? '-'); ?></div>
                                <div><strong>Admission No:</strong> <?php echo htmlspecialchars($linkedStudent['admission_no'] ?? '-'); ?></div>
                                <div><strong>Class:</strong> <?php echo htmlspecialchars(($linkedStudent['class_name'] ?? '-') . ' ' . ($linkedStudent['section_name'] ?? '')); ?></div>
                            <?php else: ?>
                                <div class="text-muted">No student record linked yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($application['rejection_reason']) && $isRejected): ?>
                    <div class="alert alert-warning mt-4 mb-0">
                        <strong>Rejection reason:</strong> <?php echo htmlspecialchars($application['rejection_reason']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
(function() {
    const input = document.getElementById('profile_photo');
    const preview = document.getElementById('applicationPhotoPreview');
    if (input && preview) {
        input.addEventListener('change', function(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) {
                return;
            }
            const reader = new FileReader();
            reader.onload = function(ev) {
                preview.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    const form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
})();
";

include '../../includes/footer.php';
