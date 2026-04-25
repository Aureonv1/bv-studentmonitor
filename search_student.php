<?php
require_once 'config.php';
require_once 'maintenance_mode.php';

header('Content-Type: application/json');

if (is_maintenance_enabled()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'The portal is currently under maintenance. Please try again later.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$year_id = $_POST['academic_year'] ?? '';
$class_id = $_POST['class_id'] ?? '';
$student_name = trim($_POST['student_name'] ?? '');

if (empty($year_id) || empty($class_id) || empty($student_name)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

try {
    // Look up the student precisely by name, class, and year.
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_code, s.name, c.class_name, y.year_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN academic_years y ON s.academic_year_id = y.id
        WHERE s.class_id = ? AND s.academic_year_id = ? AND s.name = ?
        LIMIT 1
    ");
    $stmt->execute([$class_id, $year_id, $student_name]);
    $student = $stmt->fetch();

    if (!$student) {
        $suggestions = [];
        $limit = 8;
        $prefixPattern = $student_name . '%';

        $prefixStmt = $pdo->prepare("
            SELECT DISTINCT s.name
            FROM students s
            WHERE s.class_id = ?
              AND s.academic_year_id = ?
              AND s.name LIKE ?
            ORDER BY s.name ASC
            LIMIT $limit
        ");
        $prefixStmt->execute([$class_id, $year_id, $prefixPattern]);
        $suggestions = array_map(static function ($row) {
            return $row['name'];
        }, $prefixStmt->fetchAll());

        if (count($suggestions) < $limit) {
            $remaining = $limit - count($suggestions);
            $containsStmt = $pdo->prepare("
                SELECT DISTINCT s.name
                FROM students s
                WHERE s.class_id = ?
                  AND s.academic_year_id = ?
                  AND s.name LIKE ?
                  AND s.name NOT LIKE ?
                ORDER BY s.name ASC
                LIMIT $remaining
            ");
            $containsStmt->execute([$class_id, $year_id, '%' . $student_name . '%', $prefixPattern]);
            $extra = array_map(static function ($row) {
                return $row['name'];
            }, $containsStmt->fetchAll());
            $suggestions = array_values(array_unique(array_merge($suggestions, $extra)));
        }

        echo json_encode([
            'success' => false,
            'message' => 'Student not found. Please check the spelling and selected class/year.',
            'suggestions' => $suggestions
        ]);
        exit;
    }

    // Fetch marks for the student.
    $stmtMarks = $pdo->prepare("
        SELECT exam_name, subject_name, marks_obtained, max_marks, ROUND(CASE WHEN max_marks > 0 THEN (marks_obtained / max_marks) * 100 ELSE 0 END, 2) AS percentage
        FROM marks
        WHERE student_id = ?
        ORDER BY exam_name ASC, subject_name ASC
    ");
    $stmtMarks->execute([$student['id']]);
    $marks = $stmtMarks->fetchAll();

    if (empty($marks)) {
        echo json_encode(['success' => false, 'message' => 'Student found, but no marks are available yet.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'student' => $student,
        'marks' => $marks
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>

