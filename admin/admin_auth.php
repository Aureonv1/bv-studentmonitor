<?php

if (!function_exists('admin_base_path')) {
    function admin_base_path(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('#/(admin(?:/.*)?|index(?:\.php)?|student_login(?:\.php)?|student_profile(?:\.php)?|student_logout(?:\.php)?)$#i', '', $script);
        if ($base === null) {
            $base = '';
        }
        return rtrim($base, '/');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        $base = admin_base_path();
        $path = trim($path, '/');
        if ($path === '') {
            return $base . '/admin';
        }
        return $base . '/admin/' . $path;
    }
}

if (!function_exists('admin_login_url')) {
    function admin_login_url(): string
    {
        $base = admin_base_path();
        return ($base !== '' ? $base : '') . '/student_login';
    }
}

if (!defined('SUPER_ADMIN_USERNAME')) {
    define('SUPER_ADMIN_USERNAME', 'rnsdev');
}

if (!function_exists('is_super_admin_username')) {
    function is_super_admin_username(string $username): bool
    {
        return strtolower(trim($username)) === SUPER_ADMIN_USERNAME;
    }
}

if (!function_exists('current_admin_is_super_admin')) {
    function current_admin_is_super_admin(): bool
    {
        return is_super_admin_username((string) ($_SESSION['admin_username'] ?? ''));
    }
}

if (!function_exists('admin_permissions_from_row')) {
    function admin_permissions_from_row(array $row): array
    {
        if (is_super_admin_username((string) ($row['username'] ?? ''))) {
            return [
                'manage_students' => true,
                'manage_marks' => true,
                'import_csv' => true,
                'backup_db' => true,
                'maintenance_mode' => true,
                'manage_admins' => true,
                'manage_site_settings' => true,
                'view_analytics' => true,
            ];
        }

        return [
            'manage_students' => !empty($row['can_manage_students']),
            'manage_marks' => !empty($row['can_manage_marks']),
            'import_csv' => !empty($row['can_import_csv']),
            'backup_db' => !empty($row['can_backup_db']),
            'maintenance_mode' => !empty($row['can_maintenance_mode']),
            'manage_admins' => !empty($row['can_manage_admins']),
            'manage_site_settings' => !empty($row['can_manage_site_settings']),
            'view_analytics' => array_key_exists('can_view_analytics', $row)
                ? !empty($row['can_view_analytics'])
                : !empty($row['can_manage_marks']),
        ];
    }
}

if (!function_exists('set_admin_session_state')) {
    function set_admin_session_state(array $adminRow): void
    {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int) ($adminRow['id'] ?? 0);
        $_SESSION['admin_username'] = (string) ($adminRow['username'] ?? '');
        $_SESSION['admin_name'] = (string) ($adminRow['full_name'] ?? 'Administrator');
        $_SESSION['admin_email'] = (string) ($adminRow['email'] ?? '');
        $_SESSION['admin_permissions'] = admin_permissions_from_row($adminRow);
    }
}

if (!function_exists('refresh_admin_session')) {
    function refresh_admin_session(PDO $pdo): bool
    {
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        if (empty($_SESSION['admin_logged_in']) || $adminId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                full_name,
                email,
                is_active,
                can_manage_students,
                can_manage_marks,
                can_import_csv,
                can_backup_db,
                can_maintenance_mode,
                can_manage_admins,
                can_manage_site_settings,
                can_view_analytics
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['is_active'])) {
            return false;
        }

        set_admin_session_state($row);
        return true;
    }
}

if (!function_exists('require_admin_login')) {
    function require_admin_login(PDO $pdo): void
    {
        if (!refresh_admin_session($pdo)) {
            unset(
                $_SESSION['admin_logged_in'],
                $_SESSION['admin_id'],
                $_SESSION['admin_username'],
                $_SESSION['admin_name'],
                $_SESSION['admin_email'],
                $_SESSION['admin_permissions']
            );
            header('Location: ' . admin_login_url());
            exit;
        }
    }
}

if (!function_exists('admin_permissions')) {
    function admin_permissions(): array
    {
        $permissions = $_SESSION['admin_permissions'] ?? [];
        return is_array($permissions) ? $permissions : [];
    }
}

if (!function_exists('admin_can')) {
    function admin_can(string $permission): bool
    {
        $permissions = admin_permissions();
        return !empty($permissions[$permission]);
    }
}

if (!function_exists('admin_display_name')) {
    function admin_display_name(): string
    {
        $name = trim((string) ($_SESSION['admin_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $username = trim((string) ($_SESSION['admin_username'] ?? ''));
        return $username !== '' ? $username : 'Administrator';
    }
}

if (!function_exists('require_admin_permission')) {
    function require_admin_permission(string $permission, string $fallback = 'dashboard.php'): void
    {
        if (admin_can($permission)) {
            return;
        }
        $_SESSION['admin_flash'] = [
            'type' => 'error',
            'message' => 'You do not have permission to access this area.'
        ];
        header('Location: ' . $fallback);
        exit;
    }
}
