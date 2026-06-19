<?php
/**
 * Parent Portal Printable Receipt
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';

requireParentPortalLogin();
parentPortalEnsureSchema();

$currentUser = getCurrentUser();
$receiptId = intval($_GET['id'] ?? 0);
$paperSize = strtoupper(trim((string)($_GET['size'] ?? 'A4')));
if (!in_array($paperSize, ['A4', 'A5'], true)) {
    $paperSize = 'A4';
}

if ($receiptId <= 0) {
    die('Invalid receipt ID.');
}

$linkedStudentIds = parentPortalGetLinkedStudentIds($currentUser['user_id']);
if (empty($linkedStudentIds)) {
    die('No children are linked to this parent account.');
}

$placeholders = implode(',', array_fill(0, count($linkedStudentIds), '?'));
$types = count($linkedStudentIds) > 0 ? str_repeat('i', count($linkedStudentIds)) : '';

$query = "SELECT
            fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
            fr.payment_mode, fr.payment_date, fr.remarks, fr.is_cancelled,
            s.student_name, s.admission_no, s.father_name, s.mother_name,
            s.contact_no, s.address, s.roll_no,
            c.class_name, sec.section_name
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN sections sec ON s.section_id = sec.section_id
          WHERE fr.receipt_id = ?";

$params = [$receiptId];
$bindTypes = 'i';

if (!empty($linkedStudentIds)) {
    $query .= " AND fr.student_id IN ({$placeholders})";
    $params = array_merge($params, $linkedStudentIds);
    $bindTypes .= $types;
}

$receipt = fetchOne($query, $bindTypes, $params);

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
$receiptUrl = APP_URL . '/modules/parent/receipt.php?id=' . $receiptId . '&size=' . $paperSize;
$viewUrl = APP_URL . '/modules/parent/receipts.php?student_id=' . intval($receipt['student_id']);
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
    'download_pdf_a4_url' => APP_URL . '/modules/parent/receipt.php?id=' . $receiptId . '&size=A4',
    'download_pdf_a5_url' => APP_URL . '/modules/parent/receipt.php?id=' . $receiptId . '&size=A5',
    'whatsapp_url' => $whatsappUrl,
    'receipt_url' => $viewUrl,
];

require_once '../../includes/pdf_helper.php';
echo generateReceiptPDF($receiptData);
