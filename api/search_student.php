<?php
/**
 * API: Search Student by Admission Number or Name
 */

header('Content-Type: application/json');

require_once '../config/config.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$admissionNo = sanitize($_POST['admission_no'] ?? '');
$searchTerm = sanitize($_POST['search_term'] ?? $admissionNo);
$classId = intval($_POST['class_id'] ?? 0);

if (empty($searchTerm)) {
    echo json_encode(['success' => false, 'message' => 'Search term is required']);
    exit();
}

// Try exact match first by admission number or roll number
$query = "SELECT s.*, c.class_name, sec.section_name
          FROM students s
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN sections sec ON s.section_id = sec.section_id
          WHERE (s.admission_no = ? OR s.roll_no = ?) AND s.status = 'Active'";

$params = [$searchTerm, $searchTerm];
$types = 'ss';

if ($classId > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $classId;
    $types .= 'i';
}

$query .= " LIMIT 1";

$student = fetchOne($query, $types, $params);

if ($student) {
    echo json_encode([
        'success' => true,
        'student' => $student
    ]);
    exit();
}

// If not found by exact admission number, try partial match
$searchPattern = '%' . $searchTerm . '%';
$query = "SELECT s.*, c.class_name, sec.section_name
          FROM students s
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN sections sec ON s.section_id = sec.section_id
          WHERE (s.admission_no LIKE ? OR s.student_name LIKE ? OR s.roll_no LIKE ?) AND s.status = 'Active'";

$params = [$searchPattern, $searchPattern, $searchPattern];
$types = 'sss';

if ($classId > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $classId;
    $types .= 'i';
}

$query .= " LIMIT 1";

$student = fetchOne($query, $types, $params);

if ($student) {
    echo json_encode([
        'success' => true,
        'student' => $student
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Student not found'
    ]);
}
?>
