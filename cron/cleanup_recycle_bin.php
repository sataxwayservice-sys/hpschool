<?php
/**
 * Recycle Bin Cleanup Disabled
 * Items are kept until they are deleted manually from the Recycle Bin screen.
 *
 * This script now intentionally performs no automatic deletion.
 */

// Include config
require_once dirname(__DIR__) . '/config/config.php';

// Log file
$logFile = dirname(__DIR__) . '/logs/recycle_bin_cleanup.log';

// Ensure logs directory exists
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("Recycle bin auto-cleanup is disabled.");
logMessage("Items remain in the recycle bin until deleted manually.");
logMessage("=== No Action Taken ===");
?>
