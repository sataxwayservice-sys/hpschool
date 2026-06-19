<?php
/**
 * Firebase Configuration
 * Handles Authentication, Realtime Database, and SMS (via Cloud Functions)
 */

// Firebase Project Configuration
// IMPORTANT: Get these values from your Firebase Console:
// https://console.firebase.google.com/ -> Project Settings -> General

define('FIREBASE_API_KEY', 'YOUR_FIREBASE_API_KEY');
define('FIREBASE_AUTH_DOMAIN', 'your-project.firebaseapp.com');
define('FIREBASE_DATABASE_URL', 'https://your-project.firebaseio.com');
define('FIREBASE_PROJECT_ID', 'your-project-id');
define('FIREBASE_STORAGE_BUCKET', 'your-project.appspot.com');
define('FIREBASE_MESSAGING_SENDER_ID', 'your-sender-id');
define('FIREBASE_APP_ID', 'your-app-id');

// Firebase Admin SDK Service Account
// Download from: Firebase Console -> Project Settings -> Service Accounts
define('FIREBASE_SERVICE_ACCOUNT_PATH', BASE_PATH . '/config/firebase-service-account.json');

// Firebase Realtime Database Configuration
define('FIREBASE_BACKUP_ENABLED', true);
define('FIREBASE_AUTO_SYNC', true); // Auto sync on INSERT/UPDATE/DELETE

// SMS Configuration via Firebase Cloud Functions
define('FIREBASE_SMS_ENABLED', true);
define('FIREBASE_SMS_FUNCTION_URL', 'https://your-region-your-project.cloudfunctions.net/sendSMS');

/**
 * Firebase Configuration Array (for JavaScript)
 * Use this in your frontend
 */
function getFirebaseConfig() {
    return [
        'apiKey' => FIREBASE_API_KEY,
        'authDomain' => FIREBASE_AUTH_DOMAIN,
        'databaseURL' => FIREBASE_DATABASE_URL,
        'projectId' => FIREBASE_PROJECT_ID,
        'storageBucket' => FIREBASE_STORAGE_BUCKET,
        'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
        'appId' => FIREBASE_APP_ID
    ];
}

/**
 * Get Firebase Admin SDK Instance
 * Requires: composer require kreait/firebase-php
 */
function getFirebaseAdmin() {
    static $firebase = null;

    if ($firebase === null && file_exists(FIREBASE_SERVICE_ACCOUNT_PATH)) {
        try {
            require_once BASE_PATH . '/vendor/autoload.php';

            $factory = (new \Kreait\Firebase\Factory)
                ->withServiceAccount(FIREBASE_SERVICE_ACCOUNT_PATH)
                ->withDatabaseUri(FIREBASE_DATABASE_URL);

            $firebase = $factory->createDatabase();

        } catch (Exception $e) {
            error_log("Firebase Admin SDK Error: " . $e->getMessage());
            return null;
        }
    }

    return $firebase;
}

/**
 * Sync data to Firebase Realtime Database
 *
 * @param string $path Firebase path (e.g., 'students/123')
 * @param array $data Data to sync
 * @return bool
 */
function syncToFirebase($path, $data) {
    if (!FIREBASE_BACKUP_ENABLED) {
        return true;
    }

    try {
        $firebase = getFirebaseAdmin();

        if ($firebase === null) {
            return false;
        }

        $reference = $firebase->getReference($path);
        $reference->set($data);

        return true;

    } catch (Exception $e) {
        error_log("Firebase Sync Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete data from Firebase
 *
 * @param string $path Firebase path
 * @return bool
 */
function deleteFromFirebase($path) {
    if (!FIREBASE_BACKUP_ENABLED) {
        return true;
    }

    try {
        $firebase = getFirebaseAdmin();

        if ($firebase === null) {
            return false;
        }

        $reference = $firebase->getReference($path);
        $reference->remove();

        return true;

    } catch (Exception $e) {
        error_log("Firebase Delete Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS via Firebase Cloud Function
 *
 * @param string $mobile Mobile number
 * @param string $message SMS message
 * @return array{success:bool,message:string,response:?string,http_code:?int}
 */
function sendSMSViaFirebase($mobile, $message) {
    if (!FIREBASE_SMS_ENABLED) {
        return [
            'success' => false,
            'message' => 'SMS gateway is disabled in configuration.',
            'response' => null,
            'http_code' => null,
        ];
    }

    $functionUrl = trim((string) FIREBASE_SMS_FUNCTION_URL);
    $isPlaceholderUrl = (
        $functionUrl === '' ||
        stripos($functionUrl, 'your-region-your-project') !== false ||
        stripos($functionUrl, 'YOUR_') !== false
    );

    if ($isPlaceholderUrl || !filter_var($functionUrl, FILTER_VALIDATE_URL)) {
        return [
            'success' => false,
            'message' => 'SMS gateway is not configured. Please set a valid Firebase Cloud Function URL in config/firebase_config.php.',
            'response' => null,
            'http_code' => null,
        ];
    }

    try {
        $data = [
            'to' => $mobile,
            'message' => $message,
            'timestamp' => time()
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true,
                'timeout' => 15,
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($functionUrl, false, $context);
        $httpCode = null;
        if (!empty($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $headerLine, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }

        $isSuccess = $result !== false;
        if ($httpCode !== null) {
            $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        }

        if (!$isSuccess) {
            $messageText = 'Failed to send SMS.';
            if ($httpCode !== null) {
                $messageText .= ' HTTP ' . $httpCode . '.';
            }

            error_log('Firebase SMS Error: ' . $messageText . ' URL=' . $functionUrl);
            return [
                'success' => false,
                'message' => $messageText,
                'response' => is_string($result) ? $result : null,
                'http_code' => $httpCode,
            ];
        }

        return [
            'success' => true,
            'message' => 'SMS sent successfully.',
            'response' => $result,
            'http_code' => $httpCode,
        ];

    } catch (Exception $e) {
        error_log("Firebase SMS Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'response' => null,
            'http_code' => null,
        ];
    }
}

?>
