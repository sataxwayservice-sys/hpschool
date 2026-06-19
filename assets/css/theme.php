<?php
/**
 * Dynamic Theme CSS
 * Generates premium theme variables and component colors from school settings.
 */

require_once '../../config/config.php';

header('Content-Type: text/css; charset=utf-8');

$settings = getSchoolSettings();

$primary = $settings['theme_primary_color'] ?? '#0f2f57';
$secondary = $settings['theme_secondary_color'] ?? '#334155';
$success = $settings['theme_success_color'] ?? '#0f7a4a';
$info = $settings['theme_info_color'] ?? '#0f6fa4';
$warning = $settings['theme_warning_color'] ?? '#b7791f';
$danger = $settings['theme_danger_color'] ?? '#b42318';

function darkenColor($hex, $percent) {
    $hex = str_replace('#', '', (string)$hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r - ($r * $percent / 100)));
    $g = max(0, min(255, $g - ($g * $percent / 100)));
    $b = max(0, min(255, $b - ($b * $percent / 100)));

    return '#' . str_pad(dechex((int)$r), 2, '0', STR_PAD_LEFT)
        . str_pad(dechex((int)$g), 2, '0', STR_PAD_LEFT)
        . str_pad(dechex((int)$b), 2, '0', STR_PAD_LEFT);
}

function lightenColor($hex, $percent) {
    $hex = str_replace('#', '', (string)$hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = min(255, $r + ((255 - $r) * $percent / 100));
    $g = min(255, $g + ((255 - $g) * $percent / 100));
    $b = min(255, $b + ((255 - $b) * $percent / 100));

    return '#' . str_pad(dechex((int)$r), 2, '0', STR_PAD_LEFT)
        . str_pad(dechex((int)$g), 2, '0', STR_PAD_LEFT)
        . str_pad(dechex((int)$b), 2, '0', STR_PAD_LEFT);
}

function hexToRgb($hex) {
    $hex = str_replace('#', '', (string)$hex);
    if (strlen($hex) !== 6) {
        return '15, 47, 87';
    }

    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}

$primaryDark = darkenColor($primary, 12);
$primaryLight = lightenColor($primary, 10);
$secondaryDark = darkenColor($secondary, 12);
$successDark = darkenColor($success, 12);
$infoDark = darkenColor($info, 12);
$warningDark = darkenColor($warning, 12);
$dangerDark = darkenColor($danger, 12);

?>
/*
 * Premium School Theme Colors
 * Generated dynamically from school settings
 * Change colors in School Settings
 */

:root {
    --app-primary: <?php echo $primary; ?>;
    --app-primary-dark: <?php echo $primaryDark; ?>;
    --app-primary-light: <?php echo $primaryLight; ?>;
    --app-secondary: <?php echo $secondary; ?>;
    --app-secondary-dark: <?php echo $secondaryDark; ?>;
    --app-success: <?php echo $success; ?>;
    --app-success-dark: <?php echo $successDark; ?>;
    --app-info: <?php echo $info; ?>;
    --app-info-dark: <?php echo $infoDark; ?>;
    --app-warning: <?php echo $warning; ?>;
    --app-warning-dark: <?php echo $warningDark; ?>;
    --app-danger: <?php echo $danger; ?>;
    --app-danger-dark: <?php echo $dangerDark; ?>;
    --app-primary-rgb: <?php echo hexToRgb($primary); ?>;
    --app-secondary-rgb: <?php echo hexToRgb($secondary); ?>;
}

body {
    color: #0f172a;
    background-color: #eef3f8;
}

a {
    color: var(--app-primary);
}

a:hover {
    color: var(--app-primary-dark);
}

.bg-primary,
.badge.bg-primary {
    background: linear-gradient(135deg, var(--app-primary) 0%, var(--app-primary-dark) 100%) !important;
    color: #fff;
}

.text-primary {
    color: var(--app-primary) !important;
}

.border-primary {
    border-color: var(--app-primary) !important;
}

.btn-primary {
    background: linear-gradient(135deg, var(--app-primary) 0%, var(--app-primary-dark) 100%);
    border-color: var(--app-primary-dark);
    box-shadow: 0 10px 24px rgba(<?php echo hexToRgb($primary); ?>, 0.18);
}

.btn-primary:hover,
.btn-primary:focus {
    background: linear-gradient(135deg, var(--app-primary-dark) 0%, #08162a 100%);
    border-color: #08162a;
}

.btn-secondary {
    background: linear-gradient(135deg, var(--app-secondary) 0%, var(--app-secondary-dark) 100%);
    border-color: var(--app-secondary-dark);
}

.btn-secondary:hover,
.btn-secondary:focus {
    background: linear-gradient(135deg, var(--app-secondary-dark) 0%, #1f2937 100%);
    border-color: #1f2937;
}

.bg-secondary {
    background: linear-gradient(135deg, var(--app-secondary) 0%, var(--app-secondary-dark) 100%) !important;
}

.text-secondary {
    color: var(--app-secondary) !important;
}

.btn-success {
    background: linear-gradient(135deg, var(--app-success) 0%, var(--app-success-dark) 100%);
    border-color: var(--app-success-dark);
}

.btn-success:hover,
.btn-success:focus {
    background: linear-gradient(135deg, var(--app-success-dark) 0%, #0a5f39 100%);
    border-color: #0a5f39;
}

.bg-success {
    background: linear-gradient(135deg, var(--app-success) 0%, var(--app-success-dark) 100%) !important;
}

.text-success {
    color: var(--app-success) !important;
}

.alert-success {
    background-color: <?php echo lightenColor($success, 84); ?>;
    border-color: <?php echo lightenColor($success, 48); ?>;
    color: <?php echo darkenColor($success, 30); ?>;
}

.btn-info {
    background: linear-gradient(135deg, var(--app-info) 0%, var(--app-info-dark) 100%);
    border-color: var(--app-info-dark);
    color: #fff;
}

.btn-info:hover,
.btn-info:focus {
    background: linear-gradient(135deg, var(--app-info-dark) 0%, #0a587f 100%);
    border-color: #0a587f;
}

.bg-info {
    background: linear-gradient(135deg, var(--app-info) 0%, var(--app-info-dark) 100%) !important;
}

.text-info {
    color: var(--app-info) !important;
}

.alert-info {
    background-color: <?php echo lightenColor($info, 84); ?>;
    border-color: <?php echo lightenColor($info, 48); ?>;
    color: <?php echo darkenColor($info, 28); ?>;
}

.btn-warning {
    background: linear-gradient(135deg, var(--app-warning) 0%, var(--app-warning-dark) 100%);
    border-color: var(--app-warning-dark);
    color: #fff;
}

.btn-warning:hover,
.btn-warning:focus {
    background: linear-gradient(135deg, var(--app-warning-dark) 0%, #8f5c16 100%);
    border-color: #8f5c16;
}

.bg-warning {
    background: linear-gradient(135deg, var(--app-warning) 0%, var(--app-warning-dark) 100%) !important;
}

.text-warning {
    color: var(--app-warning) !important;
}

.alert-warning {
    background-color: <?php echo lightenColor($warning, 86); ?>;
    border-color: <?php echo lightenColor($warning, 50); ?>;
    color: <?php echo darkenColor($warning, 30); ?>;
}

.btn-danger {
    background: linear-gradient(135deg, var(--app-danger) 0%, var(--app-danger-dark) 100%);
    border-color: var(--app-danger-dark);
}

.btn-danger:hover,
.btn-danger:focus {
    background: linear-gradient(135deg, var(--app-danger-dark) 0%, #8d1b12 100%);
    border-color: #8d1b12;
}

.bg-danger {
    background: linear-gradient(135deg, var(--app-danger) 0%, var(--app-danger-dark) 100%) !important;
}

.text-danger {
    color: var(--app-danger) !important;
}

.alert-danger {
    background-color: <?php echo lightenColor($danger, 86); ?>;
    border-color: <?php echo lightenColor($danger, 50); ?>;
    color: <?php echo darkenColor($danger, 28); ?>;
}

.navbar {
    background: linear-gradient(135deg, var(--app-primary) 0%, var(--app-primary-dark) 100%) !important;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.16);
}

.navbar .nav-link {
    color: rgba(255, 255, 255, 0.88) !important;
}

.navbar .nav-link:hover,
.navbar .nav-link:focus,
.navbar .nav-link.active {
    color: #fff !important;
}

.dropdown-menu {
    border-color: #d8e3ef;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
}

.dropdown-item:active {
    background-color: var(--app-primary);
}

.card-header.bg-primary,
.card-header.bg-success,
.card-header.bg-info,
.card-header.bg-warning,
.card-header.bg-danger {
    color: #fff;
}

.table thead th {
    background-color: #f3f7fc;
    color: #334155;
}

.table-hover tbody tr:hover {
    background-color: #f8fbff;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--app-primary);
    box-shadow: 0 0 0 0.18rem rgba(<?php echo hexToRgb($primary); ?>, 0.14);
}

.pagination .page-link {
    color: var(--app-primary);
}

.pagination .page-item.active .page-link {
    background-color: var(--app-primary);
    border-color: var(--app-primary);
}

.badge.bg-success,
.badge.bg-info,
.badge.bg-danger {
    color: #fff;
}

.badge.bg-warning {
    color: #1f2937 !important;
}

.dashboard-card:hover {
    box-shadow: 0 22px 60px rgba(15, 23, 42, 0.14);
}

.progress-bar {
    background-color: var(--app-primary);
}

.progress-bar.bg-success {
    background-color: var(--app-success) !important;
}

.progress-bar.bg-info {
    background-color: var(--app-info) !important;
}

.progress-bar.bg-warning {
    background-color: var(--app-warning) !important;
}

.progress-bar.bg-danger {
    background-color: var(--app-danger) !important;
}

::-webkit-scrollbar-thumb {
    background: var(--app-primary);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--app-primary-dark);
}

.list-group-item-primary {
    background-color: <?php echo lightenColor($primary, 84); ?>;
    color: var(--app-primary-dark);
}

.list-group-item-primary.list-group-item-action:hover {
    background-color: <?php echo lightenColor($primary, 76); ?>;
}

@media print {
    .btn-primary,
    .bg-primary,
    .navbar {
        background-color: var(--app-primary) !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
