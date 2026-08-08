<?php
/**
 * pagamento_observacao_config.php — Observação da linha de pagamento da O.S.
 *
 * Garante a existência da coluna `observacao` na tabela `pagamento_os` e
 * oferece helpers de normalização/exibição do texto.
 *
 * Uso típico:
 *   require_once __DIR__ . '/pagamento_observacao_config.php';
 *   po_obs_garantir_coluna($conn);   // $conn = mysqli
 */

if (!defined('PO_OBS_LIMITE')) {
    define('PO_OBS_LIMITE', 500); // caracteres
}

/**
 * Verifica (e cria, se necessário) a coluna `observacao` em `pagamento_os`.
 * O resultado é memorizado por requisição para evitar consultas repetidas.
 *
 * @param mysqli $conn
 * @return bool true se a coluna existe/foi criada
 */
function po_obs_garantir_coluna($conn)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = false;

    if (!($conn instanceof mysqli)) {
        return $cache;
    }

    try {
        $res = @$conn->query("SHOW COLUMNS FROM pagamento_os LIKE 'observacao'");
        if ($res && $res->num_rows > 0) {
            $cache = true;
            return $cache;
        }

        @$conn->query("ALTER TABLE pagamento_os ADD COLUMN observacao TEXT NULL DEFAULT NULL");

        $res = @$conn->query("SHOW COLUMNS FROM pagamento_os LIKE 'observacao'");
        $cache = ($res && $res->num_rows > 0);
    } catch (\Throwable $e) {
        $cache = false;
    }

    return $cache;
}

/**
 * Normaliza o texto da observação antes de gravar:
 * remove tags, colapsa espaços em excesso e aplica o limite de caracteres.
 *
 * @param mixed $texto
 * @return string string vazia quando não há observação
 */
function po_obs_normalizar($texto)
{
    if (!is_string($texto)) {
        return '';
    }

    $texto = strip_tags($texto);
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
    $texto = preg_replace('/[ \t]{2,}/', ' ', $texto);
    $texto = trim($texto);

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, PO_OBS_LIMITE, 'UTF-8');
    }

    return substr($texto, 0, PO_OBS_LIMITE);
}

/**
 * Versão curta para exibição em tabelas/relatórios.
 *
 * @param mixed $texto
 * @param int   $limite
 * @return string
 */
function po_obs_resumo($texto, $limite = 60)
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
