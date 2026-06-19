<?php
/**
 * Check if GD extension is enabled
 */

echo "<h2>PHP GD Extension Check</h2>";

if (extension_loaded('gd')) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>✅ SUCCESS! GD Extension is Enabled</h3>";
    echo "<p>Your PHP installation has GD support.</p>";

    $gdInfo = gd_info();
    echo "<h4>GD Information:</h4>";
    echo "<ul>";
    foreach ($gdInfo as $key => $value) {
        echo "<li><strong>$key:</strong> " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>❌ ERROR! GD Extension is NOT Enabled</h3>";
    echo "<p>Please enable GD extension in php.ini and restart Apache.</p>";
    echo "<h4>Steps to Enable:</h4>";
    echo "<ol>";
    echo "<li>Open XAMPP Control Panel</li>";
    echo "<li>Click 'Config' → 'PHP (php.ini)'</li>";
    echo "<li>Find the line: <code>;extension=gd</code></li>";
    echo "<li>Remove the semicolon: <code>extension=gd</code></li>";
    echo "<li>Save the file</li>";
    echo "<li>Restart Apache</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<h4>All Loaded Extensions:</h4>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>
