<?php
/**
 * Month-wise Collection shortcut
 */

require_once '../../config/config.php';
requireLogin();
requirePermission('reports', 'view');

$settings = getSchoolSettings();
$startMonth = isset($settings['academic_year_start_month']) ? intval($settings['academic_year_start_month']) : 4;
if ($startMonth < 1 || $startMonth > 12) {
    $startMonth = 4;
}

$today = new DateTime();
$currentYear = intval($today->format('Y'));
$currentMonth = intval($today->format('n'));
$startYear = ($currentMonth >= $startMonth) ? $currentYear : ($currentYear - 1);
$fromDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
$toDate = $today->format('Y-m-d');

header('Location: date_wise_collection.php?' . http_build_query([
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'group_by' => 'monthly',
]));
exit();
