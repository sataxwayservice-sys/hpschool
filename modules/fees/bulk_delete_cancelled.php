<?php
/**
 * Bulk Delete All Cancelled Receipts
 * WARNING: Permanently removes all cancelled receipts
 */

require_once '../../config/config.php';
requireLogin();
requirePermission('fees', 'delete');

$confirm = isset($_GET['confirm']) ? intval($_GET['confirm']) : 0;

// Get all cancelled receipts
$cancelledReceipts = fetchAll("SELECT fr.*, s.student_name, s.admission_no
                               FROM fee_receipts fr
                               JOIN students s ON fr.student_id = s.student_id
                               WHERE fr.is_cancelled = 1
                               ORDER BY fr.payment_date DESC");

$count = count($cancelledReceipts);
$totalAmount = 0;
foreach ($cancelledReceipts as $receipt) {
    $totalAmount += $receipt['amount_paid'];
}

// If not confirmed, show confirmation page
if (!$confirm) {
    $pageTitle = 'Bulk Delete Cancelled Receipts';
    include '../../includes/header.php';
    ?>

    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4><i class="bi bi-exclamation-triangle"></i> Warning: Bulk Delete Cancelled Receipts</h4>
                </div>
                <div class="card-body">
                    <?php if ($count == 0): ?>
                        <div class="alert alert-info">
                            <h5><i class="bi bi-check-circle"></i> No Cancelled Receipts Found!</h5>
                            <p>There are no cancelled receipts to delete.</p>
                        </div>
                        <a href="receipts.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Back to Receipts
                        </a>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h5><strong>⚠️ This action CANNOT be undone!</strong></h5>
                            <p>You are about to <strong>permanently delete ALL <?php echo $count; ?> cancelled receipts</strong> from the database.</p>
                            <p><strong>This will:</strong></p>
                            <ul>
                                <li>Delete <strong><?php echo $count; ?> cancelled receipt(s)</strong> permanently</li>
                                <li>Delete all fee details associated with these receipts</li>
                                <li>Total amount: <strong><?php echo formatCurrency($totalAmount); ?></strong></li>
                                <li>This action <strong>CANNOT be reversed</strong></li>
                            </ul>
                        </div>

                        <h5>Cancelled Receipts to be Deleted:</h5>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-bordered table-sm">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Amount</th>
                                        <th>Payment Mode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cancelledReceipts as $receipt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($receipt['receipt_no']); ?></td>
                                            <td><?php echo formatDate($receipt['payment_date']); ?></td>
                                            <td><?php echo htmlspecialchars($receipt['student_name'] . ' (' . $receipt['admission_no'] . ')'); ?></td>
                                            <td><?php echo formatCurrency($receipt['amount_paid']); ?></td>
                                            <td><?php echo htmlspecialchars($receipt['payment_mode']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-danger">
                                    <tr>
                                        <th colspan="3">Total:</th>
                                        <th><?php echo formatCurrency($totalAmount); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="receipts.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-arrow-left"></i> Cancel & Go Back
                            </a>
                            <a href="?confirm=1"
                               class="btn btn-danger btn-lg"
                               onclick="return confirm('Are you ABSOLUTELY SURE you want to permanently delete ALL <?php echo $count; ?> cancelled receipts? This CANNOT be undone!');">
                                <i class="bi bi-trash3"></i> Yes, Permanently Delete All <?php echo $count; ?> Receipts
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    include '../../includes/footer.php';
    exit();
}

// Confirmed - proceed with bulk deletion
if ($count == 0) {
    $_SESSION['info_message'] = "No cancelled receipts found to delete.";
    header("Location: receipts.php");
    exit();
}

try {
    $deletedCount = 0;
    $failedCount = 0;

    foreach ($cancelledReceipts as $receipt) {
        // Use the permanentlyDeleteReceipt function which saves to recycle bin first
        $deleted = permanentlyDeleteReceipt($receipt['receipt_id'], "Bulk delete of cancelled receipts");

        if ($deleted) {
            $deletedCount++;
        } else {
            $failedCount++;
        }
    }

    // Log activity
    $currentUser = getCurrentUser();
    logActivity($currentUser['user_id'], 'Bulk Delete Cancelled Receipts', 'fees',
                "Permanently deleted $deletedCount cancelled receipts. Total amount: " . formatCurrency($totalAmount));

    if ($deletedCount > 0) {
        $_SESSION['success_message'] = "Successfully deleted $deletedCount cancelled receipts! Total amount: " . formatCurrency($totalAmount) . " (All saved to recycle bin for recovery)";
    }

    if ($failedCount > 0) {
        $_SESSION['error_message'] = "Failed to delete $failedCount receipts.";
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to delete receipts: " . $e->getMessage();
}

header("Location: receipts.php");
exit();
?>
