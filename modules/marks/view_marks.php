<?php
/**
 * View Marks
 * View and search student marks
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('marks', 'view');

$pageTitle = 'View Marks';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Get filter parameters
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Get all data for dropdowns
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");
$exams = fetchAll("SELECT * FROM exams WHERE is_active = 1 ORDER BY exam_date DESC");
$subjects = fetchAll("SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name");

// Get marks data if filters are applied
$marksData = [];
$class = null;
$section = null;
$exam = null;
$subject = null;
$selectedStudent = null;
$studentSearch = trim((string) ($_GET['student_search'] ?? ''));
$studentId = intval($_GET['student_id'] ?? 0);
$studentWiseMode = $studentId > 0 && $examId > 0;
$studentMarksData = [];
$studentTotalMarks = 0;
$studentTotalMax = 0;
$studentTotalCount = 0;

if ($studentWiseMode) {
    $studentQuery = "SELECT s.*, c.class_name, sec.section_name
                     FROM students s
                     LEFT JOIN classes c ON s.class_id = c.class_id
                     LEFT JOIN sections sec ON s.section_id = sec.section_id
                     WHERE s.student_id = ?";
    $studentParams = [$studentId];
    $studentTypes = 'i';
    if ($currentSchoolId > 0) {
        $studentQuery .= " AND s.school_id = ?";
        $studentParams[] = $currentSchoolId;
        $studentTypes .= 'i';
    }
    $selectedStudent = fetchOne($studentQuery, $studentTypes, $studentParams);

    if ($selectedStudent) {
        $classId = intval($selectedStudent['class_id'] ?? $classId);
        $sectionId = intval($selectedStudent['section_id'] ?? $sectionId);
        $class = fetchOne("SELECT * FROM classes WHERE class_id = ?", 'i', [$classId]);
        $section = fetchOne("SELECT * FROM sections WHERE section_id = ?", 'i', [$sectionId]);
        $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]);

        if ($subjectId > 0) {
            $subject = fetchOne("SELECT * FROM subjects WHERE subject_id = ?", 'i', [$subjectId]);
        $studentQuery = "SELECT s.student_id, s.student_name, s.roll_no, s.admission_no,
                                sub.subject_id, sub.subject_name, sub.subject_code,
                                m.marks_obtained, m.max_marks, m.grade, m.remarks,
                                m.created_at, m.updated_at,
                                u.full_name as entered_by
                         FROM students s
                         JOIN subjects sub ON sub.subject_id = ?
                         LEFT JOIN marks m ON s.student_id = m.student_id
                             AND m.exam_id = ? AND m.subject_id = ?
                         LEFT JOIN users u ON m.created_by = u.user_id
                         WHERE s.student_id = ?";
        $studentParams = [$subjectId, $examId, $subjectId, $studentId];
        $studentTypes = 'iiii';
        if ($currentSchoolId > 0) {
            $studentQuery .= " AND s.school_id = ?";
            $studentParams[] = $currentSchoolId;
            $studentTypes .= 'i';
        }
        $studentQuery .= " ORDER BY s.roll_no, s.student_name";
        $studentMarksData = fetchAll($studentQuery, $studentTypes, $studentParams);
        } else {
            $studentQuery = "SELECT sub.subject_id, sub.subject_name, sub.subject_code, sub.max_marks as subject_max_marks,
                                    m.marks_obtained, m.max_marks, m.grade, m.remarks,
                                    m.created_at, m.updated_at,
                                    u.full_name as entered_by
                             FROM subjects sub
                             LEFT JOIN marks m ON sub.subject_id = m.subject_id
                                 AND m.student_id = ? AND m.exam_id = ?
                             LEFT JOIN users u ON m.created_by = u.user_id
                             WHERE sub.is_active = 1";
            $studentParams = [$studentId, $examId];
            $studentTypes = 'ii';
            $studentQuery .= " ORDER BY
                                CASE
                                    WHEN sub.subject_code REGEXP '^[0-9]+$' THEN 0
                                    ELSE 1
                                END,
                                CAST(NULLIF(sub.subject_code, '') AS UNSIGNED),
                                sub.subject_name";
            $studentMarksData = fetchAll($studentQuery, $studentTypes, $studentParams);
        }

        foreach ($studentMarksData as $row) {
            if ($row['marks_obtained'] !== null && $row['marks_obtained'] !== '') {
                $studentTotalMarks += floatval($row['marks_obtained']);
                $studentTotalMax += floatval($row['max_marks'] ?? $row['subject_max_marks'] ?? 0);
                $studentTotalCount++;
            }
        }
    }
}

if (!$studentWiseMode && $classId > 0 && $sectionId > 0 && $examId > 0) {
    // Get class, section, and exam details
    $class = fetchOne("SELECT * FROM classes WHERE class_id = ?", 'i', [$classId]);
    $section = fetchOne("SELECT * FROM sections WHERE section_id = ?", 'i', [$sectionId]);
    $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]);

    if ($subjectId > 0) {
        // View marks for specific subject
        $subject = fetchOne("SELECT * FROM subjects WHERE subject_id = ?", 'i', [$subjectId]);

        $query = "SELECT s.student_id, s.student_name, s.roll_no, s.admission_no,
                         m.marks_obtained, m.max_marks, m.grade, m.remarks,
                         m.created_at, m.updated_at,
                         u.full_name as entered_by
                  FROM students s
                  LEFT JOIN marks m ON s.student_id = m.student_id
                      AND m.exam_id = ?
                      AND m.subject_id = ?
                  LEFT JOIN users u ON m.created_by = u.user_id
                  WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'Active'";
        $queryParams = [$examId, $subjectId, $classId, $sectionId];
        $queryTypes = 'iiii';
        if ($currentSchoolId > 0) {
            $query .= " AND s.school_id = ?";
            $queryParams[] = $currentSchoolId;
            $queryTypes .= 'i';
        }
        $query .= " ORDER BY s.roll_no, s.student_name";

        $marksData = fetchAll($query, $queryTypes, $queryParams);
    } else {
        // View all subjects for the exam
        $query = "SELECT s.student_id, s.student_name, s.roll_no, s.admission_no,
                         sub.subject_id, sub.subject_name,
                         m.marks_obtained, m.max_marks, m.grade
                  FROM students s
                  CROSS JOIN subjects sub
                  LEFT JOIN marks m ON s.student_id = m.student_id
                      AND m.exam_id = ?
                      AND m.subject_id = sub.subject_id
                  WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'Active'
                    AND sub.is_active = 1";
        $queryParams = [$examId, $classId, $sectionId];
        $queryTypes = 'iii';
        if ($currentSchoolId > 0) {
            $query .= " AND s.school_id = ?";
            $queryParams[] = $currentSchoolId;
            $queryTypes .= 'i';
        }
        $query .= " ORDER BY s.roll_no, s.student_name, sub.subject_name";

        $rawData = fetchAll($query, $queryTypes, $queryParams);

        // Organize data by student
        $organized = [];
        foreach ($rawData as $row) {
            $studentId = $row['student_id'];
            if (!isset($organized[$studentId])) {
                $organized[$studentId] = [
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'roll_no' => $row['roll_no'],
                    'admission_no' => $row['admission_no'],
                    'subjects' => [],
                    'total_marks' => 0,
                    'total_max' => 0
                ];
            }

            $organized[$studentId]['subjects'][$row['subject_id']] = [
                'subject_name' => $row['subject_name'],
                'marks_obtained' => $row['marks_obtained'],
                'max_marks' => $row['max_marks'],
                'grade' => $row['grade']
            ];

            if ($row['marks_obtained'] !== null) {
                $organized[$studentId]['total_marks'] += floatval($row['marks_obtained']);
                $organized[$studentId]['total_max'] += floatval($row['max_marks']);
            }
        }

        $marksData = $organized;
    }
}

// Include header
include '../../includes/header.php';
?>

<style>
.marks-view-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}

.marks-view-toolbar .btn {
    white-space: nowrap;
}

.marks-view-card {
    height: 100%;
}

.marks-view-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.marks-view-table th,
.marks-view-table td {
    vertical-align: middle;
}

@media (max-width: 575.98px) {
    .marks-view-toolbar .btn {
        flex: 1 1 100%;
    }
}
</style>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
            <h2 class="mb-0">
                <i class="bi bi-eye"></i> View Marks
            </h2>
            <div class="marks-view-toolbar">
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-pencil-square"></i> Enter Marks
                </a>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Student-wise Search -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card marks-view-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Student-wise View</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="studentWiseMarksForm">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label">Student Search <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   name="student_search"
                                   id="studentSearch"
                                   value="<?php echo htmlspecialchars($studentSearch); ?>"
                                   placeholder="Type 2 letters to search student"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="student_name"
                                   data-student-autocomplete-min-length="2"
                                   data-student-autocomplete-id-target="#studentId">
                            <input type="hidden" name="student_id" id="studentId" value="<?php echo $studentId > 0 ? intval($studentId) : ''; ?>">
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $e): ?>
                                    <option value="<?php echo $e['exam_id']; ?>"
                                        <?php echo ($examId == $e['exam_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['exam_name']); ?>
                                        (<?php echo date('M Y', strtotime($e['exam_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label">Subject (Optional)</label>
                            <select class="form-select" name="subject_id">
                                <option value="">-- All Subjects --</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo $sub['subject_id']; ?>"
                                        <?php echo ($subjectId == $sub['subject_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sub['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-eye"></i> View Student Marks
                        </button>
                        <a href="view_marks.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card marks-view-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Marks</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['class_id']; ?>"
                                        <?php echo ($classId == $c['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label">Section <span class="text-danger">*</span></label>
                            <select class="form-select" name="section_id" required>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['section_id']; ?>"
                                        <?php echo ($sectionId == $sec['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sec['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $e): ?>
                                    <option value="<?php echo $e['exam_id']; ?>"
                                        <?php echo ($examId == $e['exam_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['exam_name']); ?>
                                        (<?php echo date('M Y', strtotime($e['exam_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label">Subject (Optional)</label>
                            <select class="form-select" name="subject_id">
                                <option value="">-- All Subjects --</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo $sub['subject_id']; ?>"
                                        <?php echo ($subjectId == $sub['subject_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sub['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-search"></i> View Marks
                        </button>
                        <a href="view_marks.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($studentWiseMode && $selectedStudent): ?>
<!-- Student Wise Marks -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card marks-view-card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-person-check"></i>
                    Student Marks - <?php echo htmlspecialchars($selectedStudent['student_name']); ?>
                </h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="view_marksheet.php?student_id=<?php echo intval($selectedStudent['student_id']); ?>&exam_id=<?php echo intval($examId); ?>"
                       class="btn btn-light btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> View Marksheet
                    </a>
                    <button onclick="window.print()" class="btn btn-light btn-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 bg-light">
                            <small class="text-muted d-block">Admission No</small>
                            <strong><?php echo htmlspecialchars($selectedStudent['admission_no'] ?? '-'); ?></strong>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 bg-light">
                            <small class="text-muted d-block">Class</small>
                            <strong><?php echo htmlspecialchars(($selectedStudent['class_name'] ?? '-') . ' ' . ($selectedStudent['section_name'] ?? '')); ?></strong>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 bg-light">
                            <small class="text-muted d-block">Roll No</small>
                            <strong><?php echo htmlspecialchars($selectedStudent['roll_no'] ?? '-'); ?></strong>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 bg-light">
                            <small class="text-muted d-block">Exam</small>
                            <strong><?php echo htmlspecialchars($exam['exam_name'] ?? '-'); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm align-middle marks-view-table">
                        <thead class="table-dark">
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Marks Obtained</th>
                                <th>Max Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                                <th>Entered By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentMarksData as $row):
                                $rowMax = floatval($row['max_marks'] ?? $row['subject_max_marks'] ?? 0);
                                $rowMarks = $row['marks_obtained'];
                                $rowPercentage = ($rowMarks !== null && $rowMax > 0)
                                    ? round(($rowMarks / $rowMax) * 100, 2)
                                    : null;
                                $rowGrade = $rowPercentage !== null ? calculateGrade($rowPercentage) : '-';
                                $rowGradeClass = $rowPercentage !== null ? getGradeColorClass($rowGrade) : 'text-secondary';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['subject_code'] ?? '-'); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['subject_name'] ?? '-'); ?></strong></td>
                                <td class="text-center"><?php echo $rowMarks !== null ? htmlspecialchars($rowMarks) : '-'; ?></td>
                                <td class="text-center"><?php echo $rowMax > 0 ? htmlspecialchars($rowMax) : '-'; ?></td>
                                <td class="text-center"><?php echo $rowPercentage !== null ? $rowPercentage . '%' : '-'; ?></td>
                                <td class="text-center">
                                    <?php if ($rowPercentage !== null): ?>
                                        <span class="badge bg-light <?php echo $rowGradeClass; ?>"><?php echo htmlspecialchars($rowGrade); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></td>
                                <td><small><?php echo htmlspecialchars($row['entered_by'] ?? '-'); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-center"><?php echo number_format((float) $studentTotalMarks, 2); ?></th>
                                <th class="text-center"><?php echo number_format((float) $studentTotalMax, 2); ?></th>
                                <th class="text-center">
                                    <?php echo $studentTotalMax > 0 ? round(($studentTotalMarks / $studentTotalMax) * 100, 2) . '%' : '-'; ?>
                                </th>
                                <th colspan="3" class="text-center">
                                    <?php
                                    if ($studentTotalMax > 0) {
                                        echo htmlspecialchars(calculateGrade(($studentTotalMarks / $studentTotalMax) * 100));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($studentWiseMode): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No student record found for the selected search.
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($classId > 0 && $sectionId > 0 && $examId > 0 && count($marksData) > 0): ?>
<!-- Marks Display -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card marks-view-card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-table"></i>
                    Marks - <?php echo htmlspecialchars($class['class_name']); ?>
                    <?php echo htmlspecialchars($section['section_name']); ?> -
                    <?php echo htmlspecialchars($exam['exam_name']); ?>
                    <?php if ($subject): ?>
                        - <?php echo htmlspecialchars($subject['subject_name']); ?>
                    <?php endif; ?>
                </h5>
                <div>
                    <button onclick="window.print()" class="btn btn-light btn-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if ($subjectId > 0): ?>
                        <!-- Single Subject View -->
                        <table class="table table-bordered table-hover table-sm align-middle marks-view-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Roll No</th>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Marks Obtained</th>
                                    <th>Max Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                    <th>Entered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalObtained = 0;
                                $totalMax = 0;
                                $count = 0;
                                foreach ($marksData as $row):
                                    if ($row['marks_obtained'] !== null) {
                                        $totalObtained += floatval($row['marks_obtained']);
                                        $totalMax += floatval($row['max_marks']);
                                        $count++;
                                    }
                                    $percentage = ($row['marks_obtained'] !== null && $row['max_marks'] > 0)
                                        ? round(($row['marks_obtained'] / $row['max_marks']) * 100, 2)
                                        : null;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['roll_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['admission_no']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
                                    <td class="text-center">
                                        <?php echo $row['marks_obtained'] !== null ? $row['marks_obtained'] : '-'; ?>
                                    </td>
                                    <td class="text-center"><?php echo $row['max_marks'] ?? '-'; ?></td>
                                    <td class="text-center">
                                        <?php echo $percentage !== null ? $percentage . '%' : '-'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['grade']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($row['grade']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></td>
                                    <td><small><?php echo htmlspecialchars($row['entered_by'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Class Average:</th>
                                    <th class="text-center">
                                        <?php echo $count > 0 ? round($totalObtained / $count, 2) : '-'; ?>
                                    </th>
                                    <th class="text-center">
                                        <?php echo $count > 0 ? round($totalMax / $count, 2) : '-'; ?>
                                    </th>
                                    <th class="text-center">
                                        <?php echo ($totalMax > 0) ? round(($totalObtained / $totalMax) * 100, 2) . '%' : '-'; ?>
                                    </th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <!-- All Subjects View -->
                        <table class="table table-bordered table-hover table-sm align-middle marks-view-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <?php
                                    // Get unique subjects
                                    $allSubjects = [];
                                    foreach ($marksData as $student) {
                                        foreach ($student['subjects'] as $subId => $subData) {
                                            if (!isset($allSubjects[$subId])) {
                                                $allSubjects[$subId] = $subData['subject_name'];
                                            }
                                        }
                                    }
                                    foreach ($allSubjects as $subName):
                                    ?>
                                        <th class="text-center"><?php echo htmlspecialchars($subName); ?></th>
                                    <?php endforeach; ?>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marksData as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                                    <?php foreach ($allSubjects as $subId => $subName): ?>
                                        <td class="text-center">
                                            <?php
                                            if (isset($student['subjects'][$subId])) {
                                                $marks = $student['subjects'][$subId]['marks_obtained'];
                                                $max = $student['subjects'][$subId]['max_marks'];
                                                echo $marks !== null ? $marks . '/' . $max : '-';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center">
                                        <strong>
                                            <?php echo $student['total_marks'] . '/' . $student['total_max']; ?>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <strong>
                                            <?php
                                            echo $student['total_max'] > 0
                                                ? round(($student['total_marks'] / $student['total_max']) * 100, 2) . '%'
                                                : '-';
                                            ?>
                                        </strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif (!$studentWiseMode && $classId > 0 && $sectionId > 0 && $examId > 0): ?>
<!-- No Data Message -->
<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>No marks found!</strong> No marks have been entered for the selected criteria.
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include '../../includes/footer.php';
?>
