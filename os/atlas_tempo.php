<?php
/**
 * =====================================================================
 * atlas_tempo.php — Fuso horário único do módulo O.S. (Atlas)
 * ---------------------------------------------------------------------
 * ATLAS-TEMPO-BUILD: 2026-07-31-fuso-unico
 *
 * PROBLEMA QUE ISTO RESOLVE
 * -------------------------
 * O módulo grava datas de duas maneiras:
 *
 *   a) pelo PHP  -> date('Y-m-d H:i:s')   (repasses, devoluções, guias,
 *                                          comprovantes de pagamento)
 *   b) pelo MySQL-> NOW() / DEFAULT CURRENT_TIMESTAMP
 *                                         (atos liquidados, pagamentos,
 *                                          anexos, logs, entrega, cancelamento)
 *
 * O PHP já estava no fuso de Brasília/Fortaleza (-03:00), mas a SESSÃO do
 * MySQL herdava o fuso do servidor — que está deslocado. Resultado: tudo
 * que é gravado com NOW()/CURRENT_TIMESTAMP saía horas atrasado, enquanto o
 * que é gravado pelo PHP saía certo. Daí a inconsistência na tela.
 *
 * A correção é alinhar os dois: PHP em America/Fortaleza e a sessão do
 * MySQL em '-03:00'. Usa-se o OFFSET NUMÉRICO (e não o nome da zona)
 * porque nomes como 'America/Fortaleza' exigem as tabelas de fuso do MySQL
 * (mysql.time_zone_name), que o XAMPP não instala por padrão.
 *
 * COMO USAR
 * ---------
 * Já está incluído nos arquivos de conexão (db_connection*.php,
 * pagamento_anexos_config.php, assinatura_os_config.php). Toda conexão nova
 * criada em qualquer ponto do sistema deve chamar:
 *
 *     atlas_alinhar_fuso($conn);   // aceita PDO e mysqli
 *
 * Para gerar um "agora" no PHP, prefira atlas_agora().
 * =====================================================================
 */

if (!defined('ATLAS_TZ')) {
    /* Maranhão: UTC-03:00 o ano inteiro (sem horário de verão desde 2019). */
    define('ATLAS_TZ', 'America/Fortaleza');
    define('ATLAS_TZ_OFFSET', '-03:00');
}

if (!function_exists('atlas_boot_tempo')) {
    /**
     * Fixa o fuso do PHP. Idempotente e barato — pode ser chamado à vontade.
     */
    function atlas_boot_tempo(): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;

        @date_default_timezone_set(ATLAS_TZ);
        @ini_set('date.timezone', ATLAS_TZ);
    }
}

if (!function_exists('atlas_alinhar_fuso')) {
    /**
     * Alinha a sessão do banco ao fuso do PHP.
     *
     * Vale para NOW(), CURDATE(), CURRENT_TIMESTAMP e para os DEFAULT
     * CURRENT_TIMESTAMP das colunas — todos passam a gravar em -03:00.
     *
     * @param  PDO|mysqli|null $conn
     * @return PDO|mysqli|null O mesmo objeto recebido (permite encadear).
     */
    function atlas_alinhar_fuso($conn = null)
    {
        atlas_boot_tempo();

        if ($conn === null) {
            return $conn;
        }

        $sql = "SET time_zone = '" . ATLAS_TZ_OFFSET . "'";

        try {
            if ($conn instanceof PDO) {
                $conn->exec($sql);
            } elseif (class_exists('mysqli') && $conn instanceof mysqli) {
                @$conn->query($sql);
            }
        } catch (Throwable $e) {
            /* Sem permissão para trocar o fuso da sessão: segue com o padrão
               do servidor. Não é motivo para derrubar a página. */
            error_log('[atlas_tempo] ' . $e->getMessage());
        }

        return $conn;
    }
}

if (!function_exists('atlas_agora')) {
    /**
     * "Agora" no fuso da serventia, pronto para gravar no banco.
     */
    function atlas_agora(string $formato = 'Y-m-d H:i:s'): string
    {
        atlas_boot_tempo();
        return date($formato);
    }
}

if (!function_exists('atlas_data_br')) {
    /**
     * Formata para exibição (dd/mm/aaaa hh:mm). Devolve '—' se vazio/inválido.
     */
    function atlas_data_br($valor, string $formato = 'd/m/Y H:i'): string
    {
        atlas_boot_tempo();

        if (empty($valor) || $valor === '0000-00-00 00:00:00') {
            return '—';
        }

        $ts = is_numeric($valor) ? (int) $valor : strtotime((string) $valor);
        return $ts ? date($formato, $ts) : '—';
    }
}

/* Aplica o fuso do PHP já no include. */
atlas_boot_tempo();
