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

    $forma = trim((string)($_POST['forma_pagamento'] ?? ''));
    $formas = cap_formas_pagamento();
    if ($forma === '' || !array_key_exists($forma, $formas)) throw new RuntimeException('Informe a forma de pagamento.');
    $contaOrigem = cap_conta_da_forma($forma);   // 'especie' | 'banco' | '' (não afeta saldo)

    $dataPag = trim((string)($_POST['data_pagamento'] ?? ''));
    $dataPag = ($dataPag !== '' && strtotime($dataPag)) ? date('Y-m-d', strtotime($dataPag)) : date('Y-m-d');
    $forcar = !empty($_POST['forcar']);

    $conn = cap_db();
    $stmt = $conn->prepare("SELECT * FROM contas_a_pagar WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new RuntimeException('Conta não encontrada.');
    $c = $res->fetch_assoc(); $stmt->close();
    if ($c['status'] === 'Pago') throw new RuntimeException('Esta conta já está paga.');

    $valor = (float)$c['valor'];

    // Valida saldo da conta virtual (quando a forma consome saldo)
    if ($contaOrigem !== '') {
        $saldos = cap_saldos();
        $disp = $saldos[$contaOrigem]['saldo'] ?? 0.0;
        if ($valor > $disp + 0.001 && !$forcar) {
            echo json_encode([
                'success' => false,
                'saldo_insuficiente' => true,
                'conta' => $contaOrigem,
                'conta_nome' => cap_nome_conta($contaOrigem),
                'saldo' => $disp,
                'saldo_fmt' => cap_money($disp),
                'valor_fmt' => cap_money($valor),
                'message' => 'Saldo insuficiente em ' . cap_nome_conta($contaOrigem) . '. Disponível: ' . cap_money($disp) . ' · Valor da conta: ' . cap_money($valor) . '.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $conn->begin_transaction();
    try {
        $u = $conn->prepare("UPDATE contas_a_pagar SET status='Pago', data_pagamento=?, forma_pagamento=?, conta_origem=? WHERE id=?");
        $co = ($contaOrigem !== '') ? $contaOrigem : null;
        $u->bind_param('sssi', $dataPag, $forma, $co, $id);
        $u->execute(); $u->close();

        $novoId = null; $prox = cap_proximo_vencimento($c['data_vencimento'], $c['recorrencia']);
        if ($prox !== null) {
            $agora = date('Y-m-d H:i:s'); $origem = $c['origem_id'] ?: $id;
            $ins = $conn->prepare("INSERT INTO contas_a_pagar (titulo, categoria, fornecedor, valor, data_vencimento, descricao, recorrencia, funcionario, status, origem_id, created_at) VALUES (?,?,?,?,?,?,?,?,'Pendente',?,?)");
            $ins->bind_param('sssdssssis', $c['titulo'], $c['categoria'], $c['fornecedor'], $c['valor'], $prox, $c['descricao'], $c['recorrencia'], $c['funcionario'], $origem, $agora);
            $ins->execute(); $novoId = $ins->insert_id; $ins->close();
        }
        $conn->commit();

        $saldos = cap_saldos();
        $msg = 'Conta paga (' . $forma . ').';
        if ($contaOrigem !== '') $msg .= ' Novo saldo em ' . cap_nome_conta($contaOrigem) . ': ' . cap_money($saldos[$contaOrigem]['saldo']) . '.';
        if ($novoId) $msg .= ' Próxima parcela gerada para ' . date('d/m/Y', strtotime($prox)) . '.';
        cap_log("Conta #$id paga via $forma" . ($contaOrigem?" (debita $contaOrigem)":" (sem débito)"));
        echo json_encode(['success'=>true,'message'=>$msg,'saldos'=>$saldos], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
