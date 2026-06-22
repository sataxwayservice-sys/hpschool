<?php
/**
 * School Subscription Upgrade
 * Admin users can open the payment link configured by Super Admin and mark the subscription as pending.
 */

require_once '../../config/config.php';

requireLogin();

$currentUser = getCurrentUser();
$currentRole = strtolower(trim((string)($currentUser['role'] ?? '')));
$currentSchoolId = getCurrentSchoolId();

if ($currentRole !== 'admin' || $currentSchoolId <= 0) {
    $_SESSION['error_message'] = 'Access denied. Only school admin users can upgrade the subscription for their school.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

ensureSchoolSettingsSchema();
ensureSchoolRegistrationSchema();

$pageTitle = 'Upgrade Subscription';
$error = '';
$success = '';

$school = function_exists('schoolRegistrationGetSchoolById')
    ? schoolRegistrationGetSchoolById($currentSchoolId)
    : null;
$settings = getSchoolSettingsBySchoolId($currentSchoolId);
$subscription = getSchoolSubscriptionDetails($currentSchoolId);

if (!$school || !$settings) {
    $_SESSION['error_message'] = 'School subscription settings were not found. Please contact the Super Admin.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

$subscriptionPlan = ucfirst(trim((string)($subscription['subscription_plan'] ?? 'free')));
$subscriptionStatus = ucfirst(trim((string)($subscription['subscription_status'] ?? 'active')));
$subscriptionPrice = formatCurrency(floatval($subscription['subscription_price'] ?? 0));
$subscriptionPaymentLink = function_exists('getSchoolSubscriptionPaymentLink')
    ? getSchoolSubscriptionPaymentLink($currentSchoolId)
    : trim((string)($subscription['subscription_payment_link'] ?? ''));
$subscriptionNotes = trim((string)($subscription['subscription_notes'] ?? ''));
$schoolName = trim((string)($school['school_name'] ?? ''));
$subscriptionPlanCatalog = function_exists('getSubscriptionPlanCatalog')
    ? getSubscriptionPlanCatalog()
    : [
        'free' => [
            'label' => 'Free Plan',
            'subtitle' => 'Starter access with ads enabled.',
            'description' => 'Best for schools that want to stay on the free tier.',
            'badge' => 'Ads enabled',
            'variant' => 'secondary',
            'icon' => 'bi-gift',
            'features' => [
                'Promotional ads remain visible',
                'Basic school dashboard access',
                'No payment required',
            ],
            'requires_payment' => false,
        ],
        'premium' => [
            'label' => 'Premium Plan',
            'subtitle' => 'Ad-free school experience.',
            'description' => 'Upgrade for a cleaner portal and premium presentation.',
            'badge' => 'Most popular',
            'variant' => 'primary',
            'icon' => 'bi-stars',
            'features' => [
                'Promotional ads are hidden',
                'Premium portal branding',
                'Configured payment gateway',
            ],
            'requires_payment' => true,
        ],
        'enterprise' => [
            'label' => 'Enterprise Plan',
            'subtitle' => 'Custom setup for larger schools.',
            'description' => 'Ideal for schools needing custom billing or dedicated support.',
            'badge' => 'Custom',
            'variant' => 'dark',
            'icon' => 'bi-building-gear',
            'features' => [
                'Custom pricing and support',
                'Advanced school-level control',
                'Payment flow managed by Super Admin',
            ],
            'requires_payment' => true,
        ],
    ];

$selectedPlan = strtolower(trim((string)($_POST['subscription_plan'] ?? ($subscription['subscription_plan'] ?? 'free'))));
if (!array_key_exists($selectedPlan, $subscriptionPlanCatalog)) {
    $selectedPlan = strtolower(trim((string)($subscription['subscription_plan'] ?? 'free')));
    if (!array_key_exists($selectedPlan, $subscriptionPlanCatalog)) {
        $selectedPlan = 'free';
    }
}
$selectedPlanMeta = $subscriptionPlanCatalog[$selectedPlan];
$configuredPrice = floatval($subscription['subscription_price'] ?? 0);
$configuredCurrency = trim((string)($subscription['subscription_currency_code'] ?? 'INR')) ?: 'INR';
$selectedPlanPriceLabel = $selectedPlan === 'free'
    ? formatCurrency(0)
    : ($configuredPrice > 0 ? formatCurrency($configuredPrice) : 'Set by Super Admin');
$selectedPlanBadge = trim((string)($selectedPlanMeta['badge'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_plan'])) {
    $settingsRowId = intval($settings['setting_id'] ?? 0);

    if ($settingsRowId <= 0) {
        $error = 'Could not identify the school subscription row.';
    } else {
        $newStatus = $selectedPlan === 'free' ? 'active' : 'pending';
        $newAdsEnabled = $selectedPlan === 'free' ? 1 : 0;

        $updated = executeQuery(
            "UPDATE school_settings SET subscription_plan = ?, subscription_status = ?, ads_enabled = ?, updated_at = NOW() WHERE setting_id = ?",
            'ssii',
            [$selectedPlan, $newStatus, $newAdsEnabled, $settingsRowId]
        );

        if ($updated !== false) {
            logActivity(
                intval($currentUser['user_id']),
                'Subscription Plan Selected',
                'subscriptions',
                'School admin selected the ' . $selectedPlan . ' plan for: ' . $schoolName
            );

            if ($selectedPlan === 'free') {
                $success = 'Free plan activated successfully. Your school is now on the free plan.';
                $subscriptionPlan = 'Free';
                $subscriptionStatus = 'Active';
                $subscriptionPaymentLink = '';
            } elseif ($subscriptionPaymentLink !== '') {
                header('Location: ' . $subscriptionPaymentLink);
                exit();
            } else {
                $error = 'No payment link has been configured by the Super Admin yet. Please contact the Super Admin to continue.';
            }
        } else {
            $error = 'Unable to save the selected subscription plan. Please try again.';
        }
    }
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-credit-card-2-front"></i> Upgrade Subscription
                </h2>
                <p class="text-muted mb-0">Open the payment link configured by the Super Admin for your school.</p>
            </div>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="alert alert-info border">
    <strong>Super Admin controls pricing and payment links.</strong>
    Choose a plan below, then continue with the payment flow configured for this school.
</div>

<div class="row">
    <?php foreach ($subscriptionPlanCatalog as $planKey => $planMeta): ?>
        <?php
        $isCurrentPlan = strtolower(trim((string)($subscription['subscription_plan'] ?? 'free'))) === $planKey;
        $cardBorder = $selectedPlan === $planKey ? 'border-primary border-2' : 'border-light';
        $actionLabel = $planKey === 'free'
            ? ($isCurrentPlan ? 'Keep Free Plan' : 'Activate Free Plan')
            : 'Select Plan & Pay';
        $priceLabel = $planKey === 'free'
            ? formatCurrency(0)
            : $selectedPlanPriceLabel;
        ?>
        <div class="col-lg-4 mb-4">
            <div class="card dashboard-card h-100 <?php echo $cardBorder; ?>">
                <div class="card-header bg-<?php echo htmlspecialchars($planMeta['variant']); ?> text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?php echo htmlspecialchars($planMeta['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($planMeta['label']); ?></span>
                    </div>
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($planMeta['badge']); ?></span>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($planMeta['subtitle']); ?></p>
                    <h3 class="mb-1"><?php echo htmlspecialchars($priceLabel); ?></h3>
                    <div class="text-muted small mb-3">
                        <?php echo htmlspecialchars(ucfirst($configuredCurrency)); ?> | <?php echo htmlspecialchars(ucfirst(trim((string)($subscription['subscription_billing_cycle'] ?? 'monthly')))); ?>
                    </div>
                    <p class="small mb-3"><?php echo htmlspecialchars($planMeta['description']); ?></p>
                    <ul class="list-unstyled small mb-4">
                        <?php foreach ($planMeta['features'] as $feature): ?>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($isCurrentPlan): ?>
                        <div class="alert alert-light border py-2 small">
                            <strong>Current plan:</strong> <?php echo htmlspecialchars($planMeta['label']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="mt-auto">
                        <input type="hidden" name="select_plan" value="1">
                        <input type="hidden" name="subscription_plan" value="<?php echo htmlspecialchars($planKey); ?>">
                        <button type="submit" class="btn <?php echo $planKey === 'free' ? 'btn-outline-secondary' : 'btn-success'; ?> w-100">
                            <i class="bi <?php echo $planKey === 'free' ? 'bi-check2-circle' : 'bi-credit-card'; ?>"></i>
                            <?php echo htmlspecialchars($actionLabel); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-wallet2"></i> Payment Summary</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Note:</strong> After you select a paid plan, the school subscription is marked <strong>Pending</strong> until payment is completed and Super Admin approves it.
                </div>

                <p class="mb-2"><strong>School:</strong> <?php echo htmlspecialchars($schoolName); ?></p>
                <p class="mb-2"><strong>Selected Plan:</strong> <?php echo htmlspecialchars(ucfirst($selectedPlan)); ?></p>
                <p class="mb-2"><strong>Status:</strong> <?php echo htmlspecialchars($selectedPlan === 'free' ? 'Active' : 'Pending'); ?></p>
                <p class="mb-2"><strong>Configured Price:</strong> <?php echo htmlspecialchars($selectedPlanPriceLabel); ?></p>
                <p class="mb-3"><strong>Payment Link:</strong> <?php echo $subscriptionPaymentLink !== '' && $selectedPlan !== 'free' ? 'Available' : ($selectedPlan === 'free' ? 'Not required' : 'Not configured'); ?></p>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($selectedPlan !== 'free' && $subscriptionPaymentLink !== ''): ?>
                        <a href="<?php echo htmlspecialchars($subscriptionPaymentLink); ?>" target="_blank" rel="noopener" class="btn btn-success">
                            <i class="bi bi-box-arrow-up-right"></i> Open Payment Link
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="copySubscriptionLink()">
                            <i class="bi bi-clipboard"></i> Copy Link
                        </button>
                    <?php else: ?>
                        <span class="badge bg-success align-self-center">No payment required for Free plan</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($subscriptionNotes)): ?>
                    <div class="alert alert-light border mt-4 mb-0">
                        <strong>Instructions:</strong><br>
                        <?php echo nl2br(htmlspecialchars($subscriptionNotes)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> School Summary</h5>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>School Name:</strong> <?php echo htmlspecialchars($schoolName); ?></p>
                <p class="mb-2"><strong>School Code:</strong> <?php echo htmlspecialchars($school['school_code'] ?? '-'); ?></p>
                <p class="mb-2"><strong>Current Plan:</strong> <?php echo htmlspecialchars($subscriptionPlan); ?></p>
                <p class="mb-2"><strong>Subscription Status:</strong> <?php echo htmlspecialchars($subscriptionStatus); ?></p>
                <p class="mb-2"><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst(trim((string)($subscription['subscription_gateway'] ?? 'manual')))); ?></p>
                <p class="mb-2"><strong>Payment Reference:</strong> <?php echo htmlspecialchars(trim((string)($subscription['subscription_gateway_reference'] ?? '')) !== '' ? trim((string)($subscription['subscription_gateway_reference'] ?? '')) : 'N/A'); ?></p>
                <p class="mb-0"><strong>Payment Link:</strong> <?php echo !empty($subscriptionPaymentLink) ? 'Configured' : 'N/A'; ?></p>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScript = "
function copySubscriptionLink() {
    const link = " . json_encode($subscriptionPaymentLink) . ";
    if (!link) {
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link);
        return;
    }

    const tempInput = document.createElement('input');
    tempInput.value = link;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
}
";

include '../../includes/footer.php';
?>
