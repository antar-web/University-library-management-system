<?php
/**
 * Utility functions for the Library Management System
 */

/**
 * Sanitize input strings
 */
function sanitize(string $data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to a given URL
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit();
}

/**
 * Display a formatted alert message (Bootstrap style)
 */
function showAlert(string $message, string $type = 'success'): string
{
    $types = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ];
    $class = $types[$type] ?? 'alert-info';
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
            . '</div>';
}

/**
 * Calculate fine for overdue books
 * Rule: 14 days free, then 10 BDT per day
 */
function calculateFine(string $issueDate, ?string $returnDate = null): float
{
    $issue = new DateTime($issueDate);
    $return = $returnDate ? new DateTime($returnDate) : new DateTime();
    $interval = $issue->diff($return);
    $days = (int) $interval->days;

    // Issue and return on same day counts as 1 day
    if ($days === 0 && $interval->invert === 0) {
        $days = 1;
    }

    $freeDays = 14;
    if ($days > $freeDays) {
        $lateDays = $days - $freeDays;
        return $lateDays * 10;
    }
    return 0.00;
}

/**
 * Format date for display
 */
function formatDate(?string $date, string $format = 'd M, Y'): string
{
    if (!$date) return '---';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Generate a unique member ID (LIB-XXXX)
 */
function generateMemberId(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM members");
    $row = $stmt->fetch();
    $nextId = ($row['max_id'] ?? 0) + 1;
    return 'LIB-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
}

/**
 * Get total count from a table
 */
function getCount(PDO $pdo, string $table, string $condition = '1=1'): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM $table WHERE $condition");
    $stmt->execute();
    return (int) $stmt->fetch()['total'];
}

/**
 * Pagination helper - returns [offset, limit, totalPages, currentPage]
 */
function paginate(int $totalRecords, int $limit = 10): array
{
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $totalPages = ceil($totalRecords / $limit);
    $offset = ($page - 1) * $limit;

    return [
        'offset'      => $offset,
        'limit'       => $limit,
        'totalPages'  => $totalPages,
        'currentPage' => $page,
    ];
}

/**
 * Render pagination links (Bootstrap)
 */
function renderPagination(int $currentPage, int $totalPages, string $url = ''): string
{
    if ($totalPages <= 1) return '';

    if (!$url) {
        $url = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
        $url .= (strpos($url, '?') === false ? '?' : '&');
    }

    $html = '<nav><ul class="pagination pagination-sm justify-content-center">';

    // Previous
    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevDisabled . '">';
    if ($currentPage > 1) {
        $html .= '<a class="page-link" href="' . $url . 'page=' . ($currentPage - 1) . '">Previous</a>';
    } else {
        $html .= '<span class="page-link">Previous</span>';
    }
    $html .= '</li>';

    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . $url . 'page=' . $i . '">' . $i . '</a>';
        $html .= '</li>';
    }

    // Next
    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextDisabled . '">';
    if ($currentPage < $totalPages) {
        $html .= '<a class="page-link" href="' . $url . 'page=' . ($currentPage + 1) . '">Next</a>';
    } else {
        $html .= '<span class="page-link">Next</span>';
    }
    $html .= '</li>';

    $html .= '</ul></nav>';
    return $html;
}
