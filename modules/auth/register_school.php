<?php
/**
 * School Registration
 * Public signup for a school and its first admin account
 */

require_once '../../config/config.php';

if (isLoggedIn()) {
    redirect(getUserHomeUrl());
}

$pageTitle = 'Register School';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = schoolRegistrationCreateRequest([
        'school_name' => sanitize($_POST['school_name'] ?? ''),
        'school_code' => sanitize($_POST['school_code'] ?? ''),
        'admin_name' => sanitize($_POST['admin_name'] ?? ''),
        'mobile' => sanitize($_POST['mobile'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'username' => sanitize($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'address' => sanitize($_POST['address'] ?? ''),
    ]);

    if (!empty($result['success'])) {
        header('Location: login.php?school_registered=1');
        exit();
    }

    $error = $result['message'] ?? 'Unable to submit the registration.';
}

$companyBranding = getCompanyBranding();
$companyLogoSrc = getCompanyLogoSrc($companyBranding['logo']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($companyBranding['name']); ?> - Register School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo filemtime(BASE_PATH . '/assets/css/style.css'); ?>">
</head>
<body class="auth-registration-page">

<div class="login-container">
    <section class="login-hero">
        <div class="login-brand">
            <?php if (!empty($companyLogoSrc)): ?>
                <img src="<?php echo htmlspecialchars($companyLogoSrc); ?>" alt="<?php echo htmlspecialchars($companyBranding['name']); ?>" class="login-brand-logo">
            <?php else: ?>
                <div class="login-brand-badge"><?php echo htmlspecialchars(strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $companyBranding['name']), 0, 2) ?: 'CO')); ?></div>
            <?php endif; ?>
            <div class="login-brand-copy">
                <div class="login-school-name"><?php echo htmlspecialchars($companyBranding['name']); ?></div>
                <div class="login-school-subtitle"><?php echo htmlspecialchars($companyBranding['tagline'] ?: 'School Registration'); ?></div>
            </div>
        </div>

        <div class="login-hero-copy">
            <h1>Register your school with a guided approval flow.</h1>
            <p>Submit the school profile and first admin account in one step. The request stays pending until the Super Admin reviews and approves it.</p>
            <?php if (!empty($companyBranding['address']) || !empty($companyBranding['phone']) || !empty($companyBranding['email']) || !empty($companyBranding['website'])): ?>
                <div class="mt-3 small text-white-50">
                    <div class="fw-semibold text-white"><?php echo htmlspecialchars($companyBranding['name']); ?></div>
                    <?php if (!empty($companyBranding['address'])): ?>
                        <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($companyBranding['address']); ?></div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-3 mt-2">
                        <?php if (!empty($companyBranding['phone'])): ?>
                            <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($companyBranding['phone']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($companyBranding['email'])): ?>
                            <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($companyBranding['email']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($companyBranding['website'])): ?>
                            <span><i class="bi bi-globe"></i> <?php echo htmlspecialchars($companyBranding['website']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="registration-steps">
            <div class="registration-step">
                <span>01</span>
                <div>
                    <strong>Submit profile</strong>
                    <p>School details, contact info, and the first admin account are captured together.</p>
                </div>
            </div>
            <div class="registration-step">
                <span>02</span>
                <div>
                    <strong>Super Admin review</strong>
                    <p>Your request stays pending until the account and school record are approved.</p>
                </div>
            </div>
            <div class="registration-step">
                <span>03</span>
                <div>
                    <strong>Go live</strong>
                    <p>Once approved, the admin can sign in and start managing the school workspace.</p>
                </div>
            </div>
        </div>

        <div class="registration-summary">
            <div class="registration-summary-item">
                <i class="bi bi-shield-check"></i>
                <div>
                    <strong>Pending approval</strong>
                    <span>Controlled onboarding</span>
                </div>
            </div>
            <div class="registration-summary-item">
                <i class="bi bi-building"></i>
                <div>
                    <strong>School profile</strong>
                    <span>Official institution data</span>
                </div>
            </div>
            <div class="registration-summary-item">
                <i class="bi bi-person-badge"></i>
                <div>
                    <strong>Admin access</strong>
                    <span>First account included</span>
                </div>
            </div>
        </div>

        <div class="registration-support">
            <div class="registration-support-title">Need a cleaner setup?</div>
            <p>Use the public company branding above so every registration screen matches your product identity.</p>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card login-card--registration" style="max-width: 840px;">
            <div class="login-header">
                <?php if (!empty($companyLogoSrc)): ?>
                    <img src="<?php echo htmlspecialchars($companyLogoSrc); ?>" alt="<?php echo htmlspecialchars($companyBranding['name']); ?>" class="img-fluid">
                <?php else: ?>
                    <div class="login-brand-badge mx-auto" style="width: 88px; height: 88px; font-size: 1.6rem;">
                        <?php echo htmlspecialchars(strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $companyBranding['name']), 0, 2) ?: 'CO')); ?>
                    </div>
                <?php endif; ?>
                <h3 class="mt-3"><?php echo htmlspecialchars($companyBranding['name']); ?></h3>
                <p class="mb-0"><?php echo htmlspecialchars($companyBranding['tagline'] ?: 'Register School'); ?></p>
            </div>

            <div class="login-body">
                <div class="registration-intro">
                    <h4>School Registration</h4>
                    <p>Complete the school profile and create the first admin login in one guided application.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="registration-section-title">School Profile</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="school_name" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="school_name" name="school_name"
                                   value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="school_code" class="form-label">School Code / Short Name</label>
                            <input type="text" class="form-control" id="school_code" name="school_code"
                                   value="<?php echo htmlspecialchars($_POST['school_code'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label for="admin_name" class="form-label">Principal/Admin Full Name</label>
                            <input type="text" class="form-control" id="admin_name" name="admin_name"
                                   value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile" name="mobile"
                                   value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" maxlength="10" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="registration-section-title mt-4">Admin Access</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <label for="password" class="form-label mb-0">Password</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="generate_password_btn">
                                    <i class="bi bi-shuffle"></i> Generate Password
                                </button>
                            </div>
                            <input type="password" class="form-control mt-2" id="password" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-3">
                        <i class="bi bi-info-circle"></i>
                        After submission, the school and admin account will stay pending until Super Admin approval. The password can be generated here or reset later from the School Requests page.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-send"></i> Submit Registration
                        </button>
                        <a href="<?php echo APP_URL; ?>/modules/auth/login.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </form>
            </div>

            <div class="login-footer">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyBranding['name']); ?></p>
                <p class="mb-0"><small>Version <?php echo APP_VERSION; ?></small></p>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/script.js?v=<?php echo filemtime(BASE_PATH . '/assets/js/script.js'); ?>"></script>
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

    const generateBtn = document.getElementById('generate_password_btn')
    const passwordField = document.getElementById('password')
    const confirmPasswordField = document.getElementById('confirm_password')

    const generatePassword = (length = 10) => {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
        let value = ''
        const randomValues = window.crypto && window.crypto.getRandomValues
            ? window.crypto.getRandomValues(new Uint32Array(length))
            : null
        for (let i = 0; i < length; i++) {
            const randomIndex = randomValues
                ? randomValues[i] % chars.length
                : Math.floor(Math.random() * chars.length)
            value += chars.charAt(randomIndex)
        }
        return value
    }

    if (generateBtn && passwordField && confirmPasswordField) {
        generateBtn.addEventListener('click', function() {
            const value = generatePassword(10)
            passwordField.value = value
            confirmPasswordField.value = value
            passwordField.focus()
        })
    }
})()
</script>

</body>
</html>
