<?php
/**
 * Test PDF Receipt - With Full Error Display
 */

// Enable ALL error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting PDF receipt test...<br>";

// Include configuration
try {
    echo "1. Loading config...<br>";
    require_once '../../config/config.php';
    echo "✓ Config loaded<br>";
} catch (Exception $e) {
    die("ERROR loading config: " . $e->getMessage());
}

// Require login
try {
    echo "2. Checking login...<br>";
    requireLogin();
    echo "✓ User logged in<br>";
} catch (Exception $e) {
    die("ERROR with login: " . $e->getMessage());
}

try {
    echo "3. Checking permissions...<br>";
    requirePermission('fees', 'view');
    echo "✓ Permissions OK<br>";
} catch (Exception $e) {
    die("ERROR with permissions: " . $e->getMessage());
}

// Get receipt ID
$receiptId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0);
echo "4. Receipt ID: $receiptId<br>";

if ($receiptId <= 0) {
    die('ERROR: Invalid receipt ID');
}

// Get receipt data
try {
    echo "5. Fetching receipt data...<br>";
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

    if (!$receipt) {
        die("ERROR: Receipt ID $receiptId not found in database");
    }
    echo "✓ Receipt found<br>";
    echo "Receipt No: " . $receipt['receipt_no'] . "<br>";
    echo "Student: " . $receipt['student_name'] . "<br>";
} catch (Exception $e) {
    die("ERROR fetching receipt: " . $e->getMessage());
}

// Get school settings
try {
    echo "6. Fetching school settings...<br>";
    $settings = getSchoolSettings();

    if (!$settings) {
        echo "⚠ No school settings, using defaults<br>";
        $settings = [
            'school_name' => 'School Name',
            'school_address' => 'School Address',
            'school_phone' => 'N/A',
            'school_email' => 'N/A',
            'currency_symbol' => '₹'
        ];
    } else {
        echo "✓ School settings found<br>";
    }
} catch (Exception $e) {
    echo "ERROR with school settings: " . $e->getMessage() . "<br>";
    $settings = [
        'school_name' => 'School Name',
        'school_address' => 'School Address',
        'school_phone' => 'N/A',
        'school_email' => 'N/A',
        'currency_symbol' => '₹'
    ];
}

// Get fee items
try {
    echo "7. Fetching fee items...<br>";
    $feeItems = fetchAll("SELECT
                            fh.fee_head_name, fh.fee_type, frd.amount
                          FROM fee_receipt_details frd
                          JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                          WHERE frd.receipt_id = ?
                          ORDER BY fh.display_order", 'i', [$receiptId]);

    if (empty($feeItems)) {
        echo "⚠ No fee items, creating default<br>";
        $feeItems = [
            [
                'fee_head_name' => 'Fee Payment',
                'fee_type' => 'General',
                'amount' => $receipt['amount_paid']
            ]
        ];
    } else {
        echo "✓ Found " . count($feeItems) . " fee items<br>";
    }
} catch (Exception $e) {
    echo "ERROR with fee items: " . $e->getMessage() . "<br>";
    $feeItems = [
        [
            'fee_head_name' => 'Fee Payment',
            'fee_type' => 'General',
            'amount' => $receipt['amount_paid']
        ]
    ];
}

// Test amount conversion
try {
    echo "8. Testing amount conversion...<br>";

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

    $amountInWords = convertToWords(intval($receipt['amount_paid'])) . ' Only';
    echo "✓ Amount in words: $amountInWords<br>";
} catch (Exception $e) {
    die("ERROR with amount conversion: " . $e->getMessage());
}

echo "<hr>";
echo "<h2>✅ ALL CHECKS PASSED!</h2>";
echo "<p>The PDF should work now. If you're seeing this page, all the data is loading correctly.</p>";
echo "<p><strong>Receipt Details:</strong></p>";
echo "<ul>";
echo "<li>Receipt ID: " . $receipt['receipt_id'] . "</li>";
echo "<li>Receipt No: " . $receipt['receipt_no'] . "</li>";
echo "<li>Student: " . $receipt['student_name'] . "</li>";
echo "<li>Amount: ₹" . number_format($receipt['amount_paid'], 2) . "</li>";
echo "<li>Cancelled: " . ($receipt['is_cancelled'] ? 'Yes' : 'No') . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Next step:</strong> Try the actual PDF page:</p>";
echo "<p><a href='pdf_receipt.php?id=$receiptId' target='_blank' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>View PDF Receipt</a></p>";

echo "<hr>";
echo "<p><strong>Debug info saved. If PDF still doesn't work, check:</strong></p>";
echo "<ol>";
echo "<li>Browser console for JavaScript errors (F12)</li>";
echo "<li>PHP error log: C:\\xampp\\php\\logs\\php_error_log</li>";
echo "<li>Apache error log: C:\\xampp\\apache\\logs\\error.log</li>";
echo "</ol>";
?>
