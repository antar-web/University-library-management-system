<?php
/**
 * Authentication Check
 * Verifies admin is logged in; redirects to login page if not.
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    redirect('../index.php');
}
