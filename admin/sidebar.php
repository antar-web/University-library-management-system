<?php
/**
 * Admin Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16">
            <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.811-1.094-.11-2.278-.037-3.213.493V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
        </svg>
        <span>GSTU LIBRARY</span>
    </div>
    <hr>
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="books.php" class="nav-link <?php echo in_array($currentPage, ['books.php', 'book-add.php', 'book-edit.php']) ? 'active' : ''; ?>">
                <i class="bi bi-book"></i> <span>Books</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="categories.php" class="nav-link <?php echo $currentPage === 'categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags"></i> <span>Categories</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="manage-departments.php" class="nav-link <?php echo $currentPage === 'manage-departments.php' ? 'active' : ''; ?>">
                <i class="bi bi-building"></i> <span>Departments</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="members.php" class="nav-link <?php echo in_array($currentPage, ['members.php', 'member-add.php', 'member-edit.php']) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> <span>Members</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="borrow-requests.php" class="nav-link <?php echo $currentPage === 'borrow-requests.php' ? 'active' : ''; ?>">
                <i class="bi bi-inbox"></i> <span>Borrow Requests</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="issue-book.php" class="nav-link <?php echo $currentPage === 'issue-book.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-arrow-right"></i> <span>Issue Book</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="issued-books.php" class="nav-link <?php echo $currentPage === 'issued-books.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-check"></i> <span>Issued Books</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="return-requests.php" class="nav-link <?php echo $currentPage === 'return-requests.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-return-left"></i> <span>Return Requests</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="return-book.php" class="nav-link <?php echo $currentPage === 'return-book.php' ? 'active' : ''; ?>">
                <i class="bi bi-journal-check"></i> <span>Process Return</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="returned-history.php" class="nav-link <?php echo $currentPage === 'returned-history.php' ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> <span>Returned History</span>
            </a>
        </li>
    </ul>
    <hr>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout-link">
            <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
        </a>
        <div class="admin-info">
            <i class="bi bi-person-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>
</div>
