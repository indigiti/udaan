<?php
require __DIR__.'/bootstrap.php';

$identity=udaan_player_session_identity();
$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){header('Location: '.app_url('player'));exit;}

$date=today_key();
$hasFit=in_array('fit',(array)($player['arenas']??[]),true);
$state=udaan_mission_ensure_daily($store,$identity,$player,$date);
$readinessState=$store->getReadinessState($identity);
$readiness=udaan_daily_readiness_for_date($readinessState,$date);
if($readiness)$state=udaan_mission_apply_readiness($store,$identity,$readiness);
$missions=udaan_missions_for_date($state,$date);
$fitMission=null;
foreach($missions as$m)if(($m['arena']??'')==='fit'&&($m['type']??'')==='fitness'){$fitMission=$m;break;}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$hasFit){http_response_code(409);$error='Add Fit to your Player Arenas before completing Fit sessions.';}
    elseif(!global_csrf_matches($_POST['csrf']??null)){http_response_code(403);$error='Your session expired. Refresh and try again.';}
    elseif(!is_array($fitMission)){http_response_code(409);$error='Today’s Fit Mission is unavailable.';}
    else{
        $rate=$store->rateLimit('fit-session',$identity,20,3600);
        if(!$rate['allowed']){rate_limit_retry_header($rate);http_response_code(429);$error='Too many Fit updates. Please try again later.';}
        else{
            try{
                $session=udaan_fit_validate_session($_POST,$readiness);
                udaan_mission_apply_status($store,$identity,$player,(string)$fitMission['id'],'completed',$session);
                header('Location: '.app_url('fit?saved=1'));exit;
            }catch(Throwable $e){http_response_code($e instanceof RuntimeException?409:400);$error=$e->getMessage();}
        }
    }
}

$state=$store->getMissionState($identity);
$summary14=udaan_fit_summary($state,14,$date);
$summary30=udaan_fit_summary($state,30,$date);
$activities=udaan_fit_activities();
$recommended=udaan_fit_recommended_activity($readiness);
$defaultMinutes=(($readiness['band']??'balanced')==='low')?5:10;
$fitMission=null;
foreach(udaan_missions_for_date($state,$date) as$m)if(($m['arena']??'')==='fit'&&($m['type']??'')==='fitness'){$fitMission=$m;break;}
$completed=is_array($fitMission)&&($fitMission['status']??'')==='completed';
$completedActivity=$completed?(string)($fitMission['result']['activity_label']??'Movement session'):'';
$completedMinutes=$completed?(int)($fitMission['result']['actual_minutes']??$fitMission['duration_minutes']??0):0;
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Udaan Fit</title><?=common_assets_head()?><style>
.fit-wrap{max-width:1120px;margin:0 auto;padding:24px 20px 72px}.fit-head{display:flex;justify-content:space-between;align-items:center;gap:16px}.fit-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.hero{margin-top:32px;display:grid;grid-template-columns:1.25fr .75fr;gap:20px}.panel{background:var(--panel,#fff);border:1px solid rgba(90,80,60,.14);border-radius:28px;padding:26px}.hero h1{font-size:clamp(42px,6vw,70px);line-height:.97;margin:6px 0 16px}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.stat strong{display:block;font-size:38px}.stat small{opacity:.66}.activities{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-top:20px}.activity{position:relative}.activity.recommended{outline:2px solid currentColor;outline-offset:2px}.activity h2{font-size:28px;margin:7px 0}.activity p{line-height:1.55}.tag{display:inline-flex;padding:7px 10px;border-radius:999px;border:1px solid rgba(90,80,60,.16);font-size:12px}.minutes{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.minutes label{padding:10px 13px;border:1px solid rgba(90,80,60,.18);border-radius:999px;cursor:pointer}.minutes input{position:absolute;opacity:0}.minutes label:has(input:checked){outline:2px solid currentColor;outline-offset:1px;font-weight:700}.safe{font-size:13px;line-height:1.55;opacity:.72}.notice{padding:13px 15px;border-radius:14px;background:rgba(60,110,80,.09);margin-top:14px}.error{padding:13px 15px;border-radius:14px;background:rgba(160,50,40,.09)}@media(max-width:760px){.hero,.activities,.stats{grid-template-columns:1fr}.panel{padding:20px}}
</style></head><body><main class="fit-wrap"><header class="fit-head"><a class="brand" href="<?=h(route_url('home'))?>"><span class="brand-mark">उ</span><span>Udaan Fit</span></a><div class="fit-actions"><a class="ghost" href="<?=h(app_url('today'))?>">Today</a><a class="ghost" href="<?=h(app_url('readiness'))?>">Readiness</a><a class="ghost" href="<?=h(app_url('progress'))?>">Progress</a><?=theme_toggle()?></div></header>

<section class="hero"><div class="panel"><p class="eyebrow">FIT · MOVEMENT FOR STUDY & LIFE</p><h1>Move a little. Return sharper.</h1><p class="lead">Udaan Fit v1 uses short, gentle movement sessions that fit around study. No calories, weight targets, body comparison or extreme exercise.</p><?php if(!$hasFit):?><div class="notice">Fit is not selected in your Player Arenas. <a href="<?=h(app_url('player'))?>">Add Fit to your profile →</a></div><?php elseif($completed):?><div class="notice"><strong>Today complete.</strong> <?=h($completedActivity)?> · <?=$completedMinutes?> min. More exercise is not required to protect a streak.</div><?php elseif(($_GET['saved']??'')==='1'):?><div class="notice">Movement recorded for today.</div><?php endif;?><?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?></div>
<aside class="panel"><p class="eyebrow">FIT CONSISTENCY</p><div class="stats"><div class="stat"><strong><?=$summary14['active_days']?></strong><small>active days · 14d</small></div><div class="stat"><strong><?=$summary14['minutes']?></strong><small>minutes · 14d</small></div><div class="stat"><strong><?=$summary30['active_days']?></strong><small>active days · 30d</small></div></div><?php if($readiness):?><p style="margin-top:20px"><strong><?=h((string)$readiness['label'])?></strong><br><span class="safe"><?=h((string)$readiness['summary'])?></span></p><?php else:?><p class="safe" style="margin-top:20px">No readiness check-in today. Udaan will use a balanced movement recommendation.</p><?php endif;?><p class="safe"><strong>Safety:</strong> stay in a comfortable range. Stop if you feel pain, dizziness, unusual shortness of breath or feel unwell. Follow a parent/guardian or clinician’s advice when relevant.</p></aside></section>

<?php if($hasFit&&!$completed&&is_array($fitMission)):?><form method="post"><input type="hidden" name="csrf" value="<?=h(global_csrf())?>"><section class="activities"><?php foreach($activities as$id=>$activity):$isRecommended=$id===$recommended;?><article class="panel activity <?=$isRecommended?'recommended':''?>"><span class="tag"><?=$isRecommended?'Recommended today':h(ucfirst((string)$activity['category']))?></span><h2><?=h((string)$activity['label'])?></h2><p><?=h((string)$activity['summary'])?></p><label class="tag"><input type="radio" name="activity_id" value="<?=h($id)?>" <?=$isRecommended?'checked':''?> required> Choose this session</label></article><?php endforeach;?></section><section class="panel" style="margin-top:20px"><p class="eyebrow">SESSION LENGTH</p><h2>Keep it short and sustainable.</h2><div class="minutes"><?php foreach(udaan_fit_allowed_minutes() as$minutes):$disabled=($readiness['band']??'balanced')==='low'&&$minutes>10;?><label style="<?=$disabled?'opacity:.42':''?>"><input type="radio" name="minutes" value="<?=$minutes?>" <?=$minutes===$defaultMinutes?'checked':''?> <?=$disabled?'disabled':''?> required><?=$minutes?> min</label><?php endforeach;?></div><button class="primary">Complete movement session →</button><p class="safe">Completion records the activity type and minutes in your private Mission history. Udaan does not estimate calories burned.</p></section></form><?php endif;?>
</main><?=common_theme_script()?></body></html>
