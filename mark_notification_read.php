<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) exit;

$userId = (int)$_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$stmt = $conn->prepare("
  UPDATE notifications
  SET is_read = 1
  WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();

echo json_encode(["ok" => true]);
