<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST only'],405);
reject_oversized_json_body(16384);
if(!global_csrf_matches($_SERVER['HTTP_X_CSRF']??null))json_response(['ok'=>false,'error'=>'Session token mismatch'],403);
if(!request_rate_limit('qoi-network',120,3600,today_key()))json_response(['ok'=>false,'error'=>'Too many responses from this network. Please try again later.'],429);
$p=json_decode(file_get_contents('php://input'),true);if(!is_array($p))$p=[];
$card=question_of_india_card();
$answer=is_string($p['answer']??null)?trim((string)$p['answer']):'';
$deviceId=is_string($p['device_install_id']??null)?strtolower(trim((string)$p['device_install_id'])):'';
$deviceHash=device_identity($deviceId);
if($deviceHash==='')json_response(['ok'=>false,'error'=>'Secure device identity unavailable'],422);
if(!request_rate_limit('qoi-device',3,86400,today_key().'|'.$deviceHash))json_response(['ok'=>false,'error'=>'This device has already submitted today.'],429);
if(!card_answer_valid($card,$answer))json_response(['ok'=>false,'error'=>'Invalid answer'],422);
$key='qoi:'.today_key().':'.$card['id'];
$agg=$store->recordAggregateResponse($key,$answer,$deviceHash);
$correct=($card['kind']??'')==='quiz'?hash_equals((string)($card['correct']??''),$answer):null;
$store->appendSyncEvent(['event_type'=>'question_of_india_answered','device_hash'=>$deviceHash,'card_id'=>(string)$card['id'],'answer_id'=>$answer,'correct'=>$correct,'daily_key'=>today_key()]);
json_response(['ok'=>true,'aggregate'=>$agg,'correct'=>$correct,'correct_answer'=>(($card['kind']??'')==='quiz'?(string)($card['correct']??''):null),'explain'=>(string)($card['explain']??'')]);
