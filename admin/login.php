<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

if (isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

redirect('../login.php?type=admin');
