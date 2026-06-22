<?php
/**
 * Student Reminders Management
 * Search students and manage reminders
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();

$pageTitle = 'Student Reminders';
$currentUser = getCurrentUser();
$isAdmin = in_array($currentUser['role'], ['super_admin', 'admin']);
$isTeacher = $currentUser['role'] === 'teacher';

// Handle add reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reminder'])) {
    $studentId = intval($_POST['student_id']);
    $reminderText = sanitize($_POST['reminder_text']);
    $reminderType = sanitize($_POST['reminder_type']);
    $priority = sanitize($_POST['priority']);
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    $query = "INSERT INTO student_reminders (student_id, reminder_text, reminder_type, priority, due_date, created_by, created_by_role, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($dueDate) {
        executeQuery($query, 'issssis', [$studentId, $reminderText, $reminderType, $priority, $dueDate, $currentUser['user_id'], $currentUser['role']]);
    } else {
        $query = "INSERT INTO student_reminders (student_id, reminder_text, reminder_type, priority, created_by, created_by_role, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";
        executeQuery($query, 'isssis', [$studentId, $reminderText, $reminderType, $priority, $currentUser['user_id'], $currentUser['role']]);
    }

    logActivity($currentUser['user_id'], 'Reminder Added', 'Students', "Added reminder for student ID: $studentId");
    $_SESSION['success_message'] = "Reminder added successfully!";
    header("Location: reminders.php?search=" . urlencode($_POST['search_term']));
    exit();
}

// Handle edit reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reminder'])) {
    $reminderId = intval($_POST['reminder_id']);
    $reminderText = sanitize($_POST['reminder_text']);
    $reminderType = sanitize($_POST['reminder_type']);
    $priority = sanitize($_POST['priority']);
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    $query = "UPDATE student_reminders
              SET reminder_text = ?, reminder_type = ?, priority = ?, due_date = ?, updated_at = NOW()
              WHERE reminder_id = ?";

    executeQuery($query, 'ssssi', [$reminderText, $reminderType, $priority, $dueDate, $reminderId]);

    logActivity($currentUser['user_id'], 'Reminder Updated', 'Students', "Updated reminder ID: $reminderId");
    $_SESSION['success_message'] = "Reminder updated successfully!";
    header("Location: reminders.php?search=" . urlencode($_POST['search_term']));
    exit();
}

// Handle mark as resolved
if (isset($_GET['resolve']) && !empty($_GET['resolve'])) {
    $reminderId = intval($_GET['resolve']);

    $query = "UPDATE student_reminders
              SET status = 'Resolved', is_resolved = 1, resolved_at = NOW(), resolved_by = ?
              WHERE reminder_id = ?";
    executeQuery($query, 'ii', [$currentUser['user_id'], $reminderId]);

    logActivity($currentUser['user_id'], 'Reminder Resolved', 'Students', "Marked reminder ID: $reminderId as resolved");
    $_SESSION['success_message'] = "Reminder marked as resolved!";
    header("Location: reminders.php" . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
    exit();
}

// Handle delete (Admin only)
if ($isAdmin && isset($_GET['delete']) && !empty($_GET['delete'])) {
    $reminderId = intval($_GET['delete']);

    executeQuery("DELETE FROM student_reminders WHERE reminder_id = ?", 'i', [$reminderId]);

    logActivity($currentUser['user_id'], 'Reminder Deleted', 'Students', "Deleted reminder ID: $reminderId");
    $_SESSION['success_message'] = "Reminder deleted successfully!";
    header("Location: reminders.php" . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
    exit();
}

// Get search results
$searchResults = [];
$activeReminders = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = sanitize($_GET['search']);

    $searchQuery = "SELECT s.*, c.class_name, sec.section_name
                    FROM students s
                    LEFT JOIN classes c ON s.class_id = c.class_id
                    LEFT JOIN sections sec ON s.section_id = sec.section_id
                    WHERE (s.student_name LIKE ? OR s.roll_no LIKE ? OR s.admission_no LIKE ?)
                    AND s.status = 'Active'
                    LIMIT 10";
    $searchResults = fetchAll($searchQuery, 'sss', ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);

    // If exactly one student found, get their active reminders
    if (count($searchResults) === 1) {
        $studentId = $searchResults[0]['student_id'];
        $activeReminders = fetchAll(
            "SELECT r.*, u.full_name as created_by_name, ru.full_name as resolved_by_name
             FROM student_reminders r
             LEFT JOIN users u ON r.created_by = u.user_id
             LEFT JOIN users ru ON r.resolved_by = ru.user_id
             WHERE r.student_id = ? AND r.status = 'Active'
             ORDER BY r.priority DESC, r.due_date ASC",
            'i',
            [$studentId]
        );
    }
}

// Get all active reminders for dashboard
$allActiveReminders = fetchAll(
    "SELECT r.*, s.student_name, s.admission_no, s.roll_no, c.class_name, sec.section_name,
            u.full_name as created_by_name
     FROM student_reminders r
     JOIN students s ON r.student_id = s.student_id
     LEFT JOIN classes c ON s.class_id = c.class_id
     LEFT JOIN sections sec ON s.section_id = sec.section_id
     LEFT JOIN users u ON r.created_by = u.user_id
     WHERE r.status = 'Active'" . (!$isAdmin ? " AND r.created_by = {$currentUser['user_id']}" : "") . "
     ORDER BY r.priority DESC, r.due_date ASC
     LIMIT 50"
);

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-bell"></i> Student Reminders
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/students/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search Box -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-search"></i> Search Student</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-9">
                            <input type="text" class="form-control form-control-lg" name="search"
                                   placeholder="Search by Student Name, Roll No, or Admission No..."
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="admission_no"
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                   required autofocus>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Search Results -->
<?php if (!empty($searchResults)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-person-check"></i> Search Results (<?php echo count($searchResults); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Admission No</th>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Active Reminders</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $student): ?>
                            <?php
                            $reminderCount = fetchOne(
                                "SELECT COUNT(*) as count FROM student_reminders WHERE student_id = ? AND status = 'Active'",
                                'i',
                                [$student['student_id']]
                            );
                            $count = $reminderCount ? $reminderCount['count'] : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section_name']); ?></td>
                                <td>
                                    <?php if ($count > 0): ?>
                                        <span class="badge bg-warning text-dark"><?php echo $count; ?> Reminders</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Reminders</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary"
                                            onclick="addReminder(<?php echo $student['student_id']; ?>, '<?php echo addslashes($student['student_name']); ?>')">
                                        <i class="bi bi-plus-circle"></i> Add Reminder
                                    </button>
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
<?php endif; ?>

<!-- Active Reminders Dashboard -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo $isAdmin ? 'All Active Reminders' : 'My Active Reminders'; ?>
                    (<?php echo count($allActiveReminders); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($allActiveReminders) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Reminder</th>
                                <th>Type</th>
                                <th>Due Date</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allActiveReminders as $reminder): ?>
                            <tr class="<?php echo $reminder['priority'] === 'Urgent' ? 'table-danger' : ($reminder['priority'] === 'High' ? 'table-warning' : ''); ?>">
                                <td>
                                    <span class="badge bg-<?php
                                        echo $reminder['priority'] === 'Urgent' ? 'danger' :
                                            ($reminder['priority'] === 'High' ? 'warning text-dark' :
                                            ($reminder['priority'] === 'Medium' ? 'info' : 'secondary'));
                                    ?>">
                                        <?php echo htmlspecialchars($reminder['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reminder['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($reminder['admission_no']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($reminder['class_name'] . ' ' . $reminder['section_name']); ?></td>
                                <td><?php echo htmlspecialchars($reminder['reminder_text']); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($reminder['reminder_type']); ?></span>
                                </td>
                                <td>
                                    <?php if ($reminder['due_date']): ?>
                                        <?php
                                        $dueDate = strtotime($reminder['due_date']);
                                        $today = strtotime(date('Y-m-d'));
                                        $isOverdue = $dueDate < $today;
                                        ?>
                                        <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('d-M-Y', $dueDate); ?>
                                            <?php if ($isOverdue): ?>
                                                <i class="bi bi-exclamation-circle text-danger"></i>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($reminder['created_by_name'] ?? 'Unknown'); ?><br>
                                        <span class="badge bg-info"><?php echo ucwords($reminder['created_by_role'] ?? ''); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-warning"
                                                onclick='editReminder(<?php echo json_encode($reminder, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?resolve=<?php echo $reminder['reminder_id']; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Mark this reminder as resolved?')">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                        <a href="?delete=<?php echo $reminder['reminder_id']; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this reminder permanently?')">
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
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No active reminders found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Reminder Modal -->
<div class="modal fade" id="addReminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> Add Reminder
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="add_student_id">
                    <input type="hidden" name="search_term" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                    <div class="mb-3">
                        <label class="form-label"><strong>Student:</strong></label>
                        <p id="add_student_name" class="form-control-plaintext"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reminder Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reminder_text" rows="3" required
                                  placeholder="e.g., Submit transfer certificate before Friday"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="reminder_type" required>
                                <option value="Other">Other</option>
                                <option value="Academic">Academic</option>
                                <option value="Behavioral">Behavioral</option>
                                <option value="Document">Document</option>
                                <option value="Fee">Fee</option>
                                <option value="Medical">Medical</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Due Date (Optional)</label>
                        <input type="date" class="form-control" name="due_date" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_reminder" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Reminder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Reminder Modal -->
<div class="modal fade" id="editReminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Edit Reminder
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="reminder_id" id="edit_reminder_id">
                    <input type="hidden" name="search_term" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                    <div class="mb-3">
                        <label class="form-label">Reminder Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reminder_text" id="edit_reminder_text" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="reminder_type" id="edit_reminder_type" required>
                                <option value="Other">Other</option>
                                <option value="Academic">Academic</option>
                                <option value="Behavioral">Behavioral</option>
                                <option value="Document">Document</option>
                                <option value="Fee">Fee</option>
                                <option value="Medical">Medical</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" id="edit_priority" required>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-control" name="due_date" id="edit_due_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_reminder" class="btn btn-warning">
                        <i class="bi bi-save"></i> Update Reminder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto-show Reminder Popup -->
<?php if (count($searchResults) === 1 && count($activeReminders) > 0): ?>
<div class="modal fade" id="autoReminderModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-warning border-3">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill"></i> ⚠ Active Reminders for <?php echo htmlspecialchars($searchResults[0]['student_name']); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>This student has <?php echo count($activeReminders); ?> active reminder(s)!</strong>
                </div>

                <?php foreach ($activeReminders as $reminder): ?>
                <div class="card mb-3 border-<?php echo $reminder['priority'] === 'Urgent' ? 'danger' : 'warning'; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">
                                    <span class="badge bg-<?php
                                        echo $reminder['priority'] === 'Urgent' ? 'danger' :
                                            ($reminder['priority'] === 'High' ? 'warning text-dark' : 'info');
                                    ?>">
                                        <?php echo $reminder['priority']; ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo $reminder['reminder_type']; ?></span>
                                </h6>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($reminder['reminder_text'])); ?></p>
                                <small class="text-muted">
                                    <?php if ($reminder['due_date']): ?>
                                        <strong>Due:</strong> <?php echo date('d-M-Y', strtotime($reminder['due_date'])); ?> |
                                    <?php endif; ?>
                                    <strong>Created by:</strong> <?php echo htmlspecialchars($reminder['created_by_name']); ?>
                                    (<?php echo ucwords($reminder['created_by_role']); ?>)
                                </small>
                            </div>
                            <div>
                                <a href="?resolve=<?php echo $reminder['reminder_id']; ?>&search=<?php echo urlencode($_GET['search']); ?>"
                                   class="btn btn-sm btn-success"
                                   onclick="return confirm('Mark as resolved?')">
                                    <i class="bi bi-check-circle"></i> Resolve
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$inlineScript = "
// Auto-show reminder popup
<?php if (count($searchResults) === 1 && count($activeReminders) > 0): ?>
$(document).ready(function() {
    var autoModal = new bootstrap.Modal(document.getElementById('autoReminderModal'));
    autoModal.show();
});
<?php endif; ?>

function addReminder(studentId, studentName) {
    document.getElementById('add_student_id').value = studentId;
    document.getElementById('add_student_name').textContent = studentName;
    var addModal = new bootstrap.Modal(document.getElementById('addReminderModal'));
    addModal.show();
}

function editReminder(reminder) {
    document.getElementById('edit_reminder_id').value = reminder.reminder_id;
    document.getElementById('edit_reminder_text').value = reminder.reminder_text;
    document.getElementById('edit_reminder_type').value = reminder.reminder_type;
    document.getElementById('edit_priority').value = reminder.priority;
    document.getElementById('edit_due_date').value = reminder.due_date || '';
    var editModal = new bootstrap.Modal(document.getElementById('editReminderModal'));
    editModal.show();
}
";

// Include footer
include '../../includes/footer.php';
?>
