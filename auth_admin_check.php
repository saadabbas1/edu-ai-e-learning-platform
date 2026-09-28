<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Forbidden (admin only)']);
  exit;
}