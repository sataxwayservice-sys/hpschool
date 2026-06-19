<?php
/**
 * QUICK SETUP: Student Reminders System
 * Run this once to create the database table
 */

require_once 'config/config.php';

echo "<!DOCTYPE html><html><head><title>Reminders Setup</title>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css'>";
echo "</head><body><div class='container mt-5'>";

echo "<h1 class='mb-4'>📝 Student Reminders - Database Setup</h1>";

try {
    $conn = getDbConnection();

    // Check if table already exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'student_reminders'");
    if ($tableCheck->num_rows > 0) {
        echo "<div class='alert alert-info'>";
        echo "<h4>✓ Table Already Exists</h4>";
        echo "<p>The 'student_reminders' table is already created. No action needed.</p>";
        echo "</div>";
    } else {
        // Create student_reminders table
        echo "<h3>Creating student_reminders table...</h3>";

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
            echo "<div class='alert alert-success'>";
            echo "<h4>✓ SUCCESS!</h4>";
            echo "<p>Table 'student_reminders' created successfully!</p>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-danger'>";
            echo "<h4>✗ Error</h4>";
            echo "<p>Error creating table: " . $conn->error . "</p>";
            echo "</div>";
        }
    }

    // Show table structure
    echo "<h3 class='mt-4'>Table Structure:</h3>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-bordered'>";
    echo "<thead class='table-dark'><tr><th>Column</th><th>Type</th><th>Description</th></tr></thead>";
    echo "<tbody>";
    echo "<tr><td>reminder_id</td><td>INT</td><td>Primary key</td></tr>";
    echo "<tr><td>student_id</td><td>INT</td><td>Student reference</td></tr>";
    echo "<tr><td>reminder_text</td><td>TEXT</td><td>Reminder message</td></tr>";
    echo "<tr><td>created_by</td><td>INT</td><td>User who created</td></tr>";
    echo "<tr><td>created_by_role</td><td>ENUM</td><td>admin/teacher/staff</td></tr>";
    echo "<tr><td>priority</td><td>ENUM</td><td>low/medium/high</td></tr>";
    echo "<tr><td>is_resolved</td><td>TINYINT</td><td>0=active, 1=resolved</td></tr>";
    echo "<tr><td>resolved_at</td><td>TIMESTAMP</td><td>When resolved</td></tr>";
    echo "<tr><td>resolved_by</td><td>INT</td><td>Who resolved it</td></tr>";
    echo "</tbody>";
    echo "</table>";
    echo "</div>";

    echo "<div class='alert alert-success mt-4'>";
    echo "<h4>✅ Setup Complete!</h4>";
    echo "<p>You can now use the reminder system.</p>";
    echo "</div>";

    echo "<h3 class='mt-4'>Next Steps:</h3>";
    echo "<div class='list-group'>";
    echo "<a href='check_student.php' class='list-group-item list-group-item-action'>";
    echo "<h5>1. Test Reminders (Student Check Page)</h5>";
    echo "<p class='mb-0'>View/Add reminders for students</p>";
    echo "</a>";
    echo "<a href='modules/admin/all_reminders.php' class='list-group-item list-group-item-action'>";
    echo "<h5>2. View All Reminders (Admin Only)</h5>";
    echo "<p class='mb-0'>See all reminders across all students</p>";
    echo "</a>";
    echo "<a href='index.php' class='list-group-item list-group-item-action'>";
    echo "<h5>3. Back to Dashboard</h5>";
    echo "<p class='mb-0'>Return to main dashboard</p>";
    echo "</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>✗ Setup Failed</h4>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</div></body></html>";
?>
