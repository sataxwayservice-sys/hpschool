<?php
/**
 * One-click Super Admin creator/reset tool.
 * Uses the application database config so it works on local and hosted installs.
 */

require_once __DIR__ . '/config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getDbConnection();
if (!$conn) {
    die('<h1>Database Connection Failed</h1><p>Unable to connect to the configured database.</p>');
}

$created = false;
$error = '';
$username = 'admin';
$email = 'admin@school.com';
$password = 'admin123';

if (!function_exists('createAdminValueExists')) {
    function createAdminValueExists(string $field, string $value): bool {
        $field = in_array($field, ['username', 'email'], true) ? $field : 'username';
        $row = fetchOne("SELECT user_id FROM users WHERE {$field} = ? LIMIT 1", 's', [$value]);
        return !empty($row);
    }
}

if (!function_exists('createAdminPickUniqueUsername')) {
    function createAdminPickUniqueUsername(): string {
        $baseCandidates = ['admin', 'superadmin', 'admin_sa', 'schooladmin'];
        foreach ($baseCandidates as $candidate) {
            if (!createAdminValueExists('username', $candidate)) {
                return $candidate;
            }
        }

        $fallback = 'admin' . date('His');
        while (createAdminValueExists('username', $fallback)) {
            $fallback .= (string) mt_rand(1, 9);
        }

        return $fallback;
    }
}

if (!function_exists('createAdminPickUniqueEmail')) {
    function createAdminPickUniqueEmail(): string {
        $baseCandidates = ['admin@school.com', 'superadmin@school.com', 'adminsa@school.com', 'schooladmin@school.com'];
        foreach ($baseCandidates as $candidate) {
            if (!createAdminValueExists('email', $candidate)) {
                return $candidate;
            }
        }

        $localPart = 'admin' . date('His');
        $fallback = $localPart . '@school.com';
        while (createAdminValueExists('email', $fallback)) {
            $localPart .= (string) mt_rand(1, 9);
            $fallback = $localPart . '@school.com';
        }

        return $fallback;
    }
}

$existingSuperAdmin = fetchOne(
    "SELECT user_id, username, email FROM users WHERE role = 'super_admin' ORDER BY user_id ASC LIMIT 1"
);

if (isset($_POST['create_now'])) {
    $canonicalUsername = 'admin';
    $canonicalEmail = 'admin@school.com';
    $canonicalFullName = 'Admin User';
    $canonicalPassword = 'admin123';
    $hashedPassword = password_hash($canonicalPassword, PASSWORD_DEFAULT);

    if (!empty($existingSuperAdmin['user_id'])) {
        $result = executeQuery(
            "UPDATE users
             SET username = ?, email = ?, full_name = ?, password = ?, is_active = 1, status = 'active', updated_at = NOW()
             WHERE user_id = ?",
            'ssssi',
            [$canonicalUsername, $canonicalEmail, $canonicalFullName, $hashedPassword, intval($existingSuperAdmin['user_id'])]
        );

        if ($result !== false) {
            $created = true;
            $username = $canonicalUsername;
            $email = $canonicalEmail;
            $password = $canonicalPassword;
            $message = 'Super Admin credentials have been reset to admin / admin123.';
        } else {
            $error = 'Failed to reset the super admin credentials.';
        }
    } else {
        $result = registerUser([
            'username' => $canonicalUsername,
            'email' => $canonicalEmail,
            'password' => $canonicalPassword,
            'full_name' => $canonicalFullName,
            'role' => 'super_admin',
            'mobile' => '',
            'status' => 'active',
            'is_active' => 1,
        ]);

        if (!empty($result['success'])) {
            $created = true;
            $username = $canonicalUsername;
            $email = $canonicalEmail;
            $password = $canonicalPassword;
            $message = 'Super Admin user has been created with admin / admin123.';
        } else {
            $error = 'Failed to create user: ' . ($result['message'] ?? 'Unknown error');
        }
    }
}

$users = fetchAll("SELECT username, email, role, is_active FROM users ORDER BY role, username");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Recovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .box { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .code { background: #f8f9fa; border: 1px dashed #0d6efd; padding: 12px 16px; border-radius: 10px; }
    </style>
</head>
<body>
<div class="box">
    <h1 class="mb-3"><i class="bi bi-shield-lock"></i> Super Admin Recovery</h1>
    <p class="text-muted">This page uses the configured database, so it works on InfinityFree and local XAMPP.</p>

    <?php if ($created): ?>
        <div class="alert alert-success">
            <strong>Done.</strong> <?php echo htmlspecialchars($message); ?>
        </div>
        <div class="code mb-3">
            <div><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></div>
            <div><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></div>
            <div><strong>Password:</strong> <?php echo htmlspecialchars($password); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <button type="submit" name="create_now" class="btn btn-success btn-lg">
            <i class="bi bi-arrow-repeat"></i> Create / Reset Super Admin
        </button>
        <a href="index.php" class="btn btn-primary btn-lg ms-2">
            <i class="bi bi-box-arrow-in-right"></i> Go to Login
        </a>
    </form>

    <div class="alert alert-info">
        <strong>Tip:</strong> If login still fails after running this, open the Super Admin Login page and use the credentials shown above.
    </div>

    <h5 class="mt-4">Current users</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Active</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['role'] ?? ''); ?></td>
                        <td><?php echo !empty($user['is_active']) ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="small text-muted">
        For safety, delete this file after you finish the login reset.
    </div>
</div>
</body>
</html>
