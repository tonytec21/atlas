<?php
/* =============================================================================
   ATLAS - MODULO DE OFICIOS
   busca_helper.php - Motor de busca avancada / filtros inteligentes
   -----------------------------------------------------------------------------
   Responsabilidades:
     - Conexao dedicada ao banco oficios_db
     - Descoberta defensiva das colunas existentes (nao quebra se faltar coluna)
     - Parser da sintaxe de busca inteligente (operadores campo:valor, frases,
       exclusoes, intervalos de data, palavras-chave em pt-BR)
     - Construcao de SQL com prepared statements (sem concatenacao de valores)
     - Calculo de relevancia (score) para ordenacao inteligente
     - Facetas dinamicas (contagens por destinatario, assinante, cargo, ano)
     - Sugestoes de autocomplete
   -----------------------------------------------------------------------------
   Todas as consultas usam bind de parametros. Nenhum valor de usuario entra
   diretamente no SQL.
============================================================================= */

if (!defined('OFB_LOADED')) {
    define('OFB_LOADED', true);
}

/* -----------------------------------------------------------------------------
   1. CONEXAO
----------------------------------------------------------------------------- */
function ofb_db()
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "oficios_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new RuntimeException('Falha na conexao com o banco: ' . $conn->connect_error);
    }
    if (!@$conn->set_charset('utf8mb4')) {
        @$conn->set_charset('utf8');
    }
    return $conn;
}

/* -----------------------------------------------------------------------------
   2. INTROSPECCAO DE COLUNAS
   Evita erro caso alguma instalacao ainda nao possua as colunas do modulo de
   assinatura ou colunas opcionais.
----------------------------------------------------------------------------- */
function ofb_columns()
{
    static $cols = null;
    if ($cols !== null) {
        return $cols;
    }
    $cols = [];
    try {
        $conn = ofb_db();
        if ($res = @$conn->query("SHOW COLUMNS FROM `oficios`")) {
            while ($row = $res->fetch_assoc()) {
                $cols[strtolower($row['Field'])] = $row['Type'];
            }
            $res->free();
        }
    } catch (Throwable $e) {
        $cols = [];
    }
    return $cols;
}

function ofb_has_col($name)
{
    $cols = ofb_columns();
    return isset($cols[strtolower($name)]);
}

/**
 * Retorna somente as colunas que realmente existem, na ordem informada.
 */
function ofb_only_existing(array $names)
{
    $out = [];
    foreach ($names as $n) {
        if (ofb_has_col($n)) {
            $out[] = $n;
        }
    }
    return $out;
}

/* -----------------------------------------------------------------------------
   3. BUSCA INSENSIVEL A ACENTOS
   Testa uma unica vez se o servidor aceita CONVERT(... USING utf8mb4) COLLATE
   utf8mb4_general_ci. Se aceitar, todas as comparacoes LIKE ignoram acentos
   (Joao == João, orgao == órgão). Caso contrario, cai no LIKE simples.
----------------------------------------------------------------------------- */
function ofb_ai_supported()
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    try {
        $conn = ofb_db();
        $res = @$conn->query("SELECT CONVERT('a' USING utf8mb4) COLLATE utf8mb4_general_ci = 'A' AS t");
        if ($res) {
            $ok = true;
            $res->free();
        }
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Envolve uma expressao de coluna para comparacao sem acento/caixa.
 */
function ofb_ai($expr)
{
    if (ofb_ai_supported()) {
        return "CONVERT($expr USING utf8mb4) COLLATE utf8mb4_general_ci";
    }
    return $expr;
}

/**
 * Envolve o parametro (lado direito) de forma coerente com ofb_ai().
 */
function ofb_ai_param()
{
    if (ofb_ai_supported()) {
        return "CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci";
    }
    return "?";
}

/* -----------------------------------------------------------------------------
   4. UTILITARIOS DE TEXTO E DATA
----------------------------------------------------------------------------- */

/** Remove acentos em PHP (usado em normalizacoes locais). */
function ofb_sem_acento($s)
{
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N',
    ];
    return strtr($s, $map);
}

/** Normaliza para comparacao de chaves (minusculo e sem acento). */
function ofb_key($s)
{
    return strtolower(ofb_sem_acento(trim((string)$s)));
}

/** Escapa curingas do LIKE para tratar o termo como literal. */
function ofb_like($termo)
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string)$termo) . '%';
}

function ofb_like_inicio($termo)
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string)$termo) . '%';
}

/**
 * Converte varias formas de data para Y-m-d.
 * Aceita: 2025-03-14, 14/03/2025, 14-03-2025, 14/03/25, 14/03 (ano corrente),
 * e palavras-chave: hoje, ontem, anteontem, amanha.
 */
function ofb_parse_data($valor)
{
    $v = trim((string)$valor);
    if ($v === '') {
        return null;
    }

    $k = ofb_key($v);
    $hoje = new DateTime('today');

    switch ($k) {
        case 'hoje':      return $hoje->format('Y-m-d');
        case 'ontem':     return $hoje->modify('-1 day')->format('Y-m-d');
        case 'anteontem': return $hoje->modify('-2 day')->format('Y-m-d');
        case 'amanha':    return $hoje->modify('+1 day')->format('Y-m-d');
    }

    // Y-m-d
    if (preg_match('~^(\d{4})-(\d{1,2})-(\d{1,2})$~', $v, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    // d/m/Y ou d-m-Y
    if (preg_match('~^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$~', $v, $m)) {
        $ano = (int)$m[3];
        if ($ano < 100) {
            $ano += ($ano <= 69) ? 2000 : 1900;
        }
        return sprintf('%04d-%02d-%02d', $ano, $m[2], $m[1]);
    }
    // d/m (assume ano corrente)
    if (preg_match('~^(\d{1,2})[/\-.](\d{1,2})$~', $v, $m)) {
        return sprintf('%04d-%02d-%02d', (int)date('Y'), $m[2], $m[1]);
    }
    // Somente ano
    if (preg_match('~^(\d{4})$~', $v, $m)) {
        return sprintf('%04d-01-01', $m[1]);
    }
    return null;
}

/**
 * Resolve um periodo pre-definido para [inicio, fim] em Y-m-d.
 */
function ofb_periodo_preset($preset)
{
    $p = ofb_key($preset);
    $hoje = date('Y-m-d');

    switch ($p) {
        case 'hoje':
            return [$hoje, $hoje];
        case 'ontem':
            $d = date('Y-m-d', strtotime('-1 day'));
            return [$d, $d];
        case '7dias':
        case 'ultimos7':
            return [date('Y-m-d', strtotime('-6 days')), $hoje];
        case '15dias':
            return [date('Y-m-d', strtotime('-14 days')), $hoje];
        case '30dias':
        case 'ultimos30':
            return [date('Y-m-d', strtotime('-29 days')), $hoje];
        case '90dias':
            return [date('Y-m-d', strtotime('-89 days')), $hoje];
        case 'semana':
        case 'estasemana':
            return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
        case 'semanapassada':
            return [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))];
        case 'mes':
        case 'estemes':
            return [date('Y-m-01'), date('Y-m-t')];
        case 'mespassado':
            $ini = date('Y-m-01', strtotime('first day of last month'));
            return [$ini, date('Y-m-t', strtotime($ini))];
        case 'ano':
        case 'esteano':
            return [date('Y-01-01'), date('Y-12-31')];
        case 'anopassado':
            $a = (int)date('Y') - 1;
            return ["$a-01-01", "$a-12-31"];
        case 'trimestre':
            $mes = (int)date('n');
            $iniMes = (int)(floor(($mes - 1) / 3) * 3) + 1;
            $ini = sprintf('%04d-%02d-01', (int)date('Y'), $iniMes);
            return [$ini, date('Y-m-t', strtotime(sprintf('%04d-%02d-01', (int)date('Y'), $iniMes + 2)))];
    }
    return [null, null];
}

/* -----------------------------------------------------------------------------
   5. PARSER DA SINTAXE DE BUSCA INTELIGENTE
   -----------------------------------------------------------------------------
   Exemplos aceitos:
     prefeitura orcamento             -> termos livres (todos os campos)
     "certidao de inteiro teor"       -> frase exata
     assunto:penhora dest:banco       -> campos especificos
     numero:145/2025                  -> numero
     de:01/01/2025 ate:31/03/2025     -> intervalo de datas
     ano:2025  mes:2025-03            -> atalhos de periodo
     assinado:sim  travado:nao        -> estado
     anexo:sim                        -> possui anexo em disco
     -cancelado                       -> exclui resultados com "cancelado"
----------------------------------------------------------------------------- */

/** Mapa de aliases de campo -> chave canonica. */
function ofb_alias_campos()
{
    return [
        'numero' => 'numero', 'num' => 'numero', 'n' => 'numero', 'nº' => 'numero', 'no' => 'numero',
        'oficio' => 'numero', 'of' => 'numero',

        'assunto' => 'assunto', 'a' => 'assunto', 'asn' => 'assunto', 'tema' => 'assunto',

        'destinatario' => 'destinatario', 'dest' => 'destinatario', 'd' => 'destinatario',
        'para' => 'destinatario', 'destino' => 'destinatario',

        'assinante' => 'assinante', 'ass' => 'assinante', 'signatario' => 'assinante',

        'cargo' => 'cargo', 'funcao' => 'cargo',
        'cargoassinante' => 'cargo_assinante', 'cargo_assinante' => 'cargo_assinante',

        'tratamento' => 'tratamento', 'trat' => 'tratamento',

        'complemento' => 'dados_complementares', 'compl' => 'dados_complementares',
        'complementares' => 'dados_complementares', 'dados' => 'dados_complementares',
        'dc' => 'dados_complementares', 'dados_complementares' => 'dados_complementares',

        'corpo' => 'corpo', 'texto' => 'corpo', 'conteudo' => 'corpo', 'body' => 'corpo',

        'por' => 'assinado_por', 'assinadopor' => 'assinado_por', 'assinado_por' => 'assinado_por',

        'de' => '_de', 'desde' => '_de', 'apos' => '_de', 'from' => '_de', 'inicio' => '_de',
        'ate' => '_ate', 'antes' => '_ate', 'until' => '_ate', 'fim' => '_ate',
        'em' => '_data', 'data' => '_data', 'dia' => '_data',
        'ano' => '_ano', 'year' => '_ano',
        'mes' => '_mes', 'month' => '_mes',
        'periodo' => '_periodo',

        'assinado' => '_assinado', 'assinada' => '_assinado', 'sig' => '_assinado',
        'travado' => '_travado', 'bloqueado' => '_travado', 'status' => '_travado',
        'anexo' => '_anexo', 'anexos' => '_anexo', 'attach' => '_anexo',
    ];
}

/** Interpreta sim/nao/1/0/true/false. */
function ofb_bool($v)
{
    $k = ofb_key($v);
    if (in_array($k, ['sim', 's', '1', 'true', 'yes', 'y', 'ok'], true))  return 1;
    if (in_array($k, ['nao', 'n', '0', 'false', 'no'], true))             return 0;
    return null;
}

/**
 * Quebra a string de busca em tokens respeitando aspas.
 * Retorna array de tokens brutos.
 */
function ofb_tokenize($q)
{
    $tokens = [];
    $len = strlen($q);
    $buf = '';
    $emAspas = false;
    $i = 0;

    while ($i < $len) {
        $ch = $q[$i];
        if ($ch === '"') {
            $emAspas = !$emAspas;
            $buf .= $ch;
        } elseif (!$emAspas && ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r")) {
            if ($buf !== '') { $tokens[] = $buf; $buf = ''; }
        } else {
            $buf .= $ch;
        }
        $i++;
    }
    if ($buf !== '') { $tokens[] = $buf; }
    return $tokens;
}

/** Remove aspas externas de um valor. */
function ofb_unquote($v)
{
    $v = trim((string)$v);
    if (strlen($v) >= 2 && $v[0] === '"' && substr($v, -1) === '"') {
        return substr($v, 1, -1);
    }
    return $v;
}

/**
 * Analisa a query livre e devolve estrutura normalizada.
 *
 * @return array {
 *   livres:      [ ['termo'=>..., 'frase'=>bool] ],
 *   excluidos:   [ 'termo', ... ],
 *   campos:      [ 'assunto' => ['t1','t2'], ... ],
 *   data_ini, data_fim, data_exata, ano, mes,
 *   assinado, travado, anexo  (1|0|null)
 * }
 */
function ofb_parse_query($q)
{
    $out = [
        'livres'     => [],
        'excluidos'  => [],
        'campos'     => [],
        'data_ini'   => null,
        'data_fim'   => null,
        'data_exata' => null,
        'ano'        => null,
        'mes'        => null,
        'assinado'   => null,
        'travado'    => null,
        'anexo'      => null,
        'nao_reconhecidos' => [],
    ];

    $q = trim((string)$q);
    if ($q === '') {
        return $out;
    }

    $aliases = ofb_alias_campos();
    $tokens  = ofb_tokenize($q);

    foreach ($tokens as $tk) {
        if ($tk === '') continue;

        // Exclusao: -termo
        if ($tk[0] === '-' && strlen($tk) > 1) {
            $out['excluidos'][] = ofb_unquote(substr($tk, 1));
            continue;
        }

        // campo:valor
        if (strpos($tk, ':') !== false && $tk[0] !== '"') {
            list($campoRaw, $valorRaw) = explode(':', $tk, 2);
            $campoKey = ofb_key($campoRaw);
            $valor    = ofb_unquote($valorRaw);

            if (isset($aliases[$campoKey]) && $valor !== '') {
                $campo = $aliases[$campoKey];

                switch ($campo) {
                    case '_de':
                        $d = ofb_parse_data($valor);
                        if ($d) $out['data_ini'] = $d;
                        break;

                    case '_ate':
                        $d = ofb_parse_data($valor);
                        if ($d) $out['data_fim'] = $d;
                        break;

                    case '_data':
                        // aceita intervalo "de..ate" no mesmo token
                        if (strpos($valor, '..') !== false) {
                            list($a, $b) = explode('..', $valor, 2);
                            $da = ofb_parse_data($a);
                            $db = ofb_parse_data($b);
                            if ($da) $out['data_ini'] = $da;
                            if ($db) $out['data_fim'] = $db;
                        } else {
                            $d = ofb_parse_data($valor);
                            if ($d) $out['data_exata'] = $d;
                        }
                        break;

                    case '_ano':
                        if (preg_match('~^\d{4}$~', $valor)) {
                            $out['ano'] = $valor;
                        }
                        break;

                    case '_mes':
                        if (preg_match('~^(\d{4})-(\d{1,2})$~', $valor, $m)) {
                            $out['mes'] = sprintf('%04d-%02d', $m[1], $m[2]);
                        } elseif (preg_match('~^(\d{1,2})/(\d{4})$~', $valor, $m)) {
                            $out['mes'] = sprintf('%04d-%02d', $m[2], $m[1]);
                        } elseif (preg_match('~^(\d{1,2})$~', $valor)) {
                            $out['mes'] = sprintf('%04d-%02d', (int)date('Y'), (int)$valor);
                        }
                        break;

                    case '_periodo':
                        list($pi, $pf) = ofb_periodo_preset($valor);
                        if ($pi) $out['data_ini'] = $pi;
                        if ($pf) $out['data_fim'] = $pf;
                        break;

                    case '_assinado':
                        $out['assinado'] = ofb_bool($valor);
                        break;

                    case '_travado':
                        $out['travado'] = ofb_bool($valor);
                        break;

                    case '_anexo':
                        $out['anexo'] = ofb_bool($valor);
                        break;

                    default:
                        if (!isset($out['campos'][$campo])) {
                            $out['campos'][$campo] = [];
                        }
                        $out['campos'][$campo][] = $valor;
                        break;
                }
                continue;
            }
            // Campo desconhecido: cai para termo livre completo
            $out['nao_reconhecidos'][] = $campoRaw;
        }

        // Palavras-chave de periodo isoladas (ex.: "hoje", "este mes" nao suportado com espaco)
        $kk = ofb_key(ofb_unquote($tk));
        if (in_array($kk, ['hoje', 'ontem', 'anteontem'], true)) {
            $d = ofb_parse_data($kk);
            if ($d) {
                $out['data_ini'] = $d;
                $out['data_fim'] = $d;
                continue;
            }
        }

        // Termo livre / frase exata
        $ehFrase = (strlen($tk) >= 2 && $tk[0] === '"' && substr($tk, -1) === '"');
        $termo   = ofb_unquote($tk);
        if ($termo !== '') {
            $out['livres'][] = ['termo' => $termo, 'frase' => $ehFrase];
        }
    }

    return $out;
}

/* -----------------------------------------------------------------------------
   6. ANEXOS EM DISCO
   Os anexos ficam em anexos/{numero}/. Monta a lista de numeros que possuem
   ao menos um arquivo, para permitir o filtro "com/sem anexo".
----------------------------------------------------------------------------- */
function ofb_numeros_com_anexo()
{
    static $lista = null;
    if ($lista !== null) {
        return $lista;
    }
    $lista = [];
    $base = __DIR__ . '/anexos';
    if (!is_dir($base)) {
        return $lista;
    }

    /* IMPORTANTE: o numero do oficio contem barra (ex.: 145/2026). Como o
       upload usa "anexos/{numero}/", o diretorio resultante fica aninhado:
       anexos/145/2026/arquivo.pdf. Por isso a varredura desce dois niveis e
       remonta a chave com a barra. */
    $nivel1 = @scandir($base);
    if (!$nivel1) {
        return $lista;
    }

    foreach ($nivel1 as $d1) {
        if ($d1 === '.' || $d1 === '..') continue;
        $caminho1 = $base . '/' . $d1;
        if (!is_dir($caminho1)) continue;

        $itens = @scandir($caminho1);
        if (!$itens) continue;

        $temArquivoDireto = false;
        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') continue;
            $caminho2 = $caminho1 . '/' . $item;

            if (is_file($caminho2)) {
                $temArquivoDireto = true;
                continue;
            }

            // Subpasta: corresponde ao formato "numero/ano"
            if (is_dir($caminho2)) {
                $sub = @scandir($caminho2);
                if (!$sub) continue;
                foreach ($sub as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if (is_file($caminho2 . '/' . $f)) {
                        $lista[] = $d1 . '/' . $item;
                        break;
                    }
                }
            }
        }

        if ($temArquivoDireto) {
            $lista[] = $d1;
        }
    }

    return array_values(array_unique($lista));
}

/* -----------------------------------------------------------------------------
   7. NORMALIZACAO DOS PARAMETROS DE ENTRADA
   Combina a busca livre (q) com os campos do formulario avancado e mantem
   compatibilidade com os parametros antigos do modulo.
----------------------------------------------------------------------------- */
function ofb_normalizar_params(array $src)
{
    $p = [
        'q'            => trim((string)($src['q'] ?? '')),
        'modo'         => (ofb_key($src['modo'] ?? 'e') === 'ou') ? 'OU' : 'E',
        'numero'       => trim((string)($src['numero'] ?? '')),
        'assunto'      => trim((string)($src['assunto'] ?? '')),
        'destinatario' => trim((string)($src['destinatario'] ?? '')),
        'assinante'    => trim((string)($src['assinante'] ?? '')),
        'cargo'        => trim((string)($src['cargo'] ?? '')),
        'dados_complementares' => trim((string)($src['dados_complementares'] ?? '')),
        'corpo'        => trim((string)($src['corpo'] ?? '')),
        'data_ini'     => trim((string)($src['data_ini'] ?? '')),
        'data_fim'     => trim((string)($src['data_fim'] ?? '')),
        'periodo'      => trim((string)($src['periodo'] ?? '')),
        'assinado'     => (isset($src['assinado'])  && $src['assinado']  !== '') ? ofb_bool($src['assinado'])  : null,
        'travado'      => (isset($src['travado'])   && $src['travado']   !== '') ? ofb_bool($src['travado'])   : null,
        'anexo'        => (isset($src['anexo'])     && $src['anexo']     !== '') ? ofb_bool($src['anexo'])     : null,
        'buscar_corpo' => !empty($src['buscar_corpo']),
        'ordem'        => (string)($src['ordem'] ?? 'relevancia'),
        'dir'          => (ofb_key($src['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC',
        'pagina'       => max(1, (int)($src['pagina'] ?? 1)),
        'por_pagina'   => (int)($src['por_pagina'] ?? 25),
    ];

    // Compatibilidade: parametro antigo "data" (data exata)
    if (!empty($src['data'])) {
        $d = ofb_parse_data($src['data']);
        if ($d) {
            $p['data_ini'] = $d;
            $p['data_fim'] = $d;
        }
    }

    // Presets de periodo tem prioridade quando informados
    if ($p['periodo'] !== '' && ofb_key($p['periodo']) !== 'personalizado') {
        list($pi, $pf) = ofb_periodo_preset($p['periodo']);
        if ($pi) $p['data_ini'] = $pi;
        if ($pf) $p['data_fim'] = $pf;
    }

    // Normaliza datas digitadas em formato br
    foreach (['data_ini', 'data_fim'] as $campo) {
        if ($p[$campo] !== '') {
            $d = ofb_parse_data($p[$campo]);
            $p[$campo] = $d ?: '';
        }
    }

    // Limites de paginacao
    $permitidos = [10, 25, 50, 100, 200, 500];
    if (!in_array($p['por_pagina'], $permitidos, true)) {
        $p['por_pagina'] = 25;
    }

    $ordensValidas = ['relevancia', 'data', 'numero', 'assunto', 'destinatario', 'assinante', 'id'];
    if (!in_array($p['ordem'], $ordensValidas, true)) {
        $p['ordem'] = 'relevancia';
    }

    // Mescla o que veio da sintaxe da busca livre
    $parsed = ofb_parse_query($p['q']);
    $p['_parsed'] = $parsed;

    if ($parsed['data_ini'] && $p['data_ini'] === '') $p['data_ini'] = $parsed['data_ini'];
    if ($parsed['data_fim'] && $p['data_fim'] === '') $p['data_fim'] = $parsed['data_fim'];
    if ($parsed['data_exata']) {
        $p['data_ini'] = $parsed['data_exata'];
        $p['data_fim'] = $parsed['data_exata'];
    }
    if ($parsed['ano']) {
        $p['data_ini'] = $parsed['ano'] . '-01-01';
        $p['data_fim'] = $parsed['ano'] . '-12-31';
    }
    if ($parsed['mes']) {
        $p['data_ini'] = $parsed['mes'] . '-01';
        $p['data_fim'] = date('Y-m-t', strtotime($parsed['mes'] . '-01'));
    }
    if ($parsed['assinado'] !== null && $p['assinado'] === null) $p['assinado'] = $parsed['assinado'];
    if ($parsed['travado']  !== null && $p['travado']  === null) $p['travado']  = $parsed['travado'];
    if ($parsed['anexo']    !== null && $p['anexo']    === null) $p['anexo']    = $parsed['anexo'];

    return $p;
}

/**
 * Indica se ha algum filtro ativo (usado para decidir entre listagem padrao
 * dos mais recentes e resultado de pesquisa).
 */
function ofb_tem_filtro(array $p)
{
    $campos = ['q','numero','assunto','destinatario','assinante','cargo',
               'dados_complementares','corpo','data_ini','data_fim'];
    foreach ($campos as $c) {
        if (!empty($p[$c])) return true;
    }
    if ($p['assinado'] !== null || $p['travado'] !== null || $p['anexo'] !== null) {
        return true;
    }
    return false;
}

/* -----------------------------------------------------------------------------
   8. CONSTRUCAO DA CLAUSULA WHERE
   Retorna ['sql' => '...', 'tipos' => 'sss', 'valores' => [...]]
----------------------------------------------------------------------------- */
function ofb_campos_texto($incluirCorpo = false)
{
    $base = ['numero', 'assunto', 'destinatario', 'assinante', 'cargo',
             'cargo_assinante', 'tratamento', 'dados_complementares'];
    if ($incluirCorpo) {
        $base[] = 'corpo';
    }
    return ofb_only_existing($base);
}

function ofb_build_where(array $p)
{
    $where   = [];
    $tipos   = '';
    $valores = [];

    $parsed = $p['_parsed'];
    $ai     = ofb_ai_param();

    /* ---- 8.1 Termos livres (busca em varios campos) ---- */
    $campos = ofb_campos_texto($p['buscar_corpo']);
    if (!empty($parsed['livres']) && !empty($campos)) {
        $blocos = [];
        foreach ($parsed['livres'] as $item) {
            $termo = $item['termo'];
            $ors   = [];

            foreach ($campos as $c) {
                $ors[] = ofb_ai("`$c`") . " LIKE $ai";
                $tipos .= 's';
                $valores[] = ofb_like($termo);
            }

            // Numero informado sem formatacao: tambem tenta casar 123/2025
            if (preg_match('~^\d+$~', $termo) && ofb_has_col('numero')) {
                $ors[] = "`numero` LIKE ?";
                $tipos .= 's';
                $valores[] = ofb_like_inicio($termo . '/');
            }

            $blocos[] = '(' . implode(' OR ', $ors) . ')';
        }
        $juncao = ($p['modo'] === 'OU') ? ' OR ' : ' AND ';
        $where[] = '(' . implode($juncao, $blocos) . ')';
    }

    /* ---- 8.2 Exclusoes (-termo) ---- */
    if (!empty($parsed['excluidos']) && !empty($campos)) {
        foreach ($parsed['excluidos'] as $termo) {
            $ors = [];
            foreach ($campos as $c) {
                $ors[] = ofb_ai("COALESCE(`$c`,'')") . " LIKE $ai";
                $tipos .= 's';
                $valores[] = ofb_like($termo);
            }
            $where[] = 'NOT (' . implode(' OR ', $ors) . ')';
        }
    }

    /* ---- 8.3 Campos vindos da sintaxe campo:valor ---- */
    foreach ($parsed['campos'] as $coluna => $lista) {
        if (!ofb_has_col($coluna)) continue;
        foreach ($lista as $valor) {
            $where[] = ofb_ai("`$coluna`") . " LIKE $ai";
            $tipos .= 's';
            $valores[] = ofb_like($valor);
        }
    }

    /* ---- 8.4 Campos do formulario avancado ---- */
    $mapaForm = [
        'numero'               => 'numero',
        'assunto'              => 'assunto',
        'destinatario'         => 'destinatario',
        'assinante'            => 'assinante',
        'cargo'                => 'cargo',
        'dados_complementares' => 'dados_complementares',
        'corpo'                => 'corpo',
    ];
    foreach ($mapaForm as $param => $coluna) {
        if (empty($p[$param]) || !ofb_has_col($coluna)) continue;

        if ($param === 'numero') {
            // Numero aceita busca flexivel: 145, 145/2025, 0145
            $valor = trim($p['numero']);
            $ors = ["`numero` LIKE ?"];
            $tipos .= 's';
            $valores[] = ofb_like($valor);

            if (preg_match('~^0*(\d+)$~', $valor, $m)) {
                $ors[] = "`numero` LIKE ?";
                $tipos .= 's';
                $valores[] = ofb_like_inicio($m[1] . '/');
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
            continue;
        }

        $where[] = ofb_ai("`$coluna`") . " LIKE $ai";
        $tipos .= 's';
        $valores[] = ofb_like($p[$param]);
    }

    /* ---- 8.5 Intervalo de datas ---- */
    if (ofb_has_col('data')) {
        if (!empty($p['data_ini'])) {
            $where[] = "`data` >= ?";
            $tipos .= 's';
            $valores[] = $p['data_ini'];
        }
        if (!empty($p['data_fim'])) {
            $where[] = "`data` <= ?";
            $tipos .= 's';
            $valores[] = $p['data_fim'];
        }
    }

    /* ---- 8.6 Estado: assinado / travado ---- */
    if ($p['assinado'] !== null && ofb_has_col('assinado')) {
        $where[] = $p['assinado'] ? "COALESCE(`assinado`,0) = 1" : "COALESCE(`assinado`,0) = 0";
    }
    if ($p['travado'] !== null && ofb_has_col('status')) {
        $where[] = $p['travado'] ? "COALESCE(`status`,0) = 1" : "COALESCE(`status`,0) = 0";
    }

    /* ---- 8.7 Possui anexo (diretorio em disco) ---- */
    if ($p['anexo'] !== null && ofb_has_col('numero')) {
        $numeros = ofb_numeros_com_anexo();
        if (empty($numeros)) {
            // Sem nenhum anexo cadastrado
            $where[] = $p['anexo'] ? '1=0' : '1=1';
        } else {
            $placeholders = implode(',', array_fill(0, count($numeros), '?'));
            $where[] = ($p['anexo'] ? "`numero` IN ($placeholders)" : "`numero` NOT IN ($placeholders)");
            foreach ($numeros as $n) {
                $tipos .= 's';
                $valores[] = $n;
            }
        }
    }

    return [
        'sql'     => empty($where) ? '' : ('WHERE ' . implode(' AND ', $where)),
        'tipos'   => $tipos,
        'valores' => $valores,
    ];
}

/* -----------------------------------------------------------------------------
   9. EXPRESSAO DE RELEVANCIA
   Pontua cada registro conforme onde o termo apareceu. Numero exato vale mais,
   depois assunto, destinatario, e assim por diante.
----------------------------------------------------------------------------- */
function ofb_build_score(array $p, &$tipos, array &$valores)
{
    $parsed = $p['_parsed'];
    $termos = [];

    foreach ($parsed['livres'] as $item) {
        $termos[] = $item['termo'];
    }
    foreach (['numero','assunto','destinatario','assinante','cargo','dados_complementares'] as $c) {
        if (!empty($p[$c])) $termos[] = $p[$c];
    }

    if (empty($termos)) {
        return '0';
    }

    $pesos = [
        'numero'               => 60,
        'assunto'              => 25,
        'destinatario'         => 18,
        'assinante'            => 10,
        'cargo'                => 6,
        'tratamento'           => 4,
        'cargo_assinante'      => 4,
        'dados_complementares' => 8,
    ];
    if ($p['buscar_corpo']) {
        $pesos['corpo'] = 3;
    }

    $ai    = ofb_ai_param();
    $cases = [];

    foreach ($termos as $termo) {
        foreach ($pesos as $coluna => $peso) {
            if (!ofb_has_col($coluna)) continue;

            // Correspondencia em qualquer posicao
            $cases[] = "(CASE WHEN " . ofb_ai("COALESCE(`$coluna`,'')") . " LIKE $ai THEN $peso ELSE 0 END)";
            $tipos .= 's';
            $valores[] = ofb_like($termo);

            // Bonus para correspondencia no inicio do campo
            $bonus = (int)ceil($peso / 2);
            $cases[] = "(CASE WHEN " . ofb_ai("COALESCE(`$coluna`,'')") . " LIKE $ai THEN $bonus ELSE 0 END)";
            $tipos .= 's';
            $valores[] = ofb_like_inicio($termo);
        }

        // Bonus alto para numero identico
        if (ofb_has_col('numero')) {
            $cases[] = "(CASE WHEN `numero` = ? THEN 200 ELSE 0 END)";
            $tipos .= 's';
            $valores[] = $termo;
        }
    }

    return empty($cases) ? '0' : implode(' + ', $cases);
}

/* -----------------------------------------------------------------------------
   10. ORDENACAO
----------------------------------------------------------------------------- */
function ofb_build_order(array $p, $temScore)
{
    $dir = $p['dir'];

    switch ($p['ordem']) {
        case 'data':
            return ofb_has_col('data') ? "`data` $dir, `id` $dir" : "`id` $dir";

        case 'numero':
            // Ordena numericamente respeitando o formato 145/2025
            if (ofb_has_col('numero')) {
                return "CAST(SUBSTRING_INDEX(`numero`, '/', -1) AS UNSIGNED) $dir, "
                     . "CAST(SUBSTRING_INDEX(`numero`, '/', 1) AS UNSIGNED) $dir, `id` $dir";
            }
            return "`id` $dir";

        case 'assunto':
            return ofb_has_col('assunto') ? "`assunto` $dir, `id` DESC" : "`id` $dir";

        case 'destinatario':
            return ofb_has_col('destinatario') ? "`destinatario` $dir, `id` DESC" : "`id` $dir";

        case 'assinante':
            return ofb_has_col('assinante') ? "`assinante` $dir, `id` DESC" : "`id` $dir";

        case 'id':
            return "`id` $dir";

        case 'relevancia':
        default:
            if ($temScore) {
                $ordData = ofb_has_col('data') ? "`data` DESC, " : '';
                return "`_score` DESC, {$ordData}`id` DESC";
            }
            $ordData = ofb_has_col('data') ? "`data` $dir, " : '';
            return "{$ordData}`id` $dir";
    }
}

/* -----------------------------------------------------------------------------
   11. EXECUCAO DA BUSCA
----------------------------------------------------------------------------- */
function ofb_bind(mysqli_stmt $stmt, $tipos, array $valores)
{
    if ($tipos === '' || empty($valores)) {
        return;
    }
    $refs = [];
    $refs[] = &$tipos;
    foreach ($valores as $k => $v) {
        $refs[$k + 1] = &$valores[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

/**
 * Executa a busca e retorna resultado paginado.
 *
 * @return array {
 *   ok, linhas, total, pagina, paginas, por_pagina, tempo_ms, sql_erro
 * }
 */
function ofb_buscar(array $p, $limitePadrao = 20)
{
    $t0   = microtime(true);
    $conn = ofb_db();

    $temFiltro = ofb_tem_filtro($p);
    $where     = ofb_build_where($p);

    /* ---------- Contagem total ---------- */
    $sqlCount = "SELECT COUNT(*) AS total FROM `oficios` " . $where['sql'];
    $stmt = $conn->prepare($sqlCount);
    if (!$stmt) {
        return ['ok' => false, 'erro' => $conn->error, 'linhas' => [], 'total' => 0,
                'pagina' => 1, 'paginas' => 1, 'por_pagina' => $p['por_pagina'], 'tempo_ms' => 0];
    }
    ofb_bind($stmt, $where['tipos'], $where['valores']);
    $stmt->execute();
    $res   = $stmt->get_result();
    $total = 0;
    if ($res && ($row = $res->fetch_assoc())) {
        $total = (int)$row['total'];
    }
    $stmt->close();

    /* ---------- Selecao ---------- */
    $tiposSel   = '';
    $valoresSel = [];

    $score = ofb_build_score($p, $tiposSel, $valoresSel);
    $temScore = ($score !== '0');

    $selecao = "SELECT `oficios`.*";
    if ($temScore) {
        $selecao .= ", ($score) AS `_score`";
    } else {
        $selecao .= ", 0 AS `_score`";
    }

    // Parametros do SELECT vem antes dos do WHERE
    $tiposFinal   = $tiposSel . $where['tipos'];
    $valoresFinal = array_merge($valoresSel, $where['valores']);

    $order = ofb_build_order($p, $temScore);

    // Sem nenhum filtro: mostra apenas os mais recentes
    if (!$temFiltro) {
        $porPagina = $limitePadrao;
        $offset    = 0;
        $pagina    = 1;
        $paginas   = 1;
        $totalExib = min($total, $limitePadrao);
    } else {
        $porPagina = $p['por_pagina'];
        $paginas   = max(1, (int)ceil($total / $porPagina));
        $pagina    = min($p['pagina'], $paginas);
        $offset    = ($pagina - 1) * $porPagina;
        $totalExib = $total;
    }

    $sql = "$selecao FROM `oficios` {$where['sql']} ORDER BY $order LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'erro' => $conn->error, 'linhas' => [], 'total' => 0,
                'pagina' => 1, 'paginas' => 1, 'por_pagina' => $porPagina, 'tempo_ms' => 0];
    }

    $tiposFinal   .= 'ii';
    $valoresFinal[] = (int)$porPagina;
    $valoresFinal[] = (int)$offset;

    ofb_bind($stmt, $tiposFinal, $valoresFinal);
    $stmt->execute();
    $res = $stmt->get_result();

    $linhas = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $linhas[] = $row;
        }
    }
    $stmt->close();

    return [
        'ok'         => true,
        'linhas'     => $linhas,
        'total'      => $total,
        'total_exib' => $totalExib,
        'tem_filtro' => $temFiltro,
        'pagina'     => $pagina,
        'paginas'    => $paginas,
        'por_pagina' => $porPagina,
        'tempo_ms'   => round((microtime(true) - $t0) * 1000, 1),
    ];
}

/* -----------------------------------------------------------------------------
   12. FACETAS (contagens por valor, respeitando os filtros ativos exceto o
   proprio campo da faceta). Alimenta os "filtros inteligentes" clicaveis.
----------------------------------------------------------------------------- */
function ofb_facetas(array $p, $limitePorFaceta = 8)
{
    $conn  = ofb_db();
    $where = ofb_build_where($p);
    $out   = [];

    $campos = ofb_only_existing(['destinatario', 'assinante', 'cargo', 'assunto']);

    foreach ($campos as $c) {
        $sql = "SELECT `$c` AS valor, COUNT(*) AS total
                FROM `oficios` {$where['sql']}
                " . ($where['sql'] ? "AND" : "WHERE") . " `$c` IS NOT NULL AND TRIM(`$c`) <> ''
                GROUP BY `$c`
                ORDER BY total DESC, valor ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;

        $tipos   = $where['tipos'] . 'i';
        $valores = $where['valores'];
        $valores[] = (int)$limitePorFaceta;

        ofb_bind($stmt, $tipos, $valores);
        $stmt->execute();
        $res = $stmt->get_result();

        $itens = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $itens[] = ['valor' => $row['valor'], 'total' => (int)$row['total']];
            }
        }
        $stmt->close();
        if (!empty($itens)) {
            $out[$c] = $itens;
        }
    }

    /* Faceta de anos */
    if (ofb_has_col('data')) {
        $sql = "SELECT LEFT(`data`, 4) AS valor, COUNT(*) AS total
                FROM `oficios` {$where['sql']}
                " . ($where['sql'] ? "AND" : "WHERE") . " `data` IS NOT NULL AND `data` <> ''
                GROUP BY LEFT(`data`, 4)
                ORDER BY valor DESC
                LIMIT 12";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            ofb_bind($stmt, $where['tipos'], $where['valores']);
            $stmt->execute();
            $res = $stmt->get_result();
            $itens = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if ($row['valor'] === null || $row['valor'] === '') continue;
                    $itens[] = ['valor' => $row['valor'], 'total' => (int)$row['total']];
                }
            }
            $stmt->close();
            if (!empty($itens)) {
                $out['ano'] = $itens;
            }
        }
    }

    /* Contadores de estado */
    $estado = [];
    if (ofb_has_col('assinado')) {
        $sql = "SELECT COALESCE(`assinado`,0) AS v, COUNT(*) AS total FROM `oficios` {$where['sql']} GROUP BY COALESCE(`assinado`,0)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            ofb_bind($stmt, $where['tipos'], $where['valores']);
            $stmt->execute();
            $res = $stmt->get_result();
            $assinados = 0; $naoAssinados = 0;
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if ((int)$row['v'] === 1) $assinados = (int)$row['total'];
                    else $naoAssinados = (int)$row['total'];
                }
            }
            $stmt->close();
            $estado['assinados']     = $assinados;
            $estado['nao_assinados'] = $naoAssinados;
        }
    }
    if (!empty($estado)) {
        $out['_estado'] = $estado;
    }

    return $out;
}

/* -----------------------------------------------------------------------------
   13. SUGESTOES DE AUTOCOMPLETE
----------------------------------------------------------------------------- */
function ofb_sugestoes($termo, $limite = 8)
{
    $termo = trim((string)$termo);

    // Nao depende da extensao mbstring (pode estar desabilitada no PHP)
    $tamanho = function_exists('mb_strlen') ? mb_strlen($termo, 'UTF-8') : strlen($termo);
    if ($tamanho < 2) {
        return [];
    }

    $conn = ofb_db();
    $out  = [];
    $ai   = ofb_ai_param();

    $mapa = [
        'numero'       => ['rotulo' => 'Numero',       'icone' => 'fa-hashtag',  'prefixo' => 'numero:'],
        'assunto'      => ['rotulo' => 'Assunto',      'icone' => 'fa-book',     'prefixo' => 'assunto:'],
        'destinatario' => ['rotulo' => 'Destinatario', 'icone' => 'fa-user',     'prefixo' => 'dest:'],
        'assinante'    => ['rotulo' => 'Assinante',    'icone' => 'fa-pencil',   'prefixo' => 'assinante:'],
    ];

    foreach ($mapa as $coluna => $meta) {
        if (!ofb_has_col($coluna)) continue;

        $sql = "SELECT `$coluna` AS valor, COUNT(*) AS total
                FROM `oficios`
                WHERE " . ofb_ai("`$coluna`") . " LIKE $ai
                  AND `$coluna` IS NOT NULL AND TRIM(`$coluna`) <> ''
                GROUP BY `$coluna`
                ORDER BY total DESC, valor ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;

        $like = ofb_like($termo);
        $lim  = (int)$limite;
        $stmt->bind_param('si', $like, $lim);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'campo'   => $coluna,
                    'rotulo'  => $meta['rotulo'],
                    'icone'   => $meta['icone'],
                    'valor'   => $row['valor'],
                    'total'   => (int)$row['total'],
                    'consulta'=> $meta['prefixo'] . (strpos($row['valor'], ' ') !== false ? '"' . $row['valor'] . '"' : $row['valor']),
                ];
            }
        }
        $stmt->close();
    }

    return array_slice($out, 0, 20);
}

/* -----------------------------------------------------------------------------
   14. RESUMO DOS FILTROS ATIVOS (para os chips removiveis da interface)
----------------------------------------------------------------------------- */
function ofb_chips_ativos(array $p)
{
    $chips = [];

    $rotulos = [
        'q'                    => 'Busca',
        'numero'               => 'Numero',
        'assunto'              => 'Assunto',
        'destinatario'         => 'Destinatario',
        'assinante'            => 'Assinante',
        'cargo'                => 'Cargo',
        'dados_complementares' => 'Complementos',
        'corpo'                => 'Corpo',
    ];

    foreach ($rotulos as $campo => $rotulo) {
        if (!empty($p[$campo])) {
            $chips[] = ['campo' => $campo, 'rotulo' => $rotulo, 'valor' => $p[$campo]];
        }
    }

    if (!empty($p['data_ini']) || !empty($p['data_fim'])) {
        $ini = $p['data_ini'] ? date('d/m/Y', strtotime($p['data_ini'])) : '...';
        $fim = $p['data_fim'] ? date('d/m/Y', strtotime($p['data_fim'])) : '...';
        $texto = ($p['data_ini'] && $p['data_fim'] && $p['data_ini'] === $p['data_fim'])
               ? $ini
               : "$ini ate $fim";
        $chips[] = ['campo' => 'periodo_datas', 'rotulo' => 'Periodo', 'valor' => $texto];
    }

    if ($p['assinado'] !== null) {
        $chips[] = ['campo' => 'assinado', 'rotulo' => 'Assinado', 'valor' => $p['assinado'] ? 'Sim' : 'Nao'];
    }
    if ($p['travado'] !== null) {
        $chips[] = ['campo' => 'travado', 'rotulo' => 'Travado', 'valor' => $p['travado'] ? 'Sim' : 'Nao'];
    }
    if ($p['anexo'] !== null) {
        $chips[] = ['campo' => 'anexo', 'rotulo' => 'Anexo', 'valor' => $p['anexo'] ? 'Com anexo' : 'Sem anexo'];
    }

    return $chips;
}
