<?php
/**
 * Delete Student
 */

// Include configuration
require_once '../../config/config.php';

// Require login and permission
requireLogin();
requirePermission('students', 'delete');

$currentUser = getCurrentUser();
$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($studentId == 0) {
    alertAndRedirect('Invalid student ID', APP_URL . '/modules/students/', 'error');
}

// Get student info first
$student = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$studentId]);

if (!$student) {
    alertAndRedirect('Student not found', APP_URL . '/modules/students/', 'error');
}

// Delete student
beginTransaction();

try {
    // Delete student record
    $deleteQuery = "DELETE FROM students WHERE student_id = ?";
    $result = executeQuery($deleteQuery, 'i', [$studentId]);

    if ($result !== false) {
        // Delete photo if exists
        if (!empty($student['photo'])) {
            deleteFile(STUDENT_PHOTO_PATH . $student['photo']);
        }

        // Log activity
        logActivity($currentUser['user_id'], 'Delete Student', 'Students',
            "Deleted student: {$student['student_name']} (ID: $studentId)");

        commitTransaction();
        alertAndRedirect('Student deleted successfully', APP_URL . '/modules/students/', 'success');
    } else {
        rollbackTransaction();
        alertAndRedirect('Failed to delete student', APP_URL . '/modules/students/', 'error');
    }
} catch (Exception $e) {
    rollbackTransaction();
    alertAndRedirect('Error: ' . $e->getMessage(), APP_URL . '/modules/students/', 'error');
}
?>
