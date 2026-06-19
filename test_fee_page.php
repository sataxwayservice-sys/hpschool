<?php
/**
 * Simple Fee Collection Test Page
 */

require_once 'config/config.php';
requireLogin();

// Get a student with fees
$testStudent = fetchOne("
    SELECT DISTINCT s.*, c.class_name, sec.section_name
    FROM students s
    JOIN fee_structure fs ON s.student_id = fs.student_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.status = 'Active' AND fs.is_active = 1
    LIMIT 1
");

if (!$testStudent) {
    echo "<h1>Setup Required</h1>";
    echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid red;'>";
    echo "<h3>No students with fees found!</h3>";
    echo "<p>Please:</p>";
    echo "<ol>";
    echo "<li>Add students</li>";
    echo "<li>Create fee heads</li>";
    echo "<li>Assign fees to students via Fee Structure</li>";
    echo "</ol>";
    echo "<p><a href='debug_fee_collection.php'>Run Full Diagnostic</a></p>";
    echo "</div>";
    exit();
}

echo "<h1>Fee Collection Test</h1>";
echo "<div style='background: #d4edda; padding: 20px; border: 1px solid green; margin: 20px 0;'>";
echo "<h3>Test Student Found!</h3>";
echo "<p><strong>Name:</strong> " . htmlspecialchars($testStudent['student_name']) . "</p>";
echo "<p><strong>Admission No:</strong> " . htmlspecialchars($testStudent['admission_no']) . "</p>";
echo "<p><strong>Class:</strong> " . htmlspecialchars($testStudent['class_name'] . ' - ' . $testStudent['section_name']) . "</p>";
echo "<p><strong>Student ID:</strong> " . $testStudent['student_id'] . "</p>";
echo "</div>";

// Get their fee structure
$feeStructure = fetchAll(
    "SELECT fs.*, fh.fee_head_name, fh.fee_type
     FROM fee_structure fs
     JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
     WHERE fs.student_id = ? AND fs.is_active = 1
     ORDER BY fh.display_order",
    'i',
    [$testStudent['student_id']]
);

echo "<h2>Assigned Fees:</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Fee Head</th><th>Type</th><th>Amount</th></tr>";
foreach ($feeStructure as $fee) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($fee['fee_head_name']) . "</td>";
    echo "<td>" . htmlspecialchars($fee['fee_type']) . "</td>";
    echo "<td>" . number_format($fee['amount'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check for pending fees
$paidFees = fetchAll(
    "SELECT frd.fee_head_id, frd.fee_month, frd.fee_year
     FROM fee_receipt_details frd
     JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
     WHERE fr.student_id = ? AND fr.is_cancelled = 0",
    'i',
    [$testStudent['student_id']]
);

$pendingCount = 0;
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$currentYear = date('Y');
$currentMonth = date('F');
$currentMonthIndex = array_search($currentMonth, $months);

foreach ($feeStructure as $fee) {
    if ($fee['fee_type'] == 'Monthly') {
        for ($i = 0; $i <= $currentMonthIndex; $i++) {
            $isPaid = false;
            foreach ($paidFees as $paid) {
                if ($paid['fee_head_id'] == $fee['fee_head_id'] &&
                    $paid['fee_month'] == $months[$i] &&
                    $paid['fee_year'] == $currentYear) {
                    $isPaid = true;
                    break;
                }
            }
            if (!$isPaid) $pendingCount++;
        }
    } else {
        $isPaid = false;
        foreach ($paidFees as $paid) {
            if ($paid['fee_head_id'] == $fee['fee_head_id']) {
                $isPaid = true;
                break;
            }
        }
        if (!$isPaid) $pendingCount++;
    }
}

echo "<div style='background: #d1ecf1; padding: 20px; border: 1px solid #0c5460; margin: 20px 0;'>";
echo "<h3>Pending Fees: $pendingCount</h3>";
if ($pendingCount > 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ This student has pending fees to collect!</p>";
} else {
    echo "<p style='color: orange; font-weight: bold;'>⚠ All fees already collected. Try another student.</p>";
}
echo "</div>";

echo "<h2>Test Options:</h2>";
echo "<div style='margin: 20px 0;'>";
echo "<p><a href='modules/fees/collect_complete.php?student_id=" . $testStudent['student_id'] . "' style='background: green; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 18px;'>Open Fee Collection Page for This Student</a></p>";
echo "<p><a href='debug_fee_collection.php'>Run Full Diagnostic</a> | ";
echo "<a href='modules/fees/collect_complete.php'>Open Fee Collection (Blank)</a></p>";
echo "</div>";

echo "<h2>Step-by-Step Instructions:</h2>";
echo "<ol>";
echo "<li>Click the green button above to open fee collection with this student pre-selected</li>";
echo "<li>You should see pending fees in the right panel (yellow header)</li>";
echo "<li>Click the green checkmark (✓) button next to a fee to add it to payable list</li>";
echo "<li>The fee will move to the left panel (green header)</li>";
echo "<li>Fill in payment details (date, payment method)</li>";
echo "<li>Click the green 'Save' button at the top</li>";
echo "<li>Receipt will be generated</li>";
echo "</ol>";

echo "<h2>Common Issues:</h2>";
echo "<ul>";
echo "<li><strong>No student shown:</strong> Search for admission number: " . htmlspecialchars($testStudent['admission_no']) . "</li>";
echo "<li><strong>No pending fees:</strong> All fees already collected, try another student</li>";
echo "<li><strong>Save button disabled:</strong> Add at least one fee to payable list first</li>";
echo "<li><strong>JavaScript not working:</strong> Check browser console (F12) for errors</li>";
echo "</ul>";
?>
