<?php
/**
 * School Registration Requests
 * Super Admin approval queue for school and admin signup
 */

require_once '../../config/config.php';

requireLogin();
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'super_admin') {
    $_SESSION['error_message'] = 'Access denied. Only Super Admin can manage school requests.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

ensureSchoolRegistrationSchema();

$pageTitle = 'School Registration Requests';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $schoolId = intval($_POST['school_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));

    if (!in_array($action, ['approve', 'reject', 'block', 'delete', 'generate_password'], true)) {
        $error = 'Invalid action selected.';
    } else {
        if ($action === 'delete') {
            $result = schoolRegistrationDeleteRequest($schoolId, intval($currentUser['user_id']));
        } elseif ($action === 'generate_password') {
            $result = schoolRegistrationGenerateAdminPassword($schoolId, intval($currentUser['user_id']));
        } else {
            $result = schoolRegistrationUpdateStatus($schoolId, $action, intval($currentUser['user_id']), $reason);
        }

        if (!empty($result['success'])) {
            $_SESSION['success_message'] = $result['message'] ?? 'School request updated successfully.';
            if ($action === 'generate_password' && !empty($result['password'])) {
                $_SESSION['generated_password'] = $result['password'];
            } else {
                unset($_SESSION['generated_password']);
            }
            header('Location: school_requests.php?id=' . $schoolId);
            exit();
        }

        $error = $result['message'] ?? 'Unable to update the request.';
    }
}

$selectedSchoolId = intval($_GET['id'] ?? 0);
$selectedSchool = $selectedSchoolId > 0 ? schoolRegistrationGetSchoolById($selectedSchoolId) : null;
$allRequests = schoolRegistrationGetRequests('', 200);
$pendingRequests = schoolRegistrationGetRequests('pending', 50);
$approvedRequests = schoolRegistrationGetRequests('approved', 50);
$rejectedRequests = schoolRegistrationGetRequests('rejected', 50);
$blockedRequests = schoolRegistrationGetRequests('blocked', 50);

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="mb-0">
                <i class="bi bi-building-check"></i> School Registration Requests
            </h2>
            <a href="<?php echo APP_URL; ?>/modules/dashboard/" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['generated_password'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <strong>Generated Password:</strong>
        <code class="ms-2"><?php echo htmlspecialchars($_SESSION['generated_password']); ?></code>
        <div class="small mt-1">Share this password securely with the school admin, then clear it from your notes.</div>
        <?php unset($_SESSION['generated_password']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-warning">
            <div class="card-body text-center">
                <h5>Pending</h5>
                <h3><?php echo count($pendingRequests); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body text-center">
                <h5>Approved</h5>
                <h3><?php echo count($approvedRequests); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-danger">
            <div class="card-body text-center">
                <h5>Rejected</h5>
                <h3><?php echo count($rejectedRequests); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-dark">
            <div class="card-body text-center">
                <h5>Blocked</h5>
                <h3><?php echo count($blockedRequests); ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedSchool): ?>
    <div class="row mb-4" id="details">
        <div class="col-12">
            <div class="card dashboard-card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Request Details</h5>
                    <span class="badge bg-light text-dark">School ID #<?php echo intval($selectedSchool['school_id']); ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <strong>School Name</strong>
                            <div><?php echo htmlspecialchars($selectedSchool['school_name'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <strong>School Code</strong>
                            <div><?php echo htmlspecialchars($selectedSchool['school_code'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <strong>Status</strong>
                            <div>
                                <span class="badge bg-<?php echo ($selectedSchool['status'] ?? '') === 'approved' ? 'success' : (($selectedSchool['status'] ?? '') === 'rejected' ? 'danger' : (($selectedSchool['status'] ?? '') === 'blocked' ? 'dark' : 'warning text-dark')); ?>">
                                    <?php echo htmlspecialchars(ucfirst((string)($selectedSchool['status'] ?? 'pending'))); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <strong>Admin Name</strong>
                            <div><?php echo htmlspecialchars($selectedSchool['admin_name'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <strong>Username</strong>
                            <div><?php echo htmlspecialchars($selectedSchool['admin_username'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <strong>Email</strong>
                            <div><?php echo htmlspecialchars($selectedSchool['admin_email'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <strong>Mobile</strong>
                            <div><?php echo htmlspecialchars($selectedSchool['admin_mobile'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-8">
                            <strong>Address</strong>
                            <div><?php echo nl2br(htmlspecialchars($selectedSchool['school_address'] ?? '-')); ?></div>
                        </div>
                        <div class="col-md-12">
                            <strong>Submitted</strong>
                            <div><?php echo !empty($selectedSchool['created_at']) ? htmlspecialchars(date('d-M-Y h:i A', strtotime($selectedSchool['created_at']))) : '-'; ?></div>
                        </div>
                    </div>

                    <?php if (!empty($selectedSchool['status_reason'])): ?>
                        <div class="alert alert-secondary mt-3 mb-0">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($selectedSchool['status_reason']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="school_id" value="<?php echo intval($selectedSchool['school_id']); ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Approve this school registration?')">
                                <i class="bi bi-check-circle"></i> Approve
                            </button>
                        </form>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="school_id" value="<?php echo intval($selectedSchool['school_id']); ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this school registration?')">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        </form>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="school_id" value="<?php echo intval($selectedSchool['school_id']); ?>">
                            <input type="hidden" name="action" value="block">
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="btn btn-dark" onclick="return confirm('Block this school account?')">
                                <i class="bi bi-slash-circle"></i> Block
                            </button>
                        </form>
                        <?php if (!empty($selectedSchool['admin_user_id'])): ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="school_id" value="<?php echo intval($selectedSchool['school_id']); ?>">
                                <input type="hidden" name="action" value="generate_password">
                                <button type="submit" class="btn btn-outline-info" onclick="return confirm('Generate a new login password for the linked admin account?')">
                                    <i class="bi bi-key"></i> Generate Password
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (!in_array(strtolower((string)($selectedSchool['status'] ?? 'pending')), ['approved', 'active'], true)): ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="school_id" value="<?php echo intval($selectedSchool['school_id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Permanently delete this school request? This will free the school code for reuse.')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Requests</h5>
                <span class="badge bg-light text-dark"><?php echo count($allRequests); ?> total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>School</th>
                                <th>Admin</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRequests as $request): ?>
                                <tr>
                                    <td><?php echo intval($request['school_id']); ?></td>
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
                                    <td>
                                        <span class="badge bg-<?php echo ($request['status'] ?? '') === 'approved' ? 'success' : (($request['status'] ?? '') === 'rejected' ? 'danger' : (($request['status'] ?? '') === 'blocked' ? 'dark' : 'warning text-dark')); ?>">
                                            <?php echo htmlspecialchars(ucfirst((string)($request['status'] ?? 'pending'))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($request['created_at']) ? htmlspecialchars(date('d-M-Y h:i A', strtotime($request['created_at']))) : '-'; ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="<?php echo APP_URL; ?>/modules/settings/school_requests.php?id=<?php echo intval($request['school_id']); ?>#details" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this request?')">
                                                    <i class="bi bi-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this request?')">
                                                    <i class="bi bi-x"></i> Reject
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                <input type="hidden" name="action" value="block">
                                                <button type="submit" class="btn btn-sm btn-outline-dark" onclick="return confirm('Block this request?')">
                                                    <i class="bi bi-slash-circle"></i> Block
                                                </button>
                                            </form>
                                            <?php if (!empty($request['admin_user_id'])): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                    <input type="hidden" name="action" value="generate_password">
                                                    <button type="submit" class="btn btn-sm btn-outline-info" onclick="return confirm('Generate a new login password for this school admin?')">
                                                        <i class="bi bi-key"></i> Password
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (!in_array(strtolower((string)($request['status'] ?? 'pending')), ['approved', 'active'], true)): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($request['school_id']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete this school request? This will free the school code for reuse.')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($allRequests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No school registrations found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
