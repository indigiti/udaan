<?php
require __DIR__.'/bootstrap.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');}
if(!global_csrf_matches($_POST['csrf']??null)){http_response_code(403);exit('Session expired.');}

$identity=udaan_player_session_identity();$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){http_response_code(401);exit('Player session unavailable.');}
if(!udaan_social_enabled_for_player($player)){http_response_code(403);exit('Social features are unavailable for under-13 Players in this release.');}

$limit=$store->rateLimit('social-connect',$identity,60,3600);
if(!$limit['allowed']){rate_limit_retry_header($limit);http_response_code(429);exit('Too many social actions. Please retry later.');}

$action=(string)($_POST['action']??'');$session=&udaan_session();
try{
    if($action==='create_friend_invite'){
        $invite=udaan_social_create_friend_invite($store,$identity,$player);
        $session['social_flash']=['message'=>'Friend invite created.','code'=>$invite['code']];
    }elseif($action==='accept_friend_invite'){
        udaan_social_accept_friend_invite($store,$identity,$player,(string)($_POST['code']??''));
        $session['social_flash']=['message'=>'Friend connected.'];
    }elseif($action==='remove_friend'){
        udaan_social_remove_friend($store,$identity,$player,(string)($_POST['relationship_id']??''));
        $session['social_flash']=['message'=>'Friend connection removed.'];
    }elseif($action==='create_team'){
        $team=udaan_social_create_team($store,$identity,$player,(string)($_POST['team_name']??''));
        $session['social_flash']=['message'=>'Team created.'];
        header('Location: '.app_url('team/'.rawurlencode((string)$team['id'])));exit;
    }elseif($action==='accept_team_invite'){
        $result=udaan_social_accept_team_invite($store,$identity,$player,(string)($_POST['code']??''));
        $session['social_flash']=['message'=>'You joined the team.'];
        header('Location: '.app_url('team/'.rawurlencode((string)$result['team_id'])));exit;
    }else{throw new InvalidArgumentException('Unsupported social action.');}
}catch(Throwable $e){
    $session['social_flash']=['message'=>$e->getMessage()];
}
header('Location: '.app_url('connect'));exit;
