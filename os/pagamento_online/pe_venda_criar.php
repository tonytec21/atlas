<?php

declare(strict_types=1);

/**
 * pe_venda_criar.php — Envia a cobrança para a maquininha.
 *
 * O valor é conferido no servidor contra o saldo da O.S. no banco, nunca
 * aceito do navegador sem checagem.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if (!pe_pos_habilitado()) {
    pe_json_erro('Cobrança na maquininha não está habilitada.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pe_json_erro('Método inválido.', 405);
}

$osId = (int) ($_POST['os_id'] ?? 0);
$terminalId = trim((string) ($_POST['terminal_id'] ?? ''));
$formaInput = strtolower(trim((string) ($_POST['forma'] ?? '')));
$parcelas = max(1, (int) ($_POST['parcelas'] ?? 1));
$valorInformado = (float) str_replace(',', '.', (string) ($_POST['valor'] ?? 0));
$observacao = mb_substr(trim((string) ($_POST['observacao'] ?? '')), 0, 500);

if ($osId <= 0) {
    pe_json_erro('O.S. inválida.');
}

if ($terminalId === '') {
    pe_json_erro('Informe a maquininha (terminal).');
}

$modalidade = match ($formaInput) {
    'credit', 'credito', 'crédito' => 'CREDITO',
    'debit', 'debito', 'débito' => 'DEBITO',
    'pix' => 'PIX',
    default => null,
};

if ($modalidade === null) {
    pe_json_erro('Forma de pagamento inválida.');
}

if ($modalidade !== 'CREDITO') {
    $parcelas = 1;
}

try {
    $pdo = pe_pdo();
    pe_migrar($pdo);

    $stmt = $pdo->prepare("SELECT id, cliente, cpf_cliente FROM ordens_de_servico WHERE id = ? LIMIT 1");
    $stmt->execute([$osId]);
    $os = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        pe_json_erro('O.S. não encontrada.', 404);
    }

    $saldo = pe_saldo_devido($osId);
    if ($saldo <= 0) {
        pe_json_erro('Esta O.S. não possui saldo em aberto.');
    }

    $valor = $valorInformado > 0 ? round($valorInformado, 2) : $saldo;

    if ($valor > $saldo + 0.001) {
        pe_json_erro(sprintf(
            'O valor informado (R$ %s) excede o saldo em aberto (R$ %s).',
            number_format($valor, 2, ',', '.'),
            number_format($saldo, 2, ',', '.')
        ));
    }

    // Já existe cobrança aguardando a maquininha nesta O.S.? Evita duas ao mesmo tempo.
    $emAberto = $pdo->prepare(
        "SELECT local_reference, remote_id, amount_cents, installments FROM pe_pos_sales
          WHERE ordem_de_servico_id = ? AND reconciliation_state = 'pending'
            AND remote_id IS NOT NULL AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
          ORDER BY id DESC LIMIT 1"
    );
    $emAberto->execute([$osId]);

    if ($pendente = $emAberto->fetch(PDO::FETCH_ASSOC)) {
        pe_json_ok([
            'reaproveitada' => true,
            'sale_id' => $pendente['remote_id'],
            'local_reference' => $pendente['local_reference'],
            'valor' => ((int) $pendente['amount_cents']) / 100,
            'parcelas' => (int) $pendente['installments'],
            'mensagem' => 'Já existe uma cobrança em andamento para esta O.S.',
        ]);
    }

    $refId = pe_referencia($osId);

    $pdo->prepare(
        "INSERT INTO pe_pos_sales
            (local_reference, ordem_de_servico_id, amount_cents, installments, payment_form,
             terminal_id, funcionario, reconciliation_state, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
    )->execute([$refId, $osId, (int) round($valor * 100), $parcelas, $modalidade, $terminalId, pe_usuario()]);

    $res = pe_cliente()->enviarParaMaquininha(
        $terminalId, $valor, $modalidade, $parcelas, pe_referencia_api($refId), (string) ($os['cpf_cliente'] ?? '')
    );

    if (empty($res['success'])) {
        $semResposta = (int) ($res['http_code'] ?? 0) === 0;
        $msg = ParcelaExpressClient::mensagemErro($res, 'Erro na requisição para a maquininha');

        $pdo->prepare("UPDATE pe_pos_sales SET reconciliation_state = ?, last_error = ?, raw_payload = ? WHERE local_reference = ?")
            ->execute([$semResposta ? 'unknown' : 'failed', mb_substr($msg, 0, 500),
                json_encode($res['data'] ?? null, JSON_UNESCAPED_UNICODE), $refId]);

        pe_log('error', 'pos', 'Falha ao enviar à maquininha (HTTP ' . ($res['http_code'] ?? 0) . '): ' . $msg, $osId);

        pe_json_erro($semResposta
            ? $msg . ' A cobrança pode ter chegado à maquininha — confira no aparelho antes de reenviar.'
            : $msg, 502);
    }

    $saleId = (string) (is_array($res['data'] ?? null) && isset($res['data']['id']) ? $res['data']['id'] : $refId);

    $pdo->prepare("UPDATE pe_pos_sales SET remote_id = ?, raw_payload = ? WHERE local_reference = ?")
        ->execute([$saleId, json_encode($res['data'] ?? null, JSON_UNESCAPED_UNICODE), $refId]);

    pe_log('info', 'pos', "Cobrança {$saleId} enviada à maquininha {$terminalId}. Valor R$ {$valor}.", $osId);

    pe_json_ok([
        'sale_id' => $saleId,
        'local_reference' => $refId,
        'valor' => $valor,
        'parcelas' => $parcelas,
        'aviso_emissor' => false,
        'observacao' => $observacao,
    ]);
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Falha ao enviar à maquininha: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível enviar à maquininha: ' . $e->getMessage(), 502);
}
