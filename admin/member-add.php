<?php
/**
 * Add New Member
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

$errors = [];
$formData = [
    'name'     => '',
    'email'    => '',
    'phone'    => '',
    'reg_date' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name']     = sanitize($_POST['name'] ?? '');
    $formData['email']    = sanitize($_POST['email'] ?? '');
    $formData['phone']    = sanitize($_POST['phone'] ?? '');
    $formData['reg_date'] = sanitize($_POST['reg_date'] ?? date('Y-m-d'));

    if (empty($formData['name'])) $errors[] = 'Member name is required.';
    if (empty($formData['email'])) $errors[] = 'Email address is required.';
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (empty($formData['phone'])) $errors[] = 'Phone number is required.';
    if (empty($formData['reg_date'])) $errors[] = 'Registration date is required.';

    if (empty($errors)) {
        // Check duplicate email
        $stmtCheck = $pdo->prepare("SELECT id FROM members WHERE email = :email");
        $stmtCheck->execute([':email' => $formData['email']]);
        if ($stmtCheck->fetch()) {
            $errors[] = 'A member with this email already exists.';
        }
    }

    if (empty($errors)) {
        $memberId = generateMemberId($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO members (member_id, name, email, phone, reg_date)
            VALUES (:member_id, :name, :email, :phone, :reg_date)
        ");
        $stmt->execute([
            ':member_id' => $memberId,
            ':name'      => $formData['name'],
            ':email'     => $formData['email'],
            ':phone'     => $formData['phone'],
            ':reg_date'  => $formData['reg_date'],
        ]);
        $_SESSION['message'] = "Member $memberId added successfully!";
        $_SESSION['message_type'] = 'success';
        redirect('members.php');
    }
}

$pageTitle = 'Add Member';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Add New Member</h2>
        <a href="members.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Members</a>
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
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="reg_date" class="form-label">Registration Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="reg_date" name="reg_date"
                               value="<?php echo htmlspecialchars($formData['reg_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Member</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
