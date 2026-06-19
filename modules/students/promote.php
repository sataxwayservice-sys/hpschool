<?php
/**
 * Promote Students
 * Batch promote students from one class to another
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('students', 'edit');

$pageTitle = 'Promote Students';
$currentUser = getCurrentUser();

$success = '';
$error = '';

// Handle promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_students'])) {
    $fromClassId = intval($_POST['from_class_id']);
    $fromSectionId = intval($_POST['from_section_id']);
    $toClassId = intval($_POST['to_class_id']);
    $toSectionId = intval($_POST['to_section_id']);
    $studentIds = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];

    if (empty($studentIds)) {
        $error = 'Please select at least one student to promote';
    } elseif ($fromClassId == $toClassId && $fromSectionId == $toSectionId) {
        $error = 'Source and destination class/section cannot be the same';
    } else {
        $promotedCount = 0;
        $failedCount = 0;

        beginTransaction();
        try {
            foreach ($studentIds as $studentId) {
                $studentId = intval($studentId);

                // Update student class and section
                $query = "UPDATE students
                         SET class_id = ?, section_id = ?, roll_no = NULL
                         WHERE student_id = ?";

                if (executeQuery($query, 'iii', [$toClassId, $toSectionId, $studentId])) {
                    $promotedCount++;

                    // Log activity
                    $studentData = fetchOne("SELECT student_name FROM students WHERE student_id = ?", 'i', [$studentId]);
                    logActivity($currentUser['user_id'],
                               'Student Promoted',
                               'students',
                               "Promoted student: {$studentData['student_name']} (ID: $studentId)");
                } else {
                    $failedCount++;
                }
            }

            commitTransaction();

            if ($promotedCount > 0) {
                $success = "Successfully promoted $promotedCount student(s)!";
                if ($failedCount > 0) {
                    $success .= " ($failedCount failed)";
                }
            } else {
                $error = 'Failed to promote students';
            }

        } catch (Exception $e) {
            rollbackTransaction();
            $error = 'Error during promotion: ' . $e->getMessage();
        }
    }
}

// Get classes and sections
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-arrow-up-circle"></i> Promote Students
            </h2>
            <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Students
            </a>
        </div>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Instructions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> How to Promote Students</h5>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>Select the <strong>current class and section</strong> (where students are now)</li>
                    <li>Click <strong>"Load Students"</strong> to see all students in that class</li>
                    <li>Select students you want to promote (or click "Select All")</li>
                    <li>Choose the <strong>destination class and section</strong> (where to promote them)</li>
                    <li>Click <strong>"Promote Selected Students"</strong></li>
                </ol>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Note:</strong> Roll numbers will be cleared after promotion. You'll need to assign new roll numbers manually.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Promotion Form -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-1-circle"></i> Step 1: Select Current Class/Section</h5>
            </div>
            <div class="card-body">
                <form id="loadStudentsForm">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Current Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="from_class_id" id="fromClass" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Current Section <span class="text-danger">*</span></label>
                            <select class="form-select" name="from_section_id" id="fromSection" required>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-primary w-100" id="loadStudentsBtn">
                                <i class="bi bi-search"></i> Load Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Students List -->
<div class="row mt-4" id="studentsSection" style="display: none;">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-2-circle"></i> Step 2: Select Students to Promote</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">
                        <i class="bi bi-check-all"></i> Select All
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
                        <i class="bi bi-x-circle"></i> Deselect All
                    </button>
                    <span class="ms-3 badge bg-info" id="selectedCount">0 selected</span>
                </div>

                <div id="studentsListContainer">
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-arrow-up-circle" style="font-size: 48px;"></i>
                        <p>Click "Load Students" to see students</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Promotion Destination -->
<div class="row mt-4" id="destinationSection" style="display: none;">
    <div class="col-12">
        <form method="POST" action="" id="promotionForm">
            <div class="card dashboard-card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-3-circle"></i> Step 3: Select Destination Class/Section</h5>
                </div>
                <div class="card-body">
                    <input type="hidden" name="from_class_id" id="fromClassHidden">
                    <input type="hidden" name="from_section_id" id="fromSectionHidden">

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Promote To Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="to_class_id" id="toClass" required>
                                <option value="">-- Select Destination Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Promote To Section <span class="text-danger">*</span></label>
                            <select class="form-select" name="to_section_id" id="toSection" required>
                                <option value="">-- Select Destination Section --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="selectedStudentsContainer"></div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="promote_students" class="btn btn-success btn-lg" id="promoteBtn">
                            <i class="bi bi-arrow-up-circle-fill"></i> Promote Selected Students
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$inlineScript = "
let studentsData = [];

// Load students when button clicked
$('#loadStudentsBtn').on('click', function() {
    const fromClass = $('#fromClass').val();
    const fromSection = $('#fromSection').val();

    if (!fromClass || !fromSection) {
        alert('Please select both class and section');
        return;
    }

    // Show loading
    $('#studentsListContainer').html('<div class=\"text-center py-4\"><div class=\"spinner-border\" role=\"status\"></div><p>Loading students...</p></div>');

    // AJAX call to load students
    $.ajax({
        url: '" . APP_URL . "/ajax/get_students.php',
        method: 'POST',
        data: {
            class_id: fromClass,
            section_id: fromSection,
            status: 'Active'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.students && response.students.length > 0) {
                studentsData = response.students;
                renderStudentsList(response.students);
                $('#studentsSection').slideDown();
                $('#destinationSection').slideDown();
                $('#fromClassHidden').val(fromClass);
                $('#fromSectionHidden').val(fromSection);
            } else {
                let message = '<div class=\"alert alert-info\"><i class=\"bi bi-info-circle\"></i> No active students found in this class/section</div>';
                $('#studentsListContainer').html(message);
                $('#studentsSection').slideDown();
                $('#destinationSection').hide();
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = '<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-triangle\"></i> Error loading students. Please try again.</div>';
            $('#studentsListContainer').html(errorMsg);
            $('#studentsSection').slideDown();
        }
    });
});

function renderStudentsList(students) {
    let html = '<div class=\"table-responsive\"><table class=\"table table-hover\"><thead class=\"table-light\"><tr>';
    html += '<th width=\"50\"><input type=\"checkbox\" id=\"selectAllCheckbox\"></th>';
    html += '<th>Admission No</th><th>Student Name</th><th>Roll No</th><th>Gender</th><th>Father Name</th>';
    html += '</tr></thead><tbody>';

    students.forEach(student => {
        html += '<tr>';
        html += '<td><input type=\"checkbox\" class=\"student-checkbox\" value=\"' + student.student_id + '\" data-name=\"' + student.student_name + '\"></td>';
        html += '<td>' + student.admission_no + '</td>';
        html += '<td><strong>' + student.student_name + '</strong></td>';
        html += '<td>' + (student.roll_no || '-') + '</td>';
        html += '<td>' + student.gender + '</td>';
        html += '<td>' + student.father_name + '</td>';
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    $('#studentsListContainer').html(html);
    updateSelectedCount();
}

// Select all functionality
$('#selectAllBtn, #studentsListContainer').on('click', '#selectAllCheckbox', function() {
    $('.student-checkbox').prop('checked', this.checked || $(this).is('#selectAllBtn'));
    updateSelectedCount();
});

$('#selectAllBtn').on('click', function() {
    $('.student-checkbox').prop('checked', true);
    $('#selectAllCheckbox').prop('checked', true);
    updateSelectedCount();
});

$('#deselectAllBtn').on('click', function() {
    $('.student-checkbox').prop('checked', false);
    $('#selectAllCheckbox').prop('checked', false);
    updateSelectedCount();
});

// Update selected count
$(document).on('change', '.student-checkbox', function() {
    updateSelectedCount();
});

function updateSelectedCount() {
    const count = $('.student-checkbox:checked').length;
    $('#selectedCount').text(count + ' selected');

    // Update hidden inputs for selected students
    let hiddenInputs = '';
    $('.student-checkbox:checked').each(function() {
        hiddenInputs += '<input type=\"hidden\" name=\"student_ids[]\" value=\"' + $(this).val() + '\">';
    });
    $('#selectedStudentsContainer').html(hiddenInputs);

    // Show/hide promote button
    if (count > 0) {
        $('#promoteBtn').prop('disabled', false);
    } else {
        $('#promoteBtn').prop('disabled', true);
    }
}

// Form validation
$('#promotionForm').on('submit', function(e) {
    const selectedCount = $('.student-checkbox:checked').length;
    const toClass = $('#toClass').val();
    const toSection = $('#toSection').val();
    const fromClass = $('#fromClass').val();
    const fromSection = $('#fromSection').val();

    if (selectedCount === 0) {
        e.preventDefault();
        alert('Please select at least one student to promote');
        return false;
    }

    if (!toClass || !toSection) {
        e.preventDefault();
        alert('Please select destination class and section');
        return false;
    }

    if (fromClass === toClass && fromSection === toSection) {
        e.preventDefault();
        alert('Source and destination class/section cannot be the same');
        return false;
    }

    const fromClassName = $('#fromClass option:selected').text();
    const toClassName = $('#toClass option:selected').text();
    const fromSectionName = $('#fromSection option:selected').text();
    const toSectionName = $('#toSection option:selected').text();

    const confirmMsg = 'Are you sure you want to promote ' + selectedCount + ' student(s) from\\n' +
                      fromClassName + ' - ' + fromSectionName + ' to ' + toClassName + ' - ' + toSectionName + '?';

    if (!confirm(confirmMsg)) {
        e.preventDefault();
        return false;
    }

    return true;
});
";

// Include footer
include '../../includes/footer.php';
?>
