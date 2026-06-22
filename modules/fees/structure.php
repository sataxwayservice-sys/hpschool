<?php
/**
 * Fee Structure Management
 * Assign fee structure to individual students
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'add');

$pageTitle = 'Fee Structure';
$currentUser = getCurrentUser();
$currentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
$error = '';
$success = '';

if (function_exists('ensureFeeModuleSchema')) {
    ensureFeeModuleSchema();
}

// Get student ID if provided
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$student = null;
if ($studentId > 0) {
    $query = "SELECT s.*, c.class_name, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.student_id = ? AND s.status = 'Active'";
    $params = [$studentId];
    $types = 'i';

    if ($currentSchoolId > 0) {
        $query .= " AND COALESCE(s.school_id, 0) = ?";
        $params[] = $currentSchoolId;
        $types .= 'i';
    }

    $student = fetchOne($query, $types, $params);
}

// Get all fee heads
$feeHeadsQuery = "SELECT * FROM fee_heads WHERE is_active = 1";
$feeHeadsParams = [];
$feeHeadsTypes = '';
if ($currentSchoolId > 0) {
    $feeHeadsQuery .= " AND COALESCE(school_id, 0) = ?";
    $feeHeadsParams[] = $currentSchoolId;
    $feeHeadsTypes .= 'i';
}
$feeHeadsQuery .= " ORDER BY fee_head_id";
$feeHeads = empty($feeHeadsTypes) ? fetchAll($feeHeadsQuery) : fetchAll($feeHeadsQuery, $feeHeadsTypes, $feeHeadsParams);

// Debug: Check if fee heads exist at all
if (empty($feeHeads)) {
    $allFeeHeadsQuery = "SELECT * FROM fee_heads";
    $allFeeHeadsParams = [];
    $allFeeHeadsTypes = '';
    if ($currentSchoolId > 0) {
        $allFeeHeadsQuery .= " WHERE COALESCE(school_id, 0) = ?";
        $allFeeHeadsParams[] = $currentSchoolId;
        $allFeeHeadsTypes .= 'i';
    }
    $allFeeHeads = empty($allFeeHeadsTypes)
        ? fetchAll($allFeeHeadsQuery)
        : fetchAll($allFeeHeadsQuery, $allFeeHeadsTypes, $allFeeHeadsParams);
    if (empty($allFeeHeads)) {
        $error = $currentSchoolId > 0
            ? 'No fee heads found for this school! Please add fee heads first from Settings → Fee Heads.'
            : 'No fee heads found in the system! Please add fee heads first from Settings → Fee Heads.';
    } else {
        $inactiveCount = count($allFeeHeads);
        $error = $currentSchoolId > 0
            ? "All $inactiveCount fee heads for this school are inactive! Please activate at least one fee head from Settings → Fee Heads."
            : "All $inactiveCount fee heads are inactive! Please activate at least one fee head from Settings → Fee Heads.";
    }
}

// Get existing fee structure for student
$existingFees = [];
$hasDuplicates = false;
$duplicateFees = [];

if ($studentId > 0) {
    $existingFeesQuery = "SELECT * FROM fee_structure WHERE student_id = ? AND is_active = 1";
    $existingFeesParams = [$studentId];
    $existingFeesTypes = 'i';
    if ($currentSchoolId > 0) {
        $existingFeesQuery .= " AND COALESCE(school_id, 0) = ?";
        $existingFeesParams[] = $currentSchoolId;
        $existingFeesTypes .= 'i';
    }
    $existingFees = fetchAll($existingFeesQuery, $existingFeesTypes, $existingFeesParams);

    // Check for duplicates and convert to associative array by fee_head_id
    $feeHeadCounts = [];
    $tempFees = [];

    foreach ($existingFees as $fee) {
        $feeHeadId = $fee['fee_head_id'];

        // Count occurrences
        if (!isset($feeHeadCounts[$feeHeadId])) {
            $feeHeadCounts[$feeHeadId] = 0;
        }
        $feeHeadCounts[$feeHeadId]++;

        // Keep only the first occurrence in tempFees
        if (!isset($tempFees[$feeHeadId])) {
            $tempFees[$feeHeadId] = $fee;
        }
    }

    // Check for duplicates
    foreach ($feeHeadCounts as $feeHeadId => $count) {
        if ($count > 1) {
            $hasDuplicates = true;
            $feeHeadName = '';
            foreach ($feeHeads as $fh) {
                if ($fh['fee_head_id'] == $feeHeadId) {
                    $feeHeadName = $fh['fee_head_name'];
                    break;
                }
            }
            $duplicateFees[] = "$feeHeadName (assigned $count times)";
        }
    }

    $existingFees = $tempFees;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_structure'])) {
    $studentId = intval($_POST['student_id']);
    $effectiveFrom = sanitize($_POST['effective_from']);
    $feeAmounts = $_POST['fee_amount'] ?? [];

    if ($studentId == 0) {
        $error = 'Please select a student';
    } else {
        beginTransaction();
        try {
            // Deactivate old fee structure
            $deactivateQuery = "UPDATE fee_structure SET is_active = 0 WHERE student_id = ?";
            $deactivateParams = [$studentId];
            $deactivateTypes = 'i';
            if ($currentSchoolId > 0) {
                $deactivateQuery .= " AND COALESCE(school_id, 0) = ?";
                $deactivateParams[] = $currentSchoolId;
                $deactivateTypes .= 'i';
            }
            executeQuery($deactivateQuery, $deactivateTypes, $deactivateParams);

            // Insert new fee structure
            $insertedCount = 0;
            foreach ($feeAmounts as $feeHeadId => $amount) {
                if (!empty($amount) && $amount > 0) {
                    $query = "INSERT INTO fee_structure
                             (student_id, fee_head_id, amount, effective_from, is_active, created_by";
                    $types = 'iidsi';
                    $params = [
                        $studentId,
                        $feeHeadId,
                        $amount,
                        $effectiveFrom,
                        $currentUser['user_id']
                    ];

                    if ($currentSchoolId > 0) {
                        $query .= ", school_id";
                        $types .= 'i';
                        $params[] = $currentSchoolId;
                    }

                    $query .= ") VALUES (?, ?, ?, ?, 1, ?";
                    if ($currentSchoolId > 0) {
                        $query .= ", ?";
                    }
                    $query .= ")";

                    executeQuery($query, $types, $params);
                    $insertedCount++;
                }
            }

            if ($insertedCount > 0) {
                // Log activity
                logActivity($currentUser['user_id'], 'Set Fee Structure', 'Fees',
                    "Set fee structure for student ID: $studentId");

                commitTransaction();
                $success = "Fee structure saved successfully! $insertedCount fee heads assigned.";

                // Reload existing fees
                $existingFees = fetchAll(
                    $existingFeesQuery,
                    $existingFeesTypes,
                    $existingFeesParams
                );
                $tempFees = [];
                foreach ($existingFees as $fee) {
                    $tempFees[$fee['fee_head_id']] = $fee;
                }
                $existingFees = $tempFees;
            } else {
                rollbackTransaction();
                $error = 'No fee amounts entered!';
            }
        } catch (Exception $e) {
            rollbackTransaction();
            $error = 'Failed to save fee structure: ' . $e->getMessage();
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-clipboard-data"></i> Fee Structure Management
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible alert-permanent fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible alert-permanent fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card dashboard-card">
            <div class="card-body">

                <!-- Student Search/Selection -->
                <h5><i class="bi bi-search"></i> Select Student</h5>
                <form method="GET" action="" class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search"
                                   placeholder="Search by admission number or name"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="admission_no"
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (isset($_GET['search'])): ?>
                    <?php
                    $searchTerm = sanitize($_GET['search']);
                    $searchQuery = "SELECT s.*, c.class_name, sec.section_name
                                   FROM students s
                                   JOIN classes c ON s.class_id = c.class_id
                                   JOIN sections sec ON s.section_id = sec.section_id
                                   WHERE (s.admission_no LIKE ? OR s.student_name LIKE ?)
                                   AND s.status = 'Active'
                                   ";
                    $searchParams = ['%' . $searchTerm . '%', '%' . $searchTerm . '%'];
                    $searchTypes = 'ss';
                    if ($currentSchoolId > 0) {
                        $searchQuery .= " AND COALESCE(s.school_id, 0) = ?";
                        $searchParams[] = $currentSchoolId;
                        $searchTypes .= 'i';
                    }
                    $searchQuery .= " LIMIT 10";
                    $students = fetchAll($searchQuery, $searchTypes, $searchParams);
                    ?>

                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Admission No</th>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                        <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($s['class_name'] . ' ' . $s['section_name']); ?></td>
                                        <td>
                                            <a href="?student_id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-cash-stack"></i> Set Fee Structure
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No students found!</div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($student): ?>
                <hr>

                <!-- Student Info -->
                <div class="alert alert-info alert-permanent">
                    <h5>Selected Student:</h5>
                    <strong><?php echo htmlspecialchars($student['student_name']); ?></strong><br>
                    Admission No: <?php echo htmlspecialchars($student['admission_no']); ?><br>
                    Class: <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section_name']); ?>
                    <hr>
                    <a href="<?php echo APP_URL; ?>/manage_assigned_fees.php?student_id=<?php echo $studentId; ?>"
                       class="btn btn-sm btn-warning" target="_blank">
                        <i class="bi bi-pencil-square"></i> View/Edit Current Assignments
                    </a>
                </div>

                <?php if ($hasDuplicates): ?>
                <div class="alert alert-danger alert-permanent">
                    <h5><i class="bi bi-exclamation-triangle"></i> Warning: Duplicate Fee Assignments Detected!</h5>
                    <p>This student has the same fee head assigned multiple times, which causes <strong>repeating months</strong> in pending fees:</p>
                    <ul>
                        <?php foreach ($duplicateFees as $dupFee): ?>
                            <li><?php echo htmlspecialchars($dupFee); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mb-0">
                        <strong>Solution:</strong>
                        <a href="<?php echo APP_URL; ?>/manage_assigned_fees.php?student_id=<?php echo $studentId; ?>"
                           class="btn btn-sm btn-danger" target="_blank">
                            <i class="bi bi-trash"></i> Delete Duplicate Assignments
                        </a>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Fee Structure Form -->
                <form method="POST" action="">
                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="effective_from" class="form-label required">Effective From</label>
                            <input type="date" class="form-control" id="effective_from" name="effective_from"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="alert alert-info alert-permanent mt-3">
                        <h6><i class="bi bi-info-circle"></i> How Monthly Fees Work:</h6>
                        <ul class="mb-0">
                            <li><strong>Monthly Fees:</strong> Enter the amount <strong>ONCE</strong> - System will automatically generate <strong>all 11 months</strong> (January to November) in pending fees</li>
                            <li><strong>One-time/Annual Fees:</strong> Will appear only once in pending fees</li>
                            <li><strong>Tip:</strong> Don't assign the same fee head multiple times - it will create duplicates!</li>
                        </ul>
                    </div>

                    <h5 class="mt-4">Fee Heads</h5>

                    <?php if (empty($feeHeads)): ?>
                        <div class="alert alert-danger alert-permanent">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> No Active Fee Heads Found!</h5>
                            <p>You cannot assign fees because there are no active fee heads in the system.</p>
                            <p class="mb-0">
                                <strong>Solution:</strong>
                                <a href="<?php echo APP_URL; ?>/modules/settings/fee_heads.php" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="bi bi-plus-circle"></i> Go to Settings → Fee Heads
                                </a>
                                to add and activate fee heads.
                            </p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="40%">Fee Head</th>
                                    <th>Type</th>
                                    <th>Amount (<?php echo CURRENCY_SYMBOL; ?>)</th>
                                    <th>Current Amount</th>
                                    <th>What Will Appear</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feeHeads as $feeHead): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($feeHead['fee_head_name']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $feeHead['fee_type'] == 'Monthly' ? 'primary' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($feeHead['fee_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" step="0.01"
                                               name="fee_amount[<?php echo $feeHead['fee_head_id']; ?>]"
                                               value="<?php echo isset($existingFees[$feeHead['fee_head_id']]) ? $existingFees[$feeHead['fee_head_id']]['amount'] : ''; ?>"
                                               placeholder="Enter amount"
                                               id="fee_<?php echo $feeHead['fee_head_id']; ?>">
                                    </td>
                                    <td>
                                        <?php if (isset($existingFees[$feeHead['fee_head_id']])): ?>
                                            <span class="text-success">
                                                <?php echo formatCurrency($existingFees[$feeHead['fee_head_id']]['amount']); ?>
                                            </span>
                                            <br><small class="text-muted">Already assigned</small>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($feeHead['fee_type'] == 'Monthly'): ?>
                                            <span class="badge bg-info">11 months</span>
                                            <small class="text-muted d-block">Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep, Oct, Nov</small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">1 entry</span>
                                            <small class="text-muted d-block">Appears once in pending fees</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div id="preview-section" class="mt-4" style="display:none;">
                        <div class="alert alert-warning">
                            <h5><i class="bi bi-eye"></i> Preview: What Will Appear in Pending Fees</h5>
                            <div id="preview-content"></div>
                        </div>
                    </div>

                    <?php if (!empty($feeHeads)): ?>
                    <div class="mt-4">
                        <button type="submit" name="save_structure" class="btn btn-success btn-lg">
                            <i class="bi bi-save"></i> Save Fee Structure
                        </button>
                        <button type="button" class="btn btn-info btn-lg" onclick="showPreview()">
                            <i class="bi bi-eye"></i> Preview Pending Fees
                        </button>
                        <a href="?" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
// Fee heads data
const feeHeads = <?php echo json_encode($feeHeads); ?>;
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November'];

function showPreview() {
    let previewHtml = '<table class="table table-sm table-bordered"><thead><tr><th>Fee Head</th><th>Month</th><th>Amount</th></tr></thead><tbody>';
    let totalEntries = 0;
    let totalAmount = 0;

    feeHeads.forEach(feeHead => {
        const amountInput = document.getElementById('fee_' + feeHead.fee_head_id);
        const amount = parseFloat(amountInput.value) || 0;

        if (amount > 0) {
            if (feeHead.fee_type === 'Monthly') {
                // Monthly fee - show all months
                months.forEach(month => {
                    previewHtml += `<tr>
                        <td>${feeHead.fee_head_name}</td>
                        <td><span class="badge bg-primary">${month} 2025</span></td>
                        <td>₹${amount.toFixed(2)}</td>
                    </tr>`;
                    totalEntries++;
                    totalAmount += amount;
                });
            } else {
                // One-time fee
                previewHtml += `<tr>
                    <td>${feeHead.fee_head_name}</td>
                    <td><span class="badge bg-secondary">One-time</span></td>
                    <td>₹${amount.toFixed(2)}</td>
                </tr>`;
                totalEntries++;
                totalAmount += amount;
            }
        }
    });

    if (totalEntries === 0) {
        previewHtml = '<p class="text-danger">No fees entered! Please enter amounts for at least one fee head.</p>';
    } else {
        previewHtml += `</tbody><tfoot><tr class="table-info"><th colspan="2">Total: ${totalEntries} entries</th><th>₹${totalAmount.toFixed(2)}</th></tr></tfoot></table>`;
        previewHtml += `<p class="mb-0"><strong>Note:</strong> These ${totalEntries} entries will appear in the "Pending Fee List" for this student.</p>`;
    }

    document.getElementById('preview-content').innerHTML = previewHtml;
    document.getElementById('preview-section').style.display = 'block';

    // Scroll to preview
    document.getElementById('preview-section').scrollIntoView({behavior: 'smooth'});
}
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
