<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';
require_once $root.'/lib/Store.php';
require_once $root.'/lib/Performance.php';
require_once $root.'/lib/DailyReadiness.php';
require_once $root.'/lib/Fit.php';
require_once $root.'/lib/Momentum.php';
require_once $root.'/lib/Mission.php';

$identity=str_repeat('e',64);
$player=udaan_player_default($identity);
$player['onboarding_completed']=true;
$player['arenas']=['learn','fit','mind'];
$player['momentum']=['weekly_training_days'=>4,'habits'=>['learn','fit','mind']];

$settings=udaan_momentum_normalize_settings([
    'weekly_training_days'=>5,
    'habits'=>['learn','fit','reflect'],
],$player);
if(($settings['weekly_training_days']??0)!==5){fwrite(STDERR,"Weekly goal normalization failed\n");exit(1);}
if(($settings['habits']??[])!==['learn','fit']){fwrite(STDERR,"Habit selection was not restricted to Player Arenas\n");exit(1);}

$state=['schema_version'=>1,'missions'=>[]];
$add=function(string $id,string $date,string $arena,string $status,int $minutes,string $type='learning')use(&$state):void{
    $state['missions'][$id]=[
        'id'=>$id,'scheduled_date'=>$date,'arena'=>$arena,'status'=>$status,
        'duration_minutes'=>$minutes,'type'=>$type,'result'=>null,
    ];
};
$add('a','2026-09-14','learn','completed',20);
$add('b','2026-09-15','fit','completed',10,'fitness');
$add('c','2026-09-18','mind','completed',10,'focus');

$summary=udaan_momentum_summary($state,$player,'2026-09-22');
if(empty($summary['comeback_due'])){fwrite(STDERR,"Comeback was not triggered after inactivity gap\n");exit(1);}
if(($summary['inactive_days']??0)!==3){fwrite(STDERR,"Inactive-day calculation mismatch\n");exit(1);}

$tmp=$root.'/data/test-momentum-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');
$store->putPlayer($identity,$player);
$store->mutateMissionState($identity,fn($_)=>$state);

$withToday=udaan_mission_ensure_daily($store,$identity,$player,'2026-09-22');
$rows=udaan_missions_for_date($withToday,'2026-09-22');
$comebacks=array_values(array_filter($rows,fn($m)=>($m['type']??'')==='comeback'));
if(count($comebacks)!==1){fwrite(STDERR,"Expected exactly one Comeback Mission\n");exit(1);}
$comeback=$comebacks[0];
if(($comeback['arena']??'')!=='core'||($comeback['duration_minutes']??0)!==2){fwrite(STDERR,"Comeback Mission must remain neutral and short\n");exit(1);}

$again=udaan_mission_ensure_daily($store,$identity,$player,'2026-09-22');
$rowsAgain=udaan_missions_for_date($again,'2026-09-22');
if(count(array_filter($rowsAgain,fn($m)=>($m['type']??'')==='comeback'))!==1){fwrite(STDERR,"Comeback assignment was not idempotent\n");exit(1);}

$done=udaan_mission_apply_status($store,$identity,$player,$comeback['id'],'completed');
if(($done['mission']['status']??'')!=='completed'){fwrite(STDERR,"Comeback completion failed\n");exit(1);}

$summary2=udaan_momentum_summary($store->getMissionState($identity),$player,'2026-09-22');
if(($summary2['week_active_days']??0)<1){fwrite(STDERR,"Comeback did not restore a meaningful training day\n");exit(1);}
if(($summary2['habits']['learn']['days']??0)!==0||($summary2['habits']['fit']['days']??0)!==0||($summary2['habits']['mind']['days']??0)!==0){
    fwrite(STDERR,"Core Comeback incorrectly inflated an Arena habit\n");exit(1);
}

$milestones=udaan_momentum_milestones($store->getMissionState($identity));
$first=array_values(array_filter($milestones,fn($m)=>($m['key']??'')==='first_day'))[0]??null;
if(!is_array($first)||empty($first['earned'])){fwrite(STDERR,"First Training Day milestone was not earned\n");exit(1);}

$events=[];
foreach(glob($tmp.'/events/*.jsonl')?:[] as$file)foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as$line){
    $decoded=secure_unpack($line);if(is_array($decoded))$events[]=$decoded;
}
$types=array_column($events,'event_type');
if(!in_array('momentum.comeback_assigned',$types,true)||!in_array('momentum.comeback_completed',$types,true)){
    fwrite(STDERR,"Comeback Event Ledger entries missing\n");exit(1);
}

$text=udaan_momentum_share_text(['label'=>'5 Training Days'],$player);
if(!str_contains($text,'5 Training Days')||str_contains(strtolower($text),'streak lost')){fwrite(STDERR,"Achievement share text mismatch\n");exit(1);}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "momentum-habits-smoke: PASS\n";
