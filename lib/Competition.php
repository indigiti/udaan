<?php
declare(strict_types=1);

function udaan_competition_state_default(): array {
    return ['schema_version'=>1,'updated_at'=>null,'leagues'=>[],'invites'=>[]];
}

function udaan_competition_divisions(): array {
    return [
        'foundation'=>'Foundation',
        'rising'=>'Rising',
        'elite'=>'Elite',
    ];
}

function udaan_competition_invite_code(): string {
    return strtoupper(bin2hex(random_bytes(8)));
}

function udaan_competition_invite_hash(string $code): string {
    $normalized=strtoupper(preg_replace('/[^A-F0-9]/','',$code)??'');
    if(!preg_match('/^[A-F0-9]{16}$/',$normalized))return '';
    return hash_hmac('sha256','competition-invite:'.$normalized,app_secret());
}

function udaan_competition_prune_state(array $state): array {
    $state=array_replace(udaan_competition_state_default(),$state);
    if(!is_array($state['leagues']))$state['leagues']=[];
    if(!is_array($state['invites']))$state['invites']=[];
    $now=time();
    foreach($state['invites'] as$hash=>$invite){
        if(!is_array($invite)||strtotime((string)($invite['expires_at']??''))<$now||!empty($invite['used_at']))unset($state['invites'][$hash]);
    }
    $state['schema_version']=1;$state['updated_at']=now_iso();
    return $state;
}

function udaan_competition_league_name(string $name): string {
    $name=trim(preg_replace('/\s+/u',' ',$name)??'');
    $length=function_exists('mb_strlen')?mb_strlen($name,'UTF-8'):strlen($name);
    if($length<3||$length>40)throw new InvalidArgumentException('League name must be 3–40 characters.');
    if(!preg_match('/^[\p{L}\p{N} ._-]+$/u',$name))throw new InvalidArgumentException('League name can use letters, numbers, spaces, dot, dash or underscore.');
    return $name;
}

function udaan_competition_owned_team(TempStore $store,string $identity,string $teamId): ?array {
    $graph=$store->getSocialGraph();
    $team=udaan_social_team($graph,$teamId,$identity);
    if(!is_array($team))return null;
    return (($team['members'][$identity]['role']??'member')==='owner')?$team:null;
}

function udaan_competition_create_league(TempStore $store,string $identity,array $player,string $teamId,string $name,string $arena): array {
    if(!udaan_social_enabled_for_player($player))throw new RuntimeException('Competition features are unavailable for under-13 Players in this release.');
    $team=udaan_competition_owned_team($store,$identity,$teamId);
    if(!is_array($team))throw new RuntimeException('Only a team owner can create a league for that team.');
    $name=udaan_competition_league_name($name);
    if(!isset(udaan_player_arenas()[$arena]))throw new InvalidArgumentException('Choose a supported Arena.');
    if(!in_array($arena,udaan_social_team_common_arenas($store,$team),true))throw new RuntimeException('The selected Arena must be enabled by every member of your team.');
    $leagueId=uuid_v4();
    $league=[
        'schema_version'=>1,
        'id'=>$leagueId,
        'name'=>$name,
        'arena'=>$arena,
        'division'=>'foundation',
        'visibility'=>'private',
        'created_by_identity'=>$identity,
        'owner_team_id'=>$teamId,
        'created_at'=>now_iso(),
        'updated_at'=>now_iso(),
        'teams'=>[$teamId=>['joined_at'=>now_iso()]],
        'season'=>null,
        'archived_at'=>null,
    ];
    $store->mutateCompetitionState(function(?array $current)use($leagueId,$league):array{
        $state=udaan_competition_prune_state(is_array($current)?$current:[]);
        $state['leagues'][$leagueId]=$league;
        return udaan_competition_prune_state($state);
    });
    $store->appendPlayerEvent(udaan_player_event('competition.league_created',$player,['arena'=>$arena,'league_id'=>$leagueId,'team_id'=>$teamId,'division'=>'foundation']));
    return $league;
}

function udaan_competition_league(array $state,string $leagueId,string $identity,TempStore $store): ?array {
    $league=$state['leagues'][$leagueId]??null;
    if(!is_array($league)||!empty($league['archived_at']))return null;
    $graph=$store->getSocialGraph();
    foreach(array_keys((array)($league['teams']??[])) as$teamId){
        $team=udaan_social_team($graph,(string)$teamId,$identity);
        if(is_array($team))return $league;
    }
    return null;
}

function udaan_competition_leagues_for_identity(TempStore $store,string $identity,?array $state=null): array {
    $state=is_array($state)?$state:$store->getCompetitionState();
    $graph=$store->getSocialGraph();$rows=[];
    foreach((array)($state['leagues']??[]) as$league){
        if(!is_array($league)||!empty($league['archived_at']))continue;
        foreach(array_keys((array)($league['teams']??[])) as$teamId){
            if(is_array(udaan_social_team($graph,(string)$teamId,$identity))){$rows[]=$league;break;}
        }
    }
    usort($rows,fn($a,$b)=>strcasecmp((string)($a['name']??''),(string)($b['name']??'')));
    return $rows;
}

function udaan_competition_create_invite(TempStore $store,string $identity,array $player,string $leagueId): array {
    $state=$store->getCompetitionState();$league=$state['leagues'][$leagueId]??null;
    if(!is_array($league)||!empty($league['archived_at']))throw new RuntimeException('League not found.');
    if((string)($league['created_by_identity']??'')!==$identity)throw new RuntimeException('Only the league creator can make team join codes.');
    if(is_array($league['season']??null))throw new RuntimeException('Teams cannot join after the season has started.');
    if(count((array)($league['teams']??[]))>=8)throw new RuntimeException('This private pilot league already has the maximum of 8 teams.');
    $code=udaan_competition_invite_code();$hash=udaan_competition_invite_hash($code);
    $expires=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->modify('+7 days')->format(DATE_ATOM);
    $store->mutateCompetitionState(function(?array $current)use($hash,$identity,$leagueId,$expires):array{
        $state=udaan_competition_prune_state(is_array($current)?$current:[]);
        $league=$state['leagues'][$leagueId]??null;
        if(!is_array($league)||is_array($league['season']??null))throw new RuntimeException('League is no longer accepting teams.');
        $state['invites'][$hash]=[
            'type'=>'league_team','league_id'=>$leagueId,'created_by_identity'=>$identity,
            'created_at'=>now_iso(),'expires_at'=>$expires,'used_at'=>null,'used_by_identity'=>null,
        ];
        return udaan_competition_prune_state($state);
    });
    $store->appendPlayerEvent(udaan_player_event('competition.league_invite_created',$player,['arena'=>(string)$league['arena'],'league_id'=>$leagueId,'expires_days'=>7]));
    return ['code'=>$code,'expires_at'=>$expires,'league_id'=>$leagueId];
}

function udaan_competition_join_league(TempStore $store,string $identity,array $player,string $teamId,string $code): array {
    $team=udaan_competition_owned_team($store,$identity,$teamId);
    if(!is_array($team))throw new RuntimeException('Only a team owner can enter that team into a league.');
    $hash=udaan_competition_invite_hash($code);if($hash==='')throw new InvalidArgumentException('Invalid league invite code.');
    $leagueId=null;$arena=null;
    $state=$store->mutateCompetitionState(function(?array $current)use($store,$hash,$identity,$teamId,$team,&$leagueId,&$arena):array{
        $state=udaan_competition_prune_state(is_array($current)?$current:[]);
        $invite=$state['invites'][$hash]??null;
        if(!is_array($invite)||($invite['type']??'')!=='league_team')throw new RuntimeException('League invite is invalid or expired.');
        $leagueId=(string)($invite['league_id']??'');$league=$state['leagues'][$leagueId]??null;
        if(!is_array($league)||is_array($league['season']??null))throw new RuntimeException('League is no longer accepting teams.');
        if(isset($league['teams'][$teamId]))throw new RuntimeException('This team is already in the league.');
        if(count((array)$league['teams'])>=8)throw new RuntimeException('This private pilot league already has 8 teams.');
        $arena=(string)($league['arena']??'learn');
        if(!in_array($arena,udaan_social_team_common_arenas($store,$team),true))throw new RuntimeException('Every team member must have the league Arena enabled before joining.');
        $state['leagues'][$leagueId]['teams'][$teamId]=['joined_at'=>now_iso()];
        $state['leagues'][$leagueId]['updated_at']=now_iso();
        $state['invites'][$hash]['used_at']=now_iso();$state['invites'][$hash]['used_by_identity']=$identity;
        return udaan_competition_prune_state($state);
    });
    $store->appendPlayerEvent(udaan_player_event('competition.team_joined_league',$player,['arena'=>$arena,'league_id'=>$leagueId,'team_id'=>$teamId]));
    return ['state'=>$state,'league_id'=>$leagueId];
}

function udaan_competition_round_robin(array $teamIds): array {
    $teams=array_values(array_unique(array_map('strval',$teamIds)));
    if(count($teams)<2)return [];
    if(count($teams)%2===1)$teams[]='__BYE__';
    $n=count($teams);$rounds=[];
    for($round=0;$round<$n-1;$round++){
        $pairs=[];
        for($i=0;$i<$n/2;$i++){
            $a=$teams[$i];$b=$teams[$n-1-$i];
            if($a!=='__BYE__'&&$b!=='__BYE__')$pairs[]=[$a,$b];
        }
        $rounds[]=$pairs;
        $fixed=array_shift($teams);
        $last=array_pop($teams);
        array_unshift($teams,$fixed);
        array_splice($teams,1,0,[$last]);
    }
    return $rounds;
}

function udaan_competition_start_season(TempStore $store,string $identity,array $player,string $leagueId,string $date): array {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new InvalidArgumentException('Invalid season date.');
    $season=null;
    $store->mutateCompetitionState(function(?array $current)use($identity,$leagueId,$date,&$season):array{
        $state=udaan_competition_prune_state(is_array($current)?$current:[]);
        $league=$state['leagues'][$leagueId]??null;
        if(!is_array($league)||!empty($league['archived_at']))throw new RuntimeException('League not found.');
        if((string)($league['created_by_identity']??'')!==$identity)throw new RuntimeException('Only the league creator can start the season.');
        if(is_array($league['season']??null))throw new RuntimeException('This league already has a season.');
        $teamIds=array_keys((array)($league['teams']??[]));
        if(count($teamIds)<2)throw new RuntimeException('At least two teams are required to start a season.');
        $rosters=[];
        foreach($teamIds as$teamId){
            $team=$social['teams'][$teamId]??null;
            if(!is_array($team)||empty($team['members']))throw new RuntimeException('Every season team must have at least one active member.');
            $rosters[$teamId]=[
                'team_name'=>(string)($team['name']??'Team'),
                'member_identities'=>array_values(array_map('strval',array_keys((array)$team['members']))),
                'frozen_at'=>now_iso(),
            ];
        }
        $rounds=udaan_competition_round_robin($teamIds);$matches=[];$start=new DateTimeImmutable($date,new DateTimeZone('Asia/Kolkata'));
        foreach($rounds as$roundIndex=>$pairs){
            $roundStart=$start->modify('+'.($roundIndex*7).' days');
            $roundEnd=$roundStart->modify('+6 days');
            foreach($pairs as$pair){
                $id=uuid_v4();
                $matches[$id]=[
                    'schema_version'=>1,'id'=>$id,'round'=>$roundIndex+1,
                    'team_a'=>$pair[0],'team_b'=>$pair[1],
                    'start_date'=>$roundStart->format('Y-m-d'),'end_date'=>$roundEnd->format('Y-m-d'),
                    'created_at'=>now_iso(),
                ];
            }
        }
        $season=[
            'schema_version'=>1,'id'=>uuid_v4(),'number'=>1,'division'=>(string)($league['division']??'foundation'),
            'start_date'=>$date,'end_date'=>$start->modify('+'.((count($rounds)*7)-1).' days')->format('Y-m-d'),
            'status'=>'active','created_at'=>now_iso(),'team_rosters'=>$rosters,'matches'=>$matches,
        ];
        $state['leagues'][$leagueId]['season']=$season;$state['leagues'][$leagueId]['updated_at']=now_iso();
        return udaan_competition_prune_state($state);
    });
    $store->appendPlayerEvent(udaan_player_event('competition.season_started',$player,['arena'=>'core','league_id'=>$leagueId,'season_id'=>$season['id'],'division'=>$season['division']]));
    return $season;
}

function udaan_competition_match_status(array $match,string $date): string {
    if($date<(string)($match['start_date']??''))return 'scheduled';
    if($date>(string)($match['end_date']??''))return 'completed';
    return 'live';
}

function udaan_competition_member_active_days(array $missionState,string $arena,string $from,string $through): int {
    $dates=[];
    foreach((array)($missionState['missions']??[]) as$m){
        if(!is_array($m)||($m['status']??'')!=='completed'||($m['arena']??'')!==$arena)continue;
        $date=(string)($m['scheduled_date']??'');
        if($date>=$from&&$date<=$through)$dates[$date]=true;
    }
    return min(3,count($dates));
}

function udaan_competition_roster_match_score(TempStore $store,array $memberIdentities,string $arena,string $from,string $through): array {
    $members=0;$participants=0;$totalContribution=0;$memberRows=[];
    foreach(array_values(array_unique(array_map('strval',$memberIdentities))) as$identity){
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))continue;
        $player=$store->getPlayer($identity);if(!is_array($player))continue;
        $members++;
        $activeDays=udaan_competition_member_active_days($store->getMissionState($identity),$arena,$from,$through);
        if($activeDays>0)$participants++;
        $totalContribution+=$activeDays;
        $memberRows[]=['nickname'=>(string)($player['nickname']??'Player'),'participated'=>$activeDays>0];
    }
    $normalized=$members?(int)round(($totalContribution/($members*3))*100):0;
    $participationRate=$members?(int)round(($participants/$members)*100):0;
    return [
        'score'=>$normalized,
        'participants'=>$participants,
        'members'=>$members,
        'participation_rate'=>$participationRate,
        'capped_active_days'=>$totalContribution,
        'member_rows'=>$memberRows,
    ];
}

function udaan_competition_team_match_score(TempStore $store,array $team,string $arena,string $from,string $through): array {
    return udaan_competition_roster_match_score($store,array_keys((array)($team['members']??[])),$arena,$from,$through);
}

function udaan_competition_match_result(TempStore $store,array $league,array $match,string $date): array {
    $arena=(string)($league['arena']??'learn');
    $rosters=(array)($league['season']['team_rosters']??[]);
    $rosterA=(array)($rosters[(string)$match['team_a']]['member_identities']??[]);
    $rosterB=(array)($rosters[(string)$match['team_b']]['member_identities']??[]);
    $a=udaan_competition_roster_match_score($store,$rosterA,$arena,(string)$match['start_date'],(string)$match['end_date']);
    $b=udaan_competition_roster_match_score($store,$rosterB,$arena,(string)$match['start_date'],(string)$match['end_date']);
    $status=udaan_competition_match_status($match,$date);
    $winner=null;
    if($status==='completed'&&$a['score']!==$b['score'])$winner=$a['score']>$b['score']?(string)$match['team_a']:(string)$match['team_b'];
    return ['match'=>$match,'status'=>$status,'team_a'=>$a,'team_b'=>$b,'winner_team_id'=>$winner,'draw'=>$status==='completed'&&$a['score']===$b['score']];
}

function udaan_competition_standings(TempStore $store,array $league,string $date): array {
    $season=$league['season']??null;if(!is_array($season))return [];
    $graph=$store->getSocialGraph();$table=[];
    foreach(array_keys((array)($league['teams']??[])) as$teamId){
        $team=$graph['teams'][$teamId]??null;
        $table[$teamId]=[
            'team_id'=>$teamId,'name'=>(string)($team['name']??'Team'),'played'=>0,'wins'=>0,'draws'=>0,'losses'=>0,
            'points'=>0,'score_for'=>0,'participation_total'=>0,'completed_matches'=>0,'scores'=>[],
        ];
    }
    foreach((array)($season['matches']??[]) as$match){
        if(!is_array($match)||udaan_competition_match_status($match,$date)!=='completed')continue;
        $result=udaan_competition_match_result($store,$league,$match,$date);
        $a=(string)$match['team_a'];$b=(string)$match['team_b'];if(!isset($table[$a],$table[$b]))continue;
        $table[$a]['played']++;$table[$b]['played']++;
        $table[$a]['completed_matches']++;$table[$b]['completed_matches']++;
        $table[$a]['score_for']+=(int)$result['team_a']['score'];$table[$b]['score_for']+=(int)$result['team_b']['score'];
        $table[$a]['participation_total']+=(int)$result['team_a']['participation_rate'];$table[$b]['participation_total']+=(int)$result['team_b']['participation_rate'];
        $table[$a]['scores'][]=(int)$result['team_a']['score'];$table[$b]['scores'][]=(int)$result['team_b']['score'];
        if(!empty($result['draw'])){
            $table[$a]['draws']++;$table[$b]['draws']++;$table[$a]['points']++;$table[$b]['points']++;
        }elseif($result['winner_team_id']===$a){
            $table[$a]['wins']++;$table[$b]['losses']++;$table[$a]['points']+=3;
        }else{
            $table[$b]['wins']++;$table[$a]['losses']++;$table[$b]['points']+=3;
        }
    }
    foreach($table as$id=>$row){
        $n=max(1,(int)$row['completed_matches']);
        $table[$id]['average_score']=$row['completed_matches']?(int)round($row['score_for']/$n):0;
        $table[$id]['average_participation']=$row['completed_matches']?(int)round($row['participation_total']/$n):0;
        $scores=$row['scores'];$table[$id]['improvement']=count($scores)>=2?end($scores)-reset($scores):0;
        $table[$id]['consistent_matches']=count(array_filter($scores,fn($s)=>$s>=50));
    }
    $rows=array_values($table);
    usort($rows,function($a,$b){
        return ($b['points']<=>$a['points'])?:($b['average_score']<=>$a['average_score'])?:strcasecmp($a['name'],$b['name']);
    });
    foreach($rows as$i=>$row)$rows[$i]['position']=$i+1;
    return $rows;
}

function udaan_competition_awards(array $standings): array {
    if(!$standings)return [];
    $eligible=array_values(array_filter($standings,fn($r)=>(int)($r['completed_matches']??0)>0));
    if(!$eligible)return [];
    $champion=$standings[0];
    $participation=$eligible;$consistency=$eligible;$improvement=$eligible;
    usort($participation,fn($a,$b)=>($b['average_participation']<=>$a['average_participation'])?:strcasecmp($a['name'],$b['name']));
    usort($consistency,fn($a,$b)=>($b['consistent_matches']<=>$a['consistent_matches'])?:($b['average_score']<=>$a['average_score'])?:strcasecmp($a['name'],$b['name']));
    usort($improvement,fn($a,$b)=>($b['improvement']<=>$a['improvement'])?:strcasecmp($a['name'],$b['name']));
    return [
        ['key'=>'season_champion','label'=>'Season Champion','team_id'=>$champion['team_id'],'team_name'=>$champion['name'],'value'=>$champion['points'],'unit'=>'points'],
        ['key'=>'participation','label'=>'Participation Award','team_id'=>$participation[0]['team_id'],'team_name'=>$participation[0]['name'],'value'=>$participation[0]['average_participation'],'unit'=>'% average participation'],
        ['key'=>'consistency','label'=>'Consistency Award','team_id'=>$consistency[0]['team_id'],'team_name'=>$consistency[0]['name'],'value'=>$consistency[0]['consistent_matches'],'unit'=>'matches at 50%+'],
        ['key'=>'improvement','label'=>'Improvement Award','team_id'=>$improvement[0]['team_id'],'team_name'=>$improvement[0]['name'],'value'=>$improvement[0]['improvement'],'unit'=>'score change'],
    ];
}

function udaan_competition_season_status(array $season,string $date): string {
    if($date<(string)($season['start_date']??''))return 'scheduled';
    if($date>(string)($season['end_date']??''))return 'completed';
    return 'active';
}
