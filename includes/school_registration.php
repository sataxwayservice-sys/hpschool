<?php
/**
 * School Registration and Approval Helpers
 */

if (!function_exists('schoolRegistrationNormalizeStatus')) {
    function schoolRegistrationNormalizeStatus($status, $fallback = 'pending') {
        $status = strtolower(trim((string) $status));
        $aliases = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'block' => 'blocked',
        ];

        if (isset($aliases[$status])) {
            $status = $aliases[$status];
        }

        $allowed = ['pending', 'approved', 'rejected', 'blocked', 'active'];

        if (!in_array($status, $allowed, true)) {
            $status = strtolower(trim((string) $fallback));
        }

        if ($status === 'approved') {
            $status = 'active';
        }

        if (!in_array($status, ['pending', 'active', 'rejected', 'blocked'], true)) {
            $status = 'pending';
        }

        return $status;
    }
}

if (!function_exists('schoolRegistrationGeneratePassword')) {
    function schoolRegistrationGeneratePassword($length = 10) {
        $length = max(8, intval($length));
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $characterLength = strlen($characters);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $characterLength - 1)];
        }

        return $password;
    }
}

if (!function_exists('ensureSchoolRegistrationSchema')) {
    function ensureSchoolRegistrationSchema() {
        static $checked = false;
        if ($checked) {
            return true;
        }
        $checked = true;

        ensureSchoolSettingsSchema();

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("School registration schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $conn->query(
            "CREATE TABLE IF NOT EXISTS schools (
                school_id int(11) NOT NULL AUTO_INCREMENT,
                school_name varchar(200) NOT NULL,
                school_code varchar(50) NOT NULL,
                admin_name varchar(150) NOT NULL,
                admin_username varchar(100) NOT NULL,
                admin_mobile varchar(15) NOT NULL,
                admin_email varchar(100) NOT NULL,
                school_address text DEFAULT NULL,
                school_phone varchar(20) DEFAULT NULL,
                school_email varchar(100) DEFAULT NULL,
                affiliation_no varchar(50) DEFAULT NULL,
                udise_code varchar(50) DEFAULT NULL,
                current_academic_year varchar(50) DEFAULT NULL,
                admission_prefix varchar(30) DEFAULT 'STU',
                receipt_prefix varchar(30) DEFAULT 'REC',
                transfer_certificate_prefix varchar(30) DEFAULT 'TC/',
                transfer_certificate_last_no int(11) NOT NULL DEFAULT 0,
                school_logo varchar(255) DEFAULT NULL,
                login_logo varchar(255) DEFAULT NULL,
                banner_logo varchar(255) DEFAULT NULL,
                theme_preset varchar(50) DEFAULT 'default',
                theme_primary_color varchar(20) DEFAULT '#0d6efd',
                theme_secondary_color varchar(20) DEFAULT '#6c757d',
                theme_success_color varchar(20) DEFAULT '#198754',
                theme_info_color varchar(20) DEFAULT '#0dcaf0',
                theme_warning_color varchar(20) DEFAULT '#ffc107',
                theme_danger_color varchar(20) DEFAULT '#dc3545',
                currency_symbol varchar(10) DEFAULT '₹',
                upi_id varchar(100) DEFAULT NULL,
                payment_recipient_name varchar(150) DEFAULT NULL,
                payment_note varchar(255) DEFAULT NULL,
                teacher_signature varchar(255) DEFAULT NULL,
                class_teacher_signature varchar(255) DEFAULT NULL,
                principal_signature varchar(255) DEFAULT NULL,
                status enum('pending','approved','rejected','blocked') NOT NULL DEFAULT 'pending',
                status_reason text DEFAULT NULL,
                admin_user_id int(11) DEFAULT NULL,
                approved_by int(11) DEFAULT NULL,
                approved_at datetime DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (school_id),
                UNIQUE KEY idx_school_code (school_code),
                KEY idx_school_status (status),
                KEY idx_school_admin_user (admin_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $ensureColumn('users', 'school_id', "school_id int(11) DEFAULT NULL AFTER role");
        $ensureColumn('users', 'status', "status enum('pending','active','rejected','blocked') NOT NULL DEFAULT 'active' AFTER school_id");
        $ensureColumn('users', 'status_reason', "status_reason text DEFAULT NULL AFTER status");

        $userSchoolIndex = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_school_id'");
        if (!$userSchoolIndex || $userSchoolIndex->num_rows === 0) {
            if (!$conn->query("ALTER TABLE users ADD KEY idx_users_school_id (school_id)")) {
                error_log('Failed to create users.school_id index: ' . $conn->error);
            }
        }

        return true;
    }
}

if (!function_exists('schoolRegistrationGetDefaultSettings')) {
    function schoolRegistrationGetDefaultSettings() {
        return [
            'school_id' => 0,
            'school_name' => APP_NAME,
            'school_address' => '',
            'school_phone' => '',
            'school_email' => '',
            'current_academic_year' => date('Y') . '-' . (date('Y') + 1),
            'admission_prefix' => 'STU',
            'receipt_prefix' => 'REC',
            'affiliation_no' => '',
            'school_code' => '',
            'udise_code' => '',
            'school_logo' => '',
            'login_logo' => '',
            'banner_logo' => '',
            'theme_preset' => 'default',
            'theme_primary_color' => '#0d6efd',
            'theme_secondary_color' => '#6c757d',
            'theme_success_color' => '#198754',
            'theme_info_color' => '#0dcaf0',
            'theme_warning_color' => '#ffc107',
            'theme_danger_color' => '#dc3545',
            'currency_symbol' => CURRENCY_SYMBOL,
            'upi_id' => '',
            'payment_recipient_name' => '',
            'payment_note' => '',
            'teacher_signature' => '',
            'class_teacher_signature' => '',
            'principal_signature' => '',
            'student_add_limit' => 0,
            'attendance_scan_mode' => 'daily',
            'attendance_class_start_time' => '08:00:00',
            'attendance_period_duration_minutes' => 45,
            'attendance_auto_alert_enabled' => 1,
            'attendance_absent_message_template' => 'Dear Parent, {student_details} was marked ABSENT {period_text} on {date} at {school_name}. Please contact the school office if this needs correction.',
            'enabled_roles' => json_encode(['admin', 'accountant', 'clerk', 'teacher', 'parent']),
            'subscription_plan' => 'free',
            'subscription_price' => '0.00',
            'subscription_currency_code' => 'INR',
            'subscription_billing_cycle' => 'monthly',
            'subscription_status' => 'active',
            'ads_enabled' => 1,
            'subscription_started_at' => null,
            'subscription_expires_at' => null,
            'subscription_gateway' => 'manual',
            'subscription_gateway_reference' => '',
            'subscription_payment_link' => '',
            'subscription_notes' => '',
            'transfer_certificate_prefix' => 'TC/',
            'transfer_certificate_last_no' => 0,
        ];
    }
}

if (!function_exists('schoolRegistrationGetSchoolById')) {
    function schoolRegistrationGetSchoolById($schoolId) {
        $schoolId = intval($schoolId);
        if ($schoolId <= 0) {
            return null;
        }

        ensureSchoolRegistrationSchema();

        return fetchOne(
            "SELECT * FROM schools WHERE school_id = ? LIMIT 1",
            'i',
            [$schoolId]
        );
    }
}

if (!function_exists('schoolRegistrationGetSchoolByUserId')) {
    function schoolRegistrationGetSchoolByUserId($userId) {
        $userId = intval($userId);
        if ($userId <= 0) {
            return null;
        }

        ensureSchoolRegistrationSchema();

        return fetchOne(
            "SELECT s.*
             FROM users u
             JOIN schools s ON u.school_id = s.school_id
             WHERE u.user_id = ? LIMIT 1",
            'i',
            [$userId]
        );
    }
}

if (!function_exists('schoolRegistrationMapSchoolToSettings')) {
    function schoolRegistrationMapSchoolToSettings(array $school, array $fallback = []): array {
        $fallback = array_merge([
            'school_id' => 0,
            'school_name' => APP_NAME,
            'school_address' => '',
            'school_phone' => '',
            'school_email' => '',
            'current_academic_year' => date('Y') . '-' . (date('Y') + 1),
            'admission_prefix' => 'STU',
            'receipt_prefix' => 'REC',
            'affiliation_no' => '',
            'school_code' => '',
            'udise_code' => '',
            'school_logo' => '',
            'login_logo' => '',
            'banner_logo' => '',
            'theme_preset' => 'default',
            'theme_primary_color' => '#0d6efd',
            'theme_secondary_color' => '#6c757d',
            'theme_success_color' => '#198754',
            'theme_info_color' => '#0dcaf0',
            'theme_warning_color' => '#ffc107',
            'theme_danger_color' => '#dc3545',
            'currency_symbol' => CURRENCY_SYMBOL,
            'upi_id' => '',
            'payment_recipient_name' => '',
            'payment_note' => '',
            'teacher_signature' => '',
            'class_teacher_signature' => '',
            'principal_signature' => '',
            'student_add_limit' => 0,
            'attendance_scan_mode' => 'daily',
            'attendance_class_start_time' => '08:00:00',
            'attendance_period_duration_minutes' => 45,
            'attendance_auto_alert_enabled' => 1,
            'attendance_absent_message_template' => 'Dear Parent, {student_details} was marked ABSENT {period_text} on {date} at {school_name}. Please contact the school office if this needs correction.',
            'enabled_roles' => json_encode(['admin', 'accountant', 'clerk', 'teacher', 'parent']),
            'transfer_certificate_prefix' => 'TC/',
            'transfer_certificate_last_no' => 0,
        ], $fallback);

        return [
            'school_id' => intval($school['school_id'] ?? $fallback['school_id']),
            'school_name' => trim((string) ($school['school_name'] ?? $fallback['school_name'])),
            'school_address' => trim((string) ($school['school_address'] ?? $fallback['school_address'])),
            'school_phone' => trim((string) ($school['school_phone'] ?? ($school['admin_mobile'] ?? $fallback['school_phone']))),
            'school_email' => trim((string) ($school['school_email'] ?? ($school['admin_email'] ?? $fallback['school_email']))),
            'current_academic_year' => trim((string) ($school['current_academic_year'] ?? $fallback['current_academic_year'])),
            'admission_prefix' => trim((string) ($school['admission_prefix'] ?? $fallback['admission_prefix'])),
            'receipt_prefix' => trim((string) ($school['receipt_prefix'] ?? $fallback['receipt_prefix'])),
            'affiliation_no' => trim((string) ($school['affiliation_no'] ?? $fallback['affiliation_no'])),
            'school_code' => trim((string) ($school['school_code'] ?? $fallback['school_code'])),
            'udise_code' => trim((string) ($school['udise_code'] ?? $fallback['udise_code'])),
            'school_logo' => trim((string) ($school['school_logo'] ?? $fallback['school_logo'])),
            'login_logo' => trim((string) ($school['login_logo'] ?? $fallback['login_logo'])),
            'banner_logo' => trim((string) ($school['banner_logo'] ?? $fallback['banner_logo'])),
            'theme_preset' => trim((string) ($school['theme_preset'] ?? $fallback['theme_preset'])),
            'theme_primary_color' => trim((string) ($school['theme_primary_color'] ?? $fallback['theme_primary_color'])),
            'theme_secondary_color' => trim((string) ($school['theme_secondary_color'] ?? $fallback['theme_secondary_color'])),
            'theme_success_color' => trim((string) ($school['theme_success_color'] ?? $fallback['theme_success_color'])),
            'theme_info_color' => trim((string) ($school['theme_info_color'] ?? $fallback['theme_info_color'])),
            'theme_warning_color' => trim((string) ($school['theme_warning_color'] ?? $fallback['theme_warning_color'])),
            'theme_danger_color' => trim((string) ($school['theme_danger_color'] ?? $fallback['theme_danger_color'])),
            'currency_symbol' => trim((string) ($school['currency_symbol'] ?? $fallback['currency_symbol'])),
            'upi_id' => trim((string) ($school['upi_id'] ?? $fallback['upi_id'])),
            'payment_recipient_name' => trim((string) ($school['payment_recipient_name'] ?? $fallback['payment_recipient_name'])),
            'payment_note' => trim((string) ($school['payment_note'] ?? $fallback['payment_note'])),
            'teacher_signature' => trim((string) ($school['teacher_signature'] ?? $fallback['teacher_signature'])),
            'class_teacher_signature' => trim((string) ($school['class_teacher_signature'] ?? $fallback['class_teacher_signature'])),
            'principal_signature' => trim((string) ($school['principal_signature'] ?? $fallback['principal_signature'])),
            'student_add_limit' => intval($school['student_add_limit'] ?? $fallback['student_add_limit']),
            'attendance_scan_mode' => trim((string) ($school['attendance_scan_mode'] ?? $fallback['attendance_scan_mode'])),
            'attendance_class_start_time' => trim((string) ($school['attendance_class_start_time'] ?? $fallback['attendance_class_start_time'])),
            'attendance_period_duration_minutes' => intval($school['attendance_period_duration_minutes'] ?? $fallback['attendance_period_duration_minutes']),
            'attendance_auto_alert_enabled' => intval($school['attendance_auto_alert_enabled'] ?? $fallback['attendance_auto_alert_enabled']),
            'attendance_absent_message_template' => trim((string) ($school['attendance_absent_message_template'] ?? $fallback['attendance_absent_message_template'])),
            'enabled_roles' => trim((string) ($school['enabled_roles'] ?? $fallback['enabled_roles'])),
            'subscription_plan' => trim((string) ($school['subscription_plan'] ?? $fallback['subscription_plan'])),
            'subscription_price' => trim((string) ($school['subscription_price'] ?? $fallback['subscription_price'])),
            'subscription_currency_code' => trim((string) ($school['subscription_currency_code'] ?? $fallback['subscription_currency_code'])),
            'subscription_billing_cycle' => trim((string) ($school['subscription_billing_cycle'] ?? $fallback['subscription_billing_cycle'])),
            'subscription_status' => trim((string) ($school['subscription_status'] ?? $fallback['subscription_status'])),
            'ads_enabled' => intval($school['ads_enabled'] ?? $fallback['ads_enabled']),
            'subscription_started_at' => $school['subscription_started_at'] ?? $fallback['subscription_started_at'],
            'subscription_expires_at' => $school['subscription_expires_at'] ?? $fallback['subscription_expires_at'],
            'subscription_gateway' => trim((string) ($school['subscription_gateway'] ?? $fallback['subscription_gateway'])),
            'subscription_gateway_reference' => trim((string) ($school['subscription_gateway_reference'] ?? $fallback['subscription_gateway_reference'])),
            'subscription_payment_link' => trim((string) ($school['subscription_payment_link'] ?? $fallback['subscription_payment_link'])),
            'subscription_notes' => trim((string) ($school['subscription_notes'] ?? $fallback['subscription_notes'])),
            'transfer_certificate_prefix' => trim((string) ($school['transfer_certificate_prefix'] ?? $fallback['transfer_certificate_prefix'])),
            'transfer_certificate_last_no' => intval($school['transfer_certificate_last_no'] ?? $fallback['transfer_certificate_last_no']),
        ];
    }
}

if (!function_exists('schoolRegistrationSyncApprovedSchoolSettings')) {
    function schoolRegistrationSyncApprovedSchoolSettings(array $school): bool {
        ensureSchoolSettingsSchema();

        $settings = schoolRegistrationMapSchoolToSettings($school, schoolRegistrationGetDefaultSettings());
        $existingSettings = fetchOne(
            "SELECT setting_id FROM school_settings WHERE school_id = ? ORDER BY updated_at DESC, setting_id DESC LIMIT 1",
            'i',
            [intval($settings['school_id'])]
        );

        if ($existingSettings && intval($existingSettings['setting_id'] ?? 0) > 0) {
            return true;
        }

        $fields = [
            'school_id', 'school_name', 'school_address', 'school_phone', 'school_email',
            'current_academic_year', 'admission_prefix', 'receipt_prefix',
            'affiliation_no', 'school_code', 'udise_code',
            'school_logo', 'login_logo', 'banner_logo',
            'theme_preset', 'theme_primary_color', 'theme_secondary_color',
            'theme_success_color', 'theme_info_color', 'theme_warning_color',
            'theme_danger_color', 'currency_symbol', 'upi_id',
            'payment_recipient_name', 'payment_note',
            'teacher_signature', 'class_teacher_signature', 'principal_signature',
            'student_add_limit',
            'attendance_scan_mode', 'attendance_class_start_time', 'attendance_period_duration_minutes',
            'attendance_auto_alert_enabled', 'attendance_absent_message_template',
            'enabled_roles',
            'subscription_plan',
            'subscription_price',
            'subscription_currency_code',
            'subscription_billing_cycle',
            'subscription_status',
            'ads_enabled',
            'subscription_started_at',
            'subscription_expires_at',
            'subscription_gateway',
            'subscription_gateway_reference',
            'subscription_payment_link',
            'subscription_notes',
            'transfer_certificate_prefix', 'transfer_certificate_last_no'
        ];

        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $query = "INSERT INTO school_settings (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        $values = [];
        $types = '';
        $fieldTypeMap = [
            'school_id' => 'i',
            'transfer_certificate_last_no' => 'i',
            'attendance_period_duration_minutes' => 'i',
            'attendance_auto_alert_enabled' => 'i',
            'ads_enabled' => 'i',
        ];
        foreach ($fields as $field) {
            $values[] = $settings[$field] ?? '';
            $types .= $fieldTypeMap[$field] ?? 's';
        }

        return executeQuery($query, $types, $values) !== false;
    }
}

if (!function_exists('schoolRegistrationCreateRequest')) {
    function schoolRegistrationCreateRequest(array $input): array {
        ensureSchoolRegistrationSchema();

        $schoolName = trim((string)($input['school_name'] ?? ''));
        $schoolCode = trim((string)($input['school_code'] ?? ''));
        $adminName = trim((string)($input['admin_name'] ?? ''));
        $mobile = trim((string)($input['mobile'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');
        $address = trim((string)($input['address'] ?? ''));
        $errors = [];

        if ($schoolName === '') {
            $errors[] = 'School name is required.';
        }
        if ($schoolCode === '') {
            $errors[] = 'School code is required.';
        }
        if ($adminName === '') {
            $errors[] = 'Principal/Admin name is required.';
        }
        if ($mobile === '' || !isValidMobile($mobile)) {
            $errors[] = 'Please enter a valid mobile number.';
        }
        if ($email === '' || !isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($username === '') {
            $errors[] = 'Username is required.';
        }
        if ($password === '' || strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        if ($address === '') {
            $errors[] = 'School address is required.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        $existingCode = fetchOne("SELECT school_id FROM schools WHERE school_code = ? LIMIT 1", 's', [$schoolCode]);
        if ($existingCode) {
            return ['success' => false, 'message' => 'School code already exists.'];
        }

        if (fetchOne("SELECT user_id FROM users WHERE username = ? LIMIT 1", 's', [$username])) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        if (fetchOne("SELECT user_id FROM users WHERE email = ? LIMIT 1", 's', [$email])) {
            return ['success' => false, 'message' => 'Email already exists.'];
        }

        beginTransaction();
        try {
            $defaults = schoolRegistrationGetDefaultSettings();
            $schoolData = array_merge($defaults, [
                'school_name' => $schoolName,
                'school_code' => $schoolCode,
                'admin_name' => $adminName,
                'admin_username' => $username,
                'admin_mobile' => $mobile,
                'admin_email' => $email,
                'school_address' => $address,
                'school_phone' => $defaults['school_phone'] ?? $mobile,
                'school_email' => $defaults['school_email'] ?? $email,
                'status' => 'pending',
                'status_reason' => null,
                'admin_user_id' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $schoolFields = [
                'school_name', 'school_code', 'admin_name', 'admin_username', 'admin_mobile', 'admin_email',
                'school_address', 'school_phone', 'school_email', 'affiliation_no', 'udise_code',
                'current_academic_year', 'admission_prefix', 'receipt_prefix', 'transfer_certificate_prefix',
                'transfer_certificate_last_no', 'school_logo', 'login_logo', 'banner_logo', 'theme_preset',
                'theme_primary_color', 'theme_secondary_color', 'theme_success_color', 'theme_info_color',
                'theme_warning_color', 'theme_danger_color', 'currency_symbol', 'upi_id',
                'payment_recipient_name', 'payment_note', 'teacher_signature', 'class_teacher_signature',
                'principal_signature', 'status', 'status_reason', 'admin_user_id', 'approved_by', 'approved_at'
            ];

            $schoolTypes = '';
            $schoolTypeMap = [
                'transfer_certificate_last_no' => 'i',
                'admin_user_id' => 'i',
                'approved_by' => 'i',
                'approved_at' => 's',
            ];
            $schoolValues = [];
            foreach ($schoolFields as $field) {
                $schoolValues[] = $schoolData[$field] ?? '';
                $schoolTypes .= $schoolTypeMap[$field] ?? 's';
            }

            $schoolInsert = executeQuery(
                "INSERT INTO schools (" . implode(', ', $schoolFields) . ")
                 VALUES (" . implode(', ', array_fill(0, count($schoolFields), '?')) . ")",
                $schoolTypes,
                $schoolValues
            );

            if ($schoolInsert === false || empty($schoolInsert['insert_id'])) {
                rollbackTransaction();
                return ['success' => false, 'message' => 'Failed to create the school record.'];
            }

            $schoolId = intval($schoolInsert['insert_id']);

            $userResult = registerUser([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'full_name' => $adminName,
                'role' => 'admin',
                'mobile' => $mobile,
                'school_id' => $schoolId,
                'status' => 'pending',
                'status_reason' => 'Waiting for Super Admin approval.',
                'is_active' => 0,
            ]);

            if (empty($userResult['success'])) {
                rollbackTransaction();
                return ['success' => false, 'message' => $userResult['message'] ?? 'Failed to create the admin account.'];
            }

            $userId = intval($userResult['user_id']);
            $linked = executeQuery(
                "UPDATE schools SET admin_user_id = ?, updated_at = NOW() WHERE school_id = ?",
                'ii',
                [$userId, $schoolId]
            );

            if ($linked === false) {
                rollbackTransaction();
                return ['success' => false, 'message' => 'School account could not be linked to the admin user.'];
            }

            logActivity($userId, 'school_registered', 'School Registration', 'New school registration submitted: ' . $schoolName);

            commitTransaction();
            return [
                'success' => true,
                'message' => 'Registration submitted. Waiting for Super Admin approval.',
                'school_id' => $schoolId,
                'user_id' => $userId,
            ];
        } catch (Exception $e) {
            rollbackTransaction();
            return ['success' => false, 'message' => 'Unable to submit the registration.'];
        }
    }
}

if (!function_exists('schoolRegistrationGetRequests')) {
    function schoolRegistrationGetRequests($status = '', $limit = 50) {
        ensureSchoolRegistrationSchema();

        $status = strtolower(trim((string) $status));
        $limit = max(1, min(200, intval($limit)));
        $query = "SELECT
                    s.*,
                    u.username AS login_username,
                    u.email AS login_email,
                    u.mobile AS login_mobile,
                    u.status AS user_status,
                    u.status_reason AS user_status_reason,
                    u.created_at AS user_created_at
                  FROM schools s
                  LEFT JOIN users u ON s.admin_user_id = u.user_id
                  WHERE 1=1";
        $types = '';
        $params = [];

        if (in_array($status, ['pending', 'approved', 'rejected', 'blocked'], true)) {
            $query .= " AND s.status = ?";
            $types .= 's';
            $params[] = $status;
        }

        $query .= " ORDER BY s.created_at DESC, s.school_id DESC LIMIT {$limit}";
        return empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    }
}

if (!function_exists('schoolRegistrationUpdateStatus')) {
    function schoolRegistrationUpdateStatus($schoolId, $status, $currentUserId, $reason = '') {
        ensureSchoolRegistrationSchema();

        $schoolId = intval($schoolId);
        $currentUserId = intval($currentUserId);
        $status = schoolRegistrationNormalizeStatus($status, 'pending');
        $reason = trim((string) $reason);

        if ($schoolId <= 0) {
            return ['success' => false, 'message' => 'Invalid school request.'];
        }

        $school = schoolRegistrationGetSchoolById($schoolId);
        if (!$school) {
            return ['success' => false, 'message' => 'School request not found.'];
        }

        if (empty($school['admin_user_id'])) {
            return ['success' => false, 'message' => 'Linked admin user not found.'];
        }

        beginTransaction();
        try {
            $schoolStatus = $status === 'active' ? 'approved' : $status;
            $approvedBy = null;
            $approvedAt = null;
            $schoolDefaultReason = match ($schoolStatus) {
                'rejected' => 'Rejected by Super Admin.',
                'blocked' => 'Blocked by Super Admin.',
                default => null,
            };
            if ($schoolStatus === 'approved') {
                $approvedBy = $currentUserId;
                $approvedAt = date('Y-m-d H:i:s');
            }

            $schoolUpdate = executeQuery(
                "UPDATE schools SET status = ?, status_reason = ?, approved_by = ?, approved_at = ?, updated_at = NOW() WHERE school_id = ?",
                'ssisi',
                [
                    $schoolStatus,
                    $reason !== '' ? $reason : $schoolDefaultReason,
                    $approvedBy,
                    $approvedAt,
                    $schoolId,
                ]
            );

            if ($schoolUpdate === false) {
                rollbackTransaction();
                return ['success' => false, 'message' => 'Unable to update the school request.'];
            }

            $userStatus = match ($schoolStatus) {
                'approved' => 'active',
                'rejected' => 'rejected',
                'blocked' => 'blocked',
                default => 'pending',
            };

            $userActive = $userStatus === 'active' ? 1 : 0;
            $userDefaultReason = match ($userStatus) {
                'active' => null,
                'rejected' => 'Rejected by Super Admin.',
                'blocked' => 'Blocked by Super Admin.',
                default => 'Waiting for Super Admin approval.',
            };

            $userUpdate = executeQuery(
                "UPDATE users SET status = ?, status_reason = ?, is_active = ?, updated_at = NOW() WHERE user_id = ?",
                'ssii',
                [
                    $userStatus,
                    $reason !== '' ? $reason : $userDefaultReason,
                    $userActive,
                    intval($school['admin_user_id']),
                ]
            );

            if ($userUpdate === false) {
                rollbackTransaction();
                return ['success' => false, 'message' => 'Unable to update the linked admin account.'];
            }

            if ($schoolStatus === 'approved') {
                schoolRegistrationSyncApprovedSchoolSettings($school);
                schoolRegistrationSeedDefaultAdminPermissions(intval($school['admin_user_id']));
                schoolRegistrationSeedSchoolRolePermissions($schoolId);
            }

            $actionName = match ($schoolStatus) {
                'approved' => 'admin_approved',
                'rejected' => 'admin_rejected',
                'blocked' => 'admin_blocked',
                default => 'school_status_updated',
            };

            $description = 'School #' . $schoolId . ' (' . ($school['school_name'] ?? '-') . ') marked as ' . $schoolStatus . '.';
            if ($reason !== '') {
                $description .= ' Reason: ' . $reason;
            }

            logActivity($currentUserId, $actionName, 'School Requests', $description);

            commitTransaction();
            return ['success' => true, 'message' => 'School request updated successfully.'];
        } catch (Exception $e) {
            rollbackTransaction();
            return ['success' => false, 'message' => 'Unable to update the school request.'];
        }
    }
}

if (!function_exists('schoolRegistrationDeleteRequest')) {
    function schoolRegistrationDeleteRequest($schoolId, $currentUserId) {
        ensureSchoolRegistrationSchema();

        $schoolId = intval($schoolId);
        $currentUserId = intval($currentUserId);

        if ($schoolId <= 0) {
            return ['success' => false, 'message' => 'Invalid school request.'];
        }

        $school = schoolRegistrationGetSchoolById($schoolId);
        if (!$school) {
            return ['success' => false, 'message' => 'School request not found.'];
        }

        $status = strtolower(trim((string)($school['status'] ?? 'pending')));
        if ($status === 'approved' || $status === 'active') {
            return ['success' => false, 'message' => 'Approved school requests cannot be deleted from here.'];
        }

        beginTransaction();
        try {
            if (!empty($school['admin_user_id'])) {
                $deletedUser = executeQuery(
                    "DELETE FROM users WHERE user_id = ?",
                    'i',
                    [intval($school['admin_user_id'])]
                );

                if ($deletedUser === false) {
                    rollbackTransaction();
                    return ['success' => false, 'message' => 'Unable to delete the linked admin account.'];
                }
            }

            $deletedSchool = executeQuery(
                "DELETE FROM schools WHERE school_id = ?",
                'i',
                [$schoolId]
            );

            if ($deletedSchool === false) {
                rollbackTransaction();
                return ['success' => false, 'message' => 'Unable to delete the school request.'];
            }

            $description = 'Deleted school request #' . $schoolId . ' (' . ($school['school_name'] ?? '-') . ').';
            logActivity($currentUserId, 'school_deleted', 'School Requests', $description);

            commitTransaction();
            return ['success' => true, 'message' => 'School request deleted successfully.'];
        } catch (Exception $e) {
            rollbackTransaction();
            return ['success' => false, 'message' => 'Unable to delete the school request.'];
        }
    }
}

if (!function_exists('schoolRegistrationGenerateAdminPassword')) {
    function schoolRegistrationGenerateAdminPassword($schoolId, $currentUserId, $length = 10) {
        ensureSchoolRegistrationSchema();

        $schoolId = intval($schoolId);
        $currentUserId = intval($currentUserId);

        if ($schoolId <= 0) {
            return ['success' => false, 'message' => 'Invalid school request.'];
        }

        $school = schoolRegistrationGetSchoolById($schoolId);
        if (!$school) {
            return ['success' => false, 'message' => 'School request not found.'];
        }

        $newPassword = schoolRegistrationGeneratePassword($length);
        $adminUserId = intval($school['admin_user_id'] ?? 0);
        $adminUser = $adminUserId > 0
            ? fetchOne("SELECT user_id FROM users WHERE user_id = ? LIMIT 1", 'i', [$adminUserId])
            : null;

        if ($adminUser) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                return ['success' => false, 'message' => 'Unable to generate a password.'];
            }

            $updated = executeQuery(
                "UPDATE users SET password = ?, status = 'active', is_active = 1, updated_at = NOW() WHERE user_id = ?",
                'si',
                [$hashedPassword, $adminUserId]
            );

            if ($updated === false) {
                return ['success' => false, 'message' => 'Unable to update the admin password.'];
            }

            schoolRegistrationSeedDefaultAdminPermissions($adminUserId);
            schoolRegistrationSeedSchoolRolePermissions($schoolId);
        } else {
            $newAdmin = registerUser([
                'username' => trim((string)($school['admin_username'] ?? '')),
                'email' => trim((string)($school['admin_email'] ?? '')),
                'password' => $newPassword,
                'full_name' => trim((string)($school['admin_name'] ?? '')),
                'role' => 'admin',
                'mobile' => trim((string)($school['admin_mobile'] ?? '')),
                'school_id' => $schoolId,
                'status' => 'active',
                'status_reason' => null,
                'is_active' => 1,
            ]);

            if (empty($newAdmin['success']) || empty($newAdmin['user_id'])) {
                return ['success' => false, 'message' => $newAdmin['message'] ?? 'Unable to recreate the admin account.'];
            }

            $linked = executeQuery(
                "UPDATE schools SET admin_user_id = ?, updated_at = NOW() WHERE school_id = ?",
                'ii',
                [intval($newAdmin['user_id']), $schoolId]
            );

            if ($linked === false) {
                return ['success' => false, 'message' => 'Admin account created, but the school could not be relinked.'];
            }

            schoolRegistrationSeedDefaultAdminPermissions(intval($newAdmin['user_id']));
            schoolRegistrationSeedSchoolRolePermissions($schoolId);
        }

        $description = 'Generated a login password for school #' . $schoolId . ' (' . ($school['school_name'] ?? '-') . ').';
        if (!$adminUser) {
            $description .= ' A missing admin account was recreated.';
        }
        logActivity($currentUserId, 'school_password_generated', 'School Requests', $description);

        return [
            'success' => true,
            'message' => $adminUser ? 'New login password generated successfully.' : 'Admin account recreated and new password generated successfully.',
            'password' => $newPassword,
        ];
    }
}

if (!function_exists('schoolRegistrationSeedDefaultAdminPermissions')) {
    function schoolRegistrationSeedDefaultAdminPermissions($userId) {
        $userId = intval($userId);
        if ($userId <= 0) {
            return false;
        }

        $tableCheck = fetchAll("SHOW TABLES LIKE 'user_permissions'");
        if (count($tableCheck) === 0) {
            return false;
        }

        $defaultPermissions = [
            ['dashboard', 1, 0, 0, 0],
            ['students', 1, 1, 1, 1],
            ['fees', 1, 1, 1, 1],
            ['marks', 1, 1, 1, 1],
            ['reports', 1, 0, 0, 0],
            ['settings', 1, 1, 1, 1],
            ['users', 1, 1, 1, 1],
            ['sms', 1, 1, 0, 0],
        ];

        try {
            foreach ($defaultPermissions as $permission) {
                [$moduleName, $canView, $canAdd, $canEdit, $canDelete] = $permission;
                $existingPermission = fetchOne(
                    "SELECT permission_id FROM user_permissions WHERE user_id = ? AND module_name = ? LIMIT 1",
                    'is',
                    [$userId, $moduleName]
                );

                if ($existingPermission) {
                    continue;
                }

                $result = executeQuery(
                    "INSERT INTO user_permissions (user_id, module_name, can_view, can_add, can_edit, can_delete)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'isiiii',
                    [$userId, $moduleName, $canView, $canAdd, $canEdit, $canDelete]
                );

                if ($result === false) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('Failed to seed default admin permissions: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('schoolRegistrationSeedSchoolRolePermissions')) {
    function schoolRegistrationSeedSchoolRolePermissions($schoolId) {
        $schoolId = intval($schoolId);
        if ($schoolId <= 0) {
            return false;
        }

        if (function_exists('ensureRolePermissionsSchema')) {
            ensureRolePermissionsSchema();
        }

        $globalPermissions = fetchAll(
            "SELECT role_name, permissions
             FROM role_permissions
             WHERE school_id = 0"
        );

        if (empty($globalPermissions)) {
            return false;
        }

        try {
            foreach ($globalPermissions as $permissionRow) {
                $roleName = trim((string)($permissionRow['role_name'] ?? ''));
                if ($roleName === '') {
                    continue;
                }

                $existingPermission = fetchOne(
                    "SELECT id FROM role_permissions WHERE school_id = ? AND role_name = ? LIMIT 1",
                    'is',
                    [$schoolId, $roleName]
                );

                if ($existingPermission) {
                    continue;
                }

                $result = executeQuery(
                    "INSERT INTO role_permissions (school_id, role_name, permissions, created_at, updated_at)
                     VALUES (?, ?, ?, NOW(), NOW())",
                    'iss',
                    [$schoolId, $roleName, (string)($permissionRow['permissions'] ?? '[]')]
                );

                if ($result === false) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('Failed to seed school role permissions: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('schoolRegistrationGetLoginStatusMessage')) {
    function schoolRegistrationGetLoginStatusMessage(array $user): string {
        $role = strtolower(trim((string)($user['role'] ?? '')));
        $status = strtolower(trim((string)($user['status'] ?? '')));
        $reason = trim((string)($user['status_reason'] ?? ''));
        $isActive = intval($user['is_active'] ?? 0) === 1;

        $school = null;
        if (!empty($user['school_id'])) {
            $school = schoolRegistrationGetSchoolById(intval($user['school_id']));
        }

        if ($role === 'admin' && $school) {
            $schoolStatus = strtolower(trim((string)($school['status'] ?? '')));
            if (in_array($schoolStatus, ['pending', 'rejected', 'blocked'], true)) {
                $status = $schoolStatus;
                $schoolReason = trim((string)($school['status_reason'] ?? ''));
                if ($schoolReason !== '') {
                    $reason = $schoolReason;
                }
            }
        }

        if ($status === '' && !$isActive) {
            $status = 'inactive';
        }

        if ($role === 'admin') {
            if ($status === 'pending') {
                return 'Your school registration is pending Super Admin approval. Please wait for approval.';
            }
            if ($status === 'rejected') {
                return $reason !== ''
                    ? 'Your school registration was rejected. ' . $reason
                    : 'Your school registration was rejected by the Super Admin.';
            }
            if ($status === 'blocked') {
                return $reason !== ''
                    ? 'Your school account has been blocked. ' . $reason
                    : 'Your school account has been blocked by the Super Admin.';
            }
            if (!$isActive) {
                return 'Your admin account is inactive. Please contact the school office.';
            }
            return '';
        }

        if ($role === 'student') {
            if ($status === 'pending') {
                return 'Your student account is pending approval. Please wait for the super admin to approve it.';
            }
            if ($status === 'rejected') {
                return $reason !== ''
                    ? 'Your student application was rejected. ' . $reason
                    : 'Your student application was rejected by the super admin.';
            }
            if ($status === 'blocked') {
                return $reason !== ''
                    ? 'Your student account has been blocked. ' . $reason
                    : 'Your student account has been blocked by the super admin.';
            }

            if (!$isActive) {
                $application = function_exists('getStudentApplicationByUserId')
                    ? getStudentApplicationByUserId(intval($user['user_id'] ?? 0))
                    : null;

                if ($application && ($application['status'] ?? '') === 'Rejected') {
                    $applicationReason = trim((string)($application['rejection_reason'] ?? ''));
                    return $applicationReason !== ''
                        ? 'Your student application was rejected. ' . $applicationReason
                        : 'Your student application was rejected by the super admin.';
                }

                return 'Your student account is pending approval. Please wait for the super admin to approve it.';
            }

            return '';
        }

        if ($status === 'rejected') {
            return $reason !== '' ? $reason : 'Your account was rejected. Please contact the school office.';
        }

        if ($status === 'blocked') {
            return $reason !== '' ? $reason : 'Your account is blocked. Please contact the school office.';
        }

        if ($status === 'pending' || !$isActive) {
            return 'Your account is inactive. Please contact the school office.';
        }

        return '';
    }
}
