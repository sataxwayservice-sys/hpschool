<?php
/**
 * Due Fees - Students with Pending Fees
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();
requirePermission('fees', 'view');

$pageTitle = 'Due Fees';
$currentUser = getCurrentUser();

// Get filter parameters
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$studentSearch = trim((string)($_GET['student_search'] ?? ''));
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$dueStudents = getStudentsWithDueFees($classId, $sectionId);

if ($studentId > 0 || $studentSearch !== '') {
    $dueStudents = array_values(array_filter($dueStudents, function ($student) use ($studentId, $studentSearch) {
        if ($studentId > 0) {
            return intval($student['student_id'] ?? 0) === $studentId;
        }

        $haystack = trim(implode(' ', array_filter([
            (string)($student['student_name'] ?? ''),
            (string)($student['admission_no'] ?? ''),
            (string)($student['father_name'] ?? ''),
            (string)($student['class_name'] ?? ''),
            (string)($student['section_name'] ?? ''),
            (string)($student['contact_no'] ?? ''),
        ])));

        if ($haystack === '') {
            return false;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $studentSearch) !== false;
        }

        return stripos($haystack, $studentSearch) !== false;
    }));
}

// Calculate totals
$totalDue = array_sum(array_column($dueStudents, 'due_amount'));
$totalFee = array_sum(array_column($dueStudents, 'total_fee_assigned'));
$totalPaid = array_sum(array_column($dueStudents, 'total_paid'));

// Get classes and sections for filter
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
$sections = fetchAll("SELECT * FROM sections WHERE is_active = 1 ORDER BY section_name");

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-exclamation-triangle-fill text-warning"></i> Due Fees
            </h2>
            <div>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
                            <label class="form-label">Student Search</label>
                            <input type="text"
                                   class="form-control"
                                   name="student_search"
                                   id="due_student_search"
                                   value="<?php echo htmlspecialchars($studentSearch); ?>"
                                   placeholder="Type 2 letters to search student name"
                                   autocomplete="off"
                                   data-student-autocomplete="true"
                                   data-student-autocomplete-fill="student_name"
                                   data-student-autocomplete-min-length="2"
                                   data-student-autocomplete-class="#due_class_id"
                                   data-student-autocomplete-submit="#dueFeesFilterBtn"
                                   data-student-autocomplete-id-target="#due_student_id">
                            <input type="hidden" name="student_id" id="due_student_id" value="<?php echo $studentId > 0 ? intval($studentId) : ''; ?>">
                            <small class="text-muted">Search by name or admission number, then select a student.</small>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <label class="form-label">Class</label>
                            <select class="form-select" name="class_id" id="due_class_id">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo ($classId == $class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <label class="form-label">Section</label>
                            <select class="form-select" name="section_id" id="due_section_id">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>"
                                        <?php echo ($sectionId == $section['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" id="dueFeesFilterBtn">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body text-center">
                <h6>Students with Dues</h6>
                <h3><?php echo count($dueStudents); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body text-center">
                <h6>Total Fee Assigned</h6>
                <h3><?php echo formatCurrency($totalFee); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h6>Total Paid</h6>
                <h3><?php echo formatCurrency($totalPaid); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-danger">
            <div class="card-body text-center">
                <h6>Total Due</h6>
                <h3><?php echo formatCurrency($totalDue); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Due Fees Table -->
<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Students with Due Fees</h5>
            </div>
            <div class="card-body">
                <?php if (count($dueStudents) > 0): ?>
                <div class="table-responsive">
                    <table id="dueFeesTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Father Name</th>
                                <th>Class</th>
                                <th>Contact</th>
                                <th>Total Fee</th>
                                <th>Paid</th>
                                <th>Due Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sno = 1;
                            foreach ($dueStudents as $student):
                            ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/modules/students/view.php?id=<?php echo $student['student_id']; ?>"
                                       target="_blank">
                                        <?php echo htmlspecialchars($student['student_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section_name']); ?></td>
                                <td>
                                    <a href="tel:<?php echo $student['contact_no']; ?>">
                                        <?php echo htmlspecialchars($student['contact_no']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatCurrency($student['total_fee_assigned']); ?></td>
                                <td class="text-success"><?php echo formatCurrency($student['total_paid']); ?></td>
                                <td>
                                    <strong class="text-danger">
                                        <?php echo formatCurrency($student['due_amount']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if (hasPermission('fees', 'add')): ?>
                                        <a href="collect.php?student_id=<?php echo $student['student_id']; ?>"
                                           class="btn btn-sm btn-success"
                                           title="Collect Fee">
                                            <i class="bi bi-cash-coin"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="receipts.php?search=<?php echo urlencode($student['admission_no']); ?>"
                                           class="btn btn-sm btn-primary"
                                           title="View Receipts">
                                            <i class="bi bi-receipt"></i>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/modules/students/view.php?id=<?php echo $student['student_id']; ?>"
                                           class="btn btn-sm btn-info"
                                           title="View Details"
                                           target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (hasPermission('sms', 'send')): ?>
                                        <button class="btn btn-sm btn-warning send-sms-btn"
                                                data-phone="<?php echo htmlspecialchars($student['contact_no']); ?>"
                                                data-name="<?php echo htmlspecialchars($student['student_name']); ?>"
                                                data-amount="<?php echo $student['due_amount']; ?>"
                                                title="Send SMS Reminder">
                                            <i class="bi bi-chat-dots"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-warning">
                            <tr>
                                <th colspan="6" class="text-end">Total:</th>
                                <th><?php echo formatCurrency($totalFee); ?></th>
                                <th class="text-success"><?php echo formatCurrency($totalPaid); ?></th>
                                <th class="text-danger"><?php echo formatCurrency($totalDue); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <?php if ($studentSearch !== '' || $studentId > 0): ?>
                        <i class="bi bi-search"></i> No due-fee record matched the selected student search.
                    <?php else: ?>
                        <i class="bi bi-check-circle"></i> <strong>Great!</strong> No students have due fees.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
// Initialize DataTables
$('#dueFeesTable').DataTable({
    order: [[8, 'desc']], // Sort by due amount descending
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excel',
            text: '<i class=\"bi bi-file-earmark-excel\"></i> Export to Excel',
            className: 'btn btn-success btn-sm',
            title: 'Due Fees Report',
            exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
            }
        },
        {
            extend: 'print',
            text: '<i class=\"bi bi-printer\"></i> Print',
            className: 'btn btn-primary btn-sm',
            title: 'Due Fees Report'
        }
    ]
});

// Send SMS reminder functionality
$('.send-sms-btn').on('click', function() {
    const phone = $(this).data('phone');
    const name = $(this).data('name');
    const amount = $(this).data('amount');

    const message = 'Dear Parent, Your ward ' + name + ' has pending fee payment of Rs. ' + amount + '. Please pay at the earliest. Thank you!';

    if (confirm('Send SMS reminder to ' + phone + '?\\n\\nMessage: ' + message)) {
        // Redirect to SMS module with pre-filled data
        window.open('" . APP_URL . "/modules/sms/index.php?phone=' + phone + '&message=' + encodeURIComponent(message), '_blank');
    }
});
";

// Include footer
include '../../includes/footer.php';
?>
