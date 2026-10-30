<?php
/**
 * =====================================================================
 * pagamento_exclusao_lib.php — Quem pode excluir um pagamento da O.S.
 * ---------------------------------------------------------------------
 * Um pagamento só pode ser excluído quando TODAS as condições valem:
 *   1. quem exclui é o colaborador que lançou o pagamento ou o Oficial
 *      (nível "administrador");
 *   2. o pagamento é de hoje;
 *   3. nenhum ato da O.S. foi liquidado — OU, se já houver liquidação, quem
 *      exclui é o Oficial e o valor do pagamento ainda está disponível na O.S.
 *      (não foi consumido por liquidações, devoluções, repasses ou
 *      transferências de saldo);
 *   4. o caixa do colaborador que lançou o pagamento (na data do pagamento)
 *      ainda está ABERTO. Depois que esse caixa é fechado, o pagamento não
 *      pode mais ser excluído — nem pelo Oficial (ele pode, se for o caso,
 *      desfazer o fechamento do caixa no Fluxo de Caixa).
 * O registro "Isento de Pagamento" não movimenta o caixa: dispensa a regra 4.
 * Pagamentos da API ("API/<sistema>: <pessoa>") pertencem ao colaborador
 * indicado (mesma regra do Fluxo de Caixa).
 *
 * Troca da forma de pagamento (os_pagto_alteracao): somente o Oficial, no dia
 * do pagamento e com o caixa de quem lançou ainda aberto. Vale também depois
 * de atos liquidados (o valor não muda, só a forma). Fechado o caixa ou
 * passado o dia, nem o Oficial altera.
 * =====================================================================
 */
require_once __DIR__ . '/os_versao.php';
require_once __DIR__ . '/caixa_pagamento_lib.php';
require_once __DIR__ . '/../caixa/caixa_funcionario.php';
require_once __DIR__ . '/transferencia_saldo_lib.php';

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

    function os_pagto_moeda(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    /**
     * Valor ainda disponível na O.S. (o mesmo cálculo da tela da O.S.): pagamentos + créditos de
     * transferência − devoluções − transferências enviadas − repasses ao credor − atos liquidados.
     */
    function os_pagto_disponivel(mysqli $conn, int $osId): float
    {
        $sql = "SELECT
                  (SELECT COALESCE(SUM(total_pagamento), 0) FROM pagamento_os WHERE ordem_de_servico_id = ?)
                - (SELECT COALESCE(SUM(total_devolucao), 0) FROM devolucao_os WHERE ordem_de_servico_id = ?)
                - (SELECT COALESCE(SUM(total_repasse), 0) FROM repasse_credor WHERE ordem_de_servico_id = ?)
                - (SELECT COALESCE(SUM(total), 0) FROM atos_liquidados WHERE ordem_servico_id = ?)
                - (SELECT COALESCE(SUM(total), 0) FROM atos_manuais_liquidados WHERE ordem_servico_id = ?)";
        $st = $conn->prepare($sql);
        $st->bind_param('iiiii', $osId, $osId, $osId, $osId, $osId);
        $st->execute();
        $st->bind_result($v);
        $st->fetch();
        $st->close();
        $ts = function_exists('ts_totais') ? ts_totais($osId) : ['entrada' => 0, 'saida' => 0];
        return round(max(0.0, (float) $v + (float) $ts['entrada'] - (float) $ts['saida']), 2);
    }

    /** Formas de pagamento que podem ser escolhidas na troca (as mesmas da tela de pagamento). */
    function os_pagto_formas_troca(): array
    {
        return ['Espécie', 'Crédito', 'Débito', 'PIX', 'Centrais Eletrônicas', 'Transferência Bancária', 'Depósito Bancário', 'Boleto', 'Cheque'];
    }

    /**
     * Pode trocar a forma de pagamento? Somente o Oficial, no dia do pagamento e com o caixa
     * de quem lançou ainda aberto. Devolve ['pode' => bool, 'motivo' => string, 'dono' => string].
     */
    function os_pagto_alteracao(mysqli $conn, array $pag, string $usuario): array
    {
        $dono   = os_pagto_dono($conn, (string) ($pag['funcionario'] ?? ''));
        $dataPg = substr((string) ($pag['data_pagamento'] ?? ''), 0, 10);
        $forma  = trim((string) ($pag['forma_de_pagamento'] ?? ''));
        $r = ['pode' => false, 'motivo' => '', 'dono' => $dono];

        if (!os_pagto_eh_oficial($conn, $usuario)) {
            $r['motivo'] = 'Somente o Oficial pode alterar a forma de pagamento.';
            return $r;
        }
        if ($dataPg !== date('Y-m-d')) {
            $r['motivo'] = 'A forma de pagamento só pode ser alterada no dia em que o pagamento foi lançado.';
            return $r;
        }
        if (!in_array($forma, os_pagto_formas_troca(), true)) {
            $r['motivo'] = 'A forma "' . $forma . '" não pode ser trocada por aqui.';
            return $r;
        }
        $st = os_pagto_status_caixa($conn, $dono, $dataPg);
        if ($st === 'fechado') {
            $r['motivo'] = 'O caixa de ' . $dono . ' já foi fechado: a forma de pagamento não pode mais ser alterada.';
            return $r;
        }
        if ($st !== 'aberto') {
            $r['motivo'] = 'O pagamento não está em um caixa aberto de ' . $dono . ': a forma não pode ser alterada.';
            return $r;
        }
        $r['pode'] = true;
        return $r;
    }

    /**
     * Pode excluir? Devolve ['pode' => bool, 'motivo' => string, 'dono' => string, 'mesmo_dia' => bool].
     * @param array $pag linha de pagamento_os (id, ordem_de_servico_id, funcionario, data_pagamento, forma_de_pagamento, total_pagamento)
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
            // Com ato liquidado: só o Oficial, e só se o valor ainda não foi consumido
            if (!os_pagto_eh_oficial($conn, $usuario)) {
                $r['motivo'] = 'Algum ato da O.S. já foi liquidado: somente o Oficial pode excluir este pagamento.';
                return $r;
            }
            $valor = round((float) ($pag['total_pagamento'] ?? 0), 2);
            $disp  = os_pagto_disponivel($conn, (int) $pag['ordem_de_servico_id']);
            if ($valor > $disp + 0.004) {
                $r['motivo'] = 'O valor deste pagamento (' . os_pagto_moeda($valor) . ') já foi usado na O.S.: só '
                    . os_pagto_moeda($disp) . ' estão disponíveis (o restante foi consumido pelas liquidações, devoluções, repasses ou transferências).';
                return $r;
            }
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
