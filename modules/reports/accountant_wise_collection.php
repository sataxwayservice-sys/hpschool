<?php
/**
 * Accountant-wise Collection Report
 * Fee collection grouped by collector
 */

require_once '../../config/config.php';
require_once '../../includes/report_export.php';

requireLogin();
requirePermission('reports', 'view');

$pageTitle = 'Accountant-wise Collection Report';
$currentUser = getCurrentUser();

$defaultFromDate = date('Y-m-01');
$defaultToDate = date('Y-m-d');

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : $defaultFromDate;
    $toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : $defaultToDate;
    $schoolSettings = getSchoolSettings();

    $query = "SELECT
                u.user_id,
                u.full_name as collector_name,
                u.username as collector_username,
                u.role as collector_role,
                COUNT(fr.receipt_id) as total_receipts,
                SUM(fr.amount_paid) as total_amount,
                MIN(fr.payment_date) as first_payment,
                MAX(fr.payment_date) as last_payment,
                AVG(fr.amount_paid) as avg_amount
              FROM fee_receipts fr
              LEFT JOIN users u ON fr.collected_by = u.user_id
              WHERE fr.payment_date BETWEEN ? AND ? AND fr.is_cancelled = 0
              GROUP BY u.user_id, u.full_name, u.username, u.role
              ORDER BY total_amount DESC, collector_name";

    $collectors = fetchAll($query, 'ss', [$fromDate, $toDate]);

    $filename = "Accountant_Wise_Collection_Report_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    reportExportWriteCsvHeaderRows(
        $output,
        $schoolSettings,
        'Accountant-wise Collection Report',
        date('d M Y, h:i A'),
        [
            'From Date' => date('d-M-Y', strtotime($fromDate)),
            'To Date' => date('d-M-Y', strtotime($toDate)),
        ]
    );

    fputcsv($output, [
        'S.No',
        'Collector Name',
        'Username',
        'Role',
        'Receipts',
        'Total Amount',
        'Average Amount',
        'First Collection',
        'Last Collection'
    ]);

    $sno = 1;
    $totalReceipts = 0;
    $grandTotal = 0;

    foreach ($collectors as $collector) {
        fputcsv($output, [
            $sno++,
            $collector['collector_name'] ?? 'Unknown',
            $collector['collector_username'] ?? '-',
            $collector['collector_role'] ?? '-',
            $collector['total_receipts'],
            number_format($collector['total_amount'], 2),
            number_format($collector['avg_amount'], 2),
            !empty($collector['first_payment']) ? date('d-M-Y', strtotime($collector['first_payment'])) : '-',
            !empty($collector['last_payment']) ? date('d-M-Y', strtotime($collector['last_payment'])) : '-'
        ]);

        $totalReceipts += $collector['total_receipts'];
        $grandTotal += $collector['total_amount'];
    }

    fputcsv($output, ['', '', '', 'TOTAL', $totalReceipts, number_format($grandTotal, 2), '', '', '']);

    fclose($output);
    exit();
}

$fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : $defaultFromDate;
$toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : $defaultToDate;

$query = "SELECT
            u.user_id,
            u.full_name as collector_name,
            u.username as collector_username,
            u.role as collector_role,
            COUNT(fr.receipt_id) as total_receipts,
            SUM(fr.amount_paid) as total_amount,
            MIN(fr.payment_date) as first_payment,
            MAX(fr.payment_date) as last_payment,
            AVG(fr.amount_paid) as avg_amount
          FROM fee_receipts fr
          LEFT JOIN users u ON fr.collected_by = u.user_id
          WHERE fr.payment_date BETWEEN ? AND ? AND fr.is_cancelled = 0
          GROUP BY u.user_id, u.full_name, u.username, u.role
          ORDER BY total_amount DESC, collector_name";

$collectorStats = fetchAll($query, 'ss', [$fromDate, $toDate]);

$grandTotal = array_sum(array_column($collectorStats, 'total_amount'));
$totalReceipts = array_sum(array_column($collectorStats, 'total_receipts'));

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-person-check"></i> Accountant-wise Collection Report
            </h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
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

<?php if (isset($_GET['from_date']) || isset($_GET['to_date'])): ?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h6>Total Collectors</h6>
                <h3><?php echo count($collectorStats); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h6>Total Receipts</h6>
                <h3><?php echo $totalReceipts; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h6>Total Amount</h6>
                <h3><?php echo formatCurrency($grandTotal); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-table"></i> Accountant-wise Collection Details</h5>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-file-earmark-excel"></i> Export to Excel
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="collectorReportTable" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>S.No</th>
                                <th>Collector</th>
                                <th>Role</th>
                                <th>Receipts</th>
                                <th>Total Amount</th>
                                <th>Average</th>
                                <th>First Collection</th>
                                <th>Last Collection</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sno = 1; foreach ($collectorStats as $collector): ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($collector['collector_name'] ?? 'Unknown'); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($collector['collector_username'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($collector['collector_role'] ?? '-'); ?></span>
                                </td>
                                <td><?php echo (int)$collector['total_receipts']; ?></td>
                                <td class="text-success fw-bold"><?php echo formatCurrency($collector['total_amount']); ?></td>
                                <td><?php echo formatCurrency($collector['avg_amount']); ?></td>
                                <td><?php echo !empty($collector['first_payment']) ? formatDate($collector['first_payment']) : '-'; ?></td>
                                <td><?php echo !empty($collector['last_payment']) ? formatDate($collector['last_payment']) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th><?php echo $totalReceipts; ?></th>
                                <th><?php echo formatCurrency($grandTotal); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$inlineScript = "
$('#collectorReportTable').DataTable({
    order: [[4, 'desc']],
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Accountant Wise Collection Report',
            exportOptions: { columns: [0,1,2,3,4,5,6,7] }
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Accountant Wise Collection Report'
        }
    ]
});
";

include '../../includes/footer.php';
