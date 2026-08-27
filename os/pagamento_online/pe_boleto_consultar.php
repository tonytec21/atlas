<?php

declare(strict_types=1);

/**
 * pe_boleto_consultar.php — Consulta o banco e, se o título estiver pago,
 * lança o pagamento na O.S.
 *
 * Este é o único caminho pelo qual um boleto vira dinheiro no Atlas.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\Endpoints;

pe_guard_operacao();

if (!pe_boleto_habilitado()) {
    pe_json_erro('Emissão de boleto não está habilitada.', 403);
}

$localReference = trim((string) ($_POST['local_reference'] ?? $_GET['local_reference'] ?? ''));
$osId = (int) ($_POST['os_id'] ?? $_GET['os_id'] ?? 0);

if ($localReference === '' || $osId <= 0) {
    pe_json_erro('Parâmetros inválidos.');
}

try {
    pe_migrar();

    $stmt = pe_pdo()->prepare(
        "SELECT remote_id, pagamento_id FROM pe_boletos WHERE local_reference = ? LIMIT 1"
    );
    $stmt->execute([$localReference]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        pe_json_erro('Boleto não encontrado.', 404);
    }

    if (!$registro['remote_id']) {
        // Registro local sem identificador: a emissão não chegou a se
        // confirmar, então não há o que consultar na API.
        pe_json_erro(
            'Este boleto não chegou a ser confirmado pela Parcela Express, '
            . 'então não há o que consultar. Verifique no portal e, se ele não existir lá, emita outro.',
            409
        );
    }

    // Já lançado: nada a fazer, e nunca lançar de novo.
    if ($registro['pagamento_id']) {
        pe_json_ok([
            'pago'          => true,
            'ja_lancado'    => true,
            'pagamento_id'  => (int) $registro['pagamento_id'],
            'status_rotulo' => 'Pago',
        ]);
    }

    $boleto = pe_boleto_service()->find((string) $registro['remote_id']);

    $upd = pe_pdo()->prepare(
        "UPDATE pe_boletos SET status = ?, raw_payload = ? WHERE local_reference = ?"
    );
    $upd->execute([
        $boleto->status?->value ?? 'pending',
        json_encode($boleto->raw, JSON_UNESCAPED_UNICODE),
        $localReference,
    ]);

    if (!$boleto->isPaid()) {
        pe_json_ok([
            'pago'          => false,
            'status'        => $boleto->status?->value,
            'status_rotulo' => $boleto->status?->rotulo() ?? 'Aguardando pagamento',
        ]);
    }

    $pagamentoId = pe_registrar_pagamento_boleto($osId, $boleto, $localReference);
    $valor = ($boleto->paidAmountCents ?? $boleto->amountCents ?? 0) / 100;

    pe_json_ok([
        'pago'               => true,
        'status_rotulo'      => 'Pago',
        'pagamento_id'       => $pagamentoId,
        'forma_de_pagamento' => 'Boleto',
        'total_pagamento'    => $valor,
        'data_pagamento'     => date('Y-m-d H:i:s'),
        'funcionario'        => pe_usuario(),
    ]);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha ao consultar: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível consultar o boleto: ' . $e->getMessage(), 502);
}
