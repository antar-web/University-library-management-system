<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

if (isset($_POST['add_department']) && !empty($_POST['dept_name'])) {
    $name = sanitize($_POST['dept_name']);
    try {
        $stmt = $pdo->prepare("INSERT INTO departments (dept_name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        $_SESSION['message'] = 'Department added successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Department name already exists!';
        $_SESSION['message_type'] = 'error';
    }
    redirect('manage-departments.php');
}

if (isset($_GET['delete'])) {
    $deptId = (int)$_GET['delete'];
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE department COLLATE utf8mb4_general_ci = (SELECT dept_name FROM departments WHERE id = :id)");
    $stmtCheck->execute([':id' => $deptId]);
    if ($stmtCheck->fetch()['total'] > 0) {
        $_SESSION['message'] = 'Cannot delete: Students are registered under this department.';
        $_SESSION['message_type'] = 'error';
    } else {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
        $stmt->execute([':id' => $deptId]);
        $_SESSION['message'] = 'Department deleted successfully!';
        $_SESSION['message_type'] = 'success';
    }
    redirect('manage-departments.php');
}

$departments = $pdo->query("
    SELECT d.*, (SELECT COUNT(*) FROM students WHERE department COLLATE utf8mb4_general_ci = d.dept_name) as student_count
    FROM departments d ORDER BY d.dept_name
")->fetchAll();

$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$pageTitle = 'Manage Departments';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Departments</h2>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $messageType); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Add Department Form -->
        <div class="col-md-4">
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Department</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="dept_name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="dept_name" name="dept_name"
                                   placeholder="e.g. CSE, EEE, BBA" required>
                        </div>
                        <button type="submit" name="add_department" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Department
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Departments List -->
        <div class="col-md-8">
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">All Departments</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Department Name</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($dept['dept_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center"><?php echo $dept['student_count']; ?></td>
                                        <td class="text-center">
                                            <a href="?delete=<?php echo $dept['id']; ?>"
                                               class="btn btn-sm btn-danger"
                                               title="Delete"
                                               onclick="return confirm('Delete department &ldquo;<?php echo htmlspecialchars($dept['dept_name'], ENT_QUOTES, 'UTF-8'); ?>&rdquo;?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($departments)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No departments found.</td></tr>
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
