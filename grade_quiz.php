<?php
// /edu_ai/grade_quiz.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require __DIR__ . '/config.php';

function jexit($d,$c=200){
  http_response_code($c);
  echo json_encode($d, JSON_UNESCAPED_UNICODE);
  exit;
}

function strip_code_fences($s){
  $s = trim((string)$s);
  $s = preg_replace('/^\s*```(?:json)?\s*/i', '', $s);
  $s = preg_replace('/\s*```\s*$/', '', $s);
  return trim($s);
}

function extract_first_json_object($s){
  $s = strip_code_fences($s);
  $start = strpos($s, '{');
  if ($start === false) return $s;
  $substr = substr($s, $start);
  $end = strrpos($substr, '}');
  if ($end === false) return $substr;
  return substr($substr, 0, $end + 1);
}

function decode_json_relaxed($s){
  $s = extract_first_json_object($s);
  $j = json_decode($s, true);
  return is_array($j) ? $j : null;
}

function openai_call($apiKey, $messages, $max_tokens = 1200){
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer '.$apiKey,
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
      'model' => 'gpt-4o-mini',
      'messages' => $messages,
      'temperature' => 0.2,
      'max_tokens' => $max_tokens
    ], JSON_UNESCAPED_UNICODE)
  ]);

  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($res === false) return ['ok'=>false, 'http'=>$http ?: 0, 'detail'=>'cURL error: '.$err];
  if ($http >= 400)    return ['ok'=>false, 'http'=>$http, 'detail'=>$res];

  $json = json_decode($res, true);
  if (!is_array($json)) return ['ok'=>false, 'http'=>$http, 'detail'=>'OpenAI returned invalid JSON', 'raw'=>substr($res,0,800)];
  return ['ok'=>true, 'http'=>$http, 'json'=>$json];
}

/* ---------------- API key ---------------- */
$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : getenv('OPENAI_API_KEY');
if (!$apiKey) jexit(['ok'=>false,'error'=>'Missing API key'],500);

/* ---------------- input ---------------- */
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) jexit(['ok'=>false,'error'=>'Invalid JSON payload'],400);

$quiz    = $payload['quiz'] ?? null;
$answers = $payload['answers'] ?? null;

if (!is_array($quiz) || !is_array($answers)) {
  jexit(['ok'=>false,'error'=>'Missing quiz or answers in payload'],400);
}
if (!isset($quiz['mcqs'], $quiz['shorts'], $quiz['longs'])) {
  jexit(['ok'=>false,'error'=>'Quiz object missing mcqs/shorts/longs'],400);
}

/* ---------------- MCQ grading ---------------- */
$mcqScore=0; $mcqMax=0;

foreach ((array)$quiz['mcqs'] as $i=>$q){
  $mcqMax++;
  $correct = (int)($q['correct_index'] ?? -999);
  $given   = isset($answers['mcqs'][$i]) ? (int)$answers['mcqs'][$i] : -1;
  if ($given === $correct) $mcqScore++;
}

/* ---------------- AI grading schema ---------------- */
$shortCount = count((array)$quiz['shorts']);
$longCount  = count((array)$quiz['longs']);

$shortMax = $shortCount * 2; // 0-2 each
$longMax  = $longCount  * 4; // 0-4 each

$schemaHint = <<<JSON
{
  "shorts":[
    {"index":0,"score":0,"feedback":"..."}
  ],
  "longs":[
    {"index":0,"score":0,"feedback":"..."}
  ]
}
JSON;

$system = <<<SYS
You are a strict quiz grader.
Grade based ONLY on the provided reference answers in the quiz object.

Return ONLY valid JSON matching this schema (no markdown, no extra text):
{$schemaHint}

Scoring rules:
- Each short question: score 0..2
- Each long question: score 0..4
- Provide brief feedback for each.
- Return EXACTLY {$shortCount} shorts items and {$longCount} longs items with correct indexes.
SYS;

$userObj = [
  'quiz' => [
    'shorts' => $quiz['shorts'], // each has question + reference answer
    'longs'  => $quiz['longs']
  ],
  'student_answers' => [
    'shorts' => $answers['shorts'] ?? [],
    'longs'  => $answers['longs'] ?? []
  ]
];

$res = openai_call($apiKey, [
  ['role'=>'system','content'=>$system],
  ['role'=>'user','content'=>json_encode($userObj, JSON_UNESCAPED_UNICODE)]
], 1400);

if (!$res['ok']) {
  jexit([
    'ok'=>false,
    'error'=>'AI grading request failed',
    'http'=>$res['http'],
    'detail'=>substr((string)$res['detail'], 0, 900)
  ], 502);
}

$content = $res['json']['choices'][0]['message']['content'] ?? '';
$graded  = decode_json_relaxed($content);

if (!is_array($graded) || !isset($graded['shorts'], $graded['longs'])) {
  jexit([
    'ok'=>false,
    'error'=>'AI returned invalid grading JSON',
    'raw_preview'=>substr((string)$content, 0, 900)
  ], 502);
}

/* ---------------- sum AI scores safely ---------------- */
$shortScore = 0;
$longScore  = 0;

foreach ((array)$graded['shorts'] as $s) $shortScore += (int)($s['score'] ?? 0);
foreach ((array)$graded['longs']  as $l) $longScore  += (int)($l['score'] ?? 0);

/* clamp to max just in case */
if ($shortScore < 0) $shortScore = 0;
if ($longScore  < 0) $longScore  = 0;
if ($shortScore > $shortMax) $shortScore = $shortMax;
if ($longScore  > $longMax)  $longScore  = $longMax;

jexit([
  'ok'=>true,
  'scores'=>[
    'mcq'=>['score'=>$mcqScore,'max'=>$mcqMax],
    'short'=>['score'=>$shortScore,'max'=>$shortMax],
    'long'=>['score'=>$longScore,'max'=>$longMax],
    'total'=>[
      'score'=>$mcqScore+$shortScore+$longScore,
      'max'=>$mcqMax+$shortMax+$longMax
    ]
  ],
  'ai_detail'=>[
    'shorts'=>$graded['shorts'],
    'longs'=>$graded['longs']
  ]
], 200);
