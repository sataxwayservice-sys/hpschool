<?php
/**
 * Test User Creation - Diagnostic Script
 * This will show the actual MySQL error
 */

require_once 'config/config.php';

// Test data
$testUsername = 'testuser_' . time();
$testPassword = 'password123';
$testFullName = 'Test User';
$testEmail = 'test@example.com';
$testRole = 'teacher';
$testIsActive = 1;

echo "<h2>User Creation Diagnostic</h2>";

// Test 1: Check users table structure
echo "<h3>Step 1: Check users table structure</h3>";
$tableCheck = fetchAll("DESCRIBE users");
if ($tableCheck) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Users table exists<br>";
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($tableCheck as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ Users table not found or error accessing it";
    echo "</div>";
}

// Test 2: Check if activity_log table exists
echo "<h3>Step 2: Check activity_log table</h3>";
$activityTableCheck = fetchAll("SHOW TABLES LIKE 'activity_log'");
if (count($activityTableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ activity_log table exists";
    echo "</div>";

    // Check structure
    $activityColumns = fetchAll("DESCRIBE activity_log");
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th></tr>";
    foreach ($activityColumns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ activity_log table DOES NOT EXIST - This might cause issues!";
    echo "</div>";
}

// Test 3: Try to insert a test user with detailed error reporting
echo "<h3>Step 3: Attempt test user creation</h3>";

$conn = getDbConnection();
$hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);

$query = "INSERT INTO users (username, password, full_name, email, role, is_active, created_at)
          VALUES (?, ?, ?, ?, ?, ?, NOW())";

echo "<p><strong>SQL Query:</strong> " . htmlspecialchars($query) . "</p>";
echo "<p><strong>Parameters:</strong></p>";
echo "<ul>";
echo "<li>username: " . htmlspecialchars($testUsername) . " (string)</li>";
echo "<li>password: [hashed] (string)</li>";
echo "<li>full_name: " . htmlspecialchars($testFullName) . " (string)</li>";
echo "<li>email: " . htmlspecialchars($testEmail) . " (string)</li>";
echo "<li>role: " . htmlspecialchars($testRole) . " (string)</li>";
echo "<li>is_active: " . $testIsActive . " (integer)</li>";
echo "</ul>";

$stmt = $conn->prepare($query);

if (!$stmt) {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "<strong>✗ PREPARE FAILED:</strong><br>";
    echo "Error: " . htmlspecialchars($conn->error) . "<br>";
    echo "Error Number: " . $conn->errno;
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Query prepared successfully";
    echo "</div>";

    // Bind parameters
    $bindResult = $stmt->bind_param('ssssii',
        $testUsername,
        $hashedPassword,
        $testFullName,
        $testEmail,
        $testRole,
        $testIsActive
    );

    if (!$bindResult) {
        echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
        echo "<strong>✗ BIND FAILED:</strong><br>";
        echo "Error: " . htmlspecialchars($stmt->error);
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
        echo "✓ Parameters bound successfully";
        echo "</div>";

        // Execute
        $execResult = $stmt->execute();

        if (!$execResult) {
            echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
            echo "<strong>✗ EXECUTE FAILED:</strong><br>";
            echo "Error: " . htmlspecialchars($stmt->error) . "<br>";
            echo "Error Number: " . $stmt->errno . "<br><br>";
            echo "<strong>Possible causes:</strong><br>";
            echo "<ul>";
            echo "<li>ENUM value mismatch (role must be one of: super_admin, admin, accountant, clerk, teacher)</li>";
            echo "<li>Field length exceeded</li>";
            echo "<li>Unique constraint violation</li>";
            echo "<li>Foreign key constraint violation</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
            echo "✓ User created successfully!<br>";
            echo "Insert ID: " . $stmt->insert_id . "<br>";
            echo "Affected Rows: " . $stmt->affected_rows;
            echo "</div>";

            // Clean up test user
            echo "<p><strong>Cleaning up test user...</strong></p>";
            $deleteQuery = "DELETE FROM users WHERE user_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $insertId = $stmt->insert_id;
            $deleteStmt->bind_param('i', $insertId);
            $deleteStmt->execute();
            echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid orange;'>";
            echo "Test user deleted.";
            echo "</div>";
        }
    }

    $stmt->close();
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If you see an error above, that's the root cause of the issue</li>";
echo "<li>Fix the error based on the message shown</li>";
echo "<li>If activity_log table is missing, create it</li>";
echo "<li>After fixing, try creating a user again from <a href='modules/settings/add_user.php'>add_user.php</a></li>";
echo "</ol>";
?>
