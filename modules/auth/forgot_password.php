<?php
/**
 * Forgot Password
 * Reset a user password from the login page
 */

require_once '../../config/config.php';

if (isLoggedIn()) {
    redirect(getUserHomeUrl());
}

$loginContext = strtolower(trim((string)($_GET['context'] ?? '')));
$isSuperAdminContext = $loginContext === 'super_admin';
$loginUrl = $isSuperAdminContext
    ? APP_URL . '/modules/auth/super_admin_login.php'
    : APP_URL . '/modules/auth/login.php';
$usernameUrl = $isSuperAdminContext
    ? APP_URL . '/modules/auth/forgot_username.php?context=super_admin'
    : APP_URL . '/modules/auth/forgot_username.php';
$branding = $isSuperAdminContext ? getCompanyBranding() : getSchoolSettings();
$pageTitle = $isSuperAdminContext ? 'Super Admin Forgot Password' : 'Forgot Password';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($identifier === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } else {
        $roleClause = $isSuperAdminContext ? " AND role = 'super_admin'" : '';
        $user = fetchOne(
            "SELECT user_id, username, email, full_name, role, is_active
             FROM users
             WHERE (username = ? OR email = ?)" . $roleClause . "
             LIMIT 1",
            'ss',
            [$identifier, $identifier]
        );

        if (!$user) {
            $error = 'No user found for that username or email.';
        } else {
            $updated = updatePassword(intval($user['user_id']), $newPassword);
            if ($updated) {
                $success = 'Password updated successfully for ' . htmlspecialchars($user['username']) . '.';
                header('Location: ' . $loginUrl . '?reset=1');
                exit();
            }

            $error = 'Failed to update the password. Please try again.';
        }
    }
}

$pageTitle = $isSuperAdminContext ? 'Super Admin Forgot Password' : 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($branding['name']); ?> - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/style.css'); ?>">
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <?php if (!empty($branding['logo'])): ?>
                <?php $brandingLogoSrc = getCompanyLogoSrc($branding['logo']); ?>
                <?php if (!empty($brandingLogoSrc)): ?>
                    <img src="<?php echo htmlspecialchars($brandingLogoSrc); ?>"
                         alt="Logo" class="img-fluid">
                <?php else: ?>
                    <i class="bi bi-key-fill" style="font-size: 4rem;"></i>
                <?php endif; ?>
            <?php else: ?>
                <i class="bi bi-key-fill" style="font-size: 4rem;"></i>
            <?php endif; ?>
            <h3 class="mt-3"><?php echo htmlspecialchars($branding['name']); ?></h3>
            <p class="mb-0"><?php echo $isSuperAdminContext ? 'Super Admin Account Recovery' : 'Account Recovery'; ?></p>
        </div>

        <div class="login-body">
            <h4 class="text-center mb-4"><?php echo $isSuperAdminContext ? 'Reset Your Super Admin Password' : 'Reset Your Password'; ?></h4>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <?php if ($isSuperAdminContext): ?>
                    Enter your super admin username or email, then choose a new password.
                <?php else: ?>
                    Enter your username or email, then choose a new password.
                <?php endif; ?>
            </div>

            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="bi bi-person"></i> Username or Email
                    </label>
                    <input type="text" class="form-control" id="username" name="username"
                           placeholder="<?php echo $isSuperAdminContext ? 'Enter super admin username or email' : 'Enter username or email'; ?>" required autofocus>
                    <div class="invalid-feedback">
                        Please enter your username or email.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">
                        <i class="bi bi-lock"></i> New Password
                    </label>
                    <input type="password" class="form-control" id="new_password" name="new_password"
                           placeholder="Enter new password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                    <div class="invalid-feedback">
                        Please enter a valid new password.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">
                        <i class="bi bi-lock-fill"></i> Confirm New Password
                    </label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter new password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                    <div class="invalid-feedback">
                        Please confirm the new password.
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-shield-lock"></i> Update Password
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="<?php echo htmlspecialchars($usernameUrl); ?>" class="text-decoration-none">
                        <i class="bi bi-person-badge"></i> Forgot Username?
                    </a>
                </div>

                <div class="text-center mt-2">
                    <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>

        <div class="login-footer">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($branding['name']); ?>
            </p>
            <p class="mb-0">
                <small>Version <?php echo APP_VERSION; ?></small>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/script.js"></script>
<script>
(function() {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

</body>
</html>
