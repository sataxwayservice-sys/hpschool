<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

echo "<h1>Receipt Details Diagnostic</h1>";
echo "<style>
body{font-family:Arial;padding:20px;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #ddd;padding:12px;text-align:left;}
th{background:#0d6efd;color:white;}
tr:hover{background:#f5f5f5;}
.cancelled{background:#ffebee !important;}
.active{background:#e8f5e9 !important;}
.alert{padding:15px;margin:20px 0;border-radius:5px;}
.alert-info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}
.alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
</style>";

// Get all receipts
$receipts = fetchAll("SELECT fr.*, s.student_name, s.admission_no
                      FROM fee_receipts fr
                      JOIN students s ON fr.student_id = s.student_id
                      ORDER BY fr.receipt_id DESC");

echo "<div class='alert alert-info'>";
echo "<h3>Total Receipts in Database: " . count($receipts) . "</h3>";
echo "</div>";

echo "<h2>All Receipts with Details</h2>";
echo "<table>";
echo "<tr>
        <th>Receipt No</th>
        <th>Student</th>
        <th>Date</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Fee Details</th>
        <th>Action</th>
      </tr>";

$activeCount = 0;
$cancelledCount = 0;

foreach ($receipts as $receipt) {
    $receiptId = $receipt['receipt_id'];

    // Get fee details for this receipt
    $details = fetchAll("SELECT frd.*, fh.fee_head_name
                        FROM fee_receipt_details frd
                        JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                        WHERE frd.receipt_id = ?
                        ORDER BY frd.detail_id", 'i', [$receiptId]);

    $rowClass = $receipt['is_cancelled'] ? 'cancelled' : 'active';
    if ($receipt['is_cancelled']) {
        $cancelledCount++;
    } else {
        $activeCount++;
    }

    echo "<tr class='$rowClass'>";
    echo "<td><strong>" . htmlspecialchars($receipt['receipt_no']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($receipt['student_name']) . "<br><small>" . htmlspecialchars($receipt['admission_no']) . "</small></td>";
    echo "<td>" . date('d-M-Y', strtotime($receipt['payment_date'])) . "</td>";
    echo "<td><strong>₹" . number_format($receipt['amount_paid'], 2) . "</strong></td>";

    echo "<td>";
    if ($receipt['is_cancelled']) {
        echo "<span style='color:red;font-weight:bold;'>❌ CANCELLED</span>";
    } else {
        echo "<span style='color:green;font-weight:bold;'>✓ ACTIVE</span>";
    }
    echo "</td>";

    echo "<td>";
    if (count($details) > 0) {
        echo "<strong>" . count($details) . " fee item(s):</strong><br>";
        echo "<ul style='margin:5px 0;padding-left:20px;'>";
        foreach ($details as $detail) {
            echo "<li>" . htmlspecialchars($detail['fee_head_name']);
            if ($detail['fee_month']) {
                echo " - " . htmlspecialchars($detail['fee_month']);
            }
            echo " (₹" . number_format($detail['amount'], 2) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<span style='color:red;'>⚠️ NO FEE DETAILS!</span>";
    }
    echo "</td>";

    echo "<td>";
    if ($receipt['is_cancelled']) {
        echo "<a href='modules/fees/permanent_delete_receipt.php?id=$receiptId' style='color:red;text-decoration:none;'>
                🗑️ Delete Permanently
              </a>";
    } else {
        echo "<a href='modules/fees/delete_receipt.php?id=$receiptId' style='color:orange;text-decoration:none;'>
                ⚠️ Cancel Receipt
              </a>";
    }
    echo "</td>";

    echo "</tr>";
}

echo "</table>";

echo "<div class='alert alert-info'>";
echo "<h3>Summary:</h3>";
echo "<p><span style='color:green;font-weight:bold;'>✓ Active Receipts: $activeCount</span></p>";
echo "<p><span style='color:red;font-weight:bold;'>❌ Cancelled Receipts: $cancelledCount</span></p>";
echo "</div>";

if ($activeCount > 0) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>⚠️ You have $activeCount ACTIVE receipts!</h4>";
    echo "<p>These receipts are NOT cancelled, which is why they appear in your receipts list.</p>";
    echo "<p><strong>To remove them:</strong></p>";
    echo "<ol>";
    echo "<li><strong>First:</strong> Click 'Cancel Receipt' to mark each receipt as cancelled</li>";
    echo "<li><strong>Then:</strong> Click 'Delete Permanently' to remove them from database</li>";
    echo "</ol>";
    echo "<p><strong>OR use bulk operations:</strong></p>";
    echo "<ul>";
    echo "<li>Go to <a href='modules/fees/receipts.php'>Fee Receipts page</a></li>";
    echo "<li>Cancel each receipt individually (click trash icon)</li>";
    echo "<li>Then click the red 'Delete All Cancelled' button at the top</li>";
    echo "</ul>";
    echo "</div>";
}

if ($cancelledCount > 0) {
    echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h4>🗑️ You have $cancelledCount cancelled receipts</h4>";
    echo "<p>These are marked as cancelled but still in the database. You can permanently delete them.</p>";
    echo "<p><a href='modules/fees/bulk_delete_cancelled.php' style='padding:10px 20px;background:#dc3545;color:white;text-decoration:none;border-radius:5px;'>
            Delete All Cancelled Receipts
          </a></p>";
    echo "</div>";
}

echo "<hr>";
echo "<p>
        <a href='modules/fees/receipts.php' style='padding:10px 20px;background:#0d6efd;color:white;text-decoration:none;border-radius:5px;'>
            → Go to Fee Receipts Page
        </a>
      </p>";
?>
