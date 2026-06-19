<?php
/**
 * List All Receipts - Simple Diagnostic
 * Shows all receipt IDs in the database
 */

require_once 'config/config.php';
requireLogin();

$conn = getDbConnection();

echo "<h1>All Receipts in Database</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th { background: #0d6efd; color: white; padding: 10px; text-align: left; }
    td { padding: 8px; border: 1px solid #ddd; }
    tr:nth-child(even) { background: #f8f9fa; }
    .cancelled { background: #f8d7da !important; }
    .actions a { margin: 0 5px; padding: 5px 10px; text-decoration: none;
                 background: #0d6efd; color: white; border-radius: 3px; }
    .actions a:hover { background: #0a58ca; }
    .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #0c5460; margin: 20px 0; }
</style>";

// Count total receipts
$totalCount = $conn->query("SELECT COUNT(*) as count FROM fee_receipts")->fetch_assoc()['count'];
$activeCount = $conn->query("SELECT COUNT(*) as count FROM fee_receipts WHERE is_cancelled = 0")->fetch_assoc()['count'];
$cancelledCount = $conn->query("SELECT COUNT(*) as count FROM fee_receipts WHERE is_cancelled = 1")->fetch_assoc()['count'];

echo "<div class='info'>";
echo "<strong>Database Summary:</strong><br>";
echo "📊 Total Receipts: $totalCount<br>";
echo "✅ Active Receipts: $activeCount<br>";
echo "❌ Cancelled Receipts: $cancelledCount";
echo "</div>";

// Get all receipts
$query = "SELECT
            fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
            fr.payment_mode, fr.payment_date, fr.is_cancelled, fr.created_at,
            s.student_name, s.admission_no
          FROM fee_receipts fr
          LEFT JOIN students s ON fr.student_id = s.student_id
          ORDER BY fr.receipt_id DESC";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Receipt ID</th>";
    echo "<th>Receipt No</th>";
    echo "<th>Student</th>";
    echo "<th>Admission No</th>";
    echo "<th>Amount</th>";
    echo "<th>Payment Date</th>";
    echo "<th>Payment Mode</th>";
    echo "<th>Status</th>";
    echo "<th>Actions</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    while ($row = $result->fetch_assoc()) {
        $isCancelled = $row['is_cancelled'] == 1;
        $rowClass = $isCancelled ? 'class="cancelled"' : '';

        echo "<tr $rowClass>";
        echo "<td><strong>" . $row['receipt_id'] . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['receipt_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['admission_no'] ?? 'N/A') . "</td>";
        echo "<td>₹" . number_format($row['amount_paid'], 2) . "</td>";
        echo "<td>" . date('d-M-Y', strtotime($row['payment_date'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['payment_mode']) . "</td>";
        echo "<td>" . ($isCancelled ? '<span style="color: red; font-weight: bold;">CANCELLED</span>' : '<span style="color: green;">Active</span>') . "</td>";
        echo "<td class='actions'>";
        echo "<a href='modules/fees/pdf_receipt.php?id=" . $row['receipt_id'] . "' target='_blank'>View PDF</a> ";
        echo "<a href='check_receipt.php?id=" . $row['receipt_id'] . "'>Diagnose</a>";
        echo "</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
} else {
    echo "<div class='info' style='background: #fff3cd; border-left-color: #856404;'>";
    echo "<strong>⚠️ No receipts found in database!</strong><br>";
    echo "It appears your database has no fee receipts yet. You need to collect fees first.";
    echo "</div>";

    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>Go to <a href='modules/fees/collect_complete.php'>Fees → Collect Fee</a> to create a receipt</li>";
    echo "<li>Or check if you need to restore receipts from <a href='modules/settings/recycle_bin.php?type=fee_receipt'>Recycle Bin</a></li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><strong>Quick Links:</strong></p>";
echo "<p>";
echo "<a href='modules/fees/collect_complete.php' style='padding: 10px 20px; background: #198754; color: white; text-decoration: none; border-radius: 5px;'>Collect New Fee</a> ";
echo "<a href='modules/fees/receipts.php' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>View Receipts List</a> ";
echo "<a href='modules/settings/recycle_bin.php?type=fee_receipt' style='padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>Recycle Bin</a>";
echo "</p>";
?>
