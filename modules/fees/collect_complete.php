<?php
/**
 * Professional Fee Receipt Collection
 * Matching the uploaded design layout
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'add');

$pageTitle = 'Fee Receipt';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$error = '';
$success = '';

// Get school settings
$schoolSettings = getSchoolSettings();
$currentBatch = $schoolSettings['current_academic_year'] ?? date('Y') . '-' . (date('Y') + 1);
$schoolName = $schoolSettings['school_name'] ?? 'My School';

// Get student if ID provided
$student = null;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : (isset($_POST['student_id']) ? intval($_POST['student_id']) : 0);

// Get academic batches
$batches = fetchAll("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC");
$classes = fetchAll("SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_order");

// Get receipt books - check if table exists first
$receiptBooks = [];
$receiptBooksTableExists = false;
$tableCheck = fetchAll("SHOW TABLES LIKE 'receipt_books'");
if (count($tableCheck) > 0) {
    $receiptBooksTableExists = true;
    $receiptBooks = fetchAll("SELECT * FROM receipt_books WHERE is_active = 1 ORDER BY book_name");
}

if ($studentId > 0) {
    $query = "SELECT s.*, c.class_name, sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.student_id = ?";
    $studentParams = [$studentId];
    $studentTypes = 'i';
    if ($currentSchoolId > 0) {
        $query .= " AND s.school_id = ?";
        $studentParams[] = $currentSchoolId;
        $studentTypes .= 'i';
    }
    $student = fetchOne($query, $studentTypes, $studentParams);

    if (!$student) {
        $studentId = 0;
    }

    // Get student reminders
    $reminders = fetchAll("SELECT r.*, u.username as created_by_name
                           FROM student_reminders r
                           LEFT JOIN users u ON r.created_by = u.user_id
                           WHERE r.student_id = ? AND r.is_resolved = 0
                           ORDER BY r.priority DESC, r.created_at DESC", 'i', [$studentId]);

    // Get payment history (all receipts for this student)
    // TEMPORARY: Get ALL receipts without filtering to debug
    $paymentHistory = fetchAll("SELECT fr.*, u.full_name as collected_by_name
                                FROM fee_receipts fr
                                LEFT JOIN users u ON fr.collected_by = u.user_id
                                WHERE fr.student_id = ? AND fr.is_cancelled = 0
                                ORDER BY fr.payment_date DESC, fr.receipt_id DESC", 'i', [$studentId]);

    // Add total_amount to each receipt (use existing field or calculate from details)
    if (!empty($paymentHistory)) {
        foreach ($paymentHistory as &$receipt) {
            // If total_amount field exists and has value, use it; otherwise calculate
            if (!isset($receipt['total_amount']) || $receipt['total_amount'] == 0) {
                $details = fetchAll("SELECT SUM(amount) as total FROM fee_receipt_details WHERE receipt_id = ?", 'i', [$receipt['receipt_id']]);
                $receipt['total_amount'] = $details[0]['total'] ?? $receipt['total_amount'] ?? 0;
            }
        }
        unset($receipt); // Break reference
    }
} else {
    $reminders = [];
    $paymentHistory = [];
}

// Get student's pending fees using the shared month-wise fee summary helper
$pendingFees = [];
$feeItems = [];
$payableFees = [];
$feeSummary = null;
if ($studentId > 0) {
    $feeSummary = getStudentFeeSummary($studentId);
    $pendingFees = $feeSummary['pending_items'] ?? [];
    $feeItems = $feeSummary['fee_items'] ?? $pendingFees;
}

// DEBUG: Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success .= "<br><strong>DEBUG:</strong> Form was POSTed";
    if (isset($_POST['save_receipt'])) {
        $success .= "<br><strong>DEBUG:</strong> save_receipt field is set";
    } else {
        $error .= "<br><strong>DEBUG:</strong> save_receipt field is MISSING!";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_receipt'])) {
    // DEBUG: Log that form was submitted
    error_log("Fee collection form submitted for student_id: " . ($_POST['student_id'] ?? 'unknown'));
    $success .= "<br><strong>DEBUG:</strong> Processing fee collection...";

    $studentId = intval($_POST['student_id']);
    $receiptBookId = isset($_POST['receipt_book_id']) ? intval($_POST['receipt_book_id']) : null;
    $paymentMode = sanitize($_POST['pay_type']);
    $paymentDate = sanitize($_POST['receipt_date']);
    $chequeNo = sanitize($_POST['cheque_no'] ?? '');
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $chequeDate = sanitize($_POST['cheque_date'] ?? null);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $amount = floatval($_POST['amount']);
    $charge = floatval($_POST['charge'] ?? 0);

    // Get selected payable fees
    $selectedFees = $_POST['selected_fees'] ?? [];
    $payableAmounts = $_POST['payable_amount'] ?? [];

    // DEBUG: Log selected fees count
    error_log("Selected fees count: " . count($selectedFees));

    if (count($selectedFees) == 0) {
        $error = 'Please select at least one fee to collect!';
    } else {
        beginTransaction();
        try {
            // Generate receipt number
            $receiptNo = getNextReceiptNumber();

            // Calculate total from selected fees
            $totalAmount = 0;
            foreach ($selectedFees as $index) {
                if (isset($payableAmounts[$index])) {
                    $totalAmount += floatval($payableAmounts[$index]);
                }
            }

            $totalAmount += $charge;

            // Check which columns exist in fee_receipts table
            $conn = getDbConnection();
            $columnsQuery = "SHOW COLUMNS FROM fee_receipts";
            $columnsResult = $conn->query($columnsQuery);
            $existingColumns = [];
            while ($row = $columnsResult->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }

            // Build dynamic insert query based on existing columns
            $hasReceiptBookId = in_array('receipt_book_id', $existingColumns);
            $hasChargeAmount = in_array('charge_amount', $existingColumns);
            $hasBankName = in_array('bank_name', $existingColumns);
            $hasChequeDate = in_array('cheque_date', $existingColumns);

            // Build columns and values arrays
            $columns = ['receipt_no', 'student_id', 'total_amount', 'amount_paid', 'payment_mode', 'transaction_id', 'payment_date', 'collected_by', 'remarks'];
            $values = [$receiptNo, $studentId, $totalAmount, $totalAmount, $paymentMode, $chequeNo, $paymentDate, $currentUser['user_id'], $remarks];
            $types = 'siddsssis';

            if ($hasReceiptBookId && $receiptBookId) {
                $columns[] = 'receipt_book_id';
                $values[] = $receiptBookId;
                $types .= 'i';
            }

            if ($hasChargeAmount) {
                $columns[] = 'charge_amount';
                $values[] = $charge;
                $types .= 'd';
            }

            if ($hasBankName && !empty($bankName)) {
                $columns[] = 'bank_name';
                $values[] = $bankName;
                $types .= 's';
            }

            if ($hasChequeDate && !empty($chequeDate)) {
                $columns[] = 'cheque_date';
                $values[] = $chequeDate;
                $types .= 's';
            }

            $columnsList = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));

            $insertReceipt = "INSERT INTO fee_receipts ($columnsList) VALUES ($placeholders)";

            $result = executeQuery($insertReceipt, $types, $values);

            $receiptId = $result['insert_id'];

            // DEBUG: Log successful receipt creation
            error_log("Receipt created successfully with ID: $receiptId, Receipt No: $receiptNo");

            // Insert fee details
            foreach ($selectedFees as $index) {
                if (isset($_POST['fee_head_id'][$index])) {
                    $feeHeadId = intval($_POST['fee_head_id'][$index]);
                    $feeAmount = floatval($payableAmounts[$index]);
                    $feeMonth = sanitize($_POST['fee_month'][$index] ?? null);
                    $feeYear = sanitize($_POST['fee_year'][$index] ?? null);
                    $discountAmount = floatval($_POST['discount_amount'][$index] ?? 0);
                    $discountReason = sanitize($_POST['discount_reason'][$index] ?? null);

                    // Check if discount columns exist in fee_receipt_details
                    $detailColumnsQuery = "SHOW COLUMNS FROM fee_receipt_details";
                    $detailColumnsResult = $conn->query($detailColumnsQuery);
                    $detailColumns = [];
                    while ($row = $detailColumnsResult->fetch_assoc()) {
                        $detailColumns[] = $row['Field'];
                    }

                    $hasDiscount = in_array('discount', $detailColumns);
                    $hasDiscountReason = in_array('discount_reason', $detailColumns);

                    if ($hasDiscount && $hasDiscountReason) {
                        // New schema with discount support
                        $insertDetail = "INSERT INTO fee_receipt_details
                                        (receipt_id, fee_head_id, fee_month, fee_year, amount, discount, discount_reason)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                        executeQuery($insertDetail, 'iissdds', [
                            $receiptId, $feeHeadId, $feeMonth, $feeYear, $feeAmount, $discountAmount, $discountReason
                        ]);
                    } else {
                        // Legacy schema without discount
                        $insertDetail = "INSERT INTO fee_receipt_details
                                        (receipt_id, fee_head_id, fee_month, fee_year, amount)
                                        VALUES (?, ?, ?, ?, ?)";
                        executeQuery($insertDetail, 'iissd', [
                            $receiptId, $feeHeadId, $feeMonth, $feeYear, $feeAmount
                        ]);
                    }
                }
            }

            // Log activity
            logActivity($currentUser['user_id'], 'Collect Fee', 'fees',
                "Fee collected: Receipt $receiptNo, Amount: " . formatCurrency($totalAmount));

            // DEBUG: Before commit
            error_log("About to commit transaction for receipt ID: $receiptId");
            $success .= "<br><strong>DEBUG:</strong> About to commit transaction...";

            // Try to commit with error checking
            try {
                commitTransaction();
                $success .= "<br><strong>DEBUG:</strong> commitTransaction() called successfully";
            } catch (Exception $commitError) {
                $success .= "<br><strong>ERROR:</strong> Commit failed: " . $commitError->getMessage();
                error_log("Commit error: " . $commitError->getMessage());
            }

            // DEBUG: After commit - verify it's in database
            $conn = getDbConnection();
            $verifyQuery = "SELECT COUNT(*) as count FROM fee_receipts WHERE receipt_id = ?";
            $stmt = $conn->prepare($verifyQuery);
            $stmt->bind_param('i', $receiptId);
            $stmt->execute();
            $verifyResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($verifyResult['count'] > 0) {
                $success .= "<br><strong>✅ VERIFIED:</strong> Receipt ID $receiptId exists in database!";
            } else {
                $success .= "<br><strong>❌ WARNING:</strong> Receipt ID $receiptId NOT found in database after commit!";
            }

            error_log("Transaction committed for receipt ID: $receiptId, Verified in DB: " . ($verifyResult['count'] > 0 ? 'YES' : 'NO'));
            $success .= "<br><strong>DEBUG:</strong> Transaction processing complete";

            // Redirect to receipt page immediately to prevent script from continuing and rolling back
            $_SESSION['success_message'] = "Fee collected successfully! Receipt No: $receiptNo (ID: $receiptId)";

            // IMPORTANT: Exit immediately after commit to prevent rollback
            header("Location: receipt.php?id=$receiptId");
            exit();

        } catch (Exception $e) {
            rollbackTransaction();
            $error = 'Failed to save receipt: ' . $e->getMessage();

            // Enhanced error logging
            error_log("Receipt save failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // Show detailed error in alert
            $error .= "<br><br><strong>Technical Details:</strong><br>";
            $error .= "Error: " . $e->getMessage() . "<br>";
            $error .= "File: " . $e->getFile() . "<br>";
            $error .= "Line: " . $e->getLine();
        }
    }
}

// Get session messages (APPEND, don't overwrite debug messages)
if (isset($_SESSION['success_message'])) {
    $success .= "<br>" . $_SESSION['success_message'];  // Append instead of overwrite
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error .= "<br>" . $_SESSION['error_message'];  // Append instead of overwrite
    unset($_SESSION['error_message']);
}

// Include header
include '../../includes/header.php';
?>

<style>
.payable-fee-row, .pending-fee-row {
    cursor: pointer;
    transition: background-color 0.2s;
}
.payable-fee-row:hover, .pending-fee-row:hover {
    background-color: #f8f9fa !important;
}
.payable-fee-row.selected {
    background-color: #d4edda !important;
}
.table-payable td, .table-pending td {
    padding: 8px !important;
    vertical-align: middle !important;
}
.fee-action-btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.top-actions {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.student-info-box {
    background: #e7f3ff;
    border-left: 4px solid #0066cc;
    padding: 12px 15px;
    margin-bottom: 15px;
}
.form-control-sm, .form-select-sm {
    font-size: 0.875rem;
}
.fee-search-row {
    align-items: end;
}
.fee-search-toolbar {
    display: flex;
    flex-wrap: nowrap;
    width: 100%;
}
.fee-search-toolbar .btn {
    white-space: nowrap;
}
.student-search-card {
    position: relative;
    overflow: visible;
    z-index: 20;
}
.student-search-card .card-body {
    overflow: visible;
}
.student-search-card .student-autocomplete-host {
    z-index: 30;
}
.student-search-card .student-autocomplete-menu {
    z-index: 9999;
}
@media (max-width: 575.98px) {
    .fee-search-toolbar {
        flex-wrap: wrap;
    }
    .fee-search-toolbar .btn {
        flex: 1 1 100%;
    }
}
</style>

<!-- Top Action Buttons -->
<div class="top-actions d-flex justify-content-between align-items-center">
    <div>
        <button type="button" class="btn btn-success btn-sm" id="saveBtn" disabled>
            <i class="bi bi-floppy"></i> Save
        </button>
        <button type="button" class="btn btn-primary btn-sm" disabled>
            <i class="bi bi-pencil"></i> Edit
        </button>
        <button type="button" class="btn btn-danger btn-sm" disabled>
            <i class="bi bi-trash"></i> Delete
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='collect.php'">
            <i class="bi bi-x-circle"></i> Cancel
        </button>
        <button type="button" class="btn btn-warning btn-sm" onclick="clearForm()">
            <i class="bi bi-arrow-clockwise"></i> Clear
        </button>
        <button type="button" class="btn btn-info btn-sm" onclick="window.open('receipts.php', '_blank')">
            <i class="bi bi-file-earmark-text"></i> View Report
        </button>
    </div>
    <div>
        <button type="button" class="btn btn-secondary btn-sm" onclick='window.location.href=<?php echo json_encode(getSmartBackUrl(APP_URL . "/modules/fees/collect.php")); ?>'>
            <i class="bi bi-arrow-left"></i> Back
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$receiptBooksTableExists): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Notice:</strong> Receipt books table not found. Please visit
        <a href="../../test_receipt_tables.php" target="_blank" class="alert-link">test_receipt_tables.php</a>
        to create required tables for full functionality.
    </div>
<?php endif; ?>

<form method="POST" action="" id="feeReceiptForm">
    <input type="hidden" name="student_id" id="student_id" value="<?php echo $studentId; ?>">

    <div class="row">
        <!-- Left Section -->
        <div class="col-12">
            <div class="card mb-3 student-search-card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-person-badge"></i> Student Search & Details</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Organization <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($schoolName); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch</label>
                            <select class="form-select form-select-sm" id="batch" name="batch">
                                <option value="">-- Select Batch --</option>
                                <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo htmlspecialchars($batch['batch']); ?>"
                                            <?php echo ($batch['batch'] == $currentBatch) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($batch['batch']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-2 g-3 fee-search-row">
                        <div class="col-12 col-lg-6">
                            <label class="form-label">Search Student <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm fee-search-toolbar">
                                <input type="text" class="form-control form-control-sm" id="searchAdmNo"
                                       placeholder="Enter Admission No, Name, or Roll No"
                                       autocomplete="off"
                                       data-student-autocomplete="true"
                                       data-student-autocomplete-fill="admission_no"
                                       data-student-autocomplete-submit="#searchBtn"
                                       data-student-autocomplete-class="#searchClassId">
                                <button type="button" class="btn btn-primary" id="searchBtn">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn" title="Clear">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label">Class Filter</label>
                            <select class="form-select form-select-sm" id="searchClassId">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <small class="text-muted d-block mb-3">Search by admission number, student name, or roll number.</small>

                    <!-- Search Results -->
                    <div id="searchResults" class="mb-3"></div>

                    <?php if ($student): ?>
                    <!-- Student Reminders Alert -->
                    <?php if (count($reminders) > 0): ?>
                    <div class="alert alert-warning alert-dismissible alert-permanent fade show" role="alert">
                        <h6 class="alert-heading"><i class="bi bi-bell-fill"></i> Active Reminders (<?php echo count($reminders); ?>)</h6>
                        <ul class="mb-0 small">
                            <?php foreach (array_slice($reminders, 0, 2) as $reminder): ?>
                            <li>
                                <?php if ($reminder['priority'] == 'high'): ?>🔴<?php elseif ($reminder['priority'] == 'medium'): ?>🟡<?php else: ?>🟢<?php endif; ?>
                                <?php echo htmlspecialchars(substr($reminder['reminder_text'], 0, 80)) . (strlen($reminder['reminder_text']) > 80 ? '...' : ''); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($reminders) > 2): ?>
                        <small class="text-muted">+ <?php echo count($reminders) - 2; ?> more reminder(s)</small>
                        <?php endif; ?>
                        <hr>
                        <button type="button" class="btn btn-sm btn-warning" onclick="openReminderModal()">
                            <i class="bi bi-eye"></i> View All Reminders
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="student-info-box">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <strong>Name:</strong> <span class="text-primary"><?php echo htmlspecialchars($student['student_name']); ?></span>
                                <button type="button" class="btn btn-sm btn-outline-warning float-end" onclick="openReminderModal()" title="View/Add Reminders">
                                    <i class="bi bi-bell-fill"></i>
                                    <?php if (count($reminders) > 0): ?>
                                    <span class="badge bg-danger"><?php echo count($reminders); ?></span>
                                    <?php endif; ?>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_no']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong>
                                <span class="badge bg-<?php echo $student['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($student['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-12">
                                <strong>Class/Division/Roll No:</strong>
                                <?php echo htmlspecialchars(($student['class_name'] ?? 'N/A') . '-' . ($student['section_name'] ?? 'N/A') . '-' . ($student['roll_no'] ?? 'N/A')); ?>
                            </div>
                            <div class="col-md-12">
                                <strong>Father Name:</strong> <?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-12">
                                <strong>Mother Name:</strong> <?php echo htmlspecialchars($student['mother_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-12">
                                <strong>Mobile:</strong> <?php echo htmlspecialchars($student['contact_no'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-gear"></i> <strong>Manage Fee Assignments</strong></span>
                            <a href="<?php echo APP_URL; ?>/manage_assigned_fees.php?student_id=<?php echo $studentId; ?>"
                               class="btn btn-sm btn-primary"
                               target="_blank">
                                <i class="bi bi-pencil-square"></i> View/Edit Assigned Fees
                            </a>
                        </div>
                    </div>

                    <?php if ($feeSummary): ?>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted d-block">Assigned</small>
                                <strong><?php echo formatCurrency($feeSummary['assigned_total']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted d-block">Paid</small>
                                <strong class="text-success"><?php echo formatCurrency($feeSummary['paid_total']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted d-block">Due</small>
                                <strong class="text-danger"><?php echo formatCurrency($feeSummary['due_total']); ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Tabs for Collect Fees and Payment History (Full Width) -->
    <?php if ($student): ?>
    <div class="row mt-3">
        <div class="col-12">
            <ul class="nav nav-tabs" id="feeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="collect-tab" data-bs-toggle="tab" data-bs-target="#collect-fees" type="button" role="tab">
                        <i class="bi bi-cash-coin"></i> Collect Fees
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#payment-history" type="button" role="tab">
                        <i class="bi bi-clock-history"></i> Payment History (<?php echo count($paymentHistory); ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content border border-top-0 p-3 bg-white" id="feeTabContent">
                <!-- Collect Fees Tab -->
                <div class="tab-pane fade show active" id="collect-fees" role="tabpanel">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-receipt"></i> Receipt Details</h6>
                                </div>
                                <div class="card-body">
                    <?php if ($receiptBooksTableExists): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Receipt Book</label>
                            <select class="form-select form-select-sm" name="receipt_book_id">
                                <option value="">- ALL -</option>
                                <?php foreach ($receiptBooks as $book): ?>
                                    <option value="<?php echo $book['book_id']; ?>">
                                        <?php echo htmlspecialchars($book['book_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Receipt No</label>
                            <input type="text" class="form-control form-control-sm" readonly placeholder="Auto-generated after save">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Selected Month / Fee</label>
                            <input type="text" class="form-control form-control-sm" id="selectedFeesDisplay" readonly value="No fee selected yet">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cheque No / Reference No</label>
                            <input type="text" class="form-control form-control-sm" name="cheque_no" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control form-control-sm" name="bank_name" placeholder="Optional">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Paying Amount</label>
                            <input type="number" class="form-control form-control-sm" name="amount" id="totalAmountField" step="0.01" min="0" placeholder="Enter amount">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fine</label>
                            <input type="number" class="form-control form-control-sm" name="charge" id="chargeAmount" value="0" step="0.01" placeholder="Optional">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Section -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Payment Details</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" name="receipt_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pay Type <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="pay_type" required>
                                <option value="Cash" selected>Cash</option>
                                <option value="Bank">Bank Transfer</option>
                                <option value="UPI">UPI</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cheque Date</label>
                            <input type="date" class="form-control form-control-sm" name="cheque_date">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea class="form-control form-control-sm" name="remarks" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-check-circle"></i> Selected Fees</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0 table-payable">
                            <thead class="table-light">
                                <tr>
                                    <th width="30%">Fee</th>
                                    <th width="15%">Amount</th>
                                    <th width="15%">Discount</th>
                                    <th width="15%">Payable</th>
                                    <th width="20%">Reason</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="payableFeeList">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <em>Select a fee from the table above to add it here</em>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td><strong class="text-danger"><span id="discountTotal">0.00</span></strong></td>
                                    <td><strong class="text-success"><span id="payableTotal">0.00</span></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr class="table-info">
                                    <td colspan="3"><strong>Net Amount Payable</strong></td>
                                    <td colspan="3"><strong class="text-primary fs-5"><span id="netAmount">0.00</span></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($student && count($feeItems) > 0): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-list-check"></i> Fee Details Table</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0 table-pending table-fee-details">
                            <thead class="table-light">
                                <tr>
                                    <th width="12%">Month</th>
                                    <th width="26%">Fee Type</th>
                                    <th width="12%">Amount</th>
                                    <th width="12%">Paid</th>
                                    <th width="12%">Due</th>
                                    <th width="12%">Status</th>
                                    <th width="14%">Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feeItems as $index => $fee): ?>
                                <?php
                                    $displayMonth = trim((string)($fee['display_month'] ?? $fee['fee_month'] ?? ''));
                                    if ($displayMonth === '') {
                                        $displayMonth = '-';
                                    }
                                    $displayFeeType = trim((string)($fee['display_fee_type'] ?? preg_replace('/\s*\([^)]+\)\s*$/', '', $fee['fee_head_name'])));
                                    $originalAmount = floatval($fee['original_amount'] ?? $fee['amount'] ?? 0);
                                    $paidAmount = floatval($fee['paid_amount'] ?? 0);
                                    $dueAmount = floatval($fee['due_amount'] ?? 0);
                                    $status = $fee['status'] ?? ($dueAmount <= 0 ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid'));
                                    $statusClass = $status === 'Paid' ? 'success' : ($status === 'Partial' ? 'warning' : 'secondary');
                                    $monthValue = trim((string)($fee['fee_month'] ?? ''));
                                    if ($monthValue === '' && $displayMonth !== '-') {
                                        $monthValue = $displayMonth;
                                    }
                                ?>
                                <tr class="pending-fee-row"
                                    data-index="<?php echo $index; ?>"
                                    data-fee-id="<?php echo $fee['fee_head_id']; ?>"
                                    data-fee-name="<?php echo htmlspecialchars($displayFeeType); ?>"
                                    data-display-fee-type="<?php echo htmlspecialchars($displayFeeType); ?>"
                                    data-original-amount="<?php echo $originalAmount; ?>"
                                    data-paid-amount="<?php echo $paidAmount; ?>"
                                    data-due-amount="<?php echo $dueAmount; ?>"
                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                    data-month="<?php echo htmlspecialchars($monthValue); ?>"
                                    data-year="<?php echo htmlspecialchars($fee['fee_year']); ?>"
                                    data-start-date="<?php echo htmlspecialchars($fee['start_date']); ?>">
                                    <td class="fee-month-cell"><?php echo htmlspecialchars($displayMonth); ?></td>
                                    <td class="fee-type-cell">
                                        <strong><?php echo htmlspecialchars($displayFeeType); ?></strong>
                                        <?php if (!empty($fee['fee_month'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($fee['fee_month'] . ' ' . $fee['fee_year']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fee-amount-cell"><?php echo formatCurrency($originalAmount); ?></td>
                                    <td class="fee-paid-cell text-success"><?php echo formatCurrency($paidAmount); ?></td>
                                    <td class="fee-due-cell <?php echo $dueAmount > 0 ? 'text-danger fw-bold' : 'text-success'; ?>"><?php echo formatCurrency($dueAmount); ?></td>
                                    <td class="fee-status-cell">
                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                    </td>
                                    <td class="fee-pay-cell">
                                        <?php if ($dueAmount > 0): ?>
                                            <button type="button" class="btn btn-success btn-sm fee-action-btn add-fee-btn"
                                                    data-index="<?php echo $index; ?>"
                                                    data-fee-id="<?php echo $fee['fee_head_id']; ?>"
                                                    data-fee-name="<?php echo htmlspecialchars($displayFeeType); ?>"
                                                    data-original-amount="<?php echo $originalAmount; ?>"
                                                    data-paid-amount="<?php echo $paidAmount; ?>"
                                                    data-due-amount="<?php echo $dueAmount; ?>"
                                                    data-amount="<?php echo $dueAmount; ?>"
                                                    data-month="<?php echo htmlspecialchars($monthValue); ?>"
                                                    data-year="<?php echo htmlspecialchars($fee['fee_year']); ?>"
                                                    data-start-date="<?php echo htmlspecialchars($fee['start_date']); ?>"
                                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                                    title="Add to payable list">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php endif; ?>
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
    <?php elseif ($student): ?>
    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle"></i> <strong>No fee structure assigned</strong> for this student yet.
    </div>
    <?php endif; ?>

                        </div>
                        <!-- End Collect Fees Tab -->

                        <!-- Payment History Tab -->
                        <div class="tab-pane fade" id="payment-history" role="tabpanel">
                            <?php if ($student): ?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0"><i class="bi bi-receipt-cutoff"></i> Payment History</h5>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                                            <i class="bi bi-printer"></i> Print History
                                        </button>
                                    </div>

                                    <?php if (count($paymentHistory) > 0): ?>
                                        <?php
                                        $totalPaid = 0;
                                        foreach ($paymentHistory as $receipt) {
                                            $totalPaid += $receipt['total_amount'];
                                        }
                                        ?>

                                        <div class="alert alert-success alert-permanent">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <strong>Total Receipts:</strong> <?php echo count($paymentHistory); ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Total Paid:</strong> <?php echo CURRENCY_SYMBOL . number_format($totalPaid, 2); ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Student:</strong> <?php echo htmlspecialchars($student['student_name']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="paymentHistoryTable">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Receipt No</th>
                                                        <th>Date</th>
                                                        <th>Fee Details</th>
                                                        <th>Pay Type</th>
                                                        <th>Amount</th>
                                                        <th>Collected By</th>
                                                        <th class="no-print">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($paymentHistory as $receipt): ?>
                                                        <?php
                                                        // Get fee details for this receipt
                                                        $receiptDetails = fetchAll("SELECT frd.*, fh.fee_head_name
                                                                                    FROM fee_receipt_details frd
                                                                                    JOIN fee_heads fh ON frd.fee_head_id = fh.fee_head_id
                                                                                    WHERE frd.receipt_id = ?
                                                                                    ORDER BY frd.detail_id", 'i', [$receipt['receipt_id']]);
                                                        ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                                                            <td><?php echo date('d M Y', strtotime($receipt['payment_date'])); ?></td>
                                                            <td>
                                                                <small>
                                                                    <?php foreach ($receiptDetails as $detail): ?>
                                                                        <div>
                                                                            • <?php echo htmlspecialchars($detail['fee_head_name']); ?>
                                                                            <?php if ($detail['fee_month']): ?>
                                                                                (<?php echo $detail['fee_month'] . ' ' . $detail['fee_year']; ?>)
                                                                            <?php endif; ?>
                                                                            - <?php echo CURRENCY_SYMBOL . number_format($detail['amount'], 2); ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $receipt['payment_mode'] == 'Cash' ? 'success' : ($receipt['payment_mode'] == 'UPI' ? 'info' : 'warning'); ?>">
                                                                    <?php echo htmlspecialchars($receipt['payment_mode']); ?>
                                                                </span>
                                                            </td>
                                                            <td><strong><?php echo CURRENCY_SYMBOL . number_format($receipt['total_amount'], 2); ?></strong></td>
                                                            <td><small><?php echo htmlspecialchars($receipt['collected_by_name'] ?? 'N/A'); ?></small></td>
                                                            <td class="no-print">
                                                                <a href="<?php echo APP_URL; ?>/modules/fees/view_receipt.php?id=<?php echo $receipt['receipt_id']; ?>"
                                                                   class="btn btn-sm btn-info" target="_blank" title="View Receipt">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                <a href="<?php echo APP_URL; ?>/modules/fees/pdf_receipt.php?id=<?php echo $receipt['receipt_id']; ?>"
                                                                   class="btn btn-sm btn-primary" target="_blank" title="Download PDF">
                                                                    <i class="bi bi-file-pdf"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-success">
                                                    <tr>
                                                        <th colspan="4">Total Paid</th>
                                                        <th colspan="3"><?php echo CURRENCY_SYMBOL . number_format($totalPaid, 2); ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info alert-permanent">
                                            <h5><i class="bi bi-info-circle"></i> No Payment History Found</h5>
                                            <p>No receipts found for this student.</p>
                                            <p class="mb-0">No receipts have been generated for this student yet. Create a receipt from the "Collect Fees" tab.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- End Payment History Tab -->
                    </div>
                    <!-- End Tab Content -->
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$student): ?>
    <div class="row mt-4">
        <div class="col-md-8 offset-md-2">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Quick Start Guide</h6>
                </div>
                <div class="card-body">
                    <h6>How to Collect Fees:</h6>
                    <ol>
                        <li><strong>Search for Student:</strong> Enter admission number or student name in the search box above</li>
                        <li><strong>Select Student:</strong> Click Search button or press Enter to find the student</li>
                        <li><strong>Review Pending Fees:</strong> All unpaid fees will appear in the "Pending Fee List" (right side)</li>
                        <li><strong>Add Fees to Collect:</strong> Click the green checkmark (✓) button to move fees to "Payable Fee List" (left side)</li>
                        <li><strong>Adjust Amounts:</strong> You can modify the payable amount if collecting partial payment</li>
                        <li><strong>Enter Payment Details:</strong> Select payment method (Cash/Bank/UPI/Cheque) and date</li>
                        <li><strong>Save Receipt:</strong> Click the green "Save" button at the top to generate receipt</li>
                    </ol>

                    <div class="alert alert-warning mt-3 mb-0">
                        <strong><i class="bi bi-exclamation-triangle"></i> Before You Start:</strong>
                        <ul class="mb-0">
                            <li>Make sure students have been added to the system</li>
                            <li>Ensure fee heads have been created</li>
                            <li>Verify that fees are assigned to students via Fee Structure</li>
                            <li class="mt-2">
                                <a href="../../test_fee_collection.php" target="_blank" class="btn btn-sm btn-info">
                                    <i class="bi bi-tools"></i> Run Diagnostic Test
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</form>

<?php
// Include footer
include '../../includes/footer.php';
?>

<script>
let payableIndex = 0;

// Debug: Check if jQuery is loaded
console.log('jQuery loaded:', typeof jQuery !== 'undefined');
console.log('Search button exists:', $('#searchBtn').length);
console.log('Search input exists:', $('#searchAdmNo').length);

// Search student
$('#searchBtn').click(function() {
    console.log('Search button clicked');

    const searchTerm = $('#searchAdmNo').val().trim();
    const classId = $('#searchClassId').val();
    console.log('Search term:', searchTerm);

    if (!searchTerm && !classId) {
        alert('Please enter admission number or student name');
        return;
    }

    $('#searchResults').html('<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Searching...</div>');

    if (!searchTerm && classId) {
        searchByName('', classId);
        return;
    }

    console.log('Sending AJAX request to: ../../api/search_student.php');

    $.ajax({
        url: '../../api/search_student.php',
        type: 'POST',
        data: { admission_no: searchTerm, search_term: searchTerm, class_id: classId },
        dataType: 'json',
        success: function(response) {
            console.log('API Response:', response);

            if (response.success && response.student) {
                console.log('Student found! ID:', response.student.student_id);
                $('#searchResults').html('<div class="alert alert-success">Student found! Loading...</div>');
                window.location.href = 'collect.php?student_id=' + response.student.student_id;
            } else {
                console.log('Student not found by admission number, trying name search');
                $('#searchResults').html('<div class="alert alert-warning">Not found by admission number, searching by name...</div>');
                // Try searching by name
                searchByName(searchTerm, classId);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            $('#searchResults').html('<div class="alert alert-danger">API Error: ' + error + '<br>Status: ' + status + '</div>');
            // Try searching by name
            searchByName(searchTerm, classId);
        }
    });
});

function searchByName(searchTerm, classId = '') {
    console.log('Searching by name:', searchTerm);

    $.ajax({
        url: '../../api/search_students_by_name.php',
        type: 'POST',
        data: { search: searchTerm, class_id: classId },
        dataType: 'json',
        success: function(response) {
            console.log('Name search response:', response);

            if (response.success && response.students && response.students.length > 0) {
                console.log('Found', response.students.length, 'students');
                let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr><th>Adm No</th><th>Name</th><th>Roll No</th><th>Class</th><th>Action</th></tr></thead><tbody>';
                response.students.forEach(function(student) {
                    html += '<tr>';
                    html += '<td>' + student.admission_no + '</td>';
                    html += '<td>' + student.student_name + '</td>';
                    html += '<td>' + (student.roll_no || 'N/A') + '</td>';
                    html += '<td>' + (student.class_name || 'N/A') + '-' + (student.section_name || 'N/A') + '</td>';
                    html += '<td><a href="collect.php?student_id=' + student.student_id + '" class="btn btn-sm btn-success"><i class="bi bi-check"></i> Select</a></td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                $('#searchResults').html(html);
            } else {
                console.warn('No students found in name search');
                $('#searchResults').html('<div class="alert alert-warning"><i class="bi bi-exclamation-circle"></i> No students found matching "' + searchTerm + '"!</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Name search error:', status, error);
            console.error('Response:', xhr.responseText);
            $('#searchResults').html('<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Error searching students: ' + error + '</div>');
        }
    });
}

// Clear search
$('#clearSearchBtn').click(function() {
    $('#searchAdmNo').val('');
    $('#searchClassId').val('');
    window.location.href = 'collect.php';
});

// Add fee to payable list
$(document).on('click', '.add-fee-btn', function() {
    const btn = $(this);
    const index = btn.data('index');
    const feeId = btn.data('fee-id');
    const feeName = btn.data('fee-name');
    const originalAmount = parseFloat(btn.data('original-amount')) || parseFloat(btn.data('amount')) || 0;
    const paidAmount = parseFloat(btn.data('paid-amount')) || 0;
    const dueAmount = parseFloat(btn.data('due-amount')) || parseFloat(btn.data('amount')) || 0;
    const month = btn.data('month');
    const year = btn.data('year');
    const startDate = btn.data('start-date') || '';
    const displayFeeType = btn.closest('tr').data('display-fee-type') || feeName;
    const status = btn.data('status') || (paidAmount > 0 ? 'Partial' : 'Unpaid');

    // Remove from pending list
    btn.closest('tr').remove();

    // Add to payable list
    const payableRow = `
        <tr class="payable-fee-row"
            data-payable-index="${payableIndex}"
            data-fee-id="${feeId}"
            data-fee-name="${feeName}"
            data-display-fee-type="${displayFeeType}"
            data-original-amount="${originalAmount}"
            data-paid-amount="${paidAmount}"
            data-due-amount="${dueAmount}"
            data-status="${status}"
            data-month="${month || ''}"
            data-year="${year}"
            data-start-date="${startDate}">
            <td>
                <strong>${displayFeeType}</strong>
                ${month ? '<br><small class="text-muted">' + month + ' ' + year + '</small>' : ''}
                <input type="hidden" name="pending_start_date[${payableIndex}]" value="${startDate}">
                <input type="hidden" name="fee_head_id[${payableIndex}]" value="${feeId}">
                <input type="hidden" name="fee_month[${payableIndex}]" value="${month}">
                <input type="hidden" name="fee_year[${payableIndex}]" value="${year}">
                <input type="hidden" name="selected_fees[]" value="${payableIndex}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm original-amount"
                       name="original_amount[${payableIndex}]" value="${originalAmount}" step="0.01" min="0"
                       data-payable-index="${payableIndex}" placeholder="Enter amount">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm discount-amount"
                       name="discount_amount[${payableIndex}]" value="0.00" step="0.01" min="0"
                       data-payable-index="${payableIndex}" placeholder="0.00">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm payable-amount"
                       name="payable_amount[${payableIndex}]" value="${dueAmount}" step="0.01" min="0"
                       data-payable-index="${payableIndex}" placeholder="Enter amount">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm discount-reason"
                       name="discount_reason[${payableIndex}]" placeholder="Optional reason" maxlength="255">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm fee-action-btn remove-fee-btn"
                        data-payable-index="${payableIndex}"
                        data-pending-index="${index}"
                        data-fee-id="${feeId}"
                        data-fee-name="${feeName}"
                        data-display-fee-type="${displayFeeType}"
                        data-original-amount="${originalAmount}"
                        data-paid-amount="${paidAmount}"
                        data-due-amount="${dueAmount}"
                        data-amount="${dueAmount}"
                        data-month="${month}"
                        data-year="${year}"
                        data-start-date="${startDate}"
                        data-status="${status}"
                        title="Remove from payable list">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `;

    // Remove "no data" row if exists
    $('#payableFeeList tr td[colspan="6"]').closest('tr').remove();

    $('#payableFeeList').append(payableRow);
    payableIndex++;

    calculateTotals();
    updateSaveButton();
    updateSelectedFeesDisplay();
});

// Remove fee from payable list
$(document).on('click', '.remove-fee-btn', function() {
    const btn = $(this);
    const payableIndex = btn.data('payable-index');
    const pendingIndex = btn.data('pending-index');
    const feeId = btn.data('fee-id');
    const feeName = btn.data('fee-name');
    const displayFeeType = btn.data('display-fee-type') || feeName;
    const originalAmount = parseFloat(btn.data('original-amount')) || parseFloat(btn.data('amount')) || 0;
    const paidAmount = parseFloat(btn.data('paid-amount')) || 0;
    const dueAmount = parseFloat(btn.data('due-amount')) || parseFloat(btn.data('amount')) || 0;
    const month = btn.data('month');
    const year = btn.data('year');
    const startDate = btn.closest('tr').find('input[name^="pending_start_date"]').val() || btn.data('start-date') || '';
    const status = btn.data('status') || (dueAmount <= 0 ? 'Paid' : (paidAmount > 0 ? 'Partial' : 'Unpaid'));

    // Remove from payable list
    btn.closest('tr').remove();

    // Add back to pending list
    const pendingRow = `
        <tr class="pending-fee-row"
            data-index="${pendingIndex}"
            data-fee-id="${feeId}"
            data-fee-name="${feeName}"
            data-display-fee-type="${displayFeeType}"
            data-original-amount="${originalAmount}"
            data-paid-amount="${paidAmount}"
            data-due-amount="${dueAmount}"
            data-status="${status}"
            data-month="${month || ''}"
            data-year="${year}"
            data-start-date="${startDate}">
            <td class="fee-month-cell">${month || '-'}</td>
            <td class="fee-type-cell">
                <strong>${displayFeeType}</strong>
                ${month ? '<br><small class="text-muted">' + month + ' ' + year + '</small>' : ''}
            </td>
            <td class="fee-amount-cell">${formatMoney(originalAmount)}</td>
            <td class="fee-paid-cell text-success">${formatMoney(paidAmount)}</td>
            <td class="fee-due-cell ${dueAmount > 0 ? 'text-danger fw-bold' : 'text-success'}">${formatMoney(dueAmount)}</td>
            <td class="fee-status-cell"><span class="badge bg-${status === 'Paid' ? 'success' : (status === 'Partial' ? 'warning' : 'secondary')}">${status}</span></td>
            <td class="fee-pay-cell">
                ${dueAmount > 0 ? `<button type="button" class="btn btn-success btn-sm fee-action-btn add-fee-btn"
                        data-index="${pendingIndex}"
                        data-fee-id="${feeId}"
                        data-fee-name="${displayFeeType}"
                        data-display-fee-type="${displayFeeType}"
                        data-original-amount="${originalAmount}"
                        data-paid-amount="${paidAmount}"
                        data-due-amount="${dueAmount}"
                        data-amount="${dueAmount}"
                        data-month="${month || ''}"
                        data-year="${year}"
                        data-start-date="${startDate}"
                        data-status="${status}"
                        title="Add to payable list">
                    <i class="bi bi-check-lg"></i>
                </button>` : '<span class="badge bg-success">Paid</span>'}
            </td>
        </tr>
    `;

    $('.table-fee-details tbody').append(pendingRow);

    // Check if payable list is empty
    if ($('#payableFeeList tr').length === 0) {
        $('#payableFeeList').html(`
            <tr>
                <td colspan="6" class="text-center text-muted">
                    <em>Select a fee from the table above to add it here</em>
                </td>
            </tr>
        `);
    }

    calculateTotals();
    updateSaveButton();
    updateSelectedFeesDisplay();
});

// Calculate amounts on input change
$(document).on('input', '.original-amount, .discount-amount, .payable-amount, #chargeAmount', function() {
    const row = $(this).closest('tr');
    const originalAmount = parseFloat(row.find('.original-amount').val()) || 0;

    // If original amount changed, recalculate payable (keeping discount same)
    if ($(this).hasClass('original-amount')) {
        const discount = parseFloat(row.find('.discount-amount').val()) || 0;
        const payableAmount = Math.max(0, originalAmount - discount);
        row.find('.payable-amount').val(payableAmount.toFixed(2));
    }

    // If discount changed, recalculate payable amount for that row
    if ($(this).hasClass('discount-amount')) {
        const discount = parseFloat($(this).val()) || 0;

        // Validate discount doesn't exceed amount
        if (discount > originalAmount) {
            alert('Discount cannot exceed the fee amount!');
            $(this).val(originalAmount.toFixed(2));
            return;
        }

        const payableAmount = originalAmount - discount;
        row.find('.payable-amount').val(payableAmount.toFixed(2));
    }

    // If payable amount changed manually, recalculate discount
    if ($(this).hasClass('payable-amount')) {
        const payable = parseFloat($(this).val()) || 0;

        // Validate payable doesn't exceed original amount
        if (payable > originalAmount) {
            alert('Payable amount cannot exceed the original fee amount!');
            $(this).val(originalAmount.toFixed(2));
            return;
        }

        // Auto-calculate discount based on payable amount
        const discount = originalAmount - payable;
        row.find('.discount-amount').val(discount.toFixed(2));
    }

    calculateTotals();
});

function calculateTotals() {
    let totalAmount = 0;
    let totalDiscount = 0;
    let totalPayable = 0;

    $('.payable-fee-row').each(function() {
        const amount = parseFloat($(this).find('.original-amount').val()) || 0;
        const discount = parseFloat($(this).find('.discount-amount').val()) || 0;
        const payable = parseFloat($(this).find('.payable-amount').val()) || 0;

        totalAmount += amount;
        totalDiscount += discount;
        totalPayable += payable;
    });

    const charge = parseFloat($('#chargeAmount').val()) || 0;
    const netAmount = totalPayable + charge;

    $('#discountTotal').text(totalDiscount.toFixed(2));
    $('#payableTotal').text(totalPayable.toFixed(2));
    $('#netAmount').text(netAmount.toFixed(2));

    // Only auto-update Amount field if user hasn't manually entered an amount
    // This allows user to override with custom amount (e.g., partial payment)
    const currentAmount = $('#totalAmountField').val();
    if (!currentAmount || currentAmount == '0' || currentAmount == '0.00') {
        $('#totalAmountField').val(netAmount.toFixed(2));
    }

    updateSelectedFeesDisplay();
}

function updateSaveButton() {
    const hasPayable = $('#payableFeeList tr').length > 0 && !$('#payableFeeList tr td[colspan="6"]').length;
    $('#saveBtn').prop('disabled', !hasPayable);
}

function clearForm() {
    if (confirm('Are you sure you want to clear the form and start over?')) {
        window.location.href = 'collect.php';
    }
}

// Auto-distribute amount across pending fees when amount is entered
$('#totalAmountField').on('blur', function() {
    const targetAmount = parseFloat($(this).val()) || 0;
    const hasPayableFees = $('#payableFeeList tr').length > 0 && !$('#payableFeeList tr td[colspan="6"]').length;

    if (targetAmount > 0 && !hasPayableFees) {
        // Auto-distribute fees to match the entered amount
        autoDistributeFees(targetAmount);
    } else if (targetAmount === 0 && hasPayableFees) {
        // Clear payable fees if amount is cleared
        if (confirm('Clear all fees from payable list?')) {
            clearPayableFees();
        }
    }
});

// Clear payable fees and restore to pending list
function clearPayableFees() {
    // Move all payable fees back to pending
    $('#payableFeeList tr.payable-fee-row').each(function() {
        const removeBtn = $(this).find('.remove-fee-btn');
        if (removeBtn.length) {
            removeBtn.click(); // Trigger remove button to restore to pending
        }
    });

    // Clear the amount field
    $('#totalAmountField').val('');

    // Reset totals
    calculateTotals();
    updateSaveButton();
}

// Auto-distribute fees to match target amount
function autoDistributeFees(targetAmount) {
    let remainingAmount = targetAmount;
    const pendingRows = $('.pending-fee-row');

    if (pendingRows.length === 0) {
        alert('No pending fees available to distribute amount!');
        return;
    }

    $('#payableFeeList').empty();

    pendingRows.each(function(index) {
        if (remainingAmount <= 0) {
            return false;
        }

        const row = $(this);
        const addBtn = row.find('.add-fee-btn');
        const feeId = addBtn.data('fee-id');
        const feeName = addBtn.data('fee-name');
        const displayFeeType = row.data('display-fee-type') || feeName;
        const originalAmount = parseFloat(addBtn.data('amount')) || 0;
        const currentPaid = parseFloat(row.data('paid-amount')) || parseFloat(addBtn.data('paid-amount')) || 0;
        const currentDue = parseFloat(row.data('due-amount')) || parseFloat(addBtn.data('due-amount')) || originalAmount;
        const month = addBtn.data('month');
        const year = addBtn.data('year');
        const startDate = addBtn.data('start-date') || '';
        const pendingIndex = row.data('index');
        const status = row.data('status') || (currentPaid > 0 ? 'Partial' : 'Unpaid');

        if (originalAmount <= 0) {
            return;
        }

        const amountToCollect = Math.min(remainingAmount, originalAmount);
        const newPaid = currentPaid + amountToCollect;
        const newDue = Math.max(0, currentDue - amountToCollect);
        const isPartial = amountToCollect < originalAmount;

        const payableRow = `
            <tr class="payable-fee-row"
                data-payable-index="${payableIndex}"
                data-fee-id="${feeId}"
                data-fee-name="${feeName}"
                data-display-fee-type="${displayFeeType}"
                data-original-amount="${currentPaid + currentDue}"
                data-paid-amount="${currentPaid}"
                data-due-amount="${currentDue}"
                data-status="${status}"
                data-month="${month || ''}"
                data-year="${year}"
                data-start-date="${startDate}">
                <td>
                    <strong>${displayFeeType}</strong>
                    ${month ? '<br><small class="text-muted">' + month + ' ' + year + '</small>' : ''}
                    ${isPartial ? '<br><small class="badge bg-warning text-dark">Partial: ' + formatMoney(amountToCollect) + '</small>' : ''}
                    <input type="hidden" name="pending_start_date[${payableIndex}]" value="${startDate}">
                    <input type="hidden" name="fee_head_id[${payableIndex}]" value="${feeId}">
                    <input type="hidden" name="fee_month[${payableIndex}]" value="${month || ''}">
                    <input type="hidden" name="fee_year[${payableIndex}]" value="${year}">
                    <input type="hidden" name="selected_fees[]" value="${payableIndex}">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm original-amount"
                           name="original_amount[${payableIndex}]" value="${(currentPaid + currentDue).toFixed(2)}" step="0.01" min="0"
                           data-payable-index="${payableIndex}" placeholder="Enter amount">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm discount-amount"
                           name="discount_amount[${payableIndex}]" value="0.00" step="0.01" min="0"
                           data-payable-index="${payableIndex}" placeholder="0.00">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm payable-amount"
                           name="payable_amount[${payableIndex}]" value="${amountToCollect.toFixed(2)}" step="0.01" min="0"
                           data-payable-index="${payableIndex}" placeholder="Enter amount">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm discount-reason"
                           name="discount_reason[${payableIndex}]" placeholder="Optional reason" maxlength="255">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm fee-action-btn remove-fee-btn"
                            data-payable-index="${payableIndex}"
                            data-pending-index="${pendingIndex}"
                            data-fee-id="${feeId}"
                            data-fee-name="${feeName}"
                            data-display-fee-type="${displayFeeType}"
                            data-original-amount="${currentPaid + currentDue}"
                            data-paid-amount="${currentPaid}"
                            data-due-amount="${currentDue}"
                            data-amount="${currentDue}"
                            data-month="${month || ''}"
                            data-year="${year}"
                            data-start-date="${startDate}"
                            data-status="${status}"
                            title="Remove from payable list">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#payableFeeList').append(payableRow);

        if (isPartial) {
            row.attr('data-paid-amount', newPaid.toFixed(2));
            row.attr('data-due-amount', newDue.toFixed(2));
            row.attr('data-status', 'Partial');
            row.find('.fee-paid-cell').text(formatMoney(newPaid));
            row.find('.fee-due-cell')
                .text(formatMoney(newDue))
                .removeClass('text-success text-danger fw-bold')
                .addClass('text-danger fw-bold');
            row.find('.fee-status-cell').html('<span class="badge bg-warning">Partial</span>');
            addBtn.data('amount', newDue).attr('data-amount', newDue);
            addBtn.data('paid-amount', newPaid).attr('data-paid-amount', newPaid);
            addBtn.data('due-amount', newDue).attr('data-due-amount', newDue);
        } else {
            row.remove();
        }

        remainingAmount -= amountToCollect;
        payableIndex++;
    });

    calculateTotals();
    updateSaveButton();
    updateSelectedFeesDisplay();

    if (remainingAmount > 0) {
        alert(`Distributed ${(targetAmount - remainingAmount).toFixed(2)} across available pending fees.\nRemaining amount ${remainingAmount.toFixed(2)} could not be distributed (no more pending fees).`);
    } else {
        $('#totalAmountField').val(targetAmount.toFixed(2));
    }
}

// Save button
$('#saveBtn').click(function() {
    if ($('#payableFeeList tr td[colspan="6"]').length) {
        alert('Please add at least one fee to the payable list');
        return;
    }

    const studentId = $('#student_id').val();
    if (!studentId || studentId == '0') {
        alert('Please select a student first');
        return;
    }

    // Add the save_receipt input to the form
    const saveInput = $('<input>').attr({
        type: 'hidden',
        name: 'save_receipt',
        value: '1'
    });
    $('#feeReceiptForm').append(saveInput);

    if (confirm('Save this fee receipt and generate receipt?')) {
        $('#feeReceiptForm').submit();
    } else {
        saveInput.remove();
    }
});

// Debug: Log form data before submission
$('#feeReceiptForm').on('submit', function(e) {
    console.log('Form submitting...');
    console.log('Student ID:', $('#student_id').val());
    console.log('Payable fees count:', $('.payable-fee-row').length);
    console.log('Total amount:', $('#totalAmountField').val());
});

// Enter key on search
$('#searchAdmNo').keypress(function(e) {
    if (e.which == 13) {
        e.preventDefault();
        $('#searchBtn').click();
    }
});

<?php if ($student && count($reminders) > 0): ?>
// Auto-show reminder modal when student has reminders
$(document).ready(function() {
    var reminderModal = new bootstrap.Modal(document.getElementById('reminderModal'));
    reminderModal.show();
});
<?php endif; ?>
</script>

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
function formatMoney(value) {
    const num = Number(value) || 0;
    return '₹' + num.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function updateSelectedFeesDisplay() {
    const labels = [];

    $('#payableFeeList tr.payable-fee-row').each(function() {
        const row = $(this);
        const name = row.data('display-fee-type') || row.data('fee-name') || '';
        const month = row.data('month') || '';
        const year = row.data('year') || '';
        labels.push(month ? `${name} (${month} ${year})` : name);
    });

    $('#selectedFeesDisplay').val(labels.length ? labels.join(', ') : 'No fee selected yet');
}

function openReminderModal() {
    var reminderModal = new bootstrap.Modal(document.getElementById('reminderModal'));
    reminderModal.show();
}

// Add new reminder
document.getElementById('addReminderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_reminder');

    fetch('../../ajax/reminder_handler.php', {
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

    fetch('../../ajax/reminder_handler.php', {
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

    fetch('../../ajax/reminder_handler.php', {
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

    fetch('../../ajax/reminder_handler.php', {
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

<style>
/* Print Styles for Payment History */
@media print {
    /* Hide all non-essential elements when printing */
    .no-print,
    .btn,
    .nav-tabs,
    header,
    .sidebar,
    nav,
    footer,
    .alert-dismissible .btn-close {
        display: none !important;
    }

    /* Show only the payment history tab content */
    #payment-history {
        display: block !important;
        opacity: 1 !important;
        position: static !important;
    }

    #collect-fees {
        display: none !important;
    }

    /* Improve table printing */
    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    thead {
        display: table-header-group;
    }

    tfoot {
        display: table-footer-group;
    }

    /* Adjust print layout */
    body {
        margin: 0;
        padding: 15px;
    }

    .card {
        border: none !important;
        box-shadow: none !important;
    }

    .table-bordered {
        border: 1px solid #000 !important;
    }

    .table-bordered th,
    .table-bordered td {
        border: 1px solid #000 !important;
    }

    /* Add print header */
    #payment-history::before {
        content: "<?php echo htmlspecialchars($schoolName ?? ''); ?> - Payment History";
        display: block;
        font-size: 20px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #000;
    }
}

/* Improve tab styling */
.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
}

.tab-content {
    background-color: #f8f9fa;
}

/* Payment History Table Improvements */
#paymentHistoryTable {
    font-size: 14px;
}

#paymentHistoryTable thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

#paymentHistoryTable small {
    line-height: 1.6;
}
</style>
<?php endif; ?>
