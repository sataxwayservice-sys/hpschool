<?php
/**
 * One-time script to add academic year settings
 */
require_once 'config/config.php';
requireLogin();

$conn = getDbConnection();

echo "<h2>Adding Academic Year Settings...</h2>";
echo "<style>body{font-family:Arial;padding:20px;}</style>";

// Check if columns exist
$checkQuery = "SHOW COLUMNS FROM school_settings LIKE 'academic_year_start_month'";
$result = $conn->query($checkQuery);

if ($result->num_rows == 0) {
    echo "<p>Adding academic_year_start_month column...</p>";
    $alterQuery = "ALTER TABLE school_settings ADD COLUMN academic_year_start_month INT DEFAULT 4 COMMENT 'Start month of academic year (1=Jan, 4=Apr, 6=Jun)' AFTER current_academic_year";
    $conn->query($alterQuery);
    echo "<p style='color:green;'>✓ Added academic_year_start_month column (default: April)</p>";
} else {
    echo "<p style='color:blue;'>✓ academic_year_start_month column already exists</p>";
}

// Update default value if needed
$updateQuery = "UPDATE school_settings SET academic_year_start_month = 4 WHERE setting_id = 1 AND academic_year_start_month IS NULL";
$conn->query($updateQuery);

echo "<hr>";
echo "<p><strong>Academic Year Settings Added Successfully!</strong></p>";
echo "<p>Default start month is set to <strong>April (Month 4)</strong></p>";
echo "<p>You can change this in: <a href='modules/settings/school.php'>Settings → School Settings</a></p>";
echo "<hr>";
echo "<p><a href='modules/fees/collect_complete.php' style='padding:10px 20px;background:#0d6efd;color:white;text-decoration:none;border-radius:5px;'>Go to Fee Collection</a></p>";
?>
