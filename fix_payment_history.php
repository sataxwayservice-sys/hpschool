<?php
/**
 * Diagnostic & Fix Script for Payment History Issue
 * Identifies and fixes student_id mismatches between receipts and URL
 */

require_once 'config/config.php';
requireLogin();

$pageTitle = 'Payment History Fix';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-tools"></i> Payment History Diagnostic & Fix</h2>

            <?php
            // Debug: Check if tables exist
            $tablesExist = [
                'students' => false,
                'fee_receipts' => false,
                'fee_receipt_details' => false
            ];

            $tables = fetchAll("SHOW TABLES");
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                if (isset($tablesExist[$tableName])) {
                    $tablesExist[$tableName] = true;
                }
            }

            // Step 1: Get ALL students in database
            $allStudents = [];
            if ($tablesExist['students']) {
                $allStudents = fetchAll("SELECT student_id, student_name, admission_no, class_id, section_id, status
                                        FROM students
                                        ORDER BY student_id");
            }

            // Step 2: Get all receipts
            $allReceipts = [];
            if ($tablesExist['fee_receipts']) {
                $allReceipts = fetchAll("SELECT receipt_id, receipt_no, student_id, payment_date, total_amount
                                        FROM fee_receipts
                                        ORDER BY payment_date DESC");
            }

            // Step 3: Try to find the specific student
            $student = null;
            if (!empty($allStudents)) {
                // First try by admission number
                foreach ($allStudents as $s) {
                    if ($s['admission_no'] === 'STU000001') {
                        $student = $s;
                        break;
                    }
                }
                // If not found, try by name
                if (!$student) {
                    foreach ($allStudents as $s) {
                        if (stripos($s['student_name'], 'Shajahan') !== false) {
                            $student = $s;
                            break;
                        }
                    }
                }
                // If still not found, just take the first student
                if (!$student) {
                    $student = $allStudents[0];
                }
            }

            // Step 4: Count receipts for this student
            $studentReceipts = [];
            $otherReceipts = [];

            if ($student) {
                foreach ($allReceipts as $receipt) {
                    if ($receipt['student_id'] == $student['student_id']) {
                        $studentReceipts[] = $receipt;
                    } else {
                        $otherReceipts[] = $receipt;
                    }
                }
            }
            ?>

            <!-- DATABASE STATUS -->
            <div class="alert alert-info alert-permanent">
                <h6><i class="bi bi-database"></i> Database Table Status:</h6>
                <div class="row">
                    <div class="col-md-4">
                        <?php if ($tablesExist['students']): ?>
                            <span class="badge bg-success">✅ students table exists</span>
                            <small class="d-block"><?php echo count($allStudents); ?> records found</small>
                        <?php else: ?>
                            <span class="badge bg-danger">❌ students table NOT found</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <?php if ($tablesExist['fee_receipts']): ?>
                            <span class="badge bg-success">✅ fee_receipts table exists</span>
                            <small class="d-block"><?php echo count($allReceipts); ?> records found</small>
                        <?php else: ?>
                            <span class="badge bg-danger">❌ fee_receipts table NOT found</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <?php if ($tablesExist['fee_receipt_details']): ?>
                            <span class="badge bg-success">✅ fee_receipt_details table exists</span>
                        <?php else: ?>
                            <span class="badge bg-danger">❌ fee_receipt_details table NOT found</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ALL STUDENTS IN DATABASE -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">👥 All Students in Database (<?php echo count($allStudents); ?> total)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($allStudents)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>No students found in database!</strong>
                            <p>The students table is empty. You need to add students first.</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student Name</th>
                                    <th>Admission No</th>
                                    <th>Class ID</th>
                                    <th>Section ID</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allStudents as $s): ?>
                                <tr class="<?php echo $student && $s['student_id'] == $student['student_id'] ? 'table-success' : ''; ?>">
                                    <td><strong><?php echo $s['student_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                    <td><?php echo $s['class_id']; ?></td>
                                    <td><?php echo $s['section_id']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $s['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($s['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Student Information -->
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">📋 Selected Student (Auto-detected)</h5>
                </div>
                <div class="card-body">
                    <?php if ($student): ?>
                        <table class="table table-bordered">
                            <tr>
                                <th width="200">Student ID (Database):</th>
                                <td><span class="badge bg-success fs-5"><?php echo $student['student_id']; ?></span></td>
                            </tr>
                            <tr>
                                <th>Student Name:</th>
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Admission No:</th>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                            </tr>
                            <tr>
                                <th>Class ID:</th>
                                <td><?php echo $student['class_id']; ?></td>
                            </tr>
                            <tr>
                                <th>Section ID:</th>
                                <td><?php echo $student['section_id']; ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><span class="badge bg-<?php echo $student['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($student['status']); ?></span></td>
                            </tr>
                        </table>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This is the student we'll check receipts for.
                            <?php if (count($allStudents) > 1): ?>
                                If this is the wrong student, check the table above.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>No students found in database!</strong>
                            <p class="mb-0">Please add a student first before generating receipts.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Receipt Analysis -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">🧾 Receipt Analysis</h5>
                </div>
                <div class="card-body">
                    <?php if (!$student): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i> Cannot analyze receipts - no student found in database.
                        </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="alert alert-primary">
                                <h6>Total Receipts:</h6>
                                <h3><?php echo count($allReceipts); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-success">
                                <h6>Receipts for Student ID <?php echo $student['student_id']; ?>:</h6>
                                <h3><?php echo count($studentReceipts); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-warning">
                                <h6>Receipts with Other IDs:</h6>
                                <h3><?php echo count($otherReceipts); ?></h3>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($student && !empty($otherReceipts)): ?>
                        <div class="alert alert-danger alert-permanent mt-3">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> PROBLEM FOUND!</h5>
                            <p>You have <?php echo count($otherReceipts); ?> receipts that are NOT linked to student ID <?php echo $student['student_id']; ?>.</p>
                            <p>These receipts have incorrect student_id values and won't appear in the payment history.</p>
                        </div>

                        <h6 class="mt-4">Receipts with Wrong Student ID:</h6>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Receipt ID</th>
                                    <th>Receipt No</th>
                                    <th>Current Student ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($otherReceipts as $receipt): ?>
                                <tr class="table-danger">
                                    <td><?php echo $receipt['receipt_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?php echo $receipt['student_id']; ?> ❌
                                        </span>
                                        Should be: <span class="badge bg-success"><?php echo $student['student_id']; ?> ✅</span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($receipt['payment_date'])); ?></td>
                                    <td><?php echo CURRENCY_SYMBOL . number_format($receipt['total_amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- FIX BUTTON -->
                        <form method="POST" id="fixForm" class="mt-4">
                            <input type="hidden" name="fix_action" value="fix_student_ids">
                            <input type="hidden" name="correct_student_id" value="<?php echo $student['student_id']; ?>">

                            <div class="alert alert-warning alert-permanent">
                                <h5><i class="bi bi-wrench"></i> Automatic Fix Available</h5>
                                <p>Click the button below to automatically update all receipts to use the correct student ID: <strong><?php echo $student['student_id']; ?></strong></p>
                                <p class="mb-0"><small>This will update <?php echo count($otherReceipts); ?> receipt(s) in the database.</small></p>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Fix All Receipts Now
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success alert-permanent mt-3">
                            <h5><i class="bi bi-check-circle-fill"></i> All Receipts are Correct!</h5>
                            <p class="mb-0">All <?php echo count($studentReceipts); ?> receipts are properly linked to student ID <?php echo $student['student_id']; ?>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // Handle the fix action
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_action']) && $_POST['fix_action'] === 'fix_student_ids') {
                $correctStudentId = intval($_POST['correct_student_id']);

                // Get wrong student IDs
                $wrongIds = array_unique(array_column($otherReceipts, 'student_id'));

                if (!empty($wrongIds)) {
                    $placeholders = implode(',', array_fill(0, count($wrongIds), '?'));
                    $types = str_repeat('i', count($wrongIds) + 1);
                    $params = array_merge([$correctStudentId], $wrongIds);

                    $updated = executeQuery(
                        "UPDATE fee_receipts SET student_id = ? WHERE student_id IN ($placeholders)",
                        $types,
                        $params
                    );

                    if ($updated) {
                        logActivity($_SESSION['user_id'], 'Fixed Student IDs', 'fees', "Updated receipts to student_id: $correctStudentId");
                        echo '<div class="alert alert-success alert-permanent mt-4">';
                        echo '<h5><i class="bi bi-check-circle-fill"></i> Success!</h5>';
                        echo '<p>All receipts have been updated to use student ID: <strong>' . $correctStudentId . '</strong></p>';
                        echo '<a href="modules/fees/collect_complete.php?student_id=' . $correctStudentId . '" class="btn btn-primary">';
                        echo '<i class="bi bi-arrow-right"></i> Go to Payment History</a>';
                        echo '</div>';

                        // Refresh the page after 2 seconds
                        echo '<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>';
                    } else {
                        echo '<div class="alert alert-danger mt-4">';
                        echo '<i class="bi bi-exclamation-triangle-fill"></i> Error: Failed to update receipts. Please try again.';
                        echo '</div>';
                    }
                }
            }
            ?>

            <!-- All Receipts Overview -->
            <?php if (!empty($allReceipts)): ?>
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">📊 All Receipts in Database</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Student ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allReceipts as $receipt): ?>
                            <tr class="<?php echo $student && $receipt['student_id'] == $student['student_id'] ? 'table-success' : 'table-danger'; ?>">
                                <td><?php echo htmlspecialchars($receipt['receipt_no']); ?></td>
                                <td>
                                    <strong><?php echo $receipt['student_id']; ?></strong>
                                    <?php if ($student && $receipt['student_id'] == $student['student_id']): ?>
                                        <span class="badge bg-success">✅</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">❌</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($receipt['payment_date'])); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($receipt['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($student && $receipt['student_id'] == $student['student_id']): ?>
                                        <span class="text-success">Correct</span>
                                    <?php else: ?>
                                        <span class="text-danger">Wrong ID</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="<?php echo APP_URL; ?>/modules/fees/collect_complete.php?student_id=<?php echo $student ? $student['student_id'] : ''; ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Fee Collection
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
