<?php
/**
 * Edit Fee Receipt
 * Allows modification of receipt details
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'edit');

$pageTitle = 'Edit Receipt';
$currentUser = getCurrentUser();
$error = '';
$success = '';

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['receipt_id']) ? intval($_POST['receipt_id']) : 0);

if ($receiptId == 0) {
    header("Location: receipts.php");
    exit();
}

// Get receipt details
$query = "SELECT fr.*, s.student_name, s.admission_no
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          WHERE fr.receipt_id = ?";
$receipt = fetchOne($query, 'i', [$receiptId]);

if (!$receipt) {
    header("Location: receipts.php");
    exit();
}

// Check if receipt is cancelled
if ($receipt['is_cancelled'] == 1) {
    $_SESSION['error_message'] = 'Cannot edit a cancelled receipt!';
    header("Location: receipts.php");
    exit();
}

// Get fee details
$feeDetailsQuery = "SELECT frd.*, fh.fee_head_name, fh.fee_type
                    FROM fee_receipt_details frd
                    JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                    WHERE frd.receipt_id = ?";
$feeDetails = fetchAll($feeDetailsQuery, 'i', [$receiptId]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_receipt'])) {
    $paymentMode = sanitize($_POST['payment_mode']);
    $paymentDate = sanitize($_POST['payment_date']);
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');

    beginTransaction();
    try {
        // Update receipt
        $updateQuery = "UPDATE fee_receipts
                       SET payment_mode = ?,
                           payment_date = ?,
                           transaction_id = ?,
                           remarks = ?,
                           updated_at = NOW()
                       WHERE receipt_id = ?";

        executeQuery($updateQuery, 'ssssi', [
            $paymentMode,
            $paymentDate,
            $transactionId,
            $remarks,
            $receiptId
        ]);

        // Log activity
        logActivity($currentUser['user_id'], 'Edit Receipt', 'Fees',
            "Edited receipt: " . $receipt['receipt_no']);

        commitTransaction();

        $_SESSION['success_message'] = 'Receipt updated successfully!';
        header("Location: view_receipt_details.php?id=$receiptId");
        exit();

    } catch (Exception $e) {
        rollbackTransaction();
        $error = 'Failed to update receipt: ' . $e->getMessage();
    }
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-pencil-square"></i> Edit Receipt
            </h2>
            <div>
                <a href="view_receipt_details.php?id=<?php echo $receiptId; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card dashboard-card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Receipt Information</h5>
            </div>
            <div class="card-body">
                <!-- Receipt Info (Read-only) -->
                <div class="alert alert-info">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Receipt No:</strong> <?php echo htmlspecialchars($receipt['receipt_no']); ?><br>
                            <strong>Student:</strong> <?php echo htmlspecialchars($receipt['student_name']); ?><br>
                            <strong>Admission No:</strong> <?php echo htmlspecialchars($receipt['admission_no']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Total Amount:</strong> <?php echo formatCurrency($receipt['total_amount']); ?><br>
                            <strong>Amount Paid:</strong> <?php echo formatCurrency($receipt['amount_paid']); ?><br>
                            <strong>Created:</strong> <?php echo formatDate($receipt['created_at']); ?>
                        </div>
                    </div>
                </div>

                <!-- Fee Details (Read-only) -->
                <h6 class="mt-4"><i class="bi bi-list-check"></i> Fee Details (Cannot be edited)</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Fee Head</th>
                                <th>Month</th>
                                <th>Year</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeDetails as $detail): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($detail['fee_head_name']); ?></td>
                                <td><?php echo htmlspecialchars($detail['fee_month'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($detail['fee_year'] ?? '-'); ?></td>
                                <td><?php echo formatCurrency($detail['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Editable Form -->
                <form method="POST" action="">
                    <input type="hidden" name="receipt_id" value="<?php echo $receiptId; ?>">

                    <h6 class="mt-4"><i class="bi bi-pencil"></i> Editable Fields</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_date" class="form-label required">Payment Date</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date"
                                   value="<?php echo $receipt['payment_date']; ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="payment_mode" class="form-label required">Payment Mode</label>
                            <select class="form-select" id="payment_mode" name="payment_mode" required>
                                <option value="Cash" <?php echo $receipt['payment_mode'] == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="Bank" <?php echo $receipt['payment_mode'] == 'Bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="UPI" <?php echo $receipt['payment_mode'] == 'UPI' ? 'selected' : ''; ?>>UPI</option>
                                <option value="Cheque" <?php echo $receipt['payment_mode'] == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="transaction_id" class="form-label">Transaction/Cheque No</label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id"
                               value="<?php echo htmlspecialchars($receipt['transaction_id'] ?? ''); ?>"
                               placeholder="Optional">
                    </div>

                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                  placeholder="Optional remarks"><?php echo htmlspecialchars($receipt['remarks'] ?? ''); ?></textarea>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Note:</strong> Only payment information can be edited. Fee amounts, fee heads, and student information cannot be changed.
                        To modify fee details, you must delete this receipt and create a new one.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="update_receipt" class="btn btn-warning btn-lg">
                            <i class="bi bi-save"></i> Update Receipt
                        </button>
                        <a href="view_receipt_details.php?id=<?php echo $receiptId; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
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
