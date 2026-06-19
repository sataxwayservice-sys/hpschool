<?php
/**
 * Emergency Login Fix
 * This will check everything and fix your login issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to connect to database
$conn = new mysqli('localhost', 'root', '', 'school_fees_system');

$step = $_GET['step'] ?? 1;
$message = '';
$error = '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Emergency Login Fix</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 20px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; color: #155724; margin: 15px 0; border-radius: 5px; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; color: #721c24; margin: 15px 0; border-radius: 5px; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; color: #856404; margin: 15px 0; border-radius: 5px; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; color: #0c5460; margin: 15px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 12px; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover { opacity: 0.9; }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚨 Emergency Login Fix</h1>

<?php

// Step 1: Check Database Connection
if ($conn->connect_error) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Connection Failed</h3>";
    echo "Error: " . $conn->connect_error . "<br><br>";
    echo "<strong>What to do:</strong><br>";
    echo "1. Make sure XAMPP MySQL is running (green in control panel)<br>";
    echo "2. Database name should be: <code>school_fees_system</code><br>";
    echo "3. If database doesn't exist, run <a href='setup.php'>setup.php</a> first<br>";
    echo "</div>";
    exit;
}

echo "<div class='success'>";
echo "✅ Database connected: <strong>school_fees_system</strong>";
echo "</div>";

// Step 2: Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    echo "<div class='error'>";
    echo "<h3>❌ Users table doesn't exist!</h3>";
    echo "The database exists but tables are missing.<br><br>";
    echo "<strong>Solution:</strong><br>";
    echo "<a href='setup.php' class='btn'>Go to Setup Wizard</a>";
    echo "</div>";
    exit;
}

echo "<div class='success'>";
echo "✅ Users table exists";
echo "</div>";

// Step 3: Check existing users
$result = $conn->query("SELECT user_id, username, email, full_name, role, is_active FROM users");

echo "<h3>📋 Current Users in Database</h3>";

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th><th>Action</th></tr>";

    while ($user = $result->fetch_assoc()) {
        $active = $user['is_active'] ? '✅' : '❌';
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>{$active}</td>";
        echo "<td><a href='?step=reset&user={$user['user_id']}' class='btn btn-success' style='padding: 5px 10px; font-size: 12px;'>Reset Password</a></td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div class='info'>";
    echo "<strong>📌 Use these usernames to login!</strong><br>";
    echo "Copy the exact username from the table above.";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<h3>⚠️ No users found in database!</h3>";
    echo "You need to create an admin user first.<br><br>";
    echo "<strong>Choose an option:</strong><br>";
    echo "<a href='?step=create' class='btn btn-success'>Create Admin User Now</a>";
    echo "<a href='setup.php' class='btn'>Go to Setup Wizard</a>";
    echo "</div>";
    exit;
}

// Step 4: Handle password reset
if (isset($_GET['step']) && $_GET['step'] == 'reset' && isset($_GET['user'])) {
    $userId = intval($_GET['user']);

    // Get user info
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $newPassword = $_POST['new_password'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param('si', $hashedPassword, $userId);

        if ($stmt->execute()) {
            echo "<div class='success'>";
            echo "<h3>✅ Password Reset Successful!</h3>";
            echo "Username: <strong>{$user['username']}</strong><br>";
            echo "New Password: <strong>{$newPassword}</strong><br><br>";
            echo "<strong>Now you can login with:</strong><br>";
            echo "Username: <code>{$user['username']}</code><br>";
            echo "Password: <code>{$newPassword}</code><br><br>";
            echo "<a href='index.php' class='btn btn-success'>Go to Login Page</a>";
            echo "</div>";
            exit;
        } else {
            echo "<div class='error'>Failed to update password!</div>";
        }
    }

    echo "<div class='info'>";
    echo "<h3>🔑 Reset Password for: {$user['username']}</h3>";
    echo "<form method='POST'>";
    echo "<div class='form-group'>";
    echo "<label>Username (Read-only)</label>";
    echo "<input type='text' value='{$user['username']}' disabled>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label>New Password</label>";
    echo "<input type='text' name='new_password' value='admin123' required>";
    echo "<small style='color: #666;'>Change this to your desired password</small>";
    echo "</div>";
    echo "<button type='submit' name='reset_password' class='btn btn-success'>Reset Password Now</button>";
    echo "<a href='?' class='btn'>Cancel</a>";
    echo "</form>";
    echo "</div>";
    exit;
}

// Step 5: Create new admin user
if (isset($_GET['step']) && $_GET['step'] == 'create') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $email = $_POST['email'];
        $fullName = $_POST['full_name'];

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 'super_admin', 1)");
        $stmt->bind_param('ssss', $username, $email, $hashedPassword, $fullName);

        if ($stmt->execute()) {
            echo "<div class='success'>";
            echo "<h3>✅ Admin User Created Successfully!</h3>";
            echo "Username: <strong>{$username}</strong><br>";
            echo "Password: <strong>{$password}</strong><br>";
            echo "Email: <strong>{$email}</strong><br><br>";
            echo "<strong>Now login with these credentials:</strong><br><br>";
            echo "<a href='index.php' class='btn btn-success'>Go to Login Page</a>";
            echo "</div>";
            exit;
        } else {
            echo "<div class='error'>Failed to create user: " . $conn->error . "</div>";
        }
    }

    echo "<div class='info'>";
    echo "<h3>👤 Create Super Admin User</h3>";
    echo "<form method='POST'>";
    echo "<div class='form-group'>";
    echo "<label>Full Name</label>";
    echo "<input type='text' name='full_name' value='Admin User' required>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label>Username</label>";
    echo "<input type='text' name='username' value='admin' required>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label>Email</label>";
    echo "<input type='email' name='email' value='admin@school.com' required>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label>Password</label>";
    echo "<input type='text' name='password' value='admin123' required>";
    echo "<small style='color: #666;'>You can change this after login</small>";
    echo "</div>";
    echo "<button type='submit' name='create_admin' class='btn btn-success'>Create Admin User</button>";
    echo "<a href='?' class='btn'>Cancel</a>";
    echo "</form>";
    echo "</div>";
    exit;
}

// Final instructions
echo "<hr style='margin: 30px 0;'>";
echo "<h3>📝 What to Do Next</h3>";

echo "<div class='info'>";
echo "<strong>Option 1: Reset Existing User Password</strong><br>";
echo "Click 'Reset Password' button next to any user above<br><br>";

echo "<strong>Option 2: Create New Admin User</strong><br>";
echo "<a href='?step=create' class='btn btn-success'>Create New Admin</a><br><br>";

echo "<strong>Option 3: Test Your Login</strong><br>";
echo "<a href='test_login.php' class='btn'>Test Login Credentials</a>";
echo "</div>";

?>

</div>

</body>
</html>
