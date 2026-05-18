<?php
require_once __DIR__ . '/../session_bootstrap.php';
require_once '../config.php';
require_once 'admin_auth.php';

if (!empty($_SESSION['admin_logged_in']) && refresh_admin_session($pdo)) {
    header('Location: ' . admin_url('dashboard'));
    exit;
}

header('Location: ' . admin_login_url());
exit;
