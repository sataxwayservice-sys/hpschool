<?php
/**
 * HTML to PDF export helpers using installed Chrome in headless mode.
 * Falls back to a browser-printable HTML view when Chrome or shell exec is
 * unavailable on the hosting server.
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

if (!function_exists('pdfExportCanUseChrome')) {
    function pdfExportCanUseChrome() {
        $chromePath = pdfExportFindChromePath();
        if ($chromePath === '') {
            return false;
        }

        $disabledFunctions = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        if (in_array('exec', $disabledFunctions, true)) {
            return false;
        }

        return function_exists('exec') && is_callable('exec');
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

if (!function_exists('pdfExportResolveWindowSize')) {
    function pdfExportResolveWindowSize($paperSize = 'A4', $paperOrientation = 'portrait') {
        $paperSize = strtoupper(trim((string) $paperSize));
        $paperOrientation = strtolower(trim((string) $paperOrientation));
        if (!in_array($paperSize, ['A4', 'A5'], true)) {
            $paperSize = 'A4';
        }
        if (!in_array($paperOrientation, ['portrait', 'landscape'], true)) {
            $paperOrientation = $paperSize === 'A5' ? 'landscape' : 'portrait';
        }

        $dimensionsMm = $paperSize === 'A5'
            ? [148.0, 210.0]
            : [210.0, 297.0];

        if ($paperOrientation === 'landscape') {
            $dimensionsMm = [$dimensionsMm[1], $dimensionsMm[0]];
        }

        $widthPx = max(1, (int) round(($dimensionsMm[0] / 25.4) * 96));
        $heightPx = max(1, (int) round(($dimensionsMm[1] / 25.4) * 96));

        return [$widthPx, $heightPx];
    }
}

if (!function_exists('pdfExportGenerateFromHtml')) {
    function pdfExportGenerateFromHtml($html, $downloadName = 'document.pdf', array $options = []) {
        if (!pdfExportCanUseChrome()) {
            return [
                'success' => false,
                'message' => 'Google Chrome was not found on this server.',
            ];
        }

        $chromePath = pdfExportFindChromePath();

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
        $windowWidth = intval($options['window_width'] ?? 0);
        $windowHeight = intval($options['window_height'] ?? 0);
        if (($windowWidth <= 0 || $windowHeight <= 0) && !empty($options['paper_size'])) {
            [$windowWidth, $windowHeight] = pdfExportResolveWindowSize(
                $options['paper_size'] ?? 'A4',
                $options['paper_orientation'] ?? 'portrait'
            );
        }

        $chromeArgs = [
            pdfExportQuotePath($chromePath),
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--allow-file-access-from-files',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=2500',
        ];

        if ($windowWidth > 0 && $windowHeight > 0) {
            $chromeArgs[] = '--window-size=' . intval($windowWidth) . ',' . intval($windowHeight);
        }

        $chromeArgs = array_merge($chromeArgs, [
            '--print-to-pdf=' . pdfExportQuotePath($pdfPath),
            '--print-to-pdf-no-header',
            pdfExportQuotePath($url),
        ]);

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

if (!function_exists('pdfExportBuildBrowserFallbackHtml')) {
    function pdfExportBuildBrowserFallbackHtml($html, $downloadName = 'document.pdf') {
        $html = (string) $html;
        $banner = '<div id="pdf-export-fallback-banner" style="position:sticky;top:0;z-index:99999;background:#0d6efd;color:#fff;padding:12px 16px;font:14px/1.4 Arial,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.15)">' .
            '<strong>Printable view:</strong> PDF generation is not available on this hosting server. Use the Print button or your browser\'s print dialog to save as PDF.' .
            '<button type="button" onclick="window.print()" style="float:right;border:0;background:#fff;color:#0d6efd;border-radius:4px;padding:6px 10px;cursor:pointer;font-weight:600">Print / Save PDF</button>' .
        '</div>';
        $script = '<script>window.addEventListener("load",function(){setTimeout(function(){try{window.print();}catch(e){}},400);});</script>';
        $style = '<style>@media print { #pdf-export-fallback-banner { display:none !important; } }</style>';

        if (preg_match('/<body\b[^>]*>/i', $html)) {
            $html = preg_replace('/<body\b([^>]*)>/i', '<body$1>' . $style . $banner, $html, 1);
            if (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1);
            } else {
                $html .= $script;
            }
        } else {
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">' . $style . '</head><body>' . $banner . $html . $script . '</body></html>';
        }

        return $html;
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
        if (!pdfExportCanUseChrome()) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }

            echo pdfExportBuildBrowserFallbackHtml($html, $downloadName);
            return [
                'success' => true,
                'fallback' => true,
                'message' => 'Browser-print fallback rendered because Chrome is unavailable on this server.',
            ];
        }

        $result = pdfExportGenerateFromHtml($html, $downloadName, $options);
        if (!$result['success']) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }

            echo pdfExportBuildBrowserFallbackHtml($html, $downloadName);
            return [
                'success' => true,
                'fallback' => true,
                'message' => $result['message'] ?? 'Browser-print fallback rendered.',
            ];
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
