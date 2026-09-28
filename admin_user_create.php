<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/auth_admin_check.php';
require_once __DIR__ . '/config.php';

$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');
$level = trim($_POST['level'] ?? '');

if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>false,'error'=>'Invalid name or email']); exit;
}
if (!in_array($level, ['Beginner','Intermediate','Advanced'], true)) {
  echo json_encode(['ok'=>false,'error'=>'Invalid study level']); exit;
}

// default new users to student role and a temporary password
$role = 'student';
$tempPassword = 'Edu@1234'; // you can change this
$hash = password_hash($tempPassword, PASSWORD_DEFAULT);

$level_db = strtolower($level); // store as: beginner/intermediate/advanced

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // ensure email unique
  $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  if ($stmt->get_result()->fetch_assoc()) {
    echo json_encode(['ok'=>false,'error'=>'Email already exists']); exit;
  }

  $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role, study_level, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->bind_param('sssss', $name, $email, $hash, $role, $level_db);
  $stmt->execute();
  $id = $stmt->insert_id;

  echo json_encode([
    'ok'=>true,
    'user'=>[
      'id'=>$id, 'name'=>$name, 'email'=>$email, 'role'=>$role, 'level'=>$level
    ],
    'temp_password'=>$tempPassword
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
