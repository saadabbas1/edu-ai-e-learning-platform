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
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['ok' => false, 'errors' => ['avatar' => 'No file uploaded or upload error.']]);
  exit;
}
$file = $_FILES['avatar'];
if ($file['size'] > 2 * 1024 * 1024) {
  echo json_encode(['ok' => false, 'errors' => ['avatar' => 'File too large (max 2MB).']]);
  exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$ok    = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($ok[$mime])) {
  echo json_encode(['ok'=>false,'errors'=>['avatar'=>'Only JPG, PNG, or WEBP allowed.']]);
  exit;
}
$ext = $ok[$mime];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

// fetch any existing pic
$stmt = $conn->prepare('SELECT profile_pic FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$oldRel = $row['profile_pic'] ?? null;

// ensure folder
$dir = __DIR__ . '/uploads/avatars';
if (!is_dir($dir)) mkdir($dir, 0777, true);

// save new file
$fname   = 'user_' . $userId . '_' . time() . '.' . $ext;
$destFs  = $dir . '/' . $fname;
if (!move_uploaded_file($file['tmp_name'], $destFs)) {
  echo json_encode(['ok'=>false,'errors'=>['avatar'=>'Failed to save file.']]);
  exit;
}
$relative = 'uploads/avatars/' . $fname;
$public   = '/edu_ai/' . $relative;

// delete old file (no UI)
if ($oldRel) {
  $full = __DIR__ . '/' . ltrim($oldRel,'/');
  if (!is_file($full)) {
    $alt = __DIR__ . '/' . ltrim(str_replace('/edu_ai/','',$oldRel),'/');
    if (is_file($alt)) $full = $alt;
  }
  if (is_file($full)) @unlink($full);
}

// update DB
$upd = $conn->prepare('UPDATE users SET profile_pic = ? WHERE id = ? LIMIT 1');
$upd->bind_param('si', $relative, $userId);
$upd->execute();

echo json_encode(['ok'=>true,'message'=>'Profile picture updated.','url'=>$public]);