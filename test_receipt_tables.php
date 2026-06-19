<?php
/**
 * Test and Create Receipt Books and Related Tables
 */

require_once 'config/config.php';

echo "<h2>Receipt System Database Check</h2>";
echo "<p>This page will check for required tables and columns for the fee receipt system.</p>";
echo "<hr>";

// Test 1: Check if receipt_books table exists
echo "<h3>Step 1: Check if receipt_books table exists</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'receipt_books'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ receipt_books table EXISTS";
    echo "</div>";

    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $structure = fetchAll("DESCRIBE receipt_books");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Show existing books
    echo "<h4>Existing Receipt Books:</h4>";
    $existing = fetchAll("SELECT * FROM receipt_books");
    if (count($existing) > 0) {
        echo "<p>Found " . count($existing) . " receipt book(s)</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Book Name</th><th>Prefix</th><th>Active</th></tr>";
        foreach ($existing as $book) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($book['book_id']) . "</td>";
            echo "<td>" . htmlspecialchars($book['book_name']) . "</td>";
            echo "<td>" . htmlspecialchars($book['prefix']) . "</td>";
            echo "<td>" . ($book['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No receipt books in the table yet.</p>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ receipt_books table DOES NOT EXIST";
    echo "</div>";
}

echo "<hr>";

// Test 2: Check fee_receipts table columns
echo "<h3>Step 2: Check fee_receipts table columns</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'fee_receipts'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ fee_receipts table EXISTS";
    echo "</div>";

    echo "<h4>Table Structure:</h4>";
    $structure = fetchAll("DESCRIBE fee_receipts");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

    $hasReceiptBookId = false;
    $hasChargeAmount = false;
    $hasBankName = false;
    $hasChequeDate = false;

    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";

        if ($col['Field'] == 'receipt_book_id') $hasReceiptBookId = true;
        if ($col['Field'] == 'charge_amount') $hasChargeAmount = true;
        if ($col['Field'] == 'bank_name') $hasBankName = true;
        if ($col['Field'] == 'cheque_date') $hasChequeDate = true;
    }
    echo "</table>";

    // Check for missing columns
    $missingCols = [];
    if (!$hasReceiptBookId) $missingCols[] = 'receipt_book_id';
    if (!$hasChargeAmount) $missingCols[] = 'charge_amount';
    if (!$hasBankName) $missingCols[] = 'bank_name';
    if (!$hasChequeDate) $missingCols[] = 'cheque_date';

    if (count($missingCols) > 0) {
        echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid orange; margin-top: 10px;'>";
        echo "⚠ Missing columns: " . implode(', ', $missingCols);
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green; margin-top: 10px;'>";
        echo "✓ All required columns exist";
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ fee_receipts table DOES NOT EXIST";
    echo "</div>";
}

echo "<hr>";
echo "<h3>SQL to Create/Fix Tables:</h3>";

// Check if we need to provide SQL
$tableCheck1 = fetchAll("SHOW TABLES LIKE 'receipt_books'");
$tableCheck2 = fetchAll("SHOW TABLES LIKE 'fee_receipts'");

if (count($tableCheck1) == 0 || count($tableCheck2) == 0 || (isset($missingCols) && count($missingCols) > 0)) {
    echo "<p><strong>Copy and run this SQL in phpMyAdmin:</strong></p>";
    echo "<textarea style='width: 100%; height: 400px; font-family: monospace;'>";

    // Create receipt_books table if needed
    if (count($tableCheck1) == 0) {
        echo "-- Create receipt_books table\n";
        echo "CREATE TABLE IF NOT EXISTS `receipt_books` (\n";
        echo "  `book_id` int(11) NOT NULL AUTO_INCREMENT,\n";
        echo "  `book_name` varchar(100) NOT NULL,\n";
        echo "  `prefix` varchar(20) DEFAULT NULL,\n";
        echo "  `is_active` tinyint(1) DEFAULT 1,\n";
        echo "  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
        echo "  PRIMARY KEY (`book_id`)\n";
        echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";

        echo "-- Insert default receipt book\n";
        echo "INSERT INTO `receipt_books` (`book_name`, `prefix`, `is_active`) VALUES ('DEFAULT', 'FEE', 1);\n\n";
    }

    // Add missing columns to fee_receipts if needed
    if (isset($missingCols) && count($missingCols) > 0) {
        echo "-- Add missing columns to fee_receipts table\n";
        if (!$hasReceiptBookId) {
            echo "ALTER TABLE `fee_receipts` ADD COLUMN `receipt_book_id` int(11) DEFAULT NULL AFTER `receipt_id`;\n";
        }
        if (!$hasChargeAmount) {
            echo "ALTER TABLE `fee_receipts` ADD COLUMN `charge_amount` decimal(10,2) DEFAULT 0.00 AFTER `amount_paid`;\n";
        }
        if (!$hasBankName) {
            echo "ALTER TABLE `fee_receipts` ADD COLUMN `bank_name` varchar(100) DEFAULT NULL AFTER `transaction_id`;\n";
        }
        if (!$hasChequeDate) {
            echo "ALTER TABLE `fee_receipts` ADD COLUMN `cheque_date` date DEFAULT NULL AFTER `bank_name`;\n";
        }
    }

    echo "</textarea>";
} else {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ All tables and columns are correctly set up! No SQL needed.";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If SQL is shown above, copy it and run it in phpMyAdmin</li>";
echo "<li>After running the SQL, refresh this page to verify</li>";
echo "<li>Once everything is green, visit <a href='modules/fees/collect_receipt.php'>Fee Receipt Collection</a></li>";
echo "</ol>";
?>
