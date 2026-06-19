<?php
/**
 * Login Page
 * User authentication
 */

// Include configuration (handles session start)
require_once '../../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(getUserHomeUrl());
}

$pageTitle = 'Login';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = loginUser($username, $password);

    if ($result['success']) {
        redirect(getUserHomeUrl($result['user'] ?? null));
    } else {
        $error = $result['message'];
    }
}

$schoolSettings = getSchoolSettings();

$loginText = function (string $key, string $default) use ($schoolSettings): string {
    $value = trim((string)($schoolSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$loginBrandSubtitle = $loginText('login_brand_subtitle', 'School Management System');
$loginHeroTitle = $loginText('login_hero_title', 'Secure access for staff, records, and school operations.');
$loginHeroSubtitle = $loginText('login_hero_subtitle', 'Sign in to manage admissions, fees, reports, marks, documents, and daily workflows from one premium school ERP interface.');
$loginPill1 = $loginText('login_pill_1', 'Role-based access');
$loginPill2 = $loginText('login_pill_2', 'Reports and receipts');
$loginPill3 = $loginText('login_pill_3', 'Student management');
$loginMetric1Title = $loginText('login_metric_1_title', 'Secure');
$loginMetric1Text = $loginText('login_metric_1_text', 'Controlled by user role');
$loginMetric2Title = $loginText('login_metric_2_title', 'Fast');
$loginMetric2Text = $loginText('login_metric_2_text', 'Quick access on any device');
$loginMetric3Title = $loginText('login_metric_3_title', 'Reliable');
$loginMetric3Text = $loginText('login_metric_3_text', 'Daily school operations ready');
$loginCardSubtitle = $loginText('login_card_subtitle', 'Staff Login');
$loginCardTitle = $loginText('login_card_title', 'Login to Your Account');
$loginUsernameLabel = $loginText('login_username_label', 'Username or Email');
$loginUsernamePlaceholder = $loginText('login_username_placeholder', 'Enter username or email');
$loginPasswordLabel = $loginText('login_password_label', 'Password');
$loginPasswordPlaceholder = $loginText('login_password_placeholder', 'Enter password');
$loginRememberMeLabel = $loginText('login_remember_me_label', 'Remember me');
$loginButtonText = $loginText('login_button_text', 'Login');
$loginForgotPasswordText = $loginText('login_forgot_password_text', 'Forgot Password?');
$loginForgotUsernameText = $loginText('login_forgot_username_text', 'Forgot Username?');
$loginStudentLoginText = $loginText('login_student_login_text', 'Student Login');
$loginAlertRegisteredText = $loginText('login_alert_registered_text', 'Your account has been created. Please login with your new credentials.');
$loginAlertSchoolRegisteredText = $loginText('login_alert_school_registered_text', 'School registration submitted. Waiting for Super Admin approval.');
$loginAlertResetText = $loginText('login_alert_reset_text', 'Password updated successfully. You can now login with the new password.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($schoolSettings['school_name']); ?> - Login</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/style.css'); ?>">
</head>
<body>

<div class="login-container">
    <section class="login-hero">
        <div class="login-brand">
            <?php if (!empty($schoolSettings['login_logo'])): ?>
                <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $schoolSettings['login_logo']; ?>" alt="Logo" class="login-brand-logo">
            <?php else: ?>
                <div class="login-brand-badge">S</div>
            <?php endif; ?>
            <div class="login-brand-copy">
                <div class="login-school-name"><?php echo htmlspecialchars($schoolSettings['school_name']); ?></div>
                <div class="login-school-subtitle"><?php echo htmlspecialchars($loginBrandSubtitle); ?></div>
            </div>
        </div>

        <div class="login-hero-copy">
            <h1><?php echo htmlspecialchars($loginHeroTitle); ?></h1>
            <p><?php echo htmlspecialchars($loginHeroSubtitle); ?></p>
        </div>

        <div class="login-pill-row">
            <span class="login-pill"><i class="bi bi-shield-lock"></i> <?php echo htmlspecialchars($loginPill1); ?></span>
            <span class="login-pill"><i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($loginPill2); ?></span>
            <span class="login-pill"><i class="bi bi-people"></i> <?php echo htmlspecialchars($loginPill3); ?></span>
        </div>

        <div class="login-metrics">
            <div class="login-metric">
                <strong><?php echo htmlspecialchars($loginMetric1Title); ?></strong>
                <span><?php echo htmlspecialchars($loginMetric1Text); ?></span>
            </div>
            <div class="login-metric">
                <strong><?php echo htmlspecialchars($loginMetric2Title); ?></strong>
                <span><?php echo htmlspecialchars($loginMetric2Text); ?></span>
            </div>
            <div class="login-metric">
                <strong><?php echo htmlspecialchars($loginMetric3Title); ?></strong>
                <span><?php echo htmlspecialchars($loginMetric3Text); ?></span>
            </div>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="login-header">
                <?php if (!empty($schoolSettings['login_logo'])): ?>
                    <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $schoolSettings['login_logo']; ?>"
                         alt="Logo" class="img-fluid">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill" style="font-size: 3.6rem; color: #0f2f57;"></i>
                <?php endif; ?>
                <h3 class="mt-3"><?php echo htmlspecialchars($schoolSettings['school_name']); ?></h3>
                <p class="mb-0"><?php echo htmlspecialchars($loginCardSubtitle); ?></p>
            </div>

            <div class="login-body">
                <h4 class="text-center mb-4"><?php echo htmlspecialchars($loginCardTitle); ?></h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['registered'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($loginAlertRegisteredText); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['school_registered'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($loginAlertSchoolRegisteredText); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['reset'])): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-key-fill"></i> <?php echo htmlspecialchars($loginAlertResetText); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($loginUsernameLabel); ?>
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="<?php echo htmlspecialchars($loginUsernamePlaceholder); ?>" required autofocus>
                        <div class="invalid-feedback">
                            Please enter your username or email.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> <?php echo htmlspecialchars($loginPasswordLabel); ?>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="<?php echo htmlspecialchars($loginPasswordPlaceholder); ?>" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me"><?php echo htmlspecialchars($loginRememberMeLabel); ?></label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> <?php echo htmlspecialchars($loginButtonText); ?>
                        </button>
                    </div>

                    <div class="login-links text-center">
                        <a href="<?php echo APP_URL; ?>/modules/auth/forgot_password.php">
                            <i class="bi bi-question-circle"></i> <?php echo htmlspecialchars($loginForgotPasswordText); ?>
                        </a>
                        <a href="<?php echo APP_URL; ?>/modules/auth/forgot_username.php">
                            <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($loginForgotUsernameText); ?>
                        </a>
                        <a href="<?php echo APP_URL; ?>/modules/auth/super_admin_login.php">
                            <i class="bi bi-shield-lock-fill"></i> Super Admin Login
                        </a>
                        <a href="<?php echo APP_URL; ?>/modules/student/login.php">
                            <i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($loginStudentLoginText); ?>
                        </a>
                    </div>
                </form>
            </div>

            <div class="login-footer">
                <p class="mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolSettings['school_name']); ?>
                </p>
                <p class="mb-0">
                    <small>Version <?php echo APP_VERSION; ?></small>
                </p>
            </div>
        </div>
    </section>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Custom JS -->
<script src="<?php echo APP_URL; ?>/assets/js/script.js"></script>

<script>
// Form validation
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
