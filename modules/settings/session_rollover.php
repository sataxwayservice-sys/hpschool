<?php
/**
 * Session Rollover
 * Close the current academic session and move into the next one
 */

require_once '../../config/config.php';
requireLogin();

$pageTitle = 'Session Rollover';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$rolloverAccessRole = $currentUser['role'] ?? '';
if ($rolloverAccessRole !== 'super_admin') {
    requireRolePermissionForSchool('session_rollover', 'view', $currentSchoolId, $rolloverAccessRole);
}
$error = '';
$success = '';

$conn = getDbConnection();

// Create supporting tables if they do not exist yet
$conn->query("CREATE TABLE IF NOT EXISTS academic_years (
    year_id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS session_rollover_logs (
    rollover_id INT AUTO_INCREMENT PRIMARY KEY,
    from_academic_year VARCHAR(20) NOT NULL,
    to_academic_year VARCHAR(20) NOT NULL,
    session_end_date DATE DEFAULT NULL,
    next_session_start_date DATE DEFAULT NULL,
    due_students_count INT DEFAULT 0,
    due_amount_total DECIMAL(12,2) DEFAULT 0.00,
    active_reminder_count INT DEFAULT 0,
    urgent_reminder_count INT DEFAULT 0,
    active_student_count INT DEFAULT 0,
    batch_updated_count INT DEFAULT 0,
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from_year (from_academic_year),
    INDEX idx_to_year (to_academic_year),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$executeStatement = function ($sql, $types = '', $params = []) use ($conn) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $message = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception($message);
    }

    return $stmt;
};

$isValidDate = function ($value) {
    if (empty($value)) {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
};

$schoolSettings = getSchoolSettings();
$activeAcademicYear = trim($schoolSettings['current_academic_year'] ?? '');
if (empty($activeAcademicYear)) {
    $activeAcademicYear = date('Y') . '-' . (date('Y') + 1);
}

$activeYearRecord = fetchOne(
    "SELECT * FROM academic_years WHERE year_name = ?",
    's',
    [$activeAcademicYear]
);

if (!$activeYearRecord && !empty($activeAcademicYear)) {
    $currentYearDates = getAcademicYearDateRange($activeAcademicYear);
    $stmt = $executeStatement(
        "INSERT INTO academic_years (year_name, start_date, end_date, is_active, created_at)
         VALUES (?, ?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date), is_active = 1, updated_at = NOW()",
        'sss',
        [$activeAcademicYear, $currentYearDates['start_date'], $currentYearDates['end_date']]
    );
    $stmt->close();
    $activeYearRecord = fetchOne(
        "SELECT * FROM academic_years WHERE year_name = ?",
        's',
        [$activeAcademicYear]
    );
}

if ($activeYearRecord && intval($activeYearRecord['is_active'] ?? 0) !== 1) {
    $stmt = $executeStatement(
        "UPDATE academic_years SET is_active = 1 WHERE year_name = ?",
        's',
        [$activeAcademicYear]
    );
    $stmt->close();
    $activeYearRecord['is_active'] = 1;
}

$currentAcademicYear = $activeAcademicYear;
$closedAcademicYear = $activeAcademicYear;

$nextAcademicYearSuggestion = getNextAcademicYearLabel($currentAcademicYear);
$nextAcademicYearDates = getAcademicYearDateRange($nextAcademicYearSuggestion);
$defaultPreviousSessionEndDate = !empty($activeYearRecord['end_date'])
    ? $activeYearRecord['end_date']
    : date('Y-m-d', strtotime($nextAcademicYearDates['start_date'] . ' -1 day'));

$formNewAcademicYear = $nextAcademicYearSuggestion;
$formPreviousSessionEndDate = $defaultPreviousSessionEndDate;
$formNewSessionStartDate = $nextAcademicYearDates['start_date'];
$formNewSessionEndDate = $nextAcademicYearDates['end_date'];
$formCarryStudents = true;
$formNotes = '';

$pendingSnapshot = getSessionPendingSummary($defaultPreviousSessionEndDate);
$rolloverCompleted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_rollover'])) {
    $formNewAcademicYear = sanitize($_POST['new_academic_year'] ?? '');
    $formPreviousSessionEndDate = sanitize($_POST['previous_session_end_date'] ?? '');
    $formNewSessionStartDate = sanitize($_POST['new_session_start_date'] ?? '');
    $formNewSessionEndDate = sanitize($_POST['new_session_end_date'] ?? '');
    $formCarryStudents = isset($_POST['carry_students']);
    $formNotes = sanitize($_POST['notes'] ?? '');

    if (empty($formNewAcademicYear)) {
        $formNewAcademicYear = $nextAcademicYearSuggestion;
    }

    if (empty($formPreviousSessionEndDate)) {
        $formPreviousSessionEndDate = $defaultPreviousSessionEndDate;
    }

    if (empty($formNewSessionStartDate)) {
        $formNewSessionStartDate = $nextAcademicYearDates['start_date'];
    }

    if (empty($formNewSessionEndDate)) {
        $formNewSessionEndDate = $nextAcademicYearDates['end_date'];
    }

    if ($formNewAcademicYear === $closedAcademicYear) {
        $error = 'The new academic year must be different from the current active year.';
    } elseif (!$isValidDate($formPreviousSessionEndDate)) {
        $error = 'Please enter a valid previous session end date.';
    } elseif (!$isValidDate($formNewSessionStartDate)) {
        $error = 'Please enter a valid new session start date.';
    } elseif (!$isValidDate($formNewSessionEndDate)) {
        $error = 'Please enter a valid new session end date.';
    } elseif (strtotime($formNewSessionStartDate) <= strtotime($formPreviousSessionEndDate)) {
        $error = 'The new session must start after the previous session ends.';
    } elseif (strtotime($formNewSessionEndDate) < strtotime($formNewSessionStartDate)) {
        $error = 'The new session end date must be after the new session start date.';
    } else {
        $pendingSnapshot = getSessionPendingSummary($formPreviousSessionEndDate);
        $dueStudentCount = intval($pendingSnapshot['due_student_count'] ?? 0);
        $dueAmountTotal = floatval($pendingSnapshot['due_amount_total'] ?? 0);
        $activeReminderCount = intval($pendingSnapshot['active_reminder_count'] ?? 0);
        $urgentReminderCount = intval($pendingSnapshot['urgent_reminder_count'] ?? 0);
        $activeStudentCount = intval($pendingSnapshot['active_student_count'] ?? 0);
        $studentsUpdated = 0;

        beginTransaction();
        try {
            $stmt = $executeStatement(
                "INSERT INTO academic_years (year_name, start_date, end_date, is_active, created_at)
                 VALUES (?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date), is_active = 1, updated_at = NOW()",
                'sss',
                [$formNewAcademicYear, $formNewSessionStartDate, $formNewSessionEndDate]
            );
            $stmt->close();

            $stmt = $executeStatement("UPDATE academic_years SET is_active = 0");
            $stmt->close();

            $stmt = $executeStatement(
                "UPDATE academic_years SET is_active = 1 WHERE year_name = ?",
                's',
                [$formNewAcademicYear]
            );
            $stmt->close();

            $settingsId = intval($schoolSettings['setting_id'] ?? 1);
            $stmt = $executeStatement(
                "UPDATE school_settings SET current_academic_year = ?, updated_at = NOW() WHERE setting_id = ?",
                'si',
                [$formNewAcademicYear, $settingsId]
            );
            $stmt->close();

            if ($formCarryStudents) {
                if ($currentSchoolId > 0) {
                    $stmt = $executeStatement(
                        "UPDATE students SET batch = ? WHERE status = 'Active' AND school_id = ?",
                        'si',
                        [$formNewAcademicYear, $currentSchoolId]
                    );
                } else {
                    $stmt = $executeStatement(
                        "UPDATE students s
                         INNER JOIN schools sc ON sc.school_id = s.school_id
                         SET s.batch = ?
                         WHERE s.status = 'Active'",
                        's',
                        [$formNewAcademicYear]
                    );
                }
                $studentsUpdated = $stmt->affected_rows;
                $stmt->close();
            }

            $stmt = $executeStatement(
                "INSERT INTO session_rollover_logs
                    (from_academic_year, to_academic_year, session_end_date, next_session_start_date,
                     due_students_count, due_amount_total, active_reminder_count, urgent_reminder_count,
                     active_student_count, batch_updated_count, notes, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                'ssssidiiiisi',
                [
                    $closedAcademicYear,
                    $formNewAcademicYear,
                    $formPreviousSessionEndDate,
                    $formNewSessionStartDate,
                    $dueStudentCount,
                    $dueAmountTotal,
                    $activeReminderCount,
                    $urgentReminderCount,
                    $activeStudentCount,
                    $studentsUpdated,
                    $formNotes,
                    $currentUser['user_id']
                ]
            );
            $stmt->close();

            commitTransaction();

            $rolloverCompleted = true;
            $success = 'Session rollover completed from ' . htmlspecialchars($closedAcademicYear) . ' to ' . htmlspecialchars($formNewAcademicYear) . '. ';
            if ($formCarryStudents) {
                $success .= number_format($studentsUpdated) . ' active student batch(es) were moved forward. ';
            } else {
                $success .= 'Active student batches were left unchanged. ';
            }
            $success .= number_format($dueStudentCount) . ' student(s) had dues totaling ' . formatCurrency($dueAmountTotal) . '. ';
            $success .= number_format($activeReminderCount) . ' reminder(s) remain open.';

            logActivity(
                $currentUser['user_id'],
                'Session Rollover',
                'Settings',
                "Rolled over from {$closedAcademicYear} to {$formNewAcademicYear}"
            );

            $activeAcademicYear = $formNewAcademicYear;
            $currentAcademicYear = $activeAcademicYear;
            $activeYearRecord = fetchOne(
                "SELECT * FROM academic_years WHERE year_name = ?",
                's',
                [$activeAcademicYear]
            );

            $nextAcademicYearSuggestion = getNextAcademicYearLabel($activeAcademicYear);
            $nextAcademicYearDates = getAcademicYearDateRange($nextAcademicYearSuggestion);
            $formNewAcademicYear = $nextAcademicYearSuggestion;
            $formPreviousSessionEndDate = !empty($activeYearRecord['end_date'])
                ? $activeYearRecord['end_date']
                : date('Y-m-d', strtotime($nextAcademicYearDates['start_date'] . ' -1 day'));
            $formNewSessionStartDate = $nextAcademicYearDates['start_date'];
            $formNewSessionEndDate = $nextAcademicYearDates['end_date'];
            $formCarryStudents = true;
            $formNotes = '';
        } catch (Exception $e) {
            rollbackTransaction();
            $error = 'Session rollover failed: ' . $e->getMessage();
        }
    }
}

$rolloverHistory = fetchAll(
    "SELECT r.*, u.full_name as created_by_name, u.role as created_by_role
     FROM session_rollover_logs r
     LEFT JOIN users u ON r.created_by = u.user_id
     ORDER BY r.created_at DESC
     LIMIT 10"
);

$dueStudentsPreview = array_slice($pendingSnapshot['due_students'] ?? [], 0, 10);
$dueClassPreview = array_slice($pendingSnapshot['due_by_class'] ?? [], 0, 6, true);
$remindersPreview = array_slice($pendingSnapshot['active_reminders'] ?? [], 0, 10);

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-arrow-repeat"></i> Session Rollover</h2>
                <div class="text-muted">Close the current session, review pending items, and open the next year.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?php echo APP_URL; ?>/modules/settings/academic_years.php" class="btn btn-outline-primary">
                    <i class="bi bi-calendar-range"></i> Academic Years
                </a>
                <a href="<?php echo APP_URL; ?>/modules/students/promote.php" class="btn btn-outline-success">
                    <i class="bi bi-arrow-up-circle"></i> Promote Students
                </a>
                <a href="<?php echo APP_URL; ?>/modules/reports/due_fees.php" class="btn btn-outline-warning">
                    <i class="bi bi-exclamation-triangle"></i> Due Fees
                </a>
                <a href="<?php echo APP_URL; ?>/modules/settings/school.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Settings
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>What carries forward?</strong> Open fee dues, unresolved reminders, and active students are the key things to review before the next session.
    Exam and mark records stay tied to their academic year, while student promotion is still handled separately on the Promote Students page.
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Current Session Snapshot</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Active Academic Year</div>
                            <div class="fs-5 fw-bold"><?php echo htmlspecialchars($currentAcademicYear); ?></div>
                            <?php if ($rolloverCompleted): ?>
                                <div class="small text-success mt-1"><i class="bi bi-check-circle"></i> New session is active</div>
                                <div class="small text-muted">The counts on this page still show the closed-session snapshot for review.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Active Students</div>
                            <div class="fs-5 fw-bold"><?php echo number_format($pendingSnapshot['active_student_count'] ?? 0); ?></div>
                            <div class="small text-muted">Will be carried forward if you keep the checkbox enabled</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Open Reminders</div>
                            <div class="fs-5 fw-bold"><?php echo number_format($pendingSnapshot['active_reminder_count'] ?? 0); ?></div>
                            <div class="small text-muted">Urgent: <?php echo number_format($pendingSnapshot['urgent_reminder_count'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Session Dates</div>
                            <div class="fw-semibold">
                                <?php echo !empty($activeYearRecord['start_date']) ? date('d M Y', strtotime($activeYearRecord['start_date'])) : '-'; ?>
                                to
                                <?php echo !empty($activeYearRecord['end_date']) ? date('d M Y', strtotime($activeYearRecord['end_date'])) : '-'; ?>
                            </div>
                            <div class="small text-muted mt-1">These dates define the session snapshot used for the pending review.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Pending Fee Dues</div>
                            <div class="fw-semibold">
                                <?php echo number_format($pendingSnapshot['due_student_count'] ?? 0); ?> student(s)
                                <span class="text-danger">with <?php echo formatCurrency($pendingSnapshot['due_amount_total'] ?? 0); ?></span>
                            </div>
                            <div class="small text-muted mt-1">Use the report below to review the largest pending balances first.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="bi bi-list-check"></i> Due by Class</h6>
                        <span class="badge bg-warning text-dark"><?php echo count($dueClassPreview); ?> groups</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Class</th>
                                    <th class="text-end">Students</th>
                                    <th class="text-end">Due Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dueClassPreview)): ?>
                                    <?php foreach ($dueClassPreview as $className => $classSummary): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($className); ?></td>
                                            <td class="text-end"><?php echo number_format($classSummary['count'] ?? 0); ?></td>
                                            <td class="text-end text-danger fw-semibold"><?php echo formatCurrency($classSummary['amount'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No pending dues were found for the selected session date.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Start Next Session</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="start_rollover" value="1">

                    <div class="mb-3">
                        <label for="new_academic_year" class="form-label required">New Academic Year</label>
                        <input type="text" class="form-control" id="new_academic_year" name="new_academic_year"
                               value="<?php echo htmlspecialchars($formNewAcademicYear); ?>" required>
                        <small class="text-muted">Example: 2026-2027</small>
                    </div>

                    <div class="mb-3">
                        <label for="previous_session_end_date" class="form-label required">Previous Session End Date</label>
                        <input type="date" class="form-control" id="previous_session_end_date" name="previous_session_end_date"
                               value="<?php echo htmlspecialchars($formPreviousSessionEndDate); ?>" required>
                        <small class="text-muted">This date is used to calculate dues from the closing session.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_session_start_date" class="form-label required">New Session Start Date</label>
                            <input type="date" class="form-control" id="new_session_start_date" name="new_session_start_date"
                                   value="<?php echo htmlspecialchars($formNewSessionStartDate); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_session_end_date" class="form-label required">New Session End Date</label>
                            <input type="date" class="form-control" id="new_session_end_date" name="new_session_end_date"
                                   value="<?php echo htmlspecialchars($formNewSessionEndDate); ?>" required>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="carry_students" name="carry_students" <?php echo $formCarryStudents ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="carry_students">
                            <strong>Update active students to the new session</strong>
                            <br><small class="text-muted">Keeps current students' batch/year in sync with the new academic year.</small>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Rollover Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Optional note for this rollover"><?php echo htmlspecialchars($formNotes); ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Start session rollover now? This will activate the new academic year.');">
                            <i class="bi bi-arrow-right-circle"></i> Start Session Rollover
                        </button>
                        <a href="<?php echo APP_URL; ?>/modules/students/promote.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-up-circle"></i> Open Student Promotion
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="card dashboard-card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Pending Fee Students</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong>Snapshot for:</strong> <?php echo htmlspecialchars($closedAcademicYear); ?>
                        <div class="text-muted small">Calculated as of <?php echo !empty($formPreviousSessionEndDate) ? date('d M Y', strtotime($formPreviousSessionEndDate)) : '-'; ?></div>
                    </div>
                    <a href="<?php echo APP_URL; ?>/modules/reports/due_fees.php" class="btn btn-sm btn-warning">
                        <i class="bi bi-file-earmark-text"></i> Full Due Fee Report
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dueStudentsPreview)): ?>
                                <?php foreach ($dueStudentsPreview as $student): ?>
                                    <tr>
                                        <?php
                                            $paidAmount = floatval($student['total_paid'] ?? 0);
                                            $dueAmount = floatval($student['due_amount'] ?? 0);
                                            $statusLabel = $paidAmount > 0 ? 'Partial' : 'Unpaid';
                                            $statusClass = $paidAmount > 0 ? 'warning text-dark' : 'danger';
                                        ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['student_name'] ?? ''); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['admission_no'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars(trim(($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? ''))); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($paidAmount); ?></td>
                                        <td class="text-end text-danger fw-bold"><?php echo formatCurrency($dueAmount); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo APP_URL; ?>/modules/fees/collect_complete.php?student_id=<?php echo intval($student['student_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No fee dues were found for this snapshot.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card dashboard-card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-bell-fill"></i> Open Reminders</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong><?php echo number_format($pendingSnapshot['active_reminder_count'] ?? 0); ?></strong> unresolved reminder(s)
                        <div class="text-muted small"><?php echo number_format($pendingSnapshot['urgent_reminder_count'] ?? 0); ?> are marked urgent</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Reminder</th>
                                <th>Priority</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($remindersPreview)): ?>
                                <?php foreach ($remindersPreview as $reminder): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($reminder['student_name'] ?? ''); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($reminder['admission_no'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                                $reminderText = (string)($reminder['reminder_text'] ?? '');
                                                $reminderPreview = strlen($reminderText) > 70 ? substr($reminderText, 0, 70) . '...' : $reminderText;
                                            ?>
                                            <?php echo htmlspecialchars($reminderPreview); ?>
                                        </td>
                                        <td>
                                            <?php
                                                $priority = strtolower($reminder['priority'] ?? 'medium');
                                                $priorityClass = $priority === 'high' ? 'danger' : ($priority === 'medium' ? 'warning text-dark' : 'secondary');
                                            ?>
                                            <span class="badge bg-<?php echo $priorityClass; ?>"><?php echo htmlspecialchars(ucfirst($priority)); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo APP_URL; ?>/check_student.php?adm=<?php echo urlencode($reminder['admission_no'] ?? ''); ?>" class="btn btn-sm btn-outline-primary">
                                                Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No open reminders were found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Session Rollovers</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Close Date</th>
                                <th>New Start</th>
                                <th class="text-end">Fee Dues</th>
                                <th class="text-end">Reminders</th>
                                <th class="text-end">Students</th>
                                <th>By</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rolloverHistory)): ?>
                                <?php foreach ($rolloverHistory as $rollover): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rollover['from_academic_year'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($rollover['to_academic_year'] ?? '-'); ?></td>
                                        <td><?php echo !empty($rollover['session_end_date']) ? date('d M Y', strtotime($rollover['session_end_date'])) : '-'; ?></td>
                                        <td><?php echo !empty($rollover['next_session_start_date']) ? date('d M Y', strtotime($rollover['next_session_start_date'])) : '-'; ?></td>
                                        <td class="text-end">
                                            <div class="fw-semibold"><?php echo number_format($rollover['due_students_count'] ?? 0); ?> student(s)</div>
                                            <div class="text-danger"><?php echo formatCurrency($rollover['due_amount_total'] ?? 0); ?></div>
                                        </td>
                                        <td class="text-end"><?php echo number_format($rollover['active_reminder_count'] ?? 0); ?></td>
                                        <td class="text-end"><?php echo number_format($rollover['batch_updated_count'] ?? 0); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($rollover['created_by_name'] ?? 'System'); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(ucfirst($rollover['created_by_role'] ?? '')); ?></small>
                                        </td>
                                        <td><?php echo !empty($rollover['created_at']) ? date('d M Y h:i A', strtotime($rollover['created_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No rollover history yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
function formatDateInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

function syncNextSessionDates() {
    const prevDate = document.getElementById('previous_session_end_date');
    const nextStart = document.getElementById('new_session_start_date');
    const nextEnd = document.getElementById('new_session_end_date');
    const newYear = document.getElementById('new_academic_year');

    if (!prevDate || !nextStart || !nextEnd || !newYear || !prevDate.value) {
        return;
    }

    const end = new Date(prevDate.value + 'T00:00:00');
    if (isNaN(end.getTime())) {
        return;
    }

    const start = new Date(end);
    start.setDate(start.getDate() + 1);
    nextStart.value = formatDateInput(start);

    const finish = new Date(start);
    finish.setFullYear(finish.getFullYear() + 1);
    finish.setDate(finish.getDate() - 1);
    nextEnd.value = formatDateInput(finish);

    const startYear = start.getFullYear();
    newYear.value = startYear + '-' + (startYear + 1);
}

const prevDate = document.getElementById('previous_session_end_date');
if (prevDate) {
    prevDate.addEventListener('change', syncNextSessionDates);
}
";

include '../../includes/footer.php';
?>
