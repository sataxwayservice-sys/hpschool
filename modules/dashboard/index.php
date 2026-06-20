<?php
/**
 * Dashboard - Main Admin Dashboard
 */

// Include configuration (handles session start)
require_once '../../config/config.php';

// Require login
requireLogin();

$pageTitle = 'Dashboard';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

if (($currentUser['role'] ?? '') === 'admin' && function_exists('schoolRegistrationSeedDefaultAdminPermissions')) {
    $permissionCount = fetchOne(
        "SELECT COUNT(*) as total FROM user_permissions WHERE user_id = ?",
        'i',
        [intval($currentUser['user_id'] ?? 0)]
    );

    if (intval($permissionCount['total'] ?? 0) === 0) {
        schoolRegistrationSeedDefaultAdminPermissions(intval($currentUser['user_id'] ?? 0));
    }
}

// Get statistics
$totalStudents = 0;
$activeStudents = 0;
$totalFeeCollected = 0;
$outstandingFees = 0;
$todayCollection = 0;

// Total students
$query = "SELECT COUNT(*) as total FROM students WHERE status = 'Active'";
$result = $currentSchoolId > 0
    ? fetchOne($query . " AND school_id = ?", 'i', [$currentSchoolId])
    : fetchOne($query);
$activeStudents = $result['total'] ?? 0;

// Total students (including inactive)
$query = "SELECT COUNT(*) as total FROM students";
$result = $currentSchoolId > 0
    ? fetchOne($query . " WHERE school_id = ?", 'i', [$currentSchoolId])
    : fetchOne($query);
$totalStudents = $result['total'] ?? 0;

// Today's collection
$query = "SELECT COALESCE(SUM(fr.amount_paid), 0) as total
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          WHERE DATE(fr.payment_date) = CURDATE() AND fr.is_cancelled = 0";
$result = $currentSchoolId > 0
    ? fetchOne($query . " AND s.school_id = ?", 'i', [$currentSchoolId])
    : fetchOne($query);
$todayCollection = $result['total'] ?? 0;

// Total fee collected (this month)
$query = "SELECT COALESCE(SUM(fr.amount_paid), 0) as total
          FROM fee_receipts fr
          JOIN students s ON fr.student_id = s.student_id
          WHERE MONTH(fr.payment_date) = MONTH(CURDATE())
          AND YEAR(fr.payment_date) = YEAR(CURDATE())
          AND fr.is_cancelled = 0";
$result = $currentSchoolId > 0
    ? fetchOne($query . " AND s.school_id = ?", 'i', [$currentSchoolId])
    : fetchOne($query);
$totalFeeCollected = $result['total'] ?? 0;

// Outstanding fees (simplified calculation)
$query = "SELECT COUNT(DISTINCT student_id) as total FROM students WHERE status = 'Active'";
$result = $currentSchoolId > 0
    ? fetchOne($query . " AND school_id = ?", 'i', [$currentSchoolId])
    : fetchOne($query);
$studentsWithDues = $result['total'] ?? 0;

// Recent transactions
$recentTransactionsQuery = "
    SELECT fr.*, s.student_name, s.admission_no, c.class_name
    FROM fee_receipts fr
    JOIN students s ON fr.student_id = s.student_id
    JOIN classes c ON s.class_id = c.class_id
    WHERE fr.is_cancelled = 0";
if ($currentSchoolId > 0) {
    $recentTransactionsQuery .= " AND s.school_id = ?";
    $recentTransactions = fetchAll($recentTransactionsQuery . " ORDER BY fr.created_at DESC LIMIT 10", 'i', [$currentSchoolId]);
} else {
    $recentTransactions = fetchAll($recentTransactionsQuery . " ORDER BY fr.created_at DESC LIMIT 10");
}

$pendingSchoolRequests = [];
if ($currentUser['role'] === 'super_admin' && function_exists('schoolRegistrationGetRequests')) {
    $pendingSchoolRequests = schoolRegistrationGetRequests('pending', 5);
}

// Due students (students with no payments this month)
$dueStudentsQuery = "
    SELECT s.student_id, s.admission_no, s.student_name, s.contact_no, c.class_name, sec.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.class_id
    JOIN sections sec ON s.section_id = sec.section_id
    LEFT JOIN fee_receipts fr ON s.student_id = fr.student_id
        AND MONTH(fr.payment_date) = MONTH(CURDATE())
        AND YEAR(fr.payment_date) = YEAR(CURDATE())
    WHERE s.status = 'Active' AND fr.receipt_id IS NULL";
if ($currentSchoolId > 0) {
    $dueStudents = fetchAll($dueStudentsQuery . " AND s.school_id = ? LIMIT 10", 'i', [$currentSchoolId]);
} else {
    $dueStudents = fetchAll($dueStudentsQuery . " LIMIT 10");
}

$adminPortalTiles = [];
if (($currentUser['role'] ?? '') === 'admin') {
}

$superAdminControlGroups = [
    [
        'title' => 'System Setup',
        'description' => 'Core school branding, security, and structure tools.',
        'items' => [
            [
                'title' => 'User Management',
                'description' => 'Create and manage staff, admin, and system users.',
                'icon' => 'bi-people',
                'variant' => 'success',
                'href' => APP_URL . '/modules/settings/users.php'
            ],
            [
                'title' => 'Permissions',
                'description' => 'Control module access for each role and school.',
                'icon' => 'bi-shield-lock',
                'variant' => 'warning',
                'href' => APP_URL . '/modules/settings/manage_permissions.php'
            ],
            [
                'title' => 'School Role Control',
                'description' => 'Choose which staff roles are available for each school.',
                'icon' => 'bi-person-gear',
                'variant' => 'info',
                'href' => APP_URL . '/modules/settings/school_role_control.php'
            ],
            [
                'title' => 'Recycle Bin',
                'description' => 'Review deleted records before permanent cleanup.',
                'icon' => 'bi-trash3',
                'variant' => 'danger',
                'href' => APP_URL . '/modules/settings/recycle_bin.php'
            ],
        ],
    ],
    [
        'title' => 'Academic & Student',
        'description' => 'Student structure, admissions, and portal access.',
        'items' => [
            [
                'title' => 'Classes',
                'description' => 'Create and maintain class groups.',
                'icon' => 'bi-diagram-3',
                'variant' => 'primary',
                'href' => APP_URL . '/modules/settings/classes.php'
            ],
            [
                'title' => 'Sections',
                'description' => 'Organize sections for each class.',
                'icon' => 'bi-grid-3x3-gap',
                'variant' => 'success',
                'href' => APP_URL . '/modules/settings/sections.php'
            ],
            [
                'title' => 'Student Applications',
                'description' => 'Review pending applications and edit submissions.',
                'icon' => 'bi-person-badge',
                'variant' => 'warning',
                'href' => APP_URL . '/modules/settings/student_portal.php#applications'
            ],
        ],
    ],
    [
        'title' => 'Finance & Reports',
        'description' => 'Fee tools, reports, and result records.',
        'items' => [
            [
                'title' => 'Fee Heads',
                'description' => 'Configure admission, tuition, transport, and other fees.',
                'icon' => 'bi-cash-stack',
                'variant' => 'success',
                'href' => APP_URL . '/modules/settings/fee_heads.php'
            ],
            [
                'title' => 'Receipt Books',
                'description' => 'Manage receipt books and numbering setup.',
                'icon' => 'bi-book',
                'variant' => 'dark',
                'href' => APP_URL . '/modules/settings/receipt_books.php'
            ],
            [
                'title' => 'Fees Collection',
                'description' => 'Open the fee collection workspace.',
                'icon' => 'bi-cash-coin',
                'variant' => 'primary',
                'href' => APP_URL . '/modules/fees/collect.php'
            ],
            [
                'title' => 'Due Fees',
                'description' => 'Check pending fee reports and follow-ups.',
                'icon' => 'bi-exclamation-triangle',
                'variant' => 'danger',
                'href' => APP_URL . '/modules/fees/due.php'
            ],
            [
                'title' => 'Reports Hub',
                'description' => 'Open student, fee, marks, and system reports.',
                'icon' => 'bi-graph-up-arrow',
                'variant' => 'info',
                'href' => APP_URL . '/modules/reports/'
            ],
            [
                'title' => 'Marks & Exams',
                'description' => 'Enter marks and manage exam records.',
                'icon' => 'bi-journal-check',
                'variant' => 'warning',
                'href' => APP_URL . '/modules/marks/index.php'
            ],
        ],
    ],
    [
        'title' => 'Documents & Communication',
        'description' => 'Generated documents, notices, and message tools.',
        'items' => [
            [
                'title' => 'Admit Cards',
                'description' => 'Generate student-wise and class-wise admit cards.',
                'icon' => 'bi-card-heading',
                'variant' => 'primary',
                'href' => APP_URL . '/modules/reports/admit_cards.php'
            ],
            [
                'title' => 'Certificates',
                'description' => 'Create transfer and character certificates.',
                'icon' => 'bi-award',
                'variant' => 'success',
                'href' => APP_URL . '/modules/reports/student_documents.php'
            ],
            [
                'title' => 'SMS Center',
                'description' => 'Send reminders and manage SMS templates.',
                'icon' => 'bi-chat-dots',
                'variant' => 'info',
                'href' => APP_URL . '/modules/sms/index.php'
            ],
            [
                'title' => 'School Requests',
                'description' => 'Approve or reject new school registrations.',
                'icon' => 'bi-building-check',
                'variant' => 'warning',
                'href' => APP_URL . '/modules/settings/school_requests.php'
            ],
        ],
    ],
];

// Include header
include '../../includes/header.php';
?>

<!-- Dashboard Content -->
<div class="row">
    <div class="col-12">
        <h2 class="mb-4">
            <i class="bi bi-speedometer2"></i> Dashboard
        </h2>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <p class="text-muted mb-0">
                Welcome back, <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong>!
                <span class="badge bg-primary"><?php echo ucfirst($currentUser['role']); ?></span>
            </p>
            <?php if ($currentUser['role'] === 'super_admin'): ?>
                <a href="#super-admin-control-panel" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-sliders"></i> Super Admin Portal
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mt-4">
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Total Students</h6>
                        <h2 class="mb-0"><?php echo $totalStudents; ?></h2>
                        <small class="text-white-50"><?php echo $activeStudents; ?> Active</small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-people-fill text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Today's Collection</h6>
                        <h2 class="mb-0"><?php echo formatCurrency($todayCollection); ?></h2>
                        <small class="text-white-50">As of <?php echo date('d M Y'); ?></small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-cash-coin text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">This Month</h6>
                        <h2 class="mb-0"><?php echo formatCurrency($totalFeeCollected); ?></h2>
                        <small class="text-white-50"><?php echo date('F Y'); ?></small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-graph-up-arrow text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-2">Pending Fees</h6>
                        <h2 class="mb-0"><?php echo $studentsWithDues; ?></h2>
                        <small class="text-white-50">Students with dues</small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-exclamation-triangle-fill text-white"></i>
                    </div>
                </div>
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
                    <i class="bi bi-lightning-fill text-warning"></i> Quick Actions
                </h5>
                <div class="row mt-3">
                    <?php if (hasPermission('students', 'add')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo APP_URL; ?>/modules/students/add.php" class="btn btn-primary w-100">
                            <i class="bi bi-person-plus-fill"></i> Add Student
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission('fees', 'add')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo APP_URL; ?>/modules/fees/collect.php" class="btn btn-success w-100">
                            <i class="bi bi-cash-stack"></i> Collect Fee
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission('students', 'view')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-info w-100">
                            <i class="bi bi-list-ul"></i> View Students
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission('reports', 'view')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo APP_URL; ?>/modules/reports/fees.php" class="btn btn-warning w-100">
                            <i class="bi bi-file-earmark-bar-graph"></i> Fee Report
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($currentUser['role'] === 'super_admin'): ?>
<div class="row mt-4" id="super-admin-control-panel">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">
                        <i class="bi bi-sliders"></i> Super Admin Control Panel
                    </h5>
                    <small class="text-white-50">All school-wide control boxes in one place.</small>
                </div>
                <span class="badge bg-light text-dark">Super Admin only</span>
            </div>
            <div class="card-body">
                <div class="alert alert-primary mb-4">
                    Use these boxes to manage setup, approvals, academics, fees, documents, reports, and communication without leaving the portal.
                </div>

                <?php foreach ($superAdminControlGroups as $group): ?>
                    <div class="control-panel-group">
                        <div class="control-panel-group-title">
                            <h6>
                                <i class="bi bi-folder2-open"></i>
                                <?php echo htmlspecialchars($group['title']); ?>
                            </h6>
                            <small><?php echo htmlspecialchars($group['description'] ?? ''); ?></small>
                        </div>

                        <div class="control-panel-grid">
                            <?php foreach ($group['items'] as $item): ?>
                                <a href="<?php echo htmlspecialchars($item['href']); ?>" class="control-panel-tile variant-<?php echo htmlspecialchars($item['variant']); ?> text-decoration-none">
                                    <div class="control-panel-tile-icon">
                                        <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
                                    </div>
                                    <h5><?php echo htmlspecialchars($item['title']); ?></h5>
                                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                                    <div class="control-panel-footer">
                                        <span>Open module</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
</div>
</div>
</div>
<?php endif; ?>

<?php if (($currentUser['role'] ?? '') === 'admin' && !empty($adminPortalTiles)): ?>
<div class="row mt-4" id="admin-portal-control-panel">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white d-flex justify-content-end align-items-center">
                <span class="badge bg-light text-dark">Admin only</span>
            </div>
            <div class="card-body">
                <div class="alert alert-primary mb-4">
                    Use these boxes to manage school settings and student portal access without leaving the dashboard.
                </div>

                <div class="control-panel-group">
                    <div class="control-panel-grid">
                        <?php foreach ($adminPortalTiles as $tile): ?>
                        <a href="<?php echo htmlspecialchars($tile['href']); ?>" class="control-panel-tile variant-<?php echo htmlspecialchars($tile['variant']); ?> text-decoration-none">
                            <div class="control-panel-tile-icon">
                                <i class="bi <?php echo htmlspecialchars($tile['icon']); ?>"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($tile['title']); ?></h5>
                            <p><?php echo htmlspecialchars($tile['description']); ?></p>
                            <div class="control-panel-footer">
                                <span>Open module</span>
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($currentUser['role'] === 'super_admin'): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-building-check"></i> Pending School Admin Requests
                </h5>
                <a href="<?php echo APP_URL; ?>/modules/settings/school_requests.php" class="btn btn-light btn-sm">
                    <i class="bi bi-eye"></i> View All
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($pendingSchoolRequests)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>School</th>
                                    <th>Admin</th>
                                    <th>Contact</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingSchoolRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['school_name'] ?? '-'); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($request['school_code'] ?? '-'); ?></div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['admin_name'] ?? '-'); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($request['login_username'] ?? '-'); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($request['admin_mobile'] ?? '-'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($request['admin_email'] ?? '-'); ?></div>
                                        </td>
                                        <td><?php echo !empty($request['created_at']) ? htmlspecialchars(date('d-M-Y h:i A', strtotime($request['created_at']))) : '-'; ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="<?php echo APP_URL; ?>/modules/settings/school_requests.php?id=<?php echo intval($request['school_id']); ?>#details" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <form method="POST" action="<?php echo APP_URL; ?>/modules/settings/school_requests.php" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this request?')">
                                                        <i class="bi bi-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?php echo APP_URL; ?>/modules/settings/school_requests.php" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this request?')">
                                                        <i class="bi bi-x"></i> Reject
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?php echo APP_URL; ?>/modules/settings/school_requests.php" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                    <input type="hidden" name="action" value="block">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark" onclick="return confirm('Block this request?')">
                                                        <i class="bi bi-slash-circle"></i> Block
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        No pending school admin requests right now.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="row mt-4">
    <!-- Recent Transactions -->
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-clock-history"></i> Recent Transactions
                </h5>
                <div class="table-responsive mt-3">
                    <?php if (count($recentTransactions) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/modules/fees/receipt.php?id=<?php echo $transaction['receipt_id']; ?>">
                                        <?php echo htmlspecialchars($transaction['receipt_no']); ?>
                                    </a>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($transaction['admission_no']); ?></small><br>
                                    <?php echo htmlspecialchars($transaction['student_name']); ?>
                                </td>
                                <td class="paid-amount">
                                    <?php echo formatCurrency($transaction['amount_paid']); ?>
                                </td>
                                <td>
                                    <small><?php echo formatDate($transaction['payment_date']); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">
                        <i class="bi bi-inbox"></i> No transactions yet
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Students with Due Fees -->
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-exclamation-circle text-danger"></i> Pending Fees Alert
                </h5>
                <div class="table-responsive mt-3">
                    <?php if (count($dueStudents) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Admission No</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dueStudents as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($student['student_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['contact_no']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/modules/fees/collect.php?student_id=<?php echo $student['student_id']; ?>"
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-cash"></i> Collect
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-center mt-3">
                        <a href="<?php echo APP_URL; ?>/modules/fees/due.php" class="btn btn-sm btn-outline-primary">
                            View All Due Fees
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">
                        <i class="bi bi-check-circle text-success"></i> All fees are up to date!
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>
