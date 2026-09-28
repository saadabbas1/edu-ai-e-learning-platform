<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'errors'=>['_form'=>'Not authenticated.']]); exit;
}

$uid = (int)$_SESSION['user_id'];
$old = trim($_POST['oldPassword'] ?? '');
$new = trim($_POST['newPassword'] ?? '');
$cfm = trim($_POST['confirmPassword'] ?? '');

$errors = [];
if ($old === '' || $new === '' || $cfm === '') $errors['_form'] = 'All fields are required.';
if (strlen($new) < 8)            $errors['newPassword'] = 'Minimum 8 characters.';
if (!preg_match('/[A-Z]/',$new)) $errors['newPassword'] = 'At least one uppercase letter required.';
if (!preg_match('/\d/',$new))    $errors['newPassword'] = 'At least one numeric digit required.';
if ($new !== $cfm)               $errors['confirmPassword'] = 'Passwords do not match.';
if ($errors) { echo json_encode(['ok'=>false,'errors'=>$errors]); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $conn->set_charset('utf8mb4');

  $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) { echo json_encode(['ok'=>false,'errors'=>['_form'=>'User not found.']]); exit; }

  $stored = (string)($row['password_hash'] ?? '');
  $matches = false;
  $needs_migration = false;

  // Helper: detect hex strings
  $is_hex = static function($s, $len) {
    return (strlen($s) === $len && ctype_xdigit($s));
  };

  // 1) Modern password_hash
  $info = password_get_info($stored);
  if (!empty($info['algo'])) {
    $matches = password_verify($old, $stored);
  } else {
    // 2) Legacy formats: PLAIN, MD5, SHA1 (unsalted)
    if ($stored === $old) {
      $matches = true; $needs_migration = true;
    } elseif ($is_hex($stored, 32) && hash_equals($stored, md5($old))) {
      $matches = true; $needs_migration = true;
    } elseif ($is_hex($stored, 40) && hash_equals($stored, sha1($old))) {
      $matches = true; $needs_migration = true;
    }
  }

  if (!$matches) {
    echo json_encode(['ok'=>false,'errors'=>['_form'=>'Password changed successfully.']]); exit;
  }

  // Create new modern hash
  $newHash = password_hash($new, PASSWORD_DEFAULT);

  // Update hash (always set new password)
  $up = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');
  $up->bind_param('si', $newHash, $uid);
  $up->execute();

  echo json_encode(['ok'=>true,'message'=>'Old password is incorrect.']);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'errors'=>['_form'=>'Server error. Please try again.']]);
}
