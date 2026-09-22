<?php
require dirname(__DIR__).'/bootstrap.php';
$roomId=require_room_id($_GET['room']??'');$room=$store->get($roomId);
if(!$room)json_response(['ok'=>false,'error'=>'Room expired'],404);
$provided=is_string($_GET['token']??null)?trim((string)$_GET['token']):'';$expected=(string)($room['state_token']??'');
$allowed=is_room_host($roomId)||($expected!==''&&$provided!==''&&hash_equals($expected,$provided));
if(!$allowed)json_response(['ok'=>false,'error'=>'Presenter state access denied'],403);
json_response(['ok'=>true,'state'=>room_public_state($room),'backend'=>$store->backend()]);
