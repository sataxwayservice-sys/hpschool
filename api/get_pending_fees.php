<?php
/**
 * Get Pending Fees for a Student
 */

header('Content-Type: application/json');
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$studentId = intval($_POST['student_id'] ?? 0);

if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

try {
    $summary = getStudentFeeSummary($studentId);
    $pendingFees = $summary['pending_items'] ?? [];

    if (empty($pendingFees)) {
        echo json_encode([
            'success' => false,
            'message' => 'No pending fees found for this student'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'pending_fees' => $pendingFees,
        'total_pending' => count($pendingFees),
        'assigned_total' => $summary['assigned_total'] ?? 0,
        'paid_total' => $summary['paid_total'] ?? 0,
        'due_total' => $summary['due_total'] ?? 0
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pending fees: ' . $e->getMessage()
    ]);
}
?>
