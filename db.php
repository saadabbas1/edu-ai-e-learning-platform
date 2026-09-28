<?php
// db.php
require_once __DIR__ . '/config.php';

// (Optional) Keep mysqli if other files use it:
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    // Don't die here; PDO below is the primary connection for APIs.
}

// ✅ Define DSN & create PDO (this is what your quiz APIs use)
$dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$user = DB_USER;
$pass = DB_PASS;

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    // Keep output clean JSON for API callers
    echo json_encode(['ok' => false, 'error' => 'Database connection failed.']);
    exit;
}
