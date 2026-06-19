<?php
/**
 * Today's Collection shortcut
 */

require_once '../../config/config.php';
requireLogin();
requirePermission('reports', 'view');

$today = date('Y-m-d');
header('Location: date_wise_collection.php?' . http_build_query([
    'from_date' => $today,
    'to_date' => $today,
    'group_by' => 'daily',
]));
exit();
