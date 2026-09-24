<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Store.php';

function fail_state(string $message): never {fwrite(STDERR,$message."\n");exit(1);}
function valid_answer_for(array $card): string {
    $kind=(string)($card['kind']??'');
    if($kind==='reveal')return 'revealed';
    foreach(($card['options']??[]) as $option)if(is_array($option)&&isset($option['id']))return (string)$option['id'];
    fail_state('No valid answer available for test card');
}

$tmp=$root.'/data/test-state-offline-'.bin2hex(random_bytes(4));
@mkdir($tmp,0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>3600,'redis'=>['enabled'=>false]];
$store=new TempStore($config,$tmp.'/rooms');
$identity=str_repeat('a',64);$device=str_repeat('b',64);

$cards=array_values(cards_by_id());
if(count($cards)<2)fail_state('Need at least two cards for offline sync smoke test');
$cardA=$cards[0];$cardB=$cards[1];
$idA=(string)$cardA['id'];$idB=(string)$cardB['id'];

$store->mutateUserCache($identity,function(array $c):array{$c['probe_a']='A';return $c;});
$store->mutateUserCache($identity,function(array $c):array{$c['probe_b']='B';return $c;});
$cache=$store->getUserCache($identity);
if(($cache['probe_a']??null)!=='A'||($cache['probe_b']??null)!=='B')fail_state('Atomic user-cache mutation lost an existing field');

$room=uuid_v4();
$store->cacheJourneySession($identity,$room,[['id'=>$idB,'position'=>1]],[],10);
$reserve=$store->cacheOfflineReserve($identity,$device,[['id'=>$idA,'option_order'=>[]]],3600);
$reserveId=(string)($reserve['reserve_id']??'');
if($reserveId==='')fail_state('Offline reserve id missing');

$eventId=uuid_v4();
$event=['event_id'=>$eventId,'card_id'=>$idA,'answer'=>valid_answer_for($cardA),'source'=>'offline_reserve','occurred_at'=>now_iso()];
$wrong=$store->syncOfflineEvents($identity,$device,[$event],uuid_v4());
if(!in_array($eventId,$wrong['rejected']??[],true))fail_state('Wrong reserve id was accepted');

$outsideId=uuid_v4();
$outside=['event_id'=>$outsideId,'card_id'=>$idB,'answer'=>valid_answer_for($cardB),'source'=>'offline_reserve','occurred_at'=>now_iso()];
$outsideResult=$store->syncOfflineEvents($identity,$device,[$outside],$reserveId);
if(!in_array($outsideId,$outsideResult['rejected']??[],true))fail_state('Card outside issued reserve was accepted');

$accepted=$store->syncOfflineEvents($identity,$device,[$event],$reserveId);
if(!in_array($eventId,$accepted['accepted']??[],true))fail_state('Valid reserve event was not accepted');

$duplicate=$store->syncOfflineEvents($identity,$device,[$event],$reserveId);
if(!in_array($eventId,$duplicate['duplicates']??[],true))fail_state('Duplicate offline event was not rejected atomically');

$journeyId=uuid_v4();
$journey=['event_id'=>$journeyId,'card_id'=>$idB,'answer'=>valid_answer_for($cardB),'source'=>'journey_offline','room_id'=>$room,'occurred_at'=>now_iso()];
$journeyResult=$store->syncOfflineEvents($identity,$device,[$journey],'');
if(!in_array($journeyId,$journeyResult['accepted']??[],true))fail_state('Valid cached journey event was not accepted');

$wrongRoomId=uuid_v4();
$wrongRoom=['event_id'=>$wrongRoomId,'card_id'=>$idB,'answer'=>valid_answer_for($cardB),'source'=>'journey_offline','room_id'=>uuid_v4(),'occurred_at'=>now_iso()];
$wrongRoomResult=$store->syncOfflineEvents($identity,$device,[$wrongRoom],'');
if(!in_array($wrongRoomId,$wrongRoomResult['rejected']??[],true))fail_state('Journey event for uncached room was accepted');

$history=$store->getHistory($identity);
if(!isset($history['seen_cards'][$idA])||!isset($history['seen_cards'][$idB]))fail_state('Accepted offline cards were not recorded in history');

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "state-offline-sync-smoke: PASS\n";
