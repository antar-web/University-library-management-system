<?php
session_start();
require_once 'config/database.php';

$pdo = getConnection();
$error = '';
$success = '';
$studentId = '';
$fullName = '';
$department = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $studentId   = trim($_POST['student_id'] ?? '');
    $fullName    = trim($_POST['name'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($studentId) || empty($fullName) || empty($department) || empty($email) || empty($password) || empty($confirmPass)) {
        $error = 'Email is required.';
    } elseif ($password !== $confirmPass) {
        $error = 'Passwords do not match!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } else {
        try {
            $memberId = 'STU-' . $studentId;

            // Check students table for duplicate student_id OR phone
            $checkStu = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = :sid OR phone = :phone");
            $checkStu->execute([':sid' => $studentId, ':phone' => $phone]);
            $dupStu = (int) $checkStu->fetchColumn();

            // Check members table for duplicate member_id, phone, OR email
            $checkMem = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id = :mid OR (phone = :phone2 AND phone != '') OR email = :email");
            $checkMem->execute([':mid' => $memberId, ':phone2' => $phone, ':email' => $email]);
            $dupMem = (int) $checkMem->fetchColumn();

            if ($dupStu > 0 || $dupMem > 0) {
                $error = 'Error: This Student ID, Email, or Phone number is already registered.';
            } else {
                $pdo->beginTransaction();

                $hashedPass = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("INSERT INTO students (student_id, name, department, phone, password) VALUES (:sid, :name, :dept, :phone, :pass)");
                $stmt->execute([
                    ':sid'   => $studentId,
                    ':name'  => $fullName,
                    ':dept'  => $department,
                    ':phone' => $phone,
                    ':pass'  => $hashedPass,
                ]);

                $memStmt = $pdo->prepare("INSERT INTO members (member_id, name, email, phone, reg_date) VALUES (:mid, :name, :email, :phone, CURDATE())");
                $memStmt->execute([
                    ':mid'   => $memberId,
                    ':name'  => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                ]);

                $pdo->commit();

                $success = 'Account created successfully! You can now <a href="login.php" class="alert-link">log in</a>.';
                $studentId = $fullName = $department = $email = $phone = '';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();

            $error = 'Error: This Student ID or Phone number is already registered.';
        }
    }
}

$departments = $pdo->query("SELECT DISTINCT dept_name FROM departments ORDER BY dept_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - GSTU Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .brand { font-family: 'Playfair Display', serif; }

        /* ============================================================
           GLASS CARD — floating mobile-app-style sheet
           ============================================================ */
        .glass-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 32px;
            position: relative;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
        }

        /* Thin glowing accent line at the top of the card */
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 20%;
            right: 20%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #00f5d4, #3a86ff, transparent);
            border-radius: 0 0 4px 4px;
            opacity: 0.7;
        }

        /* ============================================================
           INPUT GROUP — icon + field in one row
           ============================================================ */
        .input-group-icon {
            position: relative;
        }

        .input-group-icon .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.3);
            font-size: 1rem;
            z-index: 2;
            pointer-events: none;
            transition: color 0.25s ease;
        }

        .input-group-icon:focus-within .input-icon {
            color: #00f5d4;
        }

        .input-group-icon .glass-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border-radius: 12px;
            font-size: 0.875rem;
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
        }

        .input-group-icon .glass-input::placeholder {
            color: rgba(255, 255, 255, 0.3) !important;
        }

        .input-group-icon .glass-input:focus {
            border-color: rgba(0, 245, 212, 0.5) !important;
            background: rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 0 0 4px rgba(0, 245, 212, 0.08);
        }

        /* ============================================================
           SELECT — custom arrow, matches input style
           ============================================================ */
        .glass-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='rgba(255,255,255,0.4)' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 36px !important;
            cursor: pointer;
        }

        .glass-select option {
            background: #1e293b;
            color: #fff;
        }

        /* ============================================================
           PASSWORD TOGGLE BUTTON
           ============================================================ */
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            padding: 4px;
            z-index: 2;
            font-size: 1.05rem;
            transition: color 0.2s ease;
            line-height: 1;
        }

        .password-toggle:hover {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Push input text right so it doesn't overlap the toggle */
        .input-group-icon .glass-input.has-toggle {
            padding-right: 40px;
        }

        /* ============================================================
           FORM LABEL
           ============================================================ */
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 6px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        /* ============================================================
           CYAN GRADIENT BUTTON — with arrow icon animation
           ============================================================ */
        .btn-cyan-gradient {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            border: none;
            background: linear-gradient(135deg, #00f5d4, #00b4d8) !important;
            color: #0f172a !important;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 245, 212, 0.25);
        }

        .btn-cyan-gradient:hover {
            transform: scale(1.02);
            box-shadow: 0 0 30px rgba(0, 245, 212, 0.45);
        }

        .btn-cyan-gradient:active {
            transform: scale(0.97);
        }

        .btn-cyan-gradient i {
            font-size: 1.1rem;
            transition: transform 0.25s ease;
        }

        .btn-cyan-gradient:hover i {
            transform: translateX(4px);
        }

        /* ============================================================
           TOAST-STYLE ALERTS — slide-in notification look
           ============================================================ */
        .toast-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .toast-alert i {
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .toast-alert-error {
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fca5a5;
        }

        .toast-alert-error i {
            color: #ef4444;
        }

        .toast-alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .toast-alert-success i {
            color: #10b981;
        }

        .toast-alert .alert-link {
            color: #ffffff;
            font-weight: 700;
            text-decoration: underline;
        }

        .toast-alert .alert-link:hover {
            color: #00f5d4;
        }

        /* ============================================================
           FOOTER LINK
           ============================================================ */
        .login-link {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.85rem;
            transition: color 0.2s;
        }

        .login-link:hover {
            color: rgba(255, 255, 255, 0.85);
        }

        .login-link a {
            color: #ffffff;
            font-weight: 700;
        }

        .login-link a:hover {
            color: #00f5d4;
        }

        /* ============================================================
           MOBILE RESPONSIVENESS
           ============================================================ */
        @media (max-width: 768px) {
            .max-w-md {
                width: 92% !important;
                max-width: 400px !important;
            }

            .glass-card {
                padding: 24px 18px !important;
            }

            .brand {
                font-size: 1.4rem !important;
            }

            .form-label {
                font-size: 0.75rem !important;
            }

            .input-group-icon .glass-input {
                font-size: 0.85rem !important;
                padding: 11px 14px 11px 36px !important;
            }

            .input-group-icon .glass-input.has-toggle {
                padding-right: 36px !important;
            }

            .btn-cyan-gradient {
                font-size: 0.85rem !important;
                padding: 13px 16px !important;
            }
        }

        /* Minimal tap highlight for touch devices */
        * {
            -webkit-tap-highlight-color: transparent;
        }
    </style>
</head>
<body class="min-h-screen" style="background: linear-gradient(rgba(10, 25, 47, 0.75), rgba(10, 25, 47, 0.88)), url('img/library-bg.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">

    <div class="w-full max-w-md">

        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="brand text-3xl font-bold text-white">GSTU Library</h1>
            <p class="text-blue-200/70 text-sm mt-1">Create your student account</p>
        </div>

        <!-- Card -->
        <div class="glass-card">

            <!-- Error alert -->
            <?php if ($error): ?>
            <div class="toast-alert toast-alert-error mb-5">
                <i class="bi bi-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php endif; ?>

            <!-- Success alert -->
            <?php if ($success): ?>
            <div class="toast-alert toast-alert-success mb-5">
                <i class="bi bi-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="student-register.php" method="POST">

                <!-- Student ID -->
                <div class="mb-4">
                    <label class="form-label">Student ID</label>
                    <div class="input-group-icon">
                        <i class="bi bi-person-badge input-icon"></i>
                        <input type="text" name="student_id" class="glass-input"
                               value="<?php echo htmlspecialchars($studentId, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="e.g. 22CSE033" required>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="mb-4">
                    <label class="form-label">Full Name</label>
                    <div class="input-group-icon">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" name="name" class="glass-input"
                               value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Your full name" required>
                    </div>
                </div>

                <!-- Department -->
                <div class="mb-4">
                    <label class="form-label">Department</label>
                    <div class="input-group-icon">
                        <i class="bi bi-building input-icon"></i>
                        <input type="text" name="department" class="glass-input" list="dept-list" autocomplete="off"
                               value="<?php echo htmlspecialchars($department, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Enter your department (e.g., CSE)"
                               style="text-transform: uppercase;"
                               oninput="this.value = this.value.toUpperCase();" required>
                        <datalist id="dept-list">
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['dept_name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label class="form-label">Email Address</label>
                    <div class="input-group-icon">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" class="glass-input"
                               value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="your.email@example.com" required>
                    </div>
                </div>

                <!-- Phone -->
                <div class="mb-4">
                    <label class="form-label">Phone Number</label>
                    <div class="input-group-icon">
                        <i class="bi bi-telephone input-icon"></i>
                        <input type="text" name="phone" class="glass-input"
                               value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="017XXXXXXXX">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group-icon">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" name="password" id="reg-password" class="glass-input has-toggle"
                               placeholder="Min 6 characters" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('reg-password', this)" tabindex="-1">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-5">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group-icon">
                        <i class="bi bi-lock-fill input-icon"></i>
                        <input type="password" name="confirm_password" id="reg-confirm" class="glass-input has-toggle"
                               placeholder="Re-enter password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('reg-confirm', this)" tabindex="-1">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-cyan-gradient">
                    <span>Create Account</span>
                    <i class="bi bi-arrow-right"></i>
                </button>

                <!-- Footer link -->
                <p class="mt-4 text-center login-link">
                    Already have an account?
                    <a href="login.php">Sign in</a>
                </p>

            </form>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, btn) {
            var input = document.getElementById(fieldId);
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-slash';
            }
        }
    </script>

</body>
</html>
