<?php
/**
 * View Marksheet
 * Student-wise marksheet preview and PDF download
 */

require_once '../../config/config.php';

requireLogin();
requirePermission('marks', 'view');

$pageTitle = 'View Marksheet';
$studentId = intval($_GET['student_id'] ?? 0);
$examId = intval($_GET['exam_id'] ?? 0);
$download = strtolower(trim((string) ($_GET['download'] ?? '')));
$studentSearch = trim((string) ($_GET['student_search'] ?? ''));
$errorMessage = '';

$exams = fetchAll("SELECT * FROM exams WHERE is_active = 1 ORDER BY exam_date DESC");

if ($studentId > 0 && $examId > 0) {
    require_once '../../includes/marksheet_pdf.php';
    require_once '../../includes/pdf_export.php';

    $sheetData = getMarkSheetData($studentId, $examId);
    if ($sheetData) {
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
            $sheetData['result']
        );

        if ($download === 'pdf') {
            $studentName = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) ($sheetData['student']['student_name'] ?? 'student')));
            $examName = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) ($sheetData['exam']['exam_name'] ?? 'exam')));
            $downloadName = 'marksheet_' . trim($studentName . '_' . $examName, '_');

            $pdfResult = marksheetExportOnePagePdfHtml($html, $downloadName);
            if (empty($pdfResult['success'])) {
                die($pdfResult['message'] ?? 'PDF generation failed');
            }
            exit();
        }

        echo $html;
        exit();
    }

    $errorMessage = 'No marks found for the selected student and exam.';
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-file-earmark-pdf"></i> View Marksheet
            </h2>
            <div class="d-flex gap-2 flex-wrap">
                <a href="generate_marksheet.php" class="btn btn-primary">
                    <i class="bi bi-file-pdf"></i> Generate Marksheet
                </a>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/marks/index.php'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-search"></i> Student-wise Marksheet Search</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="viewMarksheetForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Search <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   name="student_search"
                                   id="marksheetStudentSearch"
                                   value="<?php echo htmlspecialchars($studentSearch); ?>"
                                   placeholder="Type 2 letters to search student"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="student_name"
                                   data-student-autocomplete-min-length="2"
                                   data-student-autocomplete-id-target="#marksheetStudentId">
                            <input type="hidden" name="student_id" id="marksheetStudentId" value="<?php echo $studentId > 0 ? intval($studentId) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['exam_id']; ?>"
                                        <?php echo ($examId === intval($exam['exam_id'])) ? 'selected' : ''; ?>>
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
                            <i class="bi bi-file-earmark-pdf"></i> Download PDF
                        </button>
                        <a href="view_marksheet.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </form>
                <div class="mt-3 text-muted small">
                    Search by student name or admission number, then choose an exam.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
