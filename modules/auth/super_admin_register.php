<?php
/**
 * Super Admin Registration
 * Registration is disabled. Use the dedicated super admin login page.
 */

require_once '../../config/config.php';

redirect(APP_URL . '/modules/auth/super_admin_login.php');

