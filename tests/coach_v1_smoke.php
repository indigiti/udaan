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
require_once $root.'/lib/Coach.php';

$identity=str_repeat('f',64);
$player=udaan_player_default($identity);
$player['onboarding_completed']=true;
$player['main_goal']='neet';
$player['arenas']=['learn','fit','mind'];
$player['momentum']=['weekly_training_days'=>4,'habits'=>['learn','fit','mind']];

$state=['schema_version'=>1,'missions'=>[]];
$add=function(string $id,string $date,string $arena,string $type,string $status,int $minutes)use(&$state):void{
    $state['missions'][$id]=[
        'schema_version'=>1,'id'=>$id,'player_id'=>'p','source_key'=>'test:'.$id,'arena'=>$arena,'type'=>$type,
        'title'=>ucfirst($type).' mission','objective'=>'Test mission','duration_minutes'=>$minutes,'difficulty'=>'standard',
        'content_refs'=>[],'completion_rule'=>['mode'=>$type==='daily9'?'event':'self_report'],
        'status'=>$status,'scheduled_date'=>$date,'created_at'=>now_iso(),'updated_at'=>now_iso(),'result'=>null,
    ];
};

$add('a','2026-09-19','learn','daily9','completed',2);
$add('b','2026-09-20','fit','fitness','completed',10);
$add('c','2026-09-22','learn','daily9','assigned',2);
$add('d','2026-09-22','mind','focus','assigned',10);

$readinessState=udaan_daily_readiness_state_default();
$readinessState['entries']['2026-09-22']=udaan_daily_readiness_entry([
    'sleep'=>2,'energy'=>2,'stress'=>4,'focus'=>2,'body'=>3,
],'2026-09-22');

$coach=udaan_coach_generate($state,$player,$readinessState,'2026-09-22');
$recs=$coach['recommendations']??[];
if(!$recs){fwrite(STDERR,"Coach returned no recommendations\n");exit(1);}
$reasonCodes=array_column($recs,'reason_code');
if(!in_array('READINESS_LOW',$reasonCodes,true)){fwrite(STDERR,"Low-readiness recommendation missing\n");exit(1);}
if(!in_array('GOAL_ALIGNED_MISSION',$reasonCodes,true)){fwrite(STDERR,"Goal-aligned Mission recommendation missing\n");exit(1);}

$goalRec=null;
foreach($recs as$r)if(($r['reason_code']??'')==='GOAL_ALIGNED_MISSION')$goalRec=$r;
if(!is_array($goalRec)||($goalRec['mission_id']??'')!=='c'){fwrite(STDERR,"Coach did not prioritize Learn Mission for NEET goal\n");exit(1);}
if(($goalRec['action_key']??'')!=='daily'){fwrite(STDERR,"Coach Mission action routing mismatch\n");exit(1);}

$summary=$coach['weekly_summary']??[];
if(($summary['missions_completed']??0)!==2){fwrite(STDERR,"Coach weekly mission summary mismatch\n");exit(1);}
if(($summary['readiness_checkins']??0)!==1){fwrite(STDERR,"Coach readiness summary mismatch\n");exit(1);}
if(($summary['latest_readiness_band']??'')!=='low'){fwrite(STDERR,"Coach leaked or lost readiness band summary\n");exit(1);}

$missingReadiness=udaan_daily_readiness_state_default();
$coach2=udaan_coach_generate($state,$player,$missingReadiness,'2026-09-22');
$codes2=array_column($coach2['recommendations']??[],'reason_code');
if(!in_array('READINESS_MISSING',$codes2,true)){fwrite(STDERR,"Missing-readiness check-in recommendation absent\n");exit(1);}

$comebackState=['schema_version'=>1,'missions'=>[]];
$add2=function(string $id,string $date,string $arena,string $type,string $status,int $minutes)use(&$comebackState):void{
    $comebackState['missions'][$id]=[
        'schema_version'=>1,'id'=>$id,'player_id'=>'p','source_key'=>'test:'.$id,'arena'=>$arena,'type'=>$type,
        'title'=>ucfirst($type).' mission','objective'=>'Test mission','duration_minutes'=>$minutes,'difficulty'=>'standard',
        'content_refs'=>[],'completion_rule'=>['mode'=>'self_report'],'status'=>$status,'scheduled_date'=>$date,
        'created_at'=>now_iso(),'updated_at'=>now_iso(),'result'=>null,
    ];
};
$add2('old','2026-09-17','learn','learning','completed',10);
$add2('cb','2026-09-22','core','comeback','assigned',2);
$coach3=udaan_coach_generate($comebackState,$player,$missingReadiness,'2026-09-22');
$codes3=array_column($coach3['recommendations']??[],'reason_code');
if(!in_array('MOMENTUM_COMEBACK',$codes3,true)){fwrite(STDERR,"Comeback recommendation missing\n");exit(1);}
if(count(array_filter($coach3['recommendations']??[],fn($r)=>($r['mission_id']??'')==='cb'))>0){
    fwrite(STDERR,"Comeback Mission duplicated as goal-aligned recommendation\n");exit(1);
}

foreach($recs as$r){
    if(isset($r['evidence']['sleep'])||isset($r['evidence']['energy'])||isset($r['evidence']['stress'])||isset($r['evidence']['focus'])||isset($r['evidence']['body'])){
        fwrite(STDERR,"Raw readiness values leaked into Coach evidence\n");exit(1);
    }
}

$first=$recs[0];
if(udaan_coach_find_recommendation($coach,(string)$first['id'])===null){fwrite(STDERR,"Recommendation lookup failed\n");exit(1);}
if(udaan_coach_find_recommendation($coach,str_repeat('0',20))!==null){fwrite(STDERR,"Forged recommendation lookup succeeded\n");exit(1);}

$tmp=$root.'/data/test-coach-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');
$store->putPlayer($identity,$player);

if(!udaan_coach_record_shown_once($store,$player,$first)){fwrite(STDERR,"First Coach shown event was not recorded\n");exit(1);}
if(udaan_coach_record_shown_once($store,$player,$first)){fwrite(STDERR,"Coach shown event was not deduplicated in session\n");exit(1);}
udaan_coach_record_accepted($store,$player,$first);

$events=[];
foreach(glob($tmp.'/events/*.jsonl')?:[] as$file)foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as$line){
    $decoded=secure_unpack($line);if(is_array($decoded))$events[]=$decoded;
}
$types=array_column($events,'event_type');
if(count(array_filter($types,fn($t)=>$t==='coach.recommendation_shown'))!==1){fwrite(STDERR,"Coach shown telemetry count mismatch\n");exit(1);}
if(count(array_filter($types,fn($t)=>$t==='coach.recommendation_accepted'))!==1){fwrite(STDERR,"Coach accepted telemetry missing\n");exit(1);}

foreach($events as$event){
    if(!str_starts_with((string)($event['event_type']??''),'coach.'))continue;
    $metrics=(array)($event['metrics']??[]);
    foreach(['sleep','energy','stress','focus','body'] as$forbidden){
        if(array_key_exists($forbidden,$metrics)){fwrite(STDERR,"Raw readiness value leaked into Coach telemetry\n");exit(1);}
    }
}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "coach-v1-smoke: PASS\n";
