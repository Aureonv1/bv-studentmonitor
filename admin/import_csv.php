<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('import_csv');

$message = '';
$error = '';
$summary = null;

$normalizeHeader = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return trim($value, '_');
};

$resolveHeaderIndex = static function (array $headerMap, array $candidates): ?int {
    foreach ($candidates as $key) {
        if (array_key_exists($key, $headerMap)) {
            return (int) $headerMap[$key];
        }
    }
    return null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $defaultYear = trim((string) ($_POST['academic_year'] ?? ''));
    $defaultClass = trim((string) ($_POST['class'] ?? ''));
    $defaultExam = trim((string) ($_POST['exam_name'] ?? ''));
    $defaultMaxRaw = trim((string) ($_POST['max_marks'] ?? ''));
    $defaultMax = is_numeric($defaultMaxRaw) ? (float) $defaultMaxRaw : 100.0;
    if ($defaultMax <= 0) {
        $defaultMax = 100.0;
    }

    if ($defaultYear === '' || $defaultClass === '' || $defaultExam === '' || !is_numeric($defaultMaxRaw) || (float) $defaultMaxRaw <= 0) {
        $error = 'Academic year, class, exam name, and valid max marks are required.';
    }

    $file = $_FILES['csv_file'];
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    if ($error === '' && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $extension !== 'csv')) {
        $error = 'Upload a valid CSV file.';
    } elseif ($error === '') {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $error = 'Cannot read uploaded file.';
        } else {
            $header = fgetcsv($handle);
            if (!is_array($header) || count($header) < 4) {
                $error = 'CSV header is missing or invalid.';
                fclose($handle);
            } else {
                $normalized = [];
                foreach ($header as $idx => $title) {
                    $key = $normalizeHeader((string) $title);
                    if ($key !== '' && !array_key_exists($key, $normalized)) {
                        $normalized[$key] = $idx;
                    }
                }

                $idxYear = $resolveHeaderIndex($normalized, ['academic_year', 'year', 'year_name']);
                $idxClass = $resolveHeaderIndex($normalized, ['class', 'class_name', 'grade']);
                $idxStudentCode = $resolveHeaderIndex($normalized, ['student_id', 'student_code', 'id_code']);
                $idxStudentName = $resolveHeaderIndex($normalized, ['student_name', 'name']);
                $idxExam = $resolveHeaderIndex($normalized, ['exam_name', 'exam', 'term']);
                $idxSubject = $resolveHeaderIndex($normalized, ['subject', 'subject_name']);
                $idxMarks = $resolveHeaderIndex($normalized, ['marks_obtained', 'marks', 'score']);
                $idxMax = $resolveHeaderIndex($normalized, ['max_marks', 'max', 'out_of']);

                $knownKeys = [
                    'academic_year', 'year', 'year_name',
                    'class', 'class_name', 'grade',
                    'student_id', 'student_code', 'id_code',
                    'student_name', 'name',
                    'exam_name', 'exam', 'term',
                    'subject', 'subject_name',
                    'marks_obtained', 'marks', 'score',
                    'max_marks', 'max', 'out_of'
                ];

                $wideSubjectColumns = [];
                foreach ($header as $idx => $title) {
                    $key = $normalizeHeader((string) $title);
                    if ($key === '') {
                        continue;
                    }
                    if (in_array($key, $knownKeys, true)) {
                        continue;
                    }
                    $subjectTitle = trim((string) $title);
                    if ($subjectTitle !== '') {
                        $wideSubjectColumns[$idx] = $subjectTitle;
                    }
                }

                $isLongFormat = $idxStudentName !== null && $idxSubject !== null && $idxMarks !== null;
                $isWideFormat = $idxStudentName !== null && !$isLongFormat && !empty($wideSubjectColumns);

                if (!$isLongFormat && !$isWideFormat) {
                    $error = 'CSV must be either: (1) row format with student_name, subject, marks_obtained OR (2) roster format with Student ID, Student Name, and subject columns.';
                    fclose($handle);
                } else {
                    $yearCache = [];
                    $classCache = [];
                    $createdStudents = 0;
                    $createdMarks = 0;
                    $updatedMarks = 0;
                    $processedRows = 0;

                    $pdo->beginTransaction();
                    try {
                        $getYearId = static function (PDO $pdoRef, string $yearName, array &$cache): int {
                            $yearName = trim($yearName);
                            if ($yearName === '') {
                                throw new RuntimeException('Academic year is required for each record (or set a default year).');
                            }
                            if (isset($cache[$yearName])) {
                                return (int) $cache[$yearName];
                            }
                            $stmt = $pdoRef->prepare('SELECT id FROM academic_years WHERE year_name = ? LIMIT 1');
                            $stmt->execute([$yearName]);
                            $id = (int) $stmt->fetchColumn();
                            if ($id <= 0) {
                                $pdoRef->prepare('INSERT INTO academic_years (year_name) VALUES (?)')->execute([$yearName]);
                                $id = (int) $pdoRef->lastInsertId();
                            }
                            $cache[$yearName] = $id;
                            return $id;
                        };

                        $getClassId = static function (PDO $pdoRef, string $className, array &$cache): int {
                            $className = trim($className);
                            if ($className === '') {
                                throw new RuntimeException('Class is required for each record (or set a default class).');
                            }
                            if (isset($cache[$className])) {
                                return (int) $cache[$className];
                            }
                            $stmt = $pdoRef->prepare('SELECT id FROM classes WHERE class_name = ? LIMIT 1');
                            $stmt->execute([$className]);
                            $id = (int) $stmt->fetchColumn();
                            if ($id <= 0) {
                                $pdoRef->prepare('INSERT INTO classes (class_name) VALUES (?)')->execute([$className]);
                                $id = (int) $pdoRef->lastInsertId();
                            }
                            $cache[$className] = $id;
                            return $id;
                        };

                        while (($row = fgetcsv($handle)) !== false) {
                            if (!is_array($row) || count($row) === 0) {
                                continue;
                            }

                            $studentName = trim((string) ($row[$idxStudentName] ?? ''));

                            $yearName = $idxYear !== null ? trim((string) ($row[$idxYear] ?? '')) : '';
                            $className = $idxClass !== null ? trim((string) ($row[$idxClass] ?? '')) : '';
                            $examName = $idxExam !== null ? trim((string) ($row[$idxExam] ?? '')) : '';

                            if ($yearName === '') {
                                $yearName = $defaultYear;
                            }
                            if ($className === '') {
                                $className = $defaultClass;
                            }
                            if ($examName === '') {
                                $examName = $defaultExam;
                            }
                            if ($examName === '') {
                                $examName = 'Term Exam';
                            }

                            if ($studentName === '') {
                                continue;
                            }

                            $yearId = $getYearId($pdo, $yearName, $yearCache);
                            $classId = $getClassId($pdo, $className, $classCache);

                            $studentCode = $idxStudentCode !== null ? strtoupper(trim((string) ($row[$idxStudentCode] ?? ''))) : '';
                            $studentId = 0;

                            if ($studentCode !== '') {
                                $findByCode = $pdo->prepare('SELECT id FROM students WHERE student_code = ? LIMIT 1');
                                $findByCode->execute([$studentCode]);
                                $studentId = (int) $findByCode->fetchColumn();
                            }

                            if ($studentId <= 0) {
                                $findByIdentity = $pdo->prepare('SELECT id FROM students WHERE name = ? AND class_id = ? AND academic_year_id = ? LIMIT 1');
                                $findByIdentity->execute([$studentName, $classId, $yearId]);
                                $studentId = (int) $findByIdentity->fetchColumn();
                            }

                            if ($studentId <= 0) {
                                $insertStudent = $pdo->prepare('INSERT INTO students (name, class_id, academic_year_id) VALUES (?, ?, ?)');
                                $insertStudent->execute([$studentName, $classId, $yearId]);
                                $studentId = (int) $pdo->lastInsertId();
                                assign_student_code($pdo, $studentId);
                                issue_student_credentials($pdo, $studentId, false);
                                $createdStudents++;
                            } else {
                                $pdo->prepare('UPDATE students SET name = ?, class_id = ?, academic_year_id = ? WHERE id = ?')
                                    ->execute([$studentName, $classId, $yearId, $studentId]);
                                assign_student_code($pdo, $studentId);
                            }

                            $upsert = $pdo->prepare(
                                'INSERT INTO marks (student_id, exam_name, subject_name, marks_obtained, max_marks)
                                 VALUES (?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), max_marks = VALUES(max_marks)'
                            );

                            if ($isLongFormat) {
                                $subjectName = trim((string) ($row[$idxSubject] ?? ''));
                                $marksRaw = trim((string) ($row[$idxMarks] ?? ''));
                                $maxRaw = $idxMax !== null ? trim((string) ($row[$idxMax] ?? '')) : '';

                                if ($subjectName === '' || $marksRaw === '' || !is_numeric($marksRaw)) {
                                    continue;
                                }

                                $maxMarks = is_numeric($maxRaw) ? (float) $maxRaw : $defaultMax;
                                if ($maxMarks <= 0) {
                                    $maxMarks = $defaultMax;
                                }

                                $marksObtained = (float) $marksRaw;
                                $upsert->execute([$studentId, $examName, $subjectName, $marksObtained, $maxMarks]);
                                if ($upsert->rowCount() === 1) {
                                    $createdMarks++;
                                } else {
                                    $updatedMarks++;
                                }
                            } else {
                                foreach ($wideSubjectColumns as $subjectIndex => $subjectName) {
                                    $marksRaw = trim((string) ($row[$subjectIndex] ?? ''));
                                    if ($marksRaw === '' || !is_numeric($marksRaw)) {
                                        continue;
                                    }

                                    $marksObtained = (float) $marksRaw;
                                    $maxMarks = $defaultMax;
                                    $upsert->execute([$studentId, $examName, (string) $subjectName, $marksObtained, $maxMarks]);
                                    if ($upsert->rowCount() === 1) {
                                        $createdMarks++;
                                    } else {
                                        $updatedMarks++;
                                    }
                                }
                            }

                            $processedRows++;
                        }

                        fclose($handle);

                        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
                        $logStmt = $pdo->prepare('INSERT INTO imports_log (admin_id, file_name, imported_rows, created_students, updated_marks) VALUES (?, ?, ?, ?, ?)');
                        $logStmt->execute([
                            $adminId > 0 ? $adminId : null,
                            (string) ($file['name'] ?? 'import.csv'),
                            $processedRows,
                            $createdStudents,
                            $updatedMarks
                        ]);

                        $pdo->commit();
                        $summary = [
                            'processed_rows' => $processedRows,
                            'created_students' => $createdStudents,
                            'created_marks' => $createdMarks,
                            'updated_marks' => $updatedMarks
                        ];
                        $message = 'CSV import completed successfully.';
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        fclose($handle);
                        $error = 'Import failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$importStats = [
    'imports' => 0,
    'rows' => 0,
    'students' => 0,
    'updated' => 0
];
try {
    $statsRow = $pdo->query("
        SELECT
            COUNT(*) AS imports_count,
            COALESCE(SUM(imported_rows), 0) AS rows_count,
            COALESCE(SUM(created_students), 0) AS created_students_count,
            COALESCE(SUM(updated_marks), 0) AS updated_marks_count
        FROM imports_log
    ")->fetch();
    if ($statsRow) {
        $importStats['imports'] = (int) ($statsRow['imports_count'] ?? 0);
        $importStats['rows'] = (int) ($statsRow['rows_count'] ?? 0);
        $importStats['students'] = (int) ($statsRow['created_students_count'] ?? 0);
        $importStats['updated'] = (int) ($statsRow['updated_marks_count'] ?? 0);
    }
} catch (Throwable $e) {
    // Keep page working even if log table is unavailable.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Hub - BrightVision</title>
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
                <?php if (admin_can('manage_marks')): ?><a href="manage_marks" class="sb-link"><i class="fas fa-pen-to-square"></i> Marks Manager</a><?php endif; ?>
                <a href="import_csv" class="sb-link active"><i class="fas fa-upload"></i> Import Marks</a>

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
                    <h1>Import Data Hub</h1>
                </div>
                <div class="topbar-meta">
                    <span class="topbar-pill"><i class="fas fa-upload"></i> <?= (int) $importStats['imports'] ?> Imports</span>
                    <span class="topbar-pill"><i class="fas fa-calendar-days"></i> <?= date('M j, Y') ?></span>
                </div>
            </div>

            <div class="admin-body dashboard-stack">
                <section class="metrics-grid reveal d1">
                    <article class="val-card">
                        <div class="icon ic-blue"><i class="fas fa-file-import"></i></div>
                        <div class="val-lbl">Imports</div>
                        <div class="val-num"><?= (int) $importStats['imports'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-purple"><i class="fas fa-list-check"></i></div>
                        <div class="val-lbl">Rows Processed</div>
                        <div class="val-num"><?= (int) $importStats['rows'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-pink"><i class="fas fa-user-plus"></i></div>
                        <div class="val-lbl">Students Created</div>
                        <div class="val-num"><?= (int) $importStats['students'] ?></div>
                    </article>
                    <article class="val-card">
                        <div class="icon ic-orange"><i class="fas fa-pen-to-square"></i></div>
                        <div class="val-lbl">Marks Updated</div>
                        <div class="val-num"><?= (int) $importStats['updated'] ?></div>
                    </article>
                </section>

                <section class="dash-panel reveal d2" style="max-width:880px;">
                    <div class="panel-head">
                        <h2><i class="fas fa-database" style="color:var(--primary);"></i> Batch Upload</h2>
                        <a href="download_template" class="notion-btn notion-btn-ghost notion-btn-sm"><i class="fas fa-file-arrow-down"></i> Download Template</a>
                    </div>
                    <div class="panel-body">
                        <div class="info-callout">
                            <h4><i class="fas fa-circle-info"></i> Supported CSV columns</h4>
                            <p>You can import either format:
                            1) Row format: `student_name, subject, marks_obtained` (+ optional year/class/exam/max in CSV).
                            2) Roster format: `Student ID, Student Name, Subject1, Subject2...` and use the form fields below for year/class/exam/max marks.</p>
                            <div class="csv-sample">Student ID,Student Name,English,Math,Science</div>
                        </div>

                        <?php if ($flash): ?>
                            <div class="msg <?= ($flash['type'] ?? '') === 'error' ? 'msg-error' : 'msg-success' ?>" style="display:flex;">
                                <i class="fas <?= ($flash['type'] ?? '') === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i>
                                <?= htmlspecialchars((string) ($flash['text'] ?? '')) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="msg msg-error" style="display:flex;">
                                <i class="fas fa-circle-exclamation"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="msg msg-success">
                                <i class="fas fa-check-circle"></i>
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($summary): ?>
                            <div class="metrics-grid" style="margin-bottom:0.9rem;">
                                <article class="val-card"><div class="val-lbl">Rows Processed</div><div class="val-num"><?= (int) $summary['processed_rows'] ?></div></article>
                                <article class="val-card"><div class="val-lbl">Students Created</div><div class="val-num"><?= (int) $summary['created_students'] ?></div></article>
                                <article class="val-card"><div class="val-lbl">Marks Inserted</div><div class="val-num"><?= (int) $summary['created_marks'] ?></div></article>
                                <article class="val-card"><div class="val-lbl">Marks Updated</div><div class="val-num"><?= (int) $summary['updated_marks'] ?></div></article>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="academic_year">Academic Year</label>
                                    <input type="text" id="academic_year" name="academic_year" class="form-control" placeholder="e.g. 2026" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="class">Class</label>
                                    <input type="text" id="class" name="class" class="form-control" placeholder="e.g. Grade 11" required>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="exam_name">Exam Name</label>
                                    <input type="text" id="exam_name" name="exam_name" class="form-control" placeholder="e.g. Unit Test 2" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="max_marks">Max Marks (Exam Out Of)</label>
                                    <input type="number" id="max_marks" name="max_marks" class="form-control" min="0.01" step="0.01" value="100" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="csv_file">CSV File</label>
                                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required style="padding:0.6rem;">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-upload"></i>
                                Process and Import
                            </button>
                        </form>
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


