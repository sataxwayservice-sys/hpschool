<?php
/**
 * Classes Management
 * Manage school classes
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('settings', 'edit');

$pageTitle = 'Classes Management';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Handle delete
if (isset($_GET['delete']) && hasPermission('settings', 'delete')) {
    $classId = intval($_GET['delete']);

    // Check if class has students
    $studentCountQuery = "SELECT COUNT(*) as count FROM students WHERE class_id = ?";
    $studentCountParams = [$classId];
    $studentCountTypes = 'i';
    if ($currentSchoolId > 0) {
        $studentCountQuery .= " AND school_id = ?";
        $studentCountParams[] = $currentSchoolId;
        $studentCountTypes .= 'i';
    }
    $studentCount = fetchOne($studentCountQuery, $studentCountTypes, $studentCountParams);

    if ($studentCount['count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete class. It has {$studentCount['count']} student(s) enrolled.";
    } else {
        if (executeQuery("DELETE FROM classes WHERE class_id = ?", 'i', [$classId])) {
            logActivity($currentUser['user_id'], 'Class Deleted', 'settings', "Deleted class ID: $classId");
            $_SESSION['success_message'] = "Class deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete class!";
        }
    }
    header("Location: classes.php");
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $className = sanitize($_POST['class_name']);
    $classOrder = intval($_POST['display_order']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($className)) {
        $_SESSION['error_message'] = "Class name is required!";
    } else {
        if ($classId > 0) {
            // Update
            $query = "UPDATE classes SET class_name = ?, class_order = ?, is_active = ? WHERE class_id = ?";
            if (executeQuery($query, 'siii', [$className, $classOrder, $isActive, $classId])) {
                logActivity($currentUser['user_id'], 'Class Updated', 'settings', "Updated class: $className");
                $_SESSION['success_message'] = "Class updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update class!";
            }
        } else {
            // Insert
            $query = "INSERT INTO classes (class_name, class_order, is_active) VALUES (?, ?, ?)";
            if (executeQuery($query, 'sii', [$className, $classOrder, $isActive])) {
                logActivity($currentUser['user_id'], 'Class Added', 'settings', "Added new class: $className");
                $_SESSION['success_message'] = "Class added successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to add class!";
            }
        }
    }
    header("Location: classes.php");
    exit();
}

// Get all classes
$classesQuery = "SELECT c.*, COUNT(s.student_id) as student_count
                 FROM classes c
                 LEFT JOIN students s ON c.class_id = s.class_id";
$classesParams = [];
$classesTypes = '';
if ($currentSchoolId > 0) {
    $classesQuery .= " AND s.school_id = ?";
    $classesParams[] = $currentSchoolId;
    $classesTypes .= 'i';
}
$classesQuery .= " GROUP BY c.class_id
                  ORDER BY c.class_order";
$classes = empty($classesTypes) ? fetchAll($classesQuery) : fetchAll($classesQuery, $classesTypes, $classesParams);

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-building"></i> Classes Management
            </h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classModal" onclick="clearForm()">
                    <i class="bi bi-plus-circle"></i> Add New Class
                </button>
                <a href="<?php echo APP_URL; ?>/modules/settings/sections.php" class="btn btn-outline-secondary">
                    <i class="bi bi-grid-3x3-gap"></i> Sections
                </a>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h6>Total Classes</h6>
                <h3><?php echo count($classes); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h6>Active Classes</h6>
                <h3><?php echo count(array_filter($classes, fn($c) => $c['is_active'] == 1)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h6>Total Students</h6>
                <h3><?php echo array_sum(array_column($classes, 'student_count')); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Classes Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Classes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Class Name</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo $class['class_order']; ?></td>
                                <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $class['student_count']; ?> students</span>
                                </td>
                                <td>
                                    <?php if ($class['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning"
                                            onclick="editClass(<?php echo htmlspecialchars(json_encode($class)); ?>)"
                                            data-bs-toggle="modal"
                                            data-bs-target="#classModal">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ($class['student_count'] == 0): ?>
                                    <a href="?delete=<?php echo $class['class_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this class?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="class_id" id="class_id">

                    <div class="mb-3">
                        <label class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="class_name" id="class_name" required>
                        <small class="text-muted">e.g., Class 1, Class 2, Nursery, KG, etc.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Display Order <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="display_order" id="display_order"
                                value="<?php echo count($classes) + 1; ?>" required>
                        <small class="text-muted">Lower number appears first</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                <strong>Active</strong>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineScript = "
function clearForm() {
    $('#modalTitle').text('Add New Class');
    $('#class_id').val('');
    $('#class_name').val('');
    $('#display_order').val(" . (count($classes) + 1) . ");
    $('#is_active').prop('checked', true);
}

function editClass(classData) {
    $('#modalTitle').text('Edit Class');
    $('#class_id').val(classData.class_id);
    $('#class_name').val(classData.class_name);
    $('#display_order').val(classData.class_order);
    $('#is_active').prop('checked', classData.is_active == 1);
}
";

// Include footer
include '../../includes/footer.php';
?>
