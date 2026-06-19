<?php
/**
 * Parent Portal Fee Receipts
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';

requireParentPortalLogin();
parentPortalEnsureSchema();

$currentUser = getCurrentUser();
$students = parentPortalGetLinkedStudents($currentUser['user_id']);
$studentIds = array_map(fn($student) => intval($student['student_id']), $students);

$selectedStudentId = intval($_GET['student_id'] ?? 0);
if ($selectedStudentId <= 0 && count($students) === 1) {
    $selectedStudentId = intval($students[0]['student_id']);
}
if ($selectedStudentId > 0) {
    $studentIds = array_filter($studentIds, fn($id) => $id === $selectedStudentId);
}

$receipts = parentPortalGetReceipts($studentIds, 50);

$contentHtml = ob_start();
?>
<div class="parent-hero">
    <h1 class="parent-hero-title">Paid Fee Receipts</h1>
    <div class="parent-hero-subtitle">View and print fee receipts for your linked children.</div>
</div>

<?php if (empty($students)): ?>
    <div class="parent-card">
        <div class="parent-card-body">
            <div class="parent-empty">No children are linked to this parent account yet.</div>
        </div>
    </div>
<?php else: ?>
    <div class="parent-card mb-3">
        <div class="parent-card-head">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Filter by Child</label>
                    <select class="form-select" name="student_id">
                        <option value="0">All Linked Children</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo intval($student['student_id']); ?>" <?php echo $selectedStudentId === intval($student['student_id']) ? 'selected' : ''; ?>>
                                <?php echo parentPortalEscape($student['student_name'] . ' - ' . $student['class_name'] . ' ' . $student['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Show Receipts
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="parent-card">
        <div class="parent-card-head">
            <h5 class="mb-0">Receipt History</h5>
        </div>
        <div class="parent-card-body">
            <?php if (!empty($receipts)): ?>
                <div class="table-responsive">
                    <table class="parent-table">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Student</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                                <th>Mode</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><strong><?php echo parentPortalEscape($receipt['receipt_no']); ?></strong></td>
                                    <td>
                                        <strong><?php echo parentPortalEscape($receipt['student_name']); ?></strong>
                                        <div class="parent-muted"><?php echo parentPortalEscape(($receipt['class_name'] ?? '-') . ' ' . ($receipt['section_name'] ?? '')); ?></div>
                                    </td>
                                    <td><?php echo parentPortalEscape(date('d M Y', strtotime($receipt['payment_date']))); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($receipt['amount_paid']); ?></td>
                                    <td><?php echo parentPortalEscape($receipt['payment_mode']); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/modules/parent/receipt.php?id=<?php echo intval($receipt['receipt_id']); ?>" class="parent-button parent-button-primary" target="_blank" rel="noopener">
                                            <i class="bi bi-printer"></i> Print
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="parent-empty">No paid receipts found for the selected child or account.</div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$contentHtml = ob_get_clean();

echo parentPortalRenderLayout('Receipts', $contentHtml, 'receipts');
