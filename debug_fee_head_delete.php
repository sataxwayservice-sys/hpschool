<?php
/**
 * Debug Fee Head Deletion
 */
require_once 'config/config.php';
requireLogin();

$feeHeadId = isset($_GET['fee_head_id']) ? intval($_GET['fee_head_id']) : 0;

echo "<h2>Debug Fee Head Deletion</h2>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#0d6efd;color:white;} .success{color:green;} .error{color:red;}</style>";

if ($feeHeadId == 0) {
    echo "<p class='error'>Please provide fee_head_id in URL: ?fee_head_id=X</p>";
    exit;
}

// Get fee head data
$feeHead = fetchOne("SELECT * FROM fee_heads WHERE fee_head_id = ?", 'i', [$feeHeadId]);

if (!$feeHead) {
    echo "<p class='error'>Fee head ID $feeHeadId not found!</p>";
    exit;
}

echo "<h3>Fee Head Details:</h3>";
echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
foreach ($feeHead as $key => $value) {
    echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
}
echo "</table>";

// Check ALL assignments (active and inactive)
echo "<h3>Fee Structure Assignments:</h3>";
$allAssignments = fetchAll("SELECT * FROM fee_structure WHERE fee_head_id = ?", 'i', [$feeHeadId]);
echo "<p>Total assignments: <strong>" . count($allAssignments) . "</strong></p>";

if (count($allAssignments) > 0) {
    echo "<table>";
    echo "<tr><th>Structure ID</th><th>Student ID</th><th>Amount</th><th>Is Active</th><th>Effective From</th></tr>";
    foreach ($allAssignments as $assignment) {
        $activeClass = $assignment['is_active'] ? 'success' : 'error';
        echo "<tr>";
        echo "<td>{$assignment['structure_id']}</td>";
        echo "<td>{$assignment['student_id']}</td>";
        echo "<td>₹" . number_format($assignment['amount'], 2) . "</td>";
        echo "<td class='$activeClass'><strong>" . ($assignment['is_active'] ? 'ACTIVE' : 'INACTIVE') . "</strong></td>";
        echo "<td>{$assignment['effective_from']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>✓ No fee structure assignments found</p>";
}

// Check active assignments only
$activeAssignments = fetchAll("SELECT * FROM fee_structure WHERE fee_head_id = ? AND is_active = 1", 'i', [$feeHeadId]);
echo "<h3>Active Assignments Only:</h3>";
echo "<p><strong>" . count($activeAssignments) . "</strong> active assignments</p>";

// Check ALL receipts using this fee head (active and cancelled)
echo "<h3>Receipts Using This Fee Head:</h3>";
$allReceiptDetails = fetchAll("SELECT frd.*, fr.receipt_no, fr.is_cancelled, s.student_name
                               FROM fee_receipt_details frd
                               JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                               JOIN students s ON fr.student_id = s.student_id
                               WHERE frd.fee_head_id = ?", 'i', [$feeHeadId]);

// Count active vs cancelled
$activeReceipts = array_filter($allReceiptDetails, fn($r) => $r['is_cancelled'] == 0);
$cancelledReceipts = array_filter($allReceiptDetails, fn($r) => $r['is_cancelled'] == 1);

echo "<p>Total receipts: <strong>" . count($allReceiptDetails) . "</strong> (Active: <strong class='success'>" . count($activeReceipts) . "</strong>, Cancelled: <strong class='error'>" . count($cancelledReceipts) . "</strong>)</p>";

if (count($allReceiptDetails) > 0) {
    echo "<table>";
    echo "<tr><th>Receipt No</th><th>Student</th><th>Month/Year</th><th>Amount</th><th>Status</th></tr>";
    foreach ($allReceiptDetails as $detail) {
        $statusClass = $detail['is_cancelled'] ? 'error' : 'success';
        $statusText = $detail['is_cancelled'] ? 'CANCELLED' : 'ACTIVE';
        echo "<tr>";
        echo "<td>{$detail['receipt_no']}</td>";
        echo "<td>" . htmlspecialchars($detail['student_name']) . "</td>";
        echo "<td>{$detail['fee_month']} {$detail['fee_year']}</td>";
        echo "<td>₹" . number_format($detail['amount'], 2) . "</td>";
        echo "<td class='$statusClass'><strong>$statusText</strong></td>";
        echo "</tr>";
    }
    echo "</table>";

    if (count($activeReceipts) > 0) {
        echo "<p class='error'><strong>⚠️ Cannot delete:</strong> This fee head is used in <strong>" . count($activeReceipts) . " active receipt(s)</strong>. Deleting it would corrupt the accounting history.</p>";
    } else {
        echo "<p class='success'>✓ All receipts are cancelled - fee head can be deleted!</p>";
    }
} else {
    echo "<p class='success'>✓ No receipts using this fee head</p>";
}

// Check fee_ledger entries (pending/due fees)
echo "<h3>Fee Ledger Entries (Pending/Due Fees):</h3>";
$ledgerEntries = fetchAll("SELECT fl.*, s.student_name, s.roll_no
                           FROM fee_ledger fl
                           JOIN students s ON fl.student_id = s.student_id
                           WHERE fl.fee_head_id = ?", 'i', [$feeHeadId]);

echo "<p><strong>" . count($ledgerEntries) . "</strong> ledger entries (due balance records)</p>";

if (count($ledgerEntries) > 0) {
    $paidEntries = array_filter($ledgerEntries, fn($l) => !empty($l['receipt_id']));
    $unpaidEntries = array_filter($ledgerEntries, fn($l) => empty($l['receipt_id']));

    echo "<p>Paid: <strong class='success'>" . count($paidEntries) . "</strong>, Unpaid: <strong class='error'>" . count($unpaidEntries) . "</strong></p>";

    echo "<table>";
    echo "<tr><th>Student</th><th>Roll No</th><th>Month/Year</th><th>Amount Due</th><th>Status</th><th>Receipt ID</th></tr>";
    foreach ($ledgerEntries as $entry) {
        $isPaid = !empty($entry['receipt_id']);
        $statusClass = $isPaid ? 'success' : 'error';
        $statusText = $isPaid ? 'PAID' : 'UNPAID';

        echo "<tr>";
        echo "<td>" . htmlspecialchars($entry['student_name']) . "</td>";
        echo "<td>{$entry['roll_no']}</td>";
        echo "<td>{$entry['fee_month']} {$entry['fee_year']}</td>";
        echo "<td>₹" . number_format($entry['amount_due'], 2) . "</td>";
        echo "<td class='$statusClass'><strong>$statusText</strong></td>";
        echo "<td>" . ($isPaid ? "#{$entry['receipt_id']}" : "-") . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p class='error'><strong>⚠️ This fee head has " . count($ledgerEntries) . " ledger entries.</strong> These will be deleted automatically during fee head deletion.</p>";
} else {
    echo "<p class='success'>✓ No ledger entries for this fee head</p>";
}

// Check if fee head is in recycle bin
$inRecycleBin = fetchOne("SELECT * FROM deleted_items WHERE item_type = 'fee_head' AND item_id = ?", 'i', [$feeHeadId]);
echo "<h3>Recycle Bin Status:</h3>";
if ($inRecycleBin) {
    echo "<p class='success'>✓ Found in recycle bin!</p>";
    echo "<table>";
    echo "<tr><th>Deleted At</th><th>Deleted By</th><th>Reason</th></tr>";
    echo "<tr>";
    echo "<td>{$inRecycleBin['deleted_at']}</td>";
    echo "<td>User ID: {$inRecycleBin['deleted_by']}</td>";
    echo "<td>{$inRecycleBin['reason']}</td>";
    echo "</tr>";
    echo "</table>";
} else {
    echo "<p class='error'>✗ Not in recycle bin</p>";
}

// Check foreign key constraints
echo "<h3>Database Foreign Keys:</h3>";
$conn = getDbConnection();
$fkQuery = "SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'fee_heads'
AND TABLE_SCHEMA = DATABASE()";

$foreignKeys = $conn->query($fkQuery);
if ($foreignKeys && $foreignKeys->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Constraint</th><th>Table</th><th>Column</th></tr>";
    while ($fk = $foreignKeys->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
        echo "<td>{$fk['TABLE_NAME']}</td>";
        echo "<td>{$fk['COLUMN_NAME']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>✓ No foreign key constraints found</p>";
}

// Deletion recommendation
echo "<h3>Deletion Analysis:</h3>";
echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffc107;border-radius:5px;'>";

if (count($activeReceipts) > 0) {
    echo "<p class='error'><strong>❌ Cannot delete!</strong></p>";
    echo "<p><strong>Reason:</strong> This fee head has been used in <strong>" . count($activeReceipts) . " active receipt(s)</strong>.</p>";
    echo "<p>Deleting it would corrupt your financial records and receipts would show invalid data.</p>";
    echo "<p><strong>Options:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Recommended:</strong> Mark it as inactive instead (don't delete)</li>";
    echo "<li>Cancel all active receipts using this fee head first (not recommended)</li>";
    echo "</ol>";
    if (count($cancelledReceipts) > 0) {
        echo "<p><em>Note: There are " . count($cancelledReceipts) . " cancelled receipt(s) - these won't block deletion.</em></p>";
    }
} else if (count($activeAssignments) > 0) {
    echo "<p class='error'><strong>❌ Cannot delete!</strong></p>";
    echo "<p>This fee head has <strong>" . count($activeAssignments) . " active assignments</strong> to students.</p>";
    echo "<p><strong>Solution:</strong></p>";
    echo "<ol>";
    echo "<li>Deactivate or delete all active assignments first</li>";
    echo "<li>Then try deleting the fee head</li>";
    echo "</ol>";
} else if (count($allAssignments) > 0) {
    echo "<p class='success'><strong>✓ Can delete!</strong></p>";
    echo "<p>This fee head has " . count($allAssignments) . " inactive assignments (won't block deletion).</p>";
    if (count($cancelledReceipts) > 0) {
        echo "<p>Also has " . count($cancelledReceipts) . " cancelled receipt(s) (won't block deletion).</p>";
    }
    echo "<p><strong>Next step:</strong> Try deleting from Fee Heads Management page.</p>";
} else {
    echo "<p class='success'><strong>✓ Can delete!</strong></p>";
    echo "<p>This fee head has no assignments at all.</p>";
    if (count($cancelledReceipts) > 0) {
        echo "<p>Has " . count($cancelledReceipts) . " cancelled receipt(s) (won't block deletion).</p>";
    }
    echo "<p><strong>Next step:</strong> Try deleting from Fee Heads Management page.</p>";
}

echo "</div>";

// Test delete button
if (count($activeAssignments) == 0 && count($activeReceipts) == 0) {
    echo "<hr>";
    echo "<h3>Test Deletion:</h3>";
    if (isset($_POST['test_delete'])) {
        echo "<div style='background:#d1ecf1;padding:15px;border:1px solid #0dcaf0;border-radius:5px;margin:20px 0;'>";
        echo "<h4>Testing deletion...</h4>";

        $testResult = softDeleteFeeHead($feeHeadId, "Test deletion from debug page");

        if ($testResult) {
            echo "<p class='success'><strong>✓ Deletion successful!</strong></p>";
            echo "<p>Reloading to verify...</p>";
            echo "<meta http-equiv='refresh' content='2'>";
        } else {
            echo "<p class='error'><strong>✗ Deletion failed!</strong></p>";
            echo "<p>Check PHP error logs for details.</p>";
        }
        echo "</div>";
    } else {
        echo "<form method='POST'>";
        echo "<button type='submit' name='test_delete' style='padding:10px 20px;background:#dc3545;color:white;border:none;border-radius:5px;cursor:pointer;'>Test Delete Fee Head</button>";
        echo "<p><small>This will attempt to delete the fee head and save to recycle bin.</small></p>";
        echo "</form>";
    }
}

echo "<hr>";
echo "<p><a href='modules/settings/fee_heads.php' style='padding:10px 20px;background:#0d6efd;color:white;text-decoration:none;border-radius:5px;'>← Back to Fee Heads</a></p>";
?>
