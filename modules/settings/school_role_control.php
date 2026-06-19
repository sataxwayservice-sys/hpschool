<?php
/**
 * School Role Control
 * Super Admin can choose which user roles are available for each school.
 */

require_once '../../config/config.php';
require_once '../../includes/school_registration.php';

requireLogin();

if (($currentUser = getCurrentUser()) === null || ($currentUser['role'] ?? '') !== 'super_admin') {
    $_SESSION['error_message'] = 'Access denied! Only Super Admin can manage school role control.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

ensureSchoolSettingsSchema();
ensureSchoolRegistrationSchema();

$pageTitle = 'School Role Control';
$currentUser = getCurrentUser();
$roleDefinitions = [
    ['value' => 'admin', 'label' => 'Admin', 'description' => 'School-level administrator with full working access.'],
    ['value' => 'accountant', 'label' => 'Accountant', 'description' => 'Fee collection, receipts, and financial reports.'],
    ['value' => 'clerk', 'label' => 'Clerk', 'description' => 'Front office and routine school entry tasks.'],
    ['value' => 'teacher', 'label' => 'Teacher', 'description' => 'Marks, class work, and academic operations.'],
    ['value' => 'parent', 'label' => 'Parent', 'description' => 'Parent portal access for linked student records.'],
];

$schools = fetchAll(
    "SELECT school_id, school_name, school_code, status, created_at
     FROM schools
     ORDER BY created_at DESC, school_id DESC"
);

$selectedSchoolId = intval($_GET['school_id'] ?? ($_POST['school_id'] ?? 0));
if ($selectedSchoolId <= 0 && !empty($schools)) {
    $selectedSchoolId = intval($schools[0]['school_id']);
}

$selectedSchool = $selectedSchoolId > 0 ? schoolRegistrationGetSchoolById($selectedSchoolId) : null;
$selectedSettings = $selectedSchool ? getSchoolSettingsByCode($selectedSchool['school_code'] ?? '') : null;
$currentEnabledRoles = $selectedSchool ? getSchoolEnabledRoles($selectedSchoolId) : ['admin', 'accountant', 'clerk', 'teacher', 'parent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_roles'])) {
    $postedSchoolId = intval($_POST['school_id'] ?? 0);
    $roles = isset($_POST['enabled_roles']) && is_array($_POST['enabled_roles']) ? $_POST['enabled_roles'] : [];
    $allowedRoleValues = array_map(static function ($role) {
        return $role['value'];
    }, $roleDefinitions);

    $roles = array_values(array_filter(array_map('trim', $roles), function ($role) use ($allowedRoleValues) {
        return in_array($role, $allowedRoleValues, true);
    }));

    if ($postedSchoolId <= 0) {
        $_SESSION['error_message'] = 'Please select a school first.';
        header('Location: school_role_control.php');
        exit();
    }

    $school = schoolRegistrationGetSchoolById($postedSchoolId);
    if (!$school) {
        $_SESSION['error_message'] = 'School not found.';
        header('Location: school_role_control.php');
        exit();
    }

    if (empty($roles)) {
        $_SESSION['error_message'] = 'Please select at least one role.';
        header('Location: school_role_control.php?school_id=' . $postedSchoolId);
        exit();
    }

    if (!$selectedSettings) {
        schoolRegistrationSyncApprovedSchoolSettings($school);
        $selectedSettings = getSchoolSettingsByCode($school['school_code'] ?? '');
    }

    if (!$selectedSettings || empty($selectedSettings['setting_id'])) {
        $_SESSION['error_message'] = 'Could not load the school settings row for this school.';
        header('Location: school_role_control.php?school_id=' . $postedSchoolId);
        exit();
    }

    $updateResult = executeQuery(
        "UPDATE school_settings SET enabled_roles = ?, updated_at = NOW() WHERE setting_id = ?",
        'si',
        [json_encode($roles), intval($selectedSettings['setting_id'])]
    );

    if ($updateResult !== false) {
        logActivity(
            $currentUser['user_id'],
            'School Roles Updated',
            'settings',
            'Updated enabled roles for school: ' . ($school['school_name'] ?? 'Unknown School')
        );
        $_SESSION['success_message'] = 'School roles updated successfully.';
        header('Location: school_role_control.php?school_id=' . $postedSchoolId);
        exit();
    }

    $_SESSION['error_message'] = 'Failed to save school roles. Please try again.';
    header('Location: school_role_control.php?school_id=' . $postedSchoolId);
    exit();
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-person-gear"></i> School Role Control
                </h2>
                <p class="text-muted mb-0">Choose which user roles are available for each school.</p>
            </div>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Select School</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">School</label>
                        <select name="school_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo intval($school['school_id']); ?>" <?php echo intval($school['school_id']) === $selectedSchoolId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($school['school_name'] ?? '-') . ' (' . ($school['school_code'] ?? '-') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Load School
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($selectedSchool): ?>
    <div class="row mb-4">
        <div class="col-lg-5">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> School Details</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>School Name:</strong> <?php echo htmlspecialchars($selectedSchool['school_name'] ?? '-'); ?></p>
                    <p class="mb-2"><strong>School Code:</strong> <?php echo htmlspecialchars($selectedSchool['school_code'] ?? '-'); ?></p>
                    <p class="mb-2"><strong>Status:</strong> <span class="badge bg-<?php echo strtolower($selectedSchool['status'] ?? '') === 'approved' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($selectedSchool['status'] ?? '-'); ?></span></p>
                    <p class="mb-0 text-muted">This control updates the role options stored for the selected school's settings row.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Enabled Roles</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">
                        <div class="row">
                            <?php foreach ($roleDefinitions as $roleDef): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enabled_roles[]"
                                               value="<?php echo htmlspecialchars($roleDef['value']); ?>"
                                               id="role_<?php echo htmlspecialchars($roleDef['value']); ?>"
                                               <?php echo in_array($roleDef['value'], $currentEnabledRoles, true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="role_<?php echo htmlspecialchars($roleDef['value']); ?>">
                                            <strong><?php echo htmlspecialchars($roleDef['label']); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($roleDef['description']); ?></div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> These roles are stored for the selected school and can be used to control which staff roles are available in that school's user management flow.
                        </div>

                        <button type="submit" name="save_roles" class="btn btn-success">
                            <i class="bi bi-save"></i> Save School Roles
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        No school records were found.
    </div>
<?php endif; ?>

<?php
include '../../includes/footer.php';
?>
