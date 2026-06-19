<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

echo "<h1>Fee Structure Debug</h1>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#0d6efd;color:white;} .info{background:#d1ecf1;padding:15px;border-radius:5px;margin:20px 0;}</style>";

if ($studentId <= 0) {
    echo "<p style='color:red;'>Please provide student_id in URL: ?student_id=X</p>";
    exit;
}

// Get student info
$student = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$studentId]);
if (!$student) {
    echo "<p style='color:red;'>Student not found!</p>";
    exit;
}

echo "<div class='info'>";
echo "<h3>Student: " . htmlspecialchars($student['student_name']) . " (ID: $studentId)</h3>";
echo "<p>Admission No: " . htmlspecialchars($student['admission_no']) . "</p>";
echo "</div>";

// Get ALL fee structure entries (no GROUP BY)
echo "<h2>1. All Fee Structure Entries (Raw Data)</h2>";
$allEntries = fetchAll(
    "SELECT fs.*, fh.fee_head_name, fh.fee_type
     FROM fee_structure fs
     JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
     WHERE fs.student_id = ?
     ORDER BY fs.fee_head_id, fs.structure_id",
    'i',
    [$studentId]
);

echo "<p><strong>Total entries found: " . count($allEntries) . "</strong></p>";

if (count($allEntries) > 0) {
    echo "<table>";
    echo "<tr><th>Structure ID</th><th>Fee Head ID</th><th>Fee Head Name</th><th>Fee Type</th><th>Amount</th><th>Is Active</th></tr>";

    foreach ($allEntries as $entry) {
        $activeStatus = $entry['is_active'] ? '<span style="color:green;">✓ Active</span>' : '<span style="color:red;">✗ Inactive</span>';
        echo "<tr>";
        echo "<td>" . $entry['structure_id'] . "</td>";
        echo "<td>" . $entry['fee_head_id'] . "</td>";
        echo "<td>" . htmlspecialchars($entry['fee_head_name']) . "</td>";
        echo "<td>" . htmlspecialchars($entry['fee_type']) . "</td>";
        echo "<td>₹" . number_format($entry['amount'], 2) . "</td>";
        echo "<td>" . $activeStatus . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

// Get deduplicated entries (with GROUP BY - as used in collect_complete.php)
echo "<h2>2. Deduplicated Fee Structure (After GROUP BY)</h2>";
$deduped = fetchAll(
    "SELECT fs.fee_head_id, MAX(fs.amount) as amount, fh.fee_head_name, fh.fee_type
     FROM fee_structure fs
     JOIN fee_heads fh ON fs.fee_head_id = fh.fee_head_id
     WHERE fs.student_id = ? AND fs.is_active = 1
     GROUP BY fs.fee_head_id, fh.fee_head_name, fh.fee_type
     ORDER BY fh.display_order",
    'i',
    [$studentId]
);

echo "<p><strong>Total unique fee heads: " . count($deduped) . "</strong></p>";

if (count($deduped) > 0) {
    echo "<table>";
    echo "<tr><th>Fee Head ID</th><th>Fee Head Name</th><th>Fee Type</th><th>Amount</th><th>Months (if Monthly)</th></tr>";

    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $currentMonth = date('F');
    $currentMonthIndex = array_search($currentMonth, $months);

    foreach ($deduped as $entry) {
        echo "<tr>";
        echo "<td>" . $entry['fee_head_id'] . "</td>";
        echo "<td>" . htmlspecialchars($entry['fee_head_name']) . "</td>";
        echo "<td>" . htmlspecialchars($entry['fee_type']) . "</td>";
        echo "<td>₹" . number_format($entry['amount'], 2) . "</td>";

        if ($entry['fee_type'] == 'Monthly') {
            $monthCount = $currentMonthIndex + 1;
            echo "<td><strong>$monthCount months</strong> (Jan-" . $currentMonth . ")</td>";
        } else {
            echo "<td>One-time fee</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
}

// Calculate expected pending entries
echo "<h2>3. Expected Pending Fee Entries</h2>";
echo "<div class='info'>";
echo "<p><strong>Current Month:</strong> " . date('F Y') . "</p>";

$totalExpected = 0;
foreach ($deduped as $entry) {
    if ($entry['fee_type'] == 'Monthly') {
        $monthCount = $currentMonthIndex + 1;
        echo "<p>• <strong>" . htmlspecialchars($entry['fee_head_name']) . "</strong>: $monthCount entries (one per month)</p>";
        $totalExpected += $monthCount;
    } else {
        echo "<p>• <strong>" . htmlspecialchars($entry['fee_head_name']) . "</strong>: 1 entry (one-time)</p>";
        $totalExpected += 1;
    }
}

echo "<hr>";
echo "<p style='font-size:18px;'><strong>Total Expected Pending Entries:</strong> <span style='color:blue;font-size:24px;'>$totalExpected</span></p>";
echo "</div>";

// Check for duplicates
echo "<h2>4. Duplicate Analysis</h2>";
$duplicates = [];
$feeHeadCounts = [];

foreach ($allEntries as $entry) {
    if ($entry['is_active']) {
        $feeHeadId = $entry['fee_head_id'];
        if (!isset($feeHeadCounts[$feeHeadId])) {
            $feeHeadCounts[$feeHeadId] = [
                'name' => $entry['fee_head_name'],
                'count' => 0
            ];
        }
        $feeHeadCounts[$feeHeadId]['count']++;
    }
}

$hasDuplicates = false;
foreach ($feeHeadCounts as $feeHeadId => $data) {
    if ($data['count'] > 1) {
        $hasDuplicates = true;
        echo "<p style='color:red;'><strong>⚠️ DUPLICATE FOUND:</strong> " . htmlspecialchars($data['name']) . " (Fee Head ID: $feeHeadId) has <strong>" . $data['count'] . " active entries</strong></p>";
    }
}

if (!$hasDuplicates) {
    echo "<p style='color:green;'><strong>✓ No duplicates found!</strong> Each fee head appears only once.</p>";
} else {
    echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h4>⚠️ Recommendation:</h4>";
    echo "<p>You have duplicate fee structure entries. This causes the same months to appear multiple times in the pending fees list.</p>";
    echo "<p><strong>Solution:</strong> Remove duplicate entries from the fee_structure table for this student.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='modules/fees/collect_complete.php?student_id=$studentId' style='padding:10px 20px;background:#0d6efd;color:white;text-decoration:none;border-radius:5px;'>→ Go to Fee Collection Page</a></p>";
?>
