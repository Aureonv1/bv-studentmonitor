<?php
require_once __DIR__ . '/../session_bootstrap.php';
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('view_analytics');

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();
$years = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC")->fetchAll();

$filterClass = trim((string) ($_GET['class_id'] ?? ''));
$filterYear = trim((string) ($_GET['year_id'] ?? ''));
$filterExam = trim((string) ($_GET['exam_name'] ?? ''));
$viewRequested = (string) ($_GET['view'] ?? '') === '1';
$canLoad = $viewRequested && $filterClass !== '';

if (isset($_GET['fetch_exams']) && (string) $_GET['fetch_exams'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');

    $classId = (int) ($_GET['class_id'] ?? 0);
    $yearId = (int) ($_GET['year_id'] ?? 0);
    if ($classId <= 0) {
        echo json_encode(['success' => true, 'exams' => []]);
        exit;
    }

    $sql = "
        SELECT DISTINCT COALESCE(NULLIF(m.exam_name, ''), 'Term Exam') AS exam_name
        FROM marks m
        JOIN students s ON s.id = m.student_id
        WHERE s.class_id = ?
    ";
    $params = [$classId];
    if ($yearId > 0) {
        $sql .= " AND s.academic_year_id = ?";
        $params[] = $yearId;
    }
    $sql .= " ORDER BY exam_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exams = array_values(array_filter(array_map(static function ($row) {
        return trim((string) ($row['exam_name'] ?? ''));
    }, $stmt->fetchAll())));

    echo json_encode(['success' => true, 'exams' => $exams]);
    exit;
}

$notice = null;
if ($viewRequested && $filterClass === '') {
    $notice = ['type' => 'error', 'text' => 'Select a class and click View Analytics.'];
}

$examWhere = [];
$examParams = [];
if ($filterClass !== '') {
    $examWhere[] = 's.class_id = ?';
    $examParams[] = (int) $filterClass;
}
if ($filterYear !== '') {
    $examWhere[] = 's.academic_year_id = ?';
    $examParams[] = (int) $filterYear;
}
$examWhereSql = $examWhere ? ('WHERE ' . implode(' AND ', $examWhere)) : '';

$examOptions = [];
if ($filterClass !== '') {
    $examOptionsSql = "
        SELECT DISTINCT COALESCE(NULLIF(m.exam_name, ''), 'Term Exam') AS exam_name
        FROM marks m
        JOIN students s ON s.id = m.student_id
        $examWhereSql
        ORDER BY exam_name ASC
    ";
    $examOptionsStmt = $pdo->prepare($examOptionsSql);
    $examOptionsStmt->execute($examParams);
    $examOptions = array_values(array_filter(array_map(static function ($row) {
        return trim((string) ($row['exam_name'] ?? ''));
    }, $examOptionsStmt->fetchAll())));
}

$classAnalytics = [];
$registerByGroup = [];
$totalGroups = 0;
$totalStudents = 0;
$totalAssessments = 0;
$overallAvg = 0.0;

if ($canLoad) {
    $where = ['s.class_id = ?'];
    $params = [(int) $filterClass];

    if ($filterYear !== '') {
        $where[] = 's.academic_year_id = ?';
        $params[] = (int) $filterYear;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $marksJoinFilter = '';
    $marksJoinParams = [];
    if ($filterExam !== '') {
        $marksJoinFilter = " AND COALESCE(NULLIF(m.exam_name, ''), 'Term Exam') = ?";
        $marksJoinParams[] = $filterExam;
    }

    $analyticsSql = "
        SELECT
            c.id AS class_id,
            c.class_name,
            y.id AS year_id,
            y.year_name,
            COUNT(DISTINCT s.id) AS student_count,
            COUNT(DISTINCT COALESCE(NULLIF(m.exam_name, ''), 'Term Exam')) AS exam_coverage,
            COUNT(m.id) AS assessment_count,
            ROUND(AVG(CASE WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE NULL END), 2) AS avg_percentage,
            SUM(CASE WHEN m.max_marks > 0 AND ((m.marks_obtained / m.max_marks) * 100) >= 50 THEN 1 ELSE 0 END) AS pass_count,
            SUM(CASE WHEN m.max_marks > 0 AND ((m.marks_obtained / m.max_marks) * 100) < 50 THEN 1 ELSE 0 END) AS fail_count,
            ROUND(CASE WHEN COUNT(m.id) = 0 THEN 0 ELSE (SUM(CASE WHEN m.max_marks > 0 AND ((m.marks_obtained / m.max_marks) * 100) >= 50 THEN 1 ELSE 0 END) / COUNT(m.id)) * 100 END, 2) AS pass_rate
        FROM students s
        JOIN classes c ON c.id = s.class_id
        JOIN academic_years y ON y.id = s.academic_year_id
        LEFT JOIN marks m ON m.student_id = s.id$marksJoinFilter
        $whereSql
        GROUP BY c.id, c.class_name, y.id, y.year_name
        ORDER BY y.year_name DESC, c.class_name ASC
    ";
    $analyticsStmt = $pdo->prepare($analyticsSql);
    $analyticsStmt->execute(array_merge($marksJoinParams, $params));
    $classAnalytics = $analyticsStmt->fetchAll();

    foreach ($classAnalytics as &$entry) {
        $entry['avg_percentage'] = $entry['avg_percentage'] !== null ? (float) $entry['avg_percentage'] : 0.0;
        $entry['pass_rate'] = $entry['assessment_count'] > 0 ? (float) $entry['pass_rate'] : 0.0;
        $entry['pass_count'] = (int) ($entry['pass_count'] ?? 0);
        $entry['fail_count'] = (int) ($entry['fail_count'] ?? 0);
    }
    unset($entry);

    $registerSql = "
        SELECT
            c.id AS class_id,
            c.class_name,
            y.id AS year_id,
            y.year_name,
            s.id AS student_id,
            s.student_code,
            s.name AS student_name,
            COUNT(m.id) AS assessment_count,
            ROUND(AVG(CASE WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE NULL END), 2) AS avg_percentage,
            ROUND(MAX(CASE WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE NULL END), 2) AS best_percentage,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(NULLIF(m.exam_name, ''), 'Term Exam'),
                    ' / ',
                    m.subject_name,
                    ': ',
                    FORMAT(m.marks_obtained, 2),
                    '/',
                    FORMAT(m.max_marks, 2),
                    ' (',
                    FORMAT(CASE WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE 0 END, 1),
                    '%)'
                )
                ORDER BY m.exam_name, m.subject_name SEPARATOR ' | '
            ) AS marks_breakdown
        FROM students s
        JOIN classes c ON c.id = s.class_id
        JOIN academic_years y ON y.id = s.academic_year_id
        LEFT JOIN marks m ON m.student_id = s.id$marksJoinFilter
        $whereSql
        GROUP BY c.id, c.class_name, y.id, y.year_name, s.id, s.student_code, s.name
        ORDER BY y.year_name DESC, c.class_name ASC, s.name ASC
    ";
    $registerStmt = $pdo->prepare($registerSql);
    $registerStmt->execute(array_merge($marksJoinParams, $params));
    $registerRows = $registerStmt->fetchAll();

    foreach ($registerRows as $row) {
        $groupKey = (int) $row['class_id'] . ':' . (int) $row['year_id'];
        if (!isset($registerByGroup[$groupKey])) {
            $registerByGroup[$groupKey] = [];
        }

        $studentId = (int) ($row['student_id'] ?? 0);
        $studentCode = trim((string) ($row['student_code'] ?? ''));
        if ($studentCode === '' && $studentId > 0) {
            $studentCode = assign_student_code($pdo, $studentId);
        }

        $registerByGroup[$groupKey][] = [
            'student_id' => $studentId,
            'student_code' => $studentCode,
            'student_name' => (string) ($row['student_name'] ?? ''),
            'assessment_count' => (int) ($row['assessment_count'] ?? 0),
            'avg_percentage' => $row['avg_percentage'] !== null ? (float) $row['avg_percentage'] : 0.0,
            'best_percentage' => $row['best_percentage'] !== null ? (float) $row['best_percentage'] : 0.0,
            'marks_breakdown' => (string) ($row['marks_breakdown'] ?? '')
        ];
    }

    $totalGroups = count($classAnalytics);
    $totalStudents = array_sum(array_map(static fn($row) => (int) $row['student_count'], $classAnalytics));
    $totalAssessments = array_sum(array_map(static fn($row) => (int) $row['assessment_count'], $classAnalytics));
    $overallAvg = $totalGroups > 0
        ? round(array_sum(array_map(static fn($row) => (float) $row['avg_percentage'], $classAnalytics)) / $totalGroups, 1)
        : 0.0;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $canLoad) {
    $filename = 'class_analytics_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Academic Year',
        'Class',
        'Students',
        'Exam Coverage',
        'Assessments',
        'Average (%)',
        'Pass Rate (%)'
    ]);

    foreach ($classAnalytics as $entry) {
        fputcsv($out, [
            $entry['year_name'],
            $entry['class_name'],
            (int) $entry['student_count'],
            (int) $entry['exam_coverage'],
            (int) $entry['assessment_count'],
            number_format((float) $entry['avg_percentage'], 2, '.', ''),
            number_format((float) $entry['pass_rate'], 2, '.', '')
        ]);
    }

    fclose($out);
    exit;
}

$queryWith = static function (array $updates = [], array $remove = []): string {
    $query = $_GET;
    foreach ($remove as $key) {
        unset($query[$key]);
    }
    foreach ($updates as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return http_build_query($query);
};

$exportQuery = $queryWith(['export' => 'csv', 'view' => '1']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Analytics - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .analytics-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:0.75rem; }
        .analytics-card { border:1px solid var(--glass-border); border-radius:14px; padding:0.8rem; background:rgba(255,255,255,0.92); }
        .analytics-card h3 { font-size:0.95rem; margin-bottom:0.5rem; }
        .analytics-meta { display:flex; justify-content:space-between; gap:0.5rem; font-size:0.78rem; color:var(--text-muted); margin-bottom:0.55rem; }
        .pie-wrap { display:flex; align-items:center; gap:0.75rem; }
        .pie-donut { --p: 0; width:86px; height:86px; border-radius:50%; background:conic-gradient(#0d4f9e calc(var(--p) * 1%), #c70017 0); position:relative; flex-shrink:0; }
        .pie-donut::after { content:''; position:absolute; inset:13px; border-radius:50%; background:#fff; box-shadow: inset 0 0 0 1px rgba(15,23,42,0.06); }
        .pie-center { position:absolute; inset:0; display:grid; place-items:center; font-weight:700; font-size:0.8rem; color:#1f2937; z-index:1; }
        .pie-legend { display:grid; gap:0.25rem; font-size:0.8rem; color:var(--text-muted); }
        .legend-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:0.35rem; }
        .dot-pass { background:#0d4f9e; }
        .dot-fail { background:#c70017; }
        .register-group { margin-top:0.9rem; }
        .register-head { display:flex; justify-content:space-between; gap:0.6rem; flex-wrap:wrap; padding:0.7rem 0.85rem; border-bottom:1px solid var(--glass-border); background:rgba(15,23,42,0.03); }
        .register-head h3 { font-size:0.92rem; }
        .register-head span { font-size:0.78rem; color:var(--text-muted); font-weight:600; }
        .marks-cell { min-width:260px; max-width:520px; font-size:0.78rem; color:var(--text-muted); line-height:1.45; }
        .marks-list { list-style:none; display:grid; gap:0.18rem; }
        .marks-list li { padding:0.2rem 0.45rem; border-radius:8px; background:rgba(15,23,42,0.04); }
    </style>
</head>
<body>
    <div class="bg-mesh"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>

    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <a href="dashboard.php" class="sb-header"><img src="../logo.png" alt="BrightVision English Academy" class="sb-logo"></a>
            <nav class="sb-nav">
                <div class="sb-label">Analytics</div>
                <a href="dashboard.php" class="sb-link"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="class_analytics.php" class="sb-link active"><i class="fas fa-chart-column"></i> Class Analytics</a>
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
                <a href="profile.php" class="sb-link"><i class="fas fa-user-gear"></i> My Profile</a>
                <a href="../student_login.php" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout.php" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
            </nav>
            <div class="sb-profile"><div class="sb-avatar">A</div><div class="sb-profile-text"><strong><?= htmlspecialchars(admin_display_name()) ?></strong><span>System Manager</span></div></div>
        </aside>

        <div class="sb-overlay" id="sbOverlay"></div>

        <main class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-left"><button class="sb-toggle" id="sbToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button><h1>Class Analytics</h1></div>
                <div class="topbar-meta">
                    <?php if ($canLoad): ?><a href="?<?= htmlspecialchars($exportQuery) ?>" class="topbar-action"><i class="fas fa-file-csv"></i> Export CSV</a><?php endif; ?>
                    <span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span>
                </div>
            </div>

            <div class="admin-body dashboard-stack">
                <?php if ($notice): ?>
                    <div class="msg msg-error" style="display:flex;"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($notice['text']) ?></div>
                <?php endif; ?>

                <section class="dash-panel reveal d1">
                    <div class="panel-head"><h2><i class="fas fa-filter" style="color:var(--primary);"></i> Filter Scope</h2></div>
                    <div class="panel-body">
                        <form method="GET" class="filter-form">
                            <input type="hidden" name="view" value="1">
                            <div class="notion-form-group" style="flex:1;min-width:160px;">
                                <label class="notion-label" for="class_id">Class</label>
                                <select id="class_id" name="class_id" class="notion-select" required>
                                    <option value="">Select class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= (int) $class['id'] ?>" <?= (string) $filterClass === (string) $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:160px;">
                                <label class="notion-label" for="year_id">Academic Year</label>
                                <select id="year_id" name="year_id" class="notion-select">
                                    <option value="">All Years</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?= (int) $year['id'] ?>" <?= (string) $filterYear === (string) $year['id'] ? 'selected' : '' ?>><?= htmlspecialchars($year['year_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:160px;">
                                <label class="notion-label" for="exam_name">Exam</label>
                                <select id="exam_name" name="exam_name" class="notion-select">
                                    <option value="">All Exams</option>
                                    <?php foreach ($examOptions as $exam): ?>
                                        <option value="<?= htmlspecialchars($exam) ?>" <?= $filterExam === $exam ? 'selected' : '' ?>><?= htmlspecialchars($exam) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="notion-btn notion-btn-primary notion-btn-sm"><i class="fas fa-chart-column"></i> View Analytics</button>
                            <a href="class_analytics.php" class="notion-btn notion-btn-ghost notion-btn-sm">Clear</a>
                        </form>
                    </div>
                </section>

                <?php if (!$canLoad): ?>
                    <section class="dash-panel reveal d2"><div class="panel-body"><div class="empty-state"><i class="fas fa-circle-info"></i>Select class, year, exam and click View Analytics.</div></div></section>
                <?php else: ?>
                    <section class="metrics-grid reveal d2">
                        <article class="val-card"><div class="icon ic-blue"><i class="fas fa-layer-group"></i></div><div class="val-lbl">Class Groups</div><div class="val-num"><?= (int) $totalGroups ?></div></article>
                        <article class="val-card"><div class="icon ic-purple"><i class="fas fa-users"></i></div><div class="val-lbl">Students Covered</div><div class="val-num"><?= (int) $totalStudents ?></div></article>
                        <article class="val-card"><div class="icon ic-pink"><i class="fas fa-list-check"></i></div><div class="val-lbl">Assessments</div><div class="val-num"><?= (int) $totalAssessments ?></div></article>
                        <article class="val-card"><div class="icon ic-orange"><i class="fas fa-chart-line"></i></div><div class="val-lbl">Average Performance</div><div class="val-num"><?= number_format($overallAvg, 1) ?>%</div></article>
                    </section>

                    <section class="dash-panel reveal d3">
                        <div class="panel-head"><h2><i class="fas fa-table" style="color:var(--primary);"></i> Class Performance Table</h2></div>
                        <div class="panel-body" style="padding:0;">
                            <div class="table-scroll">
                                <table class="glass-table">
                                    <thead><tr><th>Academic Year</th><th>Class</th><th>Students</th><th>Exam Coverage</th><th>Assessments</th><th>Average</th><th>Pass Rate</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($classAnalytics as $entry): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($entry['year_name']) ?></td>
                                                <td style="font-weight:700;"><?= htmlspecialchars($entry['class_name']) ?></td>
                                                <td><?= (int) $entry['student_count'] ?></td>
                                                <td><?= (int) $entry['exam_coverage'] ?></td>
                                                <td><?= (int) $entry['assessment_count'] ?></td>
                                                <td><?= number_format((float) $entry['avg_percentage'], 1) ?>%</td>
                                                <td><?= number_format((float) $entry['pass_rate'], 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($classAnalytics)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);">No analytics found for this filter.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="dash-panel reveal d3">
                        <div class="panel-head"><h2><i class="fas fa-chart-pie" style="color:var(--primary);"></i> Pass vs Fail By Class</h2></div>
                        <div class="panel-body">
                            <?php if (!empty($classAnalytics)): ?>
                                <div class="analytics-grid">
                                    <?php foreach ($classAnalytics as $entry): ?>
                                        <?php $totalPie = (int) ($entry['pass_count'] ?? 0) + (int) ($entry['fail_count'] ?? 0); $passPct = $totalPie > 0 ? round(((int) $entry['pass_count'] / $totalPie) * 100, 1) : 0; ?>
                                        <article class="analytics-card">
                                            <h3><?= htmlspecialchars($entry['class_name']) ?> <span class="text-muted">(<?= htmlspecialchars($entry['year_name']) ?>)</span></h3>
                                            <div class="analytics-meta"><span>Students: <?= (int) $entry['student_count'] ?></span><span>Assessments: <?= (int) $entry['assessment_count'] ?></span></div>
                                            <div class="pie-wrap">
                                                <div class="pie-donut" style="--p: <?= number_format($passPct, 1, '.', '') ?>;"><div class="pie-center"><?= number_format($passPct, 1) ?>%</div></div>
                                                <div class="pie-legend"><div><span class="legend-dot dot-pass"></span>Pass: <?= (int) ($entry['pass_count'] ?? 0) ?></div><div><span class="legend-dot dot-fail"></span>Fail: <?= (int) ($entry['fail_count'] ?? 0) ?></div><div>Avg: <?= number_format((float) $entry['avg_percentage'], 1) ?>%</div></div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-chart-pie"></i>No chart data for this filter.</div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="dash-panel reveal d3">
                        <div class="panel-head"><h2><i class="fas fa-clipboard-list" style="color:var(--primary);"></i> Full Class Register & Marks</h2></div>
                        <div class="panel-body" style="padding:0;">
                            <?php if (!empty($classAnalytics)): ?>
                                <?php foreach ($classAnalytics as $entry): ?>
                                    <?php $key = (int) $entry['class_id'] . ':' . (int) $entry['year_id']; $rows = $registerByGroup[$key] ?? []; ?>
                                    <div class="register-group">
                                        <div class="register-head"><h3><?= htmlspecialchars($entry['class_name']) ?> - <?= htmlspecialchars($entry['year_name']) ?></h3><span><?= count($rows) ?> students</span></div>
                                        <div class="table-scroll">
                                            <table class="glass-table">
                                                <thead><tr><th>Student ID</th><th>Student</th><th>Assessments</th><th>Average</th><th>Best</th><th>Marks Register</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($rows as $row): ?>
                                                        <tr>
                                                            <td><span class="notion-tag tag-blue"><?= htmlspecialchars((string) ($row['student_code'] ?: 'Pending')) ?></span></td>
                                                            <td style="font-weight:700;"><?= htmlspecialchars($row['student_name']) ?></td>
                                                            <td><?= (int) $row['assessment_count'] ?></td>
                                                            <td><?= number_format((float) $row['avg_percentage'], 1) ?>%</td>
                                                            <td><?= number_format((float) $row['best_percentage'], 1) ?>%</td>
                                                            <td class="marks-cell">
                                                                <?php if ($row['marks_breakdown'] !== ''): ?>
                                                                    <?php $parts = array_filter(array_map('trim', explode(' | ', (string) $row['marks_breakdown']))); ?>
                                                                    <?php if (!empty($parts)): ?><ul class="marks-list"><?php foreach ($parts as $part): ?><li><?= htmlspecialchars($part) ?></li><?php endforeach; ?></ul><?php else: ?>-<?php endif; ?>
                                                                <?php else: ?>-<?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($rows)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No students in this class for selected filter.</td></tr><?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-users"></i>No register data for this filter.</div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
            <?php render_admin_footer($pdo); ?>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sbOverlay = document.getElementById('sbOverlay');
        const sbToggle = document.getElementById('sbToggle');
        const classSelect = document.getElementById('class_id');
        const yearSelect = document.getElementById('year_id');
        const examSelect = document.getElementById('exam_name');
        const selectedExamFromServer = <?= json_encode($filterExam, JSON_UNESCAPED_UNICODE) ?>;
        sbToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); sbOverlay.classList.toggle('show'); });
        sbOverlay?.addEventListener('click', () => { sidebar.classList.remove('open'); sbOverlay.classList.remove('show'); });

        async function refreshExamOptions(keepSelection = true) {
            if (!classSelect || !yearSelect || !examSelect) return;

            const classId = classSelect.value || '';
            const yearId = yearSelect.value || '';

            examSelect.innerHTML = '<option value="">All Exams</option>';
            if (!classId) {
                examSelect.disabled = true;
                return;
            }

            examSelect.disabled = false;
            const params = new URLSearchParams({ fetch_exams: '1', class_id: classId });
            if (yearId) params.set('year_id', yearId);

            try {
                const response = await fetch(`class_analytics.php?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data || !Array.isArray(data.exams)) return;

                data.exams.forEach((exam) => {
                    const option = document.createElement('option');
                    option.value = String(exam);
                    option.textContent = String(exam);
                    examSelect.appendChild(option);
                });

                if (keepSelection && selectedExamFromServer) {
                    const exists = Array.from(examSelect.options).some((opt) => opt.value === selectedExamFromServer);
                    if (exists) {
                        examSelect.value = selectedExamFromServer;
                    }
                }
            } catch (e) {
                // ignore fetch issues and keep fallback option
            }
        }

        classSelect?.addEventListener('change', () => refreshExamOptions(false));
        yearSelect?.addEventListener('change', () => refreshExamOptions(false));
        refreshExamOptions(true);
    </script>
</body>
</html>


