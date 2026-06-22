<?php
/**
 * Database Configuration and Connection
 * MySQL Database Handler with Error Handling
 */

// Database credentials
// Auto-detect environment or use environment variables
if (!defined('DB_HOST')) {
    $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
    $isLocalHost = $currentHost === '' || $currentHost === 'localhost' || $currentHost === '127.0.0.1' || str_starts_with($currentHost, 'localhost:') || str_starts_with($currentHost, '127.0.0.1:');
    $liveDatabaseConfigFile = __DIR__ . '/database.live.php';
    $loadedConfig = null;

    if (!$isLocalHost && is_file($liveDatabaseConfigFile)) {
        $liveDatabaseConfig = include $liveDatabaseConfigFile;

        if (is_array($liveDatabaseConfig)) {
            $loadedConfig = $liveDatabaseConfig;
        }
    }

    if ($loadedConfig === null) {
        $envHost = getenv('DB_HOST');
        if (!empty($envHost)) {
            $loadedConfig = [
                'host' => $envHost,
                'user' => getenv('DB_USER') ?: '',
                'pass' => getenv('DB_PASS') ?: '',
                'name' => getenv('DB_NAME') ?: '',
                'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
            ];
        }
    }

    if ($loadedConfig !== null) {
        define('DB_HOST', $loadedConfig['host'] ?? 'localhost');
        define('DB_USER', $loadedConfig['user'] ?? 'root');
        define('DB_PASS', $loadedConfig['pass'] ?? '');
        define('DB_NAME', $loadedConfig['name'] ?? 'school_fees_system');
        define('DB_CHARSET', $loadedConfig['charset'] ?? 'utf8mb4');
    } elseif ($isLocalHost) {
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'school_fees_system');
        define('DB_CHARSET', 'utf8mb4');
    } else {
        die("Production database configuration is missing. Please upload config/database.live.php or set DB_HOST, DB_USER, DB_PASS, and DB_NAME.");
    }
}

/**
 * Database driver support
 * Supports: mysql (mysqli) and pgsql (PDO)
 */
if (!defined('DB_DRIVER')) {
    $envDriver = getenv('DB_DRIVER');
    define('DB_DRIVER', $envDriver ?: 'mysql');
}

/**
 * Get Database Connection
 * Returns mysqli or PDO depending on DB_DRIVER
 * @return mysqli|PDO|false
 */
function getDbConnection() {
    static $conn = null;
    $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
    $isLocalHost = $currentHost === '' || $currentHost === 'localhost' || $currentHost === '127.0.0.1' || str_starts_with($currentHost, 'localhost:') || str_starts_with($currentHost, '127.0.0.1:');

    if ($conn !== null) {
        return $conn;
    }

    try {
        if (DB_DRIVER === 'pgsql') {
            // Use PDO for Postgres (e.g., Supabase)
            $port = getenv('DB_PORT') ?: 5432;
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, $port, DB_NAME);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } else {
            // Default to mysqli for MySQL
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($conn->connect_error) {
                throw new Exception($conn->connect_error, $conn->connect_errno);
            }

            $conn->set_charset(DB_CHARSET);
            $conn->autocommit(TRUE);
        }
    } catch (Exception $e) {
        error_log("Database Connection Failed: " . $e->getMessage());
        if ($isLocalHost) {
            die("Database connection failed: " . $e->getMessage() . "<br>Check your database credentials in config/database.php");
        } else {
            die("Database connection failed. Please check your database credentials in config/database.php");
        }
    }

    return $conn;
}

/**
 * Close database connection
 */
function closeDbConnection() {
    $conn = getDbConnection();
    if ($conn) {
        $conn->close();
    }
}

/**
 * Execute a prepared statement safely
 *
 * @param string $query SQL query with placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
 * @param array $params Parameters array
 * @return mysqli_result|bool
 */
function executeQuery($query, $types = '', $params = []) {
    $conn = getDbConnection();

    try {
        if (DB_DRIVER === 'pgsql') {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                error_log("Query Prepare Failed: " . implode(' ', $conn->errorInfo()));
                return false;
            }

            // Bind all parameters as strings by default
            foreach ($params as $i => $value) {
                $stmt->bindValue($i + 1, $value);
            }

            $stmt->execute();

            $insertId = null;
            $affectedRows = $stmt->rowCount();

            // Attempt to fetch last insert id if available
            try {
                $lastId = $conn->lastInsertId();
                if ($lastId !== false) {
                    $insertId = $lastId;
                }
            } catch (Exception $e) {
                // ignore
            }

            return ['insert_id' => $insertId, 'affected_rows' => $affectedRows, 'statement' => $stmt];
        } else {
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                error_log("Query Prepare Failed: " . $conn->error);
                return false;
            }

            if (!empty($types) && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $result = $stmt->execute();

            if (!$result) {
                error_log("Query Execute Failed: " . $stmt->error);
                $stmt->close();
                return false;
            }

            if ($stmt->affected_rows >= 0 || $stmt->insert_id > 0) {
                $insertId = $stmt->insert_id;
                $affectedRows = $stmt->affected_rows;
                $stmt->close();
                return ['insert_id' => $insertId, 'affected_rows' => $affectedRows];
            }

            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }

    } catch (Exception $e) {
        error_log("Database Query Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch single row
 *
 * @param string $query
 * @param string $types
 * @param array $params
 * @return array|null
 */
function fetchOne($query, $types = '', $params = []) {
    $res = executeQuery($query, $types, $params);

    if (DB_DRIVER === 'pgsql') {
        if ($res && isset($res['statement']) && $res['statement'] instanceof PDOStatement) {
            return $res['statement']->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }

    if ($res && $res instanceof mysqli_result) {
        return $res->fetch_assoc();
    }

    return null;
}

/**
 * Fetch all rows
 *
 * @param string $query
 * @param string $types
 * @param array $params
 * @return array
 */
function fetchAll($query, $types = '', $params = []) {
    $res = executeQuery($query, $types, $params);
    $data = [];

    if (DB_DRIVER === 'pgsql') {
        if ($res && isset($res['statement']) && $res['statement'] instanceof PDOStatement) {
            return $res['statement']->fetchAll(PDO::FETCH_ASSOC);
        }
        return $data;
    }

    if ($res && $res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }

    return $data;
}

/**
 * Escape string for SQL
 *
 * @param string $string
 * @return string
 */
function escapeString($string) {
    $conn = getDbConnection();
    if (DB_DRIVER === 'pgsql') {
        // PDO::quote adds surrounding quotes
        $quoted = $conn->quote($string);
        return substr($quoted, 1, -1);
    }
    return $conn->real_escape_string($string);
}

/**
 * Begin transaction
 */
function beginTransaction() {
    $conn = getDbConnection();
    if (DB_DRIVER === 'pgsql') {
        $conn->beginTransaction();
    } else {
        $conn->begin_transaction();
    }
}

/**
 * Commit transaction
 */
function commitTransaction() {
    $conn = getDbConnection();
    if (DB_DRIVER === 'pgsql') {
        $conn->commit();
    } else {
        $conn->commit();
        // Re-enable autocommit after transaction completes
        $conn->autocommit(TRUE);
    }
}

/**
 * Rollback transaction
 */
function rollbackTransaction() {
    $conn = getDbConnection();
    if (DB_DRIVER === 'pgsql') {
        $conn->rollBack();
    } else {
        $conn->rollback();
        $conn->autocommit(TRUE);
    }
}

// Initialize connection on load
$db = getDbConnection();

?>
