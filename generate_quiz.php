<?php
// /edu_ai/generate_quiz.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require __DIR__ . '/config.php';

function jexit($data, $code = 200){
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---------------- helpers ---------------- */
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

function norm_text($s){
  $s = strtolower(trim((string)$s));
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

function unique_by_question($items){
  $seen = [];
  $out = [];
  foreach ($items as $it){
    $q = norm_text($it['question'] ?? '');
    if ($q === '') continue;
    if (isset($seen[$q])) continue;
    $seen[$q] = 1;
    $out[] = $it;
  }
  return $out;
}

/* ---------------- OpenAI call ---------------- */
function openai_call($apiKey, $messages, $max_tokens = 2500){
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

/* ---------------- JSON repair using OpenAI ---------------- */
function repair_to_schema($apiKey, $rawText, $schemaHint, $max_tokens = 2000){
  $sys = "You are a JSON repair tool. Output ONLY valid JSON that matches the schema. No markdown. No commentary.";
  $usr = "Convert the following content into valid JSON matching this schema:\n\nSCHEMA:\n{$schemaHint}\n\nCONTENT:\n{$rawText}";
  $r = openai_call($apiKey, [
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$usr]
  ], $max_tokens);

  if (!$r['ok']) return null;
  $content = $r['json']['choices'][0]['message']['content'] ?? '';
  return decode_json_relaxed($content);
}

/* ---------------- API key ---------------- */
$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : getenv('OPENAI_API_KEY');
if (!$apiKey) jexit(['ok'=>false,'error'=>'OPENAI_API_KEY missing'],500);

/* ---------------- input ---------------- */
$lesson_file = $_POST['lesson_file'] ?? '';
if (!$lesson_file) jexit(['ok'=>false,'error'=>'lesson_file missing'],400);

$path = $_SERVER['DOCUMENT_ROOT'].'/'.ltrim($lesson_file,'/');
if (!is_file($path)) jexit(['ok'=>false,'error'=>'Lesson file not found','detail'=>$path],404);

/* ---------------- read lesson ---------------- */
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$text = '';

if ($ext === 'txt') {
  $text = file_get_contents($path);
}
elseif ($ext === 'pdf') {
  if (!function_exists('shell_exec')) {
    jexit(['ok'=>false,'error'=>'PDF extraction not supported','detail'=>'shell_exec disabled'],500);
  }
  $text = (string)shell_exec('pdftotext '.escapeshellarg($path).' - 2>&1');
}
else {
  jexit(['ok'=>false,'error'=>'Only TXT or PDF allowed'],400);
}

$text = trim($text);
if (strlen($text) < 300) {
  jexit(['ok'=>false,'error'=>'Lesson content too short','detail'=>'Extracted text < 300 chars. PDF may be scanned.'],400);
}

/* --- prevent token overflow: cap material --- */
$MAX_CHARS = 12000;
if (strlen($text) > $MAX_CHARS) $text = substr($text, 0, $MAX_CHARS);

$material = "STUDY MATERIAL:\n".$text;

/* ---------------- generator core ---------------- */
function generate_exact_set($apiKey, $material, $type, $expectedCount){
  // $type: 'mcq' | 'short' | 'long'
  if ($type === 'mcq') {
    $key = 'mcqs';
    $schemaHint = <<<JSON
{
  "mcqs":[
    {"question":"...","options":["A","B","C","D"],"correct_index":0}
  ]
}
JSON;

    $system = <<<SYS
You are Edu AI.
Generate EXACTLY {$expectedCount} multiple-choice questions from the study material.
Rules:
- Each must have 4 options.
- correct_index must be 0-3.
- Use ONLY the study material.
- Return ONLY valid JSON (no markdown, no extra text).
Schema:
{$schemaHint}
SYS;

    $maxTokens = 2800;
  }
  elseif ($type === 'short') {
    $key = 'shorts';
    $schemaHint = <<<JSON
{
  "shorts":[
    {"question":"...","answer":"..."}
  ]
}
JSON;

    $system = <<<SYS
You are Edu AI.
Generate EXACTLY {$expectedCount} short-answer questions from the study material.
Each must include a concise answer.
Return ONLY valid JSON (no markdown, no extra text).
Schema:
{$schemaHint}
SYS;

    $maxTokens = 1600;
  }
  else { // long
    $key = 'longs';
    $schemaHint = <<<JSON
{
  "longs":[
    {"question":"...","answer":"..."}
  ]
}
JSON;

    $system = <<<SYS
You are Edu AI.
Generate EXACTLY {$expectedCount} long-answer questions from the study material.
Each must include a detailed answer.
Return ONLY valid JSON (no markdown, no extra text).
Schema:
{$schemaHint}
SYS;

    $maxTokens = 2200;
  }

  $items = [];
  $tries = 4;
  $lastPreview = '';
  $lastDetail  = '';

  for ($t=1; $t<=$tries; $t++){
    // If we already have some items, ask only for the remaining number, avoiding duplicates.
    $remaining = $expectedCount - count($items);
    if ($remaining <= 0) break;

    $avoid = '';
    if (!empty($items)) {
      $qs = array_map(function($it){ return $it['question'] ?? ''; }, $items);
      $avoid = "\n\nAvoid duplicating these questions:\n- " . implode("\n- ", array_slice($qs, 0, 50));
    }

    $sys2 = str_replace("EXACTLY {$expectedCount}", "EXACTLY {$remaining}", $system);

    $res = openai_call($apiKey, [
      ['role'=>'system','content'=>$sys2],
      ['role'=>'user','content'=>$material . $avoid]
    ], $maxTokens);

    if (!$res['ok']) {
      $lastDetail = "OpenAI HTTP ".$res['http']." — ".substr((string)$res['detail'], 0, 700);
      continue;
    }

    $content = $res['json']['choices'][0]['message']['content'] ?? '';
    $lastPreview = substr((string)$content, 0, 700);

    $decoded = decode_json_relaxed($content);
    if (!is_array($decoded) || !isset($decoded[$key]) || !is_array($decoded[$key])) {
      // try JSON repair once for this attempt
      $repaired = repair_to_schema($apiKey, $content, $schemaHint, 1800);
      if (is_array($repaired) && isset($repaired[$key]) && is_array($repaired[$key])) {
        $decoded = $repaired;
      } else {
        continue;
      }
    }

    $batch = $decoded[$key];
    // normalize & merge
    $merged = array_merge($items, $batch);
    $merged = unique_by_question($merged);

    // If MCQ, enforce options length and correct_index bounds
    if ($type === 'mcq') {
      $clean = [];
      foreach ($merged as $q){
        $opts = $q['options'] ?? [];
        if (!is_array($opts)) $opts = [];
        $opts = array_values(array_slice($opts, 0, 4));
        if (count($opts) < 4) continue;
        $ci = (int)($q['correct_index'] ?? 0);
        if ($ci < 0 || $ci > 3) $ci = 0;
        $clean[] = [
          'question' => (string)($q['question'] ?? ''),
          'options' => $opts,
          'correct_index' => $ci
        ];
      }
      $items = unique_by_question($clean);
    } else {
      $items = $merged;
    }
  }

  // If model returns more than needed, trim.
  if (count($items) > $expectedCount) {
    $items = array_slice($items, 0, $expectedCount);
  }

  if (count($items) !== $expectedCount) {
    return ['ok'=>false, 'detail'=>$lastDetail ?: 'Model output not valid JSON or not exactly expected count.', 'preview'=>$lastPreview];
  }

  return ['ok'=>true, 'items'=>$items];
}

/* ---------------- run generators ---------------- */
$mcqGen = generate_exact_set($apiKey, $material, 'mcq', 5);
if (!$mcqGen['ok']) {
  jexit(['ok'=>false,'error'=>'Failed to generate 5 MCQs','detail'=>$mcqGen['detail'],'raw_preview'=>$mcqGen['preview']], 502);
}

$shortGen = generate_exact_set($apiKey, $material, 'short', 3);
if (!$shortGen['ok']) {
  jexit(['ok'=>false,'error'=>'Failed to generate 3 short questions','detail'=>$shortGen['detail'],'raw_preview'=>$shortGen['preview']], 502);
}

$longGen = generate_exact_set($apiKey, $material, 'long', 1);
if (!$longGen['ok']) {
  jexit(['ok'=>false,'error'=>'Failed to generate 1 long question','detail'=>$longGen['detail'],'raw_preview'=>$longGen['preview']], 502);
}

/* ---------------- success ---------------- */
jexit([
  'ok'=>true,
  'quiz'=>[
    'mcqs'   => $mcqGen['items'],
    'shorts' => $shortGen['items'],
    'longs'  => $longGen['items']
  ]
], 200);
