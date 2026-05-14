<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('student_base_path')) {
    function student_base_path(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('#/(admin(?:/.*)?|index(?:\.php)?|student_login(?:\.php)?|student_profile(?:\.php)?|student_logout(?:\.php)?|search_student(?:\.php)?|suggest_students(?:\.php)?)$#i', '', $script);
        if (!is_string($base)) {
            return '';
        }
        return rtrim($base, '/');
    }
}

if (!function_exists('student_url')) {
    function student_url(string $path = ''): string
    {
        $base = student_base_path();
        $path = ltrim($path, '/');

        if ($path === '') {
            return ($base !== '' ? $base : '') . '/';
        }

        if (!str_contains($path, '.') && !str_ends_with($path, '/')) {
            $path .= '.php';
        }

        return ($base !== '' ? $base : '') . '/' . $path;
    }
}

if (!function_exists('set_student_session_state')) {
    function set_student_session_state(array $studentRow): void
    {
        $_SESSION['student_logged_in'] = true;
        $_SESSION['student_id'] = (int) $studentRow['student_id'];
        $_SESSION['student_account_id'] = (int) $studentRow['account_id'];
        $_SESSION['student_username'] = (string) $studentRow['username'];
        $_SESSION['student_name'] = (string) $studentRow['name'];
        $_SESSION['student_class'] = (string) ($studentRow['class_name'] ?? '');
        $_SESSION['student_year'] = (string) ($studentRow['year_name'] ?? '');
    }
}

if (!function_exists('refresh_student_session')) {
    function refresh_student_session(PDO $pdo): bool
    {
        $studentId = (int) ($_SESSION['student_id'] ?? 0);
        $accountId = (int) ($_SESSION['student_account_id'] ?? 0);

        if (empty($_SESSION['student_logged_in']) || $studentId <= 0 || $accountId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT
                sa.id AS account_id,
                sa.student_id,
                sa.username,
                sa.is_active,
                s.name,
                c.class_name,
                y.year_name
            FROM student_accounts sa
            JOIN students s ON s.id = sa.student_id
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN academic_years y ON y.id = s.academic_year_id
            WHERE sa.id = ? AND sa.student_id = ?
            LIMIT 1
        ");
        $stmt->execute([$accountId, $studentId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['is_active'])) {
            return false;
        }

        set_student_session_state($row);
        return true;
    }
}

if (!function_exists('require_student_login')) {
    function require_student_login(PDO $pdo): void
    {
        if (!refresh_student_session($pdo)) {
            unset(
                $_SESSION['student_logged_in'],
                $_SESSION['student_id'],
                $_SESSION['student_account_id'],
                $_SESSION['student_username'],
                $_SESSION['student_name'],
                $_SESSION['student_class'],
                $_SESSION['student_year']
            );
            header('Location: ' . student_url('student_login'));
            exit;
        }
    }
}

if (!function_exists('student_display_name')) {
    function student_display_name(): string
    {
        $name = trim((string) ($_SESSION['student_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string) ($_SESSION['student_username'] ?? 'Student');
    }
}
