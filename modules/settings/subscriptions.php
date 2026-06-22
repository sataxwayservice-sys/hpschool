<?php
/**
 * School Subscription Management
 * Super Admin only: control free/premium pricing, ads, and payment metadata per school
 */

require_once '../../config/config.php';

requireLogin();
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'super_admin') {
    $_SESSION['error_message'] = 'Access denied. Only Super Admin can manage subscriptions.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

ensureSchoolSettingsSchema();
ensureSchoolRegistrationSchema();

$pageTitle = 'School Subscriptions';
$error = '';
$success = '';

$schools = fetchAll(
    "SELECT school_id, school_name, school_code, status, created_at
     FROM schools
     ORDER BY created_at DESC, school_id DESC"
);

$selectedSchoolId = intval($_GET['school_id'] ?? ($_POST['school_id'] ?? 0));
if ($selectedSchoolId <= 0 && !empty($schools)) {
    $selectedSchoolId = intval($schools[0]['school_id']);
}

$selectedSchool = $selectedSchoolId > 0 ? schoolRegistrationGetSchoolById($selectedSchoolId) : null;
$selectedSettings = $selectedSchoolId > 0 ? getSchoolSettingsBySchoolId($selectedSchoolId) : null;

if ($selectedSchool && !$selectedSettings && function_exists('schoolRegistrationSyncApprovedSchoolSettings')) {
    schoolRegistrationSyncApprovedSchoolSettings($selectedSchool);
    $selectedSettings = getSchoolSettingsBySchoolId($selectedSchoolId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_subscription'])) {
    $postedSchoolId = intval($_POST['school_id'] ?? 0);
    $plan = strtolower(trim((string)($_POST['subscription_plan'] ?? 'free')));
    $allowedPlans = ['free', 'premium', 'enterprise'];
    if (!in_array($plan, $allowedPlans, true)) {
        $plan = 'free';
    }

    $price = floatval($_POST['subscription_price'] ?? 0);
    $currencyCode = strtoupper(trim((string)($_POST['subscription_currency_code'] ?? 'INR')));
    $billingCycle = strtolower(trim((string)($_POST['subscription_billing_cycle'] ?? 'monthly')));
    $allowedCycles = ['monthly', 'quarterly', 'yearly', 'custom'];
    if (!in_array($billingCycle, $allowedCycles, true)) {
        $billingCycle = 'monthly';
    }

    $status = strtolower(trim((string)($_POST['subscription_status'] ?? 'active')));
    $allowedStatuses = ['active', 'pending', 'trial', 'expired', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'active';
    }

    $adsEnabled = isset($_POST['ads_enabled']) ? 1 : 0;
    if (in_array($plan, ['premium', 'enterprise'], true)) {
        $adsEnabled = 0;
    }

    $startedAt = trim((string)($_POST['subscription_started_at'] ?? ''));
    $expiresAt = trim((string)($_POST['subscription_expires_at'] ?? ''));
    $gateway = strtolower(trim((string)($_POST['subscription_gateway'] ?? 'manual')));
    $allowedGateways = ['manual', 'upi', 'razorpay'];
    if (!in_array($gateway, $allowedGateways, true)) {
        $gateway = 'manual';
    }

    $gatewayReference = trim((string)($_POST['subscription_gateway_reference'] ?? ''));
    $paymentLink = trim((string)($_POST['subscription_payment_link'] ?? ''));
    $notes = trim((string)($_POST['subscription_notes'] ?? ''));

    $school = $postedSchoolId > 0 ? schoolRegistrationGetSchoolById($postedSchoolId) : null;
    if (!$school) {
        $error = 'School not found.';
    } else {
        $settings = getSchoolSettingsBySchoolId($postedSchoolId);
        if (!$settings && function_exists('schoolRegistrationSyncApprovedSchoolSettings')) {
            schoolRegistrationSyncApprovedSchoolSettings($school);
            $settings = getSchoolSettingsBySchoolId($postedSchoolId);
        }

        if (!$settings || empty($settings['setting_id'])) {
            $error = 'Could not load the subscription settings row for this school.';
        } else {
            $schoolName = trim((string)($school['school_name'] ?? ''));
            $upiId = trim((string)($settings['upi_id'] ?? ''));
            $recipient = trim((string)($settings['payment_recipient_name'] ?? $schoolName));

            if ($paymentLink === '' && $gateway === 'upi' && $upiId !== '' && $price > 0) {
                $paymentLink = 'upi://pay?pa=' . rawurlencode($upiId)
                    . '&pn=' . rawurlencode($recipient !== '' ? $recipient : $schoolName)
                    . '&am=' . number_format($price, 2, '.', '')
                    . '&cu=' . rawurlencode($currencyCode ?: 'INR')
                    . '&tn=' . rawurlencode($schoolName . ' subscription');
            }

            $updateQuery = "UPDATE school_settings SET
                                subscription_plan = ?,
                                subscription_price = ?,
                                subscription_currency_code = ?,
                                subscription_billing_cycle = ?,
                                subscription_status = ?,
                                ads_enabled = ?,
                                subscription_started_at = ?,
                                subscription_expires_at = ?,
                                subscription_gateway = ?,
                                subscription_gateway_reference = ?,
                                subscription_payment_link = ?,
                                subscription_notes = ?,
                                updated_at = NOW()
                            WHERE setting_id = ?";

            $updateValues = [
                $plan,
                $price,
                $currencyCode ?: 'INR',
                $billingCycle,
                $status,
                $adsEnabled,
                $startedAt !== '' ? $startedAt : null,
                $expiresAt !== '' ? $expiresAt : null,
                $gateway,
                $gatewayReference,
                $paymentLink,
                $notes,
                intval($settings['setting_id'])
            ];

            $updateTypes = 'sdsssissssssi';
            $result = executeQuery($updateQuery, $updateTypes, $updateValues);

            if ($result !== false) {
                $success = 'Subscription settings updated successfully.';
                logActivity(
                    intval($currentUser['user_id']),
                    'Update Subscription',
                    'Subscriptions',
                    'Updated subscription settings for school: ' . $schoolName . ' (' . $plan . ')'
                );

                $selectedSettings = getSchoolSettingsBySchoolId($postedSchoolId);
                $selectedSchoolId = $postedSchoolId;
            } else {
                $error = 'Failed to save subscription settings.';
            }
        }
    }
}

$selectedSettings = $selectedSchoolId > 0 ? ($selectedSettings ?: getSchoolSettingsBySchoolId($selectedSchoolId)) : null;
$planLabel = ucfirst(trim((string)($selectedSettings['subscription_plan'] ?? 'free')));
$priceValue = floatval($selectedSettings['subscription_price'] ?? 0);
$currencyCode = trim((string)($selectedSettings['subscription_currency_code'] ?? 'INR')) ?: 'INR';
$billingCycleLabel = ucfirst(trim((string)($selectedSettings['subscription_billing_cycle'] ?? 'monthly')));
$statusLabel = ucfirst(trim((string)($selectedSettings['subscription_status'] ?? 'active')));
$adsEnabled = intval($selectedSettings['ads_enabled'] ?? 1) === 1;
$gatewayValue = trim((string)($selectedSettings['subscription_gateway'] ?? 'manual'));
$paymentLinkValue = trim((string)($selectedSettings['subscription_payment_link'] ?? ''));
$gatewayReferenceValue = trim((string)($selectedSettings['subscription_gateway_reference'] ?? ''));

$generatedUpiLink = '';
if ($selectedSchoolId > 0) {
    $upiId = trim((string)($selectedSettings['upi_id'] ?? ''));
    $schoolName = trim((string)($selectedSchool['school_name'] ?? ''));
    $recipient = trim((string)($selectedSettings['payment_recipient_name'] ?? $schoolName));
    if ($upiId !== '' && $priceValue > 0) {
        $generatedUpiLink = 'upi://pay?pa=' . rawurlencode($upiId)
            . '&pn=' . rawurlencode($recipient !== '' ? $recipient : $schoolName)
            . '&am=' . number_format($priceValue, 2, '.', '')
            . '&cu=' . rawurlencode($currencyCode)
            . '&tn=' . rawurlencode($schoolName . ' subscription');
    }
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-credit-card-2-front"></i> School Subscriptions
                </h2>
                <p class="text-muted mb-0">Control free and premium pricing, ads, and payment details for each school.</p>
            </div>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="alert alert-info border">
    <strong>Super Admin control:</strong> Set the school plan, pricing, gateway, and payment link here. School admins only choose a plan from the upgrade page and continue to the configured payment flow.
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-primary">
            <div class="card-body">
                <h6 class="mb-1">Plan</h6>
                <h4 class="mb-0"><?php echo htmlspecialchars($planLabel); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-success">
            <div class="card-body">
                <h6 class="mb-1">Price</h6>
                <h4 class="mb-0"><?php echo htmlspecialchars($currencyCode . ' ' . number_format($priceValue, 2)); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-info">
            <div class="card-body">
                <h6 class="mb-1">Status</h6>
                <h4 class="mb-0"><?php echo htmlspecialchars($statusLabel); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card text-white bg-dark">
            <div class="card-body">
                <h6 class="mb-1">Ads</h6>
                <h4 class="mb-0"><?php echo $adsEnabled ? 'Enabled' : 'Hidden'; ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Select School</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">School</label>
                        <select name="school_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo intval($school['school_id']); ?>" <?php echo intval($school['school_id']) === $selectedSchoolId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($school['school_name'] ?? '-') . ' (' . ($school['school_code'] ?? '-') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Load School
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedSchool): ?>
    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> School Subscription Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">
                        <input type="hidden" name="save_subscription" value="1">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Subscription Plan</label>
                                <select name="subscription_plan" class="form-select" id="subscriptionPlan">
                                    <option value="free" <?php echo ($selectedSettings['subscription_plan'] ?? 'free') === 'free' ? 'selected' : ''; ?>>Free Plan (Ads Enabled)</option>
                                    <option value="premium" <?php echo ($selectedSettings['subscription_plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium (No Ads)</option>
                                    <option value="enterprise" <?php echo ($selectedSettings['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise (Custom)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" min="0" name="subscription_price" class="form-control" value="<?php echo htmlspecialchars((string)$priceValue); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency</label>
                                <input type="text" name="subscription_currency_code" class="form-control" value="<?php echo htmlspecialchars($currencyCode); ?>" maxlength="10">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Billing Cycle</label>
                                <select name="subscription_billing_cycle" class="form-select">
                                    <option value="monthly" <?php echo ($selectedSettings['subscription_billing_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="quarterly" <?php echo ($selectedSettings['subscription_billing_cycle'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="yearly" <?php echo ($selectedSettings['subscription_billing_cycle'] ?? '') === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                    <option value="custom" <?php echo ($selectedSettings['subscription_billing_cycle'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Subscription Status</label>
                                <select name="subscription_status" class="form-select">
                                    <option value="active" <?php echo ($selectedSettings['subscription_status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="trial" <?php echo ($selectedSettings['subscription_status'] ?? '') === 'trial' ? 'selected' : ''; ?>>Trial</option>
                                    <option value="pending" <?php echo ($selectedSettings['subscription_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending Payment</option>
                                    <option value="expired" <?php echo ($selectedSettings['subscription_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="cancelled" <?php echo ($selectedSettings['subscription_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label d-block">Ads</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="ads_enabled" id="adsEnabled" value="1" <?php echo $adsEnabled ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="adsEnabled">Show ads/promotional banner on free plan</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Subscription Start Date</label>
                                <input type="date" class="form-control" name="subscription_started_at"
                                       value="<?php echo !empty($selectedSettings['subscription_started_at']) ? htmlspecialchars(date('Y-m-d', strtotime($selectedSettings['subscription_started_at']))) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subscription Expiry Date</label>
                                <input type="date" class="form-control" name="subscription_expires_at"
                                       value="<?php echo !empty($selectedSettings['subscription_expires_at']) ? htmlspecialchars(date('Y-m-d', strtotime($selectedSettings['subscription_expires_at']))) : ''; ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Payment Gateway</label>
                                <select name="subscription_gateway" class="form-select" id="subscriptionGateway">
                                    <option value="manual" <?php echo $gatewayValue === 'manual' ? 'selected' : ''; ?>>Manual</option>
                                    <option value="upi" <?php echo $gatewayValue === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                    <option value="razorpay" <?php echo $gatewayValue === 'razorpay' ? 'selected' : ''; ?>>Razorpay</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gateway Reference / Order No.</label>
                                <input type="text" class="form-control" name="subscription_gateway_reference"
                                       value="<?php echo htmlspecialchars($gatewayReferenceValue); ?>"
                                       placeholder="Gateway order id, transaction id, or manual ref">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Link</label>
                                <input type="text" class="form-control" name="subscription_payment_link"
                                       value="<?php echo htmlspecialchars($paymentLinkValue); ?>"
                                       placeholder="Optional payment checkout link">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Subscription Notes</label>
                                <textarea class="form-control" name="subscription_notes" rows="3"
                                          placeholder="Internal notes for this school subscription"><?php echo htmlspecialchars((string)($selectedSettings['subscription_notes'] ?? '')); ?></textarea>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-3">
                            <strong>Payment preview:</strong>
                            <?php if ($generatedUpiLink !== ''): ?>
                                <div class="mt-2">
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($generatedUpiLink); ?>" target="_blank" rel="noopener">
                                        <i class="bi bi-wallet2"></i> Open UPI Payment Link
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copySubscriptionLink()">
                                        <i class="bi bi-clipboard"></i> Copy Link
                                    </button>
                                </div>
                                <input type="text" class="form-control form-control-sm mt-2" id="subscriptionPaymentLinkPreview" readonly value="<?php echo htmlspecialchars($generatedUpiLink); ?>">
                            <?php else: ?>
                                <div class="mb-0">Set a UPI ID and a price to generate a payment link preview.</div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Save Subscription
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-building"></i> School Summary</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>School Name:</strong> <?php echo htmlspecialchars($selectedSchool['school_name'] ?? '-'); ?></p>
                    <p class="mb-2"><strong>School Code:</strong> <?php echo htmlspecialchars($selectedSchool['school_code'] ?? '-'); ?></p>
                    <p class="mb-2"><strong>Current Plan:</strong> <?php echo htmlspecialchars($planLabel); ?></p>
                    <p class="mb-2"><strong>Price:</strong> <?php echo htmlspecialchars($currencyCode . ' ' . number_format($priceValue, 2)); ?></p>
                    <p class="mb-2"><strong>Status:</strong> <?php echo htmlspecialchars($statusLabel); ?></p>
                    <p class="mb-2"><strong>Ads:</strong> <?php echo $adsEnabled ? 'Enabled' : 'Hidden'; ?></p>
                    <p class="mb-2"><strong>Gateway:</strong> <?php echo htmlspecialchars(ucfirst($gatewayValue)); ?></p>
                    <p class="mb-2"><strong>Gateway Ref:</strong> <?php echo htmlspecialchars($gatewayReferenceValue !== '' ? $gatewayReferenceValue : 'N/A'); ?></p>
                    <p class="mb-0"><strong>Payment Link:</strong> <?php echo !empty($paymentLinkValue) ? 'Saved' : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        No school records were found.
    </div>
<?php endif; ?>

<?php
$inlineScript = "
function copySubscriptionLink() {
    const linkField = document.getElementById('subscriptionPaymentLinkPreview');
    if (!linkField) {
        return;
    }
    linkField.select();
    linkField.setSelectionRange(0, 99999);
    document.execCommand('copy');
}

const subscriptionPlan = document.getElementById('subscriptionPlan');
const adsEnabled = document.getElementById('adsEnabled');
if (subscriptionPlan && adsEnabled) {
    const syncAdsToggle = () => {
        const plan = (subscriptionPlan.value || 'free').toLowerCase();
        if (plan === 'free') {
            adsEnabled.disabled = false;
        } else {
            adsEnabled.checked = false;
            adsEnabled.disabled = true;
        }
    };
    subscriptionPlan.addEventListener('change', syncAdsToggle);
    syncAdsToggle();
}
";

include '../../includes/footer.php';
?>
