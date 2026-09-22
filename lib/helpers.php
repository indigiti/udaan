<?php
declare(strict_types=1);

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function now_iso(): string { return date(DATE_ATOM); }
function rand_id(int $bytes=12): string { return bin2hex(random_bytes($bytes)); }
function uuid_v4(): string {$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
function room_id(): string { return uuid_v4(); }
function room_label(string $id): string { return strtoupper(substr(str_replace('-', '', $id), 0, 8)); }
function text_cut(string $value,int $max): string { return function_exists('mb_substr')?mb_substr($value,0,$max,'UTF-8'):substr($value,0,$max); }
function normalized_phone(string $phone): string { return preg_replace('/\D+/', '', $phone) ?? ''; }
function phone_mask(string $phone): string {$p=normalized_phone($phone);if(strlen($p)<4)return '••••';return (strlen($p)===10?'+91 ':'').'••••••'.substr($p,-4);}

function app_secret(): string {
    static $secret=null; if(is_string($secret))return $secret;
    $file=dirname(__DIR__).'/data/app-secret.key';
    $fh=@fopen($file,'c+');
    if(!$fh)throw new RuntimeException('Application secret file is not writable.');
    if(!flock($fh,LOCK_EX)){fclose($fh);throw new RuntimeException('Could not lock application secret file.');}
    rewind($fh);$existing=trim((string)stream_get_contents($fh));
    if(strlen($existing)>=32){@chmod($file,0600);flock($fh,LOCK_UN);fclose($fh);return $secret=$existing;}
    $secret=bin2hex(random_bytes(32));rewind($fh);ftruncate($fh,0);
    if(fwrite($fh,$secret)===false){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Could not persist application secret.');}
    fflush($fh);@chmod($file,0600);flock($fh,LOCK_UN);fclose($fh);return $secret;
}
function learning_identity(string $phone): string { return hash_hmac('sha256',normalized_phone($phone),app_secret()); }
function client_cache_token(string $learningIdentity): string { return substr(hash_hmac('sha256','browser-cache:'.$learningIdentity,app_secret()),0,32); }

function b64url_encode(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function b64url_decode(string $raw): string|false { $pad=strlen($raw)%4; if($pad)$raw.=str_repeat('=',4-$pad); return base64_decode(strtr($raw,'-_','+/'),true); }
function storage_encryption_available(): bool { return function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true); }
function storage_key(): string {
    static $key=null; if(is_string($key)) return $key;
    $file=dirname(__DIR__).'/data/storage-encryption.key'; $fh=@fopen($file,'c+'); if(!$fh) throw new RuntimeException('Storage encryption key file is not writable.');
    if(!flock($fh,LOCK_EX)){fclose($fh);throw new RuntimeException('Could not lock storage encryption key file.');}
    rewind($fh);$raw=stream_get_contents($fh);$hex=is_string($raw)?trim($raw):'';
    if(!preg_match('/^[a-f0-9]{64}$/i',$hex)){ $bytes=random_bytes(32);$hex=bin2hex($bytes);rewind($fh);ftruncate($fh,0);if(fwrite($fh,$hex)===false){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Could not persist storage encryption key.');}fflush($fh); }
    @chmod($file,0600);
    flock($fh,LOCK_UN);fclose($fh);$bin=hex2bin($hex);if(!is_string($bin)||strlen($bin)!==32)throw new RuntimeException('Storage encryption key is invalid.');return $key=$bin;
}
function secure_pack(array $payload): string {
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); if(!is_string($json)) throw new RuntimeException('Could not encode secure payload.');
    if(!storage_encryption_available()) throw new RuntimeException('AES-256-GCM is required for sensitive server storage.');
    $iv=random_bytes(12); $tag=''; $ct=openssl_encrypt($json,'aes-256-gcm',storage_key(),OPENSSL_RAW_DATA,$iv,$tag,'udaan:v1',16);
    if(!is_string($ct)) throw new RuntimeException('Could not encrypt secure payload.');
    $env=json_encode(['iv'=>b64url_encode($iv),'tag'=>b64url_encode($tag),'ct'=>b64url_encode($ct)],JSON_UNESCAPED_SLASHES);if(!is_string($env))throw new RuntimeException('Could not encode encryption envelope.');return 'UDENC1.'.b64url_encode($env);
}
function secure_unpack(string $raw): ?array {
    if(str_starts_with($raw,'UDENC1.')){
        if(!storage_encryption_available()) return null; $envRaw=b64url_decode(substr($raw,7)); if(!is_string($envRaw))return null; $env=json_decode($envRaw,true); if(!is_array($env))return null;
        $iv=b64url_decode((string)($env['iv']??'')); $tag=b64url_decode((string)($env['tag']??'')); $ct=b64url_decode((string)($env['ct']??'')); if(!is_string($iv)||!is_string($tag)||!is_string($ct))return null;
        $json=openssl_decrypt($ct,'aes-256-gcm',storage_key(),OPENSSL_RAW_DATA,$iv,$tag,'udaan:v1'); if(!is_string($json))return null; $d=json_decode($json,true); return is_array($d)?$d:null;
    }
    $d=json_decode($raw,true); return is_array($d)?$d:null;
}
function device_install_id_valid(string $id): bool { return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',trim($id)); }
function device_identity(string $id): string { return device_install_id_valid($id)?hash_hmac('sha256','device:'.strtolower(trim($id)),app_secret()):''; }
function offline_sync_token(string $learningKey,string $deviceHash,int $expires): string { $payload=b64url_encode(json_encode(['u'=>$learningKey,'d'=>$deviceHash,'exp'=>$expires],JSON_UNESCAPED_SLASHES)); return $payload.'.'.b64url_encode(hash_hmac('sha256','offline-sync:'.$payload,app_secret(),true)); }
function offline_sync_token_parse(string $token): ?array { $p=explode('.',$token,2); if(count($p)!==2)return null; [$payload,$sig]=$p; $expect=b64url_encode(hash_hmac('sha256','offline-sync:'.$payload,app_secret(),true)); if(!hash_equals($expect,$sig))return null; $raw=b64url_decode($payload); $d=is_string($raw)?json_decode($raw,true):null; if(!is_array($d)||empty($d['u'])||empty($d['d'])||(int)($d['exp']??0)<time())return null; return $d; }

function app_base_path(): string {
    static $base=null;if($base!==null)return $base;$forced=trim((string)(getenv('UDAAN_BASE_PATH')?:''));if($forced!==''){return $base=$forced==='/'?'':'/'.trim($forced,'/');}if(PHP_SAPI==='cli')return $base='';$app=str_replace('\\','/',realpath(__DIR__.'/..')?:dirname(__DIR__));$doc=str_replace('\\','/',realpath($_SERVER['DOCUMENT_ROOT']??'')?:'');
    if($doc!==''&&str_starts_with($app,rtrim($doc,'/'))){$rel=substr($app,strlen(rtrim($doc,'/')));return $base=rtrim('/'.trim($rel,'/'),'/');}
    $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'');return $base=rtrim(dirname($script),'/.');
}
function app_url(string $path=''): string { $base=app_base_path();return ($base?:'').'/'.ltrim($path,'/'); }
function request_is_https(): bool {global $config;$trustProxy=(bool)($config['trust_proxy_headers']??false);$forwarded=strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]));return ($trustProxy&&$forwarded==='https')||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');}
function absolute_app_url(string $path=''): string {$proto=request_is_https()?'https':'http';$host=preg_replace('/[^A-Za-z0-9.:-]/','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost';return $proto.'://'.$host.app_url($path);}
function route_url(string $name,?string $room=null): string {$map=['home'=>'','join'=>'join','verify'=>'verify','journey'=>'journey','complete'=>'complete','present'=>'present','state'=>'state','answer'=>'answer','qr'=>'qr','demo'=>'demo-crowd','reset'=>'reset','offline_pack'=>'offline-pack','offline_sync'=>'offline-sync'];if($name==='home')return app_url('');if($room===null||!isset($map[$name]))throw new InvalidArgumentException('Invalid route');return app_url('room/'.rawurlencode($room).'/'.$map[$name]);}
function absolute_route_url(string $name,?string $room=null): string {$path=route_url($name,$room);$proto=request_is_https()?'https':'http';$host=preg_replace('/[^A-Za-z0-9.:-]/','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost';return $proto.'://'.$host.$path;}
function json_response(array $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function json_request_payload(int $maxBytes=65536): array {
    $maxBytes=max(1024,$maxBytes);$declared=(int)($_SERVER['CONTENT_LENGTH']??0);if($declared>$maxBytes)json_response(['ok'=>false,'error'=>'Request body too large'],413);
    $raw=file_get_contents('php://input',false,null,0,$maxBytes+1);if(!is_string($raw))json_response(['ok'=>false,'error'=>'Request body unavailable'],400);if(strlen($raw)>$maxBytes)json_response(['ok'=>false,'error'=>'Request body too large'],413);
    if($raw==='')return [];try{$decoded=json_decode($raw,true,128,JSON_THROW_ON_ERROR);}catch(JsonException $e){json_response(['ok'=>false,'error'=>'Invalid JSON request'],400);}return is_array($decoded)?$decoded:[];
}
function request_network_fingerprint(string $scope='web'): string {
    $remote=trim((string)($_SERVER['REMOTE_ADDR']??'unknown'));$ua=text_cut(trim((string)($_SERVER['HTTP_USER_AGENT']??'')),240);return hash_hmac('sha256',$scope.'|'.$remote.'|'.$ua,app_secret());
}
function request_fingerprint(string $scope='web'): string {
    $session=session_status()===PHP_SESSION_ACTIVE?session_id():'';return hash_hmac('sha256',$scope.'|'.request_network_fingerprint($scope).'|'.$session,app_secret());
}
function rate_limit_retry_header(array $rate): void { $retry=max(1,(int)($rate['retry_after']??60));header('Retry-After: '.$retry); }
function require_room_id(?string $id): string {$id=trim((string)$id);$uuid='/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';$legacy='/^[A-Z2-9]{5}$/';if(preg_match($uuid,$id))return strtolower($id);if(preg_match($legacy,strtoupper($id)))return strtoupper($id);http_response_code(400);exit('Invalid room');}
function canonicalize_get_route(string $route,string $room): void {if($_SERVER['REQUEST_METHOD']!=='GET')return;$path=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:'';if(str_ends_with($path,'/'.basename((string)($_SERVER['SCRIPT_NAME']??'')))){header('Location: '.route_url($route,$room),true,302);exit;}}

function &udaan_session(): array {if(!isset($_SESSION['udaan_live'])||!is_array($_SESSION['udaan_live']))$_SESSION['udaan_live']=[];return $_SESSION['udaan_live'];}
function participant_id_for(string $room): ?string {$s=&udaan_session();return isset($s['participants'][$room])&&is_string($s['participants'][$room])?$s['participants'][$room]:null;}
function participant_set(string $room,string $pid): void {$s=&udaan_session();if(!isset($s['participants'])||!is_array($s['participants']))$s['participants']=[];$s['participants'][$room]=$pid;}
function host_key_for(string $room): ?string {$s=&udaan_session();return isset($s['hosts'][$room])&&is_string($s['hosts'][$room])?$s['hosts'][$room]:null;}
function host_set(string $room): void {$s=&udaan_session();if(!isset($s['hosts'])||!is_array($s['hosts']))$s['hosts']=[];$s['hosts'][$room]=rand_id(18);}
function is_room_host(string $room): bool { return host_key_for($room)!==null; }
function pending_for(string $room): ?array {$s=&udaan_session();$v=$s['pending'][$room]??null;return is_array($v)?$v:null;}
function pending_set(string $room,array $value): void {$s=&udaan_session();if(!isset($s['pending'])||!is_array($s['pending']))$s['pending']=[];$s['pending'][$room]=$value;}
function pending_remove(string $room): void {$s=&udaan_session();if(isset($s['pending'])&&is_array($s['pending']))unset($s['pending'][$room]);}
function csrf_for(string $room): string {$s=&udaan_session();if(!isset($s['csrf'])||!is_array($s['csrf']))$s['csrf']=[];if(!isset($s['csrf'][$room])||!is_string($s['csrf'][$room]))$s['csrf'][$room]=rand_id(16);return $s['csrf'][$room];}
function csrf_matches(string $room,?string $token): bool {$expected=csrf_for($room);return is_string($token)&&hash_equals($expected,$token);}
function global_csrf(): string {$s=&udaan_session();if(!isset($s['global_csrf'])||!is_string($s['global_csrf']))$s['global_csrf']=rand_id(16);return $s['global_csrf'];}
function global_csrf_matches(?string $token): bool {$s=&udaan_session();return isset($s['global_csrf'])&&is_string($s['global_csrf'])&&is_string($token)&&hash_equals($s['global_csrf'],$token);}
function clear_room_session(string $room): void {$s=&udaan_session();foreach(['hosts','participants','pending','csrf'] as $k)if(isset($s[$k])&&is_array($s[$k]))unset($s[$k][$room]);}

function journey_length_options(): array { return [21,24,27,30,36]; }
function normalize_journey_length(mixed $n): int {$n=(int)$n;return in_array($n,journey_length_options(),true)?$n:27;}
function normalize_learning_length(mixed $n): int {$n=(int)$n;if($n===9)return 9;return normalize_journey_length($n);}
function content_bank_file_path(): string { return dirname(__DIR__).'/data/content/cards.json'; }
function content_runtime_cache_file(): string { return dirname(__DIR__).'/data/content/runtime-cache.php'; }
function content_runtime_cache_build(array $bank): array {
    if(!isset($bank['cards'])||!is_array($bank['cards']))throw new RuntimeException('Cannot compile an invalid content bank.');
    $pillars=[];$futureLabels=[];$cardMap=[];
    foreach($bank['cards'] as $c){
        if(!is_array($c))continue;
        $p=(string)($c['pillar']??'other');$pillars[$p]=($pillars[$p]??0)+1;
        if(!empty($c['id']))$cardMap[(string)$c['id']]=$c;
        if($p==='future'&&!empty($c['topic'])&&!isset($futureLabels[(string)$c['topic']]))$futureLabels[(string)$c['topic']]=(string)($c['topic_name']??$c['topic']);
    }
    $source=content_bank_file_path();$compiled=[
        'schema'=>2,
        'source_mtime'=>(int)(@filemtime($source)?:0),
        'source_size'=>(int)(@filesize($source)?:0),
        'bank'=>$bank,
        'stats'=>['count'=>count($bank['cards']),'pillars'=>count($pillars),'by_pillar'=>$pillars,'version'=>$bank['version']??''],
        'future_labels'=>$futureLabels,
        'card_map'=>$cardMap,
    ];
    $php="<?php\nreturn ".var_export($compiled,true).";\n";$file=content_runtime_cache_file();$tmp=$file.'.tmp-'.bin2hex(random_bytes(5));
    if(@file_put_contents($tmp,$php,LOCK_EX)===false)throw new RuntimeException('Could not write compiled content runtime cache.');
    @chmod($tmp,0640);if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Could not publish compiled content runtime cache.');}
    @chmod($file,0640);if(function_exists('opcache_invalidate'))@opcache_invalidate($file,true);return $compiled;
}
function content_runtime_cache(): ?array {
    static $loaded=false,$cache=null;if($loaded)return $cache;$loaded=true;$source=content_bank_file_path();$file=content_runtime_cache_file();
    if(!is_file($file)||!is_file($source))return null;
    try{$data=require $file;}catch(Throwable $e){return null;}
    if(!is_array($data)||!isset($data['bank']['cards'])||!is_array($data['bank']['cards']))return null;
    if((int)($data['source_mtime']??-1)!==(int)(@filemtime($source)?:0)||(int)($data['source_size']??-1)!==(int)(@filesize($source)?:0))return null;
    return $cache=$data;
}
function content_bank(): array {
    static $bank=null;if(is_array($bank))return $bank;$compiled=content_runtime_cache();if(is_array($compiled['bank']??null))return $bank=$compiled['bank'];
    $file=content_bank_file_path();$raw=@file_get_contents($file);$data=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($data)||!isset($data['cards'])||!is_array($data['cards']))throw new RuntimeException('Content bank is unavailable.');
    return $bank=$data;
}
function cards_by_id(): array {static $map=null;if(is_array($map))return $map;$compiled=content_runtime_cache();if(is_array($compiled['card_map']??null))return $map=$compiled['card_map'];$map=[];foreach(content_bank()['cards'] as $c)if(is_array($c)&&isset($c['id']))$map[(string)$c['id']]=$c;return $map;}
function bank_stats(): array {$compiled=content_runtime_cache();if(is_array($compiled['stats']??null))return $compiled['stats'];$b=content_bank();$pillars=[];foreach($b['cards'] as $c){$p=(string)($c['pillar']??'other');$pillars[$p]=($pillars[$p]??0)+1;}return ['count'=>count($b['cards']),'pillars'=>count($pillars),'by_pillar'=>$pillars,'version'=>$b['version']??''];}
function future_topic_labels(): array {$compiled=content_runtime_cache();if(is_array($compiled['future_labels']??null))return $compiled['future_labels'];$out=[];foreach(content_bank()['cards'] as$c)if(is_array($c)&&($c['pillar']??'')==='future'&&!empty($c['topic'])&&!isset($out[(string)$c['topic']]))$out[(string)$c['topic']]=(string)($c['topic_name']??$c['topic']);return $out;}
function secure_shuffle(array $items): array {
    for($i=count($items)-1;$i>0;$i--){$j=random_int(0,$i);if($i!==$j){$tmp=$items[$i];$items[$i]=$items[$j];$items[$j]=$tmp;}}
    return $items;
}
function allocate_card_points(int $count): array {$count=max(1,$count);$base=intdiv(90,$count);$rem=90-($base*$count);$points=array_fill(0,$count,$base);$idx=secure_shuffle(range(0,$count-1));for($i=0;$i<$rem;$i++)$points[$idx[$i]]++;return $points;}
function journey_quota(int $length,array $pillars): array {$base=intdiv($length,count($pillars));$rem=$length%count($pillars);$order=secure_shuffle($pillars);$q=array_fill_keys($pillars,$base);for($i=0;$i<$rem;$i++)$q[$order[$i]]++;return $q;}
function journey_kind_targets(int $length): array {
    $length=max(1,$length);$action=min(1,$length);$choice=min(max(2,(int)round($length*.15)),max(0,$length-$action));$reveal=min(max(3,(int)round($length*.33)),max(0,$length-$action-$choice));$quiz=max(0,$length-$action-$choice-$reveal);
    return ['quiz'=>$quiz,'reveal'=>$reveal,'choice'=>$choice,'action'=>$action];
}
function journey_kind_plan(int $length): array {$t=journey_kind_targets($length);$plan=[];foreach(['quiz','reveal','choice','action'] as$k)for($i=0;$i<($t[$k]??0);$i++)$plan[]=$k;return secure_shuffle($plan);}
function card_option_order(array $card): array {if(!isset($card['options'])||!is_array($card['options']))return [];$ids=[];foreach($card['options'] as$o)if(is_array($o)&&isset($o['id']))$ids[]=(string)$o['id'];return secure_shuffle($ids);}
function apply_option_order(array $card,array $order): array {if(!$order||!isset($card['options'])||!is_array($card['options']))return $card;$map=[];foreach($card['options'] as$o)if(is_array($o)&&isset($o['id']))$map[(string)$o['id']]=$o;$out=[];foreach($order as$id)if(isset($map[(string)$id])){$out[]=$map[(string)$id];unset($map[(string)$id]);}foreach($map as$o)$out[]=$o;$card['options']=$out;$card['option_order']=array_values(array_map('strval',$order));return $card;}
function journey_fingerprint_from_cards(array $cards): string {$ids=[];foreach($cards as$c)if(is_array($c)&&!empty($c['id']))$ids[]=(string)$c['id'];return hash('sha256',implode('|',$ids));}
function journey_fingerprint_from_refs(array $refs): string {$ids=[];foreach($refs as$r)if(is_array($r)&&!empty($r['id']))$ids[]=(string)$r['id'];return hash('sha256',implode('|',$ids));}
function room_journey_fingerprints(array $room,?string $excludePid=null): array {$out=[];foreach(($room['participants']??[])as$pid=>$p){if($excludePid!==null&&(string)$pid===$excludePid)continue;if(!is_array($p))continue;$fp=(string)($p['journey_fingerprint']??'');if($fp===''&&is_array($p['journey']??null))$fp=journey_fingerprint_from_refs($p['journey']);if($fp!=='')$out[$fp]=true;}return $out;}

function build_unseen_journey(string $identity,int $length,TempStore $store,array $extraSeen=[],array $usedFingerprints=[]): array {
    $length=normalize_learning_length($length);$history=$store->getHistory($identity);$seen=is_array($history['seen_cards']??null)?$history['seen_cards']:[];$seenTopics=is_array($history['seen_topics']??null)?$history['seen_topics']:[];
    $userCache=$store->getUserCache($identity);foreach(($userCache['seen_cards']??[]) as $id=>$at)if(is_string($id)&&$id!=='')$seen[$id]=is_string($at)?$at:now_iso();foreach($extraSeen as $id)if(is_string($id)&&preg_match('/^[0-9a-f-]{36}$/i',$id))$seen[$id]=$seen[$id]??now_iso();
    $all=array_values(array_filter(content_bank()['cards'],fn($c)=>is_array($c)&&!empty($c['active'])));$groups=[];foreach($all as $c)$groups[(string)$c['pillar']][]=$c;$pillars=array_keys($groups);$quota=journey_quota($length,$pillars);$kindPlan=journey_kind_plan($length);$selected=[];$selectedIds=[];$selectedTopic=[];$globalKinds=['quiz'=>0,'reveal'=>0,'choice'=>0,'action'=>0];
    foreach($pillars as $pillar){
        $need=$quota[$pillar]??0;$pool=$groups[$pillar];
        for($slot=0;$slot<$need;$slot++){
            $desired=array_shift($kindPlan)??'quiz';
            $baseCandidates=array_values(array_filter($pool,function($c)use($selectedIds,$globalKinds){$id=(string)($c['id']??'');$kind=(string)($c['kind']??'');if($id===''||isset($selectedIds[$id]))return false;if($kind==='action'&&($globalKinds['action']??0)>=1)return false;return true;}));
            if(!$baseCandidates)break;
            $preferred=array_values(array_filter($baseCandidates,fn($c)=>(string)($c['kind']??'')===$desired));$candidates=$preferred?:$baseCandidates;$candidates=secure_shuffle($candidates);
            usort($candidates,function($a,$b)use($seen,$seenTopics,$selectedTopic,$globalKinds,$pillar,$desired){
                $score=function($c)use($seen,$seenTopics,$selectedTopic,$globalKinds,$pillar,$desired){$id=(string)$c['id'];$topic=$pillar.':'.(string)$c['topic'];$kind=(string)($c['kind']??'');$wasSeen=isset($seen[$id]);$seenAt=$wasSeen?(strtotime((string)$seen[$id])?:0):0;$kindMismatch=$kind===$desired?0:1;$kindUsage=(int)($globalKinds[$kind]??0);return [$wasSeen?1:0,isset($seenTopics[$topic])?1:0,isset($selectedTopic[$topic])?1:0,$kindMismatch,$kindUsage,$seenAt];};return $score($a)<=>$score($b);
            });
            $pick=$candidates[0];$kind=(string)$pick['kind'];$selected[]=$pick;$selectedIds[(string)$pick['id']]=true;$selectedTopic[$pillar.':'.(string)$pick['topic']]=true;$globalKinds[$kind]=($globalKinds[$kind]??0)+1;
        }
    }
    // Fill any rare shortfall while preserving the one-action hard cap.
    if(count($selected)<$length){$rest=array_values(array_filter($all,function($c)use($selectedIds,$globalKinds){$id=(string)($c['id']??'');if($id===''||isset($selectedIds[$id]))return false;return (string)($c['kind']??'')!=='action'||($globalKinds['action']??0)<1;}));$rest=secure_shuffle($rest);foreach($rest as$c){$selected[]=$c;$selectedIds[(string)$c['id']]=true;$k=(string)$c['kind'];$globalKinds[$k]=($globalKinds[$k]??0)+1;if(count($selected)>=$length)break;}}
    // Per-room sequence uniqueness: even if two learners receive the same cards, their Q&A order cannot collide.
    $used=is_array($usedFingerprints)?$usedFingerprints:[];$attempt=0;do{$selected=secure_shuffle($selected);$fingerprint=journey_fingerprint_from_cards($selected);$attempt++;}while(isset($used[$fingerprint])&&$attempt<64);
    if(isset($used[$fingerprint])){ // astronomically unlikely; reselect one card then reshuffle.
        $available=array_values(array_filter($all,fn($c)=>!isset($selectedIds[(string)($c['id']??'')])&&((string)($c['kind']??'')!=='action'||($globalKinds['action']??0)<1)));
        if($available){$replacement=secure_shuffle($available)[0];$selected[count($selected)-1]=$replacement;do{$selected=secure_shuffle($selected);$fingerprint=journey_fingerprint_from_cards($selected);$attempt++;}while(isset($used[$fingerprint])&&$attempt<128);}
    }
    if(isset($used[$fingerprint]))throw new RuntimeException('Could not allocate a unique learning sequence. Please retry.');
    $points=allocate_card_points(count($selected));$fresh=0;$actionCount=0;
    foreach($selected as $i=>&$c){$c['position']=$i+1;$c['points']=$points[$i]??0;$c['review']=isset($seen[(string)$c['id']]);if(!$c['review'])$fresh++;if(($c['kind']??'')==='action')$actionCount++;if(isset($c['options'])&&is_array($c['options'])){$order=card_option_order($c);$c=apply_option_order($c,$order);}}unset($c);
    return ['cards'=>$selected,'fresh_count'=>$fresh,'review_count'=>count($selected)-$fresh,'history_before'=>count($seen),'history_visits'=>(int)($history['visits']??0),'journey_fingerprint'=>$fingerprint,'kind_counts'=>$globalKinds,'action_count'=>$actionCount];
}
function balanced_pick_cards(array $cards,int $count): array {
    if($count<=0||!$cards)return [];$groups=[];foreach($cards as$c)if(is_array($c)&&!empty($c['pillar']))$groups[(string)$c['pillar']][]=$c;$pillars=array_keys($groups);if(!$pillars)return [];$quota=journey_quota($count,$pillars);$out=[];$used=[];
    foreach($pillars as$p){$pool=secure_shuffle($groups[$p]);$need=$quota[$p]??0;foreach($pool as$c){$id=(string)($c['id']??'');if($id===''||isset($used[$id]))continue;$out[]=$c;$used[$id]=true;if(--$need<=0)break;}}
    if(count($out)<min($count,count($cards))){$rest=secure_shuffle(array_values(array_filter($cards,fn($c)=>is_array($c)&&!isset($used[(string)($c['id']??'')]))));foreach($rest as$c){$out[]=$c;if(count($out)>=$count)break;}}
    return array_slice($out,0,$count);
}
function build_offline_reserve(string $identity,int $count,TempStore $store,array $exclude=[]): array {
    global $config;$count=max(9,$count);$history=$store->getHistory($identity);$seen=is_array($history['seen_cards']??null)?$history['seen_cards']:[];$excludeSet=array_fill_keys(array_filter(array_map('strval',$exclude)),true);
    $all=array_values(array_filter(content_bank()['cards'],fn($c)=>is_array($c)&&!empty($c['active'])&&!isset($excludeSet[(string)($c['id']??'')])&&!isset($seen[(string)($c['id']??'')])));if(!$all)return [];
    $target=min($count,count($all));$sessionSize=normalize_journey_length($config['default_journey_length']??27);
    $nonActions=array_values(array_filter($all,fn($c)=>(string)($c['kind']??'')!=='action'));$actions=array_values(array_filter($all,fn($c)=>(string)($c['kind']??'')==='action'));
    $nonTake=min(count($nonActions),$target);$maxActionsBySessions=$nonTake>0?(int)ceil($nonTake/max(1,$sessionSize-1)):1;$actionTake=min(count($actions),max(0,$target-$nonTake),$maxActionsBySessions);
    $selected=balanced_pick_cards($nonActions,$nonTake);$actionSelected=balanced_pick_cards($actions,$actionTake);
    // Arrange the reserve as local micro-sessions: no 21–36-card chunk receives more than one action prompt.
    $non=secure_shuffle($selected);$acts=secure_shuffle($actionSelected);$out=[];$ni=0;$ai=0;
    while($ni<count($non)||$ai<count($acts)){
        $chunk=[];$willAction=$ai<count($acts);$slots=max(0,$sessionSize-($willAction?1:0));for($j=0;$j<$slots&&$ni<count($non);$j++)$chunk[]=$non[$ni++];
        if($willAction){$insert=random_int(0,count($chunk));array_splice($chunk,$insert,0,[$acts[$ai++]]);}foreach($chunk as$c)$out[]=$c;
    }
    $out=array_slice($out,0,$target);foreach($out as$i=>&$c){if(isset($c['options'])&&is_array($c['options']))$c=apply_option_order($c,card_option_order($c));$c['offline_position']=$i+1;}unset($c);return $out;
}
function public_card_payload(array $c,bool $includeAnswers=false): array { $pc=['id'=>$c['id'],'pillar'=>$c['pillar'],'label'=>$c['label'],'topic'=>$c['topic']??'','topic_name'=>$c['topic_name']??'','kind'=>$c['kind'],'title'=>$c['title'],'subtitle'=>$c['subtitle']??'']; if(isset($c['options']))$pc['options']=$c['options']; if(($c['kind']??'')==='reveal')$pc['reveal']=$c['reveal']??''; if($includeAnswers){if(isset($c['correct']))$pc['correct']=$c['correct']; if(isset($c['explain']))$pc['explain']=$c['explain'];} return $pc; }

function journey_for_participant(array $participant): array {$map=cards_by_id();$out=[];foreach(($participant['journey']??[]) as $j){if(!is_array($j)||empty($j['id'])||!isset($map[$j['id']]))continue;$c=$map[$j['id']];$c['position']=(int)($j['position']??(count($out)+1));$c['points']=(int)($j['points']??0);$c['review']=(bool)($j['review']??false);if(isset($j['option_order'])&&is_array($j['option_order']))$c=apply_option_order($c,$j['option_order']);$out[]=$c;}return $out;}
function card_answer_valid(array $card,string $answer): bool {if(in_array($card['kind']??'',['choice','quiz','action'],true)){foreach(($card['options']??[]) as $o)if((string)($o['id']??'')===$answer)return true;return false;}if(($card['kind']??'')==='reveal')return $answer==='revealed';return false;}
function pillar_label(string $pillar): string {$map=['think'=>'Think & Reason','science'=>'Science & Discovery','roots'=>'Roots & India','math'=>'Maths & Logic','build'=>'Build & Create','future'=>'Future & Careers','values'=>'Values & Character','world'=>'India & World','life'=>'Life Skills'];return $map[$pillar]??ucwords(str_replace('_',' ',$pillar));}
function pillar_class(string $pillar): string {return 'pillar-'.preg_replace('/[^a-z0-9-]/','',strtolower($pillar));}
function daily_device_learning_identity(string $deviceHash): string {return $deviceHash!==''?hash_hmac('sha256','daily-device:'.$deviceHash,app_secret()):'';}
function today_key(): string {return date('Y-m-d');}
function question_of_india_card(): array {
    $eligible=array_values(array_filter(content_bank()['cards'],fn($c)=>is_array($c)&&!empty($c['active'])&&in_array((string)($c['kind']??''),['quiz','choice'],true)&&count(is_array($c['options']??null)?$c['options']:[])>=3));
    if(!$eligible)throw new RuntimeException('Question of India is unavailable.');
    usort($eligible,fn($a,$b)=>strcmp((string)$a['id'],(string)$b['id']));$seed=hexdec(substr(hash('sha256','qoi:'.today_key()),0,8));return $eligible[$seed%count($eligible)];
}
function mystery_card_for_room(array $room): array {
    $exclude=array_fill_keys(array_map('strval',is_array($room['challenge_card_ids']??null)?$room['challenge_card_ids']:[]),true);
    $eligible=array_values(array_filter(content_bank()['cards'],fn($c)=>is_array($c)&&!empty($c['active'])&&($c['kind']??'')==='reveal'&&!isset($exclude[(string)($c['id']??'')])));
    if(!$eligible)$eligible=array_values(array_filter(content_bank()['cards'],fn($c)=>is_array($c)&&!empty($c['active'])&&($c['kind']??'')==='reveal'));
    if(!$eligible)throw new RuntimeException('Mystery card is unavailable.');usort($eligible,fn($a,$b)=>strcmp((string)$a['id'],(string)$b['id']));$rid=(string)($room['id']??'');$seed=hexdec(substr(hash('sha256','mystery:'.$rid),0,8));return $eligible[$seed%count($eligible)];
}
function completion_badges(array $participant,array $room): array {
    $badges=[];$total=(int)($participant['journey_total']??0);$mode=(string)($room['mode']??'live');if($mode==='daily9'){$badges[]=['icon'=>'✦','name'=>'Daily 9 Finisher'];$badges[]=['icon'=>'◉','name'=>'9-Pillar Explorer'];}
    else{$badges[]=['icon'=>'✓','name'=>'Journey Finisher'];}
    $correct=0;foreach(($participant['answers']??[])as$a)if(is_array($a)&&($a['correct']??null)===true)$correct++;if($correct>=max(3,(int)floor($total*.45)))$badges[]=['icon'=>'🧠','name'=>'Curiosity Champion'];
    if((int)($participant['fresh_count']??0)>=(int)floor($total*.8))$badges[]=['icon'=>'◇','name'=>'Fresh Explorer'];return array_slice($badges,0,4);
}

function theme_boot_script(): string {return '<script>(function(){try{var t=localStorage.getItem("udaan-theme");if(t!=="light"&&t!=="dark")t=matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light";document.documentElement.dataset.theme=t}catch(e){document.documentElement.dataset.theme="light"}})();</script>';}
function theme_toggle(): string {return '<button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme"><span class="theme-sun" aria-hidden="true">☼</span><span class="theme-moon" aria-hidden="true">◐</span></button>';}
function common_assets_head(string $themeColor='#f3f0e9'): string {return '<meta name="theme-color" content="'.h($themeColor).'"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><link rel="manifest" href="'.h(app_url('manifest.webmanifest')).'"><link rel="apple-touch-icon" href="'.h(app_url('assets/icons/icon-192.png')).'"><link rel="stylesheet" href="'.h(app_url('assets/css/app.css?v=0.9.0')).'">'.theme_boot_script();}
function common_theme_script(): string {return '<script src="'.h(app_url('assets/js/theme.js?v=0.9.0')).'" defer></script><script src="'.h(app_url('assets/js/learning-progress.js?v=0.9.0')).'" defer></script><script src="'.h(app_url('assets/js/pwa.js?v=0.9.0')).'" defer></script>';}
