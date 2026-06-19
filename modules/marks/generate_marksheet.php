<?php
/**
 * Generate Mark Sheet
 * PDF mark sheet generation for students
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('marks', 'view');

$pageTitle = 'Generate Mark Sheet';
$currentUser = getCurrentUser();

// Get parameters for PDF generation
if (isset($_GET['generate']) && $_GET['generate'] == 'pdf') {
    $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

    if ($studentId > 0 && $examId > 0) {
        require_once '../../includes/marksheet_pdf.php';
        require_once '../../includes/pdf_export.php';

        $sheetData = getMarkSheetData($studentId, $examId);
        if (!$sheetData) {
            die('No marks found for this student and exam');
        }

        $schoolSettings = getSchoolSettings();
        $html = generateMarkSheetHTML(
            $sheetData['student'],
            $sheetData['exam'],
            $sheetData['marks'],
            $schoolSettings,
            $sheetData['totalMarks'],
            $sheetData['totalMaxMarks'],
            $sheetData['totalPassMarks'],
            $sheetData['percentage'],
            $sheetData['overallGrade'],
            $sheetData['result'],
            false
        );

        $studentName = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string)($sheetData['student']['student_name'] ?? 'student')));
        $examName = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string)($sheetData['exam']['exam_name'] ?? 'exam')));
        $downloadName = 'marksheet_' . trim($studentName . '_' . $examName, '_');

        $pdfResult = marksheetExportOnePagePdfHtml($html, $downloadName);
        if (empty($pdfResult['success'])) {
            die($pdfResult['message'] ?? 'PDF generation failed');
        }
        exit();
    }
}

// Get filter data
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");
$exams = fetchAll("SELECT * FROM exams WHERE is_active = 1 ORDER BY exam_date DESC");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-file-earmark-pdf"></i> Generate Mark Sheet
            </h2>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Student-wise Marksheet -->
<div class="row mb-4">
    <div class="col-md-8 offset-md-2">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Student-wise Marksheet</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="view_marksheet.php" id="studentMarksheetForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Search</label>
                            <input type="text"
                                   class="form-control"
                                   name="student_search"
                                   id="studentMarksheetSearch"
                                   placeholder="Type 2 letters to search student"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="student_name"
                                   data-student-autocomplete-min-length="2"
                                   data-student-autocomplete-id-target="#studentMarksheetStudentId">
                            <input type="hidden" name="student_id" id="studentMarksheetStudentId" value="">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['exam_id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_name']); ?>
                                        (<?php echo date('M Y', strtotime($exam['exam_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-eye"></i> View Marksheet
                        </button>
                        <button type="submit" name="download" value="pdf" class="btn btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Generate PDF
                        </button>
                        <a href="view_marksheet.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Selection Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Select Criteria</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="searchForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" id="classId" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Section <span class="text-danger">*</span></label>
                            <select class="form-select" name="section_id" id="sectionId" required>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Exam <span class="text-danger">*</span></label>
                        <select class="form-select" name="exam_id" id="examId" required>
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['exam_id']; ?>">
                                    <?php echo htmlspecialchars($exam['exam_name']); ?>
                                    (<?php echo date('M Y', strtotime($exam['exam_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="search_students" class="btn btn-primary btn-lg">
                            <i class="bi bi-search"></i> Search Students
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Students List -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_students'])) {
    $classId = intval($_POST['class_id']);
    $sectionId = intval($_POST['section_id']);
    $examId = intval($_POST['exam_id']);

    if ($classId > 0 && $sectionId > 0 && $examId > 0) {
        // Get students with marks
        $students = fetchAll("SELECT DISTINCT
                             s.student_id, s.student_name, s.admission_no, s.roll_no,
                             c.class_name, sec.section_name,
                             e.exam_name,
                             COUNT(m.mark_id) as subjects_count,
                             SUM(m.marks_obtained) as total_marks,
                             SUM(sub.max_marks) as total_max_marks
                            FROM students s
                            JOIN classes c ON s.class_id = c.class_id
                            JOIN sections sec ON s.section_id = sec.section_id
                            CROSS JOIN exams e
                            LEFT JOIN marks m ON s.student_id = m.student_id AND m.exam_id = e.exam_id
                            LEFT JOIN subjects sub ON m.subject_id = sub.subject_id
                            WHERE s.class_id = ? AND s.section_id = ? AND e.exam_id = ?
                                AND s.status = 'Active'
                            GROUP BY s.student_id
                            HAVING subjects_count > 0
                            ORDER BY s.roll_no",
                            'iii', [$classId, $sectionId, $examId]);

        if (count($students) > 0):
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Students List (<?php echo count($students); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Exam:</strong> <?php echo htmlspecialchars($students[0]['exam_name']); ?> |
                    <strong>Class:</strong> <?php echo htmlspecialchars($students[0]['class_name'] . ' ' . $students[0]['section_name']); ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Roll No</th>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Subjects</th>
                                <th>Total Marks</th>
                                <th>Percentage</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student):
                                $percentage = ($student['total_max_marks'] > 0)
                                    ? round(($student['total_marks'] / $student['total_max_marks']) * 100, 2)
                                    : 0;
                                $grade = calculateGrade($percentage);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td><?php echo $student['subjects_count']; ?></td>
                                <td><?php echo $student['total_marks']; ?> / <?php echo $student['total_max_marks']; ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $percentage >= 75 ? 'success' :
                                            ($percentage >= 50 ? 'warning' : 'danger');
                                    ?>">
                                        <?php echo $percentage; ?>% (<?php echo $grade; ?>)
                                    </span>
                                </td>
                                <td>
                                    <a href="?generate=pdf&student_id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $examId; ?>"
                                       class="btn btn-sm btn-danger" target="_blank">
                                        <i class="bi bi-file-earmark-pdf"></i> Generate PDF
                                    </a>
                                    <a href="view_marksheet.php?student_id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $examId; ?>"
                                       class="btn btn-sm btn-info" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="?generate=all_pdf&class_id=<?php echo $classId; ?>&section_id=<?php echo $sectionId; ?>&exam_id=<?php echo $examId; ?>"
                       class="btn btn-danger btn-lg">
                        <i class="bi bi-file-earmark-pdf"></i> Generate All Mark Sheets (ZIP)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
        else:
?>
<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No students found with marks for the selected criteria.
        </div>
    </div>
</div>
<?php
        endif;
    }
}
?>

<?php
// Include footer
include '../../includes/footer.php';
?>
