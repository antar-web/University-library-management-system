<?php
/**
 * Return Book
 * Calculates fine using strict 14-day rule:
 * Fine = (Total Days Kept - 14) * 10 BDT
 * Updates issued_books status + increments book available_copies
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- PROCESS RETURN ---
if (isset($_GET['return'])) {
    $issueId = (int)$_GET['return'];

    $stmt = $pdo->prepare("
        SELECT ib.*, b.title as book_title, b.id as book_id, m.name as member_name
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.id
        JOIN members m ON ib.member_id = m.id
        WHERE ib.id = :id AND (ib.status = 'issued' OR ib.status = 'Return Pending')
    ");
    $stmt->execute([':id' => $issueId]);
    $issue = $stmt->fetch();

    if (!$issue) {
        $_SESSION['message'] = 'Invalid issue record or book already returned.';
        $_SESSION['message_type'] = 'error';
        redirect('return-book.php');
    }

    // Calculate fine using explicit formula:
    // Fine = (Total Days Kept - 14) * 10, minimum 0
    $issueDate   = new DateTime($issue['issue_date']);
    $returnDate  = new DateTime(); // today
    $daysKept    = (int) $issueDate->diff($returnDate)->days;
    $overdueDays = max(0, $daysKept - 14);
    $fine        = $overdueDays * 10;

    try {
        $pdo->beginTransaction();

        // Update issued_book: set return date, fine, mark as returned
        $stmtUpdate = $pdo->prepare("
            UPDATE issued_books
            SET actual_return_date = CURDATE(),
                fine_amount = :fine,
                status = 'returned'
            WHERE id = :id
        ");
        $stmtUpdate->execute([':fine' => $fine, ':id' => $issueId]);

        // Increase available copies in books table
        $stmtBook = $pdo->prepare("UPDATE books SET available = available + 1 WHERE id = :id");
        $stmtBook->execute([':id' => $issue['book_id']]);

        $pdo->commit();

        $msg = 'Book "' . htmlspecialchars($issue['book_title'], ENT_QUOTES, 'UTF-8') . '" returned successfully.';
        $msg .= ' Kept ' . $daysKept . ' day(s)';
        if ($fine > 0) {
            $msg .= ', overdue by ' . $overdueDays . ' day(s). Fine: ' . number_format($fine, 2) . ' BDT.';
        } else {
            $msg .= ', no fine.';
        }
        $_SESSION['message'] = $msg;
        $_SESSION['message_type'] = $fine > 0 ? 'warning' : 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Error processing return: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    redirect('return-book.php');
}

// --- LIST ISSUED BOOKS ---
$search = sanitize($_GET['search'] ?? '');
$searchCondition = '';
$params = [];

if ($search) {
    $searchCondition = "AND (b.title LIKE :search OR m.name LIKE :search2 OR m.member_id LIKE :search3)";
    $params = [
        ':search'  => "%$search%",
        ':search2' => "%$search%",
        ':search3' => "%$search%",
    ];
}

$sql = "
    SELECT ib.*, b.title as book_title, b.isbn, m.member_id, m.name as member_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN members m ON ib.member_id = m.id
    WHERE (ib.status = 'issued' OR ib.status = 'Return Pending') $searchCondition
    ORDER BY ib.issue_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issuedBooks = $stmt->fetchAll();

$pageTitle = 'Return Book';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Return a Book</h2>
        <a href="issue-book.php" class="btn btn-primary"><i class="bi bi-box-arrow-right"></i> Issue a Book</a>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $messageType); ?>
    <?php endif; ?>

    <!-- Search -->
    <div class="card dashboard-card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-10">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search by Book Title, Member Name, or Member ID..."
                           value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Issued Books Table -->
    <div class="card dashboard-card">
        <div class="card-header">
            <h5 class="card-title mb-0">Currently Issued Books</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Book Title</th>
                            <th>Member</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Kept</th>
                            <th>Fine (BDT)</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($issuedBooks)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No books are currently issued.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; ?>
                            <?php foreach ($issuedBooks as $row):
                                $issueDate  = new DateTime($row['issue_date']);
                                $today      = new DateTime();
                                $daysKept   = (int) $issueDate->diff($today)->days;
                                $overdue    = max(0, $daysKept - 14);
                                $displayFine = $overdue * 10;
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($row['book_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <code><?php echo htmlspecialchars($row['member_id'], ENT_QUOTES, 'UTF-8'); ?></code>
                                        <?php echo htmlspecialchars($row['member_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo formatDate($row['issue_date']); ?></td>
                                    <td><?php echo formatDate($row['expected_return_date']); ?></td>
                                    <td>
                                        <?php echo $daysKept; ?> day(s)
                                        <?php if ($overdue > 0): ?>
                                            <span class="text-danger fw-bold">(<?php echo $overdue; ?> overdue)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($displayFine > 0): ?>
                                            <span class="text-danger fw-bold"><?php echo number_format($displayFine, 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (trim($row['status']) === 'Return Pending'): ?>
                                            <span class="badge bg-warning text-dark">Return Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">Issued</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (trim($row['status']) === 'Return Pending'): ?>
                                        <a href="?return=<?php echo $row['id']; ?>"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Return this book?<?php echo $displayFine > 0 ? ' Fine: ' . number_format($displayFine, 2) . ' BDT' : ''; ?>');">
                                            <i class="bi bi-arrow-return-left"></i> Return
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">
                                            <i class="bi bi-hourglass-split"></i> Awaiting Request
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
