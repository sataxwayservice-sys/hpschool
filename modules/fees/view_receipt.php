<?php
/**
 * View Receipt (No Auto-Print)
 * Same as pdf_receipt.php but without window.print()
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/config.php';
require_once '../../includes/pdf_helper.php';
requireLogin();
requirePermission('fees', 'view');

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0);

if ($receiptId <= 0) {
    die('Invalid receipt ID. <a href="receipts.php">Go back</a>');
}

$receipt = fetchOne("SELECT
                        fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
                        fr.payment_mode, fr.payment_date, fr.remarks, fr.created_at, fr.is_cancelled,
                        fr.transaction_id, fr.bank_name, fr.cheque_date,
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
    die('Receipt not found. <a href="check_pdf_error.php?id=' . $receiptId . '">Diagnose</a>');
}

$isCancelled = isset($receipt['is_cancelled']) && $receipt['is_cancelled'] == 1;

$feeItems = fetchAll("SELECT
                        fh.fee_head_name, fh.fee_type, frd.amount
                      FROM fee_receipt_details frd
                      JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                      WHERE frd.receipt_id = ?
                      ORDER BY fh.display_order", 'i', [$receiptId]);

if (empty($feeItems)) {
    $feeItems = [
        [
            'fee_head_name' => 'Fee Payment',
            'fee_type' => 'General',
            'amount' => $receipt['amount_paid']
        ]
    ];
}

$settings = getSchoolSettings();

if (!$settings) {
    $settings = [
        'school_name' => 'School Name',
        'school_address' => 'School Address',
        'school_phone' => 'N/A',
        'school_email' => 'N/A',
        'currency_symbol' => '₹'
    ];
}

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

$paperSize = strtoupper($_GET['size'] ?? 'A4');
if (!in_array($paperSize, ['A4', 'A5'])) {
    $paperSize = 'A4';
}

$receiptUrl = APP_URL . '/modules/fees/receipt.php?id=' . $receiptId;
$pdfA4Url = APP_URL . '/modules/fees/pdf_receipt.php?id=' . $receiptId . '&size=A4';
$pdfA5Url = APP_URL . '/modules/fees/pdf_receipt.php?id=' . $receiptId . '&size=A5';
$shareMessage = 'Fee receipt ' . $receipt['receipt_no'] . ' for ' . $receipt['student_name'] . ' - ' . formatCurrency($receipt['amount_paid']) . '. View: ' . $receiptUrl;

$receiptData = [
    'receipt_id' => $receiptId,
    'paper_size' => $paperSize,
    'school_name' => $settings['school_name'] ?? 'School Name',
    'school_address' => $settings['school_address'] ?? 'School Address',
    'school_phone' => $settings['school_phone'] ?? 'N/A',
    'school_email' => $settings['school_email'] ?? 'N/A',
    'receipt_no' => $receipt['receipt_no'],
    'payment_date' => $receipt['payment_date'],
    'student_name' => $receipt['student_name'],
    'admission_no' => $receipt['admission_no'],
    'father_name' => $receipt['father_name'],
    'class' => trim(($receipt['class_name'] ?? '') . ' ' . ($receipt['section_name'] ?? '')),
    'contact_no' => $receipt['contact_no'],
    'payment_mode' => $receipt['payment_mode'],
    'transaction_no' => $receipt['transaction_id'] ?? '',
    'bank_name' => $receipt['bank_name'] ?? '',
    'cheque_date' => $receipt['cheque_date'] ?? '',
    'received_by' => trim((string) ($receipt['received_by'] ?? '')) !== '' ? $receipt['received_by'] : 'N/A',
    'amount_paid' => $receipt['amount_paid'],
    'remarks' => $receipt['remarks'],
    'fee_details' => $feeItems,
    'receipt_url' => $receiptUrl,
    'download_pdf_a4_url' => $pdfA4Url,
    'download_pdf_a5_url' => $pdfA5Url,
    'whatsapp_url' => buildWhatsAppUrl($receipt['contact_no'] ?? '', $shareMessage),
    'qr_code_url' => buildQrCodeUrl($receiptUrl, 180),
];

echo generateReceiptPDF($receiptData);
exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt - <?php echo $receipt['receipt_no']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
            background: #f5f5f5;
        }

        .print-button {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-button button {
            padding: 12px 30px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .print-button button:hover {
            background: #0a58ca;
        }

        .receipt-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 20px;
            position: relative;
            background: white;
        }

        .cancelled-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: bold;
            color: rgba(220, 53, 69, 0.2);
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
        }

        .cancelled-alert {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }

        .school-header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .school-name {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 5px;
        }

        .school-details {
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
        }

        .receipt-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .info-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }

        .info-table td:first-child {
            width: 30%;
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .fee-table th {
            background-color: #0d6efd;
            color: white;
            padding: 10px;
            text-align: left;
            border: 1px solid #0d6efd;
        }

        .fee-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }

        .fee-table .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #fff3cd;
        }

        .amount-words {
            background-color: #f8f9fa;
            padding: 10px;
            border: 1px solid #dee2e6;
            margin-bottom: 15px;
            font-style: italic;
        }

        .footer-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .signature-box {
            text-align: center;
            min-width: 220px;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 11px;
        }

        .note {
            font-size: 10px;
            color: #666;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #dee2e6;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }
            .print-button {
                display: none;
            }
            .receipt-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-button">
        <button onclick="window.print()">🖨️ Print Receipt</button>
        <button onclick="window.location.href='receipts.php'" style="background: #6c757d; margin-left: 10px;">← Back to List</button>
    </div>

    <div class="receipt-container">
        <?php if ($isCancelled): ?>
        <div class="cancelled-watermark">CANCELLED</div>
        <?php endif; ?>

        <div class="school-header">
            <div class="school-name"><?php echo htmlspecialchars($settings['school_name'] ?? 'School Name'); ?></div>
            <div class="school-details"><?php echo htmlspecialchars($settings['school_address'] ?? 'School Address'); ?></div>
            <div class="school-details">
                Phone: <?php echo htmlspecialchars($settings['contact_no'] ?? 'N/A'); ?> |
                Email: <?php echo htmlspecialchars($settings['email'] ?? 'N/A'); ?>
            </div>
        </div>

        <?php if ($isCancelled): ?>
        <div class="cancelled-alert">
            ⚠️ THIS RECEIPT HAS BEEN CANCELLED AND IS NO LONGER VALID ⚠️
            <br><small>This receipt can be restored from Settings -> Recycle Bin until you delete it manually</small>
        </div>
        <?php endif; ?>

        <div class="receipt-title">
            FEE RECEIPT <?php echo $isCancelled ? '(CANCELLED)' : ''; ?>
        </div>

        <div class="receipt-info">
            <div><strong>Receipt No:</strong> <?php echo htmlspecialchars($receipt['receipt_no']); ?></div>
            <div><strong>Date:</strong> <?php echo date('d-M-Y', strtotime($receipt['payment_date'])); ?></div>
        </div>

        <table class="info-table">
            <tr>
                <td>Student Name</td>
                <td><?php echo htmlspecialchars($receipt['student_name'] ?? 'N/A'); ?></td>
                <td>Admission No</td>
                <td><?php echo htmlspecialchars($receipt['admission_no'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Father's Name</td>
                <td><?php echo htmlspecialchars($receipt['father_name'] ?? 'N/A'); ?></td>
                <td>Mother's Name</td>
                <td><?php echo htmlspecialchars($receipt['mother_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Class</td>
                <td><?php
                    $className = $receipt['class_name'] ?? 'N/A';
                    $sectionName = $receipt['section_name'] ?? 'N/A';
                    echo htmlspecialchars($className . ' - ' . $sectionName);
                ?></td>
                <td>Roll No</td>
                <td><?php echo htmlspecialchars($receipt['roll_no'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Contact No</td>
                <td><?php echo htmlspecialchars($receipt['contact_no'] ?? 'N/A'); ?></td>
                <td>Payment Mode</td>
                <td><strong><?php echo htmlspecialchars($receipt['payment_mode']); ?></strong></td>
            </tr>
            <tr>
                <td>Transaction / Cheque No.</td>
                <td><?php echo htmlspecialchars($receipt['transaction_id'] ?? 'N/A'); ?></td>
                <td>Bank Name</td>
                <td><?php echo htmlspecialchars($receipt['bank_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Cheque Date</td>
                <td>
                    <?php
                    $chequeDate = $receipt['cheque_date'] ?? '';
                    echo !empty($chequeDate) ? htmlspecialchars(date('d-m-Y', strtotime($chequeDate))) : 'N/A';
                    ?>
                </td>
                <td>Received By</td>
                <td><?php echo htmlspecialchars($receipt['received_by'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <table class="fee-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Fee Head</th>
                    <th>Fee Type</th>
                    <th class="text-right">Amount (<?php echo $settings['currency_symbol'] ?? '₹'; ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sno = 1;
                foreach ($feeItems as $item):
                ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <td><?php echo htmlspecialchars($item['fee_head_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['fee_type']); ?></td>
                    <td class="text-right"><?php echo number_format($item['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>Total Amount Paid</strong></td>
                    <td class="text-right"><strong><?php echo number_format($receipt['amount_paid'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="amount-words">
            <strong>Amount in Words:</strong> <?php echo $amountInWords; ?>
        </div>

        <?php if (!empty($receipt['remarks'])): ?>
        <div class="amount-words">
            <strong>Remarks:</strong> <?php echo htmlspecialchars($receipt['remarks']); ?>
        </div>
        <?php endif; ?>

        <div class="footer-section">
            <div class="signature-box">
                <div>Received By: <?php echo htmlspecialchars($receipt['received_by'] ?? 'N/A'); ?></div>
                <div class="signature-line">
                    Authorized Signatory
                </div>
            </div>
        </div>

        <div class="note">
            <strong>Note:</strong> This is a computer-generated receipt. Please preserve this receipt for future reference.
            Fee once paid is non-refundable. For any queries, please contact the school office.
        </div>
    </div>
</body>
</html>
