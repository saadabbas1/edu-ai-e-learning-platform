<?php
// download_lesson.php — streams the lesson PDF with the original filename
ini_set('display_errors', 1);
error_reporting(E_ALL);

// If this is admin-only page, keep this. If students will access, switch to your generic auth check.
// require __DIR__ . '/auth_admin_check.php';

require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'Invalid lesson id';
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  $stmt = $db->prepare("SELECT file_path, file_name FROM lessons WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) {
    http_response_code(404);
    echo 'Lesson not found';
    exit;
  }

  $relPath = $row['file_path'];   // e.g. uploads/lessons/abcd1234.pdf
  $orig    = $row['file_name'] ?: 'lesson.pdf';

  $absBase = realpath(__DIR__ . '/uploads/lessons');
  $absFile = realpath(__DIR__ . '/' . $relPath);

  // Security: ensure file is inside uploads/lessons
  if ($absFile === false || $absBase === false || strpos($absFile, $absBase) !== 0 || !is_file($absFile)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }

  // Clean filename for header
  $downloadName = preg_replace('/[^\w\-. ]+/', '_', $orig);
  $filesize = filesize($absFile);

  // Headers
  header('Content-Type: application/pdf');
  header('X-Content-Type-Options: nosniff');
  header('Content-Length: ' . $filesize);
  // RFC 5987 for UTF-8 filenames + fallback
  header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));

  // Stream the file
  readfile($absFile);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo 'Server error';
  exit;
}
