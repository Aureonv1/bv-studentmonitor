<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_admins');

$setFlash = static function (string $type, string $text): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'text' => $text];
};

$allPermissions = [
    'can_manage_students' => 'Manage Students',
    'can_manage_marks' => 'Manage Marks',
    'can_import_csv' => 'Import CSV',
    'can_backup_db' => 'Backup Database',
    'can_maintenance_mode' => 'Maintenance Mode',
    'can_manage_admins' => 'Manage Admins',
    'can_manage_site_settings' => 'Site Settings',
    'can_view_analytics' => 'View Analytics'
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add_admin') {
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                throw new RuntimeException('Username and password are required.');
            }
            if (strlen($password) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }

            $checkStmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
            $checkStmt->execute([$username]);
            if ((int) $checkStmt->fetchColumn() > 0) {
                throw new RuntimeException('Username already exists.');
            }

            $permissionValues = [];
            foreach (array_keys($allPermissions) as $column) {
                $permissionValues[$column] = !empty($_POST[$column]) ? 1 : 0;
            }

            $stmt = $pdo->prepare("
                INSERT INTO admins (
                    username,
                    full_name,
                    password_hash,
                    is_active,
                    can_manage_students,
                    can_manage_marks,
                    can_import_csv,
                    can_backup_db,
                    can_maintenance_mode,
                    can_manage_admins,
                    can_manage_site_settings,
                    can_view_analytics
                ) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $username,
                $fullName !== '' ? $fullName : $username,
                password_hash($password, PASSWORD_DEFAULT),
                $permissionValues['can_manage_students'],
                $permissionValues['can_manage_marks'],
                $permissionValues['can_import_csv'],
                $permissionValues['can_backup_db'],
                $permissionValues['can_maintenance_mode'],
                $permissionValues['can_manage_admins'],
                $permissionValues['can_manage_site_settings'],
                $permissionValues['can_view_analytics']
            ]);

            $setFlash('success', 'Admin account created.');
        } elseif ($action === 'update_admin') {
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            if ($adminId <= 0) {
                throw new RuntimeException('Invalid admin selected.');
            }

            $stmtCurrent = $pdo->prepare('SELECT id, username, is_active FROM admins WHERE id = ? LIMIT 1');
            $stmtCurrent->execute([$adminId]);
            $target = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                throw new RuntimeException('Admin not found.');
            }

            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            $permissionValues = [];
            foreach (array_keys($allPermissions) as $column) {
                $permissionValues[$column] = !empty($_POST[$column]) ? 1 : 0;
            }

            if ($adminId === (int) ($_SESSION['admin_id'] ?? 0)) {
                $permissionValues['can_manage_admins'] = 1;
                $isActive = 1;
            }

            $updateStmt = $pdo->prepare("
                UPDATE admins SET
                    full_name = ?,
                    is_active = ?,
                    can_manage_students = ?,
                    can_manage_marks = ?,
                    can_import_csv = ?,
                    can_backup_db = ?,
                    can_maintenance_mode = ?,
                    can_manage_admins = ?,
                    can_manage_site_settings = ?,
                    can_view_analytics = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $fullName !== '' ? $fullName : $target['username'],
                $isActive,
                $permissionValues['can_manage_students'],
                $permissionValues['can_manage_marks'],
                $permissionValues['can_import_csv'],
                $permissionValues['can_backup_db'],
                $permissionValues['can_maintenance_mode'],
                $permissionValues['can_manage_admins'],
                $permissionValues['can_manage_site_settings'],
                $permissionValues['can_view_analytics'],
                $adminId
            ]);

            $newPassword = trim((string) ($_POST['new_password'] ?? ''));
            if ($newPassword !== '') {
                if (strlen($newPassword) < 6) {
                    throw new RuntimeException('New password must be at least 6 characters.');
                }
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);
            }

            $setFlash('success', 'Admin account updated.');
        } elseif ($action === 'delete_admin') {
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            $currentAdminId = (int) ($_SESSION['admin_id'] ?? 0);
            if ($adminId <= 0) {
                throw new RuntimeException('Invalid admin selected.');
            }
            if ($adminId === $currentAdminId) {
                throw new RuntimeException('You cannot delete your own account.');
            }

            $remaining = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($remaining <= 1) {
                throw new RuntimeException('Cannot delete the last admin account.');
            }

            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$adminId]);
            $setFlash('success', 'Admin account removed.');
        }
    } catch (Throwable $e) {
        $setFlash('error', $e->getMessage());
    }

    header('Location: manage_admins');
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$admins = $pdo->query("
    SELECT
        id,
        username,
        full_name,
        is_active,
        can_manage_students,
        can_manage_marks,
        can_import_csv,
        can_backup_db,
        can_maintenance_mode,
        can_manage_admins,
        can_manage_site_settings,
        can_view_analytics,
        created_at
    FROM admins
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$adminStats = [
    'total' => count($admins),
    'active' => 0,
    'inactive' => 0,
    'full_access' => 0
];
foreach ($admins as $adm) {
    $isActive = !empty($adm['is_active']);
    if ($isActive) {
        $adminStats['active']++;
    } else {
        $adminStats['inactive']++;
    }

    $fullAccess = !empty($adm['can_manage_students'])
        && !empty($adm['can_manage_marks'])
        && !empty($adm['can_import_csv'])
        && !empty($adm['can_backup_db'])
        && !empty($adm['can_maintenance_mode'])
        && !empty($adm['can_manage_admins'])
        && !empty($adm['can_manage_site_settings'])
        && !empty($adm['can_view_analytics']);
    if ($fullAccess) {
        $adminStats['full_access']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .admin-page-manage-admins .admin-body {
            gap: 1rem;
        }
        .admin-page-manage-admins .admin-create-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.75fr);
            gap: 0.95rem;
            align-items: stretch;
        }
        .admin-page-manage-admins .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 0.55rem;
            margin-top: 0.2rem;
        }
        .admin-page-manage-admins .perm-chip {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.8);
            border-radius: 11px;
            padding: 0.5rem 0.58rem;
            font-size: 0.82rem;
            color: var(--text-dark);
        }
        .admin-page-manage-admins .perm-chip input {
            accent-color: #0d4f9e;
        }
        .admin-page-manage-admins .admin-help {
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 0.95rem 1rem;
            background: linear-gradient(145deg, rgba(13, 79, 158, 0.09), rgba(199, 0, 23, 0.06));
            box-shadow: var(--shadow-card);
        }
        .admin-page-manage-admins .admin-help h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #0d3f7d;
        }
        .admin-page-manage-admins .admin-help p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.86rem;
            line-height: 1.45;
        }
        .admin-page-manage-admins .admins-table th,
        .admin-page-manage-admins .admins-table td {
            vertical-align: top;
        }
        .admin-page-manage-admins .admin-identity {
            display: grid;
            gap: 0.22rem;
        }
        .admin-page-manage-admins .admin-identity strong {
            font-size: 0.9rem;
            color: #1f2a44;
        }
        .admin-page-manage-admins .admin-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.25rem 0.6rem;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .admin-page-manage-admins .admin-pill.active {
            background: rgba(16, 185, 129, 0.14);
            color: #0f766e;
        }
        .admin-page-manage-admins .admin-pill.inactive {
            background: rgba(239, 68, 68, 0.13);
            color: #b91c1c;
        }
        .admin-page-manage-admins .perm-editor-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.26rem 0.4rem;
            margin-bottom: 0.45rem;
        }
        .admin-page-manage-admins .perm-editor-grid label {
            display: flex;
            align-items: center;
            gap: 0.32rem;
            font-size: 0.76rem;
            color: #45536f;
            padding: 0.2rem 0.28rem;
            border-radius: 8px;
        }
        .admin-page-manage-admins .perm-editor-grid label:hover {
            background: rgba(15, 23, 42, 0.05);
        }
        .admin-page-manage-admins .inline-stack {
            display: grid;
            gap: 0.4rem;
        }
        .admin-page-manage-admins .hero-admin {
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 18px;
            background: linear-gradient(135deg, #0d4f9e 0%, #3257b8 46%, #c70017 100%);
            box-shadow: 0 20px 34px rgba(8, 25, 61, 0.22);
            color: #fff;
            padding: 1rem 1.2rem;
            position: relative;
            overflow: hidden;
        }
        .admin-page-manage-admins .hero-admin::before {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            top: -65px;
            right: -35px;
        }
        .admin-page-manage-admins .hero-admin h2 {
            font-size: 1.15rem;
            margin-bottom: 0.3rem;
            color: #fff;
            position: relative;
            z-index: 1;
        }
        .admin-page-manage-admins .hero-admin p {
            margin: 0;
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.87rem;
            line-height: 1.45;
            position: relative;
            z-index: 1;
            max-width: 700px;
        }
        .admin-page-manage-admins .admin-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 0.85rem;
        }
        .admin-page-manage-admins .admin-user-card {
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.93);
            border-radius: 16px;
            box-shadow: var(--shadow-card);
            padding: 0.85rem;
            display: grid;
            gap: 0.65rem;
        }
        .admin-page-manage-admins .admin-user-top {
            display: flex;
            justify-content: space-between;
            gap: 0.6rem;
            align-items: flex-start;
        }
        .admin-page-manage-admins .admin-user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .admin-page-manage-admins .meta-chip {
            border-radius: 999px;
            background: rgba(13, 79, 158, 0.12);
            color: #0d4f9e;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
        }
        .admin-page-manage-admins .admin-section {
            border-top: 1px dashed rgba(15, 23, 42, 0.15);
            padding-top: 0.55rem;
            display: grid;
            gap: 0.4rem;
        }
        .admin-page-manage-admins .admin-section h4 {
            font-size: 0.78rem;
            font-weight: 800;
            color: #58657e;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .admin-page-manage-admins .status-form-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.45rem;
        }
        .admin-page-manage-admins .status-switch {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            color: #45536f;
        }
        .admin-page-manage-admins .quick-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.45rem;
            align-items: center;
        }
        @media (max-width: 1040px) {
            .admin-page-manage-admins .admin-create-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .admin-page-manage-admins .perm-editor-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-page-manage-admins">
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
                <?php endif; ?>
                <?php if (admin_can('manage_marks')): ?><a href="manage_marks" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
                <?php if (admin_can('import_csv')): ?><a href="import_csv" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a><?php endif; ?>
                <div class="sb-label">System</div>
                <a href="manage_admins" class="sb-link active"><i class="fas fa-user-shield"></i> Manage Admins</a>
                <?php if (admin_can('manage_site_settings')): ?><a href="site_settings" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a><?php endif; ?>
                <?php if (admin_can('backup_db')): ?><a href="backup_database" class="sb-link"><i class="fas fa-download"></i> Backup Database</a><?php endif; ?>
                <?php if (admin_can('maintenance_mode')): ?><a href="maintenance" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
                <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
            </nav>
            <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
        </aside>

        <div class="sb-overlay" id="sbOverlay"></div>

        <main class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-left"><button class="sb-toggle" id="sbToggle"><i class="fas fa-bars"></i></button><h1>Manage Admin Accounts</h1></div>
                <div class="topbar-meta">
                    <span class="topbar-pill"><i class="fas fa-user-shield"></i> <?= (int) $adminStats['total'] ?> Admins</span>
                    <span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span>
                </div>
            </div>

            <div class="admin-body dashboard-stack">
                <?php if ($flash): ?>
                    <div class="msg <?= $flash['type'] === 'error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;">
                        <i class="fas <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i>
                        <?= htmlspecialchars($flash['text']) ?>
                    </div>
                <?php endif; ?>

                <section class="hero-admin reveal d1">
                    <h2>Admin Security Control Center</h2>
                    <p>Manage who can access each critical module. Keep only trusted users on full permissions, and disable accounts instantly when needed.</p>
                </section>

                <section class="metrics-grid reveal d1">
                    <article class="val-card">
                        <div class="icon ic-blue"><i class="fas fa-users-gear"></i></div>
                        <div class="val-lbl">Total Admins</div>
                        <div class="val-num"><?= (int) $adminStats['total'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-purple"><i class="fas fa-user-check"></i></div>
                        <div class="val-lbl">Active</div>
                        <div class="val-num"><?= (int) $adminStats['active'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-pink"><i class="fas fa-user-slash"></i></div>
                        <div class="val-lbl">Inactive</div>
                        <div class="val-num"><?= (int) $adminStats['inactive'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-orange"><i class="fas fa-crown"></i></div>
                        <div class="val-lbl">Full Access</div>
                        <div class="val-num"><?= (int) $adminStats['full_access'] ?></div>
                    </article>
                </section>

                <section class="admin-create-layout reveal d2">
                    <article class="dash-panel">
                        <div class="panel-head"><h2><i class="fas fa-user-plus" style="color:var(--primary);"></i> Add Admin</h2></div>
                        <div class="panel-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_admin">
                                <div class="form-grid">
                                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                                    <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control"></div>
                                </div>
                                <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="6" required></div>
                                <div class="form-group">
                                    <label class="form-label">Permissions</label>
                                    <div class="permission-grid">
                                        <?php foreach ($allPermissions as $column => $label): ?>
                                            <label class="perm-chip"><input type="checkbox" name="<?= htmlspecialchars($column) ?>" value="1" checked> <?= htmlspecialchars($label) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-plus"></i> Create Admin</button>
                            </form>
                        </div>
                    </article>
                    <aside class="admin-help">
                        <h3><i class="fas fa-shield-halved"></i> Access Guide</h3>
                        <p>Use narrow permissions for daily tasks and reserve full access for trusted senior admins only. Deactivating an account blocks login immediately without deleting history.</p>
                    </aside>
                </section>

                <section class="dash-panel reveal d3">
                    <div class="panel-head"><h2><i class="fas fa-users-cog" style="color:var(--primary);"></i> Existing Admins</h2></div>
                    <div class="panel-body">
                        <?php if (!empty($admins)): ?>
                            <div class="admin-card-grid">
                                <?php foreach ($admins as $row): ?>
                                    <article class="admin-user-card">
                                        <div class="admin-user-top">
                                            <div class="admin-identity">
                                                <strong><?= htmlspecialchars((string) ($row['full_name'] ?: $row['username'])) ?></strong>
                                                <span class="text-muted">@<?= htmlspecialchars((string) $row['username']) ?></span>
                                            </div>
                                            <div class="admin-user-meta">
                                                <span class="meta-chip">#<?= (int) $row['id'] ?></span>
                                                <span class="admin-pill <?= !empty($row['is_active']) ? 'active' : 'inactive' ?>">
                                                    <i class="fas <?= !empty($row['is_active']) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                                    <?= !empty($row['is_active']) ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="admin-section">
                                            <h4>Status</h4>
                                            <form method="POST" class="status-form-line">
                                                <input type="hidden" name="action" value="update_admin">
                                                <input type="hidden" name="admin_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="full_name" value="<?= htmlspecialchars((string) ($row['full_name'] ?? '')) ?>">
                                                <?php foreach (array_keys($allPermissions) as $column): ?>
                                                    <?php if (!empty($row[$column])): ?>
                                                        <input type="hidden" name="<?= htmlspecialchars($column) ?>" value="1">
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <label class="status-switch">
                                                    <input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>>
                                                    Enabled Access
                                                </label>
                                                <button type="submit" class="notion-btn notion-btn-ghost notion-btn-sm">Apply</button>
                                            </form>
                                        </div>

                                        <div class="admin-section">
                                            <h4>Permissions</h4>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="update_admin">
                                                <input type="hidden" name="admin_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="full_name" value="<?= htmlspecialchars((string) ($row['full_name'] ?: $row['username'])) ?>">
                                                <input type="hidden" name="is_active" value="<?= !empty($row['is_active']) ? 1 : 0 ?>">
                                                <div class="perm-editor-grid">
                                                    <?php foreach ($allPermissions as $column => $label): ?>
                                                        <label>
                                                            <input type="checkbox" name="<?= htmlspecialchars($column) ?>" value="1" <?= !empty($row[$column]) ? 'checked' : '' ?>>
                                                            <?= htmlspecialchars($label) ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="submit" class="notion-btn notion-btn-ghost notion-btn-sm"><i class="fas fa-floppy-disk"></i> Save Permissions</button>
                                            </form>
                                        </div>

                                        <div class="admin-section">
                                            <h4>Security</h4>
                                            <form method="POST" class="quick-actions">
                                                <input type="hidden" name="action" value="update_admin">
                                                <input type="hidden" name="admin_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="full_name" value="<?= htmlspecialchars((string) ($row['full_name'] ?: $row['username'])) ?>">
                                                <input type="hidden" name="is_active" value="<?= !empty($row['is_active']) ? 1 : 0 ?>">
                                                <?php foreach (array_keys($allPermissions) as $column): ?>
                                                    <?php if (!empty($row[$column])): ?>
                                                        <input type="hidden" name="<?= htmlspecialchars($column) ?>" value="1">
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <input type="password" name="new_password" class="form-control" minlength="6" placeholder="New password">
                                                <button type="submit" class="notion-btn notion-btn-ghost notion-btn-sm"><i class="fas fa-key"></i> Reset</button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Delete this admin account?');">
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="admin_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="notion-btn notion-btn-danger notion-btn-sm" <?= ((int) $row['id'] === (int) ($_SESSION['admin_id'] ?? 0)) ? 'disabled' : '' ?>>
                                                    <i class="fas fa-trash"></i> Delete Account
                                                </button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-user-shield"></i>No admin accounts found.</div>
                        <?php endif; ?>
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
