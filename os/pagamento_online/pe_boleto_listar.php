<?php

declare(strict_types=1);

/**
 * pe_boleto_listar.php — Boletos emitidos para uma O.S.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\Boleto\BoletoStatus;

pe_guard_operacao();

if (!pe_boleto_habilitado()) {
    pe_json_erro('Emissão de boleto não está habilitada.', 403);
}

$osId = (int) ($_GET['os_id'] ?? 0);

if ($osId <= 0) {
    pe_json_erro('O.S. inválida.');
}

try {
    pe_migrar();

    /* 'failed' fica de fora: são tentativas que a API recusou, então o
       título nunca existiu. O registro permanece no banco para auditoria,
       mas mostrá-lo ao operador só geraria dúvida sobre um boleto que o
       banco desconhece. */
    $stmt = pe_pdo()->prepare(
        "SELECT local_reference, remote_id, status, amount_cents, vencimento,
                linha_digitavel, url_pdf, pix_copia_cola, pagamento_id, pagador_nome
           FROM pe_boletos
          WHERE ordem_de_servico_id = ?
            AND status <> 'failed'
       ORDER BY id DESC"
    );
    $stmt->execute([$osId]);

    $linhas = array_map(static function (array $r): array {
        $st = BoletoStatus::tryFrom((string) $r['status']);
        $venc = $r['vencimento'] ? new DateTimeImmutable((string) $r['vencimento']) : null;

        // Vencido é uma leitura de calendário, não um estado que a API precise
        // nos informar: se está aberto e a data passou, mostramos como vencido.
        if ($st === BoletoStatus::Pending && $venc && $venc < new DateTimeImmutable('today')) {
            $st = BoletoStatus::Overdue;
        }

        /* Sem remote_id não há o que consultar na API: o botão "Conferir"
           precisa ficar de fora, senão o operador clica e recebe
           "Boleto não encontrado". */
        $temId = !empty($r['remote_id']);

        return [
            'local_reference' => $r['local_reference'],
            'id'              => $r['remote_id'],
            'status'          => $st?->value ?? (string) $r['status'],
            'status_rotulo'   => $st?->rotulo() ?? (string) $r['status'],
            'aberto'          => ($st?->isOpen() ?? false) && $temId,
            'indeterminado'   => $st === BoletoStatus::Unknown || !$temId,
            'pago'            => $st?->isPaid() ?? false,
            'valor'           => ((int) $r['amount_cents']) / 100,
            'vencimento_br'   => $venc?->format('d/m/Y'),
            'linha_digitavel' => $r['linha_digitavel'],
            'url_pdf'         => $r['url_pdf'],
            'pix_copia_cola'  => $r['pix_copia_cola'],
            'pagador_nome'    => $r['pagador_nome'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    pe_json_ok(['boletos' => $linhas]);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha ao listar: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível listar os boletos.', 500);
}
