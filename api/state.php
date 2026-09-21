<?php
require dirname(__DIR__).'/bootstrap.php';
$roomId=require_room_id($_GET['room']??''); $room=$store->get($roomId);
if(!$room)json_response(['ok'=>false,'error'=>'Room expired'],404);
json_response(['ok'=>true,'state'=>room_public_state($room),'backend'=>$store->backend()]);
