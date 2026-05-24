<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'maintenance_mode.php';
require_once 'config.php';
require_once 'student_auth.php';
require_once 'footer.php';

if (is_maintenance_enabled()) {
    header('Location: ' . student_url('student_login'));
    exit;
}

require_student_login($pdo);

$studentId = (int) ($_SESSION['student_id'] ?? 0);

$studentStmt = $pdo->prepare("
    SELECT
        s.id,
        s.name,
        c.class_name,
        y.year_name,
        sa.username
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN academic_years y ON y.id = s.academic_year_id
    LEFT JOIN student_accounts sa ON sa.student_id = s.id
    WHERE s.id = ?
    LIMIT 1
");
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch();

if (!$student) {
    header('Location: ' . student_url('student_logout'));
    exit;
}

$marksStmt = $pdo->prepare("
    SELECT
        exam_name,
        subject_name,
        marks_obtained,
        max_marks,
        ROUND(CASE WHEN max_marks > 0 THEN (marks_obtained / max_marks) * 100 ELSE 0 END, 2) AS percentage
    FROM marks
    WHERE student_id = ?
    ORDER BY exam_name ASC, subject_name ASC
");
$marksStmt->execute([$studentId]);
$marks = $marksStmt->fetchAll();

$groupedExams = [];
$totalPercent = 0.0;
$totalAssessments = 0;

foreach ($marks as $mark) {
    $examName = trim((string) ($mark['exam_name'] ?? ''));
    if ($examName === '') {
        $examName = 'Term Exam';
    }

    if (!isset($groupedExams[$examName])) {
        $groupedExams[$examName] = [
            'rows' => [],
            'sum_percent' => 0.0,
            'count' => 0
        ];
    }

    $percent = (float) ($mark['percentage'] ?? 0);
    $groupedExams[$examName]['rows'][] = [
        'subject_name' => (string) ($mark['subject_name'] ?? ''),
        'marks_obtained' => (float) ($mark['marks_obtained'] ?? 0),
        'max_marks' => (float) ($mark['max_marks'] ?? 100),
        'percentage' => $percent
    ];

    $groupedExams[$examName]['sum_percent'] += $percent;
    $groupedExams[$examName]['count']++;

    $totalPercent += $percent;
    $totalAssessments++;
}

$examNames = [];
$examAverages = [];
$examSummaries = [];

foreach ($groupedExams as $examName => $examData) {
    $avg = $examData['count'] > 0
        ? round($examData['sum_percent'] / $examData['count'], 1)
        : 0.0;

    $examNames[] = $examName;
    $examAverages[] = $avg;
    $examSummaries[] = [
        'name' => $examName,
        'average' => $avg,
        'rows' => $examData['rows']
    ];
}

$overallAverage = $totalAssessments > 0 ? round($totalPercent / $totalAssessments, 1) : 0.0;

$gradeFromScore = static function (float $score): array {
    if ($score >= 75) {
        return ['label' => 'A', 'class' => 'grade-A'];
    }
    if ($score >= 65) {
        return ['label' => 'B', 'class' => 'grade-B'];
    }
    if ($score >= 50) {
        return ['label' => 'C', 'class' => 'grade-C'];
    }
    if ($score >= 35) {
        return ['label' => 'D', 'class' => 'grade-D'];
    }
    return ['label' => 'F', 'class' => 'grade-F'];
};

$overallGrade = $gradeFromScore($overallAverage);

$bestExamLabel = '-';
$bestExamScore = 0.0;
if (!empty($examAverages)) {
    $bestExamScore = max($examAverages);
    $bestIndex = array_search($bestExamScore, $examAverages, true);
    if ($bestIndex !== false && isset($examNames[$bestIndex])) {
        $bestExamLabel = $examNames[$bestIndex];
    }
}

$recentActivities = [];
$activityPalette = [
    ['icon' => 'fa-chart-line', 'tone' => 'blue'],
    ['icon' => 'fa-medal', 'tone' => 'gold'],
    ['icon' => 'fa-book-open', 'tone' => 'purple'],
    ['icon' => 'fa-sparkles', 'tone' => 'pink']
];
foreach (array_reverse($examSummaries) as $index => $examSummary) {
    $palette = $activityPalette[$index % count($activityPalette)];
    $recentActivities[] = [
        'icon' => $palette['icon'],
        'tone' => $palette['tone'],
        'title' => (string) $examSummary['name'],
        'meta' => count($examSummary['rows']) . ' subjects analysed',
        'value' => number_format((float) $examSummary['average'], 1) . '%'
    ];
    if (count($recentActivities) >= 4) {
        break;
    }
}

$footerSettings = get_site_footer_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Progress - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="icon.png?v=20260424">
    <link rel="shortcut icon" href="icon.png?v=20260424">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="profile-dashboard-body">
    <div class="bg-mesh no-print">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <nav class="glass-nav glass-panel no-print">
        <a href="student_profile.php" class="nav-brand logo-only">
            <img src="logo.png" alt="BrightVision English Academy">
        </a>
        <div class="nav-links">
            <a href="student_logout.php" class="nav-link"><i class="fas fa-right-from-bracket"></i> Logout</a>
        </div>
    </nav>

    <main class="portal-container profile-stage reveal d1">
        <section class="glass-panel profile-hero-card profile-dashboard-hero reveal d2">
            <div class="profile-hero-orb profile-hero-orb-a"></div>
            <div class="profile-hero-orb profile-hero-orb-b"></div>
            <div class="profile-hero-grid profile-dashboard-hero-grid">
                <div class="profile-hero-copy profile-dashboard-copy">
                    <span class="hero-note">Welcome back,</span>
                    <h1 class="profile-name"><?= htmlspecialchars((string) $student['name']) ?></h1>
                    <p class="profile-welcome">Stay consistent, keep improving, and track your academic growth in one place.</p>
                    <div class="result-subline profile-dashboard-badges">
                        <span class="badge badge-blue"><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars((string) ($student['class_name'] ?? '-')) ?></span>
                        <span class="badge badge-purple"><i class="fas fa-calendar-days"></i> <?= htmlspecialchars((string) ($student['year_name'] ?? '-')) ?></span>
                        <span class="badge"><i class="fas fa-user"></i> <?= htmlspecialchars((string) ($student['username'] ?? '')) ?></span>
                    </div>
                </div>
                <div class="profile-dashboard-grade-wrap no-print">
                    <div class="profile-dashboard-grade-card <?= htmlspecialchars($overallGrade['class']) ?>">
                        <span>Current Grade</span>
                        <strong><?= htmlspecialchars($overallGrade['label']) ?></strong>
                        <small><i class="fas fa-wand-magic-sparkles"></i> Keep pushing!</small>
                    </div>
                </div>
            </div>
        </section>

        <?php if (empty($examSummaries)): ?>
            <div class="msg msg-info" style="display:flex;">
                <i class="fas fa-circle-info"></i>
                No marks are available for your account yet. Please check back later.
            </div>
        <?php else: ?>
            <section class="summary-grid profile-summary-grid profile-reference-stats reveal d2" id="summaryRow">
                <article class="summary-card profile-stat-card profile-ref-card profile-ref-blue">
                    <div class="profile-ref-card-top">
                        <div class="profile-stat-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="summary-val"><?= count($examSummaries) ?></div>
                    </div>
                    <div class="summary-lbl">Terms Taken</div>
                    <div class="profile-ref-wave profile-ref-wave-blue"></div>
                </article>
                <article class="summary-card profile-stat-card profile-ref-card profile-ref-pink">
                    <div class="profile-ref-card-top">
                        <div class="profile-stat-icon"><i class="fas fa-list-check"></i></div>
                        <div class="summary-val"><?= (int) $totalAssessments ?></div>
                    </div>
                    <div class="summary-lbl">Assessments</div>
                    <div class="profile-ref-wave profile-ref-wave-pink"></div>
                </article>
                <article class="summary-card profile-stat-card profile-ref-card profile-ref-blue-soft">
                    <div class="profile-ref-card-top">
                        <div class="profile-stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="summary-val"><?= number_format($overallAverage, 1) ?>%</div>
                    </div>
                    <div class="summary-lbl">Overall Average</div>
                    <div class="profile-ref-wave profile-ref-wave-blue-soft"></div>
                </article>
                <article class="summary-card profile-stat-card profile-stat-grade profile-ref-card profile-ref-gold">
                    <div class="profile-ref-card-top profile-ref-grade-top">
                        <div class="profile-stat-icon"><i class="fas fa-award"></i></div>
                        <div class="summary-val"><?= htmlspecialchars($overallGrade['label']) ?></div>
                    </div>
                    <div class="summary-lbl">Overall Grade</div>
                    <div class="profile-ref-wave profile-ref-wave-gold"></div>
                </article>
            </section>

            <section class="profile-dashboard-lower reveal d3">
                <div class="glass-panel chart-panel profile-chart-panel profile-dashboard-chart">
                    <div class="chart-head">
                        <h3>Exam Trend</h3>
                        <span class="chart-note"><?= count($examSummaries) ?> exams - Best: <?= htmlspecialchars($bestExamLabel) ?> (<?= number_format($bestExamScore, 1) ?>%)</span>
                    </div>
                    <div class="chart-track profile-dashboard-chart-track">
                        <canvas id="studentChart"></canvas>
                    </div>
                </div>

                <aside class="glass-panel profile-dashboard-activity">
                    <div class="profile-dashboard-side-head">
                        <h3>Recent Activity</h3>
                        <a href="#profileDetails" class="profile-dashboard-viewall">View All</a>
                    </div>
                    <div class="profile-activity-list">
                        <?php foreach ($recentActivities as $activity): ?>
                            <article class="profile-activity-item">
                                <div class="profile-activity-icon tone-<?= htmlspecialchars($activity['tone']) ?>"><i class="fas <?= htmlspecialchars($activity['icon']) ?>"></i></div>
                                <div class="profile-activity-copy">
                                    <strong><?= htmlspecialchars($activity['title']) ?></strong>
                                    <span><?= htmlspecialchars($activity['meta']) ?></span>
                                </div>
                                <div class="profile-activity-value"><?= htmlspecialchars($activity['value']) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </section>

            <section class="profile-dashboard-details profile-exam-list" id="profileDetails">
                <?php foreach ($examSummaries as $exam): ?>
                    <article class="glass-panel exam-card profile-exam-card reveal d3">
                        <div class="exam-head">
                            <h3><?= htmlspecialchars($exam['name']) ?></h3>
                            <span>Average: <strong><?= number_format((float) $exam['average'], 1) ?>%</strong></span>
                        </div>
                        <div class="table-scroll">
                            <table class="glass-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Percent</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam['rows'] as $row): ?>
                                        <?php $rowGrade = $gradeFromScore((float) $row['percentage']); ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                            <td style="font-weight:700;"><?= number_format((float) $row['marks_obtained'], 2) ?> / <?= number_format((float) $row['max_marks'], 2) ?></td>
                                            <td><?= number_format((float) $row['percentage'], 1) ?>%</td>
                                            <td><span class="badge <?= htmlspecialchars($rowGrade['class']) ?>" style="color:#fff;"><?= htmlspecialchars($rowGrade['label']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php render_portal_footer($footerSettings, 'no-print'); ?>

    <script>
        const examLabels = <?= json_encode(array_values($examNames), JSON_UNESCAPED_UNICODE) ?>;
        const examValues = <?= json_encode(array_values($examAverages), JSON_NUMERIC_CHECK) ?>;
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function renderTrendChart(labels, values, animate = true) {
            const canvas = document.getElementById('studentChart');
            if (!canvas || !Array.isArray(labels) || !labels.length) {
                return;
            }

            const isMobile = window.innerWidth <= 640;
            const width = Math.max(320, canvas.parentElement?.clientWidth || 680);
            const height = Math.max(220, isMobile ? 230 : 250);
            const ratio = Math.min(1.5, window.devicePixelRatio || 1);

            canvas.width = Math.floor(width * ratio);
            canvas.height = Math.floor(height * ratio);
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return;
            }

            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

            const leftPad = isMobile ? 34 : 46;
            const rightPad = 14;
            const topPad = 14;
            const bottomPad = isMobile ? 48 : 34;
            const chartW = Math.max(120, width - leftPad - rightPad);
            const chartH = Math.max(100, height - topPad - bottomPad);
            const baselineY = topPad + chartH;

            const yMin = 0;
            const yMax = 100;
            const ySteps = 4;

            const points = values.map((value, index) => {
                const x = labels.length === 1
                    ? leftPad + chartW / 2
                    : leftPad + (chartW * index / (labels.length - 1));
                const clamped = Math.max(yMin, Math.min(yMax, Number(value) || 0));
                const y = topPad + chartH - ((clamped - yMin) / (yMax - yMin)) * chartH;
                return { x, y, value: clamped };
            });

            if (!points.length) {
                return;
            }

            const drawScene = (progress) => {
                ctx.clearRect(0, 0, width, height);

                ctx.font = `${isMobile ? 11 : 12}px Segoe UI, Verdana, Tahoma, sans-serif`;
                ctx.fillStyle = '#5b6578';
                ctx.textAlign = 'right';
                ctx.textBaseline = 'middle';

                for (let i = 0; i <= ySteps; i++) {
                    const y = topPad + chartH - (chartH * i / ySteps);
                    const value = Math.round(yMin + ((yMax - yMin) * i / ySteps));

                    ctx.strokeStyle = 'rgba(15, 23, 42, 0.11)';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(leftPad, y);
                    ctx.lineTo(leftPad + chartW, y);
                    ctx.stroke();

                    ctx.fillText(String(value), leftPad - 6, y);
                }

                const animatedPoints = points.map((point) => ({
                    x: point.x,
                    y: baselineY - ((baselineY - point.y) * progress),
                    value: point.value
                }));

                const gradientFill = ctx.createLinearGradient(0, topPad, 0, topPad + chartH);
                gradientFill.addColorStop(0, 'rgba(29,99,223,0.26)');
                gradientFill.addColorStop(1, 'rgba(29,99,223,0.08)');

                ctx.beginPath();
                animatedPoints.forEach((p, idx) => {
                    if (idx === 0) {
                        ctx.moveTo(p.x, p.y);
                    } else {
                        ctx.lineTo(p.x, p.y);
                    }
                });
                ctx.lineTo(animatedPoints[animatedPoints.length - 1].x, baselineY);
                ctx.lineTo(animatedPoints[0].x, baselineY);
                ctx.closePath();
                ctx.fillStyle = gradientFill;
                ctx.fill();

                ctx.strokeStyle = '#1d63df';
                ctx.lineWidth = isMobile ? 2.2 : 2.8;
                ctx.lineJoin = 'round';
                ctx.lineCap = 'round';
                ctx.beginPath();
                animatedPoints.forEach((p, idx) => {
                    if (idx === 0) {
                        ctx.moveTo(p.x, p.y);
                    } else {
                        ctx.lineTo(p.x, p.y);
                    }
                });
                ctx.stroke();

                const pointOpacity = Math.max(0.3, progress);
                animatedPoints.forEach((point, idx) => {
                    ctx.globalAlpha = pointOpacity;
                    ctx.beginPath();
                    ctx.fillStyle = '#1d63df';
                    ctx.arc(point.x, point.y, isMobile ? 3 : 4, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.strokeStyle = '#fff';
                    ctx.lineWidth = 1.7;
                    ctx.stroke();
                    ctx.globalAlpha = 1;
                });

                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillStyle = '#6f80a0';
                ctx.font = `${isMobile ? 10 : 12}px Segoe UI, Verdana, Tahoma, sans-serif`;

                points.forEach((point, idx) => {
                    let label = `Exam ${idx + 1}`;
                    ctx.fillText(label, point.x, baselineY + 10);
                });
            };

            if (!animate || prefersReducedMotion) {
                drawScene(1);
                return;
            }

            const start = performance.now();
            const duration = 780;

            const tick = (now) => {
                const elapsed = now - start;
                const t = Math.min(1, elapsed / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                drawScene(eased);
                if (t < 1) {
                    requestAnimationFrame(tick);
                }
            };

            requestAnimationFrame(tick);
        }

        function enableHeroTilt() {
            return;
        }

        if (examLabels.length) {
            renderTrendChart(examLabels, examValues, false);
            window.addEventListener('resize', () => {
                clearTimeout(window.__studentChartTimer);
                window.__studentChartTimer = setTimeout(() => {
                    renderTrendChart(examLabels, examValues, false);
                }, 160);
            });
        }

        enableHeroTilt();
    </script>
</body>
</html>





