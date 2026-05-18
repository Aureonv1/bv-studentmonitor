<?php
require_once __DIR__ . '/../session_bootstrap.php';
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
if (!admin_can('import_csv') && !admin_can('manage_marks')) {
    require_admin_permission('import_csv');
}

$classId = (int) ($_GET['class_id'] ?? 0);
$yearId = (int) ($_GET['year_id'] ?? 0);

if ($classId <= 0) {
    http_response_code(400);
    echo 'Class is required.';
    exit;
}

$classStmt = $pdo->prepare('SELECT class_name FROM classes WHERE id = ? LIMIT 1');
$classStmt->execute([$classId]);
$className = trim((string) $classStmt->fetchColumn());
if ($className === '') {
    http_response_code(404);
    echo 'Class not found.';
    exit;
}

$where = ['s.class_id = ?'];
$params = [$classId];
if ($yearId > 0) {
    $where[] = 's.academic_year_id = ?';
    $params[] = $yearId;
}

$studentsStmt = $pdo->prepare('
    SELECT s.id, s.student_code, s.name
    FROM students s
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY s.name ASC
');
$studentsStmt->execute($params);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as &$student) {
    $studentId = (int) ($student['id'] ?? 0);
    if ($studentId > 0) {
        $student['student_code'] = assign_student_code($pdo, $studentId);
    }
}
unset($student);

$subjects = [];
try {
    $subjectSql = '
        SELECT DISTINCT m.subject_name
        FROM marks m
        JOIN students s ON s.id = m.student_id
        WHERE s.class_id = ?
    ';
    $subjectParams = [$classId];
    if ($yearId > 0) {
        $subjectSql .= ' AND s.academic_year_id = ?';
        $subjectParams[] = $yearId;
    }
    $subjectSql .= " AND m.subject_name <> '' ORDER BY m.subject_name ASC LIMIT 8";
    $subjectStmt = $pdo->prepare($subjectSql);
    $subjectStmt->execute($subjectParams);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $subjects = [];
}

if (empty($subjects)) {
    $subjects = ['English', 'Mathematics', 'Science'];
}

$safeClassName = preg_replace('/[^A-Za-z0-9]+/', '_', $className) ?? 'class';
$safeClassName = trim($safeClassName, '_');
if ($safeClassName === '') {
    $safeClassName = 'class';
}

$filename = 'class_import_template_' . $safeClassName . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputcsv($out, array_merge(['Student ID', 'Student Name'], $subjects));
foreach ($students as $student) {
    $row = [
        (string) ($student['student_code'] ?? ''),
        (string) ($student['name'] ?? '')
    ];
    foreach ($subjects as $_subject) {
        $row[] = '';
    }
    fputcsv($out, $row);
}
fclose($out);
exit;
