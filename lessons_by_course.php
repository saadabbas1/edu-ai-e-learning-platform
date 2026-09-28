<?php
// lessons_by_course.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false, 'error'=>'Invalid course id']); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // Course info
  $stmt = $db->prepare("SELECT id, title, description, level, created_at FROM courses WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $course = $stmt->get_result()->fetch_assoc();
  if (!$course) { echo json_encode(['ok'=>false, 'error'=>'Course not found']); exit; }

  // Lessons for this course
  $stmt = $db->prepare("SELECT id, title, description, file_path, file_name, created_at
                        FROM lessons WHERE course_id=? ORDER BY created_at ASC");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  echo json_encode(['ok'=>true, 'course'=>$course, 'lessons'=>$lessons]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Server error']);
}
