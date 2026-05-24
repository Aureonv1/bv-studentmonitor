<?php
require_once __DIR__ . '/compatibility.php';

$session_path = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'sessions';

if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_handler', 'files');
    ini_set('session.save_path', $session_path);
    session_start();
}
