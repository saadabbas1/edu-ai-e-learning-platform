<?php
// /edu_ai/feedback_submit.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

session_start();
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Login required']); exit;
}

$uid     = (int)$_SESSION['user_id'];
$rating  = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$message = trim($_POST['message'] ?? '');

if ($rating < 1 || $rating > 5 || $message === '') {
  echo json_encode(['ok'=>false,'error'=>'Invalid data']); exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // Normalize message for fair compare
$norm = preg_replace('/\s+/', ' ', strtolower($message));

// Check for recent duplicate (same user + rating + message in last 10 minutes)
$stmt = $db->prepare(
  "SELECT id FROM feedback
   WHERE user_id=? AND rating=? AND LOWER(message)=?
     AND created_at >= (NOW() - INTERVAL 10 MINUTE)
   LIMIT 1"
);
$stmt->bind_param('iis', $uid, $rating, $norm);
$stmt->execute();
$dup = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($dup) {
  echo json_encode(['ok'=>true, 'deduped'=>true]); exit;
}

  // 2) Insert
  $stmt = $db->prepare('INSERT INTO feedback (user_id, rating, message) VALUES (?,?,?)');
  $stmt->bind_param('iis', $uid, $rating, $message);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok'=>true, 'deduped'=>false]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}