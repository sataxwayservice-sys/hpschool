<?php
/**
 * Student Admit Card
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';
require_once '../../includes/admit_card_renderer.php';
require_once '../../includes/pdf_export.php';

studentPortalEnsureSchema();
requireStudentPortalLogin();

$studentId = studentPortalGetCurrentStudentId();
$student = studentPortalGetStudentRecord($studentId);
$schoolSettings = getSchoolSettings();
$exams = studentPortalGetActiveExams(25);
$selectedExamId = intval($_GET['exam_id'] ?? 0);
$downloadPdf = strtolower(trim((string)($_GET['download'] ?? ''))) === 'pdf';

if ($selectedExamId > 0) {
    $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$selectedExamId]);

    if (!$student || !$exam) {
        die('Admit card data not found.');
    }

    $scheduleRows = admitCardGetScheduleRows($student, $exam, [
        'start_date' => $exam['exam_date'] ?? '',
    ]);

    $cards = [[
        'student' => $student,
        'exam' => $exam,
        'schedule_rows' => $scheduleRows,
        'options' => [
            'issue_date' => date('Y-m-d'),
            'admit_no' => trim((string)($student['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0),
        ],
    ]];

    $html = admitCardRenderDocument($cards, [
        'school_settings' => $schoolSettings,
        'page_title' => 'Admit Card - ' . ($student['student_name'] ?? ''),
        'back_url' => APP_URL . '/modules/student/admit_card.php',
        'refresh_url' => APP_URL . '/modules/student/admit_card.php?exam_id=' . intval($selectedExamId),
        'download_url' => APP_URL . '/modules/student/admit_card.php?exam_id=' . intval($selectedExamId) . '&download=pdf',
        'show_toolbar' => true,
        'watermark' => 'ADMIT CARD',
        'paper_size' => 'A5',
    ]);

    if ($downloadPdf) {
        $downloadName = admitCardBuildDownloadName($student, $exam, 'student');
        $pdfResult = pdfExportDownloadHtml($html, $downloadName, [
            'paper_size' => 'A5',
            'paper_orientation' => 'landscape',
        ]);
        if (!empty($pdfResult['success'])) {
            exit();
        }

        alertAndRedirect(
            'PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'),
            APP_URL . '/modules/student/admit_card.php?exam_id=' . intval($selectedExamId),
            'error'
        );
    }

    echo $html;
    exit();
}

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Admit Card</h1>
    <div class="parent-hero-subtitle">Select an exam to view and download your admit card.</div>
</div>

<div class="parent-card">
    <div class="parent-card-head">
        <h5 class="mb-0">Student Details</h5>
    </div>
    <div class="parent-card-body">
        <?php if (!$student): ?>
            <div class="parent-empty">Student record not found for this account.</div>
        <?php else: ?>
            <div class="row g-2">
                <div class="col-md-4"><strong>Name:</strong> <?php echo parentPortalEscape($student['student_name']); ?></div>
                <div class="col-md-4"><strong>Admission No:</strong> <?php echo parentPortalEscape($student['admission_no']); ?></div>
                <div class="col-md-4"><strong>Class:</strong> <?php echo parentPortalEscape(($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '')); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="parent-card mt-3">
    <div class="parent-card-head">
        <h5 class="mb-0">Available Exams</h5>
    </div>
    <div class="parent-card-body">
        <?php if (!empty($exams)): ?>
            <div class="table-responsive">
                <table class="parent-table">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td><strong><?php echo parentPortalEscape($exam['exam_name']); ?></strong></td>
                                <td><?php echo parentPortalEscape($exam['exam_type'] ?? '-'); ?></td>
                                <td><?php echo parentPortalEscape(date('d M Y', strtotime($exam['exam_date']))); ?></td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/modules/student/admit_card.php?exam_id=<?php echo intval($exam['exam_id']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/modules/student/admit_card.php?exam_id=<?php echo intval($exam['exam_id']); ?>&download=pdf" class="parent-button parent-button-secondary" target="_blank" rel="noopener">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="parent-empty">No exams found.</div>
        <?php endif; ?>
    </div>
</div>
<?php
$contentHtml = ob_get_clean();

echo studentPortalRenderLayout('Admit Card', $contentHtml, 'admit_card');
