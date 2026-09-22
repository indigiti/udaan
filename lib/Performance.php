<?php
declare(strict_types=1);

function udaan_performance_completed_minutes(array $mission): int {
    if(($mission['status']??'')!=='completed')return 0;
    $actual=(int)($mission['result']['actual_minutes']??($mission['duration_minutes']??0));
    return max(0,min(480,$actual));
}

function udaan_performance_daily(array $state): array {
    $days=[];
    foreach((array)($state['missions']??[]) as $mission){
        if(!is_array($mission))continue;
        $date=(string)($mission['scheduled_date']??'');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))continue;
        if(!isset($days[$date])){
            $days[$date]=[
                'date'=>$date,
                'assigned'=>0,
                'completed'=>0,
                'skipped'=>0,
                'minutes'=>0,
                'arenas'=>[],
            ];
        }
        $days[$date]['assigned']++;
        $status=(string)($mission['status']??'assigned');
        if($status==='completed'){
            $days[$date]['completed']++;
            $days[$date]['minutes']+=udaan_performance_completed_minutes($mission);
            $arena=(string)($mission['arena']??'core');
            if(!isset($days[$date]['arenas'][$arena]))$days[$date]['arenas'][$arena]=['completed'=>0,'minutes'=>0];
            $days[$date]['arenas'][$arena]['completed']++;
            $days[$date]['arenas'][$arena]['minutes']+=udaan_performance_completed_minutes($mission);
        }elseif($status==='skipped'){
            $days[$date]['skipped']++;
        }
    }
    ksort($days);
    return $days;
}

function udaan_performance_window(array $state,int $days=14,?string $throughDate=null): array {
    $days=max(1,min(366,$days));
    $throughDate=$throughDate?:today_key();
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$throughDate))throw new InvalidArgumentException('Invalid performance date.');
    $through=new DateTimeImmutable($throughDate,new DateTimeZone('Asia/Kolkata'));
    $start=$through->modify('-'.($days-1).' days');
    $daily=udaan_performance_daily($state);
    $rows=[];$assigned=0;$completed=0;$skipped=0;$minutes=0;$trainingDays=0;$arenaStats=[];
    for($i=0;$i<$days;$i++){
        $date=$start->modify('+'.$i.' days')->format('Y-m-d');
        $row=$daily[$date]??['date'=>$date,'assigned'=>0,'completed'=>0,'skipped'=>0,'minutes'=>0,'arenas'=>[]];
        $rows[]=$row;
        $assigned+=(int)$row['assigned'];$completed+=(int)$row['completed'];$skipped+=(int)$row['skipped'];$minutes+=(int)$row['minutes'];
        if((int)$row['completed']>0)$trainingDays++;
        foreach((array)$row['arenas'] as $arena=>$stat){
            if(!isset($arenaStats[$arena]))$arenaStats[$arena]=['completed'=>0,'minutes'=>0];
            $arenaStats[$arena]['completed']+=(int)($stat['completed']??0);
            $arenaStats[$arena]['minutes']+=(int)($stat['minutes']??0);
        }
    }
    arsort($arenaStats);
    return [
        'days'=>$days,
        'from'=>$start->format('Y-m-d'),
        'through'=>$throughDate,
        'assigned'=>$assigned,
        'completed'=>$completed,
        'skipped'=>$skipped,
        'minutes'=>$minutes,
        'training_days'=>$trainingDays,
        'completion_rate'=>$assigned>0?(int)round(($completed/$assigned)*100):0,
        'arena_stats'=>$arenaStats,
        'daily'=>$rows,
    ];
}

function udaan_performance_personal_bests(array $state): array {
    $daily=udaan_performance_daily($state);
    $bestCompleted=['value'=>0,'date'=>null];
    $bestMinutes=['value'=>0,'date'=>null];
    foreach($daily as $date=>$row){
        $completed=(int)($row['completed']??0);$minutes=(int)($row['minutes']??0);
        if($completed>$bestCompleted['value']||($completed===$bestCompleted['value']&&$completed>0&&strcmp($date,(string)$bestCompleted['date'])>0))$bestCompleted=['value'=>$completed,'date'=>$date];
        if($minutes>$bestMinutes['value']||($minutes===$bestMinutes['value']&&$minutes>0&&strcmp($date,(string)$bestMinutes['date'])>0))$bestMinutes=['value'=>$minutes,'date'=>$date];
    }
    $allTimeCompleted=array_sum(array_map(fn($r)=>(int)($r['completed']??0),$daily));
    $allTimeMinutes=array_sum(array_map(fn($r)=>(int)($r['minutes']??0),$daily));
    $trainingDays=count(array_filter($daily,fn($r)=>(int)($r['completed']??0)>0));
    return [
        'best_completed_day'=>$bestCompleted,
        'best_minutes_day'=>$bestMinutes,
        'all_time_completed'=>$allTimeCompleted,
        'all_time_minutes'=>$allTimeMinutes,
        'training_days'=>$trainingDays,
    ];
}

function udaan_performance_personal_best_events(array $before,array $after,string $date): array {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return [];
    $beforeDaily=udaan_performance_daily($before);$afterDaily=udaan_performance_daily($after);
    $beforeToday=$beforeDaily[$date]??['completed'=>0,'minutes'=>0];
    $afterToday=$afterDaily[$date]??['completed'=>0,'minutes'=>0];
    $previousCompleted=0;$previousMinutes=0;
    foreach($afterDaily as $day=>$row){
        if($day===$date)continue;
        $previousCompleted=max($previousCompleted,(int)($row['completed']??0));
        $previousMinutes=max($previousMinutes,(int)($row['minutes']??0));
    }
    $events=[];
    $afterCompleted=(int)($afterToday['completed']??0);$beforeCompleted=(int)($beforeToday['completed']??0);
    if($afterCompleted>$beforeCompleted&&$afterCompleted>$previousCompleted){
        $events[]=['kind'=>'missions_in_day','value'=>$afterCompleted,'previous_best'=>$previousCompleted,'date'=>$date];
    }
    $afterMinutes=(int)($afterToday['minutes']??0);$beforeMinutes=(int)($beforeToday['minutes']??0);
    if($afterMinutes>$beforeMinutes&&$afterMinutes>$previousMinutes){
        $events[]=['kind'=>'minutes_in_day','value'=>$afterMinutes,'previous_best'=>$previousMinutes,'date'=>$date];
    }
    return $events;
}
