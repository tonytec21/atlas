<?php

declare(strict_types=1);

/**
 * pe_venda_interromper.php — Para de aguardar a cobrança na maquininha.
 *
 * A API não oferece cancelamento remoto da cobrança: aqui ela é apenas
 * encerrada no Atlas. Se ainda estiver na tela do aparelho, cancele nele.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pe_json_erro('Método inválido.', 405);
}

$saleId = trim((string) ($_POST['sale_id'] ?? ''));
$osId = (int) ($_POST['os_id'] ?? 0);

if ($saleId === '') {
    pe_json_erro('Cobrança não informada.');
}

try {
    pe_pdo()->prepare(
        "UPDATE pe_pos_sales SET reconciliation_state = 'failed', last_error = 'Interrompida pelo operador'
          WHERE remote_id = ? AND pagamento_id IS NULL AND reconciliation_state = 'pending'"
    )->execute([$saleId]);

    pe_log('info', 'pos', "Cobrança {$saleId} interrompida pelo operador.", $osId ?: null);

    pe_json_ok(['sale_id' => $saleId]);
} catch (Throwable $e) {
    pe_json_erro('Não foi possível interromper: ' . $e->getMessage(), 500);
}
