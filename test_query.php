<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
requireLogin();

$receiptId = isset($_GET['id']) ? intval($_GET['id']) : 8;

echo "<h1>Query Test - Receipt ID: $receiptId</h1>";
echo "<style>body{font-family:Arial;padding:20px;} pre{background:#f8f9fa;padding:15px;border-radius:5px;} .error{color:red;} .success{color:green;}</style>";

echo "<h2>Test 1: Simple Query (what check_database.php uses)</h2>";
try {
    $simple = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);
    if ($simple) {
        echo "<p class='success'>✅ Simple query works!</p>";
        echo "<pre>" . print_r($simple, true) . "</pre>";
    } else {
        echo "<p class='error'>❌ Simple query returned null</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Test 2: Complex Query with JOINs (what pdf_receipt.php uses)</h2>";
try {
    $complex = fetchOne("SELECT
                            fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
                            fr.payment_mode, fr.payment_date, fr.remarks, fr.created_at, fr.is_cancelled,
                            s.student_name, s.admission_no, s.father_name, s.mother_name,
                            s.contact_no, s.address, s.roll_no,
                            c.class_name, sec.section_name,
                            u.full_name as received_by
                         FROM fee_receipts fr
                         LEFT JOIN students s ON fr.student_id = s.student_id
                         LEFT JOIN classes c ON s.class_id = c.class_id
                         LEFT JOIN sections sec ON s.section_id = sec.section_id
                         LEFT JOIN users u ON fr.collected_by = u.user_id
                         WHERE fr.receipt_id = ?", 'i', [$receiptId]);

    if ($complex) {
        echo "<p class='success'>✅ Complex query works!</p>";
        echo "<pre>" . print_r($complex, true) . "</pre>";
    } else {
        echo "<p class='error'>❌ Complex query returned null - THIS IS THE PROBLEM!</p>";

        // Try to get more info
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT
                            fr.receipt_id, fr.receipt_no, fr.student_id, fr.amount_paid,
                            fr.payment_mode, fr.payment_date, fr.remarks, fr.created_at, fr.is_cancelled,
                            s.student_name, s.admission_no, s.father_name, s.mother_name,
                            s.contact_no, s.address, s.roll_no,
                            c.class_name, sec.section_name,
                            u.full_name as received_by
                         FROM fee_receipts fr
                         LEFT JOIN students s ON fr.student_id = s.student_id
                         LEFT JOIN classes c ON s.class_id = c.class_id
                         LEFT JOIN sections sec ON s.section_id = sec.section_id
                         LEFT JOIN users u ON fr.collected_by = u.user_id
                         WHERE fr.receipt_id = ?");
        $stmt->bind_param('i', $receiptId);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<p>Query executed. Rows returned: " . $result->num_rows . "</p>";

        if ($result->num_rows > 0) {
            echo "<p class='success'>Query found data, but fetchOne returned null. Issue with fetchOne function!</p>";
            $row = $result->fetch_assoc();
            echo "<pre>" . print_r($row, true) . "</pre>";
        } else {
            echo "<p class='error'>Query returned 0 rows. Issue with the query itself or data.</p>";
        }

        if ($conn->error) {
            echo "<p class='error'>MySQL Error: " . $conn->error . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Test 3: Check Related Data</h2>";
$receipt = fetchOne("SELECT * FROM fee_receipts WHERE receipt_id = ?", 'i', [$receiptId]);
if ($receipt) {
    echo "<p>Student ID: " . $receipt['student_id'] . "</p>";

    // Check if student exists
    $student = fetchOne("SELECT * FROM students WHERE student_id = ?", 'i', [$receipt['student_id']]);
    if ($student) {
        echo "<p class='success'>✅ Student exists: " . $student['student_name'] . "</p>";
        echo "<p>Class ID: " . ($student['class_id'] ?? 'NULL') . "</p>";
        echo "<p>Section ID: " . ($student['section_id'] ?? 'NULL') . "</p>";

        // Check class
        if (!empty($student['class_id'])) {
            $class = fetchOne("SELECT * FROM classes WHERE class_id = ?", 'i', [$student['class_id']]);
            if ($class) {
                echo "<p class='success'>✅ Class exists: " . $class['class_name'] . "</p>";
            } else {
                echo "<p class='error'>❌ Class doesn't exist! This might cause JOIN issues.</p>";
            }
        } else {
            echo "<p class='error'>❌ Student has no class_id!</p>";
        }

        // Check section
        if (!empty($student['section_id'])) {
            $section = fetchOne("SELECT * FROM sections WHERE section_id = ?", 'i', [$student['section_id']]);
            if ($section) {
                echo "<p class='success'>✅ Section exists: " . $section['section_name'] . "</p>";
            } else {
                echo "<p class='error'>❌ Section doesn't exist! This might cause JOIN issues.</p>";
            }
        } else {
            echo "<p class='error'>❌ Student has no section_id!</p>";
        }
    } else {
        echo "<p class='error'>❌ Student doesn't exist!</p>";
    }

    // Check user
    if (!empty($receipt['collected_by'])) {
        $user = fetchOne("SELECT * FROM users WHERE user_id = ?", 'i', [$receipt['collected_by']]);
        if ($user) {
            echo "<p class='success'>✅ User exists: " . $user['full_name'] . "</p>";
        } else {
            echo "<p class='error'>❌ User doesn't exist!</p>";
        }
    } else {
        echo "<p>No collected_by user set</p>";
    }
}

echo "<hr>";
echo "<p><a href='modules/fees/pdf_receipt.php?id=$receiptId'>Try PDF Receipt</a></p>";
?>
