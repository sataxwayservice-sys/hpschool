<?php
/**
 * PDF Helper Functions
 * Simple PDF generation without external libraries
 */

/**
 * Generate Fee Receipt PDF
 *
 * @param array $receiptData Receipt information
 * @return string HTML receipt output
 */
function generateReceiptPDF($receiptData) {
    $paperSize = strtoupper($receiptData['paper_size'] ?? 'A4');
    if (!in_array($paperSize, ['A4', 'A5'])) {
        $paperSize = 'A4';
    }

    $downloadA4Url = $receiptData['download_pdf_a4_url'] ?? ($receiptData['download_pdf_url'] ?? '');
    $downloadA5Url = $receiptData['download_pdf_a5_url'] ?? '';
    $whatsappUrl = $receiptData['whatsapp_url'] ?? '';
    $qrCodeUrl = $receiptData['qr_code_url'] ?? '';
    $receiptUrl = $receiptData['receipt_url'] ?? '';
    $containerMaxWidth = $paperSize === 'A5' ? '640px' : '800px';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Fee Receipt</title>
        <style>
            @page { size: <?php echo $paperSize === 'A5' ? 'A5 portrait' : 'A4 portrait'; ?>; margin: 20px; }
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .receipt-container { max-width: <?php echo $containerMaxWidth; ?>; margin: 0 auto; border: 2px solid #000; padding: 30px; position: relative; }
            .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 20px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 28px; }
            .header p { margin: 5px 0; }
            .receipt-title { text-align: center; background: #f0f0f0; padding: 10px; margin: 20px 0; font-size: 20px; font-weight: bold; }
            .receipt-qr { position: absolute; top: 24px; right: 24px; text-align: center; }
            .receipt-qr img { width: 110px; height: 110px; border: 1px solid #ddd; background: #fff; padding: 4px; }
            .receipt-qr small { display: block; margin-top: 4px; font-size: 10px; color: #666; }
            .details { margin: 20px 0; }
            .details table { width: 100%; }
            .details td { padding: 8px 0; }
            .details .label { font-weight: bold; width: 200px; }
            .fee-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .fee-table th, .fee-table td { border: 1px solid #000; padding: 10px; text-align: left; }
            .fee-table th { background: #f0f0f0; font-weight: bold; }
            .fee-table .text-right { text-align: right; }
            .total-row { font-weight: bold; background: #f9f9f9; }
            .footer { margin-top: 40px; }
            .footer { display: flex; justify-content: flex-end; }
            .signature { display: inline-block; min-width: 220px; text-align: center; margin-top: 60px; }
            .signature-meta { font-size: 12px; margin-bottom: 10px; color: #333; }
            .signature-line { border-top: 1px solid #000; padding-top: 5px; margin-top: 50px; }
            .receipt-actions { text-align: center; margin-bottom: 20px; background: #f8f9fa; padding: 14px; border-radius: 6px; }
            .receipt-actions a, .receipt-actions button { display: inline-block; margin: 4px; padding: 10px 16px; border-radius: 5px; border: none; text-decoration: none; color: #fff; cursor: pointer; font-size: 14px; }
            .btn-primary-action { background: #0d6efd; }
            .btn-secondary-action { background: #6c757d; }
            .btn-success-action { background: #198754; }
            .btn-warning-action { background: #fd7e14; }
            @media print {
                .no-print { display: none; }
                body { margin: 0; padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print receipt-actions">
            <?php if (!empty($downloadA4Url)): ?>
                <a href="<?php echo htmlspecialchars($downloadA4Url); ?>" target="_blank" class="btn-primary-action">PDF A4</a>
            <?php endif; ?>
            <?php if (!empty($downloadA5Url)): ?>
                <a href="<?php echo htmlspecialchars($downloadA5Url); ?>" target="_blank" class="btn-warning-action">PDF A5</a>
            <?php endif; ?>
            <?php if (!empty($whatsappUrl)): ?>
                <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" target="_blank" class="btn-success-action">WhatsApp</a>
            <?php endif; ?>
            <?php if (!empty($receiptUrl)): ?>
                <a href="<?php echo htmlspecialchars($receiptUrl); ?>" target="_blank" class="btn-secondary-action">Open Receipt</a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-primary-action">Print</button>
            <button onclick="window.close()" class="btn-secondary-action">Close</button>
        </div>

        <div class="receipt-container">
            <div class="header">
                <h1><?php echo htmlspecialchars($receiptData['school_name']); ?></h1>
                <p><?php echo htmlspecialchars($receiptData['school_address']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($receiptData['school_phone']); ?> | Email: <?php echo htmlspecialchars($receiptData['school_email']); ?></p>
            </div>

            <div class="receipt-title">FEE RECEIPT</div>

            <?php if (!empty($qrCodeUrl)): ?>
                <div class="receipt-qr">
                    <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="Receipt QR">
                    <small>Scan for receipt</small>
                </div>
            <?php endif; ?>

            <div class="details">
                <table>
                    <tr>
                        <td class="label">Receipt No:</td>
                        <td><strong><?php echo htmlspecialchars($receiptData['receipt_no']); ?></strong></td>
                        <td class="label">Date:</td>
                        <td><strong><?php echo date('d-m-Y', strtotime($receiptData['payment_date'])); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="label">Student Name:</td>
                        <td><strong><?php echo htmlspecialchars($receiptData['student_name']); ?></strong></td>
                        <td class="label">Admission No:</td>
                        <td><strong><?php echo htmlspecialchars($receiptData['admission_no']); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="label">Father's Name:</td>
                        <td><?php echo htmlspecialchars($receiptData['father_name']); ?></td>
                        <td class="label">Class:</td>
                        <td><?php echo htmlspecialchars($receiptData['class']); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Contact Number:</td>
                        <td><?php echo htmlspecialchars($receiptData['contact_no']); ?></td>
                        <td class="label">Payment Mode:</td>
                        <td><?php echo htmlspecialchars($receiptData['payment_mode']); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Transaction / Cheque No.:</td>
                        <td><?php echo htmlspecialchars($receiptData['transaction_no'] ?? 'N/A'); ?></td>
                        <td class="label">Bank Name:</td>
                        <td><?php echo htmlspecialchars($receiptData['bank_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Cheque Date:</td>
                        <td>
                            <?php
                            $chequeDate = $receiptData['cheque_date'] ?? '';
                            echo !empty($chequeDate) ? htmlspecialchars(date('d-m-Y', strtotime($chequeDate))) : 'N/A';
                            ?>
                        </td>
                        <td class="label">User / Received By:</td>
                        <td>
                            <?php
                            $receivedBy = trim((string) ($receiptData['received_by'] ?? ''));
                            echo htmlspecialchars($receivedBy !== '' ? $receivedBy : 'N/A');
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="fee-table">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Fee Head</th>
                        <th>Period</th>
                        <th class="text-right">Amount (<?php echo CURRENCY_SYMBOL; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sno = 1;
                foreach ($receiptData['fee_details'] as $detail):
                    $period = !empty($detail['fee_month']) ? $detail['fee_month'] . ' ' . $detail['fee_year'] : '-';
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo htmlspecialchars($detail['fee_head_name']); ?></td>
                        <td><?php echo htmlspecialchars($period); ?></td>
                        <td class="text-right"><?php echo number_format($detail['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Total Amount Paid:</td>
                        <td class="text-right"><?php echo CURRENCY_SYMBOL . ' ' . number_format($receiptData['amount_paid'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <p><strong>Amount in Words:</strong> <?php echo convertNumberToWords($receiptData['amount_paid']); ?> Only</p>

            <?php if (!empty($receiptData['remarks'])): ?>
                <p><strong>Remarks:</strong> <?php echo htmlspecialchars($receiptData['remarks']); ?></p>
            <?php endif; ?>

            <div class="footer">
                <div class="signature">
                    <div class="signature-line">Authorized Signature</div>
                </div>
            </div>

            <p style="text-align: center; margin-top: 40px; font-size: 12px; color: #666;">
                This is a computer-generated receipt. Please preserve this for your records.
            </p>
        </div>
    </body>
    </html>
    <?php

    return ob_get_clean();
}

/**
 * Convert number to words (Indian format)
 */
function convertNumberToWords($number) {
    $amount = number_format($number, 2, '.', '');
    list($rupees, $paise) = explode('.', $amount);

    $words = '';

    if ($rupees > 0) {
        $words = numberToWordsHelper($rupees) . ' Rupees';
    }

    if ($paise > 0) {
        $words .= ($words ? ' and ' : '') . numberToWordsHelper($paise) . ' Paise';
    }

    return $words ? $words : 'Zero Rupees';
}

function numberToWordsHelper($number) {
    $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                  'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                  'Seventeen', 'Eighteen', 'Nineteen');
    $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');

    if ($number < 20) {
        return $ones[$number];
    } elseif ($number < 100) {
        return $tens[intval($number / 10)] . ' ' . $ones[$number % 10];
    } elseif ($number < 1000) {
        return $ones[intval($number / 100)] . ' Hundred ' . numberToWordsHelper($number % 100);
    } elseif ($number < 100000) {
        return numberToWordsHelper(intval($number / 1000)) . ' Thousand ' . numberToWordsHelper($number % 1000);
    } elseif ($number < 10000000) {
        return numberToWordsHelper(intval($number / 100000)) . ' Lakh ' . numberToWordsHelper($number % 100000);
    } else {
        return numberToWordsHelper(intval($number / 10000000)) . ' Crore ' . numberToWordsHelper($number % 10000000);
    }
}
