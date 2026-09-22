<?php
declare(strict_types=1);

require dirname(__DIR__).'/lib/helpers.php';
require dirname(__DIR__).'/lib/Store.php';

$root=dirname(__DIR__).'/data/test-redis-policy-'.bin2hex(random_bytes(4));
$config=[
    'room_ttl_seconds'=>60,
    'user_cache_ttl_seconds'=>60,
    'redis'=>['enabled'=>false,'required'=>true,'host'=>'127.0.0.1','port'=>6379,'password'=>'','database'=>0],
];
$store=new TempStore($config,$root.'/rooms');
$status=$store->runtimeStatus();
if(!$store->redisRequired()||$store->redisConnected()||($status['redis_error']??null)!=='disabled'){
    fwrite(STDERR,"Redis policy state mismatch\n");exit(1);
}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as$p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($root);

echo "redis-policy-smoke: PASS\n";
