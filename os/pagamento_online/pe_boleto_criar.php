<?php

declare(strict_types=1);

/**
 * pe_boleto_criar.php — Emite um boleto para a O.S.
 *
 * ORDEM DAS OPERAÇÕES (aprendida na marra): validar → gravar → chamar a API.
 * Gravar antes de validar deixa registro de boleto que nunca existiu, e o
 * operador vê na lista um título que o banco desconhece.
 *
 * E emitir NÃO é receber: nada é lançado em pagamento_os aqui. O título fica
 * em pe_boletos como pendente até a consulta confirmar a compensação.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\Boleto\BoletoRequest;
use TCloud\ParcelaExpress\Endpoints;

pe_guard_operacao();

if (!pe_boleto_habilitado()) {
    pe_json_erro('Emissão de boleto não está habilitada.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pe_json_erro('Método inválido.', 405);
}

$osId = (int) ($_POST['os_id'] ?? 0);
$valorInformado = (float) ($_POST['valor'] ?? 0);
$vencimentoStr = trim((string) ($_POST['vencimento'] ?? ''));
$nome = trim((string) ($_POST['pagador_nome'] ?? ''));
$documento = trim((string) ($_POST['pagador_documento'] ?? ''));
$email = trim((string) ($_POST['pagador_email'] ?? ''));
$instrucoes = mb_substr(trim((string) ($_POST['instrucoes'] ?? '')), 0, 255);

/* Endereço do pagador. A API não o exige na validação, mas quebra sem ele
   (erro 500 "reading 'complement' of undefined"). */
$endereco = [
    'cep'         => trim((string) ($_POST['cep'] ?? '')),
    'logradouro'  => trim((string) ($_POST['logradouro'] ?? '')),
    'numero'      => trim((string) ($_POST['numero'] ?? '')),
    'complemento' => trim((string) ($_POST['complemento'] ?? '')),
    'bairro'      => trim((string) ($_POST['bairro'] ?? '')),
    'cidade'      => trim((string) ($_POST['cidade'] ?? '')),
    'uf'          => trim((string) ($_POST['uf'] ?? '')),
];

if ($osId <= 0) {
    pe_json_erro('O.S. inválida.');
}

if ($documento === '') {
    pe_json_erro('Informe o CPF ou CNPJ do pagador.');
}

try {
    $vencimento = new DateTimeImmutable($vencimentoStr !== '' ? $vencimentoStr : '+3 days');
} catch (Throwable $e) {
    pe_json_erro('Data de vencimento inválida.');
}

$localReference = null;

try {
    $pdo = pe_pdo();
    pe_migrar($pdo);

    $stmt = $pdo->prepare("SELECT id, cliente FROM ordens_de_servico WHERE id = ? LIMIT 1");
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

    /* Boletos que ainda podem ser pagos consomem o saldo. 'failed' fica de
       fora porque não existe do lado do banco; 'unknown' entra porque pode
       existir, e nesse caso é mais seguro bloquear do que cobrar em dobro. */
    $abertos = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_cents), 0)
           FROM pe_boletos
          WHERE ordem_de_servico_id = ?
            AND status IN ('pending', 'overdue', 'unknown')"
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

    // ---- 1. Monta e VALIDA, antes de gravar qualquer coisa ----

    $localReference = bin2hex(random_bytes(16));

    $request = new BoletoRequest(
        amountCents: (int) round($valor * 100),
        vencimento: $vencimento,
        pagadorNome: $nome !== '' ? $nome : (string) $os['cliente'],
        pagadorDocumento: $documento,
        pagadorEmail: $email !== '' ? $email : null,
        // Vira shopper_statement: é o que o pagador vê. Como a API não tem
        // campo de protocolo, é aqui que a O.S. fica visível.
        descricao: 'O.S. ' . $osId . ' - ' . mb_substr((string) $os['cliente'], 0, 40),
        instrucoes: $instrucoes !== '' ? $instrucoes : null,
        protocol: (string) $osId,
        endereco: $endereco,
    );

    try {
        $request->validate();
    } catch (InvalidArgumentException $e) {
        // Nada foi gravado e nada foi enviado. Só a mensagem volta.
        pe_json_erro($e->getMessage());
    }

    // ---- 2. Registra a tentativa ----
    // A partir daqui a chamada vai sair, então precisa haver rastro local
    // caso a resposta se perca no caminho.

    $ins = $pdo->prepare(
        "INSERT INTO pe_boletos
            (local_reference, ordem_de_servico_id, status, amount_cents, vencimento,
             pagador_nome, pagador_documento, pagador_email, funcionario, request_payload)
         VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $localReference,
        $osId,
        $request->amountCents,
        $vencimento->format('Y-m-d'),
        $request->pagadorNome,
        preg_replace('/\D/', '', $documento),
        $email ?: null,
        pe_usuario(),
        json_encode($request->toArray($localReference), JSON_UNESCAPED_UNICODE),
    ]);

    // ---- 3. Chama a API ----

    try {
        $boleto = pe_boleto_service()->create($request, $localReference);
    } catch (ApiException $e) {
        if ($e->isTransportError()) {
            /* Sem resposta: o boleto PODE ter sido criado. Marcar como
               falha aqui seria mentira, e apagar o registro seria pior —
               ficaria um título vivo no banco sem rastro nenhum aqui. */
            $pdo->prepare("UPDATE pe_boletos SET status = 'unknown', last_error = ? WHERE local_reference = ?")
                ->execute([mb_substr($e->getMessage(), 0, 500), $localReference]);

            pe_log('error', 'boleto', 'Emissão sem resposta (estado indeterminado): ' . $e->getMessage(), $osId);

            pe_json_erro(
                'Não houve resposta da Parcela Express. O boleto pode ter sido gerado — '
                . 'confira no portal antes de emitir outro.',
                502
            );
        }

        /* A API respondeu erro: o título não existe do outro lado. O registro
           fica marcado como falha para auditoria e some da lista do operador. */
        $pdo->prepare("UPDATE pe_boletos SET status = 'failed', last_error = ? WHERE local_reference = ?")
            ->execute([mb_substr($e->getMessage(), 0, 500), $localReference]);

        pe_log('error', 'boleto', 'Emissão recusada pela API: ' . $e->getMessage(), $osId);

        pe_json_erro(
            pe_mensagem_api($e, 'Emitir boleto', Endpoints::build(
                Endpoints::path('BOLETO_CREATE'),
                ['seller_id' => pe_config()['seller_id'] ?? '{seller_id}']
            )),
            502
        );
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE pe_boletos SET status = 'failed', last_error = ? WHERE local_reference = ?")
            ->execute([mb_substr($e->getMessage(), 0, 500), $localReference]);

        pe_log('error', 'boleto', 'Falha inesperada na emissão: ' . $e->getMessage(), $osId);

        pe_json_erro('Não foi possível emitir o boleto: ' . $e->getMessage(), 502);
    }

    // ---- 4. Sucesso ----

    if ($boleto->id === '') {
        // Respondeu 2xx sem identificador: não dá para consultar depois.
        $pdo->prepare("UPDATE pe_boletos SET status = 'unknown', last_error = ? WHERE local_reference = ?")
            ->execute(['A API não retornou identificador do boleto.', $localReference]);

        pe_json_erro(
            'A API respondeu sem identificar o boleto. Confira no portal antes de emitir outro.',
            502
        );
    }

    /* O POST de emissão não devolve o link do PDF — ele vem de
       GET /v1/billets/{id}/url. Buscamos aqui para guardar junto. */
    $urlPdf = $boleto->urlPdf ?? pe_boleto_service()->url($boleto->id);

    $upd = $pdo->prepare(
        "UPDATE pe_boletos
            SET remote_id = ?, status = ?, linha_digitavel = ?, codigo_barras = ?,
                pix_copia_cola = ?, url_pdf = ?, raw_payload = ?
          WHERE local_reference = ?"
    );
    $upd->execute([
        $boleto->id,
        $boleto->status?->value ?? 'pending',
        $boleto->linhaDigitavel,
        $boleto->codigoBarras,
        $boleto->pixCopiaCola,
        $urlPdf,
        json_encode($boleto->raw, JSON_UNESCAPED_UNICODE),
        $localReference,
    ]);

    /* CONFERENCIA DE UNIDADE.
       O campo 'value' foi deduzido como centavos — a API não documentou a
       unidade na mensagem de erro. Se ela interpretar como reais, um boleto
       de R$ 471,64 vira R$ 47.164,00. Comparamos o que voltou com o que
       enviamos e avisamos alto se divergir. */
    $divergencia = null;

    if ($boleto->amountCents !== null && $boleto->amountCents !== $request->amountCents) {
        $divergencia = sprintf(
            'Enviamos %d e a API registrou %d. Confira o valor do boleto antes de entregá-lo ao cliente.',
            $request->amountCents,
            $boleto->amountCents
        );

        pe_log('error', 'boleto', 'DIVERGÊNCIA DE VALOR: ' . $divergencia, $osId);
    }

    pe_log('info', 'boleto', "Boleto {$boleto->id} emitido no valor de R$ {$valor}.", $osId);

    pe_json_ok([
        'boleto' => [
            'id' => $boleto->id,
            'local_reference' => $localReference,
            'valor' => $valor,
            'vencimento' => $vencimento->format('Y-m-d'),
            'vencimento_br' => $vencimento->format('d/m/Y'),
            'linha_digitavel' => $boleto->linhaFormatada(),
            'linha_crua' => $boleto->linhaDigitavel,
            'pix_copia_cola' => $boleto->pixCopiaCola,
            'url_pdf' => $urlPdf,
            'status' => $boleto->status?->value ?? 'pending',
            'status_rotulo' => $boleto->status?->rotulo() ?? 'Aguardando pagamento',
            'pagador_nome' => $request->pagadorNome,
            'divergencia' => $divergencia,
        ],
    ]);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha ao emitir: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível emitir o boleto: ' . $e->getMessage(), 502);
}
