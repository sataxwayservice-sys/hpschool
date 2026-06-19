<?php
/**
 * Minimal Fee Collection Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/config.php';
requireLogin();

$currentUser = getCurrentUser();

echo "<h1>Fee Collection - Minimal Test</h1>";

// Test 1: Can we query students?
echo "<h2>Test 1: Students Query</h2>";
try {
    $students = fetchAll("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
    echo "<p style='color: green;'>✓ Students query works. Found: " . $students[0]['count'] . " students</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test 2: Can we query batches?
echo "<h2>Test 2: Batches Query</h2>";
try {
    $batches = fetchAll("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC");
    echo "<p style='color: green;'>✓ Batches query works. Found: " . count($batches) . " batches</p>";
    foreach ($batches as $b) {
        echo "- " . htmlspecialchars($b['batch']) . "<br>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test 3: Check getMonths function
echo "<h2>Test 3: getMonths() Function</h2>";
try {
    $months = getMonths();
    echo "<p style='color: green;'>✓ getMonths() works. Returns: " . count($months) . " months</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test 4: Check SCHOOL_NAME constant
echo "<h2>Test 4: SCHOOL_NAME Constant</h2>";
if (defined('SCHOOL_NAME')) {
    echo "<p style='color: green;'>✓ SCHOOL_NAME defined: " . htmlspecialchars(SCHOOL_NAME) . "</p>";
} else {
    echo "<p style='color: orange;'>⚠ SCHOOL_NAME not defined</p>";
}

// Test 5: Simple HTML rendering
echo "<h2>Test 5: HTML Form Rendering</h2>";
?>

<form>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6>Assign To</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Organization</label>
                        <input type="text" class="form-control" value="<?php echo SCHOOL_NAME ?? 'Test School'; ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label>Batch</label>
                        <select class="form-select">
                            <option value="">-- Select Batch --</option>
                            <?php foreach ($batches as $batch): ?>
                                <option><?php echo htmlspecialchars($batch['batch']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Search Student</label>
                        <div class="input-group">
                        <input type="text" class="form-control"
                               placeholder="Enter admission number"
                               autocomplete="off"
                               data-student-autocomplete="true"
                               data-student-autocomplete-fill="admission_no">
                            <button type="button" class="btn btn-primary">Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<p style='color: green;'>✓ If you can see the form above, HTML rendering works!</p>

<hr>
<p><a href="collect_complete.php">Back to Full Fee Collection Page</a></p>

<?php
echo "<h2>Page Completed Successfully</h2>";
echo "<p>If you see this message, PHP is executing completely.</p>";
?>
