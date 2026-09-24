<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Store.php';

function partition_fail(string $message): never {fwrite(STDERR,$message."\n");exit(1);}

$tmp=$root.'/data/test-partition-'.bin2hex(random_bytes(4));
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false]];
$store=new TempStore($config,$tmp.'/rooms');

$friendId=str_repeat('a',32);
$inviteId=str_repeat('b',64);
$teamId='11111111-1111-4111-8111-111111111111';
$legacySocial=[
    'schema_version'=>1,'updated_at'=>now_iso(),
    'friendships'=>[$friendId=>['id'=>$friendId,'members'=>[str_repeat('c',64),str_repeat('d',64)],'created_at'=>now_iso()]],
    'invites'=>[$inviteId=>['type'=>'friend','created_at'=>now_iso(),'expires_at'=>date(DATE_ATOM,time()+3600)]],
    'teams'=>[$teamId=>['id'=>$teamId,'name'=>'Legacy Team','members'=>[],'created_at'=>now_iso(),'updated_at'=>now_iso()]],
];
@mkdir($tmp.'/social',0775,true);
file_put_contents($tmp.'/social/graph.json',secure_pack($legacySocial),LOCK_EX);

$loaded=$store->getSocialGraph();
if(!isset($loaded['friendships'][$friendId],$loaded['teams'][$teamId]))partition_fail('Legacy social graph did not load');

$newTeamId='22222222-2222-4222-8222-222222222222';
$store->mutateSocialGraph(function(?array $state)use($newTeamId):array{
    $state=is_array($state)?$state:[];
    $state['teams'][$newTeamId]=['id'=>$newTeamId,'name'=>'Partition Team','members'=>[],'created_at'=>now_iso(),'updated_at'=>now_iso()];
    return $state;
});
if(!is_file($tmp.'/social/.schema-v2'))partition_fail('Social v2 marker missing');
if(!is_file($tmp.'/social/friend/'.$friendId.'.json'))partition_fail('Friendship was not partitioned');
if(!is_file($tmp.'/social/invite/'.$inviteId.'.json'))partition_fail('Invite was not partitioned');
if(!is_file($tmp.'/social/team/'.$teamId.'.json')||!is_file($tmp.'/social/team/'.$newTeamId.'.json'))partition_fail('Teams were not partitioned');
$socialV2=$store->getSocialGraph();
if(!isset($socialV2['friendships'][$friendId],$socialV2['teams'][$teamId],$socialV2['teams'][$newTeamId]))partition_fail('Social migration lost entities');

$leagueId='33333333-3333-4333-8333-333333333333';
$competitionInvite=str_repeat('e',64);
$legacyCompetition=[
    'schema_version'=>1,'updated_at'=>now_iso(),
    'leagues'=>[$leagueId=>['id'=>$leagueId,'name'=>'Legacy League','teams'=>[],'created_at'=>now_iso(),'updated_at'=>now_iso()]],
    'invites'=>[$competitionInvite=>['type'=>'league','league_id'=>$leagueId,'created_at'=>now_iso(),'expires_at'=>date(DATE_ATOM,time()+3600)]],
];
@mkdir($tmp.'/competition',0775,true);
file_put_contents($tmp.'/competition/state.json',secure_pack($legacyCompetition),LOCK_EX);

$loadedCompetition=$store->getCompetitionState();
if(!isset($loadedCompetition['leagues'][$leagueId]))partition_fail('Legacy competition state did not load');

$newLeagueId='44444444-4444-4444-8444-444444444444';
$store->mutateCompetitionState(function(?array $state)use($newLeagueId):array{
    $state=is_array($state)?$state:[];
    $state['leagues'][$newLeagueId]=['id'=>$newLeagueId,'name'=>'Partition League','teams'=>[],'created_at'=>now_iso(),'updated_at'=>now_iso()];
    return $state;
});
if(!is_file($tmp.'/competition/.schema-v2'))partition_fail('Competition v2 marker missing');
if(!is_file($tmp.'/competition/league/'.$leagueId.'.json')||!is_file($tmp.'/competition/league/'.$newLeagueId.'.json'))partition_fail('Leagues were not partitioned');
if(!is_file($tmp.'/competition/invite/'.$competitionInvite.'.json'))partition_fail('Competition invite was not partitioned');
$competitionV2=$store->getCompetitionState();
if(!isset($competitionV2['leagues'][$leagueId],$competitionV2['leagues'][$newLeagueId]))partition_fail('Competition migration lost entities');

if(!str_contains($store->socialBackend(),'Partitioned')&&!str_contains($store->socialBackend(),'partitioned'))partition_fail('Social backend does not report partitioning');
if(!str_contains($store->competitionBackend(),'Partitioned')&&!str_contains($store->competitionBackend(),'partitioned'))partition_fail('Competition backend does not report partitioning');

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);

echo "partitioned-social-competition-smoke: PASS\n";
