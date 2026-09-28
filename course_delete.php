<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

session_start();

require __DIR__.'/auth_admin_check.php';
require __DIR__.'/config.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  echo json_encode(['ok'=>false,'error'=>'Invalid id']);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // 1️⃣ Remove lesson files first
  $stmt = $db->prepare("SELECT file_path FROM lessons WHERE course_id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();

  $rs = $stmt->get_result();
  while ($r = $rs->fetch_assoc()) {
    $path = __DIR__ . '/' . $r['file_path'];
    if (is_file($path)) {
      @unlink($path);
    }
  }

  // 2️⃣ Delete course (CASCADE deletes lessons)
  $stmt = $db->prepare("DELETE FROM courses WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();

  // 3️⃣ 🔔 CREATE NOTIFICATION
  $userId = $_SESSION['user_id'] ?? null;

  if ($userId) {
    $notifTitle = "Course Deleted";
    $notifLink  = "all_courses.html";

    $n = $db->prepare("
      INSERT INTO notifications (user_id, title, link)
      VALUES (?, ?, ?)
    ");
    $n->bind_param("iss", $userId, $notifTitle, $notifLink);
    $n->execute();
  }

  // 4️⃣ Response
  echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
