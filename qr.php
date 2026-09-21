<?php
require __DIR__.'/bootstrap.php';
$roomId=require_room_id($_GET['room']??''); $room=$store->get($roomId); if(!$room){http_response_code(404);exit;}
$url=absolute_route_url('join',$roomId); $py=__DIR__.'/python/make_qr.py';
header('Content-Type: image/svg+xml; charset=utf-8'); header('Cache-Control: public, max-age=300');
$cmd='python3 '.escapeshellarg($py).' '.escapeshellarg($url).' 2>/dev/null'; $svg=function_exists('shell_exec')?shell_exec($cmd):null;
if(is_string($svg)&&str_contains($svg,'<svg')){echo $svg;exit;}
$label=h(room_label($roomId)); echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 360"><rect width="360" height="360" fill="white"/><rect x="24" y="24" width="312" height="312" rx="24" fill="#f3f0e9"/><text x="180" y="145" text-anchor="middle" font-family="sans-serif" font-size="18" fill="#141510">QR generator unavailable</text><text x="180" y="180" text-anchor="middle" font-family="sans-serif" font-size="13" fill="#74736d">Open the join URL below</text><text x="180" y="220" text-anchor="middle" font-family="monospace" font-size="11" fill="#141510">Session '.$label.'</text></svg>';
