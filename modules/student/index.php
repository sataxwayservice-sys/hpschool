<?php
require_once '../../config/config.php';

if (isLoggedIn() && (($user = getCurrentUser()) && ($user['role'] ?? '') === 'student')) {
    redirect(APP_URL . '/modules/student/dashboard.php');
}

redirect(APP_URL . '/modules/student/login.php');
