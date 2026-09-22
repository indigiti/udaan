<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';
require_once $root.'/lib/Store.php';
require_once $root.'/lib/Fit.php';
require_once $root.'/lib/Mission.php';

$tmp=$root.'/data/test-mission-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');

$identity=str_repeat('b',64);
$player=udaan_player_default($identity);
$player['onboarding_completed']=true;
$player['arenas']=['learn','fit','mind','reflect'];
$date='2026-09-22';

$state=udaan_mission_ensure_daily($store,$identity,$player,$date);
$missions=udaan_missions_for_date($state,$date);
if(count($missions)!==4){fwrite(STDERR,"Expected four daily missions\n");exit(1);}

$state2=udaan_mission_ensure_daily($store,$identity,$player,$date);
if(count(udaan_missions_for_date($state2,$date))!==4){fwrite(STDERR,"Daily assignment was not idempotent\n");exit(1);}

$byType=[];foreach(udaan_missions_for_date($state2,$date) as$m)$byType[$m['type']]=$m;
foreach(['daily9','fitness','focus','reflection'] as$type)if(!isset($byType[$type])){fwrite(STDERR,"Missing mission type: $type\n");exit(1);}

$fit=udaan_mission_apply_status($store,$identity,$player,$byType['fitness']['id'],'completed',['activity_id'=>'mobility','activity_label'=>'Gentle mobility','category'=>'mobility','actual_minutes'=>10,'readiness_band'=>'balanced']);
if(($fit['mission']['status']??'')!=='completed'||($fit['mission']['result']['source']??'')!=='fit_session'){fwrite(STDERR,"Fit-session completion failed\n");exit(1);}

$blocked=false;
try{udaan_mission_apply_status($store,$identity,$player,$byType['daily9']['id'],'completed');}catch(RuntimeException $e){$blocked=true;}
if(!$blocked){fwrite(STDERR,"Verified Daily 9 mission accepted self-report completion\n");exit(1);}

$verified=udaan_mission_complete_by_source($store,$identity,$player,'daily:'.$date.':daily9',['room_id'=>'smoke']);
if(!is_array($verified)||($verified['status']??'')!=='completed'||($verified['result']['source']??'')!=='verified_event'){fwrite(STDERR,"Verified mission completion failed\n");exit(1);}

$skip=udaan_mission_apply_status($store,$identity,$player,$byType['focus']['id'],'skipped');
if(($skip['mission']['status']??'')!=='skipped'){fwrite(STDERR,"Mission skip failed\n");exit(1);}

$final=$store->getMissionState($identity);
if(count(udaan_mission_history($final,20))!==4){fwrite(STDERR,"Mission history mismatch\n");exit(1);}
$finalRows=udaan_missions_for_date($final,$date);
$completed=count(array_filter($finalRows,fn($m)=>($m['status']??'')==='completed'));
$skipped=count(array_filter($finalRows,fn($m)=>($m['status']??'')==='skipped'));
if($completed!==2||$skipped!==1){fwrite(STDERR,"Mission status aggregate mismatch\n");exit(1);}

$eventFiles=glob($tmp.'/events/*.jsonl')?:[];
if(count($eventFiles)!==1){fwrite(STDERR,"Mission event ledger file missing\n");exit(1);}
$lines=file($eventFiles[0],FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
if(count($lines)<7){fwrite(STDERR,"Mission event ledger is incomplete\n");exit(1);}
foreach($lines as$line){if(!is_array(secure_unpack($line))){fwrite(STDERR,"Mission event ledger encryption/decode failed\n");exit(1);}}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "mission-engine-smoke: PASS\n";
