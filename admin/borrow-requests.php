<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo     = getConnection();
$message = '';
$msgType = '';

if (isset($_GET['approve'])) {
    $id = (int) $_GET['approve'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT ib.id, ib.book_id, b.available
            FROM issued_books ib
            JOIN books b ON ib.book_id = b.id
            WHERE ib.id = :id AND ib.status COLLATE utf8mb4_unicode_ci = 'Pending'
        ");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch();

        if (!$req) {
            $pdo->rollBack();
            $message = 'Request not found or already processed.';
            $msgType = 'error';
        } elseif ((int) $req['available'] <= 0) {
            $pdo->rollBack();
            $message = 'Book is no longer available. Cannot approve.';
            $msgType = 'error';
        } else {
            $stmtUpd = $pdo->prepare("
                UPDATE issued_books
                SET status = 'issued',
                    issue_date = CURDATE(),
                    expected_return_date = DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                WHERE id = :id AND status COLLATE utf8mb4_unicode_ci = 'Pending'
            ");
            $stmtUpd->execute([':id' => $id]);

            $stmtBook = $pdo->prepare("UPDATE books SET available = available - 1 WHERE id = :id AND available > 0");
            $stmtBook->execute([':id' => $req['book_id']]);

            $pdo->commit();

            $message = 'Borrow request approved. Book issued successfully. (Due in 14 days)';
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Database error: ' . $e->getMessage();
        $msgType = 'error';
    }
}

if (isset($_GET['reject'])) {
    $id = (int) $_GET['reject'];

    $stmt = $pdo->prepare("UPDATE issued_books SET status = 'Rejected' WHERE id = :id AND status COLLATE utf8mb4_unicode_ci = 'Pending'");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        $message = 'Borrow request rejected.';
        $msgType = 'success';
    } else {
        $message = 'Request not found or already processed.';
        $msgType = 'error';
    }
}

$stmt = $pdo->query("
    SELECT
        ib.id,
        ib.issue_date AS requested_on,
        ib.created_at,
        b.id AS book_id,
        b.title COLLATE utf8mb4_unicode_ci AS book_title,
        b.author COLLATE utf8mb4_unicode_ci AS author,
        b.isbn,
        m.member_id COLLATE utf8mb4_unicode_ci AS mem_id,
        m.name COLLATE utf8mb4_unicode_ci AS member_name,
        m.phone COLLATE utf8mb4_unicode_ci AS phone
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN members m ON ib.member_id = m.id
    WHERE ib.status COLLATE utf8mb4_unicode_ci = 'Pending'
    ORDER BY ib.created_at DESC
");
$requests = $stmt->fetchAll();

$pageTitle = 'Borrow Requests';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Borrow Requests</h2>
        <span class="text-muted"><?php echo count($requests); ?> pending request(s)</span>
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
                            <th>Requested On</th>
                            <th>Contact</th>
                            <th class="text-center" style="width:180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No pending borrow requests.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; ?>
                            <?php foreach ($requests as $row): ?>
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
                                    <td><?php echo formatDate($row['requested_on']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? '---', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center">
                                        <a href="?approve=<?php echo (int) $row['id']; ?>"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Approve this borrow request?');">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </a>
                                        <a href="?reject=<?php echo (int) $row['id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Reject this borrow request?');">
                                            <i class="bi bi-x-lg"></i> Reject
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
