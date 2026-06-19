<?php
/**
 * Parent Portal Helpers
 * Shared schema, access, and rendering helpers for parent-facing pages
 */

if (!function_exists('parentPortalEscape')) {
    function parentPortalEscape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('parentPortalEnsureSchema')) {
    function parentPortalEnsureSchema() {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $conn = getDbConnection();

        $ensureColumn = function (string $table, string $column, string $ddl) use ($conn) {
            $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$result || $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
                    error_log("Parent portal schema update failed for $table.$column: " . $conn->error);
                }
            }
        };

        $roleCheck = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role' LIMIT 1");
        if ($roleCheck && ($row = $roleCheck->fetch_assoc())) {
            $columnType = strtolower((string)($row['COLUMN_TYPE'] ?? ''));
            if (strpos($columnType, 'parent') === false || strpos($columnType, 'student') === false) {
                if (!$conn->query("ALTER TABLE users MODIFY role ENUM('super_admin','admin','accountant','clerk','teacher','parent','student') NOT NULL DEFAULT 'clerk'")) {
                    error_log('Parent role enum update failed: ' . $conn->error);
                }
            }
        }

        $ensureColumn('school_settings', 'upi_id', "upi_id varchar(100) DEFAULT NULL AFTER currency_symbol");
        $ensureColumn('school_settings', 'payment_recipient_name', "payment_recipient_name varchar(150) DEFAULT NULL AFTER upi_id");
        $ensureColumn('school_settings', 'payment_note', "payment_note varchar(255) DEFAULT NULL AFTER payment_recipient_name");

        $conn->query(
            "CREATE TABLE IF NOT EXISTS parent_student_links (
                link_id int(11) NOT NULL AUTO_INCREMENT,
                parent_user_id int(11) NOT NULL,
                student_id int(11) NOT NULL,
                relation varchar(50) NOT NULL DEFAULT 'Parent',
                is_active tinyint(1) NOT NULL DEFAULT 1,
                created_by int(11) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (link_id),
                UNIQUE KEY idx_parent_student (parent_user_id, student_id),
                KEY idx_parent_user (parent_user_id),
                KEY idx_student_id (student_id),
                CONSTRAINT fk_parent_link_parent FOREIGN KEY (parent_user_id) REFERENCES users (user_id) ON DELETE CASCADE,
                CONSTRAINT fk_parent_link_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS parent_announcements (
                announcement_id int(11) NOT NULL AUTO_INCREMENT,
                title varchar(200) NOT NULL,
                message text NOT NULL,
                attachment_url varchar(255) DEFAULT NULL,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                publish_date date DEFAULT NULL,
                expire_date date DEFAULT NULL,
                created_by int(11) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (announcement_id),
                KEY idx_active_dates (is_active, publish_date, expire_date),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $ensureColumn('parent_announcements', 'school_id', "school_id int(11) DEFAULT NULL AFTER created_by");

        $conn->query(
            "UPDATE parent_announcements pa
             LEFT JOIN users creator ON pa.created_by = creator.user_id
             SET pa.school_id = COALESCE(pa.school_id, creator.school_id)
             WHERE pa.school_id IS NULL AND COALESCE(creator.school_id, 0) > 0"
        );

        return true;
    }
}

if (!function_exists('requireParentPortalLogin')) {
    function requireParentPortalLogin() {
        requireLogin();

        $user = getCurrentUser();
        if (!$user || ($user['role'] ?? '') !== 'parent') {
            redirect(APP_URL . '/modules/auth/login.php');
        }
    }
}

if (!function_exists('parentPortalGetLinkedStudents')) {
    function parentPortalGetLinkedStudents($parentUserId, $includeInactive = false) {
        $parentUserId = intval($parentUserId);
        if ($parentUserId <= 0) {
            return [];
        }

        $query = "SELECT
                    pl.link_id,
                    pl.relation,
                    pl.is_active as link_is_active,
                    s.student_id, s.admission_no, s.student_name, s.roll_no, s.photo, s.status,
                    s.contact_no, s.email, s.father_name, s.mother_name, s.date_of_birth,
                    c.class_name, c.class_order, sec.section_name
                  FROM parent_student_links pl
                  JOIN students s ON pl.student_id = s.student_id
                  LEFT JOIN classes c ON s.class_id = c.class_id
                  LEFT JOIN sections sec ON s.section_id = sec.section_id
                  WHERE pl.parent_user_id = ?";

        $params = [$parentUserId];
        $types = 'i';

        if (!$includeInactive) {
            $query .= " AND pl.is_active = 1";
        }

        $query .= " ORDER BY COALESCE(c.class_order, 9999), COALESCE(sec.section_name, ''), s.student_name";

        return fetchAll($query, $types, $params);
    }
}

if (!function_exists('parentPortalGetLinkedStudentIds')) {
    function parentPortalGetLinkedStudentIds($parentUserId, $includeInactive = false) {
        $students = parentPortalGetLinkedStudents($parentUserId, $includeInactive);
        return array_map(fn($student) => intval($student['student_id']), $students);
    }
}

if (!function_exists('parentPortalHasStudentAccess')) {
    function parentPortalHasStudentAccess($parentUserId, $studentId) {
        $parentUserId = intval($parentUserId);
        $studentId = intval($studentId);
        if ($parentUserId <= 0 || $studentId <= 0) {
            return false;
        }

        $row = fetchOne(
            "SELECT link_id FROM parent_student_links WHERE parent_user_id = ? AND student_id = ? AND is_active = 1 LIMIT 1",
            'ii',
            [$parentUserId, $studentId]
        );

        return !empty($row);
    }
}

if (!function_exists('parentPortalGetAnnouncements')) {
    function parentPortalGetAnnouncements($limit = 8) {
        $limit = max(1, min(50, intval($limit)));
        $currentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
        $query = "SELECT pa.announcement_id, pa.title, pa.message, pa.attachment_url, pa.publish_date, pa.expire_date, pa.created_at
                  FROM parent_announcements pa
                  LEFT JOIN users creator ON pa.created_by = creator.user_id
                  WHERE pa.is_active = 1
                    AND (pa.publish_date IS NULL OR pa.publish_date <= CURDATE())
                    AND (pa.expire_date IS NULL OR pa.expire_date >= CURDATE())";

        $params = [];
        $types = '';
        if ($currentSchoolId > 0) {
            $query .= " AND COALESCE(pa.school_id, creator.school_id, 0) IN (?, 0)";
            $params[] = $currentSchoolId;
            $types .= 'i';
        }

        $query .= " ORDER BY COALESCE(pa.publish_date, pa.created_at) DESC, pa.announcement_id DESC LIMIT {$limit}";
        return empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    }
}

if (!function_exists('parentPortalGetAnnouncementById')) {
    function parentPortalGetAnnouncementById($announcementId) {
        $announcementId = intval($announcementId);
        if ($announcementId <= 0) {
            return null;
        }

        $currentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
        $query = "SELECT pa.*, creator.full_name AS created_by_name
             FROM parent_announcements pa
             LEFT JOIN users creator ON pa.created_by = creator.user_id
             WHERE pa.announcement_id = ?";
        $params = [$announcementId];
        $types = 'i';

        if ($currentSchoolId > 0) {
            $query .= " AND COALESCE(pa.school_id, creator.school_id, 0) = ?";
            $params[] = $currentSchoolId;
            $types .= 'i';
        }

        return fetchOne($query, $types, $params);
    }
}

if (!function_exists('parentPortalGetAnnouncementList')) {
    function parentPortalGetAnnouncementList($limit = 100) {
        $limit = max(1, min(500, intval($limit)));
        $currentSchoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
        $query = "SELECT pa.*, creator.full_name AS created_by_name
             FROM parent_announcements pa
             LEFT JOIN users creator ON pa.created_by = creator.user_id";
        $params = [];
        $types = '';

        if ($currentSchoolId > 0) {
            $query .= " WHERE COALESCE(pa.school_id, creator.school_id, 0) = ?";
            $params[] = $currentSchoolId;
            $types .= 'i';
        }

        $query .= " ORDER BY pa.created_at DESC, pa.announcement_id DESC LIMIT {$limit}";
        return empty($types) ? fetchAll($query) : fetchAll($query, $types, $params);
    }
}

if (!function_exists('parentPortalGetPaymentSettings')) {
    function parentPortalGetPaymentSettings() {
        $settings = getSchoolSettings();
        return [
            'upi_id' => trim((string)($settings['upi_id'] ?? '')),
            'payment_recipient_name' => trim((string)($settings['payment_recipient_name'] ?? ($settings['school_name'] ?? APP_NAME))),
            'payment_note' => trim((string)($settings['payment_note'] ?? 'School fee payment')),
        ];
    }
}

if (!function_exists('parentPortalBuildUpiLink')) {
    function parentPortalBuildUpiLink($upiId, $payeeName, $amount = 0, $note = '') {
        $upiId = trim((string)$upiId);
        if ($upiId === '') {
            return '';
        }

        $params = [
            'pa' => $upiId,
            'pn' => trim((string)($payeeName ?: APP_NAME)),
            'cu' => 'INR',
        ];

        $amount = floatval($amount);
        if ($amount > 0) {
            $params['am'] = number_format($amount, 2, '.', '');
        }

        $note = trim((string)$note);
        if ($note !== '') {
            $params['tn'] = $note;
        }

        return 'upi://pay?' . http_build_query($params);
    }
}

if (!function_exists('parentPortalGetReceipts')) {
    function parentPortalGetReceipts(array $studentIds, $limit = 20) {
        $studentIds = array_values(array_filter(array_map('intval', $studentIds), fn($id) => $id > 0));
        $limit = max(1, min(100, intval($limit)));
        if (empty($studentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $types = str_repeat('i', count($studentIds));
        $query = "SELECT
                    fr.receipt_id, fr.receipt_no, fr.payment_date, fr.amount_paid, fr.payment_mode, fr.transaction_id,
                    fr.remarks, fr.is_cancelled,
                    s.student_id, s.student_name, s.admission_no, s.roll_no,
                    c.class_name, sec.section_name
                  FROM fee_receipts fr
                  JOIN students s ON fr.student_id = s.student_id
                  LEFT JOIN classes c ON s.class_id = c.class_id
                  LEFT JOIN sections sec ON s.section_id = sec.section_id
                  WHERE fr.is_cancelled = 0
                    AND fr.student_id IN ({$placeholders})
                  ORDER BY fr.payment_date DESC, fr.receipt_id DESC
                  LIMIT {$limit}";

        return fetchAll($query, $types, $studentIds);
    }
}

if (!function_exists('parentPortalGetFeeSummary')) {
    function parentPortalGetFeeSummary($studentId, $asOfDate = null) {
        return getStudentFeeSummary(intval($studentId), $asOfDate);
    }
}

if (!function_exists('parentPortalGetActiveExams')) {
    function parentPortalGetActiveExams($limit = 25) {
        $limit = max(1, min(100, intval($limit)));
        return fetchAll("SELECT exam_id, exam_name, exam_type, exam_date, academic_year, description
                         FROM exams
                         WHERE is_active = 1
                         ORDER BY exam_date DESC, exam_id DESC
                         LIMIT {$limit}");
    }
}

if (!function_exists('parentPortalStyles')) {
    function parentPortalStyles() {
        return <<<'CSS'
.parent-shell {
    min-height: 100vh;
    color: #0f172a;
    background:
        linear-gradient(180deg, #f8fbff 0%, #eef3f8 100%);
}

.parent-topbar {
    position: sticky;
    top: 0;
    z-index: 1030;
    background: linear-gradient(135deg, #0f2f57 0%, #0a1f3b 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14);
    backdrop-filter: blur(10px);
}

.parent-topbar-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    flex-wrap: wrap;
}

.parent-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.parent-brand-logo {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    object-fit: cover;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.parent-brand-name {
    font-size: 1.08rem;
    font-weight: 800;
    line-height: 1.2;
    color: #fff;
}

.parent-brand-subtitle {
    font-size: 0.86rem;
    color: rgba(255, 255, 255, 0.8);
}

.parent-nav {
    background: rgba(255, 255, 255, 0.03);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0 12px 10px;
}

.parent-nav .nav-link {
    color: rgba(255, 255, 255, 0.82);
    border-radius: 999px;
    padding: 0.72rem 1rem;
    margin: 0.35rem 0.25rem 0;
    font-weight: 600;
    transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.parent-nav .nav-link.active,
.parent-nav .nav-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
}

.parent-main {
    padding: 24px;
}

.parent-hero {
    background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
    border: 1px solid #d8e3ef;
    border-left: 6px solid #c8a04a;
    border-radius: 10px;
    padding: 22px 24px;
    margin-bottom: 18px;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
}

.parent-hero-title {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.15;
    color: #0f172a;
}

.parent-hero-subtitle {
    margin-top: 6px;
    color: #64748b;
    line-height: 1.6;
}

.parent-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin: 16px 0;
}

.parent-summary-card,
.parent-card {
    background: #ffffff;
    border: 1px solid #d8e3ef;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.parent-summary-card {
    padding: 16px;
    min-height: 92px;
    border-top: 4px solid #c8a04a;
}

.parent-summary-label {
    display: block;
    font-size: 0.73rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.parent-summary-value {
    display: block;
    margin-top: 6px;
    font-size: 1.28rem;
    font-weight: 800;
    color: #0f172a;
}

.parent-card-head {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
}

.parent-card-body {
    padding: 16px;
}

.parent-student-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 14px;
}

.parent-student-card {
    background: #ffffff;
    border: 1px solid #d8e3ef;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
}

.parent-student-top {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #edf2f7;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
}

.parent-student-photo,
.parent-student-initials {
    width: 58px;
    height: 74px;
    border-radius: 8px;
    object-fit: cover;
    background: #f1f5f9;
    border: 1px solid #dbe3ec;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.05);
}

.parent-student-initials {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
    color: #1d4ed8;
    font-weight: 800;
}

.parent-student-name {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
}

.parent-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.parent-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.22rem 0.58rem;
    border: 1px solid #d8e2ee;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.8rem;
    font-weight: 600;
}

.parent-button-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.parent-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.42rem 0.8rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #1f2937;
    text-decoration: none;
    font-size: 0.84rem;
    font-weight: 600;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
    transition: transform 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.parent-button:hover {
    background: #f8fafc;
    color: #111827;
    transform: translateY(-1px);
}

.parent-button-primary {
    border-color: #2563eb;
    color: #1d4ed8;
    background: rgba(37, 99, 235, 0.06);
}

.parent-button-success {
    border-color: #16a34a;
    color: #166534;
    background: rgba(22, 163, 74, 0.06);
}

.parent-button-warning {
    border-color: #d97706;
    color: #92400e;
    background: rgba(217, 119, 6, 0.06);
}

.parent-button-danger {
    border-color: #dc2626;
    color: #b91c1c;
    background: rgba(220, 38, 38, 0.06);
}

.parent-table {
    width: 100%;
    border-collapse: collapse;
    overflow: hidden;
    border-radius: 10px;
}

.parent-table th,
.parent-table td {
    border-bottom: 1px solid #e5e7eb;
    padding: 0.78rem 0.84rem;
    vertical-align: top;
}

.parent-table thead th {
    background: #0f172a;
    color: #ffffff;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.parent-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.parent-table tbody tr:hover td {
    background: #eef6ff;
}

.parent-empty {
    padding: 22px 18px;
    text-align: center;
    color: #64748b;
    background: #f8fafc;
    border: 1px dashed #dbe3ec;
    border-radius: 8px;
}

.parent-announcement {
    background: #ffffff;
    border: 1px solid #dbe3ec;
    border-radius: 10px;
    padding: 16px 18px;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
}

.parent-announcement + .parent-announcement {
    margin-top: 12px;
}

.parent-announcement-title {
    margin: 0;
    font-weight: 800;
}

.parent-muted {
    color: #64748b;
    font-size: 0.88rem;
}

@media (max-width: 767px) {
    .parent-main {
        padding: 16px 12px 28px;
    }

    .parent-topbar-inner {
        padding: 14px 12px;
    }

    .parent-nav {
        padding: 0 8px 8px;
    }

    .parent-nav .nav-link {
        padding: 0.68rem 0.85rem;
    }

    .parent-hero,
    .parent-card-body,
    .parent-student-top {
        padding-left: 14px;
        padding-right: 14px;
    }
}

@media (max-width: 480px) {
    .parent-brand {
        width: 100%;
    }

    .parent-topbar .text-end {
        width: 100%;
        text-align: left !important;
    }

    .parent-button {
        width: 100%;
        justify-content: center;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .parent-shell {
        background: #ffffff;
    }

    .parent-topbar {
        position: static;
        box-shadow: none;
    }

    .parent-main {
        padding: 0;
    }

    .parent-card,
    .parent-summary-card,
    .parent-student-card,
    .parent-announcement {
        box-shadow: none;
    }
}
CSS;
    }
}

if (!function_exists('parentPortalRenderLayout')) {
    function parentPortalRenderLayout($pageTitle, $contentHtml, $activeNav = 'dashboard') {
        $schoolSettings = getSchoolSettings();
        $currentUser = getCurrentUser();
        $schoolName = $schoolSettings['school_name'] ?? APP_NAME;
        $logoSrc = '';
        if (!empty($schoolSettings['banner_logo'])) {
            $logoSrc = APP_URL . '/assets/uploads/logos/' . $schoolSettings['banner_logo'];
        }

        $navItems = [
            'dashboard' => ['label' => 'Dashboard', 'href' => APP_URL . '/modules/parent/dashboard.php', 'icon' => 'bi-house'],
            'due_fees' => ['label' => 'Due Fees', 'href' => APP_URL . '/modules/parent/due_fees.php', 'icon' => 'bi-cash-stack'],
            'receipts' => ['label' => 'Receipts', 'href' => APP_URL . '/modules/parent/receipts.php', 'icon' => 'bi-receipt'],
            'marksheet' => ['label' => 'Marksheet', 'href' => APP_URL . '/modules/parent/marksheet.php', 'icon' => 'bi-file-earmark-pdf'],
            'admit_card' => ['label' => 'Admit Card', 'href' => APP_URL . '/modules/parent/admit_card.php', 'icon' => 'bi-card-heading'],
        ];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo parentPortalEscape($pageTitle); ?> - <?php echo parentPortalEscape($schoolName); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
            <style><?php echo parentPortalStyles(); ?></style>
        </head>
        <body>
            <div class="parent-shell">
                <header class="parent-topbar no-print">
                    <div class="parent-topbar-inner">
                        <div class="parent-brand">
                            <?php if (!empty($logoSrc)): ?>
                                <img src="<?php echo parentPortalEscape($logoSrc); ?>" class="parent-brand-logo" alt="Logo">
                            <?php else: ?>
                                <div class="parent-brand-logo d-flex align-items-center justify-content-center text-primary fw-bold">S</div>
                            <?php endif; ?>
                            <div>
                                <div class="parent-brand-name"><?php echo parentPortalEscape($schoolName); ?></div>
                                <div class="parent-brand-subtitle">
                                    Parent Portal
                                    <?php if (!empty($currentUser['full_name'])): ?>
                                        | <?php echo parentPortalEscape($currentUser['full_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="<?php echo APP_URL; ?>/modules/auth/logout.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                    </div>
                    <nav class="parent-nav">
                        <div class="container-fluid px-0">
                            <div class="d-flex flex-wrap">
                                <?php foreach ($navItems as $navKey => $navItem): ?>
                                    <a class="nav-link<?php echo $activeNav === $navKey ? ' active' : ''; ?>" href="<?php echo parentPortalEscape($navItem['href']); ?>">
                                        <i class="bi <?php echo parentPortalEscape($navItem['icon']); ?>"></i>
                                        <?php echo parentPortalEscape($navItem['label']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </nav>
                </header>

                <main class="parent-main">
                    <?php echo $contentHtml; ?>
                </main>
            </div>
            <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
