<?php
/**
 * Permanently Delete Receipt
 * WARNING: This permanently removes the receipt from the database
 */

require_once '../../config/config.php';
requireLogin();
requirePermission('fees', 'delete');

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$confirm = isset($_GET['confirm']) ? intval($_GET['confirm']) : 0;

if ($receiptId <= 0) {
    $_SESSION['error_message'] = "Invalid receipt ID!";
    header("Location: receipts.php");
    exit();
}

// Get receipt info
$receipt = fetchOne("SELECT fr.*, s.student_name, s.admission_no
                     FROM fee_receipts fr
                     JOIN students s ON fr.student_id = s.student_id
                     WHERE fr.receipt_id = ?", 'i', [$receiptId]);

if (!$receipt) {
    $_SESSION['error_message'] = "Receipt not found!";
    header("Location: receipts.php");
    exit();
}

// If not confirmed, show confirmation page
if (!$confirm) {
    $pageTitle = 'Confirm Permanent Delete';
    include '../../includes/header.php';
    ?>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4><i class="bi bi-exclamation-triangle"></i> Warning: Permanent Delete</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h5><strong>⚠️ This action CANNOT be undone!</strong></h5>
                        <p>You are about to <strong>permanently delete</strong> this receipt from the database.</p>
                        <p>This will:</p>
                        <ul>
                            <li>Delete the receipt record permanently</li>
                            <li>Delete all fee details associated with this receipt</li>
                            <li>Make the fee unpaid again (student will owe the fee)</li>
                            <li>This action <strong>CANNOT be reversed</strong></li>
                        </ul>
                    </div>

                    <h5>Receipt Details:</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Receipt No:</th>
                            <td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Student:</th>
                            <td><?php echo htmlspecialchars($receipt['student_name'] . ' (' . $receipt['admission_no'] . ')'); ?></td>
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td><strong class="text-danger"><?php echo formatCurrency($receipt['amount_paid']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td><?php echo formatDate($receipt['payment_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Payment Mode:</th>
                            <td><?php echo htmlspecialchars($receipt['payment_mode']); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($receipt['is_cancelled']): ?>
                                    <span class="badge bg-danger">CANCELLED</span>
                                <?php else: ?>
                                    <span class="badge bg-success">ACTIVE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="receipts.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancel & Go Back
                        </a>
                        <a href="?id=<?php echo $receiptId; ?>&confirm=1"
                           class="btn btn-danger"
                           onclick="return confirm('Are you ABSOLUTELY SURE you want to permanently delete this receipt? This CANNOT be undone!');">
                            <i class="bi bi-trash"></i> Yes, Permanently Delete This Receipt
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    include '../../includes/footer.php';
    exit();
}

// Confirmed - proceed with deletion
try {
    // Use the permanentlyDeleteReceipt function which saves to recycle bin first
    $deleted = permanentlyDeleteReceipt($receiptId, "Permanently deleted via admin panel");

    if ($deleted) {
        // Log activity
        $currentUser = getCurrentUser();
        logActivity($currentUser['user_id'], 'Permanently Deleted Receipt', 'fees',
                    "Permanently deleted receipt {$receipt['receipt_no']} for student {$receipt['student_name']}");

        $_SESSION['success_message'] = "Receipt permanently deleted successfully! Receipt No: " . htmlspecialchars($receipt['receipt_no']) . " (Saved to recycle bin for recovery)";
    } else {
        $_SESSION['error_message'] = "Failed to delete receipt. Please try again.";
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to delete receipt: " . $e->getMessage();
}

header("Location: receipts.php");
exit();
?>
