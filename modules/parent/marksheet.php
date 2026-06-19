<?php
/**
 * Parent Portal Marksheet
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';

requireParentPortalLogin();
parentPortalEnsureSchema();

$currentUser = getCurrentUser();
$students = parentPortalGetLinkedStudents($currentUser['user_id']);
$exams = parentPortalGetActiveExams(25);

$selectedStudentId = intval($_GET['student_id'] ?? 0);
$selectedExamId = intval($_GET['exam_id'] ?? 0);

if ($selectedStudentId > 0 && $selectedExamId > 0) {
    if (!parentPortalHasStudentAccess($currentUser['user_id'], $selectedStudentId)) {
        die('You do not have access to this student.');
    }

    require_once '../../includes/marksheet_pdf.php';
    generateMarkSheetPDF($selectedStudentId, $selectedExamId);
    exit();
}

if ($selectedStudentId <= 0 && count($students) === 1) {
    $selectedStudentId = intval($students[0]['student_id']);
}

$firstSelectedStudentId = $selectedStudentId ?: intval($students[0]['student_id'] ?? 0);

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Marksheet Download</h1>
    <div class="parent-hero-subtitle">Pick a child and an exam to open the printable mark sheet.</div>
</div>

<div class="parent-card">
    <div class="parent-card-head">
        <h5 class="mb-0">Generate Marksheet</h5>
    </div>
    <div class="parent-card-body">
        <?php if (empty($students)): ?>
            <div class="parent-empty">No children are linked to this parent account yet.</div>
        <?php elseif (empty($exams)): ?>
            <div class="parent-empty">No active exams are available right now.</div>
        <?php else: ?>
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Child</label>
                        <select class="form-select" name="student_id" required>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo intval($student['student_id']); ?>" <?php echo $selectedStudentId === intval($student['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo parentPortalEscape($student['student_name'] . ' - ' . $student['class_name'] . ' ' . $student['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Exam</label>
                        <select class="form-select" name="exam_id" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo intval($exam['exam_id']); ?>">
                                    <?php echo parentPortalEscape($exam['exam_name'] . ' - ' . date('M Y', strtotime($exam['exam_date']))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="parent-button-row mt-3">
                    <button type="submit" class="parent-button parent-button-primary">
                        <i class="bi bi-file-earmark-pdf"></i> Open Marksheet
                    </button>
                    <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/parent/dashboard.php'); ?>" class="parent-button">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </form>
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
                                    <?php if ($firstSelectedStudentId > 0): ?>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/marksheet.php?student_id=<?php echo intval($firstSelectedStudentId); ?>&exam_id=<?php echo intval($exam['exam_id']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                            <i class="bi bi-printer"></i> Generate
                                        </a>
                                    <?php else: ?>
                                        <span class="parent-muted">Select a child first</span>
                                    <?php endif; ?>
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

echo parentPortalRenderLayout('Marksheet', $contentHtml, 'marksheet');
