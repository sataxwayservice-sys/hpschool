<?php
/**
 * Student List - View All Students
 * With search, filter, and actions
 */

// Include configuration (handles session start)
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('students', 'view');

$pageTitle = 'All Students';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Get filters
$searchTerm = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$classFilter = isset($_GET['class']) ? intval($_GET['class']) : 0;
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$query = "SELECT s.*, c.class_name, sec.section_name
          FROM students s
          JOIN classes c ON s.class_id = c.class_id
          JOIN sections sec ON s.section_id = sec.section_id
          WHERE 1=1";

$params = [];
$types = '';

if ($currentSchoolId > 0) {
    $query .= " AND s.school_id = ?";
    $params[] = $currentSchoolId;
    $types .= 'i';
}

if (!empty($searchTerm)) {
    $query .= " AND (s.student_name LIKE ? OR s.admission_no LIKE ? OR s.father_name LIKE ?)";
    $searchParam = '%' . $searchTerm . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if ($classFilter > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $classFilter;
    $types .= 'i';
}

if ($sectionFilter > 0) {
    $query .= " AND s.section_id = ?";
    $params[] = $sectionFilter;
    $types .= 'i';
}

if (!empty($statusFilter)) {
    $query .= " AND s.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$query .= " ORDER BY s.admission_no DESC";

// Fetch students
$students = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);

// Get classes and sections for filters
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
                <i class="bi bi-people-fill"></i> All Students
            </h2>
            <div>
                <a href="<?php echo APP_URL; ?>/modules/students/generate_id_card.php" class="btn btn-info">
                    <i class="bi bi-credit-card"></i> Generate ID Cards
                </a>
                <?php if (hasPermission('students', 'add')): ?>
                <a href="<?php echo APP_URL; ?>/modules/students/add.php" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill"></i> Add New Student
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card dashboard-card mb-4">
    <div class="card-body">
        <h5><i class="bi bi-funnel"></i> Search & Filter</h5>
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search name, admission no, father..."
                           autocomplete="off"
                           data-student-autocomplete="true"
                           data-student-autocomplete-fill="admission_no"
                           value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="class">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"
                                    <?php echo $classFilter == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="section">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['section_id']; ?>"
                                    <?php echo $sectionFilter == $section['section_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $statusFilter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card dashboard-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>
                <i class="bi bi-list-ul"></i> Student List
                <span class="badge bg-primary"><?php echo count($students); ?> students</span>
            </h5>
            <div>
                <button class="btn btn-success btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button class="btn btn-info btn-sm btn-export-excel" data-table=".datatable">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
            </div>
        </div>

        <?php if (count($students) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <?php if (!empty($student['photo'])): ?>
                                <img src="<?php echo htmlspecialchars(getStudentPhotoSrc($student['photo'])); ?>"
                                     class="student-photo-small" alt="Photo">
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars(getStudentPhotoSrc()); ?>"
                                     class="student-photo-small" alt="No Photo">
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($student['admission_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section_name']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($student['contact_no']); ?></td>
                        <td>
                            <?php if ($student['status'] == 'Active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="no-print">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $student['student_id']; ?>"
                                   class="btn btn-info" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasPermission('students', 'edit')): ?>
                                <a href="edit.php?id=<?php echo $student['student_id']; ?>"
                                   class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('students', 'delete')): ?>
                                <a href="delete.php?id=<?php echo $student['student_id']; ?>"
                                   class="btn btn-danger btn-delete" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this student?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle"></i> No students found.
            <?php if (hasPermission('students', 'add')): ?>
                <a href="add.php">Add your first student</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
}
</style>

<?php
// Include footer
include '../../includes/footer.php';
?>
