<?php
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false]); exit;
}

$theme = $_POST['theme'] ?? 'light';
if (!in_array($theme, ['light','dark'])) {
  echo json_encode(['ok'=>false]); exit;
}

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$db->set_charset('utf8mb4');

$stmt = $db->prepare("UPDATE users SET theme=? WHERE id=?");
$stmt->bind_param('si', $theme, $_SESSION['user_id']);
$stmt->execute();

echo json_encode(['ok'=>true]);
