<?php
/**
 * Password Reset Utility
 * Use this to reset admin password if you forgot it
 */

require_once 'config/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($username) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if user exists
        $query = "SELECT user_id, username FROM users WHERE username = ? OR email = ?";
        $user = fetchOne($query, 'ss', [$username, $username]);

        if (!$user) {
            $error = "User '{$username}' not found in database";
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET password = ? WHERE user_id = ?";
            $result = executeQuery($updateQuery, 'si', [$hashedPassword, $user['user_id']]);

            if ($result !== false) {
                $message = "✅ Password updated successfully for user: <strong>{$user['username']}</strong><br>You can now login with your new password.";
            } else {
                $error = 'Failed to update password. Please check error logs.';
            }
        }
    }
}

// Get all users
$users = fetchAll("SELECT user_id, username, email, role FROM users ORDER BY user_id");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Utility</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h2 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: 500;
        }
        button:hover {
            opacity: 0.9;
        }
        .users-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .users-list h4 {
            margin-bottom: 10px;
            color: #333;
        }
        .user-item {
            background: white;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 5px;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #667eea;
            color: white;
            border-radius: 3px;
            font-size: 11px;
        }
        .links {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>🔑 Password Reset Utility</h2>
    <p class="subtitle">Reset password for any user account</p>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            ❌ <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="warning">
        <strong>⚠️ Security Notice:</strong><br>
        This utility should be deleted after use for security reasons.
        Only use this on localhost/development environments.
    </div>

    <?php if (count($users) > 0): ?>
    <div class="users-list">
        <h4>Available Users:</h4>
        <?php foreach ($users as $user): ?>
        <div class="user-item">
            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
            <span class="badge"><?php echo $user['role']; ?></span><br>
            <small style="color: #666;"><?php echo htmlspecialchars($user['email']); ?></small>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username"
                   placeholder="Enter username or email" required>
        </div>

        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password"
                   placeholder="Enter new password (min 6 characters)" required minlength="6">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   placeholder="Re-enter new password" required minlength="6">
        </div>

        <button type="submit">Reset Password</button>
    </form>

    <div class="links">
        <a href="test_login.php">🔍 Test Login</a>
        <a href="index.php">← Back to Login</a>
        <a href="setup.php">⚙️ Setup</a>
    </div>
</div>

</body>
</html>
