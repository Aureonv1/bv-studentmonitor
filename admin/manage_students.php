<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_students');

$set_flash = static function (string $type, string $text): void {
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'text' => $text
    ];
};

$redirect_back = static function (string $returnQuery = ''): void {
    $target = 'manage_students';
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
            case 'add_student':
            case 'update_student':
                $name = trim($_POST['student_name'] ?? '');
                $classId = (int) ($_POST['class_id'] ?? 0);
                $yearId = (int) ($_POST['year_id'] ?? 0);

                if ($name === '' || $classId <= 0 || $yearId <= 0) {
                    throw new RuntimeException('Student name, class, and academic year are required.');
                }

                if ($action === 'add_student') {
                    $stmt = $pdo->prepare("
                        INSERT INTO students (name, class_id, academic_year_id)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$name, $classId, $yearId]);
                    $newStudentId = (int) $pdo->lastInsertId();
                    assign_student_code($pdo, $newStudentId);
                    issue_student_credentials($pdo, $newStudentId, false);
                    $set_flash('success', 'Student added successfully.');
                } else {
                    $studentId = (int) ($_POST['student_id'] ?? 0);
                    if ($studentId <= 0) {
                        throw new RuntimeException('Invalid student selected for update.');
                    }

                    $stmt = $pdo->prepare("
                        UPDATE students
                        SET name = ?, class_id = ?, academic_year_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $classId, $yearId, $studentId]);
                    $set_flash('success', 'Student updated successfully.');
                }
                break;

            case 'delete_student':
                $studentId = (int) ($_POST['student_id'] ?? 0);
                if ($studentId <= 0) {
                    throw new RuntimeException('Invalid student selected for deletion.');
                }
                $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
                $set_flash('success', 'Student and related marks deleted.');
                break;

            case 'toggle_student_active':
                $studentId = (int) ($_POST['student_id'] ?? 0);
                if ($studentId <= 0) {
                    throw new RuntimeException('Invalid student selected.');
                }
                $currentStmt = $pdo->prepare("SELECT is_active FROM student_accounts WHERE student_id = ? LIMIT 1");
                $currentStmt->execute([$studentId]);
                $current = $currentStmt->fetchColumn();

                if ($current === false) {
                    issue_student_credentials($pdo, $studentId, false);
                    $currentStmt->execute([$studentId]);
                    $current = $currentStmt->fetchColumn();
                }

                $isActive = (int) $current === 1;
                $nextValue = $isActive ? 0 : 1;
                $pdo->prepare("UPDATE student_accounts SET is_active = ? WHERE student_id = ?")->execute([$nextValue, $studentId]);
                $set_flash('success', $nextValue === 1 ? 'Student account activated.' : 'Student account deactivated.');
                break;

            case 'reset_credentials':
                $studentId = (int) ($_POST['student_id'] ?? 0);
                if ($studentId <= 0) {
                    throw new RuntimeException('Invalid student selected.');
                }
                $creds = issue_student_credentials($pdo, $studentId, true);
                $set_flash('success', 'Credentials reset for ' . $creds['username'] . '.');
                break;

            case 'add_class':
            case 'update_class':
                $className = trim($_POST['class_name'] ?? '');
                if ($className === '') {
                    throw new RuntimeException('Class name is required.');
                }

                if ($action === 'add_class') {
                    $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)")->execute([$className]);
                    $set_flash('success', 'Class added successfully.');
                } else {
                    $classId = (int) ($_POST['class_manage_id'] ?? 0);
                    if ($classId <= 0) {
                        throw new RuntimeException('Invalid class selected for update.');
                    }
                    $pdo->prepare("UPDATE classes SET class_name = ? WHERE id = ?")->execute([$className, $classId]);
                    $set_flash('success', 'Class updated successfully.');
                }
                break;

            case 'delete_class':
                $classId = (int) ($_POST['class_manage_id'] ?? 0);
                if ($classId <= 0) {
                    throw new RuntimeException('Invalid class selected for deletion.');
                }
                $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$classId]);
                $set_flash('success', 'Class deleted. Related students and marks were removed too.');
                break;

            case 'add_year':
            case 'update_year':
                $yearName = trim($_POST['year_name'] ?? '');
                if ($yearName === '') {
                    throw new RuntimeException('Academic year is required.');
                }

                if ($action === 'add_year') {
                    $pdo->prepare("INSERT INTO academic_years (year_name) VALUES (?)")->execute([$yearName]);
                    $set_flash('success', 'Academic year added successfully.');
                } else {
                    $yearId = (int) ($_POST['year_manage_id'] ?? 0);
                    if ($yearId <= 0) {
                        throw new RuntimeException('Invalid academic year selected for update.');
                    }
                    $pdo->prepare("UPDATE academic_years SET year_name = ? WHERE id = ?")->execute([$yearName, $yearId]);
                    $set_flash('success', 'Academic year updated successfully.');
                }
                break;

            case 'delete_year':
                $yearId = (int) ($_POST['year_manage_id'] ?? 0);
                if ($yearId <= 0) {
                    throw new RuntimeException('Invalid academic year selected for deletion.');
                }
                $pdo->prepare("DELETE FROM academic_years WHERE id = ?")->execute([$yearId]);
                $set_flash('success', 'Academic year deleted. Related students and marks were removed too.');
                break;

            default:
                throw new RuntimeException('Unknown management action.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $set_flash('error', 'That record already exists. Please use a different value.');
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

$search = trim($_GET['search'] ?? '');
$filterClass = trim($_GET['class_id'] ?? '');
$filterYear = trim($_GET['year_id'] ?? '');

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

$baseReturnQuery = $query_with([], ['edit_student', 'edit_class', 'edit_year']);
$baseReturnUrl = 'manage_students.php' . ($baseReturnQuery !== '' ? '?' . $baseReturnQuery : '');

$studentSql = "
    SELECT
        s.id,
        s.student_code,
        s.name,
        s.class_id,
        s.academic_year_id,
        c.class_name,
        y.year_name,
        sa.username,
        sa.is_active AS account_active,
        COUNT(m.id) AS mark_count,
        COUNT(DISTINCT m.exam_name) AS exam_count
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN academic_years y ON s.academic_year_id = y.id
    LEFT JOIN student_accounts sa ON sa.student_id = s.id
    LEFT JOIN marks m ON m.student_id = s.id
    WHERE 1 = 1
";
$studentParams = [];

if ($search !== '') {
    $studentSql .= " AND s.name LIKE ?";
    $studentParams[] = '%' . $search . '%';
}
if ($filterClass !== '') {
    $studentSql .= " AND s.class_id = ?";
    $studentParams[] = (int) $filterClass;
}
if ($filterYear !== '') {
    $studentSql .= " AND s.academic_year_id = ?";
    $studentParams[] = (int) $filterYear;
}

$studentSql .= "
    GROUP BY s.id, s.student_code, s.name, s.class_id, s.academic_year_id, c.class_name, y.year_name, sa.username, sa.is_active
    ORDER BY s.name ASC
    LIMIT 300
";
$studentStmt = $pdo->prepare($studentSql);
$studentStmt->execute($studentParams);
$students = $studentStmt->fetchAll();

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();
$years = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC")->fetchAll();

$classStats = $pdo->query("
    SELECT c.id, c.class_name, COUNT(s.id) AS student_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    GROUP BY c.id, c.class_name
    ORDER BY c.class_name ASC
")->fetchAll();

$yearStats = $pdo->query("
    SELECT y.id, y.year_name, COUNT(s.id) AS student_count
    FROM academic_years y
    LEFT JOIN students s ON s.academic_year_id = y.id
    GROUP BY y.id, y.year_name
    ORDER BY y.year_name DESC
")->fetchAll();

$totals = [
    'students' => (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'classes' => (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'years' => (int) $pdo->query("SELECT COUNT(*) FROM academic_years")->fetchColumn(),
    'marks' => (int) $pdo->query("SELECT COUNT(*) FROM marks")->fetchColumn()
];

$editStudent = null;
$editStudentId = (int) ($_GET['edit_student'] ?? 0);
if ($editStudentId > 0) {
    $stmt = $pdo->prepare("SELECT id, name, class_id, academic_year_id FROM students WHERE id = ?");
    $stmt->execute([$editStudentId]);
    $editStudent = $stmt->fetch();
}

$editClass = null;
$editClassId = (int) ($_GET['edit_class'] ?? 0);
if ($editClassId > 0) {
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE id = ?");
    $stmt->execute([$editClassId]);
    $editClass = $stmt->fetch();
}

$editYear = null;
$editYearId = (int) ($_GET['edit_year'] ?? 0);
if ($editYearId > 0) {
    $stmt = $pdo->prepare("SELECT id, year_name FROM academic_years WHERE id = ?");
    $stmt->execute([$editYearId]);
    $editYear = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management - BrightVision</title>
    <link rel="icon" type="image/png" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="shortcut icon" href="/Bv-StudentMonitor/icon.png?v=20260424">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
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
                <a href="manage_students" class="sb-link active"><i class="fas fa-database"></i> Data Manager</a>
                <a href="student_credentials" class="sb-link"><i class="fas fa-id-card"></i> Student Credentials</a>
                <a href="manage_academics" class="sb-link"><i class="fas fa-graduation-cap"></i> Academics</a>
                    <a href="export_student_ids" class="sb-link"><i class="fas fa-address-card"></i> Export Student IDs</a>
                <?php if (admin_can('manage_marks')): ?>
                    <a href="manage_marks" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a>
                <?php endif; ?>
                <?php if (admin_can('import_csv')): ?>
                    <a href="import_csv" class="sb-link"><i class="fas fa-upload"></i> Import Marks</a>
                <?php endif; ?>

                <div class="sb-label">System</div>
                <?php if (admin_can('manage_admins')): ?>
                    <a href="manage_admins" class="sb-link"><i class="fas fa-user-shield"></i> Manage Admins</a>
                <?php endif; ?>
                <?php if (admin_can('manage_site_settings')): ?>
                    <a href="site_settings" class="sb-link"><i class="fas fa-sliders"></i> Site Settings</a>
                <?php endif; ?>
                <?php if (admin_can('backup_db')): ?>
                    <a href="backup_database" class="sb-link"><i class="fas fa-download"></i> Backup Database</a>
                <?php endif; ?>
                <?php if (admin_can('maintenance_mode')): ?>
                    <a href="maintenance" class="sb-link"><i class="fas fa-screwdriver-wrench"></i> Maintenance Mode</a>
                <?php endif; ?>
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
                    <h1>Data Management Center</h1>
                </div>
                <div class="topbar-meta">
                    <span class="topbar-pill"><i class="fas fa-users"></i> <?= (int) $totals['students'] ?> Students</span>
                    <span class="topbar-pill"><i class="fas fa-layer-group"></i> <?= (int) $totals['classes'] ?> Classes</span>
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
                        <div class="icon ic-blue"><i class="fas fa-users"></i></div>
                        <div class="val-lbl">Students</div>
                        <div class="val-num"><?= (int) $totals['students'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-purple"><i class="fas fa-layer-group"></i></div>
                        <div class="val-lbl">Classes</div>
                        <div class="val-num"><?= (int) $totals['classes'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-pink"><i class="fas fa-calendar"></i></div>
                        <div class="val-lbl">Academic Years</div>
                        <div class="val-num"><?= (int) $totals['years'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-orange"><i class="fas fa-book-open"></i></div>
                        <div class="val-lbl">Marks Records</div>
                        <div class="val-num"><?= (int) $totals['marks'] ?></div>
                    </article>
                </section>

                <section class="management-grid reveal d2">
                    <article class="dash-panel">
                        <div class="panel-head">
                            <h2><i class="fas fa-user-plus" style="color:var(--primary);"></i> <?= $editStudent ? 'Edit Student' : 'Add Student' ?></h2>
                            <?php if ($editStudent): ?>
                                <a href="<?= htmlspecialchars($baseReturnUrl) ?>" class="notion-btn notion-btn-ghost notion-btn-sm">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                        <div class="panel-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="<?= $editStudent ? 'update_student' : 'add_student' ?>">
                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                <?php if ($editStudent): ?>
                                    <input type="hidden" name="student_id" value="<?= (int) $editStudent['id'] ?>">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label" for="student_name">Student Name</label>
                                    <input
                                        type="text"
                                        id="student_name"
                                        name="student_name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($editStudent['name'] ?? '') ?>"
                                        required
                                    >
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="class_id">Class</label>
                                        <select id="class_id" name="class_id" class="form-control" required>
                                            <option value="">Select class...</option>
                                            <?php foreach ($classes as $class): ?>
                                                <?php $selectedClass = (string) ($editStudent['class_id'] ?? '') === (string) $class['id']; ?>
                                                <option value="<?= (int) $class['id'] ?>" <?= $selectedClass ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($class['class_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="year_id">Academic Year</label>
                                        <select id="year_id" name="year_id" class="form-control" required>
                                            <option value="">Select year...</option>
                                            <?php foreach ($years as $year): ?>
                                                <?php $selectedYear = (string) ($editStudent['academic_year_id'] ?? '') === (string) $year['id']; ?>
                                                <option value="<?= (int) $year['id'] ?>" <?= $selectedYear ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($year['year_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas <?= $editStudent ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                                    <?= $editStudent ? 'Save Student Changes' : 'Add Student' ?>
                                </button>
                            </form>
                        </div>
                    </article>

                    <article class="dash-panel">
                        <div class="panel-head">
                            <h2><i class="fas fa-sliders" style="color:var(--accent);"></i> Master Data</h2>
                        </div>
                        <div class="panel-body panel-stack">
                            <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="<?= $editClass ? 'update_class' : 'add_class' ?>">
                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                <?php if ($editClass): ?>
                                    <input type="hidden" name="class_manage_id" value="<?= (int) $editClass['id'] ?>">
                                <?php endif; ?>
                                <div class="form-group" style="flex:1; margin-bottom:0;">
                                    <label class="form-label">Class Name</label>
                                    <input type="text" name="class_name" class="form-control" value="<?= htmlspecialchars($editClass['class_name'] ?? '') ?>" required>
                                </div>
                                <button type="submit" class="notion-btn notion-btn-primary">
                                    <i class="fas <?= $editClass ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                                    <?= $editClass ? 'Update Class' : 'Add Class' ?>
                                </button>
                                <?php if ($editClass): ?>
                                    <a href="<?= htmlspecialchars($baseReturnUrl) ?>" class="notion-btn notion-btn-ghost">Cancel</a>
                                <?php endif; ?>
                            </form>

                            <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="<?= $editYear ? 'update_year' : 'add_year' ?>">
                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                <?php if ($editYear): ?>
                                    <input type="hidden" name="year_manage_id" value="<?= (int) $editYear['id'] ?>">
                                <?php endif; ?>
                                <div class="form-group" style="flex:1; margin-bottom:0;">
                                    <label class="form-label">Academic Year</label>
                                    <input type="text" name="year_name" class="form-control" value="<?= htmlspecialchars($editYear['year_name'] ?? '') ?>" placeholder="e.g. 2026-2027" required>
                                </div>
                                <button type="submit" class="notion-btn notion-btn-primary">
                                    <i class="fas <?= $editYear ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                                    <?= $editYear ? 'Update Year' : 'Add Year' ?>
                                </button>
                                <?php if ($editYear): ?>
                                    <a href="<?= htmlspecialchars($baseReturnUrl) ?>" class="notion-btn notion-btn-ghost">Cancel</a>
                                <?php endif; ?>
                            </form>

                            <div class="split-grid">
                                <div class="notion-table-wrap">
                                    <table class="notion-table">
                                        <thead>
                                            <tr>
                                                <th>Classes</th>
                                                <th>Students</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classStats as $class): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($class['class_name']) ?></td>
                                                    <td><?= (int) $class['student_count'] ?></td>
                                                    <td>
                                                        <div class="table-actions">
                                                            <a href="manage_students.php?<?= htmlspecialchars($query_with(['edit_class' => (int) $class['id']], ['edit_student', 'edit_year'])) ?>" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                                <i class="fas fa-pen"></i> Edit
                                                            </a>
                                                            <form method="POST" onsubmit="return confirm('Delete this class and all related student records?');">
                                                                <input type="hidden" name="action" value="delete_class">
                                                                <input type="hidden" name="class_manage_id" value="<?= (int) $class['id'] ?>">
                                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                                                <button type="submit" class="notion-btn notion-btn-danger notion-btn-sm"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($classStats)): ?>
                                                <tr><td colspan="3" style="text-align:center;">No classes yet.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="notion-table-wrap">
                                    <table class="notion-table">
                                        <thead>
                                            <tr>
                                                <th>Years</th>
                                                <th>Students</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($yearStats as $year): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($year['year_name']) ?></td>
                                                    <td><?= (int) $year['student_count'] ?></td>
                                                    <td>
                                                        <div class="table-actions">
                                                            <a href="manage_students.php?<?= htmlspecialchars($query_with(['edit_year' => (int) $year['id']], ['edit_student', 'edit_class'])) ?>" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                                <i class="fas fa-pen"></i> Edit
                                                            </a>
                                                            <form method="POST" onsubmit="return confirm('Delete this year and all related student records?');">
                                                                <input type="hidden" name="action" value="delete_year">
                                                                <input type="hidden" name="year_manage_id" value="<?= (int) $year['id'] ?>">
                                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                                                <button type="submit" class="notion-btn notion-btn-danger notion-btn-sm"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($yearStats)): ?>
                                                <tr><td colspan="3" style="text-align:center;">No academic years yet.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="dash-panel reveal d3">
                    <div class="panel-head">
                        <h2><i class="fas fa-list-check" style="color:var(--primary);"></i> Student Directory</h2>
                    </div>
                    <div class="panel-body">
                        <form method="GET" class="filter-form" style="margin-bottom:0.85rem;">
                            <div class="notion-form-group" style="flex:2;min-width:180px;">
                                <label class="notion-label" for="search">Search</label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    class="notion-input"
                                    placeholder="Student name..."
                                    value="<?= htmlspecialchars($search) ?>"
                                >
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:130px;">
                                <label class="notion-label" for="class_filter">Class</label>
                                <select id="class_filter" name="class_id" class="notion-select">
                                    <option value="">All</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= (int) $class['id'] ?>" <?= (string) $filterClass === (string) $class['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($class['class_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="notion-form-group" style="flex:1;min-width:130px;">
                                <label class="notion-label" for="year_filter">Year</label>
                                <select id="year_filter" name="year_id" class="notion-select">
                                    <option value="">All</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?= (int) $year['id'] ?>" <?= (string) $filterYear === (string) $year['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['year_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="notion-btn notion-btn-primary notion-btn-sm"><i class="fas fa-search"></i> Filter</button>
                            <a href="manage_students.php" class="notion-btn notion-btn-ghost notion-btn-sm">Clear</a>
                            <?php if ($filterClass !== ''): ?>
                                <a
                                    href="export_student_roster?class_id=<?= urlencode((string) $filterClass) ?><?= $filterYear !== '' ? '&year_id=' . urlencode((string) $filterYear) : '' ?>"
                                    class="notion-btn notion-btn-primary notion-btn-sm"
                                >
                                    <i class="fas fa-id-card"></i> Export IDs
                                </a>
                            <?php else: ?>
                                <button type="button" class="notion-btn notion-btn-ghost notion-btn-sm" disabled title="Select a class to export IDs">
                                    <i class="fas fa-id-card"></i> Export IDs
                                </button>
                            <?php endif; ?>
                        </form>

                        <div class="notion-table-wrap">
                            <table class="notion-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Status</th>
                                        <th>Class</th>
                                        <th>Year</th>
                                        <th>Exams</th>
                                        <th>Marks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="10" style="text-align:center;color:var(--text-muted);padding:1.3rem;">No students found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $index => $student): ?>
                                            <tr>
                                                <td style="color:var(--text-muted);"><?= $index + 1 ?></td>
                                                <td><span class="notion-tag tag-blue"><?= htmlspecialchars((string) ($student['student_code'] ?: 'Pending')) ?></span></td>
                                                <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                                                <td><?= htmlspecialchars((string) ($student['username'] ?? '-')) ?></td>
                                                <td>
                                                    <?php $active = isset($student['account_active']) ? ((int) $student['account_active'] === 1) : false; ?>
                                                    <span class="notion-tag <?= $active ? 'tag-blue' : 'tag-orange' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
                                                </td>
                                                <td><span class="notion-tag tag-blue"><?= htmlspecialchars($student['class_name'] ?? '-') ?></span></td>
                                                <td><?= htmlspecialchars($student['year_name'] ?? '-') ?></td>
                                                <td><?= (int) $student['exam_count'] ?></td>
                                                <td><?= (int) $student['mark_count'] ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="manage_students.php?<?= htmlspecialchars($query_with(['edit_student' => (int) $student['id']], ['edit_class', 'edit_year'])) ?>" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                            <i class="fas fa-pen"></i> Edit
                                                        </a>
                                                        <a href="student_credentials?student_id=<?= (int) $student['id'] ?>" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                            <i class="fas fa-key"></i> Credentials
                                                        </a>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="toggle_student_active">
                                                            <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                                            <button type="submit" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                                <i class="fas <?= $active ? 'fa-user-slash' : 'fa-user-check' ?>"></i> <?= $active ? 'Deactivate' : 'Activate' ?>
                                                            </button>
                                                        </form>
                                                        <form method="POST" onsubmit="return confirm('Regenerate credentials for this student?');">
                                                            <input type="hidden" name="action" value="reset_credentials">
                                                            <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($baseReturnQuery) ?>">
                                                            <button type="submit" class="notion-btn notion-btn-ghost notion-btn-sm">
                                                                <i class="fas fa-arrows-rotate"></i> Reset Login
                                                            </button>
                                                        </form>
                                                        <form method="POST" onsubmit="return confirm('Delete this student and all marks?');">
                                                            <input type="hidden" name="action" value="delete_student">
                                                            <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
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






