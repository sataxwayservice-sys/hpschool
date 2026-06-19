<?php
/**
 * Debug Fee Collection Issues
 */

require_once 'config/config.php';

// Force error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fee Collection Debug Report</h1>";
echo "<style>
    .success { background: #d4edda; padding: 10px; border: 1px solid green; margin: 10px 0; }
    .error { background: #f8d7da; padding: 10px; border: 1px solid red; margin: 10px 0; }
    .warning { background: #fff3cd; padding: 10px; border: 1px solid orange; margin: 10px 0; }
    .info { background: #d1ecf1; padding: 10px; border: 1px solid #0c5460; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

$issues = [];
$studentWithFees = null;

// Test 1: Check Students
echo "<h2>1. Checking Students</h2>";
$students = fetchAll("SELECT * FROM students WHERE status = 'Active' LIMIT 1");
if (count($students) > 0) {
    $student = $students[0];
    echo "<div class='success'>✓ Found active student: " . htmlspecialchars($student['student_name']) . " (Adm: " . htmlspecialchars($student['admission_no']) . ")</div>";
    echo "<p><strong>Student ID:</strong> " . $student['student_id'] . "</p>";
    $studentId = $student['student_id'];
} else {
    echo "<div class='error'>✗ No active students found!</div>";
    $issues[] = "No active students";
}

// Test 2: Check Fee Heads
echo "<h2>2. Checking Fee Heads</h2>";
$feeHeads = fetchAll("SELECT * FROM fee_heads WHERE is_active = 1");
if (count($feeHeads) > 0) {
    echo "<div class='success'>✓ Found " . count($feeHeads) . " active fee head(s)</div>";
    echo "<table><tr><th>ID</th><th>Name</th><th>Type</th><th>Default Amount</th></tr>";
    foreach ($feeHeads as $fh) {
        echo "<tr>";
        echo "<td>" . $fh['fee_head_id'] . "</td>";
        echo "<td>" . htmlspecialchars($fh['fee_head_name']) . "</td>";
        echo "<td>" . htmlspecialchars($fh['fee_type']) . "</td>";
        echo "<td>" . ($fh['default_amount'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>✗ No active fee heads found!</div>";
    $issues[] = "No fee heads";
}

// Test 3: Check Fee Structure for the student
if (isset($studentId)) {
    echo "<h2>3. Checking Fee Structure for Student ID: $studentId</h2>";
    $feeStructure = fetchAll(
        "SELECT fs.*, fh.fee_head_name, fh.fee_type
         FROM fee_structure fs
         JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
         WHERE fs.student_id = ? AND fs.is_active = 1",
        'i',
        [$studentId]
    );

    if (count($feeStructure) > 0) {
        echo "<div class='success'>✓ Found " . count($feeStructure) . " fee assignment(s) for this student</div>";
        echo "<table><tr><th>Fee Head</th><th>Type</th><th>Amount</th></tr>";
        foreach ($feeStructure as $fs) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fs['fee_head_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fs['fee_type']) . "</td>";
            echo "<td>" . number_format($fs['amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        $studentWithFees = $studentId;
    } else {
        echo "<div class='error'>✗ No fee structure assigned to this student!</div>";
        $issues[] = "Student has no fees assigned";

        // Check if ANY student has fees
        $anyStudentWithFees = fetchOne("SELECT student_id FROM fee_structure WHERE is_active = 1 LIMIT 1");
        if ($anyStudentWithFees) {
            echo "<div class='warning'>⚠ Some students have fees assigned, but not this one. Try another student.</div>";
            $studentWithFees = $anyStudentWithFees['student_id'];
        }
    }
}

// Test 4: Check for pending fees
if ($studentWithFees) {
    echo "<h2>4. Checking Pending Fees for Student ID: $studentWithFees</h2>";

    // Get fee structure
    $feeStructure = fetchAll(
        "SELECT fs.*, fh.fee_head_name, fh.fee_type
         FROM fee_structure fs
         JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
         WHERE fs.student_id = ? AND fs.is_active = 1",
        'i',
        [$studentWithFees]
    );

    // Get paid fees
    $paidFees = fetchAll(
        "SELECT frd.fee_head_id, frd.fee_month, frd.fee_year
         FROM fee_receipt_details frd
         JOIN fee_receipts fr ON frd.receipt_id = fr.receipt_id
         WHERE fr.student_id = ? AND fr.is_cancelled = 0",
        'i',
        [$studentWithFees]
    );

    $pendingCount = 0;
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $currentYear = date('Y');
    $currentMonth = date('F');
    $currentMonthIndex = array_search($currentMonth, $months);

    foreach ($feeStructure as $fee) {
        if ($fee['fee_type'] == 'Monthly') {
            foreach ($months as $monthIndex => $month) {
                if ($monthIndex > $currentMonthIndex) continue;

                $isPaid = false;
                foreach ($paidFees as $paid) {
                    if ($paid['fee_head_id'] == $fee['fee_head_id'] &&
                        $paid['fee_month'] == $month &&
                        $paid['fee_year'] == $currentYear) {
                        $isPaid = true;
                        break;
                    }
                }
                if (!$isPaid) $pendingCount++;
            }
        } else {
            $isPaid = false;
            foreach ($paidFees as $paid) {
                if ($paid['fee_head_id'] == $fee['fee_head_id']) {
                    $isPaid = true;
                    break;
                }
            }
            if (!$isPaid) $pendingCount++;
        }
    }

    if ($pendingCount > 0) {
        echo "<div class='success'>✓ Found $pendingCount pending fee(s) for this student</div>";
    } else {
        echo "<div class='warning'>⚠ All fees already collected for this student</div>";
    }
}

// Test 5: Check API endpoints
echo "<h2>5. Checking API Endpoints</h2>";
$apiFiles = [
    'api/search_student.php',
    'api/search_students_by_name.php'
];

foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✓ " . htmlspecialchars($file) . " exists</div>";
    } else {
        echo "<div class='error'>✗ " . htmlspecialchars($file) . " NOT FOUND!</div>";
        $issues[] = "Missing API file: $file";
    }
}

// Test 6: Test search functionality
if (isset($student)) {
    echo "<h2>6. Testing Search API</h2>";
    echo "<button onclick='testSearch()'>Test Search for: " . htmlspecialchars($student['admission_no']) . "</button>";
    echo "<div id='searchResult' style='margin-top: 10px;'></div>";

    echo "<script>
    function testSearch() {
        document.getElementById('searchResult').innerHTML = '<p>Testing...</p>';

        fetch('api/search_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'search_term=" . urlencode($student['admission_no']) . "'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('searchResult').innerHTML =
                    '<div class=\"success\">✓ Search API working! Found: ' + data.student.student_name + ' (ID: ' + data.student.student_id + ')</div>';
            } else {
                document.getElementById('searchResult').innerHTML =
                    '<div class=\"error\">✗ Search failed: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('searchResult').innerHTML =
                '<div class=\"error\">✗ API Error: ' + error + '</div>';
        });
    }
    </script>";
}

// Test 7: Check fee_receipts table structure
echo "<h2>7. Checking Fee Receipts Table</h2>";
$columnsResult = fetchAll("DESCRIBE fee_receipts");
if (count($columnsResult) > 0) {
    echo "<div class='success'>✓ fee_receipts table exists with " . count($columnsResult) . " columns</div>";
    echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columnsResult as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>✗ fee_receipts table not found!</div>";
    $issues[] = "fee_receipts table missing";
}

// Test 8: Check session and permissions
echo "<h2>8. Checking Session & Permissions</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<div class='success'>✓ User logged in (User ID: " . $_SESSION['user_id'] . ")</div>";

    $currentUser = getCurrentUser();
    if ($currentUser) {
        echo "<p><strong>Username:</strong> " . htmlspecialchars($currentUser['username']) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($currentUser['role']) . "</p>";
    }
} else {
    echo "<div class='error'>✗ No active session!</div>";
    $issues[] = "User not logged in";
}

// Summary
echo "<hr><h2>Summary</h2>";

if (count($issues) == 0) {
    echo "<div class='success'><h3>✓ System appears to be configured correctly!</h3>";
    if ($studentWithFees) {
        $testStudent = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$studentWithFees]);
        if ($testStudent) {
            echo "<p><strong>Ready to test with:</strong></p>";
            echo "<ul>";
            echo "<li>Student: " . htmlspecialchars($testStudent['student_name']) . "</li>";
            echo "<li>Admission No: " . htmlspecialchars($testStudent['admission_no']) . "</li>";
            echo "<li>Student ID: " . $studentWithFees . "</li>";
            echo "</ul>";
            echo "<p><a href='modules/fees/collect_complete.php?student_id=$studentWithFees' class='btn' style='background: green; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Fee Collection for This Student</a></p>";
        }
    }
    echo "</div>";
} else {
    echo "<div class='error'><h3>✗ Issues Found:</h3>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ul>";
    echo "<h4>How to Fix:</h4>";
    echo "<ol>";
    if (in_array("No active students", $issues)) {
        echo "<li>Add students via <a href='modules/students/add.php'>Student Management</a></li>";
    }
    if (in_array("No fee heads", $issues)) {
        echo "<li>Create fee heads via <a href='modules/fees/fee_heads.php'>Fee Heads Management</a></li>";
    }
    if (in_array("Student has no fees assigned", $issues)) {
        echo "<li>Assign fees to students via <a href='modules/fees/structure.php'>Fee Structure</a></li>";
    }
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='modules/fees/collect_complete.php'>Go to Fee Collection Page</a> | ";
echo "<a href='test_fee_collection.php'>Run Full Diagnostic</a></p>";
?>
