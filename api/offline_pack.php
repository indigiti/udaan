<?php
require dirname(__DIR__).'/bootstrap.php';
$roomId=require_room_id($_GET['room']??'');$pid=participant_id_for($roomId);$room=$store->get($roomId);
if(!$room||!$pid||empty($room['participants'][$pid]['verified_at']))json_response(['ok'=>false,'error'=>'Verified session required'],401);
$p=$room['participants'][$pid];$learningKey=(string)($p['learning_key']??'');$deviceHash=(string)($p['device_hash']??'');if($learningKey===''||$deviceHash==='')json_response(['ok'=>false,'error'=>'Device identity unavailable'],409);
$count=(int)($config['offline_reserve_cards']??1008);$cached=$store->getOfflineReserve($learningKey,$deviceHash);$bank=cards_by_id();$history=$store->getHistory($learningKey);$seen=is_array($history['seen_cards']??null)?$history['seen_cards']:[];$refs=[];
if(is_array($cached)){
    $cachedRefs=is_array($cached['card_refs']??null)?$cached['card_refs']:array_map(fn($id)=>['id'=>$id,'option_order'=>[]],is_array($cached['card_ids']??null)?$cached['card_ids']:[]);
    foreach($cachedRefs as$r){if(!is_array($r))continue;$id=(string)($r['id']??'');if($id!==''&&isset($bank[$id])&&!isset($seen[$id]))$refs[]=['id'=>$id,'option_order'=>is_array($r['option_order']??null)?$r['option_order']:[]];}
}
if(count($refs)<min($count,count($bank))){
    $exclude=[];foreach(($p['journey']??[])as$r)if(is_array($r)&&!empty($r['id']))$exclude[]=(string)$r['id'];
    $reserve=build_offline_reserve($learningKey,$count,$store,$exclude);
    $refs=array_values(array_map(fn($c)=>['id'=>(string)$c['id'],'option_order'=>array_values($c['option_order']??[])],$reserve));
    $cached=$store->cacheOfflineReserve($learningKey,$deviceHash,$refs,(int)($config['offline_reserve_ttl_seconds']??604800));
}
$cards=[];foreach(array_slice($refs,0,$count) as$r){$id=(string)($r['id']??'');if(!isset($bank[$id]))continue;$c=$bank[$id];if(is_array($r['option_order']??null))$c=apply_option_order($c,$r['option_order']);$cards[]=public_card_payload($c,true);}
$token=(string)($p['offline_sync_token']??offline_sync_token($learningKey,$deviceHash,time()+(int)($config['offline_reserve_ttl_seconds']??604800)));
json_response(['ok'=>true,'version'=>2,'reserve_id'=>$cached['reserve_id']??uuid_v4(),'count'=>count($cards),'capacity'=>$count,'cards'=>$cards,'sync_token'=>$token,'sync_url'=>route_url('offline_sync',$roomId),'expires_at'=>$cached['expires_at']??date(DATE_ATOM,time()+(int)($config['offline_reserve_ttl_seconds']??604800))]);
