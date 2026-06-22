<?php
/**
 * Reports Dashboard
 * Access to all system reports
 */

// Include configuration
require_once '../../config/config.php';

// Require login
requireLogin();

// Check if user has reports permission, if not, allow if they have any other module permissions
if (!hasPermission('reports', 'view')) {
    // Allow access if user has dashboard or any administrative permission
    if (!hasPermission('dashboard', 'view') && !hasPermission('students', 'view') && !hasPermission('fees', 'view')) {
        $_SESSION['error_message'] = 'You do not have permission to access reports. Please contact administrator.';
        header("Location: " . APP_URL . "/modules/dashboard/");
        exit();
    }
}

$pageTitle = 'Reports';
$currentUser = getCurrentUser();

// Include header
include '../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-graph-up-arrow"></i> Reports Dashboard
            </h2>
            <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<!-- Dashboard Search -->
<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="card dashboard-card report-dashboard-search-card">
            <div class="card-body">
                <form id="reportDashboardSearchForm" class="row g-3 align-items-end">
                    <div class="col-lg-10 col-md-9">
                        <label for="reportDashboardSearchInput" class="form-label mb-1">Search Reports</label>
                        <div class="student-autocomplete-host">
                            <input
                                type="search"
                                class="form-control"
                                id="reportDashboardSearchInput"
                                name="q"
                                placeholder="Search by report name, like attendance, fee, marks, admit card, certificate"
                                value="<?php echo htmlspecialchars(trim((string)($_GET['q'] ?? ''))); ?>"
                                autocomplete="off"
                                aria-autocomplete="list"
                                aria-expanded="false"
                            >
                            <div
                                id="reportDashboardSuggestions"
                                class="student-autocomplete-menu"
                                role="listbox"
                                aria-label="Report suggestions"
                                style="display:none;"
                            ></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
                <div id="reportDashboardNoResults" class="alert alert-warning mt-3 mb-0 no-print" style="display:none;">
                    <i class="bi bi-search"></i> No reports matched your search.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Reports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Student Reports</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-person-lines-fill text-primary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">All Students List</h5>
                                <p class="text-muted">Complete list of all students with details</p>
                                <a href="student_list.php" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-building text-primary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Class-wise Students</h5>
                                <p class="text-muted">Students grouped by class and section</p>
                                <a href="class_wise_students.php" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-person-badge text-primary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Student Details Report</h5>
                                <p class="text-muted">Detailed student information with parents</p>
                                <a href="student_details.php" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-check text-primary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Attendance Report</h5>
                                <p class="text-muted">Student-wise attendance summary with exports</p>
                                <a href="attendance_report.php" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fee Reports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Fee Reports</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-receipt-cutoff text-success" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Fee Collection Report</h5>
                                <p class="text-muted">All fee receipts with date filter</p>
                                <a href="fee_collection.php" class="btn btn-success">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Due Fee Report</h5>
                                <p class="text-muted">Students with pending fee payments</p>
                                <a href="due_fees.php" class="btn btn-success">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-range text-success" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Date-wise Collection</h5>
                                <p class="text-muted">Daily/Monthly fee collection summary</p>
                                <a href="date_wise_collection.php" class="btn btn-success">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-people-fill text-success" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Class-wise Fee Report</h5>
                                <p class="text-muted">Fee collection grouped by class</p>
                                <a href="class_wise_fees.php" class="btn btn-success">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-list-check text-success" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Fee Head-wise Report</h5>
                                <p class="text-muted">Collection summary by fee heads</p>
                                <a href="fee_head_wise.php" class="btn btn-success">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-credit-card text-success" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Payment Mode Report</h5>
                                <p class="text-muted">Collection by payment method</p>
                                <a href="payment_mode_report.php" class="btn btn-success">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Fee Collection Reports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar2-week"></i> Quick Collection Views</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-secondary">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-day text-secondary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Today's Collection</h5>
                                <p class="text-muted">Fee receipts collected today</p>
                                <a href="today_collection.php" class="btn btn-secondary">
                                    <i class="bi bi-download"></i> Open Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-secondary">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-month text-secondary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Month-wise Collection</h5>
                                <p class="text-muted">Academic month summary</p>
                                <a href="month_wise_collection.php" class="btn btn-secondary">
                                    <i class="bi bi-download"></i> Open Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-secondary">
                            <div class="card-body text-center">
                                <i class="bi bi-person-check text-secondary" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Accountant-wise Collection</h5>
                                <p class="text-muted">Collection grouped by collector</p>
                                <a href="accountant_wise_collection.php" class="btn btn-secondary">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Academic Reports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-book"></i> Academic Reports</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-trophy text-info" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Marks Report</h5>
                                <p class="text-muted">Student marks by subject and exam</p>
                                <a href="marks_report.php" class="btn btn-info">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-file-earmark-text text-info" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Mark Sheet Generation</h5>
                                <p class="text-muted">Generate student mark sheets</p>
                                <a href="generate_marksheet.php" class="btn btn-info">
                                    <i class="bi bi-file-pdf"></i> Generate PDF
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-graph-up text-info" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Performance Analysis</h5>
                                <p class="text-muted">Class and student performance trends</p>
                                <a href="performance_analysis.php" class="btn btn-info">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Documents -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-binary"></i> Student Documents</h5>
            </div>
            <div class="card-body">
                <div class="document-tile-grid">
                    <div class="document-tile document-tile-dark">
                        <div class="document-tile-icon">
                            <i class="bi bi-card-heading"></i>
                        </div>
                        <h5>Admit Card</h5>
                        <p class="text-muted">Generate student-wise or class-wise admit cards</p>
                        <a href="admit_cards.php" class="btn btn-dark w-100">
                            <i class="bi bi-printer"></i> Open Generator
                        </a>
                    </div>

                    <div class="document-tile document-tile-dark">
                        <div class="document-tile-icon">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </div>
                        <h5>Transfer Certificate</h5>
                        <p class="text-muted">Issue transfer certificate with signature</p>
                        <a href="student_documents.php?type=transfer_certificate" class="btn btn-dark w-100">
                            <i class="bi bi-printer"></i> Open Generator
                        </a>
                    </div>

                    <div class="document-tile document-tile-dark">
                        <div class="document-tile-icon">
                            <i class="bi bi-award"></i>
                        </div>
                        <h5>Character Certificate</h5>
                        <p class="text-muted">Generate character certificate for students</p>
                        <a href="student_documents.php?type=character_certificate" class="btn btn-dark w-100">
                            <i class="bi bi-printer"></i> Open Generator
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Reports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-gear"></i> System Reports</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-clock-history text-warning" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Activity Log Report</h5>
                                <p class="text-muted">User activity and system logs</p>
                                <a href="activity_log.php" class="btn btn-warning">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-person-check text-warning" style="font-size: 48px;"></i>
                                <h5 class="mt-3">User Management Report</h5>
                                <p class="text-muted">All system users and roles</p>
                                <a href="user_report.php" class="btn btn-warning">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-bar-chart text-warning" style="font-size: 48px;"></i>
                                <h5 class="mt-3">Summary Dashboard</h5>
                                <p class="text-muted">Overall system statistics</p>
                                <a href="summary_report.php" class="btn btn-warning">
                                    <i class="bi bi-download"></i> Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reportDashboardSearchForm');
    const input = document.getElementById('reportDashboardSearchInput');
    const noResults = document.getElementById('reportDashboardNoResults');
    const suggestionsMenu = document.getElementById('reportDashboardSuggestions');
    const cards = Array.from(document.querySelectorAll('.dashboard-card .card.h-100'));
    const documentTiles = Array.from(document.querySelectorAll('.document-tile-grid .document-tile'));
    const minLength = 2;
    let debounceTimer = null;

    function normalizeText(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function getContainer(element) {
        return element.closest('.col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-lg-2, .col-lg-3, .col-lg-4') || element.parentElement;
    }

    function hideSuggestions() {
        if (!suggestionsMenu) {
            return;
        }

        suggestionsMenu.innerHTML = '';
        suggestionsMenu.style.display = 'none';
        input.setAttribute('aria-expanded', 'false');
    }

    function collectSearchableItems() {
        const items = [];
        const seen = new Set();

        cards.forEach((card) => {
            const titleElement = card.querySelector('.card-body h5');
            const linkElement = card.querySelector('a[href]');
            if (!titleElement || !linkElement) {
                return;
            }

            const title = titleElement.textContent.trim();
            if (!title) {
                return;
            }

            const sectionTitleElement = card.closest('.dashboard-card')?.querySelector('.card-header h5');
            const sectionTitle = sectionTitleElement ? sectionTitleElement.textContent.trim() : '';
            const descriptionElement = card.querySelector('p.text-muted');
            const description = descriptionElement ? descriptionElement.textContent.trim() : '';
            const key = 'card|' + title.toLowerCase() + '|' + sectionTitle.toLowerCase();

            if (seen.has(key)) {
                return;
            }
            seen.add(key);

            items.push({
                title: title,
                meta: description || sectionTitle || 'Report',
                text: normalizeText([title, description, sectionTitle, card.innerText].join(' ')),
                container: getContainer(card)
            });
        });

        documentTiles.forEach((tile) => {
            const titleElement = tile.querySelector('h5');
            const linkElement = tile.querySelector('a[href]');
            if (!titleElement || !linkElement) {
                return;
            }

            const title = titleElement.textContent.trim();
            if (!title) {
                return;
            }

            const sectionTitleElement = tile.closest('.dashboard-card')?.querySelector('.card-header h5');
            const sectionTitle = sectionTitleElement ? sectionTitleElement.textContent.trim() : '';
            const descriptionElement = tile.querySelector('p.text-muted');
            const description = descriptionElement ? descriptionElement.textContent.trim() : '';
            const key = 'tile|' + title.toLowerCase() + '|' + sectionTitle.toLowerCase();

            if (seen.has(key)) {
                return;
            }
            seen.add(key);

            items.push({
                title: title,
                meta: description || sectionTitle || 'Report',
                text: normalizeText([title, description, sectionTitle, tile.innerText].join(' ')),
                container: tile
            });
        });

        return items;
    }

    const searchableItems = collectSearchableItems();

    function renderSuggestions(query) {
        if (!suggestionsMenu) {
            return;
        }

        const term = normalizeText(query);
        if (term.length < minLength) {
            hideSuggestions();
            return;
        }

        const matches = searchableItems
            .filter((item) => item.text.includes(term))
            .sort((a, b) => {
                const aStarts = a.title.toLowerCase().startsWith(term) ? 0 : 1;
                const bStarts = b.title.toLowerCase().startsWith(term) ? 0 : 1;
                if (aStarts !== bStarts) {
                    return aStarts - bStarts;
                }
                return a.title.localeCompare(b.title);
            })
            .slice(0, 8);

        suggestionsMenu.innerHTML = '';

        if (!matches.length) {
            hideSuggestions();
            return;
        }

        matches.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'student-autocomplete-item';
            button.setAttribute('role', 'option');

            const titleSpan = document.createElement('span');
            titleSpan.className = 'student-autocomplete-name';
            titleSpan.textContent = item.title;

            const metaSpan = document.createElement('span');
            metaSpan.className = 'student-autocomplete-meta';
            metaSpan.textContent = item.meta;

            button.appendChild(titleSpan);
            button.appendChild(metaSpan);

            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                input.value = item.title;
                hideSuggestions();
                applyFilter();
                if (item.container && typeof item.container.scrollIntoView === 'function') {
                    item.container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            suggestionsMenu.appendChild(button);
        });

        suggestionsMenu.style.display = 'block';
        input.setAttribute('aria-expanded', 'true');
    }

    function applyFilter() {
        const query = (input.value || '').trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach((card) => {
            const container = getContainer(card);
            const text = (card.innerText || '').toLowerCase();
            const match = query === '' || text.includes(query);
            if (container) {
                container.style.display = match ? '' : 'none';
            }
            if (match) {
                visibleCount++;
            }
        });

        documentTiles.forEach((tile) => {
            const text = (tile.innerText || '').toLowerCase();
            const match = query === '' || text.includes(query);
            tile.style.display = match ? '' : 'none';
            if (match) {
                visibleCount++;
            }
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    if (form && input) {
        const params = new URLSearchParams(window.location.search);
        const initialQuery = params.get('q');
        if (initialQuery && !input.value) {
            input.value = initialQuery;
        }

        input.addEventListener('input', function () {
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }

            debounceTimer = window.setTimeout(function () {
                applyFilter();
                renderSuggestions(input.value);
            }, 120);
        });

        input.addEventListener('focus', function () {
            renderSuggestions(input.value);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            applyFilter();
            renderSuggestions(input.value);
        });

        if ((input.value || '').trim() !== '') {
            applyFilter();
            renderSuggestions(input.value);
        }
    }

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target) && !suggestionsMenu.contains(event.target)) {
            hideSuggestions();
        }
    });
});
</script>

<style>
.report-dashboard-search-card {
    overflow: visible;
    position: relative;
    z-index: 50;
}

.report-dashboard-search-card .card-body {
    overflow: visible;
}

#reportDashboardSuggestions {
    z-index: 9999;
}
</style>

<?php
// Include footer
include '../../includes/footer.php';
?>
