<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(['ok' => false, 'errors' => ['_form' => 'Please log in first.']]);
  exit;
}
