<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);

$flash = null;
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, username, full_name FROM admins WHERE id = ? LIMIT 1');
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: ' . admin_url('logout'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $newUsername = strtolower(trim((string) ($_POST['username'] ?? '')));
        if ($newUsername === '') {
            throw new RuntimeException('Username is required.');
        }
        if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $newUsername)) {
            throw new RuntimeException('Use 3-50 letters, numbers, dots, dashes, or underscores.');
        }
        if (is_super_admin_username((string) $admin['username']) && !is_super_admin_username($newUsername)) {
            throw new RuntimeException('The super admin username must stay rnsdev.');
        }

        $checkStmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? AND id <> ? LIMIT 1');
        $checkStmt->execute([$newUsername, $adminId]);
        if ((int) $checkStmt->fetchColumn() > 0) {
            throw new RuntimeException('That username is already taken.');
        }

        $updateStmt = $pdo->prepare('UPDATE admins SET username = ? WHERE id = ?');
        $updateStmt->execute([$newUsername, $adminId]);

        $admin['username'] = $newUsername;
        refresh_admin_session($pdo);
        $flash = ['type' => 'success', 'text' => 'Username updated.'];
    } catch (Throwable $e) {
        $flash = ['type' => 'error', 'text' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                <?php if (admin_can('maintenance_mode')): ?><a href="maintenance" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
                <a href="profile" class="sb-link active"><i class="fas fa-user-gear"></i> My Profile</a>
                <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
            </nav>
            <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
        </aside>

        <div class="sb-overlay" id="sbOverlay"></div>

        <main class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-left"><button class="sb-toggle" id="sbToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button><h1>My Profile</h1></div>
                <div class="topbar-meta"><span class="topbar-pill"><i class="fas fa-user"></i> @<?= htmlspecialchars((string) $admin['username']) ?></span></div>
            </div>

            <div class="admin-body dashboard-stack">
                <?php if ($flash): ?>
                    <div class="msg <?= $flash['type'] === 'error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;">
                        <i class="fas <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i>
                        <?= htmlspecialchars($flash['text']) ?>
                    </div>
                <?php endif; ?>

                <section class="dash-panel reveal d1">
                    <div class="panel-head"><h2><i class="fas fa-user-gear" style="color:var(--primary);"></i> Account Username</h2></div>
                    <div class="panel-body">
                        <form method="POST" class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars((string) $admin['username']) ?>" required>
                            </div>
                            <div class="form-group" style="align-self:end;">
                                <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-floppy-disk"></i> Save Username</button>
                            </div>
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
