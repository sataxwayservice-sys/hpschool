<?php
/**
 * Database Diagnostic Tool
 * Use this to identify database connection issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Diagnostic Tool</h1>";
echo "<hr>";

// Step 1: Check PHP Version
echo "<h2>1. PHP Environment</h2>";
echo "PHP Version: <strong>" . phpversion() . "</strong><br>";
$driver = getenv('DB_DRIVER') ?: 'mysql';
if ($driver === 'mysql') {
    echo "MySQLi Extension: <strong>" . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not Loaded') . "</strong><br>";
} else {
    echo "PDO Extension: <strong>" . (extension_loaded('pdo') ? '✅ Loaded' : '❌ Not Loaded') . "</strong><br>";
    echo "PDO_PGSQL: <strong>" . (extension_loaded('pdo_pgsql') ? '✅ Loaded' : '❌ Not Loaded') . "</strong><br>";
}
echo "<br>";

// Step 2: Check Config Files
echo "<h2>2. Configuration Files</h2>";

$configFile = __DIR__ . '/config/config.php';
$dbFile = __DIR__ . '/config/database.php';

echo "config.php exists: <strong>" . (file_exists($configFile) ? '✅ Yes' : '❌ No') . "</strong><br>";
echo "database.php exists: <strong>" . (file_exists($dbFile) ? '✅ Yes' : '❌ No') . "</strong><br>";
echo "<br>";

// Step 3: Load Config and Check Constants
echo "<h2>3. Loading Configuration</h2>";

try {
    require_once $configFile;
    echo "✅ config.php loaded successfully<br>";
    echo "<br>";
} catch (Exception $e) {
    echo "❌ Error loading config.php: " . $e->getMessage() . "<br>";
    echo "<br>";
    exit;
}

// Step 4: Check Database Constants
echo "<h2>4. Database Configuration</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Constant</th><th>Value</th><th>Status</th></tr>";

$dbConstants = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_CHARSET'];
foreach ($dbConstants as $const) {
    $defined = defined($const);
    $value = $defined ? constant($const) : 'Not defined';

    // Hide password partially
    if ($const === 'DB_PASS' && $defined) {
        $pass = constant($const);
        $value = empty($pass) ? '<em>Empty (no password)</em>' : str_repeat('*', strlen($pass));
    }

    $status = $defined ? '✅' : '❌';
    echo "<tr><td><strong>$const</strong></td><td>$value</td><td>$status</td></tr>";
}
echo "</table>";
echo "<br>";

// Step 5: Environment Detection
echo "<h2>5. Environment Detection</h2>";
echo "HTTP_HOST: <strong>" . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</strong><br>";
echo "DOCUMENT_ROOT: <strong>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "</strong><br>";
echo "Detected Environment: <strong>" .
    (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ? 'DEVELOPMENT (localhost)' : 'PRODUCTION') .
    "</strong><br>";
echo "<br>";

// Step 6: Test Database Connection
echo "<h2>6. Database Connection Test</h2>";

if (!defined('DB_HOST')) {
    echo "❌ <span style='color: red;'>Database constants not defined. Check config/database.php</span><br>";
    exit;
}

echo "Attempting to connect with:<br>";
echo "- Host: <strong>" . DB_HOST . "</strong><br>";
echo "- User: <strong>" . DB_USER . "</strong><br>";
echo "- Database: <strong>" . DB_NAME . "</strong><br>";
echo "<br>";

require_once __DIR__ . '/config/config.php';

try {
    $conn = getDbConnection();
    echo "✅ <span style='color: green;'><strong>Connection Successful!</strong></span><br>";
    echo "<br>";

    if (DB_DRIVER === 'mysql') {
        // Test charset
        $charset = $conn->character_set_name();
        echo "Character Set: <strong>$charset</strong><br>";
        echo "<br>";

        echo "<h3>7. Database Tables</h3>";
        $result = $conn->query("SHOW TABLES");

        if ($result) {
            $tableCount = $result->num_rows;
            echo "Tables found: <strong>$tableCount</strong><br><br>";

            if ($tableCount > 0) {
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr><th>#</th><th>Table Name</th></tr>";
                $i = 1;
                while ($row = $result->fetch_array()) {
                    echo "<tr><td>$i</td><td>" . $row[0] . "</td></tr>";
                    $i++;
                }
                echo "</table>";
            } else {
                echo "⚠️ <span style='color: orange;'>No tables found. You need to import database.sql</span><br>";
            }
        }

        $conn->close();
    } else {
        // Postgres
        echo "Character Set: <strong>UTF-8 (Postgres)</strong><br><br>";

        echo "<h3>7. Database Tables</h3>";
        $stmt = $conn->prepare("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $tableCount = is_array($rows) ? count($rows) : 0;

        if ($tableCount > 0) {
            echo "Tables found: <strong>$tableCount</strong><br><br>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>#</th><th>Table Name</th></tr>";
            $i = 1;
            foreach ($rows as $row) {
                echo "<tr><td>$i</td><td>" . htmlspecialchars($row[0]) . "</td></tr>";
                $i++;
            }
            echo "</table>";
        } else {
            echo "⚠️ <span style='color: orange;'>No tables found. You need to import Postgres schema</span><br>";
        }
    }

} catch (Exception $e) {
    echo "❌ <span style='color: red;'><strong>Connection Failed!</strong></span><br>";
    echo "Error Message: <strong>" . $e->getMessage() . "</strong><br>";
    echo "<br>";
    echo "<h3>💡 Troubleshooting:</h3>";
    echo "Check your database credentials and driver configuration in config/database.php or environment variables.<br>";
}

echo "<br><hr>";
echo "<h2>Summary</h2>";
echo "If connection successful: ✅ Your database is working fine!<br>";
echo "If connection failed: Follow the troubleshooting steps above<br>";
echo "<br>";
echo "<strong>Next Steps:</strong><br>";
echo "1. If MySQL not running: Start MySQL in XAMPP Control Panel<br>";
echo "2. If database doesn't exist: Create it and import database.sql<br>";
echo "3. If credentials wrong: Update config/database.php<br>";
echo "<br>";
echo "📖 <a href='PRODUCTION_DEPLOYMENT_GUIDE.md'>View Production Deployment Guide</a><br>";

?>
