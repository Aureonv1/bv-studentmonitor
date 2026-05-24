<?php
require_once __DIR__ . '/../session_bootstrap.php';
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_students');

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC")->fetchAll(PDO::FETCH_ASSOC);

$classId = (int) ($_GET['class_id'] ?? 0);
$yearId = (int) ($_GET['year_id'] ?? 0);

$students = [];
if ($classId > 0) {
    $sql = "
        SELECT s.id, s.student_code, s.name, c.class_name, y.year_name
        FROM students s
        LEFT JOIN classes c ON c.id = s.class_id
        LEFT JOIN academic_years y ON y.id = s.academic_year_id
        WHERE s.class_id = ?
    ";
    $params = [$classId];
    if ($yearId > 0) {
        $sql .= " AND s.academic_year_id = ?";
        $params[] = $yearId;
    }
    $sql .= " ORDER BY s.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as &$row) {
        $sid = (int) ($row['id'] ?? 0);
        if ($sid > 0) {
            $row['student_code'] = assign_student_code($pdo, $sid);
        }
    }
    unset($row);
}

$totalIds = count($students);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Student IDs - BrightVision</title>
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
            <a href="manage_students.php" class="sb-link"><i class="fas fa-database"></i> Data Manager</a>
            <a href="student_credentials.php" class="sb-link"><i class="fas fa-id-card"></i> Student Credentials</a>
            <a href="manage_academics.php" class="sb-link"><i class="fas fa-graduation-cap"></i> Academics</a>
            <a href="export_student_ids.php" class="sb-link active"><i class="fas fa-address-card"></i> Export Student IDs</a>
            <?php if (admin_can('manage_marks')): ?><a href="manage_marks.php" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
            <?php if (admin_can('import_csv')): ?><a href="import_csv.php" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a><?php endif; ?>

            <div class="sb-label">System</div>
            <?php if (admin_can('manage_admins')): ?><a href="manage_admins.php" class="sb-link"><i class="fas fa-user-shield"></i> Manage Admins</a><?php endif; ?>
            <?php if (admin_can('manage_site_settings')): ?><a href="site_settings.php" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a><?php endif; ?>
            <?php if (admin_can('backup_db')): ?><a href="backup_database.php" class="sb-link"><i class="fas fa-download"></i> Backup Database</a><?php endif; ?>
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
            <div class="topbar-left"><button class="sb-toggle" id="sbToggle"><i class="fas fa-bars"></i></button><h1>Export Student IDs</h1></div>
            <div class="topbar-meta"><span class="topbar-pill"><i class="fas fa-address-card"></i> <?= (int) $totalIds ?> IDs</span><span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span></div>
        </div>

        <div class="admin-body dashboard-stack">
            <section class="dash-panel reveal d1">
                <div class="panel-head"><h2><i class="fas fa-filter" style="color:var(--primary);"></i> Select Scope</h2></div>
                <div class="panel-body">
                    <form method="GET" class="filter-form">
                        <div class="notion-form-group" style="min-width:220px;flex:1;">
                            <label class="notion-label" for="class_id">Class</label>
                            <select id="class_id" name="class_id" class="notion-select" required>
                                <option value="">Select class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int) $class['id'] ?>" <?= $classId === (int) $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="notion-form-group" style="min-width:220px;flex:1;">
                            <label class="notion-label" for="year_id">Academic Year (optional)</label>
                            <select id="year_id" name="year_id" class="notion-select">
                                <option value="">All years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= (int) $year['id'] ?>" <?= $yearId === (int) $year['id'] ? 'selected' : '' ?>><?= htmlspecialchars($year['year_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="notion-btn notion-btn-primary notion-btn-sm"><i class="fas fa-search"></i> Load Students</button>
                        <a href="export_student_ids.php" class="notion-btn notion-btn-ghost notion-btn-sm">Clear</a>
                        <?php if ($classId > 0): ?>
                            <a href="export_student_roster.php?class_id=<?= (int) $classId ?><?= $yearId > 0 ? '&year_id=' . (int) $yearId : '' ?>" class="notion-btn notion-btn-primary notion-btn-sm"><i class="fas fa-file-csv"></i> Export CSV</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>

            <section class="dash-panel reveal d2">
                <div class="panel-head"><h2><i class="fas fa-list" style="color:var(--primary);"></i> Student ID Preview</h2></div>
                <div class="panel-body" style="padding:0;">
                    <div class="table-scroll">
                        <table class="glass-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Academic Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Select a class to load student IDs.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($students as $row): ?>
                                        <tr>
                                            <td><span class="notion-tag tag-blue"><?= htmlspecialchars((string) ($row['student_code'] ?? '')) ?></span></td>
                                            <td style="font-weight:700;"><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['class_name'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['year_name'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
