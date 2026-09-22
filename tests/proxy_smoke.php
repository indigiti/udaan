<?php
declare(strict_types=1);

require dirname(__DIR__).'/lib/helpers.php';

$config=['trust_proxy_headers'=>false];
$_SERVER['HTTPS']='off';
$_SERVER['HTTP_X_FORWARDED_PROTO']='https';
if(request_is_https()){fwrite(STDERR,"Untrusted forwarded proto was accepted\n");exit(1);}

$config=['trust_proxy_headers'=>true];
if(!request_is_https()){fwrite(STDERR,"Trusted forwarded proto was ignored\n");exit(1);}

$config=['trust_proxy_headers'=>false];
$_SERVER['HTTPS']='on';
$_SERVER['HTTP_X_FORWARDED_PROTO']='http';
if(!request_is_https()){fwrite(STDERR,"Direct HTTPS was not detected\n");exit(1);}

echo "proxy-smoke: PASS\n";
