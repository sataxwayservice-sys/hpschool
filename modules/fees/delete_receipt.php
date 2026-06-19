<?php
/**
 * Delete Fee Receipt
 * Mark receipt as cancelled (soft delete)
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'delete');

$currentUser = getCurrentUser();
$receiptId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['receipt_id']) ? intval($_POST['receipt_id']) : 0);

if ($receiptId == 0) {
    $_SESSION['error_message'] = 'Invalid receipt ID!';
    header("Location: receipts.php");
    exit();
}

// Get receipt details
$query = "SELECT fr.*, s.student_name
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          WHERE fr.receipt_id = ?";
$receipt = fetchOne($query, 'i', [$receiptId]);

if (!$receipt) {
    $_SESSION['error_message'] = 'Receipt not found!';
    header("Location: receipts.php");
    exit();
}

// Check if already cancelled
if ($receipt['is_cancelled'] == 1) {
    $_SESSION['error_message'] = 'Receipt is already cancelled!';
    header("Location: receipts.php");
    exit();
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $reason = isset($_POST['cancellation_reason']) ? sanitize($_POST['cancellation_reason']) : 'Cancelled by admin';

    beginTransaction();
    try {
        // Get complete receipt data including details before deletion
        $receiptData = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);

        // Get receipt details (fee breakdown)
        $receiptDetails = fetchAll("SELECT * FROM fee_receipt_details WHERE receipt_id = ?", 'i', [$receiptId]);

        // Add details to receipt data
        $receiptData['details'] = $receiptDetails;

        // Move to recycle bin
        $moved = softDeleteFeeReceipt($receiptId, $reason);

        if (!$moved) {
            throw new Exception("Failed to move receipt to recycle bin");
        }

        // Log activity
        logActivity($currentUser['user_id'], 'Cancel Receipt', 'Fees',
            "Cancelled receipt: " . $receipt['receipt_no'] . " for student: " . $receipt['student_name']);

        commitTransaction();

        $_SESSION['success_message'] = 'Receipt cancelled and moved to recycle bin. You can restore it later from Settings -> Recycle Bin until you delete it manually.';
        header("Location: receipts.php");
        exit();

    } catch (Exception $e) {
        rollbackTransaction();
        $_SESSION['error_message'] = 'Failed to cancel receipt: ' . $e->getMessage();
        header("Location: receipts.php");
        exit();
    }
}

$pageTitle = 'Cancel Receipt';

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-trash"></i> Cancel Receipt
            </h2>
            <div>
                <a href="receipts.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card dashboard-card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Confirm Cancellation</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="bi bi-info-circle"></i> Receipt Information:</h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <th width="40%">Receipt No:</th>
                            <td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Student Name:</th>
                            <td><?php echo htmlspecialchars($receipt['student_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Payment Date:</th>
                            <td><?php echo formatDate($receipt['payment_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td><strong class="text-danger"><?php echo formatCurrency($receipt['amount_paid']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Payment Mode:</th>
                            <td><?php echo htmlspecialchars($receipt['payment_mode']); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle"></i> What Happens:</h6>
                    <ul class="mb-0">
                        <li>Receipt will be <strong>moved to Recycle Bin</strong></li>
                        <li>You can <strong>restore it later</strong> from Settings -> Recycle Bin</li>
                        <li>It stays there until you delete it manually</li>
                        <li>If you want to correct payment details, use <strong>Edit</strong> instead</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="receipt_id" value="<?php echo $receiptId; ?>">

                    <div class="mb-3">
                        <label for="cancellation_reason" class="form-label">Reason for Cancellation <span class="text-danger">*</span></label>
                        <select class="form-select" id="cancellation_reason" name="cancellation_reason" required>
                            <option value="">-- Select Reason --</option>
                            <option value="Duplicate payment">Duplicate payment</option>
                            <option value="Wrong amount entered">Wrong amount entered</option>
                            <option value="Wrong student">Wrong student</option>
                            <option value="Payment refunded">Payment refunded</option>
                            <option value="Error in receipt">Error in receipt</option>
                            <option value="Requested by parent">Requested by parent</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="confirm_delete" class="btn btn-danger btn-lg"
                                onclick="return confirm('Cancel this receipt? You can restore it later from Recycle Bin until you delete it manually.');">
                            <i class="bi bi-trash-fill"></i> Yes, Cancel This Receipt
                        </button>
                        <a href="receipts.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> No, Go Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>
