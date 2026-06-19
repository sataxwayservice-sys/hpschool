<?php
/**
 * Mark Sheet PDF Generator
 * Generates printable mark sheets and stores the latest copy per exam head.
 */

require_once __DIR__ . '/student_portal.php';
require_once __DIR__ . '/pdf_export.php';

if (!function_exists('marksheetEscape')) {
    function marksheetEscape($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('marksheetText')) {
    function marksheetText($value, $fallback = '-') {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('marksheetUpper')) {
    function marksheetUpper($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }

        return strtoupper($value);
    }
}

if (!function_exists('marksheetSettingValue')) {
    function marksheetSettingValue(array $settings, array $keys, $fallback = '-') {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = trim((string) $settings[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}

if (!function_exists('marksheetFormatDate')) {
    function marksheetFormatDate($value, $fallback = '-') {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $fallback;
        }

        return marksheetUpper(date('d F Y', $timestamp));
    }
}

if (!function_exists('marksheetRomanNumeral')) {
    function marksheetRomanNumeral($number) {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        $number = intval($number);
        return $map[$number] ?? (string) $number;
    }
}

if (!function_exists('marksheetClassLabel')) {
    function marksheetClassLabel($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        $normalized = preg_replace('/\s+/', ' ', strtolower($value));
        $normalized = str_replace(['class ', 'standard ', 'std. ', 'std ', 'grade '], '', $normalized);

        $map = [
            'nursery' => 'NURSERY',
            'lkg' => 'LKG',
            'ukg' => 'UKG',
            '1st' => 'I',
            'first' => 'I',
            '1' => 'I',
            '2nd' => 'II',
            'second' => 'II',
            '2' => 'II',
            '3rd' => 'III',
            'third' => 'III',
            '3' => 'III',
            '4th' => 'IV',
            'fourth' => 'IV',
            '4' => 'IV',
            '5th' => 'V',
            'fifth' => 'V',
            '5' => 'V',
            '6th' => 'VI',
            'sixth' => 'VI',
            '6' => 'VI',
            '7th' => 'VII',
            'seventh' => 'VII',
            '7' => 'VII',
            '8th' => 'VIII',
            'eighth' => 'VIII',
            '8' => 'VIII',
            '9th' => 'IX',
            'ninth' => 'IX',
            '9' => 'IX',
            '10th' => 'X',
            'tenth' => 'X',
            '10' => 'X',
            'x' => 'X',
            '11th' => 'XI',
            'eleventh' => 'XI',
            '11' => 'XI',
            'xi' => 'XI',
            '12th' => 'XII',
            'twelfth' => 'XII',
            '12' => 'XII',
            'xii' => 'XII',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if (preg_match('/\b(10|11|12|[1-9])(?:st|nd|rd|th)?\b/', $normalized, $matches)) {
            return marksheetRomanNumeral(intval($matches[1]));
        }

        return marksheetUpper($value);
    }
}

if (!function_exists('marksheetGradeInfo')) {
    function marksheetGradeInfo($percentage) {
        $percentage = floatval($percentage);

        if ($percentage >= 91) {
            return ['label' => 'A1+', 'point' => 10, 'range' => '91 - 100'];
        }
        if ($percentage >= 81) {
            return ['label' => 'A1', 'point' => 9, 'range' => '81 - 90'];
        }
        if ($percentage >= 71) {
            return ['label' => 'B1', 'point' => 8, 'range' => '71 - 80'];
        }
        if ($percentage >= 61) {
            return ['label' => 'B2', 'point' => 7, 'range' => '61 - 70'];
        }
        if ($percentage >= 51) {
            return ['label' => 'C1', 'point' => 6, 'range' => '51 - 60'];
        }
        if ($percentage >= 41) {
            return ['label' => 'C2', 'point' => 5, 'range' => '41 - 50'];
        }
        if ($percentage >= 33) {
            return ['label' => 'D', 'point' => 4, 'range' => '33 - 40'];
        }

        return ['label' => 'E', 'point' => '--', 'range' => 'Below 33'];
    }
}

if (!function_exists('marksheetDivisionLabel')) {
    function marksheetDivisionLabel($percentage) {
        $percentage = floatval($percentage);
        if ($percentage >= 60) {
            return 'FIRST DIVISION';
        }
        if ($percentage >= 45) {
            return 'SECOND DIVISION';
        }
        if ($percentage >= 33) {
            return 'THIRD DIVISION';
        }

        return 'FAILED';
    }
}

if (!function_exists('marksheetBuildSerialNumber')) {
    function marksheetBuildSerialNumber(array $student, array $exam) {
        $examYear = date('Y');
        $examDate = trim((string) ($exam['exam_date'] ?? ''));
        if ($examDate !== '' && strtotime($examDate) !== false) {
            $examYear = date('Y', strtotime($examDate));
        }

        $studentId = intval($student['student_id'] ?? 0);
        $examId = intval($exam['exam_id'] ?? 0);
        $seed = sprintf('%u', crc32($studentId . '|' . $examId . '|' . $examYear));
        $serial = str_pad((string) (intval($seed) % 1000000), 6, '0', STR_PAD_LEFT);

        return 'MS/' . $examYear . '/' . $serial;
    }
}

if (!function_exists('marksheetBuildBarcodeSvg')) {
    function marksheetBuildBarcodeSvg($value, $width = 170, $height = 34) {
        $hash = sha1((string) $value);
        $x = 4;
        $svg = '';
        $hashLength = strlen($hash);

        for ($i = 0; $i < $hashLength; $i += 2) {
            $byte = hexdec(substr($hash, $i, 2));
            $barWidth = 1 + ($byte % 3);
            $gapWidth = 1 + (int) (($byte >> 1) % 3);
            $barHeight = $height - 6 - ($byte % 10);
            $y = $height - 3 - $barHeight;

            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="#111827" />';
            $x += $barWidth + $gapWidth;

            if ($x > $width - 8) {
                break;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Marksheet barcode">' . $svg . '</svg>';
    }
}

if (!function_exists('marksheetBuildOnePagePdfWrapperHtml')) {
    function marksheetBuildOnePagePdfWrapperHtml($pngDataUri) {
        $pngDataUri = trim((string) $pngDataUri);
        if ($pngDataUri === '') {
            return '';
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
            overflow: hidden;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sheet {
            width: 210mm;
            height: 297mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
        }
        .sheet img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <img src="<?php echo marksheetEscape($pngDataUri); ?>" alt="Marksheet">
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('marksheetExportOnePagePdfHtml')) {
    function marksheetExportOnePagePdfHtml($html, $downloadName = 'document.pdf', $windowWidth = 1600, $windowHeight = 2200) {
        $chromePath = pdfExportFindChromePath();
        if ($chromePath === '') {
            return [
                'success' => false,
                'message' => 'Google Chrome was not found on this server.',
            ];
        }

        $html = (string) $html;
        $trimmedHtml = ltrim($html);
        $looksLikeFullDocument = preg_match('/^(?:<!doctype\s+html\b|<html\b)/i', $trimmedHtml) === 1;

        $tempRoot = BASE_PATH . '/.runtime/pdf_exports';
        if (function_exists('ensureDirectoryExists')) {
            ensureDirectoryExists($tempRoot);
        } elseif (!is_dir($tempRoot)) {
            @mkdir($tempRoot, 0777, true);
        }

        $token = uniqid('marksheet_', true);
        $htmlPath = $tempRoot . '/' . $token . '.html';
        $pngPath = $tempRoot . '/' . $token . '.png';

        $htmlWrapper = $looksLikeFullDocument
            ? $trimmedHtml
            : "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n</head>\n<body>\n" . $html . "\n</body>\n</html>";
        if (@file_put_contents($htmlPath, $htmlWrapper) === false) {
            return [
                'success' => false,
                'message' => 'Unable to write temporary HTML file for screenshot export.',
            ];
        }

        $url = pdfExportBuildFileUrl($htmlPath);
        $chromeArgs = [
            pdfExportQuotePath($chromePath),
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--allow-file-access-from-files',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=3500',
            '--window-size=' . intval($windowWidth) . ',' . intval($windowHeight),
            '--hide-scrollbars',
            '--screenshot=' . pdfExportQuotePath($pngPath),
            pdfExportQuotePath($url),
        ];

        $command = implode(' ', $chromeArgs);
        $output = [];
        $exitCode = 0;
        @exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($pngPath) || filesize($pngPath) === 0) {
            @unlink($htmlPath);
            @unlink($pngPath);
            return [
                'success' => false,
                'message' => 'Screenshot capture failed.',
                'output' => $output,
                'command' => $command,
            ];
        }

        $pngBytes = file_get_contents($pngPath);
        if ($pngBytes === false || $pngBytes === '') {
            @unlink($htmlPath);
            @unlink($pngPath);
            return [
                'success' => false,
                'message' => 'Unable to read generated screenshot.',
            ];
        }

        $pngDataUri = 'data:image/png;base64,' . base64_encode($pngBytes);
        $wrapperHtml = marksheetBuildOnePagePdfWrapperHtml($pngDataUri);
        @unlink($pngPath);
        @unlink($htmlPath);

        return pdfExportDownloadHtml($wrapperHtml, $downloadName);
    }
}

if (!function_exists('marksheetGetGradePointText')) {
    function marksheetGetGradePointText($percentage) {
        $info = marksheetGradeInfo($percentage);
        return (string) $info['point'];
    }
}

if (!function_exists('getMarkSheetData')) {
    function getMarkSheetData($studentId, $examId) {
        $studentId = intval($studentId);
        $examId = intval($examId);

        if ($studentId <= 0 || $examId <= 0) {
            return null;
        }

        $student = fetchOne(
            "SELECT s.*, c.class_name, sec.section_name
             FROM students s
             JOIN classes c ON s.class_id = c.class_id
             JOIN sections sec ON s.section_id = sec.section_id
             WHERE s.student_id = ?",
            'i',
            [$studentId]
        );

        $exam = fetchOne("SELECT * FROM exams WHERE exam_id = ?", 'i', [$examId]);

        if (!$student || !$exam) {
            return null;
        }

        $marks = fetchAll(
            "SELECT m.*, sub.subject_name, sub.subject_code, sub.max_marks, sub.pass_marks
             FROM marks m
             JOIN subjects sub ON m.subject_id = sub.subject_id
             WHERE m.student_id = ? AND m.exam_id = ?
             ORDER BY
                CASE
                    WHEN sub.subject_code REGEXP '^[0-9]+$' THEN 0
                    ELSE 1
                END,
                CAST(NULLIF(sub.subject_code, '') AS UNSIGNED),
                sub.subject_name",
            'ii',
            [$studentId, $examId]
        );

        if (empty($marks)) {
            return null;
        }

        $totalMarks = 0;
        $totalMaxMarks = 0;
        $totalPassMarks = 0;
        $failedSubjects = 0;

        foreach ($marks as $mark) {
            $obtained = floatval($mark['marks_obtained'] ?? 0);
            $maxMarks = floatval($mark['max_marks'] ?? 0);
            $passMarks = floatval($mark['pass_marks'] ?? 0);

            $totalMarks += $obtained;
            $totalMaxMarks += $maxMarks;
            $totalPassMarks += $passMarks;

            if ($obtained < $passMarks) {
                $failedSubjects++;
            }
        }

        $percentage = $totalMaxMarks > 0 ? round(($totalMarks / $totalMaxMarks) * 100, 2) : 0;
        $overallGradeInfo = marksheetGradeInfo($percentage);
        $result = $failedSubjects === 0 ? 'PASS' : 'FAIL';

        return [
            'student' => $student,
            'exam' => $exam,
            'marks' => $marks,
            'totalMarks' => $totalMarks,
            'totalMaxMarks' => $totalMaxMarks,
            'totalPassMarks' => $totalPassMarks,
            'percentage' => $percentage,
            'overallGrade' => $overallGradeInfo['label'],
            'overallGradePoint' => $overallGradeInfo['point'],
            'result' => $result,
        ];
    }
}

if (!function_exists('generateMarkSheetHTML')) {
    function generateMarkSheetHTML($student, $exam, $marks, $schoolSettings, $totalMarks, $totalMaxMarks, $totalPassMarks, $percentage, $grade, $result, $showToolbar = true) {
        $schoolNameRaw = marksheetText($schoolSettings['school_name'] ?? APP_NAME, APP_NAME);
        $schoolName = marksheetUpper($schoolNameRaw);
        $schoolAddress = marksheetText($schoolSettings['school_address'] ?? '');
        $schoolPhone = marksheetText($schoolSettings['school_phone'] ?? '');
        $schoolEmail = marksheetText($schoolSettings['school_email'] ?? '');
        $affiliationNo = marksheetSettingValue($schoolSettings, ['affiliation_no', 'affiliation_number'], '-');
        $schoolCode = marksheetSettingValue($schoolSettings, ['school_code', 'udise_code'], '-');
        $sealYear = marksheetSettingValue($schoolSettings, ['established_year', 'estd_year', 'school_established_year'], date('Y'));

        $studentName = marksheetUpper(marksheetText($student['student_name'] ?? '-'));
        $admissionNo = marksheetText($student['admission_no'] ?? '-');
        $registrationNo = marksheetText($student['registration_no'] ?? ($student['registration_number'] ?? ($student['udise_no'] ?? $admissionNo)));
        $rollNo = marksheetText($student['roll_no'] ?? '-');
        $fatherName = marksheetUpper(marksheetText($student['father_name'] ?? '-'));
        $motherName = marksheetUpper(marksheetText($student['mother_name'] ?? '-'));
        $dateOfBirth = marksheetFormatDate($student['date_of_birth'] ?? '');
        $admissionDate = marksheetFormatDate($student['admission_date'] ?? '');
        $classDisplay = marksheetClassLabel($student['class_name'] ?? '');
        $sectionDisplay = marksheetUpper(marksheetText($student['section_name'] ?? '-'));
        $examName = marksheetUpper(marksheetText($exam['exam_name'] ?? 'STATEMENT OF MARKS', 'STATEMENT OF MARKS'));
        $examHeading = $examName;
        if ($classDisplay !== '-' && $classDisplay !== '') {
            $examHeading .= ' (CLASS ' . $classDisplay . ')';
        }
        $academicSession = marksheetUpper(marksheetText($exam['academic_year'] ?? ($schoolSettings['current_academic_year'] ?? ''), marksheetText($schoolSettings['current_academic_year'] ?? '', date('Y') . '-' . (date('Y') + 1))));
        $generatedOn = marksheetUpper(date('d F Y'));
        $examHeldLabel = $exam['exam_date'] ?? '';
        $examHeldLabel = $examHeldLabel !== '' && strtotime($examHeldLabel) !== false
            ? marksheetUpper(date('F Y', strtotime($examHeldLabel)))
            : marksheetUpper(date('F Y'));

        $teacherSignatureSrc = getSchoolSignatureSrc($schoolSettings['class_teacher_signature'] ?? ($schoolSettings['teacher_signature'] ?? ''));
        $principalSignatureSrc = getSchoolSignatureSrc($schoolSettings['principal_signature'] ?? '');
        $studentPhotoSrc = getStudentPhotoSrc($student['photo'] ?? '');
        $marksheetNo = marksheetBuildSerialNumber($student, $exam);
        $marksheetBarcode = marksheetBuildBarcodeSvg($marksheetNo);
        $qrCodeUrl = buildQrCodeUrl('MARKSHEET|' . $marksheetNo . '|' . intval($student['student_id'] ?? 0) . '|' . intval($exam['exam_id'] ?? 0), 120);

        $rankInSchool = trim((string) ($student['rank_in_school'] ?? ($student['class_rank'] ?? ($student['overall_rank'] ?? ''))));
        if ($rankInSchool === '') {
            $rankInSchool = 'N/A';
        }

        $attendanceValue = trim((string) ($student['attendance'] ?? ($student['attendance_days'] ?? ($student['present_days'] ?? ''))));
        if ($attendanceValue === '') {
            $attendanceValue = 'N/A';
        }

        $division = marksheetDivisionLabel($percentage);
        $overallGradeInfo = marksheetGradeInfo($percentage);
        $overallGrade = $overallGradeInfo['label'];
        $overallGradePoint = $overallGradeInfo['point'];

        $sealInitialsSource = preg_replace('/[^A-Za-z0-9\s]+/', ' ', $schoolNameRaw);
        $sealInitials = '';
        foreach (preg_split('/\s+/', trim($sealInitialsSource)) as $word) {
            if ($word === '') {
                continue;
            }
            $sealInitials .= marksheetUpper(substr($word, 0, 1));
            if (strlen($sealInitials) >= 3) {
                break;
            }
        }
        if ($sealInitials === '') {
            $sealInitials = 'SPS';
        }

        $gradeScaleRows = [
            ['A1+', '91 - 100', '10'],
            ['A1', '81 - 90', '9'],
            ['B1', '71 - 80', '8'],
            ['B2', '61 - 70', '7'],
            ['C1', '51 - 60', '6'],
            ['C2', '41 - 50', '5'],
            ['D', '33 - 40', '4'],
            ['E', 'Below 33', '--'],
        ];

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marksheet - <?php echo marksheetEscape($studentName); ?></title>
    <style>
        :root {
            --navy: #102a5b;
            --navy-dark: #0b1f46;
            --navy-light: #1d4b8f;
            --gold: #c79d3b;
            --gold-soft: #ead8a3;
            --ink: #161b2a;
            --line: #9bb0cf;
            --muted: #52607c;
            --paper: #ffffff;
            --panel: #f8fbff;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @page {
            size: A4 portrait;
            margin: 4mm;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #f3f6fb;
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
        }
        body {
            padding: 12px;
        }
        .print-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .print-btn {
            border: 0;
            border-radius: 6px;
            padding: 9px 16px;
            background: var(--navy);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(16, 42, 91, 0.18);
        }
        .print-btn.secondary {
            background: #64748b;
        }
        .marksheet-shell {
            position: relative;
            width: 100%;
            max-width: 202mm;
            min-height: 289mm;
            margin: 0 auto;
            background:
                radial-gradient(circle at 50% 18%, rgba(16, 42, 91, 0.03), transparent 40%),
                linear-gradient(180deg, #ffffff 0%, #ffffff 100%);
            box-shadow:
                inset 0 0 0 1.2mm var(--navy),
                inset 0 0 0 2.0mm var(--gold),
                inset 0 0 0 2.8mm var(--navy),
                0 10px 28px rgba(17, 24, 39, 0.10);
            padding: 8.4mm 7.2mm 7.2mm;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .marksheet-shell::before {
            content: attr(data-watermark);
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            font-weight: 700;
            letter-spacing: 7px;
            color: rgba(16, 42, 91, 0.045);
            transform: rotate(-18deg);
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
        }
        .corner {
            position: absolute;
            width: 18mm;
            height: 18mm;
            border: 1.4px solid rgba(199, 157, 59, 0.95);
            pointer-events: none;
            z-index: 1;
        }
        .corner.tl {
            top: 4.2mm;
            left: 4.2mm;
            border-right: 0;
            border-bottom: 0;
            border-radius: 10mm 0 0 0;
        }
        .corner.tr {
            top: 4.2mm;
            right: 4.2mm;
            border-left: 0;
            border-bottom: 0;
            border-radius: 0 10mm 0 0;
        }
        .corner.bl {
            left: 4.2mm;
            bottom: 4.2mm;
            border-right: 0;
            border-top: 0;
            border-radius: 0 0 0 10mm;
        }
        .corner.br {
            right: 4.2mm;
            bottom: 4.2mm;
            border-left: 0;
            border-top: 0;
            border-radius: 0 0 10mm 0;
        }
        .corner::before,
        .corner::after {
            content: '';
            position: absolute;
            border: 1px solid rgba(199, 157, 59, 0.9);
            border-radius: 50%;
        }
        .corner::before {
            width: 10mm;
            height: 10mm;
            top: -1px;
            left: -1px;
        }
        .corner::after {
            width: 6mm;
            height: 6mm;
            top: 3mm;
            left: 3mm;
        }
        .marksheet-content {
            position: relative;
            z-index: 2;
        }
        .top-grid {
            display: grid;
            grid-template-columns: 24mm minmax(0, 1fr) 45mm;
            gap: 4mm;
            align-items: center;
        }
        .brand-logo {
            width: 24mm;
            height: 24mm;
            border: 1px solid rgba(16, 42, 91, 0.18);
            border-radius: 2mm;
            padding: 1mm;
            background: linear-gradient(180deg, #ffffff 0%, #f5f8ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .brand-fallback {
            width: 100%;
            height: 100%;
            border-radius: 1.5mm;
            background: linear-gradient(180deg, var(--navy) 0%, #132f6b 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: 700;
            line-height: 1.05;
            padding: 2mm;
            font-size: 9px;
            text-transform: uppercase;
        }
        .brand-copy {
            text-align: center;
            padding: 0 2mm;
        }
        .school-name {
            font-size: 26px;
            line-height: 1.05;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: var(--navy);
            text-transform: uppercase;
        }
        .school-meta {
            margin-top: 3.4mm;
            font-size: 11px;
            line-height: 1.45;
            color: #22304e;
        }
        .school-meta .line-1 {
            font-weight: 700;
            font-size: 11.5px;
            letter-spacing: 0.1px;
        }
        .marksheet-no {
            align-self: stretch;
            border: 1px solid rgba(16, 42, 91, 0.28);
            border-radius: 1mm;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 3mm 3mm 2.4mm;
            text-align: center;
        }
        .marksheet-no-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
            line-height: 1;
        }
        .marksheet-no-value {
            margin-top: 1.6mm;
            font-size: 13px;
            font-weight: 700;
            color: #b3202f;
            letter-spacing: 0.2px;
        }
        .barcode {
            margin-top: 2mm;
            display: flex;
            justify-content: center;
        }
        .barcode svg {
            display: block;
            width: 100%;
            max-width: 100%;
            height: 20mm;
        }
        .ribbon-title {
            margin: 5mm auto 2.8mm;
            width: fit-content;
            position: relative;
            background: linear-gradient(180deg, #13326f 0%, var(--navy) 100%);
            color: #fff;
            border: 1px solid var(--gold);
            border-radius: 999px;
            padding: 2.1mm 9mm;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
        }
        .ribbon-title::before,
        .ribbon-title::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 22mm;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        .ribbon-title::before {
            left: -26mm;
        }
        .ribbon-title::after {
            right: -26mm;
        }
        .title-flourish {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4mm;
            margin-bottom: 1mm;
            color: var(--gold);
        }
        .title-flourish .line {
            width: 20mm;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        .title-flourish .diamond {
            width: 2.6mm;
            height: 2.6mm;
            background: var(--gold);
            transform: rotate(45deg);
        }
        .exam-heading {
            text-align: center;
            margin-top: 1mm;
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .academic-session {
            text-align: center;
            margin-top: 1.2mm;
            font-size: 12px;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
        }
        .student-panel {
            margin-top: 4mm;
            border: 1px solid rgba(16, 42, 91, 0.55);
            border-radius: 2mm;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            padding: 3mm;
        }
        .student-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 37mm;
            gap: 3mm;
        }
        .student-details {
            display: grid;
            gap: 1.6mm;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 50% 4% 46%;
            align-items: baseline;
            gap: 0.6mm;
            font-size: 10.5px;
            line-height: 1.2;
            color: var(--ink);
        }
        .detail-row .label {
            font-weight: 700;
        }
        .detail-row .colon {
            text-align: center;
            font-weight: 700;
        }
        .detail-row .value {
            font-weight: 700;
            text-transform: uppercase;
            word-break: break-word;
        }
        .photo-card {
            grid-column: 3;
            grid-row: 1 / span 5;
            border: 1px solid rgba(16, 42, 91, 0.45);
            border-radius: 1mm;
            background: linear-gradient(180deg, #edf4ff 0%, #ffffff 100%);
            padding: 2mm;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 58mm;
        }
        .photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.8mm;
            display: block;
        }
        .photo-fallback {
            width: 100%;
            height: 100%;
            min-height: 54mm;
            border-radius: 0.8mm;
            background: linear-gradient(180deg, var(--navy) 0%, #1d3f7f 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4mm;
        }
        .marks-table-wrap {
            margin-top: 4mm;
            border: 1px solid rgba(16, 42, 91, 0.55);
            border-radius: 2mm;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .marks-table th,
        .marks-table td {
            border: 1px solid rgba(16, 42, 91, 0.35);
            padding: 1.7mm 1.6mm;
            font-size: 9.7px;
            line-height: 1.12;
            text-align: center;
            word-break: normal;
            overflow-wrap: normal;
        }
        .marks-table thead th {
            background: linear-gradient(180deg, #163773 0%, var(--navy) 100%);
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
        }
        .marks-table thead .group-row th {
            font-size: 10px;
            letter-spacing: 0.2px;
        }
        .marks-table thead .sub-row th {
            font-size: 9px;
            letter-spacing: 0.1px;
        }
        .marks-table tbody tr:nth-child(even) td {
            background: #f8fbff;
        }
        .marks-table td.subject {
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
        }
        .marks-table td.code,
        .marks-table td.full,
        .marks-table td.theory,
        .marks-table td.practical,
        .marks-table td.total,
        .marks-table td.grade,
        .marks-table td.point {
            font-weight: 700;
        }
        .marks-table .total-row td {
            background: #eaf1ff !important;
            font-weight: 700;
        }
        .summary-grid {
            margin-top: 3.6mm;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 3mm;
        }
        .summary-box,
        .scale-box {
            border: 1px solid rgba(16, 42, 91, 0.55);
            border-radius: 2mm;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            padding: 2.8mm 3mm;
        }
        .summary-rows {
            display: grid;
            gap: 1.5mm;
        }
        .summary-row {
            display: grid;
            grid-template-columns: 60% 4% 36%;
            gap: 0.8mm;
            align-items: baseline;
            font-size: 11px;
            line-height: 1.2;
        }
        .summary-row .label {
            font-weight: 700;
            white-space: nowrap;
        }
        .summary-row .colon {
            text-align: center;
            font-weight: 700;
        }
        .summary-row .value {
            font-weight: 700;
            text-transform: uppercase;
        }
        .summary-row .value.pass {
            color: #166534;
        }
        .summary-title,
        .scale-title {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
            margin-bottom: 1.8mm;
        }
        .scale-table th,
        .scale-table td {
            border: 1px solid rgba(16, 42, 91, 0.28);
            padding: 1.6mm 1.4mm;
            font-size: 9.3px;
            text-align: center;
            line-height: 1.1;
        }
        .scale-table th {
            background: #eef4ff;
            color: var(--navy);
            text-transform: uppercase;
        }
        .issue-row {
            margin-top: 3.2mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm;
            align-items: center;
        }
        .issue-date {
            font-size: 11px;
            font-weight: 700;
            color: var(--ink);
            text-transform: uppercase;
        }
        .issue-note {
            border: 1px solid rgba(16, 42, 91, 0.45);
            border-radius: 1.5mm;
            padding: 2.2mm 2.8mm;
            font-size: 9.5px;
            color: #334155;
            text-align: center;
            background: #f8fbff;
            line-height: 1.2;
        }
        .footer-row {
            margin-top: 3.6mm;
            display: grid;
            grid-template-columns: 23mm 1fr 34mm 1fr;
            gap: 3mm;
            align-items: end;
        }
        .qr-card {
            text-align: center;
            font-size: 8px;
            color: #334155;
        }
        .qr-card img {
            width: 23mm;
            height: 23mm;
            display: block;
            margin: 0 auto 1mm;
        }
        .qr-fallback {
            width: 23mm;
            height: 23mm;
            margin: 0 auto 1mm;
            border: 1px solid #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: 700;
            color: #111827;
            background: #fff;
        }
        .signature-card {
            text-align: center;
            min-height: 28mm;
            display: flex;
            flex-direction: column;
            justify-content: end;
        }
        .signature-image {
            height: 12mm;
            object-fit: contain;
            display: block;
            margin: 0 auto 1.1mm;
        }
        .signature-line {
            border-top: 1px solid #233656;
            padding-top: 1.6mm;
            font-size: 9.8px;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
            line-height: 1.15;
        }
        .signature-role {
            margin-top: 0.6mm;
            font-size: 8.8px;
            line-height: 1.12;
            color: #334155;
        }
        .seal-card {
            width: 34mm;
            height: 34mm;
            margin: 0 auto;
            border-radius: 50%;
            border: 3px double var(--navy);
            background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f9fbff 52%, #eef4ff 100%);
            color: var(--navy);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2mm;
            box-shadow: inset 0 0 0 1px rgba(199, 157, 59, 0.35);
        }
        .seal-card .seal-top,
        .seal-card .seal-bottom {
            font-size: 6.8px;
            line-height: 1.05;
            font-weight: 700;
            text-transform: uppercase;
        }
        .seal-card .seal-mid {
            margin: 0.8mm 0;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.05;
            text-transform: uppercase;
        }
        .footer-disclaimer {
            margin-top: 3.8mm;
            border: 1px solid rgba(16, 42, 91, 0.45);
            border-radius: 1.5mm;
            background: #f8fbff;
            padding: 2.2mm 3mm;
            text-align: center;
            font-size: 9px;
            line-height: 1.2;
            color: #334155;
        }
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .print-actions {
                display: none;
            }
            .marksheet-shell {
                max-width: none;
                width: 202mm;
                min-height: 0;
                padding: 6.2mm 5.6mm 5.5mm;
                box-shadow:
                    inset 0 0 0 1.2mm var(--navy),
                    inset 0 0 0 2.0mm var(--gold),
                    inset 0 0 0 2.8mm var(--navy);
            }
            .marksheet-shell::before {
                font-size: 44px;
            }
            .top-grid {
                gap: 1.2mm;
            }
            .brand-logo {
                width: 20mm;
                height: 20mm;
            }
            .school-name {
                font-size: 18px;
            }
            .school-meta {
                margin-top: 1.4mm;
                font-size: 8.2px;
                line-height: 1.15;
            }
            .marksheet-no {
                padding: 1.8mm 2mm 1.6mm;
            }
            .marksheet-no-value {
                margin-top: 1mm;
                font-size: 10px;
            }
            .barcode svg {
                height: 14mm;
            }
            .ribbon-title {
                margin: 1.6mm auto 1.1mm;
                padding: 1.1mm 7.6mm;
                font-size: 10.6px;
            }
            .ribbon-title::before,
            .ribbon-title::after {
                width: 14mm;
            }
            .title-flourish {
                gap: 3mm;
                margin-bottom: 0.2mm;
            }
            .exam-heading {
                margin-top: 0.2mm;
                font-size: 9.8px;
            }
            .academic-session {
                margin-top: 0.4mm;
                font-size: 8.2px;
            }
            .student-panel {
                margin-top: 1.4mm;
                padding: 1.2mm;
            }
            .student-grid {
                gap: 1mm;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 29mm;
            }
            .student-details {
                gap: 0.8mm;
            }
            .detail-row {
                font-size: 7.4px;
                line-height: 0.95;
            }
            .photo-card {
                min-height: 38mm;
                padding: 1mm;
            }
            .marks-table th,
            .marks-table td {
                padding: 0.9mm 0.9mm;
                font-size: 7.2px;
            }
            .marks-table thead .group-row th {
                font-size: 7.6px;
            }
            .marks-table thead .sub-row th {
                font-size: 7px;
            }
            .summary-grid {
                margin-top: 1.2mm;
                gap: 1.6mm;
                grid-template-columns: 1.04fr 0.96fr;
            }
            .summary-box,
            .scale-box {
                padding: 1.4mm 1.8mm;
            }
            .summary-title,
            .scale-title {
                margin-bottom: 0.5mm;
                font-size: 8px;
            }
            .summary-rows {
                gap: 0.6mm;
            }
            .summary-row {
                font-size: 7.4px;
                line-height: 0.95;
            }
            .scale-table th,
            .scale-table td {
                padding: 0.75mm 0.8mm;
                font-size: 7px;
            }
            .issue-row {
                margin-top: 1mm;
                gap: 1.4mm;
            }
            .issue-date {
                font-size: 8px;
            }
            .issue-note {
                padding: 1.1mm 1.6mm;
                font-size: 6.8px;
            }
            .footer-row {
                margin-top: 1.3mm;
                gap: 1.2mm;
                grid-template-columns: 18mm 1fr 26mm 1fr;
            }
            .qr-card img,
            .qr-fallback {
                width: 18mm;
                height: 18mm;
            }
            .signature-card {
                min-height: 17mm;
            }
            .signature-image {
                height: 7mm;
            }
            .signature-line {
                padding-top: 0.8mm;
                font-size: 6.8px;
            }
            .signature-role {
                font-size: 6.4px;
            }
            .seal-card {
                width: 24mm;
                height: 24mm;
            }
            .seal-card .seal-top,
            .seal-card .seal-bottom {
                font-size: 4.8px;
            }
            .seal-card .seal-mid {
                font-size: 6px;
            }
            .footer-disclaimer {
                margin-top: 1mm;
                padding: 0.9mm 1.4mm;
                font-size: 6px;
                line-height: 1;
            }
        }
        @media (max-width: 900px) {
            .top-grid,
            .student-grid,
            .summary-grid,
            .issue-row,
            .footer-row {
                grid-template-columns: 1fr;
            }
            .photo-card {
                grid-column: auto;
                grid-row: auto;
                min-height: 60mm;
            }
        }
    </style>
</head>
<body>
    <?php if ($showToolbar): ?>
        <div class="print-actions">
            <button class="print-btn" onclick="window.print()">Print Mark Sheet</button>
            <button class="print-btn secondary" onclick="window.close()">Close</button>
        </div>
    <?php endif; ?>

    <div class="marksheet-shell" data-watermark="<?php echo marksheetEscape($schoolName); ?>">
        <span class="corner tl"></span>
        <span class="corner tr"></span>
        <span class="corner bl"></span>
        <span class="corner br"></span>

        <div class="marksheet-content">
            <div class="top-grid">
                <div class="brand-logo">
                    <?php if (!empty($schoolSettings['school_logo']) || !empty($schoolSettings['banner_logo'])): ?>
                        <img src="<?php echo marksheetEscape(getSchoolLogoSrc($schoolSettings['school_logo'] ?? '', $schoolSettings['banner_logo'] ?? '')); ?>" alt="School Logo">
                    <?php else: ?>
                        <div class="brand-fallback">
                            <?php
                                $initialsSource = preg_replace('/[^A-Za-z0-9\s]+/', ' ', $schoolNameRaw);
                                $initials = '';
                                foreach (preg_split('/\s+/', trim($initialsSource)) as $word) {
                                    if ($word === '') {
                                        continue;
                                    }
                                    $initials .= marksheetUpper(substr($word, 0, 1));
                                    if (strlen($initials) >= 3) {
                                        break;
                                    }
                                }
                                echo marksheetEscape($initials !== '' ? $initials : 'SCHOOL');
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="brand-copy">
                    <div class="school-name"><?php echo marksheetEscape($schoolName); ?></div>
                    <div class="school-meta">
                        <div class="line-1">
                            AFFILIATED TO CBSE, NEW DELHI | AFFILIATION NO.: <?php echo marksheetEscape($affiliationNo); ?>
                        </div>
                        <?php if ($schoolAddress !== ''): ?>
                            <div><?php echo marksheetEscape($schoolAddress); ?></div>
                        <?php endif; ?>
                        <div>
                            <?php if ($schoolPhone !== ''): ?>
                                Phone: <?php echo marksheetEscape($schoolPhone); ?>
                            <?php endif; ?>
                            <?php if ($schoolPhone !== '' && $schoolEmail !== ''): ?>
                                &nbsp;|&nbsp;
                            <?php endif; ?>
                            <?php if ($schoolEmail !== ''): ?>
                                Email: <?php echo marksheetEscape($schoolEmail); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="marksheet-no">
                    <div class="marksheet-no-label">Marksheet No.</div>
                    <div class="marksheet-no-value"><?php echo marksheetEscape($marksheetNo); ?></div>
                    <div class="barcode"><?php echo $marksheetBarcode; ?></div>
                </div>
            </div>

            <div class="ribbon-title">Statement of Marks</div>
            <div class="title-flourish" aria-hidden="true">
                <span class="line"></span>
                <span class="diamond"></span>
                <span class="line"></span>
            </div>
            <div class="exam-heading"><?php echo marksheetEscape($examHeading); ?></div>
            <div class="academic-session">ACADEMIC SESSION: <?php echo marksheetEscape($academicSession); ?></div>

            <div class="student-panel">
                <div class="student-grid">
                    <div class="student-details">
                        <div class="detail-row">
                            <div class="label">Name of Student</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($studentName); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Roll Number</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($rollNo); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Registration Number</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($registrationNo); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Father's Name</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($fatherName); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Mother's Name</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($motherName); ?></div>
                        </div>
                    </div>

                    <div class="student-details">
                        <div class="detail-row">
                            <div class="label">Date of Birth</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($dateOfBirth); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Class</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($classDisplay); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Section</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($sectionDisplay); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">School Code</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($schoolCode); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Date of First Admission</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($admissionDate); ?></div>
                        </div>
                    </div>

                    <div class="photo-card">
                        <?php if (!empty($studentPhotoSrc)): ?>
                            <img src="<?php echo marksheetEscape($studentPhotoSrc); ?>" alt="Student Photo">
                        <?php else: ?>
                            <div class="photo-fallback">Student Photo</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="marks-table-wrap">
                <table class="marks-table">
                    <thead>
                        <tr class="group-row">
                            <th rowspan="2" style="width: 10%;">Subject Code</th>
                            <th rowspan="2" style="width: 27%;">Subject Name</th>
                            <th rowspan="2" style="width: 9%;">Full Marks</th>
                            <th colspan="3" style="width: 34%;">Marks Obtained</th>
                            <th rowspan="2" style="width: 10%;">Grade</th>
                            <th rowspan="2" style="width: 10%;">Grade Point</th>
                        </tr>
                        <tr class="sub-row">
                            <th style="width: 10%;">Theory</th>
                            <th style="width: 13%;">Practical/Internal</th>
                            <th style="width: 11%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sno = 1; foreach ($marks as $mark): ?>
                            <?php
                                $subjectPercentage = floatval($mark['max_marks'] ?? 0) > 0
                                    ? round((floatval($mark['marks_obtained'] ?? 0) / floatval($mark['max_marks'] ?? 1)) * 100, 2)
                                    : 0;
                                $subjectGradeInfo = marksheetGradeInfo($subjectPercentage);
                                $subjectCode = marksheetText($mark['subject_code'] ?? '-');
                                $subjectName = marksheetUpper(marksheetText($mark['subject_name'] ?? '-'));
                                $subjectFullMarks = marksheetText($mark['max_marks'] ?? 0, '0');
                                $subjectObtained = marksheetText($mark['marks_obtained'] ?? 0, '0');
                            ?>
                            <tr>
                                <td class="code"><?php echo marksheetEscape($subjectCode); ?></td>
                                <td class="subject"><?php echo marksheetEscape($subjectName); ?></td>
                                <td class="full"><?php echo marksheetEscape($subjectFullMarks); ?></td>
                                <td class="theory">--</td>
                                <td class="practical">--</td>
                                <td class="total"><?php echo marksheetEscape($subjectObtained); ?></td>
                                <td class="grade"><?php echo marksheetEscape($subjectGradeInfo['label']); ?></td>
                                <td class="point"><?php echo marksheetEscape($subjectGradeInfo['point']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2" class="subject">TOTAL</td>
                            <td class="full"><?php echo marksheetEscape($totalMaxMarks); ?></td>
                            <td class="theory">--</td>
                            <td class="practical">--</td>
                            <td class="total"><?php echo marksheetEscape($totalMarks); ?></td>
                            <td class="grade"><?php echo marksheetEscape($overallGrade); ?></td>
                            <td class="point"><?php echo marksheetEscape($overallGradePoint); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-title">Result Summary</div>
                    <div class="summary-rows">
                        <div class="summary-row">
                            <div class="label">Total Marks Obtained</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape(number_format((float)$totalMarks, 0)); ?> / <?php echo marksheetEscape(number_format((float)$totalMaxMarks, 0)); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="label">Percentage</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape(number_format((float)$percentage, 2)); ?> %</div>
                        </div>
                        <div class="summary-row">
                            <div class="label">Division</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($division); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="label">Overall Grade</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($overallGrade); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="label">Result</div>
                            <div class="colon">:</div>
                            <div class="value <?php echo $result === 'PASS' ? 'pass' : ''; ?>"><?php echo marksheetEscape($result); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="label">Rank in School</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($rankInSchool); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="label">Attendance</div>
                            <div class="colon">:</div>
                            <div class="value"><?php echo marksheetEscape($attendanceValue); ?></div>
                        </div>
                    </div>
                </div>

                <div class="scale-box">
                    <div class="scale-title">Grade Scale</div>
                    <table class="scale-table">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Marks Range</th>
                                <th>Grade Point</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gradeScaleRows as $row): ?>
                                <tr>
                                    <td><?php echo marksheetEscape($row[0]); ?></td>
                                    <td><?php echo marksheetEscape($row[1]); ?></td>
                                    <td><?php echo marksheetEscape($row[2]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="issue-row">
                <div class="issue-date">Date of Issue : <?php echo marksheetEscape($generatedOn); ?></div>
                <div class="issue-note">
                    *This is a computer generated marksheet. No signature is required.
                </div>
            </div>

            <div class="footer-row">
                <div class="qr-card">
                    <?php if (!empty($qrCodeUrl)): ?>
                        <img src="<?php echo marksheetEscape($qrCodeUrl); ?>" alt="Scan to Verify">
                    <?php else: ?>
                        <div class="qr-fallback">QR</div>
                    <?php endif; ?>
                    <div>Scan to Verify</div>
                </div>

                <div class="signature-card">
                    <?php if (!empty($teacherSignatureSrc)): ?>
                        <img src="<?php echo marksheetEscape($teacherSignatureSrc); ?>" alt="Controller Signature" class="signature-image">
                    <?php endif; ?>
                    <div class="signature-line">Controller of Examination</div>
                    <div class="signature-role">Exam Controller</div>
                </div>

                <div class="seal-card" aria-label="School seal">
                    <div class="seal-top"><?php echo marksheetEscape($schoolNameRaw); ?></div>
                    <div class="seal-mid">ESTD.<br><?php echo marksheetEscape($sealYear); ?></div>
                    <div class="seal-bottom"><?php echo marksheetEscape($sealInitials); ?></div>
                </div>

                <div class="signature-card">
                    <?php if (!empty($principalSignatureSrc)): ?>
                        <img src="<?php echo marksheetEscape($principalSignatureSrc); ?>" alt="Principal Signature" class="signature-image">
                    <?php endif; ?>
                    <div class="signature-line">Principal</div>
                    <div class="signature-role"><?php echo marksheetEscape($schoolNameRaw); ?></div>
                </div>
            </div>

            <div class="footer-disclaimer">
                This Marksheet is issued to the student on the basis of marks secured by the student in the examination held in <?php echo marksheetEscape($examHeldLabel); ?>. Any tampering or alteration in this document will render it invalid.
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('generateMarkSheetPDF')) {
    function generateMarkSheetPDF($studentId, $examId) {
        $sheetData = getMarkSheetData($studentId, $examId);

        if (!$sheetData) {
            die('No marks found for this student and exam');
        }

        $student = $sheetData['student'];
        $exam = $sheetData['exam'];
        $marks = $sheetData['marks'];
        $totalMarks = $sheetData['totalMarks'];
        $totalMaxMarks = $sheetData['totalMaxMarks'];
        $totalPassMarks = $sheetData['totalPassMarks'];
        $percentage = $sheetData['percentage'];
        $overallGrade = $sheetData['overallGrade'];
        $result = $sheetData['result'];

        studentPortalEnsureSchema();

        $schoolSettings = getSchoolSettings();
        $issueDate = date('Y-m-d');
        $payload = studentPortalBuildDocumentPayload($student, $exam, $schoolSettings, $issueDate, '');
        $payload['marksheet'] = [
            'total_marks' => $totalMarks,
            'total_max_marks' => $totalMaxMarks,
            'total_pass_marks' => $totalPassMarks,
            'percentage' => $percentage,
            'grade' => $overallGrade,
            'result' => $result,
        ];
        $payload['marks'] = $marks;

        studentPortalSaveDocument([
            'student_id' => intval($student['student_id'] ?? 0),
            'document_type' => 'marksheet',
            'document_title' => 'Marksheet - ' . ($student['student_name'] ?? '') . ' - ' . ($exam['exam_name'] ?? ''),
            'exam_id' => intval($exam['exam_id'] ?? 0),
            'issue_date' => $issueDate,
            'remarks' => '',
            'visible_to_student' => 1,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'generated_by' => intval(getCurrentUser()['user_id'] ?? 0),
        ]);

        $html = generateMarkSheetHTML($student, $exam, $marks, $schoolSettings, $totalMarks, $totalMaxMarks, $totalPassMarks, $percentage, $overallGrade, $result);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
