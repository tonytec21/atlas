<?php

declare(strict_types=1);

/**
 * pe_simular.php — Tabela de parcelamento para um valor.
 *
 * As taxas vêm dos Planos de repasse configurados para o cartório e mudam
 * sem aviso. Nunca replique esse cálculo no PHP: consuma e exiba.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\Endpoints;

pe_guard_operacao();

if (!pe_pos_habilitado()) {
    pe_json_erro('Cobrança na maquininha não está habilitada.', 403);
}

$valorCentavos = (int) round(((float) ($_GET['valor'] ?? 0)) * 100);

if ($valorCentavos <= 0) {
    pe_json_erro('Informe um valor válido.');
}

try {
    pe_json_ok([
        'valor_centavos' => $valorCentavos,
        'opcoes' => pe_service()->simulateInstallments($valorCentavos),
    ]);
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Falha na simulação: ' . $e->getMessage());
    pe_json_erro('Não foi possível calcular as parcelas: ' . $e->getMessage(), 502);
}
