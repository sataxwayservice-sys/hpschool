<?php
/**
 * Database Configuration and Connection
 * MySQL Database Handler with Error Handling
 */

// Database credentials
// Auto-detect environment or use environment variables
if (!defined('DB_HOST')) {
    // Check if we're on InfinityFree or production
    if (strpos($_SERVER['HTTP_HOST'] ?? '', 'infinityfree') !== false ||
        strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'infinityfree') !== false ||
        getenv('DB_HOST')) {

        // PRODUCTION - Use environment variables or define below
        define('DB_HOST', getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com'); // Change sqlXXX to your host
        define('DB_USER', getenv('DB_USER') ?: 'if0_XXXXXXXX');            // Your InfinityFree username
        define('DB_PASS', getenv('DB_PASS') ?: '');                        // Your database password
        define('DB_NAME', getenv('DB_NAME') ?: 'if0_XXXXXXXX_school');     // Your database name
    } else {
        // DEVELOPMENT - Localhost
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'school_fees_system');
    }
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * Get Database Connection
 * @return mysqli|false
 */
function getDbConnection() {
    static $conn = null;

    if ($conn === null) {
        try {
            // Create connection
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            // Check connection
            if ($conn->connect_error) {
                error_log("Database Connection Failed: " . $conn->connect_error);

                // Show helpful error message
                if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false) {
                    // Production error - hide details
                    die("Database connection failed. Please check your database credentials in config/database.php");
                } else {
                    // Development error - show details
                    die("Database connection failed: " . $conn->connect_error . "<br>Check your database credentials in config/database.php");
                }
            }

            // Set charset
            $conn->set_charset(DB_CHARSET);

            // IMPORTANT: Ensure autocommit is enabled by default
            $conn->autocommit(TRUE);

        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());

            // Show helpful error message based on environment
            if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
                // Development - show detailed error
                die("<h3>Database Error</h3>" .
                    "<p><strong>Error:</strong> " . $e->getMessage() . "</p>" .
                    "<p><strong>Solutions:</strong></p>" .
                    "<ul>" .
                    "<li>Make sure XAMPP MySQL is running</li>" .
                    "<li>Check if database '" . DB_NAME . "' exists in phpMyAdmin</li>" .
                    "<li>Verify credentials in config/database.php</li>" .
                    "<li>Run <a href='diagnose_database.php'>diagnose_database.php</a> for detailed diagnostics</li>" .
                    "</ul>");
            } else {
                // Production - hide details
                die("Database error occurred. Please check your database credentials in config/database.php");
            }
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
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            error_log("Query Prepare Failed: " . $conn->error);
            return false;
        }

        // Bind parameters if provided
        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        // Execute statement
        $result = $stmt->execute();

        if (!$result) {
            error_log("Query Execute Failed: " . $stmt->error);
            $stmt->close();
            return false;
        }

        // Return result
        if ($stmt->affected_rows >= 0 || $stmt->insert_id > 0) {
            $insertId = $stmt->insert_id;
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            return ['insert_id' => $insertId, 'affected_rows' => $affectedRows];
        }

        $result = $stmt->get_result();
        $stmt->close();
        return $result;

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
    $result = executeQuery($query, $types, $params);

    if ($result && $result instanceof mysqli_result) {
        return $result->fetch_assoc();
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
    $result = executeQuery($query, $types, $params);
    $data = [];

    if ($result && $result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
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
    return $conn->real_escape_string($string);
}

/**
 * Begin transaction
 */
function beginTransaction() {
    $conn = getDbConnection();
    $conn->begin_transaction();
}

/**
 * Commit transaction
 */
function commitTransaction() {
    $conn = getDbConnection();
    $conn->commit();

    // CRITICAL: Re-enable autocommit after transaction completes
    // Without this, the connection stays in transaction mode and
    // any subsequent queries will be rolled back when connection closes
    $conn->autocommit(TRUE);
}

/**
 * Rollback transaction
 */
function rollbackTransaction() {
    $conn = getDbConnection();
    $conn->rollback();

    // Re-enable autocommit after rollback
    $conn->autocommit(TRUE);
}

// Initialize connection on load
$db = getDbConnection();

?>
