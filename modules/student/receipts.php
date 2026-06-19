<?php
/**
 * Student Fee Receipts
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();
requireStudentPortalLogin();

$studentId = studentPortalGetCurrentStudentId();
$student = studentPortalGetStudentRecord($studentId);
$receipts = studentPortalGetReceipts($studentId, 50);

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Paid Fee Receipts</h1>
    <div class="parent-hero-subtitle">View and print fee receipts for your student account.</div>
</div>

<div class="parent-card">
    <div class="parent-card-head">
        <h5 class="mb-0">Receipt History</h5>
    </div>
    <div class="parent-card-body">
        <?php if (!empty($receipts)): ?>
            <div class="table-responsive">
                <table class="parent-table">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th>Mode</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                            <tr>
                                <td><strong><?php echo parentPortalEscape($receipt['receipt_no']); ?></strong></td>
                                <td><?php echo parentPortalEscape(date('d M Y', strtotime($receipt['payment_date']))); ?></td>
                                <td class="text-end"><?php echo formatCurrency($receipt['amount_paid']); ?></td>
                                <td><?php echo parentPortalEscape($receipt['payment_mode']); ?></td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/modules/student/receipt.php?id=<?php echo intval($receipt['receipt_id']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                        <i class="bi bi-printer"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="parent-empty">No paid receipts found for your account.</div>
        <?php endif; ?>
    </div>
</div>
<?php
$contentHtml = ob_get_clean();

echo studentPortalRenderLayout('Receipts', $contentHtml, 'receipts');
