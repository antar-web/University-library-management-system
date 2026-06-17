<?php
/**
 * Edit Member - Update member profile
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();
$memberId = (int)($_GET['id'] ?? 0);

if ($memberId <= 0) {
    redirect('members.php');
}

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id");
$stmt->execute([':id' => $memberId]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['message'] = 'Member not found.';
    $_SESSION['message_type'] = 'error';
    redirect('members.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $reg_date = sanitize($_POST['reg_date'] ?? '');

    if (empty($name)) $errors[] = 'Member name is required.';
    if (empty($email)) $errors[] = 'Email address is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (empty($phone)) $errors[] = 'Phone number is required.';

    if (empty($errors)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM members WHERE email = :email AND id != :id");
        $stmtCheck->execute([':email' => $email, ':id' => $memberId]);
        if ($stmtCheck->fetch()) {
            $errors[] = 'Another member with this email already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE members SET name = :name, email = :email, phone = :phone, reg_date = :reg_date WHERE id = :id");
        $stmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':phone'    => $phone,
            ':reg_date' => $reg_date,
            ':id'       => $memberId,
        ]);
        $_SESSION['message'] = 'Member updated successfully!';
        $_SESSION['message_type'] = 'success';
        redirect('members.php');
    }
}

$pageTitle = 'Edit Member';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Edit Member</h2>
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
                        <label class="form-label">Member ID</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($member['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="reg_date" class="form-label">Registration Date</label>
                        <input type="date" class="form-control" id="reg_date" name="reg_date"
                               value="<?php echo htmlspecialchars($member['reg_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Member</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
