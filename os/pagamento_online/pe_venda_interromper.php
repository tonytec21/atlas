<?php

declare(strict_types=1);

/**
 * pe_venda_interromper.php — Equivale ao "Interromper venda" do CartExpress.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pe_json_erro('Método inválido.', 405);
}

$saleId = trim((string) ($_POST['sale_id'] ?? ''));
$osId   = (int) ($_POST['os_id'] ?? 0);

if ($saleId === '') {
    pe_json_erro('Venda não informada.');
}

try {
    $sale = pe_service()->interrupt($saleId);

    pe_log('info', 'pos', "Venda {$saleId} interrompida pelo operador.", $osId ?: null);

    pe_json_ok([
        'sale_id'     => $sale->id,
        'pre_captura' => $sale->preCaptureStatus?->value,
        'transacao'   => $sale->transactionStatus?->value,
    ]);
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Falha ao interromper: ' . $e->getMessage(), $osId ?: null);
    pe_json_erro('Não foi possível interromper a venda: ' . $e->getMessage(), 502);
}
