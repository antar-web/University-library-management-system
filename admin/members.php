<?php
/**
 * Members Management - List, search, and delete members
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- DELETE ---
if (isset($_GET['delete'])) {
    $memberId = (int)$_GET['delete'];
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as total FROM issued_books WHERE member_id = :id");
    $stmtCheck->execute([':id' => $memberId]);
    if ($stmtCheck->fetch()['total'] > 0) {
        $_SESSION['message'] = 'Cannot delete: Member has borrowing history.';
        $_SESSION['message_type'] = 'error';
    } else {
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = :id");
        $stmt->execute([':id' => $memberId]);
        $_SESSION['message'] = 'Member deleted successfully.';
        $_SESSION['message_type'] = 'success';
    }
    redirect('members.php');
}

// Search
$search = sanitize($_GET['search'] ?? '');
$searchCondition = '';
$params = [];

if ($search) {
    $searchCondition = "WHERE (member_id LIKE :search OR member_name LIKE :search2 OR email LIKE :search3 OR phone LIKE :search4)";
    $params = [
        ':search'  => "%$search%",
        ':search2' => "%$search%",
        ':search3' => "%$search%",
        ':search4' => "%$search%",
    ];
}

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM members $searchCondition");
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];

$pagination = paginate($totalRecords, 10);

$sql = "
    SELECT m.*,
        (SELECT COUNT(*) FROM issued_books WHERE member_id = m.id) as total_borrowed,
        (SELECT COUNT(*) FROM issued_books WHERE member_id = m.id AND status = 'issued') as currently_borrowed
    FROM members m
    $searchCondition
    ORDER BY m.id DESC
    LIMIT :offset, :limit
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll();

$pageTitle = 'Members';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Members Management</h2>
        <a href="member-add.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add New Member</a>
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
                           placeholder="Search by Member ID, Name, Email, or Phone..."
                           value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card dashboard-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Reg. Date</th>
                            <th class="text-center">Borrowed</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No members found.</td></tr>
                        <?php else: ?>
                            <?php $i = $pagination['offset'] + 1; ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><code><?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($member['member_name'] ?? $member['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo formatDate($member['reg_date']); ?></td>
                                    <td class="text-center">
                                        <?php if ($member['currently_borrowed'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $member['currently_borrowed']; ?> active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                        <small class="text-muted d-block">(<?php echo $member['total_borrowed']; ?> total)</small>
                                    </td>
                                    <td class="text-center">
                                        <a href="member-edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="issued-books.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info" title="Borrowing History">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="?delete=<?php echo $member['id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this member?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
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
