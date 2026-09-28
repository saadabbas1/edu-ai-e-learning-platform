<?php
// /edu_ai/lesson_update.php — unified endpoint to update a lesson
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

session_start();

require __DIR__ . '/auth_admin_check.php';
require __DIR__ . '/config.php';

$rawNewName = isset($_POST['new_file_name']) ? trim($_POST['new_file_name']) : '';

$id    = (int)($_POST['id'] ?? ($_POST['lesson_id'] ?? 0));
$title = isset($_POST['title']) ? trim($_POST['title']) : null;
$desc  = isset($_POST['description']) ? trim($_POST['description']) : null;

if ($id <= 0) {
  echo json_encode(['ok'=>false, 'error'=>'Invalid lesson id']);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // 1️⃣ Get existing lesson (need course_id for notification)
  $stmt = $db->prepare("
    SELECT id, file_path, course_id
    FROM lessons
    WHERE id=?
    LIMIT 1
  ");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $current = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$current) {
    echo json_encode(['ok'=>false, 'error'=>'Lesson not found']);
    exit;
  }

  // ---------- FILE NAME LOGIC ----------
  $newName = '';
  if ($rawNewName !== '') {
    $tmp = preg_replace('/[^A-Za-z0-9._-]+/', '_', $rawNewName);
    if (!preg_match('/\.pdf$/i', $tmp)) $tmp .= '.pdf';
    $newName = $tmp;
  }

  $fields = [];
  $params = [];
  $types  = '';

  if ($title !== null && $title !== '') {
    $fields[] = "title=?";
    $params[] = $title;
    $types .= 's';
  }

  if ($desc !== null && $desc !== '') {
    $fields[] = "description=?";
    $params[] = $desc;
    $types .= 's';
  }

  // ---------- FILE RENAME (NO UPLOAD) ----------
  if ($newName !== '' && (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)) {
    if (!empty($current['file_path'])) {
      $oldAbs = __DIR__ . '/' . $current['file_path'];
      $dir = dirname($oldAbs);
      $newAbs = $dir . '/' . $newName;

      if (@rename($oldAbs, $newAbs)) {
        $newRel = substr($newAbs, strlen(__DIR__) + 1);
        $fields[] = 'file_path=?';
        $params[] = $newRel;
        $types .= 's';

        $fields[] = 'file_name=?';
        $params[] = $newName;
        $types .= 's';
      }
    }
  }

  // ---------- FILE REPLACEMENT ----------
  if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $allowed = ['application/pdf','application/x-pdf','binary/octet-stream'];
    $mime = mime_content_type($file['tmp_name']);

    if (!in_array($mime, $allowed)) {
      echo json_encode(['ok'=>false,'error'=>'Only PDF allowed']);
      exit;
    }

    $dir = __DIR__ . '/uploads/lessons';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file['name']);
    $name = time().'_'.$safe;
    $dest = $dir.'/'.$name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      echo json_encode(['ok'=>false,'error'=>'File upload failed']);
      exit;
    }

    if (!empty($current['file_path'])) {
      $old = __DIR__.'/'.$current['file_path'];
      if (is_file($old)) @unlink($old);
    }

    if ($newName !== '') {
      $custom = $dir.'/'.$newName;
      if (@rename($dest,$custom)) {
        $dest = $custom;
        $name = $newName;
      }
    }

    $relPath = 'uploads/lessons/'.$name;

    $fields[] = 'file_path=?';
    $params[] = $relPath;
    $types .= 's';

    $fields[] = 'file_name=?';
    $params[] = $name;
    $types .= 's';
  }

  if (!$fields) {
    echo json_encode(['ok'=>false,'error'=>'No update fields']);
    exit;
  }

  // ---------- UPDATE ----------
  $fields_str = implode(', ', $fields);
  $params[] = $id;
  $types .= 'i';

  $stmt = $db->prepare("UPDATE lessons SET $fields_str WHERE id=?");
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $stmt->close();

  // 🔔 2️⃣ NOTIFICATION
  $userId = $_SESSION['user_id'] ?? null;
  if ($userId) {
    $titleN = "Lesson Updated";
    $linkN  = "course.php?id=".(int)$current['course_id'];

    $n = $db->prepare("
      INSERT INTO notifications (user_id, title, link)
      VALUES (?, ?, ?)
    ");
    $n->bind_param('iss', $userId, $titleN, $linkN);
    $n->execute();
  }

  echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
}
