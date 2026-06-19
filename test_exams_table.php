<?php
/**
 * Test Exams Table - Diagnostic Script
 */

require_once 'config/config.php';

echo "<h2>Exams Table Diagnostic</h2>";

// Test 1: Check if exams table exists
echo "<h3>Step 1: Check if exams table exists</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'exams'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ exams table EXISTS";
    echo "</div>";

    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $structure = fetchAll("DESCRIBE exams");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Show existing exams
    echo "<h4>Existing Exams:</h4>";
    $existing = fetchAll("SELECT * FROM exams");
    if (count($existing) > 0) {
        echo "<p>Found " . count($existing) . " exam(s)</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Exam Name</th><th>Type</th><th>Date</th><th>Academic Year</th><th>Active</th></tr>";
        foreach ($existing as $exam) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($exam['exam_id']) . "</td>";
            echo "<td>" . htmlspecialchars($exam['exam_name']) . "</td>";
            echo "<td>" . htmlspecialchars($exam['exam_type'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($exam['exam_date']) . "</td>";
            echo "<td>" . htmlspecialchars($exam['academic_year'] ?? '-') . "</td>";
            echo "<td>" . ($exam['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No exams in the table yet.</p>";
    }

} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ exams table DOES NOT EXIST<br><br>";
    echo "<strong>Action Required:</strong> You need to create the table first!<br><br>";
    echo "Run this SQL in phpMyAdmin:";
    echo "</div>";

    echo "<textarea style='width: 100%; height: 400px; font-family: monospace; margin-top: 10px;'>";
    echo "-- Create exams table
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(100) NOT NULL,
  `exam_type` varchar(50) DEFAULT NULL,
  `exam_date` date NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`exam_id`),
  KEY `created_by` (`created_by`),
  KEY `exam_date` (`exam_date`),
  KEY `is_active` (`is_active`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Also create marks table if it doesn't exist
CREATE TABLE IF NOT EXISTS `marks` (
  `mark_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `max_marks` decimal(5,2) DEFAULT 100.00,
  `grade` varchar(10) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mark_id`),
  UNIQUE KEY `unique_mark` (`student_id`,`exam_id`,`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`),
  KEY `subject_id` (`subject_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create subjects table if it doesn't exist
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `max_marks` int(11) NOT NULL DEFAULT 100,
  `pass_marks` int(11) NOT NULL DEFAULT 33,
  `class_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `subject_code` (`subject_code`),
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    echo "</textarea>";
}

// Test 2: Check if marks table exists
echo "<h3>Step 2: Check if marks table exists</h3>";
$marksCheck = fetchAll("SHOW TABLES LIKE 'marks'");
if (count($marksCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ marks table EXISTS";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ marks table DOES NOT EXIST - needed for the marks system";
    echo "</div>";
}

// Test 3: Check if subjects table exists
echo "<h3>Step 3: Check if subjects table exists</h3>";
$subjectsCheck = fetchAll("SHOW TABLES LIKE 'subjects'");
if (count($subjectsCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ subjects table EXISTS";
    echo "</div>";

    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $structure = fetchAll("DESCRIBE subjects");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Show existing subjects
    echo "<h4>Existing Subjects:</h4>";
    $existing = fetchAll("SELECT * FROM subjects");
    if (count($existing) > 0) {
        echo "<p>Found " . count($existing) . " subject(s)</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Subject Name</th><th>Code</th><th>Max Marks</th><th>Pass Marks</th><th>Active</th></tr>";
        foreach ($existing as $subject) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($subject['subject_id']) . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_code'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($subject['max_marks'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($subject['pass_marks'] ?? '-') . "</td>";
            echo "<td>" . ($subject['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No subjects in the table yet.</p>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ subjects table DOES NOT EXIST - needed for the marks system";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If any table is missing, copy the SQL above and run it in phpMyAdmin</li>";
echo "<li>After creating tables, try adding an exam again</li>";
echo "<li>Go to <a href='modules/marks/manage_exams.php'>Manage Exams</a></li>";
echo "</ol>";
?>
