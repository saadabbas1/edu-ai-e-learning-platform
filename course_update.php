<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

session_start();

require __DIR__.'/auth_admin_check.php';
require __DIR__.'/config.php';

$id    = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');

if ($id <= 0 || $title === '' || $desc === '') {
  echo json_encode(['ok'=>false,'error'=>'Invalid data']);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try_topics:
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // 1️⃣ Update course
  $stmt = $db->prepare("UPDATE courses SET title=?, description=? WHERE id=?");
  $stmt->bind_param('ssi', $title, $desc, $id);
  $stmt->execute();

  // 2️⃣ 🔔 CREATE NOTIFICATION
  $userId = $_SESSION['user_id'] ?? null;

  if ($userId) {
    $notifTitle = "Course Updated";
    $notifLink  = "course.php?id=" . $id;

    $n = $db->prepare("
      INSERT INTO notifications (user_id, title, link)
      VALUES (?, ?, ?)
    ");
    $n->bind_param("iss", $userId, $notifTitle, $notifLink);
    $n->execute();
  }

  // 3️⃣ Response
  echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
