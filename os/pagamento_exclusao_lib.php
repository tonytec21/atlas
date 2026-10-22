<?php
/**
 * =====================================================================
 * pagamento_exclusao_lib.php — Quem pode excluir um pagamento da O.S.
 * ---------------------------------------------------------------------
 * Um pagamento só pode ser excluído quando TODAS as condições valem:
 *   1. quem exclui é o colaborador que lançou o pagamento ou o Oficial
 *      (nível "administrador");
 *   2. o pagamento é de hoje;
 *   3. nenhum ato da O.S. foi liquidado;
 *   4. o caixa do colaborador que lançou o pagamento (na data do pagamento)
 *      ainda está ABERTO. Depois que esse caixa é fechado, o pagamento não
 *      pode mais ser excluído — nem pelo Oficial (ele pode, se for o caso,
 *      desfazer o fechamento do caixa no Fluxo de Caixa).
 * O registro "Isento de Pagamento" não movimenta o caixa: dispensa a regra 4.
 * Pagamentos da API ("API/<sistema>: <pessoa>") pertencem ao colaborador
 * indicado (mesma regra do Fluxo de Caixa).
 * =====================================================================
 */
require_once __DIR__ . '/os_versao.php';
require_once __DIR__ . '/caixa_pagamento_lib.php';
require_once __DIR__ . '/../caixa/caixa_funcionario.php';

if (!function_exists('os_pagto_exclusao')) {

    /** Usuário é o Oficial (nível administrador, ativo)? */
    function os_pagto_eh_oficial(mysqli $conn, string $usuario): bool
    {
        static $cache = [];
        if (array_key_exists($usuario, $cache)) {
            return $cache[$usuario];
        }
        $st = $conn->prepare('SELECT nivel_de_acesso, status FROM funcionarios WHERE usuario = ? LIMIT 1');
        $st->bind_param('s', $usuario);
        $st->execute();
        $st->bind_result($nivel, $status);
        $ok = $st->fetch()
            && in_array(mb_strtolower(trim((string) $nivel), 'UTF-8'), ['administrador', 'admin'], true)
            && mb_strtolower(trim((string) $status), 'UTF-8') === 'ativo';
        $st->close();
        return $cache[$usuario] = $ok;
    }

    /** Usuário (colaborador) dono do pagamento, já resolvendo os lançados pela API. */
    function os_pagto_dono(mysqli $conn, string $funcionario): string
    {
        static $cache = [];
        if (isset($cache[$funcionario])) {
            return $cache[$funcionario];
        }
        $dono = $funcionario;
        if (stripos($funcionario, 'API/') === 0) {
            $st = $conn->prepare('SELECT ' . cx_func_sql('?'));
            if ($st) {
                $st->bind_param(str_repeat('s', substr_count(cx_func_sql('?'), '?')), ...array_fill(0, substr_count(cx_func_sql('?'), '?'), $funcionario));
                $st->execute();
                $st->bind_result($v);
                if ($st->fetch() && $v) {
                    $dono = (string) $v;
                }
                $st->close();
            }
        }
        return $cache[$funcionario] = $dono;
    }

    /** A O.S. tem algum ato liquidado (total ou parcial, normal ou manual)? */
    function os_pagto_os_tem_liquidacao(mysqli $conn, int $osId): bool
    {
        static $cache = [];
        if (isset($cache[$osId])) {
            return $cache[$osId];
        }
        $sql = "SELECT
                  (SELECT COUNT(*) FROM atos_liquidados WHERE ordem_servico_id = ?)
                + (SELECT COUNT(*) FROM atos_manuais_liquidados WHERE ordem_servico_id = ?)
                + (SELECT COUNT(*) FROM ordens_de_servico_itens WHERE ordem_servico_id = ? AND LOWER(COALESCE(status,'')) LIKE 'liquidad%')";
        $st = $conn->prepare($sql);
        $st->bind_param('iii', $osId, $osId, $osId);
        $st->execute();
        $st->bind_result($n);
        $st->fetch();
        $st->close();
        return $cache[$osId] = ((int) $n > 0);
    }

    /** Situação ('aberto' | 'fechado' | null = sem caixa) do caixa do colaborador numa data. */
    function os_pagto_status_caixa(mysqli $conn, string $usuario, string $data): ?string
    {
        static $cache = [];
        $k = $usuario . '|' . $data;
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        $fim = date('Y-m-d', strtotime($data . ' +1 day'));
        $st = $conn->prepare('SELECT status FROM caixa WHERE funcionario = ? AND data_caixa >= ? AND data_caixa < ? ORDER BY id ASC LIMIT 1');
        $st->bind_param('sss', $usuario, $data, $fim);
        $st->execute();
        $st->bind_result($s);
        $r = $st->fetch() ? mb_strtolower(trim((string) $s), 'UTF-8') : null;
        $st->close();
        return $cache[$k] = $r;
    }

    /**
     * Pode excluir? Devolve ['pode' => bool, 'motivo' => string, 'dono' => string, 'mesmo_dia' => bool].
     * @param array $pag linha de pagamento_os (id, ordem_de_servico_id, funcionario, data_pagamento, forma_de_pagamento)
     */
    function os_pagto_exclusao(mysqli $conn, array $pag, string $usuario): array
    {
        $dono   = os_pagto_dono($conn, (string) ($pag['funcionario'] ?? ''));
        $dataPg = substr((string) ($pag['data_pagamento'] ?? ''), 0, 10);
        $hoje   = date('Y-m-d');
        $r = ['pode' => false, 'motivo' => '', 'dono' => $dono, 'mesmo_dia' => ($dataPg === $hoje)];

        if (!os_pagto_eh_oficial($conn, $usuario) && mb_strtolower($dono, 'UTF-8') !== mb_strtolower($usuario, 'UTF-8')) {
            $r['motivo'] = 'Somente quem lançou o pagamento (' . $dono . ') ou o Oficial pode excluí-lo.';
            return $r;
        }
        if ($dataPg !== $hoje) {
            $r['motivo'] = 'Só é possível excluir pagamentos lançados hoje.';
            return $r;
        }
        if (os_pagto_os_tem_liquidacao($conn, (int) $pag['ordem_de_servico_id'])) {
            $r['motivo'] = 'Não é possível excluir pagamentos depois que algum ato da O.S. foi liquidado.';
            return $r;
        }
        if (!os_caixa_forma_isenta((string) ($pag['forma_de_pagamento'] ?? ''))) {
            $st = os_pagto_status_caixa($conn, $dono, $dataPg);
            if ($st === 'fechado') {
                $r['motivo'] = 'O caixa de ' . $dono . ' já foi fechado: o pagamento não pode mais ser excluído.';
                return $r;
            }
            if ($st !== 'aberto') {
                $r['motivo'] = 'O pagamento não está em um caixa aberto de ' . $dono . ': não pode ser excluído.';
                return $r;
            }
        }
        $r['pode'] = true;
        return $r;
    }
}
