<?php
/**
 * SMS Templates
 * Ready-made templates for quick reuse in SMS composer
 */

require_once '../../config/config.php';

requireLogin();
requirePermission('sms', 'send');

$pageTitle = 'SMS Templates';
$currentUser = getCurrentUser();

$schoolSettings = getSchoolSettings();
$schoolName = $schoolSettings['school_name'] ?? APP_NAME;

$templates = [
    [
        'title' => 'Fee Reminder',
        'description' => 'Used for pending or due fee reminders.',
        'template' => "Dear Parent, Your ward [STUDENT_NAME] has pending fee payment of Rs. [AMOUNT]. Please pay at the earliest. Thank you! - {$schoolName}",
    ],
    [
        'title' => 'Fee Receipt',
        'description' => 'Send after fee collection.',
        'template' => "Dear Parent, Fee payment of Rs. [AMOUNT] has been received for [STUDENT_NAME]. Receipt No: [RECEIPT_NO]. Thank you! - {$schoolName}",
    ],
    [
        'title' => 'Exam Notification',
        'description' => 'Exam date and schedule announcement.',
        'template' => "Dear Parent, Exam Schedule for [EXAM_NAME] will start from [START_DATE]. Please prepare [STUDENT_NAME] accordingly. - {$schoolName}",
    ],
    [
        'title' => 'Holiday Notice',
        'description' => 'School holiday or closure announcement.',
        'template' => "Dear Parent, School will remain closed on [DATE] due to [REASON]. Kindly note. - {$schoolName}",
    ],
    [
        'title' => 'Parent Meeting',
        'description' => 'Parent-teacher meeting reminder.',
        'template' => "Dear Parent, PTM is scheduled on [DATE] at [TIME]. Your presence is requested. - {$schoolName}",
    ],
    [
        'title' => 'Admission Update',
        'description' => 'Admission approved or student onboarding message.',
        'template' => "Dear Parent, Your ward [STUDENT_NAME] has been admitted successfully. Admission No: [ADMISSION_NO]. Welcome to {$schoolName}.",
    ],
];

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-text"></i> SMS Templates</h2>
            <div class="d-flex gap-2 flex-wrap">
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-send"></i> Open SMS Composer
                </a>
                <a href="logs.php" class="btn btn-secondary">
                    <i class="bi bi-clock-history"></i> SMS Logs
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i>
                    Click a template to copy it, or open it in the SMS composer and edit the placeholders before sending.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($templates as $index => $template): ?>
        <?php $encodedMessage = rawurlencode($template['template']); ?>
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <strong><?php echo htmlspecialchars($template['title']); ?></strong>
                    <span class="badge bg-light text-dark"><?php echo str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($template['description']); ?></p>
                    <textarea class="form-control mb-3" rows="4" readonly id="templateText_<?php echo $index; ?>"><?php echo htmlspecialchars($template['template']); ?></textarea>
                    <div class="d-flex gap-2 flex-wrap mt-auto">
                        <button type="button" class="btn btn-outline-primary btn-sm copy-template-btn" data-target="#templateText_<?php echo $index; ?>">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                        <a class="btn btn-primary btn-sm" href="index.php?recipient_type=individual&message=<?php echo $encodedMessage; ?>">
                            <i class="bi bi-send"></i> Use in SMS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.copy-template-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const target = document.querySelector(this.getAttribute('data-target'));
        if (!target) return;
        target.select();
        target.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(target.value || '').then(function() {
            button.innerHTML = '<i class="bi bi-check2"></i> Copied';
            setTimeout(function() {
                button.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
            }, 1200);
        }).catch(function() {
            alert('Copy failed. Please select the text manually.');
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
