<?php
/**
 * Book Management - List all books with search and pagination
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Search functionality
$search = sanitize($_GET['search'] ?? '');
$searchCondition = '';
$params = [];

if ($search) {
    $searchCondition = "WHERE (b.title LIKE :search OR b.author LIKE :search2 OR b.isbn LIKE :search3)";
    $params = [
        ':search'  => "%$search%",
        ':search2' => "%$search%",
        ':search3' => "%$search%",
    ];
}

// Count total records for pagination
$countSql = "SELECT COUNT(*) as total FROM books b $searchCondition";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];

$pagination = paginate($totalRecords, 10);

// Fetch books with pagination
$sql = "
    SELECT b.*, c.name
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    $searchCondition
    ORDER BY b.id DESC
    LIMIT :offset, :limit
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll();

$pageTitle = 'Books';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Books Management</h2>
        <a href="book-add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add New Book</a>
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
                           placeholder="Search by Title, Author, or ISBN..."
                           value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Table -->
    <div class="card dashboard-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Available</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No books found.</td></tr>
                        <?php else: ?>
                            <?php $i = $pagination['offset'] + 1; ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><code><?php echo htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($book['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td class="text-center"><?php echo $book['quantity']; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo ($book['available'] ?? 0) > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $book['available'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="book-edit.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="book-add.php?delete=<?php echo $book['id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this book?');">
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
