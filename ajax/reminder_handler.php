<?php
/**
 * AJAX Handler for Student Reminders
 * Handles: Add, Edit, Delete, Resolve reminders
 */

require_once '../config/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'get_reminders':
        // Get all reminders for a student
        $studentId = intval($_POST['student_id']);

        $reminders = fetchAll("SELECT r.*, u1.username as created_by_name, u2.username as resolved_by_name
                               FROM student_reminders r
                               LEFT JOIN users u1 ON r.created_by = u1.user_id
                               LEFT JOIN users u2 ON r.resolved_by = u2.user_id
                               WHERE r.student_id = ?
                               ORDER BY r.is_resolved ASC, r.priority DESC, r.created_at DESC", 'i', [$studentId]);

        $response = [
            'success' => true,
            'reminders' => $reminders
        ];
        break;

    case 'add_reminder':
        // Add new reminder
        $studentId = intval($_POST['student_id']);
        $reminderText = sanitize($_POST['reminder_text']);
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['role'];

        if (empty($reminderText)) {
            $response = ['success' => false, 'message' => 'Reminder text is required'];
            break;
        }

        $inserted = executeQuery(
            "INSERT INTO student_reminders (student_id, reminder_text, created_by, created_by_role, priority)
             VALUES (?, ?, ?, ?, ?)",
            'iisss',
            [$studentId, $reminderText, $userId, $userRole, $priority]
        );

        if ($inserted) {
            logActivity($userId, 'Reminder Added', 'students', "Added reminder for student ID: $studentId");
            $response = [
                'success' => true,
                'message' => 'Reminder added successfully!',
                'reminder_id' => $inserted
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to add reminder'];
        }
        break;

    case 'edit_reminder':
        // Edit existing reminder
        $reminderId = intval($_POST['reminder_id']);
        $reminderText = sanitize($_POST['reminder_text']);
        $priority = sanitize($_POST['priority'] ?? 'medium');

        if (empty($reminderText)) {
            $response = ['success' => false, 'message' => 'Reminder text is required'];
            break;
        }

        $updated = executeQuery(
            "UPDATE student_reminders SET reminder_text = ?, priority = ? WHERE reminder_id = ?",
            'ssi',
            [$reminderText, $priority, $reminderId]
        );

        if ($updated) {
            logActivity($_SESSION['user_id'], 'Reminder Updated', 'students', "Updated reminder ID: $reminderId");
            $response = [
                'success' => true,
                'message' => 'Reminder updated successfully!'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to update reminder'];
        }
        break;

    case 'resolve_reminder':
        // Mark reminder as resolved
        $reminderId = intval($_POST['reminder_id']);
        $userId = $_SESSION['user_id'];

        $updated = executeQuery(
            "UPDATE student_reminders SET is_resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE reminder_id = ?",
            'ii',
            [$userId, $reminderId]
        );

        if ($updated) {
            logActivity($userId, 'Reminder Resolved', 'students', "Resolved reminder ID: $reminderId");
            $response = [
                'success' => true,
                'message' => 'Reminder marked as resolved!'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to resolve reminder'];
        }
        break;

    case 'unresolve_reminder':
        // Mark reminder as unresolved (reopen)
        $reminderId = intval($_POST['reminder_id']);

        $updated = executeQuery(
            "UPDATE student_reminders SET is_resolved = 0, resolved_at = NULL, resolved_by = NULL WHERE reminder_id = ?",
            'i',
            [$reminderId]
        );

        if ($updated) {
            logActivity($_SESSION['user_id'], 'Reminder Reopened', 'students', "Reopened reminder ID: $reminderId");
            $response = [
                'success' => true,
                'message' => 'Reminder reopened!'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to reopen reminder'];
        }
        break;

    case 'delete_reminder':
        // Delete reminder (admin only)
        if ($_SESSION['role'] !== 'admin') {
            $response = ['success' => false, 'message' => 'Only admins can delete reminders'];
            break;
        }

        $reminderId = intval($_POST['reminder_id']);

        $deleted = executeQuery("DELETE FROM student_reminders WHERE reminder_id = ?", 'i', [$reminderId]);

        if ($deleted) {
            logActivity($_SESSION['user_id'], 'Reminder Deleted', 'students', "Deleted reminder ID: $reminderId");
            $response = [
                'success' => true,
                'message' => 'Reminder deleted successfully!'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Failed to delete reminder'];
        }
        break;

    default:
        $response = ['success' => false, 'message' => 'Unknown action'];
}

echo json_encode($response);
?>
