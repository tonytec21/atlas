<?php
/**
 * Atlas · Arquivamento Digital
 * Repositório do acervo — leitura/gravação dos metadados em JSON.
 *
 * O formato em disco continua compatível com a versão anterior do módulo
 * (um arquivo meta-dados/<id>.json por arquivamento). O que mudou:
 *
 *  - "anexos" agora aceita objetos {ref, nome, tamanho, mime, hash, ...}
 *    além das strings antigas. A leitura normaliza os dois formatos.
 *  - existe um índice em cache (meta-dados/.indice.json) que evita reler
 *    todos os JSON a cada consulta.
 */

function arq_dir_meta()    { return dirname(__DIR__) . '/meta-dados'; }
function arq_dir_arquivos(){ return dirname(__DIR__) . '/arquivos'; }
function arq_dir_lixeira() { return dirname(__DIR__) . '/lixeira'; }

/* ================================================================== *
 * Normalização
 * ================================================================== */

/** Converte um item de "anexos" (string legada ou objeto) em objeto completo. */
function arq_normalizar_anexo($item, $origemPadrao = 'acervo')
{
    if (is_string($item)) {
        $ref = str_replace('\\', '/', $item);
        $anexo = [
            'ref'      => $ref,
            'nome'     => basename($ref),
            'tamanho'  => null,
            'mime'     => null,
            'hash'     => null,
            'origem'   => $origemPadrao,
        ];
    } elseif (is_array($item)) {
        $ref = isset($item['ref']) ? str_replace('\\', '/', $item['ref']) : '';
        $anexo = [
            'ref'         => $ref,
            'nome'        => isset($item['nome']) && $item['nome'] !== '' ? $item['nome'] : basename($ref),
            'tamanho'     => isset($item['tamanho']) ? (int) $item['tamanho'] : null,
            'mime'        => isset($item['mime']) ? $item['mime'] : null,
            'hash'        => isset($item['hash']) ? $item['hash'] : null,
            'origem'      => isset($item['origem']) ? $item['origem'] : $origemPadrao,
            'enviado_por' => isset($item['enviado_por']) ? $item['enviado_por'] : null,
            'enviado_em'  => isset($item['enviado_em']) ? $item['enviado_em'] : null,
        ];
    } else {
        return null;
    }
    if ($anexo['ref'] === '') { return null; }

    $anexo['ext'] = mb_strtolower(pathinfo($anexo['nome'], PATHINFO_EXTENSION));
    return $anexo;
}

/** Lê os metadados de um arquivamento e devolve a estrutura normalizada. */
function arq_normalizar_ato(array $d)
{
    $ato = [
        'id'                => isset($d['id']) ? (string) $d['id'] : '',
        'atribuicao'        => isset($d['atribuicao']) ? (string) $d['atribuicao'] : '',
        'categoria'         => isset($d['categoria']) ? (string) $d['categoria'] : '',
        'data_ato'          => isset($d['data_ato']) ? (string) $d['data_ato'] : '',
        'livro'             => isset($d['livro']) ? (string) $d['livro'] : '',
        'folha'             => isset($d['folha']) ? (string) $d['folha'] : '',
        'termo'             => isset($d['termo']) ? (string) $d['termo'] : '',
        'protocolo'         => isset($d['protocolo']) ? (string) $d['protocolo'] : '',
        'matricula'         => isset($d['matricula']) ? (string) $d['matricula'] : '',
        'descricao'         => isset($d['descricao']) ? (string) $d['descricao'] : '',
        'partes_envolvidas' => [],
        'anexos'            => [],
        'cadastrado_por'    => isset($d['cadastrado_por']) ? (string) $d['cadastrado_por'] : '',
        'data_cadastro'     => isset($d['data_cadastro']) ? (string) $d['data_cadastro'] : '',
        'modificacoes'      => isset($d['modificacoes']) && is_array($d['modificacoes']) ? $d['modificacoes'] : [],
        'excluido_por'      => isset($d['excluido_por']) ? (string) $d['excluido_por'] : '',
        'data_exclusao'     => isset($d['data_exclusao']) ? (string) $d['data_exclusao'] : '',
    ];

    if (isset($d['partes_envolvidas']) && is_array($d['partes_envolvidas'])) {
        foreach ($d['partes_envolvidas'] as $p) {
            if (!is_array($p)) { continue; }
            $ato['partes_envolvidas'][] = [
                'cpf'   => isset($p['cpf']) ? (string) $p['cpf'] : '',
                'nome'  => isset($p['nome']) ? (string) $p['nome'] : '',
                'papel' => isset($p['papel']) ? (string) $p['papel'] : '',
            ];
        }
    }

    if (isset($d['anexos']) && is_array($d['anexos'])) {
        foreach ($d['anexos'] as $a) {
            $n = arq_normalizar_anexo($a, 'acervo');
            if ($n) { $ato['anexos'][] = $n; }
        }
    }
    // Anexos herdados do módulo de tarefas ficam na mesma lista, marcados.
    if (isset($d['anexos_tarefa']) && is_array($d['anexos_tarefa'])) {
        foreach ($d['anexos_tarefa'] as $a) {
            $n = arq_normalizar_anexo($a, 'tarefa');
            if ($n) { $n['origem'] = 'tarefa'; $ato['anexos'][] = $n; }
        }
    }

    // Completa tamanho/mime dos anexos que ainda não têm (registros antigos).
    foreach ($ato['anexos'] as $i => $a) {
        if ($a['tamanho'] === null) {
            $abs = arq_resolver_anexo($a['ref']);
            $ato['anexos'][$i]['tamanho']    = $abs !== false ? filesize($abs) : 0;
            $ato['anexos'][$i]['disponivel'] = $abs !== false;
        } else {
            $ato['anexos'][$i]['disponivel'] = arq_resolver_anexo($a['ref']) !== false;
        }
    }

    return $ato;
}

/* ================================================================== *
 * Leitura
 * ================================================================== */

function arq_caminho_meta($id, $lixeira = false)
{
    $id = arq_id_valido($id);
    if ($id === '') { return false; }
    $dir = $lixeira ? arq_dir_lixeira() : arq_dir_meta();
    return $dir . DIRECTORY_SEPARATOR . $id . '.json';
}

/** Carrega um arquivamento. Retorna null se não existir. */
function arq_obter($id, $lixeira = false)
{
    $arquivo = arq_caminho_meta($id, $lixeira);
    if ($arquivo === false || !is_file($arquivo)) { return null; }
    $d = json_decode(@file_get_contents($arquivo), true);
    if (!is_array($d)) { return null; }
    $d['id'] = arq_id_valido($id);
    return arq_normalizar_ato($d);
}

/** Grava um arquivamento (escrita atômica). */
function arq_salvar_ato($id, array $dados, $lixeira = false)
{
    $arquivo = arq_caminho_meta($id, $lixeira);
    if ($arquivo === false) { return false; }
    $dir = dirname($arquivo);
    if (!is_dir($dir)) { @mkdir($dir, 0770, true); }

    $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp  = $arquivo . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) { return false; }
    if (!@rename($tmp, $arquivo)) { @unlink($tmp); return false; }

    arq_invalidar_indice();
    return true;
}

/* ================================================================== *
 * Índice em cache
 * ================================================================== */

function arq_arquivo_indice() { return arq_dir_meta() . '/.indice.json'; }

function arq_invalidar_indice()
{
    $f = arq_arquivo_indice();
    if (is_file($f)) { @unlink($f); }
}

/**
 * Índice compacto de todos os arquivamentos ativos.
 * Reconstruído automaticamente quando algum JSON for mais novo que o índice.
 */
function arq_indice()
{
    static $memoria = null;
    if ($memoria !== null) { return $memoria; }

    $arquivos = glob(arq_dir_meta() . '/*.json') ?: [];
    $indiceArq = arq_arquivo_indice();
    $valido = false;

    if (is_file($indiceArq)) {
        $mt = filemtime($indiceArq);
        $valido = true;
        foreach ($arquivos as $a) {
            if (filemtime($a) > $mt) { $valido = false; break; }
        }
        if ($valido) {
            $cache = json_decode(@file_get_contents($indiceArq), true);
            if (is_array($cache) && isset($cache['itens']) && count($cache['itens']) === count($arquivos)) {
                $memoria = $cache['itens'];
                return $memoria;
            }
        }
    }

    $itens = [];
    foreach ($arquivos as $arquivo) {
        $id = basename($arquivo, '.json');
        if (arq_id_valido($id) === '') { continue; }
        $d = json_decode(@file_get_contents($arquivo), true);
        if (!is_array($d)) { continue; }
        $d['id'] = $id;
        $ato = arq_normalizar_ato($d);

        $nomes = [];
        $cpfs  = [];
        foreach ($ato['partes_envolvidas'] as $p) {
            if ($p['nome'] !== '') { $nomes[] = $p['nome']; }
            if ($p['cpf'] !== '')  { $cpfs[]  = preg_replace('/\D/', '', $p['cpf']); }
        }

        $bytes = 0;
        foreach ($ato['anexos'] as $a) { $bytes += (int) $a['tamanho']; }

        $itens[] = [
            'id'            => $id,
            'atribuicao'    => $ato['atribuicao'],
            'categoria'     => $ato['categoria'],
            'data_ato'      => $ato['data_ato'],
            'livro'         => $ato['livro'],
            'folha'         => $ato['folha'],
            'termo'         => $ato['termo'],
            'protocolo'     => $ato['protocolo'],
            'matricula'     => $ato['matricula'],
            'descricao'     => $ato['descricao'],
            'partes'        => $nomes,
            'cpfs'          => $cpfs,
            'anexos_qtd'    => count($ato['anexos']),
            'anexos_bytes'  => $bytes,
            'cadastrado_por'=> $ato['cadastrado_por'],
            'data_cadastro' => $ato['data_cadastro'],
            'modificado_em' => !empty($ato['modificacoes'])
                ? (string) $ato['modificacoes'][count($ato['modificacoes']) - 1]['data_hora']
                : $ato['data_cadastro'],
            'busca'         => arq_texto_busca($ato, $nomes),
        ];
    }

    @file_put_contents(
        $indiceArq,
        json_encode(['gerado' => date('c'), 'itens' => $itens], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    $memoria = $itens;
    return $memoria;
}

/** Texto concatenado e normalizado usado na busca livre. */
function arq_texto_busca(array $ato, array $nomes)
{
    $partes = array_merge(
        [$ato['atribuicao'], $ato['categoria'], $ato['livro'], $ato['folha'], $ato['termo'],
         $ato['protocolo'], $ato['matricula'], $ato['descricao'], $ato['cadastrado_por']],
        $nomes
    );
    foreach ($ato['anexos'] as $a) { $partes[] = $a['nome']; }
    return arq_normalizar_texto(implode(' ', $partes));
}

/** minúsculas, sem acento — para comparação de busca. */
function arq_normalizar_texto($texto)
{
    $texto = (string) $texto;
    $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ];
    $texto = mb_strtolower($texto, 'UTF-8');
    return strtr($texto, $mapa);
}

/* ================================================================== *
 * Consulta
 * ================================================================== */

/**
 * Filtra o acervo.
 * $f: q, atribuicao, categoria, cpf, nome, livro, folha, termo, protocolo,
 *     matricula, descricao, data, de, ate, com_anexo, ordenar, direcao
 */
function arq_filtrar(array $f, $itens = null)
{
    if ($itens === null) { $itens = arq_indice(); }

    $q          = arq_normalizar_texto(isset($f['q']) ? trim($f['q']) : '');
    $atribuicao = isset($f['atribuicao']) ? trim($f['atribuicao']) : '';
    $categoria  = isset($f['categoria']) ? trim($f['categoria']) : '';
    $cpf        = preg_replace('/\D/', '', isset($f['cpf']) ? $f['cpf'] : '');
    $nome       = arq_normalizar_texto(isset($f['nome']) ? trim($f['nome']) : '');
    $descricao  = arq_normalizar_texto(isset($f['descricao']) ? trim($f['descricao']) : '');
    $livro      = trim(isset($f['livro']) ? $f['livro'] : '');
    $folha      = trim(isset($f['folha']) ? $f['folha'] : '');
    $termo      = trim(isset($f['termo']) ? $f['termo'] : '');
    $protocolo  = trim(isset($f['protocolo']) ? $f['protocolo'] : '');
    $matricula  = trim(isset($f['matricula']) ? $f['matricula'] : '');
    $data       = trim(isset($f['data']) ? $f['data'] : '');
    $de         = trim(isset($f['de']) ? $f['de'] : '');
    $ate        = trim(isset($f['ate']) ? $f['ate'] : '');
    $comAnexo   = isset($f['com_anexo']) ? $f['com_anexo'] : '';

    if ($data !== '') { $de = $data; $ate = $data; }

    $res = [];
    foreach ($itens as $it) {
        if ($q !== '' && strpos($it['busca'], $q) === false) { continue; }
        if ($atribuicao !== '' && $it['atribuicao'] !== $atribuicao) { continue; }
        if ($categoria !== '' && $it['categoria'] !== $categoria) { continue; }
        if ($livro !== '' && stripos($it['livro'], $livro) === false) { continue; }
        if ($folha !== '' && stripos($it['folha'], $folha) === false) { continue; }
        if ($termo !== '' && stripos($it['termo'], $termo) === false) { continue; }
        if ($protocolo !== '' && stripos($it['protocolo'], $protocolo) === false) { continue; }
        if ($matricula !== '' && stripos($it['matricula'], $matricula) === false) { continue; }
        if ($descricao !== '' && strpos(arq_normalizar_texto($it['descricao']), $descricao) === false) { continue; }

        if ($nome !== '') {
            $achou = false;
            foreach ($it['partes'] as $n) {
                if (strpos(arq_normalizar_texto($n), $nome) !== false) { $achou = true; break; }
            }
            if (!$achou) { continue; }
        }
        if ($cpf !== '') {
            $achou = false;
            foreach ($it['cpfs'] as $c) {
                if ($c !== '' && strpos($c, $cpf) !== false) { $achou = true; break; }
            }
            if (!$achou) { continue; }
        }
        if ($de !== '' && $it['data_ato'] !== '' && $it['data_ato'] < $de) { continue; }
        if ($ate !== '' && $it['data_ato'] !== '' && $it['data_ato'] > $ate) { continue; }
        if (($de !== '' || $ate !== '') && $it['data_ato'] === '') { continue; }

        if ($comAnexo === 'sim' && $it['anexos_qtd'] < 1) { continue; }
        if ($comAnexo === 'nao' && $it['anexos_qtd'] > 0) { continue; }

        $res[] = $it;
    }

    $ordenar = isset($f['ordenar']) ? $f['ordenar'] : 'data_ato';
    $desc    = (isset($f['direcao']) ? $f['direcao'] : 'desc') !== 'asc';
    $campos  = ['data_ato', 'data_cadastro', 'categoria', 'atribuicao', 'anexos_qtd', 'id', 'modificado_em'];
    if (!in_array($ordenar, $campos, true)) { $ordenar = 'data_ato'; }

    usort($res, function ($a, $b) use ($ordenar, $desc) {
        $x = isset($a[$ordenar]) ? $a[$ordenar] : '';
        $y = isset($b[$ordenar]) ? $b[$ordenar] : '';
        if (is_numeric($x) && is_numeric($y)) { $c = ($x == $y) ? 0 : (($x < $y) ? -1 : 1); }
        else { $c = strcmp((string) $x, (string) $y); }
        if ($c === 0) { $c = strcmp((string) $a['id'], (string) $b['id']); }
        return $desc ? -$c : $c;
    });

    return $res;
}

/** Itens da lixeira, normalizados como no índice. */
function arq_listar_lixeira()
{
    $arquivos = glob(arq_dir_lixeira() . '/*.json') ?: [];
    $itens = [];
    foreach ($arquivos as $arquivo) {
        $id = basename($arquivo, '.json');
        if (arq_id_valido($id) === '') { continue; }
        $d = json_decode(@file_get_contents($arquivo), true);
        if (!is_array($d)) { continue; }
        $d['id'] = $id;
        $ato = arq_normalizar_ato($d);
        $nomes = [];
        foreach ($ato['partes_envolvidas'] as $p) { if ($p['nome'] !== '') { $nomes[] = $p['nome']; } }

        $dias = null;
        if ($ato['data_exclusao'] !== '') {
            $ts = strtotime($ato['data_exclusao']);
            if ($ts) { $dias = max(0, ARQ_LIXEIRA_DIAS - (int) floor((time() - $ts) / 86400)); }
        }

        $itens[] = [
            'id'             => $id,
            'atribuicao'     => $ato['atribuicao'],
            'categoria'      => $ato['categoria'],
            'data_ato'       => $ato['data_ato'],
            'livro'          => $ato['livro'],
            'folha'          => $ato['folha'],
            'termo'          => $ato['termo'],
            'protocolo'      => $ato['protocolo'],
            'matricula'      => $ato['matricula'],
            'partes'         => $nomes,
            'anexos_qtd'     => count($ato['anexos']),
            'excluido_por'   => $ato['excluido_por'],
            'data_exclusao'  => $ato['data_exclusao'],
            'dias_restantes' => $dias,
        ];
    }
    usort($itens, function ($a, $b) { return strcmp($b['data_exclusao'], $a['data_exclusao']); });
    return $itens;
}

/* ================================================================== *
 * Estatísticas
 * ================================================================== */

function arq_estatisticas()
{
    $itens = arq_indice();
    $hoje  = date('Y-m-d');
    $mes   = date('Y-m');
    $ano   = date('Y');

    $st = [
        'total'          => count($itens),
        'hoje'           => 0,
        'mes'            => 0,
        'ano'            => 0,
        'anexos'         => 0,
        'bytes'          => 0,
        'sem_anexo'      => 0,
        'por_atribuicao' => [],
        'por_categoria'  => [],
        'por_mes'        => [],
        'lixeira'        => count(glob(arq_dir_lixeira() . '/*.json') ?: []),
    ];

    // Últimos 12 meses no eixo, mesmo sem movimento.
    // Ancorado no dia 1 — "-1 month" a partir de 31/03 cairia em 03/03 e
    // deixaria fevereiro de fora do eixo.
    $base = new DateTime('first day of this month');
    for ($i = 11; $i >= 0; $i--) {
        $d = clone $base;
        $d->modify("-$i month");
        $st['por_mes'][$d->format('Y-m')] = 0;
    }

    foreach ($itens as $it) {
        $st['anexos'] += (int) $it['anexos_qtd'];
        $st['bytes']  += (int) $it['anexos_bytes'];
        if ((int) $it['anexos_qtd'] === 0) { $st['sem_anexo']++; }

        $d = $it['data_ato'];
        if ($d !== '') {
            if ($d === $hoje) { $st['hoje']++; }
            if (strpos($d, $mes) === 0) { $st['mes']++; }
            if (strpos($d, $ano) === 0) { $st['ano']++; }
            $ym = substr($d, 0, 7);
            if (isset($st['por_mes'][$ym])) { $st['por_mes'][$ym]++; }
        }

        $a = $it['atribuicao'] !== '' ? $it['atribuicao'] : 'Não informada';
        $c = $it['categoria']  !== '' ? $it['categoria']  : 'Não informada';
        $st['por_atribuicao'][$a] = (isset($st['por_atribuicao'][$a]) ? $st['por_atribuicao'][$a] : 0) + 1;
        $st['por_categoria'][$c]  = (isset($st['por_categoria'][$c]) ? $st['por_categoria'][$c] : 0) + 1;
    }

    arsort($st['por_atribuicao']);
    arsort($st['por_categoria']);
    $st['por_categoria'] = array_slice($st['por_categoria'], 0, 8, true);
    $st['bytes_legivel'] = arq_formatar_bytes($st['bytes']);

    return $st;
}

/* ================================================================== *
 * Categorias
 * ================================================================== */

function arq_arquivo_categorias() { return dirname(__DIR__) . '/categorias/categorias.json'; }

function arq_categorias()
{
    $f = arq_arquivo_categorias();
    if (!is_file($f)) { return []; }
    $c = json_decode(@file_get_contents($f), true);
    if (!is_array($c)) { return []; }
    $c = array_values(array_filter(array_map('strval', $c), function ($v) { return trim($v) !== ''; }));
    usort($c, function ($a, $b) { return strcoll(arq_normalizar_texto($a), arq_normalizar_texto($b)); });
    return $c;
}

function arq_gravar_categorias(array $cats)
{
    $cats = array_values(array_unique(array_filter(array_map(function ($c) {
        return trim(preg_replace('/\s+/u', ' ', (string) $c));
    }, $cats), function ($v) { return $v !== ''; })));

    $f   = arq_arquivo_categorias();
    $dir = dirname($f);
    if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
    $tmp = $f . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        return false;
    }
    return @rename($tmp, $f);
}

/** Quantas vezes cada categoria é usada no acervo. */
function arq_uso_categorias()
{
    $uso = [];
    foreach (arq_indice() as $it) {
        $c = $it['categoria'];
        if ($c === '') { continue; }
        $uso[$c] = (isset($uso[$c]) ? $uso[$c] : 0) + 1;
    }
    return $uso;
}

/** Atribuições fixas do módulo. */
function arq_atribuicoes()
{
    return [
        'Registro Civil',
        'Registro de Imóveis',
        'Registro de Títulos e Documentos',
        'Registro Civil das Pessoas Jurídicas',
        'Notas',
        'Protesto',
        'Contratos Marítimos',
        'Administrativo',
    ];
}
