<?php
/**
 * Admit Card Generator
 * Student-wise and class-wise admit card preview plus PDF download.
 */

require_once '../../config/config.php';
require_once '../../includes/student_portal.php';
require_once '../../includes/admit_card_renderer.php';
require_once '../../includes/pdf_export.php';

studentPortalEnsureSchema();
requireLogin();
requirePermission('reports', 'view');

$pageTitle = 'Admit Cards';
$schoolSettings = getSchoolSettings();
$currentSchoolId = getCurrentSchoolId();

function admitCardResolveStudentBySearch($search, $schoolId = 0) {
    $search = trim((string) $search);
    if ($search === '') {
        return null;
    }

    $like = '%' . $search . '%';
    $query = "SELECT s.*, c.class_name, c.class_order, sec.section_name
              FROM students s
              LEFT JOIN classes c ON s.class_id = c.class_id
              LEFT JOIN sections sec ON s.section_id = sec.section_id
              WHERE (s.admission_no = ? OR s.roll_no = ? OR s.student_name LIKE ?)";
    $params = [$search, $search, $like];
    $types = 'sss';

    if (intval($schoolId) > 0) {
        $query .= " AND s.school_id = ?";
        $params[] = intval($schoolId);
        $types .= 'i';
    }

    $query .= " ORDER BY
                    CASE WHEN s.admission_no = ? THEN 0 ELSE 1 END,
                    CASE WHEN s.roll_no = ? THEN 0 ELSE 1 END,
                    s.student_name ASC
                LIMIT 1";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';

    return fetchOne($query, $types, $params);
}

function admitCardNormalizeMode($mode) {
    $mode = strtolower(trim((string) $mode));
    return in_array($mode, ['student', 'class'], true) ? $mode : 'student';
}

function admitCardNormalizeDateInput($value, $fallback = '') {
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

if (!function_exists('admitCardProcessSignatureUpload')) {
    function admitCardProcessSignatureUpload(array $file, string $existingFilename, int $maxWidth = 600, int $maxHeight = 240): array {
        $existingFilename = trim($existingFilename);

        if (!isset($file['error']) || intval($file['error']) === UPLOAD_ERR_NO_FILE) {
            return [
                'changed' => false,
                'filename' => $existingFilename,
                'error' => '',
            ];
        }

        if (intval($file['error']) !== UPLOAD_ERR_OK) {
            return [
                'changed' => false,
                'filename' => $existingFilename,
                'error' => 'Upload failed with error code ' . intval($file['error']) . '.',
            ];
        }

        if (!ensureDirectoryExists(SIGNATURE_PATH)) {
            return [
                'changed' => false,
                'filename' => $existingFilename,
                'error' => 'Unable to prepare the signature upload folder.',
            ];
        }

        $uploaded = uploadImage($file, SIGNATURE_PATH, $maxWidth, $maxHeight);
        if ($uploaded === false) {
            return [
                'changed' => false,
                'filename' => $existingFilename,
                'error' => 'Please upload a JPG or PNG signature image.',
            ];
        }

        if ($existingFilename !== '' && $existingFilename !== $uploaded) {
            deleteFile(SIGNATURE_PATH . $existingFilename);
        }

        return [
            'changed' => true,
            'filename' => $uploaded,
            'error' => '',
        ];
    }
}

$mode = admitCardNormalizeMode($_GET['mode'] ?? 'student');
$studentId = intval($_GET['student_id'] ?? 0);
$classId = intval($_GET['class_id'] ?? 0);
$sectionId = intval($_GET['section_id'] ?? 0);
$examId = intval($_GET['exam_id'] ?? 0);
$issueDate = admitCardNormalizeDateInput($_GET['issue_date'] ?? '', date('Y-m-d'));
$paperSize = admitCardNormalizePaperSize($_GET['paper_size'] ?? 'A5');
$studentSearch = trim((string)($_GET['student_search'] ?? ''));
$publishToStudent = intval($_GET['publish_to_student'] ?? 0) === 1;
$downloadPdf = strtolower(trim((string)($_GET['download'] ?? ''))) === 'pdf';

$classes = fetchAll("SELECT class_id, class_name, class_order FROM classes WHERE is_active = 1 ORDER BY class_order, class_name");
$sections = fetchAll("SELECT section_id, section_name FROM sections WHERE is_active = 1 ORDER BY section_name");
$examsTableExists = count(fetchAll("SHOW TABLES LIKE 'exams'")) > 0;
$exams = $examsTableExists
    ? fetchAll("SELECT exam_id, exam_name, exam_type, exam_date, academic_year FROM exams WHERE is_active = 1 ORDER BY exam_date DESC, exam_id DESC")
    : [];
$currentUser = getCurrentUser();
$canManageSignatures = in_array(($currentUser['role'] ?? ''), ['super_admin', 'admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature_upload_form'])) {
    if (!$canManageSignatures) {
        alertAndRedirect(
            'You do not have permission to update admit card signatures.',
            APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter($_GET)),
            'error'
        );
    }

    $teacherFilename = trim((string)($schoolSettings['class_teacher_signature'] ?? ($schoolSettings['teacher_signature'] ?? '')));
    $principalFilename = trim((string)($schoolSettings['principal_signature'] ?? ''));
    $messages = [];
    $changed = false;

    $teacherUpload = admitCardProcessSignatureUpload($_FILES['teacher_signature'] ?? [], $teacherFilename, 620, 240);
    if (!empty($teacherUpload['error'])) {
        $messages[] = 'Teacher signature: ' . $teacherUpload['error'];
    }
    if (!empty($teacherUpload['changed'])) {
        $teacherFilename = $teacherUpload['filename'];
        $changed = true;
        $messages[] = 'Teacher signature uploaded.';
    }

    $principalUpload = admitCardProcessSignatureUpload($_FILES['principal_signature'] ?? [], $principalFilename, 620, 240);
    if (!empty($principalUpload['error'])) {
        $messages[] = 'Principal signature: ' . $principalUpload['error'];
    }
    if (!empty($principalUpload['changed'])) {
        $principalFilename = $principalUpload['filename'];
        $changed = true;
        $messages[] = 'Principal signature uploaded.';
    }

    if (!$changed) {
        $message = !empty($messages)
            ? implode(' ', $messages)
            : 'Please choose a teacher or principal signature image to upload.';
        alertAndRedirect(
            $message,
            APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter($_GET)),
            !empty($messages) ? 'error' : 'warning'
        );
    }

    $settingId = intval($schoolSettings['setting_id'] ?? 0);
    if ($settingId <= 0) {
        alertAndRedirect(
            'Admit card signatures were uploaded, but the school settings record could not be found.',
            APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter($_GET)),
            'error'
        );
    }

    $result = executeQuery(
        "UPDATE school_settings SET teacher_signature = ?, class_teacher_signature = ?, principal_signature = ?, updated_at = NOW() WHERE setting_id = ?",
        'sssi',
        [$teacherFilename, $teacherFilename, $principalFilename, $settingId]
    );

    if ($result === false) {
        alertAndRedirect(
            'Signatures were uploaded, but saving the settings failed.',
            APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter($_GET)),
            'warning'
        );
    }

    if (function_exists('logActivity') && $currentUser && !empty($currentUser['user_id'])) {
        logActivity(
            intval($currentUser['user_id']),
            'Update Settings',
            'Reports',
            'Updated admit card teacher and principal signatures'
        );
    }

    $finalMessage = implode(' ', $messages);
    if ($finalMessage === '') {
        $finalMessage = 'Admit card signatures updated successfully.';
    }
    $hasErrors = false;
    $hasSuccess = false;
    foreach ($messages as $message) {
        if (strpos($message, ':') !== false) {
            $hasErrors = true;
        }
        if (substr($message, -9) === 'uploaded.') {
            $hasSuccess = true;
        }
    }
    $alertType = ($hasErrors && $hasSuccess) ? 'warning' : 'success';

    alertAndRedirect(
        $finalMessage,
        APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter($_GET)),
        $alertType
    );
}

if ($examId <= 0 && !empty($exams)) {
    $examId = intval($exams[0]['exam_id']);
}

$student = null;
if ($studentId > 0) {
    $studentQuery = "SELECT s.*, c.class_name, c.class_order, sec.section_name
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
    $student = admitCardResolveStudentBySearch($studentSearch, $currentSchoolId);
    if ($student) {
        $studentId = intval($student['student_id'] ?? 0);
        $studentSearch = $student['admission_no'] ?? $student['student_name'] ?? $studentSearch;
    }
}

$exam = null;
if ($examId > 0 && $examsTableExists) {
    $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]);
}

if (!$exam && !empty($exams)) {
    $exam = $exams[0];
    $examId = intval($exam['exam_id'] ?? 0);
}

$cards = [];
$generationTitle = 'Admit Card';
$generationMessage = '';
$saveVisibility = $publishToStudent ? 1 : 0;
$backUrl = APP_URL . '/modules/reports/admit_cards.php?mode=' . urlencode($mode);

if ($mode === 'student' && $student && $exam) {
    $scheduleRows = admitCardGetScheduleRows($student, $exam, [
        'start_date' => $exam['exam_date'] ?? '',
        'issue_date' => $issueDate,
    ]);

    $cards[] = [
        'student' => $student,
        'exam' => $exam,
        'schedule_rows' => $scheduleRows,
        'options' => [
            'issue_date' => $issueDate,
            'admit_no' => trim((string)($student['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0),
        ],
    ];

    $generationTitle = 'Admit Card - ' . ($student['student_name'] ?? '');
} elseif ($mode === 'class' && $classId > 0 && $exam) {
    $students = admitCardGetStudentsForClass($classId, $sectionId, 'Active');

    if (!empty($students)) {
        $previewStudent = $students[0];
        $classLabel = trim((string)($previewStudent['class_name'] ?? ''));
        if ($sectionId > 0) {
            $sectionLabel = fetchOne("SELECT section_name FROM sections WHERE section_id = ?", 'i', [$sectionId]);
            if (!empty($sectionLabel['section_name'])) {
                $classLabel = trim($classLabel . ' / ' . $sectionLabel['section_name']);
            }
        }

        $scheduleCache = [];

        foreach ($students as $studentRow) {
            $scheduleKey = intval($studentRow['class_id'] ?? 0) . ':' . intval($studentRow['section_id'] ?? 0) . ':' . intval($exam['exam_id'] ?? 0);
            if (!isset($scheduleCache[$scheduleKey])) {
                $scheduleCache[$scheduleKey] = admitCardGetScheduleRows($studentRow, $exam, [
                    'start_date' => $exam['exam_date'] ?? '',
                    'issue_date' => $issueDate,
                ]);
            }

            $cards[] = [
                'student' => $studentRow,
                'exam' => $exam,
                'schedule_rows' => $scheduleCache[$scheduleKey],
                'options' => [
                    'issue_date' => $issueDate,
                    'admit_no' => trim((string)($studentRow['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0),
                ],
            ];
        }

        $generationTitle = 'Admit Cards - ' . trim((string)($previewStudent['class_name'] ?? 'Class'));
        if ($sectionId > 0) {
            $sectionLabel = fetchOne("SELECT section_name FROM sections WHERE section_id = ?", 'i', [$sectionId]);
            if (!empty($sectionLabel['section_name'])) {
                $generationTitle .= ' / ' . $sectionLabel['section_name'];
            }
        }
    }
}

$canGenerate = !empty($cards);

if ($canGenerate) {
    foreach ($cards as $card) {
        $studentRow = $card['student'] ?? [];
        $documentTitle = admitCardBuildDownloadName($studentRow, $exam, $mode);
        $payload = studentPortalBuildDocumentPayload($studentRow, $exam, $schoolSettings, $issueDate, '');
        $payload['schedule_rows'] = is_array($card['schedule_rows'] ?? null) ? $card['schedule_rows'] : [];
        studentPortalSaveDocument([
            'student_id' => intval($studentRow['student_id'] ?? 0),
            'document_type' => 'admit_card',
            'document_title' => 'Admit Card - ' . ($studentRow['student_name'] ?? ''),
            'exam_id' => intval($exam['exam_id'] ?? 0),
            'issue_date' => $issueDate,
            'remarks' => '',
            'visible_to_student' => $saveVisibility,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'generated_by' => intval(getCurrentUser()['user_id'] ?? 0),
        ]);
    }

    $renderOptions = [
        'school_settings' => $schoolSettings,
        'page_title' => $generationTitle,
        'back_url' => $backUrl,
        'refresh_url' => APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter([
            'mode' => $mode,
            'student_id' => $studentId > 0 ? $studentId : null,
            'student_search' => $studentSearch !== '' ? $studentSearch : null,
            'class_id' => $classId > 0 ? $classId : null,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'exam_id' => $examId > 0 ? $examId : null,
            'issue_date' => $issueDate,
            'paper_size' => $paperSize,
            'publish_to_student' => $publishToStudent ? 1 : null,
        ])),
        'download_url' => APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter([
            'mode' => $mode,
            'student_id' => $studentId > 0 ? $studentId : null,
            'student_search' => $studentSearch !== '' ? $studentSearch : null,
            'class_id' => $classId > 0 ? $classId : null,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'exam_id' => $examId > 0 ? $examId : null,
            'issue_date' => $issueDate,
            'paper_size' => $paperSize,
            'publish_to_student' => $publishToStudent ? 1 : null,
            'download' => 'pdf',
        ])),
        'show_toolbar' => true,
        'watermark' => 'ADMIT CARD',
    ];

    $html = admitCardRenderDocument($cards, $renderOptions);

    if ($downloadPdf) {
        $downloadName = admitCardBuildDownloadName($student ?: ($cards[0]['student'] ?? []), $exam, $mode);
        $pdfResult = pdfExportDownloadHtml($html, $downloadName);
        if (!empty($pdfResult['success'])) {
            exit();
        }

        $failureBackUrl = APP_URL . '/modules/reports/admit_cards.php?' . http_build_query(array_filter([
            'mode' => $mode,
            'student_id' => $studentId > 0 ? $studentId : null,
            'student_search' => $studentSearch !== '' ? $studentSearch : null,
            'class_id' => $classId > 0 ? $classId : null,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'exam_id' => $examId > 0 ? $examId : null,
            'issue_date' => $issueDate,
            'paper_size' => $paperSize,
            'publish_to_student' => $publishToStudent ? 1 : null,
        ]));

        alertAndRedirect(
            'PDF download failed: ' . ($pdfResult['message'] ?? 'Unknown error'),
            $failureBackUrl,
            'error'
        );
    }

    echo $html;
    exit();
}

$teacherSignaturePreview = getSchoolSignatureSrc($schoolSettings['class_teacher_signature'] ?? ($schoolSettings['teacher_signature'] ?? ''));
$principalSignaturePreview = getSchoolSignatureSrc($schoolSettings['principal_signature'] ?? '');

include '../../includes/header.php';
?>

<style>
    .admit-mode-switch {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .admit-mode-switch a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.45rem 0.9rem;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .admit-mode-switch a.active {
        background: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .admit-signature-manager {
        border: 1px solid #dbe3ef;
    }

    .admit-signature-manager .card-header {
        padding: 0.65rem 0.9rem;
    }

    .admit-signature-manager .card-header h5 {
        font-size: 1rem;
    }

    .admit-signature-manager .card-header small {
        font-size: 0.78rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .admit-signature-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        align-items: start;
    }

    .admit-signature-item {
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f8fbff;
        padding: 10px 12px;
    }

    .admit-signature-item-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.88rem;
        font-weight: 700;
        margin-bottom: 6px;
        color: #0f172a;
    }

    .admit-signature-preview {
        min-height: 58px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        background: #fff;
        padding: 8px;
        margin-bottom: 8px;
    }

    .admit-signature-preview img {
        max-width: 100%;
        max-height: 44px;
        object-fit: contain;
        display: block;
    }

    .admit-signature-placeholder {
        text-align: center;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .admit-signature-help {
        font-size: 0.76rem;
        color: #64748b;
        margin-top: 4px;
        line-height: 1.25;
    }

    .admit-signature-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .admit-signature-actions .btn {
        padding: 0.35rem 0.7rem;
        font-size: 0.84rem;
    }

    @media (max-width: 767px) {
        .admit-signature-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="bi bi-card-heading"></i> Admit Card Generator</h2>
                <div class="text-muted">Create student-wise or class-wise admit cards and download them as PDF.</div>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Reports</a>
            </div>
        </div>
    </div>
</div>

<div class="admit-mode-switch no-print">
    <a href="<?php echo APP_URL; ?>/modules/reports/admit_cards.php?mode=student" class="<?php echo $mode === 'student' ? 'active' : ''; ?>">
        <i class="bi bi-person"></i> Student Wise
    </a>
    <a href="<?php echo APP_URL; ?>/modules/reports/admit_cards.php?mode=class" class="<?php echo $mode === 'class' ? 'active' : ''; ?>">
        <i class="bi bi-people"></i> Class Wise
    </a>
</div>

<div class="card dashboard-card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Generator Form</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">

            <div class="col-lg-4 col-md-6">
                <label class="form-label">Exam</label>
                <select name="exam_id" class="form-select">
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams as $examItem): ?>
                        <option value="<?php echo intval($examItem['exam_id']); ?>" <?php echo intval($examItem['exam_id']) === intval($examId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($examItem['exam_name']); ?>
                            (<?php echo htmlspecialchars(admitCardFormatDate($examItem['exam_date'] ?? '', 'M Y')); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                $routineLinkParams = array_filter([
                    'exam_id' => $examId > 0 ? $examId : null,
                    'class_id' => $mode === 'class' && $classId > 0
                        ? $classId
                        : (intval($student['class_id'] ?? 0) > 0 ? intval($student['class_id'] ?? 0) : null),
                    'section_id' => $mode === 'class' && $sectionId > 0
                        ? $sectionId
                        : (intval($student['section_id'] ?? 0) > 0 ? intval($student['section_id'] ?? 0) : null),
                ]);
                $routineLink = APP_URL . '/modules/marks/exam_routine.php';
                if (!empty($routineLinkParams)) {
                    $routineLink .= '?' . http_build_query($routineLinkParams);
                }
                ?>
                <a href="<?php echo htmlspecialchars($routineLink); ?>" class="btn btn-link btn-sm p-0">
                    + Manage Exam Routine
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <label class="form-label">Issue Date</label>
                <input type="date" name="issue_date" class="form-control" value="<?php echo htmlspecialchars($issueDate); ?>">
            </div>

            <div class="col-lg-4 col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="publish_to_student" name="publish_to_student" <?php echo $publishToStudent ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="publish_to_student">
                        Make visible in student portal
                    </label>
                </div>
            </div>

            <?php if ($mode === 'student'): ?>
                <div class="col-md-8">
                    <label class="form-label">Student Search</label>
                    <input type="hidden" name="student_id" id="student_id" value="<?php echo intval($student['student_id'] ?? 0); ?>">
                    <input
                        type="text"
                        class="form-control"
                        name="student_search"
                        value="<?php echo htmlspecialchars($studentSearch); ?>"
                        placeholder="Admission no, name, or roll no"
                        autocomplete="off"
                        data-student-autocomplete="true"
                        data-student-autocomplete-fill="admission_no"
                        data-student-autocomplete-skip-submit="true"
                        data-student-autocomplete-id-target="#student_id"
                    >
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info mb-0">
                        <strong>Selected student:</strong><br>
                        <?php echo htmlspecialchars($student['student_name'] ?? 'Select a student'); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo intval($class['class_id']); ?>" <?php echo intval($class['class_id']) === intval($classId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo intval($section['section_id']); ?>" <?php echo intval($section['section_id']) === intval($sectionId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info mb-0">
                        <strong>Mode:</strong> Class wise<br>
                        Admit cards will be generated for every active student in the selected class.
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-12 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-eye"></i> Generate Preview
                </button>
                <button type="submit" name="download" value="pdf" class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Download PDF
                </button>
                <a href="<?php echo APP_URL; ?>/modules/reports/admit_cards.php?mode=<?php echo htmlspecialchars($mode); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($canManageSignatures): ?>
    <div class="card dashboard-card mt-3 admit-signature-manager no-print">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-pen"></i> Admit Card Signature Setup</h5>
            <small>Upload or replace the teacher and principal signature images used on admit cards.</small>
        </div>
        <div class="card-body py-2">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" enctype="multipart/form-data">
                <input type="hidden" name="signature_upload_form" value="1">

                <div class="admit-signature-row">
                    <div class="admit-signature-item">
                        <div class="admit-signature-item-label"><i class="bi bi-pencil-square"></i> Teacher / Class Teacher</div>
                        <div class="admit-signature-preview">
                            <?php if (!empty($teacherSignaturePreview)): ?>
                                <img src="<?php echo htmlspecialchars($teacherSignaturePreview); ?>" alt="Teacher Signature Preview">
                            <?php else: ?>
                                <div class="admit-signature-placeholder">No teacher signature uploaded yet.</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" class="form-control form-control-sm" name="teacher_signature" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <div class="admit-signature-help">Use a small white-background or transparent PNG.</div>
                    </div>

                    <div class="admit-signature-item">
                        <div class="admit-signature-item-label"><i class="bi bi-pen-fill"></i> Principal</div>
                        <div class="admit-signature-preview">
                            <?php if (!empty($principalSignaturePreview)): ?>
                                <img src="<?php echo htmlspecialchars($principalSignaturePreview); ?>" alt="Principal Signature Preview">
                            <?php else: ?>
                                <div class="admit-signature-placeholder">No principal signature uploaded yet.</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" class="form-control form-control-sm" name="principal_signature" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <div class="admit-signature-help">Upload a neat signature image for the admit card footer.</div>
                    </div>
                </div>

                <div class="admit-signature-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Save Signatures
                    </button>
                    <a href="<?php echo APP_URL; ?>/modules/reports/admit_cards.php?<?php echo htmlspecialchars(http_build_query(array_filter($_GET))); ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info no-print mt-3">
        <i class="bi bi-info-circle"></i> Signature uploads are available to Super Admin and Admin users only.
    </div>
<?php endif; ?>

<?php if ($exam && !$canGenerate): ?>
    <div class="alert alert-warning mt-3">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $mode === 'student'
            ? 'Please select a student to generate the admit card.'
            : 'Please select a class that has active students.'; ?>
    </div>
<?php endif; ?>

<?php if (!$examsTableExists): ?>
    <div class="alert alert-warning mt-3">
        <i class="bi bi-exclamation-triangle"></i> Exam table is not available. Please create exams before generating admit cards.
    </div>
<?php endif; ?>

<?php
include '../../includes/footer.php';
