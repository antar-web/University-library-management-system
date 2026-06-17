<?php
/**
 * Admin Dashboard
 * Strict authentication required. Redirects to login.php if not logged in.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getConnection();

// Dashboard statistics
$totalBooks       = getCount($pdo, 'books');
$totalMembers     = getCount($pdo, 'members');
$booksIssued      = getCount($pdo, 'issued_books', "status = 'issued'");
$booksReturned    = getCount($pdo, 'issued_books', "status = 'returned'");
$pendingBorrows   = getCount($pdo, 'issued_books', "status = 'Pending'");
$pendingReturns   = getCount($pdo, 'issued_books', "status = 'Return Pending'");

$stmtFine = $pdo->query("SELECT COALESCE(SUM(fine_amount), 0) as total FROM issued_books WHERE status = 'returned'");
$totalFine = $stmtFine->fetch()['total'];

$stmtLow = $pdo->query("SELECT COUNT(*) as total FROM books WHERE quantity = 0 OR available = 0");
$lowStock = $stmtLow->fetch()['total'];

$stmtRecent = $pdo->query("
    SELECT ib.*, b.title as book_title, m.name as member_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN members m ON ib.member_id = m.id
    ORDER BY ib.id DESC
    LIMIT 8
");
$recentIssues = $stmtRecent->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<style>
/* Stat Card Layout & Accents */
.stat-link { display: block; text-decoration: none; color: inherit; height: 100%; }
.stat-link .stat-card { height: 100%; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.stat-books { border-top-color: #3a86ff !important; }
.stat-members { border-top-color: #2ec4b6 !important; }
.stat-issued { border-top-color: #ff9f1c !important; }
.stat-returned { border-top-color: #a78bfa !important; }
.stat-fine { border-top-color: #ff6b6b !important; }
.stat-fine:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(255, 107, 107, 0.25); }
.stat-lowstock { border-top-color: #f97316 !important; }
.stat-hover-books:hover .stat-card { box-shadow: 0 8px 30px rgba(58, 134, 255, 0.25); }
.stat-hover-members:hover .stat-card { box-shadow: 0 8px 30px rgba(46, 196, 182, 0.25); }
.stat-hover-issued:hover .stat-card { box-shadow: 0 8px 30px rgba(255, 159, 28, 0.25); }
.stat-hover-returned:hover .stat-card { box-shadow: 0 8px 30px rgba(167, 139, 250, 0.25); }
.stat-hover-fine:hover .stat-card { box-shadow: 0 8px 30px rgba(255, 107, 107, 0.25); }
.stat-hover-lowstock:hover .stat-card { box-shadow: 0 8px 30px rgba(249, 115, 22, 0.25); }
</style>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Dashboard</h2>
        <span class="text-muted"><?php echo date('l, d F Y'); ?></span>
    </div>

    <!-- Clickable Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <a href="books.php" class="stat-link stat-hover-books">
                <div class="stat-card stat-books">
                    <div class="stat-icon"><i class="bi bi-book"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $totalBooks; ?></h3>
                        <p>Total Books</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="members.php" class="stat-link stat-hover-members">
                <div class="stat-card stat-members">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="borrow-requests.php" class="stat-link stat-hover-issued">
                <div class="stat-card stat-issued">
                    <div class="stat-icon"><i class="bi bi-inbox"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $pendingBorrows; ?></h3>
                        <p>Borrow Requests</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="issued-books.php?status=issued" class="stat-link stat-hover-issued">
                <div class="stat-card stat-issued">
                    <div class="stat-icon"><i class="bi bi-box-arrow-right"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $booksIssued; ?></h3>
                        <p>Currently Issued</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="return-requests.php" class="stat-link stat-hover-returned">
                <div class="stat-card stat-returned">
                    <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $pendingReturns; ?></h3>
                        <p>Return Requests</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="issued-books.php?status=returned" class="stat-link stat-hover-returned">
                <div class="stat-card stat-returned">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $booksReturned; ?></h3>
                        <p>Returned</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card stat-fine" style="height:100%">
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-details">
                    <h3><?php echo number_format((float)$totalFine, 2); ?></h3>
                    <p>Fine Collected (BDT)</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="books.php" class="stat-link stat-hover-lowstock">
                <div class="stat-card stat-lowstock">
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $lowStock; ?></h3>
                        <p>Out of Stock</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Activity -->
        <div class="col-lg-8">
            <div class="card dashboard-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Book Issues</h5>
                    <a href="issued-books.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Member</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentIssues)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No books have been issued yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentIssues as $row): ?>
                                        <tr>
                                            <td class="fw-medium"><?php echo htmlspecialchars($row['book_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($row['member_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo formatDate($row['issue_date']); ?></td>
                                            <td><?php echo formatDate($row['expected_return_date']); ?></td>
                                            <td>
                                                <?php if ($row['status'] === 'Pending'): ?>
                                                    <span class="badge bg-primary">Pending</span>
                                                <?php elseif ($row['status'] === 'issued'): ?>
                                                    <span class="badge bg-warning text-dark">Issued</span>
                                                <?php elseif ($row['status'] === 'Return Pending'): ?>
                                                    <span class="badge bg-info text-dark">Return Pending</span>
                                                <?php elseif ($row['status'] === 'Rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Returned</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="col-lg-4 d-flex flex-column gap-4">
            <!-- Quick Actions -->
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="borrow-requests.php" class="btn btn-primary"><i class="bi bi-inbox"></i> Borrow Requests</a>
                        <a href="return-requests.php" class="btn btn-info text-dark"><i class="bi bi-arrow-return-left"></i> Return Requests</a>
                        <a href="book-add.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Add New Book</a>
                        <a href="member-add.php" class="btn btn-warning text-dark"><i class="bi bi-person-plus"></i> Add New Member</a>
                        <a href="issue-book.php" class="btn btn-secondary"><i class="bi bi-box-arrow-right"></i> Issue a Book</a>
                    </div>
                </div>
            </div>

            <!-- Categories Overview -->
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Categories Overview</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Category</th><th>Books</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmtCat = $pdo->query("
                                    SELECT c.name, COUNT(b.id) as total
                                    FROM categories c
                                    LEFT JOIN books b ON c.id = b.category_id
                                    GROUP BY c.id, c.name
                                    ORDER BY total DESC
                                    LIMIT 6
                                ");
                                while ($cat = $stmtCat->fetch()):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $cat['total']; ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================================
   DASHBOARD LAYOUT STABILISATION
   Ensures sidebar + main-content never collapse.
   ============================================================ */

/* Force sidebar visibility */
.sidebar {
    display: flex !important;
    width: 250px !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;
    z-index: 100 !important;
}

/* Main content clears the sidebar + topbar */
.main-content {
    margin-left: 250px !important;
    padding: 84px 32px 24px !important;
    min-height: 100vh !important;
}

/* Dashboard wrapper is the flex parent */
.dashboard-wrapper {
    display: flex !important;
    min-height: 100vh !important;
}

/* Stat cards row — proper spacing */
.main-content .row.g-3 {
    margin-left: 0 !important;
    margin-right: 0 !important;
}

/* Force white text on page header elements */
.main-content .d-flex .page-title {
    color: #ffffff !important;
    backdrop-filter: none !important;
    filter: none !important;
}
.main-content .d-flex .text-muted {
    color: rgba(255, 255, 255, 0.5) !important;
}
</style>
<?php require_once __DIR__ . '/footer.php'; ?>
