<?php
/**
 * Diagnostic script to test reminder search
 */

require_once 'config/config.php';

echo "<h2>Reminder Search Diagnostic</h2>";

$searchTerm = 'STU000004';

echo "<h3>1. Testing Search Query</h3>";
echo "<p><strong>Searching for:</strong> $searchTerm</p>";

// Test basic student query without JOIN
echo "<h4>Step 1: Basic student search (no JOIN)</h4>";
$basicQuery = "SELECT * FROM students WHERE admission_no = ?";
$basicResult = fetchOne($basicQuery, 's', [$searchTerm]);

if ($basicResult) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Student FOUND in database<br>";
    echo "Student ID: " . $basicResult['student_id'] . "<br>";
    echo "Name: " . htmlspecialchars($basicResult['student_name']) . "<br>";
    echo "Class ID: " . ($basicResult['class_id'] ?? 'NULL') . "<br>";
    echo "Section ID: " . ($basicResult['section_id'] ?? 'NULL') . "<br>";
    echo "Status: " . htmlspecialchars($basicResult['status']) . "<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ Student NOT FOUND with admission number: $searchTerm";
    echo "</div>";
}

// Test with JOIN
echo "<h4>Step 2: Search with JOIN (as used in reminders page)</h4>";
$joinQuery = "SELECT s.*, c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.admission_no LIKE ?
              AND s.status = 'Active'";
$joinResult = fetchOne($joinQuery, 's', ["%$searchTerm%"]);

if ($joinResult) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Student FOUND with JOIN<br>";
    echo "Name: " . htmlspecialchars($joinResult['student_name']) . "<br>";
    echo "Class: " . htmlspecialchars($joinResult['class_name']) . "<br>";
    echo "Section: " . htmlspecialchars($joinResult['section_name']) . "<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ Student NOT FOUND with JOIN<br>";
    echo "<strong>This means:</strong><br>";
    echo "- Student might have invalid class_id or section_id<br>";
    echo "- OR class/section is inactive<br>";
    echo "- OR student status is not 'Active'<br>";
    echo "</div>";
}

// Check if student_reminders table exists
echo "<h3>2. Check student_reminders table</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'student_reminders'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Table 'student_reminders' EXISTS";
    echo "</div>";

    if ($basicResult) {
        $reminders = fetchAll(
            "SELECT * FROM student_reminders WHERE student_id = ?",
            'i',
            [$basicResult['student_id']]
        );
        echo "<p>Reminders for this student: " . count($reminders) . "</p>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ Table 'student_reminders' DOES NOT EXIST<br>";
    echo "<strong>Action needed:</strong> Run the SQL from create_reminders_table.sql";
    echo "</div>";
}

// Check classes and sections
echo "<h3>3. Check Classes and Sections</h3>";
if ($basicResult) {
    $class = fetchOne("SELECT * FROM classes WHERE class_id = ?", 'i', [$basicResult['class_id']]);
    $section = fetchOne("SELECT * FROM sections WHERE section_id = ?", 'i', [$basicResult['section_id']]);

    echo "<p><strong>Class Info:</strong></p>";
    if ($class) {
        echo "Class ID: " . $class['class_id'] . "<br>";
        echo "Class Name: " . htmlspecialchars($class['class_name']) . "<br>";
        echo "Is Active: " . ($class['is_active'] ? 'Yes' : 'No') . "<br>";
    } else {
        echo "<span style='color: red;'>Class not found!</span><br>";
    }

    echo "<p><strong>Section Info:</strong></p>";
    if ($section) {
        echo "Section ID: " . $section['section_id'] . "<br>";
        echo "Section Name: " . htmlspecialchars($section['section_name']) . "<br>";
        echo "Is Active: " . ($section['is_active'] ? 'Yes' : 'No') . "<br>";
    } else {
        echo "<span style='color: red;'>Section not found!</span><br>";
    }
}

echo "<hr>";
echo "<h3>Solution:</h3>";
echo "<ol>";
echo "<li>If student found in Step 1 but NOT in Step 2 → Run <a href='check_students.php'>check_students.php</a> to fix orphaned students</li>";
echo "<li>If student_reminders table doesn't exist → Run create_reminders_table.sql in phpMyAdmin</li>";
echo "<li>If class/section is inactive → Activate them in the settings</li>";
echo "</ol>";
?>
