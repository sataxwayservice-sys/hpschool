<?php
require_once __DIR__ . '/config/config.php';

// Test query for student STU000001
echo "<h2>Testing Due Fees Query for STU000001</h2>";

// First, get the student_id
$student = fetchOne("SELECT student_id, student_name, admission_no FROM students WHERE admission_no = 'STU000001'");
if (!$student) {
    die("Student STU000001 not found");
}

echo "<h3>Student Info:</h3>";
echo "<pre>";
print_r($student);
echo "</pre>";

$studentId = $student['student_id'];

// Check fee_receipts
echo "<h3>Fee Receipts:</h3>";
$receipts = fetchAll("SELECT receipt_id, receipt_no, amount_paid, is_cancelled, created_at
                      FROM fee_receipts
                      WHERE student_id = ?", 'i', [$studentId]);
echo "<pre>";
print_r($receipts);
echo "</pre>";

// Check fee_receipt_details
echo "<h3>Fee Receipt Details (ALL):</h3>";
$details = fetchAll("SELECT frd.*, fr.is_cancelled
                     FROM fee_receipt_details frd
                     JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                     WHERE fr.student_id = ?", 'i', [$studentId]);
echo "<pre>";
print_r($details);
echo "</pre>";

// Sum of all receipt details
$sumAllDetails = fetchOne("SELECT SUM(frd.amount) as total
                           FROM fee_receipt_details frd
                           JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                           WHERE fr.student_id = ?", 'i', [$studentId]);
echo "<h3>Sum of ALL receipt details:</h3>";
echo "<pre>₹" . number_format($sumAllDetails['total'], 2) . "</pre>";

// Sum of non-cancelled receipts
$sumPaid = fetchOne("SELECT SUM(amount_paid) as total
                     FROM fee_receipts
                     WHERE student_id = ? AND is_cancelled = 0", 'i', [$studentId]);
echo "<h3>Sum of Non-Cancelled Receipts (Total Paid):</h3>";
echo "<pre>₹" . number_format($sumPaid['total'], 2) . "</pre>";

// Check student_fees
echo "<h3>Student Fees (Pending):</h3>";
$pendingFees = fetchAll("SELECT * FROM student_fees WHERE student_id = ?", 'i', [$studentId]);
echo "<pre>";
print_r($pendingFees);
echo "</pre>";

// Sum of pending fees
$sumPending = fetchOne("SELECT SUM(amount) as total FROM student_fees WHERE student_id = ? AND status = 'Pending'", 'i', [$studentId]);
echo "<h3>Sum of Pending Fees:</h3>";
echo "<pre>₹" . number_format($sumPending['total'] ?? 0, 2) . "</pre>";

// Calculate totals
$totalAssigned = ($sumAllDetails['total'] ?? 0) + ($sumPending['total'] ?? 0);
$totalPaid = $sumPaid['total'] ?? 0;
$dueAmount = $totalAssigned - $totalPaid;

echo "<h3 style='color: green;'>FINAL CALCULATION:</h3>";
echo "<pre>";
echo "Total Assigned: ₹" . number_format($totalAssigned, 2) . " (Receipt Details: ₹" . number_format($sumAllDetails['total'] ?? 0, 2) . " + Pending: ₹" . number_format($sumPending['total'] ?? 0, 2) . ")\n";
echo "Total Paid: ₹" . number_format($totalPaid, 2) . "\n";
echo "Due Amount: ₹" . number_format($dueAmount, 2) . "\n";
echo "</pre>";

// Test the actual query
echo "<h3>Testing Actual Query:</h3>";
$query = "SELECT
            s.student_id, s.student_name, s.admission_no,
            (COALESCE(SUM(DISTINCT sf.amount), 0) +
             COALESCE((SELECT SUM(frd.amount)
                       FROM fee_receipt_details frd
                       JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                       WHERE fr.student_id = s.student_id), 0)) as total_fee_assigned,
            COALESCE((SELECT SUM(amount_paid) FROM fee_receipts WHERE student_id = s.student_id AND is_cancelled = 0), 0) as total_paid,
            ((COALESCE(SUM(DISTINCT sf.amount), 0) +
              COALESCE((SELECT SUM(frd.amount)
                        FROM fee_receipt_details frd
                        JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                        WHERE fr.student_id = s.student_id), 0)) -
             COALESCE((SELECT SUM(amount_paid) FROM fee_receipts WHERE student_id = s.student_id AND is_cancelled = 0), 0)) as due_amount
          FROM students s
          LEFT JOIN student_fees sf ON s.student_id = sf.student_id AND sf.status = 'Pending'
          WHERE s.student_id = ?
          GROUP BY s.student_id";

try {
    $result = fetchOne($query, 'i', [$studentId]);
    echo "<pre>";
    print_r($result);
    echo "</pre>";

    if ($result) {
        echo "<h3 style='color: blue;'>Query Result:</h3>";
        echo "<pre>";
        echo "Total Assigned: ₹" . number_format($result['total_fee_assigned'], 2) . "\n";
        echo "Total Paid: ₹" . number_format($result['total_paid'], 2) . "\n";
        echo "Due Amount: ₹" . number_format($result['due_amount'], 2) . "\n";
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>Query returned no results</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3 style='color: red;'>ISSUE IDENTIFIED:</h3>";
echo "<p>You mentioned student should have ₹42,500 total assigned, but receipts only show ₹32,500.</p>";
echo "<p><strong>Missing: ₹10,000</strong></p>";
echo "<p>This ₹10,000 should either be:</p>";
echo "<ol>";
echo "<li>In student_fees table as pending (but it's not there)</li>";
echo "<li>OR these fees were never properly assigned to the student</li>";
echo "</ol>";
echo "<p><strong>Question:</strong> Were there fees assigned to this student that were never collected? If yes, they should be added to the student_fees table.</p>";
?>
