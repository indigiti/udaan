<?php
declare(strict_types=1);

function udaan_fit_activities(): array {
    return [
        'study_break'=>[
            'label'=>'Study-break reset',
            'minutes'=>5,
            'category'=>'mobility',
            'summary'=>'Stand up, change position, loosen shoulders and move gently before returning to study.',
            'readiness'=>['low','balanced','high'],
        ],
        'mobility'=>[
            'label'=>'Gentle mobility',
            'minutes'=>10,
            'category'=>'mobility',
            'summary'=>'Use comfortable joint movement and easy range-of-motion work without forcing any position.',
            'readiness'=>['low','balanced','high'],
        ],
        'walk'=>[
            'label'=>'Easy walk',
            'minutes'=>10,
            'category'=>'walk',
            'summary'=>'Walk at a conversational, comfortable pace. Indoors or outdoors both count.',
            'readiness'=>['balanced','high'],
        ],
        'stretch'=>[
            'label'=>'Light stretching',
            'minutes'=>10,
            'category'=>'stretch',
            'summary'=>'Use gentle stretches only. Avoid bouncing and stop if anything feels painful.',
            'readiness'=>['low','balanced','high'],
        ],
        'yoga'=>[
            'label'=>'Yoga basics',
            'minutes'=>10,
            'category'=>'yoga',
            'summary'=>'Choose familiar beginner movements and comfortable breathing. No advanced poses are required.',
            'readiness'=>['balanced','high'],
        ],
    ];
}

function udaan_fit_allowed_minutes(): array {
    return [5,10,15];
}

function udaan_fit_recommended_activity(?array $readiness): string {
    $band=(string)($readiness['band']??'balanced');
    return match($band){
        'low'=>'study_break',
        'high'=>'walk',
        default=>'mobility',
    };
}

function udaan_fit_validate_session(array $input,?array $readiness=null): array {
    $activities=udaan_fit_activities();
    $activity=(string)($input['activity_id']??'');
    if(!isset($activities[$activity]))throw new InvalidArgumentException('Choose a supported movement session.');

    $minutes=(int)($input['minutes']??0);
    if(!in_array($minutes,udaan_fit_allowed_minutes(),true))throw new InvalidArgumentException('Choose a supported session length.');

    $band=(string)($readiness['band']??'balanced');
    if($band==='low'&&$minutes>10)throw new InvalidArgumentException('For a low-readiness day, keep this movement session to 10 minutes or less.');
    if($band==='low'&&!in_array($activity,['study_break','mobility','stretch'],true)){
        throw new InvalidArgumentException('Choose a gentle recovery-friendly activity for a low-readiness day.');
    }

    return [
        'activity_id'=>$activity,
        'activity_label'=>$activities[$activity]['label'],
        'category'=>$activities[$activity]['category'],
        'actual_minutes'=>$minutes,
        'readiness_band'=>$band,
    ];
}

function udaan_fit_summary(array $missionState,int $days=14,?string $throughDate=null): array {
    $days=max(1,min(366,$days));
    $throughDate=$throughDate?:today_key();
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$throughDate))throw new InvalidArgumentException('Invalid Fit summary date.');
    $through=new DateTimeImmutable($throughDate,new DateTimeZone('Asia/Kolkata'));
    $from=$through->modify('-'.($days-1).' days')->format('Y-m-d');

    $completed=0;$minutes=0;$activeDates=[];$byActivity=[];
    foreach((array)($missionState['missions']??[]) as$mission){
        if(!is_array($mission)||($mission['arena']??'')!=='fit'||($mission['status']??'')!=='completed')continue;
        $date=(string)($mission['scheduled_date']??'');
        if($date<$from||$date>$throughDate)continue;
        $completed++;
        $actual=(int)($mission['result']['actual_minutes']??($mission['duration_minutes']??0));
        $actual=max(0,min(180,$actual));
        $minutes+=$actual;
        $activeDates[$date]=true;
        $activity=(string)($mission['result']['activity_id']??'movement');
        if(!isset($byActivity[$activity]))$byActivity[$activity]=['sessions'=>0,'minutes'=>0];
        $byActivity[$activity]['sessions']++;
        $byActivity[$activity]['minutes']+=$actual;
    }

    ksort($activeDates);
    return [
        'days'=>$days,
        'from'=>$from,
        'through'=>$throughDate,
        'sessions'=>$completed,
        'minutes'=>$minutes,
        'active_days'=>count($activeDates),
        'consistency_rate'=>(int)round((count($activeDates)/$days)*100),
        'by_activity'=>$byActivity,
        'active_dates'=>array_keys($activeDates),
    ];
}
