<?php
/**
 * One-time script to add discount support
 */
require_once 'config/config.php';
requireLogin();

$conn = getDbConnection();

echo "<h2>Adding Discount Support...</h2>";
echo "<style>body{font-family:Arial;padding:20px;}</style>";

// Check if discount column exists in fee_receipt_details
$checkQuery = "SHOW COLUMNS FROM fee_receipt_details LIKE 'discount'";
$result = $conn->query($checkQuery);

if ($result->num_rows == 0) {
    echo "<p>Adding discount column to fee_receipt_details...</p>";
    $alterQuery = "ALTER TABLE fee_receipt_details
                   ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Discount amount' AFTER amount,
                   ADD COLUMN discount_reason VARCHAR(255) DEFAULT NULL COMMENT 'Reason for discount' AFTER discount";
    $conn->query($alterQuery);
    echo "<p style='color:green;'>✓ Added discount and discount_reason columns</p>";
} else {
    echo "<p style='color:blue;'>✓ Discount columns already exist</p>";
}

echo "<hr>";
echo "<p><strong>Discount Support Added Successfully!</strong></p>";
echo "<p>You can now give discounts on individual fees during collection</p>";
echo "<hr>";
echo "<p><a href='modules/fees/collect_complete.php' style='padding:10px 20px;background:#0d6efd;color:white;text-decoration:none;border-radius:5px;'>Go to Fee Collection</a></p>";
?>
