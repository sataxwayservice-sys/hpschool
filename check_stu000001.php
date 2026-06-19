<?php
/**
 * Specific Check for STU000001
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

echo "<h1>Checking Student STU000001</h1>";
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

$admNo = 'STU000001';

// Test 1: Does student exist?
echo "<h2>1. Student Existence Check</h2>";
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM students WHERE admission_no = ?");
$stmt->bind_param('s', $admNo);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if ($student) {
    echo "<div class='success'>";
    echo "✓ Student <strong>$admNo</strong> EXISTS in database!<br><br>";
    echo "<table>";
    foreach ($student as $key => $value) {
        echo "<tr>";
        echo "<th>" . htmlspecialchars($key) . "</th>";
        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    $studentId = $student['student_id'];

    // Check status
    if ($student['status'] != 'Active') {
        echo "<div class='error'>";
        echo "✗ <strong>PROBLEM FOUND!</strong> Student status is '" . htmlspecialchars($student['status']) . "' but must be 'Active' to appear in search!<br>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='fix_status' value='1'>";
        echo "<button type='submit' style='background: orange; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;'>Fix Status - Set to Active</button>";
        echo "</form>";
        echo "</div>";

        if (isset($_POST['fix_status'])) {
            $conn->query("UPDATE students SET status = 'Active' WHERE student_id = $studentId");
            echo "<div class='success'>✓ Status updated to 'Active'. Refresh this page.</div>";
        }
    } else {
        echo "<div class='success'>✓ Student status is 'Active'</div>";
    }

    // Check batch
    if (empty($student['batch'])) {
        echo "<div class='warning'>";
        echo "⚠ Student has no batch assigned<br>";
        echo "<form method='POST'>";
        echo "<input type='text' name='batch' placeholder='Enter batch (e.g., 2024-2025)' value='2024-2025' required style='padding: 8px; margin: 10px 0;'><br>";
        echo "<input type='hidden' name='fix_batch' value='1'>";
        echo "<button type='submit' style='background: orange; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Assign Batch</button>";
        echo "</form>";
        echo "</div>";

        if (isset($_POST['fix_batch'])) {
            $batch = $conn->real_escape_string($_POST['batch']);
            $conn->query("UPDATE students SET batch = '$batch' WHERE student_id = $studentId");
            echo "<div class='success'>✓ Batch assigned. Refresh this page.</div>";
        }
    } else {
        echo "<div class='success'>✓ Student has batch: <strong>" . htmlspecialchars($student['batch']) . "</strong></div>";
    }

    // Test 2: Check fee structure
    echo "<h2>2. Fee Structure Check</h2>";
    $feeStructure = $conn->query("
        SELECT fs.*, fh.fee_head_name, fh.fee_type
        FROM fee_structure fs
        JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
        WHERE fs.student_id = $studentId AND fs.is_active = 1
    ");

    if ($feeStructure && $feeStructure->num_rows > 0) {
        echo "<div class='success'>";
        echo "✓ Student has " . $feeStructure->num_rows . " fee(s) assigned<br><br>";
        echo "<table>";
        echo "<tr><th>Fee Head</th><th>Type</th><th>Amount</th></tr>";
        while ($fee = $feeStructure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fee['fee_head_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fee['fee_type']) . "</td>";
            echo "<td>₹" . number_format($fee['amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "✗ <strong>NO FEES ASSIGNED!</strong> This student has no fee structure.<br>";
        echo "Go to <strong>Fee Structure</strong> page and assign fees to this student.";
        echo "</div>";
    }

    // Test 3: Check class and section
    echo "<h2>3. Class & Section Check</h2>";
    if ($student['class_id']) {
        $class = $conn->query("SELECT class_name FROM classes WHERE class_id = " . $student['class_id'])->fetch_assoc();
        if ($class) {
            echo "<div class='success'>✓ Class: <strong>" . htmlspecialchars($class['class_name']) . "</strong></div>";
        } else {
            echo "<div class='warning'>⚠ Class ID " . $student['class_id'] . " not found in classes table</div>";
        }
    } else {
        echo "<div class='warning'>⚠ No class assigned</div>";
    }

    if ($student['section_id']) {
        $section = $conn->query("SELECT section_name FROM sections WHERE section_id = " . $student['section_id'])->fetch_assoc();
        if ($section) {
            echo "<div class='success'>✓ Section: <strong>" . htmlspecialchars($section['section_name']) . "</strong></div>";
        } else {
            echo "<div class='warning'>⚠ Section ID " . $student['section_id'] . " not found in sections table</div>";
        }
    } else {
        echo "<div class='warning'>⚠ No section assigned</div>";
    }

    // Test 4: Test the search API
    echo "<h2>4. Search API Test</h2>";
    echo "<div class='info'>";
    echo "<strong>Testing search API with admission number 'STU000001'...</strong><br><br>";
    echo "<button onclick=\"testSearch()\" style='background: blue; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Search API</button>";
    echo "<div id='api-result' style='margin-top: 10px;'></div>";
    echo "</div>";

    echo "<script>
    function testSearch() {
        const resultDiv = document.getElementById('api-result');
        resultDiv.innerHTML = '<p>Searching...</p>';

        fetch('api/search_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'admission_no=STU000001'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class=\"success\">✓ API Search Successful!<br><pre>' + JSON.stringify(data, null, 2) + '</pre></div>';
            } else {
                resultDiv.innerHTML = '<div class=\"error\">✗ API Search Failed: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class=\"error\">✗ API Error: ' + error + '</div>';
        });
    }
    </script>";

} else {
    echo "<div class='error'>";
    echo "✗ Student <strong>$admNo</strong> NOT FOUND in database!<br><br>";
    echo "<strong>Possible reasons:</strong><br>";
    echo "<ul>";
    echo "<li>Student was never added to the system</li>";
    echo "<li>Admission number is different (check spelling/case)</li>";
    echo "<li>Student was deleted</li>";
    echo "</ul>";
    echo "</div>";

    // Show available students
    echo "<h3>Available Students:</h3>";
    $anyStudents = $conn->query("SELECT admission_no, student_name, status, batch FROM students LIMIT 10");
    if ($anyStudents && $anyStudents->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Admission No</th><th>Name</th><th>Status</th><th>Batch</th><th>Action</th></tr>";
        while ($s = $anyStudents->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($s['admission_no']) . "</td>";
            echo "<td>" . htmlspecialchars($s['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($s['status']) . "</td>";
            echo "<td>" . htmlspecialchars($s['batch'] ?? 'NULL') . "</td>";
            echo "<td><a href='modules/fees/collect_complete.php?admission_no=" . urlencode($s['admission_no']) . "'>Try This Student</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>✗ NO STUDENTS FOUND in database! Add students first.</div>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>For search to work, student must have:</strong></p>";
echo "<ol>";
echo "<li>✓ Exist in students table</li>";
echo "<li>✓ Status = 'Active'</li>";
echo "<li>✓ Batch assigned (e.g., '2024-2025')</li>";
echo "<li>✓ Fees assigned in fee_structure</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Quick Actions:</strong></p>";
echo "<p>";
echo "<a href='full_diagnostic.php'>Full System Diagnostic</a> | ";
echo "<a href='update_student_batches.php'>Update All Student Batches</a> | ";
echo "<a href='modules/fees/collect_complete.php'>Fee Collection Page</a>";
echo "</p>";
?>
