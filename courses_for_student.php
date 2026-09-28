<?php
// /edu_ai/courses_for_student.php
ini_set('display_errors', 0); // keep off for users
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

session_start();
require __DIR__ . '/config.php';

function fail($code, $msg) {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['user_id'])) {
  fail(401, 'Login required');
}

$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // ---- 1) Get user's study level (try multiple column names) ----
  $studyLevel = null;
  $levelCols = ['study_level', 'level', 'education_level', 'grade_level'];
  $gotLevel = false;
  foreach ($levelCols as $col) {
    try {
      $stmt = $db->prepare("SELECT $col AS lvl FROM users WHERE id=? LIMIT 1");
      $stmt->bind_param('i', $uid);
      $stmt->execute();
      $res = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if ($res && isset($res['lvl']) && $res['lvl'] !== '' && $res['lvl'] !== null) {
        $studyLevel = $res['lvl'];
        $gotLevel = true;
        break;
      }
    } catch (Throwable $e) { /* try next column name */ }
  }
  if (!$gotLevel) {
    echo json_encode(['ok'=>true, 'items'=>[], 'study_level'=>null, 'count'=>0]); exit;
  }

  // ---- 2) Fetch courses matching study level ----
  // We try a strict admin-uploaded JOIN first (common schema),
  // then fall back to simple courses-by-level if JOIN/columns missing.

  $items = [];

  // Attempt A: courses uploaded by admins (users.role='admin' and courses.uploaded_by exists)
  $sqlA = "SELECT c.id, c.title, c.description, c.study_level
           FROM courses c
           JOIN users a ON a.id = c.uploaded_by
           WHERE a.role='admin' AND c.study_level = ?
           ORDER BY COALESCE(c.created_at, c.id) DESC";
  try {
    $stmt = $db->prepare($sqlA);
    $stmt->bind_param('s', $studyLevel);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $items[] = $row; }
    $stmt->close();
  } catch (Throwable $e) {
    // If this fails (unknown columns), we will try fallback below
  }

  // Attempt B (fallback): courses table only (no JOIN), same study_level
  if (!count($items)) {
    // Try different possible level columns in courses table too
    $courseLevelCols = ['study_level', 'level', 'education_level', 'grade_level'];
    $tried = false; $success = false;
    foreach ($courseLevelCols as $cLvl) {
      try {
        $sqlB = "SELECT id, title, description, $cLvl AS study_level
                 FROM courses
                 WHERE $cLvl = ?
                 ORDER BY COALESCE(created_at, id) DESC";
        $stmt = $db->prepare($sqlB);
        $stmt->bind_param('s', $studyLevel);
        $stmt->execute();
        $res = $stmt->get_result();
        $tmp = [];
        while ($row = $res->fetch_assoc()) { $tmp[] = $row; }
        $stmt->close();
        $tried = true;
        if (count($tmp)) { $items = $tmp; $success = true; break; }
      } catch (Throwable $e) { /* try next level column */ }
    }
    // If we tried different columns but none worked, we'll still return empty items.
    // If none of the queries even prepared, include a helpful debug message:
    if (!$tried) {
      echo json_encode([
        'ok'=>false,
        'error'=>'courses table/columns not found (expected: courses.(id,title,description,study_level or level) )'
      ]); exit;
    }
  }

  echo json_encode(['ok'=>true, 'items'=>$items, 'study_level'=>$studyLevel, 'count'=>count($items)]);
} catch (Throwable $e) {
  // Return a readable error for debugging instead of a blank 500
  fail(500, 'Server error: ' . $e->getMessage());
}
