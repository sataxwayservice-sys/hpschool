<?php
/**
 * Add batch column to students table
 */

require_once 'config/config.php';
requireLogin();

echo "<h1>Add Batch Column to Students Table</h1>";
echo "<style>
    .success { background: #d4edda; padding: 20px; border: 1px solid green; margin: 20px 0; }
    .error { background: #f8d7da; padding: 20px; border: 1px solid red; margin: 20px 0; }
    .info { background: #d1ecf1; padding: 20px; border: 1px solid #0c5460; margin: 20px 0; }
</style>";

try {
    $conn = getDbConnection();

    // Check if batch column already exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM students LIKE 'batch'");

    if ($checkColumn && $checkColumn->num_rows > 0) {
        echo "<div class='info'>";
        echo "<h3>ℹ Column Already Exists</h3>";
        echo "<p>The 'batch' column already exists in the students table.</p>";
        echo "<p><a href='full_diagnostic.php'>Return to Diagnostic</a></p>";
        echo "</div>";
    } else {
        // Add the batch column
        echo "<div class='info'>";
        echo "<h3>Adding 'batch' column...</h3>";
        echo "<p>Executing SQL: <code>ALTER TABLE students ADD COLUMN batch VARCHAR(20) NULL AFTER status;</code></p>";
        echo "</div>";

        $sql = "ALTER TABLE students ADD COLUMN batch VARCHAR(20) NULL AFTER status";
        $result = $conn->query($sql);

        if ($result) {
            echo "<div class='success'>";
            echo "<h3>✓ Success!</h3>";
            echo "<p>The 'batch' column has been successfully added to the students table.</p>";
            echo "<h4>Next Steps:</h4>";
            echo "<ol>";
            echo "<li><a href='update_student_batches.php' style='color: blue; text-decoration: underline;'>Update Student Batches</a> - Assign batch '2024-2025' to all students</li>";
            echo "<li><a href='full_diagnostic.php'>Run Diagnostic Again</a> - Verify everything is working</li>";
            echo "<li><a href='modules/fees/collect_complete.php'>Open Fee Collection</a> - Start collecting fees</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            throw new Exception($conn->error);
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>✗ Error</h3>";
    echo "<p>Failed to add batch column: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please try running this SQL manually in phpMyAdmin:</p>";
    echo "<code style='display: block; background: #f5f5f5; padding: 10px; margin: 10px 0;'>";
    echo "ALTER TABLE students ADD COLUMN batch VARCHAR(20) NULL AFTER status;";
    echo "</code>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='full_diagnostic.php'>← Back to Diagnostic</a></p>";
?>
