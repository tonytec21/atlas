<?php

declare(strict_types=1);

/**
 * pe_boleto_criar.php — Emite um boleto (com link de pagamento) para a O.S.
 *
 * Envia o nome e o CPF/CNPJ do pagador; vencimento em 3 dias; demais dados
 * do pagador com os valores padrão do cliente (ParcelaExpressClient).
 *
 * Emitir NÃO é receber: nada é lançado em pagamento_os aqui. O boleto fica
 * pendente até a conferência confirmar o pagamento.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

pe_guard_operacao();

if (!pe_boleto_habilitado()) {
    pe_json_erro('Emissão de boleto não está habilitada.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pe_json_erro('Método inválido.', 405);
}

$osId = (int) ($_POST['os_id'] ?? 0);
$valorInformado = (float) str_replace(',', '.', (string) ($_POST['valor'] ?? 0));
$nome = trim((string) ($_POST['pagador_nome'] ?? ''));
$documento = preg_replace('/\D/', '', (string) ($_POST['pagador_documento'] ?? '')) ?? '';

if ($osId <= 0) {
    pe_json_erro('O.S. inválida.');
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

    if ($nome === '') {
        $nome = (string) $os['cliente'];
    }
    if ($documento === '') {
        $documento = preg_replace('/\D/', '', (string) $os['cpf_cliente']) ?? '';
    }
    if ($documento === '') {
        pe_json_erro('Informe o CPF ou CNPJ do pagador.');
    }

    $saldo = pe_saldo_devido($osId);
    if ($saldo <= 0) {
        pe_json_erro('Esta O.S. não possui saldo em aberto.');
    }

    $valor = $valorInformado > 0 ? round($valorInformado, 2) : $saldo;

    /* Boletos ainda pagáveis consomem o saldo. */
    $abertos = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_cents), 0) FROM pe_boletos
          WHERE ordem_de_servico_id = ? AND status IN ('pending', 'overdue', 'unknown')"
    );
    $abertos->execute([$osId]);
    $jaEmitido = ((int) $abertos->fetchColumn()) / 100;

    if ($valor + $jaEmitido > $saldo + 0.001) {
        pe_json_erro(sprintf(
            'Já existem R$ %s em boletos abertos nesta O.S. O máximo que ainda pode ser emitido é R$ %s.',
            number_format($jaEmitido, 2, ',', '.'),
            number_format(max(0, $saldo - $jaEmitido), 2, ',', '.')
        ));
    }

    // ---- Dados do boleto ----
    $refId = pe_referencia($osId);

    $tz = new DateTimeZone('America/Fortaleza');
    $vencimento = (new DateTimeImmutable('now', $tz))->modify('+3 days');

    $dadosBoleto = [
        'referencia_id' => pe_referencia_api($refId),
        'valor' => $valor,
        'vencimento' => $vencimento->format('Y-m-d\TH:i:s\Z'),
        'cliente_nome' => $nome,
        'cliente_documento' => $documento,
        'cliente_email' => '',
        'endereco_rua' => '',
        'endereco_numero' => 'S/N',
        'endereco_bairro' => '',
        'endereco_cidade' => '',
        'endereco_uf' => '',
        'endereco_cep' => '',
        'instrucoes' => 'Cobrança OS ' . $osId,
    ];

    $amountCents = (int) round($valor * 100);

    $pdo->prepare(
        "INSERT INTO pe_boletos
            (local_reference, ordem_de_servico_id, status, amount_cents, vencimento,
             pagador_nome, pagador_documento, funcionario, request_payload)
         VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?)"
    )->execute([
        $refId, $osId, $amountCents, $vencimento->format('Y-m-d'),
        $nome, $documento, pe_usuario(), json_encode($dadosBoleto, JSON_UNESCAPED_UNICODE),
    ]);

    // ---- Chamada à API ----
    $res = pe_cliente()->emitirBoleto($dadosBoleto);

    if (empty($res['success'])) {
        $semResposta = (int) ($res['http_code'] ?? 0) === 0;
        $msg = ParcelaExpressClient::mensagemErro($res, 'Erro na emissão do boleto');

        /* Recusa da Receita: mostra o que foi enviado, para conferência. */
        if (stripos($msg, 'Receita Federal') !== false) {
            $docFmt = strlen($documento) === 11
                ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $documento)
                : $documento;
            $msg .= ' — Enviado: CPF/CNPJ ' . $docFmt . ', pagador "' . $nome . '", conta ' . pe_cliente()->getEmail()
                . ' (' . ((pe_config()['ambiente'] ?? 'sandbox') === 'production' ? 'produção' : 'sandbox') . ').';
        }

        $pdo->prepare("UPDATE pe_boletos SET status = ?, last_error = ?, raw_payload = ? WHERE local_reference = ?")
            ->execute([
                $semResposta ? 'unknown' : 'failed',
                mb_substr($msg, 0, 500),
                json_encode($res['data'] ?? null, JSON_UNESCAPED_UNICODE),
                $refId,
            ]);

        pe_log('error', 'boleto', 'Emissão recusada (HTTP ' . ($res['http_code'] ?? 0) . '): ' . $msg
            . ' | resposta: ' . mb_substr((string) json_encode($res['data'] ?? null, JSON_UNESCAPED_UNICODE), 0, 1500), $osId);

        pe_json_erro($semResposta
            ? $msg . ' O boleto pode ter sido gerado — confira no portal antes de emitir outro.'
            : $msg, 502);
    }

    // ---- Resposta: lista com um boleto ----
    $data = $res['data'] ?? [];
    $billet = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : (is_array($data) ? $data : []);

    $urlBoleto = (string) ($billet['url'] ?? '');
    $urlPagamento = (string) ($billet['billet_data']['payment_link_url'] ?? $urlBoleto);
    $linhaDigitavel = (string) ($billet['linha_digitavel'] ?? ($billet['digitable_line'] ?? ''));
    $codigoBarras = (string) ($billet['barcode'] ?? '');
    $saleId = (string) ($billet['sale_id'] ?? ($billet['id'] ?? $refId));
    $billetId = (string) ($billet['id'] ?? $saleId);

    if ($linhaDigitavel === '' && strlen(preg_replace('/\D/', '', $codigoBarras) ?? '') === 47) {
        $linhaDigitavel = $codigoBarras;
    }

    $pdo->prepare(
        "UPDATE pe_boletos
            SET remote_id = ?, remote_sale_id = ?, linha_digitavel = ?, codigo_barras = ?,
                url_pdf = ?, url_pagamento = ?, raw_payload = ?
          WHERE local_reference = ?"
    )->execute([
        $billetId, $saleId, $linhaDigitavel ?: null, $codigoBarras ?: null,
        $urlBoleto ?: null, $urlPagamento ?: null,
        json_encode($data, JSON_UNESCAPED_UNICODE), $refId,
    ]);

    pe_log('info', 'boleto', "Boleto {$billetId} emitido no valor de R$ {$valor}.", $osId);

    $linhaFmt = $linhaDigitavel;
    $d = preg_replace('/\D/', '', $linhaDigitavel) ?? '';
    if (strlen($d) === 47) {
        $linhaFmt = sprintf('%s.%s %s.%s %s.%s %s %s',
            substr($d, 0, 5), substr($d, 5, 5), substr($d, 10, 5), substr($d, 15, 6),
            substr($d, 21, 5), substr($d, 26, 6), substr($d, 32, 1), substr($d, 33, 14));
    }

    pe_json_ok([
        'boleto' => [
            'id' => $billetId,
            'local_reference' => $refId,
            'valor' => $valor,
            'vencimento' => $vencimento->format('Y-m-d'),
            'vencimento_br' => $vencimento->format('d/m/Y'),
            'linha_digitavel' => $linhaFmt ?: null,
            'linha_crua' => $linhaDigitavel ?: null,
            'pix_copia_cola' => null,
            'url_pdf' => $urlBoleto ?: null,
            'url_pagamento' => $urlPagamento ?: null,
            'qrcode_url' => $billet['billet_data']['qrcode_url'] ?? null,
            'status' => 'pending',
            'status_rotulo' => 'Aguardando pagamento',
            'pagador_nome' => $nome,
            'divergencia' => null,
        ],
    ]);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha ao emitir: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível emitir o boleto: ' . $e->getMessage(), 502);
}
