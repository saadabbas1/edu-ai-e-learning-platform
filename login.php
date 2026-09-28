<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

session_start();
require_once __DIR__ . '/config.php';

$email = trim($_POST['email'] ?? '');
$pass  = trim($_POST['password'] ?? '');

$errs = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs['email'] = 'Enter a valid email.';
if ($pass === '') $errs['password'] = 'Password is required.';
if ($errs) { echo json_encode(['ok'=>false,'errors'=>$errs]); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  $stmt = $db->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();

  if (!$u) {
    echo json_encode(['ok'=>false,'errors'=>['password'=>'Invalid email or password.']]); exit;
  }

  $stored = (string)($u['password_hash'] ?? '');
  $ok = false;
  if ($stored && password_get_info($stored)['algo']) {
    $ok = password_verify($pass, $stored);
  } else {
    // fallback for plain/md5
    $ok = ($stored === $pass) || ($stored === md5($pass));
  }

  if (!$ok) {
    echo json_encode(['ok'=>false,'errors'=>['password'=>'Invalid email or password.']]); exit;
  }

  // Normalize role and remove spaces/case issues
  $role = strtolower(trim((string)($u['role'] ?? 'student')));

  // Save session
  $_SESSION['user_id'] = (int)$u['id'];
  $_SESSION['name']    = $u['name'];
  $_SESSION['email']   = $u['email'];
  $_SESSION['role']    = $role;

  // ✅ Decide redirect based on role
  switch ($role) {
    case 'admin':
      $redirect = '/edu_ai/admin_dashboard.php';
      break;
    case 'teacher':
      $redirect = '/edu_ai/teacher_dashboard.php';
      break;
    default:
      $redirect = '/edu_ai/student.php';
  }

  echo json_encode([
    'ok'       => true,
    'message'  => 'Login successful.',
    'role'     => $role,
    'redirect' => $redirect
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'errors'=>['_form'=>'Server error.']]); exit;
}
