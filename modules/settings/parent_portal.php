<?php
/**
 * Parent Portal Management
 * Super admin can manage parent access, payment settings, and announcements
 */

require_once '../../config/config.php';
require_once '../../includes/parent_portal.php';
require_once '../../includes/student_portal.php';

requireLogin();
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'super_admin') {
    $_SESSION['error_message'] = 'Access denied. Only Super Admin can manage student applications.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

header('Location: ' . APP_URL . '/modules/settings/student_portal.php');
exit();
