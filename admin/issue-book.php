<?php
/**
 * Issue Book to Member
 * Auto-calculates expected return date (issue_date + 14 days)
 * Decrements book available count
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

// Fetch available books and members
$books = $pdo->query("SELECT id, title, isbn, available FROM books WHERE available > 0 ORDER BY title")->fetchAll();
$members = $pdo->query("SELECT id, member_id, name FROM members ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId   = (int)($_POST['book_id'] ?? 0);
    $memberId = (int)($_POST['member_id'] ?? 0);
    $issueDate = sanitize($_POST['issue_date'] ?? date('Y-m-d'));

    if ($bookId <= 0) $errors[] = 'Please select a book.';
    if ($memberId <= 0) $errors[] = 'Please select a member.';
    if (empty($issueDate)) $errors[] = 'Please select an issue date.';

    if (empty($errors)) {
        // Check book availability
        $stmtBook = $pdo->prepare("SELECT title, available FROM books WHERE id = :id");
        $stmtBook->execute([':id' => $bookId]);
        $book = $stmtBook->fetch();

        if (!$book || $book['available'] <= 0) {
            $errors[] = 'This book is not available for issue.';
        } else {
            // Check if member already has this book issued
            $stmtCheck = $pdo->prepare("
                SELECT id FROM issued_books
                WHERE book_id = :book_id AND member_id = :member_id AND status = 'issued'
            ");
            $stmtCheck->execute([':book_id' => $bookId, ':member_id' => $memberId]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'This member already has this book and has not returned it yet.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Calculate expected return date (14 days from issue)
            $issueDateObj = new DateTime($issueDate);
            $expectedReturn = clone $issueDateObj;
            $expectedReturn->modify('+14 days');

            // Create issue record
            $stmt = $pdo->prepare("
                INSERT INTO issued_books (book_id, member_id, issue_date, expected_return_date, status)
                VALUES (:book_id, :member_id, :issue_date, :expected_return, 'issued')
            ");
            $stmt->execute([
                ':book_id'          => $bookId,
                ':member_id'        => $memberId,
                ':issue_date'       => $issueDate,
                ':expected_return'  => $expectedReturn->format('Y-m-d'),
            ]);

            // Decrement available copies
            $stmtUpdate = $pdo->prepare("UPDATE books SET available = available - 1 WHERE id = :id");
            $stmtUpdate->execute([':id' => $bookId]);

            $pdo->commit();
            $_SESSION['message'] = 'Book issued successfully! Expected return: ' . $expectedReturn->format('d M, Y');
            $_SESSION['message_type'] = 'success';
            redirect('issued-books.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error issuing book: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Issue Book';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Issue a Book</h2>
        <a href="issued-books.php" class="btn btn-outline-secondary"><i class="bi bi-list-check"></i> View Issued Books</a>
    </div>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <?php echo showAlert($err, 'error'); ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="card dashboard-card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="book_id" class="form-label">Select Book <span class="text-danger">*</span></label>
                        <select class="form-select" id="book_id" name="book_id" required>
                            <option value="">-- Choose a Book --</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo $book['id']; ?>">
                                    <?php echo htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    (ISBN: <?php echo htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8'); ?>)
                                    - Available: <?php echo $book['available']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($books)): ?>
                            <small class="text-danger">No books available for issue.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="member_id" class="form-label">Select Member <span class="text-danger">*</span></label>
                        <select class="form-select" id="member_id" name="member_id" required>
                            <option value="">-- Choose a Member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['member_id'] . ' - ' . $member['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="issue_date" class="form-label">Issue Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">&nbsp;</label>
                        <div class="alert alert-info py-2 mb-0">
                            <small><i class="bi bi-info-circle"></i>
                                Books are issued for <strong>14 days</strong>. Late returns incur a fine of
                                <strong>10 BDT/day</strong>.
                            </small>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-right"></i> Issue Book</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ============================================================
   FIX: Un-blur "View Issued Books" button + keep flex alignment
   ============================================================ */

/* Strip blur only from the header row that holds the button */
.main-content > .d-flex,
.main-content > .d-flex * {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    filter: none !important;
}

/* Override the washed-out outline button to dark premium glass */
.main-content > .d-flex .btn-outline-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1.5px solid rgba(255, 255, 255, 0.25) !important;
    background: rgba(15, 23, 42, 0.7) !important;
    color: #ffffff !important;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.25s ease;
    white-space: nowrap;
}
.main-content > .d-flex .btn-outline-secondary i {
    color: #ffffff !important;
}
.main-content > .d-flex .btn-outline-secondary:hover {
    background: rgba(58, 134, 255, 0.2) !important;
    border-color: #3a86ff !important;
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(58, 134, 255, 0.15);
}
</style>
<?php require_once __DIR__ . '/footer.php'; ?>
