<?php
/** transferir.php — move saldo entre as contas virtuais (ex.: depositar a espécie no banco). */
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    cap_ensure_schema();

    $contas = cap_contas_virtuais();
    $origem  = trim((string)($_POST['origem'] ?? ''));
    $destino = trim((string)($_POST['destino'] ?? ''));
    if (!isset($contas[$origem]) || !isset($contas[$destino])) throw new RuntimeException('Conta inválida.');
    if ($origem === $destino) throw new RuntimeException('A conta de origem e destino devem ser diferentes.');

    $valor = cap_parse_money($_POST['valor'] ?? '0');
    if ($valor <= 0) throw new RuntimeException('Informe um valor maior que zero.');

    $data = trim((string)($_POST['data_transferencia'] ?? ''));
    $data = ($data !== '' && strtotime($data)) ? date('Y-m-d', strtotime($data)) : date('Y-m-d');
    $obs = substr(trim((string)($_POST['observacao'] ?? '')), 0, 255);
    $forcar = !empty($_POST['forcar']);

    $saldos = cap_saldos();
    $disp = $saldos[$origem]['saldo'] ?? 0.0;
    if ($valor > $disp + 0.001 && !$forcar) {
        echo json_encode([
            'success' => false, 'saldo_insuficiente' => true,
            'conta_nome' => cap_nome_conta($origem),
            'saldo_fmt' => cap_money($disp), 'valor_fmt' => cap_money($valor),
            'message' => 'Saldo insuficiente em ' . cap_nome_conta($origem) . '. Disponível: ' . cap_money($disp) . '.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conn = cap_db();
    $usuario = $_SESSION['username'] ?? null; $agora = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO conta_transferencias (data_transferencia, origem, destino, valor, observacao, usuario, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('sssdsss', $data, $origem, $destino, $valor, $obs, $usuario, $agora);
    if (!$stmt->execute()) throw new RuntimeException('Erro ao registrar transferência: ' . $stmt->error);
    $id = $stmt->insert_id; $stmt->close();

    $novos = cap_saldos();
    cap_log("Transferência #$id: " . cap_money($valor) . " de $origem para $destino por $usuario");
    echo json_encode([
        'success' => true, 'id' => $id,
        'message' => 'Transferência registrada: ' . cap_money($valor) . ' de ' . cap_nome_conta($origem) . ' para ' . cap_nome_conta($destino) . '.',
        'saldos' => $novos,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
