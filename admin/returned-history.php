<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

// Search
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

// Count
$countSql = "SELECT COUNT(*) FROM issued_books ib JOIN books b ON ib.book_id = b.id JOIN members m ON ib.member_id = m.id WHERE ib.status = 'returned' $searchCondition";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$pagination = paginate($totalRecords, 15);

$sql = "
    SELECT ib.*, b.title as book_title, b.isbn, m.member_id as mem_id, m.name as member_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN members m ON ib.member_id = m.id
    WHERE ib.status = 'returned' $searchCondition
    ORDER BY ib.actual_return_date DESC
    LIMIT :offset, :limit
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

$pageTitle = 'Returned History';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Returned History</h2>
    </div>

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

    <!-- Records Table -->
    <div class="card dashboard-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Book Title</th>
                            <th>Member</th>
                            <th>Issue Date</th>
                            <th>Return Date</th>
                            <th>Fine (BDT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No returned books found.</td></tr>
                        <?php else: ?>
                            <?php $i = $pagination['offset'] + 1; ?>
                            <?php foreach ($records as $row): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($row['book_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <code><?php echo htmlspecialchars($row['mem_id'], ENT_QUOTES, 'UTF-8'); ?></code>
                                        <?php echo htmlspecialchars($row['member_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo formatDate($row['issue_date']); ?></td>
                                    <td><?php echo formatDate($row['actual_return_date']); ?></td>
                                    <td>
                                        <?php if ((float)$row['fine_amount'] > 0): ?>
                                            <span class="text-danger fw-bold"><?php echo number_format((float)$row['fine_amount'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="card-footer">
                <?php echo renderPagination($pagination['currentPage'], $pagination['totalPages']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
