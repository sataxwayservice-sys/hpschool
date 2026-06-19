<?php
/**
 * Fee Heads Management
 * Manage different types of fees (Tuition, Admission, Transport, etc.)
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('settings', 'edit');

$pageTitle = 'Fee Heads Management';
$currentUser = getCurrentUser();

// Handle delete
if (isset($_GET['delete']) && hasPermission('settings', 'delete')) {
    $feeHeadId = intval($_GET['delete']);

    // Check if fee head is used in fee structure (only active assignments)
    $usageCount = fetchOne("SELECT COUNT(*) as count FROM fee_structure WHERE fee_head_id = ? AND is_active = 1", 'i', [$feeHeadId]);

    if ($usageCount['count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete fee head. It is assigned to {$usageCount['count']} student(s).";
    } else {
        // Check if fee head has been used in any ACTIVE (non-cancelled) receipts
        $receiptCount = fetchOne("SELECT COUNT(*) as count
                                  FROM fee_receipt_details frd
                                  JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                                  WHERE frd.fee_head_id = ? AND fr.is_cancelled = 0", 'i', [$feeHeadId]);

        if ($receiptCount['count'] > 0) {
            $_SESSION['error_message'] = "Cannot delete fee head. It has been used in {$receiptCount['count']} active receipt(s). Deleting it would corrupt financial records.";
        } else {
            // Use soft delete to save to recycle bin
            $deleted = softDeleteFeeHead($feeHeadId, "Deleted via fee heads management");

            if ($deleted) {
                logActivity($currentUser['user_id'], 'Fee Head Deleted', 'settings', "Deleted fee head ID: $feeHeadId");
                $_SESSION['success_message'] = "Fee head deleted successfully! (Saved to recycle bin for recovery)";
            } else {
                $_SESSION['error_message'] = "Failed to delete fee head! Check error logs for details.";
            }
        }
    }
    header("Location: fee_heads.php");
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feeHeadId = isset($_POST['fee_head_id']) ? intval($_POST['fee_head_id']) : 0;
    $feeHeadName = sanitize($_POST['fee_head_name']);
    $feeType = sanitize($_POST['fee_type']);
    $displayOrder = intval($_POST['display_order']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($feeHeadName)) {
        $_SESSION['error_message'] = "Fee head name is required!";
    } elseif (empty($feeType)) {
        $_SESSION['error_message'] = "Fee type is required!";
    } else {
        if ($feeHeadId > 0) {
            // Update
            $query = "UPDATE fee_heads
                     SET fee_head_name = ?, fee_type = ?, display_order = ?, is_active = ?
                     WHERE fee_head_id = ?";
            if (executeQuery($query, 'ssiii', [$feeHeadName, $feeType, $displayOrder, $isActive, $feeHeadId])) {
                logActivity($currentUser['user_id'], 'Fee Head Updated', 'settings', "Updated fee head: $feeHeadName");
                $_SESSION['success_message'] = "Fee head updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update fee head!";
            }
        } else {
            // Insert
            $query = "INSERT INTO fee_heads (fee_head_name, fee_type, display_order, is_active)
                     VALUES (?, ?, ?, ?)";
            if (executeQuery($query, 'ssii', [$feeHeadName, $feeType, $displayOrder, $isActive])) {
                logActivity($currentUser['user_id'], 'Fee Head Added', 'settings', "Added new fee head: $feeHeadName");
                $_SESSION['success_message'] = "Fee head added successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to add fee head!";
            }
        }
    }
    header("Location: fee_heads.php");
    exit();
}

// Get all fee heads with usage count
$feeHeads = fetchAll("SELECT fh.*,
                     COUNT(DISTINCT fs.student_id) as student_count,
                     COALESCE(SUM(fs.amount), 0) as total_amount
                     FROM fee_heads fh
                     LEFT JOIN fee_structure fs ON fh.fee_head_id = fs.fee_head_id AND fs.is_active = 1
                     GROUP BY fh.fee_head_id
                     ORDER BY fh.display_order");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-list-ul"></i> Fee Heads Management
            </h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeHeadModal" onclick="clearForm()">
                    <i class="bi bi-plus-circle"></i> Add New Fee Head
                </button>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h6>Total Fee Heads</h6>
                <h3><?php echo count($feeHeads); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h6>Active Fee Heads</h6>
                <h3><?php echo count(array_filter($feeHeads, fn($f) => $f['is_active'] == 1)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h6>Monthly Fees</h6>
                <h3><?php echo count(array_filter($feeHeads, fn($f) => $f['fee_type'] == 'Monthly')); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h6>One-time Fees</h6>
                <h3><?php echo count(array_filter($feeHeads, fn($f) => $f['fee_type'] == 'One-time')); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Fee Heads Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Fee Heads</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Fee Head Name</th>
                                <th>Type</th>
                                <th>Students Assigned</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeHeads as $feeHead): ?>
                            <tr>
                                <td><?php echo $feeHead['display_order']; ?></td>
                                <td><strong><?php echo htmlspecialchars($feeHead['fee_head_name']); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $feeHead['fee_type'] == 'Monthly' ? 'primary' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars($feeHead['fee_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $feeHead['student_count']; ?> students</span>
                                </td>
                                <td>
                                    <strong class="text-success"><?php echo formatCurrency($feeHead['total_amount']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($feeHead['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning"
                                            onclick="editFeeHead(<?php echo htmlspecialchars(json_encode($feeHead)); ?>)"
                                            data-bs-toggle="modal"
                                            data-bs-target="#feeHeadModal">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ($feeHead['student_count'] == 0): ?>
                                    <a href="?delete=<?php echo $feeHead['fee_head_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this fee head?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Common Fee Types Info -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Common Fee Types</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">📅 Monthly Fees (Recurring):</h6>
                        <ul>
                            <li>Tuition Fee</li>
                            <li>Transport Fee</li>
                            <li>Computer Fee</li>
                            <li>Library Fee</li>
                            <li>Sports Fee</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-secondary">💰 One-time Fees:</h6>
                        <ul>
                            <li>Admission Fee</li>
                            <li>Registration Fee</li>
                            <li>Caution Money</li>
                            <li>Annual Charges</li>
                            <li>Exam Fee</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="feeHeadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Fee Head</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="fee_head_id" id="fee_head_id">

                    <div class="mb-3">
                        <label class="form-label">Fee Head Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="fee_head_name" id="fee_head_name" required>
                        <small class="text-muted">e.g., Tuition Fee, Admission Fee, Transport Fee</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Fee Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="fee_type" id="fee_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Monthly">Monthly (Recurring)</option>
                            <option value="One-time">One-time</option>
                            <option value="Annual">Annual</option>
                            <option value="Quarterly">Quarterly</option>
                        </select>
                        <small class="text-muted">Monthly fees are collected every month</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Display Order <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="display_order" id="display_order"
                               value="<?php echo count($feeHeads) + 1; ?>" required>
                        <small class="text-muted">Lower number appears first</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                <strong>Active</strong>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Fee Head
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineScript = "
function clearForm() {
    $('#modalTitle').text('Add New Fee Head');
    $('#fee_head_id').val('');
    $('#fee_head_name').val('');
    $('#fee_type').val('');
    $('#display_order').val(" . (count($feeHeads) + 1) . ");
    $('#is_active').prop('checked', true);
}

function editFeeHead(feeHeadData) {
    $('#modalTitle').text('Edit Fee Head');
    $('#fee_head_id').val(feeHeadData.fee_head_id);
    $('#fee_head_name').val(feeHeadData.fee_head_name);
    $('#fee_type').val(feeHeadData.fee_type);
    $('#display_order').val(feeHeadData.display_order);
    $('#is_active').prop('checked', feeHeadData.is_active == 1);
}
";

// Include footer
include '../../includes/footer.php';
?>
