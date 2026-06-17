<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

if (!isset($_SESSION['student_id'])) {
    redirect('../index.php');
}

$pdo              = getConnection();
$studentId        = $_SESSION['student_id'];
$studentSid       = $_SESSION['student_sid'];
$studentName      = $_SESSION['student_name'];
$studentMemberId  = $_SESSION['student_member_id'] ?? null;

$actionMsg     = '';
$actionMsgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'borrow' && isset($_POST['book_id'])) {
        $bookId = (int)$_POST['book_id'];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT available FROM books WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $bookId]);
            $book = $stmt->fetch();

            if (!$book || (int)$book['available'] <= 0) {
                $pdo->rollBack();
                $actionMsg    = 'This book is no longer available.';
                $actionMsgType = 'danger';
                } else {
                    $checkExisting = $pdo->prepare(
                        "SELECT id FROM issued_books WHERE book_id = :bid AND member_id = :mid AND status COLLATE utf8mb4_unicode_ci IN ('Pending','issued','Return Pending')"
                    );
                    $checkExisting->execute([':bid' => $bookId, ':mid' => $studentMemberId]);
                    if ($checkExisting->fetch()) {
                        $pdo->rollBack();
                        $actionMsg    = 'You already have a pending or active request for this book.';
                        $actionMsgType = 'warning';
                    } else {
                        // Verify ENUM supports 'Pending' before inserting
                        $enumCheck = $pdo->query("SHOW COLUMNS FROM issued_books WHERE Field = 'status'")->fetch();
                        if ($enumCheck && strpos($enumCheck['Type'], "'Pending'") === false) {
                            $pdo->rollBack();
                            $actionMsg    = 'System error: Pending status not supported. Run ALTER TABLE issued_books MODIFY COLUMN status ENUM(...).';
                            $actionMsgType = 'danger';
                        } else {
                            $stmt = $pdo->prepare(
                                "INSERT INTO issued_books (book_id, member_id, issue_date, expected_return_date, status)
                                 VALUES (:bid, :mid, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Pending')"
                            );
                            $stmt->execute([':bid' => $bookId, ':mid' => $studentMemberId]);

                            $pdo->commit();
                            $actionMsg    = 'Borrow request sent! Admin will review it shortly.';
                            $actionMsgType = 'success';
                        }
                    }
                }
        } catch (Exception $e) {
            $pdo->rollBack();
            $actionMsg    = 'Database error: ' . $e->getMessage();
            $actionMsgType = 'danger';
        }
    }

    if ($action === 'return_request' && isset($_POST['issue_id'])) {
        $issueId = (int)$_POST['issue_id'];
        try {
            $stmt = $pdo->prepare(
                "SELECT ib.id FROM issued_books ib
                 WHERE ib.id = :iid AND ib.member_id = :mid AND ib.status COLLATE utf8mb4_unicode_ci = 'issued'"
            );
            $stmt->execute([':iid' => $issueId, ':mid' => $studentMemberId]);
            $record = $stmt->fetch();

            if (!$record) {
                $actionMsg    = 'Record not found or already requested.';
                $actionMsgType = 'danger';
            } else {
                $enumCheck = $pdo->query("SHOW COLUMNS FROM issued_books WHERE Field = 'status'")->fetch();
                if ($enumCheck && strpos($enumCheck['Type'], "'Return Pending'") === false) {
                    $actionMsg    = 'System error: Return Pending status not supported. Run ALTER TABLE issued_books first.';
                    $actionMsgType = 'danger';
                } else {
                    $stmt = $pdo->prepare("UPDATE issued_books SET status = 'Return Pending' WHERE id = :id");
                    $stmt->execute([':id' => $issueId]);
                    $actionMsg    = 'Return request sent. Admin will process it shortly.';
                    $actionMsgType = 'success';
                }
            }
        } catch (Exception $e) {
            $actionMsg    = 'Database error: ' . $e->getMessage();
            $actionMsgType = 'danger';
        }
    }
}

// Pagination for books
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 8;
$offset     = ($page - 1) * $perPage;
$search     = trim($_GET['search'] ?? '');
$catSlug    = trim($_GET['category'] ?? '');

$params     = [];
$wheres     = [];

if ($search) {
    $wheres[] = "(b.title LIKE :search OR b.author LIKE :author)";
    $params[':search'] = "%$search%";
    $params[':author'] = "%$search%";
}
if ($catSlug && $catSlug !== 'all') {
    $wheres[] = "c.name = :cat";
    $params[':cat'] = $catSlug;
}
$whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$countSql = "SELECT COUNT(*) FROM books b JOIN categories c ON b.category_id = c.id $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalBooks = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalBooks / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "
    SELECT b.id, b.title, b.author, b.quantity, b.available, c.name
    FROM books b
    JOIN categories c ON b.category_id = c.id
    $whereClause
    ORDER BY b.title ASC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Pending requests (awaiting admin approval)
$pendingSql = "
    SELECT ib.id, b.title, b.author, ib.issue_date AS request_date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    WHERE ib.member_id = :mid AND ib.status COLLATE utf8mb4_unicode_ci = 'Pending'
    ORDER BY ib.created_at DESC
";
$pendingStmt = $pdo->prepare($pendingSql);
$pendingStmt->execute([':mid' => $studentMemberId]);
$pendingRequests = $pendingStmt->fetchAll();

// Borrowed items (issued, return pending, returned)
$borrowedSql = "
    SELECT br.*, b.title, b.author, br.issue_date, br.expected_return_date, br.actual_return_date, br.fine_amount
    FROM issued_books br
    JOIN books b ON br.book_id = b.id
    WHERE br.member_id = :mid
    ORDER BY br.issue_date DESC
";
$borrowedStmt = $pdo->prepare($borrowedSql);
$borrowedStmt->execute([':mid' => $studentMemberId]);
$borrowings = $borrowedStmt->fetchAll();

$totalFines = 0;
foreach ($borrowings as $br) {
    if ($br['actual_return_date'] && (float)$br['fine_amount'] > 0) {
        $totalFines += (float)$br['fine_amount'];
    } elseif (!$br['actual_return_date'] && $br['expected_return_date']) {
        $due = new DateTime($br['expected_return_date']);
        $now = new DateTime();
        if ($now > $due) {
            $daysOverdue = (int)$due->diff($now)->days;
            $totalFines += $daysOverdue * 5;
        }
    }
}

// Student info
$studentInfo = $pdo->prepare("SELECT * FROM students WHERE id = :id");
$studentInfo->execute([':id' => $studentId]);
$studentData = $studentInfo->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - GSTU Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           BASE — Full-screen dark background
           ============================================================ */
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(rgba(10, 25, 47, 0.72), rgba(10, 25, 47, 0.85)), url('../img/student.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        /* ============================================================
           GLASSMORPHISM HEADER
           ============================================================ */
        .glass-header {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: rgba(10, 25, 47, 0.6) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* ============================================================
           CRIMSON OUTLINE BUTTON (Sign Out)
           ============================================================ */
        .crimson-outline-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1.5px solid rgba(255, 107, 107, 0.35);
            background: transparent;
            color: rgba(255, 107, 107, 0.8) !important;
            padding: 7px 18px;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .crimson-outline-btn:hover {
            background: rgba(255, 107, 107, 0.15);
            border-color: #ff6b6b;
            color: #ff6b6b !important;
            box-shadow: 0 0 20px rgba(255, 107, 107, 0.2);
            transform: translateY(-1px);
        }

        /* ============================================================
           COLORFUL GLOWING NAVIGATION TABS
           ============================================================ */
        .nav-module {
            background: rgba(255, 255, 255, 0.06) !important;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .nav-module:hover {
            transform: translateY(-4px) scale(1.02);
        }
        .nav-module.active {
            background: rgba(17, 34, 64, 0.75) !important;
            transform: translateY(-3px) scale(1.02);
        }
        .nav-module h3 { color: rgba(255, 255, 255, 0.9) !important; }
        .nav-module p  { color: rgba(255, 255, 255, 0.5) !important; }
        .nav-module svg { color: rgba(255, 255, 255, 0.6) !important; }

        /* Browse Books — Neon Cyan / Emerald */
        #nav-browse {
            border-color: rgba(0, 245, 212, 0.25);
        }
        #nav-browse:hover {
            border-color: rgba(0, 245, 212, 0.55);
            box-shadow: 0 8px 30px rgba(0, 245, 212, 0.15);
        }
        #nav-browse.active {
            border-color: #00f5d4;
            box-shadow: 0 0 35px rgba(0, 245, 212, 0.25);
        }
        #nav-browse.active svg { color: #00f5d4 !important; }
        #nav-browse.active h3,
        #nav-browse.active p { color: #ffffff !important; }

        /* My Borrowed Items — Gold / Amber */
        #nav-borrowed {
            border-color: rgba(255, 159, 28, 0.25);
        }
        #nav-borrowed:hover {
            border-color: rgba(255, 159, 28, 0.55);
            box-shadow: 0 8px 30px rgba(255, 159, 28, 0.15);
        }
        #nav-borrowed.active {
            border-color: #ff9f1c;
            box-shadow: 0 0 35px rgba(255, 159, 28, 0.25);
        }
        #nav-borrowed.active svg { color: #ff9f1c !important; }
        #nav-borrowed.active h3,
        #nav-borrowed.active p { color: #ffffff !important; }

        /* Account Overview — Royal Electric Blue */
        #nav-account {
            border-color: rgba(58, 134, 255, 0.25);
        }
        #nav-account:hover {
            border-color: rgba(58, 134, 255, 0.55);
            box-shadow: 0 8px 30px rgba(58, 134, 255, 0.15);
        }
        #nav-account.active {
            border-color: #3a86ff;
            box-shadow: 0 0 35px rgba(58, 134, 255, 0.25);
        }
        #nav-account.active svg { color: #3a86ff !important; }
        #nav-account.active h3,
        #nav-account.active p { color: #ffffff !important; }

        /* ============================================================
           PREMIUM DARK TRANSLUCENT CONTENT CARDS
           ============================================================ */
        .premium-card {
            background: rgba(15, 23, 42, 0.8) !important;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .premium-card .text-gray-900,
        .premium-card h1, .premium-card h2,
        .premium-card h3, .premium-card h4,
        .premium-card .font-bold,
        .premium-card .font-semibold,
        .premium-card .font-medium {
            color: #ffffff !important;
        }
        .premium-card .text-gray-500,
        .premium-card .text-gray-600,
        .premium-card .text-gray-700 {
            color: rgba(255, 255, 255, 0.55) !important;
        }
        .premium-card .text-gray-400 {
            color: rgba(255, 255, 255, 0.4) !important;
        }
        .premium-card .border-gray-100,
        .premium-card .border-gray-200 {
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        .premium-card .bg-gray-50,
        .premium-card .bg-gray-100 {
            background: rgba(255, 255, 255, 0.06) !important;
        }

        /* Form inputs inside premium cards */
        .premium-card input[type="text"] {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
        }
        .premium-card input::placeholder {
            color: rgba(255, 255, 255, 0.35) !important;
        }

        /* ============================================================
           BOOK CARDS
           ============================================================ */
        .book-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        }
        .book-card svg.text-\[\#1e3a5f\] {
            color: rgba(255, 255, 255, 0.45) !important;
        }
        .book-card .text-emerald-600 { color: #00f5d4 !important; }
        .book-card .text-red-600,
        .book-card .bg-red-100 span { color: #ff6b6b !important; }
        .book-card .bg-red-100 {
            background: rgba(255, 107, 107, 0.15) !important;
        }

        /* ============================================================
           FILTER CHIPS — Genre-tinted pills
           ============================================================ */
        .chip {
            padding: 6px 18px !important;
            border-radius: 999px !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            transition: all 0.25s ease !important;
            cursor: pointer;
            user-select: none;
            border: 1.5px solid transparent;
            background: rgba(255, 255, 255, 0.08) !important;
            color: rgba(255, 255, 255, 0.7) !important;
        }
        .chip:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.14) !important;
            color: #ffffff !important;
        }
        .chip.active {
            background: linear-gradient(135deg, #1e3a5f, #2a5a8f) !important;
            color: #fff !important;
            border-color: rgba(255, 255, 255, 0.15);
        }
        .chip:nth-child(2) { border-color: rgba(0, 245, 212, 0.35); }
        .chip:nth-child(3) { border-color: rgba(255, 159, 28, 0.35); }
        .chip:nth-child(4) { border-color: rgba(58, 134, 255, 0.35); }
        .chip:nth-child(5) { border-color: rgba(187, 134, 252, 0.35); }
        .chip:nth-child(6) { border-color: rgba(255, 107, 107, 0.35); }
        .chip:nth-child(7) { border-color: rgba(0, 230, 118, 0.35); }

        /* ============================================================
           BORROWED ITEMS STATUS COLORS
           ============================================================ */
        .text-emerald-600 { color: #00f5d4 !important; }
        .text-amber-600  { color: #ff9f1c !important; }
        .text-red-600    { color: #ff6b6b !important; }
        .text-blue-600   { color: #3a86ff !important; }

        .bg-emerald-50 { background: rgba(0, 245, 212, 0.1) !important; }
        .border-emerald-200 { border-color: rgba(0, 245, 212, 0.2) !important; }
        .text-emerald-700 { color: #00f5d4 !important; }
        .bg-amber-50 { background: rgba(255, 159, 28, 0.1) !important; }
        .border-amber-200 { border-color: rgba(255, 159, 28, 0.2) !important; }
        .text-amber-700 { color: #ff9f1c !important; }
        .bg-red-50 { background: rgba(255, 107, 107, 0.1) !important; }
        .border-red-200 { border-color: rgba(255, 107, 107, 0.2) !important; }
        .text-red-700 { color: #ff6b6b !important; }

        /* ============================================================
           FINE HIGHLIGHT — Glowing Crimson Red alert
           ============================================================ */
        .fine-highlight {
            color: #ff6b6b !important;
            text-shadow: 0 0 20px rgba(255, 107, 107, 0.4);
            font-weight: 800 !important;
        }

        /* ============================================================
           PAGINATION
           ============================================================ */
        a.rounded-xl {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            color: rgba(255, 255, 255, 0.8) !important;
        }
        a.rounded-xl:hover {
            background: rgba(255, 255, 255, 0.16) !important;
        }
        a.rounded-xl.bg-\[\#1e3a5f\] {
            background: linear-gradient(135deg, #1e3a5f, #2a5a8f) !important;
            color: #fff !important;
            border-color: transparent !important;
        }

        /* ============================================================
           BUTTONS
           ============================================================ */
        .bg-\[\#1e3a5f\] {
            background: linear-gradient(135deg, #1e3a5f, #2a5a8f) !important;
        }
        .bg-\[\#1e3a5f\]:hover {
            background: linear-gradient(135deg, #2a5a8f, #3a7abf) !important;
        }

        /* Borrow button override — emerald gradient */
        .book-card .bg-\[\#1e3a5f\] {
            background: linear-gradient(135deg, #0d6b5e, #00a892) !important;
        }
        .book-card .bg-\[\#1e3a5f\]:hover {
            background: linear-gradient(135deg, #00a892, #00f5d4) !important;
            color: #0a1a2e !important;
        }

        /* ============================================================
           SECTION HEADINGS (outside premium-card)
           ============================================================ */
        .view-section h2.text-gray-900,
        .view-section .text-gray-500 {
            color: #ffffff !important;
        }
        .view-section .text-gray-500 {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        footer {
            background: rgba(10, 25, 47, 0.65) !important;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 640px) {
            .nav-module { padding: 14px !important; }
            .nav-module svg { width: 26px; height: 26px; }
        }
    </style>
</head>
<body class="min-h-screen" style="padding-top: 44px;">

<!-- Floating Policy Ticker -->
<div style="position: fixed; top: 0; left: 0; width: 100%; z-index: 9999; background: rgba(25, 20, 10, 0.95); backdrop-filter: blur(5px); border-bottom: 1px solid #ffc107; height: 44px; overflow: hidden;">
    <div style="display: inline-block; white-space: nowrap; padding-left: 100%; animation: ticker-scroll 30s linear infinite; color: #ffca28; font-weight: 700; font-size: 14px; line-height: 44px; letter-spacing: 0.3px;">
        <span>&#9888;&#65039; ATTENTION POLICY: Borrowed books must be returned within 14 days. A late fine of Tk 10 per day will be automatically charged after the due date. &bull; </span>
        <span>&#9888;&#65039; ATTENTION POLICY: Borrowed books must be returned within 14 days. A late fine of Tk 10 per day will be automatically charged after the due date. &bull; </span>
    </div>
</div>

<style>
@keyframes ticker-scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
</style>

<header class="bg-[#0b2545]/60 text-white glass-header">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between py-4">
            <span class="text-xl font-bold">GSTU Library</span>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-blue-200"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></span>
                <a href="../index.php?logout=1" class="crimson-outline-btn">Sign Out</a>
            </div>
        </div>
        <div class="pt-6 pb-10 text-center">
            <h1 class="text-2xl sm:text-3xl font-extrabold">Student Dashboard</h1>
            <p class="text-blue-200/70 text-sm mt-1">Welcome back, <?php echo htmlspecialchars(explode(' ', $studentName)[0], ENT_QUOTES, 'UTF-8'); ?>!</p>
        </div>
    </div>
</header>

<!-- Navigation Modules -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-5">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div id="nav-browse" class="nav-module active rounded-xl p-5 text-center" onclick="switchView('browse')">
            <svg class="w-8 h-8 mx-auto text-[#1e3a5f] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 class="font-semibold text-gray-900 text-sm">Browse Books</h3>
            <p class="text-xs text-gray-500 mt-0.5">Search &amp; borrow from the library</p>
        </div>
        <div id="nav-borrowed" class="nav-module rounded-xl p-5 text-center" onclick="switchView('borrowed')">
            <svg class="w-8 h-8 mx-auto text-[#1e3a5f] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <h3 class="font-semibold text-gray-900 text-sm">My Borrowed Items</h3>
            <p class="text-xs text-gray-500 mt-0.5"><?php echo count($borrowings); ?> item(s) on record<?php if (count($pendingRequests) > 0): ?> &middot; <span class="text-amber-400 font-semibold"><?php echo count($pendingRequests); ?> pending</span><?php endif; ?></p>
        </div>
        <div id="nav-account" class="nav-module rounded-xl p-5 text-center" onclick="switchView('account')">
            <svg class="w-8 h-8 mx-auto text-[#1e3a5f] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <h3 class="font-semibold text-gray-900 text-sm">Account Overview</h3>
            <p class="text-xs text-gray-500 mt-0.5">Profile, fines &amp; settings</p>
        </div>
    </div>
</div>

<!-- Action Messages -->
<?php if ($actionMsg): ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
    <div class="rounded-xl px-5 py-3 text-sm font-medium shadow-sm border <?php echo $actionMsgType === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($actionMsgType === 'warning' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200'); ?>">
        <?php echo htmlspecialchars($actionMsg, ENT_QUOTES, 'UTF-8'); ?>
    </div>
</div>
<?php endif; ?>

<!-- ========== VIEW: Browse Books ========== -->
<div id="view-browse" class="view-section">
    <!-- Search + Filters -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div class="rounded-2xl p-4 premium-card">
            <form method="GET" action="" class="flex gap-2 mb-4" onsubmit="document.querySelector('[name=view]').value='browse'">
                <input type="hidden" name="view" value="browse">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search books by title or author..." class="flex-1 px-5 py-2.5 rounded-xl text-gray-900 placeholder-gray-400 border border-gray-200 focus:ring-2 focus:ring-[#1e3a5f] text-sm">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1e3a5f] hover:bg-[#0b2545] text-white font-semibold text-sm shadow-sm transition">Search</button>
            </form>
            <div class="flex flex-wrap gap-2">
                <a href="?view=browse" class="chip px-4 py-1.5 rounded-full text-sm font-medium border <?php echo !$catSlug ? 'active' : 'bg-white text-gray-600 border-gray-300'; ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                <a href="?view=browse&category=<?php echo urlencode($cat['name']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="chip px-4 py-1.5 rounded-full text-sm font-medium border <?php echo ($catSlug === $cat['name']) ? 'active' : 'bg-white text-gray-600 border-gray-300'; ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Books Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">All Books</h2>
            <span class="text-sm text-gray-500"><?php echo $totalBooks; ?> book(s)</span>
        </div>
        <?php if (empty($books)): ?>
        <div class="text-center py-16 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <p class="text-base font-medium">No books found</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            <?php foreach ($books as $book): ?>
            <div class="book-card rounded-2xl overflow-hidden flex flex-col premium-card">
                <div class="flex items-center justify-center pt-6 pb-3">
                    <svg class="w-14 h-14 text-[#1e3a5f]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <div class="px-4 pb-4 flex flex-col flex-1">
                    <h3 class="text-sm font-semibold text-gray-900 leading-snug"><?php echo htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-xs text-gray-500 mt-0.5">by <?php echo htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="mt-2">
                        <span class="inline-block bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo htmlspecialchars($book['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex-1"></div>
                    <?php $avail = (int)($book['available'] ?? 0); ?>
                    <div class="flex items-center justify-between mt-3 pt-2 border-t border-gray-100 text-xs">
                        <span class="text-gray-600 font-medium">Qty: <?php echo (int)$book['quantity']; ?></span>
                        <?php if ($avail > 0): ?>
                        <span class="text-emerald-600 font-medium"><?php echo $avail; ?> available</span>
                        <?php else: ?>
                        <span class="bg-red-100 text-red-600 text-xs font-semibold px-2.5 py-0.5 rounded-full">Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($avail > 0 && $studentMemberId): ?>
                    <form method="POST" action="" class="mt-3">
                        <input type="hidden" name="action" value="borrow">
                        <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                        <button type="submit" class="w-full py-2 rounded-lg bg-[#1e3a5f] hover:bg-[#0b2545] text-white text-xs font-semibold shadow-sm transition" onclick="return confirm('Request to borrow this book?');">Request Borrow</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center gap-2 mt-8">
            <?php if ($page > 1): ?>
            <a href="?view=browse&page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $catSlug ? '&category=' . urlencode($catSlug) : ''; ?>" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">&larr; Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?view=browse&page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $catSlug ? '&category=' . urlencode($catSlug) : ''; ?>" class="px-4 py-2 rounded-xl text-sm font-medium transition <?php echo $i === $page ? 'bg-[#1e3a5f] text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?view=browse&page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $catSlug ? '&category=' . urlencode($catSlug) : ''; ?>" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">Next &rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<!-- ========== VIEW: My Borrowed Items ========== -->
<div id="view-borrowed" class="view-section hidden">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if (!empty($pendingRequests)): ?>
        <!-- Pending Requests -->
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Pending Approval</h2>
            <div class="space-y-3">
                <?php foreach ($pendingRequests as $pr): ?>
                <div class="rounded-2xl p-4 flex items-center justify-between gap-3 premium-card">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-amber-500/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm">
                                <?php echo htmlspecialchars($pr['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h4>
                            <p class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($pr['author'], ENT_QUOTES, 'UTF-8'); ?>
                                &middot; Requested: <?php echo $pr['request_date'] ? date('d M Y', strtotime($pr['request_date'])) : '--'; ?>
                            </p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30 shrink-0">
                        Pending Approval
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <h2 class="text-xl font-bold text-gray-900 mb-6">My Borrowed Items</h2>
        <?php if (empty($borrowings)): ?>
        <div class="text-center py-16 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <p class="text-base font-medium">No borrowed items</p>
            <p class="text-sm mt-1">Browse books and borrow your first one!</p>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($borrowings as $br): ?>
            <?php
            $isOverdue = false;
            $fineAmount = 0;
            if (!$br['actual_return_date'] && $br['expected_return_date']) {
                $due = new DateTime($br['expected_return_date']);
                $now = new DateTime();
                if ($now > $due) {
                    $isOverdue = true;
                    $overdueDays = (int)$due->diff($now)->days;
                    $fineAmount = $overdueDays * 5;
                }
            } elseif ($br['actual_return_date'] && (float)$br['fine_amount'] > 0) {
                $fineAmount = (float)$br['fine_amount'];
            }
            ?>
            <div class="rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 premium-card">
                <div>
                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($br['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($br['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 mt-1.5">
                        <span>Issued: <?php echo $br['issue_date'] ? date('d M Y', strtotime($br['issue_date'])) : '--'; ?></span>
                        <span>Due: <?php echo $br['expected_return_date'] ? date('d M Y', strtotime($br['expected_return_date'])) : '--'; ?></span>
                        <?php if ($br['actual_return_date']): ?>
                        <span class="text-emerald-600 font-medium">Returned: <?php echo date('d M Y', strtotime($br['actual_return_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0 flex-wrap">
                    <?php if ($br['actual_return_date']): ?>
                    <span class="text-emerald-600 text-sm font-semibold">Returned</span>
                    <?php elseif (trim($br['status']) === 'Return Pending'): ?>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">Return Pending Approval...</span>
                    <?php elseif ($isOverdue): ?>
                    <span class="text-red-600 text-sm font-semibold">Overdue (<?php echo $overdueDays; ?>d)</span>
                    <?php else: ?>
                    <span class="text-blue-600 text-sm font-semibold">Active</span>
                    <?php endif; ?>
                    <?php if ($fineAmount > 0): ?>
                    <span class="bg-red-50 text-red-600 text-xs font-bold px-3 py-1 rounded-full">Fine: Tk <?php echo number_format($fineAmount, 2); ?></span>
                    <?php endif; ?>
                    <?php if (!$br['actual_return_date'] && trim($br['status']) === 'issued'): ?>
                    <form method="POST" action="" class="inline">
                        <input type="hidden" name="action" value="return_request">
                        <input type="hidden" name="issue_id" value="<?php echo (int)$br['id']; ?>">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-orange-400/40 text-orange-300 hover:bg-orange-500/20 hover:border-orange-400 transition" onclick="return confirm('Return this book?');">Return Book</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>

<!-- ========== VIEW: Account Overview ========== -->
<div id="view-account" class="view-section hidden">
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Account Overview</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Profile Card -->
            <div class="rounded-2xl p-6 premium-card">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-14 h-14 rounded-full bg-[#1e3a5f]/10 flex items-center justify-center">
                        <svg class="w-7 h-7 text-[#1e3a5f]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($studentData['name'] ?? $studentName, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="text-sm text-gray-500">Student ID: <?php echo htmlspecialchars($studentData['student_id'] ?? $studentSid, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Department</span><span class="font-medium text-gray-900"><?php echo htmlspecialchars($studentData['department'] ?? '--', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Phone</span><span class="font-medium text-gray-900"><?php echo htmlspecialchars($studentData['phone'] ?? '--', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Joined</span><span class="font-medium text-gray-900"><?php echo isset($studentData['created_at']) ? date('d M Y', strtotime($studentData['created_at'])) : '--'; ?></span></div>
                </div>
            </div>

            <!-- Fines & Stats Card -->
            <div class="rounded-2xl p-6 premium-card">
                <h3 class="font-bold mb-4">Library Summary</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                        <span class="text-sm text-gray-600">Total Borrowed</span>
                        <span class="font-bold text-gray-900"><?php echo count($borrowings); ?></span>
                    </div>
                    <?php
                    $activeBorrows = 0;
                    foreach ($borrowings as $br) { if (!$br['actual_return_date']) $activeBorrows++; }
                    ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                        <span class="text-sm text-gray-600">Currently Borrowed</span>
                        <span class="font-bold text-gray-900"><?php echo $activeBorrows; ?></span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-xl">
                        <span class="text-sm text-red-600 font-medium">Total Outstanding Fines</span>
                        <span class="font-bold fine-highlight">Tk <?php echo number_format($totalFines, 2); ?></span>
                    </div>
                </div>
                <?php if ($totalFines > 0): ?>
                <p class="text-xs text-gray-500 mt-4 leading-relaxed">
                    Please visit the library to clear your fines. Overdue fines are charged at Tk 5 per day.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<!-- Policy Notice -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
    <div class="flex items-start gap-4 rounded-xl p-4 shadow-md" style="background: rgba(255, 193, 7, 0.08); border: 1px solid #ffc107;">
        <div class="shrink-0 mt-0.5" style="animation: pulse-warn 2s ease-in-out infinite;">
            <svg class="w-6 h-6" style="color: #ffca28;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div style="color: #ffca28; font-size: 15px; line-height: 1.6;">
            <span class="font-bold" style="font-size: 16px;">&#9888;&#65039; Important Library Policy:</span><br>
            Borrowed books must be returned within <strong>14 days</strong>. Failure to do so will result in a fine of <strong>Tk 10 per day</strong>.
        </div>
    </div>
</div>

<style>
@keyframes pulse-warn {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.08); }
}
</style>

<footer class="bg-[#0b2545] text-white/60 text-sm text-center py-6 mt-10">
    &copy; <?php echo date('Y'); ?> Gopalganj Science and Technology University Library
</footer>

<script>
function switchView(view) {
    var views = ['browse', 'borrowed', 'account'];
    views.forEach(function(v) {
        var el = document.getElementById('view-' + v);
        var nav = document.getElementById('nav-' + v);
        if (v === view) {
            el.classList.remove('hidden');
            nav.classList.add('active');
        } else {
            el.classList.add('hidden');
            nav.classList.remove('active');
        }
    });
    var url = new URL(window.location);
    url.searchParams.set('view', view);
    window.history.replaceState({}, '', url);
}

<?php
$defaultView = $_GET['view'] ?? 'browse';
if (!in_array($defaultView, ['browse', 'borrowed', 'account'])) $defaultView = 'browse';
echo "document.addEventListener('DOMContentLoaded', function() { switchView('$defaultView'); });";
?>
</script>

</body>
</html>