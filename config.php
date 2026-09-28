<?php
/**
 * EDU AI configuration.
 * Configure these values as environment variables on your local/server environment.
 */

function env_value(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_NAME', env_value('DB_NAME', 'edu_ai'));

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

define('OPENAI_API_KEY', env_value('OPENAI_API_KEY'));
define('MAIL_USER', env_value('MAIL_USER'));
define('MAIL_PASS', env_value('MAIL_PASS'));
