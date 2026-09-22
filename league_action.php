<?php
require __DIR__.'/bootstrap.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');}
if(!global_csrf_matches($_POST['csrf']??null)){http_response_code(403);exit('Session expired.');}

$identity=udaan_player_session_identity();$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){http_response_code(401);exit('Player session unavailable.');}
if(!udaan_social_enabled_for_player($player)){http_response_code(403);exit('Competition features are unavailable for under-13 Players in this release.');}

$limit=$store->rateLimit('competition-action',$identity,60,3600);
if(!$limit['allowed']){rate_limit_retry_header($limit);http_response_code(429);exit('Too many competition actions. Please retry later.');}

$action=(string)($_POST['action']??'');$session=&udaan_session();
try{
    if($action==='create_league'){
        $league=udaan_competition_create_league(
            $store,$identity,$player,
            (string)($_POST['team_id']??''),
            (string)($_POST['league_name']??''),
            (string)($_POST['arena']??'')
        );
        $session['competition_flash']=['message'=>'Private league created. Invite other team owners before starting the season.'];
        header('Location: '.app_url('league/'.rawurlencode((string)$league['id'])));exit;
    }
    if($action==='join_league'){
        $joined=udaan_competition_join_league(
            $store,$identity,$player,
            (string)($_POST['team_id']??''),
            (string)($_POST['code']??'')
        );
        $session['competition_flash']=['message'=>'Your team joined the league.'];
        header('Location: '.app_url('league/'.rawurlencode((string)$joined['league_id'])));exit;
    }

    $leagueId=(string)($_POST['league_id']??'');
    if(!preg_match('/^[0-9a-f-]{36}$/i',$leagueId))throw new InvalidArgumentException('Invalid league.');

    if($action==='create_invite'){
        $invite=udaan_competition_create_invite($store,$identity,$player,$leagueId);
        $session['competition_flash']=['message'=>'One-time league team invite created.','code'=>$invite['code']];
    }elseif($action==='start_season'){
        $season=udaan_competition_start_season($store,$identity,$player,$leagueId,today_key());
        $session['competition_flash']=['message'=>'Season started through '.$season['end_date'].'.'];
    }else{
        throw new InvalidArgumentException('Unsupported league action.');
    }
    header('Location: '.app_url('league/'.rawurlencode($leagueId)));exit;
}catch(Throwable $e){
    $session['competition_flash']=['message'=>$e->getMessage()];
    header('Location: '.app_url('leagues'));exit;
}
