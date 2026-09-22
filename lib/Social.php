<?php
declare(strict_types=1);

function udaan_social_graph_default(): array {
    return [
        'schema_version'=>1,
        'updated_at'=>null,
        'friendships'=>[],
        'invites'=>[],
        'teams'=>[],
    ];
}

function udaan_social_enabled_for_player(array $player): bool {
    return (string)($player['age_group']??'')!=='u13';
}

function udaan_social_invite_code(): string {
    return strtoupper(bin2hex(random_bytes(8)));
}

function udaan_social_invite_hash(string $code): string {
    $normalized=strtoupper(preg_replace('/[^A-F0-9]/','',$code)??'');
    if(!preg_match('/^[A-F0-9]{16}$/',$normalized))return '';
    return hash_hmac('sha256','social-invite:'.$normalized,app_secret());
}

function udaan_social_friendship_id(string $a,string $b): string {
    $pair=[$a,$b];sort($pair,SORT_STRING);
    return substr(hash_hmac('sha256','friendship:'.$pair[0].'|'.$pair[1],app_secret()),0,32);
}

function udaan_social_team_name(string $name): string {
    $name=trim(preg_replace('/\s+/u',' ',$name)??'');
    $length=function_exists('mb_strlen')?mb_strlen($name,'UTF-8'):strlen($name);
    if($length<3||$length>32)throw new InvalidArgumentException('Team name must be 3–32 characters.');
    if(!preg_match('/^[\p{L}\p{N} ._-]+$/u',$name))throw new InvalidArgumentException('Team name can use letters, numbers, spaces, dot, dash or underscore.');
    return $name;
}

function udaan_social_prune_graph(array $graph): array {
    $graph=array_replace(udaan_social_graph_default(),$graph);
    if(!is_array($graph['friendships']))$graph['friendships']=[];
    if(!is_array($graph['invites']))$graph['invites']=[];
    if(!is_array($graph['teams']))$graph['teams']=[];
    $now=time();
    foreach($graph['invites'] as$hash=>$invite){
        if(!is_array($invite)||strtotime((string)($invite['expires_at']??''))<$now||!empty($invite['used_at']))unset($graph['invites'][$hash]);
    }
    foreach($graph['teams'] as$id=>$team){
        if(!is_array($team)){unset($graph['teams'][$id]);continue;}
        if(!isset($team['members'])||!is_array($team['members']))$graph['teams'][$id]['members']=[];
    }
    $graph['schema_version']=1;$graph['updated_at']=now_iso();
    return $graph;
}

function udaan_social_create_friend_invite(TempStore $store,string $identity,array $player): array {
    if(!udaan_social_enabled_for_player($player))throw new RuntimeException('Social features are unavailable for under-13 Players in this release.');
    $code=udaan_social_invite_code();$hash=udaan_social_invite_hash($code);
    $expires=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->modify('+24 hours')->format(DATE_ATOM);
    $store->mutateSocialGraph(function(?array $current)use($hash,$identity,$player,$expires):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $graph['invites'][$hash]=[
            'type'=>'friend','created_by_identity'=>$identity,'created_by_player_id'=>(string)($player['id']??''),
            'created_at'=>now_iso(),'expires_at'=>$expires,'used_at'=>null,'used_by_identity'=>null,
        ];
        return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.friend_invite_created',$player,['arena'=>'core','expires_hours'=>24]));
    return ['code'=>$code,'expires_at'=>$expires];
}

function udaan_social_accept_friend_invite(TempStore $store,string $identity,array $player,string $code): array {
    if(!udaan_social_enabled_for_player($player))throw new RuntimeException('Social features are unavailable for under-13 Players in this release.');
    $hash=udaan_social_invite_hash($code);if($hash==='')throw new InvalidArgumentException('Invalid friend invite code.');
    $ownerIdentity=null;$relationshipId=null;
    $graph=$store->mutateSocialGraph(function(?array $current)use($hash,$identity,&$ownerIdentity,&$relationshipId):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $invite=$graph['invites'][$hash]??null;
        if(!is_array($invite)||($invite['type']??'')!=='friend')throw new RuntimeException('Friend invite is invalid or expired.');
        $owner=(string)($invite['created_by_identity']??'');
        if($owner===''||$owner===$identity)throw new RuntimeException('You cannot use your own friend invite.');
        $ownerCount=0;$joinerCount=0;
        foreach((array)$graph['friendships'] as$existing){
            if(!is_array($existing))continue;$members=(array)($existing['members']??[]);
            if(in_array($owner,$members,true))$ownerCount++;
            if(in_array($identity,$members,true))$joinerCount++;
        }
        if($ownerCount>=50||$joinerCount>=50)throw new RuntimeException('The v1 friend limit of 50 has been reached.');
        $ownerIdentity=$owner;$relationshipId=udaan_social_friendship_id($owner,$identity);
        if(!isset($graph['friendships'][$relationshipId])){
            $graph['friendships'][$relationshipId]=[
                'id'=>$relationshipId,'members'=>[$owner,$identity],'created_at'=>now_iso(),
            ];
        }
        $graph['invites'][$hash]['used_at']=now_iso();$graph['invites'][$hash]['used_by_identity']=$identity;
        return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.friend_connected',$player,['arena'=>'core','relationship_id'=>$relationshipId]));
    $owner=$ownerIdentity?$store->getPlayer($ownerIdentity):null;
    if(is_array($owner))$store->appendPlayerEvent(udaan_player_event('social.friend_connected',$owner,['arena'=>'core','relationship_id'=>$relationshipId]));
    return ['graph'=>$graph,'relationship_id'=>$relationshipId,'friend_identity'=>$ownerIdentity];
}

function udaan_social_friends(TempStore $store,string $identity,?array $graph=null): array {
    $graph=is_array($graph)?$graph:$store->getSocialGraph();$rows=[];
    foreach((array)($graph['friendships']??[]) as$rel){
        if(!is_array($rel))continue;$members=array_values((array)($rel['members']??[]));
        if(!in_array($identity,$members,true))continue;
        $other=$members[0]??'';if($other===$identity)$other=$members[1]??'';
        if(!is_string($other)||$other==='')continue;
        $player=$store->getPlayer($other);if(!is_array($player))continue;
        $rows[]=[
            'relationship_id'=>(string)($rel['id']??''),'identity'=>$other,
            'player_id'=>(string)($player['id']??''),'nickname'=>(string)($player['nickname']??'Player'),
            'since'=>(string)($rel['created_at']??''),
        ];
    }
    usort($rows,fn($a,$b)=>strcasecmp((string)$a['nickname'],(string)$b['nickname']));
    return $rows;
}

function udaan_social_remove_friend(TempStore $store,string $identity,array $player,string $relationshipId): void {
    if(!preg_match('/^[a-f0-9]{32}$/',$relationshipId))throw new InvalidArgumentException('Invalid friendship.');
    $otherIdentity=null;
    $store->mutateSocialGraph(function(?array $current)use($identity,$relationshipId,&$otherIdentity):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $rel=$graph['friendships'][$relationshipId]??null;
        if(!is_array($rel)||!in_array($identity,(array)($rel['members']??[]),true))throw new RuntimeException('Friendship not found.');
        foreach((array)$rel['members'] as$m)if($m!==$identity)$otherIdentity=(string)$m;
        unset($graph['friendships'][$relationshipId]);return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.friend_removed',$player,['arena'=>'core','relationship_id'=>$relationshipId]));
    $other=$otherIdentity?$store->getPlayer($otherIdentity):null;
    if(is_array($other))$store->appendPlayerEvent(udaan_player_event('social.friend_removed',$other,['arena'=>'core','relationship_id'=>$relationshipId]));
}

function udaan_social_create_team(TempStore $store,string $identity,array $player,string $name): array {
    if(!udaan_social_enabled_for_player($player))throw new RuntimeException('Social features are unavailable for under-13 Players in this release.');
    $name=udaan_social_team_name($name);
    $graph=$store->getSocialGraph();
    if(count(udaan_social_teams_for_identity($graph,$identity))>=5)throw new RuntimeException('The v1 limit is 5 team memberships per Player.');
    $teamId=uuid_v4();
    $team=[
        'schema_version'=>1,'id'=>$teamId,'name'=>$name,'created_by_identity'=>$identity,
        'created_by_player_id'=>(string)($player['id']??''),'created_at'=>now_iso(),'updated_at'=>now_iso(),
        'members'=>[$identity=>['role'=>'owner','joined_at'=>now_iso()]],'active_challenge'=>null,'archived_at'=>null,
    ];
    $store->mutateSocialGraph(function(?array $current)use($teamId,$team):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);$graph['teams'][$teamId]=$team;return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.team_created',$player,['arena'=>'core','team_id'=>$teamId]));
    return $team;
}

function udaan_social_teams_for_identity(array $graph,string $identity): array {
    $rows=[];
    foreach((array)($graph['teams']??[]) as$team){
        if(!is_array($team)||!empty($team['archived_at']))continue;
        if(isset($team['members'][$identity]))$rows[]=$team;
    }
    usort($rows,fn($a,$b)=>strcasecmp((string)($a['name']??''),(string)($b['name']??'')));
    return $rows;
}

function udaan_social_team(array $graph,string $teamId,string $identity): ?array {
    $team=$graph['teams'][$teamId]??null;
    if(!is_array($team)||!empty($team['archived_at'])||!isset($team['members'][$identity]))return null;
    return $team;
}

function udaan_social_create_team_invite(TempStore $store,string $identity,array $player,string $teamId): array {
    if(!udaan_social_enabled_for_player($player))throw new RuntimeException('Social features are unavailable for under-13 Players in this release.');
    $code=udaan_social_invite_code();$hash=udaan_social_invite_hash($code);
    $expires=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->modify('+7 days')->format(DATE_ATOM);
    $store->mutateSocialGraph(function(?array $current)use($identity,$teamId,$hash,$expires):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $team=$graph['teams'][$teamId]??null;
        if(!is_array($team)||!isset($team['members'][$identity]))throw new RuntimeException('Team not found.');
        if(($team['members'][$identity]['role']??'member')!=='owner')throw new RuntimeException('Only the team owner can create join invites.');
        if(count((array)$team['members'])>=8)throw new RuntimeException('This team already has the v1 maximum of 8 members.');
        $graph['invites'][$hash]=[
            'type'=>'team','team_id'=>$teamId,'created_by_identity'=>$identity,'created_at'=>now_iso(),
            'expires_at'=>$expires,'used_at'=>null,'used_by_identity'=>null,
        ];
        return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.team_invite_created',$player,['arena'=>'core','team_id'=>$teamId,'expires_days'=>7]));
    return ['code'=>$code,'expires_at'=>$expires,'team_id'=>$teamId];
}

function udaan_social_accept_team_invite(TempStore $store,string $identity,array $player,string $code): array {
    if(!udaan_social_enabled_for_player($player))throw new RuntimeException('Social features are unavailable for under-13 Players in this release.');
    $hash=udaan_social_invite_hash($code);if($hash==='')throw new InvalidArgumentException('Invalid team invite code.');
    $teamId=null;
    $graph=$store->mutateSocialGraph(function(?array $current)use($hash,$identity,&$teamId):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $invite=$graph['invites'][$hash]??null;
        if(!is_array($invite)||($invite['type']??'')!=='team')throw new RuntimeException('Team invite is invalid or expired.');
        $teamId=(string)($invite['team_id']??'');$team=$graph['teams'][$teamId]??null;
        if(!is_array($team)||!empty($team['archived_at']))throw new RuntimeException('Team is unavailable.');
        if(isset($team['members'][$identity]))throw new RuntimeException('You are already a member of this team.');
        if(count(udaan_social_teams_for_identity($graph,$identity))>=5)throw new RuntimeException('The v1 limit is 5 team memberships per Player.');
        if(count((array)$team['members'])>=8)throw new RuntimeException('This team has reached the v1 maximum of 8 members.');
        $graph['teams'][$teamId]['members'][$identity]=['role'=>'member','joined_at'=>now_iso()];
        $graph['teams'][$teamId]['updated_at']=now_iso();
        $graph['invites'][$hash]['used_at']=now_iso();$graph['invites'][$hash]['used_by_identity']=$identity;
        return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.team_joined',$player,['arena'=>'core','team_id'=>$teamId]));
    return ['graph'=>$graph,'team_id'=>$teamId];
}

function udaan_social_leave_team(TempStore $store,string $identity,array $player,string $teamId): void {
    $store->mutateSocialGraph(function(?array $current)use($identity,$teamId):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $team=$graph['teams'][$teamId]??null;
        if(!is_array($team)||!isset($team['members'][$identity]))throw new RuntimeException('Team not found.');
        if(($team['members'][$identity]['role']??'member')==='owner')throw new RuntimeException('The team owner cannot leave in v1. Archive/transfer controls will arrive later.');
        unset($graph['teams'][$teamId]['members'][$identity]);$graph['teams'][$teamId]['updated_at']=now_iso();
        return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.team_left',$player,['arena'=>'core','team_id'=>$teamId]));
}

function udaan_social_team_members(TempStore $store,array $team): array {
    $rows=[];
    foreach((array)($team['members']??[]) as$identity=>$membership){
        $player=$store->getPlayer((string)$identity);if(!is_array($player))continue;
        $rows[]=[
            'player_id'=>(string)($player['id']??''),
            'nickname'=>(string)($player['nickname']??'Player'),'role'=>(string)($membership['role']??'member'),
            'joined_at'=>(string)($membership['joined_at']??''),
        ];
    }
    usort($rows,function($a,$b){if($a['role']!==$b['role'])return $a['role']==='owner'?-1:1;return strcasecmp($a['nickname'],$b['nickname']);});
    return $rows;
}

function udaan_social_create_team_challenge(TempStore $store,string $identity,array $player,string $teamId,string $arena,string $date): array {
    if(!isset(udaan_player_arenas()[$arena]))throw new InvalidArgumentException('Choose a supported Arena.');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Invalid challenge date.');
    $challenge=null;
    $store->mutateSocialGraph(function(?array $current)use($identity,$teamId,$arena,$date,&$challenge):array{
        $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
        $team=$graph['teams'][$teamId]??null;
        if(!is_array($team)||!isset($team['members'][$identity]))throw new RuntimeException('Team not found.');
        if(($team['members'][$identity]['role']??'member')!=='owner')throw new RuntimeException('Only the team owner can start a challenge.');
        $existing=$team['active_challenge']??null;
        if(is_array($existing)&&($existing['end_date']??'')>=$date)throw new RuntimeException('Finish the current team challenge before starting another.');
        $start=new DateTimeImmutable($date,new DateTimeZone('Asia/Kolkata'));
        $challenge=[
            'id'=>uuid_v4(),'arena'=>$arena,'start_date'=>$date,'end_date'=>$start->modify('+6 days')->format('Y-m-d'),
            'created_at'=>now_iso(),'created_by_identity'=>$identity,'rule'=>'one_completed_mission_per_member',
        ];
        $graph['teams'][$teamId]['active_challenge']=$challenge;$graph['teams'][$teamId]['updated_at']=now_iso();
        return udaan_social_prune_graph($graph);
    });
    $store->appendPlayerEvent(udaan_player_event('social.team_challenge_created',$player,['arena'=>$arena,'team_id'=>$teamId,'challenge_id'=>$challenge['id']]));
    return $challenge;
}

function udaan_social_team_challenge_progress(TempStore $store,array $team): array {
    $challenge=$team['active_challenge']??null;
    if(!is_array($challenge))return ['active'=>false,'participants'=>0,'members'=>0,'rows'=>[]];
    $rows=[];$participants=0;$arena=(string)($challenge['arena']??'learn');
    foreach((array)($team['members']??[]) as$identity=>$membership){
        $player=$store->getPlayer((string)$identity);if(!is_array($player))continue;
        $done=false;
        $state=$store->getMissionState((string)$identity);
        foreach((array)($state['missions']??[]) as$m){
            if(!is_array($m)||($m['status']??'')!=='completed'||($m['arena']??'')!==$arena)continue;
            $date=(string)($m['scheduled_date']??'');
            if($date>=(string)$challenge['start_date']&&$date<=(string)$challenge['end_date']){$done=true;break;}
        }
        if($done)$participants++;
        $rows[]=['nickname'=>(string)($player['nickname']??'Player'),'completed'=>$done,'role'=>(string)($membership['role']??'member')];
    }
    return [
        'active'=>true,'challenge'=>$challenge,'participants'=>$participants,'members'=>count($rows),'rows'=>$rows,
        'completion_rate'=>count($rows)?(int)round(($participants/count($rows))*100):0,
    ];
}
