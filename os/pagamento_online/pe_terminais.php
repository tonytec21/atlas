<?php

declare(strict_types=1);

/**
 * pe_terminais.php — Maquininhas cadastradas na configuração.
 * O operador também pode digitar o serial na hora de cobrar.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if (!pe_pos_habilitado()) {
    pe_json_erro('Cobrança na maquininha não está habilitada.', 403);
}

pe_json_ok(['terminais' => pe_terminais_config()]);
