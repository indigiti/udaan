<?php
declare(strict_types=1);

$root=__DIR__;
$config=require $root.'/config.php';
date_default_timezone_set('Asia/Kolkata');
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/ContentImport.php';
require_once $root.'/lib/ContentPacks.php';
require_once $root.'/lib/Readiness.php';

$report=udaan_readiness_report($config,$root,false);
$ready=!empty($report['ready']);
http_response_code($ready?200:503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
echo json_encode([
    'ready'=>$ready,
    'app'=>(string)($config['app_name']??'Udaan Live'),
    'version'=>(string)($config['version']??''),
    'status'=>$ready?'ready':'not-ready',
    'time'=>date(DATE_ATOM),
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
