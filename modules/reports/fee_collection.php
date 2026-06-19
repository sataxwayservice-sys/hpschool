<?php
/**
 * Fee Collection Report
 * Generate Excel/PDF report of fee collections
 */

// Include configuration
require_once '../../config/config.php';
require_once '../../includes/report_export.php';

// Require login
requireLogin();
requirePermission('reports', 'view');

$pageTitle = 'Fee Collection Report';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$schoolFilterClause = $currentSchoolId > 0 ? " AND s.school_id = ?" : "";

// Handle export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
    $toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';
    $paymentMode = isset($_GET['payment_mode']) ? sanitize($_GET['payment_mode']) : '';
    $classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

    // Build query
    $query = "SELECT
                fr.receipt_no, fr.payment_date, fr.payment_mode,
                fr.transaction_id, fr.bank_name, fr.cheque_date,
                fr.amount_paid, fr.remarks,
                s.student_name, s.admission_no, s.father_name, s.contact_no,
                c.class_name, sec.section_name,
                u.full_name as collected_by
              FROM fee_receipts fr
              JOIN students s ON fr.student_id = s.student_id
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              LEFT JOIN users u ON fr.collected_by = u.user_id
              WHERE fr.is_cancelled = 0";

    $params = [];
    $types = '';

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

    if (!empty($paymentMode)) {
        $query .= " AND fr.payment_mode = ?";
        $types .= 's';
        $params[] = $paymentMode;
    }

    if ($classId > 0) {
        $query .= " AND s.class_id = ?";
        $types .= 'i';
        $params[] = $classId;
    }

    if ($currentSchoolId > 0) {
        $query .= $schoolFilterClause;
        $types .= 'i';
        $params[] = $currentSchoolId;
    }

    $query .= " ORDER BY fr.payment_date DESC, fr.receipt_id DESC";

    $receipts = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    $schoolSettings = getSchoolSettings();

    // Generate Excel file
    $filename = "Fee_Collection_Report_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Add BOM for proper Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    reportExportWriteCsvHeaderRows(
        $output,
        $schoolSettings,
        'Fee Collection Report',
        date('d M Y, h:i A'),
        [
            'From Date' => !empty($fromDate) ? date('d-M-Y', strtotime($fromDate)) : 'All',
            'To Date' => !empty($toDate) ? date('d-M-Y', strtotime($toDate)) : 'All',
            'Payment Mode' => !empty($paymentMode) ? $paymentMode : 'All',
            'Class' => $classId > 0 ? ($classId . '') : 'All',
        ]
    );

    // Header row
    fputcsv($output, [
        'Receipt No',
        'Date',
        'Student Name',
        'Admission No',
        'Father Name',
        'Class',
        'Contact',
        'Amount',
        'Payment Mode',
        'Transaction / Cheque No.',
        'Bank Name',
        'Cheque Date',
        'Collected By',
        'Remarks'
    ]);

    // Data rows
    $totalAmount = 0;
    foreach ($receipts as $receipt) {
        fputcsv($output, [
            $receipt['receipt_no'],
            date('d-M-Y', strtotime($receipt['payment_date'])),
            $receipt['student_name'],
            $receipt['admission_no'],
            $receipt['father_name'],
            $receipt['class_name'] . ' ' . $receipt['section_name'],
            $receipt['contact_no'],
            number_format($receipt['amount_paid'], 2),
            $receipt['payment_mode'],
            $receipt['transaction_id'] ?? '-',
            $receipt['bank_name'] ?? '-',
            !empty($receipt['cheque_date']) ? date('d-M-Y', strtotime($receipt['cheque_date'])) : '-',
            $receipt['collected_by'],
            $receipt['remarks'] ?? '-'
        ]);
        $totalAmount += $receipt['amount_paid'];
    }

    // Total row
    fputcsv($output, ['', '', '', '', '', '', 'TOTAL:', number_format($totalAmount, 2), '', '', '', '', '', '']);

    fclose($output);
    exit();
}

// Get filter data
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-file-earmark-spreadsheet"></i> Fee Collection Report
            </h2>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Report Filters -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="reportForm">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date"
                                   value="<?php echo isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date"
                                   value="<?php echo isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" name="payment_mode">
                                <option value="">All</option>
                                <option value="Cash" <?php echo (isset($_GET['payment_mode']) && $_GET['payment_mode'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="Bank" <?php echo (isset($_GET['payment_mode']) && $_GET['payment_mode'] == 'Bank') ? 'selected' : ''; ?>>Bank</option>
                                <option value="UPI" <?php echo (isset($_GET['payment_mode']) && $_GET['payment_mode'] == 'UPI') ? 'selected' : ''; ?>>UPI</option>
                                <option value="Cheque" <?php echo (isset($_GET['payment_mode']) && $_GET['payment_mode'] == 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Class</label>
                            <select class="form-select" name="class_id">
                                <option value="">All</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Preview
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview and Export -->
<?php if (isset($_GET['from_date']) || isset($_GET['to_date'])): ?>
<?php
    // Get preview data
    $fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
    $toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';
    $paymentMode = isset($_GET['payment_mode']) ? sanitize($_GET['payment_mode']) : '';
    $classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

    $query = "SELECT
                fr.receipt_no, fr.payment_date, fr.payment_mode,
                fr.transaction_id, fr.bank_name, fr.cheque_date,
                fr.amount_paid,
                s.student_name, s.admission_no,
                c.class_name, sec.section_name
              FROM fee_receipts fr
              JOIN students s ON fr.student_id = s.student_id
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE fr.is_cancelled = 0";

    $params = [];
    $types = '';

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

    if (!empty($paymentMode)) {
        $query .= " AND fr.payment_mode = ?";
        $types .= 's';
        $params[] = $paymentMode;
    }

    if ($classId > 0) {
        $query .= " AND s.class_id = ?";
        $types .= 'i';
        $params[] = $classId;
    }

    if ($currentSchoolId > 0) {
        $query .= $schoolFilterClause;
        $types .= 'i';
        $params[] = $currentSchoolId;
    }

    $query .= " ORDER BY fr.payment_date DESC LIMIT 100";

    $previewData = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);

    $totalAmount = 0;
    foreach ($previewData as $row) {
        $totalAmount += $row['amount_paid'];
    }
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-eye"></i> Report Preview (First 100 records)</h5>
                <div>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-light btn-sm">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Total Records:</strong> <?php echo count($previewData); ?> receipts |
                    <strong>Total Amount:</strong> <?php echo formatCurrency($totalAmount); ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Class</th>
                                <th>Amount</th>
                                <th>Mode</th>
                                <th>Transaction / Cheque No.</th>
                                <th>Bank Name</th>
                                <th>Cheque Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['receipt_no']); ?></td>
                                <td><?php echo formatDate($row['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['admission_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['class_name'] . ' ' . $row['section_name']); ?></td>
                                <td><?php echo formatCurrency($row['amount_paid']); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $row['payment_mode'] == 'Cash' ? 'success' :
                                            ($row['payment_mode'] == 'UPI' ? 'info' : 'primary');
                                    ?>">
                                        <?php echo htmlspecialchars($row['payment_mode']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['transaction_id'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['bank_name'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $chequeDate = $row['cheque_date'] ?? '';
                                    echo !empty($chequeDate) ? htmlspecialchars(date('d-M-Y', strtotime($chequeDate))) : '-';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="5" class="text-end">Total:</th>
                                <th><?php echo formatCurrency($totalAmount); ?></th>
                                <th colspan="4"></th>
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
// Include footer
include '../../includes/footer.php';
?>
