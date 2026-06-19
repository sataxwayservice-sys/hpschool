<?php
/**
 * Student Document Generator
 * Admit Card, Transfer Certificate, Character Certificate, and Marksheet
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';
require_once '../../includes/admit_card_renderer.php';
require_once '../../includes/marksheet_pdf.php';
require_once '../../includes/pdf_export.php';

studentPortalEnsureSchema();

requireLogin();
$currentUser = getCurrentUser();
$isStudentUser = ($currentUser['role'] ?? '') === 'student';
if (!$isStudentUser) {
    requirePermission('reports', 'view');
}

$pageTitle = 'Student Document Generator';
$schoolSettings = getSchoolSettings();
$currentSchoolId = getCurrentSchoolId();

function docEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function docNormalizeDate($value, $fallback = '') {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('Y-m-d', $timestamp);
}

function docNormalizePaperSize($value = 'A4') {
    $value = strtoupper(trim((string) $value));
    return in_array($value, ['A4', 'A5'], true) ? $value : 'A4';
}

function docTransferCertificateAllowedPaperSizes() {
    return ['A4'];
}

function docCharacterCertificateAllowedPaperSizes() {
    return ['A4'];
}

function docNormalizeCertificateNumber($value) {
    return trim((string) $value);
}

function docGetTransferCertificatePrefix(array $schoolSettings) {
    $prefix = trim((string)($schoolSettings['transfer_certificate_prefix'] ?? 'TC/'));
    if ($prefix === '') {
        $prefix = 'TC/';
    }

    if (!preg_match('/[\/\-]$/', $prefix)) {
        $prefix .= '/';
    }

    return $prefix;
}

function docBuildTransferCertificateNumber($schoolSettings, $serialNo = null) {
    $serialNo = intval($serialNo);
    if ($serialNo <= 0) {
        $serialNo = intval($schoolSettings['transfer_certificate_last_no'] ?? 0) + 1;
    }
    if ($serialNo <= 0) {
        $serialNo = 1;
    }

    $prefix = docGetTransferCertificatePrefix($schoolSettings);
    return $prefix . str_pad((string) $serialNo, 3, '0', STR_PAD_LEFT);
}

function docGetCharacterCertificatePrefix(array $schoolSettings) {
    $prefix = trim((string)($schoolSettings['character_certificate_prefix'] ?? 'CC/'));
    if ($prefix === '') {
        $prefix = 'CC/';
    }

    if (!preg_match('/[\/\-]$/', $prefix)) {
        $prefix .= '/';
    }

    return $prefix;
}

function docBuildCharacterCertificateNumber(array $schoolSettings, $issueDateRaw = '') {
    $issueDateRaw = trim((string) $issueDateRaw);
    $year = date('Y');
    if ($issueDateRaw !== '' && strtotime($issueDateRaw) !== false) {
        $year = date('Y', strtotime($issueDateRaw));
    }

    $query = "SELECT COUNT(*) AS total_count
              FROM student_documents sd
              JOIN students s ON sd.student_id = s.student_id
              WHERE sd.document_type = 'character_certificate' AND YEAR(sd.issue_date) = ?";
    $params = [intval($year)];
    $types = 'i';
    if (function_exists('getCurrentSchoolId') && intval(getCurrentSchoolId()) > 0) {
        $query .= " AND s.school_id = ?";
        $params[] = intval(getCurrentSchoolId());
        $types .= 'i';
    }

    $row = fetchOne($query, $types, $params);

    $serialNo = intval($row['total_count'] ?? 0) + 1;
    if ($serialNo <= 0) {
        $serialNo = 1;
    }

    return docGetCharacterCertificatePrefix($schoolSettings) . $year . '/' . str_pad((string) $serialNo, 3, '0', STR_PAD_LEFT);
}

function docSplitCertificateNumber($certificateNo, $fallbackPrefix = 'TC/') {
    $certificateNo = trim((string) $certificateNo);
    $fallbackPrefix = trim((string) $fallbackPrefix);
    if ($fallbackPrefix === '') {
        $fallbackPrefix = 'TC/';
    }
    if ($certificateNo === '') {
        return [$fallbackPrefix, 0];
    }

    if (preg_match('/^(.*?)(\d+)$/', $certificateNo, $matches)) {
        $prefix = trim((string) $matches[1]);
        if ($prefix === '') {
            $prefix = $fallbackPrefix;
        }

        return [$prefix, intval($matches[2])];
    }

    return [$certificateNo, 0];
}

function docSyncTransferCertificateSequence(array $schoolSettings, $certificateNo) {
    $settingId = intval($schoolSettings['setting_id'] ?? 0);
    if ($settingId <= 0) {
        return false;
    }

    [$prefix, $serialNo] = docSplitCertificateNumber($certificateNo, docGetTransferCertificatePrefix($schoolSettings));
    if ($serialNo <= 0) {
        return false;
    }

    $currentLastNo = intval($schoolSettings['transfer_certificate_last_no'] ?? 0);
    $nextLastNo = max($currentLastNo, $serialNo);

    return executeQuery(
        "UPDATE school_settings SET transfer_certificate_prefix = ?, transfer_certificate_last_no = ?, updated_at = NOW() WHERE setting_id = ?",
        'sii',
        [$prefix, $nextLastNo, $settingId]
    ) !== false;
}

function docFormatDate($value, $format = 'd M Y') {
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '-' : date($format, $timestamp);
}

function docBaseUrl($type) {
    return APP_URL . '/modules/reports/student_documents.php?type=' . urlencode($type);
}

function docTypeLabel($type) {
    switch ($type) {
        case 'transfer_certificate':
            return 'Transfer Certificate';
        case 'character_certificate':
            return 'Character Certificate';
        case 'marksheet':
            return 'Marksheet';
        case 'admit_card':
        default:
            return 'Admit Card';
    }
}

function docGetAdmitCardScheduleRows(array $student, array $exam, $issueDate = '', array $documentPayload = []) {
    $storedRows = is_array($documentPayload['schedule_rows'] ?? null) ? $documentPayload['schedule_rows'] : [];
    if (!empty($storedRows)) {
        return $storedRows;
    }

    return admitCardGetScheduleRows($student, $exam, [
        'start_date' => $exam['exam_date'] ?? '',
        'issue_date' => $issueDate,
    ]);
}

function docBuildDownloadName($documentType, array $student = [], $scope = 'student') {
    $parts = [preg_replace('/[^\w]+/u', '_', strtolower(trim((string) $documentType)))];

    if ($scope !== '') {
        $parts[] = preg_replace('/[^\w]+/u', '_', strtolower(trim((string) $scope)));
    }

    $studentName = trim((string)($student['student_name'] ?? ''));
    $admissionNo = trim((string)($student['admission_no'] ?? ''));
    $rollNo = trim((string)($student['roll_no'] ?? ''));

    foreach ([$studentName, $admissionNo, $rollNo] as $part) {
        $part = preg_replace('/[^\w\s.-]+/u', '', $part);
        $part = preg_replace('/\s+/', '_', $part);
        $part = trim($part, '._-');
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return implode('_', array_filter($parts, fn($part) => $part !== ''));
}

function docGetCharacterCertificateStudents($classId, $sectionId = 0) {
    $classId = intval($classId);
    $sectionId = intval($sectionId);
    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
    if ($classId <= 0) {
        return [];
    }

    $query = "SELECT s.*, c.class_name, c.class_order, sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.class_id = ? AND s.status = 'Active'";

    $params = [$classId];
    $types = 'i';

    if ($schoolId > 0) {
        $query .= " AND s.school_id = ?";
        $params[] = $schoolId;
        $types .= 'i';
    }

    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $params[] = $sectionId;
        $types .= 'i';
    }

    $query .= " ORDER BY s.roll_no, s.student_name, s.student_id";
    return fetchAll($query, $types, $params);
}

function docGetTransferCertificateStudents($classId, $sectionId = 0) {
    $classId = intval($classId);
    $sectionId = intval($sectionId);
    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
    if ($classId <= 0) {
        return [];
    }

    $query = "SELECT s.*, c.class_name, c.class_order, sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE s.class_id = ? AND s.status = 'Active'";

    $params = [$classId];
    $types = 'i';

    if ($schoolId > 0) {
        $query .= " AND s.school_id = ?";
        $params[] = $schoolId;
        $types .= 'i';
    }

    if ($sectionId > 0) {
        $query .= " AND s.section_id = ?";
        $params[] = $sectionId;
        $types .= 'i';
    }

    $query .= " ORDER BY s.roll_no, s.student_name, s.student_id";
    return fetchAll($query, $types, $params);
}

function docResolveStudentBySearch($search, $status = '') {
    $search = trim((string) $search);
    $status = trim((string) $status);
    $schoolId = function_exists('getCurrentSchoolId') ? intval(getCurrentSchoolId()) : 0;
    if ($search === '') {
        return null;
    }

    $query = "SELECT s.*, c.class_name, c.class_order, sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE 1=1";

    $params = [];
    $types = '';

    if ($status !== '') {
        $query .= " AND s.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($schoolId > 0) {
        $query .= " AND s.school_id = ?";
        $params[] = $schoolId;
        $types .= 'i';
    }

    $query .= " AND (s.admission_no = ? OR s.roll_no = ? OR s.student_name LIKE ?)
                ORDER BY
                    CASE WHEN s.admission_no = ? THEN 0 ELSE 1 END,
                    CASE WHEN s.roll_no = ? THEN 0 ELSE 1 END,
                    s.student_name ASC
                LIMIT 1";

    $like = '%' . $search . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $like;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sssss';

    return fetchOne($query, $types, $params);
}

function docThemeConfig($type) {
    switch ($type) {
        case 'transfer_certificate':
            return [
                'primary' => '#d97706',
                'soft' => '#fff7ed',
                'border' => '#7c2d12',
                'watermark' => 'TRANSFER CERTIFICATE',
            ];
        case 'character_certificate':
            return [
                'primary' => '#059669',
                'soft' => '#ecfdf5',
                'border' => '#065f46',
                'watermark' => 'CHARACTER CERTIFICATE',
            ];
        case 'marksheet':
            return [
                'primary' => '#0f766e',
                'soft' => '#f0fdfa',
                'border' => '#134e4a',
                'watermark' => 'MARKSHEET',
            ];
        case 'admit_card':
        default:
            return [
                'primary' => '#2563eb',
                'soft' => '#eff6ff',
                'border' => '#1e3a8a',
                'watermark' => 'ADMIT CARD',
            ];
    }
}

function docSignatureHtml($signatureSrc, $label = 'Principal') {
    ob_start();
    ?>
    <div class="doc-signature">
        <?php if (!empty($signatureSrc)): ?>
            <img src="<?php echo docEscape($signatureSrc); ?>" alt="<?php echo docEscape($label . ' Signature'); ?>" class="doc-signature-img">
        <?php else: ?>
            <div class="doc-signature-gap"></div>
        <?php endif; ?>
        <div class="doc-signature-line"><?php echo docEscape($label); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderKeyValueTable($rows) {
    ob_start();
    ?>
    <table class="doc-details-table">
        <?php foreach ($rows as $label => $value): ?>
            <tr>
                <td><?php echo docEscape($label); ?></td>
                <td><?php echo docEscape($value); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
    return ob_get_clean();
}

function docRenderCertificateFieldBlock($label, $value, $wide = false) {
    ob_start();
    ?>
    <div class="doc-certificate-field<?php echo $wide ? ' doc-certificate-wide' : ''; ?>">
        <div class="doc-certificate-field-label"><?php echo docEscape($label); ?></div>
        <div class="doc-certificate-field-value"><?php echo docEscape($value); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderCertificateHeaderBlock($schoolSettings, $certificateLabel, $referenceNo, $issueDate, $subtitle = '', array $metaRows = [], $referenceLabel = 'Reference No.') {
    $schoolName = $schoolSettings['school_name'] ?? APP_NAME;
    $schoolAddress = $schoolSettings['school_address'] ?? '';
    $schoolPhone = $schoolSettings['school_phone'] ?? '';
    $schoolEmail = $schoolSettings['school_email'] ?? '';
    $logoSrc = getSchoolLogoSrc($schoolSettings['school_logo'] ?? '', $schoolSettings['banner_logo'] ?? '');
    $initials = strtoupper(trim(preg_replace('/[^A-Za-z]+/', '', $schoolName)));
    if ($initials === '') {
        $initials = 'SC';
    } elseif (strlen($initials) > 2) {
        $initials = substr($initials, 0, 2);
    }

    ob_start();
    ?>
    <div class="doc-certificate-header">
        <div class="doc-certificate-brand">
            <?php if (!empty($logoSrc)): ?>
                <img src="<?php echo docEscape($logoSrc); ?>" alt="<?php echo docEscape($schoolName); ?>" class="doc-certificate-logo">
            <?php else: ?>
                <div class="doc-certificate-logo doc-certificate-logo-placeholder"><?php echo docEscape($initials); ?></div>
            <?php endif; ?>
            <div class="doc-certificate-brand-copy">
                <div class="doc-certificate-school-name"><?php echo docEscape($schoolName); ?></div>
                <?php if (!empty($schoolAddress)): ?>
                    <div class="doc-certificate-meta"><?php echo docEscape($schoolAddress); ?></div>
                <?php endif; ?>
                <?php if (!empty($schoolPhone) || !empty($schoolEmail)): ?>
                    <div class="doc-certificate-meta">
                        <?php if (!empty($schoolPhone)): ?>
                            Phone: <?php echo docEscape($schoolPhone); ?>
                        <?php endif; ?>
                        <?php if (!empty($schoolEmail)): ?>
                            <?php echo !empty($schoolPhone) ? ' | ' : ''; ?>Email: <?php echo docEscape($schoolEmail); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="doc-certificate-number">
            <div class="doc-certificate-number-item">
                <span class="doc-certificate-number-label"><?php echo docEscape($referenceLabel); ?></span>
                <span class="doc-certificate-number-value"><?php echo docEscape($referenceNo); ?></span>
            </div>
            <div class="doc-certificate-number-item">
                <span class="doc-certificate-number-label">Issue Date</span>
                <span class="doc-certificate-number-value"><?php echo docEscape(docFormatDate($issueDate)); ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($certificateLabel)): ?>
        <div class="doc-certificate-title"><?php echo docEscape($certificateLabel); ?></div>
    <?php endif; ?>

    <?php if (!empty($subtitle)): ?>
        <div class="doc-certificate-subtitle"><?php echo docEscape($subtitle); ?></div>
    <?php endif; ?>
    <?php if (!empty($metaRows)): ?>
        <div class="doc-certificate-header-details">
            <?php foreach ($metaRows as $metaRow): ?>
                <?php if (!is_array($metaRow)) continue; ?>
                <div class="doc-certificate-header-detail">
                    <div class="doc-certificate-header-detail-label"><?php echo docEscape($metaRow['label'] ?? ''); ?></div>
                    <div class="doc-certificate-header-detail-value"><?php echo docEscape($metaRow['value'] ?? '-'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function docRenderCertificatePhotoBlock(array $student, $caption = 'Student Photograph') {
    $studentPhoto = getStudentPhotoSrc($student['photo'] ?? '');
    $studentName = trim((string)($student['student_name'] ?? ''));

    ob_start();
    ?>
    <div class="doc-certificate-photo">
        <?php if (!empty($studentPhoto)): ?>
            <img src="<?php echo docEscape($studentPhoto); ?>" alt="<?php echo docEscape($caption); ?>">
        <?php else: ?>
            <div class="doc-certificate-photo-placeholder">PHOTO</div>
        <?php endif; ?>
        <div class="doc-certificate-photo-label"><?php echo docEscape($caption); ?></div>
        <?php if ($studentName !== ''): ?>
            <div class="doc-certificate-photo-name"><?php echo docEscape($studentName); ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderCharacterSignatureBlock($role, $name = '', $signatureSrc = '') {
    ob_start();
    ?>
    <div class="doc-character-signature-card">
        <div class="doc-character-signature-role"><?php echo docEscape($role); ?></div>
        <div class="doc-character-signature-space">
            <?php if (!empty($signatureSrc)): ?>
                <img src="<?php echo docEscape($signatureSrc); ?>" alt="<?php echo docEscape($role . ' Signature'); ?>" class="doc-character-signature-img">
            <?php else: ?>
                <div class="doc-character-signature-gap"></div>
            <?php endif; ?>
        </div>
        <div class="doc-character-signature-line"></div>
        <?php if (trim((string) $name) !== ''): ?>
            <div class="doc-character-signature-name"><?php echo docEscape($name); ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderHeaderBlock($schoolSettings, $documentLabel, $subtitle = '', $qrCodeUrl = '') {
    $schoolName = $schoolSettings['school_name'] ?? APP_NAME;
    $schoolAddress = $schoolSettings['school_address'] ?? '';
    $schoolPhone = $schoolSettings['school_phone'] ?? '';
    $schoolEmail = $schoolSettings['school_email'] ?? '';
    $logoSrc = getSchoolLogoSrc($schoolSettings['school_logo'] ?? '', $schoolSettings['banner_logo'] ?? '');

    ob_start();
    ?>
    <?php if (!empty($qrCodeUrl)): ?>
        <div class="doc-qr">
            <img src="<?php echo docEscape($qrCodeUrl); ?>" alt="<?php echo docEscape($documentLabel . ' QR'); ?>">
            <small>Scan to verify</small>
        </div>
    <?php endif; ?>

    <div class="doc-header">
        <div class="doc-header-main">
            <?php if (!empty($logoSrc)): ?>
                <img src="<?php echo docEscape($logoSrc); ?>" class="doc-brand-logo" alt="<?php echo docEscape($schoolName); ?>">
            <?php endif; ?>
            <div class="doc-header-copy">
                <div class="doc-school-name"><?php echo docEscape($schoolName); ?></div>
                <?php if (!empty($schoolAddress)): ?>
                    <div class="doc-school-meta"><?php echo docEscape($schoolAddress); ?></div>
                <?php endif; ?>
                <?php if (!empty($schoolPhone) || !empty($schoolEmail)): ?>
                    <div class="doc-school-meta">
                        <?php if (!empty($schoolPhone)): ?>
                            Phone: <?php echo docEscape($schoolPhone); ?>
                        <?php endif; ?>
                        <?php if (!empty($schoolEmail)): ?>
                            <?php echo !empty($schoolPhone) ? ' | ' : ''; ?>Email: <?php echo docEscape($schoolEmail); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="doc-title"><?php echo docEscape($documentLabel); ?></div>
    <?php if (!empty($subtitle)): ?>
        <div class="doc-subtitle"><?php echo docEscape($subtitle); ?></div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function docNormalizeDisplayValue($value, $fallback = '-') {
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
}

function docGetSettingValue(array $settings, array $keys, $fallback = '-') {
    foreach ($keys as $key) {
        if (isset($settings[$key])) {
            $value = trim((string) $settings[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return $fallback;
}

function docNormalizeCharacterGrade($value) {
    $value = strtolower(trim((string) $value));
    $value = str_replace([' ', '-'], '_', $value);

    if (in_array($value, ['good', 'very_good', 'excellent'], true)) {
        return $value;
    }

    return 'very_good';
}

function docCharacterGradeLabel($value) {
    switch (docNormalizeCharacterGrade($value)) {
        case 'good':
            return 'Good';
        case 'excellent':
            return 'Excellent';
        default:
            return 'Very Good';
    }
}

function docGetSubjectsStudiedLabel(array $student) {
    static $cache = [];

    $classId = intval($student['class_id'] ?? 0);
    $sectionId = intval($student['section_id'] ?? 0);
    $cacheKey = $classId . ':' . $sectionId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if ($classId <= 0) {
        return $cache[$cacheKey] = '-';
    }

    $subjects = [];
    $tableExists = count(fetchAll("SHOW TABLES LIKE 'class_subjects'")) > 0;
    if ($tableExists) {
        $subjects = fetchAll(
            "SELECT sub.subject_name
             FROM class_subjects cs
             INNER JOIN subjects sub ON sub.subject_id = cs.subject_id
             WHERE cs.class_id = ?
             ORDER BY cs.id ASC, sub.subject_name ASC",
            'i',
            [$classId]
        );
    }

    if (empty($subjects)) {
        $subjects = fetchAll(
            "SELECT subject_name
             FROM subjects
             WHERE is_active = 1
             ORDER BY subject_name ASC"
        );
    }

    $subjectNames = [];
    foreach ($subjects as $subjectRow) {
        $subjectName = trim((string)($subjectRow['subject_name'] ?? ''));
        if ($subjectName !== '') {
            $subjectNames[] = $subjectName;
        }
        if (count($subjectNames) >= 6) {
            break;
        }
    }

    if (empty($subjectNames)) {
        return $cache[$cacheKey] = '-';
    }

    $result = implode(', ', $subjectNames);
    if (count($subjects) > count($subjectNames)) {
        $result .= ', ...';
    }

    return $cache[$cacheKey] = $result;
}

function docRenderTransferTablePairRow($leftLabel, $leftValue, $rightLabel, $rightValue) {
    ob_start();
    ?>
    <tr>
        <th class="doc-transfer-label"><?php echo docEscape($leftLabel); ?></th>
        <td class="doc-transfer-value"><?php echo docEscape($leftValue); ?></td>
        <th class="doc-transfer-label"><?php echo docEscape($rightLabel); ?></th>
        <td class="doc-transfer-value"><?php echo docEscape($rightValue); ?></td>
    </tr>
    <?php
    return ob_get_clean();
}

function docRenderTransferTableWideRow($label, $value) {
    ob_start();
    ?>
    <tr class="doc-transfer-wide-row">
        <th class="doc-transfer-label"><?php echo docEscape($label); ?></th>
        <td colspan="3" class="doc-transfer-value"><?php echo docEscape($value); ?></td>
    </tr>
    <?php
    return ob_get_clean();
}

function docRenderTransferSignatureCell($label, $name = '', $signatureSrc = '', $isPrincipal = false) {
    ob_start();
    ?>
    <div class="doc-transfer-signature-cell<?php echo $isPrincipal ? ' doc-transfer-signature-principal' : ''; ?>">
        <?php if (!empty($signatureSrc)): ?>
            <img src="<?php echo docEscape($signatureSrc); ?>" alt="<?php echo docEscape($label); ?>" class="doc-transfer-signature-image">
        <?php else: ?>
            <div class="doc-transfer-signature-gap"></div>
        <?php endif; ?>
        <div class="doc-transfer-signature-line"></div>
        <div class="doc-transfer-signature-label"><?php echo docEscape($label); ?></div>
        <?php if ($name !== ''): ?>
            <div class="doc-transfer-signature-name"><?php echo docEscape($name); ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderTransferFieldCard($label, $value, $wide = false) {
    ob_start();
    ?>
    <div class="doc-transfer-field-card<?php echo $wide ? ' doc-transfer-field-wide' : ''; ?>">
        <div class="doc-transfer-field-label"><?php echo docEscape($label); ?></div>
        <div class="doc-transfer-field-value"><?php echo docEscape($value); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

function docWrapPrintablePage($title, $schoolName, $contentHtml, $backUrl, $refreshUrl, $autoPrint = false, $theme = [], $watermark = '', $downloadUrl = '', $paperSize = 'A4', array $allowedPaperSizes = ['A4', 'A5']) {
    $theme = array_merge(docThemeConfig('admit_card'), is_array($theme) ? $theme : []);
    $pages = is_array($contentHtml) ? array_values($contentHtml) : [$contentHtml];
    if (empty($pages)) {
        $pages = [''];
    }
    $paperSize = docNormalizePaperSize($paperSize);
    $paperMargin = $paperSize === 'A5' ? '8mm' : '12mm';
    $paperFramePadding = $paperSize === 'A5' ? '14px 16px 18px' : '18px 20px 22px';
    $paperPageWidth = $paperSize === 'A5' ? '760px' : '900px';
    $allowedPaperSizes = array_values(array_unique(array_map(function ($size) {
        return docNormalizePaperSize($size);
    }, is_array($allowedPaperSizes) ? $allowedPaperSizes : [])));
    if (empty($allowedPaperSizes)) {
        $allowedPaperSizes = ['A4'];
    }
    $toolbarParams = $_GET;
    unset($toolbarParams['download']);
    $paperUrlA4 = '';
    $paperUrlA5 = '';
    if (in_array('A4', $allowedPaperSizes, true)) {
        $toolbarParams['paper_size'] = 'A4';
        $paperUrlA4 = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter($toolbarParams, function ($value) {
            return $value !== null && $value !== '';
        }));
    }
    if (in_array('A5', $allowedPaperSizes, true)) {
        $toolbarParams['paper_size'] = 'A5';
        $paperUrlA5 = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter($toolbarParams, function ($value) {
            return $value !== null && $value !== '';
        }));
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo docEscape($title); ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <style>
            :root {
                --doc-primary: <?php echo docEscape($theme['primary']); ?>;
                --doc-soft: <?php echo docEscape($theme['soft']); ?>;
                --doc-border: <?php echo docEscape($theme['border']); ?>;
                --doc-muted: #64748b;
            }

            @page {
                size: <?php echo docEscape($paperSize); ?> portrait;
                margin: <?php echo docEscape($paperMargin); ?>;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 18px;
                font-family: Arial, Helvetica, sans-serif;
                background: #f4f7fb;
                color: #111827;
            }

            .doc-toolbar {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                align-items: center;
                margin-bottom: 12px;
            }

            .doc-paper-switch {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                background: #fff;
            }

            .doc-page + .doc-page {
                margin-top: 18px;
                page-break-before: always;
            }

            .doc-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 0.55rem 0.9rem;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #ffffff;
                color: #1f2937;
                text-decoration: none;
                font-size: 0.9rem;
                line-height: 1.1;
                white-space: nowrap;
            }

            .doc-btn-primary {
                border-color: #2563eb;
                color: #1d4ed8;
            }

            .doc-btn-muted {
                border-color: #94a3b8;
                color: #475569;
            }

            .doc-btn.active {
                background: var(--doc-soft);
                border-color: var(--doc-primary);
                color: var(--doc-primary);
                font-weight: 700;
            }

            .doc-page {
                max-width: <?php echo docEscape($paperPageWidth); ?>;
                margin: 0 auto;
                background: #ffffff;
                border: 2px solid var(--doc-border);
                border-radius: 10px;
                padding: <?php echo docEscape($paperFramePadding); ?>;
                position: relative;
                overflow: hidden;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            }

            .doc-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                text-align: left;
                border-bottom: 1px solid #dbe3ec;
                padding-bottom: 14px;
                margin-bottom: 16px;
                position: relative;
                z-index: 1;
            }

            .doc-header-main {
                display: flex;
                gap: 14px;
                align-items: flex-start;
                min-width: 0;
                flex: 1;
            }

            .doc-header-copy {
                min-width: 0;
            }

            .doc-brand-logo {
                width: 72px;
                height: 72px;
                object-fit: contain;
                border: 1px solid var(--doc-soft);
                border-radius: 10px;
                background: #fff;
                padding: 6px;
                flex: 0 0 auto;
            }

            .doc-school-name {
                font-size: 24px;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.2;
            }

            .doc-school-meta {
                margin-top: 2px;
                color: var(--doc-muted);
                font-size: 0.94rem;
                line-height: 1.35;
            }

            .doc-title {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-top: 8px;
                padding: 6px 14px;
                border-radius: 999px;
                background: var(--doc-soft);
                color: var(--doc-primary);
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            .doc-subtitle {
                margin-top: 6px;
                color: var(--doc-muted);
                font-size: 0.92rem;
            }

            .doc-watermark {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                color: var(--doc-primary);
                opacity: 0.05;
                font-size: 64px;
                font-weight: 800;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                transform: rotate(-26deg);
                user-select: none;
                white-space: nowrap;
                z-index: 0;
            }

            .doc-qr {
                position: absolute;
                top: 18px;
                right: 18px;
                text-align: center;
                z-index: 2;
            }

            .doc-qr img {
                width: 110px;
                height: 110px;
                border: 1px solid var(--doc-soft);
                border-radius: 8px;
                background: #fff;
                padding: 4px;
            }

            .doc-qr small {
                display: block;
                margin-top: 4px;
                font-size: 10px;
                color: #64748b;
            }

            .doc-photo-wrap {
                display: flex;
                gap: 14px;
                align-items: flex-start;
                margin-top: 14px;
            }

            .doc-photo {
                width: 110px;
                height: 140px;
                object-fit: cover;
                border: 1px solid #dbe3ec;
                border-radius: 8px;
                background: #f1f5f9;
                flex: 0 0 auto;
            }

            .doc-photo-placeholder {
                width: 110px;
                height: 140px;
                border: 1px solid #dbe3ec;
                border-radius: 8px;
                background: #f8fafc;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                flex: 0 0 auto;
            }

            .doc-grid-2 {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                width: 100%;
            }

            .doc-box {
                border: 1px solid #dbe3ec;
                border-radius: 8px;
                padding: 12px 14px;
                background: #ffffff;
                min-height: 100px;
            }

            .doc-label {
                font-size: 0.78rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--doc-muted);
                font-weight: 700;
            }

            .doc-value {
                margin-top: 4px;
                font-size: 1rem;
                font-weight: 700;
                color: #111827;
            }

            .doc-value-muted {
                margin-top: 4px;
                color: #475569;
                font-size: 0.95rem;
                line-height: 1.4;
            }

            .doc-certificate-text {
                margin: 18px 0;
                font-size: 1.03rem;
                line-height: 1.75;
                color: #0f172a;
                text-align: justify;
            }

            .doc-certificate-shell {
                position: relative;
                z-index: 1;
            }

            .doc-certificate-frame {
                position: relative;
                border: 1.6px solid var(--doc-border);
                border-radius: 14px;
                padding: 18px 20px 22px;
                background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.16);
                overflow: hidden;
            }

            .doc-certificate-frame::before {
                content: "";
                position: absolute;
                inset: 10px;
                border: 1px solid rgba(37, 99, 235, 0.12);
                border-radius: 10px;
                pointer-events: none;
            }

            .doc-certificate-header {
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 16px;
                align-items: center;
                text-align: center;
                border-bottom: 1px solid #dbe3ec;
                padding-bottom: 14px;
                margin-bottom: 14px;
                position: relative;
                z-index: 1;
            }

            .doc-certificate-brand {
                display: flex;
                gap: 12px;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                min-width: 0;
                width: 100%;
            }

            .doc-certificate-logo {
                width: 78px;
                height: 78px;
                object-fit: contain;
                border: 1px solid var(--doc-soft);
                border-radius: 12px;
                background: #fff;
                padding: 6px;
                flex: 0 0 auto;
            }

            .doc-certificate-logo-placeholder {
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                color: var(--doc-primary);
                background: var(--doc-soft);
                letter-spacing: 0.08em;
            }

            .doc-certificate-brand-copy {
                min-width: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .doc-certificate-school-name {
                font-size: 23px;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.15;
                text-transform: uppercase;
            }

            .doc-certificate-meta {
                margin-top: 3px;
                color: var(--doc-muted);
                font-size: 0.92rem;
                line-height: 1.45;
            }

            .doc-certificate-number {
                width: min(100%, 360px);
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                background: var(--doc-soft);
                border: 1px solid #dbe3ec;
                border-radius: 12px;
                padding: 12px 14px;
                margin: 0 auto;
            }

            .doc-certificate-number-item {
                display: grid;
                gap: 2px;
            }

            .doc-certificate-number-label {
                font-size: 0.74rem;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: var(--doc-muted);
                font-weight: 700;
            }

            .doc-certificate-number-value {
                font-size: 1rem;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.3;
                word-break: break-word;
            }

            .doc-certificate-title {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin: 10px auto 0;
                padding: 8px 22px;
                border-radius: 999px;
                background: var(--doc-primary);
                color: #ffffff;
                font-size: 15px;
                font-weight: 800;
                letter-spacing: 0.16em;
                text-transform: uppercase;
                box-shadow: 0 8px 22px rgba(15, 23, 42, 0.10);
                position: relative;
                z-index: 1;
            }

            .doc-certificate-subtitle {
                text-align: center;
                margin-top: 6px;
                color: var(--doc-muted);
                font-size: 0.92rem;
                position: relative;
                z-index: 1;
            }

            .doc-certificate-main {
                display: grid;
                grid-template-columns: minmax(104px, 120px) minmax(0, 1fr);
                gap: 18px;
                align-items: start;
                margin-top: 18px;
                position: relative;
                z-index: 1;
            }

            .doc-certificate-photo {
                width: 100%;
                max-width: 120px;
                text-align: center;
            }

            .doc-certificate-photo img,
            .doc-certificate-photo-placeholder {
                width: 100%;
                height: 150px;
                object-fit: cover;
                border: 1px solid #cbd5e1;
                border-radius: 12px;
                background: #f8fafc;
                display: block;
                margin: 0 auto;
            }

            .doc-certificate-photo-placeholder {
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.12em;
                color: var(--doc-primary);
                background: #eff6ff;
            }

            .doc-certificate-photo-label {
                margin-top: 8px;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.10em;
                color: var(--doc-muted);
                font-weight: 700;
            }

            .doc-certificate-photo-name {
                margin-top: 4px;
                font-size: 0.92rem;
                font-weight: 700;
                color: #0f172a;
            }

            .doc-certificate-intro {
                margin: 0;
                font-size: 1.02rem;
                line-height: 1.76;
                color: #0f172a;
                text-align: justify;
            }

            .doc-certificate-highlight {
                margin-top: 12px;
                border-left: 4px solid var(--doc-primary);
                background: #ffffff;
                border: 1px solid #dbe3ec;
                border-radius: 10px;
                padding: 12px 14px;
            }

            .doc-certificate-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
                gap: 12px;
                margin-top: 18px;
                position: relative;
                z-index: 1;
            }

            .doc-certificate-field {
                border: 1px solid #dbe3ec;
                border-radius: 10px;
                padding: 11px 12px;
                background: #ffffff;
                min-height: 74px;
            }

            .doc-certificate-field-label {
                font-size: 0.74rem;
                text-transform: uppercase;
                letter-spacing: 0.10em;
                color: var(--doc-muted);
                font-weight: 700;
            }

            .doc-certificate-field-value {
                margin-top: 4px;
                font-size: 0.98rem;
                font-weight: 700;
                color: #111827;
                line-height: 1.5;
            }

            .doc-certificate-wide {
                grid-column: 1 / -1;
            }

            .doc-certificate-note {
                margin-top: 18px;
                background: var(--doc-soft);
                border: 1px solid #dbe3ec;
                border-radius: 10px;
                padding: 12px 14px;
                position: relative;
                z-index: 1;
            }

            .doc-certificate-note-label {
                font-size: 0.74rem;
                text-transform: uppercase;
                letter-spacing: 0.10em;
                color: var(--doc-muted);
                font-weight: 700;
                margin-bottom: 6px;
            }

            .doc-certificate-note-body {
                font-size: 0.96rem;
                line-height: 1.7;
                color: #0f172a;
            }

            .doc-certificate-footer {
                display: flex;
                justify-content: center;
                gap: 18px;
                align-items: center;
                margin-top: 30px;
                position: relative;
                z-index: 1;
                flex-wrap: wrap;
                text-align: center;
            }

            .doc-certificate-issued {
                color: #475569;
                font-size: 0.92rem;
                line-height: 1.55;
                width: min(100%, 320px);
            }

            .doc-certificate-sign {
                min-width: 0;
                width: min(100%, 260px);
                text-align: center;
            }

            .doc-certificate-sign .doc-signature {
                min-width: 0;
            }

            .doc-certificate-stamp {
                position: absolute;
                right: 26px;
                bottom: 22px;
                width: 110px;
                height: 110px;
                border: 3px solid rgba(37, 99, 235, 0.28);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 10px;
                text-align: center;
                color: rgba(37, 99, 235, 0.50);
                font-size: 0.72rem;
                font-weight: 800;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                transform: rotate(-12deg);
                user-select: none;
                pointer-events: none;
                z-index: 0;
            }

            .doc-certificate-header-details {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 8px;
                width: 100%;
                margin-top: 10px;
            }

            .doc-certificate-header-detail {
                border: 1px solid #dbe3ec;
                border-radius: 10px;
                background: #ffffff;
                padding: 8px 10px;
                text-align: center;
            }

            .doc-certificate-header-detail-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.10em;
                color: var(--doc-muted);
                font-weight: 800;
            }

            .doc-certificate-header-detail-value {
                margin-top: 3px;
                font-size: 0.92rem;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.35;
                word-break: break-word;
            }

            .doc-transfer-certificate .doc-certificate-frame {
                background: linear-gradient(180deg, #fffdf8 0%, #ffffff 100%);
                padding: 20px 22px 24px;
                zoom: <?php echo $paperSize === 'A5' ? '0.45' : '0.62'; ?>;
                transform-origin: top center;
            }

            .doc-transfer-certificate .doc-certificate-frame::before {
                inset: 12px;
                border-color: rgba(180, 83, 9, 0.16);
                border-radius: 12px;
            }

            .doc-transfer-certificate .doc-certificate-header {
                margin-bottom: 12px;
                padding-bottom: 12px;
            }

            .doc-transfer-certificate .doc-certificate-brand {
                gap: 10px;
            }

            .doc-transfer-certificate .doc-certificate-logo,
            .doc-transfer-certificate .doc-certificate-logo-placeholder {
                width: 82px;
                height: 82px;
                border-radius: 50%;
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            }

            .doc-transfer-certificate .doc-certificate-school-name {
                font-size: 25px;
                letter-spacing: 0.03em;
            }

            .doc-transfer-certificate .doc-certificate-meta {
                font-size: 0.86rem;
            }

            .doc-transfer-certificate .doc-certificate-number {
                width: min(100%, 540px);
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                padding: 12px 14px;
                background: linear-gradient(180deg, #fff8eb 0%, #fff 100%);
                border-color: rgba(180, 83, 9, 0.18);
            }

            .doc-transfer-certificate .doc-certificate-number-item {
                text-align: center;
            }

            .doc-transfer-certificate .doc-certificate-number-label {
                letter-spacing: 0.10em;
            }

            .doc-transfer-certificate .doc-certificate-number-value {
                white-space: nowrap;
                font-size: 0.92rem;
            }

            .doc-transfer-certificate .doc-certificate-title {
                background: linear-gradient(135deg, #1e3a8a 0%, #0f5aa8 100%);
                box-shadow: 0 10px 24px rgba(30, 58, 138, 0.12);
                letter-spacing: 0.18em;
            }

            .doc-transfer-strip {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
                margin-top: 16px;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-strip-item {
                background: #ffffff;
                border: 1px solid #dbe3ec;
                border-radius: 12px;
                padding: 10px 12px;
                text-align: center;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
            }

            .doc-transfer-strip-label {
                display: block;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: var(--doc-muted);
                font-weight: 800;
            }

            .doc-transfer-strip-value {
                display: block;
                margin-top: 4px;
                font-size: 0.98rem;
                font-weight: 800;
                color: #0f172a;
            }

            .doc-transfer-declaration {
                margin-top: 16px;
                padding: 14px 16px;
                border: 1px solid #dbe3ec;
                border-left: 4px solid var(--doc-primary);
                border-radius: 12px;
                background: #ffffff;
                color: #0f172a;
                font-size: 1rem;
                line-height: 1.8;
                text-align: justify;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-fields-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
                margin-top: 16px;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-field-card {
                border: 1px solid #dbe3ec;
                border-radius: 12px;
                background: #ffffff;
                padding: 8px 10px 9px;
                min-height: 62px;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
            }

            .doc-transfer-field-wide {
                grid-column: 1 / -1;
                min-height: 72px;
            }

            .doc-transfer-field-label {
                font-size: 0.70rem;
                text-transform: uppercase;
                letter-spacing: 0.11em;
                color: var(--doc-muted);
                font-weight: 800;
                line-height: 1.25;
            }

            .doc-transfer-field-value {
                margin-top: 4px;
                font-size: 0.92rem;
                font-weight: 700;
                color: #111827;
                line-height: 1.45;
                word-break: break-word;
            }

            .doc-transfer-table-wrap {
                margin-top: 16px;
                border: 1px solid #dbe3ec;
                border-radius: 12px;
                overflow: hidden;
                background: #ffffff;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-table {
                width: 100%;
                border-collapse: collapse;
            }

            .doc-transfer-table th,
            .doc-transfer-table td {
                border-right: 1px solid #e5edf5;
                border-bottom: 1px solid #e5edf5;
                padding: 10px 11px;
                vertical-align: top;
            }

            .doc-transfer-table tr:last-child th,
            .doc-transfer-table tr:last-child td {
                border-bottom: none;
            }

            .doc-transfer-table th:last-child,
            .doc-transfer-table td:last-child {
                border-right: none;
            }

            .doc-transfer-label {
                width: 19%;
                background: #f8fbff;
                color: #334155;
                font-size: 0.72rem;
                line-height: 1.4;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-weight: 800;
            }

            .doc-transfer-value {
                width: 31%;
                color: #111827;
                font-size: 0.95rem;
                line-height: 1.55;
                font-weight: 700;
                word-break: break-word;
            }

            .doc-transfer-wide-row .doc-transfer-value {
                width: auto;
            }

            .doc-transfer-security {
                display: grid;
                grid-template-columns: minmax(0, 1.25fr) minmax(180px, 0.9fr) minmax(0, 1fr);
                gap: 12px;
                margin-top: 16px;
                align-items: stretch;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-security-item {
                border: 1px solid #dbe3ec;
                border-radius: 12px;
                padding: 12px 14px;
                background: #fbfdff;
                overflow: hidden;
            }

            .doc-transfer-security-note {
                border-left: 4px solid var(--doc-primary);
            }

            .doc-transfer-security-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: var(--doc-muted);
                font-weight: 800;
            }

            .doc-transfer-security-text {
                margin-top: 6px;
                color: #0f172a;
                font-size: 0.94rem;
                line-height: 1.65;
            }

            .doc-transfer-security-subtext {
                margin-top: 8px;
                font-size: 0.88rem;
                color: #475569;
                line-height: 1.45;
            }

            .doc-transfer-security-qr {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }

            .doc-transfer-security-qr img {
                width: 140px;
                height: 140px;
                border: 1px solid #dbe3ec;
                background: #fff;
                padding: 4px;
                border-radius: 10px;
            }

            .doc-transfer-security-qr small {
                margin-top: 8px;
                color: #475569;
                font-size: 0.8rem;
            }

            .doc-transfer-security-seal {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 10px;
                text-align: center;
            }

            .doc-transfer-seal-circle {
                width: 128px;
                height: 128px;
                border-radius: 50%;
                border: 3px solid rgba(37, 99, 235, 0.35);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: radial-gradient(circle at center, #fff 0%, #f8fbff 70%, #eef5ff 100%);
                color: rgba(29, 78, 216, 0.76);
                text-transform: uppercase;
                letter-spacing: 0.10em;
                font-weight: 800;
            }

            .doc-transfer-seal-circle span {
                font-size: 0.68rem;
            }

            .doc-transfer-seal-circle strong {
                font-size: 1rem;
                letter-spacing: 0.14em;
            }

            .doc-transfer-verification-stamp {
                width: 100%;
                border: 1px dashed rgba(37, 99, 235, 0.35);
                border-radius: 10px;
                padding: 8px 10px;
                background: #ffffff;
                font-size: 0.74rem;
                font-weight: 800;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: rgba(37, 99, 235, 0.78);
            }

            .doc-transfer-footer-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
                margin-top: 18px;
                align-items: end;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-signature-cell {
                text-align: center;
            }

            .doc-transfer-signature-image {
                max-width: 150px;
                max-height: 64px;
                object-fit: contain;
                display: block;
                margin: 0 auto 6px;
            }

            .doc-transfer-signature-gap {
                height: 52px;
            }

            .doc-transfer-signature-line {
                border-top: 1px solid var(--doc-border);
                margin-top: 34px;
                padding-top: 5px;
            }

            .doc-transfer-signature-label {
                margin-top: 4px;
                font-size: 0.9rem;
                font-weight: 800;
                color: #0f172a;
            }

            .doc-transfer-signature-name {
                margin-top: 2px;
                font-size: 0.78rem;
                color: #475569;
            }

            .doc-transfer-footer-meta {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px dashed #dbe3ec;
                position: relative;
                z-index: 1;
                flex-wrap: wrap;
            }

            .doc-transfer-footer-meta-item {
                font-size: 0.88rem;
                color: #475569;
                line-height: 1.45;
            }

            .doc-transfer-sheet {
                position: relative;
                overflow: hidden;
                margin: 0 auto;
                padding: 14px 16px 16px;
                background: linear-gradient(180deg, #ffffff 0%, #fffdf8 100%);
                border: 3px solid #c79a2d;
                box-shadow:
                    inset 0 0 0 1.5px #1b2d67,
                    inset 0 0 0 5px rgba(199, 154, 45, 0.18);
                color: #1b1b1b;
                font-family: "Times New Roman", Georgia, serif;
                zoom: 0.96;
                transform-origin: top center;
            }

            .doc-transfer-sheet::before {
                content: "";
                position: absolute;
                inset: 9px;
                border: 1px solid rgba(27, 45, 103, 0.26);
                pointer-events: none;
            }

            .doc-transfer-watermark {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                user-select: none;
                white-space: nowrap;
                font-size: 60px;
                font-weight: 800;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                color: rgba(27, 45, 103, 0.042);
                transform: rotate(-24deg);
                z-index: 0;
            }

            .doc-transfer-corner {
                position: absolute;
                width: 46px;
                height: 46px;
                pointer-events: none;
                z-index: 1;
            }

            .doc-transfer-corner::before,
            .doc-transfer-corner::after {
                content: "";
                position: absolute;
                box-sizing: border-box;
            }

            .doc-transfer-corner::before {
                width: 22px;
                height: 22px;
                border: 2px solid #c79a2d;
                border-radius: 7px;
                background: rgba(255, 255, 255, 0.6);
            }

            .doc-transfer-corner::after {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #c79a2d;
                box-shadow: 0 0 0 2px #fff;
            }

            .doc-transfer-corner-tl {
                top: 10px;
                left: 10px;
                border-top: 3px solid #c79a2d;
                border-left: 3px solid #c79a2d;
            }

            .doc-transfer-corner-tl::before {
                top: 5px;
                left: 5px;
            }

            .doc-transfer-corner-tl::after {
                right: 6px;
                bottom: 6px;
            }

            .doc-transfer-corner-tr {
                top: 10px;
                right: 10px;
                border-top: 3px solid #c79a2d;
                border-right: 3px solid #c79a2d;
            }

            .doc-transfer-corner-tr::before {
                top: 5px;
                right: 5px;
            }

            .doc-transfer-corner-tr::after {
                left: 6px;
                bottom: 6px;
            }

            .doc-transfer-corner-bl {
                bottom: 10px;
                left: 10px;
                border-bottom: 3px solid #c79a2d;
                border-left: 3px solid #c79a2d;
            }

            .doc-transfer-corner-bl::before {
                bottom: 5px;
                left: 5px;
            }

            .doc-transfer-corner-bl::after {
                right: 6px;
                top: 6px;
            }

            .doc-transfer-corner-br {
                bottom: 10px;
                right: 10px;
                border-bottom: 3px solid #c79a2d;
                border-right: 3px solid #c79a2d;
            }

            .doc-transfer-corner-br::before {
                bottom: 5px;
                right: 5px;
            }

            .doc-transfer-corner-br::after {
                left: 6px;
                top: 6px;
            }

            .doc-transfer-header {
                position: relative;
                z-index: 2;
                display: grid;
                grid-template-columns: 130px minmax(0, 1fr);
                gap: 16px;
                align-items: center;
                text-align: center;
                padding: 2px 10px 0;
            }

            .doc-transfer-logo-block {
                width: 122px;
                height: 122px;
                margin: 0 auto;
                padding: 10px;
                border: 2px solid #c79a2d;
                border-radius: 22px;
                background: linear-gradient(180deg, #fffdf7 0%, #ffffff 100%);
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: inset 0 0 0 1px rgba(27, 45, 103, 0.18);
            }

            .doc-transfer-logo-block::after {
                content: "";
                position: absolute;
                inset: 7px;
                border: 1px solid rgba(27, 45, 103, 0.16);
                border-radius: 16px;
                pointer-events: none;
            }

            .doc-transfer-logo {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
                position: relative;
                z-index: 1;
            }

            .doc-transfer-logo-placeholder {
                clip-path: polygon(50% 0%, 88% 16%, 88% 61%, 50% 100%, 12% 61%, 12% 16%);
                background: linear-gradient(180deg, #16336f 0%, #0f2f57 100%);
                color: #ffffff;
                font-size: 34px;
                font-weight: 800;
                letter-spacing: 0.08em;
            }

            .doc-transfer-header-copy {
                position: relative;
                z-index: 2;
                min-width: 0;
            }

            .doc-transfer-school-name {
                font-size: 34px;
                line-height: 1.04;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                color: #142b6b;
                text-align: center;
            }

            .doc-transfer-affiliation {
                margin-top: 4px;
                font-size: 13.6px;
                line-height: 1.45;
                color: #2a2f39;
                text-align: center;
                font-weight: 600;
            }

            .doc-transfer-line,
            .doc-transfer-extra-line {
                font-size: 13px;
                line-height: 1.45;
                color: #2f3642;
                text-align: center;
            }

            .doc-transfer-extra-line {
                font-size: 12.3px;
                color: #516175;
                margin-top: 2px;
            }

            .doc-transfer-divider {
                position: relative;
                z-index: 2;
                height: 18px;
                margin: 10px 0 8px;
            }

            .doc-transfer-divider::before {
                content: "";
                position: absolute;
                left: 40px;
                right: 40px;
                top: 50%;
                height: 1px;
                transform: translateY(-50%);
                background: #1b2d67;
            }

            .doc-transfer-divider::after {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -54%);
                background: #ffffff;
                padding: 0 12px;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.28em;
                color: #1b2d67;
            }

            .doc-transfer-divider-blue::after {
                content: "◆◆◆";
            }

            .doc-transfer-divider-gold::before {
                left: 70px;
                right: 70px;
                background: #c79a2d;
            }

            .doc-transfer-divider-gold::after {
                content: "❦";
                color: #c79a2d;
                letter-spacing: 0;
                font-size: 20px;
                padding: 0 14px;
            }

            .doc-transfer-title {
                position: relative;
                z-index: 2;
                margin: 2px 0 0;
                text-align: center;
                font-size: 44px;
                line-height: 1.02;
                font-weight: 800;
                letter-spacing: 0.03em;
                color: #162a67;
                text-transform: uppercase;
            }

            .doc-transfer-meta-row {
                position: relative;
                z-index: 2;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                margin: 8px 0 10px;
                font-size: 13.5px;
                line-height: 1.45;
                color: #1b1b1b;
                font-weight: 600;
            }

            .doc-transfer-meta-item {
                white-space: nowrap;
            }

            .doc-transfer-meta-item strong {
                color: #162a67;
                font-weight: 800;
            }

            .doc-transfer-data-table {
                position: relative;
                z-index: 2;
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                background: #ffffff;
                border: 1.4px solid rgba(27, 45, 103, 0.45);
            }

            .doc-transfer-data-table td {
                border: 1px solid rgba(27, 45, 103, 0.28);
                padding: 7px 10px;
                font-size: 13px;
                line-height: 1.45;
                vertical-align: top;
                background: rgba(255, 255, 255, 0.94);
            }

            .doc-transfer-data-table tr:nth-child(odd) td {
                background: #fbfbfd;
            }

            .doc-transfer-row-no {
                width: 46px;
                text-align: center;
                font-weight: 800;
                color: #111827;
            }

            .doc-transfer-row-label {
                width: 37%;
                font-weight: 600;
                color: #111827;
            }

            .doc-transfer-row-colon {
                width: 28px;
                text-align: center;
                font-weight: 800;
                color: #111827;
            }

            .doc-transfer-row-value {
                color: #162a67;
                font-weight: 700;
                word-break: break-word;
            }

            .doc-transfer-declaration {
                position: relative;
                z-index: 2;
                margin-top: 12px;
                font-size: 15px;
                line-height: 1.55;
                color: #111827;
                text-align: justify;
            }

            .doc-transfer-declaration p {
                margin: 0 0 7px;
            }

            .doc-transfer-declaration strong {
                color: #162a67;
            }

            .doc-transfer-footer {
                position: relative;
                z-index: 2;
                display: grid;
                grid-template-columns: minmax(0, 1fr) 136px minmax(0, 1fr);
                gap: 16px;
                align-items: end;
                margin-top: 12px;
            }

            .doc-transfer-place-date {
                padding-left: 6px;
                font-size: 14px;
                line-height: 2;
                color: #111827;
            }

            .doc-transfer-place-date strong {
                color: #111827;
                font-weight: 700;
            }

            .doc-transfer-seal-wrap {
                display: flex;
                align-items: flex-end;
                justify-content: center;
            }

            .doc-transfer-seal-circle {
                width: 118px;
                height: 118px;
                border-radius: 50%;
                border: 3px solid #162a67;
                background: radial-gradient(circle at center, #ffffff 0%, #f7fbff 70%, #eef3ff 100%);
                box-shadow: inset 0 0 0 2px rgba(199, 154, 45, 0.4);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                color: #162a67;
                text-transform: uppercase;
            }

            .doc-transfer-seal-circle span {
                font-size: 10px;
                line-height: 1.1;
                font-weight: 800;
                letter-spacing: 0.22em;
            }

            .doc-transfer-seal-circle strong {
                display: block;
                font-size: 18px;
                line-height: 1.1;
                font-weight: 800;
                letter-spacing: 0.14em;
                margin-top: 2px;
            }

            .doc-transfer-seal-circle small {
                display: block;
                margin-top: 4px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.15em;
            }

            .doc-transfer-signature-block {
                text-align: center;
            }

            .doc-transfer-signature-image {
                display: block;
                max-width: 170px;
                max-height: 62px;
                object-fit: contain;
                margin: 0 auto 4px;
            }

            .doc-transfer-signature-line {
                width: 82%;
                margin: 0 auto 6px;
                border-top: 1px solid #162a67;
            }

            .doc-transfer-signature-name {
                font-size: 16px;
                line-height: 1.15;
                font-weight: 800;
                color: #162a67;
            }

            .doc-transfer-signature-role {
                font-size: 13px;
                line-height: 1.3;
                font-weight: 700;
                color: #111827;
            }

            .doc-transfer-signature-school {
                font-size: 12.5px;
                line-height: 1.25;
                color: #111827;
            }

            @media (max-width: 767px) {
                .doc-transfer-sheet {
                    padding: 12px 12px 14px;
                }

                .doc-transfer-header {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }

                .doc-transfer-logo-block {
                    width: 112px;
                    height: 112px;
                }

                .doc-transfer-school-name {
                    font-size: 27px;
                }

                .doc-transfer-title {
                    font-size: 34px;
                }

                .doc-transfer-meta-row {
                    grid-template-columns: 1fr;
                }

                .doc-transfer-footer {
                    grid-template-columns: 1fr;
                    gap: 12px;
                    text-align: center;
                }

                .doc-transfer-place-date {
                    padding-left: 0;
                }

                .doc-transfer-signature-image {
                    margin: 0 auto 4px;
                }
            }

            .doc-character-certificate .doc-character-logo-block {
                width: 128px;
                height: 128px;
                padding: 0;
                border: 0;
                background: transparent;
                box-shadow: none;
            }

            .doc-character-certificate .doc-character-logo-block::after {
                content: none;
            }

            .doc-character-certificate .doc-character-logo-block .doc-transfer-logo,
            .doc-character-certificate .doc-character-logo-block .doc-transfer-logo-placeholder {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .doc-character-certificate .doc-character-affiliation {
                position: relative;
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                margin-top: 4px;
                font-size: 13.5px;
                line-height: 1.45;
                font-weight: 600;
                color: #2a2f39;
                text-align: center;
            }

            .doc-character-certificate .doc-character-affiliation::before,
            .doc-character-certificate .doc-character-affiliation::after {
                content: "";
                flex: 1 1 72px;
                height: 1px;
                background: #c79a2d;
            }

            .doc-character-certificate .doc-character-affiliation span {
                display: inline-block;
                padding: 0 6px;
                white-space: nowrap;
            }

            .doc-character-certificate .doc-character-meta-row {
                position: relative;
                z-index: 2;
                display: flex;
                justify-content: space-between;
                gap: 16px;
                margin: 10px 2px 12px;
                font-size: 14px;
                line-height: 1.45;
                color: #1b1b1b;
                font-weight: 600;
            }

            .doc-character-certificate .doc-character-meta-item strong {
                color: #162a67;
                font-weight: 800;
            }

            .doc-character-certificate .doc-character-declaration {
                position: relative;
                z-index: 2;
                margin-top: 8px;
                padding: 0;
                border: 0;
                background: transparent;
                font-size: 15px;
                line-height: 1.75;
                color: #111827;
                text-align: justify;
            }

            .doc-character-certificate .doc-character-declaration p {
                margin: 0 0 12px;
            }

            .doc-character-certificate .doc-character-declaration strong {
                color: #162a67;
            }

            @media (max-width: 767px) {
                .doc-character-certificate .doc-character-logo-block {
                    width: 112px;
                    height: 112px;
                }

                .doc-character-certificate .doc-character-meta-row {
                    flex-direction: column;
                }

                .doc-character-certificate .doc-character-affiliation::before,
                .doc-character-certificate .doc-character-affiliation::after {
                    flex-basis: 24px;
                }
            }

            .doc-character-certificate .doc-certificate-frame {
                background:
                    radial-gradient(circle at 12% 18%, rgba(15, 23, 42, 0.035) 0 1px, transparent 1.3px) 0 0 / 24px 24px,
                    radial-gradient(circle at 84% 78%, rgba(15, 23, 42, 0.028) 0 1px, transparent 1.3px) 0 0 / 28px 28px,
                    linear-gradient(180deg, #fffdf5 0%, #fffefb 45%, #ffffff 100%);
                padding: 22px 24px 26px;
                zoom: <?php echo $paperSize === 'A5' ? '0.30' : '0.40'; ?>;
                transform-origin: top center;
                overflow: hidden;
            }

            .doc-character-certificate .doc-certificate-frame::before {
                inset: 11px;
                border-color: rgba(30, 58, 138, 0.22);
                border-radius: 14px;
                box-shadow:
                    inset 0 0 0 2px rgba(180, 83, 9, 0.08),
                    inset 0 0 0 10px rgba(255, 255, 255, 0.75);
            }

            .doc-character-certificate .doc-certificate-frame::after {
                content: 'CHARACTER CERTIFICATE';
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 3.2rem;
                font-weight: 900;
                letter-spacing: 0.26em;
                color: rgba(30, 58, 138, 0.045);
                transform: rotate(-18deg);
                text-transform: uppercase;
                pointer-events: none;
                user-select: none;
            }

            .doc-character-certificate .doc-certificate-header {
                gap: 12px;
                margin-bottom: 12px;
                padding-bottom: 12px;
            }

            .doc-character-certificate .doc-certificate-brand {
                gap: 10px;
            }

            .doc-character-certificate .doc-certificate-logo,
            .doc-character-certificate .doc-certificate-logo-placeholder {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                border: 2px solid rgba(180, 83, 9, 0.16);
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            }

            .doc-character-certificate .doc-certificate-logo-placeholder {
                background: linear-gradient(180deg, #eef4ff 0%, #ffffff 100%);
            }

            .doc-character-certificate .doc-certificate-school-name {
                font-size: 24px;
                letter-spacing: 0.04em;
                text-align: center;
            }

            .doc-character-certificate .doc-certificate-meta {
                font-size: 0.84rem;
                text-align: center;
                max-width: 700px;
            }

            .doc-character-certificate .doc-certificate-number {
                width: min(100%, 520px);
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 12px 14px;
                background: linear-gradient(180deg, #fff7e8 0%, #ffffff 100%);
                border-color: rgba(180, 83, 9, 0.22);
                box-shadow: 0 10px 18px rgba(15, 23, 42, 0.04);
            }

            .doc-character-certificate .doc-certificate-number-item {
                text-align: center;
            }

            .doc-character-certificate .doc-certificate-title {
                margin-top: 12px;
                padding: 9px 24px;
                border-radius: 999px;
                background: linear-gradient(135deg, #1d4ed8 0%, #0f2e6e 100%);
                box-shadow: 0 10px 22px rgba(30, 58, 138, 0.16);
                letter-spacing: 0.24em;
                font-size: 15px;
            }

            .doc-character-certificate .doc-certificate-subtitle {
                margin-top: 8px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                font-size: 0.78rem;
            }

            .doc-character-intro-band {
                display: grid;
                grid-template-columns: minmax(100px, 132px) minmax(0, 1fr) minmax(220px, 0.92fr);
                gap: 14px;
                align-items: center;
                margin-top: 16px;
                position: relative;
                z-index: 1;
            }

            .doc-character-crest {
                width: 132px;
                height: 132px;
                border-radius: 50%;
                border: 2px solid rgba(180, 83, 9, 0.22);
                background:
                    radial-gradient(circle at center, #fff 0%, #fffaf1 62%, #eef4ff 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 14px;
                position: relative;
                margin: 0 auto;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.9), 0 8px 18px rgba(15, 23, 42, 0.06);
            }

            .doc-character-crest::before,
            .doc-character-crest::after {
                content: '';
                position: absolute;
                inset: 14px;
                border: 2px solid rgba(180, 83, 9, 0.16);
                border-radius: 50%;
            }

            .doc-character-crest::after {
                inset: 22px;
                border-color: rgba(30, 58, 138, 0.14);
            }

            .doc-character-crest-copy {
                position: relative;
                z-index: 1;
                text-align: center;
                color: #0f172a;
                line-height: 1.1;
                text-transform: uppercase;
                font-weight: 800;
            }

            .doc-character-crest-copy span {
                display: block;
                font-size: 0.7rem;
                letter-spacing: 0.24em;
                color: var(--doc-muted);
            }

            .doc-character-crest-copy strong {
                display: block;
                font-size: 1.1rem;
                letter-spacing: 0.14em;
                margin-top: 5px;
                color: #1e3a8a;
            }

            .doc-character-crest-copy small {
                display: block;
                margin-top: 5px;
                font-size: 0.72rem;
                letter-spacing: 0.16em;
                color: #b45309;
            }

            .doc-character-intro-copy {
                min-width: 0;
            }

            .doc-character-intro-kicker {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.18em;
                font-weight: 800;
                color: #b45309;
                margin-bottom: 8px;
            }

            .doc-character-intro-name {
                font-size: 1.95rem;
                line-height: 1.16;
                font-weight: 900;
                color: #0f172a;
            }

            .doc-character-intro-name .doc-character-highlight-name {
                display: inline-block;
                padding: 0 6px;
                border-bottom: 3px solid rgba(180, 83, 9, 0.28);
                background: linear-gradient(180deg, rgba(255, 247, 237, 0.92) 0%, rgba(255, 255, 255, 0) 100%);
                color: #1d4ed8;
            }

            .doc-character-intro-meta {
                margin-top: 8px;
                color: #475569;
                font-size: 0.92rem;
                line-height: 1.55;
            }

            .doc-character-grade-panel {
                border: 1px solid rgba(180, 83, 9, 0.18);
                border-radius: 16px;
                background: linear-gradient(180deg, #fffdf7 0%, #ffffff 100%);
                padding: 12px 14px;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.75);
            }

            .doc-character-grade-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.16em;
                color: #64748b;
                font-weight: 800;
                margin-bottom: 10px;
            }

            .doc-character-grade-list {
                display: grid;
                gap: 8px;
            }

            .doc-character-grade-option {
                display: flex;
                align-items: center;
                gap: 10px;
                border: 1px solid #e2e8f0;
                border-radius: 999px;
                padding: 8px 10px;
                color: #475569;
                background: #ffffff;
                font-size: 0.92rem;
                font-weight: 700;
            }

            .doc-character-grade-box {
                width: 14px;
                height: 14px;
                border: 2px solid currentColor;
                border-radius: 3px;
                position: relative;
                flex: 0 0 auto;
                background: #fff;
            }

            .doc-character-grade-option.is-selected {
                color: #0f172a;
                border-color: rgba(29, 78, 216, 0.28);
                background: linear-gradient(135deg, rgba(219, 234, 254, 0.85) 0%, rgba(255, 255, 255, 1) 100%);
                box-shadow: 0 8px 14px rgba(30, 58, 138, 0.06);
            }

            .doc-character-grade-option.is-selected .doc-character-grade-box {
                border-color: #1d4ed8;
                background: #1d4ed8;
            }

            .doc-character-grade-option.is-selected .doc-character-grade-box::after {
                content: '';
                position: absolute;
                left: 3px;
                top: 0px;
                width: 4px;
                height: 8px;
                border-right: 2px solid #ffffff;
                border-bottom: 2px solid #ffffff;
                transform: rotate(45deg);
            }

            .doc-character-declaration {
                margin-top: 16px;
                border: 1px solid #dbe3ec;
                border-left: 4px solid var(--doc-primary);
                border-radius: 14px;
                background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                padding: 15px 16px;
                color: #0f172a;
                font-size: 1rem;
                line-height: 1.9;
                text-align: justify;
                position: relative;
                z-index: 1;
            }

            .doc-character-declaration strong {
                color: #1e3a8a;
            }

            .doc-character-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
                margin-top: 16px;
                position: relative;
                z-index: 1;
            }

            .doc-character-grid .doc-certificate-field {
                border-radius: 12px;
                min-height: 70px;
                background: #ffffff;
                border-color: #dbe3ec;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
            }

            .doc-character-grid .doc-certificate-wide {
                grid-column: 1 / -1;
                min-height: 76px;
            }

            .doc-character-grid .doc-certificate-field-label {
                color: #64748b;
                letter-spacing: 0.12em;
            }

            .doc-character-grid .doc-certificate-field-value {
                color: #111827;
                font-size: 0.94rem;
                line-height: 1.5;
            }

            .doc-character-note {
                margin-top: 14px;
                border: 1px solid rgba(180, 83, 9, 0.16);
                border-radius: 14px;
                background: linear-gradient(180deg, #fffaf0 0%, #ffffff 100%);
                padding: 12px 14px;
                position: relative;
                z-index: 1;
            }

            .doc-character-note-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.16em;
                color: #b45309;
                font-weight: 800;
                margin-bottom: 6px;
            }

            .doc-character-note-body {
                color: #0f172a;
                font-size: 0.95rem;
                line-height: 1.75;
            }

            .doc-character-security {
                display: grid;
                grid-template-columns: minmax(0, 1.4fr) minmax(170px, 0.82fr) minmax(170px, 0.92fr);
                gap: 12px;
                margin-top: 16px;
                align-items: stretch;
                position: relative;
                z-index: 1;
            }

            .doc-character-security-item {
                border: 1px solid #dbe3ec;
                border-radius: 14px;
                padding: 12px 14px;
                background: #fbfdff;
                overflow: hidden;
            }

            .doc-character-security-note {
                border-left: 4px solid var(--doc-primary);
            }

            .doc-character-security-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.16em;
                color: #64748b;
                font-weight: 800;
            }

            .doc-character-security-text {
                margin-top: 6px;
                color: #0f172a;
                font-size: 0.93rem;
                line-height: 1.62;
            }

            .doc-character-security-subtext {
                margin-top: 8px;
                color: #475569;
                font-size: 0.88rem;
                line-height: 1.5;
            }

            .doc-character-security-qr,
            .doc-character-security-seal {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 8px;
            }

            .doc-character-security-qr img {
                width: 138px;
                height: 138px;
                border: 1px solid #dbe3ec;
                background: #fff;
                padding: 4px;
                border-radius: 12px;
            }

            .doc-character-security-qr small {
                color: #475569;
                font-size: 0.78rem;
            }

            .doc-character-seal-circle {
                width: 130px;
                height: 130px;
                border-radius: 50%;
                border: 3px solid rgba(30, 58, 138, 0.34);
                background: radial-gradient(circle at center, #ffffff 0%, #f8fbff 70%, #eef4ff 100%);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 10px;
                color: #1e3a8a;
                text-transform: uppercase;
                font-weight: 900;
                line-height: 1.1;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.8);
            }

            .doc-character-seal-circle span {
                font-size: 0.68rem;
                letter-spacing: 0.24em;
                color: #64748b;
            }

            .doc-character-seal-circle strong {
                margin-top: 4px;
                font-size: 1.1rem;
                letter-spacing: 0.12em;
            }

            .doc-character-verification-stamp {
                padding: 8px 10px;
                border: 2px dashed rgba(180, 83, 9, 0.38);
                border-radius: 999px;
                color: rgba(180, 83, 9, 0.92);
                font-size: 0.72rem;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                font-weight: 800;
                white-space: nowrap;
            }

            .doc-character-footer {
                margin-top: 16px;
                position: relative;
                z-index: 1;
            }

            .doc-character-footer-strip {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
                padding-top: 2px;
                margin-bottom: 12px;
            }

            .doc-character-footer-strip-item {
                border: 1px solid #dbe3ec;
                border-radius: 12px;
                background: #ffffff;
                padding: 10px 12px;
                color: #0f172a;
                font-size: 0.9rem;
                line-height: 1.55;
            }

            .doc-character-footer-strip-item strong {
                color: #1e3a8a;
            }

            .doc-character-signature-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                align-items: end;
            }

            .doc-character-signature-card {
                border-top: 1px solid #dbe3ec;
                padding-top: 10px;
                text-align: center;
            }

            .doc-character-signature-role {
                font-size: 0.76rem;
                text-transform: uppercase;
                letter-spacing: 0.16em;
                color: #64748b;
                font-weight: 800;
                margin-bottom: 8px;
            }

            .doc-character-signature-space {
                min-height: 72px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .doc-character-signature-gap {
                width: 100%;
                max-width: 180px;
                border-bottom: 2px solid rgba(15, 23, 42, 0.18);
                height: 1px;
            }

            .doc-character-signature-img {
                max-width: 190px;
                max-height: 76px;
                object-fit: contain;
                display: block;
                margin: 0 auto;
            }

            .doc-character-signature-line {
                margin-top: 2px;
                border-top: 1px solid #0f172a;
                opacity: 0.3;
            }

            .doc-character-signature-name {
                margin-top: 5px;
                color: #475569;
                font-size: 0.8rem;
                line-height: 1.4;
            }

            .doc-character-footer-meta {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                margin-top: 12px;
                padding-top: 10px;
                border-top: 1px dashed rgba(180, 83, 9, 0.22);
                color: #475569;
                font-size: 0.88rem;
                line-height: 1.5;
                flex-wrap: wrap;
            }

            .doc-character-footer-meta-item strong {
                color: #1e3a8a;
            }

            .doc-details-table {
                width: 100%;
                border-collapse: collapse;
                margin: 18px 0;
            }

            .doc-details-table td {
                border: 1px solid #dbe3ec;
                padding: 10px 12px;
                vertical-align: top;
            }

            .doc-details-table td:first-child {
                width: 30%;
                background: var(--doc-soft);
                font-weight: 700;
                color: #334155;
            }

            .doc-note {
                margin-top: 14px;
                font-size: 0.92rem;
                color: #475569;
                line-height: 1.5;
                border-left: 3px solid var(--doc-primary);
                padding-left: 10px;
            }

            .doc-signature-row {
                display: flex;
                justify-content: space-between;
                gap: 18px;
                margin-top: 42px;
                align-items: flex-end;
            }

            .doc-signature {
                text-align: center;
                min-width: 220px;
            }

            .doc-signature-img {
                max-width: 220px;
                max-height: 90px;
                object-fit: contain;
                display: block;
                margin: 0 auto 8px;
            }

            .doc-signature-gap {
                height: 80px;
            }

            .doc-signature-line {
                border-top: 1px solid var(--doc-border);
                margin-top: 42px;
                padding-top: 5px;
                font-size: 12px;
                color: #111827;
            }

            .doc-footer {
                margin-top: 24px;
                text-align: center;
                font-size: 11px;
                color: var(--doc-muted);
                border-top: 1px solid var(--doc-soft);
                padding-top: 10px;
                position: relative;
                z-index: 1;
            }

            .doc-instructions {
                margin-top: 18px;
                padding: 14px 16px;
                border: 1px solid #dbe3ec;
                border-radius: 8px;
                background: var(--doc-soft);
                position: relative;
                z-index: 1;
            }

            .doc-instructions h5 {
                margin: 0 0 8px 0;
                font-size: 1rem;
            }

            .doc-section-heading {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                gap: 12px;
                margin: 18px 0 10px;
                position: relative;
                z-index: 1;
            }

            .doc-section-heading h5 {
                margin: 0;
                font-size: 1rem;
                font-weight: 700;
                color: #0f172a;
            }

            .doc-section-heading small {
                color: var(--doc-muted);
            }

            .doc-instructions ul {
                margin: 0;
                padding-left: 18px;
            }

            @media print {
                .no-print {
                    display: none !important;
                }

                body {
                    background: #ffffff;
                    padding: 0;
                }

                .doc-page {
                    max-width: none;
                    border: 0;
                    border-radius: 0;
                    padding: 0;
                    box-shadow: none;
                }

                .doc-transfer-sheet {
                    box-shadow: none;
                }
            }

            @media (max-width: 767px) {
                .doc-grid-2 {
                    grid-template-columns: 1fr;
                }

                .doc-photo-wrap {
                    flex-direction: column;
                }

                .doc-qr {
                    position: static;
                    margin-bottom: 12px;
                }

                .doc-header {
                    flex-direction: column;
                }

                .doc-signature-row {
                    flex-direction: column;
                    align-items: stretch;
                }

                .doc-certificate-header,
                .doc-certificate-footer {
                    flex-direction: column;
                    align-items: stretch;
                }

                .doc-certificate-number,
                .doc-certificate-sign {
                    min-width: 0;
                    width: 100%;
                }

                .doc-certificate-main,
                .doc-certificate-grid {
                    grid-template-columns: 1fr;
                }

                .doc-certificate-photo {
                    width: 100%;
                }

                .doc-certificate-photo img,
                .doc-certificate-photo-placeholder {
                    width: 100%;
                    max-width: 180px;
                }

                .doc-certificate-stamp {
                    right: 12px;
                    bottom: 12px;
                    width: 92px;
                    height: 92px;
                    font-size: 0.65rem;
                }

                .doc-certificate-header-details,
                .doc-transfer-fields-grid,
                .doc-transfer-strip,
                .doc-transfer-security,
                .doc-transfer-footer-grid {
                    grid-template-columns: 1fr;
                }

                .doc-character-intro-band,
                .doc-character-grid,
                .doc-character-security,
                .doc-character-footer-strip,
                .doc-character-signature-grid {
                    grid-template-columns: 1fr;
                }

                .doc-transfer-certificate .doc-certificate-number {
                    grid-template-columns: 1fr;
                    width: 100%;
                }

                .doc-transfer-fields-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .doc-transfer-field-card {
                    min-height: 58px;
                }

                .doc-transfer-security-qr img {
                    width: 120px;
                    height: 120px;
                }

                .doc-transfer-seal-circle {
                    width: 112px;
                    height: 112px;
                }

                .doc-character-crest {
                    width: 118px;
                    height: 118px;
                }

                .doc-character-intro-name {
                    font-size: 1.5rem;
                }

                .doc-character-security-qr img {
                    width: 120px;
                    height: 120px;
                }

                .doc-character-seal-circle {
                    width: 112px;
                    height: 112px;
                }
            }
        </style>
    </head>
    <body>
        <div class="doc-toolbar no-print">
            <a class="doc-btn doc-btn-muted" href="<?php echo docEscape($backUrl); ?>">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a class="doc-btn doc-btn-muted" href="<?php echo docEscape($refreshUrl); ?>">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </a>
            <?php if (count($allowedPaperSizes) > 1): ?>
                <div class="doc-paper-switch" role="group" aria-label="Paper size">
                    <?php if ($paperUrlA4 !== ''): ?>
                        <a class="doc-btn <?php echo $paperSize === 'A4' ? 'active' : ''; ?>" href="<?php echo docEscape($paperUrlA4); ?>">A4</a>
                    <?php endif; ?>
                    <?php if ($paperUrlA5 !== ''): ?>
                        <a class="doc-btn <?php echo $paperSize === 'A5' ? 'active' : ''; ?>" href="<?php echo docEscape($paperUrlA5); ?>">A5</a>
                    <?php endif; ?>
                </div>
            <?php elseif (count($allowedPaperSizes) === 1): ?>
                <div class="doc-paper-switch" role="group" aria-label="Paper size">
                    <span class="doc-btn active"><?php echo docEscape($allowedPaperSizes[0]); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($downloadUrl)): ?>
                <a class="doc-btn doc-btn-primary" href="<?php echo docEscape($downloadUrl); ?>">
                    <i class="bi bi-download"></i> Download PDF
                </a>
            <?php endif; ?>
            <button type="button" class="doc-btn doc-btn-primary" onclick="window.print();">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>

        <?php foreach ($pages as $pageHtml): ?>
            <div class="doc-page">
                <?php if (!empty($watermark)): ?>
                    <div class="doc-watermark"><?php echo docEscape($watermark); ?></div>
                <?php endif; ?>
                <?php echo $pageHtml; ?>
                <div class="doc-footer">
                    This is a computer-generated document. Generated on <?php echo docEscape(date('d M Y, h:i A')); ?>.
                </div>
            </div>
        <?php endforeach; ?>

        <script>
            if (new URLSearchParams(window.location.search).get('print') === 'auto') {
                setTimeout(function() {
                    window.print();
                }, 450);
            }
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function docRenderAdmitCardContent($schoolSettings, $student, $exam, $issueDate = '') {
    $scheduleRows = docGetAdmitCardScheduleRows($student, $exam, $issueDate);

    return admitCardRenderSheetFragment($schoolSettings, $student, $exam, $scheduleRows, [
        'issue_date' => $issueDate,
        'admit_no' => trim((string)($student['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0),
    ]);
}

function docRenderTransferCertificateContent($schoolSettings, $student, $issueDate = '', $remarks = '', $certificateNo = '') {
    $signatureSrc = getSchoolSignatureSrc($schoolSettings['principal_signature'] ?? '');
    $schoolName = $schoolSettings['school_name'] ?? APP_NAME;
    $issueDateRaw = $issueDate !== '' ? $issueDate : ($_GET['issue_date'] ?? date('Y-m-d'));
    $issueDateValue = docFormatDate($issueDateRaw, 'd F Y');
    $remarks = trim((string)($remarks !== '' ? $remarks : ($_GET['remarks'] ?? '')));
    $certificateNo = docNormalizeCertificateNumber($certificateNo !== '' ? $certificateNo : ($_GET['certificate_no'] ?? ''));
    if ($certificateNo === '') {
        $certificateNo = docBuildTransferCertificateNumber($schoolSettings);
    }

    $schoolBoard = docGetSettingValue($schoolSettings, ['affiliation_board', 'board_name', 'board'], 'CBSE, New Delhi');
    $schoolAffiliationNo = docGetSettingValue($schoolSettings, ['affiliation_no', 'affiliation_number'], '-');
    $schoolCode = docGetSettingValue($schoolSettings, ['school_code', 'udise_code'], '-');
    $schoolWebsite = docGetSettingValue($schoolSettings, ['school_website', 'website'], '-');
    $schoolPhone = docGetSettingValue($schoolSettings, ['school_phone', 'phone'], '');
    $schoolEmail = docGetSettingValue($schoolSettings, ['school_email', 'email'], '');
    $schoolAddress = docGetSettingValue($schoolSettings, ['school_address', 'address'], '');
    $schoolCity = docGetSettingValue($schoolSettings, ['school_city', 'city'], '');
    $schoolCode = docGetSettingValue($schoolSettings, ['school_code', 'udise_code'], '-');
    $udiseRegNo = docNormalizeDisplayValue($student['udise_no'] ?? ($student['registration_no'] ?? ($schoolSettings['udise_code'] ?? '')), '');
    $principalName = docGetSettingValue($schoolSettings, ['principal_name', 'headmaster_name'], '');
    $sealYear = docGetSettingValue($schoolSettings, ['established_year', 'estd_year', 'school_established_year'], '');

    $studentName = docNormalizeDisplayValue($student['student_name'] ?? '', 'N/A');
    $admissionNo = docNormalizeDisplayValue($student['admission_no'] ?? '', 'N/A');
    $rollNo = docNormalizeDisplayValue($student['roll_no'] ?? '', 'N/A');
    $fatherName = docNormalizeDisplayValue($student['father_name'] ?? '', 'N/A');
    if ($fatherName !== 'N/A' && stripos($fatherName, 'mr.') !== 0 && stripos($fatherName, 'shri ') !== 0 && stripos($fatherName, 'sh.') !== 0) {
        $fatherName = 'Mr. ' . $fatherName;
    }
    $motherName = docNormalizeDisplayValue($student['mother_name'] ?? '', 'N/A');
    if ($motherName !== 'N/A' && stripos($motherName, 'mrs.') !== 0 && stripos($motherName, 'smt.') !== 0 && stripos($motherName, 'mt.') !== 0) {
        $motherName = 'Mrs. ' . $motherName;
    }
    $dateOfBirthRaw = trim((string)($student['date_of_birth'] ?? ''));
    $dateOfBirth = $dateOfBirthRaw !== '' ? docFormatDate($dateOfBirthRaw, 'd F Y') : 'N/A';
    $nationality = docNormalizeDisplayValue($student['nationality'] ?? 'Indian', 'Indian');
    $admissionDate = docFormatDate($student['admission_date'] ?? '', 'd F Y');
    $classAdmitted = docNormalizeDisplayValue($student['admission_class'] ?? ($student['class_admitted'] ?? ($student['admission_class_name'] ?? '')), 'N/A');
    $lastClassLabel = docNormalizeDisplayValue(trim((string)($student['last_class_studied'] ?? ($student['class_name'] ?? ($student['previous_class'] ?? '')))), 'N/A');
    $promotionStatus = docNormalizeDisplayValue($student['promotion_status'] ?? ($student['result_status'] ?? 'Passed'), 'Passed');
    $conductStatus = docNormalizeDisplayValue($student['conduct'] ?? ($student['character'] ?? 'Good'), 'Good');
    $leavingReason = $remarks !== '' ? $remarks : docNormalizeDisplayValue($student['reason_for_leaving'] ?? ($student['leaving_reason'] ?? "Parent's Transfer"), "Parent's Transfer");
    $leavingDate = docFormatDate($student['leaving_date'] ?? ($student['date_of_leaving'] ?? $issueDateRaw), 'd F Y');
    $dueSummary = function_exists('getStudentFeeSummary') ? getStudentFeeSummary(intval($student['student_id'] ?? 0)) : null;
    $allDuesCleared = (!is_array($dueSummary) || floatval($dueSummary['due_total'] ?? 0) <= 0) ? 'Yes' : 'No';
    $place = $schoolCity !== '' ? $schoolCity : '________________';
    $affiliationLine = 'Affiliated to ' . $schoolBoard;
    if ($schoolAffiliationNo !== '' && $schoolAffiliationNo !== '-') {
        $affiliationLine .= ' (Affiliation No.: ' . $schoolAffiliationNo . ')';
    }
    $contactBits = array_filter([
        $schoolPhone !== '' ? 'Ph.: ' . $schoolPhone : '',
        $schoolEmail !== '' ? 'Email: ' . $schoolEmail : '',
    ]);
    $contactLine = !empty($contactBits) ? implode(' | ', $contactBits) : '';
    $websiteLine = $schoolWebsite !== '' ? 'Website: ' . $schoolWebsite : '';
    $schoolCodeLine = $schoolCode !== '' && $schoolCode !== '-' ? 'School Code: ' . $schoolCode : '';
    $udiseLine = $udiseRegNo !== '' ? 'UDISE / Reg. No.: ' . $udiseRegNo : '';
    $extraMetaLine = implode(' | ', array_filter([$schoolCodeLine, $udiseLine]));
    $sealLabel = $sealYear !== '' ? 'ESTD. ' . $sealYear : 'OFFICIAL SEAL';
    $verificationText = implode('|', array_filter([
        $schoolName,
        'Transfer Certificate',
        $certificateNo,
        $studentName,
        $admissionNo,
        $issueDateValue,
    ]));
    $rows = [
        ['label' => 'Name of the Student', 'value' => $studentName],
        ['label' => 'Father\'s Name', 'value' => $fatherName],
        ['label' => 'Mother\'s Name', 'value' => $motherName],
        ['label' => 'Date of Birth (as per records)', 'value' => $dateOfBirth],
        ['label' => 'Nationality', 'value' => $nationality],
        ['label' => 'Date of Admission', 'value' => $admissionDate],
        ['label' => 'Class/Course Admitted To', 'value' => $classAdmitted],
        ['label' => 'Last Class/Course Studied', 'value' => $lastClassLabel],
        ['label' => 'Whether Qualified for Promotion/Passed', 'value' => $promotionStatus],
        ['label' => 'Conduct and Character', 'value' => $conductStatus],
        ['label' => 'Reason for Leaving', 'value' => $leavingReason],
        ['label' => 'Date of Leaving the Institution', 'value' => docFormatDate($student['leaving_date'] ?? ($student['date_of_leaving'] ?? $issueDateRaw))],
        ['label' => 'Whether All Dues Have Been Cleared', 'value' => $allDuesCleared],
    ];

    ob_start();
    ?>
    <div class="doc-certificate-shell doc-transfer-certificate">
        <div class="doc-transfer-sheet">
            <div class="doc-transfer-watermark"><?php echo docEscape($schoolName); ?></div>
            <div class="doc-transfer-corner doc-transfer-corner-tl"></div>
            <div class="doc-transfer-corner doc-transfer-corner-tr"></div>
            <div class="doc-transfer-corner doc-transfer-corner-bl"></div>
            <div class="doc-transfer-corner doc-transfer-corner-br"></div>

            <div class="doc-transfer-header">
                <div class="doc-transfer-logo-block">
                    <?php $logoSrc = getSchoolLogoSrc($schoolSettings['school_logo'] ?? '', $schoolSettings['banner_logo'] ?? ''); ?>
                    <?php if (!empty($logoSrc)): ?>
                        <img src="<?php echo docEscape($logoSrc); ?>" alt="<?php echo docEscape($schoolName); ?>" class="doc-transfer-logo">
                    <?php else: ?>
                        <div class="doc-transfer-logo doc-transfer-logo-placeholder"><?php echo docEscape(strtoupper(substr(preg_replace('/[^A-Za-z]+/', '', $schoolName), 0, 2) ?: 'SC')); ?></div>
                    <?php endif; ?>
                </div>

                <div class="doc-transfer-header-copy">
                    <div class="doc-transfer-school-name"><?php echo docEscape($schoolName); ?></div>
                    <div class="doc-transfer-affiliation"><?php echo docEscape($affiliationLine); ?></div>
                    <?php if ($schoolAddress !== ''): ?>
                        <div class="doc-transfer-line"><?php echo docEscape($schoolAddress); ?></div>
                    <?php endif; ?>
                    <?php if ($contactLine !== ''): ?>
                        <div class="doc-transfer-line"><?php echo docEscape($contactLine); ?></div>
                    <?php endif; ?>
                    <?php if ($websiteLine !== ''): ?>
                        <div class="doc-transfer-line"><?php echo docEscape($websiteLine); ?></div>
                    <?php endif; ?>
                    <?php if ($extraMetaLine !== ''): ?>
                        <div class="doc-transfer-extra-line"><?php echo docEscape($extraMetaLine); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="doc-transfer-divider doc-transfer-divider-blue"></div>
            <div class="doc-transfer-title">TRANSFER CERTIFICATE</div>
            <div class="doc-transfer-divider doc-transfer-divider-gold"></div>

            <div class="doc-transfer-meta-row">
                <div class="doc-transfer-meta-item">Certificate No.: <strong><?php echo docEscape($certificateNo); ?></strong></div>
                <div class="doc-transfer-meta-item">Admission No.: <strong><?php echo docEscape($admissionNo); ?></strong></div>
                <div class="doc-transfer-meta-item">Date of Issue: <strong><?php echo docEscape($issueDateValue); ?></strong></div>
            </div>

            <table class="doc-transfer-data-table" aria-label="Transfer certificate details">
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="doc-transfer-row-no"><?php echo docEscape((string)($index + 1)); ?></td>
                            <td class="doc-transfer-row-label"><?php echo docEscape($row['label']); ?></td>
                            <td class="doc-transfer-row-colon">:</td>
                            <td class="doc-transfer-row-value"><?php echo docEscape($row['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="doc-transfer-declaration">
                <p>
                    This is to certify that <strong><?php echo docEscape($studentName); ?></strong> was a bona fide student of this institution and attended the school regularly from <strong><?php echo docEscape($admissionDate); ?></strong> to <strong><?php echo docEscape($leavingDate); ?></strong>.
                </p>
                <p>
                    His/Her conduct and character during the stay in the school were found to be satisfactory.
                </p>
                <p>
                    The Transfer Certificate is issued on the request of the student and is hereby permitted to seek admission to another educational institution.
                </p>
            </div>

            <div class="doc-transfer-footer">
                <div class="doc-transfer-place-date">
                    <div><strong>Place:</strong> <?php echo docEscape($place); ?></div>
                    <div><strong>Date:</strong> <?php echo docEscape($issueDateValue); ?></div>
                </div>

                <div class="doc-transfer-seal-wrap">
                    <div class="doc-transfer-seal-circle">
                        <span>Official</span>
                        <strong>Seal</strong>
                        <small><?php echo docEscape($sealLabel); ?></small>
                    </div>
                </div>

                <div class="doc-transfer-signature-block">
                    <?php if (!empty($signatureSrc)): ?>
                        <img src="<?php echo docEscape($signatureSrc); ?>" alt="Principal Signature" class="doc-transfer-signature-image">
                    <?php endif; ?>
                    <div class="doc-transfer-signature-line"></div>
                    <div class="doc-transfer-signature-name"><?php echo docEscape($principalName !== '' ? $principalName : 'Principal'); ?></div>
                    <div class="doc-transfer-signature-role">Principal</div>
                    <div class="doc-transfer-signature-school"><?php echo docEscape($schoolName); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderCharacterCertificateContent($schoolSettings, $student, $issueDate = '', $remarks = '', $characterGrade = 'very_good', $referenceNo = '') {
    $signatureSrc = getSchoolSignatureSrc($schoolSettings['principal_signature'] ?? '');
    $logoSrc = getSchoolLogoSrc($schoolSettings['school_logo'] ?? '', $schoolSettings['banner_logo'] ?? '');
    $schoolName = $schoolSettings['school_name'] ?? APP_NAME;
    $schoolBoard = docGetSettingValue($schoolSettings, ['affiliation_board', 'board_name', 'board'], 'CBSE, New Delhi');
    $schoolAffiliationNo = docGetSettingValue($schoolSettings, ['affiliation_no', 'affiliation_number'], '-');
    $schoolCode = docGetSettingValue($schoolSettings, ['school_code', 'udise_code'], '-');
    $schoolAddress = docGetSettingValue($schoolSettings, ['school_address', 'address'], '');
    $schoolPhone = docGetSettingValue($schoolSettings, ['school_phone', 'phone'], '');
    $schoolEmail = docGetSettingValue($schoolSettings, ['school_email', 'email'], '');
    $schoolWebsite = docGetSettingValue($schoolSettings, ['school_website', 'website'], '-');
    $schoolCity = docGetSettingValue($schoolSettings, ['school_city', 'city'], '');
    $sealYear = docGetSettingValue($schoolSettings, ['established_year', 'estd_year', 'school_established_year'], '');
    $principalName = docGetSettingValue($schoolSettings, ['principal_name', 'headmaster_name'], 'Principal');

    $issueDateRaw = $issueDate !== '' ? $issueDate : ($_GET['issue_date'] ?? date('Y-m-d'));
    $issueDateDisplay = docFormatDate($issueDateRaw, 'd F Y');
    $issueDate = docFormatDate($issueDateRaw);
    $remarks = trim((string) ($remarks !== '' ? $remarks : ($_GET['remarks'] ?? '')));
    $characterGrade = docNormalizeCharacterGrade($characterGrade !== '' ? $characterGrade : ($_GET['character_grade'] ?? 'very_good'));

    $studentName = docNormalizeDisplayValue($student['student_name'] ?? '', 'N/A');
    $fatherName = docNormalizeDisplayValue($student['father_name'] ?? ($student['guardian_name'] ?? ''), 'N/A');
    $classLabel = docNormalizeDisplayValue(trim((string) (($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? ''))), 'N/A');
    $academicSession = docNormalizeDisplayValue(
        $student['academic_year'] ?? ($student['batch'] ?? ($student['session'] ?? ($schoolSettings['current_academic_year'] ?? getCurrentAcademicYear()))),
        'N/A'
    );
    $gender = strtolower(trim((string)($student['gender'] ?? '')));
    $isFemale = in_array($gender, ['f', 'female', 'girl', 'woman'], true);
    $salutation = $isFemale ? 'Ms.' : 'Mr.';
    $guardianPrefix = $isFemale ? 'D/O' : 'S/O';
    $pronounSubject = $isFemale ? 'She' : 'He';
    $pronounObject = $isFemale ? 'her' : 'him';
    $pronounPossessive = $isFemale ? 'her' : 'his';
    $pronounPossessiveCapital = $isFemale ? 'Her' : 'His';

    $admissionYear = '';
    $admissionDateRaw = trim((string)($student['admission_date'] ?? ''));
    if ($admissionDateRaw !== '' && strtotime($admissionDateRaw) !== false) {
        $admissionYear = date('Y', strtotime($admissionDateRaw));
    }
    if ($admissionYear === '' && preg_match('/\b(19|20)\d{2}\b/', $academicSession, $match)) {
        $admissionYear = $match[0];
    }
    if ($admissionYear === '') {
        $admissionYear = date('Y', strtotime($issueDateRaw));
    }

    $leavingYear = '';
    $leavingDateRaw = trim((string)($student['leaving_date'] ?? ($student['date_of_leaving'] ?? $issueDateRaw)));
    if ($leavingDateRaw !== '' && strtotime($leavingDateRaw) !== false) {
        $leavingYear = date('Y', strtotime($leavingDateRaw));
    }
    if ($leavingYear === '') {
        $leavingYear = date('Y', strtotime($issueDateRaw));
    }

    $referenceNo = docNormalizeCertificateNumber($referenceNo);
    if ($referenceNo === '') {
        $referenceNo = docBuildCharacterCertificateNumber($schoolSettings, $issueDateRaw);
    }

    $affiliationLine = 'Affiliated to ' . $schoolBoard;
    if ($schoolAffiliationNo !== '' && $schoolAffiliationNo !== '-') {
        $affiliationLine .= ' (Affiliation No.: ' . $schoolAffiliationNo . ')';
    }

    $contactBits = [];
    if ($schoolPhone !== '') {
        $contactBits[] = 'Phone: ' . $schoolPhone;
    }
    if ($schoolEmail !== '') {
        $contactBits[] = 'Email: ' . $schoolEmail;
    }
    $contactLine = implode(' | ', $contactBits);
    $websiteLine = ($schoolWebsite !== '' && $schoolWebsite !== '-') ? 'Website: ' . $schoolWebsite : '';
    $place = $schoolCity !== '' ? $schoolCity : '';
    if ($place === '' && $schoolAddress !== '') {
        $normalizedAddress = trim(preg_replace('/\s+/', ' ', $schoolAddress));
        $normalizedAddress = trim(preg_replace('/[\s,.-]*\d[\d\s-]*$/', '', $normalizedAddress));
        if ($normalizedAddress !== '') {
            $addressBits = preg_split('/\s+/', $normalizedAddress);
            if (is_array($addressBits) && !empty($addressBits)) {
                $place = trim((string) end($addressBits));
            }
        }

        if ($place === '') {
            $place = trim($schoolAddress);
        }
    }
    if ($place === '') {
        $place = '________________';
    }
    $sealText = $sealYear !== '' ? 'ESTD. ' . $sealYear : 'ESTD. 2005';
    $conductParagraph = $remarks !== ''
        ? $remarks
        : 'During ' . $pronounPossessive . ' stay in this institution, ' . strtolower($pronounSubject) . ' was found to be sincere, disciplined, diligent, and respectful. ' . $pronounSubject . ' maintained good conduct and demonstrated honesty, integrity, and commendable moral character throughout the course of study.';

    $verificationText = implode('|', array_filter([
        $schoolName,
        'Character Certificate',
        $referenceNo,
        $studentName,
        $fatherName,
        $classLabel,
        $academicSession,
        $issueDateDisplay,
    ]));

    ob_start();
    ?>
    <div class="doc-certificate-shell doc-character-certificate">
        <div class="doc-transfer-sheet doc-character-sheet">
            <div class="doc-transfer-watermark"><?php echo docEscape($schoolName); ?></div>
            <div class="doc-transfer-corner doc-transfer-corner-tl"></div>
            <div class="doc-transfer-corner doc-transfer-corner-tr"></div>
            <div class="doc-transfer-corner doc-transfer-corner-bl"></div>
            <div class="doc-transfer-corner doc-transfer-corner-br"></div>

            <div class="doc-transfer-header">
                <div class="doc-transfer-logo-block doc-character-logo-block">
                    <?php if (!empty($logoSrc)): ?>
                        <img src="<?php echo docEscape($logoSrc); ?>" alt="<?php echo docEscape($schoolName); ?>" class="doc-transfer-logo">
                    <?php else: ?>
                        <div class="doc-transfer-logo doc-transfer-logo-placeholder"><?php echo docEscape(strtoupper(substr(preg_replace('/[^A-Za-z]+/', '', $schoolName), 0, 2) ?: 'SC')); ?></div>
                    <?php endif; ?>
                </div>

                <div class="doc-transfer-header-copy">
                    <div class="doc-transfer-school-name"><?php echo docEscape($schoolName); ?></div>
                    <div class="doc-character-affiliation"><span><?php echo docEscape($affiliationLine); ?></span></div>
                    <?php if ($schoolAddress !== ''): ?>
                        <div class="doc-transfer-line"><?php echo docEscape($schoolAddress); ?></div>
                    <?php endif; ?>
                    <?php if ($contactLine !== ''): ?>
                        <div class="doc-transfer-line"><?php echo docEscape($contactLine); ?></div>
                    <?php endif; ?>
                    <?php if ($websiteLine !== ''): ?>
                        <div class="doc-transfer-line"><?php echo docEscape($websiteLine); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="doc-transfer-divider doc-transfer-divider-blue"></div>
            <div class="doc-transfer-title">CHARACTER CERTIFICATE</div>
            <div class="doc-transfer-divider doc-transfer-divider-gold"></div>

            <div class="doc-character-meta-row">
                <div class="doc-character-meta-item">Certificate No.: <strong><?php echo docEscape($referenceNo); ?></strong></div>
                <div class="doc-character-meta-item">Date: <strong><?php echo docEscape($issueDateDisplay); ?></strong></div>
            </div>

            <div class="doc-transfer-declaration doc-character-declaration">
                <p>
                    This is to certify that <strong><?php echo docEscape($salutation . ' ' . $studentName); ?></strong>,
                    <?php echo docEscape($guardianPrefix); ?> <strong><?php echo docEscape($fatherName); ?></strong>,
                    was a bona fide student of <strong><?php echo docEscape($schoolName); ?></strong> from
                    <strong><?php echo docEscape($admissionYear); ?></strong> to <strong><?php echo docEscape($leavingYear); ?></strong>
                    and pursued <strong><?php echo docEscape($classLabel); ?></strong> during the said period.
                </p>
                <p>
                    <?php echo docEscape($conductParagraph); ?>
                </p>
                <p>
                    According to the records of this institution, no disciplinary action or adverse remark has ever been recorded against
                    <?php echo docEscape($pronounObject); ?>. <?php echo docEscape($pronounPossessiveCapital); ?> behavior and relationship with teachers, staff members,
                    and fellow students were consistently satisfactory.
                </p>
                <p>
                    This certificate is issued at the request of the student for whatever purpose it may serve. We wish
                    <?php echo docEscape($pronounObject); ?> every success in future academic, professional, and personal endeavors.
                </p>
            </div>

            <div class="doc-transfer-footer">
                <div class="doc-transfer-place-date">
                    <div><strong>Place:</strong> <?php echo docEscape($place); ?></div>
                    <div><strong>Date:</strong> <?php echo docEscape($issueDateDisplay); ?></div>
                </div>

                <div class="doc-transfer-seal-wrap">
                    <div class="doc-transfer-seal-circle">
                        <span>Official</span>
                        <strong>Seal</strong>
                        <small><?php echo docEscape($sealText); ?></small>
                    </div>
                </div>

                <div class="doc-transfer-signature-block">
                    <?php if (!empty($signatureSrc)): ?>
                        <img src="<?php echo docEscape($signatureSrc); ?>" alt="Principal Signature" class="doc-transfer-signature-image">
                    <?php endif; ?>
                    <div class="doc-transfer-signature-line"></div>
                    <div class="doc-transfer-signature-name"><?php echo docEscape($principalName); ?></div>
                    <div class="doc-transfer-signature-role">Principal</div>
                    <div class="doc-transfer-signature-school"><?php echo docEscape($schoolName); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function docRenderFormPage($documentType, $schoolSettings, $student, $exam, $exams, $examsTableExists, $studentSearch, $issueDate, $remarks, $classes, $sections = [], $mode = 'student', $publishToStudent = 0, $classId = 0, $sectionId = 0, $paperSize = 'A4', $characterGrade = 'very_good', $certificateNo = '') {
    $documentLabel = docTypeLabel($documentType);
    $isCertificateDocument = in_array($documentType, ['transfer_certificate', 'character_certificate'], true);
    $supportsBulkCertificate = in_array($documentType, ['transfer_certificate', 'character_certificate'], true);
    $mode = strtolower(trim((string) $mode));
    if (!in_array($mode, ['student', 'bulk'], true)) {
        $mode = 'student';
    }

    if (!$supportsBulkCertificate) {
        $mode = 'student';
    }

    $studentId = intval($student['student_id'] ?? 0);
    $publishToStudent = intval($publishToStudent) === 1;
    $classId = intval($classId);
    $sectionId = intval($sectionId);
    $paperSize = docNormalizePaperSize($paperSize);
    $characterGrade = docNormalizeCharacterGrade($characterGrade);
    $certificateNo = docNormalizeCertificateNumber($certificateNo);
    if ($documentType === 'transfer_certificate' && $certificateNo === '' && intval($schoolSettings['transfer_certificate_last_no'] ?? 0) > 0) {
        $certificateNo = docBuildTransferCertificateNumber($schoolSettings);
    }
    $currentExamId = intval($exam['exam_id'] ?? 0);
    $baseUrl = docBaseUrl($documentType);
    $isBulkCertificateMode = $supportsBulkCertificate && $mode === 'bulk';
    if ($isBulkCertificateMode) {
        $baseUrl .= '&mode=bulk';
    }

    $pageTitle = $isCertificateDocument ? 'Student Certificate Generator' : 'Student Document Generator';
    $pageSubtitle = $isCertificateDocument
        ? 'Generate transfer and character certificates from one place.'
        : 'Generate admit cards from one place.';

    $submitLabel = 'Generate ' . $documentLabel;
    $remarkLabel = 'Remarks';
    $remarkPlaceholder = 'Optional note to print on the document';

    if ($documentType === 'transfer_certificate') {
        $remarkLabel = 'Reason / Remarks';
        $remarkPlaceholder = 'Reason for leaving or any official note';
    } elseif ($documentType === 'character_certificate') {
        $remarkLabel = 'Conduct Note / Remarks';
        $remarkPlaceholder = 'Optional conduct note to print on the certificate';
    }

    $selectedTypeUrl = function ($type) use ($studentId, $studentSearch, $issueDate, $remarks, $publishToStudent, $mode, $classId, $sectionId, $currentExamId, $paperSize, $characterGrade, $certificateNo) {
        $type = strtolower(trim((string) $type));
        $params = ['type' => $type];

        if ($type === 'admit_card') {
            if ($studentId > 0) {
                $params['student_id'] = $studentId;
            }
            if ($studentSearch !== '') {
                $params['student_search'] = $studentSearch;
            }
            if ($currentExamId > 0) {
                $params['exam_id'] = $currentExamId;
            }
            if ($issueDate !== '') {
                $params['issue_date'] = $issueDate;
            }
            if ($publishToStudent) {
                $params['publish_to_student'] = 1;
            }
        } elseif (in_array($type, ['transfer_certificate', 'character_certificate'], true)) {
            if ($studentId > 0) {
                $params['student_id'] = $studentId;
            }
            if ($studentSearch !== '') {
                $params['student_search'] = $studentSearch;
            }
            if ($issueDate !== '') {
                $params['issue_date'] = $issueDate;
            }
            if ($remarks !== '') {
                $params['remarks'] = $remarks;
            }
            if (in_array($type, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '') {
                $params['certificate_no'] = $certificateNo;
            }
            if ($paperSize !== '') {
                $params['paper_size'] = $paperSize;
            }
            if ($publishToStudent) {
                $params['publish_to_student'] = 1;
            }
            if ($type === 'character_certificate') {
                $params['character_grade'] = $characterGrade;
            }

            if (in_array($type, ['transfer_certificate', 'character_certificate'], true) && $mode === 'bulk') {
                $params['mode'] = 'bulk';
                if ($classId > 0) {
                    $params['class_id'] = $classId;
                }
                if ($sectionId > 0) {
                    $params['section_id'] = $sectionId;
                }
            }
        }

        return APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter($params));
    };

    $selectedModeUrl = function ($nextMode) use ($documentType, $supportsBulkCertificate, $studentId, $studentSearch, $issueDate, $remarks, $publishToStudent, $classId, $sectionId, $characterGrade, $certificateNo) {
        if (!$supportsBulkCertificate) {
            return docBaseUrl($documentType);
        }

        $nextMode = in_array($nextMode, ['student', 'bulk'], true) ? $nextMode : 'student';
        return APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
            'type' => $documentType,
            'mode' => $nextMode,
            'student_id' => $nextMode === 'student' && $studentId > 0 ? $studentId : null,
            'student_search' => $nextMode === 'student' && $studentSearch !== '' ? $studentSearch : null,
            'class_id' => $nextMode === 'bulk' && $classId > 0 ? $classId : null,
            'section_id' => $nextMode === 'bulk' && $sectionId > 0 ? $sectionId : null,
            'issue_date' => $issueDate !== '' ? $issueDate : null,
            'remarks' => $remarks !== '' ? $remarks : null,
            'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
            'publish_to_student' => $publishToStudent ? 1 : null,
            'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
        ]));
    };

    $bulkSummaryLabel = 'Select class';
    if ($isBulkCertificateMode) {
        $resolvedClassName = '';
        foreach ($classes as $classRow) {
            if (intval($classRow['class_id'] ?? 0) === $classId) {
                $resolvedClassName = trim((string)($classRow['class_name'] ?? ''));
                break;
            }
        }

        $resolvedSectionName = '';
        foreach ($sections as $sectionRow) {
            if (intval($sectionRow['section_id'] ?? 0) === $sectionId) {
                $resolvedSectionName = trim((string)($sectionRow['section_name'] ?? ''));
                break;
            }
        }

        $bulkSummaryLabel = trim($resolvedClassName . ($resolvedSectionName !== '' ? ' / ' . $resolvedSectionName : ''));
        if ($bulkSummaryLabel === '') {
            $bulkSummaryLabel = 'Select class';
        }
    }

    include '../../includes/header.php';
    ?>

    <style>
    .doc-switcher {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .doc-switcher a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.4rem 0.8rem;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        text-decoration: none;
        color: #334155;
        background: #fff;
        font-size: 0.85rem;
    }

    .doc-switcher a.active {
        background: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .doc-mode-switch {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .doc-mode-switch a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.4rem 0.8rem;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        text-decoration: none;
        color: #334155;
        background: #fff;
        font-size: 0.85rem;
    }

    .doc-mode-switch a.active {
        background: #16a34a;
        border-color: #16a34a;
        color: #fff;
    }

    .doc-summary {
        background: #ffffff;
        border: 1px solid #dbe3ec;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 16px;
    }

    .doc-summary h5 {
        margin-bottom: 12px;
    }

    .doc-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
    }

    .doc-summary-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
    }

    .doc-summary-label {
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        font-weight: 700;
    }

    .doc-summary-value {
        margin-top: 4px;
        font-weight: 700;
        color: #111827;
        word-break: break-word;
    }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-file-earmark-text"></i> <?php echo docEscape($pageTitle); ?>
                    </h2>
                    <div class="text-muted"><?php echo docEscape($pageSubtitle); ?></div>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="doc-switcher no-print">
        <?php if ($documentType === 'admit_card'): ?>
            <a href="<?php echo docEscape($selectedTypeUrl('admit_card')); ?>" class="<?php echo $documentType === 'admit_card' ? 'active' : ''; ?>">
                <i class="bi bi-card-heading"></i> Admit Card
            </a>
        <?php else: ?>
            <a href="<?php echo docEscape($selectedTypeUrl('transfer_certificate')); ?>" class="<?php echo $documentType === 'transfer_certificate' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> Transfer Certificate
            </a>
            <a href="<?php echo docEscape($selectedTypeUrl('character_certificate')); ?>" class="<?php echo $documentType === 'character_certificate' ? 'active' : ''; ?>">
                <i class="bi bi-award"></i> Character Certificate
            </a>
        <?php endif; ?>
    </div>

    <?php if ($supportsBulkCertificate): ?>
        <div class="doc-mode-switch no-print">
            <a href="<?php echo docEscape($selectedModeUrl('student')); ?>" class="<?php echo $mode === 'student' ? 'active' : ''; ?>">
                <i class="bi bi-person"></i> Student Wise
            </a>
            <a href="<?php echo docEscape($selectedModeUrl('bulk')); ?>" class="<?php echo $mode === 'bulk' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Bulk Wise
            </a>
        </div>
    <?php endif; ?>

    <div class="doc-summary">
        <h5 class="mb-0"><?php echo docEscape($documentLabel); ?></h5>
        <div class="doc-summary-grid mt-3">
            <div class="doc-summary-item">
                <div class="doc-summary-label"><?php echo $documentType === 'admit_card' ? 'Student' : ($isBulkCertificateMode ? 'Selected Class' : 'Selected Student'); ?></div>
                <div class="doc-summary-value">
                    <?php if ($isBulkCertificateMode): ?>
                        <?php echo docEscape($bulkSummaryLabel ?? 'Select class'); ?>
                    <?php else: ?>
                        <?php echo docEscape($studentSearch !== '' ? $studentSearch : 'No student selected'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="doc-summary-item">
                <div class="doc-summary-label">Issue Date</div>
                <div class="doc-summary-value"><?php echo docEscape(docFormatDate($issueDate)); ?></div>
            </div>
            <?php if (in_array($documentType, ['transfer_certificate', 'character_certificate'], true)): ?>
                <div class="doc-summary-item">
                    <div class="doc-summary-label">Certificate No.</div>
                    <div class="doc-summary-value">
                        <?php echo docEscape($certificateNo !== '' ? $certificateNo : 'Not set yet'); ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($documentType === 'character_certificate'): ?>
                <div class="doc-summary-item">
                    <div class="doc-summary-label">Character Grade</div>
                    <div class="doc-summary-value"><?php echo docEscape(docCharacterGradeLabel($characterGrade)); ?></div>
                </div>
            <?php endif; ?>
            <?php if ($documentType === 'admit_card'): ?>
                <div class="doc-summary-item">
                    <div class="doc-summary-label">School Logo</div>
                    <div class="doc-summary-value"><?php echo (!empty($schoolSettings['school_logo']) || !empty($schoolSettings['banner_logo'])) ? 'Uploaded' : 'Not uploaded yet'; ?></div>
                </div>
                <div class="doc-summary-item">
                    <div class="doc-summary-label">Exam Records</div>
                    <div class="doc-summary-value"><?php echo $examsTableExists ? count($exams) . ' available' : 'Not available'; ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (intval($student['student_id'] ?? 0) > 0 && !$isBulkCertificateMode): ?>
        <div class="alert alert-info">
            <strong>Selected student:</strong>
            <?php echo docEscape($student['student_name'] ?? '-'); ?> |
            Adm No: <?php echo docEscape($student['admission_no'] ?? '-'); ?> |
            Class: <?php echo docEscape(trim((($student['class_name'] ?? '-') . ' ' . ($student['section_name'] ?? '')))); ?>
        </div>
    <?php endif; ?>

    <?php if ($documentType === 'admit_card' && !$examsTableExists): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Exam data is not available yet. Please create the exams table first.
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card dashboard-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Generator Form</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="type" value="<?php echo docEscape($documentType); ?>">
                        <input type="hidden" name="mode" value="<?php echo docEscape($mode); ?>">
                        <input type="hidden" name="student_id" id="student_id" value="<?php echo intval($student['student_id'] ?? 0); ?>">
                        <input type="hidden" name="publish_to_student" value="<?php echo $publishToStudent ? '1' : '0'; ?>">

                        <?php if ($isBulkCertificateMode): ?>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Class</label>
                                <select class="form-select" name="class_id" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($classes as $classRow): ?>
                                        <option value="<?php echo intval($classRow['class_id']); ?>" <?php echo intval($classRow['class_id']) === $classId ? 'selected' : ''; ?>>
                                            <?php echo docEscape($classRow['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Section</label>
                                <select class="form-select" name="section_id">
                                    <option value="">All Sections</option>
                                    <?php foreach ($sections as $sectionRow): ?>
                                        <option value="<?php echo intval($sectionRow['section_id']); ?>" <?php echo intval($sectionRow['section_id']) === $sectionId ? 'selected' : ''; ?>>
                                            <?php echo docEscape($sectionRow['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Batch Preview</label>
                                <div class="alert alert-info mb-0 py-2">
                                    <?php echo docEscape($bulkSummaryLabel); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Student Search</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="student_search"
                                    value="<?php echo docEscape($studentSearch); ?>"
                                    placeholder="Admission no, student name, or roll no"
                                    autocomplete="off"
                                    data-student-autocomplete="true"
                                    data-student-autocomplete-fill="student_name"
                                    data-student-autocomplete-skip-submit="true"
                                    data-student-autocomplete-id-target="#student_id"
                                >
                            </div>
                        <?php endif; ?>

                        <?php if ($documentType === 'admit_card'): ?>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Exam</label>
                                <select class="form-select" name="exam_id">
                                    <option value="">-- Select Exam --</option>
                                    <?php foreach ($exams as $examItem): ?>
                                        <option value="<?php echo intval($examItem['exam_id']); ?>" <?php echo intval($exam['exam_id'] ?? 0) === intval($examItem['exam_id']) ? 'selected' : ''; ?>>
                                            <?php echo docEscape($examItem['exam_name']); ?> (<?php echo docEscape(docFormatDate($examItem['exam_date'] ?? '', 'M Y')); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Issue Date</label>
                            <input type="date" class="form-control" name="issue_date" value="<?php echo docEscape($issueDate); ?>">
                        </div>

                        <?php if ($supportsBulkCertificate): ?>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Certificate Number</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="certificate_no"
                                    value="<?php echo docEscape($certificateNo); ?>"
                                    placeholder="<?php echo $documentType === 'transfer_certificate' ? 'TC/2026/001' : 'CC/2026/001'; ?>"
                                    autocomplete="off"
                                >
                                <small class="text-muted">Enter the first number manually. Later numbers will auto-generate.</small>
                            </div>
                        <?php endif; ?>

                        <?php if ($documentType === 'character_certificate'): ?>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Character Grade</label>
                                <select class="form-select" name="character_grade">
                                    <option value="good" <?php echo $characterGrade === 'good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="very_good" <?php echo $characterGrade === 'very_good' ? 'selected' : ''; ?>>Very Good</option>
                                    <option value="excellent" <?php echo $characterGrade === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if ($supportsBulkCertificate): ?>
                            <div class="col-lg-4 col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="publish_to_student" name="publish_to_student" <?php echo $publishToStudent ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="publish_to_student">
                                        Make visible in student portal
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($documentType !== 'admit_card'): ?>
                            <div class="col-12">
                                <label class="form-label"><?php echo docEscape($remarkLabel); ?></label>
                                <textarea class="form-control" name="remarks" rows="3" placeholder="<?php echo docEscape($remarkPlaceholder); ?>"><?php echo docEscape($remarks); ?></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-printer"></i> <?php echo docEscape($submitLabel); ?>
                            </button>
                            <button type="submit" name="download" value="pdf" class="btn btn-danger">
                                <i class="bi bi-download"></i> Download PDF
                            </button>
                            <a href="<?php echo docEscape($baseUrl); ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <?php
}

$allowedTypes = ['admit_card', 'transfer_certificate', 'character_certificate', 'marksheet'];
$documentType = strtolower(trim((string) ($_GET['type'] ?? 'admit_card')));
if (!in_array($documentType, $allowedTypes, true)) {
    $documentType = 'admit_card';
}
$supportsBulkCertificate = in_array($documentType, ['transfer_certificate', 'character_certificate'], true);

$recordId = intval($_GET['record_id'] ?? 0);
$studentId = intval($_GET['student_id'] ?? 0);
$classId = intval($_GET['class_id'] ?? 0);
$sectionId = intval($_GET['section_id'] ?? 0);
$examId = intval($_GET['exam_id'] ?? 0);
$mode = strtolower(trim((string)($_GET['mode'] ?? 'student')));
if (!in_array($mode, ['student', 'bulk'], true)) {
    $mode = 'student';
}
if (!$supportsBulkCertificate) {
    $mode = 'student';
}

$publishToStudent = intval($_GET['publish_to_student'] ?? 0) === 1;
$issueDate = docNormalizeDate($_GET['issue_date'] ?? '', date('Y-m-d'));
$remarks = trim((string) ($_GET['remarks'] ?? ''));
$certificateNo = docNormalizeCertificateNumber($_GET['certificate_no'] ?? ($_GET['reference_no'] ?? ''));
$referenceNo = docNormalizeCertificateNumber($_GET['reference_no'] ?? $certificateNo);
if ($certificateNo === '' && $referenceNo !== '') {
    $certificateNo = $referenceNo;
}
if ($referenceNo === '' && $certificateNo !== '') {
    $referenceNo = $certificateNo;
}
$characterGrade = docNormalizeCharacterGrade($_GET['character_grade'] ?? 'very_good');
$studentSearch = trim((string) ($_GET['student_search'] ?? ''));
$requestDownloadPdf = strtolower(trim((string)($_GET['download'] ?? ''))) === 'pdf';
$paperSize = docNormalizePaperSize($_GET['paper_size'] ?? ($documentType === 'admit_card' ? 'A5' : 'A4'));
if ($documentType === 'transfer_certificate') {
    $paperSize = 'A4';
} elseif ($documentType === 'character_certificate') {
    $paperSize = 'A4';
}

$documentRecord = $recordId > 0 ? studentPortalGetDocument($recordId) : null;

if ($isStudentUser && $recordId <= 0) {
    alertAndRedirect('Open certificates from the Certificates section in your student portal.', APP_URL . '/modules/student/dashboard.php', 'info');
}

if ($recordId > 0 && !$documentRecord) {
    alertAndRedirect('Saved certificate record not found.', docBaseUrl($documentType), 'error');
}

$classes = fetchAll("SELECT class_id, class_name, class_order FROM classes WHERE is_active = 1 ORDER BY class_order, class_name");
$sections = fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name");
$examsTableExists = count(fetchAll("SHOW TABLES LIKE 'exams'")) > 0;
$exams = $examsTableExists
    ? fetchAll("SELECT exam_id, exam_name, exam_type, exam_date, academic_year FROM exams WHERE is_active = 1 ORDER BY exam_date DESC, exam_id DESC")
    : [];

$selectedClassName = '';
foreach ($classes as $classRow) {
    if (intval($classRow['class_id'] ?? 0) === $classId) {
        $selectedClassName = trim((string)($classRow['class_name'] ?? ''));
        break;
    }
}

$selectedSectionName = '';
foreach ($sections as $sectionRow) {
    if (intval($sectionRow['section_id'] ?? 0) === $sectionId) {
        $selectedSectionName = trim((string)($sectionRow['section_name'] ?? ''));
        break;
    }
}

$bulkSummaryLabel = trim($selectedClassName . ($selectedSectionName !== '' ? ' / ' . $selectedSectionName : ''));
if ($bulkSummaryLabel === '') {
    $bulkSummaryLabel = 'Select class';
}

$student = null;
if ($studentId > 0) {
    $studentQuery = "SELECT s.*, c.class_name, sec.section_name
                     FROM students s
                     LEFT JOIN classes c ON s.class_id = c.class_id
                     LEFT JOIN sections sec ON s.section_id = sec.section_id
                     WHERE s.student_id = ?";
    $studentParams = [$studentId];
    $studentTypes = 'i';
    if ($currentSchoolId > 0) {
        $studentQuery .= " AND s.school_id = ?";
        $studentParams[] = $currentSchoolId;
        $studentTypes .= 'i';
    }
    $student = fetchOne($studentQuery, $studentTypes, $studentParams);
}

if (!$student && $mode === 'student' && $studentSearch !== '') {
    $student = docResolveStudentBySearch(
        $studentSearch,
        $documentType === 'admit_card' ? 'Active' : ''
    );
    if ($student) {
        $studentId = intval($student['student_id'] ?? 0);
        $studentSearch = trim((string)($student['admission_no'] ?? ($student['student_name'] ?? $studentSearch)));
    }
}

$exam = null;
if ($examId > 0 && $examsTableExists) {
    $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]);
}

if ($student && $studentSearch === '') {
    $studentSearch = $student['admission_no'] ?? $student['student_name'] ?? '';
}

$backUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
    'type' => $documentType,
    'mode' => $supportsBulkCertificate ? $mode : null,
    'class_id' => $supportsBulkCertificate && $mode === 'bulk' && $classId > 0 ? $classId : null,
    'section_id' => $supportsBulkCertificate && $mode === 'bulk' && $sectionId > 0 ? $sectionId : null,
    'publish_to_student' => $supportsBulkCertificate ? ($publishToStudent ? 1 : null) : null,
    'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
    'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
]));
$refreshUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
    'type' => $documentType,
    'mode' => $supportsBulkCertificate ? $mode : null,
    'student_id' => $studentId > 0 ? $studentId : null,
    'class_id' => $supportsBulkCertificate && $mode === 'bulk' && $classId > 0 ? $classId : null,
    'section_id' => $supportsBulkCertificate && $mode === 'bulk' && $sectionId > 0 ? $sectionId : null,
    'exam_id' => $examId > 0 ? $examId : null,
    'issue_date' => $issueDate !== '' ? $issueDate : null,
    'remarks' => $remarks !== '' ? $remarks : null,
    'student_search' => $studentSearch !== '' ? $studentSearch : null,
    'publish_to_student' => $supportsBulkCertificate ? ($publishToStudent ? 1 : null) : null,
    'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
    'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
]));

$theme = docThemeConfig($documentType);

if ($documentRecord) {
    $documentPayload = studentPortalDecodeDocumentPayload($documentRecord['payload_json'] ?? '');
    $viewDocumentType = strtolower(trim((string)($documentRecord['document_type'] ?? $documentType)));
    if (in_array($viewDocumentType, $allowedTypes, true)) {
        $documentType = $viewDocumentType;
        $theme = docThemeConfig($documentType);
    }

    $payloadSchool = is_array($documentPayload['school'] ?? null) ? $documentPayload['school'] : [];
    if (!empty($payloadSchool)) {
        $schoolSettings = array_merge($schoolSettings, $payloadSchool);
    }

    $payloadStudent = is_array($documentPayload['student'] ?? null) ? $documentPayload['student'] : [];
    if (!empty($payloadStudent)) {
        $student = $payloadStudent;
        $studentId = intval($payloadStudent['student_id'] ?? $documentRecord['student_id'] ?? 0);
    } elseif (intval($documentRecord['student_id'] ?? 0) > 0 && !$student) {
        $studentId = intval($documentRecord['student_id'] ?? 0);
        $studentQuery = "SELECT s.*, c.class_name, sec.section_name
                         FROM students s
                         LEFT JOIN classes c ON s.class_id = c.class_id
                         LEFT JOIN sections sec ON s.section_id = sec.section_id
                         WHERE s.student_id = ?";
        $studentParams = [$studentId];
        $studentTypes = 'i';
        if ($currentSchoolId > 0) {
            $studentQuery .= " AND s.school_id = ?";
            $studentParams[] = $currentSchoolId;
            $studentTypes .= 'i';
        }
        $student = fetchOne($studentQuery, $studentTypes, $studentParams) ?: [];
    }

    $payloadExam = is_array($documentPayload['exam'] ?? null) ? $documentPayload['exam'] : [];
    if (!empty($payloadExam)) {
        $exam = $payloadExam;
        $examId = intval($payloadExam['exam_id'] ?? $documentRecord['exam_id'] ?? 0);
    } elseif (intval($documentRecord['exam_id'] ?? 0) > 0 && !$exam && $examsTableExists) {
        $examId = intval($documentRecord['exam_id'] ?? 0);
        $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]) ?: [];
    }

    $issueDate = docNormalizeDate($documentPayload['issue_date'] ?? ($documentRecord['issue_date'] ?? ''), $issueDate);
    $remarks = trim((string)($documentPayload['remarks'] ?? ($documentRecord['remarks'] ?? '')));
    if ($documentType === 'transfer_certificate') {
        $certificateNo = docNormalizeCertificateNumber($documentPayload['certificate_no'] ?? ($documentRecord['document_title'] ?? $certificateNo));
    }
    if ($documentType === 'character_certificate') {
        $certificateNo = docNormalizeCertificateNumber($documentPayload['certificate_no'] ?? ($documentPayload['reference_no'] ?? ($documentRecord['reference_no'] ?? $certificateNo)));
        $referenceNo = $certificateNo;
        $characterGrade = docNormalizeCharacterGrade($documentPayload['character_grade'] ?? ($documentRecord['character_grade'] ?? $characterGrade));
        if ($certificateNo === '') {
            $certificateNo = docBuildCharacterCertificateNumber($schoolSettings, $issueDate);
            $referenceNo = $certificateNo;
        }
    }
    $studentSearch = trim((string)($documentRecord['document_title'] ?? ($student['student_name'] ?? '')));
    $backUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
        'type' => $documentType,
        'mode' => $supportsBulkCertificate ? $mode : null,
        'class_id' => $supportsBulkCertificate && $mode === 'bulk' && $classId > 0 ? $classId : null,
        'section_id' => $supportsBulkCertificate && $mode === 'bulk' && $sectionId > 0 ? $sectionId : null,
        'publish_to_student' => $supportsBulkCertificate ? ($publishToStudent ? 1 : null) : null,
        'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
        'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
    ]));
    $refreshUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
        'type' => $documentType,
        'mode' => $supportsBulkCertificate ? $mode : null,
        'student_id' => $studentId > 0 ? $studentId : null,
        'class_id' => $supportsBulkCertificate && $mode === 'bulk' && $classId > 0 ? $classId : null,
        'section_id' => $supportsBulkCertificate && $mode === 'bulk' && $sectionId > 0 ? $sectionId : null,
        'exam_id' => $examId > 0 ? $examId : null,
        'issue_date' => $issueDate !== '' ? $issueDate : null,
        'remarks' => $remarks !== '' ? $remarks : null,
        'student_search' => $studentSearch !== '' ? $studentSearch : null,
        'publish_to_student' => $supportsBulkCertificate ? ($publishToStudent ? 1 : null) : null,
        'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
        'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
    ]));

    if ($isStudentUser) {
        $currentStudentId = studentPortalGetCurrentStudentId();
        if (intval($documentRecord['student_id'] ?? 0) !== $currentStudentId || intval($documentRecord['visible_to_student'] ?? 0) !== 1) {
            alertAndRedirect('This certificate is not available in your student portal yet.', APP_URL . '/modules/student/dashboard.php', 'error');
        }

        $backUrl = APP_URL . '/modules/student/certificates.php';
    } else {
        $backUrl = docBaseUrl($documentType) . '&student_id=' . intval($documentRecord['student_id'] ?? 0);
        if ($documentType === 'character_certificate') {
            $backUrl .= '&character_grade=' . urlencode($characterGrade);
        }
        if ($documentType === 'admit_card' && intval($documentRecord['exam_id'] ?? 0) > 0) {
            $backUrl .= '&exam_id=' . intval($documentRecord['exam_id']);
        }
    }

    $refreshUrl = APP_URL . '/modules/reports/student_documents.php?record_id=' . $recordId;
    $printTitle = $documentRecord['document_title'] ?? docTypeLabel($documentType);

    if ($documentType === 'admit_card' && $student && $exam) {
        $admitCardHtml = admitCardRenderDocument([
            [
                'student' => $student,
                'exam' => $exam,
                'schedule_rows' => docGetAdmitCardScheduleRows($student, $exam, $issueDate, $documentPayload),
                'options' => [
                    'issue_date' => $issueDate,
                    'admit_no' => trim((string)($student['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0),
                ],
            ],
        ], [
            'school_settings' => $schoolSettings,
            'page_title' => $printTitle,
            'back_url' => $backUrl,
            'refresh_url' => $refreshUrl,
            'download_url' => APP_URL . '/modules/reports/student_documents.php?record_id=' . $recordId . '&download=pdf',
            'show_toolbar' => true,
            'watermark' => 'ADMIT CARD',
            'paper_size' => $paperSize,
        ]);

        if ($requestDownloadPdf) {
            $downloadName = admitCardBuildDownloadName($student, $exam, 'record');
            $pdfResult = pdfExportDownloadHtml($admitCardHtml, $downloadName);
            if (!empty($pdfResult['success'])) {
                exit();
            }

            alertAndRedirect(
                'PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'),
                $refreshUrl,
                'error'
            );
        }

        echo $admitCardHtml;
        exit();
    } elseif ($documentType === 'marksheet' && $student && $exam) {
        $sheetData = getMarkSheetData($studentId, $examId);
        if (!$sheetData) {
            alertAndRedirect('No marks found for this student and exam.', $backUrl, 'error');
        }

        $marksheetHtml = generateMarkSheetHTML(
            $sheetData['student'],
            $sheetData['exam'],
            $sheetData['marks'],
            $schoolSettings,
            $sheetData['totalMarks'],
            $sheetData['totalMaxMarks'],
            $sheetData['totalPassMarks'],
            $sheetData['percentage'],
            $sheetData['overallGrade'],
            $sheetData['result'],
            !$requestDownloadPdf
        );

        if ($requestDownloadPdf) {
            $downloadName = docBuildDownloadName('marksheet', $student ?: [], 'record');
            $pdfResult = marksheetExportOnePagePdfHtml($marksheetHtml, $downloadName);
            if (!empty($pdfResult['success'])) {
                exit();
            }

            alertAndRedirect(
                'PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'),
                $refreshUrl,
                'error'
            );
        }

        echo $marksheetHtml;
        exit();
    } elseif ($documentType === 'transfer_certificate' && $student) {
        $contentHtml = docRenderTransferCertificateContent($schoolSettings, $student, $issueDate, $remarks, $certificateNo);
    } elseif ($documentType === 'character_certificate' && $student) {
        $contentHtml = docRenderCharacterCertificateContent($schoolSettings, $student, $issueDate, $remarks, $characterGrade, $certificateNo);
    } else {
        alertAndRedirect('Unable to render this certificate.', $backUrl, 'error');
    }

    if ($documentType === 'character_certificate') {
        $paperSize = 'A4';
    }

    $printableDownloadUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
        'record_id' => $recordId > 0 ? $recordId : null,
        'type' => $documentType,
        'student_id' => $studentId > 0 ? $studentId : null,
        'exam_id' => $examId > 0 ? $examId : null,
        'issue_date' => $issueDate !== '' ? $issueDate : null,
        'remarks' => $remarks !== '' ? $remarks : null,
        'student_search' => $studentSearch !== '' ? $studentSearch : null,
        'paper_size' => $paperSize,
        'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
        'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
        'reference_no' => $documentType === 'character_certificate' && $referenceNo !== '' ? $referenceNo : null,
        'download' => 'pdf',
    ]));

    $printableHtml = docWrapPrintablePage(
        $printTitle,
        $schoolSettings['school_name'] ?? APP_NAME,
        $contentHtml,
        $backUrl,
        $refreshUrl,
        isset($_GET['print']) && $_GET['print'] === 'auto',
        $theme,
        $theme['watermark'] ?? docTypeLabel($documentType),
        $printableDownloadUrl,
        $paperSize,
        $documentType === 'transfer_certificate'
            ? docTransferCertificateAllowedPaperSizes()
            : ($documentType === 'character_certificate' ? docCharacterCertificateAllowedPaperSizes() : ['A4', 'A5'])
    );

    if ($requestDownloadPdf) {
        $downloadName = docBuildDownloadName($documentType, $student ?: [], 'record');
        $pdfResult = pdfExportDownloadHtml($printableHtml, $downloadName);
        if (!empty($pdfResult['success'])) {
            exit();
        }

        alertAndRedirect('PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'), $refreshUrl, 'error');
    }

    echo $printableHtml;
    exit();
}

$theme = docThemeConfig($documentType);
$canGenerate = false;
$printTitle = docTypeLabel($documentType);
$contentHtml = '';

if ($documentType === 'admit_card' && $student && $exam) {
    $canGenerate = true;
    $printTitle = 'Admit Card - ' . ($student['student_name'] ?? '');
    $contentHtml = docRenderAdmitCardContent($schoolSettings, $student, $exam, $issueDate);
} elseif ($supportsBulkCertificate && $mode === 'bulk' && $classId > 0) {
    $canGenerate = true;
    $printTitle = docTypeLabel($documentType) . 's - ' . $bulkSummaryLabel;
    $contentHtml = [];
} elseif (($documentType === 'transfer_certificate' || $documentType === 'character_certificate') && $student) {
    $canGenerate = true;
    $printTitle = docTypeLabel($documentType) . ' - ' . ($student['student_name'] ?? '');
    if ($documentType === 'transfer_certificate') {
        $contentHtml = docRenderTransferCertificateContent($schoolSettings, $student, $issueDate, $remarks, $certificateNo);
    } else {
        if ($referenceNo === '') {
            $referenceNo = docBuildCharacterCertificateNumber($schoolSettings, $issueDate);
        }
        $contentHtml = docRenderCharacterCertificateContent($schoolSettings, $student, $issueDate, $remarks, $characterGrade, $referenceNo);
    }
}

if ($canGenerate) {
    if ($supportsBulkCertificate && $mode === 'bulk') {
        $bulkStudents = $documentType === 'transfer_certificate'
            ? docGetTransferCertificateStudents($classId, $sectionId)
            : docGetCharacterCertificateStudents($classId, $sectionId);
        if (empty($bulkStudents)) {
            alertAndRedirect('No active students were found for the selected class.', $backUrl, 'error');
        }

        $printTitle = docTypeLabel($documentType) . 's - ' . $bulkSummaryLabel;
        $contentHtml = [];
        $nextTransferSerial = intval($schoolSettings['transfer_certificate_last_no'] ?? 0) + 1;
        if ($documentType === 'transfer_certificate' && $certificateNo !== '') {
            [, $seedSerial] = docSplitCertificateNumber($certificateNo, docGetTransferCertificatePrefix($schoolSettings));
            if ($seedSerial > 0) {
                $nextTransferSerial = $seedSerial;
            }
        }

        foreach ($bulkStudents as $bulkStudent) {
            if ($documentType === 'transfer_certificate') {
                $bulkCertificateNo = docBuildTransferCertificateNumber($schoolSettings, $nextTransferSerial);
                $nextTransferSerial++;
                $contentHtml[] = docRenderTransferCertificateContent($schoolSettings, $bulkStudent, $issueDate, $remarks, $bulkCertificateNo);
            } else {
                $bulkCertificateNo = docBuildCharacterCertificateNumber($schoolSettings, $issueDate);
                $contentHtml[] = docRenderCharacterCertificateContent($schoolSettings, $bulkStudent, $issueDate, $remarks, $characterGrade, $bulkCertificateNo);
            }

            $bulkPayload = studentPortalBuildDocumentPayload(
                $bulkStudent,
                [],
                $schoolSettings,
                $issueDate,
                $remarks
            );
            $bulkPayload['document_mode'] = 'bulk';
            $bulkPayload['certificate_no'] = $bulkCertificateNo;
            if ($documentType === 'character_certificate') {
                $bulkPayload['character_grade'] = $characterGrade;
                $bulkPayload['reference_no'] = $bulkCertificateNo;
            }
            $bulkPayload['batch'] = [
                'class_id' => $classId,
                'section_id' => $sectionId,
                'class_name' => $selectedClassName,
                'section_name' => $selectedSectionName,
            ];

            studentPortalSaveDocument([
                'student_id' => intval($bulkStudent['student_id'] ?? 0),
                'document_type' => $documentType,
                'document_title' => docTypeLabel($documentType) . ' - ' . ($bulkStudent['student_name'] ?? ''),
                'exam_id' => 0,
                'issue_date' => $issueDate,
                'remarks' => $remarks,
                'visible_to_student' => $publishToStudent ? 1 : 0,
                'payload_json' => json_encode($bulkPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'generated_by' => intval($currentUser['user_id'] ?? 0),
            ]);

            if ($documentType === 'transfer_certificate') {
                docSyncTransferCertificateSequence($schoolSettings, $bulkCertificateNo);
            }
        }

        $paperSize = 'A4';

        $printableDownloadUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
            'type' => $documentType,
            'mode' => 'bulk',
            'class_id' => $classId > 0 ? $classId : null,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'issue_date' => $issueDate !== '' ? $issueDate : null,
            'remarks' => $remarks !== '' ? $remarks : null,
            'publish_to_student' => $publishToStudent ? 1 : null,
            'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
            'certificate_no' => $certificateNo !== '' ? $certificateNo : null,
            'paper_size' => $paperSize,
            'download' => 'pdf',
        ]));

        $printableHtml = docWrapPrintablePage(
            $printTitle,
            $schoolSettings['school_name'] ?? APP_NAME,
            $contentHtml,
            $backUrl,
            $refreshUrl,
            isset($_GET['print']) && $_GET['print'] === 'auto',
            $theme,
            $theme['watermark'] ?? docTypeLabel($documentType),
            $printableDownloadUrl,
            $paperSize,
            docCharacterCertificateAllowedPaperSizes()
        );

        if ($requestDownloadPdf) {
            $downloadName = docBuildDownloadName($documentType, [
                'student_name' => $bulkSummaryLabel,
            ], 'bulk');
            $pdfResult = pdfExportDownloadHtml($printableHtml, $downloadName);
            if (!empty($pdfResult['success'])) {
                exit();
            }

            alertAndRedirect('PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'), $refreshUrl, 'error');
        }

        echo $printableHtml;
        exit();
    }

    $payload = studentPortalBuildDocumentPayload(
        $student,
        $documentType === 'admit_card' ? $exam : [],
        $schoolSettings,
        $issueDate,
        $remarks
    );
    $admitCardScheduleRows = [];
    if ($documentType === 'admit_card') {
        $admitCardScheduleRows = docGetAdmitCardScheduleRows($student, $exam, $issueDate);
        $payload['schedule_rows'] = $admitCardScheduleRows;
    }
    if ($documentType === 'character_certificate') {
        $payload['character_grade'] = $characterGrade;
        $payload['certificate_no'] = $certificateNo;
        $payload['reference_no'] = $referenceNo;
        $paperSize = 'A4';
    }
    if ($documentType === 'transfer_certificate') {
        $payload['certificate_no'] = $certificateNo;
    }

    if ($supportsBulkCertificate && $mode === 'bulk') {
        $payload['document_mode'] = 'bulk';
        $payload['batch'] = [
            'class_id' => $classId,
            'section_id' => $sectionId,
            'class_name' => $selectedClassName,
            'section_name' => $selectedSectionName,
        ];
    }

    $visibleToStudent = ($supportsBulkCertificate && $publishToStudent) ? 1 : 0;
    $savedDocument = studentPortalSaveDocument([
        'student_id' => intval($student['student_id'] ?? 0),
        'document_type' => $documentType,
        'document_title' => $printTitle,
        'exam_id' => $documentType === 'admit_card' ? intval($exam['exam_id'] ?? 0) : 0,
        'issue_date' => $issueDate,
        'remarks' => $remarks,
        'visible_to_student' => $visibleToStudent,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'generated_by' => intval($currentUser['user_id'] ?? 0),
    ]);
    if ($savedDocument && $documentType === 'transfer_certificate' && $certificateNo !== '') {
        docSyncTransferCertificateSequence($schoolSettings, $certificateNo);
    }

    if ($documentType === 'admit_card') {
        $admitCardHtml = admitCardRenderDocument([
            [
                'student' => $student,
                'exam' => $exam,
                'schedule_rows' => $admitCardScheduleRows,
                'options' => [
                    'issue_date' => $issueDate,
                    'admit_no' => trim((string)($student['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0),
                ],
            ],
        ], [
            'school_settings' => $schoolSettings,
            'page_title' => $printTitle,
            'back_url' => $backUrl,
            'refresh_url' => $refreshUrl,
            'download_url' => '',
            'show_toolbar' => true,
            'watermark' => 'ADMIT CARD',
            'paper_size' => $paperSize,
        ]);

        echo $admitCardHtml;
        exit();
    }

    $printableDownloadUrl = APP_URL . '/modules/reports/student_documents.php?' . http_build_query(array_filter([
        'type' => $documentType,
        'student_id' => $studentId > 0 ? $studentId : null,
        'exam_id' => $examId > 0 ? $examId : null,
        'issue_date' => $issueDate !== '' ? $issueDate : null,
        'remarks' => $remarks !== '' ? $remarks : null,
        'student_search' => $studentSearch !== '' ? $studentSearch : null,
        'mode' => $supportsBulkCertificate ? $mode : null,
        'class_id' => $supportsBulkCertificate && $mode === 'bulk' && $classId > 0 ? $classId : null,
        'section_id' => $supportsBulkCertificate && $mode === 'bulk' && $sectionId > 0 ? $sectionId : null,
        'publish_to_student' => $supportsBulkCertificate ? ($publishToStudent ? 1 : null) : null,
        'character_grade' => $documentType === 'character_certificate' ? $characterGrade : null,
        'certificate_no' => in_array($documentType, ['transfer_certificate', 'character_certificate'], true) && $certificateNo !== '' ? $certificateNo : null,
        'paper_size' => $paperSize,
        'download' => 'pdf',
    ]));

    $printableHtml = docWrapPrintablePage(
        $printTitle,
        $schoolSettings['school_name'] ?? APP_NAME,
        $contentHtml,
        $backUrl,
        $refreshUrl,
        isset($_GET['print']) && $_GET['print'] === 'auto',
        $theme,
        $theme['watermark'] ?? docTypeLabel($documentType),
        $printableDownloadUrl,
        $paperSize,
        $documentType === 'transfer_certificate'
            ? docTransferCertificateAllowedPaperSizes()
            : ($documentType === 'character_certificate' ? docCharacterCertificateAllowedPaperSizes() : ['A4', 'A5'])
    );

    if ($requestDownloadPdf) {
        $downloadName = docBuildDownloadName($documentType, $student ?: [], $documentType === 'character_certificate' ? ($mode === 'bulk' ? 'bulk' : 'student') : 'record');
        if ($documentType === 'transfer_certificate') {
            $downloadName = docBuildDownloadName($documentType, $student ?: [], $mode === 'bulk' ? 'bulk' : 'student');
        }
        $pdfResult = pdfExportDownloadHtml($printableHtml, $downloadName);
        if (!empty($pdfResult['success'])) {
            exit();
        }

        alertAndRedirect('PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'), $refreshUrl, 'error');
    }

    echo $printableHtml;
    exit();
}

if ($documentType === 'admit_card' && $recordId <= 0) {
    $redirectUrl = APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter([
        'mode' => intval($classId ?? 0) > 0 ? 'class' : 'student',
        'student_id' => $studentId > 0 ? $studentId : null,
        'student_search' => $studentSearch !== '' ? $studentSearch : null,
        'class_id' => intval($classId ?? 0) > 0 ? $classId : null,
        'section_id' => intval($sectionId ?? 0) > 0 ? $sectionId : null,
        'exam_id' => $examId > 0 ? $examId : null,
        'issue_date' => $issueDate !== '' ? $issueDate : null,
    ]));
    redirect($redirectUrl);
}

if ($documentType === 'marksheet' && $recordId <= 0) {
    $redirectUrl = $isStudentUser
        ? APP_URL . '/modules/student/marksheet.php'
        : APP_URL . '/modules/marks/generate_marksheet.php';
    redirect($redirectUrl);
}

    docRenderFormPage($documentType, $schoolSettings, $student ?: [], $exam ?: [], $exams, $examsTableExists, $studentSearch, $issueDate, $remarks, $classes, $sections, $mode, $publishToStudent, $classId, $sectionId, $paperSize, $characterGrade, $certificateNo);
