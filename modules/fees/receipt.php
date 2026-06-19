<?php
/**
 * Fee Receipt Display
 * Shows printable receipt after fee collection
 */

// Include configuration
require_once '../../config/config.php';
require_once '../../includes/pdf_helper.php';

// Require login
requireLogin();
requirePermission('fees', 'view');

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receiptId == 0) {
    header("Location: receipts.php");
    exit();
}

$paperSize = strtoupper($_GET['size'] ?? 'A4');
if (!in_array($paperSize, ['A4', 'A5'])) {
    $paperSize = 'A4';
}

// Get receipt with student details
$query = "SELECT
            fr.*,
            s.student_name, s.admission_no, s.father_name, s.contact_no,
            c.class_name, sec.section_name,
            u.full_name as collected_by_name
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          JOIN classes c ON s.class_id = c.class_id
          JOIN sections sec ON s.section_id = sec.section_id
          LEFT JOIN users u ON fr.collected_by = u.user_id
          WHERE fr.receipt_id = ?";

$receipt = fetchOne($query, 'i', [$receiptId]);

if (!$receipt) {
    header("Location: receipts.php");
    exit();
}

// Get fee details
$detailsQuery = "SELECT frd.*, fh.fee_head_name
                 FROM fee_receipt_details frd
                 JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                 WHERE frd.receipt_id = ?
                 ORDER BY fh.display_order";

$feeDetails = fetchAll($detailsQuery, 'i', [$receiptId]);

// Get school settings
$schoolSettings = getSchoolSettings();
$receiptUrl = APP_URL . '/modules/fees/receipt.php?id=' . $receiptId;
$pdfA4Url = APP_URL . '/modules/fees/pdf_receipt.php?id=' . $receiptId . '&size=A4';
$pdfA5Url = APP_URL . '/modules/fees/pdf_receipt.php?id=' . $receiptId . '&size=A5';
$shareMessage = 'Fee receipt ' . $receipt['receipt_no'] . ' for ' . $receipt['student_name'] . ' - ' . formatCurrency($receipt['amount_paid']) . '. View: ' . $receiptUrl;

// Prepare receipt data for PDF generation
$receiptData = [
    'receipt_id' => $receiptId,
    'paper_size' => $paperSize,
    'school_name' => $schoolSettings['school_name'],
    'school_address' => $schoolSettings['school_address'],
    'school_phone' => $schoolSettings['school_phone'],
    'school_email' => $schoolSettings['school_email'],
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
    'received_by' => trim((string) ($receipt['collected_by_name'] ?? '')) !== '' ? $receipt['collected_by_name'] : 'N/A',
    'amount_paid' => $receipt['amount_paid'],
    'remarks' => $receipt['remarks'],
    'fee_details' => $feeDetails,
    'receipt_url' => $receiptUrl,
    'download_pdf_a4_url' => $pdfA4Url,
    'download_pdf_a5_url' => $pdfA5Url,
    'whatsapp_url' => buildWhatsAppUrl($receipt['contact_no'], $shareMessage),
    'qr_code_url' => buildQrCodeUrl($receiptUrl, 180)
];

// Generate HTML receipt
$receiptHTML = generateReceiptPDF($receiptData);

// Output the receipt
echo $receiptHTML;
?>
