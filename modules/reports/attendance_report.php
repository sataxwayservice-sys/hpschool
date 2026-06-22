<?php
/**
 * Student Attendance Report
 * Student-wise and class-wise attendance view with Excel and PDF export
 */

require_once '../../config/config.php';
require_once '../../includes/report_export.php';
require_once '../../includes/pdf_export.php';

requireLogin();
requirePermission('reports', 'view');

if (function_exists('ensureAttendanceSchema')) {
    ensureAttendanceSchema();
}

function attendanceReportEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function attendanceReportBuildUrl(array $baseParams, array $overrides = []) {
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

    return APP_URL . '/modules/reports/attendance_report.php?' . http_build_query($params);
}

function attendanceReportNormalizeStatus($status) {
    $status = ucfirst(strtolower(trim((string) $status)));
    if ($status === 'Half day') {
        $status = 'Half Day';
    }

    return in_array($status, ['Present', 'Absent', 'Late', 'Half Day'], true) ? $status : 'Present';
}

function attendanceReportStatusLabel($status) {
    return attendanceReportNormalizeStatus($status);
}

function attendanceReportStatusClass($status) {
    switch (strtolower(trim((string) attendanceReportNormalizeStatus($status)))) {
        case 'present':
            return 'success';
        case 'late':
            return 'warning text-dark';
        case 'half day':
            return 'info text-dark';
        case 'absent':
            return 'danger';
        default:
            return 'secondary';
    }
}

function attendanceReportFormatDate($value) {
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '-' : date('d-M-Y', $timestamp);
}

function attendanceReportFormatDateTime($value) {
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '-' : date('d-M-Y h:i A', $timestamp);
}

function attendanceReportFetchRows(array $filters, int $currentSchoolId): array {
    $query = "SELECT
                sa.attendance_id,
                sa.attendance_date,
                sa.status,
                sa.created_at,
                sa.updated_at,
                sa.marked_by,
                s.student_id,
                s.student_name,
                s.admission_no,
                s.roll_no,
                s.father_name,
                c.class_name,
                sec.section_name,
                u.full_name AS marked_by_name
              FROM student_attendance sa
              JOIN students s ON sa.student_id = s.student_id
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              LEFT JOIN users u ON sa.marked_by = u.user_id
              WHERE sa.attendance_date BETWEEN ? AND ?";

    $params = [$filters['from_date'], $filters['to_date']];
    $types = 'ss';

    if ($currentSchoolId > 0) {
        $query .= " AND sa.school_id = ?";
        $params[] = $currentSchoolId;
        $types .= 'i';
    }

    if ($filters['student_id'] > 0) {
        $query .= " AND s.student_id = ?";
        $params[] = $filters['student_id'];
        $types .= 'i';
    } elseif ($filters['student_search'] !== '') {
        $query .= " AND (s.student_name LIKE ? OR s.admission_no LIKE ? OR s.roll_no LIKE ? OR s.father_name LIKE ?)";
        $search = '%' . $filters['student_search'] . '%';
        for ($i = 0; $i < 4; $i++) {
            $params[] = $search;
            $types .= 's';
        }
    }

    if ($filters['class_id'] > 0) {
        $query .= " AND s.class_id = ?";
        $params[] = $filters['class_id'];
        $types .= 'i';
    }

    if ($filters['section_id'] > 0) {
        $query .= " AND s.section_id = ?";
        $params[] = $filters['section_id'];
        $types .= 'i';
    }

    if ($filters['status'] !== '') {
        $query .= " AND sa.status = ?";
        $params[] = attendanceReportNormalizeStatus($filters['status']);
        $types .= 's';
    }

    $query .= " ORDER BY sa.attendance_date DESC, c.class_order, sec.section_name, s.student_name";

    return empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
}

function attendanceReportBuildPdfHtml(array $schoolSettings, array $filters, array $rows, array $summary): string {
    $meta = [
        'From Date' => attendanceReportFormatDate($filters['from_date']),
        'To Date' => attendanceReportFormatDate($filters['to_date']),
        'Class' => $filters['class_label'] !== '' ? $filters['class_label'] : 'All Classes',
        'Section' => $filters['section_label'] !== '' ? $filters['section_label'] : 'All Sections',
        'Student' => $filters['student_label'] !== ''
            ? $filters['student_label']
            : ($filters['student_search'] !== '' ? $filters['student_search'] : 'All Students'),
        'Status' => $filters['status'] !== '' ? attendanceReportStatusLabel($filters['status']) : 'All Status',
    ];

    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #111827;
        }

        .report-wrap {
            width: 100%;
        }

        .summary-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 12px 0 14px;
        }

        .summary-card {
            flex: 1 1 120px;
            border: 1px solid #d8e2ee;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .summary-card .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-card .value {
            font-size: 22px;
            font-weight: 700;
            margin-top: 4px;
            color: #0f172a;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .attendance-table th,
        .attendance-table td {
            border: 1px solid #dbe3ec;
            padding: 8px 9px;
            font-size: 11px;
            vertical-align: top;
        }

        .attendance-table th {
            background: #1d4ed8;
            color: #fff;
            font-size: 11px;
            text-transform: uppercase;
        }

        .attendance-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1d4ed8; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-secondary { background: #e5e7eb; color: #374151; }

        .muted {
            color: #64748b;
        }

        .empty-state {
            margin-top: 18px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 18px;
            text-align: center;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="report-wrap">
        <?php echo reportExportRenderHeaderBlock($schoolSettings, 'Student Attendance Report', date('d M Y, h:i A'), 'Student-wise attendance summary with school filters', $meta); ?>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Records</div>
                <div class="value"><?php echo intval($summary['total'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Present</div>
                <div class="value"><?php echo intval($summary['Present'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Late</div>
                <div class="value"><?php echo intval($summary['Late'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Half Day</div>
                <div class="value"><?php echo intval($summary['Half Day'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Absent</div>
                <div class="value"><?php echo intval($summary['Absent'] ?? 0); ?></div>
            </div>
        </div>

        <?php if (!empty($rows)): ?>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th width="10%">Date</th>
                        <th width="18%">Student Name</th>
                        <th width="12%">Admission No</th>
                        <th width="8%">Roll</th>
                        <th width="16%">Father Name</th>
                        <th width="14%">Class</th>
                        <th width="10%">Status</th>
                        <th width="12%">Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $status = attendanceReportStatusLabel($row['status'] ?? 'Present'); ?>
                        <tr>
                            <td><?php echo attendanceReportEscape(attendanceReportFormatDate($row['attendance_date'] ?? '')); ?></td>
                            <td><strong><?php echo attendanceReportEscape($row['student_name'] ?? '-'); ?></strong></td>
                            <td><?php echo attendanceReportEscape($row['admission_no'] ?? '-'); ?></td>
                            <td><?php echo attendanceReportEscape($row['roll_no'] ?? '-'); ?></td>
                            <td><?php echo attendanceReportEscape($row['father_name'] ?? '-'); ?></td>
                            <td><?php echo attendanceReportEscape(trim((string)($row['class_name'] ?? '') . ' ' . (string)($row['section_name'] ?? '')) ?: '-'); ?></td>
                            <td><span class="badge badge-<?php echo attendanceReportEscape(strtolower(str_replace(' ', '', attendanceReportStatusClass($status)))); ?>"><?php echo attendanceReportEscape($status); ?></span></td>
                            <td>
                                <?php echo attendanceReportEscape($row['marked_by_name'] ?? 'System'); ?>
                                <div class="muted"><?php echo attendanceReportEscape(attendanceReportFormatDateTime($row['updated_at'] ?? ($row['created_at'] ?? ''))); ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                No attendance records were found for the selected filters.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$schoolSettings = getSchoolSettings();

$classes = fetchAll("SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name");

$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));

if ($fromDate === '') {
    $fromDate = date('Y-m-01');
}

if ($toDate === '') {
    $toDate = date('Y-m-d');
}

if (strtotime($fromDate) !== false && strtotime($toDate) !== false && strtotime($fromDate) > strtotime($toDate)) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$studentSearch = trim((string)($_GET['student_search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$export = strtolower(trim((string)($_GET['export'] ?? '')));

$classLabel = '';
foreach ($classes as $class) {
    if (intval($class['class_id']) === $classId) {
        $classLabel = (string)($class['class_name'] ?? '');
        break;
    }
}

$sectionLabel = '';
foreach ($sections as $section) {
    if (intval($section['section_id']) === $sectionId) {
        $sectionLabel = (string)($section['section_name'] ?? '');
        break;
    }
}

$selectedStudent = [];
if ($studentId > 0) {
    $selectedStudent = fetchOne(
        "SELECT s.student_id, s.student_name, s.admission_no, s.roll_no
         FROM students s
         WHERE s.student_id = ? LIMIT 1",
        'i',
        [$studentId]
    ) ?: [];
}

$filters = [
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'class_id' => $classId,
    'section_id' => $sectionId,
    'student_id' => $studentId,
    'student_search' => $studentSearch,
    'status' => attendanceReportNormalizeStatus($status),
    'class_label' => $classLabel,
    'section_label' => $sectionLabel,
    'student_label' => !empty($selectedStudent) ? trim((string)($selectedStudent['student_name'] ?? '')) : '',
];

$rows = attendanceReportFetchRows($filters, $currentSchoolId);

$summary = [
    'total' => count($rows),
    'Present' => 0,
    'Late' => 0,
    'Half Day' => 0,
    'Absent' => 0,
];

foreach ($rows as $row) {
    $normalizedStatus = attendanceReportNormalizeStatus($row['status'] ?? 'Present');
    if (isset($summary[$normalizedStatus])) {
        $summary[$normalizedStatus]++;
    }
}

$baseParams = [
    'from_date' => $filters['from_date'],
    'to_date' => $filters['to_date'],
    'class_id' => $filters['class_id'],
    'section_id' => $filters['section_id'],
    'student_id' => $filters['student_id'],
    'student_search' => $filters['student_search'],
    'status' => $filters['status'],
];

if ($export === 'excel') {
    $filename = 'Student_Attendance_Report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    reportExportWriteCsvHeaderRows(
        $output,
        $schoolSettings,
        'Student Attendance Report',
        date('d M Y, h:i A'),
        [
            'From Date' => attendanceReportFormatDate($filters['from_date']),
            'To Date' => attendanceReportFormatDate($filters['to_date']),
            'Class' => $filters['class_label'] !== '' ? $filters['class_label'] : 'All',
            'Section' => $filters['section_label'] !== '' ? $filters['section_label'] : 'All',
            'Student' => $filters['student_label'] !== ''
                ? $filters['student_label']
                : ($filters['student_search'] !== '' ? $filters['student_search'] : 'All'),
            'Status' => $filters['status'] !== '' ? attendanceReportStatusLabel($filters['status']) : 'All',
        ]
    );

    fputcsv($output, ['Date', 'Student Name', 'Admission No', 'Roll No', 'Father Name', 'Class', 'Status', 'Marked By', 'Marked At']);

    foreach ($rows as $row) {
        fputcsv($output, [
            attendanceReportFormatDate($row['attendance_date'] ?? ''),
            $row['student_name'] ?? '-',
            $row['admission_no'] ?? '-',
            $row['roll_no'] ?? '-',
            $row['father_name'] ?? '-',
            trim((string)($row['class_name'] ?? '') . ' ' . (string)($row['section_name'] ?? '')) ?: '-',
            attendanceReportStatusLabel($row['status'] ?? 'Present'),
            $row['marked_by_name'] ?? 'System',
            attendanceReportFormatDateTime($row['updated_at'] ?? ($row['created_at'] ?? '')),
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Records', $summary['total']]);
    fputcsv($output, ['Present', $summary['Present']]);
    fputcsv($output, ['Late', $summary['Late']]);
    fputcsv($output, ['Half Day', $summary['Half Day']]);
    fputcsv($output, ['Absent', $summary['Absent']]);

    fclose($output);
    exit();
}

if ($export === 'pdf') {
    $pdfHtml = attendanceReportBuildPdfHtml($schoolSettings, $filters, $rows, $summary);
    $downloadName = 'Student_Attendance_Report_' . date('Y-m-d');
    $pdfResult = pdfExportDownloadHtml($pdfHtml, $downloadName);

    if (!$pdfResult['success']) {
        alertAndRedirect('PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'), attendanceReportBuildUrl($baseParams), 'error');
    }

    exit();
}

include '../../includes/header.php';
?>

<style>
.attendance-report-shell {
    display: block;
}

.attendance-report-hero {
    background: #ffffff;
    border: 1px solid #dbe3ec;
    border-left: 6px solid #2563eb;
    border-radius: 8px;
    padding: 18px 20px;
}

.attendance-report-title {
    margin: 4px 0 0 0;
    font-size: 1.55rem;
    line-height: 1.25;
    font-weight: 700;
    color: #111827;
}

.attendance-report-subtitle {
    margin-top: 6px;
    color: #6b7280;
    font-size: 0.95rem;
}

.attendance-report-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}

.attendance-report-chip {
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

.attendance-report-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
}

.attendance-report-summary-card {
    background: #fff;
    border: 1px solid #dbe3ec;
    border-radius: 10px;
    padding: 14px 16px;
}

.attendance-report-summary-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    font-weight: 700;
}

.attendance-report-summary-value {
    font-size: 1.55rem;
    font-weight: 800;
    color: #111827;
    margin-top: 4px;
}

.attendance-report-table thead th {
    white-space: nowrap;
}

.attendance-report-badge {
    font-size: 0.82rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
}

.report-empty-state {
    padding: 28px 18px;
    text-align: center;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
    background: #fff;
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

<div class="attendance-report-shell">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-calendar-check"></i> Student Attendance Report
                    </h2>
                    <div class="text-muted">Student-wise attendance view with Excel and PDF export.</div>
                </div>
                <div class="no-print">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Reports
                    </a>
                    <a href="<?php echo attendanceReportEscape(attendanceReportBuildUrl($baseParams, ['export' => 'excel'])); ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </a>
                    <a href="<?php echo attendanceReportEscape(attendanceReportBuildUrl($baseParams, ['export' => 'pdf'])); ?>" target="_blank" class="btn btn-primary">
                        <i class="bi bi-file-earmark-pdf"></i> Print / PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card dashboard-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <input type="hidden" name="student_id" id="attendance_student_id" value="<?php echo intval($filters['student_id']); ?>">
                        <div class="row g-3">
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" name="from_date" value="<?php echo attendanceReportEscape($filters['from_date']); ?>">
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" name="to_date" value="<?php echo attendanceReportEscape($filters['to_date']); ?>">
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">Class</label>
                                <select class="form-select" name="class_id" id="attendance_class_id">
                                    <option value="0">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo intval($class['class_id']); ?>" <?php echo intval($filters['class_id']) === intval($class['class_id']) ? 'selected' : ''; ?>>
                                            <?php echo attendanceReportEscape($class['class_name']); ?>
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
                                            <?php echo attendanceReportEscape($section['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="Present" <?php echo $filters['status'] === 'Present' ? 'selected' : ''; ?>>Present</option>
                                    <option value="Late" <?php echo $filters['status'] === 'Late' ? 'selected' : ''; ?>>Late</option>
                                    <option value="Half Day" <?php echo $filters['status'] === 'Half Day' ? 'selected' : ''; ?>>Half Day</option>
                                    <option value="Absent" <?php echo $filters['status'] === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Student Search</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="student_search"
                                    value="<?php echo attendanceReportEscape($filters['student_label'] !== '' ? $filters['student_label'] : $filters['student_search']); ?>"
                                    placeholder="Admission no, student name, roll no, or father name"
                                    autocomplete="off"
                                    data-student-autocomplete="true"
                                    data-student-autocomplete-fill="student_name"
                                    data-student-autocomplete-min-length="2"
                                    data-student-autocomplete-class="#attendance_class_id"
                                    data-student-autocomplete-skip-submit="true"
                                    data-student-autocomplete-id-target="#attendance_student_id">
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" id="attendanceReportSearchBtn" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="attendance-report-summary">
                <div class="attendance-report-summary-card">
                    <div class="attendance-report-summary-label">Total Records</div>
                    <div class="attendance-report-summary-value"><?php echo intval($summary['total']); ?></div>
                </div>
                <div class="attendance-report-summary-card">
                    <div class="attendance-report-summary-label">Present</div>
                    <div class="attendance-report-summary-value text-success"><?php echo intval($summary['Present']); ?></div>
                </div>
                <div class="attendance-report-summary-card">
                    <div class="attendance-report-summary-label">Late</div>
                    <div class="attendance-report-summary-value text-warning"><?php echo intval($summary['Late']); ?></div>
                </div>
                <div class="attendance-report-summary-card">
                    <div class="attendance-report-summary-label">Half Day</div>
                    <div class="attendance-report-summary-value text-info"><?php echo intval($summary['Half Day']); ?></div>
                </div>
                <div class="attendance-report-summary-card">
                    <div class="attendance-report-summary-label">Absent</div>
                    <div class="attendance-report-summary-value text-danger"><?php echo intval($summary['Absent']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card dashboard-card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Attendance Records</h5>
                    <span class="badge bg-light text-dark"><?php echo count($rows); ?> row(s)</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($rows)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle attendance-report-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Admission No</th>
                                        <th>Roll No</th>
                                        <th>Father Name</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                        <th>Marked By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <?php $statusLabel = attendanceReportStatusLabel($row['status'] ?? 'Present'); ?>
                                        <tr>
                                            <td><?php echo attendanceReportEscape(attendanceReportFormatDate($row['attendance_date'] ?? '')); ?></td>
                                            <td><strong><?php echo attendanceReportEscape($row['student_name'] ?? '-'); ?></strong></td>
                                            <td><?php echo attendanceReportEscape($row['admission_no'] ?? '-'); ?></td>
                                            <td><?php echo attendanceReportEscape($row['roll_no'] ?? '-'); ?></td>
                                            <td><?php echo attendanceReportEscape($row['father_name'] ?? '-'); ?></td>
                                            <td><?php echo attendanceReportEscape(trim((string)($row['class_name'] ?? '') . ' ' . (string)($row['section_name'] ?? '')) ?: '-'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo attendanceReportEscape(strtolower(str_replace(' text-dark', '', attendanceReportStatusClass($statusLabel)))); ?> attendance-report-badge">
                                                    <?php echo attendanceReportEscape($statusLabel); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo attendanceReportEscape($row['marked_by_name'] ?? 'System'); ?>
                                                <div class="text-muted small"><?php echo attendanceReportEscape(attendanceReportFormatDateTime($row['updated_at'] ?? ($row['created_at'] ?? ''))); ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="report-empty-state">
                            <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                            <div class="mt-2">No attendance records found for the selected filters.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
