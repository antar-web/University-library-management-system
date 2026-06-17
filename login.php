<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

// Handle logout
if (isset($_POST['logout']) || isset($_GET['logout'])) {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
    session_destroy();
    redirect('index.php');
}

$error = '';
$loginType = $_GET['type'] ?? ($_POST['type'] ?? 'student');

// Already logged in guard
if (isset($_SESSION['admin_id'])) {
    redirect('admin/index.php');
}
if (isset($_SESSION['student_id'])) {
    redirect('student/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['type'] ?? 'student';
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both identifier and password.';
    } else {
        try {
            $pdo = getConnection();

            if ($loginType === 'admin') {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :u OR email = :e LIMIT 1");
                $stmt->execute([':u' => $identifier, ':e' => $identifier]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id']        = $admin['id'];
                    $_SESSION['admin_name']      = $admin['username'];
                    $_SESSION['admin_logged_in'] = true;
                    redirect('admin/index.php');
                } else {
                    $error = 'Invalid admin credentials.';
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :sid LIMIT 1");
                $stmt->execute([':sid' => $identifier]);
                $student = $stmt->fetch();

                if ($student && password_verify($password, $student['password'])) {
                    $_SESSION['student_id']    = $student['id'];
                    $_SESSION['student_sid']    = $student['student_id'];
                    $_SESSION['student_name']   = $student['name'];

                    // Find or create member record for FK compatibility
                    $memberId = 'STU-' . $student['student_id'];
                    $stmtMem = $pdo->prepare("SELECT id FROM members WHERE member_id = :mid");
                    $stmtMem->execute([':mid' => $memberId]);
                    $member = $stmtMem->fetch();
                    if ($member) {
                        $_SESSION['student_member_id'] = $member['id'];
                    } else {
                        $stmtIns = $pdo->prepare("
                            INSERT INTO members (member_id, name, email, phone, reg_date)
                            VALUES (:mid, :name, '', '', CURDATE())
                        ");
                        $stmtIns->execute([':mid' => $memberId, ':name' => $student['name']]);
                        $_SESSION['student_member_id'] = $pdo->lastInsertId();
                    }

                    redirect('student/index.php');
                } else {
                    $error = 'Invalid student ID or password.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GSTU Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .brand { font-family: 'Playfair Display', serif; }

        /* Glass login card */
        .glass-login-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
        }

        /* Role toggle tabs */
        .role-btn {
            transition: all 0.25s ease;
            cursor: pointer;
            flex: 1;
            padding: 10px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 10px;
            text-align: center;
            background: transparent;
            color: rgba(255, 255, 255, 0.6);
            border: 1.5px solid transparent;
        }
        .role-btn:hover:not(.active-tab) {
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.06);
        }
        .role-btn.active-tab {
            background: linear-gradient(135deg, #3a86ff, #8338ec) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 20px rgba(58, 134, 255, 0.35);
        }

        /* Glass inputs */
        .glass-input {
            width: 100%;
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 0.875rem;
            background: rgba(255, 255, 255, 0.06) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
            outline: none;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .glass-input::placeholder {
            color: rgba(255, 255, 255, 0.35) !important;
        }
        .glass-input:focus {
            border-color: #3a86ff !important;
            box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.2);
        }

        /* Cyan gradient sign-in */
        .btn-cyan-gradient {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            border: none;
            background: linear-gradient(135deg, #00f5d4, #00b4d8) !important;
            color: #0f172a !important;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 245, 212, 0.25);
        }
        .btn-cyan-gradient:hover {
            transform: scale(1.02);
            box-shadow: 0 0 25px rgba(0, 245, 212, 0.5);
        }
        .btn-cyan-gradient:active {
            transform: scale(0.98);
        }

        /* ============================================================
           MOBILE RESPONSIVENESS
           ============================================================ */
        @media (max-width: 768px) {
            /* Outer wrapper — ensure it fills the screen width */
            .max-w-md {
                width: 90% !important;
                max-width: 400px !important;
            }

            /* Card padding shrinks so inputs don't touch edges */
            .glass-login-card {
                padding: 24px 20px !important;
            }

            /* Heading scales down */
            .brand {
                font-size: 1.5rem !important;
            }

            /* Slightly smaller label text */
            .form-label {
                font-size: 0.8rem !important;
            }

            /* Input text scales slightly */
            .glass-input {
                font-size: 0.85rem !important;
                padding: 10px 14px !important;
            }

            /* Button text adjustment */
            .btn-cyan-gradient {
                font-size: 0.85rem !important;
                padding: 11px 16px !important;
            }
        }

        /* Labels */
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 6px;
        }
    </style>
</head>
<body class="min-h-screen" style="background: linear-gradient(rgba(10, 25, 47, 0.75), rgba(10, 25, 47, 0.88)), url('img/library-bg.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="brand text-3xl font-bold text-white">GSTU Library</h1>
            <p class="text-blue-200/70 text-sm mt-1">Sign in to your account</p>
        </div>

        <div class="glass-login-card">
            <!-- Role Toggle -->
            <div class="flex gap-2 p-1 mb-6">
                <button type="button" onclick="switchRole('student')"
                    class="role-btn <?php echo $loginType === 'student' ? 'active-tab' : ''; ?>">
                    Student
                </button>
                <button type="button" onclick="switchRole('admin')"
                    class="role-btn <?php echo $loginType === 'admin' ? 'active-tab' : ''; ?>">
                    Admin
                </button>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-400/30 text-red-200 text-sm rounded-lg px-4 py-3 mb-4">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="type" id="login_type" value="<?php echo $loginType; ?>">

                <div class="mb-4">
                    <label for="identifier" class="form-label">
                        <span id="label-text"><?php echo $loginType === 'admin' ? 'Username / Email' : 'Student ID'; ?></span>
                    </label>
                    <input type="text" id="identifier" name="identifier" required
                           placeholder="<?php echo $loginType === 'admin' ? 'Enter username or email' : 'Enter your student ID'; ?>"
                           class="glass-input">
                </div>

                <div class="mb-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password"
                           class="glass-input">
                </div>

                <button type="submit" class="btn-cyan-gradient">Sign In</button>
            </form>

            <p class="mt-5 text-center text-sm text-white/50">
                <span id="register-link">
                    New Student?
                    <a href="student-register.php" class="text-white font-bold hover:underline">Register Here</a>
                </span>
            </p>

            <p class="mt-3 text-center">
                <a href="/University%20library%20system" class="text-sm text-white/40 hover:text-white/70 transition">&larr; Back to Library</a>
            </p>
        </div>
    </div>

    <script>
        function switchRole(role) {
            document.getElementById('login_type').value = role;
            document.querySelectorAll('.role-btn').forEach(btn => {
                btn.classList.remove('active-tab');
            });
            const btns = document.querySelectorAll('.role-btn');
            const idx = role === 'student' ? 0 : 1;
            btns[idx].classList.add('active-tab');

            const label = document.getElementById('label-text');
            const input = document.getElementById('identifier');
            const regLink = document.getElementById('register-link');
            if (role === 'admin') {
                label.textContent = 'Username / Email';
                input.placeholder = 'Enter username or email';
                regLink.innerHTML = '';
            } else {
                label.textContent = 'Student ID';
                input.placeholder = 'Enter your student ID';
                regLink.innerHTML = 'New Student? <a href="student-register.php" class="text-white font-bold hover:underline">Register Here</a>';
            }

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('type', role);
            window.history.replaceState({}, '', url);
        }
    </script>
</body>
</html>