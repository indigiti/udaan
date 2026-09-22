<?php
require __DIR__.'/bootstrap.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');}
if(!global_csrf_matches($_POST['csrf']??null)){http_response_code(403);exit('Session expired.');}

$identity=udaan_player_session_identity();
$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){http_response_code(401);exit('Player session unavailable.');}

$rate=$store->rateLimit('coach-action',$identity,60,3600);
if(!$rate['allowed']){rate_limit_retry_header($rate);http_response_code(429);exit('Too many Coach actions.');}

$id=trim((string)($_POST['recommendation_id']??''));
if(!preg_match('/^[a-f0-9]{20}$/',$id)){http_response_code(400);exit('Invalid recommendation.');}

$date=today_key();
$state=udaan_mission_ensure_daily($store,$identity,$player,$date);
$readinessState=$store->getReadinessState($identity);
$readiness=udaan_daily_readiness_for_date($readinessState,$date);
if($readiness)$state=udaan_mission_apply_readiness($store,$identity,$readiness);
$coach=udaan_coach_generate($state,$player,$readinessState,$date);
$recommendation=udaan_coach_find_recommendation($coach,$id);
if(!is_array($recommendation)){http_response_code(409);exit('This Coach recommendation is no longer current. Refresh Coach.');}

udaan_coach_record_accepted($store,$player,$recommendation);
header('Location: '.udaan_coach_action_url((string)($recommendation['action_key']??'today')));exit;
