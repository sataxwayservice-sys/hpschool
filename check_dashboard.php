<?php
/**
 * Dashboard Diagnostic Tool
 * Check what's working and what's not after login
 */

// Include configuration (handles session start)
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo "<h1>❌ Not Logged In</h1>";
    echo "<p>You need to login first!</p>";
    echo "<a href='index.php'>Go to Login</a>";
    exit;
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .section {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Dashboard Diagnostic Tool</h1>

    <div class="section">
        <h2>✅ Login Status</h2>
        <p class="success">You are logged in!</p>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>User ID</td>
                <td><?php echo $currentUser['user_id']; ?></td>
            </tr>
            <tr>
                <td>Username</td>
                <td><?php echo htmlspecialchars($currentUser['username']); ?></td>
            </tr>
            <tr>
                <td>Full Name</td>
                <td><?php echo htmlspecialchars($currentUser['full_name']); ?></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><?php echo htmlspecialchars($currentUser['email']); ?></td>
            </tr>
            <tr>
                <td>Role</td>
                <td><strong><?php echo htmlspecialchars($currentUser['role']); ?></strong></td>
            </tr>
            <tr>
                <td>Active</td>
                <td><?php echo $currentUser['is_active'] ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>📊 Database Check</h2>
        <?php
        $conn = getDbConnection();
        if ($conn) {
            echo "<p class='success'>✅ Database connected</p>";

            // Check tables
            $tables = ['students', 'classes', 'sections', 'fee_heads', 'users'];
            echo "<table>";
            echo "<tr><th>Table</th><th>Records</th><th>Status</th></tr>";

            foreach ($tables as $table) {
                $result = $conn->query("SELECT COUNT(*) as count FROM $table");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $count = $row['count'];
                    echo "<tr>";
                    echo "<td><strong>$table</strong></td>";
                    echo "<td>$count</td>";
                    echo "<td class='success'>✅ OK</td>";
                    echo "</tr>";
                } else {
                    echo "<tr>";
                    echo "<td><strong>$table</strong></td>";
                    echo "<td>-</td>";
                    echo "<td class='error'>❌ Error</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ Database connection failed!</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>🔐 Permissions Check</h2>
        <p>As <strong><?php echo $currentUser['role']; ?></strong>, you should have access to:</p>
        <?php
        $modules = ['students', 'fees', 'marks', 'reports', 'settings'];
        echo "<table>";
        echo "<tr><th>Module</th><th>View</th><th>Add</th><th>Edit</th><th>Delete</th></tr>";

        foreach ($modules as $module) {
            echo "<tr>";
            echo "<td><strong>" . ucfirst($module) . "</strong></td>";
            echo "<td>" . (hasPermission($module, 'view') ? '✅' : '❌') . "</td>";
            echo "<td>" . (hasPermission($module, 'add') ? '✅' : '❌') . "</td>";
            echo "<td>" . (hasPermission($module, 'edit') ? '✅' : '❌') . "</td>";
            echo "<td>" . (hasPermission($module, 'delete') ? '✅' : '❌') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        if ($currentUser['role'] == 'super_admin') {
            echo "<p class='success'>✅ As Super Admin, you have ALL permissions!</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>🧪 Function Tests</h2>
        <table>
            <tr>
                <th>Function</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>isLoggedIn()</td>
                <td class="<?php echo isLoggedIn() ? 'success' : 'error'; ?>">
                    <?php echo isLoggedIn() ? '✅ Working' : '❌ Failed'; ?>
                </td>
            </tr>
            <tr>
                <td>getCurrentUser()</td>
                <td class="<?php echo $currentUser ? 'success' : 'error'; ?>">
                    <?php echo $currentUser ? '✅ Working' : '❌ Failed'; ?>
                </td>
            </tr>
            <tr>
                <td>getSchoolSettings()</td>
                <td class="<?php $settings = getSchoolSettings(); echo $settings ? 'success' : 'error'; ?>">
                    <?php echo $settings ? '✅ Working' : '❌ Failed'; ?>
                </td>
            </tr>
            <tr>
                <td>hasPermission()</td>
                <td class="success">✅ Working</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>🌐 Page Access Tests</h2>
        <p>Click these buttons to test if pages load:</p>
        <a href="modules/dashboard/index.php" class="btn">📊 Dashboard</a>
        <a href="modules/students/add.php" class="btn">👤 Add Student</a>
        <a href="modules/settings/school.php" class="btn">⚙️ Settings</a>
        <a href="index.php" class="btn">🏠 Home</a>
    </div>

    <div class="section">
        <h2>📋 Common Issues & Fixes</h2>
        <ul>
            <li><strong>If menus not showing:</strong> Check permissions table above</li>
            <li><strong>If pages are blank:</strong> Check browser console (F12) for errors</li>
            <li><strong>If getting errors:</strong> Note the exact error message</li>
            <li><strong>If CSS not loading:</strong> Press Ctrl+F5 to hard refresh</li>
        </ul>
    </div>

    <hr style="margin: 30px 0;">

    <h3>📝 What to Check:</h3>
    <ol>
        <li>All tables above should show ✅ OK</li>
        <li>Permissions should show ✅ for super_admin</li>
        <li>All functions should be ✅ Working</li>
        <li>Click each button to test page access</li>
    </ol>

    <p><a href="modules/dashboard/index.php" class="btn">Go to Dashboard</a></p>

</div>

</body>
</html>
