<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST only'],405);
reject_oversized_json_body(524288);
$payload=json_decode(file_get_contents('php://input'),true);if(!is_array($payload))$payload=[];
$token=(string)($payload['sync_token']??'');$auth=offline_sync_token_parse($token);if(!$auth)json_response(['ok'=>false,'error'=>'Offline sync token expired. Verify again when online.'],401);
if(!request_rate_limit('offline-sync',30,60,(string)$auth['u'].'|'.(string)$auth['d']))json_response(['ok'=>false,'error'=>'Too many sync requests. Please retry shortly.'],429);
$events=is_array($payload['events']??null)?$payload['events']:[];
if(count($events)>250)json_response(['ok'=>false,'error'=>'Too many offline events in one sync request'],422);
$result=$store->syncOfflineEvents((string)$auth['u'],(string)$auth['d'],$events);
json_response(['ok'=>true]+$result);
