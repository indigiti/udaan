<?php
declare(strict_types=1);

final class TempStore {
    private array $config;
    private ?Redis $redis = null;
    private ?string $redisError = null;
    private string $roomDir;
    private string $historyDir;
    private string $userCacheDir;
    private string $syncOutboxDir;
    private string $aggregateDir;
    private string $rateLimitDir;
    private string $playerDir;
    private string $eventDir;
    private string $missionDir;
    private string $readinessDir;
    private string $socialDir;
    private string $competitionDir;

    public function __construct(array $config, string $roomDir) {
        $this->config = $config;
        $this->roomDir = $roomDir;
        $this->historyDir = dirname($roomDir).'/history';
        $this->userCacheDir = dirname($roomDir).'/user-cache';
        $this->syncOutboxDir = dirname($roomDir).'/sync-outbox';
        $this->aggregateDir = dirname($roomDir).'/aggregates';
        $this->rateLimitDir = dirname($roomDir).'/rate-limit';
        $this->playerDir = dirname($roomDir).'/players';
        $this->eventDir = dirname($roomDir).'/events';
        $this->missionDir = dirname($roomDir).'/missions';
        $this->readinessDir = dirname($roomDir).'/readiness';
        $this->socialDir = dirname($roomDir).'/social';
        $this->competitionDir = dirname($roomDir).'/competition';
        foreach([$this->roomDir,$this->historyDir,$this->userCacheDir,$this->syncOutboxDir,$this->aggregateDir,$this->rateLimitDir,$this->playerDir,$this->eventDir,$this->missionDir,$this->readinessDir,$this->socialDir,$this->competitionDir] as $dir) if (!is_dir($dir)) @mkdir($dir,0775,true);
        if ($config['redis']['enabled'] ?? true) {
            if (!class_exists('Redis')) {
                $this->redisError = 'extension-unavailable';
            } else {
                try {
                    $r = new Redis();
                    $r->connect((string)$config['redis']['host'],(int)$config['redis']['port'],0.25);
                    if ((string)($config['redis']['password'] ?? '') !== '') $r->auth((string)$config['redis']['password']);
                    $r->select((int)($config['redis']['database'] ?? 0));
                    $this->redis = $r;
                } catch (Throwable $e) {
                    $this->redis = null;
                    $this->redisError = 'connection-failed';
                }
            }
        }
    }

    public function backend(): string { return $this->redis ? 'Redis' : 'Temporary JSON'; }
    public function historyBackend(): string { return $this->redis ? 'Redis persistent history' : 'Pseudonymous JSON history'; }
    public function userCacheBackend(): string { return $this->redis ? 'Redis user-session cache' : 'Temporary per-user JSON cache'; }
    public function playerBackend(): string { return $this->redis ? 'Redis persistent player profiles' : 'Encrypted JSON player profiles'; }
    public function eventBackend(): string { return 'Encrypted JSONL event ledger'; }
    public function missionBackend(): string { return $this->redis ? 'Redis persistent mission state' : 'Encrypted JSON mission state'; }
    public function readinessBackend(): string { return $this->redis ? 'Redis private readiness state' : 'Encrypted JSON readiness state'; }
    public function socialBackend(): string { return $this->redis ? 'Redis partitioned encrypted social state' : 'Partitioned encrypted JSON social state'; }
    public function competitionBackend(): string { return $this->redis ? 'Redis partitioned encrypted competition state' : 'Partitioned encrypted JSON competition state'; }
    public function redisConfigured(): bool { return (bool)($this->config['redis']['enabled'] ?? true); }
    public function redisRequired(): bool { return (bool)($this->config['redis']['required'] ?? false); }
    public function redisConnected(): bool { return $this->redis instanceof Redis; }
    public function redisErrorCode(): ?string {
        if ($this->redisConnected()) return null;
        if (!$this->redisConfigured()) return 'disabled';
        return $this->redisError ?? 'unavailable';
    }
    public function runtimeStatus(): array {
        return [
            'redis_configured'=>$this->redisConfigured(),
            'redis_required'=>$this->redisRequired(),
            'redis_connected'=>$this->redisConnected(),
            'redis_status'=>$this->redisConnected()?'online':($this->redisConfigured()?'degraded':'disabled'),
            'redis_error'=>$this->redisErrorCode(),
            'room_backend'=>$this->backend(),
            'history_backend'=>$this->historyBackend(),
            'user_cache_backend'=>$this->userCacheBackend(),
            'readiness_backend'=>$this->readinessBackend(),
            'social_backend'=>$this->socialBackend(),
            'competition_backend'=>$this->competitionBackend(),
        ];
    }
    private function roomKey(string $id): string { return 'udaan:room:'.$id; }
    private function historyKey(string $identity): string { return 'udaan:history:'.$identity; }
    private function userCacheKey(string $identity): string { return 'udaan:usercache:'.$identity; }
    private function playerKey(string $identity): string { return 'udaan:player:'.$identity; }
    private function missionKey(string $identity): string { return 'udaan:missions:'.$identity; }
    private function readinessKey(string $identity): string { return 'udaan:readiness:'.$identity; }
    private function legacySocialKey(): string { return 'udaan:social:graph'; }
    private function socialVersionKey(): string { return 'udaan:social:v2:version'; }
    private function socialEntityKey(string $type,string $id): string { return 'udaan:social:v2:'.$type.':'.$id; }
    private function legacyCompetitionKey(): string { return 'udaan:competition:state'; }
    private function competitionVersionKey(): string { return 'udaan:competition:v2:version'; }
    private function competitionEntityKey(string $type,string $id): string { return 'udaan:competition:v2:'.$type.':'.$id; }
    private function roomFile(string $id): string { return $this->roomDir.'/'.$id.'.json'; }
    private function historyFile(string $identity): string { return $this->historyDir.'/'.preg_replace('/[^a-f0-9]/i','',$identity).'.json'; }
    private function userCacheFile(string $identity): string { return $this->userCacheDir.'/'.preg_replace('/[^a-f0-9]/i','',$identity).'.json'; }
    private function playerFile(string $identity): string { return $this->playerDir.'/'.preg_replace('/[^a-f0-9]/i','',$identity).'.json'; }
    private function missionFile(string $identity): string { return $this->missionDir.'/'.preg_replace('/[^a-f0-9]/i','',$identity).'.json'; }
    private function readinessFile(string $identity): string { return $this->readinessDir.'/'.preg_replace('/[^a-f0-9]/i','',$identity).'.json'; }
    private function legacySocialFile(): string { return $this->socialDir.'/graph.json'; }
    private function socialMarkerFile(): string { return $this->socialDir.'/.schema-v2'; }
    private function socialEntityFile(string $type,string $id): string { return $this->socialDir.'/'.$type.'/'.$id.'.json'; }
    private function legacyCompetitionFile(): string { return $this->competitionDir.'/state.json'; }
    private function competitionMarkerFile(): string { return $this->competitionDir.'/.schema-v2'; }
    private function competitionEntityFile(string $type,string $id): string { return $this->competitionDir.'/'.$type.'/'.$id.'.json'; }
    private function decode(mixed $raw): ?array { if (!is_string($raw) || $raw === '') return null; return secure_unpack($raw); }
    private function encode(array $data): string { return secure_pack($data); }


    public function getPlayer(string $identity): ?array {
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))return null;
        if($this->redis){$raw=$this->redis->get($this->playerKey($identity));$d=$raw===false?null:$this->decode($raw);return is_array($d)?$d:null;}
        $f=$this->playerFile($identity);if(!is_file($f))return null;$d=$this->decode(@file_get_contents($f));return is_array($d)?$d:null;
    }

    public function putPlayer(string $identity,array $player): void {
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))throw new InvalidArgumentException('Invalid player identity.');
        $encoded=$this->encode($player);
        if($this->redis){$this->redis->set($this->playerKey($identity),$encoded);return;}
        $this->atomicWrite($this->playerFile($identity),$encoded);
    }

    public function mutatePlayer(string $identity,callable $fn): array {
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))throw new InvalidArgumentException('Invalid player identity.');
        if($this->redis){
            $key=$this->playerKey($identity);
            for($i=0;$i<8;$i++){
                $this->redis->watch($key);$raw=$this->redis->get($key);$current=$raw===false?null:$this->decode($raw);
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid player mutation.');}
                $this->redis->multi();$this->redis->set($key,$this->encode($next));$ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('Player profile was busy. Please retry.');
        }
        $file=$this->playerFile($identity);$fh=fopen($file,'c+');if(!$fh)throw new RuntimeException('Player storage is unavailable.');
        flock($fh,LOCK_EX);rewind($fh);$raw=stream_get_contents($fh);$current=$this->decode($raw);$next=$fn($current);
        if(!is_array($next)){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Invalid player mutation.');}
        $encoded=$this->encode($next);rewind($fh);ftruncate($fh,0);fwrite($fh,$encoded);fflush($fh);flock($fh,LOCK_UN);fclose($fh);return $next;
    }

    public function listPlayers(int $limit=200): array {
        $limit=max(1,min(1000,$limit));$rows=[];
        if($this->redis){
            $it=null;
            do{
                $keys=$this->redis->scan($it,'udaan:player:*',100);
                if(is_array($keys))foreach($keys as $key){$raw=$this->redis->get((string)$key);$d=$raw===false?null:$this->decode($raw);if(is_array($d))$rows[]=$d;if(count($rows)>=$limit)break 2;}
            }while($it!==0&&$it!==null);
        }else{
            foreach(glob($this->playerDir.'/*.json')?:[] as $file){$d=$this->decode(@file_get_contents($file));if(is_array($d))$rows[]=$d;if(count($rows)>=$limit)break;}
        }
        usort($rows,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
        return array_slice($rows,0,$limit);
    }

    public function appendPlayerEvent(array $event): void {
        $event=array_merge(['event_id'=>uuid_v4(),'occurred_at'=>now_iso(),'schema_version'=>1],$event);
        $line=$this->encode($event);$file=$this->eventDir.'/'.date('Y-m-d').'.jsonl';$fh=fopen($file,'ab');
        if(!$fh)throw new RuntimeException('Event ledger is not writable.');
        flock($fh,LOCK_EX);fwrite($fh,$line."\n");fflush($fh);flock($fh,LOCK_UN);fclose($fh);
    }

    public function getMissionState(string $identity): array {
        $empty=['schema_version'=>1,'updated_at'=>null,'missions'=>[]];
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))return $empty;
        if($this->redis){$raw=$this->redis->get($this->missionKey($identity));$d=$raw===false?null:$this->decode($raw);return is_array($d)?array_replace($empty,$d):$empty;}
        $f=$this->missionFile($identity);if(!is_file($f))return $empty;$d=$this->decode(@file_get_contents($f));return is_array($d)?array_replace($empty,$d):$empty;
    }

    public function mutateMissionState(string $identity,callable $fn): array {
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))throw new InvalidArgumentException('Invalid mission identity.');
        if($this->redis){
            $key=$this->missionKey($identity);
            for($i=0;$i<8;$i++){
                $this->redis->watch($key);$raw=$this->redis->get($key);$current=$raw===false?null:$this->decode($raw);
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid mission state mutation.');}
                $this->redis->multi();$this->redis->set($key,$this->encode($next));$ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('Mission state was busy. Please retry.');
        }
        $file=$this->missionFile($identity);$fh=fopen($file,'c+');if(!$fh)throw new RuntimeException('Mission storage is unavailable.');
        flock($fh,LOCK_EX);rewind($fh);$raw=stream_get_contents($fh);$current=$this->decode($raw);$next=$fn($current);
        if(!is_array($next)){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Invalid mission state mutation.');}
        $encoded=$this->encode($next);rewind($fh);ftruncate($fh,0);fwrite($fh,$encoded);fflush($fh);flock($fh,LOCK_UN);fclose($fh);return $next;
    }


    public function getReadinessState(string $identity): array {
        $empty=['schema_version'=>1,'updated_at'=>null,'entries'=>[]];
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))return $empty;
        if($this->redis){$raw=$this->redis->get($this->readinessKey($identity));$d=$raw===false?null:$this->decode($raw);return is_array($d)?array_replace($empty,$d):$empty;}
        $f=$this->readinessFile($identity);if(!is_file($f))return $empty;$d=$this->decode(@file_get_contents($f));return is_array($d)?array_replace($empty,$d):$empty;
    }

    public function mutateReadinessState(string $identity,callable $fn): array {
        if(!preg_match('/^[a-f0-9]{64}$/i',$identity))throw new InvalidArgumentException('Invalid readiness identity.');
        if($this->redis){
            $key=$this->readinessKey($identity);
            for($i=0;$i<8;$i++){
                $this->redis->watch($key);$raw=$this->redis->get($key);$current=$raw===false?null:$this->decode($raw);
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid readiness mutation.');}
                $this->redis->multi();$this->redis->set($key,$this->encode($next));$ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('Readiness state was busy. Please retry.');
        }
        $file=$this->readinessFile($identity);$fh=fopen($file,'c+');if(!$fh)throw new RuntimeException('Readiness storage is unavailable.');
        flock($fh,LOCK_EX);rewind($fh);$raw=stream_get_contents($fh);$current=$this->decode($raw);$next=$fn($current);
        if(!is_array($next)){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Invalid readiness mutation.');}
        $encoded=$this->encode($next);rewind($fh);ftruncate($fh,0);fwrite($fh,$encoded);fflush($fh);flock($fh,LOCK_UN);fclose($fh);return $next;
    }


    private function redisPartitionEntities(string $pattern,string $prefix): array {
        $rows=[];$it=null;
        do{
            $keys=$this->redis?->scan($it,$pattern,250);
            if(is_array($keys))foreach($keys as$key){$raw=$this->redis?->get((string)$key);$d=$raw===false?null:$this->decode($raw);if(!is_array($d))continue;$id=substr((string)$key,strlen($prefix));if($id!=='')$rows[$id]=$d;}
        }while($it!==0&&$it!==null);
        return $rows;
    }

    private function filePartitionEntities(string $dir): array {
        $rows=[];foreach(glob($dir.'/*.json')?:[] as$file){$d=$this->decode(@file_get_contents($file));if(!is_array($d))continue;$id=basename($file,'.json');if($id!=='')$rows[$id]=$d;}return $rows;
    }

    private function queueRedisEntityDiff(string $scope,string $type,array $current,array $next): void {
        foreach($next as$id=>$value){
            $id=(string)$id;if($id===''||!is_array($value))continue;
            if(!isset($current[$id])||$current[$id]!=$value)$this->redis?->set($scope==='social'?$this->socialEntityKey($type,$id):$this->competitionEntityKey($type,$id),$this->encode($value));
        }
        foreach($current as$id=>$value)if(!array_key_exists($id,$next))$this->redis?->del($scope==='social'?$this->socialEntityKey($type,(string)$id):$this->competitionEntityKey($type,(string)$id));
    }

    private function persistFileEntityDiff(string $base,string $type,array $current,array $next): void {
        $dir=$base.'/'.$type;if(!is_dir($dir)&&!@mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Partition directory is unavailable.');
        foreach($next as$id=>$value){
            $id=preg_replace('/[^A-Za-z0-9_-]/','',(string)$id)??'';if($id===''||!is_array($value))continue;
            if(!isset($current[$id])||$current[$id]!=$value)$this->atomicWrite($dir.'/'.$id.'.json',$this->encode($value));
        }
        foreach($current as$id=>$value){$safe=preg_replace('/[^A-Za-z0-9_-]/','',(string)$id)??'';if($safe!==''&&!array_key_exists($id,$next))@unlink($dir.'/'.$safe.'.json');}
    }

    public function getSocialGraph(): array {
        $empty=['schema_version'=>1,'updated_at'=>null,'friendships'=>[],'invites'=>[],'teams'=>[]];
        if($this->redis){
            if($this->redis->get($this->socialVersionKey())!==false){
                $graph=$empty;
                $graph['friendships']=$this->redisPartitionEntities('udaan:social:v2:friend:*','udaan:social:v2:friend:');
                $graph['invites']=$this->redisPartitionEntities('udaan:social:v2:invite:*','udaan:social:v2:invite:');
                $graph['teams']=$this->redisPartitionEntities('udaan:social:v2:team:*','udaan:social:v2:team:');
                return $graph;
            }
            $raw=$this->redis->get($this->legacySocialKey());$d=$raw===false?null:$this->decode($raw);return is_array($d)?array_replace($empty,$d):$empty;
        }
        if(is_file($this->socialMarkerFile())){
            $graph=$empty;
            $graph['friendships']=$this->filePartitionEntities($this->socialDir.'/friend');
            $graph['invites']=$this->filePartitionEntities($this->socialDir.'/invite');
            $graph['teams']=$this->filePartitionEntities($this->socialDir.'/team');
            return $graph;
        }
        $f=$this->legacySocialFile();if(!is_file($f))return $empty;$d=$this->decode(@file_get_contents($f));return is_array($d)?array_replace($empty,$d):$empty;
    }

    public function mutateSocialGraph(callable $fn): array {
        if($this->redis){
            $versionKey=$this->socialVersionKey();
            for($i=0;$i<8;$i++){
                $this->redis->watch($versionKey);$versionRaw=$this->redis->get($versionKey);$current=$this->getSocialGraph();
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid social graph mutation.');}
                $next=array_replace(['schema_version'=>1,'updated_at'=>now_iso(),'friendships'=>[],'invites'=>[],'teams'=>[]],$next);
                foreach(['friendships','invites','teams'] as$k)if(!is_array($next[$k]))$next[$k]=[];
                $this->redis->multi();
                $this->queueRedisEntityDiff('social','friend',(array)($current['friendships']??[]),(array)$next['friendships']);
                $this->queueRedisEntityDiff('social','invite',(array)($current['invites']??[]),(array)$next['invites']);
                $this->queueRedisEntityDiff('social','team',(array)($current['teams']??[]),(array)$next['teams']);
                $this->redis->set($versionKey,(string)(((int)$versionRaw)+1));
                if($versionRaw===false)$this->redis->del($this->legacySocialKey());
                $ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('Social state was busy. Please retry.');
        }
        $lock=@fopen($this->socialDir.'/.partition.lock','c+');if(!$lock)throw new RuntimeException('Social state storage is unavailable.');
        if(!flock($lock,LOCK_EX)){fclose($lock);throw new RuntimeException('Social state lock is unavailable.');}
        try{
            $current=$this->getSocialGraph();$next=$fn($current);if(!is_array($next))throw new RuntimeException('Invalid social graph mutation.');
            $next=array_replace(['schema_version'=>1,'updated_at'=>now_iso(),'friendships'=>[],'invites'=>[],'teams'=>[]],$next);
            foreach(['friendships','invites','teams'] as$k)if(!is_array($next[$k]))$next[$k]=[];
            $this->persistFileEntityDiff($this->socialDir,'friend',(array)($current['friendships']??[]),(array)$next['friendships']);
            $this->persistFileEntityDiff($this->socialDir,'invite',(array)($current['invites']??[]),(array)$next['invites']);
            $this->persistFileEntityDiff($this->socialDir,'team',(array)($current['teams']??[]),(array)$next['teams']);
            if(@file_put_contents($this->socialMarkerFile(),"2\n",LOCK_EX)===false)throw new RuntimeException('Could not activate social partitions.');@chmod($this->socialMarkerFile(),0640);
            return $next;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public function getCompetitionState(): array {
        $empty=['schema_version'=>1,'updated_at'=>null,'leagues'=>[],'invites'=>[]];
        if($this->redis){
            if($this->redis->get($this->competitionVersionKey())!==false){
                $state=$empty;
                $state['leagues']=$this->redisPartitionEntities('udaan:competition:v2:league:*','udaan:competition:v2:league:');
                $state['invites']=$this->redisPartitionEntities('udaan:competition:v2:invite:*','udaan:competition:v2:invite:');
                return $state;
            }
            $raw=$this->redis->get($this->legacyCompetitionKey());$d=$raw===false?null:$this->decode($raw);return is_array($d)?array_replace($empty,$d):$empty;
        }
        if(is_file($this->competitionMarkerFile())){
            $state=$empty;
            $state['leagues']=$this->filePartitionEntities($this->competitionDir.'/league');
            $state['invites']=$this->filePartitionEntities($this->competitionDir.'/invite');
            return $state;
        }
        $f=$this->legacyCompetitionFile();if(!is_file($f))return $empty;$d=$this->decode(@file_get_contents($f));return is_array($d)?array_replace($empty,$d):$empty;
    }

    public function mutateCompetitionState(callable $fn): array {
        if($this->redis){
            $versionKey=$this->competitionVersionKey();
            for($i=0;$i<8;$i++){
                $this->redis->watch($versionKey);$versionRaw=$this->redis->get($versionKey);$current=$this->getCompetitionState();
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid competition state mutation.');}
                $next=array_replace(['schema_version'=>1,'updated_at'=>now_iso(),'leagues'=>[],'invites'=>[]],$next);
                foreach(['leagues','invites'] as$k)if(!is_array($next[$k]))$next[$k]=[];
                $this->redis->multi();
                $this->queueRedisEntityDiff('competition','league',(array)($current['leagues']??[]),(array)$next['leagues']);
                $this->queueRedisEntityDiff('competition','invite',(array)($current['invites']??[]),(array)$next['invites']);
                $this->redis->set($versionKey,(string)(((int)$versionRaw)+1));
                if($versionRaw===false)$this->redis->del($this->legacyCompetitionKey());
                $ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('Competition state was busy. Please retry.');
        }
        $lock=@fopen($this->competitionDir.'/.partition.lock','c+');if(!$lock)throw new RuntimeException('Competition state storage is unavailable.');
        if(!flock($lock,LOCK_EX)){fclose($lock);throw new RuntimeException('Competition state lock is unavailable.');}
        try{
            $current=$this->getCompetitionState();$next=$fn($current);if(!is_array($next))throw new RuntimeException('Invalid competition state mutation.');
            $next=array_replace(['schema_version'=>1,'updated_at'=>now_iso(),'leagues'=>[],'invites'=>[]],$next);
            foreach(['leagues','invites'] as$k)if(!is_array($next[$k]))$next[$k]=[];
            $this->persistFileEntityDiff($this->competitionDir,'league',(array)($current['leagues']??[]),(array)$next['leagues']);
            $this->persistFileEntityDiff($this->competitionDir,'invite',(array)($current['invites']??[]),(array)$next['invites']);
            if(@file_put_contents($this->competitionMarkerFile(),"2\n",LOCK_EX)===false)throw new RuntimeException('Could not activate competition partitions.');@chmod($this->competitionMarkerFile(),0640);
            return $next;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public function get(string $id): ?array {
        if ($this->redis) { $v=$this->redis->get($this->roomKey($id)); return $v===false?null:$this->decode($v); }
        $f=$this->roomFile($id); if(!is_file($f)) return null; $d=$this->decode(@file_get_contents($f));
        if($d && isset($d['expires_at']) && strtotime((string)$d['expires_at']) < time()){ @unlink($f); return null; }
        return $d;
    }

    public function put(string $id,array $room): void {
        $ttl=(int)($this->config['room_ttl_seconds']??21600);$encoded=$this->encode($room);
        if($this->redis){$this->redis->setex($this->roomKey($id),$ttl,$encoded);return;}$this->atomicWrite($this->roomFile($id),$encoded);
    }

    public function mutate(string $id, callable $fn): ?array {
        if($this->redis){$k=$this->roomKey($id);for($i=0;$i<8;$i++){$this->redis->watch($k);$raw=$this->redis->get($k);if($raw===false){$this->redis->unwatch();return null;}$room=$this->decode($raw);if(!$room){$this->redis->unwatch();return null;}$next=$fn($room);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid room mutation.');}$encoded=$this->encode($next);$this->redis->multi();$this->redis->setex($k,(int)$this->config['room_ttl_seconds'],$encoded);$ok=$this->redis->exec();if($ok!==false)return $next;}throw new RuntimeException('Room state was busy. Please retry.');}
        $f=$this->roomFile($id);if(!is_file($f))return null;$fh=fopen($f,'c+');if(!$fh)return null;flock($fh,LOCK_EX);rewind($fh);$raw=stream_get_contents($fh);$room=$this->decode($raw);if(!$room){flock($fh,LOCK_UN);fclose($fh);return null;}$next=$fn($room);if(!is_array($next)){flock($fh,LOCK_UN);fclose($fh);throw new RuntimeException('Invalid room mutation.');}$encoded=$this->encode($next);rewind($fh);ftruncate($fh,0);fwrite($fh,$encoded);fflush($fh);flock($fh,LOCK_UN);fclose($fh);return $next;
    }

    public function delete(string $id): void { if($this->redis)$this->redis->del($this->roomKey($id)); else @unlink($this->roomFile($id)); }


    public function rateLimit(string $scope,string $identity,int $limit,int $windowSeconds): array {
        $scope=preg_replace('/[^a-z0-9:_-]/i','',strtolower($scope))?:'general';$limit=max(1,$limit);$windowSeconds=max(1,$windowSeconds);$now=time();
        $fingerprint=hash('sha256',$scope.'|'.$identity);
        if($this->redis){
            $key='udaan:ratelimit:'.$scope.':'.$fingerprint;
            $count=(int)$this->redis->incr($key);
            if($count===1)$this->redis->expire($key,$windowSeconds);
            $ttl=(int)$this->redis->ttl($key);if($ttl<0){$this->redis->expire($key,$windowSeconds);$ttl=$windowSeconds;}
            return ['allowed'=>$count<=$limit,'count'=>$count,'remaining'=>max(0,$limit-$count),'retry_after'=>max(1,$ttl)];
        }
        $file=$this->rateLimitDir.'/'.$fingerprint.'.json';$fh=fopen($file,'c+');if(!$fh)throw new RuntimeException('Rate-limit storage is unavailable.');
        flock($fh,LOCK_EX);rewind($fh);$raw=stream_get_contents($fh);$d=is_string($raw)?json_decode($raw,true):null;$reset=(int)($d['reset']??0);$count=(int)($d['count']??0);
        if($reset<=$now){$reset=$now+$windowSeconds;$count=0;}$count++;$payload=json_encode(['count'=>$count,'reset'=>$reset],JSON_UNESCAPED_SLASHES);
        rewind($fh);ftruncate($fh,0);fwrite($fh,$payload?:'{}');fflush($fh);flock($fh,LOCK_UN);fclose($fh);
        if($count===1&&random_int(1,50)===1){foreach(glob($this->rateLimitDir.'/*.json')?:[] as$old){$m=@filemtime($old);if(is_int($m)&&$m<($now-86400))@unlink($old);}}
        return ['allowed'=>$count<=$limit,'count'=>$count,'remaining'=>max(0,$limit-$count),'retry_after'=>max(1,$reset-$now)];
    }

    public function getHistory(string $identity): array {
        $empty=['seen_cards'=>[],'seen_topics'=>[],'visits'=>0,'updated_at'=>null];
        if($this->redis){$raw=$this->redis->get($this->historyKey($identity));$d=$raw===false?null:$this->decode($raw);return is_array($d)?array_replace($empty,$d):$empty;}
        $f=$this->historyFile($identity);if(!is_file($f))return $empty;$d=$this->decode(@file_get_contents($f));return is_array($d)?array_replace($empty,$d):$empty;
    }

    public function mutateHistory(string $identity,callable $fn): array {
        $empty=['seen_cards'=>[],'seen_topics'=>[],'visits'=>0,'updated_at'=>null];
        if($this->redis){
            $key=$this->historyKey($identity);
            for($i=0;$i<8;$i++){
                $this->redis->watch($key);$raw=$this->redis->get($key);$current=$raw===false?[]:($this->decode($raw)??[]);$current=array_replace($empty,$current);
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid learning-history mutation.');}
                $this->redis->multi();$this->redis->set($key,$this->encode($next));$ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('Learning history was busy. Please retry.');
        }
        $file=$this->historyFile($identity);$lock=@fopen($file.'.lock','c+');if(!$lock)throw new RuntimeException('Learning-history storage is not writable.');
        if(!flock($lock,LOCK_EX)){fclose($lock);throw new RuntimeException('Learning-history storage lock is unavailable.');}
        try{$raw=is_file($file)?@file_get_contents($file):'';$current=$this->decode($raw)??[];$current=array_replace($empty,$current);$next=$fn($current);if(!is_array($next))throw new RuntimeException('Invalid learning-history mutation.');$this->atomicWrite($file,$this->encode($next));return $next;}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public function markJourneySeen(string $identity,array $cards): array {
        return $this->mutateHistory($identity,function(array $h)use($cards):array{if(!isset($h['seen_cards'])||!is_array($h['seen_cards']))$h['seen_cards']=[];if(!isset($h['seen_topics'])||!is_array($h['seen_topics']))$h['seen_topics']=[];$at=now_iso();foreach($cards as$c){if(!is_array($c)||empty($c['id']))continue;$h['seen_cards'][(string)$c['id']]=$at;if(!empty($c['pillar'])&&!empty($c['topic']))$h['seen_topics'][(string)$c['pillar'].':'.(string)$c['topic']]=$at;}$h['visits']=(int)($h['visits']??0)+1;$h['updated_at']=$at;return $h;});
    }

    public function getUserCache(string $identity): array {
        $empty=['version'=>1,'updated_at'=>null,'expires_at'=>null,'sessions'=>[],'seen_cards'=>[]];
        if($this->redis){$raw=$this->redis->get($this->userCacheKey($identity));$d=$raw===false?null:$this->decode($raw);return is_array($d)?array_replace($empty,$d):$empty;}
        $f=$this->userCacheFile($identity);if(!is_file($f))return $empty;$d=$this->decode(@file_get_contents($f));if(!is_array($d))return $empty;if(!empty($d['expires_at'])&&strtotime((string)$d['expires_at'])<time()){@unlink($f);return $empty;}return array_replace($empty,$d);
    }

    public function mutateUserCache(string $identity,callable $fn): array {
        $empty=['version'=>1,'updated_at'=>null,'expires_at'=>null,'sessions'=>[],'seen_cards'=>[]];$ttl=max(60,(int)($this->config['user_cache_ttl_seconds']??86400));
        $finalize=function(array $cache)use($ttl):array{$cache['version']=(int)($cache['version']??1);$cache['updated_at']=now_iso();$cache['expires_at']=date(DATE_ATOM,time()+$ttl);return $cache;};
        if($this->redis){
            $key=$this->userCacheKey($identity);
            for($i=0;$i<8;$i++){
                $this->redis->watch($key);$raw=$this->redis->get($key);$current=$raw===false?[]:($this->decode($raw)??[]);
                if(!empty($current['expires_at'])&&strtotime((string)$current['expires_at'])<time())$current=[];$current=array_replace($empty,$current);
                $next=$fn($current);if(!is_array($next)){$this->redis->unwatch();throw new RuntimeException('Invalid user-cache mutation.');}$next=$finalize($next);
                $this->redis->multi();$this->redis->setex($key,$ttl,$this->encode($next));$ok=$this->redis->exec();if($ok!==false)return $next;
            }
            throw new RuntimeException('User cache was busy. Please retry.');
        }
        $file=$this->userCacheFile($identity);$lock=@fopen($file.'.lock','c+');if(!$lock)throw new RuntimeException('User-cache storage is not writable.');
        if(!flock($lock,LOCK_EX)){fclose($lock);throw new RuntimeException('User-cache storage lock is unavailable.');}
        try{$raw=is_file($file)?@file_get_contents($file):'';$current=$this->decode($raw)??[];if(!empty($current['expires_at'])&&strtotime((string)$current['expires_at'])<time())$current=[];$current=array_replace($empty,$current);$next=$fn($current);if(!is_array($next))throw new RuntimeException('Invalid user-cache mutation.');$next=$finalize($next);$this->atomicWrite($file,$this->encode($next));return $next;}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public function cacheJourneySession(string $identity,string $roomId,array $refs,array $answers=[],int $score=10): array {
        $session=[];$this->mutateUserCache($identity,function(array $c)use($roomId,$refs,$answers,$score,&$session):array{if(!isset($c['sessions'])||!is_array($c['sessions']))$c['sessions']=[];if(!isset($c['seen_cards'])||!is_array($c['seen_cards']))$c['seen_cards']=[];$seenAt=now_iso();foreach($refs as$r)if(is_array($r)&&!empty($r['id']))$c['seen_cards'][(string)$r['id']]=$seenAt;$session=['room_id'=>$roomId,'journey'=>$refs,'answers'=>$answers,'score'=>$score,'answered_count'=>count($answers),'completed'=>false,'created_at'=>$c['sessions'][$roomId]['created_at']??now_iso(),'updated_at'=>now_iso()];$c['sessions'][$roomId]=$session;return $c;});return $session;
    }

    public function getCachedSession(string $identity,string $roomId): ?array {$c=$this->getUserCache($identity);$s=$c['sessions'][$roomId]??null;return is_array($s)?$s:null;}

    public function cacheAnswer(string $identity,string $roomId,string $cardId,array $answer,int $score,int $answeredCount): void {
        $this->mutateUserCache($identity,function(array $c)use($roomId,$cardId,$answer,$score,$answeredCount):array{if(!isset($c['sessions'][$roomId])||!is_array($c['sessions'][$roomId]))return $c;$c['sessions'][$roomId]['answers'][$cardId]=$answer;$c['sessions'][$roomId]['score']=$score;$c['sessions'][$roomId]['answered_count']=$answeredCount;$c['sessions'][$roomId]['updated_at']=now_iso();return $c;});
    }

    public function cacheComplete(string $identity,string $roomId,int $score,int $answeredCount): void {
        $this->mutateUserCache($identity,function(array $c)use($roomId,$score,$answeredCount):array{if(!isset($c['sessions'][$roomId])||!is_array($c['sessions'][$roomId]))return $c;$c['sessions'][$roomId]['completed']=true;$c['sessions'][$roomId]['score']=$score;$c['sessions'][$roomId]['answered_count']=$answeredCount;$c['sessions'][$roomId]['completed_at']=now_iso();return $c;});
    }

    public function cacheDevice(string $identity,string $deviceHash): void {
        if($deviceHash==='')return;$this->mutateUserCache($identity,function(array $c)use($deviceHash):array{if(!isset($c['devices'])||!is_array($c['devices']))$c['devices']=[];$d=is_array($c['devices'][$deviceHash]??null)?$c['devices'][$deviceHash]:[];$c['devices'][$deviceHash]=array_replace(['first_seen'=>now_iso()],$d,['last_seen'=>now_iso()]);return $c;});
    }

    public function getOfflineReserve(string $identity,string $deviceHash): ?array {
        $c=$this->getUserCache($identity); $r=$c['devices'][$deviceHash]['offline_reserve']??null; if(!is_array($r))return null; if(!empty($r['expires_at'])&&strtotime((string)$r['expires_at'])<time())return null; return $r;
    }

    public function cacheOfflineReserve(string $identity,string $deviceHash,array $cardRefs,int $ttl): array {
        $refs=[];$ids=[];foreach($cardRefs as$r){if(is_array($r)&&!empty($r['id'])){$id=(string)$r['id'];$refs[]=['id'=>$id,'option_order'=>array_values(array_map('strval',is_array($r['option_order']??null)?$r['option_order']:[]))];$ids[]=$id;}elseif(is_string($r)&&$r!==''){$refs[]=['id'=>$r,'option_order'=>[]];$ids[]=$r;}}
        $reserve=[];$this->mutateUserCache($identity,function(array $c)use($deviceHash,$refs,$ids,$ttl,&$reserve):array{if(!isset($c['devices'])||!is_array($c['devices']))$c['devices']=[];if(!isset($c['devices'][$deviceHash])||!is_array($c['devices'][$deviceHash]))$c['devices'][$deviceHash]=['first_seen'=>now_iso()];$reserve=['reserve_id'=>uuid_v4(),'card_ids'=>$ids,'card_refs'=>$refs,'created_at'=>now_iso(),'expires_at'=>date(DATE_ATOM,time()+$ttl)];$c['devices'][$deviceHash]['offline_reserve']=$reserve;$c['devices'][$deviceHash]['last_seen']=now_iso();return $c;});return $reserve;
    }

    public function syncOfflineEvents(string $identity,string $deviceHash,array $events,string $reserveId=''): array {
        $bank=cards_by_id();$result=['accepted'=>[],'duplicates'=>[],'rejected'=>[]];$acceptedEvents=[];$seenCards=[];
        $this->mutateUserCache($identity,function(array $c)use($identity,$deviceHash,$events,$reserveId,$bank,&$result,&$acceptedEvents,&$seenCards):array{
            if(!isset($c['offline_event_ids'])||!is_array($c['offline_event_ids']))$c['offline_event_ids']=[];
            $result=['accepted'=>[],'duplicates'=>[],'rejected'=>[]];$acceptedEvents=[];$seenCards=[];
            $reserve=$c['devices'][$deviceHash]['offline_reserve']??null;$reserveActive=is_array($reserve)&&(!isset($reserve['expires_at'])||strtotime((string)$reserve['expires_at'])>=time());
            $reserveMatches=$reserveActive&&$reserveId!==''&&isset($reserve['reserve_id'])&&hash_equals((string)$reserve['reserve_id'],$reserveId);
            $reserveCards=$reserveMatches?array_fill_keys(array_map('strval',is_array($reserve['card_ids']??null)?$reserve['card_ids']:[]),true):[];
            foreach(array_slice($events,0,250) as$ev){
                if(!is_array($ev))continue;$eid=(string)($ev['event_id']??'');$cid=(string)($ev['card_id']??'');$ans=is_string($ev['answer']??null)?(string)$ev['answer']:'';$source=(string)($ev['source']??'offline_reserve');
                if(!preg_match('/^[0-9a-f-]{36}$/i',$eid)||!isset($bank[$cid])||!card_answer_valid($bank[$cid],$ans)){$result['rejected'][]=$eid?:'invalid';continue;}
                if(isset($c['offline_event_ids'][$eid])){$result['duplicates'][]=$eid;continue;}
                $authorized=false;
                if($source==='offline_reserve')$authorized=$reserveMatches&&isset($reserveCards[$cid]);
                elseif($source==='journey_offline'){
                    $roomId=(string)($ev['room_id']??'');$session=$c['sessions'][$roomId]??null;
                    if(is_array($session)&&is_array($session['journey']??null))foreach($session['journey'] as$ref){if(is_array($ref)&&(string)($ref['id']??'')===$cid){$authorized=true;break;}}
                }
                if(!$authorized){$result['rejected'][]=$eid;continue;}
                $c['offline_event_ids'][$eid]=now_iso();$result['accepted'][]=$eid;$seenCards[]=$bank[$cid];
                $acceptedEvents[]=['event_id'=>$eid,'event_type'=>'offline_card_answered','user_hash'=>$identity,'device_hash'=>$deviceHash,'card_id'=>$cid,'answer_id'=>$ans,'correct'=>(($bank[$cid]['kind']??'')==='quiz'?hash_equals((string)($bank[$cid]['correct']??''),$ans):null),'source'=>$source,'client_occurred_at'=>(string)($ev['occurred_at']??'')];
            }
            if(count($c['offline_event_ids'])>4000)$c['offline_event_ids']=array_slice($c['offline_event_ids'],-3000,null,true);return $c;
        });
        foreach($acceptedEvents as$event)$this->appendSyncEvent($event);if($seenCards)$this->markCardsSeen($identity,$seenCards);return $result;
    }

    public function markCardsSeen(string $identity,array $cards): void {
        $this->mutateHistory($identity,function(array $h)use($cards):array{if(!isset($h['seen_cards'])||!is_array($h['seen_cards']))$h['seen_cards']=[];if(!isset($h['seen_topics'])||!is_array($h['seen_topics']))$h['seen_topics']=[];$at=now_iso();foreach($cards as$c){if(!is_array($c)||empty($c['id']))continue;$h['seen_cards'][(string)$c['id']]=$at;if(!empty($c['pillar'])&&!empty($c['topic']))$h['seen_topics'][(string)$c['pillar'].':'.(string)$c['topic']]=$at;}$h['updated_at']=$at;return $h;});
    }

    private function aggregateFile(string $key): string { return $this->aggregateDir.'/'.hash('sha256',$key).'.json'; }

    public function recordAggregateResponse(string $key,string $answer,string $responderHash=''): array {
        $file=$this->aggregateFile($key);$fh=fopen($file,'c+');if(!$fh)throw new RuntimeException('Aggregate storage is not writable.');flock($fh,LOCK_EX);rewind($fh);$raw=stream_get_contents($fh);$d=$this->decode($raw)??['key'=>$key,'total'=>0,'counts'=>[],'responders'=>[],'updated_at'=>null];
        if(!isset($d['counts'])||!is_array($d['counts']))$d['counts']=[];if(!isset($d['responders'])||!is_array($d['responders']))$d['responders']=[];$dedupe=$responderHash!==''?hash_hmac('sha256','aggregate:'.$key.':'.$responderHash,app_secret()):'';
        if($dedupe===''||!isset($d['responders'][$dedupe])){$d['total']=(int)($d['total']??0)+1;$d['counts'][$answer]=(int)($d['counts'][$answer]??0)+1;if($dedupe!=='')$d['responders'][$dedupe]=now_iso();}
        if(count($d['responders'])>25000)$d['responders']=array_slice($d['responders'],-20000,null,true);$d['updated_at']=now_iso();$encoded=$this->encode($d);rewind($fh);ftruncate($fh,0);fwrite($fh,$encoded);fflush($fh);flock($fh,LOCK_UN);fclose($fh);return $this->aggregatePublic($d);
    }

    public function getAggregate(string $key): array {
        $f=$this->aggregateFile($key);if(!is_file($f))return ['total'=>0,'counts'=>[],'percentages'=>[],'top'=>null];$d=$this->decode(@file_get_contents($f));return is_array($d)?$this->aggregatePublic($d):['total'=>0,'counts'=>[],'percentages'=>[],'top'=>null];
    }

    private function aggregatePublic(array $d): array {
        $total=max(0,(int)($d['total']??0));$counts=is_array($d['counts']??null)?$d['counts']:[];$pct=[];$top=null;$topN=-1;foreach($counts as$k=>$n){$n=(int)$n;$pct[(string)$k]=$total?round($n/$total*100):0;if($n>$topN){$topN=$n;$top=(string)$k;}}return ['total'=>$total,'counts'=>$counts,'percentages'=>$pct,'top'=>$top,'updated_at'=>$d['updated_at']??null];
    }

    public function appendSyncEvent(array $event): void {
        $event=array_merge(['event_id'=>uuid_v4(),'occurred_at'=>now_iso(),'schema_version'=>1],$event);
        $line=secure_pack($event);
        $file=$this->syncOutboxDir.'/'.date('Y-m-d').'.jsonl';$fh=fopen($file,'ab');if(!$fh)throw new RuntimeException('Sync outbox is not writable.');flock($fh,LOCK_EX);fwrite($fh,$line."\n");fflush($fh);flock($fh,LOCK_UN);fclose($fh);
    }

    public function cleanupUserCacheFiles(): int {
        if($this->redis)return 0;$n=0;foreach(glob($this->userCacheDir.'/*.json')?:[] as$f){$d=$this->decode(@file_get_contents($f));if(!$d||(!empty($d['expires_at'])&&strtotime((string)$d['expires_at'])<time())){if(@unlink($f))$n++;}}return $n;
    }

    private function atomicWrite(string $file,string $encoded): void {$tmp=$file.'.tmp.'.getmypid().'.'.bin2hex(random_bytes(3));if(file_put_contents($tmp,$encoded,LOCK_EX)===false||!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Storage is not writable.');}@chmod($file,0640);}
}
