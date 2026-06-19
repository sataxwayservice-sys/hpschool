<?php
/**
 * SMS Module
 * Send SMS to students/parents
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('sms', 'send');

$pageTitle = 'SMS Management';
$currentUser = getCurrentUser();

// Prefill from query string for compose links
$prefillPhone = trim((string) ($_GET['phone'] ?? ''));
$prefillMessage = trim((string) ($_GET['message'] ?? ''));
$prefillRecipientType = trim((string) ($_GET['recipient_type'] ?? ''));
if ($prefillRecipientType === '') {
    $prefillRecipientType = ($prefillPhone !== '' || $prefillMessage !== '') ? 'individual' : '';
}
$prefillClassId = intval($_GET['class_id'] ?? 0);
$prefillSectionId = intval($_GET['section_id'] ?? 0);
$prefillNumbers = trim((string) ($_GET['individual_numbers'] ?? ''));
if ($prefillNumbers === '' && $prefillPhone !== '') {
    $prefillNumbers = $prefillPhone;
}
$smsGatewayConfigured = FIREBASE_SMS_ENABLED
    && stripos((string) FIREBASE_SMS_FUNCTION_URL, 'your-region-your-project') === false
    && stripos((string) FIREBASE_SMS_FUNCTION_URL, 'YOUR_') === false;

// Handle SMS sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $recipient_type = sanitize($_POST['recipient_type']);
    $message = sanitize($_POST['message']);
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    $individual_numbers = isset($_POST['individual_numbers']) ? sanitize($_POST['individual_numbers']) : '';

    $phoneNumbers = [];

    // Get phone numbers based on recipient type
    if ($recipient_type == 'all_students') {
        $query = "SELECT DISTINCT contact_no FROM students WHERE status = 'Active' AND contact_no != ''";
        $result = fetchAll($query);
        foreach ($result as $row) {
            $phoneNumbers[] = $row['contact_no'];
        }
    } elseif ($recipient_type == 'class_wise') {
        $query = "SELECT DISTINCT contact_no FROM students WHERE status = 'Active' AND contact_no != ''";
        $params = [];
        $types = '';

        if ($class_id > 0) {
            $query .= " AND class_id = ?";
            $types .= 'i';
            $params[] = $class_id;
        }

        if ($section_id > 0) {
            $query .= " AND section_id = ?";
            $types .= 'i';
            $params[] = $section_id;
        }

        $result = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
        foreach ($result as $row) {
            $phoneNumbers[] = $row['contact_no'];
        }
    } elseif ($recipient_type == 'individual') {
        $numbers = explode(',', $individual_numbers);
        foreach ($numbers as $num) {
            $num = trim($num);
            if (!empty($num)) {
                $phoneNumbers[] = $num;
            }
        }
    }

    // Send SMS via Firebase
    $successCount = 0;
    $failureCount = 0;
    $errors = [];

    foreach ($phoneNumbers as $phone) {
        $result = sendSMSViaFirebase($phone, $message);
        $smsSuccess = is_array($result) && !empty($result['success']);
        if ($smsSuccess) {
            $successCount++;

            // Log SMS
            $logQuery = "INSERT INTO sms_logs (phone_number, message, status, sent_by, sent_at)
                        VALUES (?, ?, 'Sent', ?, NOW())";
            executeQuery($logQuery, 'ssi', [$phone, $message, $currentUser['user_id']]);
        } else {
            $failureCount++;
            $failureMessage = 'Unknown SMS error';
            if (is_array($result) && !empty($result['message'])) {
                $failureMessage = $result['message'];
            }
            $errors[] = "Failed to send to $phone: " . $failureMessage;

            // Log failed SMS
            $logQuery = "INSERT INTO sms_logs (phone_number, message, status, error_message, sent_by, sent_at)
                        VALUES (?, ?, 'Failed', ?, ?, NOW())";
            executeQuery($logQuery, 'sssi', [$phone, $message, $failureMessage, $currentUser['user_id']]);
        }
    }

    // Log activity
    logActivity($currentUser['user_id'], 'SMS Sent', 'sms', "Sent SMS to $successCount recipients. $failureCount failed.");

    $_SESSION['success_message'] = "SMS sent successfully to $successCount recipient(s). $failureCount failed.";
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }

    header("Location: index.php");
    exit();
}

// Get classes for dropdown
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

// Get recent SMS logs
$recentSMS = fetchAll("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 20");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-chat-dots-fill"></i> SMS Management
            </h2>
            <div>
                <a href="templates.php" class="btn btn-info">
                    <i class="bi bi-file-text"></i> SMS Templates
                </a>
                <a href="logs.php" class="btn btn-secondary">
                    <i class="bi bi-clock-history"></i> All Logs
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php
        echo $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$smsGatewayConfigured): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>SMS gateway not configured.</strong>
        Update <code>config/firebase_config.php</code> with your real Firebase Cloud Function URL before sending messages.
    </div>
<?php endif; ?>

<div class="row">
    <!-- Send SMS Form -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-send"></i> Send SMS</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="smsForm">
                    <div class="mb-3">
                        <label class="form-label">Recipient Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="recipient_type" id="recipientType" required>
                            <option value="">-- Select Recipient Type --</option>
                            <option value="all_students" <?php echo $prefillRecipientType === 'all_students' ? 'selected' : ''; ?>>All Students</option>
                            <option value="class_wise" <?php echo $prefillRecipientType === 'class_wise' ? 'selected' : ''; ?>>Class/Section Wise</option>
                            <option value="individual" <?php echo $prefillRecipientType === 'individual' ? 'selected' : ''; ?>>Individual Numbers</option>
                        </select>
                    </div>

                    <div id="classWiseDiv" style="display: <?php echo $prefillRecipientType === 'class_wise' ? 'block' : 'none'; ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class</label>
                                <select class="form-select" name="class_id">
                                    <option value="">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" <?php echo $prefillClassId === intval($class['class_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Section</label>
                                <select class="form-select" name="section_id">
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section['section_id']; ?>" <?php echo $prefillSectionId === intval($section['section_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($section['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="individualDiv" style="display: <?php echo $prefillRecipientType === 'individual' ? 'block' : 'none'; ?>;">
                        <div class="mb-3">
                            <label class="form-label">Phone Numbers (comma separated)</label>
                            <textarea class="form-control" name="individual_numbers" rows="3"
                                      placeholder="9876543210, 9876543211, 9876543212"><?php echo htmlspecialchars($prefillNumbers); ?></textarea>
                            <small class="text-muted">Enter phone numbers separated by commas</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="message" id="smsMessage" rows="5"
                                  required maxlength="160" placeholder="Type your message here..."><?php echo htmlspecialchars($prefillMessage); ?></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Characters: <span id="charCount">0</span>/160</small>
                            <small class="text-muted">SMS Count: <span id="smsCount">0</span></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quick Templates</label>
                        <div class="btn-group-vertical w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm template-btn"
                                    data-template="Dear Parent, Your ward's fee is due. Please pay at the earliest. Thank you. - [SCHOOL_NAME]">
                                Fee Reminder
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm template-btn"
                                    data-template="Dear Parent, School will remain closed on [DATE] due to [REASON]. - [SCHOOL_NAME]">
                                Holiday Notification
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm template-btn"
                                    data-template="Dear Parent, Parent-Teacher meeting scheduled on [DATE] at [TIME]. Your presence is mandatory. - [SCHOOL_NAME]">
                                PTM Notice
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm template-btn"
                                    data-template="Dear Parent, Exam Schedule: [EXAM_NAME] from [START_DATE] to [END_DATE]. - [SCHOOL_NAME]">
                                Exam Notification
                            </button>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="send_sms" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Send SMS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SMS Statistics -->
    <div class="col-md-4">
        <div class="card dashboard-card mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Today's Stats</h5>
            </div>
            <div class="card-body">
                <?php
                $todayStats = fetchOne("SELECT
                                        COUNT(*) as total_sent,
                                        SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) as success_count,
                                        SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed_count
                                        FROM sms_logs
                                        WHERE DATE(sent_at) = CURDATE()");
                ?>
                <div class="text-center">
                    <h3 class="text-primary"><?php echo $todayStats['total_sent'] ?? 0; ?></h3>
                    <p class="text-muted mb-2">Total SMS Sent</p>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h5 class="text-success"><?php echo $todayStats['success_count'] ?? 0; ?></h5>
                            <small>Delivered</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-danger"><?php echo $todayStats['failed_count'] ?? 0; ?></h5>
                            <small>Failed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> SMS Info</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 160 chars = 1 SMS</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Uses Firebase SMS Gateway</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Instant delivery</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Delivery reports logged</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Recent SMS Logs -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent SMS Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Phone Number</th>
                                <th>Message</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentSMS) > 0): ?>
                                <?php foreach ($recentSMS as $sms): ?>
                                <tr>
                                    <td><?php echo date('d-M-Y h:i A', strtotime($sms['sent_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($sms['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($sms['message'], 0, 50)) . (strlen($sms['message']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $sms['status'] == 'Sent' ? 'success' : 'danger'; ?>">
                                            <?php echo htmlspecialchars($sms['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No SMS logs found</td>
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
// Recipient type change handler
$('#recipientType').on('change', function() {
    const type = $(this).val();
    $('#classWiseDiv').hide();
    $('#individualDiv').hide();

    if (type === 'class_wise') {
        $('#classWiseDiv').show();
    } else if (type === 'individual') {
        $('#individualDiv').show();
    }
});

// Character counter
$('#smsMessage').on('input', function() {
    const length = $(this).val().length;
    const smsCount = Math.ceil(length / 160) || 0;
    $('#charCount').text(length);
    $('#smsCount').text(smsCount);
});

// Template buttons
$('.template-btn').on('click', function() {
    const template = $(this).data('template');
    $('#smsMessage').val(template);
    $('#smsMessage').trigger('input');
});

// Form validation
$('#smsForm').on('submit', function(e) {
    const message = $('#smsMessage').val().trim();
    if (message.length === 0) {
        e.preventDefault();
        alert('Please enter a message');
        return false;
    }

    return confirm('Are you sure you want to send this SMS?');
});

$('#recipientType').trigger('change');
$('#smsMessage').trigger('input');
";

// Include footer
include '../../includes/footer.php';
?>
