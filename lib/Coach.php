<?php
declare(strict_types=1);

function udaan_coach_goal_arena_order(array $player): array {
    $goal=(string)($player['main_goal']??'general');
    return match($goal){
        'fitness'=>['fit','mind','learn','reflect'],
        'growth'=>['mind','reflect','fit','learn'],
        'school','jee','neet','upsc'=>['learn','mind','fit','reflect'],
        default=>['learn','mind','fit','reflect'],
    };
}

function udaan_coach_action_for_mission(array $mission): array {
    $type=(string)($mission['type']??'');
    return match($type){
        'daily9'=>['key'=>'daily','label'=>'Start Daily 9'],
        'fitness'=>['key'=>'fit','label'=>'Start Fit session'],
        'comeback'=>['key'=>'today','label'=>'Open Comeback Mission'],
        default=>['key'=>'today','label'=>'Open Today'],
    };
}

function udaan_coach_action_url(string $key): string {
    return match($key){
        'daily'=>app_url('daily'),
        'fit'=>app_url('fit'),
        'readiness'=>app_url('readiness'),
        'momentum'=>app_url('momentum'),
        'progress'=>app_url('progress'),
        default=>app_url('today'),
    };
}

function udaan_coach_recommendation_id(string $date,string $reasonCode,string $missionId=''): string {
    return substr(hash('sha256','coach-v1|'.$date.'|'.$reasonCode.'|'.$missionId),0,20);
}

function udaan_coach_make_recommendation(
    string $date,
    string $reasonCode,
    string $type,
    string $priority,
    string $title,
    string $body,
    string $actionKey,
    string $actionLabel,
    array $evidence=[],
    ?array $mission=null
): array {
    $missionId=is_array($mission)?(string)($mission['id']??''):'';
    return [
        'schema_version'=>1,
        'id'=>udaan_coach_recommendation_id($date,$reasonCode,$missionId),
        'date'=>$date,
        'reason_code'=>$reasonCode,
        'type'=>$type,
        'priority'=>$priority,
        'title'=>$title,
        'body'=>$body,
        'action_key'=>$actionKey,
        'action_label'=>$actionLabel,
        'mission_id'=>$missionId,
        'arena'=>is_array($mission)?(string)($mission['arena']??'core'):'core',
        'evidence'=>$evidence,
    ];
}

function udaan_coach_prioritized_pending_mission(array $missions,array $player): ?array {
    $pending=array_values(array_filter($missions,fn($m)=>is_array($m)&&!in_array((string)($m['status']??'assigned'),['completed','skipped'],true)));
    if(!$pending)return null;
    $order=udaan_coach_goal_arena_order($player);
    $rank=array_flip($order);
    usort($pending,function($a,$b)use($rank){
        $aComeback=(($a['type']??'')==='comeback')?0:1;
        $bComeback=(($b['type']??'')==='comeback')?0:1;
        if($aComeback!==$bComeback)return $aComeback<=>$bComeback;
        $ar=$rank[(string)($a['arena']??'')]??99;
        $br=$rank[(string)($b['arena']??'')]??99;
        if($ar!==$br)return $ar<=>$br;
        return ((int)($a['duration_minutes']??999))<=>((int)($b['duration_minutes']??999));
    });
    return $pending[0]??null;
}

function udaan_coach_weekly_summary(array $missionState,array $player,array $readinessState,string $date): array {
    $week=udaan_performance_window($missionState,7,$date);
    $momentum=udaan_momentum_summary($missionState,$player,$date);
    $readinessHistory=udaan_daily_readiness_history($readinessState,7);
    $topArena=null;$topCompleted=0;
    foreach((array)($week['arena_stats']??[]) as$arena=>$stat){
        $completed=(int)($stat['completed']??0);
        if($completed>$topCompleted){$topArena=(string)$arena;$topCompleted=$completed;}
    }
    return [
        'training_days'=>(int)($week['training_days']??0),
        'missions_completed'=>(int)($week['completed']??0),
        'training_minutes'=>(int)($week['minutes']??0),
        'completion_rate'=>(int)($week['completion_rate']??0),
        'weekly_goal'=>(int)($momentum['weekly_goal']??4),
        'weekly_goal_days'=>(int)($momentum['week_active_days']??0),
        'weekly_goal_progress'=>(int)($momentum['goal_progress']??0),
        'readiness_checkins'=>count($readinessHistory),
        'latest_readiness_band'=>$readinessHistory[0]['band']??null,
        'top_arena'=>$topArena,
        'top_arena_completed'=>$topCompleted,
        'comeback_due'=>!empty($momentum['comeback_due']),
        'next_milestone'=>$momentum['next_milestone']??null,
    ];
}

function udaan_coach_generate(array $missionState,array $player,array $readinessState,string $date): array {
    $missions=udaan_missions_for_date($missionState,$date);
    $todayReadiness=udaan_daily_readiness_for_date($readinessState,$date);
    $momentum=udaan_momentum_summary($missionState,$player,$date);
    $summary=udaan_coach_weekly_summary($missionState,$player,$readinessState,$date);
    $recommendations=[];

    if(!empty($momentum['comeback_due'])){
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'MOMENTUM_COMEBACK','comeback','high',
            'Restart with one small win',
            'You have had a short gap. Complete the neutral Comeback Mission first—no catch-up marathon and no lost progress.',
            'today','Open Comeback Mission',
            ['inactive_days'=>(int)($momentum['inactive_days']??0)]
        );
    }

    if(is_array($todayReadiness)&&($todayReadiness['band']??'')==='low'){
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'READINESS_LOW','recovery','high',
            'Keep today lighter',
            'Your private readiness signal is low. Use the light versions of pending Missions and prioritize sustainable effort over volume.',
            'today','See lighter Missions',
            ['readiness_band'=>'low']
        );
    }elseif(!is_array($todayReadiness)){
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'READINESS_MISSING','checkin','normal',
            'Check today’s readiness',
            'A quick private check-in helps Udaan tune Mission intensity before recommending harder work.',
            'readiness','Check readiness',
            ['readiness_checkins_7d'=>(int)$summary['readiness_checkins']]
        );
    }

    $mission=udaan_coach_prioritized_pending_mission($missions,$player);
    if(is_array($mission)&&($mission['type']??'')!=='comeback'){
        $action=udaan_coach_action_for_mission($mission);
        $goalLabel=udaan_player_goals()[(string)($player['main_goal']??'general')]??'your goal';
        $readinessBand=(string)($todayReadiness['band']??'balanced');
        $body='This pending Mission best matches '.$goalLabel.' among today’s available work.';
        if($readinessBand==='high'&&in_array((string)($mission['arena']??''),['learn','mind'],true)){
            $body.=' Complete the core Mission first; any extra challenge remains optional.';
        }
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'GOAL_ALIGNED_MISSION','mission','normal',
            (string)($mission['title']??'Continue today’s Mission'),
            $body,
            $action['key'],$action['label'],
            [
                'main_goal'=>(string)($player['main_goal']??'general'),
                'arena'=>(string)($mission['arena']??'core'),
                'duration_minutes'=>(int)($mission['duration_minutes']??0),
                'difficulty'=>(string)($mission['difficulty']??'standard'),
            ],
            $mission
        );
    }

    if(empty($momentum['goal_met'])&&((int)($momentum['weekly_goal']??0)-(int)($momentum['week_active_days']??0))===1){
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'WEEKLY_GOAL_NEAR','momentum','normal',
            'One meaningful day from your weekly goal',
            'You are one training day away from the weekly rhythm you chose. One useful Mission is enough; extra volume is not required.',
            'momentum','View Momentum',
            ['week_active_days'=>(int)$momentum['week_active_days'],'weekly_goal'=>(int)$momentum['weekly_goal']]
        );
    }elseif(!empty($momentum['goal_met'])){
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'WEEKLY_GOAL_MET','recovery','low',
            'Weekly goal already reached',
            'You have met the weekly training-day goal you chose. More work today is optional—protect recovery and quality.',
            'momentum','View weekly progress',
            ['week_active_days'=>(int)$momentum['week_active_days'],'weekly_goal'=>(int)$momentum['weekly_goal']]
        );
    }

    $next=$momentum['next_milestone']??null;
    if(is_array($next)&&!empty($next['progress'])&&(int)$next['progress']>=80&&(int)$next['progress']<100){
        $recommendations[]=udaan_coach_make_recommendation(
            $date,'MILESTONE_NEAR','milestone','low',
            'A milestone is close',
            'Your next milestone is within reach through normal training. Do not add unsafe or unnecessary volume just to earn it.',
            'momentum','See milestone progress',
            ['milestone_key'=>(string)($next['key']??''),'progress'=>(int)($next['progress']??0)]
        );
    }

    $priorityRank=['high'=>0,'normal'=>1,'low'=>2];
    usort($recommendations,function($a,$b)use($priorityRank){
        $pa=$priorityRank[(string)($a['priority']??'normal')]??1;
        $pb=$priorityRank[(string)($b['priority']??'normal')]??1;
        if($pa!==$pb)return $pa<=>$pb;
        return strcmp((string)$a['reason_code'],(string)$b['reason_code']);
    });

    $seen=[];$deduped=[];
    foreach($recommendations as$r){
        $key=(string)$r['reason_code'].'|'.(string)($r['mission_id']??'');
        if(isset($seen[$key]))continue;
        $seen[$key]=true;$deduped[]=$r;
    }

    return [
        'schema_version'=>1,
        'generated_for'=>$date,
        'engine'=>'rules-v1',
        'recommendations'=>array_slice($deduped,0,3),
        'weekly_summary'=>$summary,
    ];
}

function udaan_coach_find_recommendation(array $coach,string $id): ?array {
    foreach((array)($coach['recommendations']??[]) as$r){
        if(is_array($r)&&hash_equals((string)($r['id']??''),$id))return $r;
    }
    return null;
}

function udaan_coach_record_shown_once(TempStore $store,array $player,array $recommendation): bool {
    $session=&udaan_session();
    if(!isset($session['coach_shown'])||!is_array($session['coach_shown']))$session['coach_shown']=[];
    $id=(string)($recommendation['id']??'');
    if($id===''||isset($session['coach_shown'][$id]))return false;
    $session['coach_shown'][$id]=time();
    if(count($session['coach_shown'])>100)$session['coach_shown']=array_slice($session['coach_shown'],-80,null,true);
    $store->appendPlayerEvent(udaan_player_event('coach.recommendation_shown',$player,[
        'arena'=>(string)($recommendation['arena']??'core'),
        'recommendation_id'=>$id,
        'reason_code'=>(string)($recommendation['reason_code']??''),
        'recommendation_type'=>(string)($recommendation['type']??''),
        'priority'=>(string)($recommendation['priority']??'normal'),
        'action_key'=>(string)($recommendation['action_key']??'today'),
        'mission_id'=>(string)($recommendation['mission_id']??''),
    ]));
    return true;
}

function udaan_coach_record_accepted(TempStore $store,array $player,array $recommendation): void {
    $store->appendPlayerEvent(udaan_player_event('coach.recommendation_accepted',$player,[
        'arena'=>(string)($recommendation['arena']??'core'),
        'recommendation_id'=>(string)($recommendation['id']??''),
        'reason_code'=>(string)($recommendation['reason_code']??''),
        'recommendation_type'=>(string)($recommendation['type']??''),
        'priority'=>(string)($recommendation['priority']??'normal'),
        'action_key'=>(string)($recommendation['action_key']??'today'),
        'mission_id'=>(string)($recommendation['mission_id']??''),
    ]));
}
