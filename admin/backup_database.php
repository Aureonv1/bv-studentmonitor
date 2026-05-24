<?php
require_once __DIR__ . '/../session_bootstrap.php';
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('backup_db');

$flash = null;

if (!function_exists('backup_identifier')) {
    function backup_identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('backup_sql_value')) {
    function backup_sql_value(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return $pdo->quote((string) $value);
    }
}

if (!function_exists('backup_order_tables')) {
    function backup_order_tables(array $tables): array
    {
        $preferredOrder = [
            'admins',
            'site_settings',
            'app_meta',
            'academic_years',
            'classes',
            'exams',
            'subjects',
            'students',
            'student_code_counter',
            'student_accounts',
            'marks',
            'imports_log',
            'database_backups',
            'admin_security_codes'
        ];
        $orderIndex = array_flip($preferredOrder);
        usort($tables, static function (array $a, array $b) use ($orderIndex): int {
            $tableA = (string) ($a[0] ?? '');
            $tableB = (string) ($b[0] ?? '');
            $rankA = $orderIndex[$tableA] ?? PHP_INT_MAX;
            $rankB = $orderIndex[$tableB] ?? PHP_INT_MAX;

            if ($rankA === $rankB) {
                return strnatcasecmp($tableA, $tableB);
            }

            return $rankA <=> $rankB;
        });

        return $tables;
    }
}

if (!function_exists('stream_table_rows')) {
    function stream_table_rows(PDO $pdo, string $tableName, string $headerPrefix = 'Records for'): void
    {
        $safeTable = backup_identifier($tableName);
        $rowStmt = $pdo->query('SELECT * FROM ' . $safeTable);
        $columns = [];
        for ($i = 0; $i < $rowStmt->columnCount(); $i++) {
            $meta = $rowStmt->getColumnMeta($i);
            $columns[] = (string) ($meta['name'] ?? ('column_' . $i));
        }

        if (empty($columns)) {
            return;
        }

        $wroteHeader = false;
        $columnSql = implode(', ', array_map('backup_identifier', $columns));
        while ($row = $rowStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$wroteHeader) {
                echo "-- ----------------------------\n";
                echo "-- {$headerPrefix} {$tableName}\n";
                echo "-- ----------------------------\n";
                $wroteHeader = true;
            }

            $values = [];
            foreach ($columns as $column) {
                $values[] = backup_sql_value($pdo, $row[$column] ?? null);
            }
            echo 'INSERT INTO ' . $safeTable . ' (' . $columnSql . ') VALUES (' . implode(', ', $values) . ");\n";
        }

        if ($wroteHeader) {
            echo "\n";
        }
    }
}

if (!function_exists('stream_current_data_section')) {
    function stream_current_data_section(PDO $pdo): void
    {
        $tables = backup_order_tables($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM));
        $tableNames = array_values(array_filter(array_map(static fn(array $table): string => (string) ($table[0] ?? ''), $tables)));

        echo "\n\n-- ============================================================\n";
        echo "-- Current live data snapshot\n";
        echo "-- This section replaces installer seed rows with the data that\n";
        echo "-- existed when the backup was downloaded.\n";
        echo "-- ============================================================\n\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach (array_reverse($tableNames) as $tableName) {
            echo 'DELETE FROM ' . backup_identifier($tableName) . ";\n";
        }
        echo "\n";

        foreach ($tableNames as $tableName) {
            stream_table_rows($pdo, $tableName, 'Current records for');
        }

        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    }
}

if (!function_exists('stream_database_backup')) {
    function stream_database_backup(PDO $pdo, string $databaseName): void
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $safeDatabase = backup_identifier($databaseName);
        $tables = backup_order_tables($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM));
        $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);

        echo "-- BrightVision Student Monitor SQL Backup\n";
        echo "-- Database: {$databaseName}\n";
        echo "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
        echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        echo "SET time_zone = \"+00:00\";\n";
        echo "SET NAMES utf8mb4;\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        echo "CREATE DATABASE IF NOT EXISTS {$safeDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
        echo "USE {$safeDatabase};\n\n";

        foreach ($tables as $tableInfo) {
            $tableName = (string) ($tableInfo[0] ?? '');
            if ($tableName === '') {
                continue;
            }

            $safeTable = backup_identifier($tableName);
            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $safeTable)->fetch(PDO::FETCH_ASSOC);
            $createSql = (string) ($createStmt['Create Table'] ?? '');

            echo "-- ----------------------------\n";
            echo "-- Table structure for {$tableName}\n";
            echo "-- ----------------------------\n";
            echo "DROP TABLE IF EXISTS {$safeTable};\n";
            echo $createSql . ";\n\n";

            stream_table_rows($pdo, $tableName);
        }

        if (!empty($views)) {
            foreach ($views as $viewInfo) {
                $viewName = (string) ($viewInfo[0] ?? '');
                if ($viewName === '') {
                    continue;
                }

                $safeView = backup_identifier($viewName);
                $createStmt = $pdo->query('SHOW CREATE VIEW ' . $safeView)->fetch(PDO::FETCH_ASSOC);
                $createSql = (string) ($createStmt['Create View'] ?? '');
                if ($createSql === '') {
                    continue;
                }

                echo "-- ----------------------------\n";
                echo "-- View structure for {$viewName}\n";
                echo "-- ----------------------------\n";
                echo "DROP VIEW IF EXISTS {$safeView};\n";
                echo $createSql . ";\n\n";
            }
        }

        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'download_original_schema') {
    try {
        $schemaPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database.sql';
        if (!is_file($schemaPath) || !is_readable($schemaPath)) {
            throw new RuntimeException('The original database.sql file could not be found.');
        }

        $filename = 'brightvision_original_with_data_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($schemaPath);
        stream_current_data_section($pdo);
        exit;
    } catch (Throwable $e) {
        $flash = [
            'type' => 'error',
            'text' => 'Original database download failed: ' . $e->getMessage()
        ];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'download_backup') {
    try {
        global $db_name;

        $filename = 'brightvision_backup_' . date('Ymd_His') . '.sql';
        try {
            $adminId = (int) ($_SESSION['admin_id'] ?? 0);
            $logStmt = $pdo->prepare("INSERT INTO database_backups (file_name, created_by) VALUES (?, ?)");
            $logStmt->execute([$filename, $adminId > 0 ? $adminId : null]);
        } catch (Throwable $e) {
            // Backup file download should still proceed even if logging fails.
        }
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        stream_database_backup($pdo, (string) $db_name);
        exit;
    } catch (Throwable $e) {
        $flash = [
            'type' => 'error',
            'text' => 'Backup generation failed: ' . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Database - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png?v=20260424">
    <link rel="shortcut icon" href="../icon.png?v=20260424">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="bg-mesh"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>

    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <a href="dashboard.php" class="sb-header"><img src="../logo.png" alt="BrightVision" class="sb-logo"></a>
            <nav class="sb-nav">
                <div class="sb-label">Analytics</div>
                <a href="dashboard.php" class="sb-link"><i class="fas fa-chart-line"></i> Dashboard</a>
                <?php if (admin_can('view_analytics')): ?><a href="class_analytics.php" class="sb-link"><i class="fas fa-chart-column"></i> Class Analytics</a><?php endif; ?>

                <div class="sb-label">Management</div>
                <?php if (admin_can('manage_students')): ?>
                    <a href="manage_students.php" class="sb-link"><i class="fas fa-database"></i> Data Manager</a>
                    <a href="student_credentials.php" class="sb-link"><i class="fas fa-id-card"></i> Student Credentials</a>
                    <a href="manage_academics.php" class="sb-link"><i class="fas fa-graduation-cap"></i> Academics</a>
                    <a href="export_student_ids.php" class="sb-link"><i class="fas fa-address-card"></i> Export Student IDs</a>
                <?php endif; ?>
                <?php if (admin_can('manage_marks')): ?><a href="manage_marks.php" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
                <?php if (admin_can('import_csv')): ?><a href="import_csv.php" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a><?php endif; ?>

                <div class="sb-label">System</div>
                <?php if (admin_can('manage_admins')): ?><a href="manage_admins.php" class="sb-link"><i class="fas fa-user-shield"></i> Manage Admins</a><?php endif; ?>
                <?php if (admin_can('manage_site_settings')): ?><a href="site_settings.php" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a><?php endif; ?>
                <a href="backup_database.php" class="sb-link active"><i class="fas fa-download"></i> Backup Database</a>
                <?php if (admin_can('maintenance_mode')): ?><a href="maintenance.php" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
                <a href="profile.php" class="sb-link"><i class="fas fa-user-gear"></i> My Profile</a>
                <a href="../student_login.php" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout.php" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
            </nav>
            <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
        </aside>

        <div class="sb-overlay" id="sbOverlay"></div>

        <main class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-left"><button class="sb-toggle" id="sbToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button><h1>Database Backup</h1></div>
                <div class="topbar-meta"><span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span></div>
            </div>

            <div class="admin-body dashboard-stack">
                <?php if ($flash): ?>
                    <div class="msg msg-error" style="display:flex;"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($flash['text']) ?></div>
                <?php endif; ?>

                <section class="dash-panel reveal d1">
                    <div class="panel-head"><h2><i class="fas fa-database" style="color:var(--primary);"></i> Download Database SQL</h2></div>
                    <div class="panel-body">
                        <p class="maintenance-note" style="margin-bottom:0.9rem;">Both options include the current database data. The installer-style option keeps the original database.sql setup script and appends a live data snapshot.</p>
                        <div class="backup-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="download_backup">
                                <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-download"></i> Current Database Backup</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="download_original_schema">
                                <button type="submit" class="notion-btn notion-btn-ghost"><i class="fas fa-file-code"></i> Original Style + Data</button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
            <?php render_admin_footer($pdo); ?>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sbOverlay = document.getElementById('sbOverlay');
        const sbToggle = document.getElementById('sbToggle');
        sbToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); sbOverlay.classList.toggle('show'); });
        sbOverlay?.addEventListener('click', () => { sidebar.classList.remove('open'); sbOverlay.classList.remove('show'); });
    </script>
</body>
</html>


