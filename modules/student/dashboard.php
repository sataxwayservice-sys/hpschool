<?php
/**
 * Student Dashboard
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();
requireStudentPortalLogin();

$currentUser = getCurrentUser();
$studentId = studentPortalGetCurrentStudentId();
$summary = getStudentFeeSummary($studentId);
$student = $summary['student'] ?? studentPortalGetStudentRecord($studentId);
$announcements = studentPortalGetAnnouncements(6);
$receipts = studentPortalGetReceipts($studentId, 8);
$documents = studentPortalGetStudentDocuments($studentId, true);
$paymentSettings = studentPortalGetPaymentSettings();

$upiLink = '';
if (!empty($paymentSettings['upi_id']) && $student) {
    $upiLink = studentPortalBuildUpiLink(
        $paymentSettings['upi_id'],
        $paymentSettings['payment_recipient_name'],
        $summary['due_total'] ?? 0,
        trim(($paymentSettings['payment_note'] ?? 'School fee payment') . ' - ' . ($student['student_name'] ?? 'Student'))
    );
}

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Welcome, <?php echo parentPortalEscape($currentUser['full_name'] ?? 'Student'); ?></h1>
    <div class="parent-hero-subtitle">
        View your fee dues, receipts, marksheet, admit card, and school announcements.
    </div>
    <div class="parent-button-row no-print">
        <a href="<?php echo APP_URL; ?>/modules/student/due_fees.php" class="parent-button parent-button-primary">
            <i class="bi bi-cash-stack"></i> Due Fees
        </a>
        <a href="<?php echo APP_URL; ?>/modules/student/receipts.php" class="parent-button parent-button-success">
            <i class="bi bi-receipt"></i> Receipts
        </a>
        <a href="<?php echo APP_URL; ?>/modules/student/marksheet.php" class="parent-button">
            <i class="bi bi-file-earmark-pdf"></i> Marksheet
        </a>
        <a href="<?php echo APP_URL; ?>/modules/student/admit_card.php" class="parent-button">
            <i class="bi bi-card-heading"></i> Admit Card
        </a>
        <a href="<?php echo APP_URL; ?>/modules/student/certificates.php" class="parent-button">
            <i class="bi bi-award"></i> Certificates
        </a>
    </div>
</div>

<div class="parent-summary-grid">
    <div class="parent-summary-card">
        <span class="parent-summary-label">Student</span>
        <span class="parent-summary-value"><?php echo parentPortalEscape($student['student_name'] ?? '-'); ?></span>
        <span class="parent-muted"><?php echo parentPortalEscape(($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '')); ?></span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Pending Fees</span>
        <span class="parent-summary-value text-danger"><?php echo formatCurrency($summary['due_total'] ?? 0); ?></span>
        <span class="parent-muted">Month-wise due amount</span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Fees Paid</span>
        <span class="parent-summary-value"><?php echo formatCurrency($summary['paid_total'] ?? 0); ?></span>
        <span class="parent-muted">Collected so far</span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Announcements</span>
        <span class="parent-summary-value"><?php echo count($announcements); ?></span>
        <span class="parent-muted">Active school notices</span>
    </div>
    <div class="parent-summary-card">
        <span class="parent-summary-label">Certificates</span>
        <span class="parent-summary-value"><?php echo count($documents); ?></span>
        <span class="parent-muted">Visible to your account</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="parent-card h-100">
            <div class="parent-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">My Details</h5>
                    <div class="parent-muted">Your account is linked only to your own student record.</div>
                </div>
                <div class="parent-muted"><?php echo date('d M Y'); ?></div>
            </div>
            <div class="parent-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <?php if (!empty($student['photo'])): ?>
                            <img src="<?php echo parentPortalEscape(getStudentPhotoSrc($student['photo'])); ?>" alt="Student Photo" class="img-fluid rounded border" style="width: 100%; max-width: 170px;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center border rounded bg-light fw-bold text-primary" style="width: 100%; max-width: 170px; min-height: 210px;">NO PHOTO</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2">
                            <div class="col-sm-6"><strong>Name:</strong> <?php echo parentPortalEscape($student['student_name'] ?? '-'); ?></div>
                            <div class="col-sm-6"><strong>Admission No:</strong> <?php echo parentPortalEscape($student['admission_no'] ?? '-'); ?></div>
                            <div class="col-sm-6"><strong>Class:</strong> <?php echo parentPortalEscape(($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '')); ?></div>
                            <div class="col-sm-6"><strong>Roll No:</strong> <?php echo parentPortalEscape($student['roll_no'] ?? '-'); ?></div>
                            <div class="col-sm-6"><strong>Father:</strong> <?php echo parentPortalEscape($student['father_name'] ?? '-'); ?></div>
                            <div class="col-sm-6"><strong>Mobile:</strong> <?php echo parentPortalEscape($student['contact_no'] ?? '-'); ?></div>
                        </div>

                        <div class="parent-button-row mt-3">
                            <a href="<?php echo APP_URL; ?>/modules/student/due_fees.php" class="parent-button parent-button-warning">
                                <i class="bi bi-clock-history"></i> View Pending Fees
                            </a>
                            <a href="<?php echo APP_URL; ?>/modules/student/receipts.php" class="parent-button parent-button-success">
                                <i class="bi bi-receipt"></i> Download Receipts
                            </a>
                        </div>

                        <?php if (!empty($upiLink) && floatval($summary['due_total'] ?? 0) > 0): ?>
                            <div class="mt-3 p-3 border rounded-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <div class="fw-bold">Pay pending fee</div>
                                        <div class="parent-muted">Open any UPI app and pay your due amount.</div>
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
                                        <i class="bi bi-wallet2"></i> Other UPI App
                                    </a>
                                    <img src="<?php echo parentPortalEscape(buildQrCodeUrl($upiLink, 120)); ?>" alt="UPI QR" style="width: 72px; height: 72px; margin-left: auto;">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
                <a href="<?php echo APP_URL; ?>/modules/student/receipts.php" class="text-decoration-none small">View all</a>
            </div>
            <div class="parent-card-body">
                <?php if (!empty($receipts)): ?>
                    <div class="table-responsive">
                        <table class="parent-table">
                            <thead>
                                <tr>
                                    <th>Receipt</th>
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
                                        <td><?php echo formatCurrency($receipt['amount_paid']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="parent-empty">No fee receipts found yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="parent-card">
            <div class="parent-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">My Certificates</h5>
                    <div class="parent-muted">Documents approved for your student account.</div>
                </div>
                <a href="<?php echo APP_URL; ?>/modules/student/certificates.php" class="text-decoration-none small">View all</a>
            </div>
            <div class="parent-card-body">
                <?php if (!empty($documents)): ?>
                    <div class="table-responsive">
                        <table class="parent-table">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Issue Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($documents, 0, 5) as $document): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo parentPortalEscape($document['document_title'] ?? '-'); ?></strong>
                                            <div class="parent-muted"><?php echo parentPortalEscape(ucwords(str_replace('_', ' ', $document['document_type'] ?? 'Document'))); ?></div>
                                        </td>
                                        <td><?php echo !empty($document['issue_date']) ? parentPortalEscape(date('d M Y', strtotime($document['issue_date']))) : '-'; ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/reports/student_documents.php?record_id=<?php echo intval($document['document_id']); ?>"
                                               class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                                <i class="bi bi-printer"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="parent-empty">No certificates are visible for your account yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$contentHtml = ob_get_clean();

echo studentPortalRenderLayout('Dashboard', $contentHtml, 'dashboard');
