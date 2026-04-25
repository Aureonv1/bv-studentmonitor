<?php
require_once 'config.php';
require_once 'maintenance_mode.php';

header('Content-Type: application/json');

if (is_maintenance_enabled()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'suggestions' => [],
        'message' => 'Portal is in maintenance mode.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'suggestions' => []]);
    exit;
}

$year_id = trim($_POST['academic_year'] ?? '');
$class_id = trim($_POST['class_id'] ?? '');
$query = trim($_POST['q'] ?? '');

if ($year_id === '' || $class_id === '' || strlen($query) < 2) {
    echo json_encode(['success' => true, 'suggestions' => []]);
    exit;
}

try {
    $limit = 10;
    $prefixPattern = $query . '%';

    // Fast path: prefix search uses class/year/name index efficiently.
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

    // Fallback: contains search only if needed.
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
        $containsStmt->execute([$class_id, $year_id, '%' . $query . '%', $prefixPattern]);

        $extra = array_map(static function ($row) {
            return $row['name'];
        }, $containsStmt->fetchAll());

        $suggestions = array_values(array_unique(array_merge($suggestions, $extra)));
    }

    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'suggestions' => []
    ]);
}
?>
