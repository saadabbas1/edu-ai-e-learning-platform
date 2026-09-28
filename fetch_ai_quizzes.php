<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not logged in']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare("
        SELECT 
            id,
            quiz_name   AS name,
            quiz_content AS content,
            quiz_format AS format,
            created_at
        FROM ai_quizzes
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
