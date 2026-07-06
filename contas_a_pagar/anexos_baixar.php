<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
$id=(int)($_GET['id']??0); if($id<=0){ http_response_code(400); die('ID inválido.'); }
$conn=cap_db();
$st=$conn->prepare("SELECT nome_original,arquivo,mime FROM conta_anexos WHERE id=? LIMIT 1"); $st->bind_param('i',$id); $st->execute();
$res=$st->get_result(); if($res->num_rows===0){ http_response_code(404); die('Anexo não encontrado.'); }
$a=$res->fetch_assoc(); $st->close();
$base=realpath(cap_dir_anexos()); $path=realpath(__DIR__.'/'.$a['arquivo']);
if($path===false || strncmp($path,$base.DIRECTORY_SEPARATOR,strlen($base)+1)!==0 || !is_file($path)){ http_response_code(404); die('Arquivo não encontrado.'); }
$inline=isset($_GET['inline']);
header('Content-Type: '.($a['mime']?:'application/octet-stream'));
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.str_replace('"','',$a['nome_original']).'"');
header('Content-Length: '.filesize($path)); header('X-Content-Type-Options: nosniff');
readfile($path);
