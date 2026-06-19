<?php
/**
 * Check fee_receipts table schema
 */

require_once 'config/config.php';
requireLogin();

$pageTitle = 'Schema Check';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2>fee_receipts Table Schema</h2>

    <?php
    $conn = getDbConnection();

    // Get column information
    $columnsQuery = "SHOW COLUMNS FROM fee_receipts";
    $result = $conn->query($columnsQuery);
    ?>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><strong><?php echo $row['Field']; ?></strong></td>
                <td><?php echo $row['Type']; ?></td>
                <td><?php echo $row['Null']; ?></td>
                <td><?php echo $row['Key']; ?></td>
                <td><?php echo $row['Default'] ?? 'NULL'; ?></td>
                <td><?php echo $row['Extra']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <h3 class="mt-4">Test: What columns does the code expect?</h3>
    <div class="alert alert-info alert-permanent">
        <p>The collect_complete.php code tries to insert into these columns:</p>
        <ul>
            <li><strong>receipt_no</strong> - Receipt number (e.g., REC000001)</li>
            <li><strong>student_id</strong> - Student ID</li>
            <li><strong>total_amount</strong> - Total amount</li>
            <li><strong>amount_paid</strong> - Amount paid</li>
            <li><strong>payment_mode</strong> - Payment mode (Cash/Cheque/etc)</li>
            <li><strong>transaction_id</strong> - Cheque/Transaction number</li>
            <li><strong>payment_date</strong> - Payment date</li>
            <li><strong>collected_by</strong> - User ID who collected</li>
            <li><strong>remarks</strong> - Remarks</li>
        </ul>
        <p class="mb-0"><strong>Check if "collected_by" column exists!</strong> It might need to be "user_id" instead.</p>
    </div>

    <?php
    // Check for "collected_by" vs "user_id"
    $columnsResult = $conn->query("SHOW COLUMNS FROM fee_receipts");
    $hasCollectedBy = false;
    $hasUserId = false;
    $hasPaymentDate = false;
    $hasReceiptDate = false;

    while ($col = $columnsResult->fetch_assoc()) {
        if ($col['Field'] === 'collected_by') $hasCollectedBy = true;
        if ($col['Field'] === 'user_id') $hasUserId = true;
        if ($col['Field'] === 'payment_date') $hasPaymentDate = true;
        if ($col['Field'] === 'receipt_date') $hasReceiptDate = true;
    }
    ?>

    <div class="alert alert-<?php echo $hasCollectedBy ? 'success' : 'danger'; ?> alert-permanent">
        <?php if ($hasCollectedBy): ?>
            ✅ <strong>collected_by</strong> column exists
        <?php else: ?>
            ❌ <strong>collected_by</strong> column MISSING!
            <?php if ($hasUserId): ?>
                <br>But <strong>user_id</strong> column exists - this is the problem!
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="alert alert-<?php echo $hasPaymentDate ? 'success' : 'danger'; ?> alert-permanent">
        <?php if ($hasPaymentDate): ?>
            <strong>payment_date</strong> column exists
        <?php else: ?>
            <strong>payment_date</strong> column MISSING! Receipt pages need this column.
        <?php endif; ?>
    </div>

    <?php if ($hasReceiptDate): ?>
    <div class="alert alert-warning alert-permanent">
        Legacy <strong>receipt_date</strong> column found. The app now uses <strong>payment_date</strong>.
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
