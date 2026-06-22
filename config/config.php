<?php
/**
 * Main Configuration File
 * School Students and Fees Management System
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'School Dashboard');
}

// Application Settings
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('SCHOOL_NAME', APP_NAME); // Neutral default label when a school has not configured branding yet

// Public company branding shown on registration and vendor-facing pages.
// Update these values to match your company name, logo, and contact details.
if (!defined('COMPANY_NAME')) {
    define('COMPANY_NAME', APP_NAME);
}
if (!defined('COMPANY_TAGLINE')) {
    define('COMPANY_TAGLINE', 'School ERP Solutions');
}
if (!defined('COMPANY_ADDRESS')) {
    define('COMPANY_ADDRESS', '');
}
if (!defined('COMPANY_PHONE')) {
    define('COMPANY_PHONE', '');
}
if (!defined('COMPANY_EMAIL')) {
    define('COMPANY_EMAIL', '');
}
if (!defined('COMPANY_WEBSITE')) {
    define('COMPANY_WEBSITE', '');
}
if (!defined('COMPANY_LOGO')) {
    define('COMPANY_LOGO', '/assets/uploads/logos/company-logo.png');
}

if (!function_exists('isLocalRequest')) {
    function isLocalRequest() {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
        if ($host === '') {
            return PHP_SAPI === 'cli';
        }

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_starts_with($host, 'localhost:')
            || str_starts_with($host, '127.0.0.1:');
    }
}

if (!function_exists('isSecureRequest')) {
    function isSecureRequest() {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return (!empty($_SERVER['HTTPS']) && $https !== 'off') || $forwardedProto === 'https';
    }
}

if (!function_exists('resolveAppUrl')) {
    function resolveAppBasePath() {
        $serverPath = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $serverPath = parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
        } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
            $serverPath = (string)$_SERVER['SCRIPT_NAME'];
        } elseif (!empty($_SERVER['PHP_SELF'])) {
            $serverPath = (string)$_SERVER['PHP_SELF'];
        }

        $serverPath = str_replace('\\', '/', $serverPath);

        $markers = [
            '/modules/',
            '/assets/',
            '/ajax/',
            '/api/',
            '/config/',
            '/database/',
            '/includes/',
            '/cron/',
            '/migrations/',
            '/vendor/',
        ];

        foreach ($markers as $marker) {
            $markerPos = strpos($serverPath, $marker);
            if ($markerPos !== false) {
                return rtrim(substr($serverPath, 0, $markerPos), '/');
            }
        }

        if ($serverPath !== '' && preg_match('/\.php$/i', $serverPath)) {
            $basePath = rtrim(str_replace('\\', '/', dirname($serverPath)), '/');
            if ($basePath === '/' || $basePath === '.' ) {
                return '';
            }
            return $basePath;
        }

        $basePath = rtrim($serverPath, '/');
        if ($basePath === '/' || $basePath === '.') {
            return '';
        }

        return $basePath;
    }

    function resolveAppUrl() {
        $fallback = 'http://localhost:8080/account3';
        $envUrl = getenv('APP_URL');
        if (!empty($envUrl)) {
            return rtrim($envUrl, '/');
        }

        if (PHP_SAPI === 'cli') {
            return $fallback;
        }

        $scheme = 'http';
        if (isSecureRequest()) {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        if ($host === '') {
            return $fallback;
        }

        $basePath = resolveAppBasePath();
        return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
    }
}

define('APP_URL', resolveAppUrl());

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', isLocalRequest() ? '1' : '0');
ini_set('log_errors', '1');
ini_set('html_errors', '0');

// Session Configuration (only if session not started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isSecureRequest() ? '1' : '0');
    @ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// File Upload Settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png']);
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('STUDENT_PHOTO_PATH', UPLOAD_PATH . 'students/');
define('STUDENT_APPLICATION_PATH', UPLOAD_PATH . 'student_applications/');
define('STUDENT_APPLICATION_PHOTO_PATH', STUDENT_APPLICATION_PATH . 'photos/');
define('STUDENT_APPLICATION_DOC_PATH', STUDENT_APPLICATION_PATH . 'documents/');
define('LOGO_PATH', UPLOAD_PATH . 'logos/');
define('SIGNATURE_PATH', UPLOAD_PATH . 'signatures/');
define('AD_PATH', UPLOAD_PATH . 'ads/');

// Pagination
define('RECORDS_PER_PAGE', 20);

// Currency
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '₹');
}

// Date Format
define('DATE_FORMAT', 'd-m-Y');
define('DATETIME_FORMAT', 'd-m-Y H:i:s');

// Security
define('ENCRYPTION_KEY', '4954f9e2790d265ed41ed13c160c117e466e5c567cefe850fb8921c974acd070');
define('PASSWORD_MIN_LENGTH', 6);

// Include other config files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/firebase_config.php';

// Auto-include common functions
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/school_registration.php';
require_once BASE_PATH . '/includes/auth.php';

if (function_exists('ensureStudentSchema')) {
    ensureStudentSchema();
}

if (function_exists('ensureAttendanceSchema')) {
    ensureAttendanceSchema();
}

if (function_exists('trackNavigationHistory')) {
    trackNavigationHistory();
}

?>
