<?php
/**
 * Edit User
 * Update existing user details
 */

// Include configuration
require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';

parentPortalEnsureSchema();
require_once '../../includes/student_portal.php';
studentPortalEnsureSchema();

// Require login
requireLogin();
requirePermission('users', 'edit');

$pageTitle = 'Edit User';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();

// Get user ID
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId == 0) {
    header("Location: users.php");
    exit();
}

// Get user details
$user = fetchOne("SELECT * FROM users WHERE user_id = ?", 'i', [$userId]);

if (!$user) {
    $_SESSION['error_message'] = "User not found!";
    header("Location: users.php");
    exit();
}

if (($currentUser['role'] ?? '') !== 'super_admin' && (
    $currentSchoolId <= 0 || intval($user['school_id'] ?? 0) !== $currentSchoolId
)) {
    $_SESSION['error_message'] = "You can only edit users from your own school.";
    header("Location: users.php");
    exit();
}

if (($user['role'] ?? '') === 'student') {
    $_SESSION['error_message'] = "Student accounts are managed from Student Portal.";
    header("Location: student_portal.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? '');
    $studentId = intval($_POST['student_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $allowedRoles = ['super_admin', 'admin', 'accountant', 'clerk', 'teacher', 'parent'];
    if (($currentUser['role'] ?? '') !== 'super_admin') {
        $allowedRoles = !empty($currentUser['school_id'])
            ? getSchoolEnabledRoles(intval($currentUser['school_id']))
            : ['admin', 'accountant', 'clerk', 'teacher', 'parent'];
    }

    $errors = [];

    // Validate required fields
    if (empty($username)) $errors[] = "Username is required";
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($role)) $errors[] = "Role is required";
    if ($role === 'student') {
        $errors[] = "Student accounts are managed from Student Portal.";
    } elseif (!in_array($role, $allowedRoles, true)) {
        $errors[] = "Invalid role selected";
    }

    // Check if username exists (excluding current user)
    if (empty($errors)) {
        $checkQuery = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
        $existingUser = fetchOne($checkQuery, 'si', [$username, $userId]);
        if ($existingUser) {
            $errors[] = "Username already exists";
        }
    }

    // Validate email if provided
    if (!empty($email) && !isValidEmail($email)) {
        $errors[] = "Invalid email address";
    }

    if (!empty($mobile) && !isValidMobile($mobile)) {
        $errors[] = "Invalid mobile number";
    }

    // Validate password if changing
    if (!empty($password)) {
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
    }

    if (empty($errors)) {
        // Build update query
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            if ($role === 'student') {
                $query = "UPDATE users SET
                         username = ?, password = ?, full_name = ?, email = ?, mobile = ?,
                         role = ?, student_id = ?, is_active = ?, updated_at = NOW()
                         WHERE user_id = ?";
                $result = executeQuery($query, 'ssssssiii', [
                    $username,
                    $hashedPassword,
                    $fullName,
                    $email,
                    $mobile,
                    $role,
                    $studentId,
                    $isActive,
                    $userId
                ]);
            } else {
                $query = "UPDATE users SET
                         username = ?, password = ?, full_name = ?, email = ?, mobile = ?,
                         role = ?, student_id = NULL, is_active = ?, updated_at = NOW()
                         WHERE user_id = ?";
                $result = executeQuery($query, 'ssssssii', [
                    $username,
                    $hashedPassword,
                    $fullName,
                    $email,
                    $mobile,
                    $role,
                    $isActive,
                    $userId
                ]);
            }
        } else {
            if ($role === 'student') {
                $query = "UPDATE users SET
                         username = ?, full_name = ?, email = ?, mobile = ?,
                         role = ?, student_id = ?, is_active = ?, updated_at = NOW()
                         WHERE user_id = ?";
                $result = executeQuery($query, 'sssssiii', [
                    $username,
                    $fullName,
                    $email,
                    $mobile,
                    $role,
                    $studentId,
                    $isActive,
                    $userId
                ]);
            } else {
                $query = "UPDATE users SET
                         username = ?, full_name = ?, email = ?, mobile = ?,
                         role = ?, student_id = NULL, is_active = ?, updated_at = NOW()
                         WHERE user_id = ?";
                $result = executeQuery($query, 'sssssii', [
                    $username,
                    $fullName,
                    $email,
                    $mobile,
                    $role,
                    $isActive,
                    $userId
                ]);
            }
        }

        if ($result !== false) {
            logActivity($currentUser['user_id'], 'User Updated', 'users', "Updated user: $username (ID: $userId)");
            $_SESSION['success_message'] = "User updated successfully!";
            header("Location: users.php");
            exit();
        } else {
            $errors[] = "Failed to update user. Please try again.";
        }
    }

    // Refresh user data with submitted values
    $user['username'] = $username;
    $user['full_name'] = $fullName;
    $user['email'] = $email;
    $user['mobile'] = $mobile;
    $user['role'] = $role;
    $user['student_id'] = $role === 'student' ? $studentId : null;
    $user['is_active'] = $isActive;
}

// Define available roles (matching the ENUM in users table)
$allRoles = [
    ['role_value' => 'super_admin', 'role_name' => 'Super Admin'],
    ['role_value' => 'admin', 'role_name' => 'Admin'],
    ['role_value' => 'accountant', 'role_name' => 'Accountant'],
    ['role_value' => 'clerk', 'role_name' => 'Clerk'],
    ['role_value' => 'teacher', 'role_name' => 'Teacher'],
    ['role_value' => 'parent', 'role_name' => 'Parent']
];

$roles = array_values(array_filter($allRoles, function ($role) use ($currentUser) {
    if (($currentUser['role'] ?? '') === 'super_admin') {
        return true;
    }

    $schoolRoles = !empty($currentUser['school_id'])
        ? getSchoolEnabledRoles(intval($currentUser['school_id']))
        : ['admin', 'accountant', 'clerk', 'teacher', 'parent'];

    return in_array($role['role_value'], $schoolRoles, true);
}));

if (($currentUser['role'] ?? '') === 'super_admin') {
    $students = fetchAll(
        "SELECT s.student_id, s.student_name, s.admission_no, c.class_name, sec.section_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.class_id
         LEFT JOIN sections sec ON s.section_id = sec.section_id
         ORDER BY s.student_name"
    );
} elseif ($currentSchoolId > 0) {
    $students = fetchAll(
        "SELECT s.student_id, s.student_name, s.admission_no, c.class_name, sec.section_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.class_id
         LEFT JOIN sections sec ON s.section_id = sec.section_id
         WHERE COALESCE(s.school_id, 0) = ?
         ORDER BY s.student_name",
        'i',
        [$currentSchoolId]
    );
} else {
    $students = [];
}

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-pencil-square"></i> Edit User
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/settings/users.php'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (isset($errors) && count($errors) > 0): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Error!</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card dashboard-card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> User Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="editUserForm">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>User ID:</strong> <?php echo $userId; ?>
                        <?php if ($userId == $currentUser['user_id']): ?>
                            <span class="badge bg-info">You are editing your own account</span>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username"
                                   value="<?php echo htmlspecialchars($user['username']); ?>"
                                   required>
                            <small class="text-muted">Used for login. Must be unique.</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name"
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        <small class="text-muted">Optional. Used for notifications.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" class="form-control" name="mobile"
                               value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" maxlength="10">
                        <small class="text-muted">Used for SMS notifications.</small>
                    </div>

                    <hr>
                    <h5 class="mb-3">Change Password (Optional)</h5>
                    <p class="text-muted">Leave blank to keep current password</p>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password"
                                       minlength="6" placeholder="Leave blank to keep current">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password"
                                   id="confirmPassword" minlength="6" placeholder="Re-enter new password">
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="roleSelect" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_value']; ?>"
                                    <?php echo ($user['role'] == $role['role_value']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="studentLinkDiv" style="display: none;">
                        <label class="form-label">Linked Student <span class="text-danger">*</span></label>
                        <select class="form-select" name="student_id">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo intval($student['student_id']); ?>"
                                    <?php echo (intval($user['student_id'] ?? 0) === intval($student['student_id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['student_name'] . ' - ' . $student['admission_no'] . ' (' . ($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Required for student login accounts.</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   id="isActive" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActive">
                                <strong>Active</strong> (User can login)
                            </label>
                        </div>
                    </div>

                    <?php if ($userId == $currentUser['user_id']): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> You are editing your own account. Be careful not to deactivate yourself or remove admin permissions!
                    </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="bi bi-save"></i> Update User
                        </button>
                        <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/settings/users.php'); ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
// Toggle password visibility
$('#togglePassword').on('click', function() {
    const passwordField = $('#password');
    const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
    passwordField.attr('type', type);
    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
});

function toggleStudentLink() {
    const isStudent = $('#roleSelect').val() === 'student';
    $('#studentLinkDiv').toggle(isStudent);
    $('#studentLinkDiv select').prop('required', isStudent);
}

$('#roleSelect').on('change', toggleStudentLink);
toggleStudentLink();

// Password match validation
$('#editUserForm').on('submit', function(e) {
    const password = $('#password').val();
    const confirmPassword = $('#confirmPassword').val();
    const isStudent = $('#roleSelect').val() === 'student';

    // Only validate if password is being changed
    if (password !== '' || confirmPassword !== '') {
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }

        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return false;
        }
    }

    if (isStudent && $('#studentLinkDiv select').val() === '') {
        e.preventDefault();
        alert('Please select a linked student record!');
        return false;
    }

    return true;
});
";

// Include footer
include '../../includes/footer.php';
?>
