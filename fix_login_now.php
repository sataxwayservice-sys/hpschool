<?php
/**
 * Emergency Login Fix
 * Uses the configured database so it works on live hosting too.
 */

require_once __DIR__ . '/config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getDbConnection();
if (!$conn) {
    die('<h1>Database Connection Failed</h1><p>Unable to connect to the configured database.</p>');
}

$message = '';
$error = '';
$finalUsername = '';
$finalEmail = '';
$finalPassword = '';

if (!function_exists('fixLoginValueExists')) {
    function fixLoginValueExists(string $field, string $value): bool {
        $field = in_array($field, ['username', 'email'], true) ? $field : 'username';
        $row = fetchOne("SELECT user_id FROM users WHERE {$field} = ? LIMIT 1", 's', [$value]);
        return !empty($row);
    }
}

if (!function_exists('fixLoginPickUniqueUsername')) {
    function fixLoginPickUniqueUsername(): string {
        $baseCandidates = ['admin', 'superadmin', 'admin_sa', 'schooladmin'];
        foreach ($baseCandidates as $candidate) {
            if (!fixLoginValueExists('username', $candidate)) {
                return $candidate;
            }
        }

        $fallback = 'admin' . date('His');
        while (fixLoginValueExists('username', $fallback)) {
            $fallback .= (string) mt_rand(1, 9);
        }

        return $fallback;
    }
}

if (!function_exists('fixLoginPickUniqueEmail')) {
    function fixLoginPickUniqueEmail(): string {
        $baseCandidates = ['admin@school.com', 'superadmin@school.com', 'adminsa@school.com', 'schooladmin@school.com'];
        foreach ($baseCandidates as $candidate) {
            if (!fixLoginValueExists('email', $candidate)) {
                return $candidate;
            }
        }

        $localPart = 'admin' . date('His');
        $fallback = $localPart . '@school.com';
        while (fixLoginValueExists('email', $fallback)) {
            $localPart .= (string) mt_rand(1, 9);
            $fallback = $localPart . '@school.com';
        }

        return $fallback;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'], $_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    $newPassword = trim((string)($_POST['new_password'] ?? ''));

    if ($userId <= 0 || $newPassword === '') {
        $error = 'Please choose a user and enter a new password.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $userRow = fetchOne("SELECT username, email, full_name, role FROM users WHERE user_id = ? LIMIT 1", 'i', [$userId]);

        if (!empty($userRow) && strtolower((string)($userRow['role'] ?? '')) === 'super_admin') {
            $result = executeQuery(
                "UPDATE users
                 SET username = ?, email = ?, full_name = ?, password = ?, is_active = 1, status = 'active', updated_at = NOW()
                 WHERE user_id = ?",
                'ssssi',
                ['admin', 'admin@school.com', 'Admin User', $hashedPassword, $userId]
            );
        } else {
            $result = executeQuery(
                "UPDATE users SET password = ?, is_active = 1, updated_at = NOW() WHERE user_id = ?",
                'si',
                [$hashedPassword, $userId]
            );
        }

        if ($result !== false) {
            $userRow = fetchOne("SELECT username, email FROM users WHERE user_id = ? LIMIT 1", 'i', [$userId]);
            $finalUsername = (string)($userRow['username'] ?? '');
            $finalEmail = (string)($userRow['email'] ?? '');
            $finalPassword = $newPassword;
            $message = 'Password updated successfully.';
        } else {
            $error = 'Failed to update password.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = 'admin';
    $email = 'admin@school.com';
    $password = 'admin123';
    $fullName = 'Admin User';

    if ($username === '' || $email === '' || $password === '' || $fullName === '') {
        $error = 'Please fill all admin fields.';
    } else {
        $result = registerUser([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName,
            'role' => 'super_admin',
            'mobile' => '',
            'status' => 'active',
            'is_active' => 1,
        ]);

        if (!empty($result['success'])) {
            $finalUsername = $username;
            $finalEmail = $email;
            $finalPassword = $password;
            $message = 'Super Admin user created successfully.';
        } else {
            $error = $result['message'] ?? 'Failed to create super admin.';
        }
    }
}

$users = fetchAll("SELECT user_id, username, email, full_name, role, is_active FROM users ORDER BY role, username");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Login Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 24px; }
        .container-box { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
    </style>
</head>
<body>
<div class="container-box">
    <h1 class="mb-2"><i class="bi bi-wrench-adjustable-circle"></i> Emergency Login Fix</h1>
    <p class="text-muted mb-4">This tool works with the configured database, so it can repair hosted logins too.</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php if ($finalUsername !== '' || $finalEmail !== '' || $finalPassword !== ''): ?>
            <div class="code mb-3">
                <?php if ($finalUsername !== ''): ?>
                    <div><strong>Username:</strong> <?php echo htmlspecialchars($finalUsername); ?></div>
                <?php endif; ?>
                <?php if ($finalEmail !== ''): ?>
                    <div><strong>Email:</strong> <?php echo htmlspecialchars($finalEmail); ?></div>
                <?php endif; ?>
                <?php if ($finalPassword !== ''): ?>
                    <div><strong>Password:</strong> <?php echo htmlspecialchars($finalPassword); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="table-responsive mb-4">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Active</th>
                    <th style="min-width: 280px;">Reset Password</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo intval($user['user_id'] ?? 0); ?></td>
                        <td><strong><?php echo htmlspecialchars($user['username'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['role'] ?? ''); ?></td>
                        <td><?php echo !empty($user['is_active']) ? 'Yes' : 'No'; ?></td>
                        <td>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="user_id" value="<?php echo intval($user['user_id'] ?? 0); ?>">
                                <input type="text" name="new_password" class="form-control form-control-sm" placeholder="New password" value="admin123">
                                <button type="submit" name="reset_password" class="btn btn-sm btn-success">Reset</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Create New Super Admin</h5>
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="full_name" class="form-control" placeholder="Full name" value="Admin User">
                </div>
                <div class="col-md-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" value="admin">
                </div>
                <div class="col-md-3">
                    <input type="email" name="email" class="form-control" placeholder="Email" value="admin@school.com">
                </div>
                <div class="col-md-3">
                    <input type="text" name="password" class="form-control" placeholder="Password" value="admin123">
                </div>
                <div class="col-12">
                    <button type="submit" name="create_admin" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Create Super Admin
                    </button>
                    <a href="modules/auth/super_admin_login.php" class="btn btn-outline-secondary">
                        Go to Super Admin Login
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-info">
        If the login is still invalid after this, the username/password you are typing does not match the selected user row. Reset the password from here and try again.
    </div>
</div>
</body>
</html>
