<?php
require __DIR__.'/bootstrap.php';
$roomId=require_room_id($_GET['room']??'');
canonicalize_get_route('verify',$roomId);
$room=$store->get($roomId);
$pending=pending_for($roomId);
if(!$room||!is_array($pending)){header('Location: '.route_url('join',$roomId));exit;}

$error='';
$learningKey=(string)($pending['learning_key']??'');
$cacheToken=$learningKey!==''?client_cache_token($learningKey):'';
$deviceHash=(string)($pending['device_hash']??'');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $pending=pending_for($roomId);
    if(!is_array($pending)){header('Location: '.route_url('join',$roomId));exit;}
    $otp=preg_replace('/\D/','',(string)($_POST['otp']??''));
    $expires=(int)($pending['expires']??0);
    $expected=(string)($pending['otp']??'');
    $attempts=(int)($pending['attempts']??0);

    if(!preg_match('/^[0-9]{4}$/',$expected)){pending_remove($roomId);header('Location: '.route_url('join',$roomId));exit;}
    if($expires<time())$error='This demo code expired. Please join again.';
    elseif($attempts>=5){pending_remove($roomId);header('Location: '.route_url('join',$roomId));exit;}
    elseif(!hash_equals($expected,$otp)){
        $pending['attempts']=$attempts+1;pending_set($roomId,$pending);$error='That code does not match the screen code.';
    } else {
        $pid=(string)($pending['pid']??'');
        $learningKey=(string)($pending['learning_key']??'');
        if($pid===''||$learningKey===''){pending_remove($roomId);header('Location: '.route_url('join',$roomId));exit;}

        $cacheToken=client_cache_token($learningKey);
        $deviceHash=(string)($pending['device_hash']??'');
        if($deviceHash!=='')$store->cacheDevice($learningKey,$deviceHash);
        $latestRoom=$store->get($roomId);
        if(!$latestRoom){$error='This presentation room expired. Please scan the QR again.';} else {
            $length=normalize_journey_length($latestRoom['journey_length']??27);
            $localSeen=[];$localRaw=(string)($_POST['local_seen_json']??'');
            if($localRaw!==''){$decoded=json_decode($localRaw,true);if(is_array($decoded)){foreach(array_slice($decoded,0,1500) as$id)if(is_string($id)&&preg_match('/^[0-9a-f-]{36}$/i',$id))$localSeen[]=strtolower($id);}}

            // Same verified identity + same room restores the exact randomized cards, option order and prior answers.
            $cached=$store->getCachedSession($learningKey,$roomId);
            $refs=[];$answers=[];$score=10;$answeredCount=0;$completed=false;$freshCount=0;$reviewCount=0;$historyBefore=0;$restoredFromCache=false;$journeyFingerprint='';$builtCards=[];$kindCounts=[];
            if(is_array($cached)&&isset($cached['journey'])&&is_array($cached['journey'])){
                $bank=cards_by_id();
                foreach($cached['journey'] as$r)if(is_array($r)&&isset($r['id'])&&isset($bank[(string)$r['id']]))$refs[]=$r;
                if($refs){
                    $restoredFromCache=true;$answers=is_array($cached['answers']??null)?$cached['answers']:[];$score=max(10,(int)($cached['score']??10));$answeredCount=min(count($refs),(int)($cached['answered_count']??count($answers)));$completed=(bool)($cached['completed']??false);
                    foreach($refs as$r){if(!empty($r['review']))$reviewCount++;else$freshCount++;}
                    $historyBefore=max(0,count(($store->getHistory($learningKey)['seen_cards']??[]))-$freshCount);
                    $journeyFingerprint=journey_fingerprint_from_refs($refs);
                }
            }

            $updated=null;$collision=false;
            for($allocationAttempt=0;$allocationAttempt<6;$allocationAttempt++){
                if(!$refs){
                    $roomSnapshot=$store->get($roomId);if(!$roomSnapshot)break;
                    $used=room_journey_fingerprints($roomSnapshot,$pid);
                    $built=build_unseen_journey($learningKey,$length,$store,$localSeen,$used);
                    $builtCards=$built['cards'];$freshCount=$built['fresh_count'];$reviewCount=$built['review_count'];$historyBefore=$built['history_before'];$journeyFingerprint=(string)$built['journey_fingerprint'];$kindCounts=$built['kind_counts']??[];
                    $refs=array_map(fn($c)=>['id'=>$c['id'],'position'=>$c['position'],'points'=>$c['points'],'review'=>$c['review'],'option_order'=>array_values($c['option_order']??[])],$builtCards);
                }

                $collision=false;
                $updated=$store->mutate($roomId,function($r)use($pid,$refs,$answers,$score,$answeredCount,$completed,$freshCount,$reviewCount,$historyBefore,$cacheToken,$deviceHash,$journeyFingerprint,&$collision){
                    $used=room_journey_fingerprints($r,$pid);
                    if(isset($used[$journeyFingerprint])){$collision=true;return $r;}
                    if(isset($r['participants'][$pid])&&is_array($r['participants'][$pid])){
                        $p=&$r['participants'][$pid];$p['verified_at']=now_iso();$p['score']=$score;$p['stage']=$completed?'complete':($answeredCount>=count($refs)?'complete-ready':'journey');$p['journey']=$refs;$p['journey_fingerprint']=$journeyFingerprint;$p['journey_total']=count($refs);$p['answers']=$answers;$p['answered_count']=$answeredCount;$p['completed']=$completed;$p['fresh_count']=$freshCount;$p['review_count']=$reviewCount;$p['history_before']=$historyBefore;$p['cache_token']=$cacheToken;$p['device_hash']=$deviceHash;$p['offline_sync_token']=offline_sync_token((string)$p['learning_key'],$deviceHash,time()+604800);
                    }
                    return $r;
                });
                if(!$updated)break;
                if(!$collision)break;
                // A concurrent learner claimed the same sequence between selection and mutation. Build another sequence.
                if($restoredFromCache){$restoredFromCache=false;$answers=[];$score=10;$answeredCount=0;$completed=false;}
                $refs=[];$builtCards=[];$journeyFingerprint='';
            }

            if(!$updated)$error='This presentation room expired. Please scan the QR again.';
            elseif($collision)$error='A unique learning sequence could not be allocated. Please tap Verify again.';
            else {
                if(!$restoredFromCache){
                    if($builtCards)$store->markJourneySeen($learningKey,$builtCards);
                    $store->cacheJourneySession($learningKey,$roomId,$refs,[],10);
                }
                participant_set($roomId,$pid);
                $store->appendSyncEvent([
                    'event_type'=>'session_verified','user_hash'=>$learningKey,'room_id'=>$roomId,'journey_total'=>count($refs),
                    'journey_card_ids'=>array_values(array_map(fn($r)=>(string)($r['id']??''),$refs)),
                    'journey_fingerprint'=>$journeyFingerprint,'journey_kind_counts'=>$kindCounts,'max_action_cards'=>1,
                    'restored_from_cache'=>$restoredFromCache,'device_hash'=>$deviceHash
                ]);
                pending_remove($roomId);csrf_for($roomId);session_regenerate_id(true);
                header('Location: '.($completed||$answeredCount>=count($refs)?route_url('complete',$roomId):route_url('journey',$roomId)));exit;
            }
        }
    }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify · Udaan</title><?=common_assets_head()?></head><body><div class="floating-theme"><?=theme_toggle()?></div><div class="join-wrap"><section class="join-art"><a class="brand" href="<?=h(route_url('home'))?>"><span class="brand-mark">उ</span><span>Udaan</span></a><div class="join-copy"><p class="eyebrow">DEMO VERIFICATION</p><h1>One code. Then your unique journey is restored or built.</h1><p>Each learner receives a room-unique Q&amp;A order and independently shuffled choices. Refreshing or reopening restores the exact same sequence for that learner.</p></div><div class="room-code"><?=h((string)($pending['phone_mask']??'••••'))?></div></section><section class="join-form-side"><form class="join-card" method="post" id="verify-form" data-progress-submit data-progress-label="Verifying & preparing your journey" data-cache-token="<?=h($cacheToken)?>"><p class="eyebrow">SCREEN OTP</p><h2>Verify your session</h2><p class="lead">Enter the four digits shown below.</p><div class="otp-hero"><span class="otp-badge">DEMO CODE</span><div class="otp-code"><?=h((string)($pending['otp']??''))?></div><p class="small-note">Expires in 5 minutes · No message is sent</p></div><?php if($error):?><p class="form-error"><?=h($error)?></p><?php endif;?><div class="field"><label>4-DIGIT CODE</label><input class="otp-input" name="otp" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autofocus autocomplete="one-time-code"></div><input type="hidden" name="local_seen_json" id="local-seen-json" value="[]"><button class="primary">Verify &amp; open my journey →</button><p class="small-note">This device contributes only previously seen card GUIDs. No WhatsApp number is stored in browser cache.</p></form></section></div>
<script src="<?=h(app_url('assets/js/vault.js?v=0.6.1'))?>"></script><script>(async function(){try{var f=document.getElementById('verify-form'),t=f&&f.dataset.cacheToken;if(!t||!window.UdaanVault)return;await UdaanVault.ready();var d=await UdaanVault.get('seen:'+t),ids=d&&Array.isArray(d.ids)?d.ids.slice(-1500):[];document.getElementById('local-seen-json').value=JSON.stringify(ids)}catch(e){}})();</script><?=common_theme_script()?></body></html>
