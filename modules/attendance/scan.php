<?php
/**
 * Attendance QR Scan Page
 * Teachers scan the student QR code to mark attendance for the current day.
 */

require_once '../../config/config.php';

requireLogin();
requireRolePermissionForSchool('attendance_scan', 'view');

if (function_exists('ensureAttendanceSchema')) {
    ensureAttendanceSchema();
}

$pageTitle = 'Scan Attendance';
$currentUser = getCurrentUser();
$currentSchoolId = getCurrentSchoolId();
$schoolSettings = function_exists('getSchoolSettings') ? getSchoolSettings() : [];
$attendanceContext = function_exists('attendanceGetScanContext')
    ? attendanceGetScanContext($schoolSettings)
    : [
        'mode' => 'daily',
        'period_no' => 0,
        'period_label' => 'Daily Attendance',
        'period_start' => null,
        'period_end' => null,
    ];
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$scanMessage = '';
$scanMessageType = '';
$student = null;
$attendanceRow = null;
$attendanceStatus = 'Present';
$attendanceDate = date('Y-m-d');
$showScannerPanel = false;

function attendanceScanNormalizeStatus($status) {
    $status = ucfirst(strtolower(trim((string) $status)));
    $allowed = ['Present', 'Absent', 'Late', 'Half day'];
    if ($status === 'Half day') {
        $status = 'Half Day';
    }

    return in_array($status, ['Present', 'Absent', 'Late', 'Half Day'], true) ? $status : 'Present';
}

function attendanceScanNormalizeMobileNumber($mobile) {
    $digits = preg_replace('/\D+/', '', (string) $mobile);
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }

    return strlen($digits) === 10 ? $digits : '';
}

function attendanceScanLogSmsDispatch($phone, $message, $status, $currentUser, $errorMessage = '') {
    $tableCheck = fetchAll("SHOW TABLES LIKE 'sms_logs'");
    if (count($tableCheck) === 0) {
        return;
    }

    $sentBy = intval($currentUser['user_id'] ?? 0);
    $status = strtolower(trim((string) $status)) === 'failed' ? 'Failed' : 'Sent';

    if ($status === 'Sent') {
        executeQuery(
            "INSERT INTO sms_logs (phone_number, message, status, sent_by, sent_at)
             VALUES (?, ?, 'Sent', ?, NOW())",
            'ssi',
            [$phone, $message, $sentBy]
        );
    } else {
        executeQuery(
            "INSERT INTO sms_logs (phone_number, message, status, error_message, sent_by, sent_at)
             VALUES (?, ?, 'Failed', ?, ?, NOW())",
            'sssi',
            [$phone, $message, $errorMessage, $sentBy]
        );
    }
}

function attendanceScanSendAbsentSms($student, $attendanceDate, $currentUser, array $schoolSettings = [], array $attendanceContext = []) {
    if (intval($schoolSettings['attendance_auto_alert_enabled'] ?? 1) !== 1) {
        return [
            'success' => true,
            'sent' => false,
            'skipped' => true,
            'message' => 'Auto alert is disabled for this school.',
        ];
    }

    if (intval($student['attendance_auto_alert_disabled'] ?? 0) === 1) {
        return [
            'success' => true,
            'sent' => false,
            'skipped' => true,
            'message' => 'Auto alert is disabled for this student.',
        ];
    }

    $mobile = attendanceScanNormalizeMobileNumber($student['contact_no'] ?? '');
    if ($mobile === '') {
        return [
            'success' => false,
            'message' => 'No valid registered mobile number found for this student.',
        ];
    }

    $message = function_exists('attendanceBuildAbsentSmsMessage')
        ? attendanceBuildAbsentSmsMessage($student, $attendanceDate, $schoolSettings, $attendanceContext)
        : '';
    if ($message === '') {
        $schoolName = trim((string)($schoolSettings['school_name'] ?? APP_NAME));
        $studentName = trim((string)($student['student_name'] ?? 'Student'));
        $admissionNo = trim((string)($student['admission_no'] ?? ''));
        $rollNo = trim((string)($student['roll_no'] ?? ''));
        $fatherName = trim((string)($student['father_name'] ?? ''));
        $className = trim((string)($student['class_name'] ?? ''));
        $sectionName = trim((string)($student['section_name'] ?? ''));
        $classDisplay = trim($className . ($sectionName !== '' ? ' - ' . $sectionName : ''));
        if ($classDisplay === '') {
            $classDisplay = 'N/A';
        }
        $studentDetails = implode('; ', array_filter([
            'Name: ' . $studentName,
            'Adm No.: ' . ($admissionNo !== '' ? $admissionNo : 'N/A'),
            'Roll No.: ' . ($rollNo !== '' ? $rollNo : 'N/A'),
            'Class: ' . $classDisplay,
            'Father: ' . ($fatherName !== '' ? $fatherName : 'N/A'),
        ]));
        $dateLabel = date('d M Y', strtotime($attendanceDate));
        $periodMode = strtolower(trim((string)($attendanceContext['mode'] ?? 'daily'))) === 'period';
        $periodLabel = trim((string)($attendanceContext['period_label'] ?? ''));

        $message = 'Dear Parent, ' . $studentDetails;
        if ($periodMode && $periodLabel !== '') {
            $message .= ' was marked ABSENT in ' . $periodLabel . ' on ' . $dateLabel . ' at ' . $schoolName . '.';
        } else {
            $message .= ' was marked ABSENT on ' . $dateLabel . ' at ' . $schoolName . '.';
        }
        $message .= ' Please contact the school office if this needs correction.';
    }

    if (!function_exists('sendSMSViaFirebase')) {
        return [
            'success' => false,
            'message' => 'SMS gateway helper is not available.',
        ];
    }

    $smsResult = sendSMSViaFirebase($mobile, $message);
    $smsSuccess = is_array($smsResult) && !empty($smsResult['success']);
    if ($smsSuccess) {
        attendanceScanLogSmsDispatch($mobile, $message, 'Sent', $currentUser);
        if (function_exists('logActivity')) {
            logActivity(
                intval($currentUser['user_id'] ?? 0),
                'Attendance SMS Sent',
                'attendance',
                'Sent absent SMS to ' . $mobile . ' for student #' . intval($student['student_id'] ?? 0) . '.'
            );
        }

        return [
            'success' => true,
            'sent' => true,
            'skipped' => false,
            'message' => 'Absence SMS sent successfully.',
        ];
    }

    $errorMessage = is_array($smsResult) && !empty($smsResult['message'])
        ? (string) $smsResult['message']
        : 'Failed to send SMS.';

    attendanceScanLogSmsDispatch($mobile, $message, 'Failed', $currentUser, $errorMessage);
    if (function_exists('logActivity')) {
        logActivity(
            intval($currentUser['user_id'] ?? 0),
            'Attendance SMS Failed',
            'attendance',
            'Failed to send absent SMS to ' . $mobile . ' for student #' . intval($student['student_id'] ?? 0) . '. ' . $errorMessage
        );
    }

    return [
        'success' => false,
        'sent' => false,
        'skipped' => false,
        'message' => $errorMessage,
    ];
}

function attendanceScanSave($student, $status, $qrToken, $currentUser, array $attendanceContext = []) {
    $studentId = intval($student['student_id'] ?? 0);
    $schoolId = intval($student['school_id'] ?? 0);
    if ($schoolId <= 0 && function_exists('getCurrentSchoolId')) {
        $schoolId = intval(getCurrentSchoolId());
    }

    $status = attendanceScanNormalizeStatus($status);
    $markedBy = intval($currentUser['user_id'] ?? 0);
    $attendanceDate = date('Y-m-d');
    $attendancePeriodNo = intval($attendanceContext['period_no'] ?? 0);
    $existingAttendance = fetchOne(
        "SELECT status
         FROM student_attendance
         WHERE school_id = ? AND student_id = ? AND attendance_date = ? AND attendance_period_no = ? LIMIT 1",
        'iisi',
        [$schoolId, $studentId, $attendanceDate, $attendancePeriodNo]
    );

    $result = executeQuery(
        "INSERT INTO student_attendance (
            school_id, student_id, attendance_date, attendance_period_no, status, marked_by, qr_token, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            attendance_period_no = VALUES(attendance_period_no),
            status = VALUES(status),
            marked_by = VALUES(marked_by),
            qr_token = VALUES(qr_token),
            updated_at = NOW()",
        'iisisis',
        [$schoolId, $studentId, $attendanceDate, $attendancePeriodNo, $status, $markedBy, $qrToken]
    );

    return [
        'success' => $result !== false,
        'previous_status' => trim((string)($existingAttendance['status'] ?? '')),
        'current_status' => $status,
        'attendance_date' => $attendanceDate,
        'attendance_period_no' => $attendancePeriodNo,
    ];
}

if ($token !== '') {
    $decodedToken = json_decode(decryptAppValue($token), true);
    if (is_array($decodedToken) && !empty($decodedToken['student_id'])) {
        $studentId = intval($decodedToken['student_id']);
        $student = fetchOne(
            "SELECT s.*, c.class_name, sec.section_name
             FROM students s
             LEFT JOIN classes c ON s.class_id = c.class_id
             LEFT JOIN sections sec ON s.section_id = sec.section_id
             WHERE s.student_id = ? LIMIT 1",
            'i',
            [$studentId]
        );

        if ($student) {
            $studentSchoolId = intval($student['school_id'] ?? 0);
            $tokenSchoolId = intval($decodedToken['school_id'] ?? 0);

            if ($currentSchoolId > 0 && $studentSchoolId > 0 && $studentSchoolId !== $currentSchoolId) {
                $student = null;
                $scanMessage = 'This student does not belong to your school.';
                $scanMessageType = 'danger';
            } elseif ($tokenSchoolId > 0 && $studentSchoolId > 0 && $tokenSchoolId !== $studentSchoolId) {
                $student = null;
                $scanMessage = 'Attendance token is not valid for this student.';
                $scanMessageType = 'danger';
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_status'])) {
                    $attendanceStatus = attendanceScanNormalizeStatus($_POST['attendance_status']);
                    $saveResult = attendanceScanSave($student, $attendanceStatus, $token, $currentUser, $attendanceContext);
                    if (!empty($saveResult['success'])) {
                        $scanMessage = 'Attendance updated to ' . $attendanceStatus . ' successfully.';
                        $scanMessageType = 'success';

                        if ($attendanceStatus === 'Absent') {
                            $previousStatus = strtolower(trim((string)($saveResult['previous_status'] ?? '')));
                            if ($previousStatus !== 'absent') {
                                $smsResult = attendanceScanSendAbsentSms(
                                    $student,
                                    $saveResult['attendance_date'] ?? date('Y-m-d'),
                                    $currentUser,
                                    $schoolSettings,
                                    $attendanceContext
                                );
                                if (!empty($smsResult['sent'])) {
                                    $scanMessage .= ' Absence SMS sent to the registered mobile number.';
                                } elseif (!empty($smsResult['skipped'])) {
                                    $scanMessage .= ' ' . ($smsResult['message'] ?? 'Auto alert skipped.');
                                } else {
                                    $scanMessage .= ' SMS could not be sent: ' . ($smsResult['message'] ?? 'Unknown SMS error');
                                    $scanMessageType = 'warning';
                                }
                            }
                        }
                    } else {
                        $scanMessage = 'Failed to update attendance. Please try again.';
                        $scanMessageType = 'danger';
                    }
                } else {
                    $attendanceStatus = 'Present';
                    $saveResult = attendanceScanSave($student, $attendanceStatus, $token, $currentUser, $attendanceContext);
                    if (!empty($saveResult['success'])) {
                        $scanMessage = 'Attendance marked as Present for today.';
                        $scanMessageType = 'success';
                    } else {
                        $scanMessage = 'Failed to mark attendance. Please try again.';
                        $scanMessageType = 'danger';
                    }
                }

                $attendanceRow = fetchOne(
                    "SELECT * FROM student_attendance
                     WHERE school_id = ? AND student_id = ? AND attendance_date = ? AND attendance_period_no = ? LIMIT 1",
                    'iisi',
                    [$studentSchoolId, $studentId, $attendanceDate, intval($attendanceContext['period_no'] ?? 0)]
                );

                if (!empty($attendanceRow['status'])) {
                    $attendanceStatus = trim((string) $attendanceRow['status']);
                }
            }
        } else {
            $scanMessage = 'Student record not found.';
            $scanMessageType = 'danger';
        }
    } else {
        $scanMessage = 'Invalid attendance QR token.';
        $scanMessageType = 'danger';
    }
} else {
    $scanMessage = 'Scan a student QR code to mark attendance.';
    $scanMessageType = 'info';
}

$showScannerPanel = $student === null;

include '../../includes/header.php';
?>

<style>
    .attendance-scan-wrap {
        max-width: 980px;
        margin: 0 auto;
    }

    .attendance-student-photo {
        width: 100%;
        max-width: 180px;
        aspect-ratio: 3 / 4;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #dbe3ec;
        background: #f8fafc;
    }

    .attendance-badge {
        font-size: 0.95rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
    }

    .attendance-actions .btn {
        min-width: 130px;
    }

    .attendance-meta {
        font-size: 0.95rem;
        color: #52606d;
    }

    .attendance-scanner-shell {
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        border-radius: 18px;
        color: #fff;
        overflow: hidden;
    }

    .attendance-scanner-video {
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        background: #000;
    }

    .attendance-scanner-status {
        min-height: 48px;
    }
</style>

<div class="attendance-scan-wrap py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-qr-code-scan"></i> Attendance Scan</h3>
            <div class="text-muted">Scan the QR on the student ID card and update attendance using the school's current scan mode.</div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="badge bg-primary attendance-badge">
                    <i class="bi bi-diagram-3"></i>
                    <?php echo htmlspecialchars(($attendanceContext['mode'] ?? 'daily') === 'period' ? 'Every class / period' : 'One-time per day'); ?>
                </span>
                <?php if (($attendanceContext['mode'] ?? 'daily') === 'period'): ?>
                    <span class="badge bg-info text-dark attendance-badge">
                        <i class="bi bi-clock"></i>
                        <?php echo htmlspecialchars(($attendanceContext['period_label'] ?? 'Period 1') . ' | ' . ($attendanceContext['period_start'] ?? '--') . ' - ' . ($attendanceContext['period_end'] ?? '--')); ?>
                    </span>
                <?php endif; ?>
                <span class="badge <?php echo !empty($schoolSettings['attendance_auto_alert_enabled']) ? 'bg-success' : 'bg-secondary'; ?> attendance-badge">
                    <i class="bi bi-bell"></i>
                    Auto alert: <?php echo !empty($schoolSettings['attendance_auto_alert_enabled']) ? 'On' : 'Off'; ?>
                </span>
            </div>
        </div>
        <a href="<?php echo APP_URL; ?>/modules/students/" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Students
        </a>
    </div>

    <?php if (!empty($scanMessage)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($scanMessageType ?: 'info'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($scanMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($showScannerPanel): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-lg-7">
                        <div class="attendance-scanner-shell h-100 p-3 p-md-4">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <h4 class="mb-1"><i class="bi bi-camera-video"></i> Open Camera Scanner</h4>
                                    <div class="text-white-50">Point the phone camera at the student QR code. The page will open the student record automatically.</div>
                                </div>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars(($attendanceContext['mode'] ?? 'daily') === 'period' ? 'Period scan' : 'Daily scan'); ?>
                                </span>
                            </div>

                            <div class="border rounded-4 overflow-hidden bg-black mb-3">
                                <video id="attendanceScannerVideo" class="attendance-scanner-video" autoplay muted playsinline></video>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-success" id="attendanceStartScanner">
                                    <i class="bi bi-camera-video"></i> Start Camera Scan
                                </button>
                                <button type="button" class="btn btn-outline-light" id="attendanceStopScanner" disabled>
                                    <i class="bi bi-stop-circle"></i> Stop Camera
                                </button>
                                <button type="button" class="btn btn-outline-info" id="attendanceOpenCapture">
                                    <i class="bi bi-camera"></i> Open Mobile Camera
                                </button>
                                <button type="button" class="btn btn-outline-warning" id="attendancePasteTokenBtn">
                                    <i class="bi bi-keyboard"></i> Paste QR Token
                                </button>
                            </div>

                            <input type="file" id="attendanceQrCapture" accept="image/*" capture="environment" class="d-none">

                            <div class="attendance-scanner-status alert alert-light mt-3 mb-0" id="attendanceScannerStatus" role="status">
                                Ready to open the camera.
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="p-3 p-md-4 h-100 bg-light">
                            <h5 class="mb-3"><i class="bi bi-info-circle"></i> Scanner Options</h5>
                            <div class="mb-3">
                                <label for="attendanceManualToken" class="form-label">Manual QR Token / URL</label>
                                <textarea id="attendanceManualToken" class="form-control" rows="5" placeholder="Paste the QR token or full scan URL here"></textarea>
                            </div>
                            <button type="button" class="btn btn-primary w-100 mb-3" id="attendanceUseManualToken">
                                <i class="bi bi-arrow-right-circle"></i> Open Student Record
                            </button>
                            <div class="border rounded-3 p-3 bg-white small text-muted">
                                Camera access works best on HTTPS or localhost. If your browser does not support live QR detection, use the mobile camera button or paste the token manually.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($student): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="row g-4 align-items-start">
                    <div class="col-md-3 text-center">
                        <img
                            src="<?php echo htmlspecialchars(!empty($student['photo']) ? getStudentPhotoSrc($student['photo']) : getStudentPhotoSrc()); ?>"
                            alt="<?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?>"
                            class="attendance-student-photo mb-3"
                        >
                        <div class="attendance-meta">
                            Admission No:
                            <strong><?php echo htmlspecialchars($student['admission_no'] ?? 'N/A'); ?></strong>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></h4>
                                <div class="attendance-meta">
                                    Class:
                                    <strong><?php echo htmlspecialchars(trim(($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? '')) ?: 'N/A'); ?></strong>
                                </div>
                            </div>

                            <span class="badge bg-success attendance-badge">
                                Today: <?php echo htmlspecialchars($attendanceStatus); ?>
                            </span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="text-muted small">Father's Name</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="text-muted small">Address</div>
                                    <div class="fw-semibold"><?php echo nl2br(htmlspecialchars($student['address'] ?? 'N/A')); ?></div>
                                </div>
                            </div>
                        </div>

                        <form method="post" class="attendance-actions d-flex flex-wrap gap-2">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <button type="submit" name="attendance_status" value="Present" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Present
                            </button>
                            <button type="submit" name="attendance_status" value="Late" class="btn btn-warning">
                                <i class="bi bi-clock"></i> Late
                            </button>
                            <button type="submit" name="attendance_status" value="Half Day" class="btn btn-info">
                                <i class="bi bi-scissors"></i> Half Day
                            </button>
                            <button type="submit" name="attendance_status" value="Absent" class="btn btn-danger">
                                <i class="bi bi-x-circle"></i> Absent
                            </button>
                        </form>

                        <div class="mt-3 small text-muted">
                            Scanned by <?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'staff'); ?>
                            on <?php echo date('d M Y'); ?>.
                        </div>
                        <?php if (!empty($student['attendance_auto_alert_disabled'])): ?>
                            <div class="mt-2 small text-warning fw-semibold">
                                <i class="bi bi-bell-slash"></i> Auto alert is disabled for this student.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <i class="bi bi-qr-code-scan" style="font-size: 3rem; color: #0d6efd;"></i>
                <h4 class="mt-3">Ready to Scan</h4>
                <p class="text-muted mb-0">
                    Use the student ID card QR code to open this page. Once the student record loads, attendance is marked for today.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
(function() {
    const video = document.getElementById('attendanceScannerVideo');
    const startBtn = document.getElementById('attendanceStartScanner');
    const stopBtn = document.getElementById('attendanceStopScanner');
    const captureBtn = document.getElementById('attendanceOpenCapture');
    const captureInput = document.getElementById('attendanceQrCapture');
    const pasteTokenBtn = document.getElementById('attendancePasteTokenBtn');
    const useManualTokenBtn = document.getElementById('attendanceUseManualToken');
    const manualToken = document.getElementById('attendanceManualToken');
    const statusBox = document.getElementById('attendanceScannerStatus');

    if (!video || !startBtn || !stopBtn || !captureBtn || !captureInput || !useManualTokenBtn || !manualToken || !statusBox) {
        return;
    }

    const scanPageUrl = <?php echo json_encode(APP_URL . '/modules/attendance/scan.php'); ?>;
    let cameraStream = null;
    let detector = null;
    let scanning = false;
    let frameHandle = null;
    const scanCanvas = document.createElement('canvas');
    const scanCanvasContext = scanCanvas.getContext('2d', { willReadFrequently: true });

    function setStatus(message, type = 'info') {
        statusBox.className = 'attendance-scanner-status alert alert-' + type + ' mt-3 mb-0';
        statusBox.textContent = message;
    }

    function normalizeScannedValue(value) {
        const raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        try {
            const url = new URL(raw, window.location.href);
            const tokenFromUrl = url.searchParams.get('token');
            if (tokenFromUrl) {
                return tokenFromUrl.trim();
            }
        } catch (error) {
            // Not a URL, treat it as a raw token.
        }

        return raw;
    }

    function openToken(value) {
        const token = normalizeScannedValue(value);
        if (!token) {
            setStatus('No QR token was found in the scanned data.', 'warning');
            return;
        }

        if (typeof window.showLoading === 'function') {
            window.showLoading();
        }

        window.location.href = scanPageUrl + '?token=' + encodeURIComponent(token);
    }

    async function ensureDetector() {
        if (detector) {
            return detector;
        }

        if ('BarcodeDetector' in window) {
            try {
                detector = new BarcodeDetector({ formats: ['qr_code'] });
                return detector;
            } catch (error) {
                detector = null;
            }
        }

        return null;
    }

    function decodeQrFromCanvas(canvas, context) {
        if (!canvas || !context || typeof window.jsQR !== 'function') {
            return null;
        }

        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        return window.jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: 'attemptBoth'
        });
    }

    function stopCamera() {
        scanning = false;
        if (frameHandle) {
            cancelAnimationFrame(frameHandle);
            frameHandle = null;
        }

        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }

        video.srcObject = null;
        startBtn.disabled = false;
        stopBtn.disabled = true;
    }

    async function detectFromVideo() {
        if (!scanning || !detector || !video || video.readyState < 2) {
            if (scanning) {
                frameHandle = requestAnimationFrame(detectFromVideo);
            }
            return;
        }

        try {
            const codes = await detector.detect(video);
            if (codes && codes.length) {
                stopCamera();
                openToken(codes[0].rawValue || '');
                return;
            }
        } catch (error) {
            // Keep scanning; the detector can momentarily fail while the video warms up.
        }

        if (!detector && typeof window.jsQR === 'function' && video.videoWidth > 0 && video.videoHeight > 0) {
            scanCanvas.width = video.videoWidth;
            scanCanvas.height = video.videoHeight;
            scanCanvasContext.drawImage(video, 0, 0, scanCanvas.width, scanCanvas.height);
            const qrCode = decodeQrFromCanvas(scanCanvas, scanCanvasContext);
            if (qrCode && qrCode.data) {
                stopCamera();
                openToken(qrCode.data);
                return;
            }
        }

        if (scanning) {
            frameHandle = requestAnimationFrame(detectFromVideo);
        }
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('This browser does not support camera access. Use the mobile camera or paste the QR token manually.', 'danger');
            return;
        }

        startBtn.disabled = true;
        setStatus('Starting camera...', 'info');

        try {
            const constraints = {
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };

            cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = cameraStream;
            await video.play();

            scanning = true;
            stopBtn.disabled = false;

            const qrDetector = await ensureDetector();
            if (!qrDetector && typeof window.jsQR !== 'function') {
                stopCamera();
                setStatus('Live QR detection is not supported here. Use the mobile camera button or paste the token.', 'warning');
                return;
            }

            setStatus('Camera is active. Point it at the student QR code.', 'success');
            frameHandle = requestAnimationFrame(detectFromVideo);
        } catch (error) {
            stopCamera();
            setStatus('Unable to open the camera. Please allow camera permission or use the mobile camera button.', 'danger');
        }
    }

    async function decodeCapturedFile(file) {
        if (!file) {
            return;
        }

        try {
            if ('BarcodeDetector' in window) {
                const qrDetector = await ensureDetector();
                if (qrDetector) {
                    const bitmap = await createImageBitmap(file);
                    const codes = await qrDetector.detect(bitmap);
                    if (codes && codes.length) {
                        openToken(codes[0].rawValue || '');
                        return;
                    }
                }
            }

            if (typeof window.jsQR === 'function') {
                const imageUrl = URL.createObjectURL(file);
                const image = new Image();
                image.onload = function() {
                    scanCanvas.width = image.naturalWidth || image.width;
                    scanCanvas.height = image.naturalHeight || image.height;
                    scanCanvasContext.drawImage(image, 0, 0, scanCanvas.width, scanCanvas.height);
                    const qrCode = decodeQrFromCanvas(scanCanvas, scanCanvasContext);
                    URL.revokeObjectURL(imageUrl);
                    if (qrCode && qrCode.data) {
                        openToken(qrCode.data);
                        return;
                    }
                    setStatus('No QR code was detected in that image. Try again with the code centered.', 'warning');
                };
                image.onerror = function() {
                    URL.revokeObjectURL(imageUrl);
                    setStatus('Could not read that QR image. Please try the mobile camera or paste the token.', 'danger');
                };
                image.src = imageUrl;
                return;
            }

            setStatus('Your browser cannot decode QR images directly. Paste the token manually instead.', 'warning');
        } catch (error) {
            setStatus('Could not read that QR image. Please try the mobile camera or paste the token.', 'danger');
        }
    }

    startBtn.addEventListener('click', startCamera);
    stopBtn.addEventListener('click', function() {
        stopCamera();
        setStatus('Camera stopped.', 'secondary');
    });

    captureBtn.addEventListener('click', function() {
        captureInput.click();
    });

    captureInput.addEventListener('change', function(event) {
        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
        if (file) {
            decodeCapturedFile(file);
        }
        event.target.value = '';
    });

    pasteTokenBtn.addEventListener('click', function() {
        manualToken.focus();
        manualToken.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    useManualTokenBtn.addEventListener('click', function() {
        const token = normalizeScannedValue(manualToken.value || '');
        if (!token) {
            setStatus('Paste a QR token or full scan URL first.', 'warning');
            manualToken.focus();
            return;
        }

        openToken(token);
    });

    window.addEventListener('beforeunload', stopCamera);

    window.setTimeout(function() {
        startCamera();
    }, 300);
})();
</script>

<?php include '../../includes/footer.php'; ?>
