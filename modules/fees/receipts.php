<?php
/**
 * Fee Receipts List
 * View all fee receipts with search and filter
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'view');

$pageTitle = 'Fee Receipts';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Get filter parameters
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';
$paymentMode = isset($_GET['payment_mode']) ? sanitize($_GET['payment_mode']) : '';
$showCancelled = isset($_GET['show_cancelled']) ? intval($_GET['show_cancelled']) : 0;

// Build query
$query = "SELECT
            fr.*,
            s.student_name, s.admission_no,
            c.class_name, c.class_id, sec.section_name, sec.section_id,
            u.full_name as collected_by_name
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          JOIN classes c ON s.class_id = c.class_id
          JOIN sections sec ON s.section_id = sec.section_id
          LEFT JOIN users u ON fr.collected_by = u.user_id
          WHERE 1=1";

if ($currentSchoolId > 0) {
    $query .= " AND s.school_id = ?";
    $schoolFilterType = 'i';
    $schoolFilterParams = [$currentSchoolId];
} else {
    $schoolFilterType = '';
    $schoolFilterParams = [];
}

// Hide cancelled receipts by default
if (!$showCancelled) {
    $query .= " AND fr.is_cancelled = 0";
}

$params = [];
$types = '';

// Apply search filter
if (!empty($searchTerm)) {
    $query .= " AND (fr.receipt_no LIKE ? OR s.student_name LIKE ? OR s.admission_no LIKE ?)";
    $types .= 'sss';
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
}

// Apply date filters
if (!empty($fromDate)) {
    $query .= " AND fr.payment_date >= ?";
    $types .= 's';
    $params[] = $fromDate;
}

if (!empty($toDate)) {
    $query .= " AND fr.payment_date <= ?";
    $types .= 's';
    $params[] = $toDate;
}

// Apply payment mode filter
if (!empty($paymentMode)) {
    $query .= " AND fr.payment_mode = ?";
    $types .= 's';
    $params[] = $paymentMode;
}

$query .= " ORDER BY fr.receipt_id DESC";

$paramsForQuery = $params;
$typesForQuery = $types;
if ($currentSchoolId > 0) {
    $typesForQuery .= $schoolFilterType;
    $paramsForQuery = array_merge($paramsForQuery, $schoolFilterParams);
}

// Fetch receipts
$receipts = empty($typesForQuery) ? fetchAll($query) : fetchAll($query, $typesForQuery, $paramsForQuery);

// Calculate totals (exclude cancelled receipts)
$totalAmount = 0;
$activeReceiptsCount = 0;
$cancelledReceiptsCount = 0;
foreach ($receipts as $receipt) {
    if (!$receipt['is_cancelled']) {
        $totalAmount += $receipt['amount_paid'];
        $activeReceiptsCount++;
    } else {
        $cancelledReceiptsCount++;
    }
}

// Get total cancelled receipts count (for bulk delete button)
$totalCancelledQuery = "SELECT COUNT(*) as count FROM fee_receipts fr JOIN students s ON fr.student_id = s.student_id WHERE fr.is_cancelled = 1";
$totalCancelledParams = [];
$totalCancelledTypes = '';
if ($currentSchoolId > 0) {
    $totalCancelledQuery .= " AND s.school_id = ?";
    $totalCancelledParams[] = $currentSchoolId;
    $totalCancelledTypes .= 'i';
}
$totalCancelled = empty($totalCancelledTypes) ? fetchOne($totalCancelledQuery) : fetchOne($totalCancelledQuery, $totalCancelledTypes, $totalCancelledParams);
$totalCancelledCount = $totalCancelled['count'] ?? 0;

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-receipt-cutoff"></i> Fee Receipts
            </h2>
            <div>
                <?php if ($totalCancelledCount > 0 && hasPermission('fees', 'delete')): ?>
                <a href="bulk_delete_cancelled.php" class="btn btn-danger">
                    <i class="bi bi-trash3"></i> Delete All Cancelled (<?php echo $totalCancelledCount; ?>)
                </a>
                <?php endif; ?>
                <a href="collect.php" class="btn btn-success">
                    <i class="bi bi-cash-coin"></i> Collect Fee
                </a>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-funnel"></i> Search & Filter</h5>
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search"
                                   placeholder="Receipt No / Student Name"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="admission_no"
                                   value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date"
                                   value="<?php echo htmlspecialchars($fromDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date"
                                   value="<?php echo htmlspecialchars($toDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" name="payment_mode">
                                <option value="">All</option>
                                <option value="Cash" <?php echo $paymentMode == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="Bank" <?php echo $paymentMode == 'Bank' ? 'selected' : ''; ?>>Bank</option>
                                <option value="UPI" <?php echo $paymentMode == 'UPI' ? 'selected' : ''; ?>>UPI</option>
                                <option value="Cheque" <?php echo $paymentMode == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Show Cancelled</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="show_cancelled" value="1" id="showCancelledSwitch" <?php echo $showCancelled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showCancelledSwitch">
                                    Include Cancelled
                                </label>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12 mt-2">
                            <a href="receipts.php" class="btn btn-sm btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear All Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body">
                <h5><i class="bi bi-receipt"></i> <?php echo $showCancelled ? 'Total Receipts' : 'Active Receipts'; ?></h5>
                <h3><?php echo $showCancelled ? count($receipts) : $activeReceiptsCount; ?></h3>
                <?php if ($showCancelled && $activeReceiptsCount < count($receipts)): ?>
                    <small><?php echo (count($receipts) - $activeReceiptsCount); ?> cancelled</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body">
                <h5><i class="bi bi-cash-stack"></i> Total Amount</h5>
                <h3><?php echo formatCurrency($totalAmount); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body">
                <h5><i class="bi bi-calendar-check"></i> Date Range</h5>
                <h6><?php echo !empty($fromDate) ? date('d-M-Y', strtotime($fromDate)) : 'All'; ?> to <?php echo !empty($toDate) ? date('d-M-Y', strtotime($toDate)) : 'All'; ?></h6>
            </div>
        </div>
    </div>
</div>

<!-- Receipts Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="receiptsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Student Name</th>
                                <th>Admission No</th>
                                <th>Class</th>
                                <th>Amount</th>
                                <th>Payment Mode</th>
                                <th>Transaction / Cheque No.</th>
                                <th>Bank Name</th>
                                <th>Cheque Date</th>
                                <th>Collected By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($receipts) > 0): ?>
                                <?php foreach ($receipts as $receipt): ?>
                                <tr <?php echo $receipt['is_cancelled'] ? 'class="table-secondary" style="text-decoration: line-through; opacity: 0.6;"' : ''; ?>>
                                    <td>
                                        <strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong>
                                        <?php if ($receipt['is_cancelled']): ?>
                                            <br><span class="badge bg-danger">CANCELLED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($receipt['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['admission_no']); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['class_name'] . ' ' . $receipt['section_name']); ?></td>
                                    <td>
                                        <strong class="text-success">
                                            <?php echo formatCurrency($receipt['amount_paid']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $receipt['payment_mode'] == 'Cash' ? 'success' :
                                                ($receipt['payment_mode'] == 'UPI' ? 'info' : 'primary');
                                        ?>">
                                            <?php echo htmlspecialchars($receipt['payment_mode']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($receipt['transaction_id'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['bank_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $chequeDate = $receipt['cheque_date'] ?? '';
                                        echo !empty($chequeDate) ? htmlspecialchars(date('d-M-Y', strtotime($chequeDate))) : '-';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($receipt['collected_by_name']); ?></td>
                                    <td>
                                        <?php if ($receipt['is_cancelled']): ?>
                                            <div class="btn-group" role="group">
                                                <span class="badge bg-danger me-2">CANCELLED</span>
                                                <?php if (hasPermission('fees', 'delete')): ?>
                                                <a href="permanent_delete_receipt.php?id=<?php echo $receipt['receipt_id']; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   title="Permanently Delete">
                                                    <i class="bi bi-trash3"></i> Delete Permanently
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                        <div class="btn-group" role="group">
                                            <a href="pdf_receipt.php?id=<?php echo $receipt['receipt_id']; ?>"
                                               class="btn btn-sm btn-success" target="_blank" title="View Receipt">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="pdf_receipt.php?id=<?php echo $receipt['receipt_id']; ?>&print=auto"
                                               class="btn btn-sm btn-primary" target="_blank" title="Print Receipt">
                                                <i class="bi bi-printer"></i> Print
                                            </a>
                                            <a href="due.php?class_id=<?php echo $receipt['class_id']; ?>&section_id=<?php echo $receipt['section_id']; ?>"
                                               class="btn btn-sm btn-secondary" title="View Due Fees">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </a>
                                            <a href="view_receipt_details.php?id=<?php echo $receipt['receipt_id']; ?>"
                                               class="btn btn-sm btn-info" title="View Details">
                                                <i class="bi bi-info-circle"></i>
                                            </a>
                                            <?php if (hasPermission('fees', 'edit')): ?>
                                            <a href="edit_receipt.php?id=<?php echo $receipt['receipt_id']; ?>"
                                               class="btn btn-sm btn-warning" title="Edit Receipt">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('fees', 'delete')): ?>
                                            <a href="delete_receipt.php?id=<?php echo $receipt['receipt_id']; ?>"
                                               class="btn btn-sm btn-dark" title="Cancel Receipt"
                                               onclick="return confirm('Are you sure you want to cancel this receipt?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($receipts) === 0): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> No receipts found!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
// Initialize DataTables
$('#receiptsTable').DataTable({
    order: [[1, 'desc']], // Sort by date descending
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Fee Receipts Report',
            exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
            }
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Fee Receipts Report',
            exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
            }
        }
    ]
});
";

// Include footer
include '../../includes/footer.php';
?>
