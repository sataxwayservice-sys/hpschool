<?php
/**
 * School Advertisement Management
 * Super Admin only: create school-specific ads and control placements
 */

require_once '../../config/config.php';

requireLogin();
$currentUser = getCurrentUser();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'super_admin') {
    $_SESSION['error_message'] = 'Access denied. Only Super Admin can manage ads.';
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit();
}

ensureSchoolAdsSchema();
ensureSchoolRegistrationSchema();

$pageTitle = 'School Ads';
$error = '';
$success = '';

$schools = fetchAll(
    "SELECT school_id, school_name, school_code, status, created_at
     FROM schools
     ORDER BY created_at DESC, school_id DESC"
);

$placements = getSchoolAdPlacements();
$selectedSchoolId = intval($_GET['school_id'] ?? ($_POST['school_id'] ?? 0));
if ($selectedSchoolId <= 0 && !empty($schools)) {
    $selectedSchoolId = intval($schools[0]['school_id']);
}

$selectedSchool = $selectedSchoolId > 0 ? schoolRegistrationGetSchoolById($selectedSchoolId) : null;
$selectedSettings = $selectedSchoolId > 0 ? getSchoolSettingsBySchoolId($selectedSchoolId) : null;
$currentAds = $selectedSchoolId > 0 ? getSchoolAdsForPlacement($selectedSchoolId, '', 50, true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'add'));
    $postedSchoolId = intval($_POST['school_id'] ?? 0);
    $school = $postedSchoolId > 0 ? schoolRegistrationGetSchoolById($postedSchoolId) : null;

    if (!$school) {
        $error = 'School not found.';
    } elseif ($action === 'delete') {
        $adId = intval($_POST['ad_id'] ?? 0);
        $ad = $adId > 0 ? fetchOne("SELECT * FROM school_ads WHERE ad_id = ? AND school_id = ? LIMIT 1", 'ii', [$adId, $postedSchoolId]) : null;
        if (!$ad) {
            $error = 'Ad not found.';
        } else {
            if (!empty($ad['image_file'])) {
                deleteFile(AD_PATH . $ad['image_file']);
            }

            $deleted = executeQuery("DELETE FROM school_ads WHERE ad_id = ? AND school_id = ?", 'ii', [$adId, $postedSchoolId]);
            if ($deleted !== false) {
                $success = 'Ad deleted successfully.';
                logActivity($currentUser['user_id'], 'Delete School Ad', 'Ads', 'Deleted ad for school: ' . ($school['school_name'] ?? '-'));
            } else {
                $error = 'Failed to delete the ad.';
            }
        }
    } elseif ($action === 'toggle') {
        $adId = intval($_POST['ad_id'] ?? 0);
        $ad = $adId > 0 ? fetchOne("SELECT * FROM school_ads WHERE ad_id = ? AND school_id = ? LIMIT 1", 'ii', [$adId, $postedSchoolId]) : null;
        if (!$ad) {
            $error = 'Ad not found.';
        } else {
            $newStatus = intval($ad['is_active'] ?? 0) === 1 ? 0 : 1;
            $updated = executeQuery(
                "UPDATE school_ads SET is_active = ?, updated_at = NOW() WHERE ad_id = ? AND school_id = ?",
                'iii',
                [$newStatus, $adId, $postedSchoolId]
            );
            if ($updated !== false) {
                $success = $newStatus === 1 ? 'Ad activated.' : 'Ad deactivated.';
                logActivity($currentUser['user_id'], 'Toggle School Ad', 'Ads', 'Toggled ad status for school: ' . ($school['school_name'] ?? '-'));
            } else {
                $error = 'Failed to update the ad status.';
            }
        }
    } elseif ($action === 'add') {
        $placement = trim((string)($_POST['placement'] ?? 'header_banner'));
        if (!array_key_exists($placement, $placements)) {
            $placement = 'header_banner';
        }

        $adType = trim((string)($_POST['ad_type'] ?? 'image'));
        if (!in_array($adType, ['image', 'text', 'html'], true)) {
            $adType = 'image';
        }

        $title = trim(strip_tags((string)($_POST['title'] ?? '')));
        $contentText = trim((string)($_POST['content_text'] ?? ''));
        $contentHtml = trim((string)($_POST['content_html'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $priority = intval($_POST['priority'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));

        if ($title === '') {
            $error = 'Title is required.';
        } elseif ($adType === 'image' && empty($_FILES['image_file']['tmp_name'])) {
            $error = 'Please upload an image for image ads.';
        } else {
            $imageFile = null;
            if ($adType === 'image' && !empty($_FILES['image_file']['tmp_name'])) {
                if (!ensureDirectoryExists(AD_PATH)) {
                    $error = 'Ads upload folder is not writable.';
                } else {
                    $imageFile = uploadImage($_FILES['image_file'], AD_PATH, 1600, 900);
                    if ($imageFile === false) {
                        $error = 'Failed to upload the ad image.';
                    }
                }
            }

            if (empty($error)) {
                $insert = executeQuery(
                    "INSERT INTO school_ads (
                        school_id, placement, ad_type, title, content_text, content_html,
                        image_file, link_url, priority, is_active, start_date, end_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    'isssssssiiss',
                    [
                        $postedSchoolId,
                        $placement,
                        $adType,
                        $title,
                        $contentText,
                        $contentHtml,
                        $imageFile,
                        $linkUrl,
                        $priority,
                        $isActive,
                        $startDate !== '' ? $startDate : null,
                        $endDate !== '' ? $endDate : null
                    ]
                );

                if ($insert !== false) {
                    $success = 'Ad saved successfully.';
                    logActivity($currentUser['user_id'], 'Add School Ad', 'Ads', 'Added ad for school: ' . ($school['school_name'] ?? '-'));
                    $selectedSchoolId = $postedSchoolId;
                } else {
                    if (!empty($imageFile)) {
                        deleteFile(AD_PATH . $imageFile);
                    }
                    $error = 'Failed to save the ad.';
                }
            } elseif (!empty($imageFile)) {
                deleteFile(AD_PATH . $imageFile);
            }
        }
    }
}

$currentAds = $selectedSchoolId > 0 ? getSchoolAdsForPlacement($selectedSchoolId, '', 50, true) : [];

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="bi bi-megaphone"></i> School Ads</h2>
                <p class="text-muted mb-0">Create school-specific advertisements and choose where they appear.</p>
            </div>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
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
        <div class="col-lg-5 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Create New Ad</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label class="form-label">Placement</label>
                            <select name="placement" class="form-select">
                                <?php foreach ($placements as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ad Type</label>
                            <select name="ad_type" class="form-select" id="adTypeSelect">
                                <option value="image">Image</option>
                                <option value="text">Text</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="Ad title">
                        </div>

                        <div class="mb-3" id="imageUploadBox">
                            <label class="form-label">Image</label>
                            <input type="file" name="image_file" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3" id="textContentBox">
                            <label class="form-label">Text Content</label>
                            <textarea name="content_text" class="form-control" rows="4" placeholder="Short promotional text"></textarea>
                        </div>

                        <div class="mb-3" id="htmlContentBox" style="display:none;">
                            <label class="form-label">HTML Content</label>
                            <textarea name="content_html" class="form-control" rows="5" placeholder="<div>Your custom HTML ad</div>"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Link URL</label>
                            <input type="url" name="link_url" class="form-control" placeholder="https://example.com">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3 mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" checked>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Save Ad
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list"></i> School Ads</h5>
                    <span class="badge bg-light text-dark"><?php echo count($currentAds); ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Placement</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($currentAds)): ?>
                                    <?php foreach ($currentAds as $ad): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ad['title'] ?? '-'); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars($ad['link_url'] ?? ''); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($placements[$ad['placement']] ?? $ad['placement']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($ad['ad_type'] ?? 'image')); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo intval($ad['is_active'] ?? 0) === 1 ? 'success' : 'secondary'; ?>">
                                                    <?php echo intval($ad['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo intval($ad['priority'] ?? 0); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">
                                                    <input type="hidden" name="ad_id" value="<?php echo intval($ad['ad_id']); ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="school_id" value="<?php echo intval($selectedSchoolId); ?>">
                                                    <input type="hidden" name="ad_id" value="<?php echo intval($ad['ad_id']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this ad?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No ads created for this school yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">No school records were found.</div>
<?php endif; ?>

<?php
$inlineScript = "
(function() {
    const adTypeSelect = document.getElementById('adTypeSelect');
    const imageUploadBox = document.getElementById('imageUploadBox');
    const textContentBox = document.getElementById('textContentBox');
    const htmlContentBox = document.getElementById('htmlContentBox');

    function syncAdType() {
        const type = adTypeSelect ? adTypeSelect.value : 'image';
        if (imageUploadBox) imageUploadBox.style.display = type === 'image' ? '' : 'none';
        if (textContentBox) textContentBox.style.display = type === 'text' ? '' : 'none';
        if (htmlContentBox) htmlContentBox.style.display = type === 'html' ? '' : 'none';
    }

    if (adTypeSelect) {
        adTypeSelect.addEventListener('change', syncAdType);
        syncAdType();
    }
})();
";

include '../../includes/footer.php';
?>
