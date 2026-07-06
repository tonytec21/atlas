<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    cap_ensure_schema();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $conn = cap_db();
    $stmt = $conn->prepare("SELECT * FROM contas_a_pagar WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new RuntimeException('Conta não encontrada.');
    $c = $res->fetch_assoc(); $stmt->close();
    if ($c['status'] === 'Pago') throw new RuntimeException('Esta conta já está paga.');

    $conn->begin_transaction();
    try {
        $hoje = date('Y-m-d');
        $u = $conn->prepare("UPDATE contas_a_pagar SET status='Pago', data_pagamento=? WHERE id=?");
        $u->bind_param('si', $hoje, $id); $u->execute(); $u->close();

        $novoId = null;
        $prox = cap_proximo_vencimento($c['data_vencimento'], $c['recorrencia']);
        if ($prox !== null) {
            $agora = date('Y-m-d H:i:s'); $origem = $c['origem_id'] ?: $id;
            $ins = $conn->prepare("INSERT INTO contas_a_pagar (titulo, categoria, fornecedor, valor, data_vencimento, descricao, recorrencia, funcionario, status, origem_id, created_at) VALUES (?,?,?,?,?,?,?,?,'Pendente',?,?)");
            $ins->bind_param('sssdssssis', $c['titulo'], $c['categoria'], $c['fornecedor'], $c['valor'], $prox, $c['descricao'], $c['recorrencia'], $c['funcionario'], $origem, $agora);
            $ins->execute(); $novoId = $ins->insert_id; $ins->close();
        }
        $conn->commit();
        cap_log("Conta #$id marcada como paga" . ($novoId ? " (recorrência gerou #$novoId em $prox)" : ""));
        echo json_encode(['success'=>true,'message'=>'Conta marcada como paga.' . ($novoId ? ' Próxima gerada para ' . date('d/m/Y', strtotime($prox)) . '.' : ''), 'proxima'=>$prox], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
