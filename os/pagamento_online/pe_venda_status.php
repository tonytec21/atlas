<?php

declare(strict_types=1);

/**
 * pe_venda_status.php — Consulta a situação da cobrança na maquininha.
 *
 * Uma chamada = uma consulta. A tela repete a cada poucos segundos enquanto
 * estiver pendente. Quando paga, o lançamento em pagamento_os acontece aqui,
 * no servidor.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

$saleId = trim((string) ($_GET['sale_id'] ?? ''));
$osId = (int) ($_GET['os_id'] ?? 0);
$observacao = mb_substr(trim((string) ($_GET['observacao'] ?? '')), 0, 500);
/* AUDITORIA O.S. — registra o antes/depois desta operação */
require_once __DIR__ . '/../auditoria_os_lib.php';
osaud_monitorar('pagamento_online', ['os_id' => $osId]);

if ($saleId === '' || $osId <= 0) {
    pe_json_erro('Parâmetros inválidos.');
}

try {
    $pdo = pe_pdo();

    $st = $pdo->prepare("SELECT local_reference, payment_form, pagamento_id FROM pe_pos_sales WHERE remote_id = ? AND ordem_de_servico_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$saleId, $osId]);
    $venda = $st->fetch(PDO::FETCH_ASSOC);

    if (!$venda) {
        pe_json_erro('Cobrança não encontrada.', 404);
    }

    $res = pe_cliente()->consultarTransacao($saleId);

    if (empty($res['success'])) {
        pe_json_erro('Falha ao consultar a cobrança: ' . ParcelaExpressClient::mensagemErro($res, 'Erro na consulta'), 502);
    }

    $statusApi = is_array($res['data'] ?? null) ? (string) ($res['data']['status'] ?? '') : '';
    $situacao = ParcelaExpressClient::statusLocal($statusApi);

    $pdo->prepare("UPDATE pe_pos_sales SET transaction_status = ?, raw_payload = ? WHERE local_reference = ?")
        ->execute([mb_substr($statusApi, 0, 32), json_encode($res['data'], JSON_UNESCAPED_UNICODE), $venda['local_reference']]);

    $resposta = [
        'sale_id' => $saleId,
        'transacao' => $statusApi,
        'aguardando' => $situacao === 'pendente',
        'aprovada' => $situacao === 'pago',
    ];

    if ($situacao === 'pendente') {
        pe_json_ok($resposta);
    }

    if ($situacao === 'pago') {
        $forma = pe_forma_para_rotulo((string) $venda['payment_form']);
        $obs = $observacao !== '' ? $observacao : null;

        $pagamentoId = pe_registrar_pagamento($osId, 'pe_pos_sales', (string) $venda['local_reference'], $forma, $obs);

        $vq = $pdo->prepare("SELECT total_pagamento FROM pagamento_os WHERE id = ?");
        $vq->execute([$pagamentoId]);

        pe_json_ok($resposta + [
            'pagamento_id' => $pagamentoId,
            'forma_de_pagamento' => $forma,
            'total_pagamento' => (float) $vq->fetchColumn(),
            'data_pagamento' => date('Y-m-d H:i:s'),
            'funcionario' => pe_usuario(),
            'observacao' => $obs ?? '',
        ]);
    }

    // Cancelada, expirada ou estornada.
    $pdo->prepare("UPDATE pe_pos_sales SET reconciliation_state = 'failed' WHERE local_reference = ?")
        ->execute([$venda['local_reference']]);

    pe_log('info', 'pos', "Cobrança {$saleId} não aprovada: {$statusApi}", $osId);

    pe_json_ok($resposta + ['motivo' => 'A cobrança não foi aprovada (' . ($statusApi !== '' ? $statusApi : 'sem status') . ').']);
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Falha ao consultar status: ' . $e->getMessage(), $osId);
    pe_json_erro('Falha ao consultar a cobrança: ' . $e->getMessage(), 502);
}
