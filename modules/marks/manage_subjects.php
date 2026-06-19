<?php
/**
 * Manage Subjects
 * Add, Edit, Delete, and View Subjects
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('marks', 'add');

$pageTitle = 'Manage Subjects';
$currentUser = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add New Subject
    if (isset($_POST['add_subject'])) {
        $subjectName = sanitize($_POST['subject_name']);
        $subjectCode = sanitize($_POST['subject_code']);
        $maxMarks = (int)$_POST['max_marks'];
        $passMarks = (int)$_POST['pass_marks'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($subjectName)) {
            $error = 'Subject name is required!';
        } elseif ($passMarks > $maxMarks) {
            $error = 'Pass marks cannot be greater than max marks!';
        } else {
            // Check if subject code already exists
            if (!empty($subjectCode)) {
                $checkQuery = "SELECT subject_id FROM subjects WHERE subject_code = ?";
                $existing = fetchOne($checkQuery, 's', [$subjectCode]);
                if ($existing) {
                    $error = 'Subject code already exists!';
                }
            }

            if (empty($error)) {
                $query = "INSERT INTO subjects (subject_name, subject_code, max_marks, pass_marks, is_active)
                          VALUES (?, ?, ?, ?, ?)";
                $result = executeQuery($query, 'ssiii', [
                    $subjectName, $subjectCode, $maxMarks, $passMarks, $isActive
                ]);

                if ($result) {
                    $message = 'Subject added successfully!';
                    logActivity($currentUser['user_id'], 'Add Subject', 'marks', "Added subject: $subjectName");
                } else {
                    $conn = getDbConnection();
                    $mysqlError = $conn->error;
                    $error = 'Failed to add subject! ' . ($mysqlError ? 'MySQL Error: ' . htmlspecialchars($mysqlError) : 'Please run test_exams_table.php to check if tables exist.');
                }
            }
        }
    }

    // Update Subject
    if (isset($_POST['update_subject'])) {
        $subjectId = (int)$_POST['subject_id'];
        $subjectName = sanitize($_POST['subject_name']);
        $subjectCode = sanitize($_POST['subject_code']);
        $maxMarks = (int)$_POST['max_marks'];
        $passMarks = (int)$_POST['pass_marks'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($subjectName)) {
            $error = 'Subject name is required!';
        } elseif ($passMarks > $maxMarks) {
            $error = 'Pass marks cannot be greater than max marks!';
        } else {
            // Check if subject code already exists (excluding current subject)
            if (!empty($subjectCode)) {
                $checkQuery = "SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ?";
                $existing = fetchOne($checkQuery, 'si', [$subjectCode, $subjectId]);
                if ($existing) {
                    $error = 'Subject code already exists!';
                }
            }

            if (empty($error)) {
                $query = "UPDATE subjects
                          SET subject_name = ?, subject_code = ?, max_marks = ?, pass_marks = ?, is_active = ?
                          WHERE subject_id = ?";
                $result = executeQuery($query, 'ssiiii', [
                    $subjectName, $subjectCode, $maxMarks, $passMarks, $isActive, $subjectId
                ]);

                if ($result) {
                    $message = 'Subject updated successfully!';
                    logActivity($currentUser['user_id'], 'Update Subject', 'marks', "Updated subject: $subjectName");
                } else {
                    $conn = getDbConnection();
                    $mysqlError = $conn->error;
                    $error = 'Failed to update subject! ' . ($mysqlError ? 'MySQL Error: ' . htmlspecialchars($mysqlError) : '');
                }
            }
        }
    }

    // Delete Subject
    if (isset($_POST['delete_subject'])) {
        $subjectId = (int)$_POST['subject_id'];

        // Check if subject has marks entered
        $checkQuery = "SELECT COUNT(*) as count FROM marks WHERE subject_id = ?";
        $check = fetchOne($checkQuery, 'i', [$subjectId]);

        if ($check && $check['count'] > 0) {
            $error = 'Cannot delete subject! Marks have been entered for this subject.';
        } else {
            $query = "DELETE FROM subjects WHERE subject_id = ?";
            $result = executeQuery($query, 'i', [$subjectId]);

            if ($result) {
                $message = 'Subject deleted successfully!';
                logActivity($currentUser['user_id'], 'Delete Subject', 'marks', "Deleted subject ID: $subjectId");
            } else {
                $conn = getDbConnection();
                $mysqlError = $conn->error;
                $error = 'Failed to delete subject! ' . ($mysqlError ? 'MySQL Error: ' . htmlspecialchars($mysqlError) : '');
            }
        }
    }
}

// Get all subjects
$subjects = fetchAll("SELECT * FROM subjects ORDER BY subject_name ASC");

// Get subject for editing
$editSubject = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editSubject = fetchOne("SELECT * FROM subjects WHERE subject_id = ?", 'i', [$editId]);
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-book"></i> Manage Subjects
            </h2>
            <div>
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
    <!-- Add/Edit Subject Form -->
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $editSubject ? 'pencil' : 'plus'; ?>-circle"></i>
                    <?php echo $editSubject ? 'Edit Subject' : 'Add New Subject'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editSubject): ?>
                        <input type="hidden" name="subject_id" value="<?php echo $editSubject['subject_id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_name"
                               value="<?php echo htmlspecialchars($editSubject['subject_name'] ?? ''); ?>"
                               placeholder="e.g., Mathematics" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" class="form-control" name="subject_code"
                               value="<?php echo htmlspecialchars($editSubject['subject_code'] ?? ''); ?>"
                               placeholder="e.g., MATH" maxlength="20">
                        <small class="text-muted">Optional unique code for the subject</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Maximum Marks <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="max_marks"
                               value="<?php echo $editSubject['max_marks'] ?? 100; ?>"
                               min="1" max="1000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Passing Marks <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="pass_marks"
                               value="<?php echo $editSubject['pass_marks'] ?? 33; ?>"
                               min="1" max="1000" required>
                        <small class="text-muted">Minimum marks required to pass</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active"
                               <?php echo (!$editSubject || $editSubject['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>

                    <div class="d-grid gap-2">
                        <?php if ($editSubject): ?>
                            <button type="submit" name="update_subject" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Update Subject
                            </button>
                            <a href="manage_subjects.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_subject" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Add Subject
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Subjects List -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> All Subjects (<?php echo count($subjects); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($subjects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Code</th>
                                    <th>Max Marks</th>
                                    <th>Pass Marks</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                    <td>
                                        <?php if (!empty($subject['subject_code'])): ?>
                                            <code><?php echo htmlspecialchars($subject['subject_code']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $subject['max_marks']; ?></td>
                                    <td><?php echo $subject['pass_marks']; ?></td>
                                    <td>
                                        <?php if ($subject['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="?edit=<?php echo $subject['subject_id']; ?>"
                                               class="btn btn-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['subject_id']; ?>">
                                                <button type="submit" name="delete_subject"
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
                        No subjects found. Add your first subject using the form on the left.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="alert alert-info mt-3">
            <h6><i class="bi bi-lightbulb"></i> Quick Tips:</h6>
            <ul class="mb-0">
                <li>Subject codes should be unique (e.g., MATH, ENG, SCI)</li>
                <li>Set appropriate max marks and pass marks for each subject</li>
                <li>Mark subjects as inactive instead of deleting them</li>
                <li>You cannot delete subjects that have marks entered</li>
                <li>Ensure passing marks are less than maximum marks</li>
            </ul>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>
