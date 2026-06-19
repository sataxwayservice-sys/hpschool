<?php
/**
 * Test Permissions Table - Diagnostic Script
 */

require_once 'config/config.php';

echo "<h2>Permissions Table Diagnostic</h2>";

// Test 1: Check if role_permissions table exists
echo "<h3>Step 1: Check if role_permissions table exists</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'role_permissions'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ role_permissions table EXISTS";
    echo "</div>";

    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $structure = fetchAll("DESCRIBE role_permissions");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Show existing permissions
    echo "<h4>Existing Permissions:</h4>";
    $existing = fetchAll("SELECT * FROM role_permissions");
    if (count($existing) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>School ID</th><th>Role Name</th><th>Permissions</th></tr>";
        foreach ($existing as $perm) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($perm['id']) . "</td>";
            echo "<td>" . htmlspecialchars($perm['school_id'] ?? '0') . "</td>";
            echo "<td>" . htmlspecialchars($perm['role_name']) . "</td>";
            echo "<td><pre style='margin:0;'>" . htmlspecialchars(print_r(json_decode($perm['permissions'], true), true)) . "</pre></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No permissions defined yet.</p>";
    }

} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ role_permissions table DOES NOT EXIST<br><br>";
    echo "<strong>Action Required:</strong> You need to create the table first!<br><br>";
    echo "Run this SQL in phpMyAdmin:";
    echo "</div>";

    echo "<textarea style='width: 100%; height: 300px; font-family: monospace; margin-top: 10px;'>";
    echo "-- Create role_permissions table
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `role_name` varchar(50) NOT NULL,
  `permissions` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_role_permissions_school_role` (`school_id`, `role_name`),
  KEY `idx_role_permissions_school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert global default permissions for each role (school_id = 0)
-- Admin - Full access except user management
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'admin', '[\"dashboard_view\",\"students_view\",\"students_add\",\"students_edit\",\"students_delete\",\"classes_view\",\"classes_add\",\"sections_view\",\"sections_add\",\"fees_view\",\"fees_add\",\"fees_edit\",\"fees_structure\",\"marks_view\",\"marks_add\",\"exams_manage\",\"reports_view\",\"reports_export\",\"settings_view\",\"settings_edit\",\"school_settings_view\",\"academic_years_view\",\"session_rollover_view\",\"student_portal_view\",\"recycle_bin_view\"]');

-- Accountant - Fees and reports
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'accountant', '[\"dashboard_view\",\"students_view\",\"fees_view\",\"fees_add\",\"fees_edit\",\"fees_structure\",\"reports_view\",\"reports_export\"]');

-- Clerk - Basic operations
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'clerk', '[\"dashboard_view\",\"students_view\",\"students_add\",\"students_edit\",\"fees_view\",\"fees_add\"]');

-- Teacher - Students and marks
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'teacher', '[\"dashboard_view\",\"students_view\",\"marks_view\",\"marks_add\",\"exams_manage\",\"reports_view\"]');";
    echo "</textarea>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If table doesn't exist, copy the SQL above and run it in phpMyAdmin</li>";
echo "<li>After creating the table, try saving permissions again</li>";
echo "<li>Go to <a href='modules/settings/manage_permissions.php'>Manage Permissions</a></li>";
echo "</ol>";
?>
