<?php
declare(strict_types=1);

function content_pack_dir(): string { return udaan_data_path('content/packs'); }
function content_manifest_file(): string { return udaan_data_path('content/manifest.json'); }
function content_signing_key_file(): string { return udaan_data_path('content-signing.key'); }
function content_pack_signing_available(): bool { return function_exists('sodium_crypto_sign_keypair') && function_exists('sodium_crypto_sign_detached') && function_exists('sodium_crypto_sign_verify_detached'); }
function content_pack_bank_hash(): string { $f=content_bank_file(); return is_file($f)?hash_file('sha256',$f):''; }
function content_pack_id_valid(string $id): bool { return (bool)preg_match('/^[a-z0-9_-]{2,40}-\d{3}$/',$id); }
function content_trust_epoch(): int { global $config; return max(1,(int)($config['content_trust_epoch']??1)); }

function content_signing_keys(): array {
    if(!content_pack_signing_available()) throw new RuntimeException('Ed25519 signing requires PHP Sodium.');
    static $keys=null; if(is_array($keys)) return $keys;
    $file=content_signing_key_file(); $fh=@fopen($file,'c+'); if(!$fh) throw new RuntimeException('Content signing key file is not writable.');
    if(!flock($fh,LOCK_EX)){fclose($fh);throw new RuntimeException('Could not lock content signing key.');}
    rewind($fh);$raw=trim((string)stream_get_contents($fh));$secret='';
    if($raw!==''){$decoded=base64_decode($raw,true);if(is_string($decoded)&&strlen($decoded)===SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)$secret=$decoded;}
    if($secret===''){
        $kp=sodium_crypto_sign_keypair();$secret=sodium_crypto_sign_secretkey($kp);$encoded=base64_encode($secret);
        rewind($fh);ftruncate($fh,0);if(fwrite($fh,$encoded)===false){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Could not persist content signing key.');}fflush($fh);
    }
    @chmod($file,0600);
    flock($fh,LOCK_UN);fclose($fh);$public=sodium_crypto_sign_publickey_from_secretkey($secret);
    return $keys=['secret'=>$secret,'public'=>$public,'public_b64'=>b64url_encode($public),'key_id'=>substr(hash('sha256',$public),0,16)];
}

function content_pack_json(array $payload): string {
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
    if(!is_string($json)) throw new RuntimeException('Could not encode content pack.'); return $json;
}
function content_pack_signature(string $payload,array $keys): string { return b64url_encode(sodium_crypto_sign_detached($payload,$keys['secret'])); }
function content_pack_atomic_json(string $file,array $payload,bool $pretty=true): void {
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|($pretty?JSON_PRETTY_PRINT:0));if(!is_string($json))throw new RuntimeException('Could not encode content distribution file.');
    $tmp=$file.'.tmp-'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('Could not write content distribution file.');if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Could not publish content distribution file.');}
}

function rebuild_content_packs(?array $bank=null): array {
    global $config; if(!content_pack_signing_available()) throw new RuntimeException('PHP Sodium is required for signed content packs.');
    $bank=$bank??import_read_bank_fresh();$cards=array_values(array_filter($bank['cards']??[],fn($c)=>is_array($c)&&!empty($c['active'])&&!empty($c['id'])));$packSize=max(16,(int)($config['content_pack_size']??112));
    $dir=content_pack_dir();if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Content pack directory is not writable.');
    $groups=[];foreach($cards as$c){$pillar=(string)($c['pillar']??'other');$groups[$pillar][]=$c;}ksort($groups);
    $keys=content_signing_keys();$manifestPacks=[];$keep=[];$total=0;
    foreach($groups as$pillar=>$group){usort($group,fn($a,$b)=>strcmp((string)$a['id'],(string)$b['id']));$chunks=array_chunk($group,$packSize);foreach($chunks as$i=>$chunk){$packId=preg_replace('/[^a-z0-9_-]/','',strtolower($pillar)).'-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT);$keep[$packId.'.json']=true;
            $base=['schema_version'=>1,'pack_id'=>$packId,'pillar'=>$pillar,'pillar_name'=>pillar_label($pillar),'sequence'=>$i+1,'card_count'=>count($chunk),'bank_version'=>(string)($bank['version']??''),'cards'=>array_values($chunk)];
            $payload=content_pack_json($base);$sha=hash('sha256',$payload);$sig=content_pack_signature($payload,$keys);$pack=$base+['sha256'=>$sha,'signature_alg'=>'Ed25519','signing_key_id'=>$keys['key_id'],'signature'=>$sig];
            content_pack_atomic_json($dir.'/'.$packId.'.json',$pack,true);$bytes=filesize($dir.'/'.$packId.'.json')?:0;$total+=count($chunk);
            $manifestPacks[]=['pack_id'=>$packId,'pillar'=>$pillar,'sequence'=>$i+1,'cards'=>count($chunk),'bytes'=>$bytes,'sha256'=>$sha,'signature'=>$sig,'url'=>app_url('content/pack/'.$packId)];
        }}
    foreach(glob($dir.'/*.json')?:[] as$f)if(!isset($keep[basename($f)]))@unlink($f);
    $manifestBase=['schema_version'=>1,'trust_epoch'=>content_trust_epoch(),'distribution'=>'centralize-trust-decentralize-distribution','generated_at'=>now_iso(),'bank_version'=>(string)($bank['version']??''),'bank_sha256'=>content_pack_bank_hash(),'total_cards'=>$total,'pillar_count'=>count($groups),'pack_size'=>$packSize,'pack_count'=>count($manifestPacks),'signature_alg'=>'Ed25519','signing_key_id'=>$keys['key_id'],'public_key'=>$keys['public_b64'],'packs'=>$manifestPacks];
    $manifestPayload=content_pack_json($manifestBase);$manifest=$manifestBase+['manifest_sha256'=>hash('sha256',$manifestPayload),'manifest_signature'=>content_pack_signature($manifestPayload,$keys)];content_pack_atomic_json(content_manifest_file(),$manifest,true);content_runtime_cache_build($bank);return $manifest;
}

function content_manifest(bool $ensure=false): array {
    global $config;$file=content_manifest_file();$raw=@file_get_contents($file);$m=is_string($raw)?json_decode($raw,true):null;$bankHash=content_pack_bank_hash();$packSize=max(16,(int)($config['content_pack_size']??112));
    $stale=!is_array($m)||($m['bank_sha256']??'')!==$bankHash||(int)($m['pack_size']??0)!==$packSize||(int)($m['trust_epoch']??1)!==content_trust_epoch()||!is_array($m['packs']??null);
    if($stale&&$ensure)return rebuild_content_packs();if($stale)return [];return $m;
}
function content_pack_load(string $packId): ?array { if(!content_pack_id_valid($packId))return null;$m=content_manifest(false);if(!$m)return null;$allowed=false;foreach(($m['packs']??[])as$p)if(($p['pack_id']??'')===$packId){$allowed=true;break;}if(!$allowed)return null;$raw=@file_get_contents(content_pack_dir().'/'.$packId.'.json');$d=is_string($raw)?json_decode($raw,true):null;return is_array($d)?$d:null; }
function content_distribution_stats(): array {try{$m=content_manifest(false);if(!$m)return ['ready'=>false,'error'=>'Signed content distribution is missing or stale. Rebuild it through the controlled CLI/import path.','signing'=>content_pack_signing_available()?'Ed25519 available':'Sodium unavailable','trust_epoch'=>content_trust_epoch()];return ['ready'=>true,'mode'=>$m['distribution']??'','signing'=>$m['signature_alg']??'','key_id'=>$m['signing_key_id']??'','trust_epoch'=>(int)($m['trust_epoch']??1),'pack_size'=>(int)($m['pack_size']??0),'pack_count'=>(int)($m['pack_count']??0),'pack_cards'=>(int)($m['total_cards']??0),'manifest_sha256'=>$m['manifest_sha256']??''];}catch(Throwable $e){return ['ready'=>false,'error'=>$e->getMessage(),'signing'=>content_pack_signing_available()?'Ed25519 available':'Sodium unavailable','trust_epoch'=>content_trust_epoch()];}}
