<?php
/**
 * Diagnostic script to check student data
 */

require_once 'config/config.php';

echo "<h2>Student Data Diagnostic</h2>";

// Check all students
echo "<h3>1. All Students (Raw Data)</h3>";
$allStudents = fetchAll("SELECT student_id, admission_no, student_name, class_id, section_id, status FROM students");
echo "Total Students: " . count($allStudents) . "<br><br>";

if (count($allStudents) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Admission No</th><th>Name</th><th>Class ID</th><th>Section ID</th><th>Status</th></tr>";
    foreach ($allStudents as $s) {
        echo "<tr>";
        echo "<td>" . $s['student_id'] . "</td>";
        echo "<td>" . htmlspecialchars($s['admission_no']) . "</td>";
        echo "<td>" . htmlspecialchars($s['student_name']) . "</td>";
        echo "<td>" . ($s['class_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($s['section_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($s['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check active classes
echo "<h3>2. Active Classes</h3>";
$classes = fetchAll("SELECT class_id, class_name, is_active FROM classes WHERE is_active = 1");
echo "Total Active Classes: " . count($classes) . "<br><br>";

if (count($classes) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Class ID</th><th>Class Name</th><th>Active</th></tr>";
    foreach ($classes as $c) {
        echo "<tr>";
        echo "<td>" . $c['class_id'] . "</td>";
        echo "<td>" . htmlspecialchars($c['class_name']) . "</td>";
        echo "<td>" . $c['is_active'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check active sections
echo "<h3>3. Active Sections</h3>";
$sections = fetchAll("SELECT section_id, section_name, is_active FROM sections WHERE is_active = 1");
echo "Total Active Sections: " . count($sections) . "<br><br>";

if (count($sections) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Section ID</th><th>Section Name</th><th>Active</th></tr>";
    foreach ($sections as $sec) {
        echo "<tr>";
        echo "<td>" . $sec['section_id'] . "</td>";
        echo "<td>" . htmlspecialchars($sec['section_name']) . "</td>";
        echo "<td>" . $sec['is_active'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Try the JOIN query
echo "<h3>4. Students with JOIN (Like Report Query)</h3>";
$joinQuery = "SELECT s.student_id, s.admission_no, s.student_name, s.class_id, s.section_id, s.status,
              c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.status = 'Active'";
$joinResults = fetchAll($joinQuery);
echo "Students Found with JOIN: " . count($joinResults) . "<br><br>";

if (count($joinResults) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Admission No</th><th>Name</th><th>Class ID</th><th>Class Name</th><th>Section ID</th><th>Section Name</th><th>Status</th></tr>";
    foreach ($joinResults as $s) {
        echo "<tr>";
        echo "<td>" . $s['student_id'] . "</td>";
        echo "<td>" . htmlspecialchars($s['admission_no']) . "</td>";
        echo "<td>" . htmlspecialchars($s['student_name']) . "</td>";
        echo "<td>" . $s['class_id'] . "</td>";
        echo "<td>" . htmlspecialchars($s['class_name']) . "</td>";
        echo "<td>" . $s['section_id'] . "</td>";
        echo "<td>" . htmlspecialchars($s['section_name']) . "</td>";
        echo "<td>" . htmlspecialchars($s['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<strong style='color: red;'>NO STUDENTS FOUND WITH JOIN!</strong><br><br>";
    echo "<strong>This means:</strong><br>";
    echo "- Students might have invalid class_id or section_id<br>";
    echo "- OR classes/sections are marked as is_active = 0<br>";
    echo "- OR class_id/section_id don't match between tables<br>";
}

// Check for orphaned students (students with invalid class/section IDs)
echo "<h3>5. Check for Orphaned Students</h3>";
$orphanQuery = "SELECT s.student_id, s.admission_no, s.student_name, s.class_id, s.section_id, s.status
                FROM students s
                WHERE s.status = 'Active'
                AND (s.class_id NOT IN (SELECT class_id FROM classes WHERE is_active = 1)
                     OR s.section_id NOT IN (SELECT section_id FROM sections WHERE is_active = 1))";
$orphans = fetchAll($orphanQuery);
echo "Orphaned Students (with invalid class/section): " . count($orphans) . "<br><br>";

if (count($orphans) > 0) {
    echo "<strong style='color: red;'>FOUND ORPHANED STUDENTS!</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Admission No</th><th>Name</th><th>Class ID</th><th>Section ID</th></tr>";
    foreach ($orphans as $s) {
        echo "<tr>";
        echo "<td>" . $s['student_id'] . "</td>";
        echo "<td>" . htmlspecialchars($s['admission_no']) . "</td>";
        echo "<td>" . htmlspecialchars($s['student_name']) . "</td>";
        echo "<td style='background-color: yellow;'>" . ($s['class_id'] ?? 'NULL') . "</td>";
        echo "<td style='background-color: yellow;'>" . ($s['section_id'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<br><strong>Fix: Update these students with valid class_id and section_id values.</strong>";
}
?>
