<?php
// /edu_ai/admin_feedback_list.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/auth_admin_check.php'; // requires $_SESSION['role']==='admin'
require __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // join with users to get name, email, study_level
  $sql = "SELECT f.id, f.rating, f.message, f.created_at,
                 u.name, u.email, u.study_level
          FROM feedback f
          JOIN users u ON u.id = f.user_id
          ORDER BY f.created_at DESC";
  $res = $db->query($sql);

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;

  echo json_encode(['ok'=>true, 'items'=>$rows, 'count'=>count($rows)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Server error']);
}
