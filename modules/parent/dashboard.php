<?php
/**
 * Parent Portal Dashboard
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';

requireParentPortalLogin();
parentPortalEnsureSchema();

$currentUser = getCurrentUser();
$students = parentPortalGetLinkedStudents($currentUser['user_id']);
$studentIds = array_map(fn($student) => intval($student['student_id']), $students);
$announcements = parentPortalGetAnnouncements(6);
$receipts = parentPortalGetReceipts($studentIds, 8);
$paymentSettings = parentPortalGetPaymentSettings();

$summaries = [];
$grandDue = 0.0;
$grandPaid = 0.0;

foreach ($students as $student) {
    $summary = parentPortalGetFeeSummary($student['student_id']);
    $summaries[$student['student_id']] = $summary;
    $grandDue += floatval($summary['due_total'] ?? 0);
    $grandPaid += floatval($summary['paid_total'] ?? 0);
}

$content = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Welcome, <?php echo parentPortalEscape($currentUser['full_name'] ?? 'Parent'); ?></h1>
    <div class="parent-hero-subtitle">
        Download marksheets, admit cards, fee receipts, and due fee details for your linked children.
    </div>
    <div class="parent-button-row no-print">
        <a href="<?php echo APP_URL; ?>/modules/parent/due_fees.php" class="parent-button parent-button-primary">
            <i class="bi bi-cash-stack"></i> Due Fees
        </a>
        <a href="<?php echo APP_URL; ?>/modules/parent/receipts.php" class="parent-button parent-button-success">
            <i class="bi bi-receipt"></i> Receipts
        </a>
        <a href="<?php echo APP_URL; ?>/modules/parent/marksheet.php" class="parent-button">
            <i class="bi bi-file-earmark-pdf"></i> Marksheet
        </a>
        <a href="<?php echo APP_URL; ?>/modules/parent/admit_card.php" class="parent-button">
            <i class="bi bi-card-heading"></i> Admit Card
        </a>
    </div>
</div>

<div class="parent-summary-grid">
    <div class="parent-summary-card">
        <span class="parent-summary-label">Linked Children</span>
        <span class="parent-summary-value"><?php echo count($students); ?></span>
        <span class="parent-muted">Accounts currently connected</span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Pending Fees</span>
        <span class="parent-summary-value"><?php echo formatCurrency($grandDue); ?></span>
        <span class="parent-muted">Month-wise due amount</span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Fees Paid</span>
        <span class="parent-summary-value"><?php echo formatCurrency($grandPaid); ?></span>
        <span class="parent-muted">Collected against fee structure</span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Announcements</span>
        <span class="parent-summary-value"><?php echo count($announcements); ?></span>
        <span class="parent-muted">Active school notices</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="parent-card h-100">
            <div class="parent-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">My Children</h5>
                    <div class="parent-muted">Use the quick links on each card to open the right download or fee page.</div>
                </div>
                <div class="parent-muted"><?php echo date('d M Y'); ?></div>
            </div>
            <div class="parent-card-body">
                <?php if (!empty($students)): ?>
                    <div class="parent-student-grid">
                        <?php foreach ($students as $student): ?>
                            <?php $summary = $summaries[$student['student_id']] ?? parentPortalGetFeeSummary($student['student_id']); ?>
                            <?php
                                $upiLink = parentPortalBuildUpiLink(
                                    $paymentSettings['upi_id'],
                                    $paymentSettings['payment_recipient_name'],
                                    $summary['due_total'] ?? 0,
                                    trim(($paymentSettings['payment_note'] ?? 'School fee payment') . ' - ' . ($student['student_name'] ?? 'Student'))
                                );
                            ?>
                            <div class="parent-student-card">
                                <div class="parent-student-top">
                                    <?php if (!empty($student['photo'])): ?>
                                        <img src="<?php echo parentPortalEscape(getStudentPhotoSrc($student['photo'])); ?>" class="parent-student-photo" alt="Photo">
                                    <?php else: ?>
                                        <div class="parent-student-initials"><?php echo parentPortalEscape(strtoupper(substr(trim((string)($student['student_name'] ?? 'ST')), 0, 2))); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <h4 class="parent-student-name"><?php echo parentPortalEscape($student['student_name'] ?? '-'); ?></h4>
                                        <div class="parent-muted"><?php echo parentPortalEscape(($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '')); ?></div>
                                        <div class="parent-chip-row">
                                            <span class="parent-chip"><i class="bi bi-upc"></i> <?php echo parentPortalEscape($student['admission_no'] ?? '-'); ?></span>
                                            <span class="parent-chip"><i class="bi bi-journal-text"></i> Roll <?php echo parentPortalEscape($student['roll_no'] ?? '-'); ?></span>
                                            <span class="parent-chip"><i class="bi bi-people"></i> <?php echo parentPortalEscape($student['relation'] ?? 'Parent'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="parent-card-body">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="parent-muted">Paid</div>
                                            <strong><?php echo formatCurrency($summary['paid_total'] ?? 0); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <div class="parent-muted">Due</div>
                                            <strong class="text-danger"><?php echo formatCurrency($summary['due_total'] ?? 0); ?></strong>
                                        </div>
                                    </div>

                                    <div class="parent-button-row">
                                        <a href="<?php echo APP_URL; ?>/modules/parent/marksheet.php?student_id=<?php echo intval($student['student_id']); ?>" class="parent-button parent-button-primary">
                                            <i class="bi bi-file-earmark-pdf"></i> Marksheet
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/admit_card.php?student_id=<?php echo intval($student['student_id']); ?>" class="parent-button">
                                            <i class="bi bi-card-heading"></i> Admit Card
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/due_fees.php?student_id=<?php echo intval($student['student_id']); ?>" class="parent-button parent-button-warning">
                                            <i class="bi bi-cash-stack"></i> Due Fees
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/receipts.php?student_id=<?php echo intval($student['student_id']); ?>" class="parent-button parent-button-success">
                                            <i class="bi bi-receipt"></i> Receipts
                                        </a>
                                    </div>

                                    <?php if (!empty($upiLink) && floatval($summary['due_total'] ?? 0) > 0): ?>
                                        <div class="mt-3 p-3 border rounded-3 bg-light">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <div>
                                                    <div class="fw-bold">Pay pending fee</div>
                                                    <div class="parent-muted">Open any UPI app and pay the due amount for this child.</div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-danger"><?php echo formatCurrency($summary['due_total'] ?? 0); ?></div>
                                                    <small class="parent-muted">Pending</small>
                                                </div>
                                            </div>
                                            <div class="parent-button-row mt-2">
                                                <a href="<?php echo parentPortalEscape($upiLink); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                                    <i class="bi bi-google"></i> Google Pay
                                                </a>
                                                <a href="<?php echo parentPortalEscape($upiLink); ?>" class="parent-button parent-button-success" target="_blank" rel="noopener">
                                                    <i class="bi bi-phone"></i> PhonePe
                                                </a>
                                                <a href="<?php echo parentPortalEscape($upiLink); ?>" class="parent-button" target="_blank" rel="noopener">
                                                    <i class="bi bi-qr-code"></i> UPI App
                                                </a>
                                                <img src="<?php echo parentPortalEscape(buildQrCodeUrl($upiLink, 120)); ?>" alt="UPI QR" style="width: 72px; height: 72px; margin-left: auto;">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="parent-empty">
                        <i class="bi bi-info-circle"></i> No children are linked to this parent account yet. Please contact the school office.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="parent-card mb-3">
            <div class="parent-card-head">
                <h5 class="mb-0">Announcements</h5>
            </div>
            <div class="parent-card-body">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="parent-announcement">
                            <h6 class="parent-announcement-title"><?php echo parentPortalEscape($announcement['title']); ?></h6>
                            <div class="parent-muted mb-2"><?php echo parentPortalEscape(date('d M Y', strtotime($announcement['created_at']))); ?></div>
                            <div style="white-space: pre-wrap;"><?php echo parentPortalEscape($announcement['message']); ?></div>
                            <?php if (!empty($announcement['attachment_url'])): ?>
                                <div class="mt-2">
                                    <a href="<?php echo parentPortalEscape($announcement['attachment_url']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                        <i class="bi bi-paperclip"></i> Attachment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="parent-empty">No active announcements right now.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="parent-card">
            <div class="parent-card-head d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Receipts</h5>
                <a href="<?php echo APP_URL; ?>/modules/parent/receipts.php" class="text-decoration-none small">View all</a>
            </div>
            <div class="parent-card-body">
                <?php if (!empty($receipts)): ?>
                    <div class="table-responsive">
                        <table class="parent-table">
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Student</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo parentPortalEscape($receipt['receipt_no']); ?></strong>
                                            <div class="parent-muted"><?php echo parentPortalEscape(date('d M Y', strtotime($receipt['payment_date']))); ?></div>
                                        </td>
                                        <td>
                                            <strong><?php echo parentPortalEscape($receipt['student_name']); ?></strong>
                                            <div class="parent-muted"><?php echo parentPortalEscape(($receipt['class_name'] ?? '-') . ' ' . ($receipt['section_name'] ?? '')); ?></div>
                                        </td>
                                        <td><?php echo formatCurrency($receipt['amount_paid']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="parent-empty">No fee receipts found for the linked children.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$contentHtml = ob_get_clean();

echo parentPortalRenderLayout('Dashboard', $contentHtml, 'dashboard');
