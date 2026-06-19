<?php
/**
 * Due Fee Report
 * Student-wise, month-wise, class-month-wise, and yearly due fee views
 */

require_once '../../config/config.php';
require_once '../../includes/pdf_export.php';

requireLogin();
requirePermission('reports', 'view');

function dueFeeReportEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dueFeeReportBuildUrl(array $baseParams, array $overrides = []) {
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

    return APP_URL . '/modules/reports/due_fees.php?' . http_build_query($params);
}

function dueFeeReportViewLabel($view) {
    switch ($view) {
        case 'student_detail':
            return 'Per Student Detail';
        case 'month_wise':
            return 'Month Wise';
        case 'class_month_wise':
            return 'Class Month Wise';
        case 'yearly_wise':
            return 'Yearly Wise';
        default:
            return 'Student Search Wise';
    }
}

function dueFeeReportViewDescription($view) {
    switch ($view) {
        case 'student_detail':
            return 'Detailed fee position for one selected student.';
        case 'month_wise':
            return 'Outstanding fee items grouped by month and academic year.';
        case 'class_month_wise':
            return 'Outstanding fee items grouped by class and month.';
        case 'yearly_wise':
            return 'Outstanding fee items grouped by academic year.';
        default:
            return 'Outstanding fee position for matching students.';
    }
}

function dueFeeReportStatusClass($status) {
    switch (strtolower(trim((string)$status))) {
        case 'paid':
            return 'report-pill-success';
        case 'partial':
            return 'report-pill-warning';
        case 'unpaid':
            return 'report-pill-danger';
        default:
            return 'report-pill-secondary';
    }
}

function dueFeeReportStyles() {
    return <<<'CSS'
.report-shell {
    display: block;
}

.report-hero {
    background: #ffffff;
    border: 1px solid #d9e2ec;
    border-left: 6px solid #2563eb;
    border-radius: 8px;
    padding: 18px 20px;
}

.report-eyebrow {
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 700;
}

.report-title {
    margin: 4px 0 0 0;
    font-size: 1.5rem;
    line-height: 1.25;
    font-weight: 700;
    color: #111827;
}

.report-subtitle {
    margin-top: 6px;
    color: #6b7280;
    font-size: 0.95rem;
}

.report-chip-row,
.report-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.report-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.22rem 0.6rem;
    border: 1px solid #d8e2ee;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.8rem;
    line-height: 1.2;
    white-space: nowrap;
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
    color: #6b7280;
}

.report-summary-value {
    display: block;
    margin-top: 6px;
    font-size: 1.22rem;
    font-weight: 700;
    color: #111827;
    word-break: break-word;
}

.report-summary-note {
    display: block;
    margin-top: 4px;
    font-size: 0.8rem;
    color: #6b7280;
}

.report-tone-primary { border-top: 4px solid #2563eb; }
.report-tone-success { border-top: 4px solid #16a34a; }
.report-tone-warning { border-top: 4px solid #d97706; }
.report-tone-danger { border-top: 4px solid #dc2626; }
.report-tone-info { border-top: 4px solid #0891b2; }

.report-panel {
    background: #ffffff;
    border: 1px solid #dbe3ec;
    border-radius: 8px;
    overflow: hidden;
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
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
}

.report-panel-subtitle {
    margin-top: 4px;
    font-size: 0.92rem;
    color: #6b7280;
}

.report-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.report-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.38rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #ffffff;
    color: #1f2937;
    text-decoration: none;
    font-size: 0.84rem;
    line-height: 1.1;
    white-space: nowrap;
}

.report-action:hover {
    background: #f8fafc;
    color: #111827;
}

.report-action-primary {
    border-color: #2563eb;
    color: #1d4ed8;
}

.report-action-success {
    border-color: #16a34a;
    color: #166534;
}

.report-action-muted {
    border-color: #94a3b8;
    color: #475569;
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

.report-table th,
.report-table td {
    border-bottom: 1px solid #e5e7eb;
    padding: 0.78rem 0.9rem;
    vertical-align: top;
}

.report-table thead th {
    background: #0f172a;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.report-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.report-table tfoot th {
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
    color: #6b7280;
    font-size: 0.83rem;
    line-height: 1.3;
}

.report-empty {
    padding: 22px 18px;
    text-align: center;
    color: #6b7280;
    background: #f8fafc;
    border-top: 1px dashed #dbe3ec;
}

.report-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    border: 1px solid transparent;
    line-height: 1.15;
}

.report-pill-success {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}

.report-pill-warning {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}

.report-pill-danger {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
}

.report-pill-secondary {
    background: #e5e7eb;
    color: #374151;
    border-color: #d1d5db;
}

.report-pill-info {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
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
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.report-backlink {
    text-decoration: none;
}

@media print {
    .no-print {
        display: none !important;
    }

    body {
        background: #ffffff;
    }

    .report-shell {
        padding: 0;
    }

    .report-panel,
    .report-summary-card,
    .report-hero,
    .report-export-meta-item {
        box-shadow: none !important;
    }

    .report-table-wrap {
        overflow: visible !important;
    }
}

@page {
    size: A4 landscape;
    margin: 12mm;
}
CSS;
}

function dueFeeReportBuildGroups(array $rows, callable $keyCallback, callable $metaCallback, callable $sortCallback) {
    $groups = [];

    foreach ($rows as $row) {
        $key = $keyCallback($row);
        $meta = $metaCallback($row);

        if (!isset($groups[$key])) {
            $groups[$key] = array_merge($meta, [
                'student_ids' => [],
                'student_count' => 0,
                'item_count' => 0,
                'assigned_total' => 0.00,
                'paid_total' => 0.00,
                'due_total' => 0.00,
            ]);
        }

        $groups[$key]['student_ids'][intval($row['student_id'])] = true;
        $groups[$key]['item_count']++;
        $groups[$key]['assigned_total'] += floatval($row['original_amount'] ?? 0);
        $groups[$key]['paid_total'] += floatval($row['paid_amount'] ?? 0);
        $groups[$key]['due_total'] += floatval($row['due_amount'] ?? 0);
    }

    $groupList = array_values($groups);

    foreach ($groupList as &$group) {
        $group['student_count'] = count($group['student_ids']);
        unset($group['student_ids']);
        $group['assigned_total'] = round($group['assigned_total'], 2);
        $group['paid_total'] = round($group['paid_total'], 2);
        $group['due_total'] = round($group['due_total'], 2);
    }
    unset($group);

    usort($groupList, $sortCallback);

    return $groupList;
}

function dueFeeReportSummaryGridHtml(array $totals) {
    ob_start();
    ?>
    <div class="report-summary-grid">
        <div class="report-summary-card report-tone-primary">
            <span class="report-summary-label">Students</span>
            <span class="report-summary-value"><?php echo number_format(intval($totals['student_count'] ?? 0)); ?></span>
            <span class="report-summary-note">Students with dues in the selected scope</span>
        </div>
        <div class="report-summary-card report-tone-info">
            <span class="report-summary-label">Items</span>
            <span class="report-summary-value"><?php echo number_format(intval($totals['item_count'] ?? 0)); ?></span>
            <span class="report-summary-note">Pending fee rows</span>
        </div>
        <div class="report-summary-card report-tone-warning">
            <span class="report-summary-label">Assigned</span>
            <span class="report-summary-value"><?php echo formatCurrency(floatval($totals['assigned_total'] ?? 0)); ?></span>
            <span class="report-summary-note">Total fee assigned</span>
        </div>
        <div class="report-summary-card report-tone-success">
            <span class="report-summary-label">Paid</span>
            <span class="report-summary-value"><?php echo formatCurrency(floatval($totals['paid_total'] ?? 0)); ?></span>
            <span class="report-summary-note">Amount already collected</span>
        </div>
        <div class="report-summary-card report-tone-danger">
            <span class="report-summary-label">Due</span>
            <span class="report-summary-value"><?php echo formatCurrency(floatval($totals['due_total'] ?? 0)); ?></span>
            <span class="report-summary-note">Outstanding balance</span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dueFeeReportFilterChipsHtml($search, $classLabel, $sectionLabel, $asOfDate, $viewLabel) {
    ob_start();
    ?>
    <div class="report-chip-row">
        <span class="report-chip"><strong>View:</strong> <?php echo dueFeeReportEscape($viewLabel); ?></span>
        <span class="report-chip"><strong>Search:</strong> <?php echo $search !== '' ? dueFeeReportEscape($search) : 'All'; ?></span>
        <span class="report-chip"><strong>Class:</strong> <?php echo $classLabel !== '' ? dueFeeReportEscape($classLabel) : 'All'; ?></span>
        <span class="report-chip"><strong>Section:</strong> <?php echo $sectionLabel !== '' ? dueFeeReportEscape($sectionLabel) : 'All'; ?></span>
        <span class="report-chip"><strong>As of:</strong> <?php echo dueFeeReportEscape(formatDate($asOfDate)); ?></span>
    </div>
    <?php
    return ob_get_clean();
}

function dueFeeReportStudentSummaryTableHtml(array $students, array $totals, array $baseParams, $includeActions = true) {
    ob_start();
    $colspan = 6;
    $studentCount = intval($totals['student_count'] ?? count($students));
    $itemCount = intval($totals['item_count'] ?? 0);
    ?>
    <div class="report-panel">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title">Student Search Wise Due Fees</h3>
                <div class="report-panel-subtitle">Students who still have due fee items.</div>
            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width:60px;">S.No</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th class="report-center">Pending Items</th>
                        <th>Pending Periods</th>
                        <th class="report-num">Assigned</th>
                        <th class="report-num">Paid</th>
                        <th class="report-num">Due</th>
                        <?php if ($includeActions): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td class="report-center"><?php echo $index + 1; ?></td>
                                <td><strong><?php echo dueFeeReportEscape($student['admission_no'] ?? '-'); ?></strong></td>
                                <td>
                                    <strong><?php echo dueFeeReportEscape($student['student_name'] ?? '-'); ?></strong>
                                    <?php if (!empty($student['father_name'])): ?>
                                        <div class="report-subtle">Father: <?php echo dueFeeReportEscape($student['father_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($student['roll_no'])): ?>
                                        <div class="report-subtle">Roll No: <?php echo dueFeeReportEscape($student['roll_no']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo dueFeeReportEscape(trim(($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? '')) ?: '-'); ?></strong>
                                </td>
                                <td class="report-center"><?php echo intval($student['pending_item_count'] ?? 0); ?></td>
                                <td>
                                    <div class="report-chip-group">
                                        <?php foreach (($student['pending_periods'] ?? []) as $period): ?>
                                            <span class="report-pill report-pill-info"><?php echo dueFeeReportEscape($period); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="report-num"><?php echo formatCurrency(floatval($student['total_fee_assigned'] ?? 0)); ?></td>
                                <td class="report-num"><?php echo formatCurrency(floatval($student['total_paid'] ?? 0)); ?></td>
                                <td class="report-num"><strong><?php echo formatCurrency(floatval($student['due_amount'] ?? 0)); ?></strong></td>
                                <?php if ($includeActions): ?>
                                    <td>
                                        <div class="report-actions">
                                            <a class="report-action report-action-primary" href="<?php echo dueFeeReportBuildUrl($baseParams, ['view' => 'student_detail', 'student_id' => intval($student['student_id'])]); ?>">
                                                <i class="bi bi-person-badge"></i><span>Detail</span>
                                            </a>
                                            <a class="report-action report-action-success" href="<?php echo APP_URL; ?>/modules/fees/collect.php?student_id=<?php echo intval($student['student_id']); ?>">
                                                <i class="bi bi-cash-coin"></i><span>Collect</span>
                                            </a>
                                            <a class="report-action report-action-muted" href="<?php echo APP_URL; ?>/modules/fees/receipts.php?search=<?php echo urlencode($student['admission_no'] ?? ''); ?>">
                                                <i class="bi bi-receipt"></i><span>Receipts</span>
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $includeActions ? 10 : 9; ?>">
                                <div class="report-empty">No due students found for the selected filters.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="<?php echo $colspan; ?>" class="report-num">
                            Students: <?php echo number_format($studentCount); ?> | Pending Items: <?php echo number_format($itemCount); ?>
                        </th>
                        <th class="report-num"><?php echo formatCurrency(floatval($totals['assigned_total'] ?? 0)); ?></th>
                        <th class="report-num"><?php echo formatCurrency(floatval($totals['paid_total'] ?? 0)); ?></th>
                        <th class="report-num"><?php echo formatCurrency(floatval($totals['due_total'] ?? 0)); ?></th>
                        <?php if ($includeActions): ?><th></th><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dueFeeReportStudentDetailHtml(array $detailStudent, array $detailSummary, array $baseParams, $includeActions = true) {
    $items = $detailSummary['fee_items'] ?? [];
    $summaryCards = [
        ['label' => 'Items', 'value' => number_format(count($items)), 'note' => 'All fee rows for this student', 'tone' => 'primary'],
        ['label' => 'Assigned', 'value' => formatCurrency(floatval($detailSummary['assigned_total'] ?? 0)), 'note' => 'Total fee assigned', 'tone' => 'warning'],
        ['label' => 'Paid', 'value' => formatCurrency(floatval($detailSummary['paid_total'] ?? 0)), 'note' => 'Collected so far', 'tone' => 'success'],
        ['label' => 'Due', 'value' => formatCurrency(floatval($detailSummary['due_total'] ?? 0)), 'note' => 'Outstanding balance', 'tone' => 'danger'],
    ];

    ob_start();
    ?>
    <div class="report-panel">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title">
                    <?php echo dueFeeReportEscape($detailStudent['student_name'] ?? 'Student Detail'); ?>
                </h3>
                <div class="report-panel-subtitle">
                    Admission No: <?php echo dueFeeReportEscape($detailStudent['admission_no'] ?? '-'); ?>
                    <?php if (!empty($detailStudent['roll_no'])): ?> | Roll No: <?php echo dueFeeReportEscape($detailStudent['roll_no']); ?><?php endif; ?>
                    <?php if (!empty($detailStudent['class_name']) || !empty($detailStudent['section_name'])): ?>
                        | Class: <?php echo dueFeeReportEscape(trim(($detailStudent['class_name'] ?? '') . ' ' . ($detailStudent['section_name'] ?? ''))); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($includeActions && !empty($detailStudent['student_id'])): ?>
                <div class="report-actions">
                    <a class="report-action report-action-success" href="<?php echo APP_URL; ?>/modules/fees/collect.php?student_id=<?php echo intval($detailStudent['student_id']); ?>">
                        <i class="bi bi-cash-coin"></i><span>Collect Fee</span>
                    </a>
                    <a class="report-action report-action-muted" href="<?php echo APP_URL; ?>/modules/fees/receipts.php?search=<?php echo urlencode($detailStudent['admission_no'] ?? ''); ?>">
                        <i class="bi bi-receipt"></i><span>Receipts</span>
                    </a>
                    <?php
                    $summaryBackParams = $baseParams;
                    unset($summaryBackParams['student_id']);
                    ?>
                    <a class="report-action report-action-primary" href="<?php echo dueFeeReportBuildUrl($summaryBackParams, ['view' => 'student_summary']); ?>">
                        <i class="bi bi-list-ul"></i><span>Back to Search</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div style="padding: 16px 18px 0;">
            <div class="report-summary-grid">
                <?php foreach ($summaryCards as $card): ?>
                    <div class="report-summary-card report-tone-<?php echo dueFeeReportEscape($card['tone']); ?>">
                        <span class="report-summary-label"><?php echo dueFeeReportEscape($card['label']); ?></span>
                        <span class="report-summary-value"><?php echo dueFeeReportEscape($card['value']); ?></span>
                        <span class="report-summary-note"><?php echo dueFeeReportEscape($card['note']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width:60px;">S.No</th>
                        <th>Period</th>
                        <th>Fee Head</th>
                        <th>Type</th>
                        <th class="report-num">Assigned</th>
                        <th class="report-num">Paid</th>
                        <th class="report-num">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td class="report-center"><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo dueFeeReportEscape($item['period_label'] ?? '-'); ?></strong>
                                    <?php if (!empty($item['academic_year_label'])): ?>
                                        <div class="report-subtle">Academic Year: <?php echo dueFeeReportEscape($item['academic_year_label']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo dueFeeReportEscape($item['fee_head_name'] ?? '-'); ?></td>
                                <td><?php echo dueFeeReportEscape($item['display_fee_type'] ?? '-'); ?></td>
                                <td class="report-num"><?php echo formatCurrency(floatval($item['original_amount'] ?? 0)); ?></td>
                                <td class="report-num"><?php echo formatCurrency(floatval($item['paid_amount'] ?? 0)); ?></td>
                                <td class="report-num"><strong><?php echo formatCurrency(floatval($item['due_amount'] ?? 0)); ?></strong></td>
                                <td>
                                    <span class="report-pill <?php echo dueFeeReportStatusClass($item['status'] ?? ''); ?>">
                                        <?php echo dueFeeReportEscape($item['status'] ?? 'Unpaid'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="report-empty">No fee items found for the selected student.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="report-num">
                            Items: <?php echo number_format(count($items)); ?>
                        </th>
                        <th class="report-num"><?php echo formatCurrency(floatval($detailSummary['assigned_total'] ?? 0)); ?></th>
                        <th class="report-num"><?php echo formatCurrency(floatval($detailSummary['paid_total'] ?? 0)); ?></th>
                        <th class="report-num"><?php echo formatCurrency(floatval($detailSummary['due_total'] ?? 0)); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dueFeeReportGroupedTableHtml(array $groups, $view, array $pageTotals) {
    ob_start();
    $groupCount = count($groups);
    ?>
    <div class="report-panel">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title"><?php echo dueFeeReportEscape(dueFeeReportViewLabel($view)); ?></h3>
                <div class="report-panel-subtitle"><?php echo dueFeeReportEscape(dueFeeReportViewDescription($view)); ?></div>
            </div>
        </div>
        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <?php if ($view === 'month_wise'): ?>
                        <tr>
                            <th style="width:60px;">S.No</th>
                            <th>Period</th>
                            <th>Academic Year</th>
                            <th class="report-center">Students</th>
                            <th class="report-center">Items</th>
                            <th class="report-num">Assigned</th>
                            <th class="report-num">Paid</th>
                            <th class="report-num">Due</th>
                        </tr>
                    <?php elseif ($view === 'class_month_wise'): ?>
                        <tr>
                            <th style="width:60px;">S.No</th>
                            <th>Class / Section</th>
                            <th>Period</th>
                            <th class="report-center">Students</th>
                            <th class="report-center">Items</th>
                            <th class="report-num">Assigned</th>
                            <th class="report-num">Paid</th>
                            <th class="report-num">Due</th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th style="width:60px;">S.No</th>
                            <th>Academic Year</th>
                            <th class="report-center">Students</th>
                            <th class="report-center">Items</th>
                            <th class="report-num">Assigned</th>
                            <th class="report-num">Paid</th>
                            <th class="report-num">Due</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (!empty($groups)): ?>
                        <?php foreach ($groups as $index => $group): ?>
                            <tr>
                                <td class="report-center"><?php echo $index + 1; ?></td>
                                <?php if ($view === 'month_wise'): ?>
                                    <td>
                                        <strong><?php echo dueFeeReportEscape($group['group_title'] ?? '-'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="report-pill report-pill-info"><?php echo dueFeeReportEscape($group['group_subtitle'] ?? '-'); ?></span>
                                    </td>
                                <?php elseif ($view === 'class_month_wise'): ?>
                                    <td>
                                        <strong><?php echo dueFeeReportEscape($group['group_title'] ?? '-'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="report-pill report-pill-info"><?php echo dueFeeReportEscape($group['group_subtitle'] ?? '-'); ?></span>
                                    </td>
                                <?php else: ?>
                                    <td>
                                        <strong><?php echo dueFeeReportEscape($group['group_title'] ?? '-'); ?></strong>
                                        <?php if (!empty($group['group_subtitle'])): ?>
                                            <div class="report-subtle"><?php echo dueFeeReportEscape($group['group_subtitle']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <td class="report-center"><?php echo number_format(intval($group['student_count'] ?? 0)); ?></td>
                                <td class="report-center"><?php echo number_format(intval($group['item_count'] ?? 0)); ?></td>
                                <td class="report-num"><?php echo formatCurrency(floatval($group['assigned_total'] ?? 0)); ?></td>
                                <td class="report-num"><?php echo formatCurrency(floatval($group['paid_total'] ?? 0)); ?></td>
                                <td class="report-num"><strong><?php echo formatCurrency(floatval($group['due_total'] ?? 0)); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $view === 'yearly_wise' ? 7 : 8; ?>">
                                <div class="report-empty">No due fee data found for the selected filters.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php if ($view === 'month_wise'): ?>
                        <tr>
                            <th colspan="5" class="report-num">
                                Groups: <?php echo number_format($groupCount); ?> | Students: <?php echo number_format(intval($pageTotals['student_count'] ?? 0)); ?> | Items: <?php echo number_format(intval($pageTotals['item_count'] ?? 0)); ?>
                            </th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['assigned_total'] ?? 0)); ?></th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['paid_total'] ?? 0)); ?></th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['due_total'] ?? 0)); ?></th>
                        </tr>
                    <?php elseif ($view === 'class_month_wise'): ?>
                        <tr>
                            <th colspan="5" class="report-num">
                                Groups: <?php echo number_format($groupCount); ?> | Students: <?php echo number_format(intval($pageTotals['student_count'] ?? 0)); ?> | Items: <?php echo number_format(intval($pageTotals['item_count'] ?? 0)); ?>
                            </th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['assigned_total'] ?? 0)); ?></th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['paid_total'] ?? 0)); ?></th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['due_total'] ?? 0)); ?></th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th colspan="4" class="report-num">
                                Groups: <?php echo number_format($groupCount); ?> | Students: <?php echo number_format(intval($pageTotals['student_count'] ?? 0)); ?> | Items: <?php echo number_format(intval($pageTotals['item_count'] ?? 0)); ?>
                            </th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['assigned_total'] ?? 0)); ?></th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['paid_total'] ?? 0)); ?></th>
                            <th class="report-num"><?php echo formatCurrency(floatval($pageTotals['due_total'] ?? 0)); ?></th>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function dueFeeReportRenderMainSection($view, array $studentRows, array $feeRows, array $groupsMonth, array $groupsClassMonth, array $groupsYear, array $detailStudent, array $detailSummary, array $pageTotals, array $baseParams, $selectedStudentId, $includeActions = true) {
    ob_start();

    if ($view === 'student_detail') {
        if (!empty($detailStudent) && intval($selectedStudentId) > 0) {
            echo dueFeeReportStudentDetailHtml($detailStudent, $detailSummary, $baseParams, $includeActions);
        } elseif (!empty($studentRows) && count($studentRows) === 1) {
            $singleStudent = $studentRows[0];
            $singleSummary = getStudentFeeSummary(intval($singleStudent['student_id']), $baseParams['as_of_date'] ?? null);
            $singleStudentInfo = array_merge(
                $singleSummary['student'] ?? [],
                [
                    'student_id' => intval($singleStudent['student_id']),
                    'student_name' => $singleStudent['student_name'] ?? '',
                    'admission_no' => $singleStudent['admission_no'] ?? '',
                    'roll_no' => $singleStudent['roll_no'] ?? '',
                    'father_name' => $singleStudent['father_name'] ?? '',
                    'mother_name' => $singleStudent['mother_name'] ?? '',
                    'contact_no' => $singleStudent['contact_no'] ?? '',
                    'class_name' => $singleStudent['class_name'] ?? '',
                    'section_name' => $singleStudent['section_name'] ?? '',
                ]
            );
            echo dueFeeReportStudentDetailHtml($singleStudentInfo, $singleSummary, $baseParams, $includeActions);
        } elseif (!empty($studentRows)) {
            echo dueFeeReportStudentSummaryTableHtml($studentRows, $pageTotals, $baseParams, $includeActions);
        } else {
            echo '<div class="report-panel"><div class="report-empty">No student data found for the selected filters.</div></div>';
        }
    } elseif ($view === 'month_wise') {
        echo dueFeeReportGroupedTableHtml($groupsMonth, $view, $pageTotals);
    } elseif ($view === 'class_month_wise') {
        echo dueFeeReportGroupedTableHtml($groupsClassMonth, $view, $pageTotals);
    } elseif ($view === 'yearly_wise') {
        echo dueFeeReportGroupedTableHtml($groupsYear, $view, $pageTotals);
    } else {
        echo dueFeeReportStudentSummaryTableHtml($studentRows, $pageTotals, $baseParams, $includeActions);
    }

    return ob_get_clean();
}

function dueFeeReportRenderExportDocument($mode, $schoolName, $view, $filtersHtml, $summaryGridHtml, $mainHtml, $backUrl = '') {
    $title = 'Due Fee Report - ' . dueFeeReportViewLabel($view);
    $css = dueFeeReportStyles();
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
        <title><?php echo dueFeeReportEscape($title); ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <style><?php echo $css; ?></style>
    </head>
    <body>
        <div class="report-shell" style="padding: 18px 22px 26px;">
            <?php if ($mode === 'pdf'): ?>
                <div class="report-print-toolbar no-print">
                    <a class="report-action report-action-muted report-backlink" href="<?php echo dueFeeReportEscape($backUrl); ?>">
                        <i class="bi bi-arrow-left"></i><span>Back to Report</span>
                    </a>
                    <button class="report-action report-action-primary" type="button" onclick="window.print();">
                        <i class="bi bi-printer"></i><span>Print / Save PDF</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="report-hero">
                <div class="report-eyebrow"><?php echo dueFeeReportEscape($schoolName); ?></div>
                <h1 class="report-title"><?php echo dueFeeReportEscape($title); ?></h1>
                <div class="report-subtitle"><?php echo dueFeeReportEscape(dueFeeReportViewDescription($view)); ?></div>
                <?php if ($schoolAddress !== '' || $schoolPhone !== '' || $schoolEmail !== ''): ?>
                    <div class="report-export-meta" style="margin-top: 10px;">
                        <?php if ($schoolAddress !== ''): ?>
                            <div class="report-export-meta-item">
                                <div class="report-export-meta-label">Address</div>
                                <div class="report-export-meta-value"><?php echo dueFeeReportEscape($schoolAddress); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($schoolPhone !== ''): ?>
                            <div class="report-export-meta-item">
                                <div class="report-export-meta-label">Phone</div>
                                <div class="report-export-meta-value"><?php echo dueFeeReportEscape($schoolPhone); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($schoolEmail !== ''): ?>
                            <div class="report-export-meta-item">
                                <div class="report-export-meta-label">Email</div>
                                <div class="report-export-meta-value"><?php echo dueFeeReportEscape($schoolEmail); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php echo $filtersHtml; ?>
            </div>

            <?php echo $summaryGridHtml; ?>

            <?php echo $mainHtml; ?>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

$pageTitle = 'Due Fee Report';
$currentUser = getCurrentUser();
$schoolSettings = getSchoolSettings();
$schoolName = $schoolSettings['school_name'] ?? APP_NAME;
$today = date('Y-m-d');

$validViews = ['student_summary', 'student_detail', 'month_wise', 'class_month_wise', 'yearly_wise'];
$view = strtolower(trim((string)($_GET['view'] ?? 'student_summary')));
if (!in_array($view, $validViews, true)) {
    $view = 'student_summary';
}

$search = trim((string)($_GET['search'] ?? ''));
$classId = intval($_GET['class_id'] ?? 0);
$sectionId = intval($_GET['section_id'] ?? 0);
$studentId = intval($_GET['student_id'] ?? 0);
$asOfDate = trim((string)($_GET['as_of_date'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate) || strtotime($asOfDate) === false) {
    $asOfDate = $today;
}

$classes = fetchAll("SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name");

$classMap = [];
foreach ($classes as $class) {
    $classMap[intval($class['class_id'])] = $class['class_name'];
}

$sectionMap = [];
foreach ($sections as $section) {
    $sectionMap[intval($section['section_id'])] = $section['section_name'];
}

$baseParams = [
    'view' => $view,
    'search' => $search,
    'class_id' => $classId,
    'section_id' => $sectionId,
    'as_of_date' => $asOfDate,
];

if ($studentId > 0) {
    $baseParams['student_id'] = $studentId;
}

$dataset = getDueFeeReportDataset($search, $classId, $sectionId, $studentId, $asOfDate);
$studentRows = $dataset['students'] ?? [];
$feeRows = $dataset['rows'] ?? [];
$reportTotals = $dataset['totals'] ?? [
    'student_count' => 0,
    'item_count' => 0,
    'assigned_total' => 0.00,
    'paid_total' => 0.00,
    'due_total' => 0.00,
];

$selectedStudentId = $studentId;
$detailStudent = [];
$detailSummary = [
    'student' => null,
    'assigned_total' => 0.00,
    'paid_total' => 0.00,
    'due_total' => 0.00,
    'fee_items' => [],
    'pending_items' => [],
];

if ($view === 'student_detail') {
    if ($selectedStudentId <= 0 && count($studentRows) === 1) {
        $selectedStudentId = intval($studentRows[0]['student_id']);
    }

    if ($selectedStudentId <= 0 && $search !== '') {
        $searchQuery = "SELECT s.student_id, s.student_name, s.admission_no, s.roll_no, s.father_name, s.mother_name, s.contact_no,
                               c.class_name, sec.section_name
                        FROM students s
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        LEFT JOIN sections sec ON s.section_id = sec.section_id
                        WHERE s.status = 'Active'
                          AND (
                                s.student_name LIKE ? OR
                                s.admission_no LIKE ? OR
                                s.roll_no LIKE ? OR
                                s.father_name LIKE ? OR
                                c.class_name LIKE ? OR
                                sec.section_name LIKE ?
                          )";
        $searchParams = [];
        $searchTypes = 'ssssss';
        $searchPattern = '%' . $search . '%';
        for ($i = 0; $i < 6; $i++) {
            $searchParams[] = $searchPattern;
        }

        if ($classId > 0) {
            $searchQuery .= " AND s.class_id = ?";
            $searchParams[] = $classId;
            $searchTypes .= 'i';
        }

        if ($sectionId > 0) {
            $searchQuery .= " AND s.section_id = ?";
            $searchParams[] = $sectionId;
            $searchTypes .= 'i';
        }

        $searchQuery .= " ORDER BY c.class_order, sec.section_name, s.student_name LIMIT 2";
        $matchingStudents = fetchAll($searchQuery, $searchTypes, $searchParams);

        if (count($matchingStudents) === 1) {
            $selectedStudentId = intval($matchingStudents[0]['student_id']);
            $detailStudent = $matchingStudents[0];
        }
    }

    if ($selectedStudentId > 0) {
        $detailStudent = fetchOne(
            "SELECT s.student_id, s.student_name, s.admission_no, s.roll_no, s.father_name, s.mother_name, s.contact_no,
                    c.class_name, sec.section_name
             FROM students s
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE s.student_id = ?",
            'i',
            [$selectedStudentId]
        ) ?: [];

        $detailSummary = getStudentFeeSummary($selectedStudentId, $asOfDate);

        if (!empty($detailStudent) && !empty($detailSummary['student'])) {
            $detailSummary['student'] = array_merge($detailSummary['student'], $detailStudent);
        } elseif (!empty($detailStudent)) {
            $detailSummary['student'] = $detailStudent;
        }
    }
}

$detailItems = $detailSummary['fee_items'] ?? [];

$pageTotals = $reportTotals;
if ($view === 'student_detail' && $selectedStudentId > 0) {
    $pageTotals = [
        'student_count' => !empty($detailSummary['student']) ? 1 : 0,
        'item_count' => count($detailItems),
        'assigned_total' => round(floatval($detailSummary['assigned_total'] ?? 0), 2),
        'paid_total' => round(floatval($detailSummary['paid_total'] ?? 0), 2),
        'due_total' => round(floatval($detailSummary['due_total'] ?? 0), 2),
    ];
}

$monthGroups = dueFeeReportBuildGroups(
    $feeRows,
    function ($row) {
        return trim(($row['period_label'] ?? '') . '|' . ($row['academic_year_label'] ?? ''));
    },
    function ($row) {
        return [
            'group_title' => $row['period_label'] ?? 'Unknown',
            'group_subtitle' => $row['academic_year_label'] ?? '',
            'sort_key' => intval($row['period_sort_key'] ?? 0),
        ];
    },
    function ($left, $right) {
        $sortCompare = intval($left['sort_key'] ?? 0) <=> intval($right['sort_key'] ?? 0);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return strcmp($left['group_title'] ?? '', $right['group_title'] ?? '');
    }
);

$classMonthGroups = dueFeeReportBuildGroups(
    $feeRows,
    function ($row) {
        return trim(
            intval($row['class_order'] ?? 0) . '|' .
            ($row['class_name'] ?? '') . '|' .
            ($row['section_name'] ?? '') . '|' .
            ($row['period_label'] ?? '')
        );
    },
    function ($row) {
        $classLabel = trim(($row['class_name'] ?? '') . ' ' . ($row['section_name'] ?? ''));
        return [
            'group_title' => $classLabel !== '' ? $classLabel : 'Unassigned',
            'group_subtitle' => $row['period_label'] ?? 'Unknown',
            'sort_key' => (intval($row['class_order'] ?? 0) * 100000) + intval($row['period_sort_key'] ?? 0),
        ];
    },
    function ($left, $right) {
        $sortCompare = intval($left['sort_key'] ?? 0) <=> intval($right['sort_key'] ?? 0);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return strcmp($left['group_title'] ?? '', $right['group_title'] ?? '');
    }
);

$yearGroups = dueFeeReportBuildGroups(
    $feeRows,
    function ($row) {
        return trim((string)($row['academic_year_label'] ?? ''));
    },
    function ($row) {
        return [
            'group_title' => $row['academic_year_label'] ?? 'Unknown',
            'group_subtitle' => 'Academic Session',
            'sort_key' => intval($row['academic_year_start'] ?? 0),
        ];
    },
    function ($left, $right) {
        $sortCompare = intval($left['sort_key'] ?? 0) <=> intval($right['sort_key'] ?? 0);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return strcmp($left['group_title'] ?? '', $right['group_title'] ?? '');
    }
);

$summaryGridHtml = dueFeeReportSummaryGridHtml($pageTotals);
$filtersHtml = dueFeeReportFilterChipsHtml(
    $search,
    $classMap[$classId] ?? '',
    $sectionMap[$sectionId] ?? '',
    $asOfDate,
    dueFeeReportViewLabel($view)
);

$viewTabs = [
    'student_summary' => 'Student Search',
    'student_detail' => 'Student Detail',
    'month_wise' => 'Month Wise',
    'class_month_wise' => 'Class Month Wise',
    'yearly_wise' => 'Yearly Wise',
];

$viewTabHtml = '<div class="report-panel mb-3"><div class="p-3"><div class="nav nav-pills flex-wrap gap-2">';
foreach ($viewTabs as $tabKey => $tabLabel) {
    $tabParams = $baseParams;
    if ($tabKey !== 'student_detail') {
        unset($tabParams['student_id']);
    } elseif ($selectedStudentId <= 0) {
        unset($tabParams['student_id']);
    }

    $tabUrl = dueFeeReportBuildUrl($tabParams, ['view' => $tabKey]);
    $tabActive = $view === $tabKey ? ' active' : '';
    $viewTabHtml .= '<a class="nav-link' . $tabActive . '" href="' . dueFeeReportEscape($tabUrl) . '">' . dueFeeReportEscape($tabLabel) . '</a>';
}
$viewTabHtml .= '</div></div></div>';

$mainSectionHtml = dueFeeReportRenderMainSection(
    $view,
    $studentRows,
    $feeRows,
    $monthGroups,
    $classMonthGroups,
    $yearGroups,
    $detailStudent,
    $detailSummary,
    $pageTotals,
    $baseParams,
    $selectedStudentId,
    true
);

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($export === 'excel' || $export === 'pdf') {
    $exportMainSectionHtml = dueFeeReportRenderMainSection(
        $view,
        $studentRows,
        $feeRows,
        $monthGroups,
        $classMonthGroups,
        $yearGroups,
        $detailStudent,
        $detailSummary,
        $pageTotals,
        $baseParams,
        $selectedStudentId,
        false
    );
    $exportHtml = dueFeeReportRenderExportDocument(
        $export === 'pdf' ? 'pdf' : 'excel',
        $schoolName,
        $view,
        $filtersHtml,
        $summaryGridHtml,
        $exportMainSectionHtml,
        dueFeeReportBuildUrl($baseParams, ['view' => $view])
    );

    if (ob_get_level()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    if ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', 'Due_Fee_Report_' . dueFeeReportViewLabel($view) . '_' . date('Y-m-d')) . '.xls';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        echo $exportHtml;
    } else {
        $pdfResult = pdfExportDownloadHtml($exportHtml, 'Due_Fee_Report_' . dueFeeReportViewLabel($view) . '_' . date('Y-m-d'));
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
<?php echo dueFeeReportStyles(); ?>
</style>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-exclamation-circle"></i> Due Fee Report
                </h2>
                <div class="text-muted"><?php echo dueFeeReportEscape(dueFeeReportViewDescription($view)); ?></div>
            </div>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="view" value="<?php echo dueFeeReportEscape($view); ?>">
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Student Search</label>
                            <input
                                type="text"
                                class="form-control"
                                name="search"
                                id="due_fee_search"
                                value="<?php echo dueFeeReportEscape($search); ?>"
                                placeholder="Admission no, name, roll no, or class"
                                data-student-autocomplete="true"
                                data-student-autocomplete-class="#due_fee_class"
                                data-student-autocomplete-submit="#dueFeeSearchBtn"
                            >
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Class</label>
                            <select class="form-select" name="class_id" id="due_fee_class">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo intval($class['class_id']); ?>" <?php echo $classId == intval($class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo dueFeeReportEscape($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Section</label>
                            <select class="form-select" name="section_id">
                                <option value="0">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo intval($section['section_id']); ?>" <?php echo $sectionId == intval($section['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo dueFeeReportEscape($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">As Of Date</label>
                            <input type="date" class="form-control" name="as_of_date" value="<?php echo dueFeeReportEscape($asOfDate); ?>">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="dueFeeSearchBtn">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="report-hero">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="report-eyebrow"><?php echo dueFeeReportEscape($schoolName); ?></div>
                    <h1 class="report-title">Due Fee Report</h1>
                    <div class="report-subtitle"><?php echo dueFeeReportEscape(dueFeeReportViewDescription($view)); ?></div>
                </div>
                <div class="report-actions no-print">
                    <a class="report-action report-action-primary" href="<?php echo dueFeeReportEscape(dueFeeReportBuildUrl($baseParams, ['export' => 'excel'])); ?>">
                        <i class="bi bi-file-earmark-excel"></i><span>Export Excel</span>
                    </a>
                    <a class="report-action report-action-muted" href="<?php echo dueFeeReportEscape(dueFeeReportBuildUrl($baseParams, ['export' => 'pdf'])); ?>" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i><span>Print PDF</span>
                    </a>
                </div>
            </div>
            <div style="margin-top: 12px;">
                <?php echo $filtersHtml; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <?php echo $viewTabHtml; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <?php echo $summaryGridHtml; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php echo $mainSectionHtml; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
