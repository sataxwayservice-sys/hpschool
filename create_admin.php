<?php
/**
 * ONE CLICK ADMIN CREATOR
 * This will create admin user instantly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'school_fees_system');

if ($conn->connect_error) {
    die("❌ <h1>Database Connection Failed</h1>
    <p>Make sure MySQL is running in XAMPP!</p>
    <p>Error: " . $conn->connect_error . "</p>");
}

$created = false;
$error = '';
$username = 'admin';
$password = 'admin123';

// Create admin if button clicked
if (isset($_POST['create_now'])) {
    // Check if admin already exists
    $check = $conn->query("SELECT user_id FROM users WHERE username = 'admin'");

    if ($check && $check->num_rows > 0) {
        // Admin exists, just reset password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$hashedPassword', is_active = 1 WHERE username = 'admin'");
        $created = true;
        $message = "✅ Admin user password has been RESET!";
    } else {
        // Create new admin
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $email = 'admin@school.com';
        $fullName = 'Admin User';

        $sql = "INSERT INTO users (username, email, password, full_name, role, is_active)
                VALUES ('$username', '$email', '$hashedPassword', '$fullName', 'super_admin', 1)";

        if ($conn->query($sql)) {
            $created = true;
            $message = "✅ Admin user has been CREATED!";
        } else {
            $error = "Failed to create user: " . $conn->error;
        }
    }
}

// Check existing users
$result = $conn->query("SELECT username, email, role, is_active FROM users");
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Admin - One Click</title>
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
        .box {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 32px;
        }
        .big-button {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 20px 40px;
            font-size: 24px;
            border-radius: 10px;
            cursor: pointer;
            margin: 20px 0;
            width: 100%;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .big-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .credentials {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 18px;
            border: 2px dashed #667eea;
        }
        .credentials strong {
            color: #667eea;
            font-size: 24px;
        }
        .login-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            display: inline-block;
            margin: 10px;
            font-size: 18px;
            font-weight: bold;
        }
        .login-btn:hover {
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            text-align: left;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 5px solid #ffc107;
        }
    </style>
</head>
<body>

<div class="box">

<?php if ($created): ?>

    <div class="success">
        <h1 style="color: #155724; margin-bottom: 15px;">🎉 SUCCESS!</h1>
        <p style="font-size: 18px;"><?php echo $message; ?></p>
    </div>

    <div class="credentials">
        <p style="margin-bottom: 15px; font-size: 20px;">📝 Your Login Credentials:</p>
        <p style="margin: 10px 0;">
            Username: <strong><?php echo $username; ?></strong>
        </p>
        <p style="margin: 10px 0;">
            Password: <strong><?php echo $password; ?></strong>
        </p>
    </div>

    <a href="index.php" class="login-btn">
        🚀 GO TO LOGIN PAGE NOW
    </a>

    <div class="info">
        <strong>✅ What to do next:</strong>
        <ol style="margin-top: 10px; padding-left: 20px;">
            <li>Click the button above to go to login page</li>
            <li>Enter username: <code style="background: #e9ecef; padding: 3px 8px; border-radius: 3px;"><?php echo $username; ?></code></li>
            <li>Enter password: <code style="background: #e9ecef; padding: 3px 8px; border-radius: 3px;"><?php echo $password; ?></code></li>
            <li>Click Login</li>
            <li>You're in! 🎉</li>
        </ol>
    </div>

<?php elseif ($error): ?>

    <div class="error">
        <h2>❌ Error</h2>
        <p><?php echo $error; ?></p>
    </div>

<?php else: ?>

    <h1>🔐 Admin User Creator</h1>

    <?php if (count($users) > 0): ?>
    <div class="warning">
        <strong>⚠️ Users Already Exist</strong><br>
        Click below to reset admin password to: <strong>admin123</strong>
    </div>

    <table>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Active</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><strong><?php echo $user['username']; ?></strong></td>
            <td><?php echo $user['email']; ?></td>
            <td><?php echo $user['role']; ?></td>
            <td><?php echo $user['is_active'] ? '✅' : '❌'; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" name="create_now" class="big-button">
            <?php echo count($users) > 0 ? '🔄 RESET ADMIN PASSWORD' : '✨ CREATE ADMIN USER'; ?>
        </button>
    </form>

    <div class="info" style="text-align: left;">
        <strong>This will create/reset:</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li>Username: <code style="background: #e9ecef; padding: 3px 8px; border-radius: 3px;">admin</code></li>
            <li>Password: <code style="background: #e9ecef; padding: 3px 8px; border-radius: 3px;">admin123</code></li>
            <li>Role: Super Admin (full access)</li>
            <li>Status: Active</li>
        </ul>
    </div>

<?php endif; ?>

</div>

</body>
</html>
