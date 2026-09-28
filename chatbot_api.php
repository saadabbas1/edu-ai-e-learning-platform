<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

/* -------------------- BASIC VALIDATION -------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$userMessage = trim($_POST['message'] ?? '');
if ($userMessage === '' && empty($_FILES['file'])) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

/* -------------------- SESSION MEMORY (PHASE 2) -------------------- */
$MAX_HISTORY = 8;

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

if (!isset($_SESSION['chat_file_context'])) {
    $_SESSION['chat_file_context'] = '';
}

/* -------------------- FILE UPLOAD HANDLING -------------------- */
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $tmpPath = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $savedPath = $uploadDir . time() . '_' . $fileName;

    move_uploaded_file($tmpPath, $savedPath);

    // Extract text
    $extractedText = '';

    if ($ext === 'txt') {
        $extractedText = file_get_contents($savedPath);
    } elseif ($ext === 'pdf') {
        $extractedText = shell_exec('pdftotext ' . escapeshellarg($savedPath) . ' -');
    } elseif ($ext === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($savedPath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $extractedText = strip_tags($xml);
        }
    }

    $extractedText = trim(preg_replace('/\s+/', ' ', (string)$extractedText));

    if (strlen($extractedText) > 200) {
        $_SESSION['chat_file_context'] = $extractedText;
    }
}

/* -------------------- BUILD AI MESSAGES -------------------- */
$messages = [
    [
        'role' => 'system',
        'content' =>
            "You are Edu AI, an academic assistant. " .
            "Be clear, professional, and helpful. " .
            "Avoid markdown symbols like *, #, -, or bullets."
    ]
];

// Inject file context (Phase-2)
if (!empty($_SESSION['chat_file_context'])) {
    $messages[] = [
        'role' => 'system',
        'content' =>
            "The user uploaded study material. " .
            "Answer ONLY using this content:\n\n" .
            $_SESSION['chat_file_context']
    ];
}

// Previous chat memory
foreach ($_SESSION['chat_history'] as $msg) {
    $messages[] = $msg;
}

// Current user message
if ($userMessage !== '') {
    $messages[] = [
        'role' => 'user',
        'content' => $userMessage
    ];
}

/* -------------------- OPENAI REQUEST -------------------- */
$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => $messages,
    'temperature' => 0.4
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
if (!$response) {
    echo json_encode(['error' => 'AI request failed']);
    exit;
}

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? 'No response';

/* -------------------- SAVE TO SESSION HISTORY -------------------- */
if ($userMessage !== '') {
    $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];
}
$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $reply];

// Trim history
if (count($_SESSION['chat_history']) > $MAX_HISTORY * 2) {
    $_SESSION['chat_history'] = array_slice(
        $_SESSION['chat_history'],
        -$MAX_HISTORY * 2
    );
}

/* -------------------- FINAL RESPONSE -------------------- */
echo json_encode([
    'reply' => $reply
]);
