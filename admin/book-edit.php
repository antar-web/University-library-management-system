<?php
/**
 * Edit Book - Update book details
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();
$bookId = (int)($_GET['id'] ?? 0);

if ($bookId <= 0) {
    redirect('books.php');
}

// Fetch book
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
$stmt->execute([':id' => $bookId]);
$book = $stmt->fetch();

if (!$book) {
    $_SESSION['message'] = 'Book not found.';
    $_SESSION['message_type'] = 'error';
    redirect('books.php');
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = sanitize($_POST['title'] ?? '');
    $author      = sanitize($_POST['author'] ?? '');
    $isbn        = sanitize($_POST['isbn'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $quantity    = (int)($_POST['quantity'] ?? 1);

    if (empty($title)) $errors[] = 'Book title is required.';
    if (empty($author)) $errors[] = 'Author name is required.';
    if (empty($isbn)) $errors[] = 'ISBN is required.';
    if ($category_id <= 0) $errors[] = 'Please select a category.';
    if ($quantity < 1) $errors[] = 'Quantity must be at least 1.';

    // Check duplicate ISBN (excluding current book)
    if (empty($errors)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM books WHERE isbn = :isbn AND id != :id");
        $stmtCheck->execute([':isbn' => $isbn, ':id' => $bookId]);
        if ($stmtCheck->fetch()) {
            $errors[] = 'Another book with this ISBN already exists.';
        }
    }

    if (empty($errors)) {
        // Calculate new available count
        $diff = $quantity - $book['quantity'];
        $newAvailable = $book['available'] + $diff;
        if ($newAvailable < 0) {
            $errors[] = 'Cannot reduce quantity below currently issued copies (' . $book['available'] . ' available).';
        } else {
            $stmt = $pdo->prepare("
                UPDATE books SET title = :title, author = :author, isbn = :isbn,
                category_id = :category_id, quantity = :quantity, available = :available
                WHERE id = :id
            ");
            $stmt->execute([
                ':title'            => $title,
                ':author'           => $author,
                ':isbn'             => $isbn,
                ':category_id'      => $category_id,
                ':quantity'         => $quantity,
                ':available' => $newAvailable,
                ':id'          => $bookId,
            ]);
            $_SESSION['message'] = 'Book updated successfully!';
            $_SESSION['message_type'] = 'success';
            redirect('books.php');
        }
    }
}

$pageTitle = 'Edit Book';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Edit Book</h2>
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
                               value="<?php echo htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="author" class="form-label">Author <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="author" name="author"
                               value="<?php echo htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="isbn" class="form-label">ISBN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="isbn" name="isbn"
                               value="<?php echo htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo $book['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="quantity" class="form-label">Total Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quantity" name="quantity"
                               value="<?php echo $book['quantity']; ?>" min="1" required>
                        <small class="text-muted">Currently available: <strong><?php echo $book['available']; ?></strong></small>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Book</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
