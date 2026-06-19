<?php
/**
 * Setup Reports Module Permissions
 * Run this once to add reports permissions to all roles
 */

require_once 'config/config.php';

echo "Setting up Reports Module Permissions...\n\n";

// Get all roles
$roles = fetchAll("SELECT * FROM roles");

foreach ($roles as $role) {
    echo "Adding permissions for role: " . $role['role_name'] . "\n";

    // Check if reports permissions already exist
    $existing = fetchOne("SELECT * FROM role_permissions WHERE role_id = ? AND module = 'reports'",
                         'i', [$role['role_id']]);

    if (!$existing) {
        // Add reports permissions
        $query = "INSERT INTO role_permissions (role_id, module, can_view, can_add, can_edit, can_delete)
                  VALUES (?, 'reports', 1, 0, 0, 0)";

        executeQuery($query, 'i', [$role['role_id']]);
        echo "  ✓ Reports permissions added\n";
    } else {
        // Update existing to ensure can_view is enabled
        $query = "UPDATE role_permissions SET can_view = 1 WHERE role_id = ? AND module = 'reports'";
        executeQuery($query, 'i', [$role['role_id']]);
        echo "  ✓ Reports permissions already exist (updated)\n";
    }
}

echo "\n✅ Done! All roles now have access to view reports.\n";
echo "Please delete this file (setup_reports_permissions.php) after running.\n";
?>
