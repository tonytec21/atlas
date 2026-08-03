<?php
/**
 * =====================================================================
 * caixa_funcionario.php — Normalização do campo "funcionario"
 * ---------------------------------------------------------------------
 * ATLAS-CAIXA-BUILD: 2026-08-01-funcionario-api
 *
 * O PROBLEMA
 * ----------
 * A API do módulo O.S. grava o campo `funcionario` com a marca de origem:
 *
 *     API/BookC: ADMIN
 *     API/BookC: Antonio José Martins Garcia
 *
 * A marca é proposital — na tela de Logs de Liquidação ela mostra que o
 * ato veio da integração, e não de alguém digitando no Atlas. Mas o
 * fluxo de caixa agrupa por esse mesmo campo, então o texto com prefixo
 * virava um "funcionário" à parte e abria um caixa só dele, separado do
 * caixa real do colaborador.
 *
 * A SOLUÇÃO
 * ---------
 * O caixa passa a enxergar o campo já resolvido: tudo que vier no
 * formato `API/<sistema>: <pessoa>` é convertido para o `usuario` do
 * colaborador, casando <pessoa> contra `funcionarios.nome_completo` ou
 * contra `funcionarios.usuario`. O dado no banco NÃO é alterado — a
 * marca continua lá para auditoria; apenas a leitura do caixa é
 * normalizada.
 *
 * Exemplos (com o cadastro do print):
 *     'API/BookC: Antonio José Martins Garcia'  ->  'ADMIN'
 *     'API/BookC: ADMIN'                        ->  'ADMIN'
 *     'API/BookC: JOÃO DA SILVA RODRIGUES'      ->  'JOAO'
 *     'ADMIN'                                   ->  'ADMIN'   (intocado)
 *
 * NÃO ENCONTROU?
 * --------------
 * O valor original é mantido. É de propósito: melhor o caixa mostrar
 * `API/BookC: Fulano` isolado — sinal visível de que o nome não bate com
 * ninguém do cadastro — do que somar silenciosamente no caixa errado.
 *
 * COMO USAR
 * ---------
 *   require_once __DIR__ . '/caixa_funcionario.php';
 *
 *   // Em SQL, no lugar da coluna:
 *   $sql = 'SELECT ' . cx_func_sql('al.funcionario') . ' AS funcionario, ...';
 *   $sql = '... WHERE ' . cx_func_sql('al.funcionario') . ' = :funcionario';
 *
 *   // Em PHP, sobre um valor já lido:
 *   $usuario = cx_func($linha['funcionario'], $conn);
 * =====================================================================
 */

if (!defined('CX_FUNC_LIB')) {
    define('CX_FUNC_LIB', '2026-08-01');

    /** Prefixo que a API do módulo O.S. usa em `funcionario`. */
    define('CX_FUNC_PREFIXO', 'API/');

    /**
     * Expressão SQL que devolve o `usuario` do colaborador a partir de
     * uma coluna `funcionario`.
     *
     * A subconsulta correlacionada só é avaliada nas linhas que casam com
     * 'API/%:%', então as liquidações normais (a esmagadora maioria) não
     * pagam nada por isto — o CASE curto-circuita antes.
     *
     * Usa LIMIT 1 em vez de JOIN de propósito: um JOIN que casasse duas
     * linhas de `funcionarios` (uma pelo nome, outra pelo usuário)
     * duplicaria o registro e dobraria os totais do caixa.
     *
     * @param string $coluna Coluna qualificada, ex.: 'al.funcionario'.
     */
    function cx_func_sql(string $coluna = 'funcionario'): string
    {
        /* Texto após o último ':' — o nome ou usuário do operador. */
        $pessoa = "TRIM(SUBSTRING_INDEX($coluna, ':', -1))";

        return "
        CASE
            WHEN $coluna LIKE 'API/%:%' THEN COALESCE((
                SELECT cxf.usuario
                  FROM funcionarios cxf
                 WHERE cxf.nome_completo = $pessoa
                    OR cxf.usuario       = $pessoa
                 ORDER BY (cxf.usuario = $pessoa) DESC, cxf.id ASC
                 LIMIT 1
            ), $coluna)
            ELSE $coluna
        END";
    }

    /**
     * Versão PHP da mesma regra, para valores já carregados.
     *
     * @param  string|null $valor Conteúdo bruto do campo `funcionario`.
     * @param  PDO|null    $conn  Conexão; se ausente, devolve o valor original.
     * @return string
     */
    function cx_func($valor, ?PDO $conn = null): string
    {
        static $cache = [];

        $valor = trim((string) $valor);

        if ($valor === '' || stripos($valor, CX_FUNC_PREFIXO) !== 0) {
            return $valor;
        }

        $pos = strrpos($valor, ':');
        if ($pos === false) {
            /* 'API/BookC' sem operador: não há a quem atribuir. */
            return $valor;
        }

        $pessoa = trim(substr($valor, $pos + 1));
        if ($pessoa === '') {
            return $valor;
        }

        $chave = mb_strtoupper($pessoa, 'UTF-8');
        if (array_key_exists($chave, $cache)) {
            return $cache[$chave] ?: $valor;
        }

        if (!$conn instanceof PDO) {
            return $valor;
        }

        try {
            $st = $conn->prepare(
                "SELECT usuario FROM funcionarios
                  WHERE nome_completo = :p OR usuario = :p2
                  ORDER BY (usuario = :p3) DESC, id ASC
                  LIMIT 1"
            );
            $st->execute([':p' => $pessoa, ':p2' => $pessoa, ':p3' => $pessoa]);
            $usuario = $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[cx_func] ' . $e->getMessage());
            return $valor;
        }

        $cache[$chave] = $usuario ?: '';

        return $usuario ?: $valor;
    }

    /**
     * Normaliza o campo `funcionario` de uma lista de linhas.
     * Guarda o valor bruto em `funcionario_origem` para quem quiser
     * exibir a marca da API no detalhamento.
     */
    function cx_func_linhas(array $linhas, ?PDO $conn = null, string $campo = 'funcionario'): array
    {
        foreach ($linhas as &$l) {
            if (!isset($l[$campo])) {
                continue;
            }
            $bruto = (string) $l[$campo];
            $novo  = cx_func($bruto, $conn);
            if ($novo !== $bruto) {
                $l[$campo . '_origem'] = $bruto;
            }
            $l[$campo] = $novo;
        }
        unset($l);

        return $linhas;
    }
}
