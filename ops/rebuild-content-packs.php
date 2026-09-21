<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(403);
    exit('CLI only');
}

$root=dirname(__DIR__);
$config=require $root.'/config.php';
date_default_timezone_set('Asia/Kolkata');
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/ContentImport.php';
require_once $root.'/lib/ContentPacks.php';

try{
    $bank=import_read_bank_fresh();
    $manifest=rebuild_content_packs($bank);
    echo json_encode([
        'ok'=>true,
        'version'=>(string)($config['version']??''),
        'trust_epoch'=>(int)($manifest['trust_epoch']??content_trust_epoch()),
        'signing_key_id'=>(string)($manifest['signing_key_id']??''),
        'bank_version'=>(string)($manifest['bank_version']??''),
        'pack_count'=>(int)($manifest['pack_count']??0),
        'pack_cards'=>(int)($manifest['total_cards']??0),
        'manifest_sha256'=>(string)($manifest['manifest_sha256']??''),
        'base_path'=>app_base_path(),
    ],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){
    fwrite(STDERR,"Content-pack rebuild failed: ".$e->getMessage().PHP_EOL);
    exit(1);
}
