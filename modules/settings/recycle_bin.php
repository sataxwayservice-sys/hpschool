<?php
/**
 * Recycle Bin - Restore Deleted Data
 * Shows soft-deleted records and allows restoration
 */

require_once '../../config/config.php';
requireLogin();
requireRole(['super_admin', 'admin']);

$pageTitle = 'Recycle Bin';
$currentUser = getCurrentUser();
$error = '';
$success = '';

$conn = getDbConnection();

if (function_exists('ensureFeeModuleSchema')) {
    ensureFeeModuleSchema();
}

// Create deleted_items table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS deleted_items (
    deleted_id INT AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL,
    item_id INT NOT NULL,
    item_data TEXT NOT NULL,
    deleted_by INT NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255),
    INDEX idx_type (item_type),
    INDEX idx_deleted_at (deleted_at)
)";
$conn->query($createTable);

// Handle restore action
if (isset($_POST['restore_item'])) {
    $deletedId = intval($_POST['deleted_id']);

    // Get deleted item
    $item = fetchOne("SELECT * FROM deleted_items WHERE deleted_id = ?", 'i', [$deletedId]);

    if ($item) {
        $itemData = json_decode($item['item_data'], true);
        $itemType = $item['item_type'];

        try {
            beginTransaction();

            // Restore based on type
            switch ($itemType) {
                case 'student':
                    $restoreSchoolId = intval($itemData['school_id'] ?? 0);
                    if ($restoreSchoolId > 0) {
                        $studentLimit = getSchoolStudentAddLimit($restoreSchoolId);
                        $activeStudentCount = getSchoolActiveStudentCount($restoreSchoolId);
                        if ($studentLimit > 0 && $activeStudentCount >= $studentLimit) {
                            throw new Exception(
                                'Student admission limit reached for this school (' .
                                number_format($activeStudentCount) . '/' . number_format($studentLimit) .
                                '). Increase the limit from Super Admin before restoring more students.'
                            );
                        }
                    }

                    // Restore student
                    $query = "INSERT INTO students (student_id, admission_no, student_name, class_id, section_id, status, batch, guardian_name, contact_phone, email, address, date_of_birth, gender, religion, category, admission_date, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($query, 'issiisssssssssss', [
                        $itemData['student_id'], $itemData['admission_no'], $itemData['student_name'],
                        $itemData['class_id'], $itemData['section_id'], 'Active', $itemData['batch'],
                        $itemData['guardian_name'], $itemData['contact_phone'], $itemData['email'],
                        $itemData['address'], $itemData['date_of_birth'], $itemData['gender'],
                        $itemData['religion'], $itemData['category'], $itemData['admission_date'],
                        $itemData['created_at']
                    ]);
                    break;

                case 'fee_receipt':
                    // First restore the main receipt - uncancel it
                    $query = "UPDATE fee_receipts SET is_cancelled = 0, updated_at = NOW() WHERE receipt_id = ?";
                    $result = executeQuery($query, 'i', [$itemData['receipt_id']]);

                    // If the receipt doesn't exist, insert it
                    if ($result === false) {
                        // Build dynamic insert based on available columns
                        $conn = getDbConnection();
                        $columnsQuery = "SHOW COLUMNS FROM fee_receipts";
                        $columnsResult = $conn->query($columnsQuery);
                        $existingColumns = [];
                        while ($row = $columnsResult->fetch_assoc()) {
                            $existingColumns[] = $row['Field'];
                        }

                        $columns = ['receipt_id', 'receipt_no', 'student_id', 'total_amount', 'amount_paid', 'payment_mode', 'transaction_id', 'payment_date', 'collected_by', 'is_cancelled', 'created_at'];
                        $values = [$itemData['receipt_id'], $itemData['receipt_no'], $itemData['student_id'],
                                   $itemData['total_amount'], $itemData['amount_paid'], $itemData['payment_mode'],
                                   $itemData['transaction_id'], $itemData['payment_date'], $itemData['collected_by'], 0, $itemData['created_at']];
                        $types = 'isiddsssiis';

                        // Add optional columns if they exist
                        if (in_array('remarks', $existingColumns) && isset($itemData['remarks'])) {
                            $columns[] = 'remarks';
                            $values[] = $itemData['remarks'];
                            $types .= 's';
                        }

                        $columnStr = implode(', ', $columns);
                        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                        $insertQuery = "INSERT INTO fee_receipts ($columnStr) VALUES ($placeholders)";
                        executeQuery($insertQuery, $types, $values);

                        // Restore receipt details if they exist
                        if (isset($itemData['details']) && is_array($itemData['details'])) {
                            foreach ($itemData['details'] as $detail) {
                                $detailQuery = "INSERT INTO fee_receipt_details (receipt_id, fee_head_id, fee_month, fee_year, amount)
                                               VALUES (?, ?, ?, ?, ?)";
                                executeQuery($detailQuery, 'iissd', [
                                    $itemData['receipt_id'],
                                    $detail['fee_head_id'],
                                    $detail['fee_month'],
                                    $detail['fee_year'],
                                    $detail['amount']
                                ]);
                            }
                        }
                    }

                    break;

                case 'user':
                    // Restore user
                    $restoredPassword = $itemData['password'] ?? ($itemData['password_hash'] ?? '');
                    $restoredPasswordEncrypted = $itemData['password_encrypted'] ?? null;
                    $query = "INSERT INTO users (user_id, username, email, password, password_encrypted, full_name, role, is_active, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)";
                    executeQuery($query, 'isssssss', [
                        $itemData['user_id'], $itemData['username'], $itemData['email'],
                        $restoredPassword, $restoredPasswordEncrypted, $itemData['full_name'], $itemData['role'],
                        $itemData['created_at']
                    ]);
                    break;

                case 'fee_assignment':
                    // Restore fee assignment
                    $hasSchoolId = array_key_exists('school_id', $itemData);
                    $query = "INSERT INTO fee_structure (structure_id, student_id, fee_head_id, amount, effective_from, is_active, created_at";
                    $types = 'iiidss';
                    $values = [
                        $itemData['structure_id'], $itemData['student_id'], $itemData['fee_head_id'],
                        $itemData['amount'], $itemData['effective_from'], $itemData['created_at'] ?? date('Y-m-d H:i:s')
                    ];

                    if ($hasSchoolId) {
                        $query .= ", school_id";
                        $types .= 'i';
                        $values[] = intval($itemData['school_id']);
                    }

                    $query .= ") VALUES (?, ?, ?, ?, ?, 1, ?";
                    if ($hasSchoolId) {
                        $query .= ", ?";
                    }
                    $query .= ")";

                    executeQuery($query, $types, $values);
                    break;

                case 'fee_head':
                    // Restore fee head
                    $hasSchoolId = array_key_exists('school_id', $itemData);
                    $query = "INSERT INTO fee_heads (fee_head_id, fee_head_name, fee_type, display_order, is_active, created_at";
                    $types = 'issis';
                    $values = [
                        $itemData['fee_head_id'], $itemData['fee_head_name'], $itemData['fee_type'],
                        $itemData['display_order'], $itemData['created_at'] ?? date('Y-m-d H:i:s')
                    ];

                    if ($hasSchoolId) {
                        $query .= ", school_id";
                        $types .= 'i';
                        $values[] = intval($itemData['school_id']);
                    }

                    $query .= ") VALUES (?, ?, ?, ?, 1, ?";
                    if ($hasSchoolId) {
                        $query .= ", ?";
                    }
                    $query .= ")";

                    executeQuery($query, $types, $values);
                    break;

                case 'fee_receipt_permanent':
                    // Restore permanently deleted receipt
                    $conn = getDbConnection();
                    $columnsQuery = "SHOW COLUMNS FROM fee_receipts";
                    $columnsResult = $conn->query($columnsQuery);
                    $existingColumns = [];
                    while ($row = $columnsResult->fetch_assoc()) {
                        $existingColumns[] = $row['Field'];
                    }

                    $columns = ['receipt_id', 'receipt_no', 'student_id', 'total_amount', 'amount_paid', 'payment_mode', 'transaction_id', 'payment_date', 'collected_by', 'is_cancelled', 'created_at'];
                    $values = [$itemData['receipt_id'], $itemData['receipt_no'], $itemData['student_id'],
                               $itemData['total_amount'] ?? $itemData['amount_paid'], $itemData['amount_paid'], $itemData['payment_mode'],
                               $itemData['transaction_id'] ?? null, $itemData['payment_date'], $itemData['collected_by'] ?? null, 0, $itemData['created_at']];
                    $types = 'isiddsssiis';

                    if (in_array('remarks', $existingColumns) && isset($itemData['remarks'])) {
                        $columns[] = 'remarks';
                        $values[] = $itemData['remarks'];
                        $types .= 's';
                    }

                    $columnStr = implode(', ', $columns);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $insertQuery = "INSERT INTO fee_receipts ($columnStr) VALUES ($placeholders)";
                    executeQuery($insertQuery, $types, $values);

                    // Restore receipt details
                    if (isset($itemData['details']) && is_array($itemData['details'])) {
                        foreach ($itemData['details'] as $detail) {
                            $detailQuery = "INSERT INTO fee_receipt_details (receipt_id, fee_head_id, fee_month, fee_year, amount)
                                           VALUES (?, ?, ?, ?, ?)";
                            executeQuery($detailQuery, 'iissd', [
                                $itemData['receipt_id'],
                                $detail['fee_head_id'],
                                $detail['fee_month'] ?? null,
                                $detail['fee_year'] ?? null,
                                $detail['amount']
                            ]);
                        }
                    }
                    break;
            }

            // Delete from recycle bin
            executeQuery("DELETE FROM deleted_items WHERE deleted_id = ?", 'i', [$deletedId]);

            commitTransaction();

            $success = ucfirst($itemType) . " restored successfully!";
            logActivity($currentUser['user_id'], 'Restore', ucfirst($itemType), "Restored {$itemType} ID: {$item['item_id']}");

        } catch (Exception $e) {
            rollbackTransaction();
            $error = "Failed to restore item: " . $e->getMessage();
        }
    }
}

// Handle permanent delete
if (isset($_POST['permanent_delete'])) {
    $deletedId = intval($_POST['deleted_id']);

    $item = fetchOne("SELECT * FROM deleted_items WHERE deleted_id = ?", 'i', [$deletedId]);

    if ($item) {
        executeQuery("DELETE FROM deleted_items WHERE deleted_id = ?", 'i', [$deletedId]);
        $success = "Item permanently deleted from recycle bin.";
        logActivity($currentUser['user_id'], 'Permanent Delete', 'Recycle Bin', "Permanently deleted {$item['item_type']} ID: {$item['item_id']}");
    }
}

// Handle empty recycle bin
if (isset($_POST['empty_bin'])) {
    $conn->query("TRUNCATE TABLE deleted_items");
    $success = "Recycle bin emptied successfully!";
    logActivity($currentUser['user_id'], 'Empty', 'Recycle Bin', "Emptied entire recycle bin");
}

// Get filter
$filterType = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';

// Get deleted items
if ($filterType == 'fee_receipt') {
    // Include both cancelled and permanently deleted receipts
    $whereClause = "WHERE item_type IN ('fee_receipt', 'fee_receipt_permanent')";
} elseif ($filterType != 'all') {
    $whereClause = "WHERE item_type = '$filterType'";
} else {
    $whereClause = '';
}

$deletedItems = $conn->query("SELECT di.*, u.full_name as deleted_by_name
                               FROM deleted_items di
                               LEFT JOIN users u ON di.deleted_by = u.user_id
                               $whereClause
                               ORDER BY di.deleted_at DESC");

// Get counts by type
$studentCount = $conn->query("SELECT COUNT(*) as count FROM deleted_items WHERE item_type = 'student'")->fetch_assoc()['count'];
$receiptCount = $conn->query("SELECT COUNT(*) as count FROM deleted_items WHERE item_type IN ('fee_receipt', 'fee_receipt_permanent')")->fetch_assoc()['count'];
$userCount = $conn->query("SELECT COUNT(*) as count FROM deleted_items WHERE item_type = 'user'")->fetch_assoc()['count'];
$assignmentCount = $conn->query("SELECT COUNT(*) as count FROM deleted_items WHERE item_type = 'fee_assignment'")->fetch_assoc()['count'];
$feeHeadCount = $conn->query("SELECT COUNT(*) as count FROM deleted_items WHERE item_type = 'fee_head'")->fetch_assoc()['count'];
$totalCount = $conn->query("SELECT COUNT(*) as count FROM deleted_items")->fetch_assoc()['count'];

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-trash"></i> Recycle Bin</h2>
            <div>
                <?php if ($totalCount > 0): ?>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#emptyBinModal">
                        <i class="bi bi-trash3"></i> Empty Recycle Bin
                    </button>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>/modules/settings/school.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Settings
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

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $totalCount; ?></h3>
                <p class="mb-0">Total Items</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?php echo $studentCount; ?></h3>
                <p class="mb-0">Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo $receiptCount; ?></h3>
                <p class="mb-0">Receipts</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?php echo $userCount; ?></h3>
                <p class="mb-0">Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $assignmentCount; ?></h3>
                <p class="mb-0">Assignments</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-secondary"><?php echo $feeHeadCount; ?></h3>
                <p class="mb-0">Fee Heads</p>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo $filterType == 'all' ? 'active' : ''; ?>" href="?type=all">
            All Items (<?php echo $totalCount; ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filterType == 'student' ? 'active' : ''; ?>" href="?type=student">
            Students (<?php echo $studentCount; ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($filterType == 'fee_receipt' || $filterType == 'fee_receipt_permanent') ? 'active' : ''; ?>" href="?type=fee_receipt">
            Receipts (<?php echo $receiptCount; ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filterType == 'fee_assignment' ? 'active' : ''; ?>" href="?type=fee_assignment">
            Fee Assignments (<?php echo $assignmentCount; ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filterType == 'fee_head' ? 'active' : ''; ?>" href="?type=fee_head">
            Fee Heads (<?php echo $feeHeadCount; ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filterType == 'user' ? 'active' : ''; ?>" href="?type=user">
            Users (<?php echo $userCount; ?>)
        </a>
    </li>
</ul>

<!-- Deleted Items Table -->
<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-archive"></i> Deleted Items</h5>
    </div>
    <div class="card-body">
        <?php if ($totalCount == 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-trash" style="font-size: 4rem; color: #ccc;"></i>
                <h4 class="mt-3 text-muted">Recycle Bin is Empty</h4>
                <p class="text-muted">Deleted items will appear here and stay until you restore them or delete them manually from this page.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Item Details</th>
                            <th>Deleted By</th>
                            <th>Deleted On</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $deletedItems->fetch_assoc()):
                            $itemData = json_decode($item['item_data'], true);
                            $itemDetails = '';

                            switch ($item['item_type']) {
                                case 'student':
                                    $itemDetails = '<strong>' . htmlspecialchars($itemData['student_name']) . '</strong><br>' .
                                                  '<small class="text-muted">Admission: ' . htmlspecialchars($itemData['admission_no']) . '</small>';
                                    $badge = 'bg-info';
                                    $icon = 'bi-person';
                                    break;
                                case 'fee_receipt':
                                    $itemDetails = '<strong>Receipt #' . htmlspecialchars($itemData['receipt_no']) . '</strong><br>' .
                                                  '<small class="text-muted">Amount: ₹' . number_format($itemData['total_amount'] ?? $itemData['amount_paid'], 2) . '</small>';
                                    $badge = 'bg-success';
                                    $icon = 'bi-receipt';
                                    break;
                                case 'fee_receipt_permanent':
                                    $itemDetails = '<strong>Receipt #' . htmlspecialchars($itemData['receipt_no']) . '</strong> <span class="badge bg-danger">Permanently Deleted</span><br>' .
                                                  '<small class="text-muted">Amount: ₹' . number_format($itemData['amount_paid'], 2) . '</small>';
                                    $badge = 'bg-danger';
                                    $icon = 'bi-receipt-cutoff';
                                    break;
                                case 'fee_assignment':
                                    $itemDetails = '<strong>' . htmlspecialchars($itemData['fee_head_name']) . '</strong><br>' .
                                                  '<small class="text-muted">Student: ' . htmlspecialchars($itemData['student_name']) . ' | Amount: ₹' . number_format($itemData['amount'], 2) . '</small>';
                                    $badge = 'bg-primary';
                                    $icon = 'bi-cash-stack';
                                    break;
                                case 'fee_head':
                                    $itemDetails = '<strong>' . htmlspecialchars($itemData['fee_head_name']) . '</strong><br>' .
                                                  '<small class="text-muted">Type: ' . htmlspecialchars($itemData['fee_type']) . '</small>';
                                    $badge = 'bg-secondary';
                                    $icon = 'bi-tag';
                                    break;
                                case 'user':
                                    $itemDetails = '<strong>' . htmlspecialchars($itemData['full_name']) . '</strong><br>' .
                                                  '<small class="text-muted">' . htmlspecialchars($itemData['email']) . '</small>';
                                    $badge = 'bg-warning';
                                    $icon = 'bi-person-badge';
                                    break;
                                default:
                                    $itemDetails = 'Unknown item';
                                    $badge = 'bg-secondary';
                                    $icon = 'bi-question';
                            }
                        ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $badge; ?>">
                                        <i class="<?php echo $icon; ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $item['item_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $itemDetails; ?></td>
                                <td><?php echo htmlspecialchars($item['deleted_by_name'] ?? 'Unknown'); ?></td>
                                <td>
                                    <?php
                                    $deletedDate = new DateTime($item['deleted_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($deletedDate);

                                    if ($diff->days == 0) {
                                        echo '<span class="text-danger">Today, ' . $deletedDate->format('h:i A') . '</span>';
                                    } elseif ($diff->days == 1) {
                                        echo 'Yesterday, ' . $deletedDate->format('h:i A');
                                    } else {
                                        echo $deletedDate->format('d M Y, h:i A');
                                    }

                                    echo '<br><small class="text-muted">Kept until manually deleted</small>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['reason'] ?? '-'); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="deleted_id" value="<?php echo $item['deleted_id']; ?>">
                                        <button type="submit" name="restore_item" class="btn btn-sm btn-success"
                                                onclick="return confirm('Restore this <?php echo $item['item_type']; ?>?');">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                    </form>

                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                            data-bs-target="#deleteModal<?php echo $item['deleted_id']; ?>">
                                        <i class="bi bi-trash3"></i> Delete
                                    </button>

                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $item['deleted_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Permanent Delete</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-warning">
                                                        <i class="bi bi-exclamation-triangle"></i> <strong>Warning!</strong> This action cannot be undone.
                                                    </div>
                                                    <p>Are you sure you want to permanently delete this item?</p>
                                                    <p><strong>Type:</strong> <?php echo ucfirst($item['item_type']); ?><br>
                                                       <?php echo strip_tags($itemDetails); ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="deleted_id" value="<?php echo $item['deleted_id']; ?>">
                                                        <button type="submit" name="permanent_delete" class="btn btn-danger">
                                                            <i class="bi bi-trash3"></i> Delete Permanently
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Empty Bin Modal -->
<div class="modal fade" id="emptyBinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash3"></i> Empty Recycle Bin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Danger!</strong> This will permanently delete ALL items in the recycle bin.
                </div>
                <p>Are you absolutely sure? This action cannot be undone!</p>
                <p><strong><?php echo $totalCount; ?></strong> items will be permanently deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <button type="submit" name="empty_bin" class="btn btn-danger">
                        <i class="bi bi-trash3"></i> Empty Recycle Bin
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle"></i> <strong>Note:</strong> Deleted items stay in the recycle bin until you restore them or delete them manually from this page.
</div>

<?php include '../../includes/footer.php'; ?>
