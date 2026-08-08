<?php
/**
 * pagamento_observacao_config.php — Observação da linha de pagamento (Fluxo de Caixa).
 *
 * Espelha o helper do módulo de O.S., porém para conexões PDO.
 * Permite que as consultas do caixa incluam `po.observacao` com segurança,
 * mesmo em bases que ainda não receberam a migração.
 *
 * Uso típico:
 *   require_once __DIR__ . '/pagamento_observacao_config.php';
 *   $sql = 'SELECT po.id, po.total_pagamento' . cx_pag_obs_sql($conn, 'po') . ' FROM pagamento_os po ...';
 */

/**
 * Verifica (e cria, se necessário) a coluna `observacao` em `pagamento_os`.
 *
 * @param PDO $conn
 * @return bool
 */
function cx_pag_obs_existe($conn)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = false;

    if (!($conn instanceof PDO)) {
        return $cache;
    }

    try {
        $st = $conn->query("SHOW COLUMNS FROM pagamento_os LIKE 'observacao'");
        if ($st && $st->fetch()) {
            $cache = true;
            return $cache;
        }

        $conn->exec("ALTER TABLE pagamento_os ADD COLUMN observacao TEXT NULL DEFAULT NULL");

        $st = $conn->query("SHOW COLUMNS FROM pagamento_os LIKE 'observacao'");
        $cache = (bool)($st && $st->fetch());
    } catch (\Throwable $e) {
        $cache = false;
    }

    return $cache;
}

/**
 * Trecho SELECT pronto para concatenar. Retorna `, po.observacao` quando a
 * coluna existe e `, NULL AS observacao` caso contrário — assim o restante do
 * código pode sempre ler o índice `observacao` sem verificações extras.
 *
 * @param PDO    $conn
 * @param string $alias alias da tabela pagamento_os na consulta
 * @return string
 */
function cx_pag_obs_sql($conn, $alias = 'po')
{
    $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string)$alias);
    if ($alias === '') {
        $alias = 'po';
    }

    return cx_pag_obs_existe($conn)
        ? ', ' . $alias . '.observacao AS observacao'
        : ', NULL AS observacao';
}

/**
 * Indica se há ao menos uma observação preenchida na lista de pagamentos.
 * Usado nos relatórios em PDF para só exibir a coluna quando ela for útil.
 *
 * @param array $pagamentos
 * @return bool
 */
function cx_pag_obs_tem_conteudo($pagamentos)
{
    if (!is_array($pagamentos)) {
        return false;
    }

    foreach ($pagamentos as $p) {
        if (isset($p['observacao']) && trim((string)$p['observacao']) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Versão curta da observação, para caber nas células dos relatórios.
 *
 * @param mixed $texto
 * @param int   $limite
 * @return string
 */
function cx_pag_obs_resumo($texto, $limite = 70)
{
    $texto = trim((string)$texto);
    if ($texto === '') {
        return '';
    }

    $texto = preg_replace('/\s+/u', ' ', $texto);

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($texto, 0, $limite, '...', 'UTF-8');
    }

    return (strlen($texto) > $limite) ? (substr($texto, 0, $limite - 3) . '...') : $texto;
}
