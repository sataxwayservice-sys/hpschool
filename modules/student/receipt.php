<?php
/**
 * Student Portal Printable Receipt
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();
requireStudentPortalLogin();

$studentId = studentPortalGetCurrentStudentId();
$receiptId = intval($_GET['id'] ?? 0);
$paperSize = strtoupper(trim((string)($_GET['size'] ?? 'A4')));
if (!in_array($paperSize, ['A4', 'A5'], true)) {
    $paperSize = 'A4';
}

if ($receiptId <= 0) {
    die('Invalid receipt ID.');
}

$receipt = studentPortalGetReceipt($receiptId, $studentId);
if (!$receipt) {
    die('Receipt not found or you do not have access to it.');
}

$details = fetchAll(
    "SELECT frd.*, fh.fee_head_name, fh.fee_type
     FROM fee_receipt_details frd
     JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
     WHERE frd.receipt_id = ?
     ORDER BY fh.display_order",
    'i',
    [$receiptId]
);

if (empty($details)) {
    $details = [[
        'fee_head_name' => 'Fee Payment',
        'fee_type' => 'General',
        'amount' => $receipt['amount_paid'],
    ]];
}

$settings = getSchoolSettings();
$receiptUrl = APP_URL . '/modules/student/receipt.php?id=' . $receiptId . '&size=' . $paperSize;
$viewUrl = APP_URL . '/modules/student/receipts.php';
$shareMessage = 'Fee receipt ' . $receipt['receipt_no'] . ' for ' . $receipt['student_name'] . ' - ' . formatCurrency($receipt['amount_paid']) . '.';
$whatsappUrl = buildWhatsAppUrl($receipt['contact_no'] ?? '', $shareMessage . ' View: ' . $viewUrl);

$receiptData = [
    'paper_size' => $paperSize,
    'school_name' => $settings['school_name'] ?? APP_NAME,
    'school_address' => $settings['school_address'] ?? '',
    'school_phone' => $settings['school_phone'] ?? '',
    'school_email' => $settings['school_email'] ?? '',
    'receipt_no' => $receipt['receipt_no'],
    'payment_date' => $receipt['payment_date'],
    'student_name' => $receipt['student_name'],
    'admission_no' => $receipt['admission_no'],
    'father_name' => $receipt['father_name'],
    'class' => trim(($receipt['class_name'] ?? '') . ' ' . ($receipt['section_name'] ?? '')),
    'contact_no' => $receipt['contact_no'],
    'payment_mode' => $receipt['payment_mode'],
    'transaction_id' => $receipt['transaction_id'] ?? '',
    'received_by' => 'N/A',
    'amount_paid' => $receipt['amount_paid'],
    'fee_details' => $details,
    'remarks' => $receipt['remarks'] ?? '',
    'qr_code_url' => buildQrCodeUrl($receiptUrl, 180),
    'download_pdf_a4_url' => APP_URL . '/modules/student/receipt.php?id=' . $receiptId . '&size=A4',
    'download_pdf_a5_url' => APP_URL . '/modules/student/receipt.php?id=' . $receiptId . '&size=A5',
    'whatsapp_url' => $whatsappUrl,
    'receipt_url' => $viewUrl,
];

require_once '../../includes/pdf_helper.php';
echo generateReceiptPDF($receiptData);
