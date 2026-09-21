<?php
require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST only');}
$roomId=require_room_id($_GET['room']??''); $room=$store->get($roomId); if(!$room){header('Location: '.route_url('home'));exit;}
if(!is_room_host($roomId)){http_response_code(403);exit('Host access required.');}
if(!csrf_matches($roomId,$_POST['csrf']??null)){http_response_code(403);exit('Invalid session token.');}
$store->delete($roomId); clear_room_session($roomId); header('Location: '.route_url('home'));exit;
