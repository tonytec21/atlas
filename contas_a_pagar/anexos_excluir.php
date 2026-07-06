<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $id = (int)($_POST['id'] ?? 0); if ($id<=0) throw new RuntimeException('ID inválido.');
    $conn = cap_db();
    $st=$conn->prepare("SELECT arquivo FROM conta_anexos WHERE id=? LIMIT 1"); $st->bind_param('i',$id); $st->execute();
    $res=$st->get_result(); if($res->num_rows===0) throw new RuntimeException('Anexo não encontrado.');
    $rel=$res->fetch_assoc()['arquivo']; $st->close();
    $base=realpath(cap_dir_anexos()); $p=realpath(__DIR__.'/'.$rel);
    if($p && strncmp($p,$base.DIRECTORY_SEPARATOR,strlen($base)+1)===0 && is_file($p)) @unlink($p);
    $d=$conn->prepare("DELETE FROM conta_anexos WHERE id=?"); $d->bind_param('i',$id); $d->execute(); $d->close();
    echo json_encode(['status'=>'success'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){ echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
