<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

require_admin_login($pdo);
if (!admin_can('import_csv') && !admin_can('manage_marks')) {
    require_admin_permission('import_csv');
}

$classId = (int) ($_GET['class_id'] ?? 0);
$yearId = (int) ($_GET['year_id'] ?? 0);
$examName = trim((string) ($_GET['exam_name'] ?? ''));

if ($classId <= 0 || $yearId <= 0) {
    http_response_code(400);
    echo 'Class and Academic Year are required.';
    exit;
}

$studentsStmt = $pdo->prepare('
    SELECT id, name, student_code
    FROM students
    WHERE class_id = ? AND academic_year_id = ?
    ORDER BY name ASC
');
$studentsStmt->execute([$classId, $yearId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as &$student) {
    $studentId = (int) ($student['id'] ?? 0);
    if ($studentId > 0) {
        $student['student_code'] = assign_student_code($pdo, $studentId);
    }
}
unset($student);

$subjectSql = '
    SELECT DISTINCT m.subject_name
    FROM marks m
    JOIN students s ON s.id = m.student_id
    WHERE s.class_id = ? AND s.academic_year_id = ?
';
$subjectParams = [$classId, $yearId];
if ($examName !== '') {
    $subjectSql .= " AND COALESCE(NULLIF(m.exam_name, ''), 'Term Exam') = ?";
    $subjectParams[] = $examName;
}
$subjectSql .= ' ORDER BY m.subject_name ASC';
$subjectStmt = $pdo->prepare($subjectSql);
$subjectStmt->execute($subjectParams);
$subjects = $subjectStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($subjects)) {
    $subjects = ['Math', 'Science', 'English', 'History'];
}

$marksMap = [];
if (!empty($students)) {
    $markSql = '
        SELECT m.student_id, m.subject_name, m.marks_obtained
        FROM marks m
        JOIN students s ON s.id = m.student_id
        WHERE s.class_id = ? AND s.academic_year_id = ?
    ';
    $markParams = [$classId, $yearId];
    if ($examName !== '') {
        $markSql .= " AND COALESCE(NULLIF(m.exam_name, ''), 'Term Exam') = ?";
        $markParams[] = $examName;
    }
    $markStmt = $pdo->prepare($markSql);
    $markStmt->execute($markParams);
    foreach ($markStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int) ($row['student_id'] ?? 0);
        $subj = (string) ($row['subject_name'] ?? '');
        $marksMap[$sid][$subj] = (string) ($row['marks_obtained'] ?? '');
    }
}

$filename = 'roster_import_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputcsv($out, array_merge(['Student ID', 'Student Name'], $subjects));

foreach ($students as $student) {
    $sid = (int) ($student['id'] ?? 0);
    $row = [
        (string) ($student['student_code'] ?? ''),
        (string) ($student['name'] ?? '')
    ];
    foreach ($subjects as $subject) {
        $row[] = (string) ($marksMap[$sid][$subject] ?? '');
    }
    fputcsv($out, $row);
}

fclose($out);
exit;
