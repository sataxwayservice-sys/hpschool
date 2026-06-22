<?php
/**
 * Super Admin Login
 * Dedicated login page for restricted administrative access.
 */

require_once '../../config/config.php';

if (isLoggedIn()) {
    redirect(getUserHomeUrl());
}

$pageTitle = 'Super Admin Login';
$error = '';
$superAdminExists = function_exists('superAdminExists') ? superAdminExists() : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $users = fetchAll(
            "SELECT user_id, username, email, full_name, role, password, status, status_reason, is_active, school_id, student_id
             FROM users
             WHERE (username = ? OR email = ? OR full_name = ?) AND role = 'super_admin'
             ORDER BY user_id ASC",
            'sss',
            [$identifier, $identifier, $identifier]
        );

        if (empty($users)) {
            $error = $superAdminExists
                ? 'Invalid super admin username or password.'
                : 'No super admin account exists on this database yet. Use setup.php or fix_login_now.php to create one first.';
        } else {
            $user = null;
            foreach ($users as $candidate) {
                $storedPassword = (string)($candidate['password'] ?? '');
                $passwordOk = function_exists('passwordMatchesCompat')
                    ? passwordMatchesCompat($password, $storedPassword)
                    : (password_verify($password, $storedPassword) || hash_equals($storedPassword, $password));

                if (!$passwordOk) {
                    continue;
                }

                $status = strtolower(trim((string)($candidate['status'] ?? '')));
                $reason = trim((string)($candidate['status_reason'] ?? ''));
                $isActive = intval($candidate['is_active'] ?? 0) === 1;

                if ($status === 'pending') {
                    $error = 'Your super admin account is pending approval.';
                } elseif ($status === 'rejected') {
                    $error = $reason !== ''
                        ? 'Your super admin account was rejected. ' . $reason
                        : 'Your super admin account was rejected.';
                } elseif ($status === 'blocked') {
                    $error = $reason !== ''
                        ? 'Your super admin account has been blocked. ' . $reason
                        : 'Your super admin account has been blocked.';
                } elseif (!$isActive) {
                    $error = 'Your super admin account is inactive. Please contact support.';
                } else {
                    $user = $candidate;
                }

                if ($user || $error !== '') {
                    break;
                }
            }

            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['student_id'] = $user['student_id'] ?? null;
                $_SESSION['school_id'] = $user['school_id'] ?? null;
                $_SESSION['is_logged_in'] = true;

                executeQuery(
                    "UPDATE users SET last_login = NOW() WHERE user_id = ?",
                    'i',
                    [$user['user_id']]
                );

                logActivity($user['user_id'], 'Login', 'Authentication', 'Super admin logged in successfully');
                redirect(getUserHomeUrl($user));
            } elseif ($error === '') {
                $error = 'Invalid super admin username or password.';
            }
        }
    }
}

$companyBranding = getCompanyBranding();
$companyLogoSrc = getCompanyLogoSrc($companyBranding['logo']);
$resetUrl = APP_URL . '/modules/auth/forgot_password.php?context=super_admin';
$usernameUrl = APP_URL . '/modules/auth/forgot_username.php?context=super_admin';
$staffLoginUrl = APP_URL . '/modules/auth/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($companyBranding['name']); ?> - Super Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/style.css'); ?>">
</head>
<body class="auth-super-admin-page">

<div class="login-container">
    <section class="login-hero">
        <div class="login-brand">
            <?php if (!empty($companyLogoSrc)): ?>
                <img src="<?php echo htmlspecialchars($companyLogoSrc); ?>" alt="<?php echo htmlspecialchars($companyBranding['name']); ?>" class="login-brand-logo">
            <?php else: ?>
                <div class="login-brand-badge">
                    <?php echo htmlspecialchars(strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $companyBranding['name']), 0, 2) ?: 'SA')); ?>
                </div>
            <?php endif; ?>
            <div class="login-brand-copy">
                <div class="login-school-name"><?php echo htmlspecialchars($companyBranding['name']); ?></div>
                <div class="login-school-subtitle"><?php echo htmlspecialchars($companyBranding['tagline'] !== '' ? $companyBranding['tagline'] : 'Administrative control center'); ?></div>
            </div>
        </div>

        <div class="login-hero-copy">
            <h1>Super Admin Login</h1>
            <p>Sign in to manage approvals, branding, users, reports, and system security from the restricted control panel.</p>
        </div>

        <div class="login-pill-row">
            <span class="login-pill"><i class="bi bi-shield-lock"></i> Approval workflow</span>
            <span class="login-pill"><i class="bi bi-gear"></i> System settings</span>
            <span class="login-pill"><i class="bi bi-clock-history"></i> Audit trail</span>
        </div>

        <div class="login-metrics">
            <div class="login-metric">
                <strong>Restricted</strong>
                <span>Super admin only</span>
            </div>
            <div class="login-metric">
                <strong>Verified</strong>
                <span>Credential-based access</span>
            </div>
            <div class="login-metric">
                <strong>Controlled</strong>
                <span>Secure administrative tools</span>
            </div>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="login-header">
                <?php if (!empty($companyLogoSrc)): ?>
                    <img src="<?php echo htmlspecialchars($companyLogoSrc); ?>"
                         alt="<?php echo htmlspecialchars($companyBranding['name']); ?>" class="img-fluid">
                <?php else: ?>
                    <i class="bi bi-shield-lock-fill" style="font-size: 3.6rem; color: #0f2f57;"></i>
                <?php endif; ?>
                <h3 class="mt-3"><?php echo htmlspecialchars($companyBranding['name']); ?></h3>
                <p class="mb-0">Super Admin Login</p>
            </div>

            <div class="login-body">
                <h4 class="text-center mb-4">Super Admin Login</h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['reset'])): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-key-fill"></i> Password updated successfully. You can now login with the new password.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person-badge"></i> Username or Email
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Enter super admin username or email" autocomplete="username" required autofocus>
                        <div class="invalid-feedback">
                            Please enter your username or email.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter password" autocomplete="current-password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Sign In
                        </button>
                    </div>

                    <div class="login-links text-center">
                        <a href="<?php echo htmlspecialchars($resetUrl); ?>">
                            <i class="bi bi-question-circle"></i> Forgot Password?
                        </a>
                        <a href="<?php echo htmlspecialchars($usernameUrl); ?>">
                            <i class="bi bi-person-badge"></i> Forgot Username?
                        </a>
                        <a href="<?php echo htmlspecialchars($staffLoginUrl); ?>">
                            <i class="bi bi-shield-check"></i> Staff Login
                        </a>
                    </div>
                </form>
            </div>

            <div class="login-footer">
                <p class="mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyBranding['name']); ?>
                </p>
                <p class="mb-0">
                    <small>Version <?php echo APP_VERSION; ?></small>
                </p>
            </div>
        </div>
    </section>
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
