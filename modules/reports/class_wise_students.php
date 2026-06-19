<?php
/**
 * Class-wise Students Report
 * Students grouped by class and section
 */

// Include configuration
require_once '../../config/config.php';
require_once '../../includes/report_export.php';

// Require login
requireLogin();
requirePermission('reports', 'view');

$pageTitle = 'Class-wise Students Report';
$currentUser = getCurrentUser();

// Handle export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
    $sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : 'Active';

    // Build query
    $query = "SELECT
                s.admission_no, s.student_name, s.roll_no, s.date_of_birth, s.gender,
                s.father_name, s.mother_name, s.contact_no, s.email,
                s.admission_date, s.status,
                c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE 1=1";

    $params = [];
    $types = '';

    if (!empty($status)) {
        $query .= " AND s.status = ?";
        $types .= 's';
        $params[] = $status;
    }

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

    $query .= " ORDER BY c.class_order, sec.section_name, s.roll_no";

    $students = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    $schoolSettings = getSchoolSettings();

    // Generate Excel file
    $filename = "Class_Wise_Students_Report_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Add BOM for proper Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    reportExportWriteCsvHeaderRows(
        $output,
        $schoolSettings,
        'Class-wise Students Report',
        date('d M Y, h:i A'),
        [
            'Class ID' => $classId > 0 ? (string) $classId : 'All',
            'Section ID' => $sectionId > 0 ? (string) $sectionId : 'All',
            'Status' => !empty($status) ? $status : 'All',
        ]
    );

    // Header row
    fputcsv($output, [
        'S.No',
        'Class',
        'Section',
        'Roll No',
        'Admission No',
        'Student Name',
        'Date of Birth',
        'Gender',
        'Father Name',
        'Mother Name',
        'Contact No',
        'Email',
        'Admission Date',
        'Status'
    ]);

    // Data rows
    $sno = 1;
    foreach ($students as $student) {
        fputcsv($output, [
            $sno++,
            $student['class_name'],
            $student['section_name'],
            $student['roll_no'],
            $student['admission_no'],
            $student['student_name'],
            date('d-M-Y', strtotime($student['date_of_birth'])),
            $student['gender'],
            $student['father_name'],
            $student['mother_name'],
            $student['contact_no'],
            $student['email'] ?? '-',
            date('d-M-Y', strtotime($student['admission_date'])),
            $student['status']
        ]);
    }

    fclose($output);
    exit();
}

// Get filter data
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-building"></i> Class-wise Students Report
            </h2>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Report Filters -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="reportForm">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
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
                                        <?php echo (isset($_GET['section_id']) && $_GET['section_id'] == $section['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="Active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Active') ? 'selected' : 'selected'; ?>>Active</option>
                                <option value="Inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Passout" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Passout') ? 'selected' : ''; ?>>Passout</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview and Export -->
<?php if (isset($_GET['class_id']) && !empty($_GET['class_id'])): ?>
<?php
    $classId = intval($_GET['class_id']);
    $sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : 'Active';

    $query = "SELECT
                s.student_id, s.admission_no, s.student_name, s.roll_no,
                s.date_of_birth, s.gender, s.father_name, s.contact_no, s.status,
                c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.class_id = ?";

    $params = [$classId];
    $types = 'i';

    if (!empty($status)) {
        $query .= " AND s.status = ?";
        $types .= 's';
        $params[] = $status;
    }

    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $types .= 'i';
        $params[] = $sectionId;
    }

    $query .= " ORDER BY sec.section_name, s.roll_no";

    $previewData = fetchAll($query, $types, $params);

    // Group by section
    $groupedData = [];
    foreach ($previewData as $student) {
        $sectionKey = $student['section_name'];
        if (!isset($groupedData[$sectionKey])) {
            $groupedData[$sectionKey] = [];
        }
        $groupedData[$sectionKey][] = $student;
    }
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-eye"></i> Class-wise Students Report</h5>
                <div>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-light btn-sm">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Class:</strong> <?php echo htmlspecialchars($previewData[0]['class_name'] ?? 'N/A'); ?> |
                    <strong>Total Students:</strong> <?php echo count($previewData); ?>
                </div>

                <?php foreach ($groupedData as $sectionName => $students): ?>
                <div class="mb-4">
                    <h5 class="bg-light p-2">
                        <i class="bi bi-people-fill"></i> Section: <?php echo htmlspecialchars($sectionName); ?>
                        <span class="badge bg-primary"><?php echo count($students); ?> Students</span>
                    </h5>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>S.No</th>
                                    <th>Roll No</th>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>DOB</th>
                                    <th>Gender</th>
                                    <th>Father Name</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sno = 1;
                                foreach ($students as $student):
                                ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/modules/students/view.php?id=<?php echo $student['student_id']; ?>"
                                           target="_blank">
                                            <?php echo htmlspecialchars($student['student_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($student['date_of_birth'])); ?></td>
                                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['contact_no']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $student['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($student['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (count($previewData) == 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No students found for the selected criteria.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include '../../includes/footer.php';
?>
