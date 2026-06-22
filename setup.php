<?php
/**
 * Initial Setup Page
 * Creates first Super Admin user and imports database
 */

// Include configuration (handles session start)
require_once 'config/config.php';

$pageTitle = 'System Setup';
$step = $_GET['step'] ?? 1;
$message = '';
$error = '';

// Check if setup is already complete
if (superAdminExists() && $step == 1) {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['import_database'])) {
        // Import database
        $sqlFile = __DIR__ . '/database/school_management.sql';

        if (!file_exists($sqlFile)) {
            $error = 'Database SQL file not found!';
        } else {
            $sql = file_get_contents($sqlFile);
            $conn = getDbConnection();

            // Execute multi-query
            if ($conn->multi_query($sql)) {
                do {
                    // Store result if available
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());

                $message = 'Database imported successfully!';
                $step = 2;
            } else {
                $error = 'Database import failed: ' . $conn->error;
            }
        }
    }

    if (isset($_POST['create_admin'])) {
        // Create super admin
        $data = [
            'username' => sanitize($_POST['username']),
            'email' => sanitize($_POST['email']),
            'password' => $_POST['password'],
            'full_name' => sanitize($_POST['full_name']),
            'role' => 'super_admin',
            'mobile' => sanitize($_POST['mobile'] ?? '')
        ];

        // Validate password confirmation
        if ($data['password'] !== $_POST['confirm_password']) {
            $error = 'Passwords do not match!';
        } else {
            $result = registerUser($data);

            if ($result['success']) {
                $message = 'Super Admin created successfully! You can now login.';
                $step = 3;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?> - Setup</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>

<div class="login-container">
    <div class="login-card" style="max-width: 600px;">
        <!-- Header -->
        <div class="login-header">
            <i class="bi bi-gear-fill" style="font-size: 4rem;"></i>
            <h3 class="mt-3">System Setup</h3>
            <p class="mb-0"><?php echo htmlspecialchars(APP_NAME); ?> v<?php echo APP_VERSION; ?></p>
        </div>

        <!-- Body -->
        <div class="login-body">

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Step 1: Import Database -->
            <?php if ($step == 1): ?>
                <h4 class="mb-4">
                    <span class="badge bg-primary">Step 1 of 2</span>
                    Import Database
                </h4>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Before proceeding:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Ensure MySQL/phpMyAdmin is running</li>
                        <li>Database credentials are correct in config/database.php</li>
                        <li>This will create database and tables automatically</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="d-grid">
                        <button type="submit" name="import_database" class="btn btn-primary btn-lg">
                            <i class="bi bi-database-fill-add"></i> Import Database Now
                        </button>
                    </div>
                </form>

            <!-- Step 2: Create Super Admin -->
            <?php elseif ($step == 2): ?>
                <h4 class="mb-4">
                    <span class="badge bg-primary">Step 2 of 2</span>
                    Create Super Admin Account
                </h4>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="full_name" class="form-label required">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                               placeholder="Enter full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? 'Admin User'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label required">Username</label>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Enter username" value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label required">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="Enter email" value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@school.com'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="mobile" class="form-label">Mobile Number</label>
                        <input type="text" class="form-control" id="mobile" name="mobile"
                               placeholder="10-digit mobile number" maxlength="10">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label required">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter password" value="<?php echo htmlspecialchars($_POST['password'] ?? 'admin123'); ?>" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label required">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter password" value="<?php echo htmlspecialchars($_POST['confirm_password'] ?? 'admin123'); ?>" required minlength="6">
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="create_admin" class="btn btn-success btn-lg">
                            <i class="bi bi-person-plus-fill"></i> Create Super Admin
                        </button>
                    </div>
                </form>

            <!-- Step 3: Complete -->
            <?php else: ?>
                <div class="text-center">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    <h4 class="mt-3">Setup Complete!</h4>
                    <p class="text-muted">Your <?php echo htmlspecialchars(APP_NAME); ?> is ready to use.</p>

                    <div class="alert alert-success text-start mt-4">
                        <strong>Next Steps:</strong>
                        <ol class="mb-0">
                            <li>Login with your super admin credentials</li>
                            <li>Configure school settings</li>
                            <li>Add classes and sections</li>
                            <li>Set up fee heads</li>
                            <li>Create staff users with permissions</li>
                            <li>Start adding students</li>
                        </ol>
                    </div>

                    <a href="index.php" class="btn btn-primary btn-lg mt-3">
                        <i class="bi bi-box-arrow-in-right"></i> Go to Login
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <!-- Footer -->
        <div class="login-footer">
            <p class="mb-0"><?php echo htmlspecialchars(APP_NAME); ?> &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

// Password match validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;

    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

</body>
</html>
