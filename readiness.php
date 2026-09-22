<?php
require __DIR__.'/bootstrap.php';

$identity=udaan_player_session_identity();
$player=$identity?$store->getPlayer($identity):null;
if(!$identity||!is_array($player)||empty($player['onboarding_completed'])){header('Location: '.app_url('player'));exit;}

$date=today_key();
$error='';
$dimensions=udaan_daily_readiness_dimensions();
$currentState=$store->getReadinessState($identity);
$current=udaan_daily_readiness_for_date($currentState,$date);

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!global_csrf_matches($_POST['csrf']??null))$error='Your session expired. Refresh and try again.';
    else{
        $limit=$store->rateLimit('daily-readiness',$identity,20,3600);
        if(!$limit['allowed']){$error='Too many readiness updates. Please try again later.';rate_limit_retry_header($limit);}
        else{
            try{
                $entry=udaan_daily_readiness_entry($_POST,$date);
                $state=$store->mutateReadinessState($identity,function(?array $existing)use($entry,$date):array{
                    $next=is_array($existing)?array_replace(udaan_daily_readiness_state_default(),$existing):udaan_daily_readiness_state_default();
                    if(!isset($next['entries'])||!is_array($next['entries']))$next['entries']=[];
                    $next['entries'][$date]=$entry;
                    return udaan_daily_readiness_prune($next);
                });
                udaan_mission_ensure_daily($store,$identity,$player,$date);
                udaan_mission_apply_readiness($store,$identity,$entry);
                $store->appendPlayerEvent(udaan_player_event('readiness.submitted',$player,[
                    'arena'=>'core',
                    'date'=>$date,
                    'band'=>$entry['band'],
                    'recommended_intensity'=>$entry['recommended_intensity'],
                ]));
                header('Location: '.app_url('today?readiness=saved'));exit;
            }catch(InvalidArgumentException|RuntimeException $e){$error=$e->getMessage();}
        }
    }
}

$history=udaan_daily_readiness_history($currentState,7);
$values=is_array($current['values']??null)?$current['values']:[];
function readiness_band_class(string $band): string {return in_array($band,['low','balanced','high'],true)?$band:'balanced';}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Daily Readiness · Udaan</title><?=common_assets_head()?><style>
.ready-wrap{max-width:980px;margin:0 auto;padding:24px 20px 72px}.ready-head{display:flex;justify-content:space-between;align-items:center;gap:16px}.ready-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.ready-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:20px;margin-top:32px}.panel{background:var(--panel,#fff);border:1px solid rgba(90,80,60,.14);border-radius:28px;padding:26px}.panel h1{font-size:clamp(40px,6vw,68px);line-height:.98;margin:6px 0 14px}.dimension{padding:20px 0;border-bottom:1px solid rgba(90,80,60,.12)}.dimension:last-child{border-bottom:0}.dimension-head{display:flex;justify-content:space-between;gap:16px;align-items:baseline}.scale{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:12px}.scale label{display:flex;align-items:center;justify-content:center;min-height:48px;border:1px solid rgba(90,80,60,.18);border-radius:14px;cursor:pointer}.scale input{position:absolute;opacity:0;pointer-events:none}.scale label:has(input:checked){outline:2px solid currentColor;outline-offset:1px;font-weight:700}.scale-ends{display:flex;justify-content:space-between;gap:16px;font-size:12px;opacity:.62;margin-top:6px}.submit-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:22px}.error{padding:12px 14px;border-radius:14px;background:rgba(160,50,40,.09)}.band{padding:16px;border-radius:18px;margin:12px 0}.band.low{background:rgba(160,90,50,.10)}.band.balanced{background:rgba(70,100,150,.09)}.band.high{background:rgba(50,120,80,.10)}.history-row{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid rgba(90,80,60,.10)}.privacy{font-size:13px;line-height:1.55;opacity:.72}@media(max-width:760px){.ready-grid{grid-template-columns:1fr}.scale{gap:6px}.panel{padding:20px}}
</style></head><body><main class="ready-wrap"><header class="ready-head"><a class="brand" href="<?=h(route_url('home'))?>"><span class="brand-mark">उ</span><span>Udaan</span></a><div class="ready-actions"><a class="ghost" href="<?=h(app_url('coach'))?>">Coach</a><a class="ghost" href="<?=h(app_url('today'))?>">Today</a><a class="ghost" href="<?=h(app_url('progress'))?>">Progress</a><?=theme_toggle()?></div></header>
<div class="ready-grid"><section class="panel"><p class="eyebrow">DAILY READINESS · PRIVATE</p><h1>How ready do you feel today?</h1><p class="lead">Five quick self-reports help Udaan suggest a lighter, standard or challenge-ready day. This is not a medical assessment and does not change your academic rank.</p><?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(global_csrf())?>"><?php foreach($dimensions as$key=>$meta):$selected=(int)($values[$key]??0);?><div class="dimension"><div class="dimension-head"><strong><?=h($meta['label'])?></strong><small>1–5</small></div><div class="scale"><?php for($n=1;$n<=5;$n++):?><label><input type="radio" name="<?=h($key)?>" value="<?=$n?>" <?=$selected===$n?'checked':''?> required><span><?=$n?></span></label><?php endfor;?></div><div class="scale-ends"><span><?=h($meta['low'])?></span><span><?=h($meta['high'])?></span></div></div><?php endforeach;?><div class="submit-row"><button class="primary">Save today’s check-in →</button><span class="privacy">You can update today’s answers later.</span></div></form></section>
<aside class="panel"><p class="eyebrow">YOUR SIGNAL</p><?php if($current):?><div class="band <?=h(readiness_band_class((string)$current['band']))?>"><strong><?=h((string)$current['label'])?></strong><p><?=h((string)$current['summary'])?></p><small><?=h(str_replace('_',' ',(string)$current['recommended_intensity']))?></small></div><?php else:?><p class="lead">No readiness check-in yet today.</p><?php endif;?>
<p class="privacy"><strong>Private by design.</strong> Raw Sleep, Energy, Stress, Focus and Body answers stay in encrypted readiness storage. Admin surfaces do not expose individual responses. The Event Ledger records only the readiness band and recommended intensity.</p>
<?php if($history):?><p class="eyebrow" style="margin-top:28px">RECENT CHECK-INS</p><?php foreach($history as$row):?><div class="history-row"><span><?=h((string)$row['date'])?></span><strong><?=h((string)$row['label'])?></strong></div><?php endforeach;?><?php endif;?></aside></div></main><?=common_theme_script()?></body></html>
