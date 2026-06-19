<?php
/**
 * Login Diagnostic Tool
 * Use this to check if your admin user was created correctly
 */

require_once 'config/config.php';

echo "<h2>Login Diagnostic Tool</h2>";
echo "<hr>";

// Check database connection
echo "<h3>1. Database Connection</h3>";
$conn = getDbConnection();
if ($conn) {
    echo "✅ <span style='color: green;'>Database connected successfully!</span><br>";
    echo "Database: <strong>" . DB_NAME . "</strong><br><br>";
} else {
    echo "❌ <span style='color: red;'>Database connection failed!</span><br><br>";
    exit;
}

// Check if users table exists
echo "<h3>2. Users Table</h3>";
$query = "SHOW TABLES LIKE 'users'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    echo "✅ <span style='color: green;'>Users table exists</span><br><br>";
} else {
    echo "❌ <span style='color: red;'>Users table not found!</span><br>";
    echo "Please run setup.php to import database<br><br>";
    exit;
}

// Check users in database
echo "<h3>3. Registered Users</h3>";
$query = "SELECT user_id, username, email, full_name, role, is_active, created_at FROM users";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "Found <strong>" . $result->num_rows . "</strong> user(s):<br><br>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th><th>Created</th>";
    echo "</tr>";

    while ($user = $result->fetch_assoc()) {
        $active = $user['is_active'] ? '✅ Yes' : '❌ No';
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td><span style='color: blue;'>{$user['role']}</span></td>";
        echo "<td>{$active}</td>";
        echo "<td>" . date('d-m-Y H:i', strtotime($user['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
} else {
    echo "⚠️ <span style='color: orange;'>No users found in database!</span><br>";
    echo "Please run setup.php to create admin user<br><br>";
}

// Test login with form
echo "<h3>4. Test Login</h3>";
echo "<p>Enter your credentials to test if login works:</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    echo "<div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #333; margin: 10px 0;'>";
    echo "<strong>Testing Login...</strong><br><br>";

    // Get user from database
    $query = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo "❌ <span style='color: red;'>User not found!</span><br>";
        echo "Username/Email: <strong>{$username}</strong> does not exist in database<br>";
    } else {
        echo "✅ User found: <strong>{$user['username']}</strong><br>";
        echo "Email: {$user['email']}<br>";
        echo "Role: {$user['role']}<br><br>";

        // Check password
        echo "Password Check:<br>";
        echo "Entered password: <code>" . htmlspecialchars($password) . "</code><br>";

        if (password_verify($password, $user['password'])) {
            echo "✅ <span style='color: green; font-size: 18px;'><strong>PASSWORD CORRECT!</strong></span><br>";
            echo "✅ Login should work with these credentials<br>";
        } else {
            echo "❌ <span style='color: red; font-size: 18px;'><strong>PASSWORD INCORRECT!</strong></span><br>";
            echo "The password you entered does not match the database<br><br>";

            echo "<strong>Possible Issues:</strong><br>";
            echo "1. You're using the wrong password<br>";
            echo "2. Password was not hashed correctly during registration<br>";
            echo "3. Case sensitivity issue (check caps lock)<br><br>";

            echo "<strong>Hash in database:</strong> <code style='font-size: 10px;'>" . substr($user['password'], 0, 50) . "...</code><br>";
        }
    }
    echo "</div>";
}

?>

<form method="POST" style="background: #f0f0f0; padding: 20px; max-width: 400px; margin-top: 20px;">
    <div style="margin-bottom: 10px;">
        <label><strong>Username or Email:</strong></label><br>
        <input type="text" name="username" required style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>

    <div style="margin-bottom: 10px;">
        <label><strong>Password:</strong></label><br>
        <input type="password" name="password" required style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>

    <button type="submit" name="test_login" style="background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer;">
        Test Login
    </button>
</form>

<hr style="margin-top: 30px;">

<h3>5. What to Do Next</h3>

<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
    <strong>If Login Still Fails:</strong>
    <ol>
        <li>Use the form above to test your exact credentials</li>
        <li>Make sure username and password are correct</li>
        <li>Check if user is marked as Active (✅)</li>
        <li>Try resetting password using phpMyAdmin</li>
    </ol>
</div>

<div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin-top: 15px;">
    <strong>If Login Works Here But Not on Login Page:</strong>
    <ol>
        <li>Clear browser cookies and cache</li>
        <li>Try incognito/private browsing mode</li>
        <li>Check browser console (F12) for JavaScript errors</li>
    </ol>
</div>

<div style="background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin-top: 15px;">
    <strong>Quick Reset:</strong>
    <ol>
        <li>Go to: <a href="http://localhost/phpmyadmin/" target="_blank">phpMyAdmin</a></li>
        <li>Select database: <code>school_fees_system</code></li>
        <li>Click on <code>users</code> table</li>
        <li>Delete all users (if needed)</li>
        <li>Run <a href="setup.php">setup.php</a> again</li>
    </ol>
</div>

<hr style="margin-top: 30px;">
<p><a href="index.php">← Back to Login Page</a></p>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
</style>
