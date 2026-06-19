<?php
/**
 * Simple Receipt Viewer - Minimal version for testing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Loading started -->\n";

try {
    require_once '../../config/config.php';
    echo "<!-- Config loaded -->\n";

    requireLogin();
    echo "<!-- Login checked -->\n";

    requirePermission('fees', 'view');
    echo "<!-- Permissions checked -->\n";

    $receiptId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    echo "<!-- Receipt ID: $receiptId -->\n";

    if ($receiptId <= 0) {
        throw new Exception("Invalid receipt ID: $receiptId");
    }

    // Get receipt
    $receipt = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);
    echo "<!-- Receipt query executed -->\n";

    if (!$receipt) {
        throw new Exception("Receipt ID $receiptId not found in database");
    }

    // Get settings
    $settings = getSchoolSettings();
    if (!$settings) {
        $settings = ['school_name' => 'School Name', 'school_address' => 'Address', 'school_phone' => 'N/A', 'school_email' => 'N/A', 'currency_symbol' => '₹'];
    }

    echo "<!-- All data loaded successfully -->\n";

} catch (Exception $e) {
    die('<html><body style="font-family:Arial;padding:40px;"><h1 style="color:red;">Error Loading Receipt</h1><p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p><p><a href="receipts.php">← Back to Receipts</a></p></body></html>');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo htmlspecialchars($receipt['receipt_no']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .controls {
            text-align: center;
            margin-bottom: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .controls button,
        .controls a {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-print {
            background: #0d6efd;
            color: white;
        }
        .btn-back {
            background: #6c757d;
            color: white;
        }
        .receipt {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            border: 2px solid #333;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .school-name {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .total {
            background: #fff3cd;
            font-weight: bold;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .controls {
                display: none;
            }
            .receipt {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="window.print()" class="btn-print">🖨️ Print Receipt</button>
        <a href="receipts.php" class="btn-back">← Back to List</a>
    </div>

    <div class="receipt">
        <div class="header">
            <div class="school-name"><?php echo htmlspecialchars($settings['school_name']); ?></div>
            <div><?php echo htmlspecialchars($settings['school_address']); ?></div>
        </div>

        <h2 style="text-align: center; background: #f8f9fa; padding: 10px; margin: 20px 0;">
            FEE RECEIPT
        </h2>

        <table>
            <tr>
                <th>Receipt No:</th>
                <td><?php echo htmlspecialchars($receipt['receipt_no']); ?></td>
                <th>Date:</th>
                <td><?php echo date('d-M-Y', strtotime($receipt['payment_date'])); ?></td>
            </tr>
            <tr>
                <th>Student ID:</th>
                <td><?php echo htmlspecialchars($receipt['student_id']); ?></td>
                <th>Amount:</th>
                <td><strong>₹<?php echo number_format($receipt['amount_paid'], 2); ?></strong></td>
            </tr>
            <tr>
                <th>Payment Mode:</th>
                <td><?php echo htmlspecialchars($receipt['payment_mode']); ?></td>
                <th>Status:</th>
                <td><?php echo $receipt['is_cancelled'] ? '<span style="color:red;">CANCELLED</span>' : '<span style="color:green;">Active</span>'; ?></td>
            </tr>
        </table>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><strong>This is a simplified receipt view for testing.</strong></p>
            <p>Receipt ID: <?php echo $receiptId; ?> | Generated: <?php echo date('d-M-Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        console.log('Receipt page loaded successfully');
        console.log('Receipt ID: <?php echo $receiptId; ?>');
        console.log('Print button should be visible');

        // Check if auto-print requested
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'auto') {
            console.log('Auto-print requested, triggering in 500ms...');
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
