<?php
declare(strict_types=1);

$appRoot=dirname(__DIR__);
$base=sys_get_temp_dir().'/udaan-private-data-'.bin2hex(random_bytes(5));
$public=$base.'-public';
@mkdir($base.'/content',0775,true);
@mkdir($public,0775,true);

$config=[
    'data_dir'=>$base,
    'public_root'=>$public,
    'content_pack_size'=>112,
    'content_trust_epoch'=>2,
];

require $appRoot.'/lib/helpers.php';

$bank=[
    'version'=>'external-root-smoke',
    'cards'=>[
        ['id'=>'00000000-0000-4000-8000-000000000001','pillar'=>'future','topic'=>'smoke','topic_name'=>'Smoke','active'=>true],
    ],
];
file_put_contents($base.'/content/cards.json',json_encode($bank,JSON_UNESCAPED_SLASHES));

if(udaan_data_dir()!==str_replace('\\','/',$base)){fwrite(STDERR,"External data root did not resolve\n");exit(1);}
if(udaan_data_root_mode()!=='external-private'){fwrite(STDERR,"External data root was not classified private\n");exit(1);}
if(content_bank_file_path()!==str_replace('\\','/',$base).'/content/cards.json'){fwrite(STDERR,"Content bank path escaped configured data root\n");exit(1);}

$secret=app_secret();
if(strlen($secret)<32||!is_file($base.'/app-secret.key')){fwrite(STDERR,"Application secret was not created in external root\n");exit(1);}
$key=storage_key();
if(strlen($key)!==32||!is_file($base.'/storage-encryption.key')){fwrite(STDERR,"Storage key was not created in external root\n");exit(1);}

content_runtime_cache_build($bank);
if(!is_file($base.'/content/runtime-cache.php')){fwrite(STDERR,"Runtime cache was not created in external root\n");exit(1);}
$loaded=content_runtime_cache();
if(!is_array($loaded)||($loaded['stats']['count']??0)!==1){fwrite(STDERR,"External runtime cache did not load\n");exit(1);}

$cleanup=function(string $dir)use(&$cleanup):void{
    if(!is_dir($dir))return;
    foreach(scandir($dir)?:[] as $name){
        if($name==='.'||$name==='..')continue;
        $path=$dir.DIRECTORY_SEPARATOR.$name;
        is_dir($path)?$cleanup($path):@unlink($path);
    }
    @rmdir($dir);
};
$cleanup($base);$cleanup($public);

echo "data-root-smoke: PASS\n";
