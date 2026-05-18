<?php
require_once __DIR__ . '/session_bootstrap.php';

unset(
    $_SESSION['student_logged_in'],
    $_SESSION['student_id'],
    $_SESSION['student_account_id'],
    $_SESSION['student_username'],
    $_SESSION['student_name'],
    $_SESSION['student_class'],
    $_SESSION['student_year']
);

require_once 'student_auth.php';
header('Location: ' . student_url('student_login'));
exit;
