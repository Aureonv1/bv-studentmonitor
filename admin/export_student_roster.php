<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
require_admin_permission('manage_students');

$classId = (int) ($_GET['class_id'] ?? 0);
$yearId = (int) ($_GET['year_id'] ?? 0);

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

$filename = 'student_roster_' . date('Ymd_His') . '.csv';
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
