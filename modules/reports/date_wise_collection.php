<?php
/**
 * Date-wise Collection Report
 * Daily, monthly, and yearly collection breakdown with class and student filters
 */

require_once '../../config/config.php';
require_once '../../includes/pdf_export.php';

requireLogin();
requirePermission('reports', 'view');

$pageTitle = 'Date-wise Collection Report';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$schoolSettings = getSchoolSettings();
$schoolName = $schoolSettings['school_name'] ?? APP_NAME;

function dateWiseCollectionReportEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dateWiseCollectionReportNormalizeDate($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return '';
    }

    return $value;
}

function dateWiseCollectionReportFloat($value) {
    return round((float) $value, 2);
}

function dateWiseCollectionReportFormatMoney($value) {
    return formatCurrency(dateWiseCollectionReportFloat($value));
}

function dateWiseCollectionReportFormatDateTime($value) {
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '-' : date('d-M-Y h:i A', $timestamp);
}

function dateWiseCollectionReportGroupConfig($groupBy) {
    $groupBy = strtolower(trim((string) $groupBy));

    switch ($groupBy) {
        case 'monthly':
            return [
                'key_expr' => "DATE_FORMAT(fr.payment_date, '%Y-%m')",
                'label' => 'Monthly',
            ];
        case 'yearly':
            return [
                'key_expr' => "DATE_FORMAT(fr.payment_date, '%Y')",
                'label' => 'Yearly',
            ];
        default:
            return [
                'key_expr' => "DATE_FORMAT(fr.payment_date, '%Y-%m-%d')",
                'label' => 'Daily',
            ];
    }
}

function dateWiseCollectionReportPeriodLabel($groupBy, $periodKey) {
    $periodKey = trim((string) $periodKey);
    if ($periodKey === '') {
        return '-';
    }

    switch ($groupBy) {
        case 'monthly':
            $timestamp = strtotime($periodKey . '-01');
            return $timestamp === false ? $periodKey : date('F Y', $timestamp);
        case 'yearly':
            $timestamp = strtotime($periodKey . '-01-01');
            return $timestamp === false ? $periodKey : date('Y', $timestamp);
        default:
            $timestamp = strtotime($periodKey);
            return $timestamp === false ? $periodKey : date('d-M-Y (D)', $timestamp);
    }
}

function dateWiseCollectionReportPeriodRangeLabel($startDate, $endDate) {
    $startDate = trim((string) $startDate);
    $endDate = trim((string) $endDate);

    if ($startDate === '' && $endDate === '') {
        return '-';
    }

    if ($startDate === '') {
        return dateWiseCollectionReportNormalizeDate($endDate) !== '' ? date('d-M-Y', strtotime($endDate)) : $endDate;
    }

    if ($endDate === '') {
        return dateWiseCollectionReportNormalizeDate($startDate) !== '' ? date('d-M-Y', strtotime($startDate)) : $startDate;
    }

    return date('d-M-Y', strtotime($startDate)) . ' to ' . date('d-M-Y', strtotime($endDate));
}

function dateWiseCollectionReportPaymentModeClass($mode) {
    switch (strtolower(trim((string) $mode))) {
        case 'cash':
            return 'success';
        case 'upi':
            return 'info';
        case 'bank':
            return 'primary';
        case 'cheque':
            return 'warning text-dark';
        default:
            return 'secondary';
    }
}

function dateWiseCollectionReportBuildUrl(array $baseParams, array $overrides = []) {
    $params = array_merge($baseParams, $overrides);

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
            continue;
        }

        if (in_array($key, ['class_id', 'section_id', 'student_id'], true) && intval($value) <= 0) {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return APP_URL . '/modules/reports/date_wise_collection.php' . ($query !== '' ? '?' . $query : '');
}

function dateWiseCollectionReportReadFilters() {
    $groupBy = strtolower(trim((string) ($_GET['group_by'] ?? 'daily')));
    if (!in_array($groupBy, ['daily', 'monthly', 'yearly'], true)) {
        $groupBy = 'daily';
    }

    $paymentMode = trim((string) ($_GET['payment_mode'] ?? ''));
    if (!in_array($paymentMode, ['Cash', 'UPI', 'Bank', 'Cheque'], true)) {
        $paymentMode = '';
    }

    return [
        'from_date' => dateWiseCollectionReportNormalizeDate($_GET['from_date'] ?? ''),
        'to_date' => dateWiseCollectionReportNormalizeDate($_GET['to_date'] ?? ''),
        'group_by' => $groupBy,
        'class_id' => intval($_GET['class_id'] ?? 0),
        'section_id' => intval($_GET['section_id'] ?? 0),
        'student_id' => intval($_GET['student_id'] ?? 0),
        'student_search' => trim((string) ($_GET['student_search'] ?? '')),
        'payment_mode' => $paymentMode,
    ];
}

function dateWiseCollectionReportHasFilters(array $filters) {
    return !empty($filters['from_date'])
        || !empty($filters['to_date'])
        || intval($filters['class_id']) > 0
        || intval($filters['section_id']) > 0
        || intval($filters['student_id']) > 0
        || trim((string) $filters['student_search']) !== ''
        || trim((string) $filters['payment_mode']) !== '';
}

function dateWiseCollectionReportBuildWhereClause(array $filters, &$types = '', &$params = []) {
    global $currentSchoolId;
    $conditions = ['fr.is_cancelled = 0'];

    if (intval($currentSchoolId) > 0) {
        $conditions[] = 's.school_id = ?';
        $types .= 'i';
        $params[] = intval($currentSchoolId);
    }

    if (!empty($filters['from_date'])) {
        $conditions[] = 'fr.payment_date >= ?';
        $types .= 's';
        $params[] = $filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $conditions[] = 'fr.payment_date <= ?';
        $types .= 's';
        $params[] = $filters['to_date'];
    }

    if (!empty($filters['payment_mode'])) {
        $conditions[] = 'fr.payment_mode = ?';
        $types .= 's';
        $params[] = $filters['payment_mode'];
    }

    if (intval($filters['class_id']) > 0) {
        $conditions[] = 's.class_id = ?';
        $types .= 'i';
        $params[] = intval($filters['class_id']);
    }

    if (intval($filters['section_id']) > 0) {
        $conditions[] = 's.section_id = ?';
        $types .= 'i';
        $params[] = intval($filters['section_id']);
    }

    if (intval($filters['student_id']) > 0) {
        $conditions[] = 'fr.student_id = ?';
        $types .= 'i';
        $params[] = intval($filters['student_id']);
    } elseif (trim((string) $filters['student_search']) !== '') {
        $conditions[] = '(s.student_name LIKE ? OR s.admission_no LIKE ? OR s.roll_no LIKE ? OR s.father_name LIKE ? OR s.contact_no LIKE ?)';
        $searchPattern = '%' . trim((string) $filters['student_search']) . '%';
        $types .= 'sssss';
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }

    return ' WHERE ' . implode(' AND ', $conditions);
}

function dateWiseCollectionReportFetchOverallStats(array $filters) {
    $types = '';
    $params = [];
    $where = dateWiseCollectionReportBuildWhereClause($filters, $types, $params);

    $query = "SELECT
                COUNT(DISTINCT fr.receipt_id) as total_receipts,
                COUNT(DISTINCT fr.student_id) as total_students,
                COALESCE(SUM(fr.amount_paid), 0) as total_collected,
                COALESCE(SUM(COALESCE(fr.total_amount, fr.amount_paid)), 0) as total_receipt_total,
                COALESCE(SUM(COALESCE(fr.charge_amount, 0)), 0) as total_charge,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'Cash' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as cash_amount,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'UPI' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as upi_amount,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'Bank' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as bank_amount,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'Cheque' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as cheque_amount
              FROM fee_receipts fr
              JOIN students s ON fr.student_id = s.student_id
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id" . $where;

    $stats = empty($types) ? fetchOne($query) : fetchOne($query, $types, $params);
    if (!$stats) {
        $stats = [
            'total_receipts' => 0,
            'total_students' => 0,
            'total_collected' => 0,
            'total_receipt_total' => 0,
            'total_charge' => 0,
            'cash_amount' => 0,
            'upi_amount' => 0,
            'bank_amount' => 0,
            'cheque_amount' => 0,
        ];
    }

    return $stats;
}

function dateWiseCollectionReportFetchDiscountTotal(array $filters) {
    $types = '';
    $params = [];
    $where = dateWiseCollectionReportBuildWhereClause($filters, $types, $params);

    $query = "SELECT COALESCE(SUM(COALESCE(frd.discount, 0)), 0) as total_discount
              FROM fee_receipt_details frd
              JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
              JOIN students s ON fr.student_id = s.student_id
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id" . $where;

    $row = empty($types) ? fetchOne($query) : fetchOne($query, $types, $params);
    return floatval($row['total_discount'] ?? 0);
}

function dateWiseCollectionReportFetchGroupedStats(array $filters) {
    $types = '';
    $params = [];
    $where = dateWiseCollectionReportBuildWhereClause($filters, $types, $params);
    $groupConfig = dateWiseCollectionReportGroupConfig($filters['group_by'] ?? 'daily');
    $groupExpr = $groupConfig['key_expr'];

    $query = "SELECT
                $groupExpr as period_key,
                MIN(fr.payment_date) as period_start,
                MAX(fr.payment_date) as period_end,
                COUNT(DISTINCT fr.receipt_id) as total_receipts,
                COUNT(DISTINCT fr.student_id) as total_students,
                COALESCE(SUM(fr.amount_paid), 0) as total_collected,
                COALESCE(SUM(COALESCE(fr.total_amount, fr.amount_paid)), 0) as total_receipt_total,
                COALESCE(SUM(COALESCE(fr.charge_amount, 0)), 0) as total_charge,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'Cash' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as cash_amount,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'UPI' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as upi_amount,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'Bank' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as bank_amount,
                COALESCE(SUM(CASE WHEN fr.payment_mode = 'Cheque' THEN COALESCE(fr.amount_paid, 0) ELSE 0 END), 0) as cheque_amount
              FROM fee_receipts fr
              JOIN students s ON fr.student_id = s.student_id
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id" . $where . "
              GROUP BY period_key
              ORDER BY period_key DESC";

    $rows = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    return is_array($rows) ? $rows : [];
}

function dateWiseCollectionReportFetchDetailRows(array $filters) {
    $types = '';
    $params = [];
    $where = dateWiseCollectionReportBuildWhereClause($filters, $types, $params);

    $query = "SELECT
                fr.receipt_id,
                fr.receipt_no,
                fr.payment_date,
                fr.payment_mode,
                fr.transaction_id,
                fr.bank_name,
                fr.cheque_date,
                fr.total_amount,
                fr.amount_paid,
                COALESCE(fr.charge_amount, 0) as charge_amount,
                fr.remarks,
                fr.created_at,
                fr.collected_by,
                s.student_id,
                s.student_name,
                s.admission_no,
                s.roll_no,
                s.father_name,
                s.contact_no,
                c.class_name,
                c.class_order,
                sec.section_name,
                COALESCE(u.full_name, u.username, '-') as collected_by_name
              FROM fee_receipts fr
              JOIN students s ON fr.student_id = s.student_id
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              LEFT JOIN users u ON fr.collected_by = u.user_id" . $where . "
              ORDER BY fr.payment_date DESC, fr.receipt_id DESC";

    $rows = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    return is_array($rows) ? $rows : [];
}

function dateWiseCollectionReportFetchReceiptDetailMap(array $receiptRows) {
    $receiptIds = [];
    foreach ($receiptRows as $row) {
        if (isset($row['receipt_id'])) {
            $receiptIds[] = intval($row['receipt_id']);
        }
    }

    if (empty($receiptIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($receiptIds), '?'));
    $types = str_repeat('i', count($receiptIds));
    $query = "SELECT
                frd.receipt_id,
                fh.fee_head_name,
                fh.fee_type,
                frd.fee_month,
                frd.fee_year,
                frd.amount,
                COALESCE(frd.discount, 0) as discount,
                frd.discount_reason
              FROM fee_receipt_details frd
              JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
              WHERE frd.receipt_id IN ($placeholders)
              ORDER BY frd.receipt_id, fh.display_order, fh.fee_head_name";

    $detailRows = fetchAll($query, $types, $receiptIds);
    $map = [];

    foreach ($detailRows as $row) {
        $receiptId = intval($row['receipt_id']);
        if (!isset($map[$receiptId])) {
            $map[$receiptId] = [
                'items' => [],
                'discount_total' => 0.0,
            ];
        }

        $discount = dateWiseCollectionReportFloat($row['discount'] ?? 0);
        $map[$receiptId]['items'][] = $row;
        $map[$receiptId]['discount_total'] += $discount;
    }

    return $map;
}

function dateWiseCollectionReportBuildReceiptDetailsHtml($receiptId, array $detailMap) {
    $receiptId = intval($receiptId);
    if (!isset($detailMap[$receiptId]) || empty($detailMap[$receiptId]['items'])) {
        return '<span class="report-subtle">No fee breakup recorded</span>';
    }

    $lines = [];
    foreach ($detailMap[$receiptId]['items'] as $item) {
        $feeHead = dateWiseCollectionReportEscape($item['fee_head_name'] ?? '-');
        $feeType = trim((string) ($item['fee_type'] ?? ''));
        $feeMonth = trim((string) ($item['fee_month'] ?? ''));
        $feeYear = trim((string) ($item['fee_year'] ?? ''));
        $amount = dateWiseCollectionReportFormatMoney($item['amount'] ?? 0);
        $discount = dateWiseCollectionReportFloat($item['discount'] ?? 0);
        $discountReason = trim((string) ($item['discount_reason'] ?? ''));
        $periodParts = array_filter([$feeMonth, $feeYear], function ($value) {
            return $value !== '';
        });
        $periodLabel = !empty($periodParts) ? ' <span class="report-subtle">(' . dateWiseCollectionReportEscape(implode(' ', $periodParts)) . ')</span>' : '';
        $typeLabel = $feeType !== '' ? ' <span class="report-badge report-badge-muted">' . dateWiseCollectionReportEscape($feeType) . '</span>' : '';
        $discountLine = $discount > 0 ? '<div class="report-subtle">Discount: ' . dateWiseCollectionReportFormatMoney($discount) . '</div>' : '';
        $reasonLine = $discountReason !== '' ? '<div class="report-subtle">Reason: ' . dateWiseCollectionReportEscape($discountReason) . '</div>' : '';

        $lines[] = '<li>'
            . '<strong>' . $feeHead . '</strong>' . $typeLabel . $periodLabel
            . '<div class="report-subtle">Amount: ' . $amount . '</div>'
            . $discountLine
            . $reasonLine
            . '</li>';
    }

    return '<ul class="report-detail-list">' . implode('', $lines) . '</ul>';
}

function dateWiseCollectionReportRenderFilterChips(array $filters, array $selectedLabels, $generatedAt) {
    ob_start();
    ?>
    <div class="report-chip-row mb-3">
        <span class="report-chip"><strong>Group:</strong> <?php echo dateWiseCollectionReportEscape($selectedLabels['group_by']); ?></span>
        <span class="report-chip"><strong>Date Range:</strong> <?php echo dateWiseCollectionReportEscape($selectedLabels['date_range']); ?></span>
        <span class="report-chip"><strong>Class:</strong> <?php echo dateWiseCollectionReportEscape($selectedLabels['class']); ?></span>
        <span class="report-chip"><strong>Section:</strong> <?php echo dateWiseCollectionReportEscape($selectedLabels['section']); ?></span>
        <span class="report-chip"><strong>Student:</strong> <?php echo dateWiseCollectionReportEscape($selectedLabels['student']); ?></span>
        <span class="report-chip"><strong>Payment Mode:</strong> <?php echo dateWiseCollectionReportEscape($selectedLabels['payment_mode']); ?></span>
        <span class="report-chip"><strong>Generated:</strong> <?php echo dateWiseCollectionReportEscape($generatedAt); ?></span>
    </div>
    <?php
    return ob_get_clean();
}

function dateWiseCollectionReportRenderSummaryCards(array $overallStats, $totalDiscount) {
    ob_start();
    ?>
    <div class="report-summary-grid mb-4">
        <div class="report-summary-card report-tone-primary">
            <span class="report-summary-label">Total Receipts</span>
            <span class="report-summary-value"><?php echo intval($overallStats['total_receipts'] ?? 0); ?></span>
            <span class="report-summary-note">Receipt count in the selected filters</span>
        </div>
        <div class="report-summary-card report-tone-success">
            <span class="report-summary-label">Unique Students</span>
            <span class="report-summary-value"><?php echo intval($overallStats['total_students'] ?? 0); ?></span>
            <span class="report-summary-note">Distinct students in the report</span>
        </div>
        <div class="report-summary-card report-tone-info">
            <span class="report-summary-label">Collected Amount</span>
            <span class="report-summary-value"><?php echo dateWiseCollectionReportFormatMoney($overallStats['total_collected'] ?? 0); ?></span>
            <span class="report-summary-note">Sum of collected payment amounts</span>
        </div>
        <div class="report-summary-card report-tone-warning">
            <span class="report-summary-label">Receipt Total</span>
            <span class="report-summary-value"><?php echo dateWiseCollectionReportFormatMoney($overallStats['total_receipt_total'] ?? 0); ?></span>
            <span class="report-summary-note">Sum of receipt totals recorded</span>
        </div>
        <div class="report-summary-card report-tone-danger">
            <span class="report-summary-label">Discount</span>
            <span class="report-summary-value"><?php echo dateWiseCollectionReportFormatMoney($totalDiscount); ?></span>
            <span class="report-summary-note">Discounts applied on fee details</span>
        </div>
        <div class="report-summary-card report-tone-muted">
            <span class="report-summary-label">Charge</span>
            <span class="report-summary-value"><?php echo dateWiseCollectionReportFormatMoney($overallStats['total_charge'] ?? 0); ?></span>
            <span class="report-summary-note">Any collected charge / extra amount</span>
        </div>
        <div class="report-summary-card report-tone-primary">
            <span class="report-summary-label">Average / Receipt</span>
            <span class="report-summary-value">
                <?php
                $receipts = intval($overallStats['total_receipts'] ?? 0);
                $avg = $receipts > 0 ? (floatval($overallStats['total_collected'] ?? 0) / $receipts) : 0;
                echo $receipts > 0 ? dateWiseCollectionReportFormatMoney($avg) : '-';
                ?>
            </span>
            <span class="report-summary-note">Average collection per receipt</span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dateWiseCollectionReportRenderSelectedStudentCard(array $student) {
    if (empty($student)) {
        return '';
    }

    ob_start();
    ?>
    <div class="report-panel mb-3">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title mb-0">Selected Student</h3>
                <div class="report-panel-subtitle">Exact student filter applied to the collection report</div>
            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table" style="min-width: 900px;">
                <tbody>
                    <tr>
                        <th style="width: 18%;">Name</th>
                        <td><strong><?php echo dateWiseCollectionReportEscape($student['student_name'] ?? '-'); ?></strong></td>
                        <th style="width: 18%;">Admission No</th>
                        <td><?php echo dateWiseCollectionReportEscape($student['admission_no'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Class / Section</th>
                        <td><?php echo dateWiseCollectionReportEscape(trim((($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '')))); ?></td>
                        <th>Roll No</th>
                        <td><?php echo dateWiseCollectionReportEscape($student['roll_no'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Father</th>
                        <td><?php echo dateWiseCollectionReportEscape($student['father_name'] ?? '-'); ?></td>
                        <th>Mobile</th>
                        <td><?php echo dateWiseCollectionReportEscape($student['contact_no'] ?? '-'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dateWiseCollectionReportRenderSummaryTable(array $summaryRows, $groupBy, array $overallStats, $totalDiscount) {
    ob_start();
    ?>
    <div class="report-panel mb-4">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title mb-0">Collection Summary</h3>
                <div class="report-panel-subtitle"><?php echo count($summaryRows); ?> grouped row<?php echo count($summaryRows) === 1 ? '' : 's'; ?> by <?php echo dateWiseCollectionReportEscape(ucfirst($groupBy)); ?></div>
            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table report-table--summary">
                <thead>
                    <tr>
                        <th style="width: 150px;">Period</th>
                        <th style="width: 190px;">Date Range</th>
                        <th class="report-center">Receipts</th>
                        <th class="report-center">Students</th>
                        <th class="report-num">Collected</th>
                        <th class="report-num">Receipt Total</th>
                        <th class="report-num">Discount</th>
                        <th class="report-num">Charge</th>
                        <th class="report-num">Cash</th>
                        <th class="report-num">UPI</th>
                        <th class="report-num">Bank</th>
                        <th class="report-num">Cheque</th>
                        <th class="report-num">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($summaryRows)): ?>
                        <?php foreach ($summaryRows as $row): ?>
                            <?php
                            $receipts = intval($row['total_receipts'] ?? 0);
                            $students = intval($row['total_students'] ?? 0);
                            $collected = floatval($row['total_collected'] ?? 0);
                            $receiptTotal = floatval($row['total_receipt_total'] ?? 0);
                            $discount = floatval($row['total_discount'] ?? 0);
                            $charge = floatval($row['total_charge'] ?? 0);
                            $cash = floatval($row['cash_amount'] ?? 0);
                            $upi = floatval($row['upi_amount'] ?? 0);
                            $bank = floatval($row['bank_amount'] ?? 0);
                            $cheque = floatval($row['cheque_amount'] ?? 0);
                            $avg = $receipts > 0 ? $collected / $receipts : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo dateWiseCollectionReportEscape(dateWiseCollectionReportPeriodLabel($groupBy, $row['period_key'] ?? '')); ?></strong>
                                </td>
                                <td>
                                    <div><?php echo dateWiseCollectionReportEscape(dateWiseCollectionReportPeriodRangeLabel($row['period_start'] ?? '', $row['period_end'] ?? '')); ?></div>
                                </td>
                                <td class="report-center"><?php echo $receipts; ?></td>
                                <td class="report-center"><?php echo $students; ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($collected); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($receiptTotal); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($discount); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($charge); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($cash); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($upi); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($bank); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($cheque); ?></td>
                                <td class="report-num"><?php echo dateWiseCollectionReportFormatMoney($avg); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13">
                                <div class="report-empty text-center text-muted py-4">
                                    No grouped collection records were found for the selected filters.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th>-</th>
                        <th class="report-center"><?php echo intval($overallStats['total_receipts'] ?? 0); ?></th>
                        <th class="report-center"><?php echo intval($overallStats['total_students'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['total_collected'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['total_receipt_total'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($totalDiscount); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['total_charge'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['cash_amount'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['upi_amount'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['bank_amount'] ?? 0); ?></th>
                        <th class="report-num"><?php echo dateWiseCollectionReportFormatMoney($overallStats['cheque_amount'] ?? 0); ?></th>
                        <th class="report-num"><?php
                            $receiptCount = intval($overallStats['total_receipts'] ?? 0);
                            $avg = $receiptCount > 0 ? (floatval($overallStats['total_collected'] ?? 0) / $receiptCount) : 0;
                            echo $receiptCount > 0 ? dateWiseCollectionReportFormatMoney($avg) : '-';
                        ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dateWiseCollectionReportRenderDetailTable(array $detailRows, array $detailMap) {
    ob_start();
    $totals = [
        'receipts' => 0,
        'collected' => 0,
        'receipt_total' => 0,
        'discount' => 0,
        'charge' => 0,
    ];
    ?>
    <div class="report-panel">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title mb-0">Collection Details</h3>
                <div class="report-panel-subtitle">Receipt-level breakdown with student, class, fee items, and payment details</div>
            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table report-table--detail">
                <thead>
                    <tr>
                        <th style="width: 150px;">Receipt / Date</th>
                        <th style="width: 250px;">Student</th>
                        <th style="width: 160px;">Class / Section</th>
                        <th style="width: 320px;">Fee Details</th>
                        <th style="width: 260px;">Collection</th>
                        <th style="width: 220px;">Collected By / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detailRows)): ?>
                        <?php foreach ($detailRows as $row): ?>
                            <?php
                            $receiptId = intval($row['receipt_id'] ?? 0);
                            $amountPaid = floatval($row['amount_paid'] ?? 0);
                            $receiptTotal = floatval($row['total_amount'] ?? 0);
                            $chargeAmount = floatval($row['charge_amount'] ?? 0);
                            $discountAmount = floatval($detailMap[$receiptId]['discount_total'] ?? 0);
                            $balance = max(0, $receiptTotal - $amountPaid);
                            $totals['receipts']++;
                            $totals['collected'] += $amountPaid;
                            $totals['receipt_total'] += $receiptTotal;
                            $totals['discount'] += $discountAmount;
                            $totals['charge'] += $chargeAmount;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo dateWiseCollectionReportEscape($row['receipt_no'] ?? '-'); ?></strong>
                                    <div class="report-subtle">Payment: <?php echo dateWiseCollectionReportEscape(date('d-M-Y', strtotime($row['payment_date'] ?? ''))); ?></div>
                                    <div class="report-subtle">Recorded: <?php echo dateWiseCollectionReportEscape(dateWiseCollectionReportFormatDateTime($row['created_at'] ?? '')); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo dateWiseCollectionReportEscape($row['student_name'] ?? '-'); ?></strong>
                                    <div class="report-subtle">Adm No: <?php echo dateWiseCollectionReportEscape($row['admission_no'] ?? '-'); ?></div>
                                    <div class="report-subtle">Roll No: <?php echo dateWiseCollectionReportEscape($row['roll_no'] ?? '-'); ?></div>
                                    <div class="report-subtle">Mobile: <?php echo dateWiseCollectionReportEscape($row['contact_no'] ?? '-'); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo dateWiseCollectionReportEscape(trim((($row['class_name'] ?? '-') . ' ' . ($row['section_name'] ?? '')))); ?></strong>
                                    <div class="report-subtle">Student ID: <?php echo intval($row['student_id'] ?? 0); ?></div>
                                </td>
                                <td>
                                    <?php echo dateWiseCollectionReportBuildReceiptDetailsHtml($receiptId, $detailMap); ?>
                                </td>
                                <td>
                                    <div><strong><?php echo dateWiseCollectionReportFormatMoney($amountPaid); ?></strong></div>
                                    <div class="report-subtle">Receipt Total: <?php echo dateWiseCollectionReportFormatMoney($receiptTotal); ?></div>
                                    <div class="report-subtle">Discount: <?php echo dateWiseCollectionReportFormatMoney($discountAmount); ?></div>
                                    <div class="report-subtle">Charge: <?php echo dateWiseCollectionReportFormatMoney($chargeAmount); ?></div>
                                    <div class="report-subtle">Balance: <?php echo dateWiseCollectionReportFormatMoney($balance); ?></div>
                                    <div class="mt-1">
                                        <span class="report-badge report-badge-<?php echo dateWiseCollectionReportPaymentModeClass($row['payment_mode'] ?? ''); ?>">
                                            <?php echo dateWiseCollectionReportEscape($row['payment_mode'] ?? '-'); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($row['transaction_id'])): ?>
                                        <div class="report-subtle">Transaction / Cheque No.: <?php echo dateWiseCollectionReportEscape($row['transaction_id']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['bank_name'])): ?>
                                        <div class="report-subtle">Bank Name: <?php echo dateWiseCollectionReportEscape($row['bank_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['cheque_date'])): ?>
                                        <div class="report-subtle">Cheque Date: <?php echo dateWiseCollectionReportEscape(date('d-M-Y', strtotime($row['cheque_date']))); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo dateWiseCollectionReportEscape($row['collected_by_name'] ?? '-'); ?></strong>
                                    <?php if (!empty($row['remarks'])): ?>
                                        <div class="report-subtle"><?php echo nl2br(dateWiseCollectionReportEscape($row['remarks'])); ?></div>
                                    <?php else: ?>
                                        <div class="report-subtle">-</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="report-empty text-center text-muted py-4">
                                    No collection receipts were found for the selected filters.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th><?php echo $totals['receipts']; ?> receipts</th>
                        <th></th>
                        <th></th>
                        <th>
                            <div><?php echo dateWiseCollectionReportFormatMoney($totals['collected']); ?></div>
                            <div class="report-subtle">Total receipt: <?php echo dateWiseCollectionReportFormatMoney($totals['receipt_total']); ?></div>
                            <div class="report-subtle">Discount: <?php echo dateWiseCollectionReportFormatMoney($totals['discount']); ?></div>
                            <div class="report-subtle">Charge: <?php echo dateWiseCollectionReportFormatMoney($totals['charge']); ?></div>
                        </th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dateWiseCollectionReportRenderExportDocument($mode, $schoolName, $reportTitle, $filterChipsHtml, $summaryCardsHtml, $selectedStudentHtml, $summaryTableHtml, $detailTableHtml, $backUrl) {
    global $schoolSettings;
    $schoolAddress = trim((string)($schoolSettings['school_address'] ?? ''));
    $schoolPhone = trim((string)($schoolSettings['school_phone'] ?? ''));
    $schoolEmail = trim((string)($schoolSettings['school_email'] ?? ''));
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo dateWiseCollectionReportEscape($reportTitle); ?></title>
        <style>
            body {
                margin: 0;
                padding: 18px 20px 28px;
                background: #f8fafc;
                color: #111827;
                font-family: Arial, Helvetica, sans-serif;
            }

            .report-shell {
                max-width: 1800px;
                margin: 0 auto;
            }

            .report-hero {
                background: #ffffff;
                border: 1px solid #dbe3ec;
                border-left: 6px solid #2563eb;
                border-radius: 8px;
                padding: 18px 20px;
                margin-bottom: 14px;
            }

            .report-eyebrow {
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #64748b;
            }

            .report-title {
                margin: 4px 0 0;
                font-size: 1.65rem;
                font-weight: 700;
                color: #111827;
            }

            .report-subtitle {
                margin-top: 6px;
                color: #64748b;
                font-size: 0.95rem;
            }

            .report-export-meta {
                display: grid;
                gap: 10px;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                margin-top: 14px;
            }

            .report-export-meta-item {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 10px 12px;
            }

            .report-export-meta-label {
                display: block;
                font-size: 0.74rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #6b7280;
                font-weight: 700;
            }

            .report-export-meta-value {
                display: block;
                margin-top: 4px;
                font-size: 0.92rem;
                color: #111827;
                font-weight: 600;
                word-break: break-word;
            }

            .report-print-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                margin-bottom: 12px;
                flex-wrap: wrap;
            }

            .report-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 0.45rem 0.9rem;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #ffffff;
                color: #1f2937;
                text-decoration: none;
                font-size: 0.88rem;
                white-space: nowrap;
            }

            .report-action-primary {
                border-color: #2563eb;
                color: #1d4ed8;
            }

            .report-action-muted {
                border-color: #94a3b8;
                color: #475569;
            }

            .report-chip-row {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 12px;
            }

            .report-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 0.25rem 0.7rem;
                border: 1px solid #d8e2ee;
                border-radius: 999px;
                background: #f8fafc;
                color: #334155;
                font-size: 0.82rem;
            }

            .report-summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap: 12px;
                margin: 16px 0 18px;
            }

            .report-summary-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 14px 16px;
                min-height: 88px;
            }

            .report-summary-label {
                display: block;
                font-size: 0.74rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #64748b;
            }

            .report-summary-value {
                display: block;
                margin-top: 6px;
                font-size: 1.2rem;
                font-weight: 700;
                color: #111827;
                word-break: break-word;
            }

            .report-summary-note {
                display: block;
                margin-top: 4px;
                font-size: 0.8rem;
                color: #64748b;
            }

            .report-tone-primary { border-top: 4px solid #2563eb; }
            .report-tone-success { border-top: 4px solid #16a34a; }
            .report-tone-warning { border-top: 4px solid #d97706; }
            .report-tone-danger { border-top: 4px solid #dc2626; }
            .report-tone-info { border-top: 4px solid #0891b2; }
            .report-tone-muted { border-top: 4px solid #64748b; }

            .report-panel {
                background: #ffffff;
                border: 1px solid #dbe3ec;
                border-radius: 8px;
                overflow: hidden;
                margin-bottom: 16px;
            }

            .report-panel-head {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 12px;
                padding: 16px 18px;
                border-bottom: 1px solid #e5e7eb;
                background: #f8fafc;
                flex-wrap: wrap;
            }

            .report-panel-title {
                margin: 0;
                font-size: 1.08rem;
                font-weight: 700;
                color: #111827;
            }

            .report-panel-subtitle {
                margin-top: 4px;
                font-size: 0.92rem;
                color: #64748b;
            }

            .report-table-wrap {
                width: 100%;
                overflow-x: auto;
            }

            .report-table {
                width: 100%;
                border-collapse: collapse;
                background: #ffffff;
            }

            .report-table--summary {
                min-width: 1500px;
            }

            .report-table--detail {
                min-width: 1400px;
            }

            .report-table th,
            .report-table td {
                border-bottom: 1px solid #e5e7eb;
                padding: 0.72rem 0.85rem;
                vertical-align: top;
            }

            .report-table thead th {
                background: #0f172a;
                color: #ffffff;
                font-size: 0.82rem;
                font-weight: 700;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .report-table tbody tr:nth-child(even) td {
                background: #f8fafc;
            }

            .report-table tfoot th,
            .report-table tfoot td {
                background: #eef2f7;
                font-weight: 700;
            }

            .report-num {
                text-align: right;
                white-space: nowrap;
            }

            .report-center {
                text-align: center;
            }

            .report-subtle {
                color: #64748b;
                font-size: 0.82rem;
                line-height: 1.35;
            }

            .report-detail-list {
                margin: 0;
                padding-left: 1rem;
            }

            .report-detail-list li {
                margin-bottom: 0.35rem;
            }

            .report-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.2rem 0.55rem;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 700;
                border: 1px solid transparent;
                line-height: 1.15;
            }

            .report-badge-success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
            .report-badge-info { background: #e0f2fe; color: #075985; border-color: #bae6fd; }
            .report-badge-primary { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
            .report-badge-warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
            .report-badge-secondary { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }
            .report-badge-muted { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

            .report-empty {
                padding: 26px 18px;
            }

            @media print {
                .no-print {
                    display: none !important;
                }

                body {
                    background: #ffffff;
                }

                .report-shell {
                    padding: 0 !important;
                }
            }

            @page {
                size: landscape;
                margin: 10mm;
            }
        </style>
    </head>
    <body>
        <div class="report-shell">
            <?php if ($mode === 'pdf'): ?>
                <div class="report-print-toolbar no-print">
                    <a class="report-action report-action-muted" href="<?php echo dateWiseCollectionReportEscape($backUrl); ?>">Back to Report</a>
                    <button class="report-action report-action-primary" type="button" onclick="window.print();">Print / Save PDF</button>
                </div>
            <?php endif; ?>

            <div class="report-hero">
                <div class="report-eyebrow"><?php echo dateWiseCollectionReportEscape($schoolName); ?></div>
                <h1 class="report-title"><?php echo dateWiseCollectionReportEscape($reportTitle); ?></h1>
                <div class="report-subtitle">Date range, class, section, student, and payment mode filtered collection report.</div>
                <?php if ($schoolAddress !== '' || $schoolPhone !== '' || $schoolEmail !== ''): ?>
                    <div class="report-export-meta" style="margin-top: 10px;">
                        <?php if ($schoolAddress !== ''): ?>
                            <div class="report-export-meta-item">
                                <div class="report-export-meta-label">Address</div>
                                <div class="report-export-meta-value"><?php echo dateWiseCollectionReportEscape($schoolAddress); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($schoolPhone !== ''): ?>
                            <div class="report-export-meta-item">
                                <div class="report-export-meta-label">Phone</div>
                                <div class="report-export-meta-value"><?php echo dateWiseCollectionReportEscape($schoolPhone); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($schoolEmail !== ''): ?>
                            <div class="report-export-meta-item">
                                <div class="report-export-meta-label">Email</div>
                                <div class="report-export-meta-value"><?php echo dateWiseCollectionReportEscape($schoolEmail); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php echo $filterChipsHtml; ?>
            </div>

            <?php echo $summaryCardsHtml; ?>
            <?php echo $selectedStudentHtml; ?>
            <?php echo $summaryTableHtml; ?>
            <?php echo $detailTableHtml; ?>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

$filters = dateWiseCollectionReportReadFilters();
$classes = fetchAll("SELECT class_id, class_name, class_order FROM classes WHERE is_active = 1 ORDER BY class_order, class_name");
$sections = fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name");
$schoolSettings = getSchoolSettings();
$schoolName = $schoolSettings['school_name'] ?? APP_NAME;

$selectedStudent = [];
if (intval($filters['student_id']) > 0) {
    $selectedStudent = fetchOne(
        "SELECT
            s.student_id, s.student_name, s.admission_no, s.roll_no, s.father_name,
            s.contact_no, c.class_name, sec.section_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.class_id
         LEFT JOIN sections sec ON s.section_id = sec.section_id
         WHERE s.student_id = ?",
        'i',
        [intval($filters['student_id'])]
    ) ?: [];

    if (!empty($selectedStudent) && trim((string) $filters['student_search']) === '') {
        $filters['student_search'] = $selectedStudent['admission_no'] ?? $selectedStudent['student_name'] ?? '';
    }
}

$hasFilters = dateWiseCollectionReportHasFilters($filters);
$overallStats = $hasFilters ? dateWiseCollectionReportFetchOverallStats($filters) : [
    'total_receipts' => 0,
    'total_students' => 0,
    'total_collected' => 0,
    'total_receipt_total' => 0,
    'total_charge' => 0,
    'cash_amount' => 0,
    'upi_amount' => 0,
    'bank_amount' => 0,
    'cheque_amount' => 0,
];
$totalDiscount = $hasFilters ? dateWiseCollectionReportFetchDiscountTotal($filters) : 0;
$summaryRows = $hasFilters ? dateWiseCollectionReportFetchGroupedStats($filters) : [];
$detailRows = $hasFilters ? dateWiseCollectionReportFetchDetailRows($filters) : [];
$detailMap = $hasFilters ? dateWiseCollectionReportFetchReceiptDetailMap($detailRows) : [];

$groupConfig = dateWiseCollectionReportGroupConfig($filters['group_by']);
$selectedLabels = [
    'group_by' => $groupConfig['label'],
    'date_range' => 'All Dates',
    'class' => 'All Classes',
    'section' => 'All Sections',
    'student' => 'All Students',
    'payment_mode' => 'All Modes',
];

if (!empty($filters['from_date']) || !empty($filters['to_date'])) {
    $fromLabel = !empty($filters['from_date']) ? date('d-M-Y', strtotime($filters['from_date'])) : 'Start';
    $toLabel = !empty($filters['to_date']) ? date('d-M-Y', strtotime($filters['to_date'])) : 'End';
    $selectedLabels['date_range'] = $fromLabel . ' to ' . $toLabel;
}

foreach ($classes as $class) {
    if (intval($class['class_id']) === intval($filters['class_id'])) {
        $selectedLabels['class'] = $class['class_name'];
        break;
    }
}

foreach ($sections as $section) {
    if (intval($section['section_id']) === intval($filters['section_id'])) {
        $selectedLabels['section'] = $section['section_name'];
        break;
    }
}

if (intval($filters['student_id']) > 0 && !empty($selectedStudent)) {
    $studentName = trim((string) ($selectedStudent['student_name'] ?? ''));
    $admissionNo = trim((string) ($selectedStudent['admission_no'] ?? ''));
    $selectedLabels['student'] = $studentName !== '' && $admissionNo !== ''
        ? $studentName . ' (' . $admissionNo . ')'
        : ($studentName !== '' ? $studentName : $admissionNo);
} elseif (trim((string) $filters['student_search']) !== '') {
    $selectedLabels['student'] = $filters['student_search'];
}

if (!empty($filters['payment_mode'])) {
    $selectedLabels['payment_mode'] = $filters['payment_mode'];
}

$generatedAt = date('d M Y, h:i A');
$baseParams = $filters;

$filterChipsHtml = dateWiseCollectionReportRenderFilterChips($filters, $selectedLabels, $generatedAt);
$summaryCardsHtml = $hasFilters ? dateWiseCollectionReportRenderSummaryCards($overallStats, $totalDiscount) : '';
$selectedStudentHtml = !empty($selectedStudent) ? dateWiseCollectionReportRenderSelectedStudentCard($selectedStudent) : '';
$summaryTableHtml = $hasFilters ? dateWiseCollectionReportRenderSummaryTable($summaryRows, $filters['group_by'], $overallStats, $totalDiscount) : '';
$detailTableHtml = $hasFilters ? dateWiseCollectionReportRenderDetailTable($detailRows, $detailMap) : '';

$export = strtolower(trim((string) ($_GET['export'] ?? '')));
if (($export === 'excel' || $export === 'pdf') && $hasFilters) {
    $exportHtml = dateWiseCollectionReportRenderExportDocument(
        $export === 'pdf' ? 'pdf' : 'excel',
        $schoolName,
        'Date-wise Collection Report',
        $filterChipsHtml,
        $summaryCardsHtml,
        $selectedStudentHtml,
        $summaryTableHtml,
        $detailTableHtml,
        dateWiseCollectionReportBuildUrl($baseParams)
    );

    if (ob_get_level()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    if ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Date_Wise_Collection_Report_' . date('Y-m-d') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo $exportHtml;
    } else {
        $pdfResult = pdfExportDownloadHtml($exportHtml, 'Date_Wise_Collection_Report_' . date('Y-m-d'));
        if (empty($pdfResult['success'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo $exportHtml;
        }
    }
    exit();
}

include '../../includes/header.php';
?>

<style>
.report-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.report-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.25rem 0.7rem;
    border: 1px solid #d8e2ee;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.82rem;
    line-height: 1.2;
}

.report-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
}

.report-summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    min-height: 88px;
}

.report-summary-label {
    display: block;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.report-summary-value {
    display: block;
    margin-top: 6px;
    font-size: 1.2rem;
    font-weight: 700;
    color: #111827;
    word-break: break-word;
}

.report-summary-note {
    display: block;
    margin-top: 4px;
    font-size: 0.8rem;
    color: #64748b;
}

.report-tone-primary { border-top: 4px solid #2563eb; }
.report-tone-success { border-top: 4px solid #16a34a; }
.report-tone-warning { border-top: 4px solid #d97706; }
.report-tone-danger { border-top: 4px solid #dc2626; }
.report-tone-info { border-top: 4px solid #0891b2; }
.report-tone-muted { border-top: 4px solid #64748b; }

.report-panel {
    background: #ffffff;
    border: 1px solid #dbe3ec;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 16px;
}

.report-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    flex-wrap: wrap;
}

.report-panel-title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 700;
    color: #111827;
}

.report-panel-subtitle {
    margin-top: 4px;
    font-size: 0.92rem;
    color: #64748b;
}

.report-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
}

.report-table--summary {
    min-width: 1500px;
}

.report-table--detail {
    min-width: 1400px;
}

.report-table th,
.report-table td {
    border-bottom: 1px solid #e5e7eb;
    padding: 0.72rem 0.85rem;
    vertical-align: top;
}

.report-table thead th {
    background: #0f172a;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.report-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.report-table tfoot th,
.report-table tfoot td {
    background: #eef2f7;
    font-weight: 700;
}

.report-num {
    text-align: right;
    white-space: nowrap;
}

.report-center {
    text-align: center;
}

.report-subtle {
    color: #64748b;
    font-size: 0.82rem;
    line-height: 1.35;
}

.report-detail-list {
    margin: 0;
    padding-left: 1rem;
}

.report-detail-list li {
    margin-bottom: 0.35rem;
}

.report-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid transparent;
    line-height: 1.15;
}

.report-badge-success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.report-badge-info { background: #e0f2fe; color: #075985; border-color: #bae6fd; }
.report-badge-primary { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
.report-badge-warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
.report-badge-secondary { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }
.report-badge-muted { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

.report-empty {
    padding: 26px 18px;
}

@media print {
    .no-print {
        display: none !important;
    }

    body {
        background: #ffffff;
    }
}
</style>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-calendar-range"></i> Date-wise Collection Report
                </h2>
                <div class="text-muted">Daily, monthly, and yearly collection view with class and student filters.</div>
            </div>
            <div class="no-print">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Reports
                </a>
                <?php if ($hasFilters): ?>
                    <a href="<?php echo dateWiseCollectionReportEscape(dateWiseCollectionReportBuildUrl($baseParams, ['export' => 'excel'])); ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </a>
                    <a href="<?php echo dateWiseCollectionReportEscape(dateWiseCollectionReportBuildUrl($baseParams, ['export' => 'pdf'])); ?>" target="_blank" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print / PDF
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="student_id" id="student_id" value="<?php echo intval($filters['student_id']); ?>">
                    <div class="row g-3">
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="from_date" value="<?php echo dateWiseCollectionReportEscape($filters['from_date']); ?>">
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="to_date" value="<?php echo dateWiseCollectionReportEscape($filters['to_date']); ?>">
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Group By</label>
                            <select class="form-select" name="group_by">
                                <option value="daily" <?php echo $filters['group_by'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="monthly" <?php echo $filters['group_by'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="yearly" <?php echo $filters['group_by'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Class</label>
                            <select class="form-select" name="class_id" id="class_id">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo intval($class['class_id']); ?>" <?php echo intval($filters['class_id']) === intval($class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo dateWiseCollectionReportEscape($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Section</label>
                            <select class="form-select" name="section_id">
                                <option value="0">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo intval($section['section_id']); ?>" <?php echo intval($filters['section_id']) === intval($section['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo dateWiseCollectionReportEscape($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" name="payment_mode">
                                <option value="">All Modes</option>
                                <option value="Cash" <?php echo $filters['payment_mode'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="UPI" <?php echo $filters['payment_mode'] === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                                <option value="Bank" <?php echo $filters['payment_mode'] === 'Bank' ? 'selected' : ''; ?>>Bank</option>
                                <option value="Cheque" <?php echo $filters['payment_mode'] === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Student Search</label>
                            <input
                                type="text"
                                class="form-control"
                                name="student_search"
                                value="<?php echo dateWiseCollectionReportEscape($filters['student_search']); ?>"
                                placeholder="Admission no, student name, or roll no"
                                autocomplete="off"
                                data-student-autocomplete="true"
                                data-student-autocomplete-fill="admission_no"
                                data-student-autocomplete-class="#class_id"
                                data-student-autocomplete-submit="#collectionReportSearchBtn"
                                data-student-autocomplete-id-target="#student_id"
                            >
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" id="collectionReportSearchBtn" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Generate Report
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <a href="date_wise_collection.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($hasFilters): ?>
    <div class="row mt-4">
        <div class="col-12">
            <?php echo $filterChipsHtml; ?>
            <?php if (!empty($selectedStudentHtml)): ?>
                <?php echo $selectedStudentHtml; ?>
            <?php endif; ?>
            <?php echo $summaryCardsHtml; ?>
        </div>
    </div>

    <?php echo $summaryTableHtml; ?>
    <?php echo $detailTableHtml; ?>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && count($_GET) > 0): ?>
    <div class="alert alert-warning mt-4">
        <i class="bi bi-exclamation-triangle"></i> Please select a date range or another filter to generate the collection report.
    </div>
<?php endif; ?>

<?php if ($hasFilters && intval($filters['student_id']) > 0 && empty($selectedStudent)): ?>
    <div class="alert alert-danger mt-4">
        <i class="bi bi-x-circle"></i> The selected student could not be found.
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
