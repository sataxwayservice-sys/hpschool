<?php
/**
 * Update Student Batches with Current Academic Year
 */

require_once 'config/config.php';
requireLogin();

$currentBatch = '2024-2025';

echo "<h1>Update Student Batches</h1>";
echo "<p>This will update all students to have batch: <strong>$currentBatch</strong></p>";

// Check current status
$studentsWithoutBatch = fetchAll("SELECT COUNT(*) as count FROM students WHERE batch IS NULL OR batch = ''");
$totalStudents = fetchAll("SELECT COUNT(*) as count FROM students");

// Handle potential null returns
$totalCount = ($totalStudents && isset($totalStudents[0])) ? $totalStudents[0]['count'] : 0;
$withoutBatchCount = ($studentsWithoutBatch && isset($studentsWithoutBatch[0])) ? $studentsWithoutBatch[0]['count'] : 0;

echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #0c5460; margin: 20px 0;'>";
echo "<h3>Current Status:</h3>";
echo "<p><strong>Total Students:</strong> " . $totalCount . "</p>";
echo "<p><strong>Students without Batch:</strong> " . $withoutBatchCount . "</p>";
echo "</div>";

if (isset($_POST['update_all'])) {
    echo "<h2>Updating...</h2>";

    try {
        $result = executeQuery("UPDATE students SET batch = ? WHERE batch IS NULL OR batch = ''", 's', [$currentBatch]);

        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid green; margin: 20px 0;'>";
        echo "<h3>✓ Success!</h3>";
        echo "<p>All students have been updated with batch: <strong>$currentBatch</strong></p>";
        echo "<p><a href='modules/fees/collect_complete.php' style='background: green; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Fee Collection</a></p>";
        echo "</div>";

        // Show updated count
        $updatedCount = fetchAll("SELECT COUNT(*) as count FROM students WHERE batch = ?", 's', [$currentBatch]);
        $updated = ($updatedCount && isset($updatedCount[0])) ? $updatedCount[0]['count'] : 0;
        echo "<p><strong>Students with batch '$currentBatch':</strong> " . $updated . "</p>";

    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid red;'>";
        echo "<h3>✗ Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
} else {
    // Show form
    if ($withoutBatchCount > 0) {
        echo "<form method='POST'>";
        echo "<div style='margin: 20px 0;'>";
        echo "<button type='submit' name='update_all' style='background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>";
        echo "Update All Students with Batch: $currentBatch";
        echo "</button>";
        echo "</div>";
        echo "</form>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid green;'>";
        echo "<h3>✓ All students already have batches assigned!</h3>";
        echo "</div>";
    }

    // Show sample students
    echo "<h3>Sample Students:</h3>";
    $sampleStudents = fetchAll("SELECT student_id, admission_no, student_name, batch, status FROM students LIMIT 10");

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th style='padding: 10px; background: #f2f2f2;'>Admission No</th>";
    echo "<th style='padding: 10px; background: #f2f2f2;'>Name</th>";
    echo "<th style='padding: 10px; background: #f2f2f2;'>Current Batch</th>";
    echo "<th style='padding: 10px; background: #f2f2f2;'>Status</th>";
    echo "</tr>";

    foreach ($sampleStudents as $s) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($s['admission_no']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($s['student_name']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($s['batch'] ?? '<span style="color: red;">NULL</span>') . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($s['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>After updating batches, the batch dropdown will show '2024-2025'</li>";
echo "<li>You can then <a href='modules/fees/collect_complete.php'>collect fees</a> normally</li>";
echo "<li>Search for students by admission number or name</li>";
echo "</ol>";

echo "<p style='margin-top: 30px;'>";
echo "<a href='check_student.php'>Check Individual Student</a> | ";
echo "<a href='debug_fee_collection.php'>Run Diagnostic</a> | ";
echo "<a href='modules/fees/collect_complete.php'>Fee Collection Page</a>";
echo "</p>";
?>
