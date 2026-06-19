<?php
/**
 * Quick Database Check
 * Shows what's in the fee_receipts table
 */

require_once 'config/config.php';
requireLogin();

$conn = getDbConnection();

echo "<h1>Database Status Check</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { background: #d4edda; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; border-left: 4px solid #0c5460; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th { background: #0d6efd; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #ddd; }
    .btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none;
           background: #0d6efd; color: white; border-radius: 5px; }
    .btn:hover { background: #0a58ca; }
    .btn-success { background: #28a745; }
    .btn-danger { background: #dc3545; }
</style>";

// Check if fee_receipts table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'fee_receipts'");

if ($tableCheck->num_rows == 0) {
    echo "<div class='box error'>";
    echo "<h2>❌ Error: fee_receipts table doesn't exist!</h2>";
    echo "<p>The database table for receipts hasn't been created yet.</p>";
    echo "<p><strong>Solution:</strong> You need to run the database setup scripts first.</p>";
    echo "</div>";
    exit;
}

echo "<div class='box success'>";
echo "<h2>✅ Database table 'fee_receipts' exists</h2>";
echo "</div>";

// Count receipts
$totalQuery = "SELECT COUNT(*) as count FROM fee_receipts";
$totalResult = $conn->query($totalQuery);
$totalCount = $totalResult->fetch_assoc()['count'];

$activeQuery = "SELECT COUNT(*) as count FROM fee_receipts WHERE is_cancelled = 0 OR is_cancelled IS NULL";
$activeResult = $conn->query($activeQuery);
$activeCount = $activeResult->fetch_assoc()['count'];

$cancelledQuery = "SELECT COUNT(*) as count FROM fee_receipts WHERE is_cancelled = 1";
$cancelledResult = $conn->query($cancelledQuery);
$cancelledCount = $cancelledResult->fetch_assoc()['count'];

echo "<div class='box info'>";
echo "<h2>📊 Receipt Statistics</h2>";
echo "<p><strong>Total Receipts:</strong> $totalCount</p>";
echo "<p><strong>Active Receipts:</strong> $activeCount</p>";
echo "<p><strong>Cancelled Receipts:</strong> $cancelledCount</p>";
echo "</div>";

if ($totalCount == 0) {
    echo "<div class='box error'>";
    echo "<h2>⚠️ No Receipts Found!</h2>";
    echo "<p>Your database has no fee receipts yet. This is why you're getting 'Receipt not found' errors.</p>";
    echo "<h3>What to do next:</h3>";
    echo "<ol>";
    echo "<li>Go to the fee collection page to create a receipt</li>";
    echo "<li>Or restore receipts from the Recycle Bin if you deleted them</li>";
    echo "</ol>";
    echo "<p>";
    echo "<a href='modules/fees/collect_complete.php' class='btn btn-success'>Create New Receipt</a> ";
    echo "<a href='modules/settings/recycle_bin.php?type=fee_receipt' class='btn btn-danger'>Check Recycle Bin</a>";
    echo "</p>";
    echo "</div>";
} else {
    echo "<div class='box success'>";
    echo "<h2>✅ Receipts Found: $totalCount</h2>";
    echo "<p>Here are all the receipts in your database:</p>";

    // Get all receipts with details
    $receiptsQuery = "SELECT
                        fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
                        fr.payment_mode, fr.payment_date, fr.is_cancelled,
                        s.student_name, s.admission_no
                      FROM fee_receipts fr
                      LEFT JOIN students s ON fr.student_id = s.student_id
                      ORDER BY fr.receipt_id DESC
                      LIMIT 50";

    $receiptsResult = $conn->query($receiptsQuery);

    echo "<table>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Receipt ID</th>";
    echo "<th>Receipt No</th>";
    echo "<th>Student</th>";
    echo "<th>Amount</th>";
    echo "<th>Date</th>";
    echo "<th>Status</th>";
    echo "<th>Test PDF</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    while ($receipt = $receiptsResult->fetch_assoc()) {
        $status = $receipt['is_cancelled'] == 1 ?
                  '<span style="color: red; font-weight: bold;">CANCELLED</span>' :
                  '<span style="color: green; font-weight: bold;">ACTIVE</span>';

        echo "<tr>";
        echo "<td><strong>" . $receipt['receipt_id'] . "</strong></td>";
        echo "<td>" . htmlspecialchars($receipt['receipt_no']) . "</td>";
        echo "<td>" . htmlspecialchars($receipt['student_name'] ?? 'N/A') . "</td>";
        echo "<td>₹" . number_format($receipt['amount_paid'], 2) . "</td>";
        echo "<td>" . date('d-M-Y', strtotime($receipt['payment_date'])) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "<td>";
        echo "<a href='modules/fees/pdf_receipt.php?id=" . $receipt['receipt_id'] . "' target='_blank' class='btn' style='padding: 5px 10px; margin: 0;'>View PDF</a>";
        echo "</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
    echo "</div>";

    // Show next steps
    echo "<div class='box info'>";
    echo "<h2>🎯 Next Steps</h2>";
    echo "<p><strong>To view a receipt PDF:</strong></p>";
    echo "<ol>";
    echo "<li>Click the 'View PDF' button next to any receipt above</li>";
    echo "<li>A new tab will open with the printable receipt</li>";
    echo "<li>The print dialog will auto-open (you can close it to just view)</li>";
    echo "</ol>";
    echo "<p><strong>If you still see 'Receipt not found':</strong></p>";
    echo "<ol>";
    echo "<li>Make sure the receipt ID in the URL matches one from the table above</li>";
    echo "<li>Check that the student referenced by the receipt still exists</li>";
    echo "<li>Use the diagnostic tool: <a href='check_receipt.php?id=X'>check_receipt.php?id=X</a> (replace X with receipt ID)</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>Quick Links</h2>";
echo "<p>";
echo "<a href='modules/fees/receipts.php' class='btn'>View All Receipts</a> ";
echo "<a href='modules/fees/collect_complete.php' class='btn btn-success'>Collect New Fee</a> ";
echo "<a href='modules/settings/recycle_bin.php?type=fee_receipt' class='btn btn-danger'>Recycle Bin</a>";
echo "</p>";
?>
