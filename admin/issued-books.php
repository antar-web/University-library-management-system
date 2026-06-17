<?php
/**
 * Issued Books List with History
 * Shows all issue/return records with filtering
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

// Filter by status
$statusFilter = $_GET['status'] ?? 'all';
$statusCondition = '';
$params = [];

if ($statusFilter === 'issued') {
    $statusCondition = "WHERE ib.status = 'issued'";
} elseif ($statusFilter === 'pending') {
    $statusCondition = "WHERE ib.status IN ('Pending','Return Pending')";
} elseif ($statusFilter === 'rejected') {
    $statusCondition = "WHERE ib.status = 'Rejected'";
} elseif ($statusFilter === 'returned') {
    $statusCondition = "WHERE ib.status = 'returned'";
}

// Filter by member
$memberFilter = (int)($_GET['member_id'] ?? 0);
$memberCondition = '';
if ($memberFilter > 0) {
    $memberCondition = ($statusCondition ? ' AND' : ' WHERE') . " ib.member_id = :member_id";
    $params[':member_id'] = $memberFilter;
}

$fullCondition = $statusCondition . $memberCondition;

// Count for pagination
$countSql = "SELECT COUNT(*) as total FROM issued_books ib $fullCondition";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];

$pagination = paginate($totalRecords, 15);

$sql = "
    SELECT ib.*, b.title as book_title, b.isbn, m.member_id as mem_id, m.name as member_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN members m ON ib.member_id = m.id
    $fullCondition
    ORDER BY ib.id DESC
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

$pageTitle = 'Issued Books';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Issued Books History</h2>
        <div>
            <a href="issue-book.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Issue New</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card dashboard-card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Records</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending / Return Pending</option>
                            <option value="issued" <?php echo $statusFilter === 'issued' ? 'selected' : ''; ?>>Currently Issued</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="returned" <?php echo $statusFilter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Member</label>
                    <select name="member_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Members</option>
                        <?php
                        $allMembers = $pdo->query("SELECT id, member_id, name as member_name FROM members ORDER BY name")->fetchAll();
                        foreach ($allMembers as $m):
                        ?>
                            <option value="<?php echo $m['id']; ?>"
                                <?php echo $memberFilter == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['member_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <a href="issued-books.php" class="btn btn-outline-secondary">Clear Filters</a>
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
                            <th>Book</th>
                            <th>Member</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Fine (BDT)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No records found.</td></tr>
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
                                    <td><?php echo formatDate($row['expected_return_date']); ?></td>
                                    <td><?php echo formatDate($row['actual_return_date']); ?></td>
                                    <td>
                                        <?php if ((float)$row['fine_amount'] > 0): ?>
                                            <span class="text-danger fw-bold"><?php echo number_format((float)$row['fine_amount'], 2); ?></span>
                                        <?php else: ?>
                                            0.00
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <span class="badge bg-primary">Pending</span>
                                        <?php elseif ($row['status'] === 'issued'): ?>
                                            <span class="badge bg-warning text-dark">Issued</span>
                                        <?php elseif (trim($row['status']) === 'Return Pending'): ?>
                                            <span class="badge bg-info text-dark">Return Pending</span>
                                        <?php elseif ($row['status'] === 'Rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Returned</span>
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

<style>
/* ============================================================
   🔥 ULTIMATE BLUR STRIP — issued-books.php only
   Kills ALL backdrop-filter / filter on every parent so text
   is never composited through a blur layer.
   ============================================================ */

/* 1. Nuke blur on every possible container */
body, html, .admin-body,
.dashboard-wrapper, .main-content,
.card, .dashboard-card, .card-body, .card-header, .card-footer,
div, section, .table-responsive, .table,
thead, tbody, tr, td, th,
.container, .container-fluid, .wrapper, .page-wrapper,
#page-wrapper, .content-wrapper, .table-container {
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

/* 2. Solid-dark table card — no translucency = no blur artifact */
.card.dashboard-card,
.dashboard-card {
    background: rgba(15, 23, 42, 0.9) !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

/* 3. Crisp white text everywhere inside the table */
.dashboard-card .table th,
.dashboard-card .table td,
.dashboard-card .table thead th,
.dashboard-card .table tbody td,
.dashboard-card .table tbody tr:hover,
.dashboard-card .table tbody tr:hover td {
    color: #ffffff !important;
    text-shadow: none !important;
}

/* 4. Force row hover to NOT change text color */
.dashboard-card .table tbody tr:hover,
.dashboard-card .table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
}

/* 5. Table header slightly dimmer for hierarchy */
.dashboard-card .table thead th {
    color: rgba(255, 255, 255, 0.75) !important;
}
</style>
<?php require_once __DIR__ . '/footer.php'; ?>
