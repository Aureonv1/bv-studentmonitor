<?php
session_start();
require_once '../config.php';
require_once '../maintenance_mode.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('maintenance_mode');

$flash = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $enabled = !empty($_POST['enabled']);
    $message = trim((string) ($_POST['message'] ?? ''));

    if (set_maintenance_state($enabled, $message)) {
        $flash = ['type' => 'success', 'text' => 'Maintenance mode settings updated.'];
    } else {
        $flash = ['type' => 'error', 'text' => 'Unable to save maintenance mode settings.'];
    }
}

$state = get_maintenance_state();
$lastUpdated = '-';
try {
    $stateFile = maintenance_mode_file();
    if (is_file($stateFile)) {
        $lastUpdated = date('M j, Y g:i A', (int) filemtime($stateFile));
    }
} catch (Throwable $e) {
    $lastUpdated = '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - BrightVision</title>
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
            <?php if (admin_can('backup_db')): ?><a href="backup_database" class="sb-link"><i class="fas fa-download"></i> Backup Database</a><?php endif; ?>
            <a href="maintenance" class="sb-link active"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a>
            <a href="profile" class="sb-link"><i class="fas fa-user-gear"></i> My Profile</a>
            <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
            <a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
        </nav>
        <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
    </aside>

    <div class="sb-overlay" id="sbOverlay"></div>

    <main class="admin-main">
        <div class="admin-topbar">
            <div class="topbar-left"><button class="sb-toggle" id="sbToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button><h1>Maintenance Mode</h1></div>
            <div class="topbar-meta">
                <span class="topbar-pill"><i class="fas fa-power-off"></i> <?= !empty($state['enabled']) ? 'Enabled' : 'Disabled' ?></span>
                <span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span>
            </div>
        </div>

        <div class="admin-body dashboard-stack">
            <?php if ($flash): ?>
                <div class="msg <?= $flash['type'] === 'error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;"><i class="fas <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($flash['text']) ?></div>
            <?php endif; ?>

            <section class="metrics-grid reveal d1">
                <article class="val-card">
                    <div class="icon ic-blue"><i class="fas fa-toggle-on"></i></div>
                    <div class="val-lbl">Current Status</div>
                    <div class="val-num"><?= !empty($state['enabled']) ? 'ON' : 'OFF' ?></div>
                </article>
                <article class="val-card">
                    <div class="icon ic-purple"><i class="fas fa-user-shield"></i></div>
                    <div class="val-lbl">Admin Access</div>
                    <div class="val-num">Active</div>
                </article>
                <article class="val-card">
                    <div class="icon ic-pink"><i class="fas fa-user-graduate"></i></div>
                    <div class="val-lbl">Student Portal</div>
                    <div class="val-num"><?= !empty($state['enabled']) ? 'Blocked' : 'Open' ?></div>
                </article>
                <article class="val-card">
                    <div class="icon ic-orange"><i class="fas fa-clock-rotate-left"></i></div>
                    <div class="val-lbl">Last Updated</div>
                    <div class="val-num" style="font-size:0.95rem;"><?= htmlspecialchars($lastUpdated) ?></div>
                </article>
            </section>

            <section class="dash-panel reveal d2">
                <div class="panel-head"><h2><i class="fas fa-screwdriver-wrench" style="color:var(--primary);"></i> Portal Availability</h2></div>
                <div class="panel-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="enabled">Enable Maintenance Mode</label>
                            <label style="display:flex;align-items:center;gap:0.5rem;">
                                <input id="enabled" type="checkbox" name="enabled" value="1" <?= !empty($state['enabled']) ? 'checked' : '' ?>>
                                <span>When enabled, public and student-facing pages show maintenance mode.</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="message">Maintenance Message</label>
                            <textarea id="message" name="message" class="form-control" rows="4" placeholder="Maintenance message..."><?= htmlspecialchars((string) ($state['message'] ?? '')) ?></textarea>
                        </div>

                        <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-floppy-disk"></i> Save Maintenance Settings</button>
                    </form>
                </div>
            </section>
        </div>
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


