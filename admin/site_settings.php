<?php
session_start();
require_once '../config.php';
require_once '../footer.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_site_settings');

$defaults = default_footer_settings();
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $brandName = trim((string) ($_POST['footer_brand_name'] ?? $defaults['brand_name']));
        $copyrightOwner = trim((string) ($_POST['footer_copyright_owner'] ?? $defaults['copyright_owner']));
        $rightsText = trim((string) ($_POST['footer_rights_text'] ?? $defaults['rights_text']));
        $developerName = trim((string) ($_POST['footer_text'] ?? $defaults['developer_name']));
        $developerUrl = trim((string) ($_POST['footer_url'] ?? $defaults['developer_url']));
        $collab = trim((string) ($_POST['footer_collab_text'] ?? $defaults['collaboration_text']));

        $link1Label = trim((string) ($_POST['footer_link_1_label'] ?? $defaults['link_1_label']));
        $link1Url = trim((string) ($_POST['footer_link_1_url'] ?? $defaults['link_1_url']));
        $link2Label = trim((string) ($_POST['footer_link_2_label'] ?? $defaults['link_2_label']));
        $link2Url = trim((string) ($_POST['footer_link_2_url'] ?? $defaults['link_2_url']));
        $link3Label = trim((string) ($_POST['footer_link_3_label'] ?? $defaults['link_3_label']));
        $link3Url = trim((string) ($_POST['footer_link_3_url'] ?? $defaults['link_3_url']));

        $stmt = $pdo->prepare("\n            INSERT INTO site_settings (\n                id, footer_text, footer_url, footer_brand_name, footer_copyright_owner,\n                footer_rights_text, footer_collab_text, footer_link_1_label, footer_link_1_url,\n                footer_link_2_label, footer_link_2_url, footer_link_3_label, footer_link_3_url\n            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ON DUPLICATE KEY UPDATE\n                footer_text = VALUES(footer_text),\n                footer_url = VALUES(footer_url),\n                footer_brand_name = VALUES(footer_brand_name),\n                footer_copyright_owner = VALUES(footer_copyright_owner),\n                footer_rights_text = VALUES(footer_rights_text),\n                footer_collab_text = VALUES(footer_collab_text),\n                footer_link_1_label = VALUES(footer_link_1_label),\n                footer_link_1_url = VALUES(footer_link_1_url),\n                footer_link_2_label = VALUES(footer_link_2_label),\n                footer_link_2_url = VALUES(footer_link_2_url),\n                footer_link_3_label = VALUES(footer_link_3_label),\n                footer_link_3_url = VALUES(footer_link_3_url)\n        ");
        $stmt->execute([
            $developerName !== '' ? $developerName : $defaults['developer_name'],
            $developerUrl !== '' ? $developerUrl : $defaults['developer_url'],
            $brandName !== '' ? $brandName : $defaults['brand_name'],
            $copyrightOwner !== '' ? $copyrightOwner : $defaults['copyright_owner'],
            $rightsText !== '' ? $rightsText : $defaults['rights_text'],
            $collab,
            $link1Label,
            $link1Url !== '' ? $link1Url : '#',
            $link2Label,
            $link2Url !== '' ? $link2Url : '#',
            $link3Label,
            $link3Url !== '' ? $link3Url : '#'
        ]);

        $flash = ['type' => 'success', 'text' => 'Site footer settings updated successfully.'];
    } catch (Throwable $e) {
        $flash = ['type' => 'error', 'text' => 'Failed to save settings: ' . $e->getMessage()];
    }
}

$settings = get_site_footer_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - BrightVision</title>
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
            <a href="site_settings" class="sb-link active"><i class="fas fa-sliders"></i> Site Settings</a>
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
        <div class="admin-topbar"><div class="topbar-left"><button class="sb-toggle" id="sbToggle"><i class="fas fa-bars"></i></button><h1>Site Settings</h1></div></div>
        <div class="admin-body dashboard-stack">
            <?php if ($flash): ?><div class="msg <?= $flash['type']==='error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;"><i class="fas <?= $flash['type']==='error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($flash['text']) ?></div><?php endif; ?>

            <section class="dash-panel reveal d1">
                <div class="panel-head"><h2><i class="fas fa-pen" style="color:var(--primary);"></i> Footer Configuration</h2></div>
                <div class="panel-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group"><label class="form-label">Brand Name</label><input type="text" name="footer_brand_name" class="form-control" value="<?= htmlspecialchars((string) $settings['brand_name']) ?>"></div>
                            <div class="form-group"><label class="form-label">Copyright Owner</label><input type="text" name="footer_copyright_owner" class="form-control" value="<?= htmlspecialchars((string) $settings['copyright_owner']) ?>"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Rights Text</label><input type="text" name="footer_rights_text" class="form-control" value="<?= htmlspecialchars((string) $settings['rights_text']) ?>"></div>

                        <div class="form-grid">
                            <div class="form-group"><label class="form-label">Developer Name</label><input type="text" name="footer_text" class="form-control" value="<?= htmlspecialchars((string) $settings['developer_name']) ?>"></div>
                            <div class="form-group"><label class="form-label">Developer URL</label><input type="text" name="footer_url" class="form-control" value="<?= htmlspecialchars((string) $settings['developer_url']) ?>"></div>
                        </div>

                        <div class="form-group"><label class="form-label">Collaboration Text</label><input type="text" name="footer_collab_text" class="form-control" value="<?= htmlspecialchars((string) $settings['collaboration_text']) ?>"></div>

                        <div class="form-grid">
                            <div class="form-group"><label class="form-label">Link 1 Label</label><input type="text" name="footer_link_1_label" class="form-control" value="<?= htmlspecialchars((string) ($settings['link_1_label'] ?? '')) ?>"></div>
                            <div class="form-group"><label class="form-label">Link 1 URL</label><input type="text" name="footer_link_1_url" class="form-control" value="<?= htmlspecialchars((string) ($settings['link_1_url'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group"><label class="form-label">Link 2 Label</label><input type="text" name="footer_link_2_label" class="form-control" value="<?= htmlspecialchars((string) ($settings['link_2_label'] ?? '')) ?>"></div>
                            <div class="form-group"><label class="form-label">Link 2 URL</label><input type="text" name="footer_link_2_url" class="form-control" value="<?= htmlspecialchars((string) ($settings['link_2_url'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group"><label class="form-label">Link 3 Label</label><input type="text" name="footer_link_3_label" class="form-control" value="<?= htmlspecialchars((string) ($settings['link_3_label'] ?? '')) ?>"></div>
                            <div class="form-group"><label class="form-label">Link 3 URL</label><input type="text" name="footer_link_3_url" class="form-control" value="<?= htmlspecialchars((string) ($settings['link_3_url'] ?? '')) ?>"></div>
                        </div>

                        <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-floppy-disk"></i> Save Settings</button>
                    </form>
                </div>
            </section>

            <section class="dash-panel reveal d2">
                <div class="panel-head"><h2><i class="fas fa-eye" style="color:var(--accent);"></i> Footer Preview</h2></div>
                <div class="panel-body">
                    <?php render_portal_footer($settings, ''); ?>
                </div>
            </section>
        </div>
        <?php render_admin_footer($pdo); ?>
    </main>
</div>
<script>const sidebar=document.getElementById('sidebar');const sbOverlay=document.getElementById('sbOverlay');const sbToggle=document.getElementById('sbToggle');sbToggle?.addEventListener('click',()=>{sidebar.classList.toggle('open');sbOverlay.classList.toggle('show');});sbOverlay?.addEventListener('click',()=>{sidebar.classList.remove('open');sbOverlay.classList.remove('show');});</script>
</body>
</html>


