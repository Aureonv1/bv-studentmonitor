<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, username, full_name, email, password_hash FROM admins WHERE id = ? LIMIT 1');
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: ' . admin_url('logout'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? 'update_username');

        if ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Current password, new password, and confirmation are required.');
            }
            if (!password_verify($currentPassword, (string) ($admin['password_hash'] ?? ''))) {
                throw new RuntimeException('Current password is incorrect.');
            }
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }
            if (password_verify($newPassword, (string) ($admin['password_hash'] ?? ''))) {
                throw new RuntimeException('New password must be different from the current password.');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $updateStmt->execute([$passwordHash, $adminId]);
            $admin['password_hash'] = $passwordHash;
            $flash = ['type' => 'success', 'text' => 'Password changed successfully.'];
        } else {
            $newUsername = strtolower(trim((string) ($_POST['username'] ?? '')));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if ($newUsername === '') {
                throw new RuntimeException('Username is required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
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

            $updateStmt = $pdo->prepare('UPDATE admins SET username = ?, email = ? WHERE id = ?');
            $updateStmt->execute([$newUsername, $email !== '' ? $email : null, $adminId]);

            $admin['username'] = $newUsername;
            $admin['email'] = $email;
            refresh_admin_session($pdo);
            $flash = ['type' => 'success', 'text' => 'Account details updated.'];
        }
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
                <?php if (admin_can('backup_db')): ?><a href="backup_database.php" class="sb-link"><i class="fas fa-download"></i> Backup Database</a><?php endif; ?>
                <?php if (admin_can('maintenance_mode')): ?><a href="maintenance.php" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
                <a href="profile.php" class="sb-link active"><i class="fas fa-user-gear"></i> My Profile</a>
                <a href="../student_login.php" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout.php" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
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
                    <div class="panel-head"><h2><i class="fas fa-user-gear" style="color:var(--primary);"></i> Account Details</h2></div>
                    <div class="panel-body">
                        <form method="POST" class="form-grid">
                            <input type="hidden" name="action" value="update_username">
                            <div class="form-group">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars((string) $admin['username']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Security Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars((string) ($admin['email'] ?? '')) ?>" autocomplete="email" placeholder="you@example.com">
                            </div>
                            <div class="form-group" style="align-self:end;">
                                <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-floppy-disk"></i> Save Details</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="dash-panel reveal d2">
                    <div class="panel-head"><h2><i class="fas fa-key" style="color:var(--primary);"></i> Change Password</h2></div>
                    <div class="panel-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password" minlength="8" required>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8" required>
                                </div>
                                <div class="form-group" style="align-self:end;">
                                    <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-lock"></i> Change Password</button>
                                </div>
                            </div>
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
