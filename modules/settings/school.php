<?php
/**
 * School Settings
 * Super Admin and Admin: manage school information and academic setup
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();

$pageTitle = 'School Settings';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$schoolAccessRole = $currentUser['role'] ?? '';
$isSuperAdmin = $schoolAccessRole === 'super_admin';
if ($schoolAccessRole !== 'super_admin') {
    requireRolePermissionForSchool('school_settings', 'view', $currentSchoolId, $schoolAccessRole);
}
$error = '';
$success = '';

// Get current settings
$settings = getSchoolSettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolName = sanitize($_POST['school_name']);
    $schoolAddress = sanitize($_POST['school_address'] ?? '');
    $schoolPhone = sanitize($_POST['school_phone'] ?? '');
    $schoolEmail = sanitize($_POST['school_email'] ?? '');
    $affiliationNo = sanitize($_POST['affiliation_no'] ?? '');
    $schoolCode = sanitize($_POST['school_code'] ?? '');
    $udiseCode = sanitize($_POST['udise_code'] ?? '');
    $currentAcademicYear = sanitize($_POST['current_academic_year'] ?? ($settings['current_academic_year'] ?? date('Y') . '-' . (date('Y') + 1)));
    $admissionPrefix = sanitize($_POST['admission_prefix'] ?? ($settings['admission_prefix'] ?? 'STU'));
    $receiptPrefix = sanitize($_POST['receipt_prefix'] ?? ($settings['receipt_prefix'] ?? 'REC'));
    $attendanceScanMode = sanitize($_POST['attendance_scan_mode'] ?? ($settings['attendance_scan_mode'] ?? 'daily'));
    if (!in_array($attendanceScanMode, ['daily', 'period'], true)) {
        $attendanceScanMode = 'daily';
    }
    $attendanceClassStartTime = function_exists('attendanceNormalizeTimeValue')
        ? attendanceNormalizeTimeValue($_POST['attendance_class_start_time'] ?? ($settings['attendance_class_start_time'] ?? '08:00:00'), '08:00:00')
        : date('H:i:s', strtotime((string)($_POST['attendance_class_start_time'] ?? ($settings['attendance_class_start_time'] ?? '08:00'))));
    $attendancePeriodDurationMinutes = max(1, intval($_POST['attendance_period_duration_minutes'] ?? ($settings['attendance_period_duration_minutes'] ?? 45)));
    $attendanceAutoAlertEnabled = isset($_POST['attendance_auto_alert_enabled'])
        ? 1
        : intval($settings['attendance_auto_alert_enabled'] ?? 1);
    $attendanceAbsentMessageTemplate = trim(strip_tags((string)($_POST['attendance_absent_message_template'] ?? ($settings['attendance_absent_message_template'] ?? ''))));
    if ($attendanceAbsentMessageTemplate === '' && function_exists('attendanceGetDefaultAbsentSmsTemplate')) {
        $attendanceAbsentMessageTemplate = attendanceGetDefaultAbsentSmsTemplate();
    }
    $companyName = sanitize($settings['company_name'] ?? '');
    $companyTagline = sanitize($settings['company_tagline'] ?? '');
    $companyAddress = sanitize($settings['company_address'] ?? '');
    $companyPhone = sanitize($settings['company_phone'] ?? '');
    $companyEmail = sanitize($settings['company_email'] ?? '');
    $companyWebsite = sanitize($settings['company_website'] ?? '');
    if ($isSuperAdmin) {
        $loginSettingValue = function (string $key, string $default = '') use ($settings): string {
            if (array_key_exists($key, $_POST)) {
                return sanitize((string)($_POST[$key] ?? ''));
            }
            return sanitize((string)($settings[$key] ?? $default));
        };
        $loginBrandSubtitle = $loginSettingValue('login_brand_subtitle', APP_NAME);
        $loginHeroTitle = $loginSettingValue('login_hero_title');
        $loginHeroSubtitle = $loginSettingValue('login_hero_subtitle');
        $loginPill1 = $loginSettingValue('login_pill_1');
        $loginPill2 = $loginSettingValue('login_pill_2');
        $loginPill3 = $loginSettingValue('login_pill_3');
        $loginMetric1Title = $loginSettingValue('login_metric_1_title');
        $loginMetric1Text = $loginSettingValue('login_metric_1_text');
        $loginMetric2Title = $loginSettingValue('login_metric_2_title');
        $loginMetric2Text = $loginSettingValue('login_metric_2_text');
        $loginMetric3Title = $loginSettingValue('login_metric_3_title');
        $loginMetric3Text = $loginSettingValue('login_metric_3_text');
        $loginCardSubtitle = $loginSettingValue('login_card_subtitle');
        $loginCardTitle = $loginSettingValue('login_card_title');
        $loginUsernameLabel = $loginSettingValue('login_username_label');
        $loginUsernamePlaceholder = $loginSettingValue('login_username_placeholder');
        $loginPasswordLabel = $loginSettingValue('login_password_label');
        $loginPasswordPlaceholder = $loginSettingValue('login_password_placeholder');
        $loginRememberMeLabel = $loginSettingValue('login_remember_me_label');
        $loginButtonText = $loginSettingValue('login_button_text');
        $loginForgotPasswordText = $loginSettingValue('login_forgot_password_text');
        $loginForgotUsernameText = $loginSettingValue('login_forgot_username_text');
        $loginStudentLoginText = $loginSettingValue('login_student_login_text');
        $loginAlertRegisteredText = $loginSettingValue('login_alert_registered_text');
        $loginAlertSchoolRegisteredText = $loginSettingValue('login_alert_school_registered_text');
        $loginAlertResetText = $loginSettingValue('login_alert_reset_text');
    } else {
        $loginBrandSubtitle = trim((string)($settings['login_brand_subtitle'] ?? APP_NAME));
        $loginHeroTitle = trim((string)($settings['login_hero_title'] ?? ''));
        $loginHeroSubtitle = trim((string)($settings['login_hero_subtitle'] ?? ''));
        $loginPill1 = trim((string)($settings['login_pill_1'] ?? ''));
        $loginPill2 = trim((string)($settings['login_pill_2'] ?? ''));
        $loginPill3 = trim((string)($settings['login_pill_3'] ?? ''));
        $loginMetric1Title = trim((string)($settings['login_metric_1_title'] ?? ''));
        $loginMetric1Text = trim((string)($settings['login_metric_1_text'] ?? ''));
        $loginMetric2Title = trim((string)($settings['login_metric_2_title'] ?? ''));
        $loginMetric2Text = trim((string)($settings['login_metric_2_text'] ?? ''));
        $loginMetric3Title = trim((string)($settings['login_metric_3_title'] ?? ''));
        $loginMetric3Text = trim((string)($settings['login_metric_3_text'] ?? ''));
        $loginCardSubtitle = trim((string)($settings['login_card_subtitle'] ?? ''));
        $loginCardTitle = trim((string)($settings['login_card_title'] ?? ''));
        $loginUsernameLabel = trim((string)($settings['login_username_label'] ?? ''));
        $loginUsernamePlaceholder = trim((string)($settings['login_username_placeholder'] ?? ''));
        $loginPasswordLabel = trim((string)($settings['login_password_label'] ?? ''));
        $loginPasswordPlaceholder = trim((string)($settings['login_password_placeholder'] ?? ''));
        $loginRememberMeLabel = trim((string)($settings['login_remember_me_label'] ?? ''));
        $loginButtonText = trim((string)($settings['login_button_text'] ?? ''));
        $loginForgotPasswordText = trim((string)($settings['login_forgot_password_text'] ?? ''));
        $loginForgotUsernameText = trim((string)($settings['login_forgot_username_text'] ?? ''));
        $loginStudentLoginText = trim((string)($settings['login_student_login_text'] ?? ''));
        $loginAlertRegisteredText = trim((string)($settings['login_alert_registered_text'] ?? ''));
        $loginAlertSchoolRegisteredText = trim((string)($settings['login_alert_school_registered_text'] ?? ''));
        $loginAlertResetText = trim((string)($settings['login_alert_reset_text'] ?? ''));
    }

    // Handle theme colors
    $themePreset = sanitize($_POST['theme_preset'] ?? 'default');
    $themePrimary = sanitize($_POST['theme_primary_color'] ?? '#0d6efd');
    $themeSecondary = sanitize($_POST['theme_secondary_color'] ?? '#6c757d');
    $themeSuccess = sanitize($_POST['theme_success_color'] ?? '#198754');
    $themeInfo = sanitize($_POST['theme_info_color'] ?? '#0dcaf0');
    $themeWarning = sanitize($_POST['theme_warning_color'] ?? '#ffc107');
    $themeDanger = sanitize($_POST['theme_danger_color'] ?? '#dc3545');

    // Handle logo uploads
    $schoolLogo = $settings['school_logo'];
    $loginLogo = $settings['login_logo'];
    $bannerLogo = $settings['banner_logo'];
    $companyLogo = $settings['company_logo'] ?? '';

    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadImage($_FILES['school_logo'], LOGO_PATH, 500, 500);
        if ($uploaded !== false) {
            if (!empty($schoolLogo)) deleteFile(LOGO_PATH . $schoolLogo);
            $schoolLogo = $uploaded;
        }
    }

    if (isset($_FILES['login_logo']) && $_FILES['login_logo']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadImage($_FILES['login_logo'], LOGO_PATH, 300, 300);
        if ($uploaded !== false) {
            if (!empty($loginLogo)) deleteFile(LOGO_PATH . $loginLogo);
            $loginLogo = $uploaded;
        }
    }

    if (isset($_FILES['banner_logo']) && $_FILES['banner_logo']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadImage($_FILES['banner_logo'], LOGO_PATH, 200, 80);
        if ($uploaded !== false) {
            if (!empty($bannerLogo)) deleteFile(LOGO_PATH . $bannerLogo);
            $bannerLogo = $uploaded;
        }
    }

    // Update settings
    $settingId = intval($settings['setting_id'] ?? 0);
    if ($settingId <= 0 && $currentSchoolId > 0) {
        $school = function_exists('schoolRegistrationGetSchoolById')
            ? schoolRegistrationGetSchoolById($currentSchoolId)
            : fetchOne("SELECT * FROM schools WHERE school_id = ? LIMIT 1", 'i', [$currentSchoolId]);

        if ($school && function_exists('schoolRegistrationSyncApprovedSchoolSettings')) {
            schoolRegistrationSyncApprovedSchoolSettings($school);
            $settings = getSchoolSettingsBySchoolId($currentSchoolId) ?: $settings;
            $settingId = intval($settings['setting_id'] ?? 0);
        }
    }

    if ($settingId <= 0) {
        $error = 'Unable to identify the current school settings record. Please reopen this page from the school account.';
    } else {

    $updateQuery = "UPDATE school_settings SET
                    school_name = ?, school_address = ?, school_phone = ?,
                    school_email = ?, affiliation_no = ?, school_code = ?, udise_code = ?, current_academic_year = ?,
                    admission_prefix = ?, receipt_prefix = ?,
                    attendance_scan_mode = ?, attendance_class_start_time = ?, attendance_period_duration_minutes = ?, attendance_auto_alert_enabled = ?, attendance_absent_message_template = ?,
                    school_logo = ?, login_logo = ?, banner_logo = ?, company_name = ?, company_tagline = ?,
                    company_address = ?, company_phone = ?, company_email = ?, company_website = ?, company_logo = ?,
                    login_brand_subtitle = ?, login_hero_title = ?, login_hero_subtitle = ?,
                    login_pill_1 = ?, login_pill_2 = ?, login_pill_3 = ?,
                    login_metric_1_title = ?, login_metric_1_text = ?,
                    login_metric_2_title = ?, login_metric_2_text = ?,
                    login_metric_3_title = ?, login_metric_3_text = ?,
                    login_card_subtitle = ?, login_card_title = ?,
                    login_username_label = ?, login_username_placeholder = ?,
                    login_password_label = ?, login_password_placeholder = ?,
                    login_remember_me_label = ?, login_button_text = ?,
                    login_forgot_password_text = ?, login_forgot_username_text = ?,
                    login_student_login_text = ?, login_alert_registered_text = ?,
                    login_alert_school_registered_text = ?, login_alert_reset_text = ?,
                    theme_preset = ?, theme_primary_color = ?, theme_secondary_color = ?,
                    theme_success_color = ?, theme_info_color = ?, theme_warning_color = ?,
                    theme_danger_color = ?,
                    updated_at = NOW()
                    WHERE setting_id = ?";

    $updateValues = [
        $schoolName, $schoolAddress, $schoolPhone,
        $schoolEmail, $affiliationNo, $schoolCode, $udiseCode, $currentAcademicYear,
        $admissionPrefix, $receiptPrefix,
        $attendanceScanMode, $attendanceClassStartTime, $attendancePeriodDurationMinutes, $attendanceAutoAlertEnabled, $attendanceAbsentMessageTemplate,
        $schoolLogo, $loginLogo, $bannerLogo, $companyName, $companyTagline,
        $companyAddress, $companyPhone, $companyEmail, $companyWebsite, $companyLogo,
        $loginBrandSubtitle, $loginHeroTitle, $loginHeroSubtitle,
        $loginPill1, $loginPill2, $loginPill3,
        $loginMetric1Title, $loginMetric1Text,
        $loginMetric2Title, $loginMetric2Text,
        $loginMetric3Title, $loginMetric3Text,
        $loginCardSubtitle, $loginCardTitle,
        $loginUsernameLabel, $loginUsernamePlaceholder,
        $loginPasswordLabel, $loginPasswordPlaceholder,
        $loginRememberMeLabel, $loginButtonText,
        $loginForgotPasswordText, $loginForgotUsernameText,
        $loginStudentLoginText, $loginAlertRegisteredText,
        $loginAlertSchoolRegisteredText, $loginAlertResetText,
        $themePreset, $themePrimary, $themeSecondary,
        $themeSuccess, $themeInfo, $themeWarning,
        $themeDanger,
        $settingId
    ];

    $updateTypes = str_repeat('s', count($updateValues) - 1) . 'i';

    $result = executeQuery($updateQuery, $updateTypes, $updateValues);

    if ($result !== false) {
        logActivity($currentUser['user_id'], 'Update Settings', 'Settings', 'Updated school settings');
        $success = 'Settings updated successfully!';
        $settings = array_merge($settings, [
            'school_name' => $schoolName,
            'school_address' => $schoolAddress,
            'school_phone' => $schoolPhone,
            'school_email' => $schoolEmail,
            'affiliation_no' => $affiliationNo,
            'school_code' => $schoolCode,
            'udise_code' => $udiseCode,
            'current_academic_year' => $currentAcademicYear,
            'admission_prefix' => $admissionPrefix,
            'receipt_prefix' => $receiptPrefix,
            'attendance_scan_mode' => $attendanceScanMode,
            'attendance_class_start_time' => $attendanceClassStartTime,
            'attendance_period_duration_minutes' => $attendancePeriodDurationMinutes,
            'attendance_auto_alert_enabled' => $attendanceAutoAlertEnabled,
            'attendance_absent_message_template' => $attendanceAbsentMessageTemplate,
            'school_logo' => $schoolLogo,
            'login_logo' => $loginLogo,
            'banner_logo' => $bannerLogo,
            'company_name' => $companyName,
            'company_tagline' => $companyTagline,
            'company_address' => $companyAddress,
            'company_phone' => $companyPhone,
            'company_email' => $companyEmail,
            'company_website' => $companyWebsite,
            'company_logo' => $companyLogo,
            'login_brand_subtitle' => $loginBrandSubtitle,
            'login_hero_title' => $loginHeroTitle,
            'login_hero_subtitle' => $loginHeroSubtitle,
            'login_pill_1' => $loginPill1,
            'login_pill_2' => $loginPill2,
            'login_pill_3' => $loginPill3,
            'login_metric_1_title' => $loginMetric1Title,
            'login_metric_1_text' => $loginMetric1Text,
            'login_metric_2_title' => $loginMetric2Title,
            'login_metric_2_text' => $loginMetric2Text,
            'login_metric_3_title' => $loginMetric3Title,
            'login_metric_3_text' => $loginMetric3Text,
            'login_card_subtitle' => $loginCardSubtitle,
            'login_card_title' => $loginCardTitle,
            'login_username_label' => $loginUsernameLabel,
            'login_username_placeholder' => $loginUsernamePlaceholder,
            'login_password_label' => $loginPasswordLabel,
            'login_password_placeholder' => $loginPasswordPlaceholder,
            'login_remember_me_label' => $loginRememberMeLabel,
            'login_button_text' => $loginButtonText,
            'login_forgot_password_text' => $loginForgotPasswordText,
            'login_forgot_username_text' => $loginForgotUsernameText,
            'login_student_login_text' => $loginStudentLoginText,
            'login_alert_registered_text' => $loginAlertRegisteredText,
            'login_alert_school_registered_text' => $loginAlertSchoolRegisteredText,
            'login_alert_reset_text' => $loginAlertResetText,
            'theme_preset' => $themePreset,
            'theme_primary_color' => $themePrimary,
            'theme_secondary_color' => $themeSecondary,
            'theme_success_color' => $themeSuccess,
            'theme_info_color' => $themeInfo,
            'theme_warning_color' => $themeWarning,
            'theme_danger_color' => $themeDanger,
        ]);
    } else {
        $error = 'Failed to update settings';
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
                <i class="bi bi-gear-fill"></i> School Settings
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card dashboard-card">
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">

                    <!-- School Information -->
                    <h5 class="mb-3"><i class="bi bi-building"></i> School Information</h5>

                    <div class="mb-3">
                        <label for="school_name" class="form-label required">School Name</label>
                        <input type="text" class="form-control" id="school_name" name="school_name"
                               value="<?php echo htmlspecialchars($settings['school_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="school_address" class="form-label">School Address</label>
                        <textarea class="form-control" id="school_address" name="school_address" rows="3"><?php echo htmlspecialchars($settings['school_address']); ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="school_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="school_phone" name="school_phone"
                                   value="<?php echo htmlspecialchars($settings['school_phone']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="school_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="school_email" name="school_email"
                                   value="<?php echo htmlspecialchars($settings['school_email']); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="affiliation_no" class="form-label">Affiliation No.</label>
                            <input type="text" class="form-control" id="affiliation_no" name="affiliation_no"
                                   value="<?php echo htmlspecialchars($settings['affiliation_no'] ?? ''); ?>"
                                   placeholder="School affiliation number">
                        </div>
                        <div class="col-md-4">
                            <label for="school_code" class="form-label">School Code</label>
                            <input type="text" class="form-control" id="school_code" name="school_code"
                                   value="<?php echo htmlspecialchars($settings['school_code'] ?? ''); ?>"
                                   placeholder="School code">
                        </div>
                        <div class="col-md-4">
                            <label for="udise_code" class="form-label">UDISE / Reg. No.</label>
                            <input type="text" class="form-control" id="udise_code" name="udise_code"
                                   value="<?php echo htmlspecialchars($settings['udise_code'] ?? ''); ?>"
                                   placeholder="UDISE or registration number">
                        </div>
                    </div>

                    <?php if (!$isSuperAdmin): ?>
                    <!-- Academic Settings -->
                    <h5 class="mb-3"><i class="bi bi-calendar-check"></i> Academic Settings</h5>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="current_academic_year" class="form-label required">Current Academic Year</label>
                            <input type="text" class="form-control" id="current_academic_year" name="current_academic_year"
                                   value="<?php echo htmlspecialchars($settings['current_academic_year']); ?>"
                                   placeholder="2024-2025" required>
                        </div>
                        <div class="col-md-4">
                            <label for="admission_prefix" class="form-label required">Admission Number Prefix</label>
                            <input type="text" class="form-control" id="admission_prefix" name="admission_prefix"
                                   value="<?php echo htmlspecialchars($settings['admission_prefix']); ?>"
                                   placeholder="STU" required maxlength="10">
                        </div>
                        <div class="col-md-4">
                            <label for="receipt_prefix" class="form-label required">Receipt Number Prefix</label>
                            <input type="text" class="form-control" id="receipt_prefix" name="receipt_prefix"
                                   value="<?php echo htmlspecialchars($settings['receipt_prefix']); ?>"
                                   placeholder="REC" required maxlength="10">
                        </div>
                    </div>

                    <!-- Attendance Scan Control -->
                    <h5 class="mb-3"><i class="bi bi-qr-code-scan"></i> Attendance Scan Control</h5>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Choose whether attendance is tracked once per day or by class period. When period mode is active, each scan creates a separate attendance record and absent alerts can be sent for every class.
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="attendance_scan_mode" class="form-label required">Scan Mode</label>
                            <select class="form-select" id="attendance_scan_mode" name="attendance_scan_mode">
                                <option value="daily" <?php echo (($settings['attendance_scan_mode'] ?? 'daily') === 'daily') ? 'selected' : ''; ?>>One-time per day</option>
                                <option value="period" <?php echo (($settings['attendance_scan_mode'] ?? 'daily') === 'period') ? 'selected' : ''; ?>>Every class / period</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="attendance_class_start_time" class="form-label required">Class Start Time</label>
                            <input type="time" class="form-control" id="attendance_class_start_time" name="attendance_class_start_time"
                                   value="<?php echo htmlspecialchars(substr(function_exists('attendanceNormalizeTimeValue') ? attendanceNormalizeTimeValue($settings['attendance_class_start_time'] ?? '08:00:00', '08:00:00') : ($settings['attendance_class_start_time'] ?? '08:00:00'), 0, 5)); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="attendance_period_duration_minutes" class="form-label required">Period Duration (Minutes)</label>
                            <input type="number" class="form-control" id="attendance_period_duration_minutes" name="attendance_period_duration_minutes"
                                   min="1" max="240"
                                   value="<?php echo intval($settings['attendance_period_duration_minutes'] ?? 45); ?>">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="attendance_auto_alert_enabled" name="attendance_auto_alert_enabled"
                               <?php echo !empty($settings['attendance_auto_alert_enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="attendance_auto_alert_enabled">
                            Enable auto absent alert SMS
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="attendance_absent_message_template" class="form-label">Auto Absent Message Template</label>
                        <textarea class="form-control" id="attendance_absent_message_template" name="attendance_absent_message_template" rows="4"
                                  placeholder="Dear Parent, {student_details} was marked ABSENT {period_text} on {date} at {school_name}. Please contact the school office if this needs correction."><?php echo htmlspecialchars($settings['attendance_absent_message_template'] ?? (function_exists('attendanceGetDefaultAbsentSmsTemplate') ? attendanceGetDefaultAbsentSmsTemplate() : '')); ?></textarea>
                        <small class="text-muted d-block mt-2">
                            Available placeholders: {student_name}, {admission_no}, {roll_no}, {father_name}, {class_name}, {section_name}, {class_display}, {student_details}, {date}, {date_iso}, {period_text}, {period_label}, {period_start}, {period_end}, {school_name}
                        </small>
                    </div>
                    <?php endif; ?>

                    <hr>

                    <!-- Logos -->
                    <h5 class="mb-3"><i class="bi bi-image"></i> Logos & Branding</h5>

                    <div class="row mb-3">
                        <!-- School Logo -->
                        <div class="col-md-4">
                            <label class="form-label">School Logo (Main)</label>
                            <div class="mb-2">
                                <?php if (!empty($settings['school_logo'])): ?>
                                    <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $settings['school_logo']; ?>"
                                         class="img-thumbnail" style="max-width: 150px;" alt="School Logo">
                                <?php else: ?>
                                    <div class="alert alert-secondary">No logo uploaded</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" name="school_logo" accept="image/*">
                            <small class="text-muted">Recommended: 500x500px</small>
                        </div>

                        <!-- Login Logo -->
                        <div class="col-md-4">
                            <label class="form-label">Login Page Logo</label>
                            <div class="mb-2">
                                <?php if (!empty($settings['login_logo'])): ?>
                                    <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $settings['login_logo']; ?>"
                                         class="img-thumbnail" style="max-width: 150px;" alt="Login Logo">
                                <?php else: ?>
                                    <div class="alert alert-secondary">No logo uploaded</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" name="login_logo" accept="image/*">
                            <small class="text-muted">Recommended: 300x300px</small>
                        </div>

                        <!-- Banner Logo -->
                        <div class="col-md-4">
                            <label class="form-label">Banner Logo (Header)</label>
                            <div class="mb-2">
                                <?php if (!empty($settings['banner_logo'])): ?>
                                    <img src="<?php echo APP_URL . '/assets/uploads/logos/' . $settings['banner_logo']; ?>"
                                         class="img-thumbnail" style="max-width: 150px;" alt="Banner Logo">
                                <?php else: ?>
                                    <div class="alert alert-secondary">No logo uploaded</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" name="banner_logo" accept="image/*">
                            <small class="text-muted">Recommended: 200x80px</small>
                        </div>

                    </div>

                    <?php if ($isSuperAdmin): ?>
                    <hr>

                    <!-- Staff Login Page Text -->
                    <h5 class="mb-3" id="login-page-text"><i class="bi bi-box-arrow-in-right"></i> Staff Login Page Text</h5>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Super Admin control:</strong> These settings update the staff login page shown on <?php echo APP_NAME; ?>.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_brand_subtitle" class="form-label">Brand Subtitle</label>
                                <input type="text" class="form-control" id="login_brand_subtitle" name="login_brand_subtitle"
                                       value="<?php echo htmlspecialchars($settings['login_brand_subtitle'] ?? APP_NAME); ?>"
                                       placeholder="<?php echo htmlspecialchars(APP_NAME); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_card_subtitle" class="form-label">Card Subtitle</label>
                                <input type="text" class="form-control" id="login_card_subtitle" name="login_card_subtitle"
                                       value="<?php echo htmlspecialchars($settings['login_card_subtitle'] ?? 'Staff Login'); ?>"
                                       placeholder="Staff Login">
                            </div>
                            <div class="col-md-4">
                                <label for="login_card_title" class="form-label">Card Title</label>
                                <input type="text" class="form-control" id="login_card_title" name="login_card_title"
                                       value="<?php echo htmlspecialchars($settings['login_card_title'] ?? 'Login to Your Account'); ?>"
                                       placeholder="Login to Your Account">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="login_hero_title" class="form-label">Hero Title</label>
                            <input type="text" class="form-control" id="login_hero_title" name="login_hero_title"
                                   value="<?php echo htmlspecialchars($settings['login_hero_title'] ?? 'Secure access for staff, records, and school operations.'); ?>"
                                   placeholder="Secure access for staff, records, and school operations.">
                        </div>

                        <div class="mb-3">
                            <label for="login_hero_subtitle" class="form-label">Hero Subtitle</label>
                            <textarea class="form-control" id="login_hero_subtitle" name="login_hero_subtitle" rows="3"
                                      placeholder="Sign in to manage admissions, fees, reports, marks, documents, and daily workflows."><?php echo htmlspecialchars($settings['login_hero_subtitle'] ?? ''); ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_pill_1" class="form-label">Pill Text 1</label>
                                <input type="text" class="form-control" id="login_pill_1" name="login_pill_1"
                                       value="<?php echo htmlspecialchars($settings['login_pill_1'] ?? 'Role-based access'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_pill_2" class="form-label">Pill Text 2</label>
                                <input type="text" class="form-control" id="login_pill_2" name="login_pill_2"
                                       value="<?php echo htmlspecialchars($settings['login_pill_2'] ?? 'Reports and receipts'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_pill_3" class="form-label">Pill Text 3</label>
                                <input type="text" class="form-control" id="login_pill_3" name="login_pill_3"
                                       value="<?php echo htmlspecialchars($settings['login_pill_3'] ?? 'Student management'); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_metric_1_title" class="form-label">Metric 1 Title</label>
                                <input type="text" class="form-control" id="login_metric_1_title" name="login_metric_1_title"
                                       value="<?php echo htmlspecialchars($settings['login_metric_1_title'] ?? 'Secure'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_metric_2_title" class="form-label">Metric 2 Title</label>
                                <input type="text" class="form-control" id="login_metric_2_title" name="login_metric_2_title"
                                       value="<?php echo htmlspecialchars($settings['login_metric_2_title'] ?? 'Fast'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_metric_3_title" class="form-label">Metric 3 Title</label>
                                <input type="text" class="form-control" id="login_metric_3_title" name="login_metric_3_title"
                                       value="<?php echo htmlspecialchars($settings['login_metric_3_title'] ?? 'Reliable'); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_metric_1_text" class="form-label">Metric 1 Text</label>
                                <input type="text" class="form-control" id="login_metric_1_text" name="login_metric_1_text"
                                       value="<?php echo htmlspecialchars($settings['login_metric_1_text'] ?? 'Controlled by user role'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_metric_2_text" class="form-label">Metric 2 Text</label>
                                <input type="text" class="form-control" id="login_metric_2_text" name="login_metric_2_text"
                                       value="<?php echo htmlspecialchars($settings['login_metric_2_text'] ?? 'Quick access on any device'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_metric_3_text" class="form-label">Metric 3 Text</label>
                                <input type="text" class="form-control" id="login_metric_3_text" name="login_metric_3_text"
                                       value="<?php echo htmlspecialchars($settings['login_metric_3_text'] ?? 'Daily school operations ready'); ?>">
                            </div>
                        </div>

                        <h6 class="mb-3 mt-4"><i class="bi bi-ui-checks-grid"></i> Form Labels & Helper Text</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_username_label" class="form-label">Username Label</label>
                                <input type="text" class="form-control" id="login_username_label" name="login_username_label"
                                       value="<?php echo htmlspecialchars($settings['login_username_label'] ?? 'Username or Email'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_username_placeholder" class="form-label">Username Placeholder</label>
                                <input type="text" class="form-control" id="login_username_placeholder" name="login_username_placeholder"
                                       value="<?php echo htmlspecialchars($settings['login_username_placeholder'] ?? 'Enter username or email'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_password_label" class="form-label">Password Label</label>
                                <input type="text" class="form-control" id="login_password_label" name="login_password_label"
                                       value="<?php echo htmlspecialchars($settings['login_password_label'] ?? 'Password'); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_password_placeholder" class="form-label">Password Placeholder</label>
                                <input type="text" class="form-control" id="login_password_placeholder" name="login_password_placeholder"
                                       value="<?php echo htmlspecialchars($settings['login_password_placeholder'] ?? 'Enter password'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_remember_me_label" class="form-label">Remember Me Label</label>
                                <input type="text" class="form-control" id="login_remember_me_label" name="login_remember_me_label"
                                       value="<?php echo htmlspecialchars($settings['login_remember_me_label'] ?? 'Remember me'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_button_text" class="form-label">Login Button Text</label>
                                <input type="text" class="form-control" id="login_button_text" name="login_button_text"
                                       value="<?php echo htmlspecialchars($settings['login_button_text'] ?? 'Login'); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="login_forgot_password_text" class="form-label">Forgot Password Text</label>
                                <input type="text" class="form-control" id="login_forgot_password_text" name="login_forgot_password_text"
                                       value="<?php echo htmlspecialchars($settings['login_forgot_password_text'] ?? 'Forgot Password?'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_forgot_username_text" class="form-label">Forgot Username Text</label>
                                <input type="text" class="form-control" id="login_forgot_username_text" name="login_forgot_username_text"
                                       value="<?php echo htmlspecialchars($settings['login_forgot_username_text'] ?? 'Forgot Username?'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="login_student_login_text" class="form-label">Student Login Text</label>
                                <input type="text" class="form-control" id="login_student_login_text" name="login_student_login_text"
                                       value="<?php echo htmlspecialchars($settings['login_student_login_text'] ?? 'Student Login'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="login_alert_registered_text" class="form-label">Account Registered Alert</label>
                            <textarea class="form-control" id="login_alert_registered_text" name="login_alert_registered_text" rows="2"><?php echo htmlspecialchars($settings['login_alert_registered_text'] ?? 'Your account has been created. Please login with your new credentials.'); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="login_alert_school_registered_text" class="form-label">School Registration Alert</label>
                            <textarea class="form-control" id="login_alert_school_registered_text" name="login_alert_school_registered_text" rows="2"><?php echo htmlspecialchars($settings['login_alert_school_registered_text'] ?? 'School registration submitted. Waiting for Super Admin approval.'); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="login_alert_reset_text" class="form-label">Password Reset Alert</label>
                            <textarea class="form-control" id="login_alert_reset_text" name="login_alert_reset_text" rows="2"><?php echo htmlspecialchars($settings['login_alert_reset_text'] ?? 'Password updated successfully. You can now login with the new password.'); ?></textarea>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <!-- Theme Colors -->
                    <h5 class="mb-3"><i class="bi bi-palette-fill"></i> Theme Colors & Branding</h5>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Customize Your School Colors:</strong> Choose a preset theme or customize individual colors to match your school branding.
                    </div>

                    <!-- Theme Preset Selection -->
                    <div class="mb-4">
                        <label for="theme_preset" class="form-label fw-bold">Choose Theme Preset</label>
                        <select class="form-select" id="theme_preset" name="theme_preset">
                            <option value="default" <?php echo ($settings['theme_preset'] ?? 'default') == 'default' ? 'selected' : ''; ?>>Default (Bootstrap Blue)</option>
                            <option value="school_green" <?php echo ($settings['theme_preset'] ?? '') == 'school_green' ? 'selected' : ''; ?>>School Green</option>
                            <option value="royal_blue" <?php echo ($settings['theme_preset'] ?? '') == 'royal_blue' ? 'selected' : ''; ?>>Royal Blue</option>
                            <option value="purple_academic" <?php echo ($settings['theme_preset'] ?? '') == 'purple_academic' ? 'selected' : ''; ?>>Purple Academic</option>
                            <option value="orange_energy" <?php echo ($settings['theme_preset'] ?? '') == 'orange_energy' ? 'selected' : ''; ?>>Orange Energy</option>
                            <option value="teal_modern" <?php echo ($settings['theme_preset'] ?? '') == 'teal_modern' ? 'selected' : ''; ?>>Teal Modern</option>
                            <option value="crimson_tradition" <?php echo ($settings['theme_preset'] ?? '') == 'crimson_tradition' ? 'selected' : ''; ?>>Crimson Tradition</option>
                            <option value="navy_professional" <?php echo ($settings['theme_preset'] ?? '') == 'navy_professional' ? 'selected' : ''; ?>>Navy Professional</option>
                            <option value="custom" <?php echo ($settings['theme_preset'] ?? '') == 'custom' ? 'selected' : ''; ?>>Custom Colors</option>
                        </select>
                        <small class="text-muted">Select a predefined theme or choose "Custom Colors" to set your own</small>
                    </div>

                    <!-- Custom Color Pickers -->
                    <div id="customColorsSection">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="theme_primary_color" class="form-label">Primary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="theme_primary_color" name="theme_primary_color"
                                           value="<?php echo htmlspecialchars($settings['theme_primary_color'] ?? '#0d6efd'); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['theme_primary_color'] ?? '#0d6efd'); ?>" readonly>
                                </div>
                                <small class="text-muted">Main brand color (buttons, links)</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="theme_secondary_color" class="form-label">Secondary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="theme_secondary_color" name="theme_secondary_color"
                                           value="<?php echo htmlspecialchars($settings['theme_secondary_color'] ?? '#6c757d'); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['theme_secondary_color'] ?? '#6c757d'); ?>" readonly>
                                </div>
                                <small class="text-muted">Secondary elements</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="theme_success_color" class="form-label">Success Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="theme_success_color" name="theme_success_color"
                                           value="<?php echo htmlspecialchars($settings['theme_success_color'] ?? '#198754'); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['theme_success_color'] ?? '#198754'); ?>" readonly>
                                </div>
                                <small class="text-muted">Success messages, confirmation</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="theme_info_color" class="form-label">Info Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="theme_info_color" name="theme_info_color"
                                           value="<?php echo htmlspecialchars($settings['theme_info_color'] ?? '#0dcaf0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['theme_info_color'] ?? '#0dcaf0'); ?>" readonly>
                                </div>
                                <small class="text-muted">Information, highlights</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="theme_warning_color" class="form-label">Warning Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="theme_warning_color" name="theme_warning_color"
                                           value="<?php echo htmlspecialchars($settings['theme_warning_color'] ?? '#ffc107'); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['theme_warning_color'] ?? '#ffc107'); ?>" readonly>
                                </div>
                                <small class="text-muted">Warnings, caution alerts</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="theme_danger_color" class="form-label">Danger Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="theme_danger_color" name="theme_danger_color"
                                           value="<?php echo htmlspecialchars($settings['theme_danger_color'] ?? '#dc3545'); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($settings['theme_danger_color'] ?? '#dc3545'); ?>" readonly>
                                </div>
                                <small class="text-muted">Errors, delete actions</small>
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="card bg-light mt-3">
                            <div class="card-body">
                                <h6><i class="bi bi-eye"></i> Live Preview</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn" id="preview-primary" style="background-color: <?php echo htmlspecialchars($settings['theme_primary_color'] ?? '#0d6efd'); ?>; color: white;">Primary</button>
                                    <button type="button" class="btn" id="preview-secondary" style="background-color: <?php echo htmlspecialchars($settings['theme_secondary_color'] ?? '#6c757d'); ?>; color: white;">Secondary</button>
                                    <button type="button" class="btn" id="preview-success" style="background-color: <?php echo htmlspecialchars($settings['theme_success_color'] ?? '#198754'); ?>; color: white;">Success</button>
                                    <button type="button" class="btn" id="preview-info" style="background-color: <?php echo htmlspecialchars($settings['theme_info_color'] ?? '#0dcaf0'); ?>; color: white;">Info</button>
                                    <button type="button" class="btn" id="preview-warning" style="background-color: <?php echo htmlspecialchars($settings['theme_warning_color'] ?? '#ffc107'); ?>; color: black;">Warning</button>
                                    <button type="button" class="btn" id="preview-danger" style="background-color: <?php echo htmlspecialchars($settings['theme_danger_color'] ?? '#dc3545'); ?>; color: white;">Danger</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                        <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary btn-lg ms-2">
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
// Theme presets configuration
const themePresets = {
    'default': {
        primary: '#0d6efd',
        secondary: '#6c757d',
        success: '#198754',
        info: '#0dcaf0',
        warning: '#ffc107',
        danger: '#dc3545'
    },
    'school_green': {
        primary: '#28a745',
        secondary: '#6c757d',
        success: '#20c997',
        info: '#17a2b8',
        warning: '#ffc107',
        danger: '#dc3545'
    },
    'royal_blue': {
        primary: '#0056b3',
        secondary: '#495057',
        success: '#28a745',
        info: '#007bff',
        warning: '#fd7e14',
        danger: '#dc3545'
    },
    'purple_academic': {
        primary: '#6f42c1',
        secondary: '#6c757d',
        success: '#198754',
        info: '#9b59b6',
        warning: '#f39c12',
        danger: '#e74c3c'
    },
    'orange_energy': {
        primary: '#fd7e14',
        secondary: '#6c757d',
        success: '#20c997',
        info: '#17a2b8',
        warning: '#ffc107',
        danger: '#dc3545'
    },
    'teal_modern': {
        primary: '#20c997',
        secondary: '#6c757d',
        success: '#28a745',
        info: '#17a2b8',
        warning: '#ffc107',
        danger: '#dc3545'
    },
    'crimson_tradition': {
        primary: '#dc143c',
        secondary: '#6c757d',
        success: '#198754',
        info: '#0dcaf0',
        warning: '#ffc107',
        danger: '#8b0000'
    },
    'navy_professional': {
        primary: '#001f3f',
        secondary: '#495057',
        success: '#198754',
        info: '#0dcaf0',
        warning: '#ffc107',
        danger: '#dc3545'
    }
};

// Apply theme preset
function applyThemePreset(preset) {
    if (preset === 'custom') {
        // Don't change colors for custom
        return;
    }

    const colors = themePresets[preset];
    if (colors) {
        $('#theme_primary_color').val(colors.primary).next('input').val(colors.primary);
        $('#theme_secondary_color').val(colors.secondary).next('input').val(colors.secondary);
        $('#theme_success_color').val(colors.success).next('input').val(colors.success);
        $('#theme_info_color').val(colors.info).next('input').val(colors.info);
        $('#theme_warning_color').val(colors.warning).next('input').val(colors.warning);
        $('#theme_danger_color').val(colors.danger).next('input').val(colors.danger);

        // Update preview buttons
        updatePreview();
    }
}

// Update preview buttons
function updatePreview() {
    $('#preview-primary').css('background-color', $('#theme_primary_color').val());
    $('#preview-secondary').css('background-color', $('#theme_secondary_color').val());
    $('#preview-success').css('background-color', $('#theme_success_color').val());
    $('#preview-info').css('background-color', $('#theme_info_color').val());
    $('#preview-warning').css('background-color', $('#theme_warning_color').val());
    $('#preview-danger').css('background-color', $('#theme_danger_color').val());
}

// Handle preset selection
$('#theme_preset').on('change', function() {
    const selectedPreset = $(this).val();
    applyThemePreset(selectedPreset);
});

// Update text input when color picker changes
$('input[type=\"color\"]').on('change', function() {
    $(this).next('input[type=\"text\"]').val($(this).val());
    updatePreview();
    // Change preset to custom if user manually changes colors
    $('#theme_preset').val('custom');
});

// Sync color picker with text input
$('input[type=\"color\"]').next('input[type=\"text\"]').on('input', function() {
    const color = $(this).val();
    if (/^#[0-9A-F]{6}$/i.test(color)) {
        $(this).prev('input[type=\"color\"]').val(color);
        updatePreview();
        $('#theme_preset').val('custom');
    }
});
";

// Include footer
include '../../includes/footer.php';
?>
