<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET') json_response(['ok'=>false,'error'=>'GET required'],405);
try{$m=content_manifest(true);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: public, max-age=300, stale-while-revalidate=3600');$etag='"'.($m['manifest_sha256']??hash('sha256',json_encode($m))).'"';header('ETag: '.$etag);if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}echo json_encode(['ok'=>true,'manifest'=>$m],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],503);}
