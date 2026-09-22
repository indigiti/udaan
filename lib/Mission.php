<?php
declare(strict_types=1);

require_once __DIR__.'/Performance.php';
require_once __DIR__.'/DailyReadiness.php';
require_once __DIR__.'/Fit.php';
require_once __DIR__.'/Momentum.php';

function udaan_mission_types(): array {
    return [
        'learning'=>'Learning',
        'revision'=>'Revision',
        'daily9'=>'Daily 9',
        'fitness'=>'Movement',
        'focus'=>'Focus',
        'reflection'=>'Reflection',
        'comeback'=>'Comeback',
    ];
}

function udaan_mission_statuses(): array {
    return ['assigned','in_progress','completed','skipped'];
}

function udaan_mission_difficulties(): array {
    return ['light','standard','challenge'];
}

function udaan_mission_state_default(): array {
    return ['schema_version'=>1,'updated_at'=>null,'missions'=>[]];
}

function udaan_mission_templates_for_player(array $player,string $date): array {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Invalid mission date.');
    $selected=array_values(array_unique(array_map('strval',is_array($player['arenas']??null)?$player['arenas']:['learn'])));
    $out=[];

    if(in_array('learn',$selected,true)){
        $out[]=[
            'source_key'=>'daily:'.$date.':daily9',
            'arena'=>'learn',
            'type'=>'daily9',
            'title'=>"Today's Daily 9",
            'objective'=>'Explore nine verified ideas across the Udaan learning pillars.',
            'duration_minutes'=>2,
            'difficulty'=>'standard',
            'content_refs'=>[],
            'completion_rule'=>['mode'=>'event','event_type'=>'daily9_completed'],
        ];
    }
    if(in_array('fit',$selected,true)){
        $out[]=[
            'source_key'=>'daily:'.$date.':movement',
            'arena'=>'fit',
            'type'=>'fitness',
            'title'=>'10-minute movement break',
            'objective'=>'Choose gentle walking, mobility or stretching that feels comfortable for you.',
            'duration_minutes'=>10,
            'difficulty'=>'light',
            'content_refs'=>[],
            'completion_rule'=>['mode'=>'fit_session'],
        ];
    }
    if(in_array('mind',$selected,true)){
        $out[]=[
            'source_key'=>'daily:'.$date.':focus',
            'arena'=>'mind',
            'type'=>'focus',
            'title'=>'10-minute focus block',
            'objective'=>'Choose one useful task, remove obvious distractions, and focus on only that task for ten minutes.',
            'duration_minutes'=>10,
            'difficulty'=>'standard',
            'content_refs'=>[],
            'completion_rule'=>['mode'=>'self_report'],
        ];
    }
    if(in_array('reflect',$selected,true)){
        $out[]=[
            'source_key'=>'daily:'.$date.':reflection',
            'arena'=>'reflect',
            'type'=>'reflection',
            'title'=>'2-minute reflection',
            'objective'=>'Pause and identify one useful idea, lesson or intention from today. Udaan does not collect the reflection text.',
            'duration_minutes'=>2,
            'difficulty'=>'light',
            'content_refs'=>[],
            'completion_rule'=>['mode'=>'self_report'],
        ];
    }
    return $out;
}

function udaan_mission_from_template(array $template,array $player,string $date): array {
    $arena=(string)($template['arena']??'');
    if(!isset(udaan_player_arenas()[$arena]))throw new InvalidArgumentException('Invalid mission arena.');
    $type=(string)($template['type']??'');
    if(!isset(udaan_mission_types()[$type]))throw new InvalidArgumentException('Invalid mission type.');
    $difficulty=(string)($template['difficulty']??'standard');
    if(!in_array($difficulty,udaan_mission_difficulties(),true))throw new InvalidArgumentException('Invalid mission difficulty.');
    $rule=is_array($template['completion_rule']??null)?$template['completion_rule']:[];
    $mode=(string)($rule['mode']??'');
    if(!in_array($mode,['self_report','event','fit_session'],true))throw new InvalidArgumentException('Invalid mission completion rule.');

    return [
        'schema_version'=>1,
        'id'=>uuid_v4(),
        'player_id'=>(string)($player['id']??''),
        'source_key'=>(string)($template['source_key']??''),
        'arena'=>$arena,
        'type'=>$type,
        'title'=>text_cut(trim((string)($template['title']??'Mission')),100),
        'objective'=>text_cut(trim((string)($template['objective']??'')),320),
        'duration_minutes'=>max(1,min(480,(int)($template['duration_minutes']??10))),
        'difficulty'=>$difficulty,
        'content_refs'=>array_values(is_array($template['content_refs']??null)?$template['content_refs']:[]),
        'completion_rule'=>$rule,
        'status'=>'assigned',
        'scheduled_date'=>$date,
        'created_at'=>now_iso(),
        'updated_at'=>now_iso(),
        'started_at'=>null,
        'completed_at'=>null,
        'skipped_at'=>null,
        'result'=>null,
    ];
}

function udaan_mission_prune_state(array $state,int $max=500): array {
    if(!isset($state['missions'])||!is_array($state['missions']))$state['missions']=[];
    if(count($state['missions'])>$max){
        uasort($state['missions'],fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
        $state['missions']=array_slice($state['missions'],0,$max,true);
    }
    $state['schema_version']=1;$state['updated_at']=now_iso();
    return $state;
}

function udaan_mission_ensure_daily(TempStore $store,string $identity,array $player,string $date): array {
    $templates=udaan_mission_templates_for_player($player,$date);$added=[];
    $state=$store->mutateMissionState($identity,function(?array $current)use($templates,$player,$date,&$added):array{
        $added=[];$state=is_array($current)?array_replace(udaan_mission_state_default(),$current):udaan_mission_state_default();
        if(!isset($state['missions'])||!is_array($state['missions']))$state['missions']=[];
        $existing=[];
        foreach($state['missions'] as $m)if(is_array($m)&&!empty($m['source_key']))$existing[(string)$m['source_key']]=true;
        foreach($templates as$template){
            $source=(string)$template['source_key'];if(isset($existing[$source]))continue;
            $mission=udaan_mission_from_template($template,$player,$date);
            $state['missions'][$mission['id']]=$mission;$existing[$source]=true;$added[$mission['id']]=$mission;
        }
        return udaan_mission_prune_state($state);
    });
    foreach($added as$mission){
        $store->appendPlayerEvent(udaan_player_event('mission.assigned',$player,[
            'arena'=>$mission['arena'],'mission_id'=>$mission['id'],'mission_type'=>$mission['type'],
            'scheduled_date'=>$mission['scheduled_date'],'duration_minutes'=>$mission['duration_minutes'],
        ]));
    }

    $momentum=udaan_momentum_summary($state,$player,$date);
    if(!empty($momentum['comeback_due'])){
        $template=udaan_momentum_comeback_template($player,$date);
        if(is_array($template)){
            $comebackAdded=null;
            $state=$store->mutateMissionState($identity,function(?array $current)use($template,$player,$date,&$comebackAdded):array{
                $s=is_array($current)?array_replace(udaan_mission_state_default(),$current):udaan_mission_state_default();
                foreach((array)($s['missions']??[]) as$m)if(is_array($m)&&($m['source_key']??'')===($template['source_key']??''))return $s;
                $mission=udaan_mission_from_template($template,$player,$date);
                $s['missions'][$mission['id']]=$mission;$comebackAdded=$mission;
                return udaan_mission_prune_state($s);
            });
            if(is_array($comebackAdded)){
                $store->appendPlayerEvent(udaan_player_event('momentum.comeback_assigned',$player,[
                    'arena'=>$comebackAdded['arena'],'mission_id'=>$comebackAdded['id'],
                    'inactive_days'=>(int)($momentum['inactive_days']??0),
                    'scheduled_date'=>$date,
                ]));
            }
        }
    }
    return $state;
}

function udaan_missions_for_date(array $state,string $date): array {
    $rows=[];foreach((array)($state['missions']??[])as$m)if(is_array($m)&&($m['scheduled_date']??'')===$date)$rows[]=$m;
    usort($rows,fn($a,$b)=>strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')));
    return $rows;
}

function udaan_mission_history(array $state,int $limit=30): array {
    $rows=array_values(array_filter((array)($state['missions']??[]),'is_array'));
    usort($rows,fn($a,$b)=>strcmp((string)($b['scheduled_date']??'').(string)($b['created_at']??''),(string)($a['scheduled_date']??'').(string)($a['created_at']??'')));
    return array_slice($rows,0,max(1,min(200,$limit)));
}


function udaan_mission_emit_personal_bests(TempStore $store,array $player,array $before,array $after,array $mission): void {
    $date=(string)($mission['scheduled_date']??'');
    foreach(udaan_performance_personal_best_events($before,$after,$date) as $best){
        $store->appendPlayerEvent(udaan_player_event('performance.personal_best',$player,[
            'arena'=>(string)($mission['arena']??'core'),
            'mission_id'=>(string)($mission['id']??''),
            'kind'=>(string)($best['kind']??''),
            'value'=>(int)($best['value']??0),
            'previous_best'=>(int)($best['previous_best']??0),
            'date'=>$date,
        ]));
    }
}


function udaan_mission_apply_readiness(TempStore $store,string $identity,array $entry): array {
    $date=(string)($entry['date']??'');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Invalid readiness date.');
    return $store->mutateMissionState($identity,function(?array $current)use($entry,$date):array{
        $state=is_array($current)?array_replace(udaan_mission_state_default(),$current):udaan_mission_state_default();
        foreach((array)($state['missions']??[]) as$id=>$mission){
            if(!is_array($mission)||($mission['scheduled_date']??'')!==$date)continue;
            if(in_array((string)($mission['status']??'assigned'),['completed','skipped'],true))continue;
            $arena=(string)($mission['arena']??'learn');
            $guidance=udaan_daily_readiness_mission_guidance($entry,$arena);
            $mission['readiness_guidance']=$guidance;
            if(($mission['type']??'')!=='daily9'){
                $type=(string)($mission['type']??'');
                $band=(string)($entry['band']??'balanced');
                if($band==='low'){
                    $mission['difficulty']='light';
                }elseif($band==='high'){
                    $mission['difficulty']=in_array($type,['focus','learning','revision'],true)?'challenge':($type==='fitness'?'standard':'light');
                }else{
                    $mission['difficulty']=in_array($type,['fitness','reflection'],true)?'light':'standard';
                }
            }
            $mission['updated_at']=now_iso();
            $state['missions'][$id]=$mission;
        }
        return udaan_mission_prune_state($state);
    });
}

function udaan_mission_apply_status(TempStore $store,string $identity,array $player,string $missionId,string $status,array $result=[]): array {
    if(!in_array($status,['completed','skipped'],true))throw new InvalidArgumentException('Unsupported mission action.');
    $changed=false;$mission=null;$before=$store->getMissionState($identity);
    $state=$store->mutateMissionState($identity,function(?array $current)use($missionId,$status,$result,&$changed,&$mission):array{
        $state=is_array($current)?array_replace(udaan_mission_state_default(),$current):udaan_mission_state_default();
        if(!isset($state['missions'][$missionId])||!is_array($state['missions'][$missionId]))throw new RuntimeException('Mission not found.');
        $m=$state['missions'][$missionId];
        $mode=(string)($m['completion_rule']['mode']??'self_report');
        if($status==='completed'&&$mode==='event')throw new RuntimeException('This mission completes automatically from its verified activity.');
        if($status==='completed'&&$mode==='fit_session'&&empty($result['activity_id']))throw new RuntimeException('Complete this movement mission from Udaan Fit.');
        if(($m['status']??'')===$status){$mission=$m;return $state;}
        if(($m['status']??'')==='completed'&&$status==='skipped'){$mission=$m;return $state;}
        $m['status']=$status;$m['updated_at']=now_iso();
        if($status==='completed'){
            $source=$mode==='fit_session'?'fit_session':'self_report';
            $m['completed_at']=now_iso();$m['result']=['source'=>$source]+$result;
        }else{$m['skipped_at']=now_iso();$m['result']=['source'=>'self_report']+$result;}
        $state['missions'][$missionId]=$m;$mission=$m;$changed=true;
        return udaan_mission_prune_state($state);
    });
    if($changed&&is_array($mission)){
        $store->appendPlayerEvent(udaan_player_event($status==='completed'?'mission.completed':'mission.skipped',$player,[
            'arena'=>$mission['arena'],'mission_id'=>$mission['id'],'mission_type'=>$mission['type'],
            'scheduled_date'=>$mission['scheduled_date'],
            'duration_minutes'=>(int)($mission['result']['actual_minutes']??$mission['duration_minutes']),
            'completion_source'=>(string)($mission['result']['source']??'self_report'),
            'activity_id'=>(string)($mission['result']['activity_id']??''),
        ]));
        if($status==='completed'){
            udaan_mission_emit_personal_bests($store,$player,$before,$state,$mission);
            if(($mission['type']??'')==='comeback'){
                $store->appendPlayerEvent(udaan_player_event('momentum.comeback_completed',$player,[
                    'arena'=>$mission['arena'],'mission_id'=>$mission['id'],'scheduled_date'=>$mission['scheduled_date'],
                ]));
            }
        }
    }
    return ['state'=>$state,'mission'=>$mission,'changed'=>$changed];
}

function udaan_mission_complete_by_source(TempStore $store,string $identity,array $player,string $sourceKey,array $result=[]): ?array {
    $changed=false;$mission=null;$before=$store->getMissionState($identity);
    $state=$store->mutateMissionState($identity,function(?array $current)use($sourceKey,$result,&$changed,&$mission):array{
        $state=is_array($current)?array_replace(udaan_mission_state_default(),$current):udaan_mission_state_default();
        foreach((array)($state['missions']??[])as$id=>$candidate){
            if(!is_array($candidate)||($candidate['source_key']??'')!==$sourceKey)continue;
            if(($candidate['status']??'')==='completed'){$mission=$candidate;return $state;}
            $candidate['status']='completed';$candidate['completed_at']=now_iso();$candidate['updated_at']=now_iso();
            $candidate['result']=['source'=>'verified_event']+$result;$state['missions'][$id]=$candidate;$mission=$candidate;$changed=true;
            return udaan_mission_prune_state($state);
        }
        return $state;
    });
    if($changed&&is_array($mission)){
        $store->appendPlayerEvent(udaan_player_event('mission.completed',$player,[
            'arena'=>$mission['arena'],'mission_id'=>$mission['id'],'mission_type'=>$mission['type'],
            'scheduled_date'=>$mission['scheduled_date'],'duration_minutes'=>$mission['duration_minutes'],
            'completion_source'=>'verified_event',
        ]));
        udaan_mission_emit_personal_bests($store,$player,$before,$state,$mission);
    }
    return is_array($mission)?$mission:null;
}
