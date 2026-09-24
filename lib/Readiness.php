<?php
declare(strict_types=1);

function udaan_readiness_mode(?int $mode): ?string {
    return $mode===null?null:sprintf('%04o',$mode&0777);
}

function udaan_readiness_file_mode(string $path,int $requiredMax=0600): array {
    if(!is_file($path))return ['ok'=>false,'exists'=>false,'mode'=>null,'code'=>'missing'];
    $perms=@fileperms($path);$mode=is_int($perms)?($perms&0777):null;
    if($mode===null)return ['ok'=>false,'exists'=>true,'mode'=>null,'code'=>'mode-unavailable'];
    return ['ok'=>(($mode&~$requiredMax)===0),'exists'=>true,'mode'=>udaan_readiness_mode($mode),'code'=>(($mode&~$requiredMax)===0?'ok':'permissions-too-open')];
}

function udaan_readiness_redis(array $redis): array {
    $enabled=(bool)($redis['enabled']??true);$required=(bool)($redis['required']??false);
    if(!$enabled)return ['configured'=>false,'required'=>$required,'connected'=>false,'status'=>'disabled','error_code'=>null];
    if(!class_exists('Redis'))return ['configured'=>true,'required'=>$required,'connected'=>false,'status'=>'unavailable','error_code'=>'extension-unavailable'];
    $r=new Redis();
    try{
        $r->connect((string)($redis['host']??'127.0.0.1'),(int)($redis['port']??6379),0.5);
        $password=(string)($redis['password']??'');if($password!=='')$r->auth($password);
        $r->select((int)($redis['database']??0));
        $pong=$r->ping();$ok=$pong===true||$pong==='+PONG'||$pong==='PONG';
        return ['configured'=>true,'required'=>$required,'connected'=>$ok,'status'=>$ok?'online':'unavailable','error_code'=>$ok?null:'ping-failed'];
    }catch(Throwable $e){
        return ['configured'=>true,'required'=>$required,'connected'=>false,'status'=>'unavailable','error_code'=>'connection-failed'];
    }finally{
        try{$r->close();}catch(Throwable $ignored){}
    }
}

function udaan_readiness_report(array $config,string $root,bool $repositoryMode=false): array {
    $checks=[];$warnings=[];$failures=[];
    $push=function(string $name,bool $ok,string $code,array $meta=[])use(&$checks,&$failures):void{
        $checks[$name]=['ok'=>$ok,'code'=>$code]+$meta;
        if(!$ok)$failures[]=$name;
    };
    $warn=function(string $code,string $message)use(&$warnings):void{$warnings[]=['code'=>$code,'message'=>$message];};

    $environment=(string)($config['environment']??'development');
    $push('php_version',version_compare(PHP_VERSION,'8.1.0','>='),'php-'.PHP_VERSION,['value'=>PHP_VERSION]);
    $aes=storage_encryption_available();$push('aes_256_gcm',$aes,$aes?'available':'unavailable');
    $sodium=content_pack_signing_available();$push('ed25519_sodium',$sodium,$sodium?'available':'unavailable');

    $dataDir=data_root();$dataWritable=is_dir($dataDir)&&is_writable($dataDir);
    $push('data_directory',$dataWritable,$dataWritable?'writable':'not-writable');
    $playerDir=$dataDir.'/players';$eventDir=$dataDir.'/events';$missionDir=$dataDir.'/missions';$dailyReadinessDir=$dataDir.'/readiness';$socialDir=$dataDir.'/social';$competitionDir=$dataDir.'/competition';
    $push('player_storage_directory',is_dir($playerDir)&&is_writable($playerDir),(is_dir($playerDir)&&is_writable($playerDir))?'writable':'not-writable');
    $push('event_ledger_directory',is_dir($eventDir)&&is_writable($eventDir),(is_dir($eventDir)&&is_writable($eventDir))?'writable':'not-writable');
    $push('mission_storage_directory',is_dir($missionDir)&&is_writable($missionDir),(is_dir($missionDir)&&is_writable($missionDir))?'writable':'not-writable');
    $push('daily_readiness_storage_directory',is_dir($dailyReadinessDir)&&is_writable($dailyReadinessDir),(is_dir($dailyReadinessDir)&&is_writable($dailyReadinessDir))?'writable':'not-writable');
    $push('social_storage_directory',is_dir($socialDir)&&is_writable($socialDir),(is_dir($socialDir)&&is_writable($socialDir))?'writable':'not-writable');
    $push('competition_storage_directory',is_dir($competitionDir)&&is_writable($competitionDir),(is_dir($competitionDir)&&is_writable($competitionDir))?'writable':'not-writable');

    $docRoot=str_replace('\\','/',realpath($_SERVER['DOCUMENT_ROOT']??'')?:'');$dataReal=str_replace('\\','/',realpath($dataDir)?:$dataDir);
    $dataOutsideDoc=$docRoot===''||!str_starts_with($dataReal,rtrim($docRoot,'/').'/');
    $dataDenied=$dataOutsideDoc||(is_file($dataDir.'/.htaccess')&&preg_match('/Require\s+all\s+denied/i',(string)@file_get_contents($dataDir.'/.htaccess')));
    $opsDenied=is_file($root.'/ops/.htaccess')&&preg_match('/Require\s+all\s+denied/i',(string)@file_get_contents($root.'/ops/.htaccess'));
    $push('data_http_denied',(bool)$dataDenied,$dataOutsideDoc?'outside-document-root':($dataDenied?'protected':'deny-rule-missing'));
    $push('ops_http_denied',(bool)$opsDenied,$opsDenied?'protected':'deny-rule-missing');

    $maintenance=is_file($dataDir.'/maintenance.flag');
    if(!$repositoryMode)$push('maintenance_mode',!$maintenance,$maintenance?'active':'released');
    else $checks['maintenance_mode']=['ok'=>true,'code'=>'repository-mode-skipped'];

    try{
        $bank=import_read_bank_fresh();$bankOk=is_array($bank['cards']??null)&&count($bank['cards'])>0;
        $push('content_bank',$bankOk,$bankOk?'valid':'empty',['cards'=>$bankOk?count($bank['cards']):0,'version'=>(string)($bank['version']??'')]);
    }catch(Throwable $e){
        $push('content_bank',false,'invalid');
        $bank=null;
    }

    if($repositoryMode){
        $checks['runtime_keys']=['ok'=>true,'code'=>'repository-mode-skipped'];
        $checks['runtime_content_cache']=['ok'=>true,'code'=>'repository-mode-skipped'];
        $checks['signed_content_distribution']=['ok'=>true,'code'=>'repository-mode-skipped'];
        $checks['redis']=['ok'=>true,'code'=>'repository-mode-skipped'];
    }else{
        $keyChecks=[
            'app_secret'=>udaan_readiness_file_mode($dataDir.'/app-secret.key',0600),
            'storage_encryption_key'=>udaan_readiness_file_mode($dataDir.'/storage-encryption.key',0600),
            'content_signing_key'=>udaan_readiness_file_mode($dataDir.'/content-signing.key',0600),
        ];
        $adminEnv=trim((string)(getenv('UDAAN_CONTENT_ADMIN_KEY')?:''));
        $keyChecks['content_admin_key']=strlen($adminEnv)>=12
            ?['ok'=>true,'exists'=>false,'mode'=>null,'code'=>'environment']
            :udaan_readiness_file_mode($dataDir.'/content-admin.key',0600);
        $keysOk=true;foreach($keyChecks as$key=>$state){if(empty($state['ok']))$keysOk=false;}
        $push('runtime_keys',$keysOk,$keysOk?'secure':'missing-or-permissions',['keys'=>$keyChecks]);

        $runtimeCache=content_runtime_cache();$cacheOk=is_array($runtimeCache);
        $push('runtime_content_cache',$cacheOk,$cacheOk?'fresh':'missing-or-stale',[
            'mode'=>$cacheOk?'compiled-php-opcache-friendly':'json-fallback',
            'source_size'=>(int)($runtimeCache['source_size']??0),
            'source_mtime'=>(int)($runtimeCache['source_mtime']??0),
        ]);

        $dist=content_distribution_stats();$distOk=!empty($dist['ready']);
        $push('signed_content_distribution',$distOk,$distOk?'ready':'missing-or-stale',[
            'trust_epoch'=>(int)($dist['trust_epoch']??($config['content_trust_epoch']??1)),
            'pack_count'=>(int)($dist['pack_count']??0),
            'pack_cards'=>(int)($dist['pack_cards']??0),
            'key_id'=>(string)($dist['key_id']??''),
        ]);

        $redis=udaan_readiness_redis(is_array($config['redis']??null)?$config['redis']:[]);
        $redisRequired=!empty($redis['required']);$redisOk=!$redisRequired||!empty($redis['connected']);
        $push('redis',$redisOk,$redisOk?($redis['connected']?'online':'optional-fallback'):'required-unavailable',$redis);
        if(!$redisRequired&&!empty($redis['configured'])&&empty($redis['connected']))$warn('redis-optional-fallback','Redis is configured but unavailable; encrypted file storage is serving as the fallback.');
    }

    $opcache=function_exists('opcache_get_status')?@opcache_get_status(false):false;
    if($opcache===false||empty($opcache['opcache_enabled']))$warn('opcache-disabled','PHP OPcache is not reported as enabled; runtime content-cache performance will be lower.');

    if($environment!=='production')$warn('environment-not-production','UDAAN_ENV is not set to production.');
    if(!empty($config['trust_proxy_headers']))$checks['trusted_proxy_headers']=['ok'=>true,'code'=>'enabled'];
    else $checks['trusted_proxy_headers']=['ok'=>true,'code'=>'disabled'];

    $ready=count($failures)===0;
    return [
        'ready'=>$ready,
        'app'=>(string)($config['app_name']??'Udaan Live'),
        'version'=>(string)($config['version']??''),
        'environment'=>$environment,
        'repository_mode'=>$repositoryMode,
        'checks'=>$checks,
        'warnings'=>$warnings,
        'failures'=>$failures,
        'time'=>date(DATE_ATOM),
    ];
}
