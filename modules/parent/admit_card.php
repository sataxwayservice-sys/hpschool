<?php
/**
 * Parent Portal Admit Card
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';
require_once '../../includes/admit_card_renderer.php';
require_once '../../includes/pdf_export.php';

requireParentPortalLogin();
parentPortalEnsureSchema();

$currentUser = getCurrentUser();
$students = parentPortalGetLinkedStudents($currentUser['user_id']);
$exams = parentPortalGetActiveExams(25);

$selectedStudentId = intval($_GET['student_id'] ?? 0);
$selectedExamId = intval($_GET['exam_id'] ?? 0);
$downloadPdf = strtolower(trim((string)($_GET['download'] ?? ''))) === 'pdf';

if ($selectedStudentId > 0 && $selectedExamId > 0) {
    if (!parentPortalHasStudentAccess($currentUser['user_id'], $selectedStudentId)) {
        die('You do not have access to this student.');
    }

    $student = fetchOne(
        "SELECT s.*, c.class_name, c.class_order, sec.section_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.class_id
         LEFT JOIN sections sec ON s.section_id = sec.section_id
         WHERE s.student_id = ?",
        'i',
        [$selectedStudentId]
    );

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
        'school_settings' => getSchoolSettings(),
        'page_title' => 'Admit Card - ' . ($student['student_name'] ?? ''),
        'back_url' => APP_URL . '/modules/parent/admit_card.php',
        'refresh_url' => APP_URL . '/modules/parent/admit_card.php?student_id=' . intval($selectedStudentId) . '&exam_id=' . intval($selectedExamId),
        'download_url' => APP_URL . '/modules/parent/admit_card.php?student_id=' . intval($selectedStudentId) . '&exam_id=' . intval($selectedExamId) . '&download=pdf',
        'show_toolbar' => true,
        'watermark' => 'ADMIT CARD',
        'paper_size' => 'A5',
    ]);

    if ($downloadPdf) {
        $downloadName = admitCardBuildDownloadName($student, $exam, 'parent');
        $pdfResult = pdfExportDownloadHtml($html, $downloadName, [
            'paper_size' => 'A5',
            'paper_orientation' => 'landscape',
        ]);
        if (!empty($pdfResult['success'])) {
            exit();
        }

        alertAndRedirect(
            'PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'),
            APP_URL . '/modules/parent/admit_card.php?student_id=' . intval($selectedStudentId) . '&exam_id=' . intval($selectedExamId),
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
    <div class="parent-hero-subtitle">Select a linked student and exam to view the printable admit card.</div>
</div>

<div class="parent-card">
    <div class="parent-card-head">
        <h5 class="mb-0">Linked Students</h5>
    </div>
    <div class="parent-card-body">
        <?php if (empty($students)): ?>
            <div class="parent-empty">No linked students found for this account.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="parent-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission No</th>
                            <th>Class</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $linkedStudent): ?>
                            <tr>
                                <td><strong><?php echo parentPortalEscape($linkedStudent['student_name']); ?></strong></td>
                                <td><?php echo parentPortalEscape($linkedStudent['admission_no']); ?></td>
                                <td><?php echo parentPortalEscape(($linkedStudent['class_name'] ?? '-') . ' ' . ($linkedStudent['section_name'] ?? '')); ?></td>
                                <td>
                                    <?php foreach ($exams as $exam): ?>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/admit_card.php?student_id=<?php echo intval($linkedStudent['student_id']); ?>&exam_id=<?php echo intval($exam['exam_id']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener" style="margin-right:6px; margin-bottom:6px;">
                                            <i class="bi bi-eye"></i> View <?php echo parentPortalEscape($exam['exam_name']); ?>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/admit_card.php?student_id=<?php echo intval($linkedStudent['student_id']); ?>&exam_id=<?php echo intval($exam['exam_id']); ?>&download=pdf" class="parent-button parent-button-secondary" target="_blank" rel="noopener" style="margin-right:6px; margin-bottom:6px;">
                                            <i class="bi bi-file-earmark-pdf"></i> PDF
                                        </a>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$contentHtml = ob_get_clean();

echo parentPortalRenderLayout('Admit Card', $contentHtml, 'admit_card');
