<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['ok' => false, 'error' => 'Not logged in']);
  exit;
}

$userId = (int)$_SESSION['user_id'];

try {
  $pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // 1️⃣ Get all courses
  $courses = $pdo->query("
    SELECT id, title
    FROM courses
  ")->fetchAll(PDO::FETCH_ASSOC);

  // 2️⃣ Get all lessons
  $lessons = $pdo->query("
    SELECT id, course_id, title
    FROM lessons
  ")->fetchAll(PDO::FETCH_ASSOC);

  // 3️⃣ Get user quiz attempts
  $stmt = $pdo->prepare("
    SELECT course_id, title AS lesson_title, score, max_score
    FROM quiz_attempts
    WHERE user_id = ?
    ORDER BY created_at ASC
  ");
  $stmt->execute([$userId]);
  $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'ok' => true,
    'courses' => $courses,
    'lessons' => $lessons,
    'submissions' => $submissions
  ]);
}
catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Database error']);
}
