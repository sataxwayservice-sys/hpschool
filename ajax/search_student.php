<?php
/**
 * AJAX Handler: Search Student
 * Returns student details by admission number or student ID
 */

// Include configuration (handles session start)
require_once '../config/config.php';

// Require login
if (!isLoggedIn()) {
    jsonResponse(false, 'Unauthorized access');
}

// Get search parameter
$admissionNo = sanitize($_GET['admission_no'] ?? '');
$studentId = intval($_GET['student_id'] ?? 0);

if (empty($admissionNo) && $studentId == 0) {
    jsonResponse(false, 'Please provide admission number or student ID');
}

// Search query
if (!empty($admissionNo)) {
    $query = "SELECT s.*, c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.admission_no LIKE ? AND s.status = 'Active'
              LIMIT 10";
    $students = fetchAll($query, 's', ['%' . $admissionNo . '%']);
} else {
    $query = "SELECT s.*, c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.student_id = ? AND s.status = 'Active'";
    $student = fetchOne($query, 'i', [$studentId]);
    $students = $student ? [$student] : [];
}

if (empty($students)) {
    jsonResponse(false, 'No student found');
}

// Return single or multiple results
if (count($students) == 1) {
    jsonResponse(true, 'Student found', $students[0]);
} else {
    jsonResponse(true, 'Multiple students found', $students);
}
?>
