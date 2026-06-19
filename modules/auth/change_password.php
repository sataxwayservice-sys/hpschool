<?php
/**
 * Change Password
 * Allow users to change their password
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();

$pageTitle = 'Change Password';
$currentUser = getCurrentUser();

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
    } else {
        // Verify current password
        if (!password_verify($currentPassword, $currentUser['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }

    if (empty($newPassword)) {
        $errors[] = "New password is required";
    } elseif (strlen($newPassword) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match";
    }

    // If same as current password
    if (!empty($newPassword) && password_verify($newPassword, $currentUser['password'])) {
        $errors[] = "New password cannot be the same as current password";
    }

    if (empty($errors)) {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?";
        $result = executeQuery($query, 'si', [$hashedPassword, $currentUser['user_id']]);

        if ($result !== false) {
            logActivity($currentUser['user_id'], 'Password Changed', 'users', "User changed their password");
            $success = "Password changed successfully! You can use your new password on next login.";

            // Clear form
            $_POST = [];
        } else {
            $errors[] = "Failed to change password. Please try again.";
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-key"></i> Change Password
            </h2>
            <div>
                <a href="<?php echo APP_URL; ?>/modules/auth/profile.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/auth/profile.php'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <strong>Error!</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Your Password</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Password Requirements:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Minimum 6 characters long</li>
                        <li>Should be different from current password</li>
                        <li>Use a strong, unique password</li>
                    </ul>
                </div>

                <form method="POST" action="" id="changePasswordForm">
                    <div class="mb-3">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password"
                                   id="currentPassword" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleCurrent">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Enter your existing password</small>
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password"
                                   id="newPassword" minlength="6" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleNew">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Minimum 6 characters</small>
                        <div class="mt-2">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="passwordStrength" role="progressbar"
                                     style="width: 0%"></div>
                            </div>
                            <small id="strengthText" class="text-muted"></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password"
                                   id="confirmPassword" minlength="6" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirm">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Re-enter your new password</small>
                        <div id="matchIndicator" class="mt-2"></div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Important:</strong> After changing your password, you will need to use the new password on your next login.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-shield-check"></i> Change Password
                        </button>
                        <a href="<?php echo APP_URL; ?>/modules/auth/profile.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Tips -->
        <div class="card dashboard-card mt-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Password Security Tips</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Use a mix of uppercase and lowercase letters</li>
                    <li>Include numbers and special characters</li>
                    <li>Avoid using personal information (name, birthday, etc.)</li>
                    <li>Don't reuse passwords from other websites</li>
                    <li>Change your password regularly</li>
                    <li>Never share your password with anyone</li>
                </ul>
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

$('#toggleConfirm').on('click', function() {
    const field = $('#confirmPassword');
    const type = field.attr('type') === 'password' ? 'text' : 'password';
    field.attr('type', type);
    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
});

// Password strength indicator
$('#newPassword').on('keyup', function() {
    const password = $(this).val();
    let strength = 0;
    let strengthText = '';
    let strengthClass = '';

    if (password.length >= 6) strength += 20;
    if (password.length >= 8) strength += 20;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 20;
    if (/[0-9]/.test(password)) strength += 20;
    if (/[^a-zA-Z0-9]/.test(password)) strength += 20;

    if (strength < 40) {
        strengthText = 'Weak';
        strengthClass = 'bg-danger';
    } else if (strength < 60) {
        strengthText = 'Fair';
        strengthClass = 'bg-warning';
    } else if (strength < 80) {
        strengthText = 'Good';
        strengthClass = 'bg-info';
    } else {
        strengthText = 'Strong';
        strengthClass = 'bg-success';
    }

    $('#passwordStrength').css('width', strength + '%').removeClass('bg-danger bg-warning bg-info bg-success').addClass(strengthClass);
    $('#strengthText').text(strengthText).removeClass('text-danger text-warning text-info text-success').addClass('text-' + strengthClass.replace('bg-', ''));
});

// Password match indicator
$('#confirmPassword').on('keyup', function() {
    const newPassword = $('#newPassword').val();
    const confirmPassword = $(this).val();

    if (confirmPassword.length > 0) {
        if (newPassword === confirmPassword) {
            $('#matchIndicator').html('<span class=\"text-success\"><i class=\"bi bi-check-circle\"></i> Passwords match</span>');
        } else {
            $('#matchIndicator').html('<span class=\"text-danger\"><i class=\"bi bi-x-circle\"></i> Passwords do not match</span>');
        }
    } else {
        $('#matchIndicator').html('');
    }
});

// Form validation
$('#changePasswordForm').on('submit', function(e) {
    const currentPassword = $('#currentPassword').val();
    const newPassword = $('#newPassword').val();
    const confirmPassword = $('#confirmPassword').val();

    if (!currentPassword) {
        e.preventDefault();
        alert('Please enter your current password');
        $('#currentPassword').focus();
        return false;
    }

    if (!newPassword) {
        e.preventDefault();
        alert('Please enter a new password');
        $('#newPassword').focus();
        return false;
    }

    if (newPassword.length < 6) {
        e.preventDefault();
        alert('New password must be at least 6 characters long');
        $('#newPassword').focus();
        return false;
    }

    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        $('#confirmPassword').focus();
        return false;
    }

    if (currentPassword === newPassword) {
        e.preventDefault();
        alert('New password must be different from current password');
        $('#newPassword').focus();
        return false;
    }

    return true;
});
";

// Include footer
include '../../includes/footer.php';
?>
