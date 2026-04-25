<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_students');

$classId = (int) ($_GET['class_id'] ?? 0);
$yearId = (int) ($_GET['year_id'] ?? 0);

if ($classId <= 0) {
    http_response_code(400);
    echo 'Please select a class before exporting the student ID roster.';
    exit;
}

$classNameStmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ? LIMIT 1");
$classNameStmt->execute([$classId]);
$className = (string) ($classNameStmt->fetchColumn() ?? '');
if ($className === '') {
    $className = 'class_' . $classId;
}

$where = [];
$params = [];
if ($classId > 0) {
    $where[] = 's.class_id = ?';
    $params[] = $classId;
}
if ($yearId > 0) {
    $where[] = 's.academic_year_id = ?';
    $params[] = $yearId;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        s.id,
        s.student_code,
        s.name,
        c.class_name,
        y.year_name
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN academic_years y ON y.id = s.academic_year_id
    $whereSql
    ORDER BY y.year_name DESC, c.class_name ASC, s.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $studentId = (int) ($row['id'] ?? 0);
    if ($studentId > 0) {
        $row['student_code'] = assign_student_code($pdo, $studentId);
    }
}
unset($row);

$safeClassName = preg_replace('/[^A-Za-z0-9]+/', '_', $className) ?? 'class';
$safeClassName = trim($safeClassName, '_');
if ($safeClassName === '') {
    $safeClassName = 'class';
}
$filename = 'student_ids_' . $safeClassName . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputcsv($out, ['Student ID', 'Student Name', 'Class', 'Academic Year']);
foreach ($rows as $row) {
    fputcsv($out, [
        (string) ($row['student_code'] ?? ''),
        (string) ($row['name'] ?? ''),
        (string) ($row['class_name'] ?? ''),
        (string) ($row['year_name'] ?? '')
    ]);
}
fclose($out);
exit;
