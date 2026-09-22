<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/lib/Performance.php';

$state=['schema_version'=>1,'missions'=>[]];
$add=function(string $id,string $date,string $arena,string $status,int $minutes)use(&$state):void{
    $state['missions'][$id]=[
        'id'=>$id,
        'scheduled_date'=>$date,
        'arena'=>$arena,
        'status'=>$status,
        'duration_minutes'=>$minutes,
    ];
};

$add('m1','2026-09-20','learn','completed',20);
$add('m2','2026-09-20','fit','completed',10);
$add('m3','2026-09-20','mind','skipped',10);
$add('m4','2026-09-21','learn','completed',30);
$add('m5','2026-09-21','reflect','assigned',2);
$add('m6','2026-09-22','learn','completed',15);
$add('m7','2026-09-22','fit','completed',10);
$add('m8','2026-09-22','mind','completed',10);

$window=udaan_performance_window($state,3,'2026-09-22');
if(($window['training_days']??0)!==3){fwrite(STDERR,"Training day count mismatch\n");exit(1);}
if(($window['completed']??0)!==6){fwrite(STDERR,"Completed mission count mismatch\n");exit(1);}
if(($window['skipped']??0)!==1){fwrite(STDERR,"Skipped mission count mismatch\n");exit(1);}
if(($window['minutes']??0)!==95){fwrite(STDERR,"Completed minutes mismatch\n");exit(1);}
if(($window['completion_rate']??0)!==75){fwrite(STDERR,"Completion rate mismatch\n");exit(1);}
if(($window['arena_stats']['learn']['completed']??0)!==3){fwrite(STDERR,"Learn arena aggregate mismatch\n");exit(1);}
if(($window['arena_stats']['fit']['minutes']??0)!==20){fwrite(STDERR,"Fit arena minute aggregate mismatch\n");exit(1);}

$bests=udaan_performance_personal_bests($state);
if(($bests['best_completed_day']['value']??0)!==3||($bests['best_completed_day']['date']??'')!=='2026-09-22'){fwrite(STDERR,"Best completed day mismatch\n");exit(1);}
if(($bests['best_minutes_day']['value']??0)!==35||($bests['best_minutes_day']['date']??'')!=='2026-09-22'){fwrite(STDERR,"Best minutes day mismatch\n");exit(1);}
if(($bests['training_days']??0)!==3){fwrite(STDERR,"All-time training day mismatch\n");exit(1);}

$before=$state;
unset($before['missions']['m8']);
$events=udaan_performance_personal_best_events($before,$state,'2026-09-22');
$kinds=array_column($events,'kind');
if(!in_array('missions_in_day',$kinds,true)){fwrite(STDERR,"Mission personal best event missing\n");exit(1);}
if(!in_array('minutes_in_day',$kinds,true)){fwrite(STDERR,"Minute personal best event missing\n");exit(1);}

$noChange=udaan_performance_personal_best_events($state,$state,'2026-09-22');
if($noChange!==[]){fwrite(STDERR,"Unchanged state emitted a personal best\n");exit(1);}

echo "performance-graph-smoke: PASS\n";
