<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(403);
    exit('CLI only');
}

if(!in_array('--confirm-v061-rotation',$argv,true)){
    fwrite(STDERR,"Refusing rotation. Re-run with --confirm-v061-rotation after placing the deployment in a maintenance window.".PHP_EOL);
    exit(2);
}

$skipRedis=in_array('--skip-redis',$argv,true);
$root=dirname(__DIR__);
$config=require $root.'/config.php';
date_default_timezone_set('Asia/Kolkata');

require_once $root.'/lib/helpers.php';
require_once $root.'/lib/ContentImport.php';
require_once $root.'/lib/ContentPacks.php';

$dataDir=$root.'/data';
$maintenance=$dataDir.'/maintenance.flag';
if(!is_dir($dataDir)&&!@mkdir($dataDir,0775,true)&&!is_dir($dataDir)){
    fwrite(STDERR,"Data directory is unavailable.".PHP_EOL);
    exit(1);
}

function udaan_atomic_secret_write(string $path,string $contents): void {
    $tmp=$path.'.next-'.bin2hex(random_bytes(5));
    if(@file_put_contents($tmp,$contents,LOCK_EX)===false)throw new RuntimeException('Could not write replacement secret file: '.basename($path));
    @chmod($tmp,0600);
    if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Could not activate replacement secret file: '.basename($path));}
    @chmod($path,0600);
}

function udaan_remove_matching(string $pattern): int {
    $count=0;
    foreach(glob($pattern)?:[] as $file){
        if(is_file($file)&&@unlink($file))$count++;
    }
    return $count;
}

function udaan_purge_redis(array $redisConfig,bool $skip): array {
    if(!($redisConfig['enabled']??true))return ['mode'=>'disabled','deleted'=>0];
    if($skip)return ['mode'=>'explicitly-skipped','deleted'=>0];
    if(!class_exists('Redis'))throw new RuntimeException('Redis is enabled but the PHP Redis extension is unavailable. Verify file-only mode before using --skip-redis.');

    $r=new Redis();
    try{
        $r->connect((string)($redisConfig['host']??'127.0.0.1'),(int)($redisConfig['port']??6379),0.5);
        $password=(string)($redisConfig['password']??'');
        if($password!=='')$r->auth($password);
        $r->select((int)($redisConfig['database']??0));

        $iterator=null;$deleted=0;
        do{
            $keys=$r->scan($iterator,'udaan:*',250);
            if(is_array($keys)){
                foreach($keys as $key)$deleted+=(int)$r->del((string)$key);
            }
        }while($iterator!==0&&$iterator!==null);

        return ['mode'=>'purged','deleted'=>$deleted];
    }catch(Throwable $e){
        throw new RuntimeException('Redis runtime purge failed; no keys were rotated: '.$e->getMessage(),0,$e);
    }finally{
        try{$r->close();}catch(Throwable $ignored){}
    }
}

$success=false;
if(@file_put_contents($maintenance,now_iso()." v0.6.1 security rotation\n",LOCK_EX)===false){
    fwrite(STDERR,"Could not enable maintenance mode.".PHP_EOL);
    exit(1);
}

try{
    if(!content_pack_signing_available())throw new RuntimeException('PHP Sodium is required before key rotation.');
    $bank=import_read_bank_fresh();
    if(!is_array($bank['cards']??null)||count($bank['cards'])===0)throw new RuntimeException('Approved content bank is unavailable; rotation aborted.');

    $redisResult=udaan_purge_redis(is_array($config['redis']??null)?$config['redis']:[],$skipRedis);

    $purgedFiles=0;
    foreach([
        $dataDir.'/rooms/*.json',
        $dataDir.'/history/*.json',
        $dataDir.'/user-cache/*.json',
        $dataDir.'/sync-outbox/*.jsonl',
        $dataDir.'/aggregates/*.json',
        $dataDir.'/rate-limit/*.json',
        $dataDir.'/imports/pending/*.json',
        $dataDir.'/content/packs/*.json',
    ] as $pattern)$purgedFiles+=udaan_remove_matching($pattern);

    foreach([
        $dataDir.'/php-error.log',
        $dataDir.'/content/manifest.json',
        $dataDir.'/content/import-log.json',
        $dataDir.'/content/.import.lock',
        $dataDir.'/content/runtime-cache.php',
    ] as $file){
        if(is_file($file)&&@unlink($file))$purgedFiles++;
    }

    $appSecret=bin2hex(random_bytes(32));
    $storageSecret=bin2hex(random_bytes(32));
    $adminEnv=trim((string)(getenv('UDAAN_CONTENT_ADMIN_KEY')?:''));
    $adminSource=strlen($adminEnv)>=12?'environment':'file';
    $adminSecret=$adminSource==='file'?bin2hex(random_bytes(24)):null;

    $kp=sodium_crypto_sign_keypair();
    $signingSecret=sodium_crypto_sign_secretkey($kp);
    if(strlen($signingSecret)!==SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)throw new RuntimeException('Could not generate replacement Ed25519 signing key.');

    udaan_atomic_secret_write($dataDir.'/app-secret.key',$appSecret."\n");
    udaan_atomic_secret_write($dataDir.'/storage-encryption.key',$storageSecret."\n");
    if($adminSource==='file'){
        udaan_atomic_secret_write($dataDir.'/content-admin.key',(string)$adminSecret."\n");
    }else{
        @unlink($dataDir.'/content-admin.key');
    }
    udaan_atomic_secret_write($dataDir.'/content-signing.key',base64_encode($signingSecret)."\n");

    unset($appSecret,$storageSecret,$adminSecret,$signingSecret,$kp);

    $manifest=rebuild_content_packs($bank);
    foreach(['app-secret.key','storage-encryption.key','content-signing.key'] as $name)@chmod($dataDir.'/'.$name,0600);
    if($adminSource==='file')@chmod($dataDir.'/content-admin.key',0600);

    $success=true;
    @unlink($maintenance);

    echo json_encode([
        'ok'=>true,
        'version'=>(string)($config['version']??''),
        'trust_epoch'=>(int)($manifest['trust_epoch']??content_trust_epoch()),
        'signing_key_id'=>(string)($manifest['signing_key_id']??''),
        'manifest_sha256'=>(string)($manifest['manifest_sha256']??''),
        'pack_count'=>(int)($manifest['pack_count']??0),
        'pack_cards'=>(int)($manifest['total_cards']??0),
        'bank_version'=>(string)($manifest['bank_version']??''),
        'purged_file_records'=>$purgedFiles,
        'redis_runtime'=>$redisResult,
        'content_admin_key_source'=>$adminSource,
        'maintenance_mode'=>'released',
    ],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){
    fwrite(STDERR,"SECURITY ROTATION FAILED: ".$e->getMessage().PHP_EOL);
    fwrite(STDERR,"Maintenance mode remains enabled at data/maintenance.flag. Correct the failure and rerun the command.".PHP_EOL);
    exit(1);
}
