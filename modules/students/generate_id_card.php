<?php
/**
 * Student ID Card Generator
 * Generate printable student ID cards with different designs
 */

// Include configuration
require_once '../../config/config.php';
require_once '../../includes/pdf_export.php';

// Require login
requireLogin();
requirePermission('students', 'view');

$pageTitle = 'Generate ID Cards';
$currentUser = getCurrentUser();

// Get design template
$design = isset($_GET['design']) ? sanitize($_GET['design']) : 'default';

// Get students based on filters
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$downloadPdf = strtolower(trim((string)($_GET['download'] ?? ''))) === 'pdf';
$singleCardMode = false;

// Build query
$query = "SELECT s.*, c.class_name, sec.section_name
          FROM students s
          JOIN classes c ON s.class_id = c.class_id
          JOIN sections sec ON s.section_id = sec.section_id
          WHERE s.status = 'Active'";

$params = [];
$types = '';

if ($studentId > 0) {
    $query .= " AND s.student_id = ?";
    $types .= 'i';
    $params[] = $studentId;
} else {
    if ($classId > 0) {
        $query .= " AND s.class_id = ?";
        $types .= 'i';
        $params[] = $classId;
    }
    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $types .= 'i';
        $params[] = $sectionId;
    }

}

$query .= " ORDER BY s.class_id, s.section_id, s.roll_no";

$students = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
$singleCardMode = count($students) === 1;
$primaryStudent = $students[0] ?? [];
$primaryStudentId = intval($primaryStudent['student_id'] ?? $studentId);

// Get school settings
$schoolSettings = getSchoolSettings();

// Get classes for filter
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

if (!function_exists('idCardSmartValue')) {
    function idCardSmartValue($value, $default = 'N/A', $uppercase = false) {
        $text = trim((string) $value);
        if ($text === '') {
            $text = $default;
        }

        if ($uppercase) {
            $text = function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
        }

        return $text;
    }
}

if (!function_exists('idCardGetSingleModeLayout')) {
    function idCardGetSingleModeLayout($design) {
        $design = strtolower(trim((string) $design));
        if ($design === 'vertical') {
            return [
                'page_width' => '60mm',
                'page_height' => '90mm',
                'container_width' => '60mm',
                'container_height' => '90mm',
                'card_class' => 'vertical',
            ];
        }

        return [
            'page_width' => '85.6mm',
            'page_height' => '54mm',
            'container_width' => '85.6mm',
            'container_height' => '54mm',
            'card_class' => 'single-machine ' . $design,
        ];
    }
}

$singleModeLayout = idCardGetSingleModeLayout($design);

$pageTitle = $singleCardMode ? 'Print ID Card' : 'Generate ID Cards';
?>
<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Cards - <?php echo htmlspecialchars($schoolSettings['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        <?php if ($singleCardMode): ?>
        @page {
            size: <?php echo htmlspecialchars($singleModeLayout['page_width'] . ' ' . $singleModeLayout['page_height']); ?>;
            margin: 0;
        }
        <?php else: ?>
        @page {
            size: A4;
            margin: 10mm;
        }
        <?php endif; ?>

        body {
            font-family: Arial, sans-serif;
            <?php echo $singleCardMode ? 'margin: 0; padding: 0; background: #fff;' : ''; ?>
        }

        .id-card-page {
            <?php if ($singleCardMode): ?>
            width: <?php echo htmlspecialchars($singleModeLayout['page_width']); ?>;
            min-height: <?php echo htmlspecialchars($singleModeLayout['page_height']); ?>;
            padding: 0;
            margin: 0 auto;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            <?php else: ?>
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
            margin: 0 auto;
            background: white;
            <?php endif; ?>
        }

        .id-card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8mm;
            justify-content: center;
            <?php if ($singleCardMode): ?>
            width: <?php echo htmlspecialchars($singleModeLayout['container_width']); ?>;
            min-height: <?php echo htmlspecialchars($singleModeLayout['container_height']); ?>;
            gap: 0;
            align-items: center;
            <?php endif; ?>
        }

        /* Default Design - Vertical Card */
        .id-card {
            width: 85.6mm;
            height: 58mm;
            border: 2px solid #333;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            page-break-inside: avoid;
        }

        .id-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3mm 2mm;
            text-align: center;
        }

        .id-card-header h6 {
            margin: 0;
            font-size: 11pt;
            font-weight: bold;
        }

        .id-card-header p {
            margin: 0;
            font-size: 7pt;
        }

        .id-card-body {
            padding: 1.8mm 2mm 1.2mm;
            display: flex;
            gap: 1.8mm;
            align-items: flex-start;
        }

        .id-card-photo {
            width: 18mm;
            height: 24mm;
            border: 1px solid #ddd;
            overflow: hidden;
            flex-shrink: 0;
        }

        .id-card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .id-card-info {
            flex: 1;
            font-size: 6.9pt;
            line-height: 1.06;
            min-width: 0;
        }

        .id-card-info .info-row {
            display: flex;
            align-items: flex-start;
            gap: 1mm;
            margin-bottom: 0.7mm;
            min-width: 0;
        }

        .id-card-info .label {
            font-weight: bold;
            color: #555;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .id-card-info .value {
            color: #000;
            flex: 1;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .id-card-info .value.address {
            display: block;
            font-size: 6.1pt;
            line-height: 1.05;
            max-height: 10mm;
            overflow: hidden;
        }

        .id-card-info .value.smart-uppercase {
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.15px;
        }

        .id-card-info .value.smart-tight {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            line-height: 1.02;
        }

        .id-card-footer {
            background: #f8f9fa;
            padding: 1.25mm 2mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            text-align: left;
            font-size: 5.8pt;
            color: #666;
            border-top: 1px solid #ddd;
        }

        .id-card-footer-text {
            flex: 1;
            min-width: 0;
            line-height: 1.15;
        }

        .id-card-qr {
            width: 13mm;
            height: 13mm;
            flex-shrink: 0;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 0.8mm;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .id-card-qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .id-card-qr-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6pt;
            font-weight: bold;
            color: #555;
            background: #f9fafb;
        }

        /* Modern Design */
        .id-card.modern {
            background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%);
        }

        .id-card.modern .id-card-header {
            background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);
        }

        /* Professional Design */
        .id-card.professional {
            border-color: #0d6efd;
        }

        .id-card.professional .id-card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        }

        /* Colorful Design */
        .id-card.colorful {
            border: 3px solid;
            border-image: linear-gradient(45deg, red, orange, yellow, green, blue, indigo, violet) 1;
        }

        .id-card.colorful .id-card-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* Vertical Long Card Design */
        .id-card.vertical {
            width: 60mm;
            height: 90mm;
            flex-direction: column;
        }

        .id-card.vertical .id-card-body {
            flex-direction: column;
            align-items: center;
            padding: 2.2mm 2.4mm 1.8mm;
        }

        .id-card.vertical .id-card-photo {
            width: 30mm;
            height: 35mm;
            margin-bottom: 2mm;
        }

        .id-card.vertical .id-card-info {
            width: 100%;
            text-align: left;
            font-size: 6.9pt;
            margin-top: 0.5mm;
        }

        .id-card.vertical .id-card-info .info-row {
            margin-bottom: 0.55mm;
        }

        .id-card.vertical .id-card-info .label {
            font-size: 6.1pt;
        }

        .id-card.vertical .id-card-info .value {
            font-size: 6.5pt;
        }

        .id-card.single-machine {
            width: 85.6mm;
            height: 54mm;
            border-radius: 5px;
            box-shadow: none;
            display: flex;
            flex-direction: column;
        }

        @media screen {
            .id-card.single-machine {
                height: auto;
                min-height: 54mm;
            }
        }

        .id-card.single-machine .id-card-header {
            padding: 1.7mm 1.8mm 1.3mm;
        }

        .id-card.single-machine .id-card-header h6 {
            font-size: 9pt;
        }

        .id-card.single-machine .id-card-header p {
            font-size: 5.7pt;
            line-height: 1.02;
        }

        .id-card.single-machine .id-card-body {
            flex: 1 1 auto;
            padding: 1.0mm 1.5mm 0.8mm;
            gap: 1.4mm;
        }

        .id-card.single-machine .id-card-photo {
            width: 16mm;
            height: 21mm;
        }

        .id-card.single-machine .id-card-info {
            font-size: 6.2pt;
            line-height: 1.04;
        }

        .id-card.single-machine .id-card-info .info-row {
            margin-bottom: 0.45mm;
        }

        .id-card.single-machine .id-card-info .label {
            font-size: 5.7pt;
            width: 19mm;
        }

        .id-card.single-machine .id-card-info .value {
            font-size: 6.2pt;
        }

        .id-card.single-machine .id-card-info .value.address {
            font-size: 5.7pt;
            max-height: 9mm;
        }

        .id-card.single-machine .id-card-footer {
            margin-top: auto;
            padding: 0.65mm 1mm;
            font-size: 5pt;
            line-height: 1.05;
        }

        .id-card.single-machine .id-card-qr {
            width: 10mm;
            height: 10mm;
            padding: 0.25mm;
            align-self: center;
        }

        .id-card.single-machine .id-card-footer-text {
            line-height: 1.05;
        }

        .id-card.single-machine .id-card-footer-text div:last-child {
            display: block;
            font-size: 4.5pt;
            opacity: 0.85;
        }

        @media print {
            .id-card.single-machine .id-card-footer-text div:last-child {
                display: none;
            }
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .id-card {
                box-shadow: none;
            }

            .id-card-page {
                margin: 0;
                <?php echo $singleCardMode ? 'padding: 0;' : 'padding: 10mm;'; ?>
            }
        }

        .control-panel {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="container-fluid mt-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="bi bi-credit-card"></i> <?php echo $singleCardMode ? 'Student ID Card' : 'Student ID Cards'; ?></h3>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> <?php echo $singleCardMode ? 'Print One Card' : 'Print Cards'; ?>
                    </button>
                    <?php if ($singleCardMode && $primaryStudentId > 0): ?>
                        <a href="<?php echo APP_URL; ?>/modules/students/generate_id_card.php?student_id=<?php echo intval($primaryStudentId); ?>&design=<?php echo urlencode($design); ?>&download=pdf"
                           class="btn btn-danger" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-pdf"></i> Download PDF
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <div class="control-panel">
                <form method="GET" action="">
                    <input type="hidden" name="mode" value="<?php echo $singleCardMode ? 'single' : ''; ?>">
                    <?php if ($singleCardMode): ?>
                        <input type="hidden" name="student_id" value="<?php echo intval($primaryStudentId); ?>">
                        <div class="row">
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label">Design Template</label>
                                <select class="form-select" name="design" onchange="this.form.submit()">
                                    <option value="default" <?php echo $design == 'default' ? 'selected' : ''; ?>>Default (Horizontal)</option>
                                    <option value="modern" <?php echo $design == 'modern' ? 'selected' : ''; ?>>Modern</option>
                                    <option value="professional" <?php echo $design == 'professional' ? 'selected' : ''; ?>>Professional</option>
                                    <option value="colorful" <?php echo $design == 'colorful' ? 'selected' : ''; ?>>Colorful</option>
                                    <option value="vertical" <?php echo $design == 'vertical' ? 'selected' : ''; ?>>Vertical (Long)</option>
                                </select>
                            </div>
                            <div class="col-md-8 col-lg-9">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-control bg-light d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-credit-card-2-front"></i> Smart card format for one student</span>
                                    <span class="text-muted small"><?php echo htmlspecialchars(ucfirst($design)); ?> layout</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Design Template</label>
                                <select class="form-select" name="design" onchange="this.form.submit()">
                                    <option value="default" <?php echo $design == 'default' ? 'selected' : ''; ?>>Default (Horizontal)</option>
                                    <option value="modern" <?php echo $design == 'modern' ? 'selected' : ''; ?>>Modern</option>
                                    <option value="professional" <?php echo $design == 'professional' ? 'selected' : ''; ?>>Professional</option>
                                    <option value="colorful" <?php echo $design == 'colorful' ? 'selected' : ''; ?>>Colorful</option>
                                    <option value="vertical" <?php echo $design == 'vertical' ? 'selected' : ''; ?>>Vertical (Long)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select class="form-select" name="class_id">
                                    <option value="">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>"
                                            <?php echo $classId == $class['class_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Section</label>
                                <select class="form-select" name="section_id">
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section['section_id']; ?>"
                                            <?php echo $sectionId == $section['section_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($section['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    <?php if ($singleCardMode): ?>
                        <strong>Single student format ready.</strong> Choose a design template, then print or download PDF.
                    <?php else: ?>
                        <strong>Showing <?php echo count($students); ?> card(s)</strong>
                        - Select design and click Print to generate ID cards
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- ID Cards -->
    <div class="id-card-page">
        <div class="id-card-container">
            <?php foreach ($students as $student): ?>
                <?php
                    $attendanceScanUrl = buildStudentAttendanceScanUrl($student);
                    $attendanceQrUrl = buildQrCodeUrl($attendanceScanUrl, 120);
                    $studentAddress = trim((string)($student['address'] ?? ''));
                    $studentName = idCardSmartValue($student['student_name'] ?? '', 'N/A', true);
                    $fatherName = idCardSmartValue($student['father_name'] ?? '', 'N/A', true);
                    $admissionNo = idCardSmartValue($student['admission_no'] ?? '', 'N/A', true);
                    $rollNo = idCardSmartValue($student['roll_no'] ?? '', 'N/A', true);
                    $classSection = trim((string)($student['class_name'] ?? ''));
                    $classSection .= trim((string)($student['section_name'] ?? '')) !== ''
                        ? ($classSection !== '' ? ' - ' : '') . trim((string)($student['section_name'] ?? ''))
                        : '';
                    $classSection = idCardSmartValue($classSection, 'N/A', true);
                    $contactNo = idCardSmartValue($student['contact_no'] ?? '', 'N/A');
                    $cardClass = $singleCardMode ? $singleModeLayout['card_class'] : $design;
                ?>
                <div class="id-card <?php echo $cardClass; ?>">
                    <div class="id-card-header">
                        <h6><?php echo htmlspecialchars($schoolSettings['school_name']); ?></h6>
                        <p><?php echo htmlspecialchars($schoolSettings['school_address'] ?? ''); ?></p>
                    </div>
                    <div class="id-card-body">
                        <div class="id-card-photo">
                            <?php if (!empty($student['photo'])): ?>
                                <img src="<?php echo htmlspecialchars(getStudentPhotoSrc($student['photo'])); ?>"
                                     alt="<?php echo htmlspecialchars($student['student_name']); ?>">
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars(getStudentPhotoSrc()); ?>"
                                     alt="No Photo">
                            <?php endif; ?>
                        </div>
                        <div class="id-card-info">
                            <div class="info-row">
                                <span class="label">NAME:</span>
                                <div class="value smart-uppercase smart-tight"><strong><?php echo htmlspecialchars($studentName); ?></strong></div>
                            </div>
                            <div class="info-row">
                                <span class="label">FATHER NAME:</span>
                                <span class="value smart-uppercase smart-tight"><?php echo htmlspecialchars($fatherName); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">ADM / ROLL:</span>
                                <span class="value smart-uppercase smart-tight"><?php echo htmlspecialchars($admissionNo . ' / ' . $rollNo); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">CLASS / SECTION:</span>
                                <span class="value smart-uppercase smart-tight"><?php echo htmlspecialchars($classSection); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">CONTACT:</span>
                                <span class="value smart-uppercase smart-tight"><?php echo htmlspecialchars($contactNo); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">ADDRESS:</span>
                                <span class="value address"><?php echo htmlspecialchars($studentAddress !== '' ? $studentAddress : 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="id-card-footer">
                        <div class="id-card-footer-text">
                            <div>Scan QR to mark attendance</div>
                            <div>Valid for Academic Year <?php echo date('Y') . '-' . (date('Y') + 1); ?></div>
                        </div>
                        <div class="id-card-qr" title="Scan for attendance">
                            <?php if (!empty($attendanceQrUrl)): ?>
                                <img src="<?php echo htmlspecialchars($attendanceQrUrl); ?>" alt="Attendance QR">
                            <?php else: ?>
                                <div class="id-card-qr-fallback">QR</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (count($students) == 0): ?>
                <div class="no-print alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    No students found matching the criteria. Please adjust your filters.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$pageHtml = ob_get_clean();

if ($downloadPdf) {
    $downloadName = 'Student_ID_Card';
    if ($singleCardMode && !empty($primaryStudent)) {
        $nameBits = array_filter([
            'ID_Card',
            $primaryStudent['student_name'] ?? '',
            $primaryStudent['admission_no'] ?? '',
        ]);
        $downloadName = implode('_', $nameBits);
    }

        $pdfResult = pdfExportDownloadHtml($pageHtml, $downloadName);
    if (!empty($pdfResult['success'])) {
        exit();
    }

    die('PDF generation failed: ' . htmlspecialchars((string)($pdfResult['message'] ?? 'Unknown error')));
}

echo $pageHtml;
