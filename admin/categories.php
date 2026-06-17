<?php
/**
 * Categories Management - Add, Edit, Delete categories
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

// --- ADD CATEGORY ---
if (isset($_POST['add_category']) && !empty($_POST['name'])) {
    $name = sanitize($_POST['name']);
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        $_SESSION['message'] = 'Category added successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Category name already exists!';
        $_SESSION['message_type'] = 'error';
    }
    redirect('categories.php');
}

// --- EDIT CATEGORY ---
if (isset($_POST['edit_category']) && !empty($_POST['name']) && !empty($_POST['cat_id'])) {
    $name = sanitize($_POST['name']);
    $catId = (int)$_POST['cat_id'];
    try {
        $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
        $stmt->execute([':name' => $name, ':id' => $catId]);
        $_SESSION['message'] = 'Category updated successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Category name already exists!';
        $_SESSION['message_type'] = 'error';
    }
    redirect('categories.php');
}

// --- DELETE CATEGORY ---
if (isset($_GET['delete'])) {
    $catId = (int)$_GET['delete'];
    // Check if any books use this category
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as total FROM books WHERE category_id = :id");
    $stmtCheck->execute([':id' => $catId]);
    if ($stmtCheck->fetch()['total'] > 0) {
        $_SESSION['message'] = 'Cannot delete: Books are assigned to this category.';
        $_SESSION['message_type'] = 'error';
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $catId]);
        $_SESSION['message'] = 'Category deleted successfully!';
        $_SESSION['message_type'] = 'success';
    }
    redirect('categories.php');
}

$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM books WHERE category_id = c.id) as book_count
    FROM categories c ORDER BY c.name
")->fetchAll();

$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$editingCat = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editingCat = $stmt->fetch();
}

$pageTitle = 'Categories';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Book Categories</h2>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $messageType); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Add / Edit Category Form -->
        <div class="col-md-4">
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?php echo $editingCat ? 'Edit Category' : 'Add New Category'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($editingCat): ?>
                            <input type="hidden" name="cat_id" value="<?php echo $editingCat['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo $editingCat ? htmlspecialchars($editingCat['name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                   placeholder="Enter category name" required>
                        </div>
                        <?php if ($editingCat): ?>
                            <button type="submit" name="edit_category" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Update
                            </button>
                            <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_category" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Add Category
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Categories List -->
        <div class="col-md-8">
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">All Categories</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Category Name</th>
                                    <th class="text-center">Books</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center"><?php echo $cat['book_count']; ?></td>
                                        <td class="text-center">
                                            <a href="?edit=<?php echo $cat['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?delete=<?php echo $cat['id']; ?>"
                                               class="btn btn-sm btn-danger"
                                               title="Delete"
                                               onclick="return confirm('Delete this category?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No categories found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
