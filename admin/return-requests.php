<?php
/**
 * Return Requests - Admin Panel
 * Shows all Return Pending requests with an "Accept Return" button.
 * On accept: status -> returned, actual_return_date set, fine calculated, book +1 available.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo     = getConnection();
$message = '';
$msgType = '';

// --- Accept a return request ---
if (isset($_GET['accept_return'])) {
    $issueId = (int) $_GET['accept_return'];

    // Fetch the return-pending record
    $stmt = $pdo->prepare("
        SELECT ib.*, b.title AS book_title, b.id AS book_id, m.name AS member_name
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.id
        JOIN members m ON ib.member_id = m.id
        WHERE ib.id = :id AND ib.status COLLATE utf8mb4_unicode_ci = 'Return Pending'
    ");
    $stmt->execute([':id' => $issueId]);
    $issue = $stmt->fetch();

    if (!$issue) {
        $message = 'Return request not found or already processed.';
        $msgType = 'error';
    } else {
        // Calculate fine: (days kept - 14) * 10 BDT/day
        $issueDate   = new DateTime($issue['issue_date']);
        $returnDate  = new DateTime();
        $daysKept    = (int) $issueDate->diff($returnDate)->days;
        $overdueDays = max(0, $daysKept - 14);
        $fine        = $overdueDays * 10;

        try {
            $pdo->beginTransaction();

            // Update: status -> returned, set return date, store fine
            $stmtUpd = $pdo->prepare("
                UPDATE issued_books
                SET actual_return_date = CURDATE(),
                    fine_amount = :fine,
                    status = 'returned'
                WHERE id = :id AND status COLLATE utf8mb4_unicode_ci = 'Return Pending'
            ");
            $stmtUpd->execute([':fine' => $fine, ':id' => $issueId]);

            // Increment book availability
            $stmtBook = $pdo->prepare("UPDATE books SET available = available + 1 WHERE id = :id");
            $stmtBook->execute([':id' => $issue['book_id']]);

            $pdo->commit();

            $msg = 'Return accepted for "' . htmlspecialchars($issue['book_title'], ENT_QUOTES, 'UTF-8') . '". '
                 . 'Kept ' . $daysKept . ' day(s)';
            if ($fine > 0) {
                $msg .= ', overdue by ' . $overdueDays . ' day(s). Fine: ' . number_format($fine, 2) . ' BDT.';
            } else {
                $msg .= ', no fine.';
            }
            $message = $msg;
            $msgType = $fine > 0 ? 'warning' : 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Database error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }
}

// --- Fetch all Return Pending requests ---
$stmt = $pdo->query("
    SELECT
        ib.id,
        ib.issue_date,
        ib.expected_return_date,
        b.title COLLATE utf8mb4_unicode_ci AS book_title,
        b.author COLLATE utf8mb4_unicode_ci AS author,
        b.isbn,
        m.member_id COLLATE utf8mb4_unicode_ci AS mem_id,
        m.name COLLATE utf8mb4_unicode_ci AS member_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN members m ON ib.member_id = m.id
    WHERE ib.status COLLATE utf8mb4_unicode_ci = 'Return Pending'
    ORDER BY ib.issue_date DESC
");
$requests = $stmt->fetchAll();

$pageTitle = 'Return Requests';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Return Requests</h2>
        <span class="text-muted"><?php echo count($requests); ?> pending return(s)</span>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $msgType); ?>
    <?php endif; ?>

    <div class="card dashboard-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Book</th>
                            <th>Member</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Kept</th>
                            <th>Est. Fine (BDT)</th>
                            <th class="text-center" style="width:140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No pending return requests.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; ?>
                            <?php foreach ($requests as $row):
                                $issueDate  = new DateTime($row['issue_date']);
                                $today      = new DateTime();
                                $daysKept   = (int) $issueDate->diff($today)->days;
                                $overdue    = max(0, $daysKept - 14);
                                $estFine    = $overdue * 10;
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td class="fw-medium">
                                        <?php echo htmlspecialchars($row['book_title'], ENT_QUOTES, 'UTF-8'); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($row['author'], ENT_QUOTES, 'UTF-8'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($row['mem_id'], ENT_QUOTES, 'UTF-8'); ?></code>
                                        <?php echo htmlspecialchars($row['member_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
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
                                        <?php if ($estFine > 0): ?>
                                            <span class="text-danger fw-bold"><?php echo number_format($estFine, 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?accept_return=<?php echo (int) $row['id']; ?>"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Accept this return?<?php echo $estFine > 0 ? ' Fine: ' . number_format($estFine, 2) . ' BDT' : ''; ?>');">
                                            <i class="bi bi-check-circle"></i> Accept Return
                                        </a>
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
