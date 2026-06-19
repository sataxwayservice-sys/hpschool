<?php
/**
 * Common Header File
 * Included in all pages
 */

if (!defined('APP_NAME')) {
    die('Direct access not permitted');
}

$currentUser = getCurrentUser();
$schoolSettings = getSchoolSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - <?php echo htmlspecialchars($schoolSettings['school_name']); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo @filemtime(BASE_PATH . '/assets/css/style.css'); ?>">

    <!-- Dynamic Theme Colors -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.php?v=<?php echo @filemtime(BASE_PATH . '/assets/css/theme.php'); ?>">

    <!-- Additional CSS -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<?php if (isLoggedIn()): ?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top app-navbar">
        <div class="container-fluid">
            <?php $homeUrl = getUserHomeUrl($currentUser); ?>
            <a class="navbar-brand" href="<?php echo htmlspecialchars($homeUrl); ?>">
                <?php if (!empty($schoolSettings['banner_logo'])): ?>
                    <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $schoolSettings['banner_logo']; ?>"
                         alt="Logo" height="30" class="d-inline-block align-text-top">
                <?php endif; ?>
                <?php echo htmlspecialchars($schoolSettings['school_name']); ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($homeUrl); ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>

                    <?php if (hasPermission('students', 'view')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-people"></i> Students
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (hasPermission('students', 'add')): ?>
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/students/add.php"><i class="bi bi-person-plus"></i> Add Student</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/students/"><i class="bi bi-list"></i> View Students</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/check_student.php"><i class="bi bi-search"></i> Check Student</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/students/promote.php"><i class="bi bi-arrow-up-circle"></i> Promote Students</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/students/generate_id_card.php"><i class="bi bi-credit-card"></i> Generate ID Cards</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (hasPermission('fees', 'view')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-cash-coin"></i> Fees
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/fees/collect.php">Collect Fee</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/fees/structure.php">Fee Structure</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/fees/receipts.php">Fee Receipts</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/fees/due.php">Due Fees</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole('teacher')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-journal-check"></i> Marks
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/marks/entry.php">Enter Marks</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/marks/view.php">View Marks</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (hasPermission('reports', 'view') || hasPermission('students', 'view') || hasPermission('fees', 'view')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-file-earmark-bar-graph"></i> Reports
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/"><i class="bi bi-graph-up-arrow"></i> All Reports</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/student_list.php">Student List</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/class_wise_students.php">Class-wise Students</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/fee_collection.php">Fee Collection</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/today_collection.php">Today's Collection</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/month_wise_collection.php">Month-wise Collection</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/accountant_wise_collection.php">Accountant-wise Collection</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/due_fees.php">Due Fees</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/date_wise_collection.php">Date-wise Collection</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/reports/payment_mode_report.php">Payment Mode Report</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole(['super_admin', 'admin'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($currentUser['role'] === 'super_admin'): ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/dashboard/#super-admin-control-panel"><i class="bi bi-sliders"></i> Super Admin Portal</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/school_role_control.php"><i class="bi bi-person-gear"></i> School Role Control</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <?php if (hasRolePermissionForSchool('school_settings', 'view')): ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/school.php"><i class="bi bi-building"></i> School Settings</a></li>
                            <?php endif; ?>
                            <?php if (hasRolePermissionForSchool('academic_years', 'view')): ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/academic_years.php"><i class="bi bi-calendar-range"></i> Academic Years</a></li>
                            <?php endif; ?>
                            <?php if (hasRolePermissionForSchool('session_rollover', 'view')): ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/session_rollover.php"><i class="bi bi-arrow-repeat"></i> Session Rollover</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/users.php"><i class="bi bi-people"></i> User Management</a></li>
                            <?php if (hasRolePermissionForSchool('student_portal', 'view')): ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/student_portal.php"><i class="bi bi-mortarboard"></i> Student Portal</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/classes.php"><i class="bi bi-diagram-3"></i> Classes</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/sections.php"><i class="bi bi-grid-3x3-gap"></i> Sections</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/fee_heads.php"><i class="bi bi-cash-stack"></i> Fee Heads</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/receipt_books.php"><i class="bi bi-book"></i> Receipt Books</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (hasRolePermissionForSchool('recycle_bin', 'view')): ?>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/settings/recycle_bin.php"><i class="bi bi-trash"></i> Recycle Bin</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/auth/profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/auth/change_password.php">
                                <i class="bi bi-key"></i> Change Password
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
<?php endif; ?>

<!-- Alert Messages -->
<?php
$alert = getAlert();
if ($alert):
    $alertClass = 'alert-info';
    switch ($alert['type']) {
        case 'success':
            $alertClass = 'alert-success';
            break;
        case 'error':
            $alertClass = 'alert-danger';
            break;
        case 'warning':
            $alertClass = 'alert-warning';
            break;
    }
?>
    <div class="container-fluid mt-3">
        <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($alert['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content -->
<div class="<?php echo isLoggedIn() ? 'container-fluid mt-4' : ''; ?>">
