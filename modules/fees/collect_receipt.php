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
$error = '';
$success = '';

// Get student if ID provided
$student = null;
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : (isset($_POST['student_id']) ? intval($_POST['student_id']) : 0);

// Get academic batches
$batches = fetchAll("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL ORDER BY batch DESC");

// Get receipt books. The receipt book feature is optional, so keep this page
// usable even on databases that have not run the receipt-book migration yet.
$conn = getDbConnection();
$conn->query("CREATE TABLE IF NOT EXISTS receipt_books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    book_name VARCHAR(100) NOT NULL,
    prefix VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$receiptBookColumnsResult = $conn->query("SHOW COLUMNS FROM receipt_books");
$receiptBookColumns = [];
while ($column = $receiptBookColumnsResult->fetch_assoc()) {
    $receiptBookColumns[] = $column['Field'];
}
if (!in_array('prefix', $receiptBookColumns)) {
    $conn->query("ALTER TABLE receipt_books ADD COLUMN prefix VARCHAR(20) DEFAULT NULL AFTER book_name");
}
if (!in_array('is_active', $receiptBookColumns)) {
    $conn->query("ALTER TABLE receipt_books ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER prefix");
}

$receiptBooks = fetchAll("SELECT * FROM receipt_books WHERE is_active = 1 ORDER BY book_name");
if (count($receiptBooks) == 0) {
    // Create default receipt book if none exists
    executeQuery("INSERT INTO receipt_books (book_name, prefix, is_active) VALUES (?, ?, ?)", 'ssi', ['DEFAULT', 'FEE', 1]);
    $receiptBooks = fetchAll("SELECT * FROM receipt_books WHERE is_active = 1 ORDER BY book_name");
}

if ($studentId > 0) {
    $query = "SELECT s.*, c.class_name, sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.student_id = ?";
    $student = fetchOne($query, 'i', [$studentId]);
}

// Get student's pending fees (from fee structure)
$pendingFees = [];
$payableFees = [];
if ($studentId > 0) {
    // Get fee structure
    $feeStructure = fetchAll(
        "SELECT fs.*, fh.fee_head_name, fh.fee_type
         FROM fee_structure fs
         JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
         WHERE fs.student_id = ? AND fs.is_active = 1
         ORDER BY fh.display_order",
        'i',
        [$studentId]
    );

    // Get already paid fees
    $paidFees = fetchAll(
        "SELECT frd.fee_head_id, frd.fee_month, frd.fee_year, SUM(frd.amount) as paid_amount
         FROM fee_receipt_details frd
         JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
         WHERE fr.student_id = ? AND fr.is_cancelled = 0
         GROUP BY frd.fee_head_id, frd.fee_month, frd.fee_year",
        'i',
        [$studentId]
    );

    // Build pending fees list
    $months = getMonths();
    $currentYear = date('Y');
    $currentMonth = date('F');
    $currentMonthIndex = array_search($currentMonth, $months);

    foreach ($feeStructure as $fee) {
        if ($fee['fee_type'] == 'Monthly') {
            // For monthly fees, show pending months
            foreach ($months as $monthIndex => $month) {
                // Skip future months
                if ($monthIndex > $currentMonthIndex) continue;

                // Check if already paid
                $isPaid = false;
                foreach ($paidFees as $paid) {
                    if ($paid['fee_head_id'] == $fee['fee_head_id'] &&
                        $paid['fee_month'] == $month &&
                        $paid['fee_year'] == $currentYear) {
                        $isPaid = true;
                        break;
                    }
                }

                if (!$isPaid) {
                    $pendingFees[] = [
                        'fee_head_id' => $fee['fee_head_id'],
                        'fee_head_name' => $fee['fee_head_name'],
                        'amount' => $fee['amount'],
                        'month' => $month,
                        'year' => $currentYear,
                        'start_date' => date('01/m/Y', strtotime("$currentYear-" . ($monthIndex + 1) . "-01"))
                    ];
                }
            }
        } else {
            // For one-time fees
            $isPaid = false;
            foreach ($paidFees as $paid) {
                if ($paid['fee_head_id'] == $fee['fee_head_id']) {
                    $isPaid = true;
                    break;
                }
            }

            if (!$isPaid) {
                $pendingFees[] = [
                    'fee_head_id' => $fee['fee_head_id'],
                    'fee_head_name' => $fee['fee_head_name'],
                    'amount' => $fee['amount'],
                    'month' => null,
                    'year' => $currentYear,
                    'start_date' => date('01/m/Y')
                ];
            }
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_receipt'])) {
    $studentId = intval($_POST['student_id']);
    $receiptBookId = intval($_POST['receipt_book_id']);
    $paymentMode = sanitize($_POST['pay_type']);
    $paymentDate = sanitize($_POST['receipt_date']);
    $chequeNo = sanitize($_POST['cheque_no'] ?? '');
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $chequeDate = sanitize($_POST['cheque_date'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    $amount = floatval($_POST['amount']);
    $charge = floatval($_POST['charge'] ?? 0);

    // Get selected payable fees
    $selectedFees = $_POST['selected_fees'] ?? [];
    $payableAmounts = $_POST['payable_amount'] ?? [];

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

            $columnsResult = $conn->query("SHOW COLUMNS FROM fee_receipts");
            $existingColumns = [];
            while ($row = $columnsResult->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }

            $columns = ['receipt_no', 'student_id', 'total_amount', 'amount_paid', 'payment_mode', 'transaction_id', 'payment_date', 'collected_by', 'remarks'];
            $values = [$receiptNo, $studentId, $totalAmount, $totalAmount, $paymentMode, $chequeNo, $paymentDate, $currentUser['user_id'], $remarks];
            $types = 'siddsssis';

            if (in_array('receipt_book_id', $existingColumns) && $receiptBookId > 0) {
                $columns[] = 'receipt_book_id';
                $values[] = $receiptBookId;
                $types .= 'i';
            }

            if (in_array('charge_amount', $existingColumns)) {
                $columns[] = 'charge_amount';
                $values[] = $charge;
                $types .= 'd';
            }

            if (in_array('bank_name', $existingColumns) && $bankName !== '') {
                $columns[] = 'bank_name';
                $values[] = $bankName;
                $types .= 's';
            }

            if (in_array('cheque_date', $existingColumns) && $chequeDate !== '') {
                $columns[] = 'cheque_date';
                $values[] = $chequeDate;
                $types .= 's';
            }

            $columnsList = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $insertReceipt = "INSERT INTO fee_receipts ($columnsList) VALUES ($placeholders)";

            $result = executeQuery($insertReceipt, $types, $values);

            if ($result === false) {
                throw new Exception('Unable to insert receipt. Please check the fee_receipts table schema.');
            }

            $receiptId = $result['insert_id'];

            // Insert fee details
            foreach ($selectedFees as $index) {
                if (isset($_POST['fee_head_id'][$index])) {
                    $feeHeadId = intval($_POST['fee_head_id'][$index]);
                    $feeAmount = floatval($payableAmounts[$index]);
                    $feeMonth = sanitize($_POST['fee_month'][$index] ?? null);
                    $feeYear = sanitize($_POST['fee_year'][$index] ?? null);

                    $insertDetail = "INSERT INTO fee_receipt_details
                                    (receipt_id, fee_head_id, fee_month, fee_year, amount)
                                    VALUES (?, ?, ?, ?, ?)";
                    executeQuery($insertDetail, 'iissd', [
                        $receiptId, $feeHeadId, $feeMonth, $feeYear, $feeAmount
                    ]);
                }
            }

            // Log activity
            logActivity($currentUser['user_id'], 'Collect Fee', 'fees',
                "Fee collected: Receipt $receiptNo, Amount: " . formatCurrency($totalAmount));

            commitTransaction();

            // Redirect to receipt page
            $_SESSION['success_message'] = "Fee collected successfully! Receipt No: $receiptNo";
            header("Location: receipt.php?id=$receiptId");
            exit();

        } catch (Exception $e) {
            rollbackTransaction();
            $error = 'Failed to save receipt: ' . $e->getMessage();
        }
    }
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
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='collect_receipt.php'">
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

<form method="POST" action="" id="feeReceiptForm">
    <input type="hidden" name="student_id" id="student_id" value="<?php echo $studentId; ?>">

    <div class="row">
        <!-- Left Section -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-person-badge"></i> Assign To</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Organization <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" value="<?php echo SCHOOL_NAME ?? 'School'; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="batch" name="batch">
                                <option value="">-- Select Batch --</option>
                                <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo htmlspecialchars($batch['batch']); ?>">
                                        <?php echo htmlspecialchars($batch['batch']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Id No <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="searchAdmNo"
                                       placeholder="Admission Number"
                                       autocomplete="off"
                                       data-student-autocomplete="true"
                                       data-student-autocomplete-fill="admission_no"
                                       data-student-autocomplete-submit="#searchBtn">
                                <button type="button" class="btn btn-outline-secondary" id="searchBtn">
                                    <i class="bi bi-search"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Student Status</label>
                            <input type="text" class="form-control form-control-sm" id="studentStatus" readonly value="<?php echo $student ? htmlspecialchars($student['status']) : ''; ?>">
                        </div>
                    </div>

                    <?php if ($student): ?>
                    <div class="student-info-box">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <strong>Name:</strong> <?php echo htmlspecialchars($student['student_name']); ?>
                            </div>
                            <div class="col-md-12">
                                <strong>Class/Division/Roll No:</strong>
                                <?php echo htmlspecialchars(($student['class_name'] ?? '') . '-' . ($student['section_name'] ?? '') . '-' . ($student['roll_no'] ?? 'N/A')); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Receipt Book</label>
                            <select class="form-select form-select-sm" name="receipt_book_id" required>
                                <option value="">- ALL -</option>
                                <?php foreach ($receiptBooks as $book): ?>
                                    <option value="<?php echo $book['book_id']; ?>">
                                        <?php echo htmlspecialchars($book['book_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Receipt No</label>
                            <input type="text" class="form-control form-control-sm" readonly placeholder="Auto-generated">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cheque No / Reference No</label>
                            <input type="text" class="form-control form-control-sm" name="cheque_no">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control form-control-sm" name="bank_name">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-control form-control-sm" name="amount" id="totalAmountField" step="0.01" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Charge</label>
                            <input type="number" class="form-control form-control-sm" name="charge" id="chargeAmount" value="0" step="0.01">
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
                                <option value="Cash">Cash</option>
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
                        <div class="col-md-6">
                            <!-- Empty for spacing -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea class="form-control form-control-sm" name="remarks" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($student && count($pendingFees) > 0): ?>
    <div class="row">
        <!-- Payable Fee List -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Payable Fee List</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered table-sm mb-0 table-payable">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Fee</th>
                                <th width="25%">Pending Amount</th>
                                <th width="25%">Payable Amount</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody id="payableFeeList">
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    <small>Select fees from pending list to add here</small>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2"><strong>Total</strong></td>
                                <td><strong id="payableTotal">0.00</strong></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Net Amount</strong></td>
                                <td colspan="2"><strong id="netAmount">0.00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pending Fee List -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning">
                    <h6 class="mb-0">Pending Fee List</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered table-sm mb-0 table-pending">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Fee</th>
                                <th width="30%">Pending Amount</th>
                                <th width="20%">Start Date</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingFees as $index => $fee): ?>
                            <tr class="pending-fee-row" data-index="<?php echo $index; ?>">
                                <td>
                                    <?php echo htmlspecialchars($fee['fee_head_name']); ?>
                                    <?php if ($fee['month']): ?>
                                        <br><small class="text-muted"><?php echo $fee['month'] . ' ' . $fee['year']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($fee['amount'], 2); ?></td>
                                <td><?php echo $fee['start_date']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-success btn-sm fee-action-btn add-fee-btn"
                                            data-index="<?php echo $index; ?>"
                                            data-fee-id="<?php echo $fee['fee_head_id']; ?>"
                                            data-fee-name="<?php echo htmlspecialchars($fee['fee_head_name']); ?>"
                                            data-amount="<?php echo $fee['amount']; ?>"
                                            data-month="<?php echo $fee['month'] ?? ''; ?>"
                                            data-year="<?php echo $fee['year']; ?>">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($student): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No pending fees for this student.
    </div>
    <?php endif; ?>

    <?php if (!$student): ?>
    <!-- Search Results -->
    <div id="searchResults"></div>
    <?php endif; ?>
</form>

<script>
window.addEventListener('load', function() {
let payableIndex = 0;

// Search student
$('#searchBtn').click(function() {
    const admNo = $('#searchAdmNo').val();
    if (!admNo) {
        alert('Please enter admission number');
        return;
    }

    $.ajax({
        url: '../../api/search_student.php',
        type: 'POST',
        data: { admission_no: admNo },
        success: function(response) {
            if (response.success && response.student) {
                window.location.href = 'collect_receipt.php?student_id=' + response.student.student_id;
            } else {
                alert('Student not found!');
            }
        },
        error: function() {
            alert('Error searching student');
        }
    });
});

// Clear search
$('#clearSearchBtn').click(function() {
    window.location.href = 'collect_receipt.php';
});

// Add fee to payable list
$(document).on('click', '.add-fee-btn', function() {
    const btn = $(this);
    const index = btn.data('index');
    const feeId = btn.data('fee-id');
    const feeName = btn.data('fee-name');
    const amount = parseFloat(btn.data('amount'));
    const month = btn.data('month');
    const year = btn.data('year');

    // Remove from pending list
    btn.closest('tr').remove();

    // Add to payable list
    const payableRow = `
        <tr class="payable-fee-row" data-payable-index="${payableIndex}">
            <td>
                ${feeName}
                ${month ? '<br><small class="text-muted">' + month + ' ' + year + '</small>' : ''}
                <input type="hidden" name="fee_head_id[${payableIndex}]" value="${feeId}">
                <input type="hidden" name="fee_month[${payableIndex}]" value="${month}">
                <input type="hidden" name="fee_year[${payableIndex}]" value="${year}">
                <input type="hidden" name="selected_fees[]" value="${payableIndex}">
            </td>
            <td>${amount.toFixed(2)}</td>
            <td>
                <input type="number" class="form-control form-control-sm payable-amount"
                       name="payable_amount[${payableIndex}]" value="${amount}" step="0.01" min="0">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm fee-action-btn remove-fee-btn"
                        data-payable-index="${payableIndex}"
                        data-pending-index="${index}"
                        data-fee-id="${feeId}"
                        data-fee-name="${feeName}"
                        data-amount="${amount}"
                        data-month="${month}"
                        data-year="${year}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `;

    // Remove "no data" row if exists
    $('#payableFeeList tr td[colspan="4"]').closest('tr').remove();

    $('#payableFeeList').append(payableRow);
    payableIndex++;

    calculateTotals();
    updateSaveButton();
});

// Remove fee from payable list
$(document).on('click', '.remove-fee-btn', function() {
    const btn = $(this);
    const payableIndex = btn.data('payable-index');
    const pendingIndex = btn.data('pending-index');
    const feeId = btn.data('fee-id');
    const feeName = btn.data('fee-name');
    const amount = parseFloat(btn.data('amount'));
    const month = btn.data('month');
    const year = btn.data('year');

    // Remove from payable list
    btn.closest('tr').remove();

    // Add back to pending list
    const pendingRow = `
        <tr class="pending-fee-row" data-index="${pendingIndex}">
            <td>
                ${feeName}
                ${month ? '<br><small class="text-muted">' + month + ' ' + year + '</small>' : ''}
            </td>
            <td>${amount.toFixed(2)}</td>
            <td>01/${(new Date()).getMonth() + 1}/${year}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm fee-action-btn add-fee-btn"
                        data-index="${pendingIndex}"
                        data-fee-id="${feeId}"
                        data-fee-name="${feeName}"
                        data-amount="${amount}"
                        data-month="${month}"
                        data-year="${year}">
                    <i class="bi bi-check-lg"></i>
                </button>
            </td>
        </tr>
    `;

    $('.table-pending tbody').append(pendingRow);

    // Check if payable list is empty
    if ($('#payableFeeList tr').length === 0) {
        $('#payableFeeList').html(`
            <tr>
                <td colspan="4" class="text-center text-muted">
                    <small>Select fees from pending list to add here</small>
                </td>
            </tr>
        `);
    }

    calculateTotals();
    updateSaveButton();
});

// Calculate amounts on input change
$(document).on('input', '.payable-amount, #chargeAmount', function() {
    calculateTotals();
});

function calculateTotals() {
    let total = 0;
    $('.payable-amount').each(function() {
        total += parseFloat($(this).val()) || 0;
    });

    const charge = parseFloat($('#chargeAmount').val()) || 0;
    const netAmount = total + charge;

    $('#payableTotal').text(total.toFixed(2));
    $('#netAmount').text(netAmount.toFixed(2));
    $('#totalAmountField').val(total.toFixed(2));
}

function updateSaveButton() {
    const hasPayable = $('#payableFeeList tr').length > 0 && !$('#payableFeeList tr td[colspan="4"]').length;
    $('#saveBtn').prop('disabled', !hasPayable);
}

function clearForm() {
    if (confirm('Are you sure you want to clear the form?')) {
        window.location.href = 'collect_receipt.php';
    }
}

// Save button
$('#saveBtn').click(function() {
    if ($('#payableFeeList tr td[colspan="4"]').length) {
        alert('Please add at least one fee to the payable list');
        return;
    }
    $('#feeReceiptForm').submit();
});

// Enter key on search
$('#searchAdmNo').keypress(function(e) {
    if (e.which == 13) {
        e.preventDefault();
        $('#searchBtn').click();
    }
});
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
