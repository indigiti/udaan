<?php
declare(strict_types=1);

function udaan_player_arenas(): array {
    return [
        'learn'=>'Learn',
        'fit'=>'Fit',
        'mind'=>'Mind',
        'reflect'=>'Reflect',
    ];
}

function udaan_player_goals(): array {
    return [
        'school'=>'School learning',
        'jee'=>'JEE',
        'neet'=>'NEET',
        'upsc'=>'UPSC / Civil Services',
        'general'=>'General learning',
        'fitness'=>'Fitness & health',
        'growth'=>'Personal growth',
    ];
}

function udaan_player_age_groups(): array {
    return [
        'u13'=>'Under 13',
        '13_15'=>'13–15',
        '16_17'=>'16–17',
        '18_24'=>'18–24',
        '25_plus'=>'25+',
    ];
}

function udaan_player_stages(): array {
    return [
        'class_5_8'=>'Class 5–8',
        'class_9_10'=>'Class 9–10',
        'class_11_12'=>'Class 11–12',
        'college'=>'College',
        'graduate'=>'Graduate / competitive aspirant',
        'other'=>'Other',
    ];
}

function udaan_player_languages(): array {
    return [
        'en'=>'English',
        'hi'=>'हिन्दी',
        'mr'=>'मराठी',
    ];
}

function udaan_player_identity_from_device_hash(string $deviceHash): string {
    return preg_match('/^[a-f0-9]{64}$/i',$deviceHash)
        ? hash_hmac('sha256','player-device:'.strtolower($deviceHash),app_secret())
        : '';
}

function udaan_player_session_identity(): ?string {
    $s=&udaan_session();
    $id=$s['player_identity']??null;
    return is_string($id)&&preg_match('/^[a-f0-9]{64}$/i',$id)?strtolower($id):null;
}

function udaan_player_session_set(string $identity): void {
    if(!preg_match('/^[a-f0-9]{64}$/i',$identity))throw new InvalidArgumentException('Invalid player identity.');
    $s=&udaan_session();$s['player_identity']=strtolower($identity);
}

function udaan_player_default(string $identity): array {
    if(!preg_match('/^[a-f0-9]{64}$/i',$identity))throw new InvalidArgumentException('Invalid player identity.');
    return [
        'schema_version'=>1,
        'id'=>uuid_v4(),
        'identity'=>$identity,
        'nickname'=>'Learner',
        'age_group'=>'',
        'stage'=>'',
        'language'=>'en',
        'main_goal'=>'general',
        'exam_target'=>'',
        'daily_minutes'=>30,
        'arenas'=>['learn'],
        'momentum'=>[
            'weekly_training_days'=>4,
            'habits'=>['learn'],
        ],
        'privacy'=>[
            'profile_visibility'=>'private',
            'share_achievements'=>false,
            'parent_or_institution_sharing'=>false,
        ],
        'onboarding_completed'=>false,
        'created_at'=>now_iso(),
        'updated_at'=>now_iso(),
    ];
}

function udaan_player_normalize(array $input,array $existing): array {
    $nickname=text_cut(trim((string)($input['nickname']??($existing['nickname']??'Learner'))),24);
    if(strlen($nickname)<2||!preg_match('/^[\p{L}\p{N} ._-]{2,24}$/u',$nickname))throw new InvalidArgumentException('Choose a nickname using 2–24 letters, numbers, spaces, dot, dash or underscore.');

    $age=(string)($input['age_group']??($existing['age_group']??''));
    if(!isset(udaan_player_age_groups()[$age]))throw new InvalidArgumentException('Choose an age group.');

    $stage=(string)($input['stage']??($existing['stage']??''));
    if(!isset(udaan_player_stages()[$stage]))throw new InvalidArgumentException('Choose your current learning stage.');

    $language=(string)($input['language']??($existing['language']??'en'));
    if(!isset(udaan_player_languages()[$language]))throw new InvalidArgumentException('Choose a supported language.');

    $goal=(string)($input['main_goal']??($existing['main_goal']??'general'));
    if(!isset(udaan_player_goals()[$goal]))throw new InvalidArgumentException('Choose your main goal.');

    $daily=max(10,min(480,(int)($input['daily_minutes']??($existing['daily_minutes']??30))));
    $examTarget=text_cut(trim((string)($input['exam_target']??($existing['exam_target']??''))),80);

    $arenaInput=$input['arenas']??($existing['arenas']??['learn']);
    if(!is_array($arenaInput))$arenaInput=[];
    $allowed=udaan_player_arenas();$arenas=[];
    foreach($arenaInput as $arena){$arena=(string)$arena;if(isset($allowed[$arena]))$arenas[$arena]=true;}
    $arenas=array_keys($arenas);
    if(!$arenas)$arenas=['learn'];

    $privacy=is_array($existing['privacy']??null)?$existing['privacy']:[];
    $privacy=[
        'profile_visibility'=>'private',
        'share_achievements'=>!empty($input['share_achievements']),
        'parent_or_institution_sharing'=>false,
    ];

    $next=$existing;
    $next['schema_version']=1;
    $next['nickname']=$nickname;
    $next['age_group']=$age;
    $next['stage']=$stage;
    $next['language']=$language;
    $next['main_goal']=$goal;
    $next['exam_target']=$examTarget;
    $next['daily_minutes']=$daily;
    $next['arenas']=$arenas;
    $next['privacy']=$privacy;
    $next['onboarding_completed']=true;
    $next['updated_at']=now_iso();
    return $next;
}

function udaan_player_public(array $player): array {
    return [
        'id'=>(string)($player['id']??''),
        'nickname'=>(string)($player['nickname']??'Learner'),
        'age_group'=>(string)($player['age_group']??''),
        'stage'=>(string)($player['stage']??''),
        'language'=>(string)($player['language']??'en'),
        'main_goal'=>(string)($player['main_goal']??'general'),
        'exam_target'=>(string)($player['exam_target']??''),
        'daily_minutes'=>(int)($player['daily_minutes']??30),
        'arenas'=>array_values(array_map('strval',is_array($player['arenas']??null)?$player['arenas']:[])),
        'privacy'=>is_array($player['privacy']??null)?$player['privacy']:[],
        'onboarding_completed'=>!empty($player['onboarding_completed']),
        'created_at'=>$player['created_at']??null,
        'updated_at'=>$player['updated_at']??null,
    ];
}

function udaan_player_event(string $eventType,array $player,array $metrics=[]): array {
    if(!preg_match('/^[a-z][a-z0-9_.-]{2,80}$/',$eventType))throw new InvalidArgumentException('Invalid player event type.');
    return [
        'event_id'=>uuid_v4(),
        'event_type'=>$eventType,
        'schema_version'=>1,
        'player_id'=>(string)($player['id']??''),
        'arena'=>(string)($metrics['arena']??'core'),
        'occurred_at'=>now_iso(),
        'metrics'=>$metrics,
    ];
}
