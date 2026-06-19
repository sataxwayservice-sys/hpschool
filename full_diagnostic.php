<?php
/**
 * Complete System Diagnostic
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

echo "<h1>Complete System Diagnostic</h1>";
echo "<style>
    .success { background: #d4edda; padding: 15px; border: 1px solid green; margin: 10px 0; }
    .error { background: #f8d7da; padding: 15px; border: 1px solid red; margin: 10px 0; }
    .warning { background: #fff3cd; padding: 15px; border: 1px solid orange; margin: 10px 0; }
    .info { background: #d1ecf1; padding: 15px; border: 1px solid #0c5460; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th { background: #f2f2f2; padding: 10px; text-align: left; border: 1px solid #ddd; }
    td { padding: 8px; border: 1px solid #ddd; }
    .code { background: #f5f5f5; padding: 2px 5px; font-family: monospace; }
</style>";

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
try {
    $conn = getDbConnection();
    if ($conn) {
        echo "<div class='success'>✓ Database connection successful</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Database connection failed: " . $e->getMessage() . "</div>";
    exit();
}

// Test 2: Config Constants
echo "<h2>2. Configuration Constants</h2>";
if (defined('SCHOOL_NAME')) {
    echo "<div class='success'>✓ SCHOOL_NAME defined: <strong>" . SCHOOL_NAME . "</strong></div>";
} else {
    echo "<div class='error'>✗ SCHOOL_NAME not defined in config.php</div>";
}

// Test 3: Students Table
echo "<h2>3. Students Table Check</h2>";
try {
    $totalStudents = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc();
    $activeStudents = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'")->fetch_assoc();

    echo "<div class='info'>";
    echo "<strong>Total Students:</strong> " . $totalStudents['count'] . "<br>";
    echo "<strong>Active Students:</strong> " . $activeStudents['count'];
    echo "</div>";

    if ($totalStudents['count'] == 0) {
        echo "<div class='error'>✗ No students found in database! Please add students first.</div>";
    } else {
        echo "<div class='success'>✓ Students table has data</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error querying students: " . $e->getMessage() . "</div>";
}

// Test 4: Batch Column Check
echo "<h2>4. Batch Assignment Check</h2>";
try {
    // First check if batch column exists
    $columnsResult = $conn->query("SHOW COLUMNS FROM students LIKE 'batch'");

    if ($columnsResult && $columnsResult->num_rows > 0) {
        echo "<div class='success'>✓ 'batch' column exists in students table</div>";

        $query1 = $conn->query("SELECT COUNT(*) as count FROM students WHERE batch IS NOT NULL AND batch != ''");
        $query2 = $conn->query("SELECT COUNT(*) as count FROM students WHERE batch IS NULL OR batch = ''");

        if ($query1 && $query2) {
            $studentsWithBatch = $query1->fetch_assoc();
            $studentsWithoutBatch = $query2->fetch_assoc();

            echo "<div class='info'>";
            echo "<strong>Students WITH batch:</strong> " . $studentsWithBatch['count'] . "<br>";
            echo "<strong>Students WITHOUT batch:</strong> " . $studentsWithoutBatch['count'];
            echo "</div>";

            if ($studentsWithoutBatch['count'] > 0) {
                echo "<div class='warning'>⚠ " . $studentsWithoutBatch['count'] . " students need batch assignment</div>";
                echo "<div class='info'>";
                echo "<strong>Action Required:</strong><br>";
                echo "1. Visit <a href='update_student_batches.php' style='color: blue; text-decoration: underline;'>update_student_batches.php</a><br>";
                echo "2. Click the button to assign batch '2024-2025' to all students<br>";
                echo "3. Return here to re-run this diagnostic";
                echo "</div>";
            } else {
                echo "<div class='success'>✓ All students have batch assigned</div>";
            }
        } else {
            echo "<div class='error'>✗ Query failed: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='error'>✗ 'batch' column does NOT exist in students table!</div>";
        echo "<div class='info'>";
        echo "<strong>Action Required:</strong> Add 'batch' column to students table:<br>";
        echo "<code style='display: block; background: #f5f5f5; padding: 10px; margin: 10px 0;'>";
        echo "ALTER TABLE students ADD COLUMN batch VARCHAR(20) NULL AFTER status;";
        echo "</code>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error checking batches: " . $e->getMessage() . "</div>";
}

// Test 5: Show Available Batches
echo "<h2>5. Available Batches</h2>";
try {
    // Check if batch column exists first
    $columnsResult = $conn->query("SHOW COLUMNS FROM students LIKE 'batch'");

    if ($columnsResult && $columnsResult->num_rows > 0) {
        $batchesResult = $conn->query("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC");

        if ($batchesResult && $batchesResult->num_rows > 0) {
            echo "<div class='success'>";
            echo "✓ Found " . $batchesResult->num_rows . " batch(es):<br>";
            while ($row = $batchesResult->fetch_assoc()) {
                echo "- <strong>" . htmlspecialchars($row['batch']) . "</strong><br>";
            }
            echo "</div>";
        } else {
            echo "<div class='error'>✗ No batches found! Batch dropdown will be empty.</div>";
            echo "<div class='info'>Students need batch values assigned. Use <a href='update_student_batches.php'>update_student_batches.php</a></div>";
        }
    } else {
        echo "<div class='error'>✗ 'batch' column doesn't exist in students table</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}

// Test 6: Check for STU000001
echo "<h2>6. Search for Student 'STU000001'</h2>";
try {
    $stmt = $conn->prepare("SELECT * FROM students WHERE admission_no = ?");
    $admNo = 'STU000001';
    $stmt->bind_param('s', $admNo);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        echo "<div class='success'>";
        echo "✓ Student <strong>STU000001</strong> EXISTS!<br><br>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Student ID</td><td>" . $student['student_id'] . "</td></tr>";
        echo "<tr><td>Admission No</td><td>" . htmlspecialchars($student['admission_no']) . "</td></tr>";
        echo "<tr><td>Name</td><td>" . htmlspecialchars($student['student_name']) . "</td></tr>";
        echo "<tr><td>Status</td><td><strong>" . htmlspecialchars($student['status']) . "</strong></td></tr>";
        echo "<tr><td>Batch</td><td>" . htmlspecialchars($student['batch'] ?? '<span style="color: red;">NULL</span>') . "</td></tr>";
        echo "<tr><td>Class ID</td><td>" . $student['class_id'] . "</td></tr>";
        echo "<tr><td>Section ID</td><td>" . $student['section_id'] . "</td></tr>";
        echo "</table>";
        echo "</div>";

        if ($student['status'] != 'Active') {
            echo "<div class='error'>✗ Student status is '" . $student['status'] . "' - must be 'Active' to appear in search!</div>";
        }
        if (empty($student['batch'])) {
            echo "<div class='warning'>⚠ Student has no batch assigned</div>";
        }
    } else {
        echo "<div class='error'>✗ Student 'STU000001' NOT FOUND in database</div>";
        echo "<div class='info'>Searching for any students with similar admission numbers...</div>";

        $similarResult = $conn->query("SELECT admission_no, student_name, status FROM students WHERE admission_no LIKE 'STU%' LIMIT 10");
        if ($similarResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Admission No</th><th>Name</th><th>Status</th></tr>";
            while ($row = $similarResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['admission_no']) . "</td>";
                echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}

// Test 7: Fee Heads Check
echo "<h2>7. Fee Heads Check</h2>";
try {
    $feeHeadsCount = $conn->query("SELECT COUNT(*) as count FROM fee_heads WHERE is_active = 1")->fetch_assoc();

    if ($feeHeadsCount['count'] > 0) {
        echo "<div class='success'>✓ Found " . $feeHeadsCount['count'] . " active fee head(s)</div>";

        $feeHeads = $conn->query("SELECT fee_head_name, fee_type FROM fee_heads WHERE is_active = 1");
        echo "<table>";
        echo "<tr><th>Fee Head Name</th><th>Type</th></tr>";
        while ($row = $feeHeads->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['fee_head_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['fee_type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>✗ No fee heads found! Create fee heads first.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}

// Test 8: Fee Structure Check
echo "<h2>8. Fee Structure Assignment Check</h2>";
try {
    $assignedCount = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM fee_structure WHERE is_active = 1")->fetch_assoc();

    if ($assignedCount['count'] > 0) {
        echo "<div class='success'>✓ " . $assignedCount['count'] . " student(s) have fees assigned</div>";

        // Show sample
        $sample = $conn->query("
            SELECT s.admission_no, s.student_name, COUNT(fs.structure_id) as fee_count
            FROM students s
            JOIN fee_structure fs ON s.student_id = fs.student_id
            WHERE fs.is_active = 1
            GROUP BY s.student_id
            LIMIT 5
        ");

        if ($sample->num_rows > 0) {
            echo "<div class='info'><strong>Sample students with fees:</strong></div>";
            echo "<table>";
            echo "<tr><th>Admission No</th><th>Name</th><th>Fee Count</th></tr>";
            while ($row = $sample->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['admission_no']) . "</td>";
                echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
                echo "<td>" . $row['fee_count'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='error'>✗ No students have fees assigned! Go to Fee Structure to assign fees.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}

// Test 9: API Files Check
echo "<h2>9. API Files Check</h2>";
$apiFiles = [
    'api/search_student.php',
    'api/search_students_by_name.php',
    'api/get_pending_fees.php'
];

foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✓ <span class='code'>$file</span> exists</div>";
    } else {
        echo "<div class='error'>✗ <span class='code'>$file</span> missing!</div>";
    }
}

// Summary
echo "<hr>";
echo "<h2>Summary & Next Steps</h2>";

$issues = [];

// Safely get counts
$result1 = $conn->query("SELECT COUNT(*) as count FROM students");
$totalStudents = ($result1 && $row = $result1->fetch_assoc()) ? $row['count'] : 0;

$result2 = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
$activeStudents = ($result2 && $row = $result2->fetch_assoc()) ? $row['count'] : 0;

// Check if batch column exists
$columnsResult = $conn->query("SHOW COLUMNS FROM students LIKE 'batch'");
if ($columnsResult && $columnsResult->num_rows > 0) {
    $result3 = $conn->query("SELECT COUNT(*) as count FROM students WHERE batch IS NOT NULL AND batch != ''");
    $studentsWithBatch = ($result3 && $row = $result3->fetch_assoc()) ? $row['count'] : 0;
} else {
    $studentsWithBatch = 0;
    $issues[] = "Add 'batch' column to students table";
}

$result4 = $conn->query("SELECT COUNT(*) as count FROM fee_heads WHERE is_active = 1");
$feeHeadsCount = ($result4 && $row = $result4->fetch_assoc()) ? $row['count'] : 0;

$result5 = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM fee_structure WHERE is_active = 1");
$assignedCount = ($result5 && $row = $result5->fetch_assoc()) ? $row['count'] : 0;

if ($totalStudents == 0) {
    $issues[] = "Add students to the system";
}
if ($studentsWithBatch == 0) {
    $issues[] = "Assign batch to students using <a href='update_student_batches.php'>update_student_batches.php</a>";
}
if ($feeHeadsCount == 0) {
    $issues[] = "Create fee heads";
}
if ($assignedCount == 0) {
    $issues[] = "Assign fees to students via Fee Structure";
}

if (count($issues) > 0) {
    echo "<div class='warning'>";
    echo "<h3>⚠ Issues Found:</h3>";
    echo "<ol>";
    foreach ($issues as $issue) {
        echo "<li>" . $issue . "</li>";
    }
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<h3>✓ System Ready!</h3>";
    echo "<p>All checks passed. You can now:</p>";
    echo "<p><a href='modules/fees/collect_complete.php' style='background: green; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 18px;'>Open Fee Collection Page</a></p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Other Tools:</strong></p>";
echo "<p>";
echo "<a href='update_student_batches.php'>Update Student Batches</a> | ";
echo "<a href='check_student.php'>Check Individual Student</a> | ";
echo "<a href='test_fee_collection.php'>Fee Collection Test</a> | ";
echo "<a href='modules/fees/collect_complete.php'>Fee Collection Page</a>";
echo "</p>";
?>
