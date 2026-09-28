<?php
// /edu_ai/lesson_upload.php
ini_set('display_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

session_start();

require __DIR__ . '/auth_admin_check.php';
require __DIR__ . '/config.php';

$course_id = (int)($_POST['course_id'] ?? 0);
$title     = trim($_POST['title'] ?? '');
$desc      = trim($_POST['description'] ?? '');

if ($course_id <= 0 || $title === '' || $desc === '') {
  echo json_encode(['ok'=>false, 'error'=>'Invalid data: missing course_id, title or description']);
  exit;
}

// accept file under either "file" or "pdf"
$fileField = isset($_FILES['file']) ? 'file' : (isset($_FILES['pdf']) ? 'pdf' : null);
if (!$fileField || empty($_FILES[$fileField]['name'])) {
  echo json_encode(['ok'=>false, 'error'=>'PDF required']);
  exit;
}
$f = $_FILES[$fileField];

// basic upload validations
if ($f['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['ok'=>false, 'error'=>'Upload error (code '.$f['error'].')']);
  exit;
}
$sizeOk = ($f['size'] > 0 && $f['size'] <= 50*1024*1024); // 50MB
if (!$sizeOk) {
  echo json_encode(['ok'=>false, 'error'=>'PDF too large or empty']);
  exit;
}

// MIME / extension check
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$extOk = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'pdf';
if ($mime !== 'application/pdf' && !$extOk) {
  echo json_encode(['ok'=>false, 'error'=>'Only PDF files are allowed']);
  exit;
}

// DB connect
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // ensure course exists
  $stmt = $db->prepare('SELECT id FROM courses WHERE id=? LIMIT 1');
  $stmt->bind_param('i', $course_id);
  $stmt->execute();
  if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['ok'=>false, 'error'=>'Course not found']);
    exit;
  }
  $stmt->close();

  // store under uploads/lessons/<course_id>/
  $dir = __DIR__ . '/uploads/lessons/' . $course_id;
  if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    echo json_encode(['ok'=>false, 'error'=>'Cannot create upload directory']);
    exit;
  }
  if (!is_writable($dir)) {
    echo json_encode(['ok'=>false, 'error'=>'Upload directory not writable']);
    exit;
  }

  // unique name preserving original base
  $base = preg_replace('/[^\w\- ]+/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
  $final = $base . '_' . time() . '.pdf';
  $abs   = $dir . '/' . $final;
  if (!move_uploaded_file($f['tmp_name'], $abs)) {
    echo json_encode(['ok'=>false, 'error'=>'Could not save file']);
    exit;
  }

  $rel = 'uploads/lessons/' . $course_id . '/' . $final;

  // insert lesson row
  $stmt = $db->prepare('
    INSERT INTO lessons
      (course_id, title, description, file_path, file_name, file_size, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ');
  $size = (int)$f['size'];
  $stmt->bind_param('issssi', $course_id, $title, $desc, $rel, $f['name'], $size);
  $stmt->execute();
  $lesson_id = $stmt->insert_id;
  $stmt->close();

  // 🔔 NOTIFICATION (ADMIN)
  $userId = $_SESSION['user_id'] ?? null;
  if ($userId) {
    $nTitle = 'New Lesson Added';
    $nLink  = 'course.php?id=' . $course_id;

    $n = $db->prepare('
      INSERT INTO notifications (user_id, title, link)
      VALUES (?, ?, ?)
    ');
    $n->bind_param('iss', $userId, $nTitle, $nLink);
    $n->execute();
  }

  echo json_encode([
    'ok' => true,
    'lesson' => [
      'id'          => $lesson_id,
      'course_id'   => $course_id,
      'title'       => $title,
      'description' => $desc,
      'file_name'   => $f['name'],
      'file_path'   => $rel,
      'file_size'   => $size,
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Server error']);
}
