<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';
require_once $root.'/lib/Store.php';
require_once $root.'/lib/Social.php';
require_once $root.'/lib/Competition.php';
require_once $root.'/lib/Performance.php';
require_once $root.'/lib/DailyReadiness.php';
require_once $root.'/lib/Fit.php';
require_once $root.'/lib/Momentum.php';
require_once $root.'/lib/Mission.php';

$tmp=$root.'/data/test-competition-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');

$ids=[str_repeat('1',64),str_repeat('2',64),str_repeat('3',64)];
$players=[];
foreach($ids as$i=>$identity){
    $p=udaan_player_default($identity);
    $p['nickname']='Player'.($i+1);$p['age_group']='18_24';$p['stage']='college';
    $p['arenas']=['learn'];$p['onboarding_completed']=true;
    $store->putPlayer($identity,$p);$players[]=$p;
}

$teamA=udaan_social_create_team($store,$ids[0],$players[0],'Alpha Team');
$teamB=udaan_social_create_team($store,$ids[1],$players[1],'Beta Team');
$store->mutateSocialGraph(function(?array $current)use($teamA,$ids):array{
    $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
    $graph['teams'][$teamA['id']]['members'][$ids[2]]=['role'=>'member','joined_at'=>now_iso()];
    return udaan_social_prune_graph($graph);
});

$league=udaan_competition_create_league($store,$ids[0],$players[0],$teamA['id'],'Private Pilot','learn');
if(($league['division']??'')!=='foundation'||($league['visibility']??'')!=='private'){fwrite(STDERR,"League privacy/division defaults failed\n");exit(1);}

$invite=udaan_competition_create_invite($store,$ids[0],$players[0],$league['id']);
$joined=udaan_competition_join_league($store,$ids[1],$players[1],$teamB['id'],$invite['code']);
if(($joined['league_id']??'')!==$league['id']){fwrite(STDERR,"League team join failed\n");exit(1);}

$season=udaan_competition_start_season($store,$ids[0],$players[0],$league['id'],'2026-09-01');
if(count((array)($season['team_rosters'][$teamA['id']]['member_identities']??[]))!==2){fwrite(STDERR,"Season roster snapshot failed\n");exit(1);}
$store->mutateSocialGraph(function(?array $current)use($teamA,$ids):array{
    $graph=udaan_social_prune_graph(is_array($current)?$current:[]);
    unset($graph['teams'][$teamA['id']]['members'][$ids[2]]);
    return udaan_social_prune_graph($graph);
});
if(count((array)$season['matches'])!==1){fwrite(STDERR,"Two-team season must create one round-robin match\n");exit(1);}
$match=array_values($season['matches'])[0];
if(($match['start_date']??'')!=='2026-09-01'||($match['end_date']??'')!=='2026-09-07'){fwrite(STDERR,"Match dates mismatch\n");exit(1);}
if(udaan_competition_match_status($match,'2026-08-31')!=='scheduled'||udaan_competition_match_status($match,'2026-09-04')!=='live'||udaan_competition_match_status($match,'2026-09-08')!=='completed'){
    fwrite(STDERR,"Match lifecycle mismatch\n");exit(1);
}

$rounds=udaan_competition_round_robin(['A','B','C','D']);
if(count($rounds)!==3||array_sum(array_map('count',$rounds))!==6){fwrite(STDERR,"Round-robin scheduler mismatch\n");exit(1);}

$putMission=function(string $identity,string $id,string $date)use($store):void{
    $store->mutateMissionState($identity,function(?array $current)use($id,$date):array{
        $state=is_array($current)?array_replace(udaan_mission_state_default(),$current):udaan_mission_state_default();
        $state['missions'][$id]=[
            'schema_version'=>1,'id'=>$id,'player_id'=>'p','source_key'=>'test:'.$id,'arena'=>'learn','type'=>'learning',
            'title'=>'Test Learn','objective'=>'Test','duration_minutes'=>10,'difficulty'=>'standard','content_refs'=>[],
            'completion_rule'=>['mode'=>'self_report'],'status'=>'completed','scheduled_date'=>$date,
            'created_at'=>now_iso(),'updated_at'=>now_iso(),'completed_at'=>now_iso(),'result'=>['source'=>'self_report'],
        ];
        return udaan_mission_prune_state($state);
    });
};

$putMission($ids[0],'a1','2026-09-01');$putMission($ids[0],'a2','2026-09-02');$putMission($ids[0],'a3','2026-09-03');
$putMission($ids[1],'b1','2026-09-01');$putMission($ids[1],'b2','2026-09-02');
// Third member of Alpha deliberately has zero activity.

$state=$store->getCompetitionState();
$leagueNow=$state['leagues'][$league['id']];
$result=udaan_competition_match_result($store,$leagueNow,$match,'2026-09-08');
$aId=(string)$match['team_a'];$bId=(string)$match['team_b'];
$scores=[$aId=>$result['team_a']['score'],$bId=>$result['team_b']['score']];
if(($scores[$teamA['id']]??-1)!==50){fwrite(STDERR,"Frozen two-member roster normalization failed\n");exit(1);}
if(($scores[$teamB['id']]??-1)!==67){fwrite(STDERR,"One-member team normalization failed\n");exit(1);}
if(($result['winner_team_id']??'')!==$teamB['id']){fwrite(STDERR,"Normalized scoring did not determine expected winner\n");exit(1);}

$standings=udaan_competition_standings($store,$leagueNow,'2026-09-08');
if(count($standings)!==2||($standings[0]['team_id']??'')!==$teamB['id']||($standings[0]['points']??0)!==3){fwrite(STDERR,"Standings calculation failed\n");exit(1);}
$awards=udaan_competition_awards($standings);
$keys=array_column($awards,'key');
foreach(['season_champion','participation','consistency','improvement'] as$key)if(!in_array($key,$keys,true)){fwrite(STDERR,"Missing competition award: $key\n");exit(1);}

$competitionFile=$tmp.'/competition/state.json';
if(!is_file($competitionFile)){fwrite(STDERR,"Encrypted competition state file missing\n");exit(1);}
$raw=(string)file_get_contents($competitionFile);
if(str_contains($raw,'Private Pilot')||str_contains($raw,'Alpha Team')){fwrite(STDERR,"Competition state leaked plaintext\n");exit(1);}
if(!is_array(secure_unpack($raw))){fwrite(STDERR,"Competition state encryption/decode failed\n");exit(1);}

$events=[];
foreach(glob($tmp.'/events/*.jsonl')?:[] as$file)foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as$line){
    $decoded=secure_unpack($line);if(is_array($decoded))$events[]=$decoded;
}
$types=array_column($events,'event_type');
foreach(['competition.league_created','competition.league_invite_created','competition.team_joined_league','competition.season_started'] as$type){
    if(!in_array($type,$types,true)){fwrite(STDERR,"Competition event missing: $type\n");exit(1);}
}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "competition-v1-smoke: PASS\n";
