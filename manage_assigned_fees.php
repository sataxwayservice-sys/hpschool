<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();
requirePermission('fees', 'manage');

$pageTitle = 'Manage Assigned Fees';
$currentUser = getCurrentUser();

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$success = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    $structureId = intval($_POST['structure_id']);

    try {
        // Use soft delete to save to recycle bin
        $deleted = softDeleteFeeAssignment($structureId, "Deleted by admin via fee management");

        if ($deleted) {
            $success = "Fee assignment deleted successfully! (Saved to recycle bin for recovery)";
            logActivity($currentUser['user_id'], 'Delete', 'Fee Structure', "Deleted fee structure ID: $structureId");
        } else {
            $error = "Failed to delete fee assignment. Please try again.";
        }
    } catch (Exception $e) {
        $error = "Failed to delete: " . $e->getMessage();
    }
}

// Handle deactivate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_assignment'])) {
    $structureId = intval($_POST['structure_id']);

    try {
        executeQuery("UPDATE fee_structure SET is_active = 0 WHERE structure_id = ?", 'i', [$structureId]);
        $success = "Fee assignment deactivated successfully!";
        logActivity($currentUser['user_id'], 'Update', 'Fee Structure', "Deactivated fee structure ID: $structureId");
    } catch (Exception $e) {
        $error = "Failed to deactivate: " . $e->getMessage();
    }
}

// Handle activate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_assignment'])) {
    $structureId = intval($_POST['structure_id']);

    try {
        executeQuery("UPDATE fee_structure SET is_active = 1 WHERE structure_id = ?", 'i', [$structureId]);
        $success = "Fee assignment activated successfully!";
        logActivity($currentUser['user_id'], 'Update', 'Fee Structure', "Activated fee structure ID: $structureId");
    } catch (Exception $e) {
        $error = "Failed to activate: " . $e->getMessage();
    }
}

// Get student info
$student = null;
if ($studentId > 0) {
    $student = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$studentId]);
}

// Get all fee assignments for this student
$assignments = [];
if ($studentId > 0) {
    $assignments = fetchAll(
        "SELECT fs.*, fh.fee_head_name, fh.fee_type
         FROM fee_structure fs
         JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
         WHERE fs.student_id = ?
         ORDER BY fs.is_active DESC, fh.fee_head_name",
        'i',
        [$studentId]
    );
}

include 'includes/header.php';
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
}
.student-info {
    background: #d1ecf1;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
table {
    border-collapse: collapse;
    width: 100%;
    margin: 20px 0;
}
th, td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
}
th {
    background: #0d6efd;
    color: white;
}
tr:hover {
    background: #f5f5f5;
}
.status-active {
    color: green;
    font-weight: bold;
}
.status-inactive {
    color: red;
    font-weight: bold;
}
.btn-group {
    display: flex;
    gap: 5px;
}
.btn-danger, .btn-warning, .btn-success {
    padding: 5px 10px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    color: white;
}
.btn-danger {
    background: #dc3545;
}
.btn-warning {
    background: #ffc107;
    color: #000;
}
.btn-success {
    background: #28a745;
}
.alert {
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<div class="row">
    <div class="col-12">
        <h2><i class="bi bi-cash-stack"></i> Manage Assigned Fees</h2>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($studentId <= 0): ?>
    <div class="alert alert-danger">
        <strong>No Student Selected!</strong> Please provide student_id in URL: ?student_id=X
    </div>
    <p><a href="modules/students/" class="btn btn-primary">Go to Students List</a></p>
<?php elseif (!$student): ?>
    <div class="alert alert-danger">
        <strong>Student Not Found!</strong> The student ID <?php echo $studentId; ?> does not exist.
    </div>
    <p><a href="modules/students/" class="btn btn-primary">Go to Students List</a></p>
<?php else: ?>
    <div class="student-info card">
        <div class="card-body">
            <h3>Student: <?php echo htmlspecialchars($student['student_name']); ?> (ID: <?php echo $studentId; ?>)</h3>
            <p><strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_no']); ?></p>
            <p><strong>Batch:</strong> <?php echo htmlspecialchars($student['batch'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-list"></i> Fee Assignments (<?php echo count($assignments); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (count($assignments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Structure ID</th>
                                <th>Fee Head</th>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Effective From</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo $assignment['structure_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($assignment['fee_head_name']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $assignment['fee_type'] == 'Monthly' ? 'info' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($assignment['fee_type']); ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($assignment['amount'], 2); ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($assignment['effective_from'])); ?></td>
                                    <td>
                                        <?php if ($assignment['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($assignment['is_active']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="structure_id" value="<?php echo $assignment['structure_id']; ?>">
                                                    <button type="submit" name="deactivate_assignment"
                                                            class="btn btn-sm btn-warning"
                                                            onclick="return confirm('Deactivate this fee assignment?');"
                                                            title="Deactivate">
                                                        <i class="bi bi-pause-circle"></i> Deactivate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="structure_id" value="<?php echo $assignment['structure_id']; ?>">
                                                    <button type="submit" name="activate_assignment"
                                                            class="btn btn-sm btn-success"
                                                            title="Activate">
                                                        <i class="bi bi-play-circle"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="structure_id" value="<?php echo $assignment['structure_id']; ?>">
                                                <button type="submit" name="delete_assignment"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Permanently delete this fee assignment? This cannot be undone!');"
                                                        title="Delete Permanently">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3">
                    <h6><i class="bi bi-info-circle"></i> Important Notes:</h6>
                    <ul>
                        <li><strong>Deactivate:</strong> Hides the fee from pending list but keeps the record (recommended)</li>
                        <li><strong>Delete:</strong> Permanently removes the fee assignment (cannot be undone)</li>
                        <li><strong>Monthly Fees:</strong> Each monthly fee will show 11 entries in pending list (one per month from Jan-Nov)</li>
                        <li><strong>Duplicates:</strong> If you see the same fee head multiple times with "Active" status, delete the duplicates</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>No fee assignments found!</strong> This student has no fees assigned yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-3">
        <a href="modules/fees/collect_complete.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Back to Fee Collection
        </a>
        <a href="modules/fees/structure.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Assign New Fee
        </a>
        <a href="check_fee_structure.php?student_id=<?php echo $studentId; ?>" class="btn btn-info">
            <i class="bi bi-search"></i> View Diagnostic Report
        </a>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
