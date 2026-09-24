<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}

$root=dirname(__DIR__);
$config=require $root.'/config.php';
date_default_timezone_set('Asia/Kolkata');
require_once $root.'/lib/helpers.php';

$apply=in_array('--apply',$argv,true);
$confirm=in_array('--confirm-legacy-storage-migration',$argv,true);
if($apply&&!$confirm){
    fwrite(STDERR,"Refusing migration. Use --apply --confirm-legacy-storage-migration after taking a server snapshot.\n");
    exit(2);
}

$data=data_root();
$targets=['rooms','history','user-cache','aggregates','players','events','missions','readiness','social','competition','sync-outbox'];
$timestamp=date('Ymd-His');
$backupRoot=$data.'/migration-backups/legacy-plaintext-'.$timestamp;
$scanned=0;$plaintext=0;$migrated=0;$skipped=0;$errors=[];

function udaan_legacy_atomic_write(string $file,string $contents): void {
    $tmp=$file.'.legacy-next-'.bin2hex(random_bytes(5));
    if(@file_put_contents($tmp,$contents,LOCK_EX)===false)throw new RuntimeException('Could not write migration temp file.');
    @chmod($tmp,0640);
    if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Could not activate migrated file.');}
    @chmod($file,0640);
}

function udaan_legacy_backup(string $file,string $dataRoot,string $backupRoot): void {
    $relative=ltrim(str_replace('\\','/',substr($file,strlen(rtrim($dataRoot,'/')))),'/');
    $dest=$backupRoot.'/'.$relative;$dir=dirname($dest);
    if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Could not create migration backup directory.');
    if(!@copy($file,$dest))throw new RuntimeException('Could not back up '.basename($file));
    @chmod($dest,0600);
}

foreach($targets as$dirName){
    $dir=$data.'/'.$dirName;if(!is_dir($dir))continue;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
    foreach($it as$item){
        if(!$item->isFile())continue;$file=$item->getPathname();$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
        if(!in_array($ext,['json','jsonl'],true))continue;$scanned++;
        $raw=@file_get_contents($file);if(!is_string($raw)||$raw===''){continue;}
        try{
            if($ext==='json'){
                if(str_starts_with($raw,'UDENC1.')){$skipped++;continue;}
                $decoded=json_decode($raw,true);
                if(!is_array($decoded)){$errors[]=['file'=>$file,'error'=>'invalid-plaintext-json'];continue;}
                $plaintext++;if(!$apply)continue;
                udaan_legacy_backup($file,$data,$backupRoot);
                udaan_legacy_atomic_write($file,secure_pack($decoded));$migrated++;
                continue;
            }

            $lines=preg_split('/\R/',$raw);if(!is_array($lines))continue;$changed=false;$out=[];
            foreach($lines as$line){
                if($line==='')continue;
                if(str_starts_with($line,'UDENC1.')){$out[]=$line;continue;}
                $decoded=json_decode($line,true);
                if(!is_array($decoded)){$errors[]=['file'=>$file,'error'=>'invalid-plaintext-jsonl-line'];$out[]=$line;continue;}
                $plaintext++;$changed=true;$out[]=$apply?secure_pack($decoded):$line;
            }
            if(!$changed){$skipped++;continue;}
            if($apply){udaan_legacy_backup($file,$data,$backupRoot);udaan_legacy_atomic_write($file,implode("\n",$out)."\n");$migrated++;}
        }catch(Throwable $e){$errors[]=['file'=>$file,'error'=>$e->getMessage()];}
    }
}

$result=[
    'ok'=>count($errors)===0,
    'mode'=>$apply?'apply':'dry-run',
    'data_root'=>$data,
    'scanned_files'=>$scanned,
    'plaintext_records'=>$plaintext,
    'migrated_files'=>$migrated,
    'already_encrypted_or_empty'=>$skipped,
    'backup_root'=>$apply&&$migrated>0?$backupRoot:null,
    'errors'=>$errors,
    'next_step'=>$apply&&count($errors)===0?'Set UDAAN_LEGACY_PLAINTEXT_STORAGE=deny and rerun ops/preflight.php.':'Review the dry-run/errors before applying.',
];
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
exit(count($errors)===0?0:1);
