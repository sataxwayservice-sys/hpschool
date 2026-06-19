<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

echo "<h1>Database Column Check</h1>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;margin:15px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#0d6efd;color:white;}</style>";

$conn = getDbConnection();

$tables = ['fee_receipts', 'students', 'classes', 'sections', 'users'];

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";

    $result = $conn->query("SHOW COLUMNS FROM $table");

    if ($result) {
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . $row['Field'] . "</strong></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p style='color:red;'>Table doesn't exist or error: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h2>Now testing a corrected query...</h2>";

$receiptId = 8;

// Build query using only columns that exist
$query = "SELECT
    fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
    fr.payment_mode, fr.payment_date, fr.remarks, fr.is_cancelled,
    s.student_name, s.admission_no
FROM fee_receipts fr
LEFT JOIN students s ON fr.student_id = s.student_id
WHERE fr.receipt_id = ?";

echo "<p><strong>Simplified Query:</strong></p>";
echo "<pre>" . htmlspecialchars($query) . "</pre>";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "<p style='color:red;'>❌ Query preparation failed: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green;'>✅ Query preparation successful!</p>";

    $stmt->bind_param('i', $receiptId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<p style='color:green;'>✅ Query returned data!</p>";
        $row = $result->fetch_assoc();
        echo "<pre>" . print_r($row, true) . "</pre>";
    } else {
        echo "<p style='color:red;'>❌ Query returned 0 rows</p>";
    }
}
?>
