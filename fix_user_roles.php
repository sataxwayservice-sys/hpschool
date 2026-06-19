<?php
/**
 * Fix User Roles
 * Ensures all users have valid role assignments
 */

require_once 'config/config.php';

echo "<h2>User Roles Diagnostic and Fix</h2>\n\n";

// Check all users
$users = fetchAll("SELECT user_id, username, full_name, role_id FROM users");

echo "<h3>Current Users:</h3>\n";
echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Role ID</th><th>Status</th></tr>\n";

$fixCount = 0;
foreach ($users as $user) {
    $status = "OK";

    // Check if role_id is null or invalid
    if (empty($user['role_id'])) {
        // Get the default 'user' role or create it
        $defaultRole = fetchOne("SELECT role_id FROM roles WHERE role_name = 'user'");

        if (!$defaultRole) {
            // Create default user role if it doesn't exist
            executeQuery("INSERT INTO roles (role_name, description) VALUES ('user', 'Regular User')", '', []);
            $defaultRole = fetchOne("SELECT role_id FROM roles WHERE role_name = 'user'");
        }

        // Update user with default role
        executeQuery("UPDATE users SET role_id = ? WHERE user_id = ?", 'ii', [$defaultRole['role_id'], $user['user_id']]);
        $status = "<span style='color: green;'>FIXED - Assigned default role</span>";
        $fixCount++;
    } else {
        // Verify role exists
        $roleExists = fetchOne("SELECT role_id FROM roles WHERE role_id = ?", 'i', [$user['role_id']]);
        if (!$roleExists) {
            // Role doesn't exist, assign default
            $defaultRole = fetchOne("SELECT role_id FROM roles WHERE role_name = 'user'");
            if ($defaultRole) {
                executeQuery("UPDATE users SET role_id = ? WHERE user_id = ?", 'ii', [$defaultRole['role_id'], $user['user_id']]);
                $status = "<span style='color: orange;'>FIXED - Role didn't exist, assigned default</span>";
                $fixCount++;
            }
        }
    }

    echo "<tr>";
    echo "<td>{$user['user_id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['full_name']}</td>";
    echo "<td>{$user['role_id']}</td>";
    echo "<td>$status</td>";
    echo "</tr>\n";
}
echo "</table>\n\n";

echo "<h3>Available Roles:</h3>\n";
$roles = fetchAll("SELECT * FROM roles");
echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>Role ID</th><th>Role Name</th><th>Description</th></tr>\n";
foreach ($roles as $role) {
    echo "<tr>";
    echo "<td>{$role['role_id']}</td>";
    echo "<td>{$role['role_name']}</td>";
    echo "<td>" . ($role['description'] ?? '-') . "</td>";
    echo "</tr>\n";
}
echo "</table>\n\n";

echo "<h3>Summary:</h3>\n";
echo "<p><strong>Total Users:</strong> " . count($users) . "</p>\n";
echo "<p><strong>Users Fixed:</strong> <span style='color: " . ($fixCount > 0 ? "green" : "gray") . ";'>" . $fixCount . "</span></p>\n";
echo "<p><strong>Available Roles:</strong> " . count($roles) . "</p>\n";

if ($fixCount > 0) {
    echo "\n<p style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px;'>";
    echo "✅ <strong>Success!</strong> Fixed $fixCount user(s). All users now have valid role assignments.";
    echo "</p>\n";
} else {
    echo "\n<p style='background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px;'>";
    echo "✅ <strong>No issues found.</strong> All users already have valid role assignments.";
    echo "</p>\n";
}

echo "\n<hr>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ol>\n";
echo "<li>Try accessing the profile page again: <a href='modules/auth/profile.php'>Profile Page</a></li>\n";
echo "<li>If everything works, delete this file (fix_user_roles.php) for security</li>\n";
echo "</ol>\n";

echo "\n<style>body { font-family: Arial, sans-serif; margin: 20px; } table { border-collapse: collapse; margin: 20px 0; } th { background: #007bff; color: white; }</style>";
?>
