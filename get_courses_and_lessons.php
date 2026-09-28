<?php
session_start();
require __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'Not logged in']);
  exit;
}

$userId = $_SESSION['user_id'];

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$db->set_charset('utf8mb4');

/* 1️⃣ Get user study level */
$stmt = $db->prepare("SELECT study_level FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  echo json_encode(['ok'=>false,'error'=>'User not found']);
  exit;
}

$level = $user['study_level'];

/* 2️⃣ Get all courses for that level */
$stmt = $db->prepare("SELECT id, title FROM courses WHERE level = ?");
$stmt->bind_param('s', $level);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* 3️⃣ Get lessons for those courses */
$courseIds = array_column($courses, 'id');
$lessonsByCourse = [];

if ($courseIds) {
  $in = implode(',', array_fill(0, count($courseIds), '?'));
  $types = str_repeat('i', count($courseIds));

  $stmt = $db->prepare(
    "SELECT id, course_id, title FROM lessons WHERE course_id IN ($in)"
  );
  $stmt->bind_param($types, ...$courseIds);
  $stmt->execute();

  $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  foreach ($lessons as $l) {
    $lessonsByCourse[$l['course_id']][] = $l;
  }
}

echo json_encode([
  'ok' => true,
  'courses' => $courses,
  'lessons' => $lessonsByCourse
]);
