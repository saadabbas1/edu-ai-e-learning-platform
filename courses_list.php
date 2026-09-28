<?php
ini_set('display_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
require __DIR__.'/auth_admin_check.php';
require __DIR__.'/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // Get courses
  $courses = [];
  $res = $db->query("SELECT id, title, description, level, created_at FROM courses ORDER BY created_at DESC");
  while ($c = $res->fetch_assoc()) {
    $c['id'] = (int)$c['id'];
    $c['lessons'] = [];
    $courses[$c['id']] = $c;
  }

  // Get lessons for those courses
  if ($courses) {
    $ids = implode(',', array_map('intval', array_keys($courses)));
    $res2 = $db->query("SELECT id, course_id, title, description, file_name, file_path, file_size, created_at
                        FROM lessons WHERE course_id IN ($ids) ORDER BY created_at DESC");
    while ($l = $res2->fetch_assoc()) {
      $l['id'] = (int)$l['id'];
      $l['course_id'] = (int)$l['course_id'];
      $courses[$l['course_id']]['lessons'][] = $l;
    }
  }

  // Counts
  $totals = ['Beginner'=>0,'Intermediate'=>0,'Advanced'=>0];
  $totalLessons = 0;
  foreach ($courses as $c) {
    $totals[$c['level']] = ($totals[$c['level']] ?? 0) + 1;
    $totalLessons += count($c['lessons']);
  }

  echo json_encode([
    'ok'=>true,
    'courses'=>array_values($courses),
    'stats'=>[
      'totalCourses'=>count($courses),
      'totalLessons'=>$totalLessons,
      'Beginner'=>$totals['Beginner'] ?? 0,
      'Intermediate'=>$totals['Intermediate'] ?? 0,
      'Advanced'=>$totals['Advanced'] ?? 0,
    ]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
