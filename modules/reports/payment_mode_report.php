<?php
/**
 * Payment Mode Report
 * Fee collection grouped by payment method
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('reports', 'view');

$pageTitle = 'Payment Mode Report';
$currentUser = getCurrentUser();

// Get date filters
$fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : date('Y-m-d');

// Get payment mode statistics
$query = "SELECT
            payment_mode,
          COUNT(*) as total_receipts,
          SUM(amount_paid) as total_amount,
          MIN(amount_paid) as min_amount,
          MAX(amount_paid) as max_amount,
          AVG(amount_paid) as avg_amount
          FROM fee_receipts
          WHERE payment_date BETWEEN ? AND ? AND is_cancelled = 0
          GROUP BY payment_mode
          ORDER BY total_amount DESC";

$paymentStats = fetchAll($query, 'ss', [$fromDate, $toDate]);

// Calculate totals
$grandTotal = array_sum(array_column($paymentStats, 'total_amount'));
$totalReceipts = array_sum(array_column($paymentStats, 'total_receipts'));

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-credit-card"></i> Payment Mode Report
            </h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>
</div>

<!-- Date Filter -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <?php
    $colors = ['Cash' => 'success', 'Bank' => 'primary', 'UPI' => 'info', 'Cheque' => 'warning'];
    foreach ($paymentStats as $stat):
        $color = $colors[$stat['payment_mode']] ?? 'secondary';
    ?>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card text-white bg-<?php echo $color; ?>">
            <div class="card-body">
                <h6><?php echo htmlspecialchars($stat['payment_mode']); ?></h6>
                <h3><?php echo formatCurrency($stat['total_amount']); ?></h3>
                <p class="mb-0">
                    <?php echo $stat['total_receipts']; ?> Receipts
                    <span class="float-end">
                        <?php echo $grandTotal > 0 ? round(($stat['total_amount'] / $grandTotal) * 100, 1) : 0; ?>%
                    </span>
                </p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Detailed Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-table"></i> Payment Mode Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Payment Mode</th>
                                <th>Total Receipts</th>
                                <th>Total Amount</th>
                                <th>Min Amount</th>
                                <th>Max Amount</th>
                                <th>Avg Amount</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentStats as $stat): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $colors[$stat['payment_mode']] ?? 'secondary'; ?>">
                                        <?php echo htmlspecialchars($stat['payment_mode']); ?>
                                    </span>
                                </td>
                                <td><?php echo $stat['total_receipts']; ?></td>
                                <td class="text-success fw-bold"><?php echo formatCurrency($stat['total_amount']); ?></td>
                                <td><?php echo formatCurrency($stat['min_amount']); ?></td>
                                <td><?php echo formatCurrency($stat['max_amount']); ?></td>
                                <td><?php echo formatCurrency($stat['avg_amount']); ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $colors[$stat['payment_mode']] ?? 'secondary'; ?>"
                                             style="width: <?php echo $grandTotal > 0 ? ($stat['total_amount'] / $grandTotal) * 100 : 0; ?>%">
                                            <?php echo $grandTotal > 0 ? round(($stat['total_amount'] / $grandTotal) * 100, 1) : 0; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th>Total:</th>
                                <th><?php echo $totalReceipts; ?></th>
                                <th><?php echo formatCurrency($grandTotal); ?></th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
