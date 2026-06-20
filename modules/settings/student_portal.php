<?php
/**
 * Student Portal Management
 * Super Admin and Admin manage student portal accounts and approvals
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();

requireLogin();
$currentUser = getCurrentUser();
requireRole(['super_admin', 'admin']);

$pageTitle = 'Student Portal Management';
$currentSchoolId = getCurrentSchoolId();
$schoolSettings = getSchoolSettings();
$paymentSettings = studentPortalGetPaymentSettings();
$announcementRecords = parentPortalGetAnnouncementList(100);
$editingAnnouncementId = intval($_GET['announcement_id'] ?? 0);
$editingAnnouncement = $editingAnnouncementId > 0 ? parentPortalGetAnnouncementById($editingAnnouncementId) : null;

function studentPortalGenerateAndStorePassword(array $user) {
    $plainPassword = generatePassword(8);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $encryptedPassword = encryptAppValue($plainPassword);

    $result = executeQuery(
        "UPDATE users SET password = ?, password_encrypted = ?, updated_at = NOW() WHERE user_id = ?",
        'ssi',
        [$hashedPassword, $encryptedPassword, intval($user['user_id'])]
    );

    return [$plainPassword, $result !== false];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_action'])) {
    $announcementAction = sanitize($_POST['announcement_action'] ?? 'save');
    $announcementId = intval($_POST['announcement_id'] ?? 0);
    $title = trim((string)($_POST['announcement_title'] ?? ''));
    $messageBody = trim((string)($_POST['announcement_message'] ?? ''));
    $attachmentUrl = trim((string)($_POST['attachment_url'] ?? ''));
    $publishDate = trim((string)($_POST['publish_date'] ?? ''));
    $expireDate = trim((string)($_POST['expire_date'] ?? ''));
    $isActive = isset($_POST['announcement_is_active']) ? 1 : 0;
    $errors = [];

    if ($announcementAction === 'toggle') {
        if ($announcementId <= 0) {
            $errors[] = 'Please select an announcement to update.';
        } else {
            $announcement = parentPortalGetAnnouncementById($announcementId);
            if (!$announcement) {
                $errors[] = 'Announcement not found.';
            }
        }

        if (empty($errors)) {
            $nextStatus = intval($announcement['is_active'] ?? 0) === 1 ? 0 : 1;
            $result = executeQuery(
                "UPDATE parent_announcements SET is_active = ?, school_id = COALESCE(school_id, NULLIF(?, 0)), updated_at = NOW() WHERE announcement_id = ?",
                'iii',
                [$nextStatus, $currentSchoolId, $announcementId]
            );

            if ($result === false) {
                $error = 'Unable to update the announcement status.';
            } else {
                logActivity(
                    $currentUser['user_id'],
                    'Update Announcement',
                    'student_portal',
                    'Toggled announcement ID: ' . $announcementId . ' to status: ' . ($nextStatus ? 'Active' : 'Inactive')
                );
                $_SESSION['success_message'] = 'Announcement status updated successfully.';
                header('Location: student_portal.php#announcements');
                exit();
            }
        }

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        }
    } elseif ($announcementAction === 'delete') {
        if ($announcementId <= 0) {
            $errors[] = 'Please select an announcement to delete.';
        }

        if (empty($errors)) {
            $announcement = parentPortalGetAnnouncementById($announcementId);
            if (!$announcement) {
                $errors[] = 'Announcement not found.';
            }
        }

        if (empty($errors)) {
            $result = executeQuery(
                "DELETE FROM parent_announcements WHERE announcement_id = ?",
                'i',
                [$announcementId]
            );

            if ($result === false) {
                $error = 'Unable to delete the announcement.';
            } else {
                logActivity(
                    $currentUser['user_id'],
                    'Delete Announcement',
                    'student_portal',
                    'Deleted announcement ID: ' . $announcementId
                );
                $_SESSION['success_message'] = 'Announcement deleted successfully.';
                header('Location: student_portal.php#announcements');
                exit();
            }
        }

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        }
    } else {
        if ($title === '') {
            $errors[] = 'Announcement title is required.';
        }
        if ($messageBody === '') {
            $errors[] = 'Announcement message is required.';
        }
        if ($publishDate !== '' && strtotime($publishDate) === false) {
            $errors[] = 'Please choose a valid publish date.';
        }
        if ($expireDate !== '' && strtotime($expireDate) === false) {
            $errors[] = 'Please choose a valid expire date.';
        }
        if ($publishDate !== '' && $expireDate !== '' && strtotime($publishDate) > strtotime($expireDate)) {
            $errors[] = 'Publish date cannot be later than the expire date.';
        }

        if ($attachmentUrl !== '') {
            if (preg_match('#^https?://#i', $attachmentUrl)) {
                // Keep as-is.
            } elseif (str_starts_with($attachmentUrl, '/')) {
                $attachmentUrl = APP_URL . $attachmentUrl;
            } else {
                $errors[] = 'Attachment URL must begin with http://, https://, or /';
            }
        }

        if (empty($errors)) {
            if ($announcementId > 0) {
                $result = executeQuery(
                    "UPDATE parent_announcements SET
                        title = ?,
                        message = ?,
                        attachment_url = NULLIF(?, ''),
                        is_active = ?,
                        publish_date = NULLIF(?, ''),
                        expire_date = NULLIF(?, ''),
                        school_id = COALESCE(school_id, NULLIF(?, 0)),
                        updated_at = NOW()
                     WHERE announcement_id = ?",
                    'sssissii',
                    [
                        $title,
                        $messageBody,
                        $attachmentUrl,
                        $isActive,
                        $publishDate,
                        $expireDate,
                        $currentSchoolId,
                        $announcementId,
                    ]
                );

                $logAction = 'Updated announcement ID: ' . $announcementId;
            } else {
                $result = executeQuery(
                    "INSERT INTO parent_announcements (
                        school_id, title, message, attachment_url, is_active, publish_date, expire_date, created_by
                    ) VALUES (NULLIF(?, 0), ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), ?)",
                    'isssissi',
                    [
                        $currentSchoolId,
                        $title,
                        $messageBody,
                        $attachmentUrl,
                        $isActive,
                        $publishDate,
                        $expireDate,
                        intval($currentUser['user_id']),
                    ]
                );

                $logAction = 'Created announcement: ' . $title;
            }

            if ($result === false) {
                $error = 'Unable to save the announcement.';
            } else {
                logActivity($currentUser['user_id'], 'Announcement Saved', 'student_portal', $logAction);
                $_SESSION['success_message'] = $announcementId > 0 ? 'Announcement updated successfully.' : 'Announcement created successfully.';
                header('Location: student_portal.php#announcements');
                exit();
            }
        }

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        } else {
            $editingAnnouncement = [
                'announcement_id' => $announcementId,
                'title' => $title,
                'message' => $messageBody,
                'attachment_url' => $attachmentUrl,
                'is_active' => $isActive,
                'publish_date' => $publishDate,
                'expire_date' => $expireDate,
            ];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_action'])) {
    $applicationAction = sanitize($_POST['application_action'] ?? '');
    $applicationId = intval($_POST['application_id'] ?? 0);

    if ($applicationAction === 'delete') {
        $deleteResult = studentPortalDeleteApplication($applicationId, intval($currentUser['user_id']));
        if ($deleteResult['success']) {
            $_SESSION['success_message'] = $deleteResult['message'];
            header('Location: student_portal.php#applications');
            exit();
        }

        $error = $deleteResult['message'] ?? 'Unable to delete the application.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_action'])) {
    $paymentAction = sanitize($_POST['payment_action'] ?? '');
    if ($paymentAction === 'save') {
        $upiId = trim((string)($_POST['upi_id'] ?? ''));
        $paymentRecipientName = trim((string)($_POST['payment_recipient_name'] ?? ''));
        $paymentNote = trim((string)($_POST['payment_note'] ?? ''));
        $errors = [];

        if ($paymentRecipientName === '') {
            $paymentRecipientName = trim((string)($schoolSettings['school_name'] ?? APP_NAME));
        }
        if ($paymentNote === '') {
            $paymentNote = 'School fee payment';
        }

        if ($upiId !== '' && !preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9._-]+$/', $upiId)) {
            $errors[] = 'Please enter a valid UPI ID or leave it blank to disable payment links.';
        }

        if (empty($errors)) {
            $result = executeQuery(
                "UPDATE school_settings SET upi_id = ?, payment_recipient_name = ?, payment_note = ?, updated_at = NOW() WHERE setting_id = ?",
                'sssi',
                [
                    $upiId,
                    $paymentRecipientName,
                    $paymentNote,
                    intval($schoolSettings['setting_id']),
                ]
            );

            if ($result === false) {
                $error = 'Unable to save payment settings.';
            } else {
                logActivity($currentUser['user_id'], 'Update Payment Settings', 'student_portal', 'Updated student portal payment settings');
                $_SESSION['success_message'] = 'Payment settings updated successfully.';
                header('Location: student_portal.php#payment-settings');
                exit();
            }
        }

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $userId = intval($_POST['user_id'] ?? 0);

    if ($userId > 0 && $action === 'delete') {
        $studentUser = fetchOne(
            "SELECT u.*, s.student_name, s.admission_no, s.contact_no AS student_contact, s.email AS student_email,
                    c.class_name, sec.section_name
             FROM users u
             LEFT JOIN students s ON u.student_id = s.student_id
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE u.user_id = ? AND u.role = 'student'
               AND (? = 0 OR COALESCE(u.school_id, s.school_id, 0) = ?)",
            'iii',
            [$userId, $currentSchoolId, $currentSchoolId]
        );

        if (!$studentUser) {
            $_SESSION['error_message'] = 'Student account not found.';
            header('Location: student_portal.php');
            exit();
        }

        if (intval($studentUser['is_active'] ?? 1) === 1) {
            $_SESSION['error_message'] = 'Only pending student accounts can be deleted from Student Portal.';
            header('Location: student_portal.php');
            exit();
        }

        if (softDeleteUser($userId, 'Removed pending student from Student Portal')) {
            logActivity($currentUser['user_id'], 'Student Portal Deleted', 'student_portal', 'Deleted pending student account for user ID: ' . $userId);
            $_SESSION['success_message'] = 'Pending student deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to delete pending student.';
        }

        header('Location: student_portal.php');
        exit();
    }

    if ($userId > 0 && in_array($action, ['approve', 'resend'], true)) {
        $studentUser = fetchOne(
            "SELECT u.*, s.student_name, s.admission_no, s.contact_no AS student_contact, s.email AS student_email,
                    c.class_name, sec.section_name
             FROM users u
             LEFT JOIN students s ON u.student_id = s.student_id
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE u.user_id = ? AND u.role = 'student'
               AND (? = 0 OR COALESCE(u.school_id, s.school_id, 0) = ?)",
            'iii',
            [$userId, $currentSchoolId, $currentSchoolId]
        );

        if (!$studentUser) {
            $_SESSION['error_message'] = 'Student account not found.';
            header('Location: student_portal.php');
            exit();
        }

        if (intval($studentUser['student_id'] ?? 0) <= 0 && $action === 'approve') {
            $_SESSION['error_message'] = 'This student login still needs application review before it can be approved.';
            header('Location: ' . APP_URL . '/modules/settings/student_application_edit.php?user_id=' . $userId);
            exit();
        }

        $plainPassword = decryptAppValue($studentUser['password_encrypted'] ?? '');

        if ($action === 'resend' || $plainPassword === '') {
            [$plainPassword, $passwordSaved] = studentPortalGenerateAndStorePassword($studentUser);
            if (!$passwordSaved) {
                $_SESSION['error_message'] = 'Could not save the student password. Please try again.';
                header('Location: student_portal.php');
                exit();
            }
        }

        if ($plainPassword === '') {
            $_SESSION['error_message'] = 'Could not prepare a password for this student account.';
            header('Location: student_portal.php');
            exit();
        }

        if ($action === 'approve') {
            executeQuery("UPDATE users SET is_active = 1, updated_at = NOW() WHERE user_id = ?", 'i', [$userId]);
        }

        $notification = studentPortalSendCredentialsNotification($studentUser, $plainPassword);

        if ($notification['sent']) {
            executeQuery("UPDATE users SET password_encrypted = NULL, is_active = 1, updated_at = NOW() WHERE user_id = ?", 'i', [$userId]);
        } elseif ($action === 'approve') {
            executeQuery("UPDATE users SET is_active = 1, updated_at = NOW() WHERE user_id = ?", 'i', [$userId]);
        }

        $statusLabel = $action === 'approve' ? 'approved' : 'resent';
        $messageParts = [
            'Student account ' . $statusLabel . ' for ' . ($studentUser['student_name'] ?? 'student') . '.',
        ];
        if ($notification['sms']) {
            $messageParts[] = 'SMS sent.';
        }
        if ($notification['email']) {
            $messageParts[] = 'Email sent.';
        }
        if (!$notification['sent']) {
            $messageParts[] = 'Notification could not be delivered, but the account was updated.';
            $messageParts[] = 'Temporary password: ' . $plainPassword;
        }

        logActivity($currentUser['user_id'], 'Student Portal Updated', 'student_portal', 'Updated student portal account for user ID: ' . $userId);
        $_SESSION['success_message'] = implode(' ', $messageParts);
        header('Location: student_portal.php');
        exit();
    }
}

$studentQuery = "SELECT u.user_id, u.username, u.email, u.mobile, u.is_active, u.created_at, u.last_login, u.password_encrypted,
            s.student_name, s.admission_no, c.class_name, sec.section_name
     FROM users u
     LEFT JOIN students s ON u.student_id = s.student_id
     LEFT JOIN classes c ON s.class_id = c.class_id
     LEFT JOIN sections sec ON s.section_id = sec.section_id
     WHERE u.role = 'student'";
$studentParams = [];
$studentTypes = '';
if ($currentSchoolId > 0) {
    $studentQuery .= " AND COALESCE(u.school_id, s.school_id, 0) = ?";
    $studentParams[] = $currentSchoolId;
    $studentTypes .= 'i';
}
$studentQuery .= " ORDER BY u.is_active ASC, u.created_at DESC";
$students = empty($studentTypes) ? fetchAll($studentQuery) : fetchAll($studentQuery, $studentTypes, $studentParams);
$applications = studentPortalGetApplications();

$pendingCount = count(array_filter($students, fn($row) => intval($row['is_active']) === 0));
$activeCount = count(array_filter($students, fn($row) => intval($row['is_active']) === 1));
$pendingApplications = count(array_filter($applications, fn($row) => ($row['status'] ?? '') === 'Pending'));
$approvedApplications = count(array_filter($applications, fn($row) => ($row['status'] ?? '') === 'Approved'));
$rejectedApplications = count(array_filter($applications, fn($row) => ($row['status'] ?? '') === 'Rejected'));
$activeAnnouncements = count(array_filter($announcementRecords, fn($row) => intval($row['is_active'] ?? 0) === 1));
$scheduledAnnouncements = count(array_filter($announcementRecords, fn($row) => !empty($row['publish_date']) && strtotime((string)$row['publish_date']) > strtotime(date('Y-m-d'))));
$announcementForm = $editingAnnouncement ?: [
    'announcement_id' => 0,
    'title' => '',
    'message' => '',
    'attachment_url' => '',
    'publish_date' => '',
    'expire_date' => '',
    'is_active' => 1,
];
$paymentForm = [
    'upi_id' => trim((string)($_POST['upi_id'] ?? ($paymentSettings['upi_id'] ?? ''))),
    'payment_recipient_name' => trim((string)($_POST['payment_recipient_name'] ?? ($paymentSettings['payment_recipient_name'] ?? ($schoolSettings['school_name'] ?? APP_NAME)))),
    'payment_note' => trim((string)($_POST['payment_note'] ?? ($paymentSettings['payment_note'] ?? 'School fee payment'))),
];

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="mb-0">
                <i class="bi bi-shield-lock"></i> Student Portal Management
            </h2>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?php echo APP_URL; ?>/modules/settings/users.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Users
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

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Pending Approvals</h5>
                <h3><?php echo $pendingCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Active Student Accounts</h5>
                <h3><?php echo $activeCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h5>Total Student Logins</h5>
                <h3><?php echo count($students); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Pending Applications</h5>
                <h3><?php echo $pendingApplications; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Approved Applications</h5>
                <h3><?php echo $approvedApplications; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-danger">
            <div class="card-body text-center">
                <h5>Rejected Applications</h5>
                <h3><?php echo $rejectedApplications; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h5>Total Applications</h5>
                <h3><?php echo count($applications); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Active Announcements</h5>
                <h3><?php echo $activeAnnouncements; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Scheduled Announcements</h5>
                <h3><?php echo $scheduledAnnouncements; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-dark">
            <div class="card-body text-center">
                <h5>Total Announcements</h5>
                <h3><?php echo count($announcementRecords); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-5" id="payment-settings">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-phone-vibrate"></i> Payment Settings</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    These settings build the Google Pay, PhonePe, and other UPI payment links that students see when fees are pending.
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="payment_action" value="save">
                    <div class="mb-3">
                        <label class="form-label">UPI ID</label>
                        <input type="text" class="form-control" name="upi_id"
                               value="<?php echo parentPortalEscape($paymentForm['upi_id']); ?>"
                               placeholder="schoolname@bank">
                        <small class="text-muted">Leave blank to hide the payment buttons.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipient Name</label>
                        <input type="text" class="form-control" name="payment_recipient_name"
                               value="<?php echo parentPortalEscape($paymentForm['payment_recipient_name']); ?>"
                               placeholder="HOWLY PUBLIC SCHOOL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Note</label>
                        <input type="text" class="form-control" name="payment_note"
                               value="<?php echo parentPortalEscape($paymentForm['payment_note']); ?>"
                               placeholder="School fee payment">
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Payment Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7" id="announcements">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-megaphone"></i> Announcement Control</h5>
                <span class="badge bg-light text-dark"><?php echo intval($editingAnnouncement['announcement_id'] ?? 0) > 0 ? 'Editing #' . intval($editingAnnouncement['announcement_id']) : 'Create New'; ?></span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="announcement_action" value="save">
                    <input type="hidden" name="announcement_id" value="<?php echo intval($announcementForm['announcement_id'] ?? 0); ?>">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Announcement Title</label>
                            <input type="text" class="form-control" name="announcement_title"
                                   value="<?php echo parentPortalEscape($announcementForm['title'] ?? ''); ?>"
                                   placeholder="Exam schedule update">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="announcement_message" rows="5"
                                      placeholder="Write the announcement that students should see."><?php echo parentPortalEscape($announcementForm['message'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Attachment URL</label>
                            <input type="text" class="form-control" name="attachment_url"
                                   value="<?php echo parentPortalEscape($announcementForm['attachment_url'] ?? ''); ?>"
                                   placeholder="https://example.com/notice.pdf">
                            <small class="text-muted">Optional. You can paste a PDF, image, or web link.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Publish Date</label>
                            <input type="date" class="form-control" name="publish_date"
                                   value="<?php echo parentPortalEscape($announcementForm['publish_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expire Date</label>
                            <input type="date" class="form-control" name="expire_date"
                                   value="<?php echo parentPortalEscape($announcementForm['expire_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="announcement_is_active" name="announcement_is_active" <?php echo intval($announcementForm['is_active'] ?? 1) === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="announcement_is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <?php echo intval($announcementForm['announcement_id'] ?? 0) > 0 ? 'Update Announcement' : 'Create Announcement'; ?>
                        </button>
                        <?php if (intval($announcementForm['announcement_id'] ?? 0) > 0): ?>
                            <a href="<?php echo APP_URL; ?>/modules/settings/student_portal.php#announcements" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel Edit
                            </a>
                        <?php else: ?>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-list"></i> Announcement List</h5>
                <span class="badge bg-light text-dark"><?php echo count($announcementRecords); ?> total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="announcementTable" class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Dates</th>
                                <th>Created By</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($announcementRecords as $announcement): ?>
                                <?php
                                    $messagePreview = trim((string)($announcement['message'] ?? ''));
                                    $messagePreview = preg_replace('/\s+/', ' ', $messagePreview);
                                    if (strlen($messagePreview) > 110) {
                                        $messagePreview = substr($messagePreview, 0, 107) . '...';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo intval($announcement['announcement_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($announcement['title'] ?? '-'); ?></strong>
                                        <div class="text-muted small"><?php echo htmlspecialchars($messagePreview ?: '-'); ?></div>
                                        <?php if (!empty($announcement['attachment_url'])): ?>
                                            <div class="small mt-1">
                                                <a href="<?php echo htmlspecialchars($announcement['attachment_url']); ?>" target="_blank" rel="noopener">
                                                    <i class="bi bi-paperclip"></i> Attachment
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (intval($announcement['is_active'] ?? 0) === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong>Publish:</strong> <?php echo !empty($announcement['publish_date']) ? htmlspecialchars(date('d-M-Y', strtotime($announcement['publish_date']))) : '-'; ?>
                                        </div>
                                        <div class="small">
                                            <strong>Expire:</strong> <?php echo !empty($announcement['expire_date']) ? htmlspecialchars(date('d-M-Y', strtotime($announcement['expire_date']))) : '-'; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($announcement['created_by_name'] ?? '-'); ?></td>
                                    <td><?php echo !empty($announcement['created_at']) ? htmlspecialchars(date('d-M-Y h:i A', strtotime($announcement['created_at']))) : '-'; ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="<?php echo APP_URL; ?>/modules/settings/student_portal.php?announcement_id=<?php echo intval($announcement['announcement_id']); ?>#announcements"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="announcement_action" value="toggle">
                                                <input type="hidden" name="announcement_id" value="<?php echo intval($announcement['announcement_id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Change this announcement status?')">
                                                    <i class="bi bi-toggle-on"></i> <?php echo intval($announcement['is_active'] ?? 0) === 1 ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="announcement_action" value="delete">
                                                <input type="hidden" name="announcement_id" value="<?php echo intval($announcement['announcement_id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this announcement permanently?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
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

<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Student Applications</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Review flow:</strong> Open an application, correct the information if needed, and then approve or reject it.
                    Approved applications will create or activate the student login and link it to the student record.
                </div>
                <div class="table-responsive">
                    <table id="studentApplicationsTable" class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Login</th>
                                <th>Class</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Review By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $application): ?>
                                <tr>
                                    <td><?php echo intval($application['application_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?php echo htmlspecialchars(studentPortalGetApplicationPhotoUrl($application['profile_photo'] ?? '')); ?>"
                                                 alt="Photo" class="rounded border" style="width: 42px; height: 52px; object-fit: cover;">
                                            <div>
                                                <strong><?php echo htmlspecialchars($application['student_name'] ?? '-'); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars($application['father_name'] ?? '-'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($application['username'] ?? $application['login_email'] ?? '-'); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($application['login_email'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($application['class_name'] ?? '-'); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($application['section_name'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($application['mobile'] ?? '-'); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($application['email'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if (($application['status'] ?? '') === 'Approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif (($application['status'] ?? '') === 'Rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                        <?php if (!empty($application['user_is_active'])): ?>
                                            <div class="text-success small mt-1">Login active</div>
                                        <?php else: ?>
                                            <div class="text-muted small mt-1">Login inactive</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($application['created_at']) ? date('d-M-Y h:i A', strtotime($application['created_at'])) : '-'; ?></td>
                                    <td>
                                        <?php echo !empty($application['reviewed_by_name']) ? htmlspecialchars($application['reviewed_by_name']) : '<span class="text-muted">Pending</span>'; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo APP_URL; ?>/modules/settings/student_application_edit.php?id=<?php echo intval($application['application_id']); ?>"
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil-square"></i> Review
                                            </a>
                                            <?php if (studentPortalCanDeleteApplication($application)): ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this <?php echo htmlspecialchars(strtolower((string)($application['status'] ?? 'pending'))); ?> application and its login account? This cannot be undone.');">
                                                    <input type="hidden" name="application_action" value="delete">
                                                    <input type="hidden" name="application_id" value="<?php echo intval($application['application_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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

<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Student Portal Accounts</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Note:</strong> Pending student applications appear above. Inactive student accounts with a linked student record can still be re-sent login details from this list.
                </div>
                <div class="table-responsive">
                    <table id="studentPortalTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Login</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo intval($student['user_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['student_name'] ?? $student['username']); ?></strong>
                                        <div class="text-muted small"><?php echo htmlspecialchars(trim(($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? ''))); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['admission_no'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['username'] ?? '-'); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($student['mobile'] ?? '-'); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($student['email'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if (intval($student['is_active']) === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        if (!empty($student['last_login'])) {
                                            echo date('d-M-Y h:i A', strtotime($student['last_login']));
                                        } else {
                                            echo '<span class="text-muted">Never</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if (intval($student['is_active']) === 0 && intval($student['student_id'] ?? 0) <= 0): ?>
                                                <a href="<?php echo APP_URL; ?>/modules/settings/student_application_edit.php?user_id=<?php echo intval($student['user_id']); ?>"
                                                   class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil-square"></i> Review
                                                </a>
                                            <?php elseif (intval($student['is_active']) === 0): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="user_id" value="<?php echo intval($student['user_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this student account and send login credentials?')">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="" class="d-inline ms-1" onsubmit="return confirm('Delete this pending student account? This cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo intval($student['user_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="resend">
                                                    <input type="hidden" name="user_id" value="<?php echo intval($student['user_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Generate a new password and resend credentials?')">
                                                        <i class="bi bi-arrow-repeat"></i> Resend Credentials
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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

<?php
$inlineScript = "
$('#studentApplicationsTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    language: {
        emptyTable: 'No student applications found yet.'
    },
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Student Applications'
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Student Applications'
        }
    ]
});

$('#studentPortalTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Student Portal Accounts'
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Student Portal Accounts'
        }
    ]
});

$('#announcementTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 10,
    lengthMenu: [[10, 25, 50], [10, 25, 50]],
    language: {
        emptyTable: 'No announcements created yet.'
    },
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Student Announcements'
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Student Announcements'
        }
    ]
});
";

include '../../includes/footer.php';
