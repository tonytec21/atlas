<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    cap_ensure_schema();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    if ($titulo === '') throw new RuntimeException('Informe o título da conta.');
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $fornecedor = trim((string)($_POST['fornecedor'] ?? ''));
    $nota_fiscal = trim((string)($_POST['nota_fiscal'] ?? ''));
    $valor = cap_parse_money($_POST['valor'] ?? '0');
    $venc = trim((string)($_POST['data_vencimento'] ?? ''));
    if ($venc === '' || !strtotime($venc)) throw new RuntimeException('Data de vencimento inválida.');
    $venc = date('Y-m-d', strtotime($venc));
    $recorrencia = in_array(($_POST['recorrencia'] ?? 'Nenhuma'), cap_recorrencias(), true) ? $_POST['recorrencia'] : 'Nenhuma';
    $descricao = trim((string)($_POST['descricao'] ?? ''));

    $conn = cap_db();
    $stmt = $conn->prepare("UPDATE contas_a_pagar SET titulo=?, categoria=?, fornecedor=?, nota_fiscal=?, valor=?, data_vencimento=?, descricao=?, recorrencia=? WHERE id=?");
    $stmt->bind_param('ssssdsssi', $titulo, $categoria, $fornecedor, $nota_fiscal, $valor, $venc, $descricao, $recorrencia, $id);
    if (!$stmt->execute()) throw new RuntimeException('Erro ao atualizar: ' . $stmt->error);
    $stmt->close();
    echo json_encode(['success'=>true,'message'=>'Conta atualizada com sucesso!'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
