<?php
/**
 * =====================================================================
 * base_calculo_lib.php — Base de cálculo por ato (valor declarado)
 * ---------------------------------------------------------------------
 * ATLAS-OS-BASECALC-BUILD: 2026-08-01-v1
 *
 * O PROBLEMA
 * ----------
 * Parte dos atos da tabela de emolumentos é cobrada por FAIXA DE VALOR
 * DECLARADO. A faixa está escrita na própria descrição do ato:
 *
 *     "De R$ 409.942,48 a R$ 512.428,12"
 *     "Acima de R$ 1.024.856,25"
 *     "Até R$ 1.024,85"
 *
 * Nesses atos o selo é escolhido pela faixa, então o sistema de
 * lavratura precisa saber QUAL o valor declarado. Uma única base por
 * O.S. não resolve: um mesmo orçamento pode ter duas escrituras de
 * valores diferentes, cada uma com a sua base.
 *
 * O QUE ESTA BIBLIOTECA FAZ
 * -------------------------
 *   bc_extrair_faixa($descricao)  -> lê a faixa escrita na descrição
 *   bc_exige_base($descricao)     -> o ato exige base de cálculo?
 *   bc_validar($base, $faixa)     -> a base informada cabe na faixa?
 *   bc_migrar($pdo)               -> cria a coluna do item (idempotente)
 *
 * SOBRE A DETECÇÃO
 * ----------------
 * A regra é conservadora de propósito: exige uma PALAVRA-CHAVE de faixa
 * ("de ... a ...", "acima de", "até") junto do valor. Só encontrar um
 * "R$" na descrição não basta — descrições como "multa de R$ 10,00" não
 * são faixa, e obrigar base ali só atrapalharia o escrevente.
 *
 * Como a redação da tabela varia entre serventias, use a página
 * `base_calculo_diagnostico.php` para conferir a cobertura contra a
 * tabela real ANTES de colocar em produção.
 * =====================================================================
 */

if (!defined('BC_LIB')) {
    define('BC_LIB', '2026-08-01');

    /* --------------------------------------------------------------- *
     * Conversão de valores
     * --------------------------------------------------------------- */

    /**
     * "1.024.856,25" | "1024856.25" | 1024856.25  ->  float
     */
    function bc_valor($v): float
    {
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return 0.0;
        }

        $s = preg_replace('/[^\d,.\-]/', '', $s);

        /* Formato brasileiro: a vírgula é o separador decimal. */
        if (strpos($s, ',') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/\.\d{3}(\D|$)/', $s)) {
            /* "1.024.856" — ponto usado como separador de milhar. */
            $s = str_replace('.', '', $s);
        }

        return (float) $s;
    }

    function bc_brl($v): string
    {
        return 'R$ ' . number_format((float) $v, 2, ',', '.');
    }

    /* --------------------------------------------------------------- *
     * Detecção da faixa
     * --------------------------------------------------------------- */

    /** Trecho de expressão regular que casa um valor monetário. */
    function bc_regex_valor(): string
    {
        /* 1.024.856,25 | 1024856,25 | 1024856.25 | 1.024 | 500 */
        return '(?:R\$\s*)?(\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:[,.]\d{1,2})?)';
    }

    /**
     * Lê a faixa de valor declarado escrita na descrição do ato.
     *
     * @return array|null [
     *     'tipo'   => 'intervalo' | 'acima' | 'ate',
     *     'minimo' => float|null,   (null = sem piso)
     *     'maximo' => float|null,   (null = sem teto)
     *     'trecho' => string,       (o texto que casou, para exibir)
     *     'rotulo' => string        (faixa formatada para a tela)
     * ]
     */
    function bc_extrair_faixa($descricao)
    {
        $d = trim((string) $descricao);
        if ($d === '') {
            return null;
        }

        /* Normaliza para a comparação: minúsculas, sem acento, espaços
           colapsados. A descrição original é preservada em $d. */
        $n = bc_normalizar($d);
        $v = bc_regex_valor();

        /* --- 1. Intervalo: "de X a Y" / "de X até Y" / "entre X e Y" --- */
        $padroes = [
            '/\b(?:de|entre)\s+' . $v . '\s+(?:a|ate|e)\s+' . $v . '/i',
            '/\bfaixa\s+(?:de\s+)?' . $v . '\s+(?:a|ate|e)\s+' . $v . '/i',
            '/\bvalor(?:es)?\s+(?:de\s+)?' . $v . '\s+(?:a|ate|e)\s+' . $v . '/i',
        ];
        foreach ($padroes as $p) {
            if (preg_match($p, $n, $m)) {
                $min = bc_valor($m[1]);
                $max = bc_valor($m[2]);
                if ($max >= $min && $max > 0) {
                    return [
                        'tipo'   => 'intervalo',
                        'minimo' => $min,
                        'maximo' => $max,
                        'trecho' => trim($m[0]),
                        'rotulo' => 'de ' . bc_brl($min) . ' a ' . bc_brl($max),
                    ];
                }
            }
        }

        /* --- 2. Sem teto: "acima de X" / "superior a X" / "mais de X" --- */
        $padroes = [
            '/\bacima\s+de\s+' . $v . '/i',
            '/\bsuperior(?:es)?\s+a\s+' . $v . '/i',
            '/\bmais\s+de\s+' . $v . '/i',
            '/\ba\s+partir\s+de\s+' . $v . '/i',
            '/\bexceden(?:te|do)\s+(?:a\s+)?' . $v . '/i',
        ];
        foreach ($padroes as $p) {
            if (preg_match($p, $n, $m)) {
                $min = bc_valor($m[1]);
                if ($min > 0) {
                    return [
                        'tipo'   => 'acima',
                        'minimo' => $min,
                        'maximo' => null,
                        'trecho' => trim($m[0]),
                        'rotulo' => 'acima de ' . bc_brl($min),
                    ];
                }
            }
        }

        /* --- 3. Sem piso: "até X" / "inferior a X" / "no máximo X" --- */
        $padroes = [
            '/\bate\s+' . $v . '/i',
            '/\binferior(?:es)?\s+a\s+' . $v . '/i',
            '/\bno\s+maximo\s+' . $v . '/i',
            '/\bmenor(?:es)?\s+(?:que|de)\s+' . $v . '/i',
        ];
        foreach ($padroes as $p) {
            if (preg_match($p, $n, $m)) {
                $max = bc_valor($m[1]);
                if ($max > 0) {
                    return [
                        'tipo'   => 'ate',
                        'minimo' => null,
                        'maximo' => $max,
                        'trecho' => trim($m[0]),
                        'rotulo' => 'até ' . bc_brl($max),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Minúsculas, sem acento, espaços colapsados. Sem depender de
     * iconv/intl, que nem sempre estão habilitados no XAMPP.
     */
    function bc_normalizar($s): string
    {
        $s = (string) $s;
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

        $de = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï',
               'ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $pa = ['a','a','a','a','a','e','e','e','e','i','i','i','i',
               'o','o','o','o','o','u','u','u','u','c','n'];
        $s = str_replace($de, $pa, $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * O ato exige base de cálculo?
     */
    function bc_exige_base($descricao): bool
    {
        return bc_extrair_faixa($descricao) !== null;
    }

    /* --------------------------------------------------------------- *
     * Validação
     * --------------------------------------------------------------- */

    /**
     * A base informada cabe na faixa do ato?
     *
     * Tolerância de 1 centavo nas bordas: as faixas da tabela costumam
     * ser contíguas (uma termina em ...,12 e a seguinte começa em
     * ...,13), e um arredondamento não pode barrar um lançamento
     * legítimo.
     *
     * @return array ['ok' => bool, 'codigo' => string|null, 'mensagem' => string]
     */
    function bc_validar($base, $faixa): array
    {
        $base = (float) $base;

        if (!$faixa) {
            return ['ok' => true, 'codigo' => null, 'mensagem' => ''];
        }

        if ($base <= 0) {
            return [
                'ok'       => false,
                'codigo'   => 'base_obrigatoria',
                'mensagem' => 'Este ato é cobrado por faixa de valor declarado ('
                            . $faixa['rotulo'] . '). Informe a base de cálculo.',
            ];
        }

        $tol = 0.011;

        if ($faixa['minimo'] !== null && $base < ($faixa['minimo'] - $tol)) {
            return [
                'ok'       => false,
                'codigo'   => 'base_fora_da_faixa',
                'mensagem' => 'A base informada (' . bc_brl($base) . ') está ABAIXO da faixa '
                            . 'deste ato (' . $faixa['rotulo'] . '). Confira o valor declarado '
                            . 'ou selecione o ato da faixa correta.',
            ];
        }

        if ($faixa['maximo'] !== null && $base > ($faixa['maximo'] + $tol)) {
            return [
                'ok'       => false,
                'codigo'   => 'base_fora_da_faixa',
                'mensagem' => 'A base informada (' . bc_brl($base) . ') está ACIMA da faixa '
                            . 'deste ato (' . $faixa['rotulo'] . '). Confira o valor declarado '
                            . 'ou selecione o ato da faixa correta.',
            ];
        }

        return ['ok' => true, 'codigo' => null, 'mensagem' => ''];
    }

    /* --------------------------------------------------------------- *
     * Schema
     * --------------------------------------------------------------- */

    /**
     * Cria a coluna `base_de_calculo` em `ordens_de_servico_itens` e em
     * `atos_liquidados` / `atos_manuais_liquidados`. Idempotente.
     *
     * Nas tabelas de liquidação a base fica registrada junto do ato
     * liquidado — é o dado que sustenta o selo emitido, e ele não pode
     * mudar depois se alguém editar a O.S.
     */
    function bc_migrar(PDO $pdo): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;

        $tabelas = ['ordens_de_servico_itens', 'atos_liquidados', 'atos_manuais_liquidados'];

        foreach ($tabelas as $t) {
            try {
                $existe = (int) $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = " . $pdo->quote($t) . "
                        AND COLUMN_NAME = 'base_de_calculo'"
                )->fetchColumn();

                if ($existe === 0) {
                    $pdo->exec("ALTER TABLE `$t` ADD COLUMN `base_de_calculo` DECIMAL(15,2) NULL");
                }
            } catch (Throwable $e) {
                error_log('[bc_migrar] ' . $t . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Base de cálculo de um item, já no formato da API.
     * Devolve NULL quando não informada — nunca 0.
     *
     * @return float|null
     */
    function bc_base_item(array $item)
    {
        if (!array_key_exists('base_de_calculo', $item) || $item['base_de_calculo'] === null) {
            return null;
        }

        $v = (float) $item['base_de_calculo'];

        return $v >= 0.001 ? round($v, 2) : null;
    }
}
