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
require_once $root.'/lib/Readiness.php';

$repositoryMode=in_array('--repository-mode',$argv,true);
$jsonMode=in_array('--json',$argv,true);
$report=udaan_readiness_report($config,$root,$repositoryMode);

if($jsonMode){
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
}else{
    echo 'Udaan '.($report['version']??'').' preflight'.($repositoryMode?' [repository mode]':'').PHP_EOL;
    echo str_repeat('=',56).PHP_EOL;
    foreach(($report['checks']??[]) as$name=>$check){
        $status=!empty($check['ok'])?'PASS':'FAIL';
        echo str_pad($status,6).' '.str_pad((string)$name,30).' '.(string)($check['code']??'').PHP_EOL;
    }
    foreach(($report['warnings']??[]) as$warning){
        echo 'WARN   '.(string)($warning['code']??'warning').' — '.(string)($warning['message']??'').PHP_EOL;
    }
    echo str_repeat('-',56).PHP_EOL;
    echo !empty($report['ready'])?'READY'.PHP_EOL:'NOT READY'.PHP_EOL;
}
exit(!empty($report['ready'])?0:1);
