<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST only'],405);
$payload=json_decode(file_get_contents('php://input'),true);if(!is_array($payload))$payload=[];$token=(string)($payload['sync_token']??'');$auth=offline_sync_token_parse($token);if(!$auth)json_response(['ok'=>false,'error'=>'Offline sync token expired. Verify again when online.'],401);
$events=is_array($payload['events']??null)?$payload['events']:[];$result=$store->syncOfflineEvents((string)$auth['u'],(string)$auth['d'],$events);json_response(['ok'=>true]+$result);
