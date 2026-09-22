<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/lib/helpers.php';
require_once $root.'/lib/Player.php';

$identity=str_repeat('a',64);
$base=udaan_player_default($identity);
if(($base['privacy']['profile_visibility']??'')!=='private'){fwrite(STDERR,"Player default privacy is not private\n");exit(1);}
if(!empty($base['onboarding_completed'])){fwrite(STDERR,"Fresh Player should not be onboarded\n");exit(1);}

$normalized=udaan_player_normalize([
    'nickname'=>'Aarav',
    'age_group'=>'16_17',
    'stage'=>'class_11_12',
    'language'=>'hi',
    'main_goal'=>'neet',
    'exam_target'=>'NEET 2027',
    'daily_minutes'=>'90',
    'arenas'=>['learn','fit','mind','fit'],
    'share_achievements'=>'1',
],$base);

if(($normalized['main_goal']??'')!=='neet'||($normalized['language']??'')!=='hi'){fwrite(STDERR,"Player normalization failed\n");exit(1);}
if(($normalized['daily_minutes']??0)!==90){fwrite(STDERR,"Daily minutes normalization failed\n");exit(1);}
if(($normalized['arenas']??[])!==['learn','fit','mind']){fwrite(STDERR,"Arena normalization/deduplication failed\n");exit(1);}
if(empty($normalized['onboarding_completed'])){fwrite(STDERR,"Completed onboarding flag missing\n");exit(1);}
if(($normalized['privacy']['profile_visibility']??'')!=='private'){fwrite(STDERR,"Profile visibility escaped private default\n");exit(1);}
if(!empty($normalized['privacy']['parent_or_institution_sharing'])){fwrite(STDERR,"Parent/institution sharing must default off\n");exit(1);}

$event=udaan_player_event('player.created',$normalized,['arena'=>'core','main_goal'=>'neet']);
if(($event['player_id']??'')!==($normalized['id']??'')){fwrite(STDERR,"Player event identity mismatch\n");exit(1);}
if(($event['arena']??'')!=='core'){fwrite(STDERR,"Player event arena mismatch\n");exit(1);}

$failed=false;
try{udaan_player_normalize(['nickname'=>'A','age_group'=>'bad','stage'=>'bad'],$base);}catch(InvalidArgumentException $e){$failed=true;}
if(!$failed){fwrite(STDERR,"Invalid Player input was accepted\n");exit(1);}

echo "player-foundation-smoke: PASS\n";
