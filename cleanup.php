<?php
require __DIR__.'/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}

$rooms=0;$unreadableRooms=0;$pending=0;
foreach(glob(__DIR__.'/data/rooms/*.json')?:[] as $f){
    $raw=@file_get_contents($f);
    $d=is_string($raw)?secure_unpack($raw):null;
    if(!is_array($d)){$unreadableRooms++;continue;}
    $expiresRaw=(string)($d['expires_at']??'');
    $expiresAt=$expiresRaw!==''?strtotime($expiresRaw):false;
    if($expiresAt!==false&&$expiresAt<time()){if(@unlink($f))$rooms++;}
}
foreach(glob(__DIR__.'/data/imports/pending/*.json')?:[] as $f){
    $raw=@file_get_contents($f);
    $d=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($d)||(int)($d['expires_at']??0)<time()){if(@unlink($f))$pending++;}
}
$userCache=$store->cleanupUserCacheFiles();
echo "Removed {$rooms} expired temporary room(s); {$pending} expired verified import snapshot(s); {$userCache} expired user-cache file(s); {$unreadableRooms} unreadable room file(s) retained for investigation\n";
