<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
session_start();

/** Quick JSON exit helper */
function jexit($data, int $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  jexit(['ok'=>false,'error'=>'Not authenticated'], 401);
}

/**
 * Accept multiple possible attempt id parameter names because
 * the UI may send any of these depending on the row data.
 */
$keys = ['attempt_pk','id','attempt_id','quiz_attempt_id','attempt','attemptId','attemptID'];
$attemptPk = '';
foreach ($keys as $k) {
  if (isset($_GET[$k]) && trim((string)$_GET[$k]) !== '') {
    $attemptPk = trim((string)$_GET[$k]);
    break;
  }
}
if ($attemptPk === '') {
  jexit(['ok'=>false,'error'=>'Missing attempt id'], 400);
}

try {
  // ---- DB connection (adjust if your creds differ) ----
  $DB_HOST='127.0.0.1';
  $DB_NAME='edu_ai';
  $DB_USER='root';
  $DB_PASS='';
  $DB_CHARSET='utf8mb4';

  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  // Helper to list columns of a table
  $tableColumns = function(PDO $pdo, string $t): array {
    $s=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $s->execute([':t'=>$t]);
    return array_map(fn($r)=>$r['COLUMN_NAME'],$s->fetchAll());
  };

  // Try to locate a likely answers table
  $likes = [
    '%quiz_attempt_answer%', '%attempt_answer%', '%quiz_answers%', '%answers%',
    '%response%', '%submitted_answer%', '%question_answer%'
  ];
  $q = "SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND (" .
        implode(' OR ', array_fill(0,count($likes),'TABLE_NAME LIKE ?')) .
        ") LIMIT 30";
  $st = $pdo->prepare($q);
  $st->execute($likes);
  $answerTables = array_map(fn($r)=>$r['TABLE_NAME'],$st->fetchAll());

  $answersTbl = null;
  $fkCol = null;

  foreach ($answerTables as $t) {
    $cols = $tableColumns($pdo, $t);
    $fkCandidates = ['attempt_pk','attempt_id','quiz_attempt_id','attempt','attemptId','attemptID','id'];
    foreach ($fkCandidates as $c) {
      if (in_array($c, $cols, true)) {
        $answersTbl = $t;
        $fkCol = $c;
        break 2;
      }
    }
  }

  if (!$answersTbl || !$fkCol) {
    jexit(['ok'=>false,'error'=>'Could not locate answers table'], 500);
  }

  // Pull ALL answers for this attempt (no LIMIT 1!)
  $stmt = $pdo->prepare("SELECT * FROM `$answersTbl` WHERE `$fkCol` = :pk ORDER BY 1");
  $stmt->execute([':pk'=>$attemptPk]);
  $rows = $stmt->fetchAll();

  $answers = [];
  foreach ($rows as $r) {
    $question = $r['question']         ?? $r['q_text']       ?? $r['q']               ?? '';
    $answer   = $r['answer']           ?? $r['user_answer']  ?? $r['given']           ?? $r['response'] ?? '';
    $correct  = $r['correct_answer']   ?? $r['solution']     ?? $r['answer_key']      ?? '';
    $is       = $r['is_correct']       ?? $r['correct']      ?? null;
    $answers[] = [
      'question'       => (string)$question,
      'answer'         => (string)$answer,
      'correct_answer' => (string)$correct,
      'is_correct'     => $is === null ? null : (bool)(is_string($is) ? (int)$is : $is),
    ];
  }

  jexit(['ok'=>true,'answers'=>$answers]);

} catch (Throwable $e) {
  jexit(['ok'=>false,'error'=>'Detail query failed','detail'=>$e->getMessage()], 500);
}
