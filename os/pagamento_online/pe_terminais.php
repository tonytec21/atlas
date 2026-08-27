<?php

declare(strict_types=1);

/**
 * pe_terminais.php — Lista os terminais vinculados ao estabelecimento.
 * Alimenta o "Selecione o terminal" e o diagnóstico da tela de configuração.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\AuthenticationException;
use TCloud\ParcelaExpress\Endpoints;

pe_guard_operacao();

if (!pe_pos_habilitado()) {
    pe_json_erro('Cobrança na maquininha não está habilitada.', 403);
}

try {
    pe_json_ok(['terminais' => pe_service()->terminals()]);
} catch (AuthenticationException $e) {
    pe_log('error', 'pos', 'Falha de autenticação: ' . $e->getMessage());
    pe_json_erro(
        'A API recusou as credenciais.',
        401
    );
} catch (ApiException $e) {
    pe_log('error', 'pos', 'Falha ao listar terminais: ' . $e->getMessage());

    // Detalhe técnico separado da mensagem: ajuda a distinguir "rota não
    // existe" (404, o caso esperado enquanto o spec não chega) de
    // "credencial errada" ou "serviço fora do ar".
    $detalhe = $e->isTransportError()
        ? 'Não houve resposta do servidor da Parcela Express.'
        : sprintf(
            'HTTP %d em %s',
            $e->statusCode,
            Endpoints::build(Endpoints::path('POS_TERMINALS'), ['seller_id' => pe_config()['seller_id'] ?? '{seller_id}'])
        );

    $mensagem = pe_mensagem_api($e, 'Listar terminais', Endpoints::build(
        Endpoints::path('POS_TERMINALS'),
        ['seller_id' => pe_config()['seller_id'] ?? '{seller_id}']
    ));

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => $mensagem,
        'detalhe' => $detalhe,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Erro inesperado ao listar terminais: ' . $e->getMessage());
    pe_json_erro('Erro inesperado: ' . $e->getMessage(), 500);
}
