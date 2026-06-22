<?php
/**
 * Common Functions
 * Utility functions used throughout the application
 */

/**
 * Sanitize input data
 *
 * @param string $data
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format date
 *
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = DATE_FORMAT) {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format currency
 *
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

/**
 * Generate unique ID
 *
 * @param string $prefix
 * @param int $length
 * @return string
 */
function generateUniqueId($prefix = '', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $prefix . $id;
}

/**
 * Get next admission number
 *
 * @return string
 */
function getNextAdmissionNumber() {
    $query = "SELECT admission_no FROM students ORDER BY student_id DESC LIMIT 1";
    $result = fetchOne($query);

    if ($result) {
        // Extract number from last admission number
        preg_match('/\d+/', $result['admission_no'], $matches);
        $lastNumber = isset($matches[0]) ? intval($matches[0]) : 0;
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    $settings = getSchoolSettings();
    $prefix = $settings['admission_prefix'] ?? 'STU';

    return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}

/**
 * Get next receipt number
 *
 * @return string
 */
function getNextReceiptNumber() {
    $query = "SELECT receipt_no FROM fee_receipts ORDER BY receipt_id DESC LIMIT 1";
    $result = fetchOne($query);

    if ($result) {
        preg_match('/\d+/', $result['receipt_no'], $matches);
        $lastNumber = isset($matches[0]) ? intval($matches[0]) : 0;
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    $settings = getSchoolSettings();
    $prefix = $settings['receipt_prefix'] ?? 'REC';

    return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}

/**
 * Get school settings
 *
 * @return array
 */
if (!function_exists('ensureSchoolSettingsSchema')) {
    function ensureSchoolSettingsSchema() {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $checked = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("School settings schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $ensureColumn('school_settings', 'school_id', "school_id int(11) DEFAULT NULL AFTER setting_id");
        $ensureColumn('school_settings', 'affiliation_no', "affiliation_no varchar(50) DEFAULT NULL AFTER school_email");
        $ensureColumn('school_settings', 'school_code', "school_code varchar(50) DEFAULT NULL AFTER affiliation_no");
        $ensureColumn('school_settings', 'udise_code', "udise_code varchar(50) DEFAULT NULL AFTER school_code");
        $ensureColumn('school_settings', 'teacher_signature', "teacher_signature varchar(255) DEFAULT NULL AFTER udise_code");
        $ensureColumn('school_settings', 'class_teacher_signature', "class_teacher_signature varchar(255) DEFAULT NULL AFTER teacher_signature");
        $ensureColumn('school_settings', 'principal_signature', "principal_signature varchar(255) DEFAULT NULL AFTER class_teacher_signature");
        $ensureColumn('school_settings', 'transfer_certificate_prefix', "transfer_certificate_prefix varchar(30) DEFAULT 'TC/' AFTER receipt_prefix");
        $ensureColumn('school_settings', 'transfer_certificate_last_no', "transfer_certificate_last_no int(11) NOT NULL DEFAULT 0 AFTER transfer_certificate_prefix");
        $ensureColumn('school_settings', 'attendance_scan_mode', "attendance_scan_mode enum('daily','period') NOT NULL DEFAULT 'daily' AFTER transfer_certificate_last_no");
        $ensureColumn('school_settings', 'attendance_class_start_time', "attendance_class_start_time time NOT NULL DEFAULT '08:00:00' AFTER attendance_scan_mode");
        $ensureColumn('school_settings', 'attendance_period_duration_minutes', "attendance_period_duration_minutes int(11) NOT NULL DEFAULT 45 AFTER attendance_class_start_time");
        $ensureColumn('school_settings', 'attendance_auto_alert_enabled', "attendance_auto_alert_enabled tinyint(1) NOT NULL DEFAULT 1 AFTER attendance_period_duration_minutes");
        $ensureColumn('school_settings', 'attendance_absent_message_template', "attendance_absent_message_template text DEFAULT NULL AFTER attendance_auto_alert_enabled");
        $ensureColumn('school_settings', 'company_name', "company_name varchar(200) DEFAULT NULL AFTER school_name");
        $ensureColumn('school_settings', 'company_tagline', "company_tagline varchar(150) DEFAULT NULL AFTER company_name");
        $ensureColumn('school_settings', 'company_address', "company_address text DEFAULT NULL AFTER company_tagline");
        $ensureColumn('school_settings', 'company_phone', "company_phone varchar(20) DEFAULT NULL AFTER company_address");
        $ensureColumn('school_settings', 'company_email', "company_email varchar(100) DEFAULT NULL AFTER company_phone");
        $ensureColumn('school_settings', 'company_website', "company_website varchar(255) DEFAULT NULL AFTER company_email");
        $ensureColumn('school_settings', 'company_logo', "company_logo varchar(255) DEFAULT NULL AFTER company_website");
        $ensureColumn('school_settings', 'enabled_roles', "enabled_roles text DEFAULT NULL AFTER company_logo");
        $ensureColumn('school_settings', 'student_add_limit', "student_add_limit int(11) NOT NULL DEFAULT 0 AFTER enabled_roles");
        $ensureColumn('school_settings', 'subscription_plan', "subscription_plan enum('free','premium','enterprise') NOT NULL DEFAULT 'free' AFTER enabled_roles");
        $ensureColumn('school_settings', 'subscription_price', "subscription_price decimal(10,2) NOT NULL DEFAULT 0.00 AFTER subscription_plan");
        $ensureColumn('school_settings', 'subscription_currency_code', "subscription_currency_code varchar(10) DEFAULT 'INR' AFTER subscription_price");
        $ensureColumn('school_settings', 'subscription_billing_cycle', "subscription_billing_cycle enum('monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly' AFTER subscription_currency_code");
        $ensureColumn('school_settings', 'subscription_status', "subscription_status enum('active','pending','expired','cancelled','trial') NOT NULL DEFAULT 'active' AFTER subscription_billing_cycle");
        $ensureColumn('school_settings', 'ads_enabled', "ads_enabled tinyint(1) NOT NULL DEFAULT 1 AFTER subscription_status");
        $ensureColumn('school_settings', 'subscription_started_at', "subscription_started_at datetime DEFAULT NULL AFTER ads_enabled");
        $ensureColumn('school_settings', 'subscription_expires_at', "subscription_expires_at datetime DEFAULT NULL AFTER subscription_started_at");
        $ensureColumn('school_settings', 'subscription_gateway', "subscription_gateway varchar(50) DEFAULT 'manual' AFTER subscription_expires_at");
        $ensureColumn('school_settings', 'subscription_gateway_reference', "subscription_gateway_reference varchar(150) DEFAULT NULL AFTER subscription_gateway");
        $ensureColumn('school_settings', 'subscription_payment_link', "subscription_payment_link text DEFAULT NULL AFTER subscription_gateway_reference");
        $ensureColumn('school_settings', 'subscription_notes', "subscription_notes text DEFAULT NULL AFTER subscription_payment_link");

        $loginTextColumns = [
            'login_brand_subtitle' => "login_brand_subtitle varchar(150) DEFAULT '" . APP_NAME . "' AFTER transfer_certificate_last_no",
            'login_hero_title' => "login_hero_title varchar(255) DEFAULT 'Secure access for staff, records, and school operations.' AFTER login_brand_subtitle",
            'login_hero_subtitle' => "login_hero_subtitle text DEFAULT NULL AFTER login_hero_title",
            'login_pill_1' => "login_pill_1 varchar(120) DEFAULT 'Role-based access' AFTER login_hero_subtitle",
            'login_pill_2' => "login_pill_2 varchar(120) DEFAULT 'Reports and receipts' AFTER login_pill_1",
            'login_pill_3' => "login_pill_3 varchar(120) DEFAULT 'Student management' AFTER login_pill_2",
            'login_metric_1_title' => "login_metric_1_title varchar(60) DEFAULT 'Secure' AFTER login_pill_3",
            'login_metric_1_text' => "login_metric_1_text varchar(180) DEFAULT 'Controlled by user role' AFTER login_metric_1_title",
            'login_metric_2_title' => "login_metric_2_title varchar(60) DEFAULT 'Fast' AFTER login_metric_1_text",
            'login_metric_2_text' => "login_metric_2_text varchar(180) DEFAULT 'Quick access on any device' AFTER login_metric_2_title",
            'login_metric_3_title' => "login_metric_3_title varchar(60) DEFAULT 'Reliable' AFTER login_metric_2_text",
            'login_metric_3_text' => "login_metric_3_text varchar(180) DEFAULT 'Daily school operations ready' AFTER login_metric_3_title",
            'login_card_subtitle' => "login_card_subtitle varchar(120) DEFAULT 'Staff Login' AFTER login_metric_3_text",
            'login_card_title' => "login_card_title varchar(150) DEFAULT 'Login to Your Account' AFTER login_card_subtitle",
            'login_username_label' => "login_username_label varchar(120) DEFAULT 'Username or Email' AFTER login_card_title",
            'login_username_placeholder' => "login_username_placeholder varchar(150) DEFAULT 'Enter username or email' AFTER login_username_label",
            'login_password_label' => "login_password_label varchar(120) DEFAULT 'Password' AFTER login_username_placeholder",
            'login_password_placeholder' => "login_password_placeholder varchar(150) DEFAULT 'Enter password' AFTER login_password_label",
            'login_remember_me_label' => "login_remember_me_label varchar(120) DEFAULT 'Remember me' AFTER login_password_placeholder",
            'login_button_text' => "login_button_text varchar(80) DEFAULT 'Login' AFTER login_remember_me_label",
            'login_forgot_password_text' => "login_forgot_password_text varchar(120) DEFAULT 'Forgot Password?' AFTER login_button_text",
            'login_forgot_username_text' => "login_forgot_username_text varchar(120) DEFAULT 'Forgot Username?' AFTER login_forgot_password_text",
            'login_student_login_text' => "login_student_login_text varchar(120) DEFAULT 'Student Login' AFTER login_forgot_username_text",
            'login_alert_registered_text' => "login_alert_registered_text varchar(255) DEFAULT 'Your account has been created. Please login with your new credentials.' AFTER login_student_login_text",
            'login_alert_school_registered_text' => "login_alert_school_registered_text varchar(255) DEFAULT 'School registration submitted. Waiting for Super Admin approval.' AFTER login_alert_registered_text",
            'login_alert_reset_text' => "login_alert_reset_text varchar(255) DEFAULT 'Password updated successfully. You can now login with the new password.' AFTER login_alert_school_registered_text",
        ];

        foreach ($loginTextColumns as $column => $ddl) {
            $ensureColumn('school_settings', $column, $ddl);
        }

        return true;
    }
}

if (!function_exists('ensureStudentSchema')) {
    function ensureStudentSchema() {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $checked = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("Student schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $ensureColumn('students', 'school_id', "school_id int(11) DEFAULT NULL AFTER student_id");
        $ensureColumn('students', 'attendance_auto_alert_disabled', "attendance_auto_alert_disabled tinyint(1) NOT NULL DEFAULT 0 AFTER school_id");

        $remindersTable = $conn->query("SHOW TABLES LIKE 'student_reminders'");
        if ($remindersTable && $remindersTable->num_rows > 0) {
            $ensureColumn('student_reminders', 'reminder_type', "reminder_type varchar(50) NOT NULL DEFAULT 'General' AFTER reminder_text");
            $ensureColumn('student_reminders', 'due_date', "due_date date DEFAULT NULL AFTER priority");
            $ensureColumn('student_reminders', 'status', "status enum('Active','Resolved') NOT NULL DEFAULT 'Active' AFTER is_resolved");
            $ensureColumn('student_reminders', 'updated_at', "updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }

        $indexCheck = $conn->query("SHOW INDEX FROM students WHERE Key_name = 'idx_student_school_id'");
        if (!$indexCheck || $indexCheck->num_rows === 0) {
            if (!$conn->query("ALTER TABLE students ADD KEY idx_student_school_id (school_id)")) {
                error_log('Student schema index update failed: ' . $conn->error);
            }
        }

        return true;
    }
}

if (!function_exists('ensureFeeModuleSchema')) {
    function ensureFeeModuleSchema() {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $checked = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("Fee schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $ensureIndex = function (string $table, string $indexName, string $indexSql) use ($conn) {
            $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD INDEX `$indexName` ($indexSql)")) {
                    error_log("Fee schema index update failed for $table.$indexName: " . $conn->error);
                }
            }
        };

        $ensureColumn('fee_heads', 'school_id', "school_id int(11) DEFAULT NULL AFTER fee_head_id");
        $ensureColumn('fee_structure', 'school_id', "school_id int(11) DEFAULT NULL AFTER fee_structure_id");

        $ensureIndex('fee_heads', 'idx_fee_heads_school_id', '`school_id`');
        $ensureIndex('fee_structure', 'idx_fee_structure_school_id', '`school_id`');

        // Backfill fee structure rows from their linked student so existing school data stays visible.
        $conn->query(
            "UPDATE fee_structure fs
             JOIN students s ON s.student_id = fs.student_id
             SET fs.school_id = COALESCE(fs.school_id, s.school_id)
             WHERE (fs.school_id IS NULL OR fs.school_id = 0)
               AND COALESCE(s.school_id, 0) > 0"
        );

        // Attach each fee head to the single school that uses it, when the usage is unambiguous.
        $conn->query(
            "UPDATE fee_heads fh
             JOIN (
                 SELECT fee_head_id, MAX(school_id) AS school_id
                 FROM fee_structure
                 WHERE is_active = 1 AND COALESCE(school_id, 0) > 0
                 GROUP BY fee_head_id
                 HAVING COUNT(DISTINCT school_id) = 1
             ) scoped ON scoped.fee_head_id = fh.fee_head_id
             SET fh.school_id = scoped.school_id
             WHERE fh.school_id IS NULL OR fh.school_id = 0"
        );

        return true;
    }
}

if (!function_exists('ensureAttendanceSchema')) {
    function ensureAttendanceSchema() {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $checked = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS `student_attendance` (
                `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
                `school_id` int(11) NOT NULL DEFAULT 0,
                `student_id` int(11) NOT NULL,
                `attendance_date` date NOT NULL,
                `attendance_period_no` int(11) NOT NULL DEFAULT 0,
                `status` varchar(20) NOT NULL DEFAULT 'Present',
                `marked_by` int(11) DEFAULT NULL,
                `qr_token` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`attendance_id`),
                UNIQUE KEY `idx_student_attendance_unique` (`school_id`, `student_id`, `attendance_date`, `attendance_period_no`),
                KEY `idx_student_attendance_school_date` (`school_id`, `attendance_date`),
                KEY `idx_student_attendance_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $periodColumnCheck = $conn->query("SHOW COLUMNS FROM `student_attendance` LIKE 'attendance_period_no'");
        if ($periodColumnCheck && $periodColumnCheck->num_rows > 0) {
            $indexColumns = [];
            $indexResult = $conn->query(
                "SELECT COLUMN_NAME
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'student_attendance'
                   AND index_name = 'idx_student_attendance_unique'
                 ORDER BY SEQ_IN_INDEX"
            );

            if ($indexResult) {
                while ($indexRow = $indexResult->fetch_assoc()) {
                    $indexColumns[] = $indexRow['COLUMN_NAME'] ?? '';
                }
            }

            $desiredColumns = ['school_id', 'student_id', 'attendance_date', 'attendance_period_no'];
            if (empty($indexColumns)) {
                if (!$conn->query("ALTER TABLE `student_attendance` ADD UNIQUE KEY `idx_student_attendance_unique` (`school_id`, `student_id`, `attendance_date`, `attendance_period_no`)")) {
                    error_log('Attendance schema unique key creation failed: ' . $conn->error);
                }
            } elseif ($indexColumns !== $desiredColumns) {
                $conn->query("ALTER TABLE `student_attendance` DROP INDEX `idx_student_attendance_unique`");
                if (!$conn->query("ALTER TABLE `student_attendance` ADD UNIQUE KEY `idx_student_attendance_unique` (`school_id`, `student_id`, `attendance_date`, `attendance_period_no`)")) {
                    error_log('Attendance schema unique key update failed: ' . $conn->error);
                }
            }
        }

        return true;
    }
}

if (!function_exists('attendanceNormalizeTimeValue')) {
    function attendanceNormalizeTimeValue($value, $fallback = '08:00:00') {
        $value = trim((string) $value);
        $fallback = trim((string) $fallback);
        if ($fallback === '') {
            $fallback = '08:00:00';
        }

        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('H:i:s', $timestamp);
        }

        return $fallback;
    }
}

if (!function_exists('attendanceGetScanContext')) {
    function attendanceGetScanContext(array $schoolSettings = [], ?DateTimeInterface $moment = null): array {
        $mode = strtolower(trim((string)($schoolSettings['attendance_scan_mode'] ?? 'daily')));
        if (!in_array($mode, ['daily', 'period'], true)) {
            $mode = 'daily';
        }

        if ($mode === 'daily') {
            return [
                'mode' => 'daily',
                'period_no' => 0,
                'period_label' => 'Daily Attendance',
                'period_start' => null,
                'period_end' => null,
            ];
        }

        $moment = $moment ?: new DateTimeImmutable('now');
        $startTime = attendanceNormalizeTimeValue($schoolSettings['attendance_class_start_time'] ?? '08:00:00', '08:00:00');
        $durationMinutes = intval($schoolSettings['attendance_period_duration_minutes'] ?? 45);
        if ($durationMinutes <= 0) {
            $durationMinutes = 45;
        }

        $startAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $moment->format('Y-m-d') . ' ' . $startTime);
        if (!$startAt) {
            $startAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $moment->format('Y-m-d') . ' 08:00:00');
        }
        if (!$startAt) {
            $startAt = $moment;
        }

        $minutesSinceStart = (int) floor(($moment->getTimestamp() - $startAt->getTimestamp()) / 60);
        if ($minutesSinceStart < 0) {
            $minutesSinceStart = 0;
        }

        $periodNo = intdiv($minutesSinceStart, $durationMinutes) + 1;
        $periodStart = $startAt->modify('+' . (($periodNo - 1) * $durationMinutes) . ' minutes');
        $periodEnd = $periodStart->modify('+' . $durationMinutes . ' minutes');

        return [
            'mode' => 'period',
            'period_no' => $periodNo,
            'period_label' => 'Period ' . $periodNo,
            'period_start' => $periodStart->format('h:i A'),
            'period_end' => $periodEnd->format('h:i A'),
        ];
    }
}

if (!function_exists('attendanceGetDefaultAbsentSmsTemplate')) {
    function attendanceGetDefaultAbsentSmsTemplate() {
        return 'Dear Parent, {student_details} was marked ABSENT {period_text} on {date} at {school_name}. Please contact the school office if this needs correction.';
    }
}

if (!function_exists('attendanceBuildAbsentSmsMessage')) {
    function attendanceBuildAbsentSmsMessage(array $student, $attendanceDate, array $schoolSettings = [], array $attendanceContext = []) {
        $studentName = trim((string)($student['student_name'] ?? 'Student'));
        if ($studentName === '') {
            $studentName = 'Student';
        }

        $admissionNo = trim((string)($student['admission_no'] ?? ''));
        if ($admissionNo === '') {
            $admissionNo = 'N/A';
        }

        $rollNo = trim((string)($student['roll_no'] ?? ''));
        if ($rollNo === '') {
            $rollNo = 'N/A';
        }

        $fatherName = trim((string)($student['father_name'] ?? ''));
        $className = trim((string)($student['class_name'] ?? ''));
        $sectionName = trim((string)($student['section_name'] ?? ''));
        $classDisplay = trim($className . ($sectionName !== '' ? ' - ' . $sectionName : ''));
        if ($classDisplay === '') {
            $classDisplay = 'N/A';
        }

        $studentDetails = implode('; ', array_filter([
            'Name: ' . $studentName,
            'Adm No.: ' . $admissionNo,
            'Roll No.: ' . $rollNo,
            'Class: ' . $classDisplay,
            'Father: ' . ($fatherName !== '' ? $fatherName : 'N/A'),
        ], static function ($part) {
            return trim((string) $part) !== '';
        }));

        $schoolName = trim((string)($schoolSettings['school_name'] ?? APP_NAME));
        if ($schoolName === '') {
            $schoolName = APP_NAME;
        }

        $attendanceDate = trim((string)$attendanceDate);
        $dateLabel = $attendanceDate !== '' ? date('d M Y', strtotime($attendanceDate)) : date('d M Y');

        $periodMode = strtolower(trim((string)($attendanceContext['mode'] ?? 'daily'))) === 'period';
        $periodLabel = trim((string)($attendanceContext['period_label'] ?? ''));
        $periodStart = trim((string)($attendanceContext['period_start'] ?? ''));
        $periodEnd = trim((string)($attendanceContext['period_end'] ?? ''));

        $periodText = '';
        if ($periodMode && $periodLabel !== '') {
            $periodText = 'in ' . $periodLabel;
            if ($periodStart !== '' && $periodEnd !== '') {
                $periodText .= ' (' . $periodStart . ' - ' . $periodEnd . ')';
            }
        }

        $template = trim((string)($schoolSettings['attendance_absent_message_template'] ?? ''));
        if ($template === '') {
            $template = attendanceGetDefaultAbsentSmsTemplate();
        }

        $message = strtr($template, [
            '{student_name}' => $studentName,
            '{admission_no}' => $admissionNo,
            '{roll_no}' => $rollNo,
            '{father_name}' => $fatherName !== '' ? $fatherName : 'N/A',
            '{class_name}' => $className !== '' ? $className : 'N/A',
            '{section_name}' => $sectionName !== '' ? $sectionName : 'N/A',
            '{class_display}' => $classDisplay,
            '{student_details}' => $studentDetails !== '' ? $studentDetails : 'Student details not available',
            '{school_name}' => $schoolName,
            '{date}' => $dateLabel,
            '{date_iso}' => $attendanceDate !== '' ? $attendanceDate : date('Y-m-d'),
            '{period_text}' => $periodText,
            '{period_label}' => $periodLabel !== '' ? $periodLabel : 'Daily Attendance',
            '{period_start}' => $periodStart !== '' ? $periodStart : '--',
            '{period_end}' => $periodEnd !== '' ? $periodEnd : '--',
        ]);

        $message = preg_replace('/\s+/u', ' ', (string) $message);
        return trim((string) $message);
    }
}

if (!function_exists('ensureRolePermissionsSchema')) {
    function ensureRolePermissionsSchema() {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $checked = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS `role_permissions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `school_id` int(11) NOT NULL DEFAULT 0,
                `role_name` varchar(50) NOT NULL,
                `permissions` text NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_role_permissions_school_role` (`school_id`, `role_name`),
                KEY `idx_role_permissions_school_id` (`school_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("Role permissions schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $ensureColumn('role_permissions', 'school_id', "school_id int(11) NOT NULL DEFAULT 0 AFTER id");

        if (!$conn->query("UPDATE role_permissions SET school_id = 0 WHERE school_id IS NULL OR school_id = ''")) {
            error_log('Failed to normalize role_permissions.school_id values: ' . $conn->error);
        }

        $oldIndex = $conn->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'role_name'");
        if ($oldIndex && $oldIndex->num_rows > 0) {
            if (!$conn->query("ALTER TABLE role_permissions DROP INDEX role_name")) {
                error_log('Failed to drop legacy role_permissions role_name index: ' . $conn->error);
            }
        }

        $newIndex = $conn->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'idx_role_permissions_school_role'");
        if (!$newIndex || $newIndex->num_rows === 0) {
            if (!$conn->query("ALTER TABLE role_permissions ADD UNIQUE KEY idx_role_permissions_school_role (school_id, role_name)")) {
                error_log('Failed to create school-scoped role_permissions index: ' . $conn->error);
            }
        }

        if (function_exists('migrateLegacySchoolSetupPermissions')) {
            migrateLegacySchoolSetupPermissions();
        }

        return true;
    }
}

if (!function_exists('migrateLegacySchoolSetupPermissions')) {
    function migrateLegacySchoolSetupPermissions() {
        static $migrated = false;
        if ($migrated) {
            return true;
        }
        $migrated = true;

        $rows = fetchAll(
            "SELECT id, permissions
             FROM role_permissions
             WHERE permissions LIKE '%settings_view%'
                OR permissions LIKE '%settings_edit%'"
        );

        if (empty($rows)) {
            return true;
        }

        foreach ($rows as $row) {
            $permissions = json_decode((string)($row['permissions'] ?? '[]'), true);
            if (!is_array($permissions)) {
                continue;
            }

            $permissions = array_values(array_unique($permissions));
            $changed = false;
            foreach (['school_settings_view', 'academic_years_view', 'session_rollover_view'] as $newKey) {
                if (!in_array($newKey, $permissions, true)) {
                    $permissions[] = $newKey;
                    $changed = true;
                }
            }

            if ($changed) {
                executeQuery(
                    "UPDATE role_permissions SET permissions = ?, updated_at = NOW() WHERE id = ?",
                    'si',
                    [json_encode(array_values(array_unique($permissions))), intval($row['id'])]
                );
            }
        }

        return true;
    }
}

if (!function_exists('getRolePermissionsForSchool')) {
    function getRolePermissionsForSchool($roleName, $schoolId = null) {
        $roleName = trim((string) $roleName);
        if ($roleName === '') {
            return null;
        }

        ensureRolePermissionsSchema();

        $schoolId = $schoolId !== null ? intval($schoolId) : intval(function_exists('getCurrentSchoolId') ? getCurrentSchoolId() : 0);

        if ($schoolId > 0) {
            $schoolRow = fetchOne(
                "SELECT permissions FROM role_permissions WHERE school_id = ? AND role_name = ? LIMIT 1",
                'is',
                [$schoolId, $roleName]
            );

            if ($schoolRow && !empty($schoolRow['permissions'])) {
                return $schoolRow;
            }
        }

        $globalRow = fetchOne(
            "SELECT permissions FROM role_permissions WHERE school_id = 0 AND role_name = ? LIMIT 1",
            's',
            [$roleName]
        );

        if ($globalRow && !empty($globalRow['permissions'])) {
            return $globalRow;
        }

        return fetchOne(
            "SELECT permissions FROM role_permissions WHERE role_name = ? LIMIT 1",
            's',
            [$roleName]
        );
    }
}

if (!function_exists('getRolePermissionListForSchool')) {
    function getRolePermissionListForSchool($roleName, $schoolId = null) {
        $row = function_exists('getRolePermissionsForSchool')
            ? getRolePermissionsForSchool($roleName, $schoolId)
            : null;

        if (!$row || empty($row['permissions'])) {
            return [];
        }

        $decodedPermissions = json_decode((string) $row['permissions'], true);
        if (!is_array($decodedPermissions)) {
            return [];
        }

        return array_values(array_unique(array_map(static function ($permission) {
            return trim((string) $permission);
        }, $decodedPermissions)));
    }
}

if (!function_exists('setRolePermissionForSchool')) {
    function setRolePermissionForSchool($roleName, $schoolId, $permissionKey, $enabled) {
        $roleName = trim((string) $roleName);
        $permissionKey = trim((string) $permissionKey);
        $schoolId = intval($schoolId);
        $enabled = (bool) $enabled;

        if ($roleName === '' || $permissionKey === '' || $schoolId <= 0) {
            return false;
        }

        ensureRolePermissionsSchema();

        $permissions = function_exists('getRolePermissionListForSchool')
            ? getRolePermissionListForSchool($roleName, $schoolId)
            : [];

        if ($enabled) {
            if (!in_array($permissionKey, $permissions, true)) {
                $permissions[] = $permissionKey;
            }
        } else {
            $permissions = array_values(array_filter($permissions, static function ($permission) use ($permissionKey) {
                return trim((string) $permission) !== $permissionKey;
            }));
        }

        $permissionsJson = json_encode(array_values(array_unique($permissions)));
        if ($permissionsJson === false) {
            return false;
        }

        $existingRow = fetchOne(
            "SELECT id FROM role_permissions WHERE school_id = ? AND role_name = ? LIMIT 1",
            'is',
            [$schoolId, $roleName]
        );

        if ($existingRow) {
            return executeQuery(
                "UPDATE role_permissions SET permissions = ?, updated_at = NOW() WHERE school_id = ? AND role_name = ?",
                'sis',
                [$permissionsJson, $schoolId, $roleName]
            ) !== false;
        }

        return executeQuery(
            "INSERT INTO role_permissions (school_id, role_name, permissions, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
            'iss',
            [$schoolId, $roleName, $permissionsJson]
        ) !== false;
    }
}

if (!function_exists('getRolePermissionKeysForModule')) {
    function getRolePermissionKeysForModule($module, $action) {
        $module = strtolower(trim((string) $module));
        $action = normalizePermissionAction($action);
        if ($module === '' || $action === '') {
            return [];
        }

        return [$module . '_' . $action];
    }
}

if (!function_exists('hasRolePermissionForSchool')) {
    function hasRolePermissionForSchool($module, $action, $schoolId = null, $roleName = null) {
        $module = strtolower(trim((string) $module));
        $action = normalizePermissionAction($action);

        if ($module === '' || $action === '') {
            return false;
        }

        $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
        if (!$user) {
            return false;
        }

        $roleName = $roleName !== null ? trim((string) $roleName) : trim((string)($user['role'] ?? ''));
        if ($roleName === '') {
            return false;
        }

        if ($roleName === 'super_admin') {
            return true;
        }

        $schoolId = $schoolId !== null
            ? intval($schoolId)
            : intval($user['school_id'] ?? 0);

        if ($schoolId <= 0 && function_exists('getCurrentSchoolId')) {
            $schoolId = intval(getCurrentSchoolId());
        }

        $rolePermissionRow = function_exists('getRolePermissionsForSchool')
            ? getRolePermissionsForSchool($roleName, $schoolId)
            : fetchOne(
                "SELECT permissions FROM role_permissions WHERE role_name = ? LIMIT 1",
                's',
                [$roleName]
            );

        if (!$rolePermissionRow || empty($rolePermissionRow['permissions'])) {
            return false;
        }

        $decodedPermissions = json_decode((string) $rolePermissionRow['permissions'], true);
        if (!is_array($decodedPermissions)) {
            return false;
        }

        $permissionKeys = function_exists('getRolePermissionKeysForModule')
            ? getRolePermissionKeysForModule($module, $action)
            : [$module . '_' . $action];

        foreach ($permissionKeys as $permissionKey) {
            if (in_array($permissionKey, $decodedPermissions, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('requireRolePermissionForSchool')) {
    function requireRolePermissionForSchool($module, $action, $schoolId = null, $roleName = null) {
        if (!hasRolePermissionForSchool($module, $action, $schoolId, $roleName)) {
            alertAndRedirect('You do not have permission to access this page.', getUserHomeUrl(), 'error');
        }
    }
}

function getCurrentSchoolId() {
    static $resolvedSchoolId = null;

    if ($resolvedSchoolId !== null) {
        return $resolvedSchoolId;
    }

    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    $role = strtolower(trim((string)($user['role'] ?? '')));

    if ($role === 'super_admin') {
        $resolvedSchoolId = 0;
        return $resolvedSchoolId;
    }

    if (!empty($user['school_id'])) {
        $resolvedSchoolId = intval($user['school_id']);
        return $resolvedSchoolId;
    }

    if (!empty($_SESSION['school_id'])) {
        $resolvedSchoolId = intval($_SESSION['school_id']);
        return $resolvedSchoolId;
    }

    $userId = intval($user['user_id'] ?? 0);

    if ($userId > 0 && $role === 'admin') {
        $school = null;

        if (function_exists('schoolRegistrationGetSchoolByUserId')) {
            $school = schoolRegistrationGetSchoolByUserId($userId);
        }

        if (!$school) {
            $school = fetchOne("SELECT school_id FROM schools WHERE admin_user_id = ? LIMIT 1", 'i', [$userId]);
        }

        if (!$school && !empty($user['username'])) {
            $school = fetchOne("SELECT school_id FROM schools WHERE admin_username = ? LIMIT 1", 's', [$user['username']]);
        }

        if (!$school && !empty($user['email'])) {
            $school = fetchOne("SELECT school_id FROM schools WHERE admin_email = ? LIMIT 1", 's', [$user['email']]);
        }

        if (!empty($school['school_id'])) {
            $resolvedSchoolId = intval($school['school_id']);
            $_SESSION['school_id'] = $resolvedSchoolId;
            return $resolvedSchoolId;
        }
    }

    if ($userId > 0 && $role === 'student' && !empty($user['student_id'])) {
        $student = fetchOne("SELECT school_id FROM students WHERE student_id = ? LIMIT 1", 'i', [intval($user['student_id'])]);
        if (!empty($student['school_id'])) {
            $resolvedSchoolId = intval($student['school_id']);
            $_SESSION['school_id'] = $resolvedSchoolId;
            return $resolvedSchoolId;
        }
    }

    $resolvedSchoolId = 0;
    return $resolvedSchoolId;
}

function getSchoolSettings() {
    static $settingsCache = [];
    $schoolId = getCurrentSchoolId();
    $cacheKey = $schoolId > 0 ? 'school:' . $schoolId : 'global';

    if (array_key_exists($cacheKey, $settingsCache)) {
        return $settingsCache[$cacheKey];
    }

    ensureSchoolSettingsSchema();

    $settings = null;
    if ($schoolId > 0) {
        $settings = getSchoolSettingsBySchoolId($schoolId);

        if (!$settings) {
            $school = function_exists('schoolRegistrationGetSchoolById')
                ? schoolRegistrationGetSchoolById($schoolId)
                : fetchOne("SELECT * FROM schools WHERE school_id = ? LIMIT 1", 'i', [$schoolId]);

            if ($school && function_exists('schoolRegistrationSyncApprovedSchoolSettings')) {
                schoolRegistrationSyncApprovedSchoolSettings($school);
                $settings = getSchoolSettingsBySchoolId($schoolId);
            }

            if (!$settings && $school && function_exists('schoolRegistrationMapSchoolToSettings')) {
                $settings = schoolRegistrationMapSchoolToSettings($school, [
                    'school_id' => $schoolId,
                    'school_name' => trim((string)($school['school_name'] ?? '')) ?: APP_NAME,
                    'school_address' => trim((string)($school['school_address'] ?? '')),
                    'school_phone' => trim((string)($school['school_phone'] ?? ($school['admin_mobile'] ?? ''))),
                    'school_email' => trim((string)($school['school_email'] ?? ($school['admin_email'] ?? ''))),
                ]);
            }
        }
    }

    if (!$settings) {
        // Return default settings
        $settings = [
                'school_name' => APP_NAME,
                'company_name' => '',
                'company_tagline' => '',
                'company_address' => '',
                'company_phone' => '',
                'company_email' => '',
                'company_website' => '',
                'company_logo' => '',
                'enabled_roles' => json_encode(['admin', 'accountant', 'clerk', 'teacher', 'parent']),
                'student_add_limit' => 0,
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
                'school_address' => '',
                'school_phone' => '',
                'school_email' => '',
                'current_academic_year' => date('Y') . '-' . (date('Y') + 1),
                'admission_prefix' => 'STU',
                'affiliation_no' => '',
                'school_code' => '',
                'udise_code' => '',
                'teacher_signature' => '',
                'class_teacher_signature' => '',
                'principal_signature' => '',
                'receipt_prefix' => 'REC',
                'transfer_certificate_prefix' => 'TC/',
                'transfer_certificate_last_no' => 0,
                'attendance_scan_mode' => 'daily',
                'attendance_class_start_time' => '08:00:00',
                'attendance_period_duration_minutes' => 45,
                'attendance_auto_alert_enabled' => 1,
                'attendance_absent_message_template' => 'Dear Parent, your ward {student_name} (Adm No. {admission_no}) was marked ABSENT {period_text} on {date} at {school_name}. Please contact the school office if this needs correction.',
                'login_brand_subtitle' => APP_NAME,
                'login_hero_title' => 'Secure access for staff, records, and school operations.',
                'login_hero_subtitle' => 'Sign in to manage admissions, fees, reports, marks, documents, and daily workflows from one premium school ERP interface.',
                'login_pill_1' => 'Role-based access',
                'login_pill_2' => 'Reports and receipts',
                'login_pill_3' => 'Student management',
                'login_metric_1_title' => 'Secure',
                'login_metric_1_text' => 'Controlled by user role',
                'login_metric_2_title' => 'Fast',
                'login_metric_2_text' => 'Quick access on any device',
                'login_metric_3_title' => 'Reliable',
                'login_metric_3_text' => 'Daily school operations ready',
                'login_card_subtitle' => 'Staff Login',
                'login_card_title' => 'Login to Your Account',
                'login_username_label' => 'Username or Email',
                'login_username_placeholder' => 'Enter username or email',
                'login_password_label' => 'Password',
                'login_password_placeholder' => 'Enter password',
                'login_remember_me_label' => 'Remember me',
                'login_button_text' => 'Login',
                'login_forgot_password_text' => 'Forgot Password?',
                'login_forgot_username_text' => 'Forgot Username?',
                'login_student_login_text' => 'Student Login',
                'login_alert_registered_text' => 'Your account has been created. Please login with your new credentials.',
                'login_alert_school_registered_text' => 'School registration submitted. Waiting for Super Admin approval.',
                'login_alert_reset_text' => 'Password updated successfully. You can now login with the new password.',
                'currency_symbol' => '₹'
            ];
    } else {
            $settings['school_name'] = trim((string)($settings['school_name'] ?? '')) ?: APP_NAME;
            $settings['company_name'] = trim((string)($settings['company_name'] ?? ''));
            $settings['company_tagline'] = trim((string)($settings['company_tagline'] ?? ''));
            $settings['company_address'] = trim((string)($settings['company_address'] ?? ''));
            $settings['company_phone'] = trim((string)($settings['company_phone'] ?? ''));
            $settings['company_email'] = trim((string)($settings['company_email'] ?? ''));
            $settings['company_website'] = trim((string)($settings['company_website'] ?? ''));
            $settings['company_logo'] = trim((string)($settings['company_logo'] ?? ''));
            $settings['school_address'] = trim((string)($settings['school_address'] ?? ''));
            $settings['school_phone'] = trim((string)($settings['school_phone'] ?? ($settings['phone'] ?? $settings['contact_no'] ?? '')));
            $settings['school_email'] = trim((string)($settings['school_email'] ?? ($settings['email'] ?? '')));

            if (empty($settings['class_teacher_signature'] ?? '') && !empty($settings['teacher_signature'] ?? '')) {
                $settings['class_teacher_signature'] = $settings['teacher_signature'];
            }

            if (empty($settings['teacher_signature'] ?? '') && !empty($settings['class_teacher_signature'] ?? '')) {
                $settings['teacher_signature'] = $settings['class_teacher_signature'];
            }

            $settings['subscription_plan'] = trim((string)($settings['subscription_plan'] ?? 'free'));
            $settings['subscription_price'] = floatval($settings['subscription_price'] ?? 0);
            $settings['subscription_currency_code'] = trim((string)($settings['subscription_currency_code'] ?? 'INR'));
            $settings['subscription_billing_cycle'] = trim((string)($settings['subscription_billing_cycle'] ?? 'monthly'));
            $settings['subscription_status'] = trim((string)($settings['subscription_status'] ?? 'active'));
            $settings['ads_enabled'] = intval($settings['ads_enabled'] ?? 1);
            $settings['subscription_started_at'] = $settings['subscription_started_at'] ?? null;
            $settings['subscription_expires_at'] = $settings['subscription_expires_at'] ?? null;
            $settings['subscription_gateway'] = trim((string)($settings['subscription_gateway'] ?? 'manual'));
            $settings['subscription_gateway_reference'] = trim((string)($settings['subscription_gateway_reference'] ?? ''));
            $settings['subscription_payment_link'] = trim((string)($settings['subscription_payment_link'] ?? ''));
            $settings['subscription_notes'] = trim((string)($settings['subscription_notes'] ?? ''));
            $settings['attendance_scan_mode'] = trim((string)($settings['attendance_scan_mode'] ?? 'daily'));
            if (!in_array($settings['attendance_scan_mode'], ['daily', 'period'], true)) {
                $settings['attendance_scan_mode'] = 'daily';
            }
            $settings['attendance_class_start_time'] = attendanceNormalizeTimeValue($settings['attendance_class_start_time'] ?? '08:00:00', '08:00:00');
            $settings['attendance_period_duration_minutes'] = intval($settings['attendance_period_duration_minutes'] ?? 45);
            if ($settings['attendance_period_duration_minutes'] <= 0) {
                $settings['attendance_period_duration_minutes'] = 45;
            }
            $settings['attendance_auto_alert_enabled'] = intval($settings['attendance_auto_alert_enabled'] ?? 1);
            $settings['attendance_absent_message_template'] = trim((string)($settings['attendance_absent_message_template'] ?? ''));
            $legacyAbsentTemplate = 'Dear Parent, your ward {student_name} (Adm No. {admission_no}) was marked ABSENT {period_text} on {date} at {school_name}. Please contact the school office if this needs correction.';
            if ($settings['attendance_absent_message_template'] === '' || $settings['attendance_absent_message_template'] === $legacyAbsentTemplate) {
                $settings['attendance_absent_message_template'] = attendanceGetDefaultAbsentSmsTemplate();
            }
            $settings['student_add_limit'] = intval($settings['student_add_limit'] ?? 0);
            $settings['login_brand_subtitle'] = trim((string)($settings['login_brand_subtitle'] ?? APP_NAME));
            if ($settings['login_brand_subtitle'] === '' || strtolower($settings['login_brand_subtitle']) === 'school management system') {
                $settings['login_brand_subtitle'] = APP_NAME;
            }

            $loginDefaults = [
                'login_brand_subtitle' => APP_NAME,
                'login_hero_title' => 'Secure access for staff, records, and school operations.',
                'login_hero_subtitle' => 'Sign in to manage admissions, fees, reports, marks, documents, and daily workflows from one premium school ERP interface.',
                'login_pill_1' => 'Role-based access',
                'login_pill_2' => 'Reports and receipts',
                'login_pill_3' => 'Student management',
                'login_metric_1_title' => 'Secure',
                'login_metric_1_text' => 'Controlled by user role',
                'login_metric_2_title' => 'Fast',
                'login_metric_2_text' => 'Quick access on any device',
                'login_metric_3_title' => 'Reliable',
                'login_metric_3_text' => 'Daily school operations ready',
                'login_card_subtitle' => 'Staff Login',
                'login_card_title' => 'Login to Your Account',
                'login_username_label' => 'Username or Email',
                'login_username_placeholder' => 'Enter username or email',
                'login_password_label' => 'Password',
                'login_password_placeholder' => 'Enter password',
                'login_remember_me_label' => 'Remember me',
                'login_button_text' => 'Login',
                'login_forgot_password_text' => 'Forgot Password?',
                'login_forgot_username_text' => 'Forgot Username?',
                'login_student_login_text' => 'Student Login',
                'login_alert_registered_text' => 'Your account has been created. Please login with your new credentials.',
                'login_alert_school_registered_text' => 'School registration submitted. Waiting for Super Admin approval.',
                'login_alert_reset_text' => 'Password updated successfully. You can now login with the new password.',
            ];

        foreach ($loginDefaults as $key => $default) {
            $settings[$key] = trim((string)($settings[$key] ?? ''));
            if ($settings[$key] === '') {
                $settings[$key] = $default;
            }
        }
    }

    $settingsCache[$cacheKey] = $settings;
    return $settings;
}

/**
 * Get the school settings row for a specific school code.
 *
 * @param string $schoolCode
 * @return array|null
 */
function getSchoolSettingsByCode($schoolCode) {
    ensureSchoolSettingsSchema();

    $schoolCode = trim((string) $schoolCode);
    if ($schoolCode === '') {
        return null;
    }

    return fetchOne(
        "SELECT * FROM school_settings WHERE school_code = ? ORDER BY updated_at DESC, setting_id DESC LIMIT 1",
        's',
        [$schoolCode]
    );
}

/**
 * Get the school settings row for a specific school id.
 *
 * @param int $schoolId
 * @return array|null
 */
function getSchoolSettingsBySchoolId($schoolId) {
    $schoolId = intval($schoolId);
    if ($schoolId <= 0) {
        return null;
    }

    ensureSchoolSettingsSchema();

    $settings = fetchOne(
        "SELECT * FROM school_settings WHERE school_id = ? ORDER BY updated_at DESC, setting_id DESC LIMIT 1",
        'i',
        [$schoolId]
    );

    if ($settings) {
        return $settings;
    }

    $school = fetchOne("SELECT school_code FROM schools WHERE school_id = ? LIMIT 1", 'i', [$schoolId]);
    if (!$school || empty($school['school_code'])) {
        return null;
    }

    return getSchoolSettingsByCode($school['school_code']);
}

/**
 * Get the enabled user roles for a school.
 *
 * @param int $schoolId
 * @return array
 */
function getSchoolEnabledRoles($schoolId) {
    $defaultRoles = ['admin', 'accountant', 'clerk', 'teacher', 'parent'];
    $settings = getSchoolSettingsBySchoolId($schoolId);

    if (!$settings || empty($settings['enabled_roles'])) {
        return $defaultRoles;
    }

    $decoded = json_decode((string) $settings['enabled_roles'], true);
    if (!is_array($decoded) || empty($decoded)) {
        return $defaultRoles;
    }

    $cleanRoles = array_values(array_filter(array_map('trim', $decoded), function ($role) {
        return in_array($role, ['admin', 'accountant', 'clerk', 'teacher', 'parent'], true);
    }));

    return !empty($cleanRoles) ? $cleanRoles : $defaultRoles;
}

/**
 * Get the current subscription snapshot for a school.
 *
 * @param int|null $schoolId
 * @return array
 */
function getSchoolSubscriptionDetails($schoolId = null) {
    $schoolId = $schoolId !== null ? intval($schoolId) : intval(getCurrentSchoolId());
    if ($schoolId <= 0) {
        return [
            'subscription_plan' => 'free',
            'subscription_price' => 0,
            'subscription_currency_code' => 'INR',
            'subscription_billing_cycle' => 'monthly',
            'subscription_status' => 'active',
            'ads_enabled' => 0,
            'subscription_started_at' => null,
            'subscription_expires_at' => null,
            'subscription_gateway' => 'manual',
            'subscription_gateway_reference' => '',
            'subscription_payment_link' => '',
            'subscription_notes' => '',
        ];
    }

    $settings = getSchoolSettingsBySchoolId($schoolId);

    if (!$settings) {
        return [
            'subscription_plan' => 'free',
            'subscription_price' => 0,
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
        ];
    }

    return [
        'subscription_plan' => trim((string) ($settings['subscription_plan'] ?? 'free')),
        'subscription_price' => floatval($settings['subscription_price'] ?? 0),
        'subscription_currency_code' => trim((string) ($settings['subscription_currency_code'] ?? 'INR')),
        'subscription_billing_cycle' => trim((string) ($settings['subscription_billing_cycle'] ?? 'monthly')),
        'subscription_status' => trim((string) ($settings['subscription_status'] ?? 'active')),
        'ads_enabled' => intval($settings['ads_enabled'] ?? 1),
        'subscription_started_at' => $settings['subscription_started_at'] ?? null,
        'subscription_expires_at' => $settings['subscription_expires_at'] ?? null,
        'subscription_gateway' => trim((string) ($settings['subscription_gateway'] ?? 'manual')),
        'subscription_gateway_reference' => trim((string) ($settings['subscription_gateway_reference'] ?? '')),
        'subscription_payment_link' => trim((string) ($settings['subscription_payment_link'] ?? '')),
        'subscription_notes' => trim((string) ($settings['subscription_notes'] ?? '')),
    ];
}

if (!function_exists('getSubscriptionPlanCatalog')) {
    function getSubscriptionPlanCatalog() {
        return [
            'free' => [
                'key' => 'free',
                'label' => 'Free Plan',
                'subtitle' => 'Starter access with ads enabled.',
                'description' => 'Best for schools that want to stay on the free tier.',
                'badge' => 'Ads enabled',
                'variant' => 'secondary',
                'icon' => 'bi-gift',
                'features' => [
                    'Promotional ads remain visible',
                    'Basic school dashboard access',
                    'No payment required',
                ],
                'requires_payment' => false,
            ],
            'premium' => [
                'key' => 'premium',
                'label' => 'Premium Plan',
                'subtitle' => 'Ad-free school experience.',
                'description' => 'Upgrade for a cleaner portal and premium presentation.',
                'badge' => 'Most popular',
                'variant' => 'primary',
                'icon' => 'bi-stars',
                'features' => [
                    'Promotional ads are hidden',
                    'Premium portal branding',
                    'Configured payment gateway',
                ],
                'requires_payment' => true,
            ],
            'enterprise' => [
                'key' => 'enterprise',
                'label' => 'Enterprise Plan',
                'subtitle' => 'Custom setup for larger schools.',
                'description' => 'Ideal for schools needing custom billing or dedicated support.',
                'badge' => 'Custom',
                'variant' => 'dark',
                'icon' => 'bi-building-gear',
                'features' => [
                    'Custom pricing and support',
                    'Advanced school-level control',
                    'Payment flow managed by Super Admin',
                ],
                'requires_payment' => true,
            ],
        ];
    }
}

if (!function_exists('getSubscriptionPlanMeta')) {
    function getSubscriptionPlanMeta($plan) {
        $catalog = getSubscriptionPlanCatalog();
        $key = strtolower(trim((string)$plan));
        return $catalog[$key] ?? $catalog['free'];
    }
}

if (!function_exists('getSchoolSubscriptionPaymentLink')) {
    function getSchoolSubscriptionPaymentLink($schoolId = null) {
        $schoolId = $schoolId !== null ? intval($schoolId) : intval(getCurrentSchoolId());
        if ($schoolId <= 0) {
            return '';
        }

        $details = getSchoolSubscriptionDetails($schoolId);
        $paymentLink = trim((string)($details['subscription_payment_link'] ?? ''));
        if ($paymentLink !== '') {
            return $paymentLink;
        }

        $settings = getSchoolSettingsBySchoolId($schoolId);
        if (!$settings) {
            return '';
        }

        $gateway = strtolower(trim((string)($details['subscription_gateway'] ?? 'manual')));
        $price = floatval($details['subscription_price'] ?? 0);
        if ($gateway !== 'upi' || $price <= 0) {
            return '';
        }

        $upiId = trim((string)($settings['upi_id'] ?? ''));
        if ($upiId === '') {
            return '';
        }

        $schoolName = trim((string)($settings['school_name'] ?? ''));
        $recipient = trim((string)($settings['payment_recipient_name'] ?? $schoolName));
        $currencyCode = trim((string)($details['subscription_currency_code'] ?? 'INR')) ?: 'INR';

        return 'upi://pay?pa=' . rawurlencode($upiId)
            . '&pn=' . rawurlencode($recipient !== '' ? $recipient : $schoolName)
            . '&am=' . number_format($price, 2, '.', '')
            . '&cu=' . rawurlencode($currencyCode)
            . '&tn=' . rawurlencode($schoolName . ' subscription');
    }
}

/**
 * Determine whether the current school should display ads/promotional banners.
 *
 * Free subscriptions keep ads on; premium and enterprise subscriptions hide them.
 *
 * @param int|null $schoolId
 * @return bool
 */
function isSchoolAdsEnabled($schoolId = null) {
    $subscription = getSchoolSubscriptionDetails($schoolId);
    $status = strtolower(trim((string) ($subscription['subscription_status'] ?? 'active')));
    $plan = strtolower(trim((string) ($subscription['subscription_plan'] ?? 'free')));

    if (in_array($plan, ['premium', 'enterprise'], true) && $status === 'active') {
        return false;
    }

    return intval($subscription['ads_enabled'] ?? 1) === 1;
}

if (!function_exists('ensureSchoolAdsSchema')) {
    function ensureSchoolAdsSchema() {
        static $checked = false;
        if ($checked) {
            return true;
        }
        $checked = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS school_ads (
                ad_id int(11) NOT NULL AUTO_INCREMENT,
                school_id int(11) NOT NULL DEFAULT 0,
                placement varchar(50) NOT NULL DEFAULT 'header_banner',
                ad_type enum('image','text','html') NOT NULL DEFAULT 'image',
                title varchar(200) NOT NULL,
                content_text text DEFAULT NULL,
                content_html longtext DEFAULT NULL,
                image_file varchar(255) DEFAULT NULL,
                link_url varchar(255) DEFAULT NULL,
                priority int(11) NOT NULL DEFAULT 0,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                start_date date DEFAULT NULL,
                end_date date DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (ad_id),
                KEY idx_school_ads_school_placement (school_id, placement, is_active, priority),
                KEY idx_school_ads_school_id (school_id),
                KEY idx_school_ads_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        return true;
    }
}

/**
 * Get the maximum number of students allowed for a school.
 *
 * @param int|null $schoolId
 * @return int
 */
if (!function_exists('getSchoolStudentAddLimit')) {
    function getSchoolStudentAddLimit($schoolId = null) {
        $schoolId = $schoolId !== null ? intval($schoolId) : intval(getCurrentSchoolId());
        if ($schoolId <= 0) {
            return 0;
        }

        $settings = getSchoolSettingsBySchoolId($schoolId);
        return intval($settings['student_add_limit'] ?? 0);
    }
}

/**
 * Get the count of active students for a school.
 *
 * @param int|null $schoolId
 * @return int
 */
if (!function_exists('getSchoolActiveStudentCount')) {
    function getSchoolActiveStudentCount($schoolId = null) {
        $schoolId = $schoolId !== null ? intval($schoolId) : intval(getCurrentSchoolId());
        if ($schoolId <= 0) {
            return 0;
        }

        $row = fetchOne(
            "SELECT COUNT(*) as total FROM students WHERE school_id = ? AND status = 'Active'",
            'i',
            [$schoolId]
        );

        return intval($row['total'] ?? 0);
    }
}

/**
 * Determine whether the student add limit has been reached.
 *
 * @param int|null $schoolId
 * @param int $additionalStudents
 * @return bool
 */
if (!function_exists('isSchoolStudentAddLimitReached')) {
    function isSchoolStudentAddLimitReached($schoolId = null, $additionalStudents = 1) {
        $limit = getSchoolStudentAddLimit($schoolId);
        if ($limit <= 0) {
            return false;
        }

        $currentCount = getSchoolActiveStudentCount($schoolId);
        $additionalStudents = max(1, intval($additionalStudents));

        return ($currentCount + $additionalStudents) > $limit;
    }
}

if (!function_exists('setSchoolStudentAddLimit')) {
    function setSchoolStudentAddLimit($schoolId, $limit) {
        $schoolId = intval($schoolId);
        $limit = max(0, intval($limit));

        if ($schoolId <= 0) {
            return false;
        }

        ensureSchoolSettingsSchema();

        $settings = getSchoolSettingsBySchoolId($schoolId);
        if ((!$settings || empty($settings['setting_id'])) && function_exists('schoolRegistrationSyncApprovedSchoolSettings')) {
            $school = fetchOne("SELECT * FROM schools WHERE school_id = ? LIMIT 1", 'i', [$schoolId]);
            if ($school) {
                schoolRegistrationSyncApprovedSchoolSettings($school);
                $settings = getSchoolSettingsBySchoolId($schoolId);
            }
        }

        if (!$settings || empty($settings['setting_id'])) {
            return false;
        }

        return executeQuery(
            "UPDATE school_settings SET student_add_limit = ?, updated_at = NOW() WHERE setting_id = ?",
            'ii',
            [$limit, intval($settings['setting_id'])]
        ) !== false;
    }
}

if (!function_exists('getSchoolAdPlacements')) {
    function getSchoolAdPlacements() {
        return [
            'header_banner' => 'Header Banner',
            'dashboard_top' => 'Dashboard Top',
            'login_page' => 'Login Page',
            'fees_page' => 'Fees Page',
            'reports_page' => 'Reports Page',
            'student_portal' => 'Student Portal',
        ];
    }
}

if (!function_exists('getSchoolAdsForPlacement')) {
    function getSchoolAdsForPlacement($schoolId = null, $placement = 'header_banner', $limit = 1, $includeInactive = false) {
        ensureSchoolAdsSchema();

        $schoolId = $schoolId !== null ? intval($schoolId) : intval(function_exists('getCurrentSchoolId') ? getCurrentSchoolId() : 0);
        $placement = trim((string) $placement);
        $limit = max(1, min(20, intval($limit)));

        if ($schoolId <= 0) {
            return [];
        }

        $query = "SELECT * FROM school_ads WHERE school_id = ?";
        $types = 'i';
        $params = [$schoolId];

        if ($placement !== '') {
            $query .= " AND placement = ?";
            $types .= 's';
            $params[] = $placement;
        }

        if (!$includeInactive) {
            $query .= " AND is_active = 1";
        }

        $query .= " AND (start_date IS NULL OR start_date <= CURDATE())";
        $query .= " AND (end_date IS NULL OR end_date >= CURDATE())";
        $query .= " ORDER BY priority DESC, ad_id DESC LIMIT {$limit}";

        return fetchAll($query, $types, $params);
    }
}

if (!function_exists('getSchoolAdImageSrc')) {
    function getSchoolAdImageSrc($filename = '') {
        $filename = trim((string) $filename);
        if ($filename === '') {
            return '';
        }

        $path = AD_PATH . $filename;
        if (!file_exists($path)) {
            return '';
        }

        return APP_URL . '/assets/uploads/ads/' . rawurlencode($filename);
    }
}

/**
 * Get the public company branding used on login and registration pages.
 *
 * This is intentionally separate from school settings so the public
 * registration flow can show the software vendor/company identity instead
 * of an individual school's branding.
 */
function getCompanyBranding() {
    $settings = getSchoolSettings();
    $defaultName = defined('COMPANY_NAME') ? trim((string) COMPANY_NAME) : APP_NAME;
    $defaultTagline = defined('COMPANY_TAGLINE') ? trim((string) COMPANY_TAGLINE) : '';
    $defaultAddress = defined('COMPANY_ADDRESS') ? trim((string) COMPANY_ADDRESS) : '';
    $defaultPhone = defined('COMPANY_PHONE') ? trim((string) COMPANY_PHONE) : '';
    $defaultEmail = defined('COMPANY_EMAIL') ? trim((string) COMPANY_EMAIL) : '';
    $defaultWebsite = defined('COMPANY_WEBSITE') ? trim((string) COMPANY_WEBSITE) : '';
    $defaultLogo = defined('COMPANY_LOGO') ? trim((string) COMPANY_LOGO) : '';

    return [
        'name' => trim((string)($settings['company_name'] ?? $defaultName)) ?: $defaultName,
        'tagline' => trim((string)($settings['company_tagline'] ?? $defaultTagline)) ?: $defaultTagline,
        'address' => trim((string)($settings['company_address'] ?? $defaultAddress)) ?: $defaultAddress,
        'phone' => trim((string)($settings['company_phone'] ?? $defaultPhone)) ?: $defaultPhone,
        'email' => trim((string)($settings['company_email'] ?? $defaultEmail)) ?: $defaultEmail,
        'website' => trim((string)($settings['company_website'] ?? $defaultWebsite)) ?: $defaultWebsite,
        'logo' => trim((string)($settings['company_logo'] ?? $defaultLogo)) ?: $defaultLogo,
    ];
}

/**
 * Resolve the company logo to a usable URL when the configured file exists.
 */
function getCompanyLogoSrc($logoPath = '') {
    $logoPath = trim((string) $logoPath);
    if ($logoPath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $logoPath)) {
        return $logoPath;
    }

    $normalized = ltrim($logoPath, '/');
    $absolutePath = BASE_PATH . '/' . $normalized;
    if (!file_exists($absolutePath)) {
        return '';
    }

    return APP_URL . '/' . $normalized;
}

/**
 * Build an inline data URI for a student photo.
 *
 * This avoids extra image requests and keeps photos visible even when
 * browsers are blocking local image URLs.
 *
 * @param string $photoFilename
 * @return string
 */
function getStudentPhotoSrc($photoFilename = '') {
    static $cache = [];
    static $fallback = null;

    $resolve = function ($absolutePath) use (&$cache) {
        if (empty($absolutePath) || !file_exists($absolutePath)) {
            return '';
        }

        if (isset($cache[$absolutePath])) {
            return $cache[$absolutePath];
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return '';
        }

        $cache[$absolutePath] = 'data:' . $mime . ';base64,' . base64_encode($content);
        return $cache[$absolutePath];
    };

    if ($fallback === null) {
        $fallback = $resolve(BASE_PATH . '/assets/images/default-avatar.png');
    }

    if (!empty($photoFilename)) {
        $photoPath = STUDENT_PHOTO_PATH . $photoFilename;
        $photoSrc = $resolve($photoPath);
        if (!empty($photoSrc)) {
            return $photoSrc;
        }
    }

    return $fallback ?: '';
}

/**
 * Get a school signature image as a data URI.
 *
 * This keeps the principal signature visible on printable documents even
 * when the browser or server rewrites local image URLs.
 *
 * @param string $signatureFilename
 * @return string
 */
function getSchoolSignatureSrc($signatureFilename = '') {
    static $cache = [];

    $signatureFilename = trim((string) $signatureFilename);
    if ($signatureFilename === '') {
        return '';
    }

    $signaturePath = SIGNATURE_PATH . $signatureFilename;
    if (!file_exists($signaturePath)) {
        return '';
    }

    if (isset($cache[$signaturePath])) {
        return $cache[$signaturePath];
    }

    $ext = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        default => 'image/png',
    };

    $content = file_get_contents($signaturePath);
    if ($content === false) {
        return '';
    }

    $cache[$signaturePath] = 'data:' . $mime . ';base64,' . base64_encode($content);
    return $cache[$signaturePath];
}

/**
 * Get a school logo image as a data URI.
 *
 * The helper accepts a primary logo filename and an optional fallback
 * banner logo filename so document layouts can remain resilient even when
 * only one branding asset is configured.
 *
 * @param string $logoFilename
 * @param string $fallbackFilename
 * @return string
 */
function getSchoolLogoSrc($logoFilename = '', $fallbackFilename = '') {
    static $cache = [];

    $candidates = array_values(array_filter(array_unique([
        trim((string) $logoFilename),
        trim((string) $fallbackFilename),
    ])));

    foreach ($candidates as $candidate) {
        $logoPath = LOGO_PATH . $candidate;
        if (!file_exists($logoPath)) {
            continue;
        }

        if (isset($cache[$logoPath])) {
            return $cache[$logoPath];
        }

        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        $content = file_get_contents($logoPath);
        if ($content === false) {
            continue;
        }

        $cache[$logoPath] = 'data:' . $mime . ';base64,' . base64_encode($content);
        return $cache[$logoPath];
    }

    return '';
}

/**
 * Upload image with resize and crop
 *
 * @param array $file $_FILES array
 * @param string $targetDir Target directory
 * @param int $maxWidth Max width
 * @param int $maxHeight Max height
 * @return string|false Filename on success, false on failure
 */
function uploadImage($file, $targetDir, $maxWidth = 800, $maxHeight = 800) {
    // Validate file
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }

    // Check file type
    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png'];

    if (!in_array($imageFileType, $allowedTypes)) {
        return false;
    }

    // Check file size (max 10MB)
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $imageFileType;
    $targetFile = $targetDir . $filename;

    // Create image resource
    switch ($imageFileType) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        default:
            return false;
    }

    if (!$source) {
        return false;
    }

    // Get original dimensions
    list($origWidth, $origHeight) = getimagesize($file['tmp_name']);

    // Calculate new dimensions
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = intval($origWidth * $ratio);
    $newHeight = intval($origHeight * $ratio);

    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG
    if ($imageFileType == 'png') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }

    // Resize image
    imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Save image
    $saved = false;
    switch ($imageFileType) {
        case 'jpg':
        case 'jpeg':
            $saved = imagejpeg($newImage, $targetFile, 90);
            break;
        case 'png':
            $saved = imagepng($newImage, $targetFile, 9);
            break;
    }

    // Free memory
    imagedestroy($source);
    imagedestroy($newImage);

    return $saved ? $filename : false;
}

/**
 * Upload a PDF document to a destination directory.
 *
 * @param array $file $_FILES entry
 * @param string $targetDir
 * @return string|false
 */
function uploadPdfDocument($file, $targetDir) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name']) || !isset($file['error']) || intval($file['error']) !== UPLOAD_ERR_OK) {
        return false;
    }

    if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
        return false;
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mimeType = '';
    if (function_exists('mime_content_type')) {
        $mimeType = strtolower((string) @mime_content_type($file['tmp_name']));
    }

    if ($extension !== 'pdf' && $mimeType !== 'application/pdf') {
        return false;
    }

    if (!ensureDirectoryExists($targetDir)) {
        return false;
    }

    $filename = uniqid('doc_', true) . '_' . time() . '.pdf';
    $targetFile = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    return move_uploaded_file($file['tmp_name'], $targetFile) ? $filename : false;
}

/**
 * Delete file
 *
 * @param string $filePath
 * @return bool
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Get user IP address
 *
 * @return string
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Log activity
 *
 * @param int $userId
 * @param string $action
 * @param string $module
 * @param string $description
 * @return bool
 */
function logActivity($userId, $action, $module, $description = '') {
    $ip = getUserIP();
    $query = "INSERT INTO activity_log (user_id, action, module, description, ip_address)
              VALUES (?, ?, ?, ?, ?)";
    $result = executeQuery($query, 'issss', [$userId, $action, $module, $description, $ip]);
    return $result !== false;
}

/**
 * Generate random password
 *
 * @param int $length
 * @return string
 */
function generatePassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Send JSON response
 *
 * @param bool $success
 * @param string $message
 * @param array $data
 */
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Redirect to URL
 *
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Show alert and redirect
 *
 * @param string $message
 * @param string $url
 * @param string $type (success|error|warning|info)
 */
function alertAndRedirect($message, $url, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
    redirect($url);
}

/**
 * Build the current request URL for navigation history.
 *
 * @return string
 */
function getCurrentRequestUrl() {
    if (empty($_SERVER['REQUEST_URI']) || !defined('APP_URL')) {
        return '';
    }

    $appParts = parse_url(APP_URL);
    if ($appParts === false || empty($appParts['scheme']) || empty($appParts['host'])) {
        return rtrim(APP_URL, '/') . $_SERVER['REQUEST_URI'];
    }

    $origin = $appParts['scheme'] . '://' . $appParts['host'];
    if (!empty($appParts['port'])) {
        $origin .= ':' . intval($appParts['port']);
    }

    return sanitizeNavigationUrl($origin . $_SERVER['REQUEST_URI']);
}

/**
 * Normalize a URL so we can compare paths safely.
 *
 * @param string $url
 * @return string
 */
function normalizeUrlForComparison($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return rtrim($url, '/');
    }

    $scheme = strtolower($parts['scheme'] ?? 'http');
    $host = strtolower($parts['host'] ?? '');
    $port = isset($parts['port']) ? ':' . intval($parts['port']) : '';
    $path = rtrim($parts['path'] ?? '', '/');
    if ($path === '') {
        $path = '/';
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $port . $path . $query;
}

/**
 * Check whether a URL belongs to this app.
 *
 * @param string $url
 * @return bool
 */
function isInternalAppUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || !defined('APP_URL')) {
        return false;
    }

    $appParts = parse_url(APP_URL);
    $urlParts = parse_url($url);

    if (!$appParts || !$urlParts || empty($urlParts['host'])) {
        return false;
    }

    if (strtolower($appParts['scheme'] ?? 'http') !== strtolower($urlParts['scheme'] ?? 'http')) {
        return false;
    }

    if (strtolower($appParts['host'] ?? '') !== strtolower($urlParts['host'] ?? '')) {
        return false;
    }

    $appPath = rtrim($appParts['path'] ?? '', '/');
    $urlPath = $urlParts['path'] ?? '';

    if ($appPath !== '' && strpos($urlPath, $appPath) !== 0) {
        return false;
    }

    return true;
}

/**
 * Check whether a URL is a non-page asset or background request.
 *
 * @param string $url
 * @return bool
 */
function isNavigationNoiseUrl($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return true;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }

    $path = $parts['path'] ?? '';
    if ($path === '') {
        return false;
    }

    if (preg_match('#/(assets|ajax|api)/#', $path)) {
        return true;
    }

    return (bool) preg_match('#/(login|logout|register)\.php$#', $path);
}

/**
 * Normalize navigation URLs so history stays clean and stable.
 *
 * @param string $url
 * @return string
 */
function sanitizeNavigationUrl($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    $appParts = defined('APP_URL') ? parse_url(APP_URL) : false;
    $urlParts = parse_url($url);
    if (!$appParts || !$urlParts || empty($urlParts['scheme']) || empty($urlParts['host'])) {
        return $url;
    }

    $appPath = rtrim($appParts['path'] ?? '', '/');
    $path = $urlParts['path'] ?? '';

    if ($path === '') {
        $path = '/';
    }

    if ($appPath !== '') {
        $doublePath = $appPath . $appPath;
        while (strpos($path, $doublePath) === 0) {
            $path = $appPath . substr($path, strlen($doublePath));
        }
    }

    $normalized = strtolower($urlParts['scheme']) . '://' . strtolower($urlParts['host']);
    if (!empty($urlParts['port'])) {
        $normalized .= ':' . intval($urlParts['port']);
    }
    $normalized .= $path;

    if (!empty($urlParts['query'])) {
        $normalized .= '?' . $urlParts['query'];
    }

    if (!empty($urlParts['fragment'])) {
        $normalized .= '#' . $urlParts['fragment'];
    }

    return $normalized;
}

/**
 * Group navigation URLs by app area so back buttons can recover a sensible
 * in-app target even when browser history is thin or unavailable.
 *
 * @param string $url
 * @return string
 */
function getNavigationGroupFromUrl($url) {
    $url = sanitizeNavigationUrl($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    $path = strtolower(trim((string)($parts['path'] ?? ''), '/'));
    if ($path === '') {
        return 'root';
    }

    $segments = array_values(array_filter(explode('/', $path), function ($item) {
        return $item !== '';
    }));

    if (empty($segments)) {
        return 'root';
    }

    if ($segments[0] === 'modules') {
        return $segments[1] ?? 'modules';
    }

    return $segments[0];
}

/**
 * Track the recent navigation trail for logged-in users.
 */
function trackNavigationHistory() {
    if (!isLoggedIn() || (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')) {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ($requestUri === '') {
        return;
    }

    if (preg_match('#/(modules/auth/(login|logout)|modules/parent/login)\.php(?:\?|$)#', $requestUri)) {
        return;
    }

    if (preg_match('#/(ajax|api)/#', $requestUri)) {
        return;
    }

    $currentUrl = getCurrentRequestUrl();
    if ($currentUrl === '' || isNavigationNoiseUrl($currentUrl)) {
        return;
    }

    if (!isset($_SESSION['nav_history']) || !is_array($_SESSION['nav_history'])) {
        $_SESSION['nav_history'] = [];
    }

    $history = array_values(array_filter(array_map('sanitizeNavigationUrl', $_SESSION['nav_history']), function ($item) {
        return $item !== '' && !isNavigationNoiseUrl($item);
    }));
    $_SESSION['nav_history'] = $history;
    $normalizedCurrent = normalizeUrlForComparison($currentUrl);
    $lastItem = end($history);

    if ($lastItem === false || normalizeUrlForComparison($lastItem) !== $normalizedCurrent) {
        $history[] = $currentUrl;
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        $_SESSION['nav_history'] = $history;
    }

    $group = getNavigationGroupFromUrl($currentUrl);
    if ($group !== '') {
        if (!isset($_SESSION['nav_group_history']) || !is_array($_SESSION['nav_group_history'])) {
            $_SESSION['nav_group_history'] = [];
        }

        if (!isset($_SESSION['nav_group_history'][$group]) || !is_array($_SESSION['nav_group_history'][$group])) {
            $_SESSION['nav_group_history'][$group] = [
                'current' => '',
                'previous' => '',
            ];
        }

        $groupCurrent = sanitizeNavigationUrl($_SESSION['nav_group_history'][$group]['current'] ?? '');
        if ($groupCurrent !== '' && normalizeUrlForComparison($groupCurrent) !== $normalizedCurrent) {
            $_SESSION['nav_group_history'][$group]['previous'] = $groupCurrent;
        }

        $_SESSION['nav_group_history'][$group]['current'] = $currentUrl;
    }
}

/**
 * Build a smart back URL using navigation history, referer, and fallback.
 *
 * @param string $fallback
 * @return string
 */
function getSmartBackUrl($fallback = '') {
    $currentUrl = getCurrentRequestUrl();
    $normalizedCurrent = normalizeUrlForComparison($currentUrl);

    if (!empty($_SESSION['nav_history']) && is_array($_SESSION['nav_history'])) {
        for ($i = count($_SESSION['nav_history']) - 2; $i >= 0; $i--) {
            $candidate = sanitizeNavigationUrl($_SESSION['nav_history'][$i] ?? '');
            if ($candidate === '') {
                continue;
            }

            if (isNavigationNoiseUrl($candidate)) {
                continue;
            }

            if (normalizeUrlForComparison($candidate) !== $normalizedCurrent) {
                return $candidate;
            }
        }
    }

    $group = getNavigationGroupFromUrl($currentUrl);
    if ($group !== '' && !empty($_SESSION['nav_group_history'][$group]['previous'])) {
        $candidate = sanitizeNavigationUrl($_SESSION['nav_group_history'][$group]['previous']);
        if ($candidate !== '' && normalizeUrlForComparison($candidate) !== $normalizedCurrent && !isNavigationNoiseUrl($candidate)) {
            return $candidate;
        }
    }

    $referer = sanitizeNavigationUrl($_SERVER['HTTP_REFERER'] ?? '');
    if (isInternalAppUrl($referer) && !isNavigationNoiseUrl($referer) && normalizeUrlForComparison($referer) !== $normalizedCurrent) {
        return $referer;
    }

    $fallback = sanitizeNavigationUrl($fallback);
    if ($fallback !== '' && normalizeUrlForComparison($fallback) !== $normalizedCurrent) {
        return $fallback;
    }

    return '';
}

/**
 * Get the home URL for the currently logged-in user.
 *
 * @param array|null $user
 * @return string
 */
function getUserHomeUrl($user = null) {
    if ($user === null) {
        $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    }

    if (!$user || empty($user['role'])) {
        return APP_URL . '/modules/dashboard/';
    }

    switch ($user['role']) {
        case 'student':
            return APP_URL . '/modules/student/dashboard.php';
        case 'parent':
            return APP_URL . '/modules/parent/dashboard.php';
        default:
            return APP_URL . '/modules/dashboard/';
    }
}

/**
 * Ensure a directory exists before writing uploaded files.
 *
 * @param string $path
 * @return bool
 */
function ensureDirectoryExists($path) {
    $path = rtrim((string)$path, DIRECTORY_SEPARATOR);
    if ($path === '') {
        return false;
    }

    if (is_dir($path)) {
        return true;
    }

    return @mkdir($path, 0775, true) || is_dir($path);
}

/**
 * Check whether a student application record exists for a user.
 *
 * @param int $userId
 * @return array|null
 */
function getStudentApplicationByUserId($userId) {
    $userId = intval($userId);
    if ($userId <= 0) {
        return null;
    }

    $conn = getDbConnection();
    $tableCheck = $conn->query("SHOW TABLES LIKE 'student_applications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return null;
    }

    return fetchOne(
        "SELECT * FROM student_applications WHERE user_id = ? LIMIT 1",
        'i',
        [$userId]
    );
}

/**
 * Get and clear alert message
 *
 * @return array|null
 */
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

/**
 * Validate email
 *
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate mobile number
 *
 * @param string $mobile
 * @return bool
 */
function isValidMobile($mobile) {
    return preg_match('/^[0-9]{10}$/', $mobile);
}

/**
 * Encrypt a sensitive value for temporary storage.
 *
 * @param string $value
 * @return string
 */
function encryptAppValue($value) {
    $value = (string) $value;
    if ($value === '') {
        return '';
    }

    $key = hash('sha256', ENCRYPTION_KEY, true);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    if ($ivLength <= 0) {
        return '';
    }

    try {
        $iv = random_bytes($ivLength);
    } catch (Exception $e) {
        $iv = openssl_random_pseudo_bytes($ivLength);
    }

    if ($iv === false || strlen($iv) !== $ivLength) {
        return '';
    }

    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return '';
    }

    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a value encrypted by encryptAppValue().
 *
 * @param string $payload
 * @return string
 */
function decryptAppValue($payload) {
    $payload = (string) $payload;
    if ($payload === '') {
        return '';
    }

    $raw = base64_decode($payload, true);
    if ($raw === false) {
        return '';
    }

    $key = hash('sha256', ENCRYPTION_KEY, true);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    if (strlen($raw) <= $ivLength) {
        return '';
    }

    $iv = substr($raw, 0, $ivLength);
    $ciphertext = substr($raw, $ivLength);
    $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return $decrypted === false ? '' : $decrypted;
}

/**
 * Get months array
 *
 * @return array
 */
function getMonths() {
    return [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
}

/**
 * Get a student's fee summary using fee_structure plus paid receipt details.
 *
 * This treats fee_receipt_details as the source of truth for what has been
 * collected and keeps partial payments visible as remaining balances.
 *
 * @param int $studentId
 * @param string|null $asOfDate Optional date (Y-m-d) to calculate the summary against
 * @return array
 */
function getStudentFeeSummary($studentId, $asOfDate = null) {
    static $cache = [];

    $cacheKey = $studentId . '|' . ($asOfDate ?: '');
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $emptySummary = [
        'student' => null,
        'assigned_total' => 0.00,
        'paid_total' => 0.00,
        'due_total' => 0.00,
        'fee_items' => [],
        'pending_items' => [],
    ];

    if ($studentId <= 0) {
        return $cache[$cacheKey] = $emptySummary;
    }

    if (function_exists('ensureFeeModuleSchema')) {
        ensureFeeModuleSchema();
    }

    $studentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
    $studentQuery = "SELECT s.student_id, s.student_name, s.admission_no, s.roll_no, s.gender,
                            s.date_of_birth, s.father_name, s.mother_name, s.contact_no,
                            s.email, s.address, s.photo, s.class_id, s.section_id,
                            c.class_name, sec.section_name, s.school_id
                     FROM students s
                     LEFT JOIN classes c ON s.class_id = c.class_id
                     LEFT JOIN sections sec ON s.section_id = sec.section_id
                     WHERE s.student_id = ?";
    $studentParams = [$studentId];
    $studentTypes = 'i';

    if ($studentSchoolId > 0) {
        $studentQuery .= " AND COALESCE(s.school_id, 0) = ?";
        $studentParams[] = $studentSchoolId;
        $studentTypes .= 'i';
    }

    $student = fetchOne($studentQuery, $studentTypes, $studentParams);

    if (!$student) {
        return $cache[$cacheKey] = $emptySummary;
    }

    $settings = getSchoolSettings();
    $months = getMonths();
    $academicYearStartMonth = isset($settings['academic_year_start_month']) ? intval($settings['academic_year_start_month']) : 4;
    if ($academicYearStartMonth < 1 || $academicYearStartMonth > 12) {
        $academicYearStartMonth = 4;
    }

    $referenceTimestamp = !empty($asOfDate) ? strtotime($asOfDate) : time();
    if ($referenceTimestamp === false) {
        $referenceTimestamp = time();
    }
    $currentYear = intval(date('Y', $referenceTimestamp));
    $currentMonth = intval(date('n', $referenceTimestamp));

    $feeStructureQuery = "SELECT fs.fee_head_id, fs.amount, fh.fee_head_name, fh.fee_type
                          FROM fee_structure fs
                          JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
                          WHERE fs.student_id = ? AND fs.is_active = 1";
    $feeStructureParams = [$studentId];
    $feeStructureTypes = 'i';

    if ($studentSchoolId > 0) {
        $feeStructureQuery .= " AND COALESCE(fs.school_id, 0) = ? AND COALESCE(fh.school_id, 0) = ?";
        $feeStructureParams[] = $studentSchoolId;
        $feeStructureParams[] = $studentSchoolId;
        $feeStructureTypes .= 'ii';
    }

    $feeStructureQuery .= " ORDER BY fh.display_order, fh.fee_head_name";
    $feeStructure = fetchAll($feeStructureQuery, $feeStructureTypes, $feeStructureParams);

    $paidRows = fetchAll(
        "SELECT frd.fee_head_id, frd.fee_month, frd.fee_year, SUM(frd.amount) as paid_amount
         FROM fee_receipt_details frd
         JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
         WHERE fr.student_id = ? AND fr.is_cancelled = 0
         GROUP BY frd.fee_head_id, frd.fee_month, frd.fee_year",
        'i',
        [$studentId]
    );

    $paidMap = [];
    foreach ($paidRows as $row) {
        $monthKey = trim((string)($row['fee_month'] ?? ''));
        $yearKey = trim((string)($row['fee_year'] ?? ''));
        $paidAmount = floatval($row['paid_amount']);

        $paidMap[$row['fee_head_id'] . '|' . $monthKey . '|' . $yearKey] = $paidAmount;

        // One-time fee receipts in this project are stored with an empty month
        // and a populated year, so keep a year-specific alias for those records.
        if ($monthKey === '' && $yearKey !== '') {
            $paidMap[$row['fee_head_id'] . '||' . $yearKey] = $paidAmount;
        }
    }

    $pendingItems = [];
    $feeItems = [];
    $assignedTotal = 0.00;
    $paidTotal = 0.00;
    $dueTotal = 0.00;

    foreach ($feeStructure as $fee) {
        $feeHeadId = intval($fee['fee_head_id']);
        $feeAmount = floatval($fee['amount']);
        $feeName = $fee['fee_head_name'];
        $feeType = $fee['fee_type'];
        $baseFeeName = preg_replace('/\s*\([^)]+\)\s*$/', '', $feeName);

        if ($feeType === 'Monthly') {
            for ($i = 0; $i < 12; $i++) {
                $monthNum = (($academicYearStartMonth + $i - 1) % 12) + 1;
                $monthName = $months[$monthNum - 1];

                $feeYear = $currentYear;
                if ($monthNum < $academicYearStartMonth && $currentMonth >= $academicYearStartMonth) {
                    $feeYear = $currentYear + 1;
                } elseif ($monthNum >= $academicYearStartMonth && $currentMonth < $academicYearStartMonth) {
                    $feeYear = $currentYear - 1;
                }

                if ($feeYear > $currentYear || ($feeYear == $currentYear && $monthNum > $currentMonth)) {
                    continue;
                }

                $paidAmount = $paidMap[$feeHeadId . '|' . $monthName . '|' . $feeYear] ?? 0.00;
                $dueAmount = max(0, $feeAmount - $paidAmount);
                $status = $paidAmount >= $feeAmount ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid');
                $feeItem = [
                    'fee_head_id' => $feeHeadId,
                    'fee_head_name' => $feeName,
                    'display_fee_type' => $baseFeeName,
                    'fee_type' => $feeType,
                    'original_amount' => $feeAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'amount' => $dueAmount,
                    'fee_month' => $monthName,
                    'display_month' => $monthName,
                    'fee_year' => $feeYear,
                    'status' => $status,
                    'start_date' => date('d/m/Y', strtotime(sprintf('%04d-%02d-01', $feeYear, $monthNum))),
                ];

                $assignedTotal += $feeAmount;
                $paidTotal += min($paidAmount, $feeAmount);
                $dueTotal += $dueAmount;
                $feeItems[] = $feeItem;

                if ($dueAmount > 0) {
                    $pendingItems[] = $feeItem;
                }
            }
        } else {
            $paidAmount = $paidMap[$feeHeadId . '||' . $currentYear] ?? $paidMap[$feeHeadId . '||'] ?? 0.00;
            $dueAmount = max(0, $feeAmount - $paidAmount);
            $status = $paidAmount >= $feeAmount ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid');
            preg_match('/\(([^)]+)\)/', $feeName, $monthMatch);
            $displayMonth = $monthMatch[1] ?? null;
            $feeItem = [
                'fee_head_id' => $feeHeadId,
                'fee_head_name' => $feeName,
                'display_fee_type' => $baseFeeName,
                'fee_type' => $feeType,
                'original_amount' => $feeAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'amount' => $dueAmount,
                'fee_month' => null,
                'display_month' => $displayMonth,
                'fee_year' => $currentYear,
                'status' => $status,
                'start_date' => date('d/m/Y'),
            ];

            $assignedTotal += $feeAmount;
            $paidTotal += min($paidAmount, $feeAmount);
            $dueTotal += $dueAmount;
            $feeItems[] = $feeItem;

            if ($dueAmount > 0) {
                $pendingItems[] = $feeItem;
            }
        }
    }

    return $cache[$cacheKey] = [
        'student' => $student,
        'assigned_total' => round($assignedTotal, 2),
        'paid_total' => round($paidTotal, 2),
        'due_total' => round($dueTotal, 2),
        'fee_items' => $feeItems,
        'pending_items' => $pendingItems,
    ];
}

/**
 * Get all active students who currently have due fees.
 *
 * This uses the shared fee summary helper so due-fee reports stay in sync
 * with the monthly fee logic used by the collection screen.
 *
 * @param int $classId
 * @param int $sectionId
 * @param string|null $asOfDate Optional date (Y-m-d) to calculate dues against
 * @return array
 */
function getStudentsWithDueFees($classId = 0, $sectionId = 0, $asOfDate = null) {
    if (function_exists('ensureFeeModuleSchema')) {
        ensureFeeModuleSchema();
    }

    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;

    $query = "SELECT
                s.student_id, s.student_name, s.admission_no, s.father_name,
                s.contact_no, c.class_name, c.class_order, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.status = 'Active'";

    $params = [];
    $types = '';

    if ($schoolId > 0) {
        $query .= " AND COALESCE(s.school_id, 0) = ?";
        $params[] = $schoolId;
        $types .= 'i';
    }

    if ($classId > 0) {
        $query .= " AND s.class_id = ?";
        $params[] = $classId;
        $types .= 'i';
    }

    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $params[] = $sectionId;
        $types .= 'i';
    }

    $query .= " ORDER BY c.class_order, sec.section_name, s.student_name";

    $students = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);

    $dueStudents = [];
    foreach ($students as $student) {
        $summary = getStudentFeeSummary($student['student_id'], $asOfDate);

        if (($summary['due_total'] ?? 0) > 0) {
            $dueStudents[] = array_merge($student, [
                'total_fee_assigned' => $summary['assigned_total'],
                'total_paid' => $summary['paid_total'],
                'due_amount' => $summary['due_total'],
                'pending_items' => $summary['pending_items'],
            ]);
        }
    }

    usort($dueStudents, function ($left, $right) {
        $dueCompare = $right['due_amount'] <=> $left['due_amount'];
        if ($dueCompare !== 0) {
            return $dueCompare;
        }

        return strcmp($left['student_name'], $right['student_name']);
    });

    return $dueStudents;
}

/**
 * Build a WhatsApp share URL for fee receipts.
 *
 * @param string $phone
 * @param string $message
 * @return string
 */
function buildWhatsAppUrl($phone, $message) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (empty($digits) || empty($message)) {
        return '';
    }

    return 'https://wa.me/' . $digits . '?text=' . urlencode($message);
}

/**
 * Build a QR code image URL using a remote QR generator.
 *
 * @param string $text
 * @param int $size
 * @return string
 */
function buildQrCodeUrl($text, $size = 160) {
    if (empty($text)) {
        return '';
    }

    $size = max(80, min(300, intval($size)));
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($text);
}

/**
 * Build a signed token for student attendance QR codes.
 *
 * @param array $student
 * @return string
 */
function buildStudentAttendanceToken($student) {
    $studentId = intval($student['student_id'] ?? 0);
    if ($studentId <= 0) {
        return '';
    }

    $schoolId = intval($student['school_id'] ?? 0);
    if ($schoolId <= 0 && function_exists('getCurrentSchoolId')) {
        $schoolId = intval(getCurrentSchoolId());
    }

    $payload = [
        'student_id' => $studentId,
        'school_id' => $schoolId,
        'admission_no' => trim((string)($student['admission_no'] ?? '')),
        'issued_at' => date('c'),
    ];

    return encryptAppValue(json_encode($payload));
}

/**
 * Build the teacher scan URL for a student's attendance QR code.
 *
 * @param array $student
 * @return string
 */
function buildStudentAttendanceScanUrl($student) {
    $token = buildStudentAttendanceToken($student);
    if ($token === '') {
        return '';
    }

    return APP_URL . '/modules/attendance/scan.php?token=' . urlencode($token);
}

/**
 * Get current academic year
 *
 * @return string
 */
function getCurrentAcademicYear() {
    $settings = getSchoolSettings();
    return $settings['current_academic_year'];
}

/**
 * Get the next academic year label.
 *
 * Examples:
 * - 2024-2025 -> 2025-2026
 * - 2025-2026 -> 2026-2027
 *
 * @param string|null $currentAcademicYear
 * @return string
 */
function getNextAcademicYearLabel($currentAcademicYear = null) {
    $label = trim((string)($currentAcademicYear ?: getCurrentAcademicYear()));

    if (preg_match('/^(\d{4})\s*[-\/]\s*(\d{4})$/', $label, $matches)) {
        $nextStartYear = intval($matches[2]);
        return $nextStartYear . '-' . ($nextStartYear + 1);
    }

    if (preg_match('/^(\d{4})$/', $label, $matches)) {
        $startYear = intval($matches[1]);
        return $startYear . '-' . ($startYear + 1);
    }

    $currentYear = intval(date('Y'));
    return $currentYear . '-' . ($currentYear + 1);
}

/**
 * Build the date range for an academic year label.
 *
 * Uses the school-wide academic year start month when available.
 * If the label is not in YYYY-YYYY format, the current year is used
 * as a fallback anchor.
 *
 * @param string|null $academicYear
 * @param int|null $startMonth
 * @return array{start_date:string,end_date:string}
 */
function getAcademicYearDateRange($academicYear = null, $startMonth = null) {
    $settings = getSchoolSettings();
    if ($startMonth === null) {
        $startMonth = isset($settings['academic_year_start_month']) ? intval($settings['academic_year_start_month']) : 4;
    }

    $startMonth = max(1, min(12, intval($startMonth)));
    $label = trim((string)($academicYear ?: getCurrentAcademicYear()));
    $startYear = intval(date('Y'));

    if (preg_match('/^(\d{4})\s*[-\/]\s*(\d{4})$/', $label, $matches)) {
        $startYear = intval($matches[1]);
    } elseif (preg_match('/^(\d{4})$/', $label, $matches)) {
        $startYear = intval($matches[1]);
    } else {
        $todayMonth = intval(date('n'));
        $todayYear = intval(date('Y'));
        $startYear = ($todayMonth >= $startMonth) ? $todayYear : ($todayYear - 1);
    }

    $startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
    $endDate = date('Y-m-d', strtotime($startDate . ' +1 year -1 day'));

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];
}

/**
 * Collect a small but useful snapshot of what is still pending before a
 * session rollover.
 *
 * This intentionally focuses on data already present in the app:
 * - fee dues
 * - unresolved student reminders
 * - active students who will be carried forward
 *
 * @param string|null $asOfDate Optional date (Y-m-d) used for fee due calculation
 * @return array
 */
function getSessionPendingSummary($asOfDate = null) {
    $dueStudents = getStudentsWithDueFees(0, 0, $asOfDate);
    $dueAmountTotal = 0.00;
    $dueByClass = [];

    foreach ($dueStudents as $student) {
        $dueAmountTotal += floatval($student['due_amount'] ?? 0);
        $classKey = trim(($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? ''));
        $classKey = trim($classKey);
        if ($classKey === '') {
            $classKey = 'Unassigned';
        }

        if (!isset($dueByClass[$classKey])) {
            $dueByClass[$classKey] = [
                'count' => 0,
                'amount' => 0.00,
            ];
        }

        $dueByClass[$classKey]['count']++;
        $dueByClass[$classKey]['amount'] += floatval($student['due_amount'] ?? 0);
    }

    uasort($dueByClass, function ($left, $right) {
        return $right['amount'] <=> $left['amount'];
    });

    $remindersTableExists = fetchOne("SHOW TABLES LIKE 'student_reminders'");
    $activeReminderCount = 0;
    $urgentReminderCount = 0;
    $activeReminders = [];

    if ($remindersTableExists) {
        $activeReminderCountRow = fetchOne("SELECT COUNT(*) as total FROM student_reminders WHERE is_resolved = 0");
        $urgentReminderCountRow = fetchOne("SELECT COUNT(*) as total FROM student_reminders WHERE is_resolved = 0 AND priority = 'high'");

        $activeReminderCount = intval($activeReminderCountRow['total'] ?? 0);
        $urgentReminderCount = intval($urgentReminderCountRow['total'] ?? 0);

        $activeReminders = fetchAll(
            "SELECT r.reminder_id, r.student_id, r.reminder_text, r.priority, r.due_date, r.created_at,
                    s.student_name, s.admission_no, c.class_name, sec.section_name
             FROM student_reminders r
             JOIN students s ON r.student_id = s.student_id
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE r.is_resolved = 0
             ORDER BY CASE r.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, r.created_at DESC"
        );
    }

    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
    if ($schoolId > 0) {
        $activeStudentsRow = fetchOne(
            "SELECT COUNT(*) as total
             FROM students
             WHERE status = 'Active' AND school_id = ?",
            'i',
            [$schoolId]
        );
    } else {
        // Platform-level summaries should ignore legacy/orphan rows that are
        // not attached to any school and therefore cannot be carried forward.
        $activeStudentsRow = fetchOne(
            "SELECT COUNT(*) as total
             FROM students s
             INNER JOIN schools sc ON sc.school_id = s.school_id
             WHERE s.status = 'Active'"
        );
    }
    $activeStudentCount = intval($activeStudentsRow['total'] ?? 0);

    return [
        'due_students' => $dueStudents,
        'due_student_count' => count($dueStudents),
        'due_amount_total' => round($dueAmountTotal, 2),
        'due_by_class' => $dueByClass,
        'active_student_count' => $activeStudentCount,
        'active_reminder_count' => $activeReminderCount,
        'urgent_reminder_count' => $urgentReminderCount,
        'active_reminders' => $activeReminders,
    ];
}

/**
 * Get the month number for a month name.
 *
 * @param string $monthName
 * @return int
 */
function getMonthNumberFromName($monthName) {
    $monthName = trim((string)$monthName);
    if ($monthName === '') {
        return 0;
    }

    foreach (getMonths() as $index => $name) {
        if (strcasecmp($monthName, $name) === 0) {
            return $index + 1;
        }
    }

    return 0;
}

/**
 * Build labels and sort keys for a fee period.
 *
 * @param string $feeMonth
 * @param int $feeYear
 * @param int|null $academicYearStartMonth
 * @return array
 */
function getDueFeePeriodInfo($feeMonth, $feeYear, $academicYearStartMonth = null) {
    $settings = getSchoolSettings();
    if ($academicYearStartMonth === null) {
        $academicYearStartMonth = isset($settings['academic_year_start_month']) ? intval($settings['academic_year_start_month']) : 4;
    }

    $academicYearStartMonth = max(1, min(12, intval($academicYearStartMonth)));
    $feeYear = intval($feeYear);
    $monthNumber = getMonthNumberFromName($feeMonth);

    if ($monthNumber > 0) {
        $academicStartYear = $feeYear;
        if ($monthNumber < $academicYearStartMonth) {
            $academicStartYear = $feeYear - 1;
        }

        return [
            'month_number' => $monthNumber,
            'period_label' => $feeMonth . ' ' . $feeYear,
            'academic_year_label' => $academicStartYear . '-' . ($academicStartYear + 1),
            'academic_year_start' => $academicStartYear,
            'sort_key' => ($academicStartYear * 100) + $monthNumber,
        ];
    }

    return [
        'month_number' => 0,
        'period_label' => 'Year ' . $feeYear,
        'academic_year_label' => (string)$feeYear,
        'academic_year_start' => $feeYear,
        'sort_key' => ($feeYear * 100) + 99,
    ];
}

/**
 * Collect due-fee report data from matching active students.
 *
 * @param string $searchTerm
 * @param int $classId
 * @param int $sectionId
 * @param int $studentId
 * @param string|null $asOfDate
 * @return array
 */
function getDueFeeReportDataset($searchTerm = '', $classId = 0, $sectionId = 0, $studentId = 0, $asOfDate = null) {
    $searchTerm = trim((string)$searchTerm);
    $classId = intval($classId);
    $sectionId = intval($sectionId);
    $studentId = intval($studentId);

    $settings = getSchoolSettings();
    $academicYearStartMonth = isset($settings['academic_year_start_month']) ? intval($settings['academic_year_start_month']) : 4;
    if ($academicYearStartMonth < 1 || $academicYearStartMonth > 12) {
        $academicYearStartMonth = 4;
    }

    $query = "SELECT s.student_id, s.student_name, s.admission_no, s.father_name, s.contact_no,
                     s.roll_no, s.class_id, s.section_id,
                     c.class_name, c.class_order, sec.section_name
              FROM students s
              JOIN classes c ON s.class_id = c.class_id
              JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.status = 'Active'";

    $params = [];
    $types = '';

    if ($studentId > 0) {
        $query .= " AND s.student_id = ?";
        $params[] = $studentId;
        $types .= 'i';
    } elseif ($searchTerm !== '') {
        $query .= " AND (
                        s.student_name LIKE ? OR
                        s.admission_no LIKE ? OR
                        s.roll_no LIKE ? OR
                        s.father_name LIKE ? OR
                        c.class_name LIKE ? OR
                        sec.section_name LIKE ?
                    )";
        $searchParam = '%' . $searchTerm . '%';
        for ($i = 0; $i < 6; $i++) {
            $params[] = $searchParam;
        }
        $types .= 'ssssss';
    }

    if ($classId > 0) {
        $query .= " AND s.class_id = ?";
        $params[] = $classId;
        $types .= 'i';
    }

    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $params[] = $sectionId;
        $types .= 'i';
    }

    $query .= " ORDER BY c.class_order, sec.section_name, s.student_name";

    $students = empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);

    $studentRows = [];
    $feeRows = [];
    $totals = [
        'student_count' => 0,
        'item_count' => 0,
        'assigned_total' => 0.00,
        'paid_total' => 0.00,
        'due_total' => 0.00,
    ];

    foreach ($students as $student) {
        $summary = getStudentFeeSummary(intval($student['student_id']), $asOfDate);
        $pendingItems = $summary['pending_items'] ?? [];

        if (empty($pendingItems) || floatval($summary['due_total'] ?? 0) <= 0) {
            continue;
        }

        $pendingPeriods = [];
        foreach ($pendingItems as $item) {
            $periodInfo = getDueFeePeriodInfo($item['fee_month'] ?? '', intval($item['fee_year'] ?? 0), $academicYearStartMonth);
            $periodLabel = $periodInfo['period_label'];
            $pendingPeriods[$periodLabel] = true;

            $feeRows[] = array_merge($student, [
                'fee_head_id' => intval($item['fee_head_id'] ?? 0),
                'fee_head_name' => $item['fee_head_name'] ?? '',
                'display_fee_type' => $item['display_fee_type'] ?? '',
                'fee_type' => $item['fee_type'] ?? '',
                'original_amount' => floatval($item['original_amount'] ?? 0),
                'paid_amount' => floatval($item['paid_amount'] ?? 0),
                'due_amount' => floatval($item['due_amount'] ?? 0),
                'fee_month' => $item['fee_month'] ?? '',
                'fee_year' => intval($item['fee_year'] ?? 0),
                'period_label' => $periodLabel,
                'academic_year_label' => $periodInfo['academic_year_label'],
                'academic_year_start' => intval($periodInfo['academic_year_start']),
                'period_sort_key' => intval($periodInfo['sort_key']),
                'status' => $item['status'] ?? 'Unpaid',
                'start_date' => $item['start_date'] ?? '',
                'pending_student_total' => floatval($summary['due_total'] ?? 0),
                'pending_student_paid_total' => floatval($summary['paid_total'] ?? 0),
                'pending_student_assigned_total' => floatval($summary['assigned_total'] ?? 0),
            ]);

            $totals['item_count']++;
            $totals['assigned_total'] += floatval($item['original_amount'] ?? 0);
            $totals['paid_total'] += floatval($item['paid_amount'] ?? 0);
            $totals['due_total'] += floatval($item['due_amount'] ?? 0);
        }

        $studentRows[] = array_merge($student, [
            'total_fee_assigned' => floatval($summary['assigned_total'] ?? 0),
            'total_paid' => floatval($summary['paid_total'] ?? 0),
            'due_amount' => floatval($summary['due_total'] ?? 0),
            'pending_item_count' => count($pendingItems),
            'pending_periods' => array_keys($pendingPeriods),
        ]);
    }

    usort($studentRows, function ($left, $right) {
        $dueCompare = $right['due_amount'] <=> $left['due_amount'];
        if ($dueCompare !== 0) {
            return $dueCompare;
        }

        return strcmp($left['student_name'], $right['student_name']);
    });

    usort($feeRows, function ($left, $right) {
        $classCompare = intval($left['class_order'] ?? 0) <=> intval($right['class_order'] ?? 0);
        if ($classCompare !== 0) {
            return $classCompare;
        }

        $sectionCompare = strcmp($left['section_name'] ?? '', $right['section_name'] ?? '');
        if ($sectionCompare !== 0) {
            return $sectionCompare;
        }

        $studentCompare = strcmp($left['student_name'] ?? '', $right['student_name'] ?? '');
        if ($studentCompare !== 0) {
            return $studentCompare;
        }

        $periodCompare = intval($left['period_sort_key'] ?? 0) <=> intval($right['period_sort_key'] ?? 0);
        if ($periodCompare !== 0) {
            return $periodCompare;
        }

        return strcmp($left['fee_head_name'] ?? '', $right['fee_head_name'] ?? '');
    });

    $totals['student_count'] = count($studentRows);
    $totals['assigned_total'] = round($totals['assigned_total'], 2);
    $totals['paid_total'] = round($totals['paid_total'], 2);
    $totals['due_total'] = round($totals['due_total'], 2);

    return [
        'students' => $studentRows,
        'rows' => $feeRows,
        'totals' => $totals,
        'academic_year_start_month' => $academicYearStartMonth,
        'as_of_date' => $asOfDate,
        'search_term' => $searchTerm,
        'class_id' => $classId,
        'section_id' => $sectionId,
        'student_id' => $studentId,
    ];
}

/**
 * Calculate grade based on percentage
 *
 * @param float $percentage
 * @return string
 */
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C+';
    if ($percentage >= 40) return 'C';
    if ($percentage >= 33) return 'D';
    return 'F';
}

/**
 * Get grade color class
 *
 * @param string $grade
 * @return string
 */
function getGradeColorClass($grade) {
    switch ($grade) {
        case 'A+':
        case 'A':
            return 'text-success';
        case 'B+':
        case 'B':
            return 'text-primary';
        case 'C+':
        case 'C':
            return 'text-info';
        case 'D':
            return 'text-warning';
        case 'F':
            return 'text-danger';
        default:
            return 'text-secondary';
    }
}

/**
 * Move item to recycle bin (soft delete)
 *
 * @param string $itemType Type of item (student, fee_receipt, user, etc.)
 * @param int $itemId ID of the item
 * @param array $itemData Complete data of the item to be deleted
 * @param string $reason Reason for deletion (optional)
 * @return bool Success status
 */
function moveToRecycleBin($itemType, $itemId, $itemData, $reason = '') {
    try {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $deletedBy = $_SESSION['user_id'];
        $itemDataJson = json_encode($itemData);

        $query = "INSERT INTO deleted_items (item_type, item_id, item_data, deleted_by, reason, deleted_at)
                  VALUES (?, ?, ?, ?, ?, NOW())";

        $result = executeQuery($query, 'sisss', [$itemType, $itemId, $itemDataJson, $deletedBy, $reason]);

        return $result !== false;
    } catch (Exception $e) {
        error_log("Failed to move item to recycle bin: " . $e->getMessage());
        return false;
    }
}

/**
 * Soft delete a student - moves to recycle bin instead of permanent deletion
 *
 * @param int $studentId
 * @param string $reason
 * @return bool
 */
function softDeleteStudent($studentId, $reason = 'Deleted by admin') {
    // Get student data before deletion
    $student = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$studentId]);

    if (!$student) {
        return false;
    }

    // Move to recycle bin
    $moved = moveToRecycleBin('student', $studentId, $student, $reason);

    if ($moved) {
        // Delete from students table
        executeQuery("DELETE FROM students WHERE student_id = ?", 'i', [$studentId]);
        return true;
    }

    return false;
}

/**
 * Soft delete a fee receipt
 *
 * @param int $receiptId
 * @param string $reason
 * @return bool
 */
function softDeleteFeeReceipt($receiptId, $reason = 'Cancelled by admin') {
    // Get receipt data
    $receipt = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);

    if (!$receipt) {
        return false;
    }

    // Get receipt details (fee breakdown)
    $receiptDetails = fetchAll("SELECT * FROM fee_receipt_details WHERE receipt_id = ?", 'i', [$receiptId]);

    // Add details to receipt data for restoration
    $receipt['details'] = $receiptDetails;

    // Move to recycle bin
    $moved = moveToRecycleBin('fee_receipt', $receiptId, $receipt, $reason);

    if ($moved) {
        // Mark as cancelled in the table (keep data but mark as cancelled)
        executeQuery("UPDATE fee_receipts SET is_cancelled = 1, updated_at = NOW() WHERE receipt_id = ?", 'i', [$receiptId]);

        return true;
    }

    return false;
}

/**
 * Ensure user-related columns can be cleared before deleting a user.
 *
 * @return bool
 */
function ensureUserDeletionSupport() {
    static $checked = false;

    if ($checked) {
        return true;
    }

    $checked = true;

    $nullableColumns = [
        ['table' => 'activity_log', 'column' => 'user_id'],
        ['table' => 'class_subjects', 'column' => 'teacher_id'],
        ['table' => 'fee_receipts', 'column' => 'collected_by'],
    ];

    foreach ($nullableColumns as $spec) {
        $columnInfo = fetchOne(
            "SELECT COLUMN_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1",
            'sss',
            [DB_NAME, $spec['table'], $spec['column']]
        );

        if (!$columnInfo) {
            continue;
        }

        if (strtoupper((string)($columnInfo['IS_NULLABLE'] ?? '')) !== 'YES') {
            $modifyQuery = sprintf(
                "ALTER TABLE `%s` MODIFY `%s` %s DEFAULT NULL",
                $spec['table'],
                $spec['column'],
                $columnInfo['COLUMN_TYPE']
            );

            if (executeQuery($modifyQuery) === false) {
                error_log("Failed to relax nullability for {$spec['table']}.{$spec['column']}");
                return false;
            }
        }
    }

    return true;
}

/**
 * Soft delete a user
 *
 * @param int $userId
 * @param string $reason
 * @return bool
 */
function softDeleteUser($userId, $reason = 'Removed by admin') {
    $userId = intval($userId);

    if (!ensureUserDeletionSupport()) {
        return false;
    }

    // Get user data
    $user = fetchOne("SELECT * FROM users WHERE user_id = ?", 'i', [$userId]);

    if (!$user) {
        return false;
    }

    try {
        beginTransaction();

        // Detach references that should remain in the system history
        $cleanupQueries = [
            ["UPDATE activity_log SET user_id = NULL WHERE user_id = ?", 'i'],
            ["UPDATE class_subjects SET teacher_id = NULL WHERE teacher_id = ?", 'i'],
            ["UPDATE fee_receipts SET collected_by = NULL WHERE collected_by = ?", 'i'],
        ];

        foreach ($cleanupQueries as $cleanup) {
            if (executeQuery($cleanup[0], $cleanup[1], [$userId]) === false) {
                rollbackTransaction();
                return false;
            }
        }

        // Move to recycle bin
        $moved = moveToRecycleBin('user', $userId, $user, $reason);

        if (!$moved) {
            rollbackTransaction();
            return false;
        }

        // Delete from users table
        $deleted = executeQuery("DELETE FROM users WHERE user_id = ?", 'i', [$userId]);

        if ($deleted === false) {
            rollbackTransaction();
            return false;
        }

        commitTransaction();
        return true;
    } catch (Exception $e) {
        rollbackTransaction();
        error_log("Failed to soft delete user ID {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Recycle bin cleanup is disabled.
 *
 * Items stay in the recycle bin until they are deleted manually from
 * the Recycle Bin screen.
 *
 * @return int Always returns 0.
 */
function cleanupRecycleBin($days = null) {
    return 0;
}

/**
 * Soft delete a fee assignment
 *
 * @param int $structureId
 * @param string $reason
 * @return bool
 */
function softDeleteFeeAssignment($structureId, $reason = 'Removed by admin') {
    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;

    // Get fee assignment data
    $assignmentQuery = "SELECT fs.*, fh.fee_head_name, s.student_name, s.admission_no
                        FROM fee_structure fs
                        JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
                        JOIN students s ON fs.student_id = s.student_id
                        WHERE fs.structure_id = ?";
    $assignmentParams = [$structureId];
    $assignmentTypes = 'i';

    if ($schoolId > 0) {
        $assignmentQuery .= " AND COALESCE(fs.school_id, 0) = ?";
        $assignmentParams[] = $schoolId;
        $assignmentTypes .= 'i';
    }

    $assignment = fetchOne($assignmentQuery, $assignmentTypes, $assignmentParams);

    if (!$assignment) {
        return false;
    }

    // Move to recycle bin
    $moved = moveToRecycleBin('fee_assignment', $structureId, $assignment, $reason);

    if ($moved) {
        // Delete from fee_structure table
        executeQuery("DELETE FROM fee_structure WHERE structure_id = ?", 'i', [$structureId]);
        return true;
    }

    return false;
}

/**
 * Soft delete a fee head
 *
 * @param int $feeHeadId
 * @param string $reason
 * @return bool
 */
function softDeleteFeeHead($feeHeadId, $reason = 'Removed by admin') {
    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;

    // Get fee head data
    $feeHeadQuery = "SELECT * FROM fee_heads WHERE fee_head_id = ?";
    $feeHeadParams = [$feeHeadId];
    $feeHeadTypes = 'i';

    if ($schoolId > 0) {
        $feeHeadQuery .= " AND COALESCE(school_id, 0) = ?";
        $feeHeadParams[] = $schoolId;
        $feeHeadTypes .= 'i';
    }

    $feeHead = fetchOne($feeHeadQuery, $feeHeadTypes, $feeHeadParams);

    if (!$feeHead) {
        return false;
    }

    // Check if it's in use (only active assignments)
    $usageCountQuery = "SELECT COUNT(*) as count FROM fee_structure WHERE fee_head_id = ? AND is_active = 1";
    $usageCountParams = [$feeHeadId];
    $usageCountTypes = 'i';

    if ($schoolId > 0) {
        $usageCountQuery .= " AND COALESCE(school_id, 0) = ?";
        $usageCountParams[] = $schoolId;
        $usageCountTypes .= 'i';
    }

    $usageCount = fetchOne($usageCountQuery, $usageCountTypes, $usageCountParams);

    if ($usageCount['count'] > 0) {
        return false; // Cannot delete if actively in use
    }

    // Check for any ACTIVE (non-cancelled) receipts using this fee head
    $receiptCountQuery = "SELECT COUNT(*) as count
                          FROM fee_receipt_details frd
                          JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                          JOIN students s ON fr.student_id = s.student_id
                          WHERE frd.fee_head_id = ? AND fr.is_cancelled = 0";
    $receiptCountParams = [$feeHeadId];
    $receiptCountTypes = 'i';

    if ($schoolId > 0) {
        $receiptCountQuery .= " AND COALESCE(s.school_id, 0) = ?";
        $receiptCountParams[] = $schoolId;
        $receiptCountTypes .= 'i';
    }

    $receiptCount = fetchOne($receiptCountQuery, $receiptCountTypes, $receiptCountParams);

    if ($receiptCount['count'] > 0) {
        error_log("Cannot delete fee head ID: $feeHeadId - has {$receiptCount['count']} active receipt details");
        return false; // Cannot delete if used in active receipts (preserve financial history)
    }

    // Move to recycle bin first
    $moved = moveToRecycleBin('fee_head', $feeHeadId, $feeHead, $reason);

    if (!$moved) {
        error_log("Failed to move fee head ID: $feeHeadId to recycle bin");
        return false;
    }

    try {
        // Delete all inactive fee_structure assignments first (to clear foreign key)
        $inactiveFeesQuery = "SELECT * FROM fee_structure WHERE fee_head_id = ? AND is_active = 0";
        $inactiveFeesParams = [$feeHeadId];
        $inactiveFeesTypes = 'i';

        if ($schoolId > 0) {
            $inactiveFeesQuery .= " AND COALESCE(school_id, 0) = ?";
            $inactiveFeesParams[] = $schoolId;
            $inactiveFeesTypes .= 'i';
        }

        $inactiveFees = fetchAll($inactiveFeesQuery, $inactiveFeesTypes, $inactiveFeesParams);

        foreach ($inactiveFees as $fee) {
            executeQuery("DELETE FROM fee_structure WHERE structure_id = ?", 'i', [$fee['structure_id']]);
        }

        // Delete all fee_ledger entries for this fee head (pending/due fees)
        // Only delete ledger entries that are NOT tied to active receipts
        $ledgerEntries = fetchAll("SELECT * FROM fee_ledger WHERE fee_head_id = ?", 'i', [$feeHeadId]);

        foreach ($ledgerEntries as $ledger) {
            // Check if this ledger entry has been paid (has receipt)
            if (!empty($ledger['receipt_id'])) {
                // Check if the receipt is cancelled
                $receiptQuery = "SELECT fr.is_cancelled
                                 FROM fee_receipts fr
                                 JOIN students s ON fr.student_id = s.student_id
                                 WHERE fr.receipt_id = ?";
                $receiptParams = [$ledger['receipt_id']];
                $receiptTypes = 'i';
                if ($schoolId > 0) {
                    $receiptQuery .= " AND COALESCE(s.school_id, 0) = ?";
                    $receiptParams[] = $schoolId;
                    $receiptTypes .= 'i';
                }
                $receipt = fetchOne($receiptQuery, $receiptTypes, $receiptParams);
                if ($receipt && $receipt['is_cancelled'] == 0) {
                    error_log("Cannot delete fee head ID: $feeHeadId - has active ledger entry tied to receipt ID: {$ledger['receipt_id']}");
                    return false; // Cannot delete if ledger has active receipt
                }
            }
            // Delete the ledger entry
            executeQuery("DELETE FROM fee_ledger WHERE ledger_id = ?", 'i', [$ledger['ledger_id']]);
        }

        // Delete cancelled receipt details for this fee head
        $cancelledReceiptDetailsQuery = "SELECT frd.* FROM fee_receipt_details frd
                                         JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
                                         JOIN students s ON fr.student_id = s.student_id
                                         WHERE frd.fee_head_id = ? AND fr.is_cancelled = 1";
        $cancelledReceiptDetailsParams = [$feeHeadId];
        $cancelledReceiptDetailsTypes = 'i';

        if ($schoolId > 0) {
            $cancelledReceiptDetailsQuery .= " AND COALESCE(s.school_id, 0) = ?";
            $cancelledReceiptDetailsParams[] = $schoolId;
            $cancelledReceiptDetailsTypes .= 'i';
        }

        $cancelledReceiptDetails = fetchAll($cancelledReceiptDetailsQuery, $cancelledReceiptDetailsTypes, $cancelledReceiptDetailsParams);

        foreach ($cancelledReceiptDetails as $detail) {
            executeQuery("DELETE FROM fee_receipt_details WHERE detail_id = ?", 'i', [$detail['detail_id']]);
        }

        // Now delete from fee_heads table
        $deleteHeadQuery = "DELETE FROM fee_heads WHERE fee_head_id = ?";
        $deleteHeadParams = [$feeHeadId];
        $deleteHeadTypes = 'i';
        if ($schoolId > 0) {
            $deleteHeadQuery .= " AND COALESCE(school_id, 0) = ?";
            $deleteHeadParams[] = $schoolId;
            $deleteHeadTypes .= 'i';
        }

        $deleted = executeQuery($deleteHeadQuery, $deleteHeadTypes, $deleteHeadParams);

        if ($deleted === false) {
            error_log("Failed to delete fee head ID: $feeHeadId from database after moving to recycle bin");
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Exception deleting fee head ID: $feeHeadId - " . $e->getMessage());
        return false;
    }
}

/**
 * Permanently delete a cancelled receipt (removes from database completely)
 * This should only be used on already-cancelled receipts
 *
 * @param int $receiptId
 * @param string $reason
 * @return bool
 */
function permanentlyDeleteReceipt($receiptId, $reason = 'Permanently deleted by admin') {
    // Get receipt data
    $receipt = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);

    if (!$receipt) {
        return false;
    }

    // Get receipt details
    $receiptDetails = fetchAll("SELECT * FROM fee_receipt_details WHERE receipt_id = ?", 'i', [$receiptId]);
    $receipt['details'] = $receiptDetails;

    // Move to recycle bin BEFORE permanent deletion
    $moved = moveToRecycleBin('fee_receipt_permanent', $receiptId, $receipt, $reason);

    if ($moved) {
        // Now permanently delete
        executeQuery("DELETE FROM fee_receipt_details WHERE receipt_id = ?", 'i', [$receiptId]);
        executeQuery("DELETE FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);
        return true;
    }

    return false;
}

/**
 * Get recycle bin item count
 *
 * @param string $itemType Optional filter by type
 * @return int
 */
function getRecycleBinCount($itemType = '') {
    if (!empty($itemType)) {
        $result = fetchOne("SELECT COUNT(*) as count FROM deleted_items WHERE item_type = ?", 's', [$itemType]);
    } else {
        $result = fetchOne("SELECT COUNT(*) as count FROM deleted_items");
    }

    return $result ? $result['count'] : 0;
}

?>
