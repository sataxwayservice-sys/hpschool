<?php
declare(strict_types=1);

const ADMIN_RECOVERY_KEY = '023f375ef0fc3e9d9e2e867731960ab4';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$providedKey = (string)($_GET['key'] ?? '');
if ($providedKey === '' || !hash_equals(ADMIN_RECOVERY_KEY, $providedKey)) {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/config/config.php';

$message = '';
$success = false;
$deleted = false;

try {
    $conn = getDbConnection();
    if (!$conn) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $tableResult = $conn->query("SHOW TABLES LIKE 'users'");
    if (!$tableResult || $tableResult->num_rows === 0) {
        throw new RuntimeException('The users table does not exist. Import the main database SQL first.');
    }

    $columns = [];
    $columnResult = $conn->query('SHOW COLUMNS FROM users');
    while ($columnResult && ($column = $columnResult->fetch_assoc())) {
        $columns[$column['Field']] = true;
    }

    $requiredColumns = [
        'school_id' => "school_id int(11) DEFAULT NULL",
        'status' => "status enum('pending','active','rejected','blocked') NOT NULL DEFAULT 'active'",
        'status_reason' => 'status_reason text DEFAULT NULL',
        'student_id' => 'student_id int(11) DEFAULT NULL',
        'is_active' => 'is_active tinyint(1) NOT NULL DEFAULT 1',
        'last_login' => 'last_login timestamp NULL DEFAULT NULL',
    ];

    foreach ($requiredColumns as $columnName => $definition) {
        if (!isset($columns[$columnName])) {
            if (!$conn->query("ALTER TABLE users ADD COLUMN $definition")) {
                throw new RuntimeException("Could not add users.$columnName: " . $conn->error);
            }
        }
    }

    if (!$conn->query(
        "ALTER TABLE users MODIFY COLUMN role
         enum('super_admin','admin','accountant','clerk','teacher','parent','student')
         NOT NULL DEFAULT 'clerk'"
    )) {
        throw new RuntimeException('Could not update the user-role field: ' . $conn->error);
    }

    $username = 'admin';
    $email = 'admin@school.com';
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Could not create the password hash.');
    }

    $userId = 0;
    $lookup = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
    $lookup->bind_param('s', $username);
    $lookup->execute();
    $lookupResult = $lookup->get_result();
    if ($lookupResult && ($row = $lookupResult->fetch_assoc())) {
        $userId = (int)$row['user_id'];
    }
    $lookup->close();

    if ($userId <= 0) {
        $lookup = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $lookup->bind_param('s', $email);
        $lookup->execute();
        $lookupResult = $lookup->get_result();
        if ($lookupResult && ($row = $lookupResult->fetch_assoc())) {
            $userId = (int)$row['user_id'];
        }
        $lookup->close();
    }

    if ($userId > 0) {
        $statement = $conn->prepare(
            "UPDATE users
             SET username = ?, password = ?, full_name = 'Admin User', role = 'super_admin',
                 school_id = NULL, status = 'active', status_reason = NULL,
                 student_id = NULL, is_active = 1
             WHERE user_id = ?"
        );
        $statement->bind_param('ssi', $username, $passwordHash, $userId);
    } else {
        $statement = $conn->prepare(
            "INSERT INTO users
                (username, email, password, full_name, role, school_id, status, status_reason, student_id, is_active)
             VALUES (?, ?, ?, 'Admin User', 'super_admin', NULL, 'active', NULL, NULL, 1)"
        );
        $statement->bind_param('sss', $username, $email, $passwordHash);
    }

    if (!$statement->execute()) {
        throw new RuntimeException('Could not save the admin account: ' . $statement->error);
    }
    $statement->close();

    $check = $conn->prepare(
        "SELECT user_id FROM users
         WHERE username = 'admin' AND role = 'super_admin' AND status = 'active' AND is_active = 1
         LIMIT 1"
    );
    $check->execute();
    $checkResult = $check->get_result();
    $success = $checkResult && $checkResult->num_rows === 1;
    $check->close();

    if (!$success) {
        throw new RuntimeException('The account was saved but could not be verified.');
    }

    $message = 'Super Admin account repaired successfully.';
    $deleted = @unlink(__FILE__);
} catch (Throwable $error) {
    $message = $error->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Recovery</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f5f7; padding: 40px; }
        main { max-width: 680px; margin: auto; background: white; padding: 28px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .ok { color: #146c43; }
        .error { color: #b02a37; }
        code { background: #f1f1f1; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <h1 class="<?php echo $success ? 'ok' : 'error'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </h1>
    <?php if ($success): ?>
        <p>Username: <code>admin</code></p>
        <p>Password: <code>admin123</code></p>
        <p><a href="modules/auth/super_admin_login.php">Open Super Admin Login</a></p>
        <p><?php echo $deleted ? 'The recovery file deleted itself.' : 'Delete admin_recovery_once.php from htdocs now.'; ?></p>
    <?php else: ?>
        <p>Confirm that the main SQL database was imported and try again.</p>
    <?php endif; ?>
</main>
</body>
</html>
