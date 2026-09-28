<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Invalid request']);
  exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['error' => 'No file uploaded']);
  exit;
}

$tmp  = $_FILES['file']['tmp_name'];
$name = $_FILES['file']['name'];
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

$text = '';

if ($ext === 'txt') {
  $text = file_get_contents($tmp);
}
elseif ($ext === 'pdf') {
  $text = shell_exec('pdftotext ' . escapeshellarg($tmp) . ' -');
}
elseif ($ext === 'docx') {
  $zip = new ZipArchive();
  if ($zip->open($tmp)) {
    $text = strip_tags($zip->getFromName('word/document.xml'));
    $zip->close();
  }
}

$text = trim(preg_replace('/\s+/', ' ', (string)$text));

if (strlen($text) < 100) {
  echo json_encode(['error' => 'Could not extract readable text']);
  exit;
}

// Store in session (Phase 3 context)
$_SESSION['chat_file_context'] = substr($text, 0, 12000);

echo json_encode([
  'ok' => true,
  'message' => 'File uploaded. You can now ask questions about it.'
]);
