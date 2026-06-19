<?php
/**
 * Student Login
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();

if (isLoggedIn()) {
    redirect(getUserHomeUrl());
}

$pageTitle = 'Student Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = loginUser($identifier, $password);

    if ($result['success']) {
        if (($result['user']['role'] ?? '') !== 'student') {
            logoutUser();
            exit();
        }

        redirect(APP_URL . '/modules/student/dashboard.php');
    } else {
        $error = $result['message'];
    }
}

$schoolSettings = getSchoolSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($schoolSettings['school_name']); ?> - Student Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
                <div class="login-school-subtitle">Student Portal</div>
            </div>
        </div>

        <div class="login-hero-copy">
            <h1>Access your marks, fees, admit card, and school documents in one place.</h1>
            <p>Students can securely sign in to view their own records, download approved certificates, check due fees, and stay updated with announcements.</p>
        </div>

        <div class="login-pill-row">
            <span class="login-pill"><i class="bi bi-file-earmark-text"></i> Marksheet downloads</span>
            <span class="login-pill"><i class="bi bi-receipt"></i> Fee receipts</span>
            <span class="login-pill"><i class="bi bi-card-heading"></i> Admit card access</span>
        </div>

        <div class="login-metrics">
            <div class="login-metric">
                <strong>Private</strong>
                <span>Only your own data</span>
            </div>
            <div class="login-metric">
                <strong>Simple</strong>
                <span>Easy login on any device</span>
            </div>
            <div class="login-metric">
                <strong>Current</strong>
                <span>Approved school updates</span>
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
                <p class="mb-0">Student Login</p>
            </div>

            <div class="login-body">
                <h4 class="text-center mb-4">Login to Your Student Account</h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['registered'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> Your registration has been submitted and is waiting for super admin approval.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person-badge"></i> Admission No or Email
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Enter admission no or email" required autofocus>
                        <div class="invalid-feedback">
                            Please enter your admission number or email.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter password" required>
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
                            <i class="bi bi-box-arrow-in-right"></i> Student Login
                        </button>
                    </div>

                    <div class="login-links text-center">
                        <a href="register.php">
                            <i class="bi bi-pencil-square"></i> Student Registration
                        </a>
                        <a href="<?php echo APP_URL; ?>/modules/auth/login.php">
                            <i class="bi bi-shield-lock"></i> Staff Login
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
