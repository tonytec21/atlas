<?php

declare(strict_types=1);

/**
 * pe_venda_status.php — Consulta o desfecho da venda no terminal.
 *
 * Uma chamada = uma consulta. O navegador repete a cada poucos segundos
 * enquanto o retorno for "aguardando". Quando a venda é aprovada, o
 * lançamento em pagamento_os acontece AQUI, no servidor — nunca no
 * JavaScript.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\Pos\PosSale;

pe_guard_operacao();

$saleId = trim((string) ($_GET['sale_id'] ?? ''));
$osId = (int) ($_GET['os_id'] ?? 0);
$observacao = mb_substr(trim((string) ($_GET['observacao'] ?? '')), 0, 500);

if ($saleId === '' || $osId <= 0) {
    pe_json_erro('Parâmetros inválidos.');
}

$cfg = pe_config();

try {
    $sale = pe_service()->status($saleId);

    $resposta = [
        'sale_id' => $sale->id,
        'pre_captura' => $sale->preCaptureStatus?->value,
        'transacao' => $sale->transactionStatus?->value,
        'aguardando' => !$sale->isSettled(),
        'aprovada' => $sale->isPaid(),
    ];

    if (!$sale->isSettled()) {
        pe_json_ok($resposta);
    }

    // ---- Desfecho ----

    if ($sale->isPaid()) {
        $forma = pe_forma_para_rotulo(
            (string) ($sale->raw['form_payment'] ?? 'credit')
        );

        $obs = pe_tem_coluna_observacao() && $observacao !== '' ? $observacao : null;

        // Idempotente: se o polling vir "aprovada" duas vezes, o segundo
        // lançamento devolve o mesmo pagamento_id em vez de duplicar.
        $pagamentoId = pe_registrar_pagamento($osId, $sale, $forma, $obs);

        $valor = ($sale->chargedAmountCents ?? $sale->amountCents ?? 0) / 100;

        pe_json_ok($resposta + [
            'pagamento_id' => $pagamentoId,
            'forma_de_pagamento' => $forma,
            'total_pagamento' => $valor,
            'data_pagamento' => date('Y-m-d H:i:s'),
            'funcionario' => pe_usuario(),
            'observacao' => $obs ?? '',
        ]);
    }

    // Recusada, interrompida ou expirada.
    $motivo = match (true) {
        $sale->transactionStatus?->isDeclined() === true
            => 'Pagamento recusado pelo banco emissor.',
        $sale->preCaptureStatus?->value === 'interrupted'
            => 'Venda interrompida.',
        $sale->preCaptureStatus?->value === 'expired'
            => 'A venda expirou sem ser concluída no terminal.',
        default => 'A venda não foi aprovada.',
    };

    pe_log('info', 'pos', "Venda {$saleId} sem aprovação: {$motivo}", $osId);

    pe_json_ok($resposta + ['motivo' => $motivo]);
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Falha ao consultar status: ' . $e->getMessage(), $osId);
    pe_json_erro('Falha ao consultar o status da venda: ' . $e->getMessage(), 502);
}
