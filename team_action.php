<?php
require __DIR__.'/bootstrap.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');}
if(!global_csrf_matches($_POST['csrf']??null)){http_response_code(403);exit('Session expired.');}
$identity=udaan_player_session_identity();$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){http_response_code(401);exit('Player session unavailable.');}
if(!udaan_social_enabled_for_player($player)){http_response_code(403);exit('Social features are unavailable for under-13 Players in this release.');}

$limit=$store->rateLimit('social-team-action',$identity,60,3600);
if(!$limit['allowed']){rate_limit_retry_header($limit);http_response_code(429);exit('Too many team actions. Please retry later.');}

$teamId=(string)($_POST['team_id']??'');
if(!preg_match('/^[0-9a-f-]{36}$/i',$teamId)){http_response_code(400);exit('Invalid team.');}
$action=(string)($_POST['action']??'');$session=&udaan_session();
try{
    if($action==='create_invite'){
        $invite=udaan_social_create_team_invite($store,$identity,$player,$teamId);
        $session['social_flash']=['message'=>'One-time team invite created.','code'=>$invite['code']];
    }elseif($action==='leave'){
        udaan_social_leave_team($store,$identity,$player,$teamId);
        $session['social_flash']=['message'=>'You left the team.'];
        header('Location: '.app_url('connect'));exit;
    }elseif($action==='create_challenge'){
        $challenge=udaan_social_create_team_challenge($store,$identity,$player,$teamId,(string)($_POST['arena']??''),today_key());
        $session['social_flash']=['message'=>'Team participation challenge started through '.$challenge['end_date'].'.'];
    }else{throw new InvalidArgumentException('Unsupported team action.');}
}catch(Throwable $e){
    $session['social_flash']=['message'=>$e->getMessage()];
}
header('Location: '.app_url('team/'.rawurlencode($teamId)));exit;
