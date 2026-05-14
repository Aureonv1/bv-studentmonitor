<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('backup_db');

$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'download_backup') {
    try {
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

        echo "-- BrightVision Student Monitor SQL Backup\n";
        echo "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
        echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        echo "SET time_zone = \"+00:00\";\n\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $tableName = (string) $table;
            $safeTable = '`' . str_replace('`', '``', $tableName) . '`';

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $safeTable)->fetch(PDO::FETCH_ASSOC);
            $createSql = $createStmt['Create Table'] ?? '';

            echo "-- ----------------------------\n";
            echo "-- Table structure for {$tableName}\n";
            echo "-- ----------------------------\n";
            echo "DROP TABLE IF EXISTS {$safeTable};\n";
            echo $createSql . ";\n\n";

            $rows = $pdo->query('SELECT * FROM ' . $safeTable)->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo "-- ----------------------------\n";
                echo "-- Records for {$tableName}\n";
                echo "-- ----------------------------\n";

                $columns = array_keys($rows[0]);
                $columnSql = '`' . implode('`,`', array_map(static fn($c) => str_replace('`', '``', (string) $c), $columns)) . '`';

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        $value = $row[$column] ?? null;
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote((string) $value);
                        }
                    }
                    echo 'INSERT INTO ' . $safeTable . ' (' . $columnSql . ') VALUES (' . implode(',', $values) . ");\n";
                }
                echo "\n";
            }
        }
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
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
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
            <a href="dashboard" class="sb-header"><img src="../logo.png" alt="BrightVision" class="sb-logo"></a>
            <nav class="sb-nav">
                <div class="sb-label">Analytics</div>
                <a href="dashboard" class="sb-link"><i class="fas fa-chart-line"></i> Dashboard</a>
                <?php if (admin_can('view_analytics')): ?><a href="class_analytics" class="sb-link"><i class="fas fa-chart-column"></i> Class Analytics</a><?php endif; ?>

                <div class="sb-label">Management</div>
                <?php if (admin_can('manage_students')): ?>
                    <a href="manage_students" class="sb-link"><i class="fas fa-database"></i> Data Manager</a>
                    <a href="student_credentials" class="sb-link"><i class="fas fa-id-card"></i> Student Credentials</a>
                    <a href="manage_academics" class="sb-link"><i class="fas fa-graduation-cap"></i> Academics</a>
                    <a href="export_student_ids" class="sb-link"><i class="fas fa-address-card"></i> Export Student IDs</a>
                <?php endif; ?>
                <?php if (admin_can('manage_marks')): ?><a href="manage_marks" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
                <?php if (admin_can('import_csv')): ?><a href="import_csv" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a><?php endif; ?>

                <div class="sb-label">System</div>
                <?php if (admin_can('manage_admins')): ?><a href="manage_admins" class="sb-link"><i class="fas fa-user-shield"></i> Manage Admins</a><?php endif; ?>
                <?php if (admin_can('manage_site_settings')): ?><a href="site_settings" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a><?php endif; ?>
                <a href="backup_database" class="sb-link active"><i class="fas fa-download"></i> Backup Database</a>
                <?php if (admin_can('maintenance_mode')): ?><a href="maintenance" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
                <a href="profile" class="sb-link"><i class="fas fa-user-gear"></i> My Profile</a>
                <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
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
                    <div class="panel-head"><h2><i class="fas fa-database" style="color:var(--primary);"></i> Download Full SQL Backup</h2></div>
                    <div class="panel-body">
                        <p class="maintenance-note" style="margin-bottom:0.9rem;">This exports all schema and data from the current BrightVision database in one `.sql` file.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="download_backup">
                            <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-download"></i> Download Backup</button>
                        </form>
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


