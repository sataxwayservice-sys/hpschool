<?php
/**
 * Enter Marks for Students
 * Individual mark entry page
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('marks', 'add');

$pageTitle = 'Enter Marks';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Check if required tables exist
$tablesExist = true;
$missingTables = [];

$tableCheck = fetchAll("SHOW TABLES LIKE 'exams'");
if (count($tableCheck) == 0) {
    $tablesExist = false;
    $missingTables[] = 'exams';
}

$tableCheck = fetchAll("SHOW TABLES LIKE 'subjects'");
if (count($tableCheck) == 0) {
    $tablesExist = false;
    $missingTables[] = 'subjects';
}

$tableCheck = fetchAll("SHOW TABLES LIKE 'marks'");
if (count($tableCheck) == 0) {
    $tablesExist = false;
    $missingTables[] = 'marks';
}

if (!$tablesExist) {
    $_SESSION['error_message'] = "Missing required tables: " . implode(', ', $missingTables) . ". Please visit test_exams_table.php to create them.";
    header("Location: index.php");
    exit();
}

// Get parameters
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$studentWiseMode = $studentId > 0;
$student = null;

if ($studentWiseMode) {
    $studentQuery = "SELECT s.*, c.class_name, sec.section_name
                     FROM students s
                     LEFT JOIN classes c ON s.class_id = c.class_id
                     LEFT JOIN sections sec ON s.section_id = sec.section_id
                     WHERE s.student_id = ? AND s.status = 'Active'";
    $studentParams = [$studentId];
    $studentTypes = 'i';
    if ($currentSchoolId > 0) {
        $studentQuery .= " AND s.school_id = ?";
        $studentParams[] = $currentSchoolId;
        $studentTypes .= 'i';
    }
    $student = fetchOne($studentQuery, $studentTypes, $studentParams);

    if (!$student) {
        $_SESSION['error_message'] = "Selected student was not found or is not active.";
        header("Location: index.php");
        exit();
    }

    $classId = intval($student['class_id'] ?? 0);
    $sectionId = intval($student['section_id'] ?? 0);
}

// Validate parameters
if ($examId == 0 || $subjectId == 0 || (!$studentWiseMode && ($classId == 0 || $sectionId == 0))) {
    $_SESSION['error_message'] = "Invalid parameters. Please select all required fields.";
    header("Location: index.php");
    exit();
}

// Get class, section, exam, and subject details
$class = fetchOne("SELECT * FROM classes WHERE class_id = ?", 'i', [$classId]);
$section = fetchOne("SELECT * FROM sections WHERE section_id = ?", 'i', [$sectionId]);
$exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]);
$subject = fetchOne("SELECT * FROM subjects WHERE subject_id = ?", 'i', [$subjectId]);

if (!$class || !$section || !$exam || !$subject) {
    $_SESSION['error_message'] = "Invalid selection. Please try again.";
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $marksObtained = $_POST['marks_obtained'] ?? [];
    $remarks = $_POST['remarks'] ?? [];

    beginTransaction();

    try {
        foreach ($marksObtained as $studentId => $marks) {
            $studentId = intval($studentId);
            $marks = floatval($marks);
            $studentRemarks = sanitize($remarks[$studentId] ?? '');

            // Validate marks
            if ($marks < 0 || $marks > $subject['max_marks']) {
                throw new Exception("Invalid marks for student ID $studentId");
            }

            // Check if marks already exist
            $existingMark = fetchOne("SELECT mark_id FROM marks
                                     WHERE student_id = ? AND exam_id = ? AND subject_id = ?",
                                     'iii', [$studentId, $examId, $subjectId]);

            if ($existingMark) {
                // Update existing marks
                $updateQuery = "UPDATE marks
                               SET marks_obtained = ?, remarks = ?, max_marks = ?, updated_at = NOW()
                               WHERE mark_id = ?";
                executeQuery($updateQuery, 'dsdi', [$marks, $studentRemarks, $subject['max_marks'], $existingMark['mark_id']]);
            } else {
                // Insert new marks
                $insertQuery = "INSERT INTO marks (student_id, exam_id, subject_id, marks_obtained, max_marks, remarks, created_by, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                executeQuery($insertQuery, 'iiiddsi', [$studentId, $examId, $subjectId, $marks, $subject['max_marks'], $studentRemarks, $currentUser['user_id']]);
            }
        }

        commitTransaction();

        // Log activity
        $activityTarget = $studentWiseMode
            ? ($student['student_name'] ?? 'Student')
            : ($class['class_name'] . ' ' . $section['section_name']);
        logActivity($currentUser['user_id'], 'Marks Entry', 'marks', "Entered marks for {$activityTarget} - {$exam['exam_name']} - {$subject['subject_name']}");

        $_SESSION['success_message'] = "Marks saved successfully!";
        $redirectUrl = "enter_marks.php?exam_id=$examId&subject_id=$subjectId";
        if ($studentWiseMode) {
            $redirectUrl .= "&student_id=$studentId";
        } else {
            $redirectUrl .= "&class_id=$classId&section_id=$sectionId";
        }
        header("Location: $redirectUrl");
        exit();

    } catch (Exception $e) {
        rollbackTransaction();
        $conn = getDbConnection();
        $mysqlError = $conn->error;
        $_SESSION['error_message'] = "Error saving marks: " . $e->getMessage() . ($mysqlError ? " | MySQL Error: " . htmlspecialchars($mysqlError) : "");
    }
}

// Get students in the selected class/section or a single student
if ($studentWiseMode) {
    $studentQuery = "SELECT s.*, COALESCE(m.marks_obtained, '') as existing_marks, COALESCE(m.remarks, '') as existing_remarks
                      FROM students s
                      LEFT JOIN marks m ON s.student_id = m.student_id
                         AND m.exam_id = ? AND m.subject_id = ?
                      WHERE s.student_id = ? AND s.status = 'Active'";
    $studentParams = [$examId, $subjectId, $studentId];
    $studentTypes = 'iii';
    if ($currentSchoolId > 0) {
        $studentQuery .= " AND s.school_id = ?";
        $studentParams[] = $currentSchoolId;
        $studentTypes .= 'i';
    }
    $studentQuery .= " ORDER BY s.roll_no";
    $students = fetchAll($studentQuery, $studentTypes, $studentParams);
} else {
    $studentQuery = "SELECT s.*, COALESCE(m.marks_obtained, '') as existing_marks, COALESCE(m.remarks, '') as existing_remarks
                      FROM students s
                      LEFT JOIN marks m ON s.student_id = m.student_id
                         AND m.exam_id = ? AND m.subject_id = ?
                      WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'Active'";
    $studentParams = [$examId, $subjectId, $classId, $sectionId];
    $studentTypes = 'iiii';
    if ($currentSchoolId > 0) {
        $studentQuery .= " AND s.school_id = ?";
        $studentParams[] = $currentSchoolId;
        $studentTypes .= 'i';
    }
    $studentQuery .= " ORDER BY s.roll_no";
    $students = fetchAll($studentQuery, $studentTypes, $studentParams);
}

if (count($students) == 0) {
    $_SESSION['error_message'] = $studentWiseMode
        ? "No active marks entry found for the selected student."
        : "No active students found in the selected class and section.";
    header("Location: index.php");
    exit();
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-pencil-square"></i> Enter Marks
            </h2>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php
        echo $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Details Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Class:</strong> <?php echo htmlspecialchars($class['class_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Section:</strong> <?php echo htmlspecialchars($section['section_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Exam:</strong> <?php echo htmlspecialchars($exam['exam_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Subject:</strong> <?php echo htmlspecialchars($subject['subject_name']); ?>
                        (Max: <?php echo $subject['max_marks']; ?>)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($studentWiseMode): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-success">
            <i class="bi bi-person-badge"></i>
            <strong>Individual Student Mode:</strong>
            <?php echo htmlspecialchars($student['student_name'] ?? 'Selected Student'); ?>
            | Admission No: <?php echo htmlspecialchars($student['admission_no'] ?? '-'); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Student Search -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-search"></i> Student Search</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Search Student</label>
                        <input type="text"
                               class="form-control form-control-sm"
                               id="marksStudentSearch"
                               placeholder="Type student name, admission no, or roll no"
                               autocomplete="off"
                               data-student-autocomplete="true"
                               data-student-autocomplete-fill="student_name"
                               data-student-autocomplete-min-length="2">
                        <small class="text-muted d-block mt-2">Use this to quickly locate a student row in the table below.</small>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary btn-sm w-100" id="marksStudentSearchBtn">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="marksStudentClearBtn">
                            <i class="bi bi-arrow-clockwise"></i> Clear
                        </button>
                    </div>
                </div>
                <div id="marksStudentNoResults" class="alert alert-warning mt-3 d-none mb-0">
                    No matching student found.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Marks Entry Form -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-check"></i> Enter Marks for Students
                    (Total: <?php echo count($students); ?>)
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="marksForm">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th width="8%">Roll No</th>
                                    <th width="10%">Admission No</th>
                                    <th width="25%">Student Name</th>
                                    <th width="12%">Marks Obtained <span class="text-danger">*</span></th>
                                    <th width="10%">Grade</th>
                                    <th width="10%">Percentage</th>
                                    <th width="25%">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr class="marks-student-row">
                                    <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td>
                                        <input type="number"
                                               class="form-control marks-input"
                                               name="marks_obtained[<?php echo $student['student_id']; ?>]"
                                               value="<?php echo htmlspecialchars($student['existing_marks']); ?>"
                                               min="0"
                                               max="<?php echo $subject['max_marks']; ?>"
                                               step="0.5"
                                               required
                                               data-max="<?php echo $subject['max_marks']; ?>"
                                               data-student-id="<?php echo $student['student_id']; ?>">
                                    </td>
                                    <td>
                                        <span class="grade-display" id="grade_<?php echo $student['student_id']; ?>">
                                            <?php
                                            if (!empty($student['existing_marks'])) {
                                                $percentage = ($student['existing_marks'] / $subject['max_marks']) * 100;
                                                echo calculateGrade($percentage);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="percentage-display" id="percentage_<?php echo $student['student_id']; ?>">
                                            <?php
                                            if (!empty($student['existing_marks'])) {
                                                $percentage = ($student['existing_marks'] / $subject['max_marks']) * 100;
                                                echo number_format($percentage, 2) . '%';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="form-control"
                                               name="remarks[<?php echo $student['student_id']; ?>]"
                                               value="<?php echo htmlspecialchars($student['existing_remarks']); ?>"
                                               placeholder="Optional">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0">
                            <li>Enter marks obtained by each student (0 to <?php echo $subject['max_marks']; ?>)</li>
                            <li>Grade and percentage will be calculated automatically</li>
                            <li>You can use decimal marks (e.g., 47.5)</li>
                            <li>Remarks are optional</li>
                        </ul>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" name="save_marks" class="btn btn-success btn-lg">
                            <i class="bi bi-save"></i> Save All Marks
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
const maxMarks = " . $subject['max_marks'] . ";

// Grade calculation function
function calculateGrade(percentage) {
    if (percentage >= 90) return 'A+';
    if (percentage >= 80) return 'A';
    if (percentage >= 70) return 'B+';
    if (percentage >= 60) return 'B';
    if (percentage >= 50) return 'C+';
    if (percentage >= 40) return 'C';
    if (percentage >= 33) return 'D';
    return 'F';
}

// Update grade and percentage on marks input
$('.marks-input').on('input', function() {
    const studentId = $(this).data('student-id');
    const marks = parseFloat($(this).val()) || 0;
    const max = parseFloat($(this).data('max'));

    // Validate marks
    if (marks > max) {
        $(this).val(max);
        alert('Marks cannot exceed maximum marks of ' + max);
        return;
    }

    if (marks < 0) {
        $(this).val(0);
        return;
    }

    // Calculate percentage and grade
    const percentage = (marks / max) * 100;
    const grade = calculateGrade(percentage);

    // Update display
    $('#percentage_' + studentId).text(percentage.toFixed(2) + '%');
    $('#grade_' + studentId).text(grade);

    // Color code grade
    $('#grade_' + studentId).removeClass('text-success text-warning text-danger');
    if (grade === 'A+' || grade === 'A') {
        $('#grade_' + studentId).addClass('text-success fw-bold');
    } else if (grade === 'F') {
        $('#grade_' + studentId).addClass('text-danger fw-bold');
    } else {
        $('#grade_' + studentId).addClass('text-warning fw-bold');
    }
});

// Form validation
$('#marksForm').on('submit', function(e) {
    let valid = true;
    $('.marks-input').each(function() {
        const marks = parseFloat($(this).val());
        const max = parseFloat($(this).data('max'));

        if (isNaN(marks) || marks < 0 || marks > max) {
            valid = false;
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    if (!valid) {
        e.preventDefault();
        alert('Please enter valid marks for all students.');
        return false;
    }

    return confirm('Are you sure you want to save these marks?');
});

function filterMarksStudents() {
    const searchTerm = String($('#marksStudentSearch').val() || '').trim().toLowerCase();
    let visibleCount = 0;
    let firstMatch = null;

    $('.marks-student-row').each(function() {
        const rowText = $(this).text().toLowerCase();
        const isMatch = !searchTerm || rowText.indexOf(searchTerm) !== -1;
        $(this).toggle(isMatch);
        if (isMatch) {
            if (!firstMatch) {
                firstMatch = this;
            }
            visibleCount++;
        }
    });

    $('#marksStudentNoResults').toggleClass('d-none', visibleCount !== 0);
    if (firstMatch) {
        firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

$('#marksStudentSearchBtn').on('click', function() {
    filterMarksStudents();
});

$('#marksStudentClearBtn').on('click', function() {
    $('#marksStudentSearch').val('');
    filterMarksStudents();
});

$('#marksStudentSearch').on('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        filterMarksStudents();
    }
});
";

// Include footer
include '../../includes/footer.php';
?>
