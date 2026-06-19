<?php
/**
 * Admit card layout and data helpers.
 */

if (!function_exists('admitCardEscape')) {
    function admitCardEscape($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admitCardClassLabel')) {
    function admitCardClassLabel(array $student = []) {
        $className = trim((string)($student['class_name'] ?? ''));
        $sectionName = trim((string)($student['section_name'] ?? ''));

        if ($className === '' && $sectionName === '') {
            return '-';
        }

        if ($className !== '' && $sectionName !== '') {
            return $className . ' / ' . $sectionName;
        }

        return $className !== '' ? $className : $sectionName;
    }
}

if (!function_exists('admitCardFormatDate')) {
    function admitCardFormatDate($value, $format = 'd/m/Y') {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '-' : date($format, $timestamp);
    }
}

if (!function_exists('admitCardFormatTime')) {
    function admitCardFormatTime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return strtolower(date('g.iA', $timestamp));
    }
}

if (!function_exists('admitCardBuildTimeLabel')) {
    function admitCardBuildTimeLabel($startTime, $endTime = '') {
        $startLabel = admitCardFormatTime($startTime);
        $endLabel = admitCardFormatTime($endTime);

        if ($startLabel !== '' && $endLabel !== '') {
            return $startLabel . ' to ' . $endLabel;
        }

        if ($startLabel !== '') {
            return $startLabel;
        }

        if ($endLabel !== '') {
            return $endLabel;
        }

        return '-';
    }
}

if (!function_exists('admitCardNormalizeDisplayValue')) {
    function admitCardNormalizeDisplayValue($value, $fallback = '-') {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('admitCardNormalizePaperSize')) {
    function admitCardNormalizePaperSize($value = 'A4') {
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['A4', 'A5'], true) ? $value : 'A4';
    }
}

if (!function_exists('admitCardNormalizePaperOrientation')) {
    function admitCardNormalizePaperOrientation($value = '', $paperSize = 'A4') {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['portrait', 'landscape'], true)) {
            return $value;
        }

        return admitCardNormalizePaperSize($paperSize) === 'A5' ? 'landscape' : 'portrait';
    }
}

if (!function_exists('admitCardGetSettingValue')) {
    function admitCardGetSettingValue(array $settings, array $keys, $fallback = '-') {
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
}

if (!function_exists('admitCardDayLabel')) {
    function admitCardDayLabel($value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '-' : date('D', $timestamp);
    }
}

if (!function_exists('admitCardCalculateReportingTime')) {
    function admitCardCalculateReportingTime($startTime, $minutesBefore = 30) {
        $startTime = trim((string) $startTime);
        if ($startTime === '') {
            return '-';
        }

        $timestamp = strtotime($startTime);
        if ($timestamp === false) {
            return '-';
        }

        $reporting = strtotime('-' . max(0, intval($minutesBefore)) . ' minutes', $timestamp);
        if ($reporting === false) {
            return '-';
        }

        return date('g:i A', $reporting);
    }
}

if (!function_exists('admitCardRenderField')) {
    function admitCardRenderField($label, $value, $wide = false) {
        ob_start();
        ?>
        <div class="admit-field<?php echo $wide ? ' admit-field-wide' : ''; ?>">
            <div class="admit-field-label"><?php echo admitCardEscape($label); ?></div>
            <div class="admit-field-value"><?php echo admitCardEscape($value); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('admitCardRenderSignatureBlock')) {
    function admitCardRenderSignatureBlock($label, $name = '') {
        ob_start();
        ?>
        <div class="admit-signature-block">
            <div class="admit-signature-line"></div>
            <div class="admit-signature-label"><?php echo admitCardEscape($label); ?></div>
            <?php if (trim((string) $name) !== ''): ?>
                <div class="admit-signature-name"><?php echo admitCardEscape($name); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('admitCardGetStudentsForClass')) {
    function admitCardGetStudentsForClass($classId, $sectionId = 0, $status = 'Active') {
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
                  WHERE s.class_id = ?";

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

        if ($status !== '') {
            $query .= " AND s.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $query .= " ORDER BY s.roll_no, s.student_name, s.student_id";
        return fetchAll($query, $types, $params);
    }
}

if (!function_exists('admitCardEnsureRoutineSchema')) {
    function admitCardEnsureRoutineSchema() {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $conn = getDbConnection();
        if (!$conn) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `exam_routines` (
            `routine_id` int(11) NOT NULL AUTO_INCREMENT,
            `exam_id` int(11) NOT NULL,
            `class_id` int(11) NOT NULL,
            `section_id` int(11) NOT NULL DEFAULT 0,
            `subject_id` int(11) NOT NULL,
            `exam_date` date NOT NULL,
            `start_time` time NOT NULL,
            `end_time` time NOT NULL,
            `room_no` varchar(50) DEFAULT NULL,
            `notes` varchar(255) DEFAULT NULL,
            `display_order` int(11) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_by` int(11) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`routine_id`),
            UNIQUE KEY `uk_exam_class_section_subject` (`exam_id`, `class_id`, `section_id`, `subject_id`),
            KEY `idx_exam` (`exam_id`),
            KEY `idx_class_section` (`class_id`, `section_id`),
            KEY `idx_exam_date` (`exam_date`),
            KEY `idx_active` (`is_active`),
            KEY `idx_display_order` (`display_order`),
            KEY `idx_subject` (`subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('Failed to ensure exam_routines table: ' . $conn->error);
            return false;
        }

        return true;
    }
}

if (!function_exists('admitCardFetchRoutineRows')) {
    function admitCardFetchRoutineRows($examId, $classId, $sectionId = 0) {
        $examId = intval($examId);
        $classId = intval($classId);
        $sectionId = intval($sectionId);

        if ($examId <= 0 || $classId <= 0) {
            return [];
        }

        if (!admitCardEnsureRoutineSchema()) {
            return [];
        }

        $query = "SELECT er.*, sub.subject_name, sub.subject_code, c.class_name, sec.section_name
                  FROM exam_routines er
                  INNER JOIN subjects sub ON sub.subject_id = er.subject_id
                  INNER JOIN classes c ON c.class_id = er.class_id
                  LEFT JOIN sections sec ON sec.section_id = er.section_id
                  WHERE er.exam_id = ? AND er.class_id = ? AND er.is_active = 1";

        $params = [$examId, $classId];
        $types = 'ii';

        if ($sectionId > 0) {
            $query .= " AND er.section_id IN (0, ?)
                        ORDER BY CASE WHEN er.section_id = ? THEN 0 ELSE 1 END,
                                 er.display_order ASC,
                                 er.exam_date ASC,
                                 er.start_time ASC,
                                 sub.subject_name ASC";
            $params[] = $sectionId;
            $params[] = $sectionId;
            $types .= 'ii';
        } else {
            $query .= " AND er.section_id = 0
                        ORDER BY er.display_order ASC,
                                 er.exam_date ASC,
                                 er.start_time ASC,
                                 sub.subject_name ASC";
        }

        $rows = fetchAll($query, $types, $params);
        if (empty($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $subjectId = intval($row['subject_id'] ?? 0);
            if ($subjectId <= 0 || isset($normalized[$subjectId])) {
                continue;
            }

            $normalized[$subjectId] = [
                'routine_id' => intval($row['routine_id'] ?? 0),
                'exam_id' => intval($row['exam_id'] ?? 0),
                'class_id' => intval($row['class_id'] ?? 0),
                'section_id' => intval($row['section_id'] ?? 0),
                'subject_id' => $subjectId,
                'sno' => count($normalized) + 1,
                'subject_name' => trim((string)($row['subject_name'] ?? '-')),
                'subject_code' => trim((string)($row['subject_code'] ?? '')),
                'class_label' => admitCardClassLabel([
                    'class_name' => $row['class_name'] ?? '',
                    'section_name' => $row['section_name'] ?? '',
                ]),
                'exam_date' => admitCardFormatDate($row['exam_date'] ?? '', 'd M Y'),
                'day' => admitCardDayLabel($row['exam_date'] ?? ''),
                'reporting_time' => admitCardCalculateReportingTime($row['start_time'] ?? '', 30),
                'exam_timing' => admitCardBuildTimeLabel($row['start_time'] ?? '', $row['end_time'] ?? ''),
                'exam_time' => admitCardBuildTimeLabel($row['start_time'] ?? '', $row['end_time'] ?? ''),
                'room_no' => trim((string)($row['room_no'] ?? '')),
                'invigilator_signature' => trim((string)($row['auth_sign'] ?? '')),
                'notes' => trim((string)($row['notes'] ?? '')),
            ];
        }

        return array_values($normalized);
    }
}

if (!function_exists('admitCardGetScheduleRows')) {
    function admitCardGetScheduleRows(array $student = [], array $exam = [], array $options = []) {
        $classId = intval($student['class_id'] ?? ($options['class_id'] ?? 0));
        $sectionId = intval($student['section_id'] ?? ($options['section_id'] ?? 0));

        $routineRows = admitCardFetchRoutineRows($exam['exam_id'] ?? 0, $classId, $sectionId);
        if (!empty($routineRows)) {
            foreach ($routineRows as $index => $row) {
                $routineRows[$index]['sno'] = $index + 1;
            }
            return $routineRows;
        }

        $subjectRows = [];

        $tableExists = count(fetchAll("SHOW TABLES LIKE 'class_subjects'")) > 0;
        if ($classId > 0 && $tableExists) {
            $subjectRows = fetchAll(
                "SELECT cs.id, cs.class_id, sub.subject_id, sub.subject_name, sub.subject_code
                 FROM class_subjects cs
                 INNER JOIN subjects sub ON sub.subject_id = cs.subject_id
                 WHERE cs.class_id = ?
                 ORDER BY cs.id ASC, sub.subject_name ASC",
                'i',
                [$classId]
            );
        }

        if (empty($subjectRows)) {
            $subjectRows = fetchAll(
                "SELECT subject_id, subject_name, subject_code
                 FROM subjects
                 WHERE is_active = 1
                 ORDER BY subject_name ASC"
            );
        }

        $startDate = trim((string)($options['start_date'] ?? ($exam['exam_date'] ?? '')));
        if ($startDate === '' || strtotime($startDate) === false) {
            $startDate = date('Y-m-d');
        }
        $startTimestamp = strtotime($startDate);
        if ($startTimestamp === false) {
            $startTimestamp = time();
        }

        $timeStart = trim((string)($options['time_start'] ?? '09:00'));
        $timeEnd = trim((string)($options['time_end'] ?? '12:00'));
        $timeLabel = trim((string)($options['time_label'] ?? ''));
        if ($timeLabel === '') {
            $timeLabel = admitCardBuildTimeLabel($timeStart, $timeEnd);
        }
        $reportingTimeLabel = admitCardCalculateReportingTime($timeStart, 30);

        $classLabel = trim((string)($options['class_label'] ?? admitCardClassLabel($student)));
        $rows = [];
        foreach ($subjectRows as $index => $subjectRow) {
            $rowDate = date('d/m/Y', strtotime('+' . $index . ' day', $startTimestamp));
            $rows[] = [
                'sno' => $index + 1,
                'subject_name' => trim((string)($subjectRow['subject_name'] ?? '-')),
                'subject_code' => trim((string)($subjectRow['subject_code'] ?? '')),
                'class_label' => $classLabel,
                'exam_date' => date('d M Y', strtotime('+' . $index . ' day', $startTimestamp)),
                'day' => date('D', strtotime('+' . $index . ' day', $startTimestamp)),
                'reporting_time' => $reportingTimeLabel,
                'exam_timing' => $timeLabel,
                'exam_time' => $timeLabel,
                'room_no' => '-',
                'invigilator_signature' => '',
            ];
        }

        return $rows;
    }
}

if (!function_exists('admitCardBuildDownloadName')) {
    function admitCardBuildDownloadName(array $student = [], array $exam = [], $scope = 'student') {
        $parts = ['admit-card'];
        if ($scope !== '') {
            $parts[] = $scope;
        }

        $studentName = trim((string)($student['student_name'] ?? ''));
        $admissionNo = trim((string)($student['admission_no'] ?? ''));
        $examName = trim((string)($exam['exam_name'] ?? ''));

        foreach ([$studentName, $admissionNo, $examName] as $part) {
            $part = preg_replace('/[^\w\s.-]+/u', '', $part);
            $part = preg_replace('/\s+/', '_', $part);
            $part = trim($part, '._-');
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode('_', $parts);
    }
}

if (!function_exists('admitCardRenderSheetFragment')) {
    function admitCardRenderSheetFragment(array $schoolSettings, array $student, array $exam, array $scheduleRows = [], array $options = []) {
        $schoolName = trim((string)($schoolSettings['school_name'] ?? APP_NAME));
        $schoolDisplayName = $schoolName !== '' ? $schoolName : APP_NAME;
        $schoolAddress = trim((string)($schoolSettings['school_address'] ?? ''));
        $schoolPhone = trim((string)($schoolSettings['school_phone'] ?? ''));
        $schoolEmail = trim((string)($schoolSettings['school_email'] ?? ''));
        $schoolAffiliationNo = admitCardGetSettingValue($schoolSettings, ['affiliation_no', 'affiliation_number'], '-');
        $schoolCode = admitCardGetSettingValue($schoolSettings, ['school_code', 'udise_code'], '-');
        $schoolWebsite = admitCardGetSettingValue($schoolSettings, ['school_website', 'website'], '-');
        $logoSrc = getSchoolLogoSrc($schoolSettings['school_logo'] ?? '', $schoolSettings['banner_logo'] ?? '');
        $studentPhotoFilename = trim((string)($student['photo'] ?? ''));
        $studentPhoto = '';
        if ($studentPhotoFilename !== '') {
            $studentPhotoPath = STUDENT_PHOTO_PATH . $studentPhotoFilename;
            $defaultAvatarPath = BASE_PATH . '/assets/images/default-avatar.png';
            $defaultAvatarHash = is_file($defaultAvatarPath) ? @md5_file($defaultAvatarPath) : '';
            $studentPhotoHash = is_file($studentPhotoPath) ? @md5_file($studentPhotoPath) : '';
            if (is_file($studentPhotoPath) && ($defaultAvatarHash === '' || $studentPhotoHash !== $defaultAvatarHash)) {
                $studentPhoto = getStudentPhotoSrc($studentPhotoFilename);
            }
        }
        $examName = trim((string)($exam['exam_name'] ?? 'Exam Admit Card'));
        $examType = trim((string)($exam['exam_type'] ?? ''));
        $academicYear = trim((string)($exam['academic_year'] ?? getCurrentAcademicYear()));
        $issueDate = trim((string)($options['issue_date'] ?? date('Y-m-d')));
        $issueDateLabel = admitCardFormatDate($issueDate, 'd/m/Y');
        $admitNo = trim((string)($options['admit_no'] ?? ''));
        if ($admitNo === '') {
            $admitNo = trim((string)($student['admission_no'] ?? '')) !== '' ? trim((string)($student['admission_no'] ?? '')) . '-' . intval($exam['exam_id'] ?? 0) : '-';
        }
        $studentName = admitCardNormalizeDisplayValue($student['student_name'] ?? '', '-');
        $admissionNo = admitCardNormalizeDisplayValue($student['admission_no'] ?? '', '-');
        $rollNo = admitCardNormalizeDisplayValue($student['roll_no'] ?? '', '-');
        $registrationNo = admitCardNormalizeDisplayValue($student['registration_no'] ?? ($student['registration_number'] ?? ($student['udise_no'] ?? '')), '-');
        $classLabel = admitCardClassLabel($student);
        $fatherName = admitCardNormalizeDisplayValue($student['father_name'] ?? '', '-');
        $motherName = admitCardNormalizeDisplayValue($student['mother_name'] ?? '', '-');
        $dateOfBirth = admitCardFormatDate($student['date_of_birth'] ?? '', 'd M Y');
        $gender = admitCardNormalizeDisplayValue($student['gender'] ?? '', '-');
        $category = admitCardNormalizeDisplayValue($student['category'] ?? '', '-');
        $verificationText = implode('|', array_filter([
            $schoolDisplayName,
            'Examination Admit Card',
            $studentName,
            $admissionNo,
            $rollNo,
            $examName,
            $academicYear,
            $issueDate,
        ]));
        $qrCodeUrl = buildQrCodeUrl($verificationText, 160);
        $photoInitials = strtoupper(substr(preg_replace('/[^A-Za-z]+/', '', $studentName), 0, 2));
        if ($photoInitials === '') {
            $photoInitials = 'ST';
        }
        $teacherSignatureSrc = getSchoolSignatureSrc($schoolSettings['class_teacher_signature'] ?? ($schoolSettings['teacher_signature'] ?? ''));
        $principalSignatureSrc = getSchoolSignatureSrc($schoolSettings['principal_signature'] ?? '');

        ob_start();
        ?>
        <div class="admit-simple-card">
            <div class="admit-simple-head">
                <div class="admit-simple-brand-row">
                    <div class="admit-simple-logo-wrap">
                        <?php if (!empty($logoSrc)): ?>
                            <img src="<?php echo admitCardEscape($logoSrc); ?>" alt="<?php echo admitCardEscape($schoolDisplayName); ?>" class="admit-simple-logo">
                        <?php else: ?>
                            <div class="admit-simple-logo admit-simple-logo-placeholder"><?php echo admitCardEscape($photoInitials); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="admit-simple-brand-copy">
                        <div class="admit-simple-office">office of the principal</div>
                        <div class="admit-simple-school-name"><?php echo admitCardEscape($schoolDisplayName); ?></div>
                        <?php if (!empty($schoolAddress)): ?>
                            <div class="admit-simple-address"><?php echo admitCardEscape($schoolAddress); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="admit-simple-photo-wrap">
                        <?php if (!empty($studentPhoto)): ?>
                            <img src="<?php echo admitCardEscape($studentPhoto); ?>" alt="Student Photo" class="admit-simple-photo">
                        <?php else: ?>
                            <div class="admit-simple-photo admit-simple-photo-placeholder">PHOTO</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admit-simple-contact-bar">
                    <?php if (!empty($schoolEmail)): ?>
                        <span class="admit-simple-contact-item"><i class="bi bi-envelope"></i> <?php echo admitCardEscape($schoolEmail); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($schoolWebsite) && $schoolWebsite !== '-'): ?>
                        <span class="admit-simple-contact-item"><i class="bi bi-globe"></i> <?php echo admitCardEscape($schoolWebsite); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($schoolPhone)): ?>
                        <span class="admit-simple-contact-item"><i class="bi bi-telephone"></i> <?php echo admitCardEscape($schoolPhone); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admit-simple-title">Exam Admit Card</div>

            <table class="admit-simple-meta-table">
                <tbody>
                    <tr>
                        <td><strong>Name :</strong> <?php echo admitCardEscape($studentName); ?></td>
                        <td><strong>Admission No :</strong> <?php echo admitCardEscape($admissionNo); ?></td>
                        <td><strong>Roll No :</strong> <?php echo admitCardEscape($rollNo); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Father's Name :</strong> <?php echo admitCardEscape($fatherName); ?></td>
                        <td><strong>Birthdate :</strong> <?php echo admitCardEscape(admitCardFormatDate($student['date_of_birth'] ?? '', 'd/m/Y')); ?></td>
                        <td><strong>Gender :</strong> <?php echo admitCardEscape($gender); ?></td>
                    </tr>
                    <tr>
                        <td colspan="2"><strong>Exam Name :</strong> <?php echo admitCardEscape($examName); ?></td>
                        <td><strong>Class Name :</strong> <?php echo admitCardEscape($classLabel); ?></td>
                    </tr>
                </tbody>
            </table>

            <table class="admit-simple-schedule-table">
                <thead>
                    <tr>
                        <th style="width: 9%;">Sr.</th>
                        <th style="width: 43%;">Subject</th>
                        <th style="width: 16%;">Exam date</th>
                        <th style="width: 18%;">Exam Time</th>
                        <th style="width: 14%;">Auth.Sign</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($scheduleRows)): ?>
                        <?php foreach ($scheduleRows as $index => $row): ?>
                            <?php
                                $subjectName = trim((string)($row['subject_name'] ?? '-'));
                                $examDate = admitCardFormatDate($row['exam_date'] ?? '', 'd/m/Y');
                                $examTime = trim((string)($row['exam_time'] ?? ($row['exam_timing'] ?? '')));
                                if ($examTime === '') {
                                    $examTime = trim((string)($row['reporting_time'] ?? ''));
                                }
                                $examTime = $examTime !== '' ? $examTime : '-';
                            ?>
                            <tr class="<?php echo ($index % 2 === 1) ? 'is-alt' : ''; ?>">
                                <td class="center"><?php echo admitCardEscape($row['sno'] ?? ($index + 1)); ?></td>
                                <td><?php echo admitCardEscape($subjectName); ?></td>
                                <td class="center"><?php echo admitCardEscape($examDate); ?></td>
                                <td class="center"><?php echo admitCardEscape($examTime); ?></td>
                                <td class="center">&nbsp;</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="center">No class schedule found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="admit-simple-signatures">
                <div class="admit-simple-signature">
                    <?php if (!empty($teacherSignatureSrc)): ?>
                        <img src="<?php echo admitCardEscape($teacherSignatureSrc); ?>" alt="Teacher Signature" class="admit-simple-signature-img">
                    <?php endif; ?>
                    <div class="admit-simple-signature-line"></div>
                    <div class="admit-simple-signature-label">Teacher Signature</div>
                </div>

                <div class="admit-simple-signature admit-simple-signature-right">
                    <?php if (!empty($principalSignatureSrc)): ?>
                        <img src="<?php echo admitCardEscape($principalSignatureSrc); ?>" alt="Principal Signature" class="admit-simple-signature-img">
                    <?php endif; ?>
                    <div class="admit-simple-signature-line"></div>
                    <div class="admit-simple-signature-label">Principal Signature</div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('admitCardRenderDocument')) {
    function admitCardRenderDocument(array $cards, array $options = []) {
        $schoolSettings = is_array($options['school_settings'] ?? null) ? $options['school_settings'] : getSchoolSettings();
        $pageTitle = trim((string)($options['page_title'] ?? 'Admit Card'));
        $backUrl = trim((string)($options['back_url'] ?? ''));
        $refreshUrl = trim((string)($options['refresh_url'] ?? ''));
        $downloadUrl = trim((string)($options['download_url'] ?? ''));
        $printUrl = trim((string)($options['print_url'] ?? ''));
        $showToolbar = array_key_exists('show_toolbar', $options) ? (bool) $options['show_toolbar'] : true;
        $toolbarTitle = trim((string)($options['toolbar_title'] ?? 'Admit Card'));
        $watermark = trim((string)($options['watermark'] ?? ''));
        $paperSize = admitCardNormalizePaperSize($options['paper_size'] ?? 'A4');
        $paperOrientation = admitCardNormalizePaperOrientation($options['paper_orientation'] ?? '', $paperSize);
        if ($paperSize === 'A5') {
            $paperOrientation = 'landscape';
        }
        $paperSizeCss = $paperSize . ' ' . $paperOrientation;
        $paperLandscape = $paperSize === 'A5' && $paperOrientation === 'landscape';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo admitCardEscape($pageTitle); ?></title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
            <style>
                @page {
                    size: <?php echo admitCardEscape($paperSizeCss); ?>;
                    margin: <?php echo $paperLandscape ? '7mm' : '10mm'; ?>;
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    padding: <?php echo $paperLandscape ? '12px' : '18px'; ?>;
                    font-family: Arial, Helvetica, sans-serif;
                    background: #eef3f8;
                    color: #0f172a;
                }

                .admit-toolbar {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    align-items: center;
                    justify-content: flex-start;
                    max-width: 900px;
                    margin: 0 auto 14px;
                }

                .admit-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 0.55rem 0.9rem;
                    border-radius: 6px;
                    border: 1px solid #cbd5e1;
                    background: #fff;
                    color: #1f2937;
                    text-decoration: none;
                    font-size: 0.9rem;
                    line-height: 1.1;
                    white-space: nowrap;
                }

                .admit-btn-primary {
                    border-color: #1d4ed8;
                    color: #1d4ed8;
                }

                .admit-btn-muted {
                    border-color: #64748b;
                    color: #475569;
                }

                .admit-card-stack {
                    max-width: <?php echo $paperLandscape ? '840px' : '900px'; ?>;
                    margin: 0 auto;
                }

                .admit-sheet {
                    position: relative;
                    background:
                        radial-gradient(circle at 10% 12%, rgba(29, 78, 216, 0.03) 0 1px, transparent 1.3px) 0 0 / 26px 26px,
                        radial-gradient(circle at 84% 18%, rgba(180, 83, 9, 0.03) 0 1px, transparent 1.3px) 0 0 / 30px 30px,
                        linear-gradient(180deg, #fffef9 0%, #ffffff 100%);
                    border: 1px solid rgba(15, 23, 42, 0.16);
                    border-radius: 14px;
                    padding: 18px 20px 20px;
                    overflow: hidden;
                    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
                }

                .admit-sheet::before {
                    content: '';
                    position: absolute;
                    inset: 10px;
                    border: 1px solid rgba(29, 78, 216, 0.18);
                    border-radius: 12px;
                    pointer-events: none;
                }

                .admit-sheet::after {
                    content: '';
                    position: absolute;
                    inset: 18px;
                    border: 1px solid rgba(180, 83, 9, 0.10);
                    border-radius: 10px;
                    pointer-events: none;
                }

                .admit-sheet + .admit-sheet {
                    margin-top: 14px;
                }

                .admit-card-body {
                    position: relative;
                    z-index: 1;
                }

                .admit-office {
                    font-size: 11px;
                    font-weight: 700;
                    text-align: center;
                    text-transform: lowercase;
                    letter-spacing: 0.08em;
                    color: #475569;
                }

                .admit-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 12px;
                    margin-top: 2px;
                }

                .admit-brand {
                    display: flex;
                    gap: 12px;
                    align-items: flex-start;
                    min-width: 0;
                    flex: 1;
                }

                .admit-brand-copy {
                    min-width: 0;
                }

                .admit-logo,
                .admit-logo-placeholder {
                    width: 62px;
                    height: 62px;
                    object-fit: contain;
                    border: 1px solid #dbe3ec;
                    background: #fff;
                    padding: 4px;
                    flex: 0 0 auto;
                }

                .admit-logo-placeholder,
                .admit-photo-placeholder {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 800;
                    color: #1d4ed8;
                    background: #eff6ff;
                }

                .admit-school-name {
                    font-size: 27px;
                    font-weight: 800;
                    line-height: 1.1;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                }

                .admit-school-address {
                    margin-top: 4px;
                    font-size: 13px;
                    color: #475569;
                    line-height: 1.35;
                }

                .admit-photo-wrap {
                    flex: 0 0 auto;
                }

                .admit-photo {
                    width: 84px;
                    height: 102px;
                    object-fit: cover;
                    border: 1px solid #cbd5e1;
                    background: #f8fafc;
                }

                .admit-photo-placeholder {
                    width: 84px;
                    height: 102px;
                    font-size: 12px;
                    letter-spacing: 0.08em;
                }

                .admit-contact-bar {
                    margin-top: 8px;
                    background: #1f3c67;
                    color: #fff;
                    border-radius: 0 0 999px 999px;
                    padding: 5px 12px;
                    display: flex;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 18px;
                    font-size: 12px;
                    font-weight: 600;
                }

                .admit-contact-item {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }

                .admit-title {
                    display: inline-block;
                    margin-top: 10px;
                    background: #eff6ff;
                    color: #1d4ed8;
                    padding: 6px 14px;
                    border-radius: 999px;
                    font-size: 18px;
                    font-weight: 800;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                }

                .admit-summary {
                    margin-top: 12px;
                }

                .admit-details-block {
                    width: 100%;
                }

                .admit-detail-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                }

                .admit-detail-table td {
                    border: 1px solid #7b8794;
                    padding: 7px 8px;
                }

                .admit-detail-table td.label {
                    width: 20%;
                    background: #f8fafc;
                    color: #334155;
                    font-weight: 700;
                }

                .admit-detail-table td.value {
                    font-weight: 700;
                    width: 30%;
                }

                .admit-exam-line {
                    margin-top: 8px;
                    font-size: 12px;
                    color: #0f172a;
                    font-weight: 600;
                }

                .admit-muted {
                    color: #64748b;
                    font-weight: 600;
                }

                .admit-section-title {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    gap: 10px;
                    margin-top: 14px;
                    margin-bottom: 6px;
                }

                .admit-section-title h4 {
                    margin: 0;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.12em;
                }

                .admit-section-title .subtle {
                    font-size: 11px;
                    color: #64748b;
                }

                .admit-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                }

                .admit-table th,
                .admit-table td {
                    border: 1px solid #606a75;
                    padding: 6px 7px;
                }

                .admit-table th {
                    background: #f3f4f6;
                    font-weight: 800;
                    text-align: center;
                }

                .admit-table td:nth-child(1),
                .admit-table td:nth-child(3),
                .admit-table td:nth-child(4),
                .admit-table td:nth-child(5),
                .admit-table td:nth-child(6) {
                    text-align: center;
                }

                .admit-note {
                    margin-top: 10px;
                    font-size: 11px;
                    color: #475569;
                }

                .admit-signature-row {
                    display: flex;
                    justify-content: space-between;
                    gap: 24px;
                    margin-top: 24px;
                }

                .admit-signature {
                    width: 42%;
                    text-align: center;
                    font-size: 12px;
                }

                .admit-signature-line {
                    border-top: 1px solid #111827;
                    padding-top: 6px;
                    margin-top: 44px;
                    font-weight: 700;
                }

                .admit-footer {
                    margin-top: 10px;
                    text-align: center;
                    font-size: 10px;
                    color: #64748b;
                }

                .admit-page-break {
                    page-break-after: always;
                }

                .admit-watermark {
                    position: absolute;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    pointer-events: none;
                    color: #1d4ed8;
                    opacity: 0.045;
                    font-size: 66px;
                    font-weight: 900;
                    letter-spacing: 0.22em;
                    text-transform: uppercase;
                    transform: rotate(-24deg);
                    user-select: none;
                    white-space: nowrap;
                }

                .admit-letterhead {
                    position: relative;
                    z-index: 1;
                    text-align: center;
                    padding-bottom: 14px;
                    margin-bottom: 14px;
                    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
                }

                .admit-letterhead-top {
                    display: grid;
                    grid-template-columns: 86px minmax(0, 1fr) 120px;
                    gap: 14px;
                    align-items: center;
                }

                .admit-emblem {
                    width: 86px;
                    height: 86px;
                    border-radius: 50%;
                    border: 2px solid rgba(29, 78, 216, 0.18);
                    background: radial-gradient(circle at center, #fff 0%, #f7fbff 70%, #eef4ff 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.9);
                }

                .admit-emblem img {
                    width: 68px;
                    height: 68px;
                    object-fit: contain;
                }

                .admit-emblem-placeholder {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 22px;
                    font-weight: 900;
                    color: #1d4ed8;
                    letter-spacing: 0.08em;
                }

                .admit-letterhead-copy {
                    min-width: 0;
                }

                .admit-school-name {
                    font-size: 25px;
                    line-height: 1.08;
                    font-weight: 900;
                    color: #10213a;
                    text-transform: uppercase;
                    letter-spacing: 0.03em;
                }

                .admit-school-lines {
                    margin-top: 6px;
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
                    color: #334155;
                    font-size: 0.84rem;
                    font-weight: 700;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                }

                .admit-school-meta,
                .admit-school-session {
                    margin-top: 5px;
                    color: #475569;
                    font-size: 0.9rem;
                    line-height: 1.45;
                }

                .admit-school-session {
                    color: #1d4ed8;
                    font-weight: 700;
                }

                .admit-header-qr {
                    width: 120px;
                    text-align: center;
                    justify-self: end;
                }

                .admit-header-qr img {
                    width: 96px;
                    height: 96px;
                    background: #fff;
                    border: 1px solid #dbe3ec;
                    border-radius: 12px;
                    padding: 4px;
                }

                .admit-header-qr-label {
                    margin-top: 6px;
                    font-size: 0.7rem;
                    text-transform: uppercase;
                    letter-spacing: 0.16em;
                    color: #64748b;
                    font-weight: 800;
                }

                .admit-title-pill {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-top: 12px;
                    padding: 8px 18px;
                    border-radius: 999px;
                    background: linear-gradient(135deg, #1d4ed8 0%, #0f2e6e 100%);
                    color: #ffffff;
                    font-size: 15px;
                    font-weight: 900;
                    letter-spacing: 0.22em;
                    text-transform: uppercase;
                    box-shadow: 0 10px 20px rgba(29, 78, 216, 0.14);
                }

                .admit-title-note {
                    margin-top: 6px;
                    color: #64748b;
                    font-size: 0.82rem;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }

                .admit-top-grid {
                    display: grid;
                    grid-template-columns: minmax(180px, 230px) minmax(0, 1fr);
                    gap: 14px;
                    position: relative;
                    z-index: 1;
                    align-items: stretch;
                }

                .admit-photo-card,
                .admit-panel,
                .admit-instructions,
                .admit-seal-block {
                    border: 1px solid #dbe3ec;
                    border-radius: 14px;
                    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.75);
                }

                .admit-photo-card {
                    padding: 12px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 8px;
                }

                .admit-photo-wrap {
                    width: 100%;
                    display: flex;
                    justify-content: center;
                }

                .admit-photo {
                    width: 100%;
                    max-width: 170px;
                    height: 204px;
                    object-fit: cover;
                    border: 1px solid #cbd5e1;
                    border-radius: 12px;
                    background: #f8fafc;
                }

                .admit-photo-placeholder {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 13px;
                    font-weight: 900;
                    letter-spacing: 0.14em;
                    color: #1d4ed8;
                    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
                }

                .admit-photo-caption {
                    font-size: 0.74rem;
                    text-transform: uppercase;
                    letter-spacing: 0.16em;
                    color: #64748b;
                    font-weight: 800;
                }

                .admit-photo-name {
                    width: 100%;
                    text-align: center;
                    font-size: 0.92rem;
                    font-weight: 800;
                    color: #0f172a;
                    line-height: 1.35;
                }

                .admit-signature-box {
                    width: 100%;
                    margin-top: 2px;
                    text-align: center;
                }

                .admit-signature-box-label {
                    font-size: 0.72rem;
                    text-transform: uppercase;
                    letter-spacing: 0.14em;
                    color: #64748b;
                    font-weight: 800;
                }

                .admit-signature-box-line {
                    margin-top: 14px;
                    border-top: 1px solid #111827;
                    opacity: 0.35;
                }

                .admit-qr-mini {
                    width: 100%;
                    margin-top: 2px;
                    border-top: 1px dashed rgba(180, 83, 9, 0.18);
                    padding-top: 8px;
                    text-align: center;
                }

                .admit-qr-mini img {
                    width: 96px;
                    height: 96px;
                    border: 1px solid #dbe3ec;
                    border-radius: 10px;
                    background: #ffffff;
                    padding: 4px;
                }

                .admit-qr-mini div {
                    margin-top: 5px;
                    font-size: 0.72rem;
                    color: #475569;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    font-weight: 700;
                }

                .admit-details-stack {
                    display: grid;
                    gap: 14px;
                    min-width: 0;
                }

                .admit-panel {
                    padding: 12px 14px 14px;
                }

                .admit-panel-accent {
                    border-left: 4px solid #1d4ed8;
                }

                .admit-panel-heading {
                    font-size: 0.82rem;
                    text-transform: uppercase;
                    letter-spacing: 0.18em;
                    color: #0f172a;
                    font-weight: 900;
                    margin-bottom: 10px;
                }

                .admit-field-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 10px;
                }

                .admit-field-grid-compact {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }

                .admit-field {
                    border: 1px solid #dbe3ec;
                    border-radius: 12px;
                    padding: 8px 10px;
                    background: #ffffff;
                    min-height: 62px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
                }

                .admit-field-wide {
                    grid-column: 1 / -1;
                }

                .admit-field-label {
                    font-size: 0.69rem;
                    color: #64748b;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.12em;
                    line-height: 1.25;
                }

                .admit-field-value {
                    margin-top: 4px;
                    color: #111827;
                    font-size: 0.93rem;
                    line-height: 1.45;
                    font-weight: 700;
                    word-break: break-word;
                }

                .admit-section-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: end;
                    gap: 10px;
                    margin-top: 14px;
                    margin-bottom: 8px;
                    position: relative;
                    z-index: 1;
                }

                .admit-section-header h4 {
                    margin: 0;
                    font-size: 1rem;
                    text-transform: uppercase;
                    letter-spacing: 0.14em;
                    color: #0f172a;
                    font-weight: 900;
                }

                .admit-section-header p {
                    margin: 4px 0 0;
                    color: #64748b;
                    font-size: 0.82rem;
                }

                .admit-section-badge {
                    padding: 6px 10px;
                    border-radius: 999px;
                    border: 1px solid rgba(29, 78, 216, 0.2);
                    background: #eff6ff;
                    color: #1d4ed8;
                    font-size: 0.76rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.12em;
                    white-space: nowrap;
                }

                .admit-schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    position: relative;
                    z-index: 1;
                    font-size: 0.72rem;
                }

                .admit-schedule-table th,
                .admit-schedule-table td {
                    border: 1px solid #cad5e2;
                    padding: 7px 6px;
                    vertical-align: middle;
                    word-break: break-word;
                }

                .admit-schedule-table th {
                    background: linear-gradient(180deg, #1f3c67 0%, #17315b 100%);
                    color: #ffffff;
                    font-weight: 900;
                    text-align: center;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                    font-size: 0.66rem;
                    line-height: 1.25;
                }

                .admit-schedule-table tbody tr.is-alt td {
                    background: #f8fbff;
                }

                .admit-schedule-table td:nth-child(1),
                .admit-schedule-table td:nth-child(3),
                .admit-schedule-table td:nth-child(4),
                .admit-schedule-table td:nth-child(5),
                .admit-schedule-table td:nth-child(6),
                .admit-schedule-table td:nth-child(7),
                .admit-schedule-table td:nth-child(8),
                .admit-schedule-table td:nth-child(9) {
                    text-align: center;
                }

                .admit-time-stack {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 2px;
                }

                .admit-time-stack strong {
                    color: #0f172a;
                    font-size: 0.77rem;
                    line-height: 1.25;
                }

                .admit-invigilator-sign {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 4px;
                    min-height: 34px;
                }

                .admit-invigilator-line {
                    width: 100%;
                    border-bottom: 1px solid rgba(15, 23, 42, 0.35);
                    min-height: 16px;
                }

                .admit-invigilator-sign small {
                    font-size: 0.62rem;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.12em;
                    font-weight: 800;
                }

                .admit-instructions {
                    margin-top: 12px;
                    padding: 12px 14px 14px;
                    border-left: 4px solid #b45309;
                    position: relative;
                    z-index: 1;
                }

                .admit-instructions-heading {
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.16em;
                    font-weight: 900;
                    color: #b45309;
                    margin-bottom: 8px;
                }

                .admit-instructions-list {
                    margin: 0;
                    padding-left: 18px;
                    color: #0f172a;
                    font-size: 0.88rem;
                    line-height: 1.6;
                }

                .admit-instructions-list li + li {
                    margin-top: 4px;
                }

                .admit-footer-grid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 12px;
                    margin-top: 14px;
                    align-items: end;
                    position: relative;
                    z-index: 1;
                }

                .admit-signature-block {
                    text-align: center;
                    padding-top: 10px;
                }

                .admit-signature-line {
                    border-top: 1px solid rgba(15, 23, 42, 0.30);
                    min-height: 34px;
                }

                .admit-signature-label {
                    margin-top: 6px;
                    font-size: 0.74rem;
                    text-transform: uppercase;
                    letter-spacing: 0.14em;
                    font-weight: 900;
                    color: #0f172a;
                }

                .admit-signature-name {
                    margin-top: 4px;
                    font-size: 0.76rem;
                    color: #475569;
                    line-height: 1.4;
                }

                .admit-seal-block {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 118px;
                    padding: 10px;
                }

                .admit-seal-circle {
                    width: 104px;
                    height: 104px;
                    border-radius: 50%;
                    border: 3px solid rgba(29, 78, 216, 0.32);
                    background: radial-gradient(circle at center, #fff 0%, #f7fbff 72%, #ecf4ff 100%);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    color: #1e3a8a;
                    text-transform: uppercase;
                    font-weight: 900;
                    line-height: 1.1;
                }

                .admit-seal-circle span {
                    font-size: 0.64rem;
                    letter-spacing: 0.22em;
                    color: #64748b;
                }

                .admit-seal-circle strong {
                    margin-top: 4px;
                    font-size: 1rem;
                    letter-spacing: 0.12em;
                }

                .admit-note-strip {
                    display: flex;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin-top: 10px;
                    padding-top: 8px;
                    border-top: 1px dashed rgba(15, 23, 42, 0.14);
                    color: #475569;
                    font-size: 0.78rem;
                    line-height: 1.45;
                    position: relative;
                    z-index: 1;
                }

                .admit-note-strip strong {
                    color: #1e3a8a;
                }

                @media print {
                    body {
                        background: #fff;
                        padding: 0;
                    }

                    .admit-toolbar {
                        display: none !important;
                    }

                    .admit-sheet {
                        box-shadow: none;
                        border-width: 0;
                        page-break-after: always;
                    }

                    .admit-sheet:last-child {
                        page-break-after: auto;
                    }
                }

                @media (max-width: 767px) {
                    .admit-header {
                        flex-direction: column;
                    }

                    .admit-letterhead-top,
                    .admit-top-grid,
                    .admit-footer-grid {
                        grid-template-columns: 1fr;
                    }

                    .admit-header-qr {
                        justify-self: center;
                    }

                    .admit-school-name {
                        font-size: 21px;
                    }

                    .admit-field-grid,
                    .admit-field-grid-compact {
                        grid-template-columns: 1fr;
                    }

                    .admit-schedule-table {
                        font-size: 0.61rem;
                    }

                    .admit-detail-table td.label,
                    .admit-detail-table td.value {
                        width: auto;
                    }

                    .admit-signature-row {
                        flex-direction: column;
                    }

                    .admit-signature {
                        width: 100%;
                    }

                    .admit-section-header {
                        flex-direction: column;
                        align-items: flex-start;
                    }

                    .admit-note-strip {
                        flex-direction: column;
                    }
                }
            </style>
            <style>
                @page {
                    size: <?php echo admitCardEscape($paperSizeCss); ?>;
                    margin: <?php echo $paperLandscape ? '6mm' : '8mm'; ?>;
                }

                body {
                    background: #ffffff !important;
                    color: #111827;
                    padding: <?php echo $paperLandscape ? '10px' : '14px'; ?> !important;
                }

                .admit-watermark {
                    display: none !important;
                }

                .admit-card-stack {
                    max-width: <?php echo $paperLandscape ? '860px' : '1120px'; ?>;
                }

                .admit-sheet {
                    background: #ffffff !important;
                    border: 0 !important;
                    box-shadow: none !important;
                    padding: 0 !important;
                    margin: 0 0 18px !important;
                    overflow: visible !important;
                }

                .admit-sheet::before,
                .admit-sheet::after {
                    content: none !important;
                    display: none !important;
                }

                .admit-simple-card {
                    border: 1px solid #111827;
                    background: #ffffff;
                    padding: <?php echo $paperLandscape ? '8px 8px 10px' : '10px 10px 12px'; ?>;
                }

                .admit-simple-brand-row {
                    display: grid;
                    grid-template-columns: <?php echo $paperLandscape ? '76px 1fr 96px' : '88px 1fr 110px'; ?>;
                    gap: <?php echo $paperLandscape ? '10px' : '12px'; ?>;
                    align-items: start;
                }

                .admit-simple-logo,
                .admit-simple-photo {
                    width: 100%;
                    object-fit: cover;
                    border: 1px solid #9ca3af;
                    background: #ffffff;
                }

                .admit-simple-logo {
                    height: <?php echo $paperLandscape ? '70px' : '84px'; ?>;
                    padding: 4px;
                }

                .admit-simple-photo {
                    height: <?php echo $paperLandscape ? '100px' : '118px'; ?>;
                }

                .admit-simple-logo-placeholder,
                .admit-simple-photo-placeholder {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    color: #0f172a;
                    background: #f8fafc;
                }

                .admit-simple-office {
                    text-align: center;
                    font-style: italic;
                    font-size: <?php echo $paperLandscape ? '11px' : '12px'; ?>;
                    font-weight: 700;
                    text-decoration: underline;
                    margin-top: 2px;
                    margin-bottom: 5px;
                    text-transform: lowercase;
                }

                .admit-simple-school-name {
                    text-align: center;
                    font-size: <?php echo $paperLandscape ? '23px' : '28px'; ?>;
                    font-weight: 700;
                    line-height: 1.08;
                    color: #0f2a55;
                    text-transform: uppercase;
                }

                .admit-simple-address {
                    text-align: center;
                    font-size: <?php echo $paperLandscape ? '14px' : '16px'; ?>;
                    font-weight: 700;
                    line-height: 1.25;
                    color: #111827;
                    margin-top: 3px;
                }

                .admit-simple-contact-bar {
                    margin-top: 10px;
                    background: #102b4f;
                    color: #ffffff;
                    padding: <?php echo $paperLandscape ? '6px 12px' : '7px 16px'; ?>;
                    display: flex;
                    justify-content: center;
                    gap: <?php echo $paperLandscape ? '12px' : '24px'; ?>;
                    flex-wrap: wrap;
                    align-items: center;
                    font-size: <?php echo $paperLandscape ? '11px' : '12px'; ?>;
                    font-weight: 700;
                    border-radius: 999px;
                }

                .admit-simple-contact-item {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    white-space: nowrap;
                }

                .admit-simple-title {
                    text-align: center;
                    font-size: <?php echo $paperLandscape ? '16px' : '18px'; ?>;
                    font-weight: 700;
                    color: #111827;
                    margin: 8px 0 8px;
                    padding: 4px 0;
                    border-top: 1px solid #111827;
                    border-bottom: 1px solid #111827;
                }

                .admit-simple-meta-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 6px;
                    font-size: <?php echo $paperLandscape ? '12px' : '14px'; ?>;
                }

                .admit-simple-meta-table td {
                    border-top: 1px solid #111827;
                    padding: <?php echo $paperLandscape ? '3px 5px' : '4px 6px'; ?>;
                    vertical-align: top;
                }

                .admit-simple-meta-table tr:last-child td {
                    border-bottom: 1px solid #111827;
                }

                .admit-simple-schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    font-size: <?php echo $paperLandscape ? '11px' : '13px'; ?>;
                }

                .admit-simple-schedule-table th,
                .admit-simple-schedule-table td {
                    border: 1px solid #7b7b7b;
                    padding: <?php echo $paperLandscape ? '4px 5px' : '5px 6px'; ?>;
                    vertical-align: middle;
                    word-break: break-word;
                }

                .admit-simple-schedule-table th {
                    background: #d9d9d9;
                    color: #111827;
                    font-weight: 700;
                    text-align: center;
                }

                .admit-simple-schedule-table tbody tr:nth-child(even) td {
                    background: #fafafa;
                }

                .admit-simple-schedule-table td.center {
                    text-align: center;
                }

                .admit-simple-signatures {
                    margin-top: 18px;
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: <?php echo $paperLandscape ? '28px' : '48px'; ?>;
                    align-items: end;
                }

                .admit-simple-signature {
                    width: 100%;
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-end;
                    align-items: center;
                    min-height: <?php echo $paperLandscape ? '78px' : '88px'; ?>;
                }

                .admit-simple-signature-right {
                    justify-self: end;
                }

                .admit-simple-signature-img {
                    display: block;
                    max-width: 170px;
                    max-height: <?php echo $paperLandscape ? '52px' : '60px'; ?>;
                    object-fit: contain;
                    margin: 0 auto 3px;
                }

                .admit-simple-signature-line {
                    border-top: 1px solid #111827;
                    margin-top: <?php echo $paperLandscape ? '8px' : '12px'; ?>;
                    min-height: <?php echo $paperLandscape ? '18px' : '24px'; ?>;
                    width: 100%;
                }

                .admit-simple-signature-label {
                    font-size: <?php echo $paperLandscape ? '12px' : '13px'; ?>;
                    font-weight: 700;
                    margin-top: 4px;
                }

                <?php if ($paperLandscape): ?>
                .paper-a5-landscape .admit-simple-contact-item {
                    white-space: normal;
                }

                .paper-a5-landscape .admit-simple-contact-bar {
                    border-radius: 14px;
                }

                .paper-a5-landscape .admit-simple-signatures {
                    margin-top: 14px;
                }
                <?php endif; ?>

                @media print {
                    body {
                        padding: 0 !important;
                    }

                    .admit-toolbar {
                        display: none !important;
                    }

                    .admit-sheet {
                        page-break-after: always;
                    }

                    .admit-sheet:last-child {
                        page-break-after: auto;
                    }
                }

                @media (max-width: 767px) {
                    .admit-simple-brand-row,
                    .admit-simple-signatures {
                        grid-template-columns: 1fr;
                        display: grid;
                    }

                    .admit-simple-signature {
                        width: 100%;
                        justify-self: stretch;
                    }

                    .admit-simple-school-name {
                        font-size: 22px;
                    }

                    .admit-simple-address {
                        font-size: 14px;
                    }

                    .admit-simple-contact-bar {
                        gap: 10px;
                        border-radius: 14px;
                    }
                }
            </style>
        </head>
        <body class="<?php echo admitCardEscape($paperLandscape ? 'paper-a5-landscape' : 'paper-a4-portrait'); ?>">
            <?php if ($showToolbar): ?>
                <div class="admit-toolbar no-print">
                    <?php if ($backUrl !== ''): ?>
                        <a href="<?php echo admitCardEscape($backUrl); ?>" class="admit-btn admit-btn-muted"><i class="bi bi-arrow-left"></i> Back</a>
                    <?php endif; ?>
                    <?php if ($refreshUrl !== ''): ?>
                        <a href="<?php echo admitCardEscape($refreshUrl); ?>" class="admit-btn admit-btn-muted"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
                    <?php endif; ?>
                    <?php if ($downloadUrl !== ''): ?>
                        <a href="<?php echo admitCardEscape($downloadUrl); ?>" class="admit-btn admit-btn-primary"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
                    <?php endif; ?>
                    <?php if ($printUrl !== ''): ?>
                        <a href="<?php echo admitCardEscape($printUrl); ?>" class="admit-btn admit-btn-primary"><i class="bi bi-printer"></i> Print</a>
                    <?php else: ?>
                        <button type="button" class="admit-btn admit-btn-primary" onclick="window.print();"><i class="bi bi-printer"></i> Print</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="admit-card-stack">
                <?php foreach ($cards as $index => $card): ?>
                    <?php
                        $sheetClass = 'admit-sheet';
                        if ($index < count($cards) - 1) {
                            $sheetClass .= ' admit-page-break';
                        }
                        $student = is_array($card['student'] ?? null) ? $card['student'] : [];
                        $exam = is_array($card['exam'] ?? null) ? $card['exam'] : [];
                        $scheduleRows = is_array($card['schedule_rows'] ?? null) ? $card['schedule_rows'] : [];
                        $cardOptions = is_array($card['options'] ?? null) ? $card['options'] : [];
                    ?>
                    <div class="<?php echo admitCardEscape($sheetClass); ?>">
                        <?php if (!empty($watermark)): ?>
                            <div class="admit-watermark"><?php echo admitCardEscape($watermark); ?></div>
                        <?php endif; ?>
                        <?php echo admitCardRenderSheetFragment($schoolSettings, $student, $exam, $scheduleRows, $cardOptions); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
