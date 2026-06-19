<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

echo "<h1>Receipt Test - Simple</h1>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;} th{background:#0d6efd;color:white;}</style>";

$conn = getDbConnection();

// Get all receipts
$query = "SELECT receipt_id, receipt_no, student_id, amount_paid, payment_date, is_cancelled FROM fee_receipts ORDER BY receipt_id DESC LIMIT 10";
$result = $conn->query($query);

if (!$result) {
    die("Query Error: " . $conn->error);
}

echo "<p>Found " . $result->num_rows . " receipts</p>";

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Receipt No</th><th>Amount</th><th>Date</th><th>Status</th><th>Test Links</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $status = $row['is_cancelled'] ? 'CANCELLED' : 'Active';
        $statusColor = $row['is_cancelled'] ? 'red' : 'green';

        echo "<tr>";
        echo "<td>" . $row['receipt_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['receipt_no']) . "</td>";
        echo "<td>₹" . number_format($row['amount_paid'], 2) . "</td>";
        echo "<td>" . date('d-M-Y', strtotime($row['payment_date'])) . "</td>";
        echo "<td style='color:$statusColor'><b>$status</b></td>";
        echo "<td>";
        echo "<a href='modules/fees/pdf_receipt.php?id=" . $row['receipt_id'] . "' target='_blank' style='padding:5px 10px;background:#28a745;color:white;text-decoration:none;border-radius:3px;margin:2px;display:inline-block;'>View</a> ";
        echo "<a href='modules/fees/pdf_receipt.php?id=" . $row['receipt_id'] . "&print=auto' target='_blank' style='padding:5px 10px;background:#0d6efd;color:white;text-decoration:none;border-radius:3px;margin:2px;display:inline-block;'>Print</a>";
        echo "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p style='color:red;'><b>No receipts found!</b></p>";
    echo "<p>You need to collect fees first: <a href='modules/fees/collect_complete.php'>Collect Fee</a></p>";
}

echo "<hr>";
echo "<h2>Direct Test Links</h2>";
echo "<p>If you see receipts above, click 'View' to test. If nothing works:</p>";
echo "<ol>";
echo "<li>Check browser console (F12) for JavaScript errors</li>";
echo "<li>Try in different browser or incognito mode</li>";
echo "<li>Check if popup blocker is enabled</li>";
echo "</ol>";
?>
