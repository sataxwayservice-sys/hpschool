<?php
/**
 * Student ID Card Generator
 * Generate printable student ID cards with different designs
 */

// Include configuration
require_once '../../config/config.php';

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

// Get school settings
$schoolSettings = getSchoolSettings();

// Get classes for filter
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Cards - <?php echo htmlspecialchars($schoolSettings['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
        }

        .id-card-page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
            margin: 0 auto;
            background: white;
        }

        .id-card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8mm;
            justify-content: center;
        }

        /* Default Design - Vertical Card */
        .id-card {
            width: 85.6mm;
            height: 54mm;
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
            padding: 2mm;
            display: flex;
            gap: 2mm;
        }

        .id-card-photo {
            width: 20mm;
            height: 25mm;
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
            font-size: 8pt;
        }

        .id-card-info .info-row {
            margin-bottom: 1mm;
        }

        .id-card-info .label {
            font-weight: bold;
            color: #555;
        }

        .id-card-info .value {
            color: #000;
        }

        .id-card-footer {
            background: #f8f9fa;
            padding: 1mm 2mm;
            text-align: center;
            font-size: 6pt;
            color: #666;
            border-top: 1px solid #ddd;
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
            padding: 3mm;
        }

        .id-card.vertical .id-card-photo {
            width: 30mm;
            height: 35mm;
            margin-bottom: 2mm;
        }

        .id-card.vertical .id-card-info {
            text-align: center;
            font-size: 9pt;
        }

        .barcode {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            letter-spacing: 2px;
            padding: 1mm;
            background: white;
            border: 1px solid #ddd;
            margin-top: 1mm;
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
                padding: 10mm;
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
                <h3><i class="bi bi-credit-card"></i> Student ID Cards</h3>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print Cards
                    </button>
                    <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="control-panel">
                <form method="GET" action="">
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
                </form>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Showing <?php echo count($students); ?> card(s)</strong>
                    - Select design and click Print to generate ID cards
                </div>
            </div>
        </div>
    </div>

    <!-- ID Cards -->
    <div class="id-card-page">
        <div class="id-card-container">
            <?php foreach ($students as $student): ?>
                <div class="id-card <?php echo $design; ?>">
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
                                <span class="label">Name:</span>
                                <div class="value"><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></div>
                            </div>
                            <div class="info-row">
                                <span class="label">Admission:</span>
                                <span class="value"><?php echo htmlspecialchars($student['admission_no']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Class:</span>
                                <span class="value"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Roll No:</span>
                                <span class="value"><?php echo htmlspecialchars($student['roll_no']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Contact:</span>
                                <span class="value"><?php echo htmlspecialchars($student['contact_no']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="id-card-footer">
                        <div class="barcode">*<?php echo $student['admission_no']; ?>*</div>
                        <div style="margin-top: 1mm; font-size: 5pt;">
                            Valid for Academic Year <?php echo date('Y') . '-' . (date('Y') + 1); ?>
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
