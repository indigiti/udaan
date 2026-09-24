<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';
require_once $root.'/lib/Store.php';
require_once $root.'/lib/Social.php';

$tmp=$root.'/data/test-social-'.bin2hex(random_bytes(4));
@mkdir($tmp.'/rooms',0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false,'required'=>false]];
$store=new TempStore($config,$tmp.'/rooms');

$ida=str_repeat('a',64);$idb=str_repeat('b',64);$idc=str_repeat('c',64);
$a=udaan_player_default($ida);$a['nickname']='Asha';$a['age_group']='18_24';$a['onboarding_completed']=true;$a['arenas']=['learn','fit'];
$b=udaan_player_default($idb);$b['nickname']='Bharat';$b['age_group']='16_17';$b['onboarding_completed']=true;$b['arenas']=['learn'];
$c=udaan_player_default($idc);$c['nickname']='Child';$c['age_group']='u13';$c['onboarding_completed']=true;
$store->putPlayer($ida,$a);$store->putPlayer($idb,$b);$store->putPlayer($idc,$c);

if(!udaan_social_enabled_for_player($a)||!udaan_social_enabled_for_player($b)){fwrite(STDERR,"Eligible Players were blocked from Social v1\n");exit(1);}
if(udaan_social_enabled_for_player($c)){fwrite(STDERR,"Under-13 Player was incorrectly enabled for Social v1\n");exit(1);}

$invite=udaan_social_create_friend_invite($store,$ida,$a);
if(!preg_match('/^[A-F0-9]{16}$/',$invite['code']??'')){fwrite(STDERR,"Friend invite strength/format mismatch\n");exit(1);}
$socialMarker=$tmp.'/social/.schema-v2';
$inviteFiles=glob($tmp.'/social/invite/*.json')?:[];
if(!is_file($socialMarker)||count($inviteFiles)!==1){fwrite(STDERR,"Partitioned encrypted Social state missing\n");exit(1);}
$raw=(string)file_get_contents($inviteFiles[0]);
if(str_contains($raw,(string)$invite['code'])||str_contains($raw,'Asha')||str_contains($raw,'Bharat')){
    fwrite(STDERR,"Partitioned Social state leaked plaintext invite/profile data\n");exit(1);
}
$decoded=secure_unpack($raw);
if(!is_array($decoded)||($decoded['type']??'')!=='friend'){fwrite(STDERR,"Partitioned Social encryption/decode failed\n");exit(1);}

udaan_social_accept_friend_invite($store,$idb,$b,(string)$invite['code']);
$friendsA=udaan_social_friends($store,$ida);
$friendsB=udaan_social_friends($store,$idb);
if(count($friendsA)!==1||($friendsA[0]['nickname']??'')!=='Bharat'||count($friendsB)!==1){
    fwrite(STDERR,"Friend connection failed\n");exit(1);
}
$replayed=false;
try{udaan_social_accept_friend_invite($store,$idb,$b,(string)$invite['code']);}catch(RuntimeException|InvalidArgumentException $e){$replayed=true;}
if(!$replayed){fwrite(STDERR,"One-time friend invite replay succeeded\n");exit(1);}

$selfInvite=udaan_social_create_friend_invite($store,$ida,$a);
$selfBlocked=false;
try{udaan_social_accept_friend_invite($store,$ida,$a,(string)$selfInvite['code']);}catch(RuntimeException|InvalidArgumentException $e){$selfBlocked=true;}
if(!$selfBlocked){fwrite(STDERR,"Self friend invite was accepted\n");exit(1);}

$relationship=(string)$friendsA[0]['relationship_id'];
udaan_social_remove_friend($store,$ida,$a,$relationship);
if(udaan_social_friends($store,$ida)!==[]||udaan_social_friends($store,$idb)!==[]){
    fwrite(STDERR,"Friend removal failed\n");exit(1);
}

$badName=false;
try{udaan_social_team_name('<script>bad</script>');}catch(InvalidArgumentException $e){$badName=true;}
if(!$badName){fwrite(STDERR,"Unsafe team name was accepted\n");exit(1);}

$team=udaan_social_create_team($store,$ida,$a,'Biology Crew');
$teamId=(string)$team['id'];
if(count((array)$team['members'])!==1||($team['members'][$ida]['role']??'')!=='owner'){fwrite(STDERR,"Team creation mismatch\n");exit(1);}

$teamInvite=udaan_social_create_team_invite($store,$ida,$a,$teamId);
udaan_social_accept_team_invite($store,$idb,$b,(string)$teamInvite['code']);
$graph=$store->getSocialGraph();$joined=udaan_social_team($graph,$teamId,$idb);
if(!is_array($joined)||count((array)$joined['members'])!==2){fwrite(STDERR,"Team join failed\n");exit(1);}

$teamReplay=false;
try{udaan_social_accept_team_invite($store,$idb,$b,(string)$teamInvite['code']);}catch(RuntimeException|InvalidArgumentException $e){$teamReplay=true;}
if(!$teamReplay){fwrite(STDERR,"One-time team invite replay succeeded\n");exit(1);}

$memberInviteBlocked=false;
try{udaan_social_create_team_invite($store,$idb,$b,$teamId);}catch(RuntimeException $e){$memberInviteBlocked=true;}
if(!$memberInviteBlocked){fwrite(STDERR,"Non-owner created a team invite\n");exit(1);}

$members=udaan_social_team_members($store,$joined);
if(count($members)!==2){fwrite(STDERR,"Team member projection mismatch\n");exit(1);}
foreach($members as$m){
    foreach(['identity','age_group','main_goal','daily_minutes','identity_hash','readiness'] as$forbidden){
        if(array_key_exists($forbidden,$m)){fwrite(STDERR,"Private Player field leaked to Team projection: $forbidden\n");exit(1);}
    }
}

$common=udaan_social_team_common_arenas($store,$joined);
if($common!==['learn']){fwrite(STDERR,"Common Team Arena calculation mismatch\n");exit(1);}
$unfair=false;
try{udaan_social_create_team_challenge($store,$ida,$a,$teamId,'fit','2026-09-22');}catch(RuntimeException $e){$unfair=true;}
if(!$unfair){fwrite(STDERR,"Team challenge allowed an Arena not enabled by every member\n");exit(1);}
$challenge=udaan_social_create_team_challenge($store,$ida,$a,$teamId,'learn','2026-09-22');
if(($challenge['rule']??'')!=='one_completed_mission_per_member'){fwrite(STDERR,"Team challenge rule mismatch\n");exit(1);}
$nonOwnerChallenge=false;
try{udaan_social_create_team_challenge($store,$idb,$b,$teamId,'fit','2026-10-01');}catch(RuntimeException $e){$nonOwnerChallenge=true;}
if(!$nonOwnerChallenge){fwrite(STDERR,"Non-owner created a team challenge\n");exit(1);}

$store->mutateMissionState($idb,function(?array $state):array{
    $state=is_array($state)?$state:['schema_version'=>1,'updated_at'=>null,'missions'=>[]];
    $state['missions']['social-test']=[
        'id'=>'social-test','arena'=>'learn','type'=>'learning','status'=>'completed','scheduled_date'=>'2026-09-23',
        'duration_minutes'=>10,'result'=>['source'=>'self_report'],
    ];
    return $state;
});
$graph=$store->getSocialGraph();$teamNow=udaan_social_team($graph,$teamId,$ida);
$progress=udaan_social_team_challenge_progress($store,$teamNow);
if(($progress['participants']??0)!==1||($progress['members']??0)!==2||($progress['completion_rate']??0)!==50){
    fwrite(STDERR,"Participation challenge progress mismatch\n");exit(1);
}
foreach((array)$progress['rows'] as$row){
    if(array_key_exists('rank',$row)||array_key_exists('score',$row)||array_key_exists('speed',$row)){
        fwrite(STDERR,"Team challenge exposed competitive member ranking\n");exit(1);
    }
}

udaan_social_leave_team($store,$idb,$b,$teamId);
$graph=$store->getSocialGraph();$ownerView=udaan_social_team($graph,$teamId,$ida);
if(!is_array($ownerView)||count((array)$ownerView['members'])!==1){fwrite(STDERR,"Team leave failed\n");exit(1);}

$events=[];
foreach(glob($tmp.'/events/*.jsonl')?:[] as$file)foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as$line){
    $event=secure_unpack($line);if(is_array($event))$events[]=$event;
}
$types=array_column($events,'event_type');
foreach(['social.friend_connected','social.friend_removed','social.team_created','social.team_joined','social.team_challenge_created','social.team_left'] as$required){
    if(!in_array($required,$types,true)){fwrite(STDERR,"Missing Social event: $required\n");exit(1);}
}
foreach($events as$event){
    if(!str_starts_with((string)($event['event_type']??''),'social.'))continue;
    $metrics=(array)($event['metrics']??[]);
    if(isset($metrics['code'])||isset($metrics['invite_code'])){fwrite(STDERR,"Invite code leaked into Event Ledger\n");exit(1);}
}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "friends-teams-smoke: PASS\n";
