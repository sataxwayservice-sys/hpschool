<?php
/**
 * Main Configuration File
 * School Students and Fees Management System
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'School Management System');
}

// Application Settings
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('SCHOOL_NAME', 'My School'); // School/Organization Name

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

if (!function_exists('resolveAppUrl')) {
    function resolveAppUrl() {
        $fallback = 'http://localhost:8080/account3';
        $envUrl = getenv('APP_URL');
        if (!empty($envUrl)) {
            return rtrim($envUrl, '/');
        }

        if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
            return $fallback;
        }

        $scheme = 'http';
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if (
            (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
            $forwardedProto === 'https'
        ) {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_HOST'];
        return $scheme . '://' . $host . '/account3';
    }
}

define('APP_URL', resolveAppUrl());

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session Configuration (only if session not started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
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
define('ENCRYPTION_KEY', 'your-secret-encryption-key-change-this'); // Change this!
define('PASSWORD_MIN_LENGTH', 6);

// Include other config files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/firebase_config.php';

// Auto-include common functions
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/school_registration.php';
require_once BASE_PATH . '/includes/auth.php';

if (function_exists('trackNavigationHistory')) {
    trackNavigationHistory();
}

?>
