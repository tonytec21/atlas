<?php

declare(strict_types=1);

/**
 * pe_venda_criar.php — Gera a venda e envia ao terminal.
 *
 * Validacoes de negocio importantes acontecem AQUI, no servidor, nao no
 * JavaScript: o valor cobrado e derivado da O.S. no banco, nunca do que o
 * navegador mandou. Caso contrario bastaria adulterar o POST para cobrar
 * um valor diferente do devido.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\Endpoints;

use TCloud\ParcelaExpress\Pos\PaymentForm;
use TCloud\ParcelaExpress\Pos\PosSaleRequest;

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
$valorInformado = (float) ($_POST['valor'] ?? 0);
$observacao = mb_substr(trim((string) ($_POST['observacao'] ?? '')), 0, 500);

if ($osId <= 0) {
    pe_json_erro('O.S. inválida.');
}

if ($terminalId === '') {
    pe_json_erro('Selecione o terminal.');
}

$forma = match ($formaInput) {
    'credit', 'credito', 'crédito' => PaymentForm::Credit,
    'debit', 'debito', 'débito' => PaymentForm::Debit,
    'pix' => PaymentForm::Pix,
    default => null,
};

if ($forma === null) {
    pe_json_erro('Forma de pagamento inválida.');
}

try {
    $pdo = pe_pdo();

    // Confere a O.S. e o saldo em aberto no banco.
    $stmt = $pdo->prepare("SELECT id, cliente, total_os FROM ordens_de_servico WHERE id = ? LIMIT 1");
    $stmt->execute([$osId]);
    $os = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        pe_json_erro('O.S. não encontrada.', 404);
    }

    $saldo = pe_saldo_devido($osId);

    if ($saldo <= 0) {
        pe_json_erro('Esta O.S. não possui saldo em aberto.');
    }

    // O valor cobrado nunca excede o saldo devedor.
    $valor = $valorInformado > 0 ? round($valorInformado, 2) : $saldo;

    if ($valor > $saldo + 0.001) {
        pe_json_erro(sprintf(
            'O valor informado (R$ %s) excede o saldo em aberto (R$ %s).',
            number_format($valor, 2, ',', '.'),
            number_format($saldo, 2, ',', '.')
        ));
    }

    $amountCents = (int) round($valor * 100);

    // Já existe venda aguardando terminal para esta O.S.? Evita duas
    // cobranças simultâneas na mesma ordem.
    $emAberto = $pdo->prepare(
        "SELECT local_reference, remote_id
           FROM pe_pos_sales
          WHERE ordem_de_servico_id = ?
            AND reconciliation_state = 'pending'
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
          LIMIT 1"
    );
    $emAberto->execute([$osId]);

    if ($pendente = $emAberto->fetch(PDO::FETCH_ASSOC)) {
        pe_json_ok([
            'reaproveitada' => true,
            'sale_id' => $pendente['remote_id'],
            'local_reference' => $pendente['local_reference'],
            'mensagem' => 'Já existe uma cobrança em andamento para esta O.S.',
        ]);
    }

    $localReference = bin2hex(random_bytes(16));

    $request = new PosSaleRequest(
        amountCents: $amountCents,
        paymentForm: $forma,
        terminalId: $terminalId,
        installments: $forma->allowsInstallments() ? $parcelas : 1,
        orderNumber: (string) $osId,
        description: 'O.S. ' . $osId . ' - ' . ($os['cliente'] ?? ''),
        protocol: (string) $osId,
    );

    $sale = pe_service()->create($request, $localReference);

    // Complementa os campos que só nós conhecemos.
    $upd = $pdo->prepare(
        "UPDATE pe_pos_sales
            SET ordem_de_servico_id = ?, installments = ?, payment_form = ?,
                terminal_id = ?, funcionario = ?
          WHERE local_reference = ?"
    );
    $upd->execute([
        $osId,
        $request->installments,
        $forma->value,
        $terminalId,
        pe_usuario(),
        $localReference,
    ]);

    pe_log('info', 'pos', "Venda {$sale->id} enviada ao terminal {$terminalId}. Valor R$ {$valor}.", $osId);

    pe_json_ok([
        'sale_id' => $sale->id,
        'local_reference' => $localReference,
        'valor' => $valor,
        'parcelas' => $request->installments,
        'aviso_emissor' => $forma->requiresIssuerApproval($request->installments),
        'observacao' => $observacao,
    ]);
} catch (Throwable $e) {
    pe_log('error', 'pos', 'Falha ao criar venda: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível enviar a venda ao terminal: ' . $e->getMessage(), 502);
}
