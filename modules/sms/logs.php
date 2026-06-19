<?php
/**
 * SMS Logs
 * View all SMS logs with filters
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('sms', 'view');

$pageTitle = 'SMS Logs';
$currentUser = getCurrentUser();

// Get filter parameters
$fromDate = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$searchPhone = isset($_GET['search_phone']) ? sanitize($_GET['search_phone']) : '';

// Build query
$query = "SELECT sl.*, u.full_name as sent_by_name
          FROM sms_logs sl
          LEFT JOIN users u ON sl.sent_by = u.user_id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($fromDate)) {
    $query .= " AND DATE(sl.sent_at) >= ?";
    $types .= 's';
    $params[] = $fromDate;
}

if (!empty($toDate)) {
    $query .= " AND DATE(sl.sent_at) <= ?";
    $types .= 's';
    $params[] = $toDate;
}

if (!empty($status)) {
    $query .= " AND sl.status = ?";
    $types .= 's';
    $params[] = $status;
}

if (!empty($searchPhone)) {
    $query .= " AND sl.phone_number LIKE ?";
    $types .= 's';
    $params[] = '%' . $searchPhone . '%';
}

$query .= " ORDER BY sl.sent_at DESC";

$logs = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-clock-history"></i> SMS Logs
            </h2>
            <div>
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-send"></i> Send SMS
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
                <h5 class="mb-3"><i class="bi bi-funnel"></i> Filter Logs</h5>
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date"
                                   value="<?php echo htmlspecialchars($fromDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date"
                                   value="<?php echo htmlspecialchars($toDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="Sent" <?php echo $status == 'Sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="Failed" <?php echo $status == 'Failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="search_phone"
                                   value="<?php echo htmlspecialchars($searchPhone); ?>"
                                   placeholder="Search phone">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h5>Total SMS</h5>
                <h3><?php echo count($logs); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Sent</h5>
                <h3><?php echo count(array_filter($logs, fn($l) => $l['status'] == 'Sent')); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-danger">
            <div class="card-body text-center">
                <h5>Failed</h5>
                <h3><?php echo count(array_filter($logs, fn($l) => $l['status'] == 'Failed')); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h5>Success Rate</h5>
                <h3><?php
                    $total = count($logs);
                    $sent = count(array_filter($logs, fn($l) => $l['status'] == 'Sent'));
                    echo $total > 0 ? round(($sent / $total) * 100, 1) . '%' : '0%';
                ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="logsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>Phone Number</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Sent By</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['sms_log_id']; ?></td>
                                    <td><?php echo date('d-M-Y h:i A', strtotime($log['sent_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                    <td>
                                        <span class="text-truncate" style="max-width: 300px; display: inline-block;"
                                              title="<?php echo htmlspecialchars($log['message']); ?>">
                                            <?php echo htmlspecialchars($log['message']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $log['status'] == 'Sent' ? 'success' : 'danger'; ?>">
                                            <?php echo htmlspecialchars($log['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['sent_by_name'] ?? 'System'); ?></td>
                                    <td>
                                        <?php if (!empty($log['error_message'])): ?>
                                            <small class="text-danger"><?php echo htmlspecialchars($log['error_message']); ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-info-circle"></i> No SMS logs found!
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
// Initialize DataTables
$('#logsTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 50,
    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'SMS Logs Report',
            exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6]
            }
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'SMS Logs Report'
        }
    ]
});
";

// Include footer
include '../../includes/footer.php';
?>
