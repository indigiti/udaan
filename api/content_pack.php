<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET') json_response(['ok'=>false,'error'=>'GET required'],405);
$packId=trim((string)($_GET['pack']??''));if(!content_pack_id_valid($packId))json_response(['ok'=>false,'error'=>'Invalid pack id'],400);
$pack=content_pack_load($packId);if(!$pack)json_response(['ok'=>false,'error'=>'Pack not found'],404);
$etag='"'.(string)($pack['sha256']??hash('sha256',json_encode($pack))).'"';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: public, max-age=86400, immutable');header('ETag: '.$etag);if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}echo json_encode(['ok'=>true,'pack'=>$pack],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
