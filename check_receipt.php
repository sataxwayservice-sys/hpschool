<?php
/**
 * Check Receipt Status
 * Diagnostic tool to check receipt details
 */

require_once 'config/config.php';
requireLogin();

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : 7;

echo "<h1>Receipt Diagnostic - ID: $receiptId</h1>";
echo "<style>
    .success { background: #d4edda; padding: 15px; border: 1px solid green; margin: 10px 0; }
    .error { background: #f8d7da; padding: 15px; border: 1px solid red; margin: 10px 0; }
    .info { background: #d1ecf1; padding: 15px; border: 1px solid #0c5460; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th { background: #f2f2f2; padding: 10px; text-align: left; border: 1px solid #ddd; }
    td { padding: 8px; border: 1px solid #ddd; }
</style>";

$conn = getDbConnection();

// Check if receipt exists in fee_receipts table
echo "<h2>1. Check fee_receipts table</h2>";
$receiptQuery = "SELECT * FROM fee_receipts WHERE receipt_id = $receiptId";
$receiptResult = $conn->query($receiptQuery);

if ($receiptResult && $receiptResult->num_rows > 0) {
    $receipt = $receiptResult->fetch_assoc();
    echo "<div class='success'>✓ Receipt found in fee_receipts table</div>";
    echo "<table>";
    foreach ($receipt as $key => $value) {
        $highlight = '';
        if ($key == 'is_cancelled' && $value == 1) {
            $highlight = 'style="background: #fff3cd; font-weight: bold;"';
        }
        echo "<tr $highlight><th>$key</th><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";

    $studentId = $receipt['student_id'];

    // Check if student exists
    echo "<h2>2. Check Student (ID: $studentId)</h2>";
    $studentQuery = "SELECT * FROM students WHERE student_id = $studentId";
    $studentResult = $conn->query($studentQuery);

    if ($studentResult && $studentResult->num_rows > 0) {
        $student = $studentResult->fetch_assoc();
        echo "<div class='success'>✓ Student found</div>";
        echo "<table>";
        echo "<tr><th>student_id</th><td>" . $student['student_id'] . "</td></tr>";
        echo "<tr><th>student_name</th><td>" . $student['student_name'] . "</td></tr>";
        echo "<tr><th>admission_no</th><td>" . $student['admission_no'] . "</td></tr>";
        echo "<tr><th>class_id</th><td>" . $student['class_id'] . "</td></tr>";
        echo "<tr><th>section_id</th><td>" . $student['section_id'] . "</td></tr>";
        echo "</table>";

        // Check class
        echo "<h2>3. Check Class (ID: {$student['class_id']})</h2>";
        $classQuery = "SELECT * FROM classes WHERE class_id = " . $student['class_id'];
        $classResult = $conn->query($classQuery);

        if ($classResult && $classResult->num_rows > 0) {
            $class = $classResult->fetch_assoc();
            echo "<div class='success'>✓ Class found: " . htmlspecialchars($class['class_name']) . "</div>";
        } else {
            echo "<div class='error'>✗ Class NOT FOUND! This is why the PDF query fails.</div>";
        }

        // Check section
        echo "<h2>4. Check Section (ID: {$student['section_id']})</h2>";
        $sectionQuery = "SELECT * FROM sections WHERE section_id = " . $student['section_id'];
        $sectionResult = $conn->query($sectionQuery);

        if ($sectionResult && $sectionResult->num_rows > 0) {
            $section = $sectionResult->fetch_assoc();
            echo "<div class='success'>✓ Section found: " . htmlspecialchars($section['section_name']) . "</div>";
        } else {
            echo "<div class='error'>✗ Section NOT FOUND! This is why the PDF query fails.</div>";
        }

    } else {
        echo "<div class='error'>✗ Student NOT FOUND! Receipt references student_id=$studentId which doesn't exist.</div>";
    }

    // Check receipt details
    echo "<h2>5. Check Receipt Details</h2>";
    $detailsQuery = "SELECT * FROM fee_receipt_details WHERE receipt_id = $receiptId";
    $detailsResult = $conn->query($detailsQuery);

    if ($detailsResult && $detailsResult->num_rows > 0) {
        echo "<div class='success'>✓ Found " . $detailsResult->num_rows . " receipt detail(s)</div>";
        echo "<table>";
        echo "<tr><th>detail_id</th><th>fee_head_id</th><th>fee_month</th><th>fee_year</th><th>amount</th></tr>";
        while ($detail = $detailsResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $detail['detail_id'] . "</td>";
            echo "<td>" . $detail['fee_head_id'] . "</td>";
            echo "<td>" . $detail['fee_month'] . "</td>";
            echo "<td>" . $detail['fee_year'] . "</td>";
            echo "<td>₹" . number_format($detail['amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>✗ No receipt details found</div>";
    }

} else {
    echo "<div class='error'>✗ Receipt ID $receiptId NOT FOUND in fee_receipts table</div>";

    // Check if it's in deleted_items (recycle bin)
    echo "<h2>2. Check Recycle Bin</h2>";
    $deletedQuery = "SELECT * FROM deleted_items WHERE item_type = 'fee_receipt' AND item_id = $receiptId";
    $deletedResult = $conn->query($deletedQuery);

    if ($deletedResult && $deletedResult->num_rows > 0) {
        $deleted = $deletedResult->fetch_assoc();
        echo "<div class='info'>ℹ Receipt found in RECYCLE BIN</div>";
        echo "<table>";
        echo "<tr><th>deleted_id</th><td>" . $deleted['deleted_id'] . "</td></tr>";
        echo "<tr><th>deleted_by</th><td>" . $deleted['deleted_by'] . "</td></tr>";
        echo "<tr><th>deleted_at</th><td>" . $deleted['deleted_at'] . "</td></tr>";
        echo "<tr><th>reason</th><td>" . htmlspecialchars($deleted['reason']) . "</td></tr>";
        echo "</table>";

        echo "<div class='info'>";
        echo "<strong>Action:</strong> To restore this receipt:<br>";
        echo "1. Go to <a href='modules/settings/recycle_bin.php?type=fee_receipt'>Recycle Bin</a><br>";
        echo "2. Find receipt ID $receiptId<br>";
        echo "3. Click the green 'Restore' button";
        echo "</div>";
    } else {
        echo "<div class='error'>✗ Receipt NOT found in recycle bin either!</div>";
    }
}

// Check if deleted_items table exists
echo "<h2>6. System Status</h2>";
$tableCheck = $conn->query("SHOW TABLES LIKE 'deleted_items'");
if ($tableCheck->num_rows > 0) {
    echo "<div class='success'>✓ deleted_items table exists</div>";
    $count = $conn->query("SELECT COUNT(*) as count FROM deleted_items WHERE item_type = 'fee_receipt'")->fetch_assoc()['count'];
    echo "<div class='info'>Total cancelled receipts in recycle bin: $count</div>";
} else {
    echo "<div class='error'>✗ deleted_items table doesn't exist!</div>";
}

echo "<hr>";
echo "<h2>Quick Links</h2>";
echo "<p>";
echo "<a href='modules/settings/recycle_bin.php?type=fee_receipt'>View Recycle Bin</a> | ";
echo "<a href='modules/fees/receipts.php'>View All Receipts</a> | ";
echo "<a href='check_receipt.php?id=$receiptId'>Refresh This Page</a>";
echo "</p>";
?>
