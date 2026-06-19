<?php
/**
 * Student List Report
 * Full student listing with search, filters, Excel export, and print/PDF view
 */

require_once '../../config/config.php';
require_once '../../includes/pdf_export.php';

requireLogin();
requirePermission('reports', 'view');

function studentListReportEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function studentListReportValue($value, $default = '-') {
    $value = trim((string)$value);
    return $value === '' ? $default : $value;
}

function studentListReportDate($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '-' : date('d-M-Y', $timestamp);
}

function studentListReportInitials($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return 'ST';
    }

    $parts = preg_split('/\s+/', $name);
    $letters = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $letters .= strtoupper(substr($part, 0, 1));
        if (strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? substr($letters, 0, 2) : 'ST';
}

function studentListReportNormalizeStatus($status) {
    $status = trim((string)$status);
    if ($status === '' || strtolower($status) === 'all') {
        return '';
    }

    return $status;
}

function studentListReportStatusLabel($status) {
    $status = trim((string)$status);

    if ($status === '') {
        return 'All Students';
    }

    switch (strtolower($status)) {
        case 'active':
            return 'Active';
        case 'inactive':
            return 'Inactive';
        case 'passout':
            return 'Passout';
        default:
            return $status;
    }
}

function studentListReportStatusClass($status) {
    switch (strtolower(trim((string)$status))) {
        case 'active':
            return 'success';
        case 'inactive':
            return 'secondary';
        case 'passout':
            return 'warning text-dark';
        default:
            return 'info text-dark';
    }
}

function studentListReportBuildUrl(array $baseParams, array $overrides = []) {
    $params = array_merge($baseParams, $overrides);

    foreach ($params as $key => $value) {
        if ($key === 'status') {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = (string)$value;
            }
            continue;
        }

        if ($value === '' || $value === null) {
            unset($params[$key]);
            continue;
        }

        if (in_array($key, ['class_id', 'section_id'], true) && intval($value) <= 0) {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return APP_URL . '/modules/reports/student_list.php' . ($query !== '' ? '?' . $query : '');
}

function studentListReportStyles() {
    return <<<'CSS'
.report-shell {
    display: block;
}

.report-hero {
    background: #ffffff;
    border: 1px solid #dbe3ec;
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
    font-size: 1.55rem;
    line-height: 1.25;
    font-weight: 700;
    color: #111827;
}

.report-subtitle {
    margin-top: 6px;
    color: #6b7280;
    font-size: 0.95rem;
}

.report-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}

.report-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.3rem 0.7rem;
    border: 1px solid #d8e2ee;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.8rem;
    line-height: 1.25;
    white-space: normal;
    overflow-wrap: anywhere;
}

.report-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin: 16px 0 18px;
}

.report-summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    min-height: 90px;
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
.report-tone-muted { border-top: 4px solid #64748b; }

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
    font-size: 1.08rem;
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
    padding: 0.4rem 0.8rem;
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
    min-width: 1180px;
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
    white-space: nowrap;
}

.report-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.report-table tfoot th {
    background: #eef2f7;
    font-weight: 700;
}

.report-center {
    text-align: center;
}

.report-num {
    text-align: right;
    white-space: nowrap;
}

.report-subtle {
    color: #6b7280;
    font-size: 0.82rem;
    line-height: 1.35;
}

.report-empty {
    padding: 24px 18px;
    text-align: center;
    color: #6b7280;
    background: #f8fafc;
}

.report-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    border: 1px solid transparent;
    line-height: 1.15;
}

.student-avatar {
    width: 42px;
    height: 54px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dbe3ec;
    background: #ffffff;
}

.student-avatar-fallback {
    width: 42px;
    height: 54px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    border: 1px solid #dbe3ec;
    background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
    color: #1d4ed8;
    font-weight: 700;
    font-size: 0.9rem;
    letter-spacing: 0.02em;
}

.report-print-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.report-export-meta {
    margin-top: 8px;
    color: #64748b;
    font-size: 0.86rem;
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

    .report-hero,
    .report-panel,
    .report-summary-card {
        box-shadow: none !important;
    }

    .report-table {
        min-width: 1000px;
    }
}

@page {
    size: landscape;
    margin: 10mm;
}
CSS;
}

function studentListReportFetchStudents($search, $classId, $sectionId, $status, $schoolId = 0) {
    $query = "SELECT
                s.student_id,
                s.admission_no,
                s.student_name,
                s.roll_no,
                s.photo,
                s.date_of_birth,
                s.gender,
                s.father_name,
                s.mother_name,
                s.contact_no,
                s.email,
                s.address,
                s.admission_date,
                s.status,
                c.class_name,
                c.class_order,
                sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE 1=1";

    $params = [];
    $types = '';

    if (intval($schoolId) > 0) {
        $query .= " AND s.school_id = ?";
        $params[] = intval($schoolId);
        $types .= 'i';
    }

    if ($search !== '') {
        $query .= " AND (
                        s.student_name LIKE ? OR
                        s.admission_no LIKE ? OR
                        s.roll_no LIKE ? OR
                        s.father_name LIKE ? OR
                        s.mother_name LIKE ? OR
                        s.contact_no LIKE ? OR
                        s.email LIKE ? OR
                        c.class_name LIKE ? OR
                        sec.section_name LIKE ?
                    )";
        $pattern = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 9, $pattern));
        $types .= 'sssssssss';
    }

    if ($classId > 0) {
        $query .= " AND s.class_id = ?";
        $params[] = $classId;
        $types .= 'i';
    }

    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $params[] = $sectionId;
        $types .= 'i';
    }

    if ($status !== '') {
        $query .= " AND s.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $query .= " ORDER BY COALESCE(c.class_order, 9999), COALESCE(c.class_name, ''), COALESCE(sec.section_name, ''), s.roll_no, s.student_name";

    return empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
}

function studentListReportStats($students) {
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'passout' => 0,
        'with_photo' => 0,
        'classes' => [],
    ];

    foreach ($students as $student) {
        $stats['total']++;

        $status = strtolower(trim((string)($student['status'] ?? '')));
        if (isset($stats[$status])) {
            $stats[$status]++;
        }

        if (!empty($student['photo'])) {
            $stats['with_photo']++;
        }

        $className = trim((string)($student['class_name'] ?? ''));
        if ($className !== '') {
            $stats['classes'][$className] = true;
        }
    }

    $stats['class_count'] = count($stats['classes']);
    return $stats;
}

function studentListReportAvatarMarkup($student) {
    $photoSrc = getStudentPhotoSrc($student['photo'] ?? '');
    $alt = studentListReportValue($student['student_name'] ?? 'Student');

    if (!empty($photoSrc)) {
        return '<img src="' . studentListReportEscape($photoSrc) . '" class="student-avatar" alt="' . studentListReportEscape($alt) . '">';
    }

    return '<div class="student-avatar-fallback">' . studentListReportEscape(studentListReportInitials($alt)) . '</div>';
}

function studentListReportFilterChips($search, $classLabel, $sectionLabel, $statusLabel, $totalRows, $generatedAt) {
    ob_start();
    ?>
    <div class="report-chip-row">
        <span class="report-chip"><strong>Search:</strong> <?php echo studentListReportEscape($search !== '' ? $search : 'All'); ?></span>
        <span class="report-chip"><strong>Class:</strong> <?php echo studentListReportEscape($classLabel); ?></span>
        <span class="report-chip"><strong>Section:</strong> <?php echo studentListReportEscape($sectionLabel); ?></span>
        <span class="report-chip"><strong>Status:</strong> <?php echo studentListReportEscape($statusLabel); ?></span>
        <span class="report-chip"><strong>Rows:</strong> <?php echo intval($totalRows); ?></span>
        <span class="report-chip"><strong>Generated:</strong> <?php echo studentListReportEscape($generatedAt); ?></span>
    </div>
    <?php
    return ob_get_clean();
}

function studentListReportSummaryCards($stats) {
    ob_start();
    ?>
    <div class="report-summary-grid">
        <div class="report-summary-card report-tone-primary">
            <span class="report-summary-label">Total Students</span>
            <span class="report-summary-value"><?php echo intval($stats['total']); ?></span>
            <span class="report-summary-note">Across the current filters</span>
        </div>
        <div class="report-summary-card report-tone-success">
            <span class="report-summary-label">Active</span>
            <span class="report-summary-value"><?php echo intval($stats['active']); ?></span>
            <span class="report-summary-note">Currently active records</span>
        </div>
        <div class="report-summary-card report-tone-muted">
            <span class="report-summary-label">Inactive</span>
            <span class="report-summary-value"><?php echo intval($stats['inactive']); ?></span>
            <span class="report-summary-note">Inactive records in view</span>
        </div>
        <div class="report-summary-card report-tone-warning">
            <span class="report-summary-label">Passout</span>
            <span class="report-summary-value"><?php echo intval($stats['passout']); ?></span>
            <span class="report-summary-note">Passed out / completed</span>
        </div>
        <div class="report-summary-card report-tone-info">
            <span class="report-summary-label">With Photo</span>
            <span class="report-summary-value"><?php echo intval($stats['with_photo']); ?></span>
            <span class="report-summary-note">Uploaded profile photos</span>
        </div>
        <div class="report-summary-card report-tone-primary">
            <span class="report-summary-label">Classes</span>
            <span class="report-summary-value"><?php echo intval($stats['class_count']); ?></span>
            <span class="report-summary-note">Distinct classes in view</span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function studentListReportTableHtml($students, $backUrl = '') {
    ob_start();
    ?>
    <div class="report-panel">
        <div class="report-panel-head">
            <div>
                <h3 class="report-panel-title mb-0">Student Register</h3>
                <div class="report-panel-subtitle"><?php echo intval(count($students)); ?> student<?php echo count($students) === 1 ? '' : 's'; ?> matched the current filters</div>
            </div>
            <?php if ($backUrl !== ''): ?>
                <div class="report-actions no-print">
                    <a class="report-action report-action-muted" href="<?php echo studentListReportEscape($backUrl); ?>">
                        <i class="bi bi-arrow-left"></i><span>Back to Report</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">S.No</th>
                        <th style="width: 70px;">Photo</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class / Section</th>
                        <th>Roll No</th>
                        <th>Father / Mother</th>
                        <th>Contact No</th>
                        <th>Email</th>
                        <th>Admission Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td class="report-center"><?php echo intval($index + 1); ?></td>
                                <td class="report-center"><?php echo studentListReportAvatarMarkup($student); ?></td>
                                <td>
                                    <strong><?php echo studentListReportEscape(studentListReportValue($student['admission_no'] ?? '-')); ?></strong>
                                    <div class="report-subtle">Student ID: <?php echo intval($student['student_id'] ?? 0); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo studentListReportEscape(studentListReportValue($student['student_name'] ?? '-')); ?></strong>
                                    <div class="report-subtle">
                                        <?php echo studentListReportEscape(studentListReportValue($student['gender'] ?? '-')); ?>
                                        <?php if (!empty($student['date_of_birth'])): ?>
                                            | DOB: <?php echo studentListReportEscape(studentListReportDate($student['date_of_birth'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo studentListReportEscape(studentListReportValue($student['class_name'] ?? '-')); ?></strong>
                                    <div class="report-subtle"><?php echo studentListReportEscape(studentListReportValue($student['section_name'] ?? '-')); ?></div>
                                </td>
                                <td><?php echo studentListReportEscape(studentListReportValue($student['roll_no'] ?? '-')); ?></td>
                                <td>
                                    <strong><?php echo studentListReportEscape(studentListReportValue($student['father_name'] ?? '-')); ?></strong>
                                    <div class="report-subtle"><?php echo studentListReportEscape(studentListReportValue($student['mother_name'] ?? '-')); ?></div>
                                </td>
                                <td><?php echo studentListReportEscape(studentListReportValue($student['contact_no'] ?? '-')); ?></td>
                                <td><?php echo studentListReportEscape(studentListReportValue($student['email'] ?? '-')); ?></td>
                                <td><?php echo studentListReportEscape(studentListReportDate($student['admission_date'] ?? '')); ?></td>
                                <td>
                                    <span class="report-pill bg-<?php echo studentListReportEscape(studentListReportStatusClass($student['status'] ?? '')); ?>">
                                        <?php echo studentListReportEscape(studentListReportStatusLabel($student['status'] ?? '')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11">
                                <div class="report-empty">
                                    <i class="bi bi-info-circle"></i> No students found for the current filters.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="10" class="report-num">Total Students</th>
                        <th class="report-center"><?php echo intval(count($students)); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function studentListReportRenderExportDocument($mode, $schoolName, $reportTitle, $filtersHtml, $summaryHtml, $tableHtml, $backUrl = '') {
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
        <title><?php echo studentListReportEscape($reportTitle); ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <style>
        <?php echo studentListReportStyles(); ?>

        body {
            margin: 0;
            padding: 18px 20px 24px;
            background: #f8fafc;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
        }

        .report-shell {
            background: transparent;
        }
        </style>
    </head>
    <body>
        <div class="report-shell">
            <?php if ($mode === 'pdf'): ?>
                <div class="report-print-toolbar no-print">
                    <a class="report-action report-action-muted" href="<?php echo studentListReportEscape($backUrl); ?>">
                        <i class="bi bi-arrow-left"></i><span>Back to Report</span>
                    </a>
                    <button class="report-action report-action-primary" type="button" onclick="window.print();">
                        <i class="bi bi-printer"></i><span>Print / Save PDF</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="report-hero">
                <div class="report-eyebrow"><?php echo studentListReportEscape($schoolName); ?></div>
                <h1 class="report-title"><?php echo studentListReportEscape($reportTitle); ?></h1>
                <div class="report-subtitle">Full student list with filters, summary counts, and export-ready formatting.</div>
                <?php if ($schoolAddress !== '' || $schoolPhone !== '' || $schoolEmail !== ''): ?>
                    <div class="report-export-meta">
                        <?php if ($schoolAddress !== ''): ?>
                            <div><?php echo studentListReportEscape($schoolAddress); ?></div>
                        <?php endif; ?>
                        <?php if ($schoolPhone !== '' || $schoolEmail !== ''): ?>
                            <div>
                                <?php if ($schoolPhone !== ''): ?>Phone: <?php echo studentListReportEscape($schoolPhone); ?><?php endif; ?>
                                <?php if ($schoolPhone !== '' && $schoolEmail !== ''): ?> | <?php endif; ?>
                                <?php if ($schoolEmail !== ''): ?>Email: <?php echo studentListReportEscape($schoolEmail); ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php echo $filtersHtml; ?>
                <div class="report-export-meta">Generated on <?php echo studentListReportEscape(date('d M Y, h:i A')); ?></div>
            </div>

            <?php echo $summaryHtml; ?>

            <?php echo $tableHtml; ?>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

$pageTitle = 'Student List Report';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$schoolSettings = getSchoolSettings();
$schoolName = $schoolSettings['school_name'] ?? APP_NAME;

$search = trim((string)($_GET['search'] ?? ''));
$classId = intval($_GET['class_id'] ?? 0);
$sectionId = intval($_GET['section_id'] ?? 0);

if (array_key_exists('status', $_GET)) {
    $status = studentListReportNormalizeStatus(sanitize($_GET['status']));
} else {
    $status = 'Active';
}

$classes = fetchAll("SELECT class_id, class_name, class_order FROM classes WHERE is_active = 1 ORDER BY class_order, class_name");
$sections = fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name");

$classMap = [];
foreach ($classes as $class) {
    $classMap[intval($class['class_id'])] = $class['class_name'];
}

$sectionMap = [];
foreach ($sections as $section) {
    $sectionMap[intval($section['section_id'])] = $section['section_name'];
}

$students = studentListReportFetchStudents($search, $classId, $sectionId, $status, $currentSchoolId);
$stats = studentListReportStats($students);

$selectedClassLabel = $classId > 0 && isset($classMap[$classId]) ? $classMap[$classId] : 'All Classes';
$selectedSectionLabel = $sectionId > 0 && isset($sectionMap[$sectionId]) ? $sectionMap[$sectionId] : 'All Sections';
$selectedStatusLabel = studentListReportStatusLabel($status);
$generatedAt = date('d M Y, h:i A');

$baseParams = [
    'search' => $search,
    'class_id' => $classId,
    'section_id' => $sectionId,
    'status' => $status,
];

$filterChipsHtml = studentListReportFilterChips(
    $search,
    $selectedClassLabel,
    $selectedSectionLabel,
    $selectedStatusLabel,
    $stats['total'],
    $generatedAt
);

$summaryHtml = studentListReportSummaryCards($stats);
$tableHtml = studentListReportTableHtml($students);
$backUrl = studentListReportBuildUrl($baseParams);

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($export === 'excel' || $export === 'pdf') {
    $exportHtml = studentListReportRenderExportDocument(
        $export === 'pdf' ? 'pdf' : 'excel',
        $schoolName,
        'Student List Report',
        $filterChipsHtml,
        $summaryHtml,
        $tableHtml,
        $backUrl
    );

    if (ob_get_level()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    if ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Student_List_Report_' . date('Y-m-d') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo $exportHtml;
    } else {
        $pdfResult = pdfExportDownloadHtml($exportHtml, 'Student_List_Report_' . date('Y-m-d'));
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
<?php echo studentListReportStyles(); ?>
</style>

<div class="row mb-3">
    <div class="col-12">
        <div class="report-hero">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="report-eyebrow"><?php echo studentListReportEscape($schoolName); ?></div>
                    <h1 class="report-title">Student List Report</h1>
                    <div class="report-subtitle">Full student register with class, section, roll number, contact details, and photo preview.</div>
                </div>
                <div class="report-actions no-print">
                    <a class="report-action report-action-primary" href="<?php echo studentListReportEscape(studentListReportBuildUrl($baseParams, ['export' => 'excel'])); ?>">
                        <i class="bi bi-file-earmark-excel"></i><span>Export Excel</span>
                    </a>
                    <a class="report-action report-action-muted" href="<?php echo studentListReportEscape(studentListReportBuildUrl($baseParams, ['export' => 'pdf'])); ?>" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i><span>Print / PDF</span>
                    </a>
                </div>
            </div>
            <?php echo $filterChipsHtml; ?>
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
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Student Search</label>
                            <input
                                type="text"
                                class="form-control"
                                name="search"
                                id="student_list_search"
                                value="<?php echo studentListReportEscape($search); ?>"
                                placeholder="Admission no, student name, roll no, father, or class"
                                autocomplete="off"
                                data-student-autocomplete="true"
                                data-student-autocomplete-class="#student_list_class"
                                data-student-autocomplete-submit="#studentListSearchBtn"
                            >
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Class</label>
                            <select class="form-select" name="class_id" id="student_list_class">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo intval($class['class_id']); ?>" <?php echo $classId === intval($class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo studentListReportEscape($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Section</label>
                            <select class="form-select" name="section_id">
                                <option value="0">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo intval($section['section_id']); ?>" <?php echo $sectionId === intval($section['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo studentListReportEscape($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
                                <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Passout" <?php echo $status === 'Passout' ? 'selected' : ''; ?>>Passout</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="studentListSearchBtn">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <a href="student_list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Reset
                                </a>
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
        <?php echo $summaryHtml; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php echo studentListReportTableHtml($students, APP_URL . '/modules/reports/index.php'); ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
