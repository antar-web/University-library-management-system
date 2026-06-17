<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

$pdo = getConnection();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type'])) {
    $loginType  = $_POST['login_type'];
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both identifier and password.';
    } else {
        try {
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
                $stmt->bindValue(':sid', $identifier, PDO::PARAM_STR);
                $stmt->execute();
                $student = $stmt->fetch();

                if ($student && password_verify($password, $student['password'])) {
                    $_SESSION['student_id']    = $student['id'];
                    $_SESSION['student_sid']   = $student['student_id'];
                    $_SESSION['student_name']  = $student['name'];

                    $memberId = 'STU-' . $student['student_id'];
                    $stmtMem  = $pdo->prepare("SELECT id FROM members WHERE member_id = :mid");
                    $stmtMem->bindValue(':mid', $memberId, PDO::PARAM_STR);
                    $stmtMem->execute();
                    $member = $stmtMem->fetch();
                    if ($member) {
                        $_SESSION['student_member_id'] = $member['id'];
                    } else {
                        $stmtIns = $pdo->prepare(
                            "INSERT INTO members (member_id, name, email, phone, reg_date)
                             VALUES (:mid, :name, '', '', CURDATE())"
                        );
                        $stmtIns->bindValue(':mid',  $memberId,        PDO::PARAM_STR);
                        $stmtIns->bindValue(':name', $student['name'], PDO::PARAM_STR);
                        $stmtIns->execute();
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

if (isset($_GET['logout'])) {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
    session_destroy();
    redirect('index.php');
}
if (isset($_SESSION['admin_id']))   { redirect('admin/index.php'); }
if (isset($_SESSION['student_id'])) { redirect('student/index.php'); }

// Book browsing
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$search     = sanitize($_GET['search'] ?? '');
$catSlug    = sanitize($_GET['category'] ?? '');
$params     = [];
$wheres     = [];

if ($search) {
    $wheres[] = "(b.title LIKE :search OR b.author LIKE :search2)";
    $params[':search']  = "%$search%";
    $params[':search2'] = "%$search%";
}
if ($catSlug && $catSlug !== 'all') {
    $wheres[] = "c.name = :cat";
    $params[':cat'] = $catSlug;
}
$whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$sql = "
    SELECT b.*, c.name
    FROM books b
    JOIN categories c ON b.category_id = c.id
    $whereClause
    ORDER BY b.title ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$activeTab = $_GET['tab'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gopalganj Science and Technology University - Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* -------------------------------------------------------
           BASE — Dark overlay background
           ------------------------------------------------------- */
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.55)), url('img/library-bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .brand { font-family: 'Playfair Display', serif; }

        /* -------------------------------------------------------
           LOGIN CARD — Premium dark-translucent glass
           ------------------------------------------------------- */
        .glass-login-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 8px;
        }

        /* -------------------------------------------------------
           TAB TOGGLE BUTTONS
           ------------------------------------------------------- */
        .tab-btn {
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
        .tab-btn:hover:not(.active) {
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.06);
        }
        .tab-btn.active {
            background: linear-gradient(135deg, #3a86ff, #8338ec) !important;
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 4px 20px rgba(58, 134, 255, 0.35);
        }

        /* -------------------------------------------------------
           FORM INPUTS — Semi-transparent dark with neon focus
           ------------------------------------------------------- */
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

        /* -------------------------------------------------------
           SIGN IN BUTTON — Cool Cyan-to-Teal gradient
           ------------------------------------------------------- */
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

        /* -------------------------------------------------------
           DARK THEME FOR SEARCH / FILTERS / BOOK CARDS
           ------------------------------------------------------- */
        .glass-panel {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
        }
        .glass-panel input[type="text"] {
            background: rgba(255, 255, 255, 0.06) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
        }
        .glass-panel input[type="text"]::placeholder {
            color: rgba(255, 255, 255, 0.35) !important;
        }
        .glass-panel input[type="text"]:focus {
            border-color: #3a86ff !important;
            box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.2);
        }

        /* Filter chips */
        .chip {
            transition: all 0.25s ease;
            cursor: pointer;
            user-select: none;
            padding: 6px 18px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.06) !important;
            color: rgba(255, 255, 255, 0.65) !important;
        }
        .chip:hover {
            background: rgba(255, 255, 255, 0.14) !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }
        .chip.active {
            background: linear-gradient(135deg, #3a86ff, #8338ec) !important;
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(58, 134, 255, 0.25);
        }

        /* Search button */
        .btn-search {
            padding: 10px 24px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3a86ff, #8338ec) !important;
            color: #fff;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(58, 134, 255, 0.25);
        }
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(58, 134, 255, 0.4);
        }

        /* Book cards */
        .book-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        }
        .book-card h3 { color: #ffffff; }
        .book-card .author-text { color: rgba(255, 255, 255, 0.55); }
        .book-card .category-badge {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.7);
        }
        .book-card .qty-text { color: rgba(255, 255, 255, 0.6); }
        .book-card .avail-text { color: #00f5d4; }
        .book-card .out-of-stock {
            background: rgba(255, 107, 107, 0.15);
            color: #ff6b6b;
        }
        .book-card .divider {
            border-color: rgba(255, 255, 255, 0.06);
        }

        /* -------------------------------------------------------
           HERO SECTION — Premium Glassmorphism Hero
           ------------------------------------------------------- */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 640px;
            padding: 2rem 1.5rem;
            animation: fadeInUp 1s ease-out forwards;
        }
        .hero-glass-card {
            background: rgba(10, 15, 30, 0.45);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 24px;
            padding: 3rem 2.5rem 2.5rem;
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.5),
                0 0 80px rgba(0, 229, 255, 0.06);
            text-align: center;
        }

        /* Logo Container — Prestigious academic centerpiece */
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
            padding: 20px 0;
        }

        /* Outer decorative ring — university seal aesthetic */
        .logo-ring {
            position: absolute;
            width: 230px;
            height: 230px;
            border-radius: 50%;
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            box-shadow:
                0 0 50px rgba(0, 229, 255, 0.04),
                0 0 100px rgba(124, 77, 255, 0.03),
                inset 0 0 50px rgba(0, 229, 255, 0.02);
            animation: float 7s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            pointer-events: none;
        }

        /* Primary glow aura — deep multi-layered neon radiance */
        .logo-glow {
            position: absolute;
            width: 215px;
            height: 215px;
            border-radius: 50%;
            background: radial-gradient(circle at center,
                rgba(0, 229, 255, 0.35) 0%,
                rgba(124, 77, 255, 0.2) 20%,
                rgba(0, 229, 255, 0.08) 45%,
                rgba(124, 77, 255, 0.03) 60%,
                transparent 78%);
            animation: glowPulse 4s ease-in-out infinite;
            pointer-events: none;
        }

        /* Glass circle container */
        .logo-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 2.5px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow:
                0 0 60px rgba(0, 229, 255, 0.15),
                0 0 120px rgba(0, 229, 255, 0.06),
                inset 0 0 50px rgba(0, 229, 255, 0.06);
            animation: float 6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            position: relative;
            z-index: 1;
        }
        .logo-circle img {
            width: 155px;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 0 18px rgba(0, 229, 255, 0.35));
        }

        /* University Title */
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.75rem, 5vw, 2.75rem);
            font-weight: 800;
            line-height: 1.2;
            color: #ffffff;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
            margin-bottom: 0.5rem;
            letter-spacing: 0.01em;
        }
        .hero-title .highlight-university {
            background: linear-gradient(135deg, #00E5FF, #7C4DFF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }

        /* Subtitle */
        .hero-subtitle {
            font-size: clamp(1rem, 2.5vw, 1.35rem);
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            background: linear-gradient(135deg, #00E5FF, #00FFFF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 2rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
        }
        @keyframes glowPulse {
            0%, 100% {
                opacity: 0.5;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.12);
            }
        }

        /* -------------------------------------------------------
           SEARCH CONTAINER — Responsive flex layout
           ------------------------------------------------------- */
        .search-container {
            display: flex;
            gap: 12px;
            width: 100%;
        }
        .search-container input {
            flex: 1;
            min-width: 0;
        }
        .search-container button {
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            .search-container {
                flex-direction: column;
                width: 100%;
            }
            .search-container input,
            .search-container button {
                width: 100%;
            }
            .search-container button {
                margin: 0;
            }
        }

        /* Responsive */
        @media (max-width: 640px) {
            .hero-glass-card {
                padding: 2rem 1.25rem 1.75rem;
                border-radius: 20px;
            }
            .logo-circle {
                width: 140px;
                height: 140px;
            }
            .logo-circle img {
                width: 120px;
            }
            .logo-glow {
                width: 170px;
                height: 170px;
            }
            .logo-ring {
                width: 180px;
                height: 180px;
            }
            .hero-title {
                font-size: clamp(1.5rem, 7vw, 2rem);
            }
        }
    </style>
</head>
<body class="min-h-screen">

<header class="hero-section">
    <div class="hero-content">
        <!-- Premium Glassmorphism Hero Card -->
        <div class="hero-glass-card">

            <!-- Logo: academic seal with glassmorphism, glow, and floating animation -->
            <div class="logo-container">
                <div class="logo-ring"></div>
                <div class="logo-glow"></div>
                <div class="logo-circle">
                    <img src="img/logo.png" alt="GST University Logo">
                </div>
            </div>

            <!-- University Title with gradient highlight on "University" -->
            <h1 class="hero-title">
                Gopalganj Science &amp; Technology <span class="highlight-university">University</span>
            </h1>

            <!-- Subtitle with cyan gradient -->
            <p class="hero-subtitle">Central Library Portal</p>

            <!-- Login Card (unchanged functionality) -->
            <div class="glass-login-card">
                <div class="flex gap-2 p-1">
                    <button type="button" onclick="switchTab('student')" class="tab-btn <?php echo $activeTab === 'student' ? 'active' : ''; ?>">Student Login</button>
                    <button type="button" onclick="switchTab('admin')" class="tab-btn <?php echo $activeTab === 'admin' ? 'active' : ''; ?>">Admin Login</button>
                </div>

                <?php if ($error): ?>
                <div class="mt-4 bg-red-500/20 border border-red-400/30 text-red-200 text-sm rounded-lg px-4 py-3">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="mt-4 bg-green-500/20 border border-green-400/30 text-green-200 text-sm rounded-lg px-4 py-3">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <!-- Student Login -->
                <div id="form-student" class="mt-5 px-2 pb-2 <?php echo $activeTab === 'student' ? '' : 'hidden'; ?>">
                    <form method="POST" action="" class="text-left">
                        <input type="hidden" name="login_type" value="student">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-blue-200 mb-1">Student ID</label>
                            <input type="text" name="identifier" required placeholder="e.g. 22CSE033" class="glass-input">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-blue-200 mb-1">Password</label>
                            <input type="password" name="password" required placeholder="Enter your password" class="glass-input">
                        </div>
                        <button type="submit" class="btn-cyan-gradient">Sign In</button>
                    </form>
                    <p class="mt-3 text-sm text-blue-200/70">New Student? <a href="student-register.php" class="text-white font-bold underline hover:text-blue-200 transition">Create Account</a></p>
                </div>

                <!-- Admin Login -->
                <div id="form-admin" class="mt-5 px-2 pb-2 <?php echo $activeTab === 'admin' ? '' : 'hidden'; ?>">
                    <form method="POST" action="" class="text-left">
                        <input type="hidden" name="login_type" value="admin">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-blue-200 mb-1">Username / Email</label>
                            <input type="text" name="identifier" required placeholder="Enter your username" class="glass-input">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-blue-200 mb-1">Password</label>
                            <input type="password" name="password" required placeholder="Enter your password" class="glass-input">
                        </div>
                        <button type="submit" class="btn-cyan-gradient">Sign In</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Search & Filters -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6">
    <div class="glass-panel p-4">
        <form method="GET" action="" class="search-container mb-4">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search books by name or author..." class="px-5 py-3 rounded-xl text-sm">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn-search">Search</button>
        </form>
        <div class="flex flex-wrap gap-2">
            <a href="?<?php echo $search ? 'search=' . urlencode($search) : ''; ?>" class="chip <?php echo !$catSlug ? 'active' : ''; ?>">All</a>
            <?php foreach ($categories as $cat): ?>
            <?php
            $active = $catSlug === $cat['name'];
            $url = '?category=' . urlencode($cat['name']);
            if ($search) $url .= '&search=' . urlencode($search);
            ?>
            <a href="<?php echo $url; ?>" class="chip <?php echo $active ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Books Grid -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">Available Books</h2>
        <span class="text-sm text-white/50"><?php echo count($books); ?> book(s)</span>
    </div>

    <?php if (empty($books)): ?>
    <div class="text-center py-20 text-white/40">
        <svg class="w-16 h-16 mx-auto mb-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <p class="text-lg font-medium">No books found</p>
        <p class="text-sm mt-1">Try a different search or category.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($books as $book): ?>
        <div class="book-card flex flex-col">
            <div class="flex items-center justify-center pt-8 pb-4">
                <svg class="w-16 h-16 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div class="px-5 pb-5 flex flex-col flex-1">
                <h3 class="text-base font-semibold leading-snug"><?php echo htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="author-text text-sm mt-1">by <?php echo htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="mt-3">
                    <span class="category-badge inline-block text-xs font-medium px-3 py-1 rounded-full"><?php echo htmlspecialchars($book['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="flex-1"></div>
                <?php $avail = (int)($book['available'] ?? 0); ?>
                <div class="flex items-center justify-between mt-4 pt-3 border-t text-sm divider">
                    <span class="qty-text font-medium">Qty: <?php echo (int)$book['quantity']; ?></span>
                    <?php if ($avail > 0): ?>
                    <span class="avail-text font-medium"><?php echo $avail; ?> available</span>
                    <?php else: ?>
                    <span class="out-of-stock inline-block text-xs font-semibold px-3 py-1 rounded-full">Out of Stock</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<footer class="bg-[#0b2545] text-white/60 text-sm text-center py-6 mt-10">
    &copy; <?php echo date('Y'); ?> Gopalganj Science and Technology University Library. All rights reserved.
</footer>

<script>
function switchTab(tab) {
    document.getElementById('form-student').classList.toggle('hidden', tab !== 'student');
    document.getElementById('form-admin').classList.toggle('hidden', tab !== 'admin');
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.toggle('active', b.textContent.toLowerCase().includes(tab));
    });
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}
</script>

</body>
</html>