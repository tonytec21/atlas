<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!cap_csrf_check($_POST['csrf'] ?? ($_GET['csrf'] ?? ''))) { echo json_encode(['success'=>false,'message'=>'Sessão expirada.']); exit; }
$r = cap_enviar_alertas(false);
echo json_encode(['success'=>($r['status']==='success'||$r['status']==='empty'||$r['status']==='skip'), 'result'=>$r,
   'message'=> $r['status']==='success' ? ('E-mail enviado ('.$r['vencidas'].' vencidas, '.$r['prestes'].' a vencer).') : ($r['message'] ?? 'Concluído.')], JSON_UNESCAPED_UNICODE);
