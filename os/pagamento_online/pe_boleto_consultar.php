<?php

declare(strict_types=1);

/**
 * pe_boleto_consultar.php — Consulta a situação do boleto e, se estiver
 * pago, lança o pagamento na O.S. (único caminho pelo qual um boleto vira
 * dinheiro no Atlas).
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if (!pe_boleto_habilitado()) {
    pe_json_erro('Emissão de boleto não está habilitada.', 403);
}

$localReference = trim((string) ($_POST['local_reference'] ?? $_GET['local_reference'] ?? ''));
$osId = (int) ($_POST['os_id'] ?? $_GET['os_id'] ?? 0);
/* AUDITORIA O.S. — registra o antes/depois desta operação */
require_once __DIR__ . '/../auditoria_os_lib.php';
osaud_monitorar('pagamento_online', ['os_id' => $osId]);

if ($localReference === '' || $osId <= 0) {
    pe_json_erro('Parâmetros inválidos.');
}

try {
    pe_migrar();

    $stmt = pe_pdo()->prepare(
        "SELECT remote_id, remote_sale_id, pagamento_id FROM pe_boletos WHERE local_reference = ? AND ordem_de_servico_id = ? LIMIT 1"
    );
    $stmt->execute([$localReference, $osId]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        pe_json_erro('Boleto não encontrado.', 404);
    }

    if ($registro['pagamento_id']) {
        pe_json_ok([
            'pago' => true,
            'ja_lancado' => true,
            'pagamento_id' => (int) $registro['pagamento_id'],
            'status_rotulo' => 'Pago',
        ]);
    }

    $saleId = (string) ($registro['remote_sale_id'] ?: $registro['remote_id']);
    if ($saleId === '') {
        pe_json_erro('Este boleto não chegou a ser confirmado pela Parcela Express. Verifique no portal.', 409);
    }

    $res = pe_cliente()->consultarTransacao($saleId);

    if (empty($res['success'])) {
        pe_json_erro('Não foi possível consultar o boleto: ' . ParcelaExpressClient::mensagemErro($res, 'Erro na consulta'), 502);
    }

    $statusApi = is_array($res['data'] ?? null) ? (string) ($res['data']['status'] ?? '') : '';
    $situacao = ParcelaExpressClient::statusLocal($statusApi);

    pe_pdo()->prepare("UPDATE pe_boletos SET status = ?, raw_payload = ? WHERE local_reference = ?")
        ->execute([
            $situacao === 'pago' ? 'paid' : ($situacao === 'cancelado' ? 'canceled' : 'pending'),
            json_encode($res['data'], JSON_UNESCAPED_UNICODE),
            $localReference,
        ]);

    if ($situacao !== 'pago') {
        pe_json_ok([
            'pago' => false,
            'status' => $statusApi,
            'status_rotulo' => $situacao === 'cancelado' ? 'Cancelado' : 'Aguardando pagamento',
        ]);
    }

    $pagamentoId = pe_registrar_pagamento($osId, 'pe_boletos', $localReference, 'Boleto', 'Boleto Parcela Express');

    $vq = pe_pdo()->prepare("SELECT total_pagamento FROM pagamento_os WHERE id = ?");
    $vq->execute([$pagamentoId]);

    pe_json_ok([
        'pago' => true,
        'status_rotulo' => 'Pago',
        'pagamento_id' => $pagamentoId,
        'forma_de_pagamento' => 'Boleto',
        'total_pagamento' => (float) $vq->fetchColumn(),
        'data_pagamento' => date('Y-m-d H:i:s'),
        'funcionario' => pe_usuario(),
    ]);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha ao consultar: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível consultar o boleto: ' . $e->getMessage(), 502);
}
