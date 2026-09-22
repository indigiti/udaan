<?php
declare(strict_types=1);

function udaan_daily_readiness_dimensions(): array {
    return [
        'sleep'=>['label'=>'Sleep','low'=>'Very poor','high'=>'Excellent'],
        'energy'=>['label'=>'Energy','low'=>'Very low','high'=>'Very high'],
        'stress'=>['label'=>'Stress','low'=>'Calm','high'=>'Very high'],
        'focus'=>['label'=>'Focus','low'=>'Scattered','high'=>'Very focused'],
        'body'=>['label'=>'Body','low'=>'Very low','high'=>'Feeling strong'],
    ];
}

function udaan_daily_readiness_state_default(): array {
    return ['schema_version'=>1,'updated_at'=>null,'entries'=>[]];
}

function udaan_daily_readiness_band(array $values): array {
    foreach(array_keys(udaan_daily_readiness_dimensions()) as $key){
        $value=(int)($values[$key]??0);
        if($value<1||$value>5)throw new InvalidArgumentException('Readiness answers must use the 1–5 scale.');
    }
    $sleep=(int)$values['sleep'];$energy=(int)$values['energy'];$stress=(int)$values['stress'];$focus=(int)$values['focus'];$body=(int)$values['body'];
    $total=$sleep+$energy+$focus+$body+(6-$stress);

    if($energy<=1||$body<=1||$stress>=5||$total<=13){
        return [
            'band'=>'low',
            'label'=>'Low readiness',
            'recommended_intensity'=>'recovery',
            'summary'=>'Keep today lighter. Prioritize review, gentle movement and sustainable focus.',
        ];
    }
    if($total>=21&&$stress<=3&&$energy>=4&&$body>=3){
        return [
            'band'=>'high',
            'label'=>'High readiness',
            'recommended_intensity'=>'challenge_ready',
            'summary'=>'You appear ready for a normal session and may choose an extra challenge if it still feels right.',
        ];
    }
    return [
        'band'=>'balanced',
        'label'=>'Balanced readiness',
        'recommended_intensity'=>'standard',
        'summary'=>'A normal training day is appropriate. Keep the planned workload steady and sustainable.',
    ];
}

function udaan_daily_readiness_entry(array $input,string $date): array {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Invalid readiness date.');
    $values=[];
    foreach(array_keys(udaan_daily_readiness_dimensions()) as$key){
        if(!isset($input[$key])||filter_var($input[$key],FILTER_VALIDATE_INT)===false)throw new InvalidArgumentException('Complete all readiness questions.');
        $value=(int)$input[$key];
        if($value<1||$value>5)throw new InvalidArgumentException('Readiness answers must use the 1–5 scale.');
        $values[$key]=$value;
    }
    $band=udaan_daily_readiness_band($values);
    return [
        'schema_version'=>1,
        'date'=>$date,
        'values'=>$values,
        'band'=>$band['band'],
        'label'=>$band['label'],
        'recommended_intensity'=>$band['recommended_intensity'],
        'summary'=>$band['summary'],
        'submitted_at'=>now_iso(),
    ];
}

function udaan_daily_readiness_prune(array $state,int $max=120): array {
    $state=array_replace(udaan_daily_readiness_state_default(),$state);
    if(!is_array($state['entries']))$state['entries']=[];
    krsort($state['entries']);
    if(count($state['entries'])>$max)$state['entries']=array_slice($state['entries'],0,$max,true);
    $state['schema_version']=1;$state['updated_at']=now_iso();
    return $state;
}

function udaan_daily_readiness_for_date(array $state,string $date): ?array {
    $entry=$state['entries'][$date]??null;
    return is_array($entry)?$entry:null;
}

function udaan_daily_readiness_history(array $state,int $limit=14): array {
    $rows=array_values(array_filter((array)($state['entries']??[]),'is_array'));
    usort($rows,fn($a,$b)=>strcmp((string)($b['date']??''),(string)($a['date']??'')));
    return array_slice($rows,0,max(1,min(120,$limit)));
}

function udaan_daily_readiness_mission_guidance(array $entry,string $arena): array {
    $band=(string)($entry['band']??'balanced');
    $intensity=(string)($entry['recommended_intensity']??'standard');
    $messages=[
        'low'=>[
            'learn'=>'Keep this session light; favour understanding and review over volume.',
            'fit'=>'Choose gentle movement only and stop if you feel unwell or uncomfortable.',
            'mind'=>'Use the core focus block without extending it today.',
            'reflect'=>'Use this as a quiet recovery pause.',
        ],
        'balanced'=>[
            'learn'=>'Follow the planned learning session at a steady pace.',
            'fit'=>'A standard gentle movement session fits today’s check-in.',
            'mind'=>'Use the planned focus block and stop at the scheduled finish.',
            'reflect'=>'Keep the reflection brief and useful.',
        ],
        'high'=>[
            'learn'=>'Complete the core mission first; an extra challenge is optional if you still feel good.',
            'fit'=>'A standard movement session is appropriate; intensity remains your choice.',
            'mind'=>'You may extend focus after the core block if you still feel comfortable.',
            'reflect'=>'Keep reflection brief; high readiness does not require more screen time.',
        ],
    ];
    return [
        'band'=>$band,
        'label'=>(string)($entry['label']??ucfirst($band).' readiness'),
        'recommended_intensity'=>$intensity,
        'message'=>$messages[$band][$arena]??$messages[$band]['learn']??'Follow the planned mission at a sustainable pace.',
        'date'=>(string)($entry['date']??''),
    ];
}
