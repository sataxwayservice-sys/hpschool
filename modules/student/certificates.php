<?php
/**
 * Student Certificates
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

studentPortalEnsureSchema();
requireStudentPortalLogin();

$studentId = studentPortalGetCurrentStudentId();
$student = studentPortalGetStudentRecord($studentId);
$documents = studentPortalGetStudentDocuments($studentId, true);

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Certificates</h1>
    <div class="parent-hero-subtitle">View the certificates approved for your student account.</div>
</div>

<div class="parent-card">
    <div class="parent-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Available Documents</h5>
            <div class="parent-muted">
                <?php echo parentPortalEscape($student['student_name'] ?? 'Student'); ?> | <?php echo parentPortalEscape($student['admission_no'] ?? '-'); ?>
            </div>
        </div>
        <span class="badge bg-primary"><?php echo count($documents); ?> visible</span>
    </div>
    <div class="parent-card-body">
        <?php if (!empty($documents)): ?>
            <div class="table-responsive">
                <table class="parent-table">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td>
                                    <strong><?php echo parentPortalEscape($document['document_title'] ?? '-'); ?></strong>
                                    <div class="parent-muted"><?php echo parentPortalEscape(ucwords(str_replace('_', ' ', $document['document_type'] ?? 'Document'))); ?></div>
                                </td>
                                <td><?php echo !empty($document['issue_date']) ? parentPortalEscape(date('d M Y', strtotime($document['issue_date']))) : '-'; ?></td>
                                <td><span class="badge bg-success">Available</span></td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/modules/reports/student_documents.php?record_id=<?php echo intval($document['document_id']); ?>"
                                       class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                        <i class="bi bi-printer"></i> View / Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="parent-empty">No certificates have been made visible for your account yet.</div>
        <?php endif; ?>
    </div>
</div>
<?php
$contentHtml = ob_get_clean();

echo studentPortalRenderLayout('Certificates', $contentHtml, 'certificates');
