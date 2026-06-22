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
$asOfDate = trim((string)($_POST['issue_date'] ?? ($_POST['as_of_date'] ?? '')));
if ($asOfDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
    $asOfDate = '';
}

if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

try {
    $summary = getStudentFeeSummary($studentId, $asOfDate !== '' ? $asOfDate : null);
    $pendingFees = $summary['pending_items'] ?? [];
    $dueTotal = floatval($summary['due_total'] ?? 0);
    $dueLabel = function_exists('formatCurrency')
        ? formatCurrency($dueTotal)
        : 'Rs. ' . number_format($dueTotal, 2);

    echo json_encode([
        'success' => true,
        'pending_fees' => array_values(is_array($pendingFees) ? $pendingFees : []),
        'total_pending' => count(is_array($pendingFees) ? $pendingFees : []),
        'assigned_total' => $summary['assigned_total'] ?? 0,
        'paid_total' => $summary['paid_total'] ?? 0,
        'due_total' => $dueTotal,
        'due_label' => $dueLabel,
        'as_of_date' => $asOfDate !== '' ? $asOfDate : null,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pending fees: ' . $e->getMessage()
    ]);
}
?>
