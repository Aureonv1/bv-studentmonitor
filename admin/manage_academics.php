<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_students');

$setFlash = static function (string $type, string $message): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'text' => $message];
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_class' || $action === 'update_class') {
            $name = trim((string) ($_POST['class_name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Class name is required.');
            }
            if ($action === 'add_class') {
                $pdo->prepare('INSERT INTO classes (class_name) VALUES (?)')->execute([$name]);
                $setFlash('success', 'Class added successfully.');
            } else {
                $id = (int) ($_POST['class_id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('Invalid class selected.');
                }
                $pdo->prepare('UPDATE classes SET class_name = ? WHERE id = ?')->execute([$name, $id]);
                $setFlash('success', 'Class updated successfully.');
            }
        } elseif ($action === 'delete_class') {
            $id = (int) ($_POST['class_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid class selected.');
            }
            $pdo->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
            $setFlash('success', 'Class removed.');
        } elseif ($action === 'add_year' || $action === 'update_year') {
            $name = trim((string) ($_POST['year_name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Academic year is required.');
            }
            if ($action === 'add_year') {
                $pdo->prepare('INSERT INTO academic_years (year_name) VALUES (?)')->execute([$name]);
                $setFlash('success', 'Academic year added successfully.');
            } else {
                $id = (int) ($_POST['year_id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('Invalid academic year selected.');
                }
                $pdo->prepare('UPDATE academic_years SET year_name = ? WHERE id = ?')->execute([$name, $id]);
                $setFlash('success', 'Academic year updated successfully.');
            }
        } elseif ($action === 'delete_year') {
            $id = (int) ($_POST['year_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid academic year selected.');
            }
            $pdo->prepare('DELETE FROM academic_years WHERE id = ?')->execute([$id]);
            $setFlash('success', 'Academic year removed.');
        }
    } catch (Throwable $e) {
        $setFlash('error', $e->getMessage());
    }

    header('Location: manage_academics');
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$classes = $pdo->query("SELECT c.id, c.class_name, COUNT(s.id) AS students FROM classes c LEFT JOIN students s ON s.class_id=c.id GROUP BY c.id, c.class_name ORDER BY c.class_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT y.id, y.year_name, COUNT(s.id) AS students FROM academic_years y LEFT JOIN students s ON s.academic_year_id=y.id GROUP BY y.id, y.year_name ORDER BY y.year_name DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Academics - BrightVision</title>
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
        <a href="manage_students" class="sb-link"><i class="fas fa-database"></i> Data Manager</a>
        <a href="student_credentials" class="sb-link"><i class="fas fa-id-card"></i> Student Credentials</a>
        <a href="manage_academics" class="sb-link active"><i class="fas fa-graduation-cap"></i> Academics</a>
                    <a href="export_student_ids" class="sb-link"><i class="fas fa-address-card"></i> Export Student IDs</a>
        <?php if (admin_can('manage_marks')): ?><a href="manage_marks" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
        <?php if (admin_can('import_csv')): ?><a href="import_csv" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a><?php endif; ?>
        <div class="sb-label">System</div>
        <?php if (admin_can('manage_admins')): ?><a href="manage_admins" class="sb-link"><i class="fas fa-user-shield"></i> Manage Admins</a><?php endif; ?>
        <?php if (admin_can('manage_site_settings')): ?><a href="site_settings" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a><?php endif; ?>
        <?php if (admin_can('backup_db')): ?><a href="backup_database" class="sb-link"><i class="fas fa-download"></i> Backup Database</a><?php endif; ?>
        <?php if (admin_can('maintenance_mode')): ?><a href="maintenance" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
        <a href="profile" class="sb-link"><i class="fas fa-user-gear"></i> My Profile</a>
        <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
        <a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
    </nav>
    <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
</aside>
<div class="sb-overlay" id="sbOverlay"></div>
<main class="admin-main">
    <div class="admin-topbar"><div class="topbar-left"><button class="sb-toggle" id="sbToggle"><i class="fas fa-bars"></i></button><h1>Manage Academics</h1></div></div>
    <div class="admin-body dashboard-stack">
        <?php if ($flash): ?><div class="msg <?= $flash['type']==='error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;"><i class="fas <?= $flash['type']==='error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($flash['text']) ?></div><?php endif; ?>

        <section class="management-grid reveal d1">
            <article class="dash-panel"><div class="panel-head"><h2><i class="fas fa-layer-group" style="color:var(--primary);"></i> Classes</h2></div><div class="panel-body">
                <form method="POST" class="form-inline" style="margin-bottom:0.8rem;"><input type="hidden" name="action" value="add_class"><input type="text" name="class_name" class="form-control" placeholder="New class name" required><button class="notion-btn notion-btn-primary" type="submit"><i class="fas fa-plus"></i> Add</button></form>
                <div class="table-scroll"><table class="glass-table"><thead><tr><th>Class</th><th>Students</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($classes as $class): ?>
                    <tr>
                        <td>
                            <form method="POST" class="form-inline" style="gap:0.45rem;align-items:center;">
                                <input type="hidden" name="action" value="update_class">
                                <input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>">
                                <input type="text" name="class_name" class="form-control" value="<?= htmlspecialchars($class['class_name']) ?>" style="max-width:180px;">
                                <button class="notion-btn notion-btn-ghost notion-btn-sm" type="submit"><i class="fas fa-floppy-disk"></i> Save</button>
                            </form>
                        </td>
                        <td><?= (int) $class['students'] ?></td>
                        <td><form method="POST" onsubmit="return confirm('Delete class and related students?');"><input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>"><button class="notion-btn notion-btn-danger notion-btn-sm" type="submit"><i class="fas fa-trash"></i></button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($classes)): ?><tr><td colspan="3" style="text-align:center;">No classes yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div></article>

            <article class="dash-panel"><div class="panel-head"><h2><i class="fas fa-calendar-days" style="color:var(--accent);"></i> Academic Years</h2></div><div class="panel-body">
                <form method="POST" class="form-inline" style="margin-bottom:0.8rem;"><input type="hidden" name="action" value="add_year"><input type="text" name="year_name" class="form-control" placeholder="e.g. 2026-2027" required><button class="notion-btn notion-btn-primary" type="submit"><i class="fas fa-plus"></i> Add</button></form>
                <div class="table-scroll"><table class="glass-table"><thead><tr><th>Year</th><th>Students</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($years as $year): ?>
                    <tr>
                        <td>
                            <form method="POST" class="form-inline" style="gap:0.45rem;align-items:center;">
                                <input type="hidden" name="action" value="update_year">
                                <input type="hidden" name="year_id" value="<?= (int) $year['id'] ?>">
                                <input type="text" name="year_name" class="form-control" value="<?= htmlspecialchars($year['year_name']) ?>" style="max-width:180px;">
                                <button class="notion-btn notion-btn-ghost notion-btn-sm" type="submit"><i class="fas fa-floppy-disk"></i> Save</button>
                            </form>
                        </td>
                        <td><?= (int) $year['students'] ?></td>
                        <td><form method="POST" onsubmit="return confirm('Delete year and related students?');"><input type="hidden" name="action" value="delete_year"><input type="hidden" name="year_id" value="<?= (int) $year['id'] ?>"><button class="notion-btn notion-btn-danger notion-btn-sm" type="submit"><i class="fas fa-trash"></i></button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($years)): ?><tr><td colspan="3" style="text-align:center;">No academic years yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div></article>
        </section>
    </div>
</main>
</div>
<script>const sidebar=document.getElementById('sidebar');const sbOverlay=document.getElementById('sbOverlay');const sbToggle=document.getElementById('sbToggle');sbToggle?.addEventListener('click',()=>{sidebar.classList.toggle('open');sbOverlay.classList.toggle('show');});sbOverlay?.addEventListener('click',()=>{sidebar.classList.remove('open');sbOverlay.classList.remove('show');});</script>
</body>
</html>


