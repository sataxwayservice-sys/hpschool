<?php
/**
 * Public dashboard entry point.
 *
 * The hosting tunnel can receive requests to /dashboard/ at the project root.
 * Redirect those requests into the application entry flow instead of showing
 * the Apache/XAMPP default dashboard page.
 */

header('Location: ../');
exit();
