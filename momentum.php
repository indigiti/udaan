<?php
require __DIR__.'/bootstrap.php';

$identity=udaan_player_session_identity();
$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){header('Location: '.app_url('player'));exit;}

$date=today_key();$error='';$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!global_csrf_matches($_POST['csrf']??null))$error='Your session expired. Refresh and try again.';
    else{
        $rate=$store->rateLimit('momentum-settings',$identity,20,3600);
        if(!$rate['allowed']){rate_limit_retry_header($rate);$error='Too many Momentum updates. Please retry later.';}
        else{
            try{
                $settings=udaan_momentum_normalize_settings($_POST,$player);
                $player=$store->mutatePlayer($identity,function(?array $current)use($settings):array{
                    if(!is_array($current))throw new RuntimeException('Player profile is unavailable.');
                    $current['momentum']=$settings;$current['updated_at']=now_iso();return $current;
                });
                $store->appendPlayerEvent(udaan_player_event('momentum.settings_updated',$player,[
                    'arena'=>'core','weekly_training_days'=>$settings['weekly_training_days'],'habits'=>$settings['habits'],
                ]));
                $saved=true;
            }catch(Throwable $e){$error=$e->getMessage();}
        }
    }
}

$state=udaan_mission_ensure_daily($store,$identity,$player,$date);
$summary=udaan_momentum_summary($state,$player,$date);
$settings=udaan_momentum_defaults($player);
$arenas=udaan_player_arenas();
$earned=array_values(array_filter($summary['milestones'],fn($m)=>!empty($m['earned'])));
$shareAllowed=!empty($player['privacy']['share_achievements']);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Momentum · Udaan</title><?=common_assets_head()?><style>
.m-wrap{max-width:1120px;margin:0 auto;padding:24px 20px 72px}.m-head{display:flex;justify-content:space-between;align-items:center;gap:16px}.m-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.hero,.two{display:grid;grid-template-columns:1.2fr .8fr;gap:20px;margin-top:24px}.panel{background:var(--panel,#fff);border:1px solid rgba(90,80,60,.14);border-radius:28px;padding:26px}.hero h1{font-size:clamp(42px,6vw,70px);line-height:.97;margin:6px 0 16px}.ring{font-size:54px;font-weight:700;line-height:1}.bar{height:10px;border-radius:999px;background:rgba(90,80,60,.12);overflow:hidden;margin:14px 0}.bar>span{display:block;height:100%;background:currentColor}.habit-grid,.milestone-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-top:18px}.habit,.milestone{border:1px solid rgba(90,80,60,.13);border-radius:18px;padding:16px}.habit strong,.milestone strong{display:block;font-size:22px}.days{display:flex;gap:5px;margin-top:12px;flex-wrap:wrap}.dot{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;border:1px solid rgba(90,80,60,.15);font-size:11px}.dot.on{background:currentColor;color:var(--panel,#fff)}.notice{padding:14px 16px;border-radius:16px;background:rgba(56,114,88,.09);margin-top:14px}.warn{background:rgba(174,112,43,.09)}.settings{display:grid;grid-template-columns:1fr 1fr;gap:16px}.checks{display:flex;gap:8px;flex-wrap:wrap}.checks label{border:1px solid rgba(90,80,60,.16);border-radius:999px;padding:9px 12px}.checks input{width:auto}.small{font-size:13px;opacity:.7;line-height:1.5}.share-btn{margin-top:12px}.earned{background:rgba(56,114,88,.06)}@media(max-width:760px){.hero,.two,.settings,.habit-grid,.milestone-grid{grid-template-columns:1fr}.panel{padding:20px}}
</style></head><body><main class="m-wrap"><header class="m-head"><a class="brand" href="<?=h(route_url('home'))?>"><span class="brand-mark">उ</span><span>Momentum</span></a><div class="m-actions"><a class="ghost" href="<?=h(app_url('coach'))?>">Coach</a><a class="ghost" href="<?=h(app_url('today'))?>">Today</a><a class="ghost" href="<?=h(app_url('fit'))?>">Fit</a><a class="ghost" href="<?=h(app_url('progress'))?>">Progress</a><?=theme_toggle()?></div></header>

<section class="hero"><div class="panel"><p class="eyebrow">WEEKLY MOMENTUM</p><h1>Consistency without punishment.</h1><p class="lead">Missing a day does not erase your work. Udaan measures meaningful training days, helps you restart, and recognizes real milestones.</p><div class="bar"><span style="width:<?=$summary['goal_progress']?>%"></span></div><div class="ring"><?=$summary['week_active_days']?> / <?=$summary['weekly_goal']?></div><p>training days toward this week’s goal</p><?php if($summary['goal_met']):?><div class="notice"><strong>Weekly goal reached.</strong> Extra activity is optional; you do not need to protect a streak.</div><?php elseif($summary['comeback_due']):?><div class="notice warn"><strong>Restart today.</strong> You’ve had <?=intval($summary['inactive_days'])?> quiet days. A short Comeback Mission is waiting on Today—no catch-up marathon.</div><?php endif;?></div>
<aside class="panel"><p class="eyebrow">THIS WEEK</p><div class="ring"><?=$summary['week_completed']?></div><p>missions completed · <?=intval($summary['week_minutes'])?> minutes</p><?php if(is_array($summary['next_milestone'])):?><h2>Next milestone</h2><strong><?=h((string)$summary['next_milestone']['label'])?></strong><div class="bar"><span style="width:<?=intval($summary['next_milestone']['progress'])?>%"></span></div><p class="small"><?=intval($summary['next_milestone']['value'])?> / <?=intval($summary['next_milestone']['threshold'])?> <?=h((string)$summary['next_milestone']['unit'])?></p><?php else:?><h2>Core milestones complete</h2><p class="small">Keep training for your goals, not for an endless badge ladder.</p><?php endif;?></aside></section>

<section class="panel" style="margin-top:20px"><p class="eyebrow">HABIT ENGINE</p><h2>Habits are real Arena activity.</h2><p class="small">A habit day counts only when a Mission in that Arena is completed.</p><div class="habit-grid"><?php foreach($summary['habits'] as$arena=>$habit):?><article class="habit"><strong><?=h($arenas[$arena]??ucfirst($arena))?></strong><span><?=intval($habit['days'])?> days · <?=intval($habit['minutes'])?> min</span><div class="days"><?php [$wf,$wt]=udaan_momentum_week_bounds($date);for($d=$wf;$d<=$wt;$d=(new DateTimeImmutable($d,new DateTimeZone('Asia/Kolkata')))->modify('+1 day')->format('Y-m-d')):?><span class="dot <?=in_array($d,$habit['dates'],true)?'on':''?>"><?=h(date('D',strtotime($d))[0])?></span><?php endfor;?></div></article><?php endforeach;?></div></section>

<section class="two"><div class="panel"><p class="eyebrow">MILESTONES</p><h2>Progress you earned.</h2><div class="milestone-grid"><?php foreach($summary['milestones'] as$m):?><article class="milestone <?=!empty($m['earned'])?'earned':''?>"><strong><?=!empty($m['earned'])?'✓ ':''?><?=h((string)$m['label'])?></strong><p class="small"><?=intval($m['value'])?> / <?=intval($m['threshold'])?> <?=h((string)$m['unit'])?></p><div class="bar"><span style="width:<?=intval($m['progress'])?>%"></span></div><?php if(!empty($m['earned'])&&$shareAllowed):?><button type="button" class="ghost share-btn" data-achievement-share data-share-text="<?=h(udaan_momentum_share_text($m,$player))?>">Share achievement →</button><?php endif;?></article><?php endforeach;?></div><?php if(!$shareAllowed):?><p class="small">Achievement sharing is off. You can enable explicit share-card generation in Player settings; nothing is posted automatically.</p><?php endif;?></div>
<div class="panel"><p class="eyebrow">SET YOUR RHYTHM</p><h2>Choose a realistic week.</h2><?php if($saved):?><div class="notice">Momentum settings saved.</div><?php endif;?><?php if($error):?><p class="notice warn"><?=h($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(global_csrf())?>"><div class="settings"><div><label>TRAINING DAYS / WEEK</label><select name="weekly_training_days"><?php for($n=2;$n<=7;$n++):?><option value="<?=$n?>" <?=$settings['weekly_training_days']===$n?'selected':''?>><?=$n?> days</option><?php endfor;?></select></div><div><label>HABITS TO WATCH</label><div class="checks"><?php foreach($arenas as$key=>$label):?><label><input type="checkbox" name="habits[]" value="<?=h($key)?>" <?=in_array($key,$settings['habits'],true)?'checked':''?>> <?=h($label)?></label><?php endforeach;?></div></div></div><button class="primary" style="margin-top:18px">Save weekly rhythm →</button></form><p class="small">There is no “streak lost” state. If life interrupts your routine, Udaan switches to comeback mode instead of deleting your progress.</p></div></section>
</main><script src="<?=h(app_url('assets/js/share-card.js?v=0.12.0'))?>" defer></script><?=common_theme_script()?></body></html>
