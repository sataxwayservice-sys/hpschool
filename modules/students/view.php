<?php
/**
 * View Student Details
 * Complete student profile with all information
 */

// Include configuration
require_once '../../config/config.php';
require_once '../../includes/student_portal.php';

// Require login
requireLogin();
requirePermission('students', 'view');

studentPortalEnsureSchema();

$pageTitle = 'Student Details';

// Get student ID
$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($studentId == 0) {
    alertAndRedirect('Invalid student ID', APP_URL . '/modules/students/', 'error');
}

// Get student with class and section
$query = "SELECT s.*, c.class_name, sec.section_name
          FROM students s
          JOIN classes c ON s.class_id = c.class_id
          JOIN sections sec ON s.section_id = sec.section_id
          WHERE s.student_id = ?";
$student = fetchOne($query, 'i', [$studentId]);

if (!$student) {
    alertAndRedirect('Student not found', APP_URL . '/modules/students/', 'error');
}

$currentUser = getCurrentUser();
$studentDocuments = studentPortalGetStudentDocuments($studentId, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_document_action'])) {
    if (($currentUser['role'] ?? '') !== 'super_admin') {
        alertAndRedirect('Only Super Admin can change certificate access.', APP_URL . '/modules/students/view.php?id=' . $studentId, 'error');
    }

    $documentId = intval($_POST['document_id'] ?? 0);
    $documentAction = sanitize($_POST['student_document_action'] ?? '');

    if ($documentId > 0 && $documentAction === 'toggle_visibility') {
        $document = studentPortalGetDocument($documentId);
        if ($document && intval($document['student_id'] ?? 0) === $studentId) {
            $nextVisible = intval($document['visible_to_student'] ?? 0) === 1 ? 0 : 1;
            if (studentPortalSetDocumentVisibility($documentId, $nextVisible, intval($currentUser['user_id'] ?? 0))) {
                $_SESSION['success_message'] = $nextVisible ? 'Certificate made visible to student portal.' : 'Certificate hidden from student portal.';
            } else {
                $_SESSION['error_message'] = 'Unable to update certificate visibility.';
            }
        } else {
            $_SESSION['error_message'] = 'Certificate record not found.';
        }
    }

    header('Location: view.php?id=' . $studentId . '#documents');
    exit();
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-person-badge"></i> Student Profile
            </h2>
            <div>
                <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
                <?php if (hasPermission('students', 'edit')): ?>
                <a href="edit.php?id=<?php echo $studentId; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Student Photo and Basic Info -->
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <?php if (!empty($student['photo'])): ?>
                    <img src="<?php echo htmlspecialchars(getStudentPhotoSrc($student['photo'])); ?>"
                         class="img-fluid rounded mb-3" style="max-width: 250px;" alt="Student Photo">
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars(getStudentPhotoSrc()); ?>"
                         class="img-fluid rounded mb-3" style="max-width: 250px;" alt="No Photo">
                <?php endif; ?>

                <h4><?php echo htmlspecialchars($student['student_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($student['admission_no']); ?></p>

                <?php if ($student['status'] == 'Active'): ?>
                    <span class="badge bg-success fs-6">Active</span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Detailed Information -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="bi bi-info-circle"></i> Student Information
                </h5>

                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th width="200">Admission Number</th>
                            <td><strong><?php echo htmlspecialchars($student['admission_no']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo formatDate($student['date_of_birth']); ?></td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?php echo htmlspecialchars($student['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Class</th>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Roll Number</th>
                            <td><?php echo !empty($student['roll_no']) ? htmlspecialchars($student['roll_no']) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>Father's Name</th>
                            <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Mother's Name</th>
                            <td><?php echo !empty($student['mother_name']) ? htmlspecialchars($student['mother_name']) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>Contact Number</th>
                            <td><?php echo htmlspecialchars($student['contact_no']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo !empty($student['email']) ? htmlspecialchars($student['email']) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?php echo !empty($student['address']) ? nl2br(htmlspecialchars($student['address'])) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>Admission Date</th>
                            <td><?php echo formatDate($student['admission_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php if ($student['status'] == 'Active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-lightning-fill"></i> Quick Actions
                </h5>
                <div class="row mt-3">
                    <div class="col-md-3 mb-2">
                        <?php if (hasPermission('fees', 'add')): ?>
                        <a href="<?php echo APP_URL; ?>/modules/fees/collect.php?student_id=<?php echo $studentId; ?>" class="btn btn-success w-100">
                            <i class="bi bi-cash-coin"></i> Collect Fee
                        </a>
                        <?php else: ?>
                        <button class="btn btn-success w-100" disabled>
                            <i class="bi bi-cash-coin"></i> Collect Fee
                        </button>
                        <small class="text-muted d-block mt-1">No permission</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL; ?>/modules/fees/structure.php?student_id=<?php echo $studentId; ?>" class="btn btn-info w-100">
                            <i class="bi bi-list-check"></i> Fee Structure
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL; ?>/modules/marks/enter_marks.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary w-100">
                            <i class="bi bi-pencil-square"></i> Enter Marks
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL; ?>/modules/students/generate_id_card.php?student_id=<?php echo $studentId; ?>&mode=single" class="btn btn-secondary w-100" target="_blank">
                            <i class="bi bi-credit-card"></i> Print ID Card
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL; ?>/modules/reports/student_documents.php?type=transfer_certificate&student_id=<?php echo $studentId; ?>" class="btn btn-dark w-100" target="_blank">
                            <i class="bi bi-award"></i> Certificates
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-warning w-100" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4" id="documents">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-award"></i> Certificates & Documents</h5>
                <span class="badge bg-light text-dark"><?php echo count($studentDocuments); ?> saved</span>
            </div>
            <div class="card-body">
                <?php if (!empty($studentDocuments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Issue Date</th>
                                    <th>Access</th>
                                    <th>Generated By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentDocuments as $document): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($document['document_title'] ?? '-'); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $document['document_type'] ?? 'Document'))); ?></div>
                                        </td>
                                        <td><?php echo !empty($document['issue_date']) ? formatDate($document['issue_date']) : '-'; ?></td>
                                        <td>
                                            <?php if (intval($document['visible_to_student'] ?? 0) === 1): ?>
                                                <span class="badge bg-success">Visible to student</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($document['generated_by_name'] ?? '-'); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo APP_URL; ?>/modules/reports/student_documents.php?record_id=<?php echo intval($document['document_id']); ?>"
                                                   class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="bi bi-printer"></i> View
                                                </a>
                                                <?php if (($currentUser['role'] ?? '') === 'super_admin'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="student_document_action" value="toggle_visibility">
                                                        <input type="hidden" name="document_id" value="<?php echo intval($document['document_id']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-dark"
                                                                onclick="return confirm('Change certificate visibility for this student?')">
                                                            <i class="bi bi-toggle-on"></i>
                                                            <?php echo intval($document['visible_to_student'] ?? 0) === 1 ? 'Hide' : 'Show'; ?>
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
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        No certificates or saved documents yet. Use the certificate generator to create one for this student.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Fee Summary -->
<?php
// Get fee summary for this student
$feeSummary = fetchOne("SELECT
    COALESCE(SUM(fs.amount), 0) as total_fee,
    COALESCE((SELECT SUM(amount_paid) FROM fee_receipts WHERE student_id = ? AND is_cancelled = 0), 0) as total_paid
    FROM fee_structure fs
    WHERE fs.student_id = ? AND fs.is_active = 1",
    'ii', [$studentId, $studentId]);

$totalDue = $feeSummary['total_fee'] - $feeSummary['total_paid'];

// Build a full paid-record history for this student's profile
$receiptHistoryColumns = fetchAll("SHOW COLUMNS FROM fee_receipts");
$receiptHistoryColumnNames = array_column($receiptHistoryColumns, 'Field');
$receiptDetailColumns = fetchAll("SHOW COLUMNS FROM fee_receipt_details");
$receiptDetailColumnNames = array_column($receiptDetailColumns, 'Field');
$hasReceiptBookColumn = in_array('receipt_book_id', $receiptHistoryColumnNames, true);
$hasReceiptBooksTable = !empty(fetchAll("SHOW TABLES LIKE 'receipt_books'"));
$hasDiscountColumn = in_array('discount', $receiptDetailColumnNames, true);

$receiptHistorySql = "SELECT
        fr.receipt_id,
        fr.receipt_no,
        fr.payment_date,
        fr.amount_paid,
        fr.payment_mode,
        fr.transaction_id,
        fr.is_cancelled";

if ($hasReceiptBookColumn && $hasReceiptBooksTable) {
    $receiptHistorySql .= ",
        rb.book_name,
        rb.prefix";
}

$receiptHistorySql .= "
    FROM fee_receipts fr";

if ($hasReceiptBookColumn && $hasReceiptBooksTable) {
    $receiptHistorySql .= "
    LEFT JOIN receipt_books rb ON fr.receipt_book_id = rb.book_id";
}

$receiptHistorySql .= "
    WHERE fr.student_id = ? AND fr.is_cancelled = 0
    ORDER BY fr.payment_date DESC, fr.receipt_id DESC";

$receiptHistory = fetchAll($receiptHistorySql, 'i', [$studentId]);
$receiptHistoryDetails = [];
$receiptHistoryDiscounts = [];

if (!empty($receiptHistory)) {
    $receiptIds = array_map('intval', array_column($receiptHistory, 'receipt_id'));
    if (!empty($receiptIds)) {
        $placeholders = implode(',', array_fill(0, count($receiptIds), '?'));
        $detailSql = "SELECT
                frd.receipt_id,
                frd.amount,
                frd.fee_month,
                frd.fee_year,
                fh.fee_head_name";

        if ($hasDiscountColumn) {
            $detailSql .= ",
                COALESCE(frd.discount, 0) AS discount";
        } else {
            $detailSql .= ",
                0 AS discount";
        }

        $detailSql .= "
            FROM fee_receipt_details frd
            JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
            WHERE frd.receipt_id IN ($placeholders)
            ORDER BY frd.receipt_id, frd.detail_id";

        $detailRows = fetchAll($detailSql, str_repeat('i', count($receiptIds)), $receiptIds);
        foreach ($detailRows as $detailRow) {
            $detailReceiptId = intval($detailRow['receipt_id']);
            if (!isset($receiptHistoryDetails[$detailReceiptId])) {
                $receiptHistoryDetails[$detailReceiptId] = [];
            }

            $receiptHistoryDetails[$detailReceiptId][] = $detailRow;
            $receiptHistoryDiscounts[$detailReceiptId] = ($receiptHistoryDiscounts[$detailReceiptId] ?? 0) + floatval($detailRow['discount'] ?? 0);
        }
    }
}
?>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h6>Total Fee Assigned</h6>
                <h3><?php echo formatCurrency($feeSummary['total_fee']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h6>Total Paid</h6>
                <h3><?php echo formatCurrency($feeSummary['total_paid']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card text-white bg-<?php echo $totalDue > 0 ? 'danger' : 'info'; ?>">
            <div class="card-body text-center">
                <h6>Due Amount</h6>
                <h3><?php echo formatCurrency($totalDue); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Paid Records -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Paid Records</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($receiptHistory)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Receipt Book</th>
                                    <th>Receipt Number</th>
                                    <th>Receipt Date</th>
                                    <th>Pay Type</th>
                                    <th>Cheque No</th>
                                    <th>Fee Detail</th>
                                    <th class="text-end">Receipt Amount</th>
                                    <th class="text-end">Discount Amount</th>
                                    <th>Status</th>
                                    <th>Print</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receiptHistory as $receipt): ?>
                                    <?php
                                    $receiptIdForRow = intval($receipt['receipt_id']);
                                    $feeDetailRows = $receiptHistoryDetails[$receiptIdForRow] ?? [];
                                    $feeDetailHtml = '';

                                    if (!empty($feeDetailRows)) {
                                        foreach ($feeDetailRows as $detail) {
                                            $detailLine = htmlspecialchars($detail['fee_head_name']);
                                            if (!empty($detail['fee_month'])) {
                                                $detailLine .= ' (' . htmlspecialchars(trim(($detail['fee_month'] ?? '') . ' ' . ($detail['fee_year'] ?? ''))) . ')';
                                            }
                                            $detailLine .= ' - ' . formatCurrency($detail['amount']);
                                            $feeDetailHtml .= '<div>' . $detailLine . '</div>';
                                        }
                                    } else {
                                        $feeDetailHtml = '<span class="text-muted">N/A</span>';
                                    }

                                    $receiptBookLabel = 'N/A';
                                    if ($hasReceiptBookColumn && $hasReceiptBooksTable) {
                                        if (!empty($receipt['book_name'])) {
                                            $receiptBookLabel = $receipt['book_name'];
                                        } elseif (!empty($receipt['prefix'])) {
                                            $receiptBookLabel = $receipt['prefix'];
                                        }
                                    }

                                    $chequeNoLabel = !empty($receipt['transaction_id']) ? $receipt['transaction_id'] : 'N/A';
                                    $discountAmount = $receiptHistoryDiscounts[$receiptIdForRow] ?? 0;
                                    $paymentMode = strtolower((string)($receipt['payment_mode'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($receiptBookLabel); ?></td>
                                        <td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                                        <td><?php echo formatDate($receipt['payment_date']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $paymentMode === 'cash' ? 'success' :
                                                    ($paymentMode === 'upi' ? 'info' :
                                                        ($paymentMode === 'bank' ? 'primary' : 'warning'));
                                            ?>">
                                                <?php echo htmlspecialchars($receipt['payment_mode']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($chequeNoLabel); ?></td>
                                        <td><small><?php echo $feeDetailHtml; ?></small></td>
                                        <td class="text-end"><strong class="text-success"><?php echo formatCurrency($receipt['amount_paid']); ?></strong></td>
                                        <td class="text-end"><?php echo formatCurrency($discountAmount); ?></td>
                                        <td><span class="badge bg-success">Paid</span></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/fees/receipt.php?id=<?php echo $receiptIdForRow; ?>"
                                               class="btn btn-sm btn-primary" target="_blank" title="Print Receipt">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        No paid records found for this student.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>
