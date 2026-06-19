<?php
/**
 * AJAX Endpoint - Get Students
 * Returns students filtered by class, section, and status
 */

// Include configuration
require_once '../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Get POST parameters
$classId = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$sectionId = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate input
if ($classId <= 0 || $sectionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid class or section'
    ]);
    exit();
}

try {
    // Build query (only select existing columns)
    $query = "SELECT
                s.student_id,
                s.admission_no,
                s.student_name,
                s.roll_no,
                s.gender,
                s.father_name,
                s.status,
                c.class_name,
                sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.class_id = ? AND s.section_id = ?";

    $params = [$classId, $sectionId];
    $types = 'ii';

    // Add status filter if provided
    if (!empty($status)) {
        $query .= " AND s.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    // Order by roll number, then by name
    $query .= " ORDER BY
                CASE WHEN s.roll_no IS NULL THEN 1 ELSE 0 END,
                s.roll_no ASC,
                s.student_name ASC";

    // Execute query
    $students = fetchAll($query, $types, $params);

    // Return response
    if ($students && count($students) > 0) {
        echo json_encode([
            'success' => true,
            'count' => count($students),
            'students' => $students
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No students found in this class/section',
            'students' => []
        ]);
    }

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
