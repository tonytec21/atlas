<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    cap_ensure_schema();
    $conta = (int)($_GET['conta_id'] ?? 0);
    if ($conta <= 0) throw new RuntimeException('Conta inválida.');
    $conn = cap_db();
    $st = $conn->prepare("SELECT id,nome_original,mime,tamanho,descricao,enviado_por,DATE_FORMAT(enviado_em,'%d/%m/%Y %H:%i') enviado_em FROM conta_anexos WHERE conta_id=? ORDER BY id DESC");
    $st->bind_param('i',$conta); $st->execute(); $r=$st->get_result(); $l=[];
    while($x=$r->fetch_assoc()) $l[]=$x; $st->close();
    echo json_encode(['status'=>'success','anexos'=>$l], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){ echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
