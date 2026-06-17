<?php
/**
 * Add / Delete Book
 * - Add new books with validation
 * - Delete books via GET parameter
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

// --- DELETE BOOK ---
if (isset($_GET['delete'])) {
    $bookId = (int)$_GET['delete'];

    // Check if book is currently issued
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as total FROM issued_books WHERE book_id = :id AND status = 'issued'");
    $stmtCheck->execute([':id' => $bookId]);
    if ($stmtCheck->fetch()['total'] > 0) {
        $_SESSION['message'] = 'Cannot delete: Book is currently issued to a member.';
        $_SESSION['message_type'] = 'error';
        redirect('books.php');
    }

    $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
    $stmt->execute([':id' => $bookId]);
    $_SESSION['message'] = 'Book deleted successfully.';
    $_SESSION['message_type'] = 'success';
    redirect('books.php');
}

// --- ADD BOOK ---
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$errors = [];
$formData = [
    'title'       => '',
    'author'      => '',
    'isbn'        => '',
    'category_id' => '',
    'quantity'    => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title']       = sanitize($_POST['title'] ?? '');
    $formData['author']      = sanitize($_POST['author'] ?? '');
    $formData['isbn']        = sanitize($_POST['isbn'] ?? '');
    $formData['category_id'] = (int)($_POST['category_id'] ?? 0);
    $formData['quantity']    = (int)($_POST['quantity'] ?? 1);

    // Validation
    if (empty($formData['title'])) $errors[] = 'Book title is required.';
    if (empty($formData['author'])) $errors[] = 'Author name is required.';
    if (empty($formData['isbn'])) $errors[] = 'ISBN is required.';
    if ($formData['category_id'] <= 0) $errors[] = 'Please select a category.';
    if ($formData['quantity'] < 1) $errors[] = 'Quantity must be at least 1.';

    // Check for duplicate ISBN
    if (empty($errors)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM books WHERE isbn = :isbn");
        $stmtCheck->execute([':isbn' => $formData['isbn']]);
        if ($stmtCheck->fetch()) {
            $errors[] = 'A book with this ISBN already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO books (title, author, isbn, category_id, quantity, available)
            VALUES (:title, :author, :isbn, :category_id, :quantity, :available)
        ");
        $stmt->execute([
            ':title'       => $formData['title'],
            ':author'      => $formData['author'],
            ':isbn'        => $formData['isbn'],
            ':category_id' => $formData['category_id'],
            ':quantity'    => $formData['quantity'],
            ':available'   => $formData['quantity'],
        ]);
        $_SESSION['message'] = 'Book added successfully!';
        $_SESSION['message_type'] = 'success';
        redirect('books.php');
    }
}

$pageTitle = 'Add Book';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Add New Book</h2>
        <a href="books.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Books</a>
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
                        <label for="title" class="form-label">Book Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="author" class="form-label">Author <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="author" name="author"
                               value="<?php echo htmlspecialchars($formData['author'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="isbn" class="form-label">ISBN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="isbn" name="isbn"
                               value="<?php echo htmlspecialchars($formData['isbn'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo $formData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="quantity" class="form-label">Total Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quantity" name="quantity"
                               value="<?php echo $formData['quantity']; ?>" min="1" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Book</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
