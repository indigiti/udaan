<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';
require_once $root.'/lib/Store.php';
require_once $root.'/lib/Performance.php';
require_once $root.'/lib/DailyReadiness.php';
require_once $root.'/lib/Mission.php';

$balanced=udaan_daily_readiness_entry([
    'sleep'=>3,'energy'=>3,'stress'=>3,'focus'=>3,'body'=>3,
],'2026-09-22');
if(($balanced['band']??'')!=='balanced'||($balanced['recommended_intensity']??'')!=='standard'){
    fwrite(STDERR,"Balanced readiness classification failed\n");exit(1);
}

$low=udaan_daily_readiness_entry([
    'sleep'=>2,'energy'=>2,'stress'=>4,'focus'=>2,'body'=>3,
],'2026-09-22');
if(($low['band']??'')!=='low'||($low['recommended_intensity']??'')!=='recovery'){
    fwrite(STDERR,"Low readiness classification failed\n");exit(1);
}

$high=udaan_daily_readiness_entry([
    'sleep'=>5,'energy'=>5,'stress'=>1,'focus'=>4,'body'=>5,
],'2026-09-22');
if(($high['band']??'')!=='high'||($high['recommended_intensity']??'')!=='challenge_ready'){
    fwrite(STDERR,"High readiness classification failed\n");exit(1);
}

$invalid=false;
try{udaan_daily_readiness_entry(['sleep'=>0,'energy'=>3,'stress'=>3,'focus'=>3,'body'=>3],'2026-09-22');}
catch(InvalidArgumentException $e){$invalid=true;}
if(!$invalid){fwrite(STDERR,"Invalid readiness scale was accepted\n");exit(1);}

$tmp=$root.'/data/test-readiness-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');
$identity=str_repeat('c',64);
$player=udaan_player_default($identity);
$player['onboarding_completed']=true;
$player['arenas']=['learn','fit','mind','reflect'];

$saved=$store->mutateReadinessState($identity,function(?array $current)use($low):array{
    $state=is_array($current)?array_replace(udaan_daily_readiness_state_default(),$current):udaan_daily_readiness_state_default();
    $state['entries'][$low['date']]=$low;
    return udaan_daily_readiness_prune($state);
});
if(($saved['entries']['2026-09-22']['band']??'')!=='low'){fwrite(STDERR,"Readiness state write failed\n");exit(1);}
$loaded=$store->getReadinessState($identity);
if(($loaded['entries']['2026-09-22']['recommended_intensity']??'')!=='recovery'){fwrite(STDERR,"Readiness state read failed\n");exit(1);}

$files=glob($tmp.'/readiness/*.json')?:[];
if(count($files)!==1){fwrite(STDERR,"Encrypted readiness state file missing\n");exit(1);}
$raw=(string)file_get_contents($files[0]);
if(str_contains($raw,'"sleep"')||str_contains($raw,'"stress"')){fwrite(STDERR,"Readiness state leaked plaintext values\n");exit(1);}
if(!is_array(secure_unpack($raw))){fwrite(STDERR,"Readiness state encryption/decode failed\n");exit(1);}

$missionState=udaan_mission_ensure_daily($store,$identity,$player,'2026-09-22');
$missionState=udaan_mission_apply_readiness($store,$identity,$low);
$missions=udaan_missions_for_date($missionState,'2026-09-22');
$byType=[];foreach($missions as$m)$byType[$m['type']]=$m;
if(($byType['daily9']['difficulty']??'')!=='standard'){fwrite(STDERR,"Readiness changed verified Daily 9 difficulty\n");exit(1);}
if(($byType['fitness']['difficulty']??'')!=='light'||($byType['focus']['difficulty']??'')!=='light'){fwrite(STDERR,"Low readiness did not lighten pending Missions\n");exit(1);}
if(($byType['fitness']['readiness_guidance']['band']??'')!=='low'){fwrite(STDERR,"Mission readiness guidance missing\n");exit(1);}

$completed=udaan_mission_apply_status($store,$identity,$player,$byType['fitness']['id'],'completed');
$completedBefore=$completed['mission'];
$missionState=udaan_mission_apply_readiness($store,$identity,$high);
$missions=udaan_missions_for_date($missionState,'2026-09-22');
$byType=[];foreach($missions as$m)$byType[$m['type']]=$m;
if(($byType['fitness']['difficulty']??'')!==($completedBefore['difficulty']??'')){fwrite(STDERR,"Completed Mission was altered by later readiness update\n");exit(1);}
if(($byType['focus']['difficulty']??'')!=='challenge'){fwrite(STDERR,"High readiness did not mark pending focus Mission challenge-ready\n");exit(1);}

$history=udaan_daily_readiness_history($loaded,14);
if(count($history)!==1||($history[0]['date']??'')!=='2026-09-22'){fwrite(STDERR,"Readiness history failed\n");exit(1);}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "daily-readiness-smoke: PASS\n";
