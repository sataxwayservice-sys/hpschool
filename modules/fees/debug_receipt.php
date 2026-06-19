<?php
/**
 * Debug Receipt Viewer
 * Shows raw receipt data to identify issues
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'view');

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get receipt ID
$receiptId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0);

echo "<html><head><title>Receipt Debug</title>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .section h2 { color: #0d6efd; margin-top: 0; }
    .success { background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
    .error { background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0d6efd; color: white; padding: 10px; text-align: left; }
    td { padding: 8px; border-bottom: 1px solid #ddd; }
    .btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none;
           background: #0d6efd; color: white; border-radius: 5px; }
</style></head><body>";

echo "<h1>Receipt Debug - ID: $receiptId</h1>";
echo "<p><a href='pdf_receipt.php?id=$receiptId' class='btn'>Try PDF View</a> ";
echo "<a href='receipts.php' class='btn'>Back to Receipts</a></p>";

if ($receiptId <= 0) {
    echo "<div class='error'>Invalid receipt ID!</div>";
    exit;
}

// Step 1: Check receipt exists
echo "<div class='section'>";
echo "<h2>Step 1: Check Receipt Data</h2>";

try {
    $receipt = fetchOne("SELECT
                            fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
                            fr.payment_mode, fr.payment_date, fr.remarks, fr.created_at, fr.is_cancelled,
                            s.student_name, s.admission_no, s.father_name, s.mother_name,
                            s.contact_no, s.address, s.roll_no,
                            c.class_name, sec.section_name,
                            u.full_name as received_by
                         FROM fee_receipts fr
                         LEFT JOIN students s ON fr.student_id = s.student_id
                         LEFT JOIN classes c ON s.class_id = c.class_id
                         LEFT JOIN sections sec ON s.section_id = sec.section_id
                         LEFT JOIN users u ON fr.collected_by = u.user_id
                         WHERE fr.receipt_id = ?", 'i', [$receiptId]);

    if ($receipt) {
        echo "<div class='success'>✅ Receipt found!</div>";
        echo "<table>";
        foreach ($receipt as $key => $value) {
            echo "<tr><th width='30%'>$key</th><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>❌ Receipt not found in database!</div>";
        exit;
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error fetching receipt: " . $e->getMessage() . "</div>";
    exit;
}

echo "</div>";

// Step 2: Check school settings
echo "<div class='section'>";
echo "<h2>Step 2: Check School Settings</h2>";

try {
    $settings = getSchoolSettings();

    if ($settings) {
        echo "<div class='success'>✅ School settings found!</div>";
        echo "<table>";
        echo "<tr><th width='30%'>School Name</th><td>" . htmlspecialchars($settings['school_name'] ?? 'Not set') . "</td></tr>";
        echo "<tr><th>School Address</th><td>" . htmlspecialchars($settings['school_address'] ?? 'Not set') . "</td></tr>";
        echo "<tr><th>Contact No</th><td>" . htmlspecialchars($settings['school_phone'] ?? ($settings['contact_no'] ?? 'Not set')) . "</td></tr>";
        echo "<tr><th>Email</th><td>" . htmlspecialchars($settings['school_email'] ?? ($settings['email'] ?? 'Not set')) . "</td></tr>";
        echo "<tr><th>Currency Symbol</th><td>" . htmlspecialchars($settings['currency_symbol'] ?? '₹') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<div class='error'>⚠️ School settings not found! This might cause issues.</div>";
        echo "<p>The PDF page expects school settings. You may need to configure school settings first.</p>";
        $settings = ['school_name' => 'School Name', 'school_address' => 'Address', 'school_phone' => 'N/A', 'school_email' => 'N/A', 'currency_symbol' => '₹'];
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error fetching school settings: " . $e->getMessage() . "</div>";
    $settings = ['school_name' => 'School Name', 'school_address' => 'Address', 'school_phone' => 'N/A', 'school_email' => 'N/A', 'currency_symbol' => '₹'];
}

echo "</div>";

// Step 3: Check fee breakdown
echo "<div class='section'>";
echo "<h2>Step 3: Check Fee Breakdown</h2>";

try {
    $feeItems = fetchAll("SELECT
                            fh.fee_head_name, fh.fee_type, frd.amount
                          FROM fee_receipt_details frd
                          JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                          WHERE frd.receipt_id = ?
                          ORDER BY fh.display_order", 'i', [$receiptId]);

    if (!empty($feeItems)) {
        echo "<div class='success'>✅ Fee breakdown found (" . count($feeItems) . " items)</div>";
        echo "<table>";
        echo "<tr><th>Fee Head</th><th>Fee Type</th><th>Amount</th></tr>";
        foreach ($feeItems as $item) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['fee_head_name']) . "</td>";
            echo "<td>" . htmlspecialchars($item['fee_type']) . "</td>";
            echo "<td>₹" . number_format($item['amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>⚠️ No fee breakdown found! This might cause issues.</div>";
        echo "<p>The receipt has no associated fee details in the fee_receipt_details table.</p>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error fetching fee items: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Step 4: Test amount to words conversion
echo "<div class='section'>";
echo "<h2>Step 4: Test Amount Conversion</h2>";

try {
    // Copy the convertToWords function here for testing
    function convertToWords($number) {
        $ones = array(
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen'
        );

        $tens = array(
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        );

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            return $tens[intval($number / 10)] . ' ' . $ones[$number % 10];
        }

        if ($number < 1000) {
            return $ones[intval($number / 100)] . ' Hundred ' . convertToWords($number % 100);
        }

        if ($number < 100000) {
            return convertToWords(intval($number / 1000)) . ' Thousand ' . convertToWords($number % 1000);
        }

        if ($number < 10000000) {
            return convertToWords(intval($number / 100000)) . ' Lakh ' . convertToWords($number % 100000);
        }

        return convertToWords(intval($number / 10000000)) . ' Crore ' . convertToWords($number % 10000000);
    }

    $amount = intval($receipt['amount_paid']);
    $amountInWords = convertToWords($amount) . ' Only';

    echo "<div class='success'>✅ Amount conversion successful!</div>";
    echo "<p><strong>Amount:</strong> ₹" . number_format($receipt['amount_paid'], 2) . "</p>";
    echo "<p><strong>In Words:</strong> $amountInWords</p>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error converting amount: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Step 5: Summary
echo "<div class='section'>";
echo "<h2>Step 5: Summary & Next Steps</h2>";

$issues = [];

if (!$settings || empty($settings['school_name'])) {
    $issues[] = "School settings are missing or incomplete";
}

if (empty($feeItems)) {
    $issues[] = "No fee breakdown details found";
}

if ($receipt['is_cancelled'] == 1) {
    echo "<div class='error'>⚠️ This receipt is CANCELLED</div>";
}

if (empty($issues)) {
    echo "<div class='success'>";
    echo "<h3>✅ All checks passed!</h3>";
    echo "<p>The receipt data looks good. The PDF should work.</p>";
    echo "<p><a href='pdf_receipt.php?id=$receiptId' target='_blank' class='btn'>View PDF Receipt</a></p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ Issues found:</h3>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
    echo "<h3>Solutions:</h3>";
    echo "<ul>";
    if (!$settings || empty($settings['school_name'])) {
        echo "<li>Go to <a href='../settings/school.php'>Settings → School Settings</a> and configure your school details</li>";
    }
    if (empty($feeItems)) {
        echo "<li>This receipt may have been created incorrectly. Check the fee_receipt_details table in your database.</li>";
    }
    echo "</ul>";
    echo "<p>Despite these issues, you can still try viewing the PDF (it will show 'N/A' for missing data):</p>";
    echo "<p><a href='pdf_receipt.php?id=$receiptId' target='_blank' class='btn'>Try Viewing PDF Anyway</a></p>";
    echo "</div>";
}

echo "</div>";

echo "</body></html>";
?>
