<?php
// C:\xampp\htdocs\edu_ai\profile_get.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');

    $uid = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT id, name, email, role, interest, descp, study_level, created_at
                            FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'errors' => ['_form' => 'User not found.']]);
        exit;
    }
    $u = $res->fetch_assoc();

    echo json_encode([
        'ok' => true,
        'user' => [
            'id'          => (int)$u['id'],
            'name'        => $u['name'],
            'email'       => $u['email'],     // usually read-only
            'role'        => $u['role'],
            'interest'    => $u['interest'],
            'description' => $u['descp'],
            'studyLevel'  => $u['study_level'],
            'created_at'  => $u['created_at'],
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => ['_form' => 'Server error.']]);
}
