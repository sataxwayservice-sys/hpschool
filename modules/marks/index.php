<?php
/**
 * Marks Entry Module
 * Teachers can enter marks for students
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('marks', 'add');

$pageTitle = 'Marks Entry';
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

// Get all data for dropdowns
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");
$exams = $tablesExist ? fetchAll("SELECT * FROM exams WHERE is_active = 1 ORDER BY exam_date DESC") : [];
$subjects = $tablesExist ? fetchAll("SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name") : [];

// Include header
include '../../includes/header.php';
?>

<style>
.marks-page-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}

.marks-page-toolbar .btn {
    white-space: nowrap;
}

.marks-selection-card {
    height: 100%;
}

.marks-selection-card .card-body {
    display: flex;
    flex-direction: column;
}

@media (max-width: 575.98px) {
    .marks-page-toolbar .btn {
        flex: 1 1 100%;
    }
}
</style>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
            <h2 class="mb-0">
                <i class="bi bi-pencil-square"></i> Marks Entry
            </h2>
            <div class="marks-page-toolbar">
                <a href="manage_exams.php" class="btn btn-primary">
                    <i class="bi bi-calendar-check"></i> Exams
                </a>
                <a href="manage_subjects.php" class="btn btn-info">
                    <i class="bi bi-book"></i> Subjects
                </a>
                <a href="exam_routine.php" class="btn btn-warning">
                    <i class="bi bi-calendar2-week"></i> Routine
                </a>
                <a href="view_marks.php" class="btn btn-info">
                    <i class="bi bi-eye"></i> View Marks
                </a>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
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

<?php if (!$tablesExist): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger">
            <h5><i class="bi bi-exclamation-triangle-fill"></i> Missing Required Tables!</h5>
            <p>The following tables are missing from your database:</p>
            <ul>
                <?php foreach ($missingTables as $table): ?>
                    <li><strong><?php echo htmlspecialchars($table); ?></strong></li>
                <?php endforeach; ?>
            </ul>
            <p class="mb-0">
                <strong>Action Required:</strong> Please visit
                <a href="../../test_exams_table.php" class="btn btn-sm btn-warning" target="_blank">
                    <i class="bi bi-tools"></i> Diagnostic Tool
                </a>
                to create these tables.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (count($exams) == 0 && $tablesExist): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-circle"></i> No Exams Found!</h5>
            <p>You need to add exams before you can enter marks.</p>
            <p class="mb-0">
                <a href="manage_exams.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Exams
                </a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (count($subjects) == 0 && $tablesExist): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-circle"></i> No Subjects Found!</h5>
            <p>You need to add subjects before you can enter marks.</p>
            <p class="mb-0">
                <a href="manage_subjects.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Subjects
                </a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Selection Forms -->
<div class="row g-4 align-items-stretch">
    <div class="col-12 col-lg-6">
        <div class="card dashboard-card marks-selection-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Select Criteria</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="enter_marks.php" id="selectionForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" id="classId" required <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Section <span class="text-danger">*</span></label>
                            <select class="form-select" name="section_id" id="sectionId" required <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" id="examId" required <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['exam_id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name']); ?>
                                        (<?php echo date('M Y', strtotime($exam['exam_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="manage_exams.php" class="btn btn-link btn-sm p-0">+ Add New Exam</a>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject_id" id="subjectId" required <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>"
                                            data-max-marks="<?php echo $subject['max_marks']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        (Max: <?php echo $subject['max_marks']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="manage_subjects.php" class="btn btn-link btn-sm p-0">+ Add New Subject</a>
                        </div>
                    </div>

                    <?php if (!$tablesExist): ?>
                        <div class="alert alert-danger mt-3">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Cannot proceed:</strong> Required database tables are missing. Please create them first.
                        </div>
                    <?php elseif (count($exams) == 0): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-circle"></i>
                            <strong>Cannot proceed:</strong> No exams available. Please add an exam first.
                        </div>
                    <?php elseif (count($subjects) == 0): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-circle"></i>
                            <strong>Cannot proceed:</strong> No subjects available. Please add subjects first.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Note:</strong> Select class, section, exam, and subject to enter marks for students.
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-lg" <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                            <i class="bi bi-arrow-right-circle"></i> Proceed to Enter Marks
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card dashboard-card marks-selection-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Individual Student Entry</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="enter_marks.php" id="studentSelectionForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Student Search <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   name="student_search"
                                   id="studentSearch"
                                   placeholder="Type 2 letters to search student name"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="student_name"
                                   data-student-autocomplete-min-length="2"
                                   data-student-autocomplete-id-target="#studentId">
                            <input type="hidden" name="student_id" id="studentId" value="">
                            <small class="text-muted">Search the student first, then choose the exam and subject for marks entry.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" required <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['exam_id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name']); ?>
                                        (<?php echo date('M Y', strtotime($exam['exam_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject_id" required <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>" data-max-marks="<?php echo $subject['max_marks']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        (Max: <?php echo $subject['max_marks']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (!$tablesExist || count($exams) == 0 || count($subjects) == 0): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-circle"></i>
                            <strong>Cannot proceed:</strong> Add exams and subjects before entering individual student marks.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Note:</strong> This opens marks entry for one selected student only.
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-success btn-lg" <?php echo (!$tablesExist || count($exams) == 0 || count($subjects) == 0) ? 'disabled' : ''; ?>>
                            <i class="bi bi-arrow-right-circle"></i> Enter Individual Marks
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row g-3 mt-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h5>Total Classes</h5>
                <h3><?php echo count($classes); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Total Sections</h5>
                <h3><?php echo count($sections); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h5>Total Exams</h5>
                <h3><?php echo count($exams); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Total Subjects</h5>
                <h3><?php echo count($subjects); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Recent Marks Entry Activity -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Marks Entry Activity</h5>
            </div>
            <div class="card-body">
                <?php
                $recentActivityQuery = "SELECT DISTINCT
                                            c.class_name, sec.section_name,
                                            e.exam_name, sub.subject_name,
                                            m.created_at
                                           FROM marks m
                                           JOIN students s ON m.student_id = s.student_id
                                           JOIN classes c ON s.class_id = c.class_id
                                           JOIN sections sec ON s.section_id = sec.section_id
                                           JOIN exams e ON m.exam_id = e.exam_id
                                           JOIN subjects sub ON m.subject_id = sub.subject_id
                                           WHERE 1=1";
                if ($currentSchoolId > 0) {
                    $recentActivityQuery .= " AND s.school_id = ?";
                }
                $recentActivityQuery .= " ORDER BY m.created_at DESC LIMIT 10";
                $recentActivity = $currentSchoolId > 0
                    ? fetchAll($recentActivityQuery, 'i', [$currentSchoolId])
                    : fetchAll($recentActivityQuery);
                ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Exam</th>
                                <th>Subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentActivity) > 0): ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td><?php echo date('d-M-Y h:i A', strtotime($activity['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($activity['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['exam_name']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['subject_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No recent activity</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var studentSelectionForm = document.getElementById('studentSelectionForm');
    var studentIdField = document.getElementById('studentId');

    if (studentSelectionForm && studentIdField) {
        studentSelectionForm.addEventListener('submit', function(e) {
            if (!studentIdField.value.trim()) {
                e.preventDefault();
                alert('Please select a student from the search suggestions before proceeding.');
            }
        });
    }
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
