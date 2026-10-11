<?php

declare(strict_types=1);

/**
 * pe_testar_conexao.php — Teste de conexão da tela de configuração.
 * Somente administradores. Usa a configuração JÁ SALVA (mesmo desativada).
 * Faz o login e mostra a conta e o estabelecimento (seller) que vão emitir.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!pe_usuario_e_admin()) {
    pe_json_erro('Apenas administradores podem testar a conexão.', 403);
}

if (!pe_csrf_check($_GET['csrf'] ?? $_POST['csrf'] ?? null)) {
    pe_json_erro('Token de segurança inválido. Recarregue a página.', 403);
}

$cfg = pe_config(true);
$producao = ($cfg['ambiente'] ?? 'sandbox') === 'production';

if ($producao && (trim((string) ($cfg['usuario'] ?? '')) === '' || trim((string) ($cfg['senha'] ?? '')) === '')) {
    pe_json_erro('Em produção, salve o e-mail e a senha de acesso antes de testar.');
}

$cliente = pe_cliente($cfg);

/* Login novo, sem reaproveitar o token da sessão. */
$res = $cliente->testarLogin();

$homologacao = $cliente->getEmail() === ParcelaExpressClient::HOMOLOGACAO_EMAIL;

if (empty($res['success'])) {
    pe_log('error', 'config', 'Teste de conexão: login recusado (HTTP ' . ($res['http_code'] ?? 0) . ').');
    pe_json_ok([
        'login' => false,
        'login_msg' => ParcelaExpressClient::mensagemErro($res, 'Login recusado'),
    ]);
}

$seller = $res['data']['seller'] ?? [];
$sellerFixo = trim((string) ($cfg['seller_id'] ?? ''));

pe_json_ok([
    'login' => true,
    'login_msg' => 'Conectado à Parcela Express (' . ($producao ? 'produção' : 'sandbox') . ').',
    'conta' => $cliente->getEmail() . ($homologacao ? ' (conta de homologação)' : ''),
    'seller' => $sellerFixo !== '' ? $sellerFixo : (string) ($seller['id'] ?? ''),
    'seller_origem' => $sellerFixo !== '' ? 'da configuração — o login informou ' . ($seller['id'] ?? '?') : 'informado pelo login',
    'aviso_seller' => ($sellerFixo !== '' && $sellerFixo !== (string) ($seller['id'] ?? ''))
        ? 'O "ID do estabelecimento" da configuração é diferente do estabelecimento desta conta — a emissão tende a ser recusada (Forbidden). Deixe o campo em branco para usar o da conta.'
        : null,
    'seller_nome' => $seller['name'] ?? ($seller['trading_name'] ?? null),
    'seller_documento' => $seller['document'] ?? ($seller['cnpj'] ?? null),
    'terminais' => pe_terminais_config(),
    'terminais_msg' => pe_terminais_config() ? null : 'Nenhuma maquininha cadastrada. Cadastre em "Maquininhas" ou digite o serial na hora de cobrar.',
]);
