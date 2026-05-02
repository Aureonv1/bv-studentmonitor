<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_marks');

$set_flash = static function (string $type, string $text): void {
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'text' => $text
    ];
};

$redirect_back = static function (string $returnQuery = ''): void {
    $target = 'manage_marks.php';
    if ($returnQuery !== '') {
        $target .= '?' . $returnQuery;
    }
    header('Location: ' . $target);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnQuery = trim($_POST['return_query'] ?? '');

    try {
        switch ($action) {
            case 'add_mark':
            case 'update_mark':
                $studentId = (int) ($_POST['student_id'] ?? 0);
                $examName = trim($_POST['exam_name'] ?? '');
                $subjectName = trim($_POST['subject_name'] ?? '');
                $markRaw = trim($_POST['marks_obtained'] ?? '');
                $maxRaw = trim($_POST['max_marks'] ?? '100');

                if ($studentId <= 0 || $examName === '' || $subjectName === '' || $markRaw === '' || $maxRaw === '') {
                    throw new RuntimeException('Student, exam, subject, score, and max marks are required.');
                }
                if (!is_numeric($markRaw) || !is_numeric($maxRaw)) {
                    throw new RuntimeException('Score and max marks must be valid numbers.');
                }

                $markValue = (float) $markRaw;
                $maxValue = (float) $maxRaw;
                if ($maxValue <= 0) {
                    throw new RuntimeException('Max marks must be greater than zero.');
                }
                if ($markValue < 0) {
                    throw new RuntimeException('Score cannot be negative.');
                }

                if ($action === 'add_mark') {
                    $findStmt = $pdo->prepare("
                        SELECT id
                        FROM marks
                        WHERE student_id = ? AND exam_name = ? AND subject_name = ?
                        LIMIT 1
                    ");
                    $findStmt->execute([$studentId, $examName, $subjectName]);
                    $existingId = (int) $findStmt->fetchColumn();

                    if ($existingId > 0) {
                        $pdo->prepare("UPDATE marks SET marks_obtained = ?, max_marks = ? WHERE id = ?")
                            ->execute([$markValue, $maxValue, $existingId]);
                        $set_flash('success', 'Existing subject mark updated for this exam.');
                    } else {
                        $pdo->prepare("
                            INSERT INTO marks (student_id, exam_name, subject_name, marks_obtained, max_marks)
                            VALUES (?, ?, ?, ?, ?)
                        ")->execute([$studentId, $examName, $subjectName, $markValue, $maxValue]);
                        $set_flash('success', 'Mark added successfully.');
                    }
                } else {
                    $markId = (int) ($_POST['mark_id'] ?? 0);
                    if ($markId <= 0) {
                        throw new RuntimeException('Invalid mark selected for update.');
                    }
                    $pdo->prepare("
                        UPDATE marks
                        SET student_id = ?, exam_name = ?, subject_name = ?, marks_obtained = ?, max_marks = ?
                        WHERE id = ?
                    ")->execute([$studentId, $examName, $subjectName, $markValue, $maxValue, $markId]);
                    $set_flash('success', 'Mark updated successfully.');
                }
                break;

            case 'delete_mark':
                $markId = (int) ($_POST['mark_id'] ?? 0);
                if ($markId <= 0) {
                    throw new RuntimeException('Invalid mark selected for deletion.');
                }
                $pdo->prepare("DELETE FROM marks WHERE id = ?")->execute([$markId]);
                $set_flash('success', 'Mark deleted.');
                break;

            case 'bulk_delete_exam':
                $examName = trim($_POST['bulk_exam_name'] ?? '');
                $bulkClassId = trim($_POST['bulk_class_id'] ?? '');
                $bulkYearId = trim($_POST['bulk_year_id'] ?? '');

                if ($examName === '') {
                    throw new RuntimeException('Select an exam name to remove.');
                }

                $sql = "
                    DELETE m
                    FROM marks m
                    JOIN students s ON s.id = m.student_id
                    WHERE m.exam_name = ?
                ";
                $params = [$examName];

                if ($bulkClassId !== '') {
                    $sql .= " AND s.class_id = ?";
                    $params[] = (int) $bulkClassId;
                }
                if ($bulkYearId !== '') {
                    $sql .= " AND s.academic_year_id = ?";
                    $params[] = (int) $bulkYearId;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $set_flash('success', $stmt->rowCount() . ' mark record(s) deleted.');
                break;

            default:
                throw new RuntimeException('Unknown marks action.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $set_flash('error', 'This mark already exists for the selected student/exam/subject.');
        } else {
            $set_flash('error', 'Database error: ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        $set_flash('error', $e->getMessage());
    }

    $redirect_back($returnQuery);
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$searchStudent = trim($_GET['search'] ?? '');
$filterClass = trim($_GET['class_id'] ?? '');
$filterYear = trim($_GET['year_id'] ?? '');
$filterStudent = trim($_GET['student_id'] ?? '');
$filterExam = trim($_GET['exam_name'] ?? '');

$query_with = static function (array $updates = [], array $remove = []): string {
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

$baseReturnQuery = $query_with([], ['edit_mark']);
$baseReturnUrl = 'manage_marks.php' . ($baseReturnQuery !== '' ? '?' . $baseReturnQuery : '');

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();
$years = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC")->fetchAll();

$studentOptionSql = "
    SELECT s.id, s.name, c.class_name, y.year_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN academic_years y ON s.academic_year_id = y.id
    WHERE 1 = 1
";
$studentOptionParams = [];
if ($filterClass !== '') {
    $studentOptionSql .= " AND s.class_id = ?";
    $studentOptionParams[] = (int) $filterClass;
}
if ($filterYear !== '') {
    $studentOptionSql .= " AND s.academic_year_id = ?";
    $studentOptionParams[] = (int) $filterYear;
}
$studentOptionSql .= " ORDER BY s.name ASC LIMIT 500";
$studentOptionStmt = $pdo->prepare($studentOptionSql);
$studentOptionStmt->execute($studentOptionParams);
$studentOptions = $studentOptionStmt->fetchAll();

$examOptionSql = "
    SELECT DISTINCT m.exam_name
    FROM marks m
    JOIN students s ON s.id = m.student_id
    WHERE 1 = 1
";
$examOptionParams = [];
if ($filterClass !== '') {
    $examOptionSql .= " AND s.class_id = ?";
    $examOptionParams[] = (int) $filterClass;
}
if ($filterYear !== '') {
    $examOptionSql .= " AND s.academic_year_id = ?";
    $examOptionParams[] = (int) $filterYear;
}
if ($filterStudent !== '') {
    $examOptionSql .= " AND s.id = ?";
    $examOptionParams[] = (int) $filterStudent;
}
$examOptionSql .= " ORDER BY m.exam_name ASC";
$examOptionStmt = $pdo->prepare($examOptionSql);
$examOptionStmt->execute($examOptionParams);
$examOptions = $examOptionStmt->fetchAll(PDO::FETCH_COLUMN);

$marksSql = "
    SELECT
        m.id,
        m.student_id,
        m.exam_name,
        m.subject_name,
        m.marks_obtained,
        m.max_marks,
        ROUND(CASE WHEN m.max_marks > 0 THEN (m.marks_obtained / m.max_marks) * 100 ELSE 0 END, 2) AS percentage,
        s.name AS student_name,
        c.class_name,
        y.year_name
    FROM marks m
    JOIN students s ON s.id = m.student_id
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN academic_years y ON y.id = s.academic_year_id
    WHERE 1 = 1
";
$marksParams = [];

if ($searchStudent !== '') {
    $marksSql .= " AND s.name LIKE ?";
    $marksParams[] = '%' . $searchStudent . '%';
}
if ($filterClass !== '') {
    $marksSql .= " AND s.class_id = ?";
    $marksParams[] = (int) $filterClass;
}
if ($filterYear !== '') {
    $marksSql .= " AND s.academic_year_id = ?";
    $marksParams[] = (int) $filterYear;
}
if ($filterStudent !== '') {
    $marksSql .= " AND s.id = ?";
    $marksParams[] = (int) $filterStudent;
}
if ($filterExam !== '') {
    $marksSql .= " AND m.exam_name = ?";
    $marksParams[] = $filterExam;
}

$marksSql .= "
    ORDER BY m.exam_name ASC, s.name ASC, m.subject_name ASC
    LIMIT 500
";
$marksStmt = $pdo->prepare($marksSql);
$marksStmt->execute($marksParams);
$marks = $marksStmt->fetchAll();

$totals = [
    'marks' => (int) $pdo->query("SELECT COUNT(*) FROM marks")->fetchColumn(),
    'exams' => (int) $pdo->query("SELECT COUNT(DISTINCT exam_name) FROM marks")->fetchColumn(),
    'students' => (int) $pdo->query("SELECT COUNT(DISTINCT student_id) FROM marks")->fetchColumn(),
    'avg' => round((float) ($pdo->query("SELECT AVG(CASE WHEN max_marks > 0 THEN (marks_obtained / max_marks) * 100 ELSE 0 END) FROM marks")->fetchColumn() ?? 0), 1)
];

$editMark = null;
$editMarkId = (int) ($_GET['edit_mark'] ?? 0);
if ($editMarkId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, student_id, exam_name, subject_name, marks_obtained, max_marks
        FROM marks
        WHERE id = ?
    ");
    $stmt->execute([$editMarkId]);
    $editMark = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Manager - BrightVision</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
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
                <a href="dashboard" class="sb-link"><i class="fas fa-chart-line"></i> Dashboard</a>
                <?php if (admin_can('view_analytics')): ?><a href="class_analytics" class="sb-link"><i class="fas fa-chart-column"></i> Class Analytics</a><?php endif; ?>

                <div class="sb-label">Management</div>
                <?php if (admin_can('manage_students')): ?>
                    <a href="manage_students" class="sb-link"><i class="fas fa-database"></i> Data Manager</a>
                    <a href="student_credentials" class="sb-link"><i class="fas fa-id-card"></i> Student Credentials</a>
                    <a href="manage_academics" class="sb-link"><i class="fas fa-graduation-cap"></i> Academics</a>
                    <a href="export_student_ids" class="sb-link"><i class="fas fa-address-card"></i> Export Student IDs</a>
                <?php endif; ?>
                <a href="manage_marks" class="sb-link active"><i class="fas fa-pen-to-square"></i> Marks Manager</a>
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
                    <h1>Marks Management</h1>
                </div>
                <div class="topbar-meta">
                    <span class="topbar-pill"><i class="fas fa-book-open"></i> <?= (int) $totals['marks'] ?> Records</span>
                    <span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span>
                </div>
            </div>

            <div class="admin-body dashboard-stack">
                <?php if ($flash): ?>
                    <div class="msg <?= $flash['type'] === 'error' ? 'msg-error' : 'msg-success' ?>" style="<?= $flash['type'] === 'error' ? 'display:flex;' : '' ?>">
                        <i class="fas <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i>
                        <?= htmlspecialchars($flash['text']) ?>
                    </div>
                <?php endif; ?>

                <section class="metrics-grid reveal d1">
                    <article class="val-card">
                        <div class="icon ic-blue"><i class="fas fa-book-open"></i></div>
                        <div class="val-lbl">Total Marks</div>
                        <div class="val-num"><?= (int) $totals['marks'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-purple"><i class="fas fa-file-lines"></i></div>
                        <div class="val-lbl">Exams</div>
                        <div class="val-num"><?= (int) $totals['exams'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-pink"><i class="fas fa-users"></i></div>
                        <div class="val-lbl">Students With Marks</div>
                        <div class="val-num"><?= (int) $totals['students'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-orange"><i class="fas fa-chart-line"></i></div>
                        <div class="val-lbl">Average Percentage</div>
                        <div class="val-num"><?= number_format((float) $totals['avg'], 1) ?>%</div>
                    </article>
                </section>

                <section class="management-grid reveal d2">
                    <article class="dash-panel">
                        <div class="panel-head">
                            <h2><i class="fas fa-square-plus" style="color:var(--primary);"></i> <?= $editMark ? 'Edit Mark' : 'Add Mark' ?></h2>
                            <?php if ($editMark): ?>
                                <a href="<?= htmlspecialchars($baseReturnUrl) ?>" class="notion-btn notion-btn-ghost notion-btn-sm">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                        <div class="panel-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="<?= $editMark ? 'update_mark' : 'add_mark' ?>">
                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                <?php if ($editMark): ?>
                                    <input type="hidden" name="mark_id" value="<?= (int) $editMark['id'] ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="student_id">Student</label>
                                    <select id="student_id" name="student_id" class="form-control" required>
                                        <option value="">Select student...</option>
                                        <?php foreach ($studentOptions as $student): ?>
                                            <?php $selectedStudent = (string) ($editMark['student_id'] ?? '') === (string) $student['id']; ?>
                                            <option value="<?= (int) $student['id'] ?>" <?= $selectedStudent ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($student['name']) ?> - <?= htmlspecialchars($student['class_name'] ?? '-') ?> / <?= htmlspecialchars($student['year_name'] ?? '-') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="exam_name">Exam Name</label>
                                        <input type="text" id="exam_name" name="exam_name" class="form-control" value="<?= htmlspecialchars($editMark['exam_name'] ?? '') ?>" placeholder="e.g. Unit Test 1" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="subject_name">Subject</label>
                                        <input type="text" id="subject_name" name="subject_name" class="form-control" value="<?= htmlspecialchars($editMark['subject_name'] ?? '') ?>" placeholder="e.g. Mathematics" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="marks_obtained">Score</label>
                                    <input
                                        type="number"
                                        id="marks_obtained"
                                        name="marks_obtained"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        value="<?= htmlspecialchars((string) ($editMark['marks_obtained'] ?? '')) ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="max_marks">Max Marks</label>
                                    <input
                                        type="number"
                                        id="max_marks"
                                        name="max_marks"
                                        class="form-control"
                                        min="0.01"
                                        step="0.01"
                                        value="<?= htmlspecialchars((string) ($editMark['max_marks'] ?? '100')) ?>"
                                        required
                                    >
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas <?= $editMark ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                                    <?= $editMark ? 'Save Mark Changes' : 'Add Mark' ?>
                                </button>
                            </form>
                        </div>
                    </article>

                    <article class="dash-panel">
                        <div class="panel-head">
                            <h2><i class="fas fa-trash-can" style="color:var(--accent);"></i> Bulk Exam Cleanup</h2>
                        </div>
                        <div class="panel-body">
                            <p class="maintenance-note" style="margin-bottom:0.7rem;">
                                Remove an exam from all students, or narrow it down by class/year.
                            </p>
                            <form method="POST" onsubmit="return confirm('Delete selected exam records? This cannot be undone.');">
                                <input type="hidden" name="action" value="bulk_delete_exam">
                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">

                                <div class="form-group">
                                    <label class="form-label" for="bulk_exam_name">Exam Name</label>
                                    <select id="bulk_exam_name" name="bulk_exam_name" class="form-control" required>
                                        <option value="">Select exam...</option>
                                        <?php foreach ($examOptions as $exam): ?>
                                            <option value="<?= htmlspecialchars($exam) ?>"><?= htmlspecialchars($exam) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="bulk_class_id">Class (Optional)</label>
                                        <select id="bulk_class_id" name="bulk_class_id" class="form-control">
                                            <option value="">All classes</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?= (int) $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="bulk_year_id">Year (Optional)</label>
                                        <select id="bulk_year_id" name="bulk_year_id" class="form-control">
                                            <option value="">All years</option>
                                            <?php foreach ($years as $year): ?>
                                                <option value="<?= (int) $year['id'] ?>"><?= htmlspecialchars($year['year_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="notion-btn notion-btn-danger">
                                    <i class="fas fa-trash"></i>
                                    Delete Exam Records
                                </button>
                            </form>
                        </div>
                    </article>
                </section>

                <section class="dash-panel reveal d3">
                    <div class="panel-head">
                        <h2><i class="fas fa-filter" style="color:var(--primary);"></i> Marks Registry</h2>
                    </div>
                    <div class="panel-body">
                        <form method="GET" class="filter-form" style="margin-bottom:0.85rem;">
                            <div class="notion-form-group" style="flex:2;min-width:180px;">
                                <label class="notion-label" for="search">Search Student</label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    class="notion-input"
                                    placeholder="Student name..."
                                    value="<?= htmlspecialchars($searchStudent) ?>"
                                >
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:130px;">
                                <label class="notion-label" for="class_id">Class</label>
                                <select id="class_id" name="class_id" class="notion-select">
                                    <option value="">All</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= (int) $class['id'] ?>" <?= (string) $filterClass === (string) $class['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($class['class_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:130px;">
                                <label class="notion-label" for="year_id">Year</label>
                                <select id="year_id" name="year_id" class="notion-select">
                                    <option value="">All</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?= (int) $year['id'] ?>" <?= (string) $filterYear === (string) $year['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['year_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="notion-form-group" style="flex:2;min-width:190px;">
                                <label class="notion-label" for="student_filter">Student</label>
                                <select id="student_filter" name="student_id" class="notion-select">
                                    <option value="">All</option>
                                    <?php foreach ($studentOptions as $student): ?>
                                        <option value="<?= (int) $student['id'] ?>" <?= (string) $filterStudent === (string) $student['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($student['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:140px;">
                                <label class="notion-label" for="exam_name_filter">Exam</label>
                                <select id="exam_name_filter" name="exam_name" class="notion-select">
                                    <option value="">All</option>
                                    <?php foreach ($examOptions as $exam): ?>
                                        <option value="<?= htmlspecialchars($exam) ?>" <?= $filterExam === $exam ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($exam) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="notion-btn notion-btn-primary notion-btn-sm"><i class="fas fa-search"></i> Filter</button>
                            <a href="manage_marks.php" class="notion-btn notion-btn-ghost notion-btn-sm">Clear</a>
                        </form>

                        <div class="notion-table-wrap">
                            <table class="notion-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Year</th>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Percent</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($marks)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center;color:var(--text-muted);padding:1.3rem;">No marks found for selected filters.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($marks as $index => $mark): ?>
                                            <tr>
                                                <td style="color:var(--text-muted);"><?= $index + 1 ?></td>
                                                <td><strong><?= htmlspecialchars($mark['student_name']) ?></strong></td>
                                                <td><?= htmlspecialchars($mark['class_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($mark['year_name'] ?? '-') ?></td>
                                                <td><span class="notion-tag tag-blue"><?= htmlspecialchars($mark['exam_name']) ?></span></td>
                                                <td><?= htmlspecialchars($mark['subject_name']) ?></td>
                                                <td style="font-weight:700;"><?= number_format((float) $mark['marks_obtained'], 2) ?> / <?= number_format((float) ($mark['max_marks'] ?? 100), 2) ?></td>
                                                <td><?= number_format((float) ($mark['percentage'] ?? 0), 1) ?>%</td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="manage_marks.php?<?= htmlspecialchars($query_with(['edit_mark' => (int) $mark['id']])) ?>" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                            <i class="fas fa-pen"></i> Edit
                                                        </a>
                                                        <form method="POST" onsubmit="return confirm('Delete this mark record?');">
                                                            <input type="hidden" name="action" value="delete_mark">
                                                            <input type="hidden" name="mark_id" value="<?= (int) $mark['id'] ?>">
                                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                                            <button type="submit" class="notion-btn notion-btn-danger notion-btn-sm">
                                                                <i class="fas fa-trash-alt"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
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
    </script>
</body>
</html>


