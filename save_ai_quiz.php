<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['name']) || empty($data['content'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quiz data']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$name = trim($data['name']);
$content = $data['content'];
$format = $data['format'] ?? 'text';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("
  INSERT INTO ai_quizzes (user_id, quiz_name, quiz_content, quiz_format)
  VALUES (?, ?, ?, ?)
");
$stmt->bind_param('isss', $userId, $name, $content, $format);
$stmt->execute();

// ===== NOTIFICATION: AI QUIZ GENERATED =====
session_start();
$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  $notifTitle = "AI Quiz Generated";
  $notifLink  = "ai_quizzes.php";

  $stmt = $db->prepare("
    INSERT INTO notifications (user_id, title, link)
    VALUES (?, ?, ?)
  ");
  $stmt->bind_param("iss", $userId, $notifTitle, $notifLink);
  $stmt->execute();
}

echo json_encode(['ok' => true]);
exit;