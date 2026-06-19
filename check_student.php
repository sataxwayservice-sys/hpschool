<?php
/**
 * Quick Student Check
 */

require_once 'config/config.php';
requireLogin();

$pageTitle = "Student Check";
include 'includes/header.php';

// Get search parameters
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$admNo = isset($_GET['adm']) ? sanitize($_GET['adm']) : '';

// Search for student
$student = null;
$searchResults = [];

if (!empty($admNo)) {
    // Direct admission number search
    $student = fetchOne("SELECT * FROM students WHERE admission_no = ?", 's', [$admNo]);
} elseif (!empty($searchQuery)) {
    // Search by admission number or name
    $searchResults = fetchAll(
        "SELECT * FROM students
         WHERE admission_no LIKE ? OR student_name LIKE ? OR roll_no LIKE ?
         ORDER BY student_name LIMIT 20",
        'sss',
        ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]
    );

    // If exactly one result, auto-select it
    if (count($searchResults) == 1) {
        $student = $searchResults[0];
    }
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4"><i class="bi bi-search"></i> Student Search & Check</h2>

            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-search"></i> Search Student</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text"
                                           class="form-control"
                                           name="search"
                                           placeholder="Enter Admission No, Student Name, or Roll No..."
                                           autocomplete="off"
                                           data-student-autocomplete="true"
                                           data-student-autocomplete-fill="admission_no"
                                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                                           autofocus>
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <a href="check_student.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Results (Multiple) -->
            <?php if (!empty($searchQuery) && count($searchResults) > 1): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Search Results (<?php echo count($searchResults); ?> found)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Roll No</th>
                                    <th>Father Name</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                    <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['roll_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($s['father_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $s['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo htmlspecialchars($s['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?adm=<?php echo urlencode($s['admission_no']); ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- No Results -->
            <?php if (!empty($searchQuery) && count($searchResults) == 0): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <strong>No students found</strong> matching "<?php echo htmlspecialchars($searchQuery); ?>"
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Student Details -->
    <?php if ($student): ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-person-check-fill"></i> Student Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">Student ID</th>
                            <td><?php echo $student['student_id']; ?></td>
                        </tr>
                        <tr>
                            <th>Admission No</th>
                            <td><strong><?php echo htmlspecialchars($student['admission_no']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Student Name</th>
                            <td><strong class="text-primary"><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Father Name</th>
                            <td><?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Mother Name</th>
                            <td><?php echo htmlspecialchars($student['mother_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Roll No</th>
                            <td><?php echo htmlspecialchars($student['roll_no'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Batch</th>
                            <td><?php echo htmlspecialchars($student['batch'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="badge bg-<?php echo $student['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($student['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Contact No</th>
                            <td><?php echo htmlspecialchars($student['contact_no'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo (isset($student['dob']) && $student['dob']) ? date('d M Y', strtotime($student['dob'])) : 'N/A'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <?php

    // Check for student reminders
    $studentId = $student['student_id'];
    $reminders = fetchAll("SELECT r.*, u.username as created_by_name
                           FROM student_reminders r
                           LEFT JOIN users u ON r.created_by = u.user_id
                           WHERE r.student_id = ? AND r.is_resolved = 0
                           ORDER BY r.priority DESC, r.created_at DESC", 'i', [$studentId]);

    if (count($reminders) > 0) {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var reminderModal = new bootstrap.Modal(document.getElementById('reminderModal'));
            reminderModal.show();
        });
        </script>";
    }
?>
            <!-- Reminders Card -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-bell-fill"></i> Student Reminders
                        <?php if (count($reminders) > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo count($reminders); ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($reminders) > 0): ?>
                        <div class="alert alert-danger alert-permanent">
                            <strong><i class="bi bi-exclamation-triangle-fill"></i> <?php echo count($reminders); ?> Active Reminder(s)!</strong>
                        </div>
                        <ul class="list-group">
                            <?php foreach (array_slice($reminders, 0, 3) as $reminder): ?>
                            <li class="list-group-item">
                                <?php if ($reminder['priority'] == 'high'): ?>🔴<?php elseif ($reminder['priority'] == 'medium'): ?>🟡<?php else: ?>🟢<?php endif; ?>
                                <?php echo htmlspecialchars($reminder['reminder_text']); ?>
                                <br><small class="text-muted">Added <?php echo date('M d, Y', strtotime($reminder['created_at'])); ?></small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($reminders) > 3): ?>
                        <p class="mt-2 mb-0"><small>+ <?php echo count($reminders) - 3; ?> more...</small></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-success mb-0"><i class="bi bi-check-circle"></i> No active reminders</p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-warning btn-sm mt-3 w-100" onclick="openReminderModal()">
                        <i class="bi bi-bell"></i> View/Add Reminders
                    </button>
                </div>
            </div>

            <!-- Fee Structure Card -->
            <?php
            $fees = fetchAll("SELECT fs.*, fh.fee_head_name FROM fee_structure fs
                             JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
                             WHERE fs.student_id = ? AND fs.is_active = 1", 'i', [$student['student_id']]);
            ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Fee Structure</h5>
                </div>
                <div class="card-body">
                    <?php if (count($fees) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fee Head</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total = 0;
                                    foreach ($fees as $fee):
                                        $total += $fee['amount'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fee['fee_head_name']); ?></td>
                                        <td class="text-end">₹<?php echo number_format($fee['amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-active">
                                        <th>Total</th>
                                        <th class="text-end">₹<?php echo number_format($total, 2); ?></th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle"></i> No fees assigned to this student
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning-fill"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="modules/fees/collect_complete.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-success w-100">
                                <i class="bi bi-cash-coin"></i> Collect Fees
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/students/?id=<?php echo $student['student_id']; ?>" class="btn btn-primary w-100">
                                <i class="bi bi-person-lines-fill"></i> View Full Profile
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button type="button" class="btn btn-warning w-100" onclick="openReminderModal()">
                                <i class="bi bi-bell-fill"></i> Manage Reminders
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="check_student.php" class="btn btn-secondary w-100">
                                <i class="bi bi-search"></i> New Search
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reminder Modal -->
<?php if ($student): ?>
<div class="modal fade" id="reminderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">⚠️ Student Reminders - <?php echo htmlspecialchars($student['student_name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Active Reminders List -->
                <div id="remindersList">
                    <?php if (count($reminders) > 0): ?>
                        <?php foreach ($reminders as $reminder): ?>
                            <div class="alert alert-<?php echo $reminder['priority'] == 'high' ? 'danger' : ($reminder['priority'] == 'medium' ? 'warning' : 'info'); ?> alert-permanent reminder-item" data-reminder-id="<?php echo $reminder['reminder_id']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php if ($reminder['priority'] == 'high'): ?>
                                                🔴 HIGH PRIORITY
                                            <?php elseif ($reminder['priority'] == 'medium'): ?>
                                                🟡 MEDIUM
                                            <?php else: ?>
                                                🟢 LOW
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-2 reminder-text-<?php echo $reminder['reminder_id']; ?>"><?php echo nl2br(htmlspecialchars($reminder['reminder_text'])); ?></p>
                                        <small class="text-muted">
                                            Added by <strong><?php echo htmlspecialchars($reminder['created_by_name'] ?? 'Unknown'); ?></strong>
                                            <?php if (!empty($reminder['created_by_role'])): ?>
                                                (<?php echo ucfirst(htmlspecialchars($reminder['created_by_role'])); ?>)
                                            <?php endif; ?>
                                            on <?php echo date('M d, Y h:i A', strtotime($reminder['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="btn-group-vertical ms-2">
                                        <button class="btn btn-sm btn-success" onclick="resolveReminder(<?php echo $reminder['reminder_id']; ?>)" title="Mark as Resolved">✅</button>
                                        <button class="btn btn-sm btn-primary" onclick="editReminder(<?php echo $reminder['reminder_id']; ?>, '<?php echo addslashes($reminder['reminder_text']); ?>', '<?php echo $reminder['priority']; ?>')" title="Edit">✏️</button>
                                        <?php if ($_SESSION['role'] == 'admin'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteReminder(<?php echo $reminder['reminder_id']; ?>)" title="Delete">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <p class="mb-0">✓ No active reminders for this student.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <hr>

                <!-- Add New Reminder Form -->
                <h6>Add New Reminder:</h6>
                <form id="addReminderForm">
                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                    <div class="mb-3">
                        <label class="form-label">Reminder Text:</label>
                        <textarea class="form-control" name="reminder_text" rows="3" required placeholder="E.g., Submit transfer certificate before Friday"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority:</label>
                        <select class="form-select" name="priority">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🔴 High</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Reminder</button>
                </form>

                <!-- Edit Reminder Form (Hidden) -->
                <div id="editReminderForm" style="display:none;">
                    <hr>
                    <h6>Edit Reminder:</h6>
                    <form id="editReminderFormInner">
                        <input type="hidden" name="reminder_id" id="edit_reminder_id">
                        <div class="mb-3">
                            <label class="form-label">Reminder Text:</label>
                            <textarea class="form-control" name="reminder_text" id="edit_reminder_text" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority:</label>
                            <select class="form-select" name="priority" id="edit_reminder_priority">
                                <option value="low">🟢 Low</option>
                                <option value="medium">🟡 Medium</option>
                                <option value="high">🔴 High</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Update Reminder</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openReminderModal() {
    var reminderModal = new bootstrap.Modal(document.getElementById('reminderModal'));
    reminderModal.show();
}

// Add new reminder
document.getElementById('addReminderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_reminder');

    fetch('ajax/reminder_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error adding reminder');
        console.error(error);
    });
});

// Edit reminder
document.getElementById('editReminderFormInner').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'edit_reminder');

    fetch('ajax/reminder_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating reminder');
        console.error(error);
    });
});

function editReminder(id, text, priority) {
    document.getElementById('edit_reminder_id').value = id;
    document.getElementById('edit_reminder_text').value = text;
    document.getElementById('edit_reminder_priority').value = priority;
    document.getElementById('editReminderForm').style.display = 'block';
    document.getElementById('addReminderForm').style.display = 'none';
}

function cancelEdit() {
    document.getElementById('editReminderForm').style.display = 'none';
    document.getElementById('addReminderForm').style.display = 'block';
}

function resolveReminder(id) {
    if (!confirm('Mark this reminder as resolved?')) return;

    const formData = new FormData();
    formData.append('action', 'resolve_reminder');
    formData.append('reminder_id', id);

    fetch('ajax/reminder_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteReminder(id) {
    if (!confirm('Permanently delete this reminder? This cannot be undone.')) return;

    const formData = new FormData();
    formData.append('action', 'delete_reminder');
    formData.append('reminder_id', id);

    fetch('ajax/reminder_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
