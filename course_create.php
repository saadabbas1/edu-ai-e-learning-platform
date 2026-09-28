<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

session_start();

require __DIR__.'/auth_admin_check.php';
require __DIR__.'/config.php';

$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');
$level = trim($_POST['level'] ?? '');

if ($title==='' || $desc==='' || !in_array($level, ['Beginner','Intermediate','Advanced'], true)) {
  echo json_encode(['ok'=>false,'error'=>'Invalid data']);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // 1️⃣ Insert course
  $stmt = $db->prepare("
    INSERT INTO courses (title, description, level)
    VALUES (?, ?, ?)
  ");
  $stmt->bind_param('sss', $title, $desc, $level);
  $stmt->execute();

  $courseId = $stmt->insert_id;

  // 2️⃣ 🔔 CREATE NOTIFICATION (AFTER SUCCESS)
  $userId = $_SESSION['user_id'] ?? null;

  if ($userId) {
    $notifTitle = "New Course Added";
    $notifLink  = "all_courses.html";

    $n = $db->prepare("
      INSERT INTO notifications (user_id, title, link)
      VALUES (?, ?, ?)
    ");
    $n->bind_param("iss", $userId, $notifTitle, $notifLink);
    $n->execute();
  }

  // 3️⃣ Response
  echo json_encode([
    'ok'=>true,
    'course'=>[
      'id'=>$courseId,
      'title'=>$title,
      'description'=>$desc,
      'level'=>$level,
      'created_at'=>date('Y-m-d H:i:s'),
      'lessons'=>[]
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
