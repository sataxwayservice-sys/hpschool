<?php
/**
 * HTML to PDF export helpers using installed Chrome in headless mode.
 */

if (!function_exists('pdfExportSanitizeFilename')) {
    function pdfExportSanitizeFilename($filename, $fallback = 'document') {
        $filename = trim((string) $filename);
        if ($filename === '') {
            $filename = $fallback;
        }

        $filename = preg_replace('/[^\w\s.-]+/u', '', $filename);
        $filename = preg_replace('/\s+/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '._-');

        return $filename !== '' ? $filename : $fallback;
    }
}

if (!function_exists('pdfExportQuotePath')) {
    function pdfExportQuotePath($path) {
        $path = str_replace('"', '\\"', (string) $path);
        return '"' . $path . '"';
    }
}

if (!function_exists('pdfExportFindChromePath')) {
    function pdfExportFindChromePath() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $envChrome = trim((string) getenv('CHROME_PATH'));
        $candidates = [];
        if ($envChrome !== '') {
            $candidates[] = $envChrome;
        }

        $candidates = array_merge($candidates, [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            trim((string) getenv('USERPROFILE')) . '\\AppData\\Local\\Google\\Chrome\\Application\\chrome.exe',
        ]);

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && file_exists($candidate)) {
                $cached = $candidate;
                return $cached;
            }
        }

        $cached = '';
        return $cached;
    }
}

if (!function_exists('pdfExportBuildFileUrl')) {
    function pdfExportBuildFileUrl($path) {
        $path = str_replace('\\', '/', (string) $path);
        if (preg_match('/^([A-Za-z]:)(\/.*)$/', $path, $matches)) {
            $drive = $matches[1];
            $segments = array_filter(explode('/', trim($matches[2], '/')), 'strlen');
            $segments = array_map('rawurlencode', $segments);
            return 'file:///' . $drive . '/' . implode('/', $segments);
        }

        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        $segments = array_map('rawurlencode', $segments);
        return 'file:///' . implode('/', $segments);
    }
}

if (!function_exists('pdfExportGenerateFromHtml')) {
    function pdfExportGenerateFromHtml($html, $downloadName = 'document.pdf', array $options = []) {
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

        $token = uniqid('admit_', true);
        $htmlPath = $tempRoot . '/' . $token . '.html';
        $pdfPath = $tempRoot . '/' . $token . '.pdf';

        $htmlWrapper = $looksLikeFullDocument
            ? $trimmedHtml
            : "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n</head>\n<body>\n" . $html . "\n</body>\n</html>";
        if (@file_put_contents($htmlPath, $htmlWrapper) === false) {
            return [
                'success' => false,
                'message' => 'Unable to write temporary HTML file for PDF export.',
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
            '--virtual-time-budget=2500',
            '--print-to-pdf=' . pdfExportQuotePath($pdfPath),
            '--print-to-pdf-no-header',
            pdfExportQuotePath($url),
        ];

        $command = implode(' ', $chromeArgs);
        $output = [];
        $exitCode = 0;
        @exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($pdfPath) || filesize($pdfPath) === 0) {
            @unlink($htmlPath);
            @unlink($pdfPath);
            return [
                'success' => false,
                'message' => 'PDF generation failed.',
                'output' => $output,
                'command' => $command,
            ];
        }

        return [
            'success' => true,
            'pdf_path' => $pdfPath,
            'html_path' => $htmlPath,
            'download_name' => pdfExportSanitizeFilename($downloadName, 'document') . '.pdf',
            'output' => $output,
        ];
    }
}

if (!function_exists('pdfExportStreamPdfFile')) {
    function pdfExportStreamPdfFile($pdfPath, $downloadName = 'document.pdf', $cleanup = true, $htmlPath = '') {
        if (!file_exists($pdfPath) || filesize($pdfPath) === 0) {
            return false;
        }

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . pdfExportSanitizeFilename($downloadName, 'document') . '.pdf"');
            header('Content-Length: ' . filesize($pdfPath));
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        readfile($pdfPath);

        if ($cleanup) {
            @unlink($pdfPath);
            if ($htmlPath !== '') {
                @unlink($htmlPath);
            }
        }

        return true;
    }
}

if (!function_exists('pdfExportDownloadHtml')) {
    function pdfExportDownloadHtml($html, $downloadName = 'document.pdf', array $options = []) {
        $result = pdfExportGenerateFromHtml($html, $downloadName, $options);
        if (!$result['success']) {
            return $result;
        }

        $streamed = pdfExportStreamPdfFile(
            $result['pdf_path'],
            $result['download_name'] ?? $downloadName,
            true,
            $result['html_path'] ?? ''
        );

        if (!$streamed) {
            return [
                'success' => false,
                'message' => 'Unable to stream generated PDF.',
            ];
        }

        return [
            'success' => true,
        ];
    }
}
