<?php
/**
 * =====================================================================
 * caixa_pagamento_lib.php — Pagamento na O.S. exige o caixa do dia aberto
 * ---------------------------------------------------------------------
 * O pagamento lançado na O.S. entra no caixa de quem lançou, na data de
 * hoje. Por isso só é aceito quando esse caixa está ABERTO:
 *   - sem caixa aberto hoje  → bloqueia (abra o caixa no Fluxo de Caixa);
 *   - caixa de hoje fechado  → bloqueia (o Oficial pode desfazer o fechamento).
 * O registro "Isento de Pagamento" não movimenta dinheiro e não é bloqueado.
 * Pagamentos automáticos (API e pagamento online) não passam por aqui.
 * =====================================================================
 */
require_once __DIR__ . '/os_versao.php';

if (!function_exists('os_caixa_situacao')) {

    /** Formas que não movimentam o caixa (não exigem caixa aberto). */
    function os_caixa_forma_isenta(string $forma): bool
    {
        return in_array(mb_strtolower(trim($forma), 'UTF-8'), ['isento de pagamento', 'isento'], true);
    }

    /**
     * Situação do caixa de hoje do usuário (mesma regra do Fluxo de Caixa:
     * vale o primeiro caixa aberto na data).
     * @param mysqli|PDO $conn
     * @return array{ok:bool, motivo:string, mensagem:string, data:string}
     */
    function os_caixa_situacao($conn, string $usuario): array
    {
        $hoje = date('Y-m-d');
        $amanha = date('Y-m-d', strtotime('+1 day'));
        $sql = 'SELECT status FROM caixa WHERE funcionario = ? AND data_caixa >= ? AND data_caixa < ? ORDER BY id ASC LIMIT 1';
        $status = null;
        if ($conn instanceof PDO) {
            $st = $conn->prepare($sql);
            $st->execute([$usuario, $hoje, $amanha]);
            $v = $st->fetchColumn();
            $status = ($v === false) ? null : (string) $v;
        } else {
            $st = $conn->prepare($sql);
            $st->bind_param('sss', $usuario, $hoje, $amanha);
            $st->execute();
            $st->bind_result($v);
            $status = $st->fetch() ? (string) $v : null;
            $st->close();
        }

        if ($status === null) {
            return ['ok' => false, 'motivo' => 'sem_caixa', 'data' => $hoje,
                'mensagem' => 'Você ainda não abriu o seu caixa de hoje. Abra o caixa no Fluxo de Caixa antes de lançar pagamentos na O.S.'];
        }
        if (mb_strtolower(trim($status), 'UTF-8') === 'fechado') {
            return ['ok' => false, 'motivo' => 'caixa_fechado', 'data' => $hoje,
                'mensagem' => 'O seu caixa de hoje já foi fechado. Para lançar pagamentos, peça ao Oficial para desfazer o fechamento do caixa.'];
        }
        return ['ok' => true, 'motivo' => 'aberto', 'data' => $hoje, 'mensagem' => ''];
    }
}
