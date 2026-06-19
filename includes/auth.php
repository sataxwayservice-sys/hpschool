<?php
/**
 * Authentication Helper Functions
 * Handles user authentication, sessions, and permissions
 */

/**
 * Check if user is logged in
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
}

/**
 * Require login (redirect to login page if not logged in)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/modules/auth/login.php');
    }
}

/**
 * Get current user data
 *
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    static $currentUser = null;

    if ($currentUser === null) {
        $userId = $_SESSION['user_id'];
        $query = "SELECT * FROM users WHERE user_id = ? AND is_active = 1";
        $currentUser = fetchOne($query, 'i', [$userId]);
    }

    return $currentUser;
}

/**
 * Check if user has role
 *
 * @param string|array $roles Role(s) to check
 * @return bool
 */
function hasRole($roles) {
    $user = getCurrentUser();

    if (!$user) {
        return false;
    }

    if (is_array($roles)) {
        return in_array($user['role'], $roles);
    }

    return $user['role'] === $roles;
}

/**
 * Normalize custom action names used across the app.
 */
function normalizePermissionAction($action) {
    $action = strtolower(trim((string) $action));
    $aliases = [
        'send' => 'add',
        'manage' => 'edit',
        'create' => 'add',
        'update' => 'edit',
    ];

    return $aliases[$action] ?? $action;
}

/**
 * Require specific role (redirect if not authorized)
 *
 * @param string|array $roles
 */
function requireRole($roles) {
    if (!hasRole($roles)) {
        alertAndRedirect('You do not have permission to access this page.', getUserHomeUrl(), 'error');
    }
}

/**
 * Check if user has permission for a module
 *
 * @param string $module Module name
 * @param string $action (view|add|edit|delete)
 * @return bool
 */
function hasPermission($module, $action) {
    $user = getCurrentUser();

    if (!$user) {
        return false;
    }

    // Super admin has all permissions
    if ($user['role'] === 'super_admin') {
        return true;
    }

    $module = strtolower(trim((string) $module));
    $action = normalizePermissionAction($action);

    if ($module === 'student_portal') {
        return false;
    }

    // Check explicit user permissions first
    $permission = fetchOne(
        "SELECT * FROM user_permissions
         WHERE user_id = ? AND module_name = ?",
        'is',
        [$user['user_id'], $module]
    );

    if ($permission) {
        switch ($action) {
        case 'view':
            return $permission['can_view'] == 1;
        case 'add':
            return $permission['can_add'] == 1;
        case 'edit':
            return $permission['can_edit'] == 1;
        case 'delete':
            return $permission['can_delete'] == 1;
        default:
            return false;
        }
    }

    // Fallback to school-specific role permissions, then global defaults
    $schoolId = intval($user['school_id'] ?? 0);
    if ($schoolId <= 0 && function_exists('getCurrentSchoolId')) {
        $schoolId = intval(getCurrentSchoolId());
    }

    $rolePermissionRow = function_exists('getRolePermissionsForSchool')
        ? getRolePermissionsForSchool($user['role'], $schoolId)
        : fetchOne(
            "SELECT permissions FROM role_permissions WHERE role_name = ? LIMIT 1",
            's',
            [$user['role']]
        );

    if (!$rolePermissionRow || empty($rolePermissionRow['permissions'])) {
        return false;
    }

    $decodedPermissions = json_decode((string) $rolePermissionRow['permissions'], true);
    if (!is_array($decodedPermissions)) {
        return false;
    }

    $permissionKey = $module . '_' . $action;
    return in_array($permissionKey, $decodedPermissions, true);
}

/**
 * Require permission (redirect if not authorized)
 *
 * @param string $module
 * @param string $action
 */
function requirePermission($module, $action) {
    if (!hasPermission($module, $action)) {
        alertAndRedirect('You do not have permission to perform this action.', getUserHomeUrl(), 'error');
    }
}

/**
 * Login user
 *
 * @param string $username
 * @param string $password
 * @return array
 */
function loginUser($username, $password) {
    // Validate input
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    if (function_exists('ensureSchoolRegistrationSchema')) {
        ensureSchoolRegistrationSchema();
    }

    $username = trim((string) $username);

    // Get user by username or email so we can show pending/blocked messages too
    $query = "SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1";
    $user = fetchOne($query, 'ss', [$username, $username]);

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    if (function_exists('schoolRegistrationGetLoginStatusMessage')) {
        $statusMessage = schoolRegistrationGetLoginStatusMessage($user);
        if ($statusMessage !== '') {
            return ['success' => false, 'message' => $statusMessage];
        }
    } elseif (intval($user['is_active'] ?? 0) !== 1) {
        return ['success' => false, 'message' => 'Your account is inactive. Please contact the school office.'];
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    $resolvedSchoolId = intval($user['school_id'] ?? 0);
    if ($resolvedSchoolId <= 0 && (($user['role'] ?? '') === 'admin')) {
        if (function_exists('schoolRegistrationGetSchoolByUserId')) {
            $school = schoolRegistrationGetSchoolByUserId(intval($user['user_id']));
            if (!empty($school['school_id'])) {
                $resolvedSchoolId = intval($school['school_id']);
            }
        }

        if ($resolvedSchoolId <= 0) {
            $school = fetchOne("SELECT school_id FROM schools WHERE admin_user_id = ? LIMIT 1", 'i', [intval($user['user_id'])]);
            if (!empty($school['school_id'])) {
                $resolvedSchoolId = intval($school['school_id']);
            }
        }

        if ($resolvedSchoolId <= 0 && !empty($user['username'])) {
            $school = fetchOne("SELECT school_id FROM schools WHERE admin_username = ? LIMIT 1", 's', [$user['username']]);
            if (!empty($school['school_id'])) {
                $resolvedSchoolId = intval($school['school_id']);
            }
        }
    }

    if ($resolvedSchoolId <= 0 && (($user['role'] ?? '') === 'student') && !empty($user['student_id'])) {
        $studentSchool = fetchOne("SELECT school_id FROM students WHERE student_id = ? LIMIT 1", 'i', [intval($user['student_id'])]);
        if (!empty($studentSchool['school_id'])) {
            $resolvedSchoolId = intval($studentSchool['school_id']);
        }
    }

    if ($resolvedSchoolId > 0 && intval($user['school_id'] ?? 0) <= 0) {
        executeQuery(
            "UPDATE users SET school_id = ?, updated_at = NOW() WHERE user_id = ? AND (school_id IS NULL OR school_id = 0)",
            'ii',
            [$resolvedSchoolId, intval($user['user_id'])]
        );
        $user['school_id'] = $resolvedSchoolId;
    }

    if (($user['role'] ?? '') === 'admin' && function_exists('schoolRegistrationSeedDefaultAdminPermissions')) {
        schoolRegistrationSeedDefaultAdminPermissions(intval($user['user_id']));
    }

    // Set session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['student_id'] = $user['student_id'] ?? null;
    $_SESSION['school_id'] = $resolvedSchoolId > 0 ? $resolvedSchoolId : ($user['school_id'] ?? null);
    $_SESSION['is_logged_in'] = true;

    // Update last login
    $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
    executeQuery($updateQuery, 'i', [$user['user_id']]);

    // Log activity
    logActivity($user['user_id'], 'Login', 'Authentication', 'User logged in successfully');

    $safeUser = $user;
    unset($safeUser['password']);

    return ['success' => true, 'message' => 'Login successful.', 'user' => $safeUser];
}

/**
 * Logout user
 */
function logoutUser() {
    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];
        logActivity($userId, 'Logout', 'Authentication', 'User logged out');
    }

    // Destroy session
    session_unset();
    session_destroy();

    // Redirect to login
    redirect(APP_URL . '/modules/auth/login.php');
}

/**
 * Register new user
 *
 * @param array $data
 * @return array
 */
function registerUser($data) {
    if (function_exists('ensureSchoolRegistrationSchema')) {
        ensureSchoolRegistrationSchema();
    }

    // Validate required fields
    $required = ['username', 'email', 'password', 'full_name', 'role'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => ucfirst($field) . ' is required.'];
        }
    }

    // Validate email
    if (!isValidEmail($data['email'])) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    // Validate password length
    if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'];
    }

    // Check if username exists
    $checkQuery = "SELECT user_id FROM users WHERE username = ?";
    if (fetchOne($checkQuery, 's', [$data['username']])) {
        return ['success' => false, 'message' => 'Username already exists.'];
    }

    // Check if email exists
    $checkQuery = "SELECT user_id FROM users WHERE email = ?";
    if (fetchOne($checkQuery, 's', [$data['email']])) {
        return ['success' => false, 'message' => 'Email already exists.'];
    }

    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $status = strtolower(trim((string)($data['status'] ?? '')));
    $isActive = array_key_exists('is_active', $data) ? intval($data['is_active']) : 1;

    if ($status === '') {
        $status = $isActive === 1 ? 'active' : 'pending';
    }

    if ($status === 'approved') {
        $status = 'active';
    }

    if (!in_array($status, ['pending', 'active', 'rejected', 'blocked'], true)) {
        $status = $isActive === 1 ? 'active' : 'pending';
    }

    // Insert user
    $fields = ['username', 'email', 'password', 'full_name', 'role', 'mobile', 'status'];
    $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
    $types = 'sssssss';
    $values = [
        $data['username'],
        $data['email'],
        $hashedPassword,
        $data['full_name'],
        $data['role'],
        $data['mobile'] ?? null,
        $status
    ];

    if (array_key_exists('student_id', $data) && $data['student_id'] !== null && $data['student_id'] !== '') {
        $fields[] = 'student_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = intval($data['student_id']);
    }

    if (array_key_exists('school_id', $data) && $data['school_id'] !== null && $data['school_id'] !== '') {
        $fields[] = 'school_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = intval($data['school_id']);
    }

    if (array_key_exists('password_encrypted', $data) && $data['password_encrypted'] !== null && $data['password_encrypted'] !== '') {
        $fields[] = 'password_encrypted';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $data['password_encrypted'];
    }

    if (array_key_exists('status_reason', $data)) {
        $fields[] = 'status_reason';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $data['status_reason'];
    }

    if (array_key_exists('is_active', $data)) {
        $fields[] = 'is_active';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = intval($data['is_active']);
    }

    $query = "INSERT INTO users (" . implode(', ', $fields) . ")
              VALUES (" . implode(', ', $placeholders) . ")";
    $result = executeQuery($query, $types, $values);

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to create user.'];
    }

    // Log activity
    if (isLoggedIn()) {
        $currentUser = getCurrentUser();
        logActivity($currentUser['user_id'], 'Create User', 'Users', 'Created user: ' . $data['username']);
    }

    return ['success' => true, 'message' => 'User created successfully.', 'user_id' => $result['insert_id']];
}

/**
 * Update user password
 *
 * @param int $userId
 * @param string $newPassword
 * @return bool
 */
function updatePassword($userId, $newPassword) {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $query = "UPDATE users SET password = ? WHERE user_id = ?";
    $result = executeQuery($query, 'si', [$hashedPassword, $userId]);
    return $result !== false;
}

/**
 * Check if super admin exists
 *
 * @return bool
 */
function superAdminExists() {
    $query = "SELECT user_id FROM users WHERE role = 'super_admin' LIMIT 1";
    $result = fetchOne($query);
    return $result !== null;
}

?>
