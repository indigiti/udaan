<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';
require_once $root.'/lib/Store.php';
require_once $root.'/lib/Performance.php';
require_once $root.'/lib/DailyReadiness.php';
require_once $root.'/lib/Fit.php';
require_once $root.'/lib/Mission.php';

$activities=udaan_fit_activities();
foreach(['study_break','mobility','walk','stretch','yoga'] as$id){
    if(!isset($activities[$id])){fwrite(STDERR,"Missing Fit activity: $id\n");exit(1);}
}
if(udaan_fit_allowed_minutes()!==[5,10,15]){fwrite(STDERR,"Unexpected Fit duration policy\n");exit(1);}

$low=['band'=>'low'];
$balanced=['band'=>'balanced'];
$high=['band'=>'high'];

if(udaan_fit_recommended_activity($low)!=='study_break'){fwrite(STDERR,"Low-readiness recommendation mismatch\n");exit(1);}
if(udaan_fit_recommended_activity($balanced)!=='mobility'){fwrite(STDERR,"Balanced recommendation mismatch\n");exit(1);}
if(udaan_fit_recommended_activity($high)!=='walk'){fwrite(STDERR,"High-readiness recommendation mismatch\n");exit(1);}

$valid=udaan_fit_validate_session(['activity_id'=>'mobility','minutes'=>10],$balanced);
if(($valid['actual_minutes']??0)!==10||($valid['category']??'')!=='mobility'){fwrite(STDERR,"Valid Fit session failed\n");exit(1);}

$blocked=false;
try{udaan_fit_validate_session(['activity_id'=>'walk','minutes'=>15],$low);}catch(InvalidArgumentException $e){$blocked=true;}
if(!$blocked){fwrite(STDERR,"Low-readiness long walk was accepted\n");exit(1);}

$blocked=false;
try{udaan_fit_validate_session(['activity_id'=>'mobility','minutes'=>30],$balanced);}catch(InvalidArgumentException $e){$blocked=true;}
if(!$blocked){fwrite(STDERR,"Unsupported Fit duration was accepted\n");exit(1);}

$tmp=$root.'/data/test-fit-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');
$identity=str_repeat('d',64);
$player=udaan_player_default($identity);
$player['onboarding_completed']=true;
$player['arenas']=['fit'];

$state=udaan_mission_ensure_daily($store,$identity,$player,'2026-09-22');
$rows=udaan_missions_for_date($state,'2026-09-22');
if(count($rows)!==1||($rows[0]['completion_rule']['mode']??'')!=='fit_session'){fwrite(STDERR,"Fit Mission rule mismatch\n");exit(1);}

$blocked=false;
try{udaan_mission_apply_status($store,$identity,$player,$rows[0]['id'],'completed');}catch(RuntimeException $e){$blocked=true;}
if(!$blocked){fwrite(STDERR,"Fit Mission accepted blind self-report completion\n");exit(1);}

$session=udaan_fit_validate_session(['activity_id'=>'walk','minutes'=>15],$high);
$done=udaan_mission_apply_status($store,$identity,$player,$rows[0]['id'],'completed',$session);
if(($done['mission']['result']['source']??'')!=='fit_session'||($done['mission']['result']['actual_minutes']??0)!==15){
    fwrite(STDERR,"Structured Fit completion failed\n");exit(1);
}

$summary=udaan_fit_summary($store->getMissionState($identity),14,'2026-09-22');
if(($summary['sessions']??0)!==1||($summary['minutes']??0)!==15||($summary['active_days']??0)!==1){
    fwrite(STDERR,"Fit summary mismatch\n");exit(1);
}
if(($summary['consistency_rate']??0)!==7){fwrite(STDERR,"Fit consistency calculation mismatch\n");exit(1);}

$eventFiles=glob($tmp.'/events/*.jsonl')?:[];
$events=[];
foreach($eventFiles as$file)foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as$line){
    $decoded=secure_unpack($line);if(is_array($decoded))$events[]=$decoded;
}
$completedEvents=array_values(array_filter($events,fn($e)=>($e['event_type']??'')==='mission.completed'));
if(!$completedEvents){fwrite(STDERR,"Fit completion event missing\n");exit(1);}
$metrics=$completedEvents[array_key_last($completedEvents)]['metrics']??[];
if(($metrics['completion_source']??'')!=='fit_session'||($metrics['activity_id']??'')!=='walk'||($metrics['duration_minutes']??0)!==15){
    fwrite(STDERR,"Fit completion event metadata mismatch\n");exit(1);
}
foreach(['calories','weight','bmi'] as$forbidden){
    if(array_key_exists($forbidden,$metrics)){fwrite(STDERR,"Unsafe Fit metric leaked to event ledger: $forbidden\n");exit(1);}
}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "fit-v1-smoke: PASS\n";
