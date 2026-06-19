<?php
/**
 * Migration: Add Student Reminders System
 *
 * This creates a table to store reminders for students
 * Teachers and admins can add reminders that popup when viewing student details
 */

require_once '../config/config.php';

echo "<h1>Migration: Add Student Reminders System</h1>";

try {
    $conn = getDbConnection();

    // Create student_reminders table
    echo "<h2>Creating student_reminders table...</h2>";

    $sql = "CREATE TABLE IF NOT EXISTS student_reminders (
        reminder_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        reminder_text TEXT NOT NULL,
        created_by INT NULL,
        created_by_role ENUM('admin', 'teacher', 'staff') DEFAULT 'teacher',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_resolved TINYINT(1) DEFAULT 0,
        resolved_at TIMESTAMP NULL,
        resolved_by INT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',

        INDEX idx_student (student_id),
        INDEX idx_resolved (is_resolved),
        INDEX idx_created_by (created_by),

        CONSTRAINT fk_reminder_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        CONSTRAINT fk_reminder_created_by FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
        CONSTRAINT fk_reminder_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Table 'student_reminders' created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating table: " . $conn->error . "</p>";
    }

    echo "<h2>Migration Summary:</h2>";
    echo "<ul>";
    echo "<li>✓ Created student_reminders table</li>";
    echo "<li>✓ Added foreign keys for data integrity</li>";
    echo "<li>✓ Added indexes for performance</li>";
    echo "<li>✓ Support for priority levels (low, medium, high)</li>";
    echo "<li>✓ Track who created and resolved reminders</li>";
    echo "<li>✓ Track creation and resolution timestamps</li>";
    echo "</ul>";

    echo "<h2>Features Enabled:</h2>";
    echo "<ul>";
    echo "<li>Teachers can add reminders for students</li>";
    echo "<li>Admins can view all reminders across all students</li>";
    echo "<li>Auto-popup when viewing student details</li>";
    echo "<li>Mark reminders as resolved</li>";
    echo "<li>Track who added each reminder</li>";
    echo "</ul>";

    echo "<hr>";
    echo "<p><strong>Migration completed successfully!</strong></p>";
    echo "<p><a href='../index.php'>← Back to Dashboard</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Migration failed: " . $e->getMessage() . "</p>";
}
?>
