<?php
/**
 * Admin Logout
 * Destroys session and redirects to the public homepage.
 */

require_once __DIR__ . '/../includes/functions.php';

session_start();
$_SESSION = [];

// Expire the session cookie on the client side
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 86400,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
);

session_destroy();
redirect('../index.php');
