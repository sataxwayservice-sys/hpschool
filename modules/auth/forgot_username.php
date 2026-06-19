<?php
/**
 * Forgot Username
 * Look up a username using email or mobile number
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
$forgotPasswordUrl = $isSuperAdminContext
    ? APP_URL . '/modules/auth/forgot_password.php?context=super_admin'
    : APP_URL . '/modules/auth/forgot_password.php';
$branding = $isSuperAdminContext ? getCompanyBranding() : getSchoolSettings();
$pageTitle = $isSuperAdminContext ? 'Super Admin Forgot Username' : 'Forgot Username';
$error = '';
$matches = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['email_or_mobile'] ?? '');

    if ($identifier === '') {
        $error = 'Please enter your email address or mobile number.';
    } else {
        $roleClause = $isSuperAdminContext ? " AND role = 'super_admin'" : '';
        $matches = fetchAll(
            "SELECT user_id, username, full_name, email, mobile, role, is_active
             FROM users
             WHERE (email = ? OR mobile = ?)" . $roleClause . "
             ORDER BY user_id DESC",
            'ss',
            [$identifier, $identifier]
        );

        if (empty($matches)) {
            $error = 'No matching account found.';
        }
    }
}

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
                    <i class="bi bi-person-badge-fill" style="font-size: 4rem;"></i>
                <?php endif; ?>
            <?php else: ?>
                <i class="bi bi-person-badge-fill" style="font-size: 4rem;"></i>
            <?php endif; ?>
            <h3 class="mt-3"><?php echo htmlspecialchars($branding['name']); ?></h3>
            <p class="mb-0"><?php echo $isSuperAdminContext ? 'Super Admin Account Recovery' : 'Account Recovery'; ?></p>
        </div>

        <div class="login-body">
            <h4 class="text-center mb-4"><?php echo $isSuperAdminContext ? 'Find Your Super Admin Username' : 'Find Your Username'; ?></h4>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <?php if ($isSuperAdminContext): ?>
                    Enter the email address or mobile number linked to your super admin account.
                <?php else: ?>
                    Enter the email address or mobile number linked to your account.
                <?php endif; ?>
            </div>

            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email_or_mobile" class="form-label">
                        <i class="bi bi-envelope-at"></i> Email or Mobile Number
                    </label>
                    <input type="text" class="form-control" id="email_or_mobile" name="email_or_mobile"
                           placeholder="<?php echo $isSuperAdminContext ? 'Enter super admin email or mobile number' : 'Enter email or mobile number'; ?>" required autofocus>
                    <div class="invalid-feedback">
                        Please enter your email address or mobile number.
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-search"></i> Find Username
                    </button>
                </div>
            </form>

            <?php if (!empty($matches)): ?>
                <div class="mt-4">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        We found <?php echo count($matches); ?> matching account<?php echo count($matches) > 1 ? 's' : ''; ?>.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matches as $match): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($match['full_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($match['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $match['role']))); ?></td>
                                        <td>
                                            <?php if (intval($match['is_active']) === 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="<?php echo htmlspecialchars($forgotPasswordUrl); ?>" class="text-decoration-none">
                    <i class="bi bi-key"></i> Forgot Password?
                </a>
            </div>

            <div class="text-center mt-2">
                <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
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
