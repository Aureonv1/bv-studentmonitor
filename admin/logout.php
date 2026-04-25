<?php
session_start();

unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_name'],
    $_SESSION['admin_permissions'],
    $_SESSION['admin_flash']
);

require_once '../config.php';
require_once 'admin_auth.php';

header('Location: ' . admin_url('login'));
exit;

