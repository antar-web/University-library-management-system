<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System | <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
<!-- Top Navigation Bar -->
<div class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-brand"><i class="bi bi-book-fill"></i> GSTU LIBRARY</span>
    </div>
    <div class="topbar-right">
        <span class="admin-badge">
            <i class="bi bi-person-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
        </span>
        <a href="logout.php" class="topbar-logout">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</div>

<!-- Dashboard Wrapper — flex parent for sidebar + main-content -->
<div class="dashboard-wrapper">
