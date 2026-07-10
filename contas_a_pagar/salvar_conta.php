<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    cap_ensure_schema();
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    if ($titulo === '') throw new RuntimeException('Informe o título da conta.');
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $fornecedor = trim((string)($_POST['fornecedor'] ?? ''));
    $valor = cap_parse_money($_POST['valor'] ?? '0');
    if ($valor < 0) throw new RuntimeException('Valor inválido.');
    $venc = trim((string)($_POST['data_vencimento'] ?? ''));
    if ($venc === '' || !strtotime($venc)) throw new RuntimeException('Informe uma data de vencimento válida.');
    $venc = date('Y-m-d', strtotime($venc));
    $recorrencia = in_array(($_POST['recorrencia'] ?? 'Nenhuma'), cap_recorrencias(), true) ? $_POST['recorrencia'] : 'Nenhuma';
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $func = $_SESSION['username'] ?? null;
    $agora = date('Y-m-d H:i:s');

    $conn = cap_db();
    $stmt = $conn->prepare("INSERT INTO contas_a_pagar (titulo, categoria, fornecedor, valor, data_vencimento, descricao, recorrencia, funcionario, status, created_at) VALUES (?,?,?,?,?,?,?,?,'Pendente',?)");
    $stmt->bind_param('sssdsssss', $titulo, $categoria, $fornecedor, $valor, $venc, $descricao, $recorrencia, $func, $agora);
    if (!$stmt->execute()) throw new RuntimeException('Erro ao salvar: ' . $stmt->error);
    $id = $stmt->insert_id; $stmt->close();
    cap_log("Conta criada #$id ($titulo) por $func");
    echo json_encode(['success'=>true,'id'=>$id,'message'=>'Conta cadastrada com sucesso!'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
