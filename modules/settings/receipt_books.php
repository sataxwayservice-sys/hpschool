<?php
/**
 * Receipt Books Management
 * Create and manage receipt books for fee collection
 */

require_once '../../config/config.php';
requireLogin();
requirePermission('settings', 'manage');

$pageTitle = 'Receipt Books';
$currentUser = getCurrentUser();

// Create table if not exists
$conn = getDbConnection();
$createTableQuery = "CREATE TABLE IF NOT EXISTS receipt_books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    book_name VARCHAR(100) NOT NULL,
    prefix VARCHAR(10),
    start_number INT NOT NULL DEFAULT 1,
    current_number INT NOT NULL DEFAULT 1,
    end_number INT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_book_name (book_name)
)";
$conn->query($createTableQuery);

// Ensure all columns exist (migration for existing tables)
$checkColumns = $conn->query("SHOW COLUMNS FROM receipt_books");
$existingColumns = [];
while ($col = $checkColumns->fetch_assoc()) {
    $existingColumns[] = $col['Field'];
}

// Add missing columns
if (!in_array('start_number', $existingColumns)) {
    $conn->query("ALTER TABLE receipt_books ADD COLUMN start_number INT NOT NULL DEFAULT 1 AFTER prefix");
}
if (!in_array('current_number', $existingColumns)) {
    $conn->query("ALTER TABLE receipt_books ADD COLUMN current_number INT NOT NULL DEFAULT 1 AFTER start_number");
}
if (!in_array('end_number', $existingColumns)) {
    $conn->query("ALTER TABLE receipt_books ADD COLUMN end_number INT AFTER current_number");
}
if (!in_array('is_active', $existingColumns)) {
    $conn->query("ALTER TABLE receipt_books ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER end_number");
}

// Ensure the optional receipt-book fields exist on fee_receipts before this page
// counts or protects books that have already been used.
$feeReceiptColumnsResult = $conn->query("SHOW COLUMNS FROM fee_receipts");
$feeReceiptColumns = [];
if ($feeReceiptColumnsResult) {
    while ($col = $feeReceiptColumnsResult->fetch_assoc()) {
        $feeReceiptColumns[] = $col['Field'];
    }
}

if (!in_array('receipt_book_id', $feeReceiptColumns)) {
    $conn->query("ALTER TABLE fee_receipts ADD COLUMN receipt_book_id INT(11) DEFAULT NULL AFTER receipt_id");
}
if (!in_array('charge_amount', $feeReceiptColumns)) {
    $conn->query("ALTER TABLE fee_receipts ADD COLUMN charge_amount DECIMAL(10,2) DEFAULT 0.00 AFTER amount_paid");
}
if (!in_array('bank_name', $feeReceiptColumns)) {
    $conn->query("ALTER TABLE fee_receipts ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL AFTER transaction_id");
}
if (!in_array('cheque_date', $feeReceiptColumns)) {
    $conn->query("ALTER TABLE fee_receipts ADD COLUMN cheque_date DATE DEFAULT NULL AFTER bank_name");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_book'])) {
        $bookName = sanitize($_POST['book_name']);
        $prefix = sanitize($_POST['prefix'] ?? '');
        $startNumber = intval($_POST['start_number']);
        $endNumber = !empty($_POST['end_number']) ? intval($_POST['end_number']) : null;

        $query = "INSERT INTO receipt_books (book_name, prefix, start_number, current_number, end_number)
                  VALUES (?, ?, ?, ?, ?)";
        try {
            executeQuery($query, 'ssiis', [$bookName, $prefix, $startNumber, $startNumber, $endNumber]);
            $_SESSION['success_message'] = "Receipt book '$bookName' added successfully!";
            logActivity($currentUser['user_id'], 'Add', 'Receipt Books', "Added receipt book: $bookName");
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Failed to add receipt book: ' . $e->getMessage();
        }
        header("Location: receipt_books.php");
        exit();
    }

    if (isset($_POST['toggle_status'])) {
        $bookId = intval($_POST['book_id']);
        $newStatus = intval($_POST['new_status']);

        $query = "UPDATE receipt_books SET is_active = ? WHERE book_id = ?";
        executeQuery($query, 'ii', [$newStatus, $bookId]);
        $_SESSION['success_message'] = 'Receipt book status updated!';
        logActivity($currentUser['user_id'], 'Update', 'Receipt Books', "Toggled receipt book status: ID $bookId");

        header("Location: receipt_books.php");
        exit();
    }

    if (isset($_POST['delete_book'])) {
        $bookId = intval($_POST['book_id']);

        // Check if any receipts use this book
        $checkReceipts = fetchOne("SELECT COUNT(*) as count FROM fee_receipts WHERE receipt_book_id = ?", 'i', [$bookId]);

        if ($checkReceipts['count'] > 0) {
            $_SESSION['error_message'] = 'Cannot delete receipt book as it has associated receipts!';
        } else {
            $query = "DELETE FROM receipt_books WHERE book_id = ?";
            executeQuery($query, 'i', [$bookId]);
            $_SESSION['success_message'] = 'Receipt book deleted successfully!';
            logActivity($currentUser['user_id'], 'Delete', 'Receipt Books', "Deleted receipt book: ID $bookId");
        }

        header("Location: receipt_books.php");
        exit();
    }
}

// Get all receipt books
$receiptBooks = fetchAll("SELECT * FROM receipt_books ORDER BY is_active DESC, book_name");

// Get session messages
$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-book"></i> Receipt Books</h2>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="bi bi-plus-circle"></i> Add New Book
                </button>
                <a href="<?php echo getSmartBackUrl(APP_URL . '/modules/dashboard/'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Receipt Books List</h5>
            </div>
            <div class="card-body">
                <?php if (count($receiptBooks) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Book Name</th>
                                    <th>Prefix</th>
                                    <th>Start Number</th>
                                    <th>Current Number</th>
                                    <th>End Number</th>
                                    <th>Receipts Used</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receiptBooks as $book): ?>
                                    <?php
                                    $usedCount = fetchOne("SELECT COUNT(*) as count FROM fee_receipts WHERE receipt_book_id = ?", 'i', [$book['book_id']]);
                                    $receiptsUsed = $usedCount['count'] ?? 0;
                                    $endNumber = $book['end_number'] ?? null;
                                    $currentNumber = $book['current_number'] ?? 1;
                                    $remaining = $endNumber ? ($endNumber - $currentNumber + 1) : 'Unlimited';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($book['book_name'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars(($book['prefix'] ?? '') ?: '-'); ?></td>
                                        <td><?php echo $book['start_number'] ?? 1; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $currentNumber; ?></span>
                                        </td>
                                        <td><?php echo $endNumber ?: 'Unlimited'; ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $receiptsUsed; ?> used</span>
                                            <?php if (is_numeric($remaining)): ?>
                                                <br><small class="text-muted"><?php echo $remaining; ?> remaining</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $isActive = $book['is_active'] ?? 1;
                                            ?>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id'] ?? 0; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $isActive ? 0 : 1; ?>">
                                                    <button type="submit" name="toggle_status"
                                                            class="btn btn-sm btn-<?php echo $isActive ? 'warning' : 'success'; ?>"
                                                            title="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="bi bi-<?php echo $isActive ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this receipt book? This cannot be undone!');">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id'] ?? 0; ?>">
                                                    <button type="submit" name="delete_book"
                                                            class="btn btn-sm btn-danger"
                                                            title="Delete"
                                                            <?php echo ($receiptsUsed > 0) ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>No receipt books found!</strong> Click "Add New Book" to create your first receipt book.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Receipt Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Book Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="book_name" required
                               placeholder="e.g., Book A, 2024-2025 Main Book">
                        <small class="text-muted">A unique name to identify this receipt book</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prefix (Optional)</label>
                        <input type="text" class="form-control" name="prefix" maxlength="10"
                               placeholder="e.g., REC, FEE">
                        <small class="text-muted">Prefix to add before receipt numbers (e.g., REC0001)</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Number <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="start_number" value="1" min="1" required>
                            <small class="text-muted">First receipt number</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Number (Optional)</label>
                            <input type="number" class="form-control" name="end_number" min="1"
                                   placeholder="Leave blank for unlimited">
                            <small class="text-muted">Last receipt number</small>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0">
                        <strong><i class="bi bi-lightbulb"></i> Tip:</strong>
                        Receipt books help you organize receipts by year, type, or physical book.
                        You can have multiple active books and switch between them when collecting fees.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_book" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-question-circle"></i> About Receipt Books</h6>
            </div>
            <div class="card-body">
                <h6>What are Receipt Books?</h6>
                <p>Receipt books help you organize and track fee receipts systematically. They're especially useful if you:</p>
                <ul>
                    <li>Have physical receipt books with pre-printed numbers</li>
                    <li>Want to organize receipts by academic year</li>
                    <li>Need separate numbering sequences for different purposes</li>
                    <li>Want to track how many receipts are used from each book</li>
                </ul>

                <h6 class="mt-3">How to Use:</h6>
                <ol>
                    <li><strong>Create a Book:</strong> Click "Add New Book" and enter details</li>
                    <li><strong>Set Numbering:</strong> Define start and optionally end numbers</li>
                    <li><strong>Use During Collection:</strong> Select the book when collecting fees</li>
                    <li><strong>Track Usage:</strong> Monitor how many receipts are used and remaining</li>
                    <li><strong>Manage Status:</strong> Activate/deactivate books as needed</li>
                </ol>

                <div class="alert alert-warning mt-3 mb-0">
                    <strong><i class="bi bi-exclamation-triangle"></i> Note:</strong>
                    Receipt books with existing receipts cannot be deleted. You can deactivate them instead.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
