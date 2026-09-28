<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit;
}
$userId = (int)$_SESSION['user_id'];

try {
  $DB_HOST='127.0.0.1'; $DB_NAME='edu_ai'; $DB_USER='root'; $DB_PASS=''; $DB_CHARSET='utf8mb4';
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET",$DB_USER,$DB_PASS,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB connect failed','detail'=>$e->getMessage()]); exit;
}

$TABLE_ATTEMPTS='quiz_attempts';
$pick = function(array $row, array $cands, $fb=null){ foreach($cands as $k){ if(array_key_exists($k,$row) && $row[$k]!==null && $row[$k]!=='') return $row[$k]; } return $fb; };

try{
  $cols = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
  $cols->execute([':t'=>$TABLE_ATTEMPTS]);
  $existing = array_map(fn($r)=>$r['COLUMN_NAME'],$cols->fetchAll());
  if (!$existing) throw new RuntimeException("Table not found: $TABLE_ATTEMPTS");

  $orderCol = null; foreach (['created_at','datetime','date','timestamp','createdAt','created'] as $c) if (in_array($c,$existing,true)) { $orderCol=$c; break; }
  $sql = "SELECT * FROM `$TABLE_ATTEMPTS` WHERE `user_id` = :uid";
  if ($orderCol) $sql.=" ORDER BY `$orderCol` DESC"; else if (in_array('id',$existing,true)) $sql.=" ORDER BY `id` DESC";
  $sql .= " LIMIT 200";
  $st = $pdo->prepare($sql); $st->execute([':uid'=>$userId]);
  $rows = $st->fetchAll();

  $attempts=[];
  foreach($rows as $r){
    $attempt_pk = $pick($r, ['id','attempt_id','quiz_attempt_id','attempt','attemptID','attemptId','attempt_pk'], null);
    $score      = $pick($r, ['score','obtained','marks','correct_answers'], null);
    $max_score  = $pick($r, ['max_score','total','outof','total_marks','total_questions'], null);
    if ($score!==null && is_numeric($score)) $score=(int)$score;
    if ($max_score!==null && is_numeric($max_score)) $max_score=(int)$max_score;

    $attempts[] = [
      'attempt_pk'  => $attempt_pk,
      'created_at'  => $pick($r,['created_at','datetime','date','timestamp','createdAt','created'],''),
      'lesson_title'=> $pick($r,['lesson_title','topic','title','subject','quiz_title','chapter'],''),
      'course_level'=> $pick($r,['course_level','level','difficulty','grade'],''),
      'score'       => $score,
      'max_score'   => $max_score
      // (answers intentionally omitted here; detail endpoint will fetch them reliably)
    ];
  }

  echo json_encode(['attempts'=>$attempts], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Query failed','detail'=>$e->getMessage()]);
}
