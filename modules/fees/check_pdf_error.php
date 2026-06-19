<?php
/**
 * Check PDF Errors
 * Shows what's preventing the PDF from displaying
 */

require_once '../../config/config.php';
requireLogin();

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : 4;

echo "<html><head><title>PDF Error Check</title>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; }
    .error { background: #f8d7da; border-left: 4px solid #dc3545; }
    .success { background: #d4edda; border-left: 4px solid #28a745; }
    .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
    .btn { padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
    pre { background: #f8f9fa; padding: 15px; overflow-x: auto; border-radius: 4px; }
</style></head><body>";

echo "<h1>PDF Receipt Error Diagnostic - ID: $receiptId</h1>";

// Check 1: Error log
echo "<div class='box'>";
echo "<h2>1. Check Error Logs</h2>";

$logFile = '../../logs/pdf_errors.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    if (!empty($logContent)) {
        echo "<div class='warning'>";
        echo "<p><strong>⚠️ Errors found in log:</strong></p>";
        echo "<pre>" . htmlspecialchars(substr($logContent, -2000)) . "</pre>";
        echo "</div>";
    } else {
        echo "<div class='success'><p>✅ No errors in log file</p></div>";
    }
} else {
    echo "<div class='success'><p>✅ No error log file (no errors recorded)</p></div>";
}
echo "</div>";

// Check 2: Try loading the receipt
echo "<div class='box'>";
echo "<h2>2. Test Receipt Loading</h2>";

try {
    $receipt = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);

    if ($receipt) {
        echo "<div class='success'>";
        echo "<p>✅ Receipt ID $receiptId found in database</p>";
        echo "<p><strong>Receipt No:</strong> " . $receipt['receipt_no'] . "</p>";
        echo "<p><strong>Student ID:</strong> " . $receipt['student_id'] . "</p>";
        echo "<p><strong>Amount:</strong> ₹" . number_format($receipt['amount_paid'], 2) . "</p>";
        echo "</div>";

        // Check if student exists
        $student = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$receipt['student_id']]);
        if ($student) {
            echo "<div class='success'>";
            echo "<p>✅ Student found: " . $student['student_name'] . "</p>";
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<p>⚠️ Student not found (ID: " . $receipt['student_id'] . ") - PDF will show NULL values</p>";
            echo "</div>";
        }
    } else {
        echo "<div class='error'>";
        echo "<p>❌ Receipt ID $receiptId NOT found in database</p>";
        echo "<p>This receipt does not exist. Check the database or recycle bin.</p>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<p>❌ Error loading receipt: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</div>";

// Check 3: Browser info
echo "<div class='box'>";
echo "<h2>3. Browser Information</h2>";
echo "<p>Sometimes browser extensions or settings can prevent pages from displaying.</p>";
echo "<p><strong>Your browser:</strong> " . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "</p>";
echo "<p><strong>Suggestions:</strong></p>";
echo "<ul>";
echo "<li>Try opening the PDF in a different browser (Chrome, Firefox, Edge)</li>";
echo "<li>Try in incognito/private mode (disables extensions)</li>";
echo "<li>Check browser console for errors (Press F12, go to Console tab)</li>";
echo "<li>Disable ad blockers or script blockers temporarily</li>";
echo "</ul>";
echo "</div>";

// Check 4: PHP configuration
echo "<div class='box'>";
echo "<h2>4. PHP Configuration</h2>";
$displayErrors = ini_get('display_errors');
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');

echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr><th style='background: #f8f9fa; padding: 10px; text-align: left; border: 1px solid #ddd;'>Setting</th><th style='background: #f8f9fa; padding: 10px; text-align: left; border: 1px solid #ddd;'>Value</th></tr>";
echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'>Display Errors</td><td style='padding: 8px; border: 1px solid #ddd;'>$displayErrors</td></tr>";
echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'>Memory Limit</td><td style='padding: 8px; border: 1px solid #ddd;'>$memoryLimit</td></tr>";
echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'>Max Execution Time</td><td style='padding: 8px; border: 1px solid #ddd;'>{$maxExecutionTime}s</td></tr>";
echo "</table>";
echo "</div>";

// Action buttons
echo "<div class='box'>";
echo "<h2>5. Try These Tests</h2>";
echo "<p>";
echo "<a href='test_pdf.php?id=$receiptId' class='btn'>Run Full Diagnostic</a> ";
echo "<a href='pdf_receipt.php?id=$receiptId' class='btn' target='_blank'>Try PDF Receipt</a> ";
echo "<a href='../../check_database.php' class='btn'>Check Database</a> ";
echo "<a href='receipts.php' class='btn'>View All Receipts</a>";
echo "</p>";
echo "</div>";

// Check 5: Screen output test
echo "<div class='box'>";
echo "<h2>6. Simple Output Test</h2>";
echo "<p>If you can see this text, PHP is working correctly and the browser can display content.</p>";
echo "<p>If the PDF page shows completely blank, it might be:</p>";
echo "<ul>";
echo "<li><strong>PHP Fatal Error:</strong> Check the error log above</li>";
echo "<li><strong>Browser Issue:</strong> Try different browser or incognito mode</li>";
echo "<li><strong>JavaScript Error:</strong> The window.print() might be causing issues - check browser console (F12)</li>";
echo "</ul>";

echo "<h3>Quick JavaScript Test:</h3>";
echo "<button onclick='alert(\"JavaScript is working!\")' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test JavaScript</button>";
echo "</div>";

echo "</body></html>";
?>
