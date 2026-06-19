<?php
/**
 * System Health Check
 * Quick visual check of system status
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 30px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 20px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #ddd;
            transition: all 0.3s;
        }
        .check-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .check-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .check-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .check-item.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 20px;
            flex-shrink: 0;
        }
        .success .icon {
            background: #28a745;
            color: white;
        }
        .error .icon {
            background: #dc3545;
            color: white;
        }
        .warning .icon {
            background: #ffc107;
            color: white;
        }
        .info {
            flex: 1;
        }
        .info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }
        .info p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .info code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
        }
        .actions {
            padding: 0 30px 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 5px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        .status-summary {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
        }
        .status-summary.all-good {
            background: #d4edda;
            color: #155724;
        }
        .status-summary.has-issues {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 System Health Check</h1>
            <p>School Management System Diagnostic</p>
        </div>

        <div class="content">
            <?php
            $issues = 0;
            $checks = [];

            // Check 1: PHP Version
            $phpVersion = phpversion();
            $phpOk = version_compare($phpVersion, '7.4.0', '>=');
            $checks[] = [
                'status' => $phpOk ? 'success' : 'warning',
                'icon' => $phpOk ? '✓' : '⚠',
                'title' => 'PHP Version',
                'message' => "PHP $phpVersion " . ($phpOk ? '(Compatible)' : '(Update recommended)')
            ];
            if (!$phpOk) $issues++;

            // Check 2: MySQLi Extension
            $mysqliLoaded = extension_loaded('mysqli');
            $checks[] = [
                'status' => $mysqliLoaded ? 'success' : 'error',
                'icon' => $mysqliLoaded ? '✓' : '✗',
                'title' => 'MySQLi Extension',
                'message' => $mysqliLoaded ? 'MySQLi extension is loaded and ready' : 'MySQLi extension is NOT loaded - install it in XAMPP'
            ];
            if (!$mysqliLoaded) $issues++;

            // Check 3: Config Files
            $configExists = file_exists(__DIR__ . '/config/config.php');
            $dbConfigExists = file_exists(__DIR__ . '/config/database.php');
            $bothExist = $configExists && $dbConfigExists;
            $checks[] = [
                'status' => $bothExist ? 'success' : 'error',
                'icon' => $bothExist ? '✓' : '✗',
                'title' => 'Configuration Files',
                'message' => $bothExist ? 'All configuration files present' : 'Missing configuration files'
            ];
            if (!$bothExist) $issues++;

            // Check 4: Database Connection
            if ($bothExist) {
                try {
                    require_once __DIR__ . '/config/config.php';

                    // Try to connect without dying
                    mysqli_report(MYSQLI_REPORT_OFF);
                    $testConn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                    if ($testConn->connect_error) {
                        $checks[] = [
                            'status' => 'error',
                            'icon' => '✗',
                            'title' => 'Database Connection',
                            'message' => 'Cannot connect to database<br>' .
                                        'Error: ' . $testConn->connect_error . '<br>' .
                                        'Using: Host=<code>' . DB_HOST . '</code>, User=<code>' . DB_USER . '</code>, DB=<code>' . DB_NAME . '</code>'
                        ];
                        $issues++;
                    } else {
                        // Check tables
                        $result = $testConn->query("SHOW TABLES");
                        $tableCount = $result ? $result->num_rows : 0;

                        if ($tableCount > 0) {
                            $checks[] = [
                                'status' => 'success',
                                'icon' => '✓',
                                'title' => 'Database Connection',
                                'message' => "Connected successfully! Found $tableCount tables in database <code>" . DB_NAME . "</code>"
                            ];
                        } else {
                            $checks[] = [
                                'status' => 'warning',
                                'icon' => '⚠',
                                'title' => 'Database Connection',
                                'message' => 'Connected but no tables found. You need to import database.sql'
                            ];
                            $issues++;
                        }
                        $testConn->close();
                    }
                } catch (Exception $e) {
                    $checks[] = [
                        'status' => 'error',
                        'icon' => '✗',
                        'title' => 'Database Connection',
                        'message' => 'Error: ' . $e->getMessage()
                    ];
                    $issues++;
                }
            }

            // Check 5: Upload Directories
            $uploadPath = __DIR__ . '/assets/uploads/';
            $studentPath = $uploadPath . 'students/';
            $logoPath = $uploadPath . 'logos/';

            $uploadExists = is_dir($uploadPath);
            $studentExists = is_dir($studentPath);
            $logoExists = is_dir($logoPath);
            $allExist = $uploadExists && $studentExists && $logoExists;

            $uploadWritable = $uploadExists && is_writable($uploadPath);
            $studentWritable = $studentExists && is_writable($studentPath);
            $logoWritable = $logoExists && is_writable($logoPath);
            $allWritable = $uploadWritable && $studentWritable && $logoWritable;

            if ($allExist && $allWritable) {
                $checks[] = [
                    'status' => 'success',
                    'icon' => '✓',
                    'title' => 'Upload Directories',
                    'message' => 'All upload directories exist and are writable'
                ];
            } else if ($allExist) {
                $checks[] = [
                    'status' => 'warning',
                    'icon' => '⚠',
                    'title' => 'Upload Directories',
                    'message' => 'Directories exist but may not be writable. Check permissions.'
                ];
                $issues++;
            } else {
                $checks[] = [
                    'status' => 'error',
                    'icon' => '✗',
                    'title' => 'Upload Directories',
                    'message' => 'Upload directories missing. Create: assets/uploads/students/ and assets/uploads/logos/'
                ];
                $issues++;
            }

            // Display all checks
            foreach ($checks as $check) {
                echo '<div class="check-item ' . $check['status'] . '">';
                echo '<div class="icon">' . $check['icon'] . '</div>';
                echo '<div class="info">';
                echo '<h3>' . $check['title'] . '</h3>';
                echo '<p>' . $check['message'] . '</p>';
                echo '</div>';
                echo '</div>';
            }

            // Summary
            if ($issues == 0) {
                echo '<div class="status-summary all-good">';
                echo '🎉 All Systems Operational! Your application is ready to use.';
                echo '</div>';
            } else {
                echo '<div class="status-summary has-issues">';
                echo '⚠️ Found ' . $issues . ' issue(s) that need attention';
                echo '</div>';
            }
            ?>
        </div>

        <div class="actions">
            <a href="diagnose_database.php" class="btn btn-primary">🔍 Detailed Database Diagnostic</a>
            <a href="index.php" class="btn btn-success">🏠 Go to Application</a>
            <a href="http://localhost/phpmyadmin" class="btn btn-danger" target="_blank">🗄️ Open phpMyAdmin</a>
        </div>
    </div>
</body>
</html>
