<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_students');

$setFlash = static function (string $type, string $text): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'text' => $text];
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'generate_missing') {
            $count = bulk_generate_student_credentials($pdo, false);
            $setFlash('success', $count . ' student account(s) generated.');
        } elseif ($action === 'reset_one') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            if ($studentId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }
            $creds = issue_student_credentials($pdo, $studentId, true);
            $setFlash('success', 'Credentials reset for ' . $creds['username'] . '.');
        } elseif ($action === 'toggle_active') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $active = !empty($_POST['is_active']) ? 1 : 0;
            if ($studentId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }
            $pdo->prepare('UPDATE student_accounts SET is_active = ? WHERE student_id = ?')->execute([$active, $studentId]);
            $setFlash('success', $active ? 'Student login activated.' : 'Student login deactivated.');
        }
    } catch (Throwable $e) {
        $setFlash('error', $e->getMessage());
    }

    header('Location: student_credentials');
    exit;
}

if (($_GET['action'] ?? '') === 'export_csv') {
    $stmt = $pdo->query("\n        SELECT\n            s.name AS student_name,\n            c.class_name,\n            y.year_name,\n            sa.username,\n            sa.password_plain,\n            sa.is_active,\n            sa.last_login_at\n        FROM students s\n        LEFT JOIN classes c ON c.id = s.class_id\n        LEFT JOIN academic_years y ON y.id = s.academic_year_id\n        LEFT JOIN student_accounts sa ON sa.student_id = s.id\n        ORDER BY y.year_name DESC, c.class_name ASC, s.name ASC\n    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'student_credentials_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student Name', 'Class', 'Academic Year', 'Username', 'Password', 'Status', 'Last Login']);
    foreach ($rows as $row) {
        fputcsv($out, [
            (string) ($row['student_name'] ?? ''),
            (string) ($row['class_name'] ?? ''),
            (string) ($row['year_name'] ?? ''),
            (string) ($row['username'] ?? ''),
            (string) ($row['password_plain'] ?? ''),
            !empty($row['is_active']) ? 'Active' : 'Disabled',
            (string) ($row['last_login_at'] ?? '')
        ]);
    }
    fclose($out);
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$search = trim((string) ($_GET['search'] ?? ''));
$classFilter = (int) ($_GET['class_id'] ?? 0);
$yearFilter = (int) ($_GET['year_id'] ?? 0);
$studentFilter = (int) ($_GET['student_id'] ?? 0);

$sql = "\n    SELECT\n        s.id,\n        s.name AS student_name,\n        c.class_name,\n        y.year_name,\n        sa.username,\n        sa.password_plain,\n        sa.is_active,\n        sa.last_login_at\n    FROM students s\n    LEFT JOIN classes c ON c.id = s.class_id\n    LEFT JOIN academic_years y ON y.id = s.academic_year_id\n    LEFT JOIN student_accounts sa ON sa.student_id = s.id\n    WHERE 1=1\n";
$params = [];
if ($search !== '') {
    $sql .= " AND (s.name LIKE ? OR sa.username LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($classFilter > 0) {
    $sql .= " AND s.class_id = ?";
    $params[] = $classFilter;
}
if ($yearFilter > 0) {
    $sql .= " AND s.academic_year_id = ?";
    $params[] = $yearFilter;
}
if ($studentFilter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $studentFilter;
}
$sql .= " ORDER BY y.year_name DESC, c.class_name ASC, s.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$classes = $pdo->query('SELECT id, class_name FROM classes ORDER BY class_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query('SELECT id, year_name FROM academic_years ORDER BY year_name DESC')->fetchAll(PDO::FETCH_ASSOC);
$totals = [
    'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'ready' => (int) $pdo->query("SELECT COUNT(*) FROM student_accounts WHERE username <> '' AND password_plain <> ''")->fetchColumn(),
    'active' => (int) $pdo->query('SELECT COUNT(*) FROM student_accounts WHERE is_active = 1')->fetchColumn()
];
$totals['missing'] = max(0, $totals['students'] - $totals['ready']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Credentials - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="receipt-print-page">
<div class="bg-mesh"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>
<div class="admin-layout">
<aside class="sidebar" id="sidebar">
    <a href="dashboard" class="sb-header"><img src="../logo.png" alt="BrightVision" class="sb-logo"></a>
    <nav class="sb-nav">
        <div class="sb-label">Analytics</div><a href="dashboard" class="sb-link"><i class="fas fa-chart-line"></i> Dashboard</a><?php if (admin_can('view_analytics')): ?><a href="class_analytics" class="sb-link"><i class="fas fa-chart-column"></i> Class Analytics</a><?php endif; ?>
        <div class="sb-label">Management</div><a href="manage_students" class="sb-link"><i class="fas fa-database"></i> Data Manager</a><a href="student_credentials" class="sb-link active"><i class="fas fa-id-card"></i> Student Credentials</a><a href="manage_academics" class="sb-link"><i class="fas fa-graduation-cap"></i> Academics</a>
                    <a href="export_student_ids" class="sb-link"><i class="fas fa-address-card"></i> Export Student IDs</a>
        <?php if (admin_can('manage_marks')): ?><a href="manage_marks" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
        <?php if (admin_can('import_csv')): ?><a href="import_csv" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a><?php endif; ?>
        <div class="sb-label">System</div>
        <?php if (admin_can('manage_admins')): ?><a href="manage_admins" class="sb-link"><i class="fas fa-user-shield"></i> Manage Admins</a><?php endif; ?>
        <?php if (admin_can('manage_site_settings')): ?><a href="site_settings" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a><?php endif; ?>
        <?php if (admin_can('backup_db')): ?><a href="backup_database" class="sb-link"><i class="fas fa-download"></i> Backup Database</a><?php endif; ?>
        <?php if (admin_can('maintenance_mode')): ?><a href="maintenance" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a><?php endif; ?>
        <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a><a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
    </nav>
    <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
</aside>
<div class="sb-overlay" id="sbOverlay"></div>
<main class="admin-main">
    <div class="admin-topbar">
        <div class="topbar-left"><button class="sb-toggle" id="sbToggle"><i class="fas fa-bars"></i></button><h1>Student Login Credentials</h1></div>
        <div class="topbar-meta"><button type="button" class="topbar-action" id="printVisibleReceipts"><i class="fas fa-receipt"></i> Print Receipts</button><a href="student_credentials?action=export_csv" class="topbar-action"><i class="fas fa-file-csv"></i> Export CSV</a></div>
    </div>

    <div class="admin-body dashboard-stack">
        <?php if ($flash): ?><div class="msg <?= $flash['type']==='error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;"><i class="fas <?= $flash['type']==='error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($flash['text']) ?></div><?php endif; ?>

        <section class="metrics-grid reveal d1">
            <article class="val-card"><div class="icon ic-blue"><i class="fas fa-users"></i></div><div class="val-lbl">Students</div><div class="val-num"><?= (int) $totals['students'] ?></div></article>
            <article class="val-card"><div class="icon ic-purple"><i class="fas fa-user-check"></i></div><div class="val-lbl">Ready Accounts</div><div class="val-num"><?= (int) $totals['ready'] ?></div></article>
            <article class="val-card"><div class="icon ic-pink"><i class="fas fa-circle-exclamation"></i></div><div class="val-lbl">Missing</div><div class="val-num"><?= (int) $totals['missing'] ?></div></article>
            <article class="val-card"><div class="icon ic-orange"><i class="fas fa-toggle-on"></i></div><div class="val-lbl">Active</div><div class="val-num"><?= (int) $totals['active'] ?></div></article>
        </section>

        <section class="dash-panel reveal d2">
            <div class="panel-head"><h2><i class="fas fa-wand-magic-sparkles" style="color:var(--primary);"></i> Credential Tools</h2></div>
            <div class="panel-body">
                <form method="POST" style="display:flex;gap:0.7rem;flex-wrap:wrap;">
                    <input type="hidden" name="action" value="generate_missing">
                    <button type="submit" class="notion-btn notion-btn-primary"><i class="fas fa-user-plus"></i> Generate Missing Accounts</button>
                    <button type="button" class="notion-btn notion-btn-ghost" id="printVisibleReceiptsPanel"><i class="fas fa-print"></i> Print Visible Receipts</button>
                    <a href="student_credentials?action=export_csv" class="notion-btn notion-btn-ghost"><i class="fas fa-file-export"></i> Export Credentials</a>
                </form>
            </div>
        </section>

        <section class="dash-panel reveal d3">
            <div class="panel-head"><h2><i class="fas fa-list" style="color:var(--primary);"></i> Student Credential Registry</h2></div>
            <div class="panel-body">
                <form method="GET" class="filter-form" style="margin-bottom:0.85rem;">
                    <div class="notion-form-group" style="flex:2;min-width:180px;"><label class="notion-label">Search</label><input type="text" name="search" class="notion-input" value="<?= htmlspecialchars($search) ?>" placeholder="Student or username..."></div>
                    <div class="notion-form-group" style="flex:1;min-width:130px;"><label class="notion-label">Class</label><select name="class_id" class="notion-select"><option value="">All</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $classFilter === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="notion-form-group" style="flex:1;min-width:130px;"><label class="notion-label">Year</label><select name="year_id" class="notion-select"><option value="">All</option><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= $yearFilter === (int) $y['id'] ? 'selected' : '' ?>><?= htmlspecialchars($y['year_name']) ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="notion-btn notion-btn-primary notion-btn-sm"><i class="fas fa-search"></i> Filter</button>
                    <a href="student_credentials" class="notion-btn notion-btn-ghost notion-btn-sm">Clear</a>
                    <?php if ($studentFilter > 0): ?><a href="student_credentials" class="notion-btn notion-btn-ghost notion-btn-sm"><i class="fas fa-users"></i> Show All</a><?php endif; ?>
                </form>

                <div class="table-scroll"><table class="glass-table"><thead><tr><th>Student</th><th>Class</th><th>Year</th><th>Username</th><th>Password</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($students as $row): ?>
                    <tr>
                        <td style="font-weight:700;"><?= htmlspecialchars((string) ($row['student_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['class_name'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['year_name'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['password_plain'] ?? '')) ?></td>
                        <td><span class="badge <?= !empty($row['is_active']) ? 'badge-blue' : 'badge-pink' ?>"><?= !empty($row['is_active']) ? 'Active' : 'Disabled' ?></span></td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="notion-btn notion-btn-ghost notion-btn-sm js-print-receipt" data-student-id="<?= (int) $row['id'] ?>"><i class="fas fa-print"></i> Print</button>
                                <form method="POST" style="display:inline-flex;"><input type="hidden" name="action" value="reset_one"><input type="hidden" name="student_id" value="<?= (int) $row['id'] ?>"><button type="submit" class="notion-btn notion-btn-ghost notion-btn-sm"><i class="fas fa-rotate"></i> Reset</button></form>
                                <form method="POST" style="display:inline-flex;"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="student_id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="is_active" value="<?= empty($row['is_active']) ? 1 : 0 ?>"><button type="submit" class="notion-btn notion-btn-sm <?= empty($row['is_active']) ? 'notion-btn-primary' : 'notion-btn-danger' ?>"><i class="fas <?= empty($row['is_active']) ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i> <?= empty($row['is_active']) ? 'Enable' : 'Disable' ?></button></form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);">No students found for this filter.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </section>
    </div>
</main>
</div>
<div class="receipt-print-area" aria-hidden="true">
    <?php foreach ($students as $row): ?>
        <?php
            $studentName = (string) ($row['student_name'] ?? '');
            $className = (string) ($row['class_name'] ?? '-');
            $yearName = (string) ($row['year_name'] ?? '-');
            $username = (string) ($row['username'] ?? '');
            $password = (string) ($row['password_plain'] ?? '');
            $isReady = trim($username) !== '' && trim($password) !== '';
        ?>
        <article class="receipt-card" id="receipt-<?= (int) $row['id'] ?>" data-student-id="<?= (int) $row['id'] ?>">
            <img src="../logo.png" alt="BrightVision" class="receipt-logo">
            <div class="receipt-brand">BrightVision English Academy</div>
            <div class="receipt-title">Student Login Credentials</div>
            <div class="receipt-rule"></div>
            <div class="receipt-row"><span>Name</span><strong><?= htmlspecialchars($studentName) ?></strong></div>
            <div class="receipt-row"><span>Class</span><strong><?= htmlspecialchars($className) ?></strong></div>
            <div class="receipt-row"><span>Year</span><strong><?= htmlspecialchars($yearName) ?></strong></div>
            <div class="receipt-rule"></div>
            <?php if ($isReady): ?>
                <div class="receipt-credential"><span>Username</span><strong><?= htmlspecialchars($username) ?></strong></div>
                <div class="receipt-credential"><span>Password</span><strong><?= htmlspecialchars($password) ?></strong></div>
                <div class="receipt-status">Status: <?= !empty($row['is_active']) ? 'Active' : 'Disabled' ?></div>
            <?php else: ?>
                <div class="receipt-empty">Credentials not generated yet.</div>
            <?php endif; ?>
            <div class="receipt-rule"></div>
            <div class="receipt-foot">Student Login Portal</div>
        </article>
    <?php endforeach; ?>
</div>
<script>
const sidebar=document.getElementById('sidebar');
const sbOverlay=document.getElementById('sbOverlay');
const sbToggle=document.getElementById('sbToggle');
const printButtons=document.querySelectorAll('.js-print-receipt');
const printAllButtons=[document.getElementById('printVisibleReceipts'),document.getElementById('printVisibleReceiptsPanel')].filter(Boolean);
const receipts=document.querySelectorAll('.receipt-card');
const clearReceiptTargets=()=>{document.body.classList.remove('print-single-receipt');receipts.forEach((el)=>{el.classList.remove('is-print-target');el.hidden=false;});};
const receiptPrintCss=`@page{size:80mm 90mm;margin:0}*{box-sizing:border-box}html,body{width:80mm;margin:0;padding:0;background:#fff;color:#111}.receipt-card{width:80mm;color:#111;background:#fff;font-family:Consolas,"Courier New",monospace;font-size:11px;line-height:1.3;padding:3mm 4mm 4mm}.receipt-card+.receipt-card{break-before:page;page-break-before:always}.receipt-logo{display:block;width:30mm;max-height:12mm;object-fit:contain;margin:0 auto 1.5mm}.receipt-brand{text-align:center;font-size:12px;font-weight:800;text-transform:uppercase}.receipt-title,.receipt-status,.receipt-foot,.receipt-empty{text-align:center}.receipt-title{margin-top:.7mm;font-size:11px;font-weight:700}.receipt-rule{border-top:1px dashed #111;margin:2mm 0}.receipt-row,.receipt-credential{display:grid;grid-template-columns:20mm 1fr;gap:2mm;margin:1mm 0}.receipt-row span,.receipt-credential span{color:#333}.receipt-row strong,.receipt-credential strong{min-width:0;overflow-wrap:anywhere;text-align:right}.receipt-credential{grid-template-columns:1fr;gap:.5mm;text-align:center}.receipt-credential strong{display:block;border:1px solid #111;padding:1.4mm 1mm;font-size:15px;letter-spacing:.04em;text-align:center}.receipt-status,.receipt-foot,.receipt-empty{font-size:10px}.receipt-empty{border:1px solid #111;padding:2mm 1mm;font-weight:700}`;
const normalizeReceiptHtml=(cards)=>cards.map((card)=>{const clone=card.cloneNode(true);const logo=clone.querySelector('.receipt-logo');if(logo){logo.src=new URL('../logo.png',window.location.href).href;}clone.removeAttribute('hidden');clone.classList.remove('is-print-target');return clone.outerHTML;}).join('');
const printReceiptHtml=(cards)=>{if(!cards.length){return;}const frame=document.createElement('iframe');frame.style.position='fixed';frame.style.right='0';frame.style.bottom='0';frame.style.width='0';frame.style.height='0';frame.style.border='0';frame.setAttribute('aria-hidden','true');document.body.appendChild(frame);const doc=frame.contentDocument||frame.contentWindow.document;doc.open();doc.write(`<!doctype html><html><head><meta charset="utf-8"><title>Student Credential Slip</title><style>${receiptPrintCss}</style></head><body>${normalizeReceiptHtml(cards)}</body></html>`);doc.close();const runPrint=()=>{const images=Array.from(doc.images);const ready=images.map((img)=>img.complete?Promise.resolve():new Promise((resolve)=>{img.onload=resolve;img.onerror=resolve;}));Promise.all(ready).then(()=>{setTimeout(()=>{frame.contentWindow.focus();frame.contentWindow.print();},80);});};frame.onload=runPrint;setTimeout(runPrint,250);const removeFrame=()=>setTimeout(()=>frame.remove(),500);frame.contentWindow.addEventListener('afterprint',removeFrame);};
const printReceipt=(studentId)=>{clearReceiptTargets();const receipt=document.getElementById(`receipt-${studentId}`);if(!receipt){return;}printReceiptHtml([receipt]);};
sbToggle?.addEventListener('click',()=>{sidebar.classList.toggle('open');sbOverlay.classList.toggle('show');});
sbOverlay?.addEventListener('click',()=>{sidebar.classList.remove('open');sbOverlay.classList.remove('show');});
printButtons.forEach((button)=>button.addEventListener('click',()=>printReceipt(button.dataset.studentId)));
printAllButtons.forEach((button)=>button.addEventListener('click',()=>{clearReceiptTargets();printReceiptHtml(Array.from(receipts));}));
window.addEventListener('afterprint',clearReceiptTargets);
</script>
</body>
</html>


