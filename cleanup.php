<?php
require __DIR__.'/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$rooms=0;$pending=0;
foreach(glob(__DIR__.'/data/rooms/*.json')?:[] as $f){$d=json_decode((string)@file_get_contents($f),true);if(!$d||strtotime($d['expires_at']??'1970-01-01')<time()){if(@unlink($f))$rooms++;}}
foreach(glob(__DIR__.'/data/imports/pending/*.json')?:[] as $f){$d=json_decode((string)@file_get_contents($f),true);if(!$d||(int)($d['expires_at']??0)<time()){if(@unlink($f))$pending++;}}
$userCache=$store->cleanupUserCacheFiles();
echo "Removed {$rooms} expired temporary room(s); {$pending} expired verified import snapshot(s); {$userCache} expired user-cache file(s)\n";
