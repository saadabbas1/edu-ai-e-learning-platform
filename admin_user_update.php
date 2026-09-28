<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/auth_admin_check.php';
require_once __DIR__ . '/config.php';

$id    = (int)($_POST['id'] ?? 0);
$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');
$level = trim($_POST['level'] ?? '');

if ($id <= 0 || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>false,'error'=>'Invalid data']); exit;
}
if (!in_array($level, ['Beginner','Intermediate','Advanced'], true)) {
  echo json_encode(['ok'=>false,'error'=>'Invalid study level']); exit;
}
$level_db = strtolower($level);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // ensure email unique for other users
  $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
  $stmt->bind_param('si', $email, $id);
  $stmt->execute();
  if ($stmt->get_result()->fetch_assoc()) {
    echo json_encode(['ok'=>false,'error'=>'Email already in use by another user']); exit;
  }

  $stmt = $db->prepare("UPDATE users SET name=?, email=?, study_level=? WHERE id=?");
  $stmt->bind_param('sssi', $name, $email, $level_db, $id);
  $stmt->execute();

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
