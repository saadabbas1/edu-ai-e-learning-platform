<?php
// /edu_ai/delete_quiz_history.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$dsn  = 'mysql:host=localhost;dbname=edu_ai;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB connection failed']); exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$ids = $body['ids'] ?? [];
$ids = array_values(array_filter(array_map('intval', (array)$ids)));

if (!$ids) { echo json_encode(['ok'=>false,'error'=>'No IDs provided']); exit; }

$in = implode(',', array_fill(0, count($ids), '?'));
$pdo->beginTransaction();
try {
  // answers cascade by FK if you created the FK; else delete manually:
  $pdo->prepare("DELETE FROM quiz_answers WHERE attempt_id IN ($in)")->execute($ids);
  $pdo->prepare("DELETE FROM quiz_attempts WHERE id IN ($in)")->execute($ids);
  $pdo->commit();
  echo json_encode(['ok'=>true,'deleted'=>count($ids)]);
} catch (Throwable $e) {
  $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>'Delete failed']);
}
