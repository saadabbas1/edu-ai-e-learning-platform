<?php
session_start();
require __DIR__ . '/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false]);
  exit;
}

$userId = $_SESSION['user_id'];

$notify = isset($_POST['notify_email']) ? 1 : 0;
$theme  = $_POST['theme'] ?? 'light';

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$db->set_charset('utf8mb4');

$stmt = $db->prepare("
  UPDATE users
  SET notify_email=?, theme=?
  WHERE id=?
");
$stmt->bind_param('issi', $notify, $theme, $userId);
$stmt->execute();

echo json_encode(['ok'=>true]);
