<?php
/**
 * User Management
 * Add, edit, and manage system users
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('users', 'view');

$pageTitle = 'User Management';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$usersListLabel = ($currentUser['role'] ?? '') === 'super_admin' ? 'All Users' : 'School Users';

// Handle user deletion
if (isset($_GET['delete']) && hasPermission('users', 'delete')) {
    $userId = intval($_GET['delete']);
    $targetUser = fetchOne(
        "SELECT user_id, username, role, is_active, school_id FROM users WHERE user_id = ? LIMIT 1",
        'i',
        [$userId]
    );

    if (!$targetUser) {
        $_SESSION['error_message'] = "User not found!";
    } elseif ($userId == $currentUser['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account!";
    } elseif (($currentUser['role'] ?? '') !== 'super_admin' && (
        $currentSchoolId <= 0 || intval($targetUser['school_id'] ?? 0) !== $currentSchoolId
    )) {
        $_SESSION['error_message'] = "You can only manage users from your own school.";
    } elseif (($targetUser['role'] ?? '') === 'student') {
        $_SESSION['error_message'] = "Student accounts are managed from Student Portal.";
    } else {
        if (softDeleteUser($userId, 'Removed by super admin')) {
            logActivity($currentUser['user_id'], 'User Deleted', 'users', "Deleted user ID: $userId");
            $_SESSION['success_message'] = "User deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete user!";
        }
    }
    header("Location: users.php");
    exit();
}

// Handle status toggle
if (isset($_GET['toggle_status']) && hasPermission('users', 'edit')) {
    $userId = intval($_GET['toggle_status']);
    $targetUser = fetchOne(
        "SELECT user_id, role, school_id FROM users WHERE user_id = ? LIMIT 1",
        'i',
        [$userId]
    );

    if (!$targetUser) {
        $_SESSION['error_message'] = "User not found!";
    } elseif ($userId == $currentUser['user_id']) {
        $_SESSION['error_message'] = "You cannot deactivate your own account!";
    } elseif (($currentUser['role'] ?? '') !== 'super_admin' && (
        $currentSchoolId <= 0 || intval($targetUser['school_id'] ?? 0) !== $currentSchoolId
    )) {
        $_SESSION['error_message'] = "You can only manage users from your own school.";
    } elseif (($targetUser['role'] ?? '') === 'student') {
        $_SESSION['error_message'] = "Student accounts are managed from Student Portal.";
    } else {
        $toggleQuery = "UPDATE users SET is_active = NOT is_active WHERE user_id = ?";
        if (executeQuery($toggleQuery, 'i', [$userId])) {
            logActivity($currentUser['user_id'], 'User Status Updated', 'users', "Toggled status for user ID: $userId");
            $_SESSION['success_message'] = "User status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update user status!";
        }
    }
    header("Location: users.php");
    exit();
}

// Get all users
if (($currentUser['role'] ?? '') === 'super_admin') {
    $query = "SELECT * FROM users WHERE role <> 'student' ORDER BY created_at DESC";
    $users = fetchAll($query);
} elseif ($currentSchoolId > 0) {
    $query = "SELECT * FROM users WHERE role <> 'student' AND COALESCE(school_id, 0) = ? ORDER BY created_at DESC";
    $users = fetchAll($query, 'i', [$currentSchoolId]);
} else {
    $users = [];
}

// Define available roles (matching the ENUM in users table)
$roles = [
    ['role_value' => 'super_admin', 'role_name' => 'Super Admin'],
    ['role_value' => 'admin', 'role_name' => 'Admin'],
    ['role_value' => 'accountant', 'role_name' => 'Accountant'],
    ['role_value' => 'clerk', 'role_name' => 'Clerk'],
    ['role_value' => 'teacher', 'role_name' => 'Teacher'],
    ['role_value' => 'parent', 'role_name' => 'Parent']
];

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-people-fill"></i> User Management
            </h2>
            <div>
                <?php if (hasPermission('users', 'add')): ?>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Add New User
                </a>
                <?php endif; ?>
                <?php if (($currentUser['role'] ?? '') === 'super_admin'): ?>
                <a href="<?php echo APP_URL; ?>/modules/settings/student_portal.php" class="btn btn-success">
                    <i class="bi bi-mortarboard"></i> Student Portal
                </a>
                <?php endif; ?>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php
        echo $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h5>Total Users</h5>
                <h3><?php echo count($users); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Active Users</h5>
                <h3><?php echo count(array_filter($users, fn($u) => $u['is_active'] == 1)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-danger">
            <div class="card-body text-center">
                <h5>Inactive Users</h5>
                <h3><?php echo count(array_filter($users, fn($u) => $u['is_active'] == 0)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h5>Roles</h5>
                <h3><?php echo count($roles); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> <?php echo htmlspecialchars($usersListLabel); ?></h5>
            </div>
            <div class="card-body">
                <?php if (($currentUser['role'] ?? '') !== 'super_admin'): ?>
                    <div class="alert alert-info">
                        Showing users only from your school.
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['user_id'] == $currentUser['user_id']): ?>
                                        <span class="badge bg-info">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $roleColor = 'secondary';
                                    if ($user['role'] == 'super_admin') {
                                        $roleColor = 'danger';
                                    } elseif ($user['role'] == 'admin') {
                                        $roleColor = 'warning';
                                    } elseif ($user['role'] == 'parent') {
                                        $roleColor = 'info';
                                    }
                                    ?>
                                    <span class="badge bg-<?php
                                        echo $roleColor;
                                    ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? 'N/A'))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($user['last_login']) {
                                        echo date('d-M-Y h:i A', strtotime($user['last_login']));
                                    } else {
                                        echo '<span class="text-muted">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d-M-Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if (hasPermission('users', 'edit')): ?>
                                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>"
                                           class="btn btn-sm btn-primary"
                                           title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (hasPermission('users', 'edit') && $user['user_id'] != $currentUser['user_id']): ?>
                                        <a href="?toggle_status=<?php echo $user['user_id']; ?>"
                                           class="btn btn-sm btn-warning"
                                           title="Toggle Status"
                                           onclick="return confirm('Are you sure you want to toggle this user\'s status?')">
                                            <i class="bi bi-toggle-on"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (hasPermission('users', 'delete') && $user['user_id'] != $currentUser['user_id']): ?>
                                        <a href="?delete=<?php echo $user['user_id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           title="Delete User"
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
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

<!-- Roles Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-shield-check"></i> Available Roles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($roles as $role): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-<?php
                            echo $role['role_value'] == 'super_admin' ? 'danger' :
                                ($role['role_value'] == 'admin' ? 'warning' :
                                ($role['role_value'] == 'student' ? 'primary' : 'secondary'));
                        ?>">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-person-badge"></i>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </h5>
                                <p class="card-text text-muted">
                                    <?php
                                    $userCount = count(array_filter($users, fn($u) => $u['role'] == $role['role_value']));
                                    echo "$userCount user(s) with this role";
                                    ?>
                                </p>
                                <?php if ($currentUser['role'] === 'super_admin' && $role['role_value'] !== 'super_admin'): ?>
                                <a href="manage_permissions.php?school_id=<?php echo intval($currentSchoolId); ?>&role=<?php echo $role['role_value']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-gear"></i> Manage Permissions
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
// Initialize DataTables
$('#usersTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Users Report',
            exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7]
            }
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Users Report'
        }
    ]
});
";

// Include footer
include '../../includes/footer.php';
?>
