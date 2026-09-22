<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST only'],405);
$payload=json_request_payload(524288);$token=(string)($payload['sync_token']??'');$auth=offline_sync_token_parse($token);if(!$auth)json_response(['ok'=>false,'error'=>'Offline sync token expired. Verify again when online.'],401);
$rate=$store->rateLimit('offline-sync',(string)$auth['d'],20,300);if(!$rate['allowed']){rate_limit_retry_header($rate);json_response(['ok'=>false,'error'=>'Offline sync rate limit exceeded'],429);}
$events=is_array($payload['events']??null)?$payload['events']:[];if(count($events)>250)json_response(['ok'=>false,'error'=>'Too many offline events in one sync request'],422);$result=$store->syncOfflineEvents((string)$auth['u'],(string)$auth['d'],$events);json_response(['ok'=>true]+$result);
