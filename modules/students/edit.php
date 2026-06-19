<?php
/**
 * Edit Student
 * Update student details and photo
 */

// Include configuration (handles session start)
require_once '../../config/config.php';

// Require login and permission
requireLogin();
requirePermission('students', 'edit');

$pageTitle = 'Edit Student';
$currentUser = getCurrentUser();
$error = '';
$success = '';

// Get student ID
$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($studentId == 0) {
    alertAndRedirect('Invalid student ID', APP_URL . '/modules/students/', 'error');
}

// Get student details
$query = "SELECT * FROM students WHERE student_id = ?";
$student = fetchOne($query, 'i', [$studentId]);

if (!$student) {
    alertAndRedirect('Student not found', APP_URL . '/modules/students/', 'error');
}

// Get classes and sections
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $studentName = sanitize($_POST['student_name']);
    $dateOfBirth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    $classId = intval($_POST['class_id']);
    $sectionId = intval($_POST['section_id']);
    $rollNo = sanitize($_POST['roll_no'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $fatherName = sanitize($_POST['father_name']);
    $motherName = sanitize($_POST['mother_name'] ?? '');
    $contactNo = sanitize($_POST['contact_no']);
    $email = sanitize($_POST['email'] ?? '');
    $status = sanitize($_POST['status']);

    // Handle photo upload (Passport size: 35mm x 45mm = 350px x 450px)
    $photoFilename = $student['photo']; // Keep existing photo by default

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $newPhoto = uploadImage($_FILES['photo'], STUDENT_PHOTO_PATH, 350, 450);

        if ($newPhoto !== false) {
            // Delete old photo if exists
            if (!empty($student['photo'])) {
                deleteFile(STUDENT_PHOTO_PATH . $student['photo']);
            }
            $photoFilename = $newPhoto;
        }
    }

    // Update student
    $updateQuery = "UPDATE students SET
                    student_name = ?, date_of_birth = ?, gender = ?,
                    class_id = ?, section_id = ?, roll_no = ?, address = ?,
                    father_name = ?, mother_name = ?, contact_no = ?, email = ?,
                    photo = ?, status = ?, updated_at = NOW()
                    WHERE student_id = ?";

    $result = executeQuery($updateQuery, 'sssiiisssssssi', [
        $studentName, $dateOfBirth, $gender,
        $classId, $sectionId, $rollNo, $address,
        $fatherName, $motherName, $contactNo, $email,
        $photoFilename, $status, $studentId
    ]);

    if ($result !== false) {
        // Log activity
        logActivity($currentUser['user_id'], 'Update Student', 'Students',
            "Updated student: $studentName (ID: $studentId)");

        alertAndRedirect('Student updated successfully!', APP_URL . '/modules/students/', 'success');
    } else {
        $error = 'Failed to update student. Please try again.';
    }
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-pencil-square"></i> Edit Student
            </h2>
            <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card dashboard-card">
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>

                    <!-- Current Photo -->
                    <div class="text-center mb-4">
                        <label class="form-label fw-bold">Student Photo</label>
                        <div>
                            <?php if (!empty($student['photo'])): ?>
                                <img src="<?php echo htmlspecialchars(getStudentPhotoSrc($student['photo'])); ?>"
                                     class="student-photo mb-3" id="photoPreview" alt="Photo">
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars(getStudentPhotoSrc()); ?>"
                                     class="student-photo mb-3" id="photoPreview" alt="Photo">
                            <?php endif; ?>
                        </div>
                        <input type="file" class="form-control" name="photo" id="photo"
                               accept="image/jpeg,image/jpg,image/png">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Passport size photo (35mm x 45mm) | Leave empty to keep current | Max: 10MB
                        </small>
                    </div>

                    <hr>

                    <!-- Admission Number (Read-only) -->
                    <div class="mb-3">
                        <label class="form-label">Admission Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['admission_no']); ?>" disabled>
                        <small class="text-muted">Cannot be changed</small>
                    </div>

                    <!-- Personal Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-person"></i> Personal Details</h5>

                    <div class="mb-3">
                        <label for="student_name" class="form-label required">Student Name</label>
                        <input type="text" class="form-control" id="student_name" name="student_name"
                               value="<?php echo htmlspecialchars($student['student_name']); ?>" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label required">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo $student['date_of_birth']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label required">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="Male" <?php echo $student['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $student['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $student['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Academic Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-book"></i> Academic Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="class_id" class="form-label required">Class</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>"
                                            <?php echo $student['class_id'] == $class['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="section_id" class="form-label required">Section</label>
                            <select class="form-select" id="section_id" name="section_id" required>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>"
                                            <?php echo $student['section_id'] == $section['section_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="roll_no" class="form-label">Roll Number</label>
                            <input type="text" class="form-control" id="roll_no" name="roll_no"
                                   value="<?php echo htmlspecialchars($student['roll_no']); ?>">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address']); ?></textarea>
                    </div>

                    <!-- Parent Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-people"></i> Parent/Guardian Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="father_name" class="form-label required">Father's Name</label>
                            <input type="text" class="form-control" id="father_name" name="father_name"
                                   value="<?php echo htmlspecialchars($student['father_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mother_name" class="form-label">Mother's Name</label>
                            <input type="text" class="form-control" id="mother_name" name="mother_name"
                                   value="<?php echo htmlspecialchars($student['mother_name']); ?>">
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-telephone"></i> Contact Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="contact_no" class="form-label required">Mobile Number</label>
                            <input type="text" class="form-control" id="contact_no" name="contact_no"
                                   value="<?php echo htmlspecialchars($student['contact_no']); ?>"
                                   maxlength="10" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($student['email']); ?>">
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label required">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Active" <?php echo $student['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $student['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Student
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Inline JavaScript for image preview
$inlineScript = "
// Photo preview
$('#photo').on('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#photoPreview').attr('src', e.target.result);
        }
        reader.readAsDataURL(file);
    }
});
";

// Include footer
include '../../includes/footer.php';
?>
