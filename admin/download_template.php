<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
if (!admin_can('import_csv') && !admin_can('manage_marks')) {
    require_admin_permission('import_csv');
}

$subjects = [];
try {
    $subjects = $pdo->query("SELECT DISTINCT subject_name FROM marks WHERE subject_name <> '' ORDER BY subject_name ASC LIMIT 8")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $subjects = [];
}

if (empty($subjects)) {
    $subjects = ['English', 'Mathematics', 'Science'];
}

$filename = 'marks_import_template_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fputcsv($output, array_merge(['Student ID', 'Student Name'], $subjects));

$sampleRow1 = ['', 'Alex Johnson'];
$sampleRow2 = ['', 'Emily Davis'];
for ($i = 0; $i < count($subjects); $i++) {
    $sampleRow1[] = '';
    $sampleRow2[] = '';
}
fputcsv($output, $sampleRow1);
fputcsv($output, $sampleRow2);
fclose($output);
exit;
