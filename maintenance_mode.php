<?php

if (!function_exists('maintenance_mode_file')) {
    function maintenance_mode_file(): string
    {
        return __DIR__ . '/maintenance_mode.json';
    }
}

if (!function_exists('maintenance_default_message')) {
    function maintenance_default_message(): string
    {
        return 'The student portal is temporarily under maintenance. Please check back soon.';
    }
}

if (!function_exists('get_maintenance_state')) {
    function get_maintenance_state(): array
    {
        $default = [
            'enabled' => false,
            'message' => maintenance_default_message()
        ];

        $file = maintenance_mode_file();
        if (!is_file($file)) {
            return $default;
        }

        $content = @file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return $default;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return $default;
        }

        $enabled = !empty($decoded['enabled']);
        $message = trim((string)($decoded['message'] ?? maintenance_default_message()));
        if ($message === '') {
            $message = maintenance_default_message();
        }

        return [
            'enabled' => $enabled,
            'message' => $message
        ];
    }
}

if (!function_exists('is_maintenance_enabled')) {
    function is_maintenance_enabled(): bool
    {
        $state = get_maintenance_state();
        return !empty($state['enabled']);
    }
}

if (!function_exists('set_maintenance_state')) {
    function set_maintenance_state(bool $enabled, string $message = ''): bool
    {
        $data = [
            'enabled' => $enabled,
            'message' => trim($message) !== '' ? trim($message) : maintenance_default_message(),
            'updated_at' => date('c')
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents(maintenance_mode_file(), $json, LOCK_EX) !== false;
    }
}
