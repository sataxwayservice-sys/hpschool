<?php
/**
 * Test Fee Collection System
 * Diagnostic page to check all components
 */

require_once 'config/config.php';

echo "<h2>Fee Collection System Diagnostic</h2>";
echo "<p>This page will check if all required components for fee collection are working.</p>";
echo "<hr>";

// Test 1: Check if students table has data
echo "<h3>Step 1: Check Students Table</h3>";
$students = fetchAll("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
if ($students[0]['count'] > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Found " . $students[0]['count'] . " active student(s)";
    echo "</div>";

    // Show first 5 students
    $sampleStudents = fetchAll("SELECT student_id, admission_no, student_name, status FROM students WHERE status = 'Active' LIMIT 5");
    echo "<h4>Sample Students:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Admission No</th><th>Name</th><th>Status</th></tr>";
    foreach ($sampleStudents as $s) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($s['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($s['admission_no']) . "</td>";
        echo "<td>" . htmlspecialchars($s['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($s['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ No active students found! You need to add students first.";
    echo "</div>";
}

echo "<hr>";

// Test 2: Check fee_heads table
echo "<h3>Step 2: Check Fee Heads Table</h3>";
$feeHeads = fetchAll("SELECT COUNT(*) as count FROM fee_heads WHERE is_active = 1");
if ($feeHeads[0]['count'] > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Found " . $feeHeads[0]['count'] . " active fee head(s)";
    echo "</div>";

    $sampleFeeHeads = fetchAll("SELECT * FROM fee_heads WHERE is_active = 1 LIMIT 5");
    echo "<h4>Sample Fee Heads:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Amount</th></tr>";
    foreach ($sampleFeeHeads as $fh) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($fh['fee_head_id']) . "</td>";
        echo "<td>" . htmlspecialchars($fh['fee_head_name']) . "</td>";
        echo "<td>" . htmlspecialchars($fh['fee_type']) . "</td>";
        echo "<td>" . htmlspecialchars($fh['default_amount'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ No fee heads found! You need to add fee heads first.";
    echo "</div>";
}

echo "<hr>";

// Test 3: Check fee_structure table
echo "<h3>Step 3: Check Fee Structure Assignments</h3>";
$feeStructure = fetchAll("SELECT COUNT(*) as count FROM fee_structure WHERE is_active = 1");
if ($feeStructure[0]['count'] > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ Found " . $feeStructure[0]['count'] . " fee structure assignment(s)";
    echo "</div>";

    $sampleStructure = fetchAll("SELECT fs.*, s.student_name, s.admission_no, fh.fee_head_name
                                 FROM fee_structure fs
                                 JOIN students s ON fs.student_id = s.student_id
                                 JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
                                 WHERE fs.is_active = 1
                                 LIMIT 10");
    if (count($sampleStructure) > 0) {
        echo "<h4>Sample Fee Assignments:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Student</th><th>Admission No</th><th>Fee Head</th><th>Amount</th></tr>";
        foreach ($sampleStructure as $fs) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fs['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($fs['admission_no']) . "</td>";
            echo "<td>" . htmlspecialchars($fs['fee_head_name']) . "</td>";
            echo "<td>" . number_format($fs['amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid orange;'>";
    echo "⚠ No fee structure assignments found! Students need to have fees assigned to them.";
    echo "<br>Go to Fee Structure page to assign fees to students.";
    echo "</div>";
}

echo "<hr>";

// Test 4: Check fee_receipts table structure
echo "<h3>Step 4: Check Fee Receipts Table Structure</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'fee_receipts'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ fee_receipts table EXISTS";
    echo "</div>";

    $columns = fetchAll("DESCRIBE fee_receipts");
    echo "<h4>Table Columns:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid red;'>";
    echo "✗ fee_receipts table DOES NOT EXIST";
    echo "</div>";
}

echo "<hr>";

// Test 5: Test search API
echo "<h3>Step 5: Test Search API</h3>";
if ($students[0]['count'] > 0) {
    $firstStudent = fetchOne("SELECT * FROM students WHERE status = 'Active' LIMIT 1");
    if ($firstStudent) {
        echo "<p><strong>Test student:</strong> " . htmlspecialchars($firstStudent['student_name']) . " (Adm No: " . htmlspecialchars($firstStudent['admission_no']) . ")</p>";
        echo "<button onclick='testSearch(\"" . htmlspecialchars($firstStudent['admission_no']) . "\")'>Test Search by Admission Number</button>";
        echo "<button onclick='testSearch(\"" . htmlspecialchars(substr($firstStudent['student_name'], 0, 5)) . "\")' style='margin-left: 10px;'>Test Search by Name</button>";
        echo "<div id='searchResult' style='margin-top: 10px;'></div>";
    }
} else {
    echo "<p>No students to test with.</p>";
}

echo "<hr>";

// Test 6: Check receipt_books table (optional)
echo "<h3>Step 6: Check Receipt Books Table (Optional)</h3>";
$tableCheck = fetchAll("SHOW TABLES LIKE 'receipt_books'");
if (count($tableCheck) > 0) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid green;'>";
    echo "✓ receipt_books table EXISTS (optional feature)";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid orange;'>";
    echo "⚠ receipt_books table doesn't exist (optional feature)";
    echo "<br>Visit <a href='test_receipt_tables.php'>test_receipt_tables.php</a> to create it.";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If all tests above are green, you're ready to use the fee collection system</li>";
echo "<li>Visit <a href='modules/fees/collect_complete.php'>Fee Collection Page</a></li>";
echo "<li>If any tests are red or orange, fix those issues first:";
echo "<ul>";
echo "<li>Add students via Student Management</li>";
echo "<li>Add fee heads via Fee Heads Management</li>";
echo "<li>Assign fees to students via Fee Structure</li>";
echo "</ul>";
echo "</li>";
echo "</ol>";

?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function testSearch(searchTerm) {
    document.getElementById('searchResult').innerHTML = '<p>Testing search for: <strong>' + searchTerm + '</strong>...</p>';

    fetch('api/search_student.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'search_term=' + encodeURIComponent(searchTerm)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('searchResult').innerHTML =
                '<div style="background: #d4edda; padding: 10px; border: 1px solid green;">' +
                '✓ Search API working! Found student: ' + data.student.student_name +
                ' (ID: ' + data.student.student_id + ')' +
                '</div>';
        } else {
            document.getElementById('searchResult').innerHTML =
                '<div style="background: #fff3cd; padding: 10px; border: 1px solid orange;">' +
                '⚠ Search returned: ' + data.message +
                '</div>';
        }
    })
    .catch(error => {
        document.getElementById('searchResult').innerHTML =
            '<div style="background: #f8d7da; padding: 10px; border: 1px solid red;">' +
            '✗ Search API error: ' + error +
            '</div>';
    });
}
</script>
