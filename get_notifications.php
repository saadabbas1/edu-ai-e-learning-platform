<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
  echo json_encode(["ok" => false, "error" => "Unauthorized"]);
  exit;
}

$userId = (int)$_SESSION['user_id'];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

// Get notifications
$stmt = $conn->prepare("
  SELECT id, title, link, is_read
  FROM notifications
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 10
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
$unread = 0;

while ($row = $res->fetch_assoc()) {
  if ((int)$row['is_read'] === 0) $unread++;
  $notifications[] = $row;
}

echo json_encode([
  "ok" => true,
  "notifications" => $notifications,
  "unread_count" => $unread
]);
