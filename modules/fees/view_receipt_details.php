<?php
/**
 * View Receipt Details
 * Detailed view of fee receipt (non-printable version)
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'view');

$pageTitle = 'Receipt Details';
$currentUser = getCurrentUser();

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receiptId == 0) {
    header("Location: receipts.php");
    exit();
}

// Get receipt with student details
$query = "SELECT
            fr.*,
            s.student_name, s.admission_no, s.father_name, s.mother_name,
            s.contact_no, s.email, s.address,
            c.class_name, sec.section_name,
            u.full_name as collected_by_name, u.username as collected_by_username
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          JOIN classes c ON s.class_id = c.class_id
          JOIN sections sec ON s.section_id = sec.section_id
          LEFT JOIN users u ON fr.collected_by = u.user_id
          WHERE fr.receipt_id = ?";

$receipt = fetchOne($query, 'i', [$receiptId]);

if (!$receipt) {
    header("Location: receipts.php");
    exit();
}

// Get fee details
$detailsQuery = "SELECT frd.*, fh.fee_head_name, fh.fee_type
                 FROM fee_receipt_details frd
                 JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                 WHERE frd.receipt_id = ?
                 ORDER BY fh.display_order";

$feeDetails = fetchAll($detailsQuery, 'i', [$receiptId]);

$receiptUrl = APP_URL . '/modules/fees/pdf_receipt.php?id=' . $receiptId . '&size=A4';
$shareMessage = 'Fee receipt ' . $receipt['receipt_no'] . ' for ' . $receipt['student_name'] . ' - ' . formatCurrency($receipt['amount_paid']) . '. View: ' . $receiptUrl;
$whatsappUrl = buildWhatsAppUrl($receipt['contact_no'] ?? '', $shareMessage);
$pdfA5Url = APP_URL . '/modules/fees/pdf_receipt.php?id=' . $receiptId . '&size=A5';

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-receipt"></i> Receipt Details
            </h2>
            <div>
                <?php if (!$receipt['is_cancelled']): ?>
                    <?php if (hasPermission('fees', 'edit')): ?>
                    <a href="edit_receipt.php?id=<?php echo $receiptId; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edit Receipt
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('fees', 'delete')): ?>
                    <a href="delete_receipt.php?id=<?php echo $receiptId; ?>" class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to cancel this receipt?');">
                        <i class="bi bi-trash"></i> Cancel Receipt
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="receipt.php?id=<?php echo $receiptId; ?>" class="btn btn-primary" target="_blank">
                    <i class="bi bi-printer"></i> Print Receipt
                </a>
                <a href="receipts.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Receipt Information -->
    <div class="col-md-6">
        <div class="card dashboard-card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-receipt-cutoff"></i> Receipt Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Receipt No:</th>
                        <td><strong class="text-primary"><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Payment Date:</th>
                        <td><?php echo formatDate($receipt['payment_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Payment Mode:</th>
                        <td>
                            <span class="badge bg-<?php
                                echo $receipt['payment_mode'] == 'Cash' ? 'success' :
                                    ($receipt['payment_mode'] == 'UPI' ? 'info' : 'primary');
                            ?>">
                                <?php echo htmlspecialchars($receipt['payment_mode']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if (!empty($receipt['transaction_id'])): ?>
                    <tr>
                        <th>Transaction / Cheque No:</th>
                        <td><?php echo htmlspecialchars($receipt['transaction_id']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Bank Name:</th>
                        <td><?php echo htmlspecialchars($receipt['bank_name'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Cheque Date:</th>
                        <td>
                            <?php
                            $chequeDate = $receipt['cheque_date'] ?? '';
                            echo !empty($chequeDate) ? htmlspecialchars(date('d-M-Y', strtotime($chequeDate))) : '-';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Amount:</th>
                        <td><strong class="text-success fs-5"><?php echo formatCurrency($receipt['total_amount']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Amount Paid:</th>
                        <td><strong class="text-success fs-5"><?php echo formatCurrency($receipt['amount_paid']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Collected By:</th>
                        <td><?php echo htmlspecialchars($receipt['collected_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td><?php echo date('d-M-Y h:i A', strtotime($receipt['created_at'])); ?></td>
                    </tr>
                    <?php if (!empty($receipt['remarks'])): ?>
                    <tr>
                        <th>Remarks:</th>
                        <td><?php echo nl2br(htmlspecialchars($receipt['remarks'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Information -->
    <div class="col-md-6">
        <div class="card dashboard-card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Student Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Student Name:</th>
                        <td><strong><?php echo htmlspecialchars($receipt['student_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Admission No:</th>
                        <td><?php echo htmlspecialchars($receipt['admission_no']); ?></td>
                    </tr>
                    <tr>
                        <th>Father's Name:</th>
                        <td><?php echo htmlspecialchars($receipt['father_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Mother's Name:</th>
                        <td><?php echo htmlspecialchars($receipt['mother_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Class:</th>
                        <td><?php echo htmlspecialchars($receipt['class_name'] . ' ' . $receipt['section_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Contact No:</th>
                        <td><?php echo htmlspecialchars($receipt['contact_no']); ?></td>
                    </tr>
                    <?php if (!empty($receipt['email'])): ?>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($receipt['email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Address:</th>
                        <td><?php echo nl2br(htmlspecialchars($receipt['address'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Fee Details -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Fee Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">S.No</th>
                                <th>Fee Head</th>
                                <th>Type</th>
                                <th>Period</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sno = 1;
                            $total = 0;
                            foreach ($feeDetails as $detail):
                                $period = !empty($detail['fee_month']) ? $detail['fee_month'] . ' ' . $detail['fee_year'] : '-';
                                $total += $detail['amount'];
                            ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td><strong><?php echo htmlspecialchars($detail['fee_head_name']); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $detail['fee_type'] == 'Monthly' ? 'primary' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars($detail['fee_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($period); ?></td>
                                <td class="text-end"><?php echo formatCurrency($detail['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-success">
                                <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                <td class="text-end"><strong><?php echo formatCurrency($total); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3">
                    <strong>Amount in Words:</strong>
                    <?php
                    require_once '../../includes/pdf_helper.php';
                    echo convertNumberToWords($receipt['amount_paid']) . ' Only';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h5 class="mb-3">Quick Actions</h5>
                <a href="receipt.php?id=<?php echo $receiptId; ?>" class="btn btn-primary btn-lg" target="_blank">
                    <i class="bi bi-printer"></i> Print Receipt
                </a>
                <?php if (!empty($whatsappUrl)): ?>
                <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" class="btn btn-success btn-lg" target="_blank">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                <?php endif; ?>
                <a href="<?php echo $receiptUrl; ?>" class="btn btn-outline-primary btn-lg" target="_blank">
                    <i class="bi bi-file-pdf"></i> PDF A4
                </a>
                <a href="<?php echo $pdfA5Url; ?>" class="btn btn-outline-secondary btn-lg" target="_blank">
                    <i class="bi bi-file-pdf"></i> PDF A5
                </a>
                <a href="collect.php?student_id=<?php echo $receipt['student_id']; ?>" class="btn btn-success btn-lg">
                    <i class="bi bi-cash-coin"></i> Collect More Fee
                </a>
                <a href="receipts.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-list"></i> All Receipts
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>
