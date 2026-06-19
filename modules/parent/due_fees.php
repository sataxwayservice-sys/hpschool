<?php
/**
 * Parent Portal Due Fees
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';

requireParentPortalLogin();
parentPortalEnsureSchema();

$currentUser = getCurrentUser();
$students = parentPortalGetLinkedStudents($currentUser['user_id']);
$studentIds = array_map(fn($student) => intval($student['student_id']), $students);
$paymentSettings = parentPortalGetPaymentSettings();

$selectedStudentId = intval($_GET['student_id'] ?? 0);
if ($selectedStudentId <= 0 && count($students) === 1) {
    $selectedStudentId = intval($students[0]['student_id']);
}
if ($selectedStudentId <= 0 && !empty($studentIds)) {
    $selectedStudentId = intval($studentIds[0]);
}

$selectedStudent = null;
foreach ($students as $student) {
    if (intval($student['student_id']) === $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

$summary = $selectedStudent ? parentPortalGetFeeSummary($selectedStudentId) : null;
$upiLink = '';
if ($selectedStudent && !empty($paymentSettings['upi_id'])) {
    $upiLink = parentPortalBuildUpiLink(
        $paymentSettings['upi_id'],
        $paymentSettings['payment_recipient_name'],
        $summary['due_total'] ?? 0,
        trim(($paymentSettings['payment_note'] ?? 'School fee payment') . ' - ' . ($selectedStudent['student_name'] ?? 'Student'))
    );
}

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Due Fee Statement</h1>
    <div class="parent-hero-subtitle">Month-wise pending fees for your linked children.</div>
</div>

<?php if (empty($students)): ?>
    <div class="parent-card">
        <div class="parent-card-body">
            <div class="parent-empty">No children are linked to this parent account yet.</div>
        </div>
    </div>
<?php else: ?>
    <div class="parent-card mb-3">
        <div class="parent-card-head">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Select Child</label>
                    <form method="GET" action="">
                        <select class="form-select" name="student_id" onchange="this.form.submit()">
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo intval($student['student_id']); ?>" <?php echo $selectedStudentId === intval($student['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo parentPortalEscape($student['student_name'] . ' - ' . $student['class_name'] . ' ' . $student['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="parent-button-row justify-content-md-end">
                        <a href="<?php echo APP_URL; ?>/modules/parent/marksheet.php?student_id=<?php echo intval($selectedStudentId); ?>" class="parent-button parent-button-primary">
                            <i class="bi bi-file-earmark-pdf"></i> Marksheet
                        </a>
                        <a href="<?php echo APP_URL; ?>/modules/parent/admit_card.php?student_id=<?php echo intval($selectedStudentId); ?>" class="parent-button">
                            <i class="bi bi-card-heading"></i> Admit Card
                        </a>
                        <a href="<?php echo APP_URL; ?>/modules/parent/receipts.php?student_id=<?php echo intval($selectedStudentId); ?>" class="parent-button parent-button-success">
                            <i class="bi bi-receipt"></i> Receipts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selectedStudent && $summary): ?>
        <div class="parent-summary-grid">
            <div class="parent-summary-card">
                <span class="parent-summary-label">Student</span>
                <span class="parent-summary-value"><?php echo parentPortalEscape($selectedStudent['student_name']); ?></span>
                <span class="parent-muted"><?php echo parentPortalEscape(($selectedStudent['class_name'] ?? '-') . ' ' . ($selectedStudent['section_name'] ?? '')); ?></span>
            </div>
            <div class="parent-summary-card">
                <span class="parent-summary-label">Assigned</span>
                <span class="parent-summary-value"><?php echo formatCurrency($summary['assigned_total'] ?? 0); ?></span>
                <span class="parent-muted">Total fee assigned</span>
            </div>
            <div class="parent-summary-card">
                <span class="parent-summary-label">Paid</span>
                <span class="parent-summary-value"><?php echo formatCurrency($summary['paid_total'] ?? 0); ?></span>
                <span class="parent-muted">Paid till date</span>
            </div>
            <div class="parent-summary-card">
                <span class="parent-summary-label">Due</span>
                <span class="parent-summary-value text-danger"><?php echo formatCurrency($summary['due_total'] ?? 0); ?></span>
                <span class="parent-muted">Pending amount</span>
            </div>
        </div>

        <?php if (!empty($upiLink) && floatval($summary['due_total'] ?? 0) > 0): ?>
            <div class="parent-card mb-3">
                <div class="parent-card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h5 class="mb-1">Pay pending fee</h5>
                            <div class="parent-muted">Open any UPI app and pay the pending amount for this child.</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-danger fs-4"><?php echo formatCurrency($summary['due_total'] ?? 0); ?></div>
                            <div class="parent-muted">Outstanding</div>
                        </div>
                    </div>
                    <div class="parent-button-row mt-3">
                        <a href="<?php echo parentPortalEscape($upiLink); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                            <i class="bi bi-google"></i> Google Pay
                        </a>
                        <a href="<?php echo parentPortalEscape($upiLink); ?>" class="parent-button parent-button-success" target="_blank" rel="noopener">
                            <i class="bi bi-phone"></i> PhonePe
                        </a>
                        <a href="<?php echo parentPortalEscape($upiLink); ?>" class="parent-button" target="_blank" rel="noopener">
                            <i class="bi bi-qr-code"></i> UPI App
                        </a>
                        <img src="<?php echo parentPortalEscape(buildQrCodeUrl($upiLink, 120)); ?>" alt="QR Code" style="width: 80px; height: 80px; margin-left: auto;">
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="parent-card">
            <div class="parent-card-head">
                <h5 class="mb-0">Month-wise Pending Fees</h5>
            </div>
            <div class="parent-card-body">
                <?php $pendingItems = $summary['pending_items'] ?? []; ?>
                <?php if (!empty($pendingItems)): ?>
                    <div class="table-responsive">
                        <table class="parent-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Fee Type</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Due</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingItems as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo parentPortalEscape(($item['display_month'] ?? $item['fee_month'] ?? '-') . ' ' . ($item['fee_year'] ?? '')); ?></strong>
                                            <div class="parent-muted"><?php echo parentPortalEscape($item['fee_head_name'] ?? '-'); ?></div>
                                        </td>
                                        <td><?php echo parentPortalEscape($item['display_fee_type'] ?? ($item['fee_type'] ?? '-')); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($item['original_amount'] ?? 0); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($item['paid_amount'] ?? 0); ?></td>
                                        <td class="text-end text-danger"><?php echo formatCurrency($item['due_amount'] ?? 0); ?></td>
                                        <td><span class="badge bg-warning text-dark"><?php echo parentPortalEscape($item['status'] ?? 'Pending'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total Due</th>
                                    <th class="text-end text-danger"><?php echo formatCurrency($summary['due_total'] ?? 0); ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="parent-empty">No pending fee found for this child.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
$contentHtml = ob_get_clean();

echo parentPortalRenderLayout('Due Fees', $contentHtml, 'due_fees');
