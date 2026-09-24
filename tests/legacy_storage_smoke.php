<?php
declare(strict_types=1);

require dirname(__DIR__).'/lib/helpers.php';

$config=['legacy_plaintext_storage'=>'read'];
$plain='{"schema_version":1,"value":"legacy"}';
$decoded=secure_unpack($plain);
if(!is_array($decoded)||($decoded['value']??null)!=='legacy'){fwrite(STDERR,"Legacy read mode failed\n");exit(1);}

$config=['legacy_plaintext_storage'=>'deny'];
if(secure_unpack($plain)!==null){fwrite(STDERR,"Legacy deny mode accepted plaintext\n");exit(1);}

echo "legacy-storage-smoke: PASS\n";
