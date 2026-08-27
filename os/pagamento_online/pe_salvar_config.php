<?php

declare(strict_types=1);

/**
 * pe_salvar_config.php — Grava a configuracao. Somente administradores.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pe_json_erro('Método inválido.', 405);
}

// Gate de administrador. Nao usamos o include da pagina porque ele emite
// HTML/redirect; aqui precisamos de JSON.
if (!pe_usuario_e_admin()) {
    pe_log('warn', 'config', 'Tentativa de salvar configuração sem perfil de administrador.');
    pe_json_erro('Apenas administradores podem alterar esta configuração.', 403);
}

if (!pe_csrf_check($_POST['csrf'] ?? null)) {
    pe_json_erro('Token de segurança inválido. Recarregue a página.', 403);
}

try {
    pe_migrar();

    $atual = pe_config(true);

    $ambiente = ($_POST['ambiente'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';

    // Senha em branco = manter a atual.
    $senha = trim((string) ($_POST['senha'] ?? ''));
    if ($senha === '') {
        $senha = (string) ($atual['senha'] ?? '');
    }

    $dados = [
        'ativo' => !empty($_POST['ativo']) ? 1 : 0,
        'ambiente' => $ambiente,
        'seller_id' => trim((string) ($_POST['seller_id'] ?? '')),
        'usuario' => trim((string) ($_POST['usuario'] ?? '')),
        'senha' => $senha,
        'base_url' => trim((string) ($_POST['base_url'] ?? '')),
        'habilitar_pos' => !empty($_POST['habilitar_pos']) ? 1 : 0,
        'habilitar_boleto' => !empty($_POST['habilitar_boleto']) ? 1 : 0,
        'timeout_poll_seg' => max(1, min(30, (int) ($_POST['timeout_poll_seg'] ?? 3))),
        'timeout_venda_seg' => max(60, min(900, (int) ($_POST['timeout_venda_seg'] ?? 300))),
    ];

    $stmt = pe_pdo()->prepare(
        "UPDATE pe_config SET
            ativo = :ativo,
            ambiente = :ambiente,
            seller_id = :seller_id,
            usuario = :usuario,
            senha = :senha,
            base_url = :base_url,
            habilitar_pos = :habilitar_pos,
            habilitar_boleto = :habilitar_boleto,
            timeout_poll_seg = :timeout_poll_seg,
            timeout_venda_seg = :timeout_venda_seg,
            atualizado_em = NOW(),
            atualizado_por = :usuario_log
         WHERE id = 1"
    );

    $stmt->execute($dados + ['usuario_log' => pe_usuario()]);

    // Avisa se ficou ativo mas incompleto — evita o administrador achar que
    // ligou o recurso quando na verdade ele nao vai operar.
    $pendencias = pe_pendencias($dados);
    $mensagem = '';

    if ($dados['ativo'] && $pendencias !== []) {
        $mensagem = 'Salvo, mas o recurso não entrará em operação enquanto faltar: '
            . implode(', ', $pendencias) . '.';
    } elseif ($dados['ativo']) {
        $mensagem = 'Recurso habilitado. O botão de cobrança na maquininha já aparece no modal de pagamento.';
    } else {
        $mensagem = 'Recurso desativado. A tela de O.S. volta ao comportamento padrão.';
    }

    pe_log('info', 'config', 'Configuração alterada. Ativo=' . $dados['ativo'] . ', ambiente=' . $ambiente);

    pe_json_ok(['mensagem' => $mensagem, 'pendencias' => $pendencias]);
} catch (Throwable $e) {
    pe_log('error', 'config', 'Falha ao salvar: ' . $e->getMessage());
    pe_json_erro('Erro ao salvar: ' . $e->getMessage(), 500);
}
