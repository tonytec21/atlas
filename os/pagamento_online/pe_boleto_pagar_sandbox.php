<?php

declare(strict_types=1);

/**
 * pe_boleto_pagar_sandbox.php — Marca o boleto como pago, SOMENTE em sandbox.
 *
 * A API da Parcela Express expõe POST /v1/billets/{billetId}/pay para
 * ambiente de desenvolvimento. Serve para exercitar o ciclo completo —
 * emitir, pagar, conferir, lançar em pagamento_os — sem esperar a
 * compensação bancária de verdade.
 *
 * Em produção este endpoint recusa antes de qualquer chamada.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if (!pe_boleto_habilitado()) {
    pe_json_erro('Emissão de boleto não está habilitada.', 403);
}

$cfg = pe_config();

if (($cfg['ambiente'] ?? 'sandbox') !== 'sandbox') {
    pe_json_erro('Pagamento simulado só existe em sandbox.', 403);
}

$localReference = trim((string) ($_POST['local_reference'] ?? ''));

if ($localReference === '') {
    pe_json_erro('Boleto não informado.');
}

try {
    pe_migrar();

    $stmt = pe_pdo()->prepare("SELECT remote_id FROM pe_boletos WHERE local_reference = ? LIMIT 1");
    $stmt->execute([$localReference]);
    $remoteId = $stmt->fetchColumn();

    if (!$remoteId) {
        pe_json_erro('Boleto não encontrado ou sem identificador.', 404);
    }

    pe_boleto_service()->pagarSandbox((string) $remoteId);

    pe_log('info', 'boleto', "Boleto {$remoteId} marcado como pago (sandbox).");

    pe_json_ok(['mensagem' => 'Boleto marcado como pago no sandbox. Use "Conferir" para lançar na O.S.']);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha no pagamento simulado: ' . $e->getMessage());
    pe_json_erro('Não foi possível simular o pagamento: ' . $e->getMessage(), 502);
}
