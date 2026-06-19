<?php
require_once '../../config/config.php';

$_SESSION['alert'] = [
    'message' => 'The parent portal has been replaced by the student portal.',
    'type' => 'info',
];

redirect(APP_URL . '/modules/student/login.php');
