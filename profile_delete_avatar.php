<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

$userId = (int)$_SESSION['user_id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'errors' => ['_form' => 'Method not allowed']]);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$cur = $conn->prepare('SELECT profile_pic FROM users WHERE id = ? LIMIT 1');
$cur->bind_param('i', $userId);
$cur->execute();
$row = $cur->get_result()->fetch_assoc();
$rel = $row['profile_pic'] ?? '';

if ($rel) {
  $full = __DIR__ . '/' . ltrim($rel, '/');
  if (!is_file($full)) {
    $alt = __DIR__ . '/' . ltrim(str_replace('/edu_ai/', '', $rel), '/');
    if (is_file($alt)) $full = $alt;
  }
  if (is_file($full)) @unlink($full);
}

$upd = $conn->prepare('UPDATE users SET profile_pic = NULL WHERE id = ? LIMIT 1');
$upd->bind_param('i', $userId);
$upd->execute();

echo json_encode(['ok'=>true,'message'=>'Profile picture removed.']);
