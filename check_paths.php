<?php
/**
 * Path Diagnostic Tool
 * Check if all assets are accessible
 */

require_once 'config/config.php';

echo "<h2>📁 Path Diagnostic Tool</h2>";
echo "<hr>";

echo "<h3>Configuration</h3>";
echo "<strong>APP_URL:</strong> " . APP_URL . "<br>";
echo "<strong>BASE_PATH:</strong> " . BASE_PATH . "<br><br>";

echo "<h3>Asset Files Check</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>File</th><th>Path</th><th>Exists</th><th>URL</th><th>Test</th></tr>";

$files = [
    ['CSS', 'assets/css/style.css'],
    ['JS', 'assets/js/script.js'],
    ['Default Avatar', 'assets/images/default-avatar.png'],
];

foreach ($files as $file) {
    $filePath = BASE_PATH . '/' . $file[1];
    $url = APP_URL . '/' . $file[1];
    $exists = file_exists($filePath) ? '✅ Yes' : '❌ No';

    echo "<tr>";
    echo "<td><strong>{$file[0]}</strong></td>";
    echo "<td><code>" . htmlspecialchars($file[1]) . "</code></td>";
    echo "<td>{$exists}</td>";
    echo "<td><a href='{$url}' target='_blank'>Open</a></td>";
    echo "<td>";
    if (file_exists($filePath)) {
        echo "<span style='color: green;'>Accessible</span>";
    } else {
        echo "<span style='color: red;'>Missing</span>";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<br>";

echo "<h3>Test CSS Loading</h3>";
echo "<p>If the background below is GREEN, CSS is loading correctly:</p>";
echo "<div style='padding: 20px; background: red; color: white;' class='test-css'>
    This should be GREEN if CSS loads
</div>";

echo "<link rel='stylesheet' href='" . APP_URL . "/assets/css/style.css'>";
echo "<style>
    .test-css { background: green !important; }
</style>";

echo "<br><br>";
echo "<h3>What to Do</h3>";
echo "<ul>";
echo "<li>If files show ❌ No, they need to be created</li>";
echo "<li>If files exist but CSS doesn't load, clear browser cache (Ctrl+F5)</li>";
echo "<li>Try accessing: <a href='" . APP_URL . "/assets/css/style.css' target='_blank'>" . APP_URL . "/assets/css/style.css</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='index.php'>← Back to Login</a></p>";
?>
