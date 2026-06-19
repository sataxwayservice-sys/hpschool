<?php
/**
 * User Profile
 * View and edit user profile
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();

$pageTitle = 'My Profile';
$currentUser = getCurrentUser();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Validate
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }

    if (!empty($email) && !isValidEmail($email)) {
        $errors[] = "Invalid email address";
    }

    // If changing password
    if (!empty($newPassword) || !empty($currentPassword)) {
        if (empty($currentPassword)) {
            $errors[] = "Current password is required to change password";
        } else {
            // Verify current password
            if (!password_verify($currentPassword, $currentUser['password'])) {
                $errors[] = "Current password is incorrect";
            }
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $errors[] = "New password must be at least 6 characters";
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = "New passwords do not match";
            }
        }
    }

    if (empty($errors)) {
        // Update profile
        if (!empty($newPassword)) {
            // Update with password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $query = "UPDATE users SET full_name = ?, email = ?, password = ?, updated_at = NOW() WHERE user_id = ?";
            $result = executeQuery($query, 'sssi', [$fullName, $email, $hashedPassword, $currentUser['user_id']]);
        } else {
            // Update without password
            $query = "UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE user_id = ?";
            $result = executeQuery($query, 'ssi', [$fullName, $email, $currentUser['user_id']]);
        }

        if ($result !== false) {
            logActivity($currentUser['user_id'], 'Profile Updated', 'users', "Updated profile information");

            // Update session
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;

            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Failed to update profile";
        }
    }
}

// Get fresh user data
$user = fetchOne("SELECT u.*, r.role_name
                 FROM users u
                 LEFT JOIN roles r ON u.role_id = r.role_id
                 WHERE u.user_id = ?", 'i', [$currentUser['user_id']]);

// If user not found, use current user data as fallback
if (!$user) {
    // Fallback to session data
    $user = $currentUser;

    // Set default role_name if not present
    if (!isset($user['role_name']) || empty($user['role_name'])) {
        // Try to get role name from role_id
        if (isset($user['role_id']) && $user['role_id'] > 0) {
            $roleData = fetchOne("SELECT role_name FROM roles WHERE role_id = ?", 'i', [$user['role_id']]);
            $user['role_name'] = $roleData ? $roleData['role_name'] : 'user';
        } else {
            $user['role_name'] = 'user';
        }
    }
}

// Ensure all required fields have default values
if (!isset($user['is_active'])) {
    $user['is_active'] = 1;
}
if (!isset($user['role_name']) || empty($user['role_name'])) {
    $user['role_name'] = 'user';
}
if (!isset($user['email'])) {
    $user['email'] = '';
}
if (!isset($user['last_login'])) {
    $user['last_login'] = null;
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-person-circle"></i> My Profile
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
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

<?php if (isset($errors) && count($errors) > 0): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Error!</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Info Card -->
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-person-circle" style="font-size: 100px; color: #0d6efd;"></i>
                </div>
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                <span class="badge bg-<?php
                    echo $user['role_name'] == 'super_admin' ? 'danger' :
                        ($user['role_name'] == 'admin' ? 'warning' : 'secondary');
                ?> mb-3">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role_name']))); ?>
                </span>

                <?php if ($user['is_active']): ?>
                    <p><span class="badge bg-success">Active</span></p>
                <?php else: ?>
                    <p><span class="badge bg-danger">Inactive</span></p>
                <?php endif; ?>

                <hr>

                <div class="text-start">
                    <p class="mb-2">
                        <i class="bi bi-envelope"></i>
                        <strong>Email:</strong><br>
                        <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-muted">Not set</span>'; ?>
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-calendar-check"></i>
                        <strong>Last Login:</strong><br>
                        <?php
                        if ($user['last_login']) {
                            echo date('d-M-Y h:i A', strtotime($user['last_login']));
                        } else {
                            echo '<span class="text-muted">Never</span>';
                        }
                        ?>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-calendar-plus"></i>
                        <strong>Member Since:</strong><br>
                        <?php echo date('d-M-Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card dashboard-card mt-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-graph-up"></i> My Activity</h6>
            </div>
            <div class="card-body">
                <?php
                $activityStats = fetchOne("SELECT COUNT(*) as total_actions
                                          FROM activity_log
                                          WHERE user_id = ?", 'i', [$currentUser['user_id']]);
                ?>
                <p class="mb-2">
                    <strong>Total Actions:</strong>
                    <span class="float-end badge bg-primary"><?php echo $activityStats['total_actions'] ?? 0; ?></span>
                </p>
                <p class="mb-0">
                    <strong>Today:</strong>
                    <span class="float-end badge bg-success">
                        <?php
                        $todayActions = fetchOne("SELECT COUNT(*) as count
                                                 FROM activity_log
                                                 WHERE user_id = ? AND DATE(created_at) = CURDATE()",
                                                 'i', [$currentUser['user_id']]);
                        echo $todayActions['count'] ?? 0;
                        ?>
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="profileForm">
                    <h6 class="mb-3">Basic Information</h6>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small class="text-muted">Username cannot be changed</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name"
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        <small class="text-muted">Optional. Used for notifications.</small>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">Change Password (Optional)</h6>
                    <p class="text-muted">Leave blank if you don't want to change your password</p>

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password" id="currentPassword">
                            <button class="btn btn-outline-secondary" type="button" id="toggleCurrent">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="new_password" id="newPassword" minlength="6">
                                <button class="btn btn-outline-secondary" type="button" id="toggleNew">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" minlength="6">
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> If you change your password, you will need to login again with the new password.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card dashboard-card mt-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php
                $recentActivity = fetchAll("SELECT * FROM activity_log
                                           WHERE user_id = ?
                                           ORDER BY created_at DESC
                                           LIMIT 10", 'i', [$currentUser['user_id']]);
                ?>
                <?php if (count($recentActivity) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                <small><?php echo date('d-M h:i A', strtotime($activity['created_at'])); ?></small>
                            </div>
                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></p>
                            <small class="text-muted">
                                <span class="badge bg-info"><?php echo htmlspecialchars($activity['module']); ?></span>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No recent activity</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
// Toggle password visibility
$('#toggleCurrent').on('click', function() {
    const field = $('#currentPassword');
    const type = field.attr('type') === 'password' ? 'text' : 'password';
    field.attr('type', type);
    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
});

$('#toggleNew').on('click', function() {
    const field = $('#newPassword');
    const type = field.attr('type') === 'password' ? 'text' : 'password';
    field.attr('type', type);
    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
});

// Password validation
$('#profileForm').on('submit', function(e) {
    const newPassword = $('#newPassword').val();
    const confirmPassword = $('#confirmPassword').val();
    const currentPassword = $('#currentPassword').val();

    // If trying to change password
    if (newPassword || confirmPassword || currentPassword) {
        if (!currentPassword) {
            e.preventDefault();
            alert('Please enter your current password to change password');
            return false;
        }

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match!');
            return false;
        }

        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long!');
            return false;
        }
    }

    return true;
});
";

// Include footer
include '../../includes/footer.php';
?>
