<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    cap_ensure_schema();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $conn = cap_db();
    $stmt = $conn->prepare("SELECT * FROM contas_a_pagar WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new RuntimeException('Conta não encontrada.');
    $c = $res->fetch_assoc(); $stmt->close();
    $c['status_efetivo'] = cap_status_efetivo($c);
    $c['valor_fmt'] = number_format((float)$c['valor'], 2, ',', '.');
    echo json_encode(['success'=>true,'conta'=>$c], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
