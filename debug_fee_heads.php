<?php
/**
 * Debug Fee Heads
 * Check the status of all fee heads in the system
 */

require_once 'config/config.php';
requireLogin();

$pageTitle = 'Fee Heads Debug';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-bug"></i> Fee Heads Debug Information</h2>

            <?php
            // Get ALL fee heads
            $allFeeHeads = fetchAll("SELECT * FROM fee_heads ORDER BY fee_head_id");
            ?>

            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">All Fee Heads in Database (<?php echo count($allFeeHeads); ?> total)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($allFeeHeads)): ?>
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> No Fee Heads Found!</h5>
                            <p>The fee_heads table is completely empty. You need to create fee heads first.</p>
                            <a href="<?php echo APP_URL; ?>/modules/settings/fee_heads.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Go to Fee Heads Management
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fee Head Name</th>
                                        <th>Type</th>
                                        <th>Display Order</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allFeeHeads as $feeHead): ?>
                                    <tr class="<?php echo $feeHead['is_active'] ? '' : 'table-danger'; ?>">
                                        <td><?php echo $feeHead['fee_head_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($feeHead['fee_head_name']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $feeHead['fee_type'] == 'Monthly' ? 'primary' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($feeHead['fee_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $feeHead['display_order']; ?></td>
                                        <td>
                                            <?php if ($feeHead['is_active']): ?>
                                                <span class="badge bg-success">✅ ACTIVE</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">❌ INACTIVE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo isset($feeHead['created_at']) ? date('d M Y', strtotime($feeHead['created_at'])) : 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        $activeFeeHeads = array_filter($allFeeHeads, fn($f) => $f['is_active'] == 1);
                        $inactiveFeeHeads = array_filter($allFeeHeads, fn($f) => $f['is_active'] == 0);
                        ?>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="alert alert-<?php echo count($activeFeeHeads) > 0 ? 'success' : 'danger'; ?>">
                                    <h5>✅ Active Fee Heads: <?php echo count($activeFeeHeads); ?></h5>
                                    <?php if (count($activeFeeHeads) > 0): ?>
                                        <p class="mb-0">These fee heads can be assigned to students.</p>
                                    <?php else: ?>
                                        <p class="mb-0">⚠️ <strong>Problem:</strong> No active fee heads! You cannot assign fees to students.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-<?php echo count($inactiveFeeHeads) > 0 ? 'warning' : 'info'; ?>">
                                    <h5>❌ Inactive Fee Heads: <?php echo count($inactiveFeeHeads); ?></h5>
                                    <?php if (count($inactiveFeeHeads) > 0): ?>
                                        <p class="mb-0">These fee heads are hidden and cannot be assigned.</p>
                                    <?php else: ?>
                                        <p class="mb-0">All fee heads are active. Good!</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (count($inactiveFeeHeads) > 0): ?>
                            <div class="alert alert-warning alert-permanent">
                                <h5><i class="bi bi-lightbulb"></i> Solution to Activate Fee Heads:</h5>
                                <ol>
                                    <li>Go to <a href="<?php echo APP_URL; ?>/modules/settings/fee_heads.php" class="btn btn-sm btn-warning">Settings → Fee Heads</a></li>
                                    <li>Click the <strong>Edit (✏️)</strong> button for each inactive fee head</li>
                                    <li>Turn ON the <strong>"Active"</strong> toggle switch</li>
                                    <li>Click <strong>"Save Changes"</strong></li>
                                </ol>
                                <p class="mb-0"><strong>Note:</strong> The red rows in the table above show inactive fee heads.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3">
                <a href="<?php echo APP_URL; ?>/modules/fees/structure.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Fee Structure
                </a>
                <a href="<?php echo APP_URL; ?>/modules/settings/fee_heads.php" class="btn btn-warning">
                    <i class="bi bi-pencil-square"></i> Manage Fee Heads
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
