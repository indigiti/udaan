<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST only');}
if(!global_csrf_matches($_POST['csrf']??null)){http_response_code(403);exit('Session token mismatch');}

$identity=udaan_player_session_identity();
$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){header('Location: '.app_url('player'));exit;}

$missionId=strtolower(trim((string)($_GET['mission']??'')));
if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$missionId)){http_response_code(400);exit('Invalid mission');}
$action=(string)($_GET['action']??'');
if(!in_array($action,['complete','skip'],true)){http_response_code(400);exit('Invalid action');}

$rate=$store->rateLimit('mission-action',request_fingerprint('mission-action'),120,3600);
if(!$rate['allowed']){rate_limit_retry_header($rate);http_response_code(429);exit('Too many mission updates. Please retry shortly.');}

try{
    udaan_mission_apply_status($store,$identity,$player,$missionId,$action==='complete'?'completed':'skipped');
    header('Location: '.app_url('today').'?mission='.$action);exit;
}catch(Throwable $e){
    http_response_code($e instanceof RuntimeException?409:400);
    exit(h($e->getMessage()));
}
