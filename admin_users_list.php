<?php
// /edu_ai/admin_users_list.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/auth_admin_check.php';
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  $res = $db->query("SELECT id, name, email, role, study_level, created_at FROM users ORDER BY created_at DESC");
  $users = [];
  $counts = ['Beginner'=>0,'Intermediate'=>0,'Advanced'=>0];

  while ($row = $res->fetch_assoc()) {
    $level = strtolower((string)($row['study_level'] ?? ''));
    $pretty = $level === 'beginner' ? 'Beginner'
           : ($level === 'intermediate' ? 'Intermediate'
           : ($level === 'advanced' ? 'Advanced' : ''));

    if ($pretty && isset($counts[$pretty])) $counts[$pretty]++;

    $users[] = [
      'id'         => (int)$row['id'],
      'name'       => $row['name'],
      'email'      => $row['email'],
      'role'       => $row['role'],
      'level'      => $pretty,              // title case for chips
      'created_at' => $row['created_at'],
    ];
  }

  echo json_encode([
    'ok'     => true,
    'users'  => $users,
    'total'  => count($users),
    'counts' => $counts,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Server error']);
}
