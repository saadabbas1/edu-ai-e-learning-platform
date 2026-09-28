<?php
// C:\xampp\htdocs\edu_ai\signup.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => ['_form' => 'Database connection failed.']]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'errors' => ['_form' => 'Method not allowed']]);
    exit;
}

// Map your frontend fields
$name         = trim($_POST['username']        ?? '');
$email        = trim($_POST['email']           ?? '');
$password     =        $_POST['password']      ?? '';
$confirm      =        $_POST['confirmPassword'] ?? '';
$interest     = trim($_POST['interest']        ?? '');
$descp        = trim($_POST['description']     ?? '');
$study_level  = trim($_POST['studyLevel']      ?? '');
$role         = 'student';

$errors = [];

// Field-level validation (keys match your span IDs without "Error")
if ($name === '') {
    $errors['username'] = 'Username is required.';
} elseif (mb_strlen($name) < 3) {
    $errors['username'] = 'Username must be at least 3 characters long.';
} elseif (!preg_match('/(?:[A-Za-z].*){3,}/', $name)) {
    $errors['username'] = 'Username must contain at least 3 alphabetic letters (A–Z).';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}
if ($password === '') {
    $errors['password'] = 'Password is required.';
} else {
    if (!preg_match('/[A-Z]/', $password)) $errors['password'] = 'At least one capital letter is required.';
    if (!preg_match('/\d/', $password))    $errors['password'] = 'At least one numeric digit is required.';
    if (strlen($password) < 8)             $errors['password'] = 'Minimum 8 characters required.';
}
if ($confirm === '' || $confirm !== $password) {
    $errors['confirmPassword'] = 'Passwords do not match.';
}
if ($interest === '') {
    $errors['interest'] = 'Please select your interest.';
}
if ($descp === '') {
    $errors['description'] = 'Please select a description.';
}
if ($study_level === '') {
    $errors['studyLevel'] = 'Please select your study level.';
}

if ($errors) {
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// Duplicate email check
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['ok' => false, 'errors' => ['email' => 'Email already registered.']]);
    exit;
}
$stmt->close();

// Insert
try {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role, interest, descp, study_level, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('sssssss', $name, $email, $password_hash, $role, $interest, $descp, $study_level);
    $stmt->execute();

    echo json_encode([
        'ok' => true,
        'message' => 'Signup successful! Redirecting you to login…'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => ['_form' => 'Server error. Please try again.']]);
}
