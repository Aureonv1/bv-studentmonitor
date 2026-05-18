<?php
require_once __DIR__ . '/../session_bootstrap.php';

unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email'],
    $_SESSION['admin_permissions'],
    $_SESSION['admin_flash'],
    $_SESSION['pending_admin_id'],
    $_SESSION['pending_admin_started_at'],
    $_SESSION['reset_admin_id']
);

require_once '../config.php';
require_once 'admin_auth.php';

header('Location: ' . admin_login_url());
exit;
