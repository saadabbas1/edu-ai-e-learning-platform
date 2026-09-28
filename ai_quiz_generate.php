<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

/* =========================================================
   BASIC VALIDATION
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$quizFormat = $_POST['quizFormat'] ?? '';
$types      = $_POST['types'] ?? [];

if (!$quizFormat || !is_array($types) || empty($types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quiz input']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

/* =========================================================
   FILE TEXT EXTRACTION
========================================================= */
$fileTmp  = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$text     = '';

if ($ext === 'pdf') {
    $text = shell_exec('pdftotext ' . escapeshellarg($fileTmp) . ' -');
}
elseif ($ext === 'txt') {
    $text = file_get_contents($fileTmp);
}
elseif ($ext === 'docx') {
    $zip = new ZipArchive();
    if ($zip->open($fileTmp) === true) {
        $xml  = $zip->getFromName('word/document.xml');
        $text = strip_tags($xml);
        $zip->close();
    }
}

$text = trim(preg_replace('/\s+/', ' ', (string)$text));

if (strlen($text) < 150) {
    http_response_code(400);
    echo json_encode(['error' => 'Not enough readable content']);
    exit;
}

/* =========================================================
   AI PROMPT (PROFESSIONAL + TYPE-SAFE)
========================================================= */
$typeList = implode(', ', $types);

$prompt = <<<PROMPT
You are a university-level exam paper setter.

STRICT RULES:
1. Use ONLY the provided content.
2. Do NOT copy sentences verbatim.
3. Language must be formal and academic.
4. Questions must test understanding, not memorization.
5. NEVER include answers inside questions.
6. NO bullet symbols, dots, or formatting characters.
7. Output VALID JSON ONLY.

QUESTION TYPES RULES:
- mcq: exactly 4 options, 1 correct (A/B/C/D)
- short: concise conceptual question
- long: analytical or explanatory question
- tf: statement suitable for True/False

OUTPUT FORMAT (STRICT):

{
  "items": [
    {
      "type": "mcq",
      "q": "Question text",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "answer": "B"
    },
    {
      "type": "short",
      "q": "Question text",
      "answer": "Expected answer"
    },
    {
      "type": "long",
      "q": "Question text",
      "answer": "Expected answer"
    },
    {
      "type": "tf",
      "q": "Statement",
      "answer": "True"
    }
  ]
}

TASK:
Generate EXACTLY 10 QUESTIONS FOR EACH QUESTION TYPE listed.
Allowed question types: {$typeList}

CONTENT:
\"\"\"{$text}\"\"\"
PROMPT;

/* =========================================================
   OPENAI REQUEST
========================================================= */
$payload = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => "You generate professional academic quizzes."],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.3
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
curl_close($ch);

if (!$response) {
    http_response_code(500);
    echo json_encode(['error' => 'AI request failed']);
    exit;
}

$data = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? '';

$decoded = json_decode($content, true);

if (!$decoded || !isset($decoded['items']) || !is_array($decoded['items'])) {
    http_response_code(500);
    echo json_encode(['error' => 'AI returned invalid format']);
    exit;
}

/* =========================================================
   FINAL RESPONSE (FRONTEND SAFE)
========================================================= */
echo json_encode([
    'title' => 'AI Quiz (Professional)',
    'meta' => [
        'format' => $quizFormat,
        'types'  => $types,
        'file'   => $fileName,
        'generated_at' => date('Y-m-d H:i:s')
    ],
    'items' => $decoded['items']
], JSON_PRETTY_PRINT);
