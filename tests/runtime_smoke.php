<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Store.php';

$bankFile=$root.'/data/content/cards.json';
$raw=@file_get_contents($bankFile);
$bank=is_string($raw)?json_decode($raw,true):null;
if(!is_array($bank)||!isset($bank['cards'])||!is_array($bank['cards'])){fwrite(STDERR,"Invalid content bank\n");exit(1);}

$compiled=content_runtime_cache_build($bank);
if((int)($compiled['stats']['count']??-1)!==count($bank['cards'])){fwrite(STDERR,"Compiled cache count mismatch\n");exit(1);}
if(!is_array($compiled['card_map']??null)||count($compiled['card_map'])!==count(array_filter($bank['cards'],fn($c)=>is_array($c)&&isset($c['id'])))){fwrite(STDERR,"Compiled card map mismatch\n");exit(1);}
$loaded=content_runtime_cache();
if(!is_array($loaded)||!is_array($loaded['bank']['cards']??null)){fwrite(STDERR,"Compiled cache did not load\n");exit(1);}
if(count(cards_by_id())!==count(array_filter($bank['cards'],fn($c)=>is_array($c)&&isset($c['id'])))){fwrite(STDERR,"Card map mismatch\n");exit(1);}

$tmp=$root.'/data/test-rate-limit-'.bin2hex(random_bytes(4));
@mkdir($tmp,0775,true);
$config=['room_ttl_seconds'=>60,'user_cache_ttl_seconds'=>60,'redis'=>['enabled'=>false]];
$store=new TempStore($config,$tmp.'/rooms');
$a=$store->rateLimit('smoke','one',2,60);
$b=$store->rateLimit('smoke','one',2,60);
$c=$store->rateLimit('smoke','one',2,60);
if(empty($a['allowed'])||empty($b['allowed'])||!empty($c['allowed'])){fwrite(STDERR,"Rate limiter smoke test failed\n");exit(1);}

$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as $p){$path=$p->getPathname();$p->isDir()?@rmdir($path):@unlink($path);}@rmdir($tmp);
@unlink(content_runtime_cache_file());
echo "runtime-smoke: PASS\n";
