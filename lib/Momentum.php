<?php
declare(strict_types=1);

function udaan_momentum_defaults(array $player): array {
    $selected=array_values(array_filter(array_map('strval',(array)($player['arenas']??['learn'])),fn($a)=>isset(udaan_player_arenas()[$a])));
    if(!$selected)$selected=['learn'];
    $stored=is_array($player['momentum']??null)?$player['momentum']:[];
    $goal=(int)($stored['weekly_training_days']??4);
    $goal=max(2,min(7,$goal));
    $habits=array_values(array_unique(array_filter(array_map('strval',(array)($stored['habits']??$selected)),fn($a)=>isset(udaan_player_arenas()[$a]))));
    if(!$habits)$habits=$selected;
    return [
        'weekly_training_days'=>$goal,
        'habits'=>$habits,
    ];
}

function udaan_momentum_normalize_settings(array $input,array $player): array {
    $goal=(int)($input['weekly_training_days']??4);
    if($goal<2||$goal>7)throw new InvalidArgumentException('Choose a weekly goal between 2 and 7 training days.');
    $allowed=udaan_player_arenas();
    $playerArenas=array_fill_keys(array_values(array_filter(array_map('strval',(array)($player['arenas']??['learn'])),fn($a)=>isset($allowed[$a]))),true);
    $selected=[];
    foreach((array)($input['habits']??[]) as$habit){
        $habit=(string)$habit;
        if(isset($allowed[$habit])&&isset($playerArenas[$habit]))$selected[$habit]=true;
    }
    if(!$selected)$selected=$playerArenas?:['learn'=>true];
    return [
        'weekly_training_days'=>$goal,
        'habits'=>array_keys($selected),
    ];
}

function udaan_momentum_day_map(array $missionState): array {
    $daily=udaan_performance_daily($missionState);
    $map=[];
    foreach($daily as$date=>$row){
        $active=(int)($row['completed']??0)>0;
        $map[$date]=[
            'date'=>$date,
            'active'=>$active,
            'completed'=>(int)($row['completed']??0),
            'minutes'=>(int)($row['minutes']??0),
            'arenas'=>array_keys(array_filter((array)($row['arenas']??[]),fn($v)=>(int)($v['completed']??0)>0)),
        ];
    }
    return $map;
}

function udaan_momentum_week_bounds(string $date): array {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Invalid momentum date.');
    $d=new DateTimeImmutable($date,new DateTimeZone('Asia/Kolkata'));
    $monday=$d->modify('monday this week');
    if((int)$d->format('N')===1)$monday=$d;
    $sunday=$monday->modify('+6 days');
    return [$monday->format('Y-m-d'),$sunday->format('Y-m-d')];
}

function udaan_momentum_inactive_days(array $dayMap,string $date): int {
    $today=new DateTimeImmutable($date,new DateTimeZone('Asia/Kolkata'));
    $last=null;
    foreach($dayMap as$d=>$row){
        if($d>=$date||empty($row['active']))continue;
        if($last===null||$d>$last)$last=$d;
    }
    if($last===null)return 0;
    $lastDate=new DateTimeImmutable($last,new DateTimeZone('Asia/Kolkata'));
    return max(0,(int)$lastDate->diff($today)->format('%a')-1);
}

function udaan_momentum_habits(array $missionState,array $habitArenas,string $date): array {
    [$from,$through]=udaan_momentum_week_bounds($date);
    $out=[];
    foreach($habitArenas as$arena){
        if(!isset(udaan_player_arenas()[$arena]))continue;
        $out[$arena]=['arena'=>$arena,'days'=>0,'minutes'=>0,'dates'=>[]];
    }
    foreach((array)($missionState['missions']??[]) as$m){
        if(!is_array($m)||($m['status']??'')!=='completed')continue;
        $d=(string)($m['scheduled_date']??'');
        $arena=(string)($m['arena']??'');
        if($d<$from||$d>$through||!isset($out[$arena]))continue;
        $out[$arena]['dates'][$d]=true;
        $out[$arena]['minutes']+=udaan_performance_completed_minutes($m);
    }
    foreach($out as$arena=>$row){
        $out[$arena]['days']=count($row['dates']);
        $out[$arena]['dates']=array_keys($row['dates']);
        sort($out[$arena]['dates']);
    }
    return $out;
}

function udaan_momentum_milestones(array $missionState): array {
    $daily=udaan_performance_daily($missionState);
    $trainingDays=count(array_filter($daily,fn($r)=>(int)($r['completed']??0)>0));
    $completed=0;$minutes=0;$fitSessions=0;
    foreach((array)($missionState['missions']??[]) as$m){
        if(!is_array($m)||($m['status']??'')!=='completed')continue;
        $completed++;$minutes+=udaan_performance_completed_minutes($m);
        if(($m['arena']??'')==='fit')$fitSessions++;
    }
    $defs=[
        ['key'=>'first_day','label'=>'First Training Day','threshold'=>1,'value'=>$trainingDays,'unit'=>'training days'],
        ['key'=>'five_days','label'=>'5 Training Days','threshold'=>5,'value'=>$trainingDays,'unit'=>'training days'],
        ['key'=>'ten_missions','label'=>'10 Missions Completed','threshold'=>10,'value'=>$completed,'unit'=>'missions'],
        ['key'=>'twenty_five_missions','label'=>'25 Missions Completed','threshold'=>25,'value'=>$completed,'unit'=>'missions'],
        ['key'=>'hundred_minutes','label'=>'100 Training Minutes','threshold'=>100,'value'=>$minutes,'unit'=>'minutes'],
        ['key'=>'five_fit','label'=>'5 Fit Sessions','threshold'=>5,'value'=>$fitSessions,'unit'=>'Fit sessions'],
    ];
    return array_map(function($m){
        $m['earned']=$m['value']>=$m['threshold'];
        $m['progress']=min(100,(int)round(($m['value']/max(1,$m['threshold']))*100));
        return $m;
    },$defs);
}

function udaan_momentum_summary(array $missionState,array $player,string $date): array {
    $settings=udaan_momentum_defaults($player);
    $dayMap=udaan_momentum_day_map($missionState);
    [$from,$through]=udaan_momentum_week_bounds($date);
    $weekDays=0;$weekCompleted=0;$weekMinutes=0;
    for($d=$from;$d<=$through;$d=(new DateTimeImmutable($d,new DateTimeZone('Asia/Kolkata')))->modify('+1 day')->format('Y-m-d')){
        $row=$dayMap[$d]??null;
        if($row&&$row['active'])$weekDays++;
        $weekCompleted+=(int)($row['completed']??0);
        $weekMinutes+=(int)($row['minutes']??0);
    }
    $inactive=udaan_momentum_inactive_days($dayMap,$date);
    $hadHistory=count(array_filter($dayMap,fn($r)=>!empty($r['active'])))>0;
    $goal=$settings['weekly_training_days'];
    $milestones=udaan_momentum_milestones($missionState);
    $next=null;
    foreach($milestones as$m)if(!$m['earned']){$next=$m;break;}
    return [
        'week_from'=>$from,
        'week_through'=>$through,
        'weekly_goal'=>$goal,
        'week_active_days'=>$weekDays,
        'week_completed'=>$weekCompleted,
        'week_minutes'=>$weekMinutes,
        'goal_progress'=>min(100,(int)round(($weekDays/max(1,$goal))*100)),
        'goal_met'=>$weekDays>=$goal,
        'inactive_days'=>$inactive,
        'comeback_due'=>$hadHistory&&$inactive>=2,
        'habits'=>udaan_momentum_habits($missionState,$settings['habits'],$date),
        'milestones'=>$milestones,
        'next_milestone'=>$next,
    ];
}

function udaan_momentum_comeback_template(array $player,string $date): ?array {
    return [
        'source_key'=>'daily:'.$date.':comeback',
        'arena'=>'core',
        'type'=>'comeback',
        'title'=>'Restart today',
        'objective'=>'Take two minutes to choose one useful next action. Missing days does not erase your progress, and no catch-up marathon is required.',
        'duration_minutes'=>2,
        'difficulty'=>'light',
        'content_refs'=>[],
        'completion_rule'=>['mode'=>'self_report'],
    ];
}

function udaan_momentum_share_text(array $milestone,array $player): string {
    $nickname=trim((string)($player['nickname']??'A learner'))?:'A learner';
    $label=(string)($milestone['label']??'a Udaan milestone');
    return $nickname.' reached '.$label.' on Udaan — progress built from real training, one day at a time.';
}
