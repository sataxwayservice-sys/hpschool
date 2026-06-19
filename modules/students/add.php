<?php
/**
 * Add New Student
 * Complete student admission form with photo upload
 */

// Include configuration (handles session start)
require_once '../../config/config.php';

// Require login and permission
requireLogin();
requirePermission('students', 'add');

$pageTitle = 'Add Student';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$error = '';
$success = '';

// Get classes and sections for dropdowns
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate required fields
    $required = [
        'student_name', 'date_of_birth', 'gender', 'class_id',
        'section_id', 'father_name', 'contact_no', 'admission_date'
    ];

    $errors = [];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    // Validate mobile number
    if (!empty($_POST['contact_no']) && !isValidMobile($_POST['contact_no'])) {
        $errors[] = 'Invalid mobile number';
    }

    // Validate email if provided
    if (!empty($_POST['email']) && !isValidEmail($_POST['email'])) {
        $errors[] = 'Invalid email address';
    }

    if (empty($errors)) {
        // Sanitize inputs
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
        $admissionDate = sanitize($_POST['admission_date']);

        // Generate admission number
        $admissionNo = getNextAdmissionNumber();

        // Handle photo upload (Passport size: 35mm x 45mm = 350px x 450px)
        $photoFilename = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoFilename = uploadImage($_FILES['photo'], STUDENT_PHOTO_PATH, 350, 450);

            if ($photoFilename === false) {
                $errors[] = 'Failed to upload photo. Please check file type and size.';
            }
        }

        if (empty($errors)) {
            // Begin transaction
            beginTransaction();

            try {
                // Insert student
                $query = "INSERT INTO students (
                    school_id, admission_no, student_name, date_of_birth, gender,
                    class_id, section_id, roll_no, address,
                    father_name, mother_name, contact_no, email,
                    admission_date, photo, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

                $result = executeQuery($query, 'issssiiisssssss', [
                    $currentSchoolId,
                    $admissionNo,
                    $studentName,
                    $dateOfBirth,
                    $gender,
                    $classId,
                    $sectionId,
                    $rollNo,
                    $address,
                    $fatherName,
                    $motherName,
                    $contactNo,
                    $email,
                    $admissionDate,
                    $photoFilename
                ]);

                if ($result === false) {
                    throw new Exception('Failed to insert student record');
                }

                $studentId = $result['insert_id'];

                // Log activity
                logActivity($currentUser['user_id'], 'Add Student', 'Students',
                    "Added student: $studentName (Admission No: $admissionNo)");

                // Sync to Firebase if enabled
                if (FIREBASE_BACKUP_ENABLED) {
                    $className = fetchOne("SELECT class_name FROM classes WHERE class_id = ?", 'i', [$classId]);
                    $sectionName = fetchOne("SELECT section_name FROM sections WHERE section_id = ?", 'i', [$sectionId]);

                    $firebaseData = [
                        'admission_no' => $admissionNo,
                        'student_name' => $studentName,
                        'class' => $className['class_name'] . ' ' . $sectionName['section_name'],
                        'father_name' => $fatherName,
                        'contact_no' => $contactNo,
                        'status' => 'Active',
                        'synced_at' => date('c')
                    ];

                    syncToFirebase('students/' . $studentId, $firebaseData);
                }

                // Commit transaction
                commitTransaction();

                // Send SMS if enabled (optional)
                if (FIREBASE_SMS_ENABLED) {
                    $message = "Dear Parent, Your child $studentName has been admitted to our school. Admission No: $admissionNo. Contact us for fee details. Thank you!";
                    sendSMSViaFirebase($contactNo, $message);
                }

                // Redirect to fee structure setup
                alertAndRedirect(
                    'Student added successfully! Now setup fee structure.',
                    APP_URL . '/modules/fees/structure.php?student_id=' . $studentId,
                    'success'
                );

            } catch (Exception $e) {
                // Rollback transaction
                rollbackTransaction();

                // Delete uploaded photo if exists
                if ($photoFilename) {
                    deleteFile(STUDENT_PHOTO_PATH . $photoFilename);
                }

                $error = 'Failed to add student: ' . $e->getMessage();
                error_log($error);
            }
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
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
                <i class="bi bi-person-plus"></i> Add New Student
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

                    <!-- Student Photo -->
                    <div class="text-center mb-4">
                        <label class="form-label fw-bold">Student Photo</label>
                        <div>
                            <img src="<?php echo htmlspecialchars(getStudentPhotoSrc()); ?>"
                                 class="student-photo mb-3" id="photoPreview" alt="Photo"
                                 style="width: 175px; height: 225px; object-fit: cover; border-radius: 10px; border: 3px solid #ddd;">
                        </div>
                        <div class="btn-group mb-2" role="group">
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('photo').click()">
                                <i class="bi bi-upload"></i> Upload Photo
                            </button>
                            <button type="button" class="btn btn-success" id="openCameraBtn">
                                <i class="bi bi-camera"></i> Take Photo
                            </button>
                        </div>
                        <input type="file" class="form-control d-none" name="photo" id="photo"
                               accept="image/jpeg,image/jpg,image/png">
                        <input type="hidden" name="photo_from_camera" id="photoFromCamera" value="">
                        <small class="d-block text-muted">
                            <i class="bi bi-info-circle"></i> Passport size photo (35mm x 45mm) | Max: 10MB | JPG, PNG
                        </small>
                    </div>

                    <hr>

                    <!-- Admission Details -->
                    <h5 class="mb-3"><i class="bi bi-card-checklist"></i> Admission Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="admission_date" class="form-label required">Admission Date</label>
                            <input type="date" class="form-control" id="admission_date" name="admission_date"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Admission Number</label>
                            <input type="text" class="form-control" value="Auto-generated" disabled>
                            <small class="text-muted">Will be generated automatically</small>
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-person"></i> Personal Details</h5>

                    <div class="mb-3">
                        <label for="student_name" class="form-label required">Student Name</label>
                        <input type="text" class="form-control" id="student_name" name="student_name"
                               placeholder="Enter full name" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label required">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label required">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Academic Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-book"></i> Academic Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="class_id" class="form-label required">Class</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="section_id" class="form-label required">Section</label>
                            <select class="form-select" id="section_id" name="section_id" required>
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="roll_no" class="form-label">Roll Number</label>
                            <input type="text" class="form-control" id="roll_no" name="roll_no"
                                   placeholder="Optional">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"
                                  placeholder="Enter full residential address"></textarea>
                    </div>

                    <!-- Parent Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-people"></i> Parent/Guardian Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="father_name" class="form-label required">Father's Name</label>
                            <input type="text" class="form-control" id="father_name" name="father_name"
                                   placeholder="Enter father's name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mother_name" class="form-label">Mother's Name</label>
                            <input type="text" class="form-control" id="mother_name" name="mother_name"
                                   placeholder="Enter mother's name">
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <h5 class="mb-3 mt-4"><i class="bi bi-telephone"></i> Contact Details</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="contact_no" class="form-label required">Mobile Number</label>
                            <input type="text" class="form-control" id="contact_no" name="contact_no"
                                   placeholder="10-digit mobile number" maxlength="10" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="email@example.com">
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Student & Setup Fees
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- Camera Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="cameraModalLabel">
                    <i class="bi bi-camera-fill"></i> Take Student Photo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Camera View -->
                <div id="cameraView" style="position: relative;">
                    <video id="cameraStream" autoplay playsinline style="width: 100%; max-width: 640px; border-radius: 10px; border: 3px solid #ddd;"></video>
                    <div class="mt-3">
                        <button type="button" class="btn btn-success btn-lg" id="captureBtn">
                            <i class="bi bi-camera"></i> Capture Photo
                        </button>
                    </div>
                </div>

                <!-- Captured Photo View -->
                <div id="capturedView" style="display: none;">
                    <canvas id="photoCanvas" style="width: 100%; max-width: 640px; border-radius: 10px; border: 3px solid #ddd;"></canvas>
                    <div class="mt-3">
                        <button type="button" class="btn btn-warning" id="retakeBtn">
                            <i class="bi bi-arrow-counterclockwise"></i> Retake
                        </button>
                        <button type="button" class="btn btn-primary" id="usePhotoBtn">
                            <i class="bi bi-check-circle"></i> Use This Photo
                        </button>
                    </div>
                </div>

                <!-- Camera Error Message -->
                <div id="cameraError" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Camera Access Denied</strong>
                    <p class="mb-0">Please allow camera access in your browser settings to use this feature.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Inline JavaScript for image preview and camera
$inlineScript = "
// Photo preview from file upload
$('#photo').on('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#photoPreview').attr('src', e.target.result);
        }
        reader.readAsDataURL(file);
        // Clear camera data if file is uploaded
        $('#photoFromCamera').val('');
    }
});

// Camera functionality
let cameraStream = null;
const video = document.getElementById('cameraStream');
const canvas = document.getElementById('photoCanvas');
const cameraModal = new bootstrap.Modal(document.getElementById('cameraModal'));

// Open camera when button clicked
$('#openCameraBtn').on('click', function() {
    cameraModal.show();
    startCamera();
});

// Start camera
async function startCamera() {
    try {
        // Hide error, show camera view
        $('#cameraError').hide();
        $('#cameraView').show();
        $('#capturedView').hide();

        // Request camera access
        const constraints = {
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user' // Use front camera
            },
            audio: false
        };

        cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = cameraStream;
    } catch (error) {
        console.error('Camera error:', error);
        $('#cameraView').hide();
        $('#cameraError').show();
    }
}

// Stop camera
function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
        video.srcObject = null;
    }
}

// Capture photo
$('#captureBtn').on('click', function() {
    const context = canvas.getContext('2d');

    // Set canvas size to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    // Draw video frame to canvas
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Hide camera view, show captured photo
    $('#cameraView').hide();
    $('#capturedView').show();

    // Stop camera stream
    stopCamera();
});

// Retake photo
$('#retakeBtn').on('click', function() {
    $('#capturedView').hide();
    $('#cameraView').show();
    startCamera();
});

// Use captured photo
$('#usePhotoBtn').on('click', function() {
    // Get image data from canvas
    canvas.toBlob(function(blob) {
        // Convert blob to File object
        const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });

        // Create a data transfer object to set files
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);

        // Set the file input's files
        document.getElementById('photo').files = dataTransfer.files;

        // Update preview image
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#photoPreview').attr('src', e.target.result);
        }
        reader.readAsDataURL(blob);

        // Set flag that photo is from camera
        $('#photoFromCamera').val('1');

        // Close modal
        cameraModal.hide();
    }, 'image/jpeg', 0.9);
});

// Stop camera when modal is closed
$('#cameraModal').on('hidden.bs.modal', function() {
    stopCamera();
});

// Form validation
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
";

// Include footer
include '../../includes/footer.php';
?>
