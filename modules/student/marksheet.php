<?php
/**
 * Student Marksheet
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();
requireStudentPortalLogin();

$studentId = studentPortalGetCurrentStudentId();
$student = studentPortalGetStudentRecord($studentId);
$exams = studentPortalGetActiveExams(25);
$selectedExamId = intval($_GET['exam_id'] ?? 0);

if ($selectedExamId > 0) {
    require_once '../../includes/marksheet_pdf.php';
    generateMarkSheetPDF($studentId, $selectedExamId);
    exit();
}

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Marksheet Download</h1>
    <div class="parent-hero-subtitle">Pick an exam to open the printable mark sheet for your own record.</div>
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
                                    <a href="<?php echo APP_URL; ?>/modules/student/marksheet.php?exam_id=<?php echo intval($exam['exam_id']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                        <i class="bi bi-printer"></i> Generate
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

echo studentPortalRenderLayout('Marksheet', $contentHtml, 'marksheet');
