<?php
/**
 * Manage Role Permissions
 * Super Admin can manage permissions for each role
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();

// Only super admin can access
if (getCurrentUser()['role'] !== 'super_admin') {
    $_SESSION['error_message'] = "Access denied! Only Super Admin can manage permissions.";
    header("Location: " . APP_URL . "/modules/dashboard/");
    exit();
}

$pageTitle = 'Manage Permissions';
$currentUser = getCurrentUser();
if (function_exists('ensureRolePermissionsSchema')) {
    ensureRolePermissionsSchema();
}

$schools = fetchAll(
    "SELECT school_id, school_name, school_code, status
     FROM schools
     ORDER BY created_at DESC, school_id DESC"
);

$selectedSchoolId = intval($_GET['school_id'] ?? ($_POST['school_id'] ?? 0));
if ($selectedSchoolId <= 0 && !empty($schools)) {
    $selectedSchoolId = intval($schools[0]['school_id']);
}

// Get selected role
$selectedRole = isset($_GET['role']) ? sanitize($_GET['role']) : 'admin';
if (!in_array($selectedRole, ['admin', 'accountant', 'clerk', 'teacher'], true)) {
    $selectedRole = 'admin';
}
$allowedRoles = ['admin', 'accountant', 'clerk', 'teacher'];

$selectedSchool = $selectedSchoolId > 0 ? schoolRegistrationGetSchoolById($selectedSchoolId) : null;
$selectedSchoolLabel = $selectedSchool
    ? (($selectedSchool['school_name'] ?? '-') . ' (' . ($selectedSchool['school_code'] ?? '-') . ')')
    : 'Global Default';
$selectedStudentAddLimit = getSchoolStudentAddLimit($selectedSchoolId);
$selectedActiveStudentCount = getSchoolActiveStudentCount($selectedSchoolId);
$selectedRemainingStudentSeats = $selectedStudentAddLimit > 0
    ? max(0, $selectedStudentAddLimit - $selectedActiveStudentCount)
    : 0;

// Define all available permissions grouped by module
$allPermissions = [
    'Dashboard' => [
        ['key' => 'dashboard_view', 'label' => 'View Dashboard']
    ],
    'Students' => [
        ['key' => 'students_view', 'label' => 'View Students'],
        ['key' => 'students_add', 'label' => 'Add Students'],
        ['key' => 'students_edit', 'label' => 'Edit Students'],
        ['key' => 'students_delete', 'label' => 'Delete Students']
    ],
    'Classes & Sections' => [
        ['key' => 'classes_view', 'label' => 'View Classes'],
        ['key' => 'classes_add', 'label' => 'Add/Edit Classes'],
        ['key' => 'sections_view', 'label' => 'View Sections'],
        ['key' => 'sections_add', 'label' => 'Add/Edit Sections']
    ],
    'Fees' => [
        ['key' => 'fees_view', 'label' => 'View Fees'],
        ['key' => 'fees_add', 'label' => 'Collect Fees'],
        ['key' => 'fees_edit', 'label' => 'Edit Receipts'],
        ['key' => 'fees_delete', 'label' => 'Delete Receipts'],
        ['key' => 'fees_structure', 'label' => 'Manage Fee Structure']
    ],
    'Marks & Exams' => [
        ['key' => 'marks_view', 'label' => 'View Marks'],
        ['key' => 'marks_add', 'label' => 'Add/Edit Marks'],
        ['key' => 'exams_manage', 'label' => 'Manage Exams']
    ],
    'Reports' => [
        ['key' => 'reports_view', 'label' => 'View Reports'],
        ['key' => 'reports_export', 'label' => 'Export Reports']
    ],
    'Attendance' => [
        ['key' => 'attendance_scan_view', 'label' => 'Access Attendance Scan']
    ],
    'School Setup' => [
        ['key' => 'school_settings_view', 'label' => 'School Settings'],
        ['key' => 'academic_years_view', 'label' => 'Academic Years'],
        ['key' => 'session_rollover_view', 'label' => 'Session Rollover']
    ],
    'Users & Settings' => [
        ['key' => 'users_view', 'label' => 'View Users'],
        ['key' => 'users_add', 'label' => 'Add Users'],
        ['key' => 'users_edit', 'label' => 'Edit Users'],
        ['key' => 'users_delete', 'label' => 'Delete Users'],
        ['key' => 'settings_view', 'label' => 'View Settings'],
        ['key' => 'settings_edit', 'label' => 'Edit Settings']
    ],
    'System Tools' => [
        ['key' => 'student_portal_view', 'label' => 'Access Student Portal'],
        ['key' => 'recycle_bin_view', 'label' => 'View Recycle Bin']
    ]
];

// Handle student add limit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_add_limit'])) {
    $schoolId = intval($_POST['school_id'] ?? 0);
    $studentAddLimit = max(0, intval($_POST['student_add_limit'] ?? 0));

    if ($schoolId <= 0) {
        $_SESSION['error_message'] = 'Please select a school first.';
        header("Location: manage_permissions.php?school_id={$selectedSchoolId}&role={$selectedRole}#student-add-limit");
        exit();
    }

    $saved = function_exists('setSchoolStudentAddLimit')
        ? setSchoolStudentAddLimit($schoolId, $studentAddLimit)
        : false;

    if ($saved) {
        logActivity($currentUser['user_id'], 'Student Add Limit Updated', 'settings', "Student add limit set to {$studentAddLimit} for school ID #{$schoolId}");
        $_SESSION['success_message'] = $studentAddLimit > 0
            ? 'Student add limit updated successfully to ' . number_format($studentAddLimit) . ' for school ID #' . $schoolId . '.'
            : 'Student add limit updated successfully. Unlimited students are now allowed for school ID #' . $schoolId . '.';
    } else {
        $_SESSION['error_message'] = 'Failed to update student add limit. Please try again.';
    }

    header("Location: manage_permissions.php?school_id={$schoolId}&role={$selectedRole}#student-add-limit");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $role = sanitize($_POST['role']);
    $schoolId = intval($_POST['school_id'] ?? 0);
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    $validPermissionKeys = [];
    foreach ($allPermissions as $permissionGroup) {
        foreach ($permissionGroup as $permissionItem) {
            $validPermissionKeys[] = $permissionItem['key'];
        }
    }

    if (!in_array($role, $allowedRoles, true)) {
        $_SESSION['error_message'] = 'Invalid role selected.';
        header("Location: manage_permissions.php?school_id={$selectedSchoolId}&role=admin");
        exit();
    }

    if ($schoolId <= 0) {
        $_SESSION['error_message'] = 'Please select a school first.';
        header("Location: manage_permissions.php?school_id={$selectedSchoolId}&role={$role}");
        exit();
    }

    // Convert permissions array to JSON
    $permissions = array_values(array_filter(array_map('trim', $permissions), function ($permission) use ($validPermissionKeys) {
        return in_array($permission, $validPermissionKeys, true);
    }));
    $permissionsJson = json_encode($permissions);

    // Check if role_permissions table exists
    $tableCheck = fetchAll("SHOW TABLES LIKE 'role_permissions'");
    if (count($tableCheck) == 0) {
        $_SESSION['error_message'] = "Error: role_permissions table does not exist! Please run create_permissions_table.sql first or visit <a href='../../test_permissions_table.php'>test_permissions_table.php</a>";
        header("Location: manage_permissions.php?school_id={$schoolId}&role={$role}");
        exit();
    }

    // Check if permissions record exists for this role
    $existingPerms = fetchOne(
        "SELECT * FROM role_permissions WHERE school_id = ? AND role_name = ? LIMIT 1",
        'is',
        [$schoolId, $role]
    );

    $result = false;
    if ($existingPerms) {
        // Update existing
        $query = "UPDATE role_permissions SET permissions = ?, updated_at = NOW() WHERE school_id = ? AND role_name = ?";
        $result = executeQuery($query, 'sis', [$permissionsJson, $schoolId, $role]);
    } else {
        // Insert new
        $query = "INSERT INTO role_permissions (school_id, role_name, permissions, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
        $result = executeQuery($query, 'iss', [$schoolId, $role, $permissionsJson]);
    }

    if ($result !== false) {
        logActivity($currentUser['user_id'], 'Permissions Updated', 'settings', "Updated permissions for role: $role");
        $_SESSION['success_message'] = "Permissions updated successfully for " . ucwords(str_replace('_', ' ', $role)) . " in school ID #{$schoolId}! (" . count($permissions) . " permissions set)";
    } else {
        $_SESSION['error_message'] = "Failed to save permissions. Please check database connection and table structure.";
    }

    header("Location: manage_permissions.php?school_id={$schoolId}&role={$role}");
    exit();
}

// Get current permissions for selected role
$currentPermissions = [];
$permsRecord = function_exists('getRolePermissionsForSchool')
    ? getRolePermissionsForSchool($selectedRole, $selectedSchoolId)
    : fetchOne(
        "SELECT permissions FROM role_permissions WHERE role_name = ? LIMIT 1",
        's',
        [$selectedRole]
    );
if ($permsRecord && !empty($permsRecord['permissions'])) {
    $currentPermissions = json_decode($permsRecord['permissions'], true) ?? [];
}

// Define available roles
$roles = [
    'admin' => 'Admin',
    'accountant' => 'Accountant',
    'clerk' => 'Clerk',
    'teacher' => 'Teacher'
];

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-shield-lock"></i> Manage Role Permissions
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/settings/users.php'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
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

<!-- School Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Select School</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($schools)): ?>
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="school_id" class="form-label">School</label>
                            <select name="school_id" id="school_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo intval($school['school_id']); ?>" <?php echo intval($school['school_id']) === $selectedSchoolId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($school['school_name'] ?? '-') . ' (' . ($school['school_code'] ?? '-') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-dark w-100">
                                <i class="bi bi-search"></i> Load School
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        No school records were found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Role Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Select Role</h5>
            </div>
            <div class="card-body">
                <div class="btn-group" role="group">
                    <?php foreach ($roles as $roleKey => $roleName): ?>
                        <a href="?school_id=<?php echo intval($selectedSchoolId); ?>&role=<?php echo $roleKey; ?>"
                           class="btn btn-<?php echo $selectedRole === $roleKey ? 'primary' : 'outline-primary'; ?>">
                            <?php echo $roleName; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note:</strong> Changes apply only to the selected school. Super Admin has all permissions by default and cannot be modified.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Add Limit -->
<div class="row mb-4" id="student-add-limit">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-sliders2"></i> Student Add Limit
                </h5>
                <span class="badge bg-light text-dark">
                    <?php echo $selectedStudentAddLimit > 0 ? number_format($selectedStudentAddLimit) . ' Limit' : 'Unlimited'; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-stretch mb-3">
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Selected School</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($selectedSchoolLabel); ?></div>
                            <div class="mt-3 text-muted small">Active Students</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($selectedActiveStudentCount); ?></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Current Limit</div>
                            <div class="fs-4 fw-bold">
                                <?php echo $selectedStudentAddLimit > 0 ? number_format($selectedStudentAddLimit) : 'Unlimited'; ?>
                            </div>
                            <div class="mt-3 text-muted small">Remaining Seats</div>
                            <div class="fw-semibold">
                                <?php echo $selectedStudentAddLimit > 0 ? number_format($selectedRemainingStudentSeats) : 'No limit'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Rule</div>
                            <div class="fw-semibold">0 means unlimited student admissions for this school.</div>
                            <div class="mt-3 text-muted small">Status</div>
                            <div class="badge <?php echo $selectedStudentAddLimit > 0 ? ($selectedRemainingStudentSeats <= 0 ? 'bg-danger' : 'bg-success') : 'bg-secondary'; ?>">
                                <?php echo $selectedStudentAddLimit > 0 ? ($selectedRemainingStudentSeats <= 0 ? 'Limit Reached' : 'Seats Available') : 'Unlimited'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" class="row g-3 align-items-end">
                    <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($selectedRole); ?>">
                    <input type="hidden" name="save_student_add_limit" value="1">

                    <div class="col-md-8 col-lg-6">
                        <label for="student_add_limit" class="form-label">Student Add Limit</label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary" onclick="adjustStudentLimit(-1)" aria-label="Decrease limit">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="number"
                                   min="0"
                                   step="1"
                                   class="form-control"
                                   id="student_add_limit"
                                   name="student_add_limit"
                                   value="<?php echo intval($selectedStudentAddLimit); ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="adjustStudentLimit(1)" aria-label="Increase limit">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div class="form-text">Set to 0 for unlimited student admissions.</div>
                    </div>

                    <div class="col-md-4 col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save"></i> Save Limit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Permissions Form -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-shield-check"></i>
                    Permissions for: <strong><?php echo ucwords(str_replace('_', ' ', $selectedRole)); ?></strong>
                </h5>
                <small class="text-white-50"><?php echo htmlspecialchars($selectedSchoolLabel); ?></small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="role" value="<?php echo $selectedRole; ?>">
                    <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">

                    <?php foreach ($allPermissions as $moduleName => $permissions): ?>
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">
                            <i class="bi bi-folder"></i> <?php echo $moduleName; ?>
                        </h5>
                        <div class="row">
                            <?php foreach ($permissions as $perm): ?>
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="permissions[]"
                                           value="<?php echo $perm['key']; ?>"
                                           id="perm_<?php echo $perm['key']; ?>"
                                           <?php echo in_array($perm['key'], $currentPermissions) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_<?php echo $perm['key']; ?>">
                                        <?php echo $perm['label']; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="mt-4 border-top pt-3">
                        <button type="submit" name="save_permissions" class="btn btn-success btn-lg">
                            <i class="bi bi-save"></i> Save Permissions
                        </button>
                        <button type="button" class="btn btn-warning btn-lg" onclick="checkAll()">
                            <i class="bi bi-check-all"></i> Check All
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" onclick="uncheckAll()">
                            <i class="bi bi-x-square"></i> Uncheck All
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
function checkAll() {
    $('input[type=\"checkbox\"]').prop('checked', true);
}

function uncheckAll() {
    $('input[type=\"checkbox\"]').prop('checked', false);
}

function adjustStudentLimit(delta) {
    const input = document.getElementById('student_add_limit');
    if (!input) {
        return;
    }

    const current = parseInt(input.value || '0', 10);
    const next = Math.max(0, current + delta);
    input.value = next;
}
";

// Include footer
include '../../includes/footer.php';
?>
