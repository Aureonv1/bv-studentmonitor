<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);

$has_percentage_column = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM marks LIKE 'percentage'");
    $has_percentage_column = $columnCheck && (bool) $columnCheck->fetch();
} catch (Throwable $e) {
    $has_percentage_column = false;
}

$percentage_expr = $has_percentage_column
    ? "CASE WHEN m.percentage IS NOT NULL THEN m.percentage WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE 0 END"
    : "CASE WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE 0 END";

$student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$class_count = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$year_count = $pdo->query("SELECT COUNT(*) FROM academic_years")->fetchColumn();
$exam_count = $pdo->query("SELECT COUNT(DISTINCT exam_name) FROM marks")->fetchColumn();

$top_students = $pdo->query("
    SELECT
        s.name,
        c.class_name,
        AVG($percentage_expr) AS avg_percentage
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    JOIN marks m ON s.id = m.student_id
    GROUP BY s.id
    ORDER BY avg_percentage DESC
    LIMIT 5
")->fetchAll();

$class_stats = $pdo->query("
    SELECT
        c.class_name,
        IFNULL(m.exam_name, 'Term Exam') AS exam_name,
        AVG($percentage_expr) AS avg_percentage
    FROM classes c
    JOIN students s ON c.id = s.class_id
    JOIN marks m ON s.id = m.student_id
    GROUP BY c.id, m.exam_name
    ORDER BY c.class_name, m.exam_name
")->fetchAll();

$class_names = [];
$exam_series = [];
foreach ($class_stats as $stat) {
    $class_name = $stat['class_name'];
    $exam_name = $stat['exam_name'];

    if (!in_array($class_name, $class_names, true)) {
        $class_names[] = $class_name;
    }

    if (!isset($exam_series[$exam_name])) {
        $exam_series[$exam_name] = [];
    }

    $exam_series[$exam_name][$class_name] = round((float) $stat['avg_percentage'], 2);
}

$chart_datasets = [];
$chart_colors = [
    'rgba(13,79,158,0.68)',
    'rgba(199,0,23,0.64)',
    'rgba(71,85,105,0.64)',
    'rgba(9,63,125,0.62)'
];
$color_index = 0;
foreach ($exam_series as $exam_name => $scores) {
    $points = [];
    foreach ($class_names as $class_name) {
        $points[] = $scores[$class_name] ?? 0;
    }

    $chart_datasets[] = [
        'label' => $exam_name,
        'data' => $points,
        'backgroundColor' => $chart_colors[$color_index % count($chart_colors)],
        'borderRadius' => 7,
        'borderSkipped' => false
    ];
    $color_index++;
}

$recent = $pdo->query("
    SELECT s.name, c.class_name, y.year_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN academic_years y ON s.academic_year_id = y.id
    ORDER BY s.id DESC
    LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="bg-mesh">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <a href="dashboard" class="sb-header">
                <img src="../logo.png" alt="BrightVision English Academy" class="sb-logo">
            </a>
            <nav class="sb-nav">
                <div class="sb-label">Analytics</div>
                <a href="dashboard" class="sb-link active"><i class="fas fa-chart-line"></i> Dashboard</a>
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
                <a href="profile" class="sb-link"><i class="fas fa-user-gear"></i> My Profile</a>
                <a href="../student_login" target="_blank" class="sb-link"><i class="fas fa-user-graduate"></i> Student Login</a>
                <a href="logout" class="sb-link" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Log out</a>
            </nav>
            <div class="sb-profile">
                <div class="sb-avatar">A</div>
                <div class="sb-profile-text">
                    <strong><?= htmlspecialchars(admin_display_name()) ?></strong>
                    <span>System Manager</span>
                </div>
            </div>
        </aside>

        <div class="sb-overlay" id="sbOverlay"></div>

        <main class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-left">
                    <button class="sb-toggle" id="sbToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
                    <h1>Platform Overview</h1>
                </div>
                <div class="topbar-meta">
                    <span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span>
                </div>
            </div>

            <div class="admin-body dashboard-stack">
                <section class="metrics-grid reveal d1">
                    <article class="val-card">
                        <div class="icon ic-blue"><i class="fas fa-users"></i></div>
                        <div class="val-lbl">Total Enrolled</div>
                        <div class="val-num"><?= (int) $student_count ?></div>
                        <a href="manage_students" class="val-link">View records <i class="fas fa-arrow-right"></i></a>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-purple"><i class="fas fa-layer-group"></i></div>
                        <div class="val-lbl">Active Classes</div>
                        <div class="val-num"><?= (int) $class_count ?></div>
                        <a href="manage_students" class="val-link">Manage classes <i class="fas fa-arrow-right"></i></a>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-orange"><i class="fas fa-file-lines"></i></div>
                        <div class="val-lbl">Exams Indexed</div>
                        <div class="val-num"><?= (int) $exam_count ?></div>
                        <a href="import_csv" class="val-link">Import results <i class="fas fa-arrow-right"></i></a>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-pink"><i class="fas fa-calendar"></i></div>
                        <div class="val-lbl">Academic Years</div>
                        <div class="val-num"><?= (int) $year_count ?></div>
                        <a href="manage_academics" class="val-link">Manage cycle data <i class="fas fa-arrow-right"></i></a>
                    </article>
                </section>

                <section class="grid-2 reveal d2">
                    <article class="dash-panel">
                        <div class="panel-head">
                            <h2><i class="fas fa-chart-column" style="color:var(--primary);"></i> Class Performance</h2>
                        </div>
                        <div class="panel-body" style="min-height:300px;">
                            <?php if (count($class_names) > 0): ?>
                                <canvas id="classChart"></canvas>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-chart-simple"></i> No class statistics available yet.</div>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="dash-panel">
                        <div class="panel-head">
                            <h2><i class="fas fa-trophy" style="color:var(--accent);"></i> Top Students (Avg %)</h2>
                        </div>
                        <div class="panel-body">
                            <ul class="rank-list">
                                <?php foreach ($top_students as $index => $student): ?>
                                    <?php $rank_class = $index < 3 ? 'r-' . ($index + 1) : 'r-x'; ?>
                                    <li class="rank-item">
                                        <div class="r-badge <?= $rank_class ?>"><?= $index + 1 ?></div>
                                        <div class="r-info">
                                            <span class="r-name"><?= htmlspecialchars($student['name']) ?></span>
                                            <span class="r-cls"><?= htmlspecialchars($student['class_name'] ?? '-') ?></span>
                                        </div>
                                        <div class="r-score"><?= number_format((float) $student['avg_percentage'], 1) ?>%</div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (empty($top_students)): ?>
                                <div class="empty-state">No ranking data available.</div>
                            <?php endif; ?>
                        </div>
                    </article>
                </section>

                <section class="dash-panel reveal d3">
                    <div class="panel-head">
                        <h2><i class="fas fa-clock-rotate-left" style="color:var(--accent);"></i> Recent Onboarding</h2>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <div class="table-scroll">
                            <table class="glass-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Academic Year</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $entry): ?>
                                        <tr>
                                            <td style="font-weight:700;"><?= htmlspecialchars($entry['name']) ?></td>
                                            <td><span class="badge badge-blue"><?= htmlspecialchars($entry['class_name'] ?? '-') ?></span></td>
                                            <td><?= htmlspecialchars($entry['year_name'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align:center;color:var(--text-muted);">No recent student records.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sbOverlay = document.getElementById('sbOverlay');
        const sbToggle = document.getElementById('sbToggle');

        sbToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            sbOverlay.classList.toggle('show');
        });

        sbOverlay?.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sbOverlay.classList.remove('show');
        });

        const rootStyle = getComputedStyle(document.documentElement);
        Chart.defaults.color = rootStyle.getPropertyValue('--text-muted').trim() || '#5b6578';
        Chart.defaults.font.family = rootStyle.getPropertyValue('--font-body').trim() || 'Plus Jakarta Sans';

        <?php if (count($class_names) > 0): ?>
        new Chart(document.getElementById('classChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($class_names) ?>,
                datasets: <?= json_encode($chart_datasets) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        grid: { color: 'rgba(15,23,42,0.08)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>


