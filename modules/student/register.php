<?php
/**
 * Student Registration
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();
$currentSchoolId = getCurrentSchoolId();

if (isLoggedIn()) {
    redirect(getUserHomeUrl());
}

$pageTitle = 'Student Registration';
$error = '';
$success = '';
$classes = fetchAll("SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = sanitize($_POST['student_name'] ?? '');
    $fatherName = sanitize($_POST['father_name'] ?? '');
    $motherName = sanitize($_POST['mother_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'Other');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $classId = intval($_POST['class_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

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
        $errors[] = 'A valid 10 digit mobile number is required.';
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
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
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

    $profilePhotoFilename = '';
    $uploadedDocumentFiles = [];

    if (empty($errors) && isset($_FILES['profile_photo']) && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $profilePhotoFilename = studentPortalUploadApplicationPhoto($_FILES['profile_photo']);
            if ($profilePhotoFilename === false) {
                $errors[] = 'Profile photo upload failed. Please use JPG or PNG.';
            }
        } else {
            $errors[] = 'Could not upload the profile photo.';
        }
    }

    if (empty($errors) && isset($_FILES['documents']) && is_array($_FILES['documents']['name'] ?? null)) {
        $documentCount = count($_FILES['documents']['name']);
        for ($i = 0; $i < $documentCount; $i++) {
            if (empty($_FILES['documents']['name'][$i])) {
                continue;
            }

            if (intval($_FILES['documents']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the PDF documents could not be uploaded.';
                break;
            }

            $file = [
                'name' => $_FILES['documents']['name'][$i],
                'type' => $_FILES['documents']['type'][$i] ?? '',
                'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                'error' => $_FILES['documents']['error'][$i],
                'size' => $_FILES['documents']['size'][$i],
            ];

            $uploadedDocument = studentPortalUploadApplicationDocument($file);
            if ($uploadedDocument === false) {
                $errors[] = 'Only PDF documents are allowed for supporting uploads.';
                break;
            }

            $uploadedDocumentFiles[] = [
                'stored_name' => $uploadedDocument,
                'original_name' => $file['name'],
            ];
        }
    }

    if (empty($errors)) {
        beginTransaction();
        $newFiles = [];

        try {
            $existingUser = fetchOne(
                "SELECT * FROM users WHERE email = ? AND role = 'student' LIMIT 1",
                's',
                [$email]
            );

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $encryptedPassword = encryptAppValue($password);

            if ($existingUser && intval($existingUser['is_active']) === 1) {
                throw new Exception('An active student login already exists for this email address.');
            }

            if ($existingUser) {
                $updateUser = executeQuery(
                    "UPDATE users SET
                        username = ?,
                        full_name = ?,
                        email = ?,
                        mobile = ?,
                        school_id = COALESCE(school_id, NULLIF(?, 0)),
                        password = ?,
                        password_encrypted = ?,
                        student_id = NULL,
                        role = 'student',
                        is_active = 0,
                        updated_at = NOW()
                     WHERE user_id = ?",
                    'ssssissi',
                    [
                        $email,
                        $studentName,
                        $email,
                        $mobile,
                        $currentSchoolId,
                        $hashedPassword,
                        $encryptedPassword,
                        intval($existingUser['user_id']),
                    ]
                );

                if ($updateUser === false) {
                    throw new Exception('Could not update the pending student login.');
                }

                $userId = intval($existingUser['user_id']);
            } else {
                $result = registerUser([
                    'username' => $email,
                    'email' => $email,
                    'password' => $password,
                    'full_name' => $studentName,
                    'role' => 'student',
                    'mobile' => $mobile,
                    'password_encrypted' => $encryptedPassword,
                    'is_active' => 0,
                    'school_id' => $currentSchoolId > 0 ? $currentSchoolId : null,
                ]);

                if (empty($result['success'])) {
                    throw new Exception($result['message'] ?? 'Unable to create the student login account.');
                }

                $userId = intval($result['user_id']);
            }

            $application = fetchOne(
                "SELECT application_id, profile_photo, documents_json
                 FROM student_applications
                 WHERE user_id = ? LIMIT 1",
                'i',
                [$userId]
            );

            $documents = [];
            if (!empty($application['documents_json'])) {
                $decodedDocuments = json_decode((string)$application['documents_json'], true);
                if (is_array($decodedDocuments)) {
                    $documents = $decodedDocuments;
                }
            }
            foreach ($uploadedDocumentFiles as $documentFile) {
                $documents[] = $documentFile;
            }
            $documentsJson = !empty($documents) ? json_encode($documents) : null;

            if (!empty($profilePhotoFilename)) {
                $newFiles[] = STUDENT_APPLICATION_PHOTO_PATH . $profilePhotoFilename;
            }

            if (!empty($uploadedDocumentFiles)) {
                foreach ($uploadedDocumentFiles as $documentFile) {
                    $newFiles[] = STUDENT_APPLICATION_DOC_PATH . $documentFile['stored_name'];
                }
            }

            if ($application) {
                $oldPhoto = trim((string)($application['profile_photo'] ?? ''));
                if ($profilePhotoFilename === '') {
                    $profilePhotoFilename = $oldPhoto;
                }

                $result = executeQuery(
                    "UPDATE student_applications SET
                        student_name = ?,
                        father_name = ?,
                        mother_name = ?,
                        gender = ?,
                        mobile = ?,
                        email = ?,
                        date_of_birth = ?,
                        address = ?,
                        class_id = ?,
                        school_id = COALESCE(school_id, NULLIF(?, 0)),
                        profile_photo = ?,
                        documents_json = ?,
                        status = 'Pending',
                        rejection_reason = NULL,
                        reviewed_by = NULL,
                        reviewed_at = NULL,
                        linked_student_id = NULL,
                        updated_at = NOW()
                     WHERE application_id = ?",
                    'ssssssssiissi',
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
                        $currentSchoolId,
                        $profilePhotoFilename,
                        $documentsJson,
                        intval($application['application_id']),
                    ]
                );

                if ($result === false) {
                    throw new Exception('Could not update your student application.');
                }

                if (!empty($oldPhoto) && !empty($profilePhotoFilename) && $oldPhoto !== $profilePhotoFilename) {
                    $oldPhotoPath = STUDENT_APPLICATION_PHOTO_PATH . $oldPhoto;
                    if (file_exists($oldPhotoPath)) {
                        @deleteFile($oldPhotoPath);
                    }
                }
            } else {
                $result = executeQuery(
                    "INSERT INTO student_applications (
                        user_id, school_id, student_name, father_name, mother_name, gender,
                        mobile, email, date_of_birth, address, class_id,
                        profile_photo, documents_json, status
                    ) VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')",
                    'iissssssssiss',
                    [
                        $userId,
                        $currentSchoolId,
                        $studentName,
                        $fatherName,
                        $motherName,
                        $gender,
                        $mobile,
                        $email,
                        $dateOfBirth,
                        $address,
                        $classId,
                        $profilePhotoFilename ?: null,
                        $documentsJson,
                    ]
                );

                if ($result === false) {
                    throw new Exception('Could not save your student application.');
                }
            }

            commitTransaction();
            $_SESSION['success_message'] = 'Your student application has been submitted and is waiting for Super Admin approval.';
            header('Location: login.php?registered=1');
            exit();
        } catch (Exception $e) {
            rollbackTransaction();

            foreach ($newFiles as $filePath) {
                if (!empty($filePath) && file_exists($filePath)) {
                    @deleteFile($filePath);
                }
            }

            $error = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode(' ', $errors);
    }
}

$schoolSettings = getSchoolSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($schoolSettings['school_name']); ?> - Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/style.css'); ?>">
</head>
<body>

<div class="login-container" style="align-items: flex-start; padding-top: 32px;">
    <div class="login-card" style="max-width: 760px; width: 100%;">
        <div class="login-header">
            <?php if (!empty($schoolSettings['login_logo'])): ?>
                <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $schoolSettings['login_logo']; ?>"
                     alt="Logo" class="img-fluid">
            <?php else: ?>
                <i class="bi bi-mortarboard-fill" style="font-size: 4rem;"></i>
            <?php endif; ?>
            <h3 class="mt-3"><?php echo htmlspecialchars($schoolSettings['school_name']); ?></h3>
            <p class="mb-0">Student Registration</p>
        </div>

        <div class="login-body">
            <h4 class="text-center mb-4">Create Your Student Account</h4>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <strong>Important:</strong> Fill the details carefully and upload a clear photo plus any PDF documents.
                Super Admin can correct mistakes, reject, or approve the application before login is enabled.
            </div>

            <form method="POST" action="" class="needs-validation" enctype="multipart/form-data" novalidate>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="student_name" class="form-label">Student Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="student_name" name="student_name"
                               value="<?php echo htmlspecialchars($_POST['student_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="father_name" class="form-label">Father Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="father_name" name="father_name"
                               value="<?php echo htmlspecialchars($_POST['father_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mobile" name="mobile"
                               value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" maxlength="10" required>
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">Email ID <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <?php
                            $genderOptions = ['Male', 'Female', 'Other'];
                            $selectedGender = $_POST['gender'] ?? 'Other';
                            foreach ($genderOptions as $genderOption):
                            ?>
                                <option value="<?php echo $genderOption; ?>" <?php echo $selectedGender === $genderOption ? 'selected' : ''; ?>>
                                    <?php echo $genderOption; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo intval($class['class_id']); ?>" <?php echo (intval($_POST['class_id'] ?? 0) === intval($class['class_id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_photo" class="form-label">Profile Photo</label>
                        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/jpeg,image/jpg,image/png">
                        <small class="text-muted">Upload JPG or PNG. We’ll store it for review and approval.</small>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded p-3 bg-light text-center">
                            <img src="<?php echo htmlspecialchars(getStudentPhotoSrc()); ?>" alt="Preview" id="profilePhotoPreview" class="img-fluid rounded border" style="max-width: 180px; max-height: 220px; object-fit: cover;">
                            <div class="text-muted small mt-2">Photo preview</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="documents" class="form-label">Supporting PDF Documents</label>
                        <input type="file" class="form-control" id="documents" name="documents[]" accept="application/pdf,.pdf" multiple>
                        <small class="text-muted d-block">You can upload more than one PDF file such as transfer certificate, birth certificate, or ID proof.</small>
                        <div id="documentList" class="mt-2 small text-muted"></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Create Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-person-plus"></i> Submit Application
                    </button>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>

        <div class="login-footer">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolSettings['school_name']); ?>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/script.js"></script>
<script>
(function() {
    const photoInput = document.getElementById('profile_photo');
    const photoPreview = document.getElementById('profilePhotoPreview');
    const documentsInput = document.getElementById('documents');
    const documentList = document.getElementById('documentList');

    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    if (documentsInput && documentList) {
        documentsInput.addEventListener('change', function(event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) {
                documentList.textContent = '';
                return;
            }

            documentList.innerHTML = '<strong>Selected PDFs:</strong><br>' + files.map(function(file) {
                return '• ' + file.name;
            }).join('<br>');
        });
    }
})();

(function() {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

</body>
</html>
