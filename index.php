<?php
/**
 * School Students and Fees Management System
 * Main Entry Point
 *
 * @author Your Name
 * @version 1.0.0
 */

// Include configuration (handles session start and database)
require_once 'config/config.php';

// Check if user is logged in
if (isset($_SESSION['user_id']) && $_SESSION['is_logged_in'] === true) {
    // Redirect to dashboard
    header('Location: modules/dashboard/index.php');
    exit();
} else {
    // Redirect to login page
    header('Location: modules/auth/login.php');
    exit();
}
?>
