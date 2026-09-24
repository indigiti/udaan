<?php
declare(strict_types=1);

function content_admin_key_path(): string { return data_path('content-admin.key'); }
function content_admin_key(): string {
    $env = trim((string)(getenv('UDAAN_CONTENT_ADMIN_KEY') ?: ''));
    if (strlen($env) >= 12) return $env;
    $file = content_admin_key_path();
    $raw = @file_get_contents($file);
    if (is_string($raw) && strlen(trim($raw)) >= 12) {
        @chmod($file, 0600);
        return trim($raw);
    }
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $key = '';
    for ($i=0; $i<24; $i++) $key .= $alphabet[random_int(0, strlen($alphabet)-1)];
    if (@file_put_contents($file, $key . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to initialize Content Admin access key. Make the data/ folder writable.');
    }
    @chmod($file, 0600);
    return $key;
}
function content_admin_authenticated(): bool {
    $s =& udaan_session();
    $stored = $s['content_admin_hash'] ?? null;
    if (!is_string($stored)) return false;
    return hash_equals(hash('sha256', content_admin_key()), $stored);
}
function content_admin_login(string $key): bool {
    $expected = content_admin_key();
    if (!hash_equals($expected, trim($key))) return false;
    $s =& udaan_session();
    $s['content_admin_hash'] = hash('sha256', $expected);
    session_regenerate_id(true);
    return true;
}
function content_admin_logout(): void { $s =& udaan_session(); unset($s['content_admin_hash'], $s['content_admin_csrf'], $s['content_import_pending']); }
function content_admin_csrf(): string {
    $s =& udaan_session();
    if (!isset($s['content_admin_csrf']) || !is_string($s['content_admin_csrf'])) $s['content_admin_csrf'] = rand_id(20);
    return $s['content_admin_csrf'];
}
function content_admin_csrf_matches(?string $token): bool { $s =& udaan_session(); return isset($s['content_admin_csrf']) && is_string($s['content_admin_csrf']) && is_string($token) && hash_equals($s['content_admin_csrf'], $token); }

function import_pillars(): array {
    return [
        'think'=>'Think & Reason','science'=>'Science & Discovery','roots'=>'Roots & India','math'=>'Maths & Logic',
        'build'=>'Build & Create','future'=>'Future & Careers','values'=>'Values & Character','world'=>'India & World','life'=>'Life Skills & Responsibility'
    ];
}
function import_pillar_label(string $pillar): string { return strtoupper(import_pillars()[$pillar] ?? $pillar); }
function import_kinds(): array { return ['quiz','reveal','choice','action']; }
function is_standard_uuid(string $id): bool { return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id); }
function import_norm_text(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\s]+/u', ' ', $s) ?? $s;
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    return trim($s);
}
function import_tokens(string $s): array {
    $stop = ['the','a','an','and','or','of','to','in','on','for','is','are','what','which','why','how','with','this','that','your','you'];
    $tokens = array_values(array_filter(explode(' ', import_norm_text($s)), fn($x)=>strlen($x)>=3 && !in_array($x,$stop,true)));
    return array_values(array_unique($tokens));
}
function import_similarity(string $a, string $b): float {
    $aa = import_tokens($a); $bb = import_tokens($b);
    if (!$aa || !$bb) return import_norm_text($a) === import_norm_text($b) ? 1.0 : 0.0;
    $inter = count(array_intersect($aa,$bb)); $union = count(array_unique(array_merge($aa,$bb)));
    return $union ? $inter/$union : 0.0;
}
function import_option_validation(mixed $options, array &$errors, int $min=2, int $max=8): array {
    if (!is_array($options) || count($options) < $min || count($options) > $max) { $errors[]='options must contain '.$min.'–'.$max.' items'; return []; }
    $out=[]; $ids=[];
    foreach ($options as $i=>$opt) {
        if (!is_array($opt)) { $errors[]='option '.($i+1).' must be an object'; continue; }
        $id=trim((string)($opt['id']??'')); $label=trim((string)($opt['label']??''));
        if ($id==='' || strlen($id)>32 || !preg_match('/^[A-Za-z0-9_-]+$/',$id)) $errors[]='option '.($i+1).' has invalid id';
        if ($label==='' || strlen($label)>180) $errors[]='option '.($i+1).' label is missing/too long';
        if (isset($ids[$id])) $errors[]='duplicate option id '.$id;
        $ids[$id]=true; $out[]=['id'=>$id,'label'=>$label];
    }
    return $out;
}
function import_validate_card(array $card, int $index): array {
    $errors=[]; $warnings=[]; $normalized=$card;
    $id=trim((string)($card['id']??'')); if (!is_standard_uuid($id)) $errors[]='id must be a standard UUID/GUID'; else $normalized['id']=strtolower($id);
    $pillar=trim((string)($card['pillar']??'')); if (!array_key_exists($pillar,import_pillars())) $errors[]='invalid pillar';
    $kind=trim((string)($card['kind']??'')); if (!in_array($kind,import_kinds(),true)) $errors[]='invalid kind';
    $title=trim((string)($card['title']??'')); if ($title==='' || strlen($title)>260) $errors[]='title is missing/too long';
    $subtitle=trim((string)($card['subtitle']??'')); if ($subtitle==='' || strlen($subtitle)>340) $errors[]='subtitle is missing/too long';
    $topic=trim((string)($card['topic']??'')); if ($topic==='' || strlen($topic)>80 || !preg_match('/^[a-z0-9][a-z0-9_-]*$/',$topic)) $errors[]='topic must be a short lowercase key';
    $min=(int)($card['min_grade']??0); $max=(int)($card['max_grade']??0); if ($min<1 || $max>12 || $min>$max) $errors[]='min_grade/max_grade must be 1–12 with min <= max';
    $lang=trim((string)($card['language']??'en')); if ($lang==='' || strlen($lang)>12) $errors[]='invalid language';
    if (array_key_exists('active',$card) && !is_bool($card['active'])) $errors[]='active must be true or false';
    $normalized['pillar_name'] = trim((string)($card['pillar_name']??'')) ?: (import_pillars()[$pillar]??$pillar);
    $normalized['label'] = trim((string)($card['label']??'')) ?: import_pillar_label($pillar);
    $normalized['topic_name'] = trim((string)($card['topic_name']??'')) ?: ucwords(str_replace(['_','-'],' ',$topic));
    $normalized['language']=$lang ?: 'en'; $normalized['active']=array_key_exists('active',$card)?(bool)$card['active']:true;
    $normalized['min_grade']=$min; $normalized['max_grade']=$max; $normalized['title']=$title; $normalized['subtitle']=$subtitle; $normalized['pillar']=$pillar; $normalized['topic']=$topic; $normalized['kind']=$kind;
    if ($kind==='quiz') {
        $opts=import_option_validation($card['options']??null,$errors,3,5); $normalized['options']=$opts;
        $correct=trim((string)($card['correct']??'')); if ($correct==='' || !in_array($correct,array_column($opts,'id'),true)) $errors[]='correct must match an option id'; else $normalized['correct']=$correct;
        $explain=trim((string)($card['explain']??'')); if ($explain==='' || strlen($explain)>700) $errors[]='quiz explain is missing/too long'; else $normalized['explain']=$explain;
    } elseif ($kind==='reveal') {
        $reveal=trim((string)($card['reveal']??'')); if ($reveal==='' || strlen($reveal)>700) $errors[]='reveal text is missing/too long'; else $normalized['reveal']=$reveal;
    } else {
        $normalized['options']=import_option_validation($card['options']??null,$errors,$kind==='choice'?3:2,$kind==='choice'?8:4);
    }
    if (isset($card['aggregate']) && (!is_string($card['aggregate']) || strlen($card['aggregate'])>80)) $errors[]='aggregate must be a short string';
    foreach (['source_title','source_section','source_reference','source_url'] as $meta) if (isset($card[$meta]) && !is_string($card[$meta])) $errors[]="$meta must be a string";
    return ['index'=>$index,'card'=>$normalized,'errors'=>array_values(array_unique($errors)),'warnings'=>$warnings];
}
function content_bank_file(): string { return content_bank_file_path(); }
function import_read_bank_fresh(): array {
    $raw=@file_get_contents(content_bank_file()); $bank=is_string($raw)?json_decode($raw,true):null;
    if (!is_array($bank) || !isset($bank['cards']) || !is_array($bank['cards'])) throw new RuntimeException('Live content bank is invalid or unavailable.');
    return $bank;
}
function import_bank_indexes(array $bank): array {
    $ids=[];$finger=[];$topicTitles=[];
    foreach ($bank['cards'] as $c) {
        if (!is_array($c)) continue; $id=(string)($c['id']??''); if($id!=='')$ids[strtolower($id)]=true;
        $pillar=(string)($c['pillar']??'');$topic=(string)($c['topic']??'');$kind=(string)($c['kind']??'');$title=(string)($c['title']??'');
        $fp=$pillar.'|'.$topic.'|'.$kind.'|'.import_norm_text($title); if($title!=='')$finger[$fp]=true;
        if($pillar!==''&&$topic!==''&&$title!=='')$topicTitles[$pillar.'|'.$topic][]=$title;
    }
    return compact('ids','finger','topicTitles');
}
function verify_content_json(string $raw): array {
    $maxBytes=5*1024*1024; if(strlen($raw)>$maxBytes) throw new RuntimeException('Paste is larger than 5 MB. Split it into smaller batches.');
    try {$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);} catch(JsonException $e){throw new RuntimeException('Invalid JSON: '.$e->getMessage());}
    $cards = isset($decoded['cards']) && is_array($decoded['cards']) ? $decoded['cards'] : $decoded;
    if (!is_array($cards) || !array_is_list($cards)) throw new RuntimeException('JSON must be an array of cards, or an object containing a cards array.');
    if (count($cards)===0) throw new RuntimeException('No cards found.'); if(count($cards)>1500) throw new RuntimeException('Maximum 1,500 cards per import batch.');
    $bank=import_read_bank_fresh();$idx=import_bank_indexes($bank);$ready=[];$review=[];$rejected=[];$pillars=[];$kinds=[];$batchIdCounts=[];$batchFpCounts=[];$batchReadyTitles=[];
    foreach($cards as $rawCard){if(!is_array($rawCard))continue;$rid=strtolower(trim((string)($rawCard['id']??'')));if($rid!=='')$batchIdCounts[$rid]=($batchIdCounts[$rid]??0)+1;$rfp=trim((string)($rawCard['pillar']??'')).'|'.trim((string)($rawCard['topic']??'')).'|'.trim((string)($rawCard['kind']??'')).'|'.import_norm_text((string)($rawCard['title']??''));if(trim((string)($rawCard['title']??''))!=='')$batchFpCounts[$rfp]=($batchFpCounts[$rfp]??0)+1;}
    foreach($cards as $i=>$rawCard){
        if(!is_array($rawCard)){ $rejected[]=['index'=>$i+1,'id'=>'','title'=>'','reason'=>'Card must be a JSON object']; continue; }
        $v=import_validate_card($rawCard,$i+1);$c=$v['card'];$errors=$v['errors'];$warnings=$v['warnings'];$id=strtolower((string)($c['id']??''));$title=(string)($c['title']??'');$pillar=(string)($c['pillar']??'');$topic=(string)($c['topic']??'');$kind=(string)($c['kind']??'');
        if($id!==''&&($batchIdCounts[$id]??0)>1)$errors[]='duplicate GUID inside pasted batch';
        if($id!==''&&isset($idx['ids'][$id]))$errors[]='GUID already exists in live bank';
        $fp=$pillar.'|'.$topic.'|'.$kind.'|'.import_norm_text($title); if($title!==''&&($batchFpCounts[$fp]??0)>1)$errors[]='duplicate card concept inside pasted batch'; if($title!==''&&isset($idx['finger'][$fp]))$errors[]='same pillar/topic/type/title already exists';
        if(!$errors && $title!=='' && isset($idx['topicTitles'][$pillar.'|'.$topic])){
            $best=0.0;$bestTitle='';foreach($idx['topicTitles'][$pillar.'|'.$topic] as $existing){$s=import_similarity($title,$existing);if($s>$best){$best=$s;$bestTitle=$existing;}}
            if($best>=0.72){$warnings[]='possible semantic duplicate ('.round($best*100).'%) of: '.$bestTitle;}
        }
        if(!$errors && !$warnings && $title!=='' && isset($batchReadyTitles[$pillar.'|'.$topic])){
            $best=0.0;$bestTitle='';foreach($batchReadyTitles[$pillar.'|'.$topic] as $existing){$s=import_similarity($title,$existing);if($s>$best){$best=$s;$bestTitle=$existing;}}
            if($best>=0.72){$warnings[]='possible semantic duplicate inside pasted batch ('.round($best*100).'%) of: '.$bestTitle;}
        }
        if($errors){$rejected[]=['index'=>$i+1,'id'=>$id,'title'=>$title,'reason'=>implode('; ',array_unique($errors))];continue;}
        if($warnings){$review[]=['index'=>$i+1,'id'=>$id,'title'=>$title,'reason'=>implode('; ',array_unique($warnings))];continue;}
        $ready[]=$c;$batchReadyTitles[$pillar.'|'.$topic][]=$title;$pillars[$pillar]=($pillars[$pillar]??0)+1;$kinds[$kind]=($kinds[$kind]??0)+1;
    }
    return ['submitted'=>count($cards),'ready'=>$ready,'review'=>$review,'rejected'=>$rejected,'pillar_counts'=>$pillars,'kind_counts'=>$kinds,'verified_at'=>now_iso(),'bank_before'=>count($bank['cards'])];
}
function save_verification(array $result): string {
    $token=uuid_v4();$dir=data_path('imports/pending');if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Unable to create pending import folder.');
    $payload=['token'=>$token,'session_hash'=>hash('sha256',session_id()),'created_at'=>time(),'expires_at'=>time()+1800,'result'=>$result];
    $file=$dir.'/'.$token.'.json';if(@file_put_contents($file,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)throw new RuntimeException('Unable to save verified import. Check data/imports permissions.');
    $s=&udaan_session();$s['content_import_pending']=$token;return $token;
}
function load_verification(string $token): array {
    if(!is_standard_uuid($token))throw new RuntimeException('Invalid verification token.');$file=data_path('imports/pending/'.$token.'.json');$raw=@file_get_contents($file);$p=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($p)||($p['token']??'')!==$token)throw new RuntimeException('Verified batch was not found. Paste and verify again.');
    if((int)($p['expires_at']??0)<time()){@unlink($file);throw new RuntimeException('Verification expired. Paste and verify the JSON again.');}
    if(!hash_equals((string)($p['session_hash']??''),hash('sha256',session_id())))throw new RuntimeException('Verification belongs to another admin session.');
    $s=&udaan_session();if(($s['content_import_pending']??null)!==$token)throw new RuntimeException('Verification token is no longer active in this session.');
    return $p;
}
function cleanup_pending_verification(string $token): void { @unlink(data_path('imports/pending/'.$token.'.json'));$s=&udaan_session();if(($s['content_import_pending']??null)===$token)unset($s['content_import_pending']); }
function content_import_log_path(): string {return data_path('content/import-log.json');}
function content_import_history(): array {$raw=@file_get_contents(content_import_log_path());$v=is_string($raw)?json_decode($raw,true):null;return is_array($v)?$v:[];}
function content_import_write_log(array $entry): void {$log=content_import_history();array_unshift($log,$entry);$log=array_slice($log,0,100);@file_put_contents(content_import_log_path(),json_encode($log,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);}
function prune_content_backups(int $keep=20): void {$dir=data_path('content/backups');$files=glob($dir.'/cards-*.json')?:[];usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));foreach(array_slice($files,$keep) as $f)@unlink($f);}
function import_verified_batch(string $token): array {
    $pending=load_verification($token);$ready=$pending['result']['ready']??[];if(!is_array($ready)||!$ready)throw new RuntimeException('This verified batch contains no cards ready for import.');
    $bankFile=content_bank_file();$lockFile=dirname($bankFile).'/.import.lock';$lock=fopen($lockFile,'c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Could not acquire content-bank import lock.');
    try{
        $bank=import_read_bank_fresh();$idx=import_bank_indexes($bank);$final=[];$skipped=[];
        $raceReview=[];foreach($ready as $c){$id=strtolower((string)($c['id']??''));$pillar=(string)($c['pillar']??'');$topic=(string)($c['topic']??'');$title=(string)($c['title']??'');$fp=$pillar.'|'.$topic.'|'.(string)($c['kind']??'').'|'.import_norm_text($title);if(isset($idx['ids'][$id])||isset($idx['finger'][$fp])){$skipped[]=$id;continue;}$similar=false;foreach(($idx['topicTitles'][$pillar.'|'.$topic]??[]) as $existing){if(import_similarity($title,$existing)>=0.72){$similar=true;break;}}if($similar){$raceReview[]=$id;continue;}$final[]=$c;$idx['ids'][$id]=true;$idx['finger'][$fp]=true;$idx['topicTitles'][$pillar.'|'.$topic][]=$title;}
        if(!$final)throw new RuntimeException('All verified cards now exist in the bank. Nothing was imported.');
        $backupDir=dirname($bankFile).'/backups';if(!is_dir($backupDir)&&!@mkdir($backupDir,0775,true)&&!is_dir($backupDir))throw new RuntimeException('Unable to create backup folder.');
        $stamp=date('Ymd-His');$backup=$backupDir.'/cards-'.$stamp.'.json';if(!@copy($bankFile,$backup))throw new RuntimeException('Automatic backup failed. Import cancelled.');
        foreach($final as $c)$bank['cards'][]=$c;$counts=[];foreach($bank['cards'] as $c)if(is_array($c)){$p=(string)($c['pillar']??'other');$counts[$p]=($counts[$p]??0)+1;}
        $bank['version']=date('Y.m').'.import'.date('YmdHis');$bank['count']=count($bank['cards']);$bank['pillars']=count($counts);$unique=array_unique(array_values($counts));$bank['cards_per_pillar']=count($unique)===1?reset($unique):null;$bank['by_pillar']=$counts;$bank['last_import_at']=now_iso();$bank['last_import_count']=count($final);
        $json=json_encode($bank,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($json))throw new RuntimeException('Could not encode merged content bank.');
        $tmp=$bankFile.'.tmp-'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('Could not write temporary content bank.');$check=json_decode((string)file_get_contents($tmp),true);if(!is_array($check)||count($check['cards']??[])!==count($bank['cards'])){@unlink($tmp);throw new RuntimeException('Merged bank validation failed. Live bank was not changed.');}
        if(!@rename($tmp,$bankFile)){@unlink($tmp);throw new RuntimeException('Atomic content-bank replacement failed. Live bank was not changed.');}
        try { $distribution=rebuild_content_packs($bank); } catch(Throwable $packError) { @copy($backup,$bankFile); try { rebuild_content_packs(import_read_bank_fresh()); } catch(Throwable $ignored) {} throw new RuntimeException('Card bank was rolled back because signed content-pack publishing failed: '.$packError->getMessage()); }
        $entry=['id'=>uuid_v4(),'time'=>now_iso(),'verification_token'=>$token,'submitted'=>(int)($pending['result']['submitted']??0),'imported'=>count($final),'review_excluded'=>count($pending['result']['review']??[]),'rejected'=>count($pending['result']['rejected']??[]),'race_skipped'=>count($skipped),'race_review_excluded'=>count($raceReview),'bank_before'=>(int)($pending['result']['bank_before']??0),'bank_after'=>count($bank['cards']),'backup'=>basename($backup),'by_pillar'=>$pending['result']['pillar_counts']??[],'distribution_manifest'=>$distribution['manifest_sha256']??null,'distribution_packs'=>$distribution['pack_count']??null];content_import_write_log($entry);prune_content_backups();cleanup_pending_verification($token);return $entry;
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}


function content_v060_legacy_future_choice(array $c): bool {
    if(($c['pillar']??'')!=='future'||($c['kind']??'')!=='choice'||($c['aggregate']??'')!=='future_interest')return false;
    $ids=[];foreach(($c['options']??[])as$o)if(is_array($o)&&isset($o['id']))$ids[]=(string)$o['id'];
    if(count($ids)<=3)return true;
    return count(array_diff($ids,['yes','maybe','later']))===0;
}
function content_v060_migration_status(): array {
    $bank=import_read_bank_fresh();$legacy=[];foreach($bank['cards'] as$c)if(is_array($c)&&content_v060_legacy_future_choice($c))$legacy[]=(string)($c['id']??'');
    return ['needed'=>count($legacy)>0,'legacy_future_choice_cards'=>count($legacy),'bank_count'=>count($bank['cards']),'bank_version'=>(string)($bank['version']??'')];
}
function content_v060_migrate_future_choices(): array {
    $bankFile=content_bank_file();$lockFile=dirname($bankFile).'/.import.lock';$lock=fopen($lockFile,'c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Could not acquire content-bank migration lock.');
    try{
        $bank=import_read_bank_fresh();$changed=0;$newOptions=[
            ['id'=>'strong','label'=>'Yes — this strongly interests me'],
            ['id'=>'curious','label'=>'Curious — show me what people do'],
            ['id'=>'try','label'=>'I’d try a small activity first'],
            ['id'=>'unsure','label'=>'Not sure yet'],
            ['id'=>'later','label'=>'Not for me right now'],
        ];
        foreach($bank['cards'] as&$c){if(!is_array($c)||!content_v060_legacy_future_choice($c))continue;$c['options']=$newOptions;if(!isset($c['meta'])||!is_array($c['meta']))$c['meta']=[];$c['meta']['v060_future_choice_migrated_at']=now_iso();$changed++;}unset($c);
        if($changed===0)return ['changed'=>0,'bank_after'=>count($bank['cards']),'backup'=>null,'message'=>'No legacy Future choice cards require migration.'];
        $backupDir=dirname($bankFile).'/backups';if(!is_dir($backupDir)&&!@mkdir($backupDir,0775,true)&&!is_dir($backupDir))throw new RuntimeException('Unable to create content backup folder.');$backup=$backupDir.'/cards-v060-migration-'.date('Ymd-His').'.json';if(!@copy($bankFile,$backup))throw new RuntimeException('Automatic migration backup failed.');
        $bank['version']=date('Y.m').'.v060mig'.date('YmdHis');$bank['last_v060_migration_at']=now_iso();$json=json_encode($bank,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if(!is_string($json))throw new RuntimeException('Could not encode migrated bank.');$tmp=$bankFile.'.tmp-'.bin2hex(random_bytes(5));if(@file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('Could not write migrated bank.');$check=json_decode((string)@file_get_contents($tmp),true);if(!is_array($check)||count($check['cards']??[])!==count($bank['cards'])){@unlink($tmp);throw new RuntimeException('Migration validation failed. Live bank unchanged.');}if(!@rename($tmp,$bankFile)){@unlink($tmp);throw new RuntimeException('Atomic migration replacement failed.');}
        try{$dist=rebuild_content_packs($bank);}catch(Throwable $e){@copy($backup,$bankFile);try{rebuild_content_packs(import_read_bank_fresh());}catch(Throwable $ignored){}throw new RuntimeException('Migration rolled back because signed-pack publishing failed: '.$e->getMessage());}
        content_import_write_log(['id'=>uuid_v4(),'time'=>now_iso(),'type'=>'v0.6.0_future_choice_migration','submitted'=>$changed,'imported'=>0,'review_excluded'=>0,'rejected'=>0,'bank_before'=>count($bank['cards']),'bank_after'=>count($bank['cards']),'backup'=>basename($backup),'migration_changed'=>$changed,'distribution_manifest'=>$dist['manifest_sha256']??null]);prune_content_backups();return ['changed'=>$changed,'bank_after'=>count($bank['cards']),'backup'=>basename($backup),'manifest'=>$dist['manifest_sha256']??null];
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
