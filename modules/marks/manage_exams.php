<?php
/**
 * Manage Exams
 * Add, Edit, Delete, and View Exams
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('marks', 'add');

$pageTitle = 'Manage Exams';
$currentUser = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add New Exam
    if (isset($_POST['add_exam'])) {
        $examName = sanitize($_POST['exam_name']);
        $examType = sanitize($_POST['exam_type']);
        $examDate = $_POST['exam_date'];
        $academicYear = sanitize($_POST['academic_year']);
        $description = sanitize($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($examName) || empty($examDate)) {
            $error = 'Exam name and date are required!';
        } else {
            $query = "INSERT INTO exams (exam_name, exam_type, exam_date, academic_year, description, is_active, created_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $result = executeQuery($query, 'sssssii', [
                $examName, $examType, $examDate, $academicYear, $description, $isActive, $currentUser['user_id']
            ]);

            if ($result) {
                $message = 'Exam added successfully!';
                logActivity($currentUser['user_id'], 'Add Exam', 'marks', "Added exam: $examName");
            } else {
                $conn = getDbConnection();
                $mysqlError = $conn->error;
                $error = 'Failed to add exam! ' . ($mysqlError ? 'MySQL Error: ' . htmlspecialchars($mysqlError) : 'Please run test_exams_table.php to check if tables exist.');
            }
        }
    }

    // Update Exam
    if (isset($_POST['update_exam'])) {
        $examId = (int)$_POST['exam_id'];
        $examName = sanitize($_POST['exam_name']);
        $examType = sanitize($_POST['exam_type']);
        $examDate = $_POST['exam_date'];
        $academicYear = sanitize($_POST['academic_year']);
        $description = sanitize($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($examName) || empty($examDate)) {
            $error = 'Exam name and date are required!';
        } else {
            $query = "UPDATE exams
                      SET exam_name = ?, exam_type = ?, exam_date = ?, academic_year = ?,
                          description = ?, is_active = ?
                      WHERE exam_id = ?";
            $result = executeQuery($query, 'sssssii', [
                $examName, $examType, $examDate, $academicYear, $description, $isActive, $examId
            ]);

            if ($result) {
                $message = 'Exam updated successfully!';
                logActivity($currentUser['user_id'], 'Update Exam', 'marks', "Updated exam: $examName");
            } else {
                $error = 'Failed to update exam!';
            }
        }
    }

    // Delete Exam
    if (isset($_POST['delete_exam'])) {
        $examId = (int)$_POST['exam_id'];

        // Check if exam has marks entered
        $checkQuery = "SELECT COUNT(*) as count FROM marks WHERE exam_id = ?";
        $check = fetchOne($checkQuery, 'i', [$examId]);

        if ($check && $check['count'] > 0) {
            $error = 'Cannot delete exam! Marks have been entered for this exam.';
        } else {
            $query = "DELETE FROM exams WHERE exam_id = ?";
            $result = executeQuery($query, 'i', [$examId]);

            if ($result) {
                $message = 'Exam deleted successfully!';
                logActivity($currentUser['user_id'], 'Delete Exam', 'marks', "Deleted exam ID: $examId");
            } else {
                $error = 'Failed to delete exam!';
            }
        }
    }
}

// Get all exams
$exams = fetchAll("SELECT e.*, u.full_name as created_by_name
                   FROM exams e
                   LEFT JOIN users u ON e.created_by = u.user_id
                   ORDER BY e.exam_date DESC, e.exam_id DESC");

// Get exam for editing
$editExam = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editExam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$editId]);
}

// Get current academic year
$currentYear = date('Y');
$nextYear = $currentYear + 1;
$defaultAcademicYear = "$currentYear-$nextYear";

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-calendar-check"></i> Manage Exams
            </h2>
            <div class="d-flex gap-2 flex-wrap">
                <a href="exam_routine.php" class="btn btn-warning">
                    <i class="bi bi-calendar2-week"></i> Routine
                </a>
                <a href="manage_subjects.php" class="btn btn-info">
                    <i class="bi bi-book"></i> Subjects
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Marks
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Add/Edit Exam Form -->
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $editExam ? 'pencil' : 'plus'; ?>-circle"></i>
                    <?php echo $editExam ? 'Edit Exam' : 'Add New Exam'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editExam): ?>
                        <input type="hidden" name="exam_id" value="<?php echo $editExam['exam_id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="exam_name"
                               value="<?php echo htmlspecialchars($editExam['exam_name'] ?? ''); ?>"
                               placeholder="e.g., Mid Term Exam" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Exam Type</label>
                        <select class="form-select" name="exam_type">
                            <option value="">-- Select Type --</option>
                            <?php
                            $types = ['Monthly', 'Quarterly', 'Half-Yearly', 'Annual', 'Unit Test', 'Practice Test'];
                            foreach ($types as $type):
                                $selected = ($editExam && $editExam['exam_type'] === $type) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $type; ?>" <?php echo $selected; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Exam Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="exam_date"
                               value="<?php echo $editExam['exam_date'] ?? ''; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Academic Year</label>
                        <input type="text" class="form-control" name="academic_year"
                               value="<?php echo htmlspecialchars($editExam['academic_year'] ?? $defaultAcademicYear); ?>"
                               placeholder="e.g., 2024-2025">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"
                                  placeholder="Optional description"><?php echo htmlspecialchars($editExam['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active"
                               <?php echo (!$editExam || $editExam['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>

                    <div class="d-grid gap-2">
                        <?php if ($editExam): ?>
                            <button type="submit" name="update_exam" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Update Exam
                            </button>
                            <a href="manage_exams.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_exam" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Add Exam
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Exams List -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> All Exams (<?php echo count($exams); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($exams) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Academic Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong>
                                        <?php if (!empty($exam['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($exam['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($exam['exam_type'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($exam['exam_type']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($exam['exam_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($exam['academic_year'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($exam['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="?edit=<?php echo $exam['exam_id']; ?>"
                                               class="btn btn-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this exam?');">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['exam_id']; ?>">
                                                <button type="submit" name="delete_exam"
                                                        class="btn btn-danger btn-sm" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        No exams found. Add your first exam using the form on the left.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="alert alert-info mt-3">
            <h6><i class="bi bi-lightbulb"></i> Quick Tips:</h6>
            <ul class="mb-0">
                <li>Add exams for the current academic year</li>
                <li>Mark exams as inactive when they are completed</li>
                <li>You cannot delete exams that have marks entered</li>
                <li>Exam types help organize different assessment categories</li>
            </ul>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>
