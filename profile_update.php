<?php
// C:\xampp\htdocs\edu_ai\profile_update.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

$uid = (int)$_SESSION['user_id'];

// Read POST coming from your page
$name        = trim($_POST['username']    ?? '');
$interest    = trim($_POST['interest']    ?? '');
$description = trim($_POST['description'] ?? '');
$studyLevel  = trim($_POST['studyLevel']  ?? '');

$errors = [];
if ($name === '' || mb_strlen($name) < 2)        $errors['username']    = 'Name must be at least 2 characters.';
if ($interest === '')                             $errors['interest']    = 'Please select interest.';
if ($description === '')                          $errors['description'] = 'Please select description.';
if ($studyLevel === '')                           $errors['studyLevel']  = 'Please select study level.';

if ($errors) {
  echo json_encode(['ok' => false, 'errors' => $errors]);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("UPDATE users
                               SET name = ?, interest = ?, descp = ?, study_level = ?
                             WHERE id = ? LIMIT 1");
    $stmt->bind_param('ssssi', $name, $interest, $description, $studyLevel, $uid);
    $stmt->execute();

    echo json_encode(['ok' => true, 'message' => 'Profile updated successfully.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => ['_form' => 'Server error. Please try again.']]);
}
