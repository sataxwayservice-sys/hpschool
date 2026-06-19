<?php
/**
 * Academic Year Management
 * Add, edit, and manage academic years
 */

require_once '../../config/config.php';
requireLogin();

$pageTitle = 'Academic Year Management';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$academicAccessRole = $currentUser['role'] ?? '';
if ($academicAccessRole !== 'super_admin') {
    requireRolePermissionForSchool('academic_years', 'view', $currentSchoolId, $academicAccessRole);
}
$error = '';
$success = '';

// Handle Add/Edit/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_year'])) {
        $yearName = sanitize($_POST['year_name']);
        $startDate = sanitize($_POST['start_date']);
        $endDate = sanitize($_POST['end_date']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $conn = getDbConnection();

        // If setting as active, deactivate all others
        if ($isActive) {
            $conn->query("UPDATE academic_years SET is_active = 0");

            // Update school settings
            if ($currentSchoolId > 0) {
                $stmt = $conn->prepare("UPDATE school_settings SET current_academic_year = ? WHERE school_id = ?");
                $stmt->bind_param('si', $yearName, $currentSchoolId);
                $stmt->execute();
            } else {
                $conn->query("UPDATE school_settings SET current_academic_year = '$yearName'");
            }

            // Update active student batches for the new session
            $conn->query("UPDATE students SET batch = '$yearName' WHERE status = 'Active'");
        }

        $stmt = $conn->prepare("INSERT INTO academic_years (year_name, start_date, end_date, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssi', $yearName, $startDate, $endDate, $isActive);

        if ($stmt->execute()) {
            if ($isActive) {
                $success = "Academic year '$yearName' added and set as active! School settings and student batches updated.";
            } else {
                $success = "Academic year '$yearName' added successfully!";
            }
            logActivity($currentUser['user_id'], 'Add', 'Academic Year', "Added academic year: $yearName");
        } else {
            $error = "Failed to add academic year: " . $stmt->error;
        }
    }

    if (isset($_POST['set_active'])) {
        $yearId = intval($_POST['year_id']);
        $yearName = sanitize($_POST['year_name']);

        $conn = getDbConnection();
        $conn->query("UPDATE academic_years SET is_active = 0");
        $conn->query("UPDATE academic_years SET is_active = 1 WHERE year_id = $yearId");
        if ($currentSchoolId > 0) {
            $stmt = $conn->prepare("UPDATE school_settings SET current_academic_year = ? WHERE school_id = ?");
            $stmt->bind_param('si', $yearName, $currentSchoolId);
            $stmt->execute();
        } else {
            $conn->query("UPDATE school_settings SET current_academic_year = '$yearName'");
        }

        // Also update active student batches to match the new academic year
        $conn->query("UPDATE students SET batch = '$yearName' WHERE status = 'Active'");

        $success = "Academic year '$yearName' is now active! School settings and active student batches have been updated.";
        logActivity($currentUser['user_id'], 'Update', 'Academic Year', "Set $yearName as active");
    }

    if (isset($_POST['delete_year'])) {
        $yearId = intval($_POST['year_id']);
        $conn = getDbConnection();

        // Check if this year is active
        $check = $conn->query("SELECT is_active, year_name FROM academic_years WHERE year_id = $yearId")->fetch_assoc();

        if ($check['is_active']) {
            $error = "Cannot delete the active academic year!";
        } else {
            $conn->query("DELETE FROM academic_years WHERE year_id = $yearId");
            $success = "Academic year deleted successfully!";
            logActivity($currentUser['user_id'], 'Delete', 'Academic Year', "Deleted academic year: " . $check['year_name']);
        }
    }
}

// Create table if not exists
$conn = getDbConnection();
$createTable = "CREATE TABLE IF NOT EXISTS academic_years (
    year_id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createTable);

// Get current academic year from school settings
$schoolSettings = getSchoolSettings();
$currentAcademicYear = $schoolSettings['current_academic_year'];

// Check if current academic year exists in academic_years table
$checkExisting = $conn->query("SELECT * FROM academic_years WHERE year_name = '$currentAcademicYear'");

if ($checkExisting->num_rows == 0 && !empty($currentAcademicYear)) {
    // Auto-create the current academic year from school settings
    $dateRange = getAcademicYearDateRange($currentAcademicYear);
    $startDate = $dateRange['start_date'];
    $endDate = $dateRange['end_date'];

    $stmt = $conn->prepare("INSERT INTO academic_years (year_name, start_date, end_date, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->bind_param('sss', $currentAcademicYear, $startDate, $endDate);
    $stmt->execute();

    $success = "Current academic year '$currentAcademicYear' has been imported from school settings!";
}

// Get all academic years
$academicYears = $conn->query("SELECT * FROM academic_years ORDER BY start_date DESC");

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-calendar-range"></i> Academic Year Management</h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addYearModal">
                    <i class="bi bi-plus-circle"></i> Add New Academic Year
                </button>
                <a href="<?php echo APP_URL; ?>/modules/settings/session_rollover.php" class="btn btn-success">
                    <i class="bi bi-arrow-repeat"></i> Session Rollover
                </a>
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

<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> <strong>Current School Year:</strong> <?php echo htmlspecialchars($currentAcademicYear); ?>
            <br><small class="text-muted">This is the active academic year being used throughout the system for student batches, fee collection, and reports.</small>
            <br><small class="text-muted">For full closeout and carry-forward review, use <a href="<?php echo APP_URL; ?>/modules/settings/session_rollover.php">Session Rollover</a>.</small>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Academic Years</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Year</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($academicYears->num_rows > 0): ?>
                                <?php while ($year = $academicYears->fetch_assoc()):
                                    // Count students in this batch
                                    $studentCount = $conn->query("SELECT COUNT(*) as count FROM students WHERE batch = '" . $year['year_name'] . "'")->fetch_assoc()['count'];
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($year['year_name']); ?></strong>
                                            <?php if ($year['is_active']): ?>
                                                <span class="badge bg-success ms-2">
                                                    <i class="bi bi-check-circle"></i> Current
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $year['start_date'] ? date('d M Y', strtotime($year['start_date'])) : '-'; ?></td>
                                        <td><?php echo $year['end_date'] ? date('d M Y', strtotime($year['end_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="bi bi-people"></i> <?php echo $studentCount; ?> students
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($year['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$year['is_active']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="year_id" value="<?php echo $year['year_id']; ?>">
                                                    <input type="hidden" name="year_name" value="<?php echo htmlspecialchars($year['year_name']); ?>">
                                                    <button type="submit" name="set_active" class="btn btn-sm btn-success"
                                                            onclick="return confirm('Set <?php echo htmlspecialchars($year['year_name']); ?> as active academic year?');">
                                                        <i class="bi bi-check2-square"></i> Set Active
                                                    </button>
                                                </form>

                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="year_id" value="<?php echo $year['year_id']; ?>">
                                                    <button type="submit" name="delete_year" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Delete this academic year?');">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="bi bi-lock"></i> Active year cannot be deleted</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <em>No academic years found. Click "Add New Academic Year" to create one.</em>
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

<!-- Add Academic Year Modal -->
<div class="modal fade" id="addYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Academic Year</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="year_name" class="form-label required">Academic Year</label>
                        <input type="text" class="form-control" id="year_name" name="year_name"
                               placeholder="e.g., 2024-2025, 2025-2026" required>
                        <small class="text-muted">Format: YYYY-YYYY</small>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active">
                        <label class="form-check-label" for="is_active">
                            <strong>Set as Active Academic Year</strong>
                            <br><small class="text-muted">This will deactivate the current academic year</small>
                        </label>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Note:</strong> Only one academic year can be active at a time. The active year is used throughout the system for fee collection, student enrollment, and reports.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_year" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Academic Year
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-suggest end date based on start date
$('#start_date').on('change', function() {
    const startDate = new Date($(this).val());
    if (startDate) {
        // Add 1 year minus 1 day
        const endDate = new Date(startDate);
        endDate.setFullYear(endDate.getFullYear() + 1);
        endDate.setDate(endDate.getDate() - 1);

        const endDateStr = endDate.toISOString().split('T')[0];
        $('#end_date').val(endDateStr);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
