<?php
/**
 * Sections Management
 * Manage school sections (A, B, C, etc.)
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('settings', 'edit');

$pageTitle = 'Sections Management';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Handle delete
if (isset($_GET['delete']) && hasPermission('settings', 'delete')) {
    $sectionId = intval($_GET['delete']);

    // Check if section has students
    $studentCountQuery = "SELECT COUNT(*) as count FROM students WHERE section_id = ?";
    $studentCountParams = [$sectionId];
    $studentCountTypes = 'i';
    if ($currentSchoolId > 0) {
        $studentCountQuery .= " AND school_id = ?";
        $studentCountParams[] = $currentSchoolId;
        $studentCountTypes .= 'i';
    }
    $studentCount = fetchOne($studentCountQuery, $studentCountTypes, $studentCountParams);

    if ($studentCount['count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete section. It has {$studentCount['count']} student(s) enrolled.";
    } else {
        if (executeQuery("DELETE FROM sections WHERE section_id = ?", 'i', [$sectionId])) {
            logActivity($currentUser['user_id'], 'Section Deleted', 'settings', "Deleted section ID: $sectionId");
            $_SESSION['success_message'] = "Section deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete section!";
        }
    }
    header("Location: sections.php");
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sectionId = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    $sectionName = sanitize($_POST['section_name']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($sectionName)) {
        $_SESSION['error_message'] = "Section name is required!";
    } else {
        if ($sectionId > 0) {
            // Update
            $query = "UPDATE sections SET section_name = ?, is_active = ? WHERE section_id = ?";
            if (executeQuery($query, 'sii', [$sectionName, $isActive, $sectionId])) {
                logActivity($currentUser['user_id'], 'Section Updated', 'settings', "Updated section: $sectionName");
                $_SESSION['success_message'] = "Section updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update section!";
            }
        } else {
            // Insert
            $query = "INSERT INTO sections (section_name, is_active) VALUES (?, ?)";
            if (executeQuery($query, 'si', [$sectionName, $isActive])) {
                logActivity($currentUser['user_id'], 'Section Added', 'settings', "Added new section: $sectionName");
                $_SESSION['success_message'] = "Section added successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to add section!";
            }
        }
    }
    header("Location: sections.php");
    exit();
}

// Get all sections
$sectionsQuery = "SELECT s.*, COUNT(st.student_id) as student_count
                  FROM sections s
                  LEFT JOIN students st ON s.section_id = st.section_id";
$sectionsParams = [];
$sectionsTypes = '';
if ($currentSchoolId > 0) {
    $sectionsQuery .= " AND st.school_id = ?";
    $sectionsParams[] = $currentSchoolId;
    $sectionsTypes .= 'i';
}
$sectionsQuery .= " GROUP BY s.section_id
                   ORDER BY s.section_name";
$sections = empty($sectionsTypes) ? fetchAll($sectionsQuery) : fetchAll($sectionsQuery, $sectionsTypes, $sectionsParams);

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-diagram-3"></i> Sections Management
            </h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectionModal" onclick="clearForm()">
                    <i class="bi bi-plus-circle"></i> Add New Section
                </button>
                <a href="<?php echo APP_URL; ?>/modules/settings/classes.php" class="btn btn-outline-secondary">
                    <i class="bi bi-diagram-3"></i> Classes
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
                <h6>Total Sections</h6>
                <h3><?php echo count($sections); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h6>Active Sections</h6>
                <h3><?php echo count(array_filter($sections, fn($s) => $s['is_active'] == 1)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h6>Total Students</h6>
                <h3><?php echo array_sum(array_column($sections, 'student_count')); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Sections Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Sections</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Section Name</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?php echo $section['section_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($section['section_name']); ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $section['student_count']; ?> students</span>
                                </td>
                                <td>
                                    <?php if ($section['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning"
                                            onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)"
                                            data-bs-toggle="modal"
                                            data-bs-target="#sectionModal">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ($section['student_count'] == 0): ?>
                                    <a href="?delete=<?php echo $section['section_id']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this section?')">
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
<div class="modal fade" id="sectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="section_id" id="section_id">

                    <div class="mb-3">
                        <label class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="section_name" id="section_name" required>
                        <small class="text-muted">e.g., A, B, C, Morning, Evening, etc.</small>
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
                        <i class="bi bi-save"></i> Save Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineScript = "
function clearForm() {
    $('#modalTitle').text('Add New Section');
    $('#section_id').val('');
    $('#section_name').val('');
    $('#is_active').prop('checked', true);
}

function editSection(sectionData) {
    $('#modalTitle').text('Edit Section');
    $('#section_id').val(sectionData.section_id);
    $('#section_name').val(sectionData.section_name);
    $('#is_active').prop('checked', sectionData.is_active == 1);
}
";

// Include footer
include '../../includes/footer.php';
?>
