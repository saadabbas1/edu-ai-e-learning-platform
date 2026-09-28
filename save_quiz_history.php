<?php
// save_quiz_history.php — robust attempt + answers save (single transaction)
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
session_start();

/* ---- DB CONFIG (adjust if needed) ---- */
$DB_HOST    = '127.0.0.1';
$DB_NAME    = 'edu_ai';
$DB_USER    = 'root';
$DB_PASS    = '';
$DB_CHARSET = 'utf8mb4';

/* ---- helpers ---- */
function jexit($data, int $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function read_json_body(): ?array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
      $j = json_decode($raw, true);
      if (is_array($j)) return $j;
    }
  }
  return null;
}
function table_exists(PDO $pdo, string $t): bool {
  try { $pdo->query("DESCRIBE `$t`"); return true; } catch (Throwable $e) { return false; }
}
function columns(PDO $pdo, string $t): array {
  $st = $pdo->prepare("DESCRIBE `$t`");
  $st->execute();
  return array_map(fn($r) => $r['Field'], $st->fetchAll(PDO::FETCH_ASSOC));
}

/* ---- connect ---- */
try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  jexit(['ok'=>false,'error'=>'DB connection failed','detail'=>$e->getMessage()], 500);
}

/* ---- resolve table names ---- */
$ATTEMPTS = null;  // quiz_attempts or quiz_attemps
$ANSWERS  = null;  // quiz_answers
if (table_exists($pdo, 'quiz_attempts')) $ATTEMPTS = 'quiz_attempts';
elseif (table_exists($pdo, 'quiz_attemps')) $ATTEMPTS = 'quiz_attemps';
else jexit(['ok'=>false,'error'=>'Attempts table not found (quiz_attempts/quiz_attemps)'], 500);

if (table_exists($pdo, 'quiz_answers')) $ANSWERS = 'quiz_answers';
else jexit(['ok'=>false,'error'=>'Answers table not found (quiz_answers)'], 500);

$attemptCols = columns($pdo, $ATTEMPTS);
$answerCols  = columns($pdo, $ANSWERS);

/* ---- input ---- */
$in = read_json_body();
if ($in === null) {
  // allow traditional form posting too
  $in = [
    'course_id'    => $_POST['course_id']    ?? null,
    'course_level' => $_POST['course_level'] ?? null,
    'level'        => $_POST['level']        ?? null,
    'lesson_title' => $_POST['lesson_title'] ?? null,
    'feedback'     => $_POST['feedback']     ?? null,
    'score'        => isset($_POST['score']) ? (int)$_POST['score'] : 0,
    'max_score'    => isset($_POST['max_score']) ? (int)$_POST['max_score'] : 0,
    'mcqs'         => isset($_POST['mcqs'])   ? json_decode((string)$_POST['mcqs'], true) : [],
    'shorts'       => isset($_POST['shorts']) ? json_decode((string)$_POST['shorts'], true) : [],
    'long'         => isset($_POST['long'])   ? json_decode((string)$_POST['long'], true)   : [],
  ];
}

$user_id   = isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '' ? (int)$_SESSION['user_id'] : 0;
$course_id = (string)($in['course_id'] ?? '');
$title     = trim((string)($in['lesson_title'] ?? 'Untitled Quiz'));
$level     = trim((string)($in['level'] ?? $in['course_level'] ?? ''));
$feedback  = trim((string)($in['feedback'] ?? ''));
$score     = (int)($in['score'] ?? 0);
$max_score = (int)($in['max_score'] ?? 0);

$mcqs  = is_array($in['mcqs'] ?? null)   ? $in['mcqs']   : [];
$short = is_array($in['shorts'] ?? null) ? $in['shorts'] : [];
$long  = is_array($in['long'] ?? null)   ? $in['long']   : [];

/* ---- attempt insert: build column list that actually exists ---- */
$attemptData = [
  'user_id'   => $user_id,
  'course_id' => $course_id,
  'title'     => $title,
  'level'     => $level,
  'feedback'  => $feedback,
  'score'     => $score,
  'max_score' => $max_score,
  // created_at should be DEFAULT CURRENT_TIMESTAMP if present
];

$useAttemptCols = array_values(array_intersect(array_keys($attemptData), $attemptCols));
if (!$useAttemptCols) jexit(['ok'=>false,'error'=>'No matching attempt columns found in DB'], 500);

$attemptSQL = "INSERT INTO `$ATTEMPTS` (" .
  implode(',', array_map(fn($c) => "`$c`", $useAttemptCols)) .
  ") VALUES (" .
  implode(',', array_map(fn($c) => ":" . $c, $useAttemptCols)) .
  ")";
$attemptStmt = $pdo->prepare($attemptSQL);
$attemptBind = [];
foreach ($useAttemptCols as $c) $attemptBind[":$c"] = $attemptData[$c];

/* ---- answers insert prepared statements ---- */
if (!in_array('attempt_id', $answerCols, true)) {
  jexit(['ok'=>false,'error'=>"$ANSWERS table must have attempt_id column"], 500);
}

// Flexible answer columns
$maybeCols = ['question_id','question','type','answer','correct_answer','points','max_points','options_json'];
$useAnswerCols = array_values(array_intersect(array_merge(['attempt_id'], $maybeCols), $answerCols));
$ansSQL = "INSERT INTO `$ANSWERS` (" .
  implode(',', array_map(fn($c) => "`$c`", $useAnswerCols)) .
  ") VALUES (" .
  implode(',', array_map(fn($c) => ":" . $c, $useAnswerCols)) .
  ")";
$ansStmt = $pdo->prepare($ansSQL);

/* ---- begin transaction ---- */
try {
  $pdo->beginTransaction();

  // 1) Attempt
  $attemptStmt->execute($attemptBind);
  $attempt_id = (int)$pdo->lastInsertId();
  if ($attempt_id <= 0) {
    throw new RuntimeException('Attempt insert returned invalid lastInsertId()');
  }

  // helper to exec one answer row
  $execAnswer = function(array $data) use ($ansStmt, $useAnswerCols, $attempt_id) {
    $bind = [':attempt_id' => $attempt_id];
    foreach ($useAnswerCols as $col) {
      if ($col === 'attempt_id') continue;
      $val = $data[$col] ?? null;
      $bind[":$col"] = $val;
    }
    $ansStmt->execute($bind);
  };

  // 2) MCQs
  foreach ($mcqs as $a) {
    $execAnswer([
      'question_id'     => $a['id'] ?? null,
      'question'        => $a['question'] ?? null,
      'type'            => 'mcq',
      'answer'          => $a['userAnswer'] ?? $a['answer'] ?? null,
      'correct_answer'  => $a['correct'] ?? $a['correct_answer'] ?? null,
      'points'          => (int)($a['points'] ?? 0),
      'max_points'      => (int)($a['max'] ?? $a['max_points'] ?? 1),
      'options_json'    => isset($a['options']) ? json_encode($a['options'], JSON_UNESCAPED_UNICODE) : null,
    ]);
  }

  // 3) shorts
  foreach ($short as $a) {
    $execAnswer([
      'question_id'     => $a['id'] ?? null,
      'question'        => $a['question'] ?? null,
      'type'            => 'short',
      'answer'          => $a['answer'] ?? null,
      'correct_answer'  => $a['gold'] ?? $a['correct_answer'] ?? null,
      'points'          => (int)($a['points'] ?? 0),
      'max_points'      => (int)($a['max'] ?? $a['max_points'] ?? 2),
      'options_json'    => null,
    ]);
  }

  // 4) long
  foreach ($long as $a) {
    $execAnswer([
      'question_id'     => $a['id'] ?? null,
      'question'        => $a['question'] ?? null,
      'type'            => 'long',
      'answer'          => $a['answer'] ?? null,
      'correct_answer'  => $a['gold'] ?? $a['correct_answer'] ?? null,
      'points'          => (int)($a['points'] ?? 0),
      'max_points'      => (int)($a['max'] ?? $a['max_points'] ?? 4),
      'options_json'    => null,
    ]);
  }

  $pdo->commit();
  jexit(['ok'=>true,'attempt_id'=>$attempt_id]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(['ok'=>false,'error'=>'Save failed','detail'=>$e->getMessage()], 500);
}
