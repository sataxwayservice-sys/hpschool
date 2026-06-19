<?php
/**
 * API: Search Students by Name or Admission Number
 */

header('Content-Type: application/json');

require_once '../config/config.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$searchTerm = sanitize($_POST['search'] ?? '');
$classId = intval($_POST['class_id'] ?? 0);

if (empty($searchTerm) && $classId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Search term is required']);
    exit();
}

// Search for students by name or admission number, with optional class filter
$query = "SELECT s.*, c.class_name, sec.section_name
          FROM students s
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN sections sec ON s.section_id = sec.section_id
          WHERE s.status = 'Active'";

$params = [];
$types = '';

if (!empty($searchTerm)) {
    $query .= " AND (s.student_name LIKE ? OR s.admission_no LIKE ? OR s.roll_no LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'sss';
}

if ($classId > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $classId;
    $types .= 'i';
}

$query .= " ORDER BY s.student_name LIMIT 10";

$students = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);

if (count($students) > 0) {
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No students found'
    ]);
}
?>
