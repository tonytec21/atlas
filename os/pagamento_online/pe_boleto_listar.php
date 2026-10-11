<?php

declare(strict_types=1);

/**
 * pe_boleto_listar.php — Boletos emitidos para uma O.S.
 */

include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/pe_lib.php';

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

    /* 'failed' fica de fora: a API recusou, o boleto não existe. */
    $stmt = pe_pdo()->prepare(
        "SELECT local_reference, remote_id, remote_sale_id, status, amount_cents, vencimento,
                linha_digitavel, url_pdf, url_pagamento, pagamento_id, pagador_nome
           FROM pe_boletos
          WHERE ordem_de_servico_id = ? AND status <> 'failed'
       ORDER BY id DESC"
    );
    $stmt->execute([$osId]);

    $rotulos = [
        'pending' => 'Aguardando pagamento', 'paid' => 'Pago', 'overdue' => 'Vencido',
        'canceled' => 'Cancelado', 'unknown' => 'Emissão não confirmada',
    ];

    $linhas = array_map(static function (array $r) use ($rotulos): array {
        $st = (string) $r['status'];
        $venc = $r['vencimento'] ? new DateTimeImmutable((string) $r['vencimento']) : null;

        if ($st === 'pending' && $venc && $venc < new DateTimeImmutable('today')) {
            $st = 'overdue';
        }

        $temId = !empty($r['remote_sale_id']) || !empty($r['remote_id']);

        return [
            'local_reference' => $r['local_reference'],
            'id'              => $r['remote_id'],
            'status'          => $st,
            'status_rotulo'   => $rotulos[$st] ?? $st,
            'aberto'          => in_array($st, ['pending', 'overdue'], true) && $temId,
            'indeterminado'   => $st === 'unknown' || !$temId,
            'pago'            => $st === 'paid',
            'valor'           => ((int) $r['amount_cents']) / 100,
            'vencimento_br'   => $venc?->format('d/m/Y'),
            'linha_digitavel' => $r['linha_digitavel'],
            'url_pdf'         => $r['url_pdf'],
            'url_pagamento'   => $r['url_pagamento'],
            'pix_copia_cola'  => null,
            'pagador_nome'    => $r['pagador_nome'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    pe_json_ok(['boletos' => $linhas]);
} catch (Throwable $e) {
    pe_log('error', 'boleto', 'Falha ao listar: ' . $e->getMessage(), $osId);
    pe_json_erro('Não foi possível listar os boletos.', 500);
}
