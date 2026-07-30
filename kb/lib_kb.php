<?php
/**
 * atlas/kb/lib_kb.php
 * Nucleo da base de conhecimento: chunking, embeddings, busca hibrida.
 * Compativel com PHP 7.4+.
 */

require_once __DIR__ . '/config_kb.php';

/**
 * Faz endpoints AJAX responderem JSON mesmo em erro fatal do PHP.
 *
 * Sem isso, um Error (constante indefinida, funcao inexistente, memoria
 * estourada) escapa do try/catch -- porque Error NAO estende Exception --
 * e o navegador recebe HTML, resultando no inutil "falha de comunicacao".
 */
function kbBlindarJson()
{
    register_shutdown_function(function () {
        $e = error_get_last();
        if (!$e || !in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            return;
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array(
            'success' => false,
            'ok'      => false,
            'message' => 'Erro interno: ' . $e['message']
                       . ' (' . basename($e['file']) . ':' . $e['line'] . ')',
            'mensagem'=> 'Erro interno: ' . $e['message']
                       . ' (' . basename($e['file']) . ':' . $e['line'] . ')',
        ), JSON_UNESCAPED_UNICODE);
    });
}

// ===========================================================================
// 1. CHUNKING POR ESTRUTURA NORMATIVA
// ===========================================================================

/**
 * Quebra o texto de um provimento em trechos, usando os marcadores
 * estruturais (Art., Paragrafo unico, Capitulo, Secao, Anexo).
 *
 * O conteudo_anexo chega como linha unica -- o cadastro converte \n em espaco --
 * entao nao da para chunkar por paragrafo. A estrutura juridica e o unico
 * delimitador confiavel que sobrevive.
 */
function kbChunk($texto, $minChars = KB_CHUNK_MIN, $maxChars = KB_CHUNK_MAX)
{
    $texto = preg_replace('/\s+/u', ' ', trim((string) $texto));
    if ($texto === '') {
        return array();
    }

    // Lookahead: quebra ANTES do marcador, preservando-o no inicio do trecho.
    $re = '/(?=\bArt\.?\s*\d+[\x{00BA}\x{00B0}\x{00AA}o]?(?:[-\x{2010}-\x{2015}]?[A-Z])?\s*[\.\-\x{2013}]?)'
        . '|(?=\bPar\x{00E1}grafo\s+\x{00FA}nico)'
        . '|(?=\bANEXO\s+[IVXLC]+\b)'
        . '|(?=\bCAP\x{00CD}TULO\s+[IVXLC]+\b)'
        . '|(?=\bSe\x{00E7}\x{00E3}o\s+[IVXLC]+\b)/u';

    $partes = preg_split($re, $texto, -1, PREG_SPLIT_NO_EMPTY);

    // Reagrupa fragmentos curtos no bloco anterior (ementa solta, inciso orfao,
    // cabecalho de capitulo). Um "Art." nunca e absorvido: ele sempre abre trecho.
    $blocos = array();
    foreach ($partes as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if ($blocos && mb_strlen($p) < $minChars && !preg_match('/^Art\.?\s*\d/u', $p)) {
            $blocos[count($blocos) - 1] .= ' ' . $p;
        } else {
            $blocos[] = $p;
        }
    }

    // Artigos muito longos (com dezenas de incisos) sao subdivididos para nao
    // estourar o limite de 2048 tokens por entrada da API.
    $final = array();
    foreach ($blocos as $b) {
        if (mb_strlen($b) <= $maxChars) {
            $final[] = $b;
            continue;
        }
        foreach (kbSubdividir($b, $maxChars) as $pedaco) {
            $final[] = $pedaco;
        }
    }

    // Extrai a referencia citavel de cada trecho.
    $saida = array();
    foreach ($final as $i => $c) {
        $saida[] = array(
            'ordem'      => $i,
            'referencia' => kbReferencia($c),
            'conteudo'   => $c,
            'hash'       => md5($c),
        );
    }
    return $saida;
}

/** Subdivide um bloco longo em incisos, sem cortar palavra ao meio. */
function kbSubdividir($bloco, $maxChars)
{
    $cabecalho = mb_substr($bloco, 0, 80);
    $pedacos   = array();

    // Tenta quebrar em incisos romanos ("I -", "XIV -") ou paragrafos ("§ 2o").
    $partes = preg_split('/(?=\b[IVXLC]{1,6}\s*[-\x{2013}]\s)|(?=\x{00A7}\s*\d+)/u',
                         $bloco, -1, PREG_SPLIT_NO_EMPTY);

    $atual = '';
    foreach ($partes as $p) {
        if (mb_strlen($atual) + mb_strlen($p) > $maxChars && $atual !== '') {
            $pedacos[] = trim($atual);
            // Repete o cabecalho para o trecho nao perder o contexto do artigo.
            $atual = $cabecalho . ' [...] ' . $p;
        } else {
            $atual .= ' ' . $p;
        }
    }
    if (trim($atual) !== '') {
        $pedacos[] = trim($atual);
    }

    // Ultimo recurso: corte duro por caractere.
    if (count($pedacos) === 1 && mb_strlen($pedacos[0]) > $maxChars) {
        $pedacos = array();
        $total = mb_strlen($bloco);
        for ($i = 0; $i < $total; $i += $maxChars) {
            $pedacos[] = ($i > 0 ? $cabecalho . ' [...] ' : '') . mb_substr($bloco, $i, $maxChars);
        }
    }
    return $pedacos;
}

/** Identifica a referencia ("Art. 20-A", "CAPITULO III") no inicio do trecho. */
function kbReferencia($trecho)
{
    if (preg_match('/^(Art\.?\s*\d+[\x{00BA}\x{00B0}\x{00AA}o]?(?:[-\x{2010}-\x{2015}]?[A-Z])?)/u', $trecho, $m)) {
        return preg_replace('/\s+/', ' ', trim($m[1]));
    }
    if (preg_match('/^(CAP\x{00CD}TULO\s+[IVXLC]+|Se\x{00E7}\x{00E3}o\s+[IVXLC]+|ANEXO\s+[IVXLC]+|Par\x{00E1}grafo\s+\x{00FA}nico)/u', $trecho, $m)) {
        return preg_replace('/\s+/', ' ', trim($m[1]));
    }
    return null;
}

// ===========================================================================
// 2. EMBEDDINGS (Gemini)
// ===========================================================================

/**
 * Gera embeddings para ate 250 textos por chamada.
 *
 * ATENCAO: taskType e assimetrico. Documentos usam RETRIEVAL_DOCUMENT e a
 * pergunta usa RETRIEVAL_QUERY. Trocar os dois degrada a busca em silencio.
 *
 * @return array lista de vetores float ja normalizados
 */
function kbEmbed(array $textos, $taskType = 'RETRIEVAL_DOCUMENT')
{
    if (empty($textos)) {
        return array();
    }
    if (count($textos) > 250) {
        throw new InvalidArgumentException('Maximo de 250 textos por lote.');
    }

    $requests = array();
    foreach ($textos as $t) {
        $requests[] = array(
            'model'               => 'models/' . kbModelo('embedding'),
            'content'             => array('parts' => array(array('text' => $t))),
            'taskType'            => $taskType,
            'outputDimensionality' => kbEmbedDim(),
        );
    }

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('embedding') . ':batchEmbedContents?key=' . kbApiKey();
    $resp = kbHttpPost($url, array('requests' => $requests));

    if (!isset($resp['embeddings'])) {
        throw new RuntimeException('Resposta inesperada da API de embeddings: '
            . substr(json_encode($resp), 0, 400));
    }

    $vetores = array();
    foreach ($resp['embeddings'] as $e) {
        // Vetores de 3072 ja vem normalizados; abaixo disso, NAO vem.
        // Sem esta normalizacao o produto escalar deixa de ser cosseno.
        $vetores[] = kbNormalizar($e['values']);
    }
    return $vetores;
}

/**
 * Testa uma chave sem grava-la: faz um embedding minimo e verifica a resposta.
 * @return array('ok' => bool, 'mensagem' => string)
 */
function kbTestarChave($chave)
{
    $chave = trim($chave);
    if ($chave === '') {
        return array('ok' => false, 'mensagem' => 'Informe a chave.');
    }

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('embedding') . ':embedContent?key=' . urlencode($chave);
    $body = array(
        'model'                => 'models/' . kbModelo('embedding'),
        'content'              => array('parts' => array(array('text' => 'teste de conexao'))),
        'taskType'             => 'RETRIEVAL_QUERY',
        'outputDimensionality' => kbEmbedDim(),
    );

    try {
        $r = kbHttpPost($url, $body, 1); // sem retry: teste deve falhar rapido
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'HTTP 400') !== false || strpos($msg, 'HTTP 403') !== false) {
            $msg = 'Chave recusada pelo Google. Confira se foi copiada inteira e se a '
                 . 'Generative Language API esta habilitada no projeto.';
        } elseif (strpos($msg, 'HTTP 429') !== false) {
            $msg = 'Chave valida, mas a cota do momento esta esgotada. Tente em alguns minutos.';
        } elseif (strpos($msg, 'HTTP 0') !== false) {
            $msg = 'Sem resposta do Google. Verifique a saida de internet ou o proxy do servidor.';
        }
        return array('ok' => false, 'mensagem' => $msg);
    }

    $dims = isset($r['embedding']['values']) ? count($r['embedding']['values']) : 0;
    if ($dims !== kbEmbedDim()) {
        return array('ok' => false, 'mensagem' => 'Resposta inesperada da API (dimensao ' . $dims . ').');
    }
    return array('ok' => true, 'mensagem' => 'Conexao confirmada. Vetores de ' . $dims . ' dimensoes.');
}

/** Normalizacao L2. Depois disso, produto escalar == similaridade de cosseno. */
function kbNormalizar(array $v)
{
    $soma = 0.0;
    foreach ($v as $x) {
        $soma += $x * $x;
    }
    $norma = sqrt($soma);
    if ($norma < 1e-12) {
        return $v;
    }
    foreach ($v as $i => $x) {
        $v[$i] = $x / $norma;
    }
    return $v;
}

// ===========================================================================
// 3. QUANTIZACAO int8 (armazenamento)
// ===========================================================================

/**
 * Empacota o vetor em int8 com escala no cabecalho.
 * 768 dims -> 4 + 768 = 772 bytes (contra 3072 bytes em float32).
 * Perda de recall medida em corpora tipicos: ~1%.
 */
function kbQuantizar(array $vetor)
{
    $max = 0.0;
    foreach ($vetor as $x) {
        $a = abs($x);
        if ($a > $max) {
            $max = $a;
        }
    }
    $escala = ($max > 0 ? $max / 127.0 : 1.0);

    $bytes = pack('f', $escala);
    foreach ($vetor as $x) {
        $i = (int) round($x / $escala);
        if ($i > 127)  { $i = 127; }
        if ($i < -127) { $i = -127; }
        $bytes .= pack('c', $i);
    }
    return $bytes;
}

/** Produto escalar entre vetor float (pergunta) e BLOB int8 (documento). */
function kbDot(array $consulta, $blob)
{
    $escala = unpack('f', substr($blob, 0, 4));
    $escala = $escala[1];
    $ints   = unpack('c*', substr($blob, 4));

    $soma = 0.0;
    $i = 1;
    foreach ($consulta as $q) {
        if (!isset($ints[$i])) {
            break;
        }
        $soma += $q * $ints[$i];
        $i++;
    }
    return $soma * $escala;
}

// ===========================================================================
// 4. BUSCA HIBRIDA
// ===========================================================================

/**
 * Duas camadas:
 *   1) FULLTEXT traz candidatos (rapido, usa indice, acerta numero de artigo)
 *   2) Similaridade vetorial reordena (entende sinonimo e parafrase)
 * Fusao por RRF -- Reciprocal Rank Fusion.
 *
 * Varrer os 25-30 mil chunks com cosseno em PHP levaria segundos numa VM
 * modesta. O prefiltro derruba isso para dezenas de milissegundos.
 *
 * @param array $filtros origem, tipo, ano_min
 */
function kbBuscar(PDO $conn, $pergunta, $k = KB_TOP_K, array $filtros = array())
{
    $termos = kbTermosBusca($pergunta);
    if ($termos === '') {
        return array();
    }

    $where  = array("MATCH(c.conteudo) AGAINST (:q IN NATURAL LANGUAGE MODE)");
    $params = array(':q' => $termos);

    $where[] = "p.status = 'Ativo'";

    // Trecho revogado por norma posterior nao entra na resposta. Citar
    // dispositivo revogado como se valesse e o pior erro possivel aqui.
    if (empty($filtros['incluir_revogados'])) {
        $where[] = "c.situacao <> 'revogado'";
    }
    if (!empty($filtros['origem'])) {
        $where[] = "p.origem = :origem";
        $params[':origem'] = $filtros['origem'];
    }
    if (!empty($filtros['tipo'])) {
        $where[] = "p.tipo = :tipo";
        $params[':tipo'] = $filtros['tipo'];
    }
    if (!empty($filtros['ano_min'])) {
        $where[] = "YEAR(p.data_provimento) >= :ano_min";
        $params[':ano_min'] = (int) $filtros['ano_min'];
    }

    $sql = "SELECT c.id, c.referencia, c.conteudo, c.embedding,
                   p.id AS provimento_id, p.numero_provimento, p.origem, p.tipo,
                   p.data_provimento, p.descricao, p.caminho_anexo, c.situacao,
                   YEAR(p.data_provimento) AS ano,
                   MATCH(c.conteudo) AGAINST (:q2 IN NATURAL LANGUAGE MODE) AS score_ft
              FROM kb_chunks c
              JOIN provimentos p ON p.id = c.provimento_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY score_ft DESC
             LIMIT " . (int) KB_CANDIDATOS;

    $params[':q2'] = $termos;
    $st = $conn->prepare($sql);
    $st->execute($params);
    $candidatos = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($candidatos)) {
        return array();
    }

    // Rerank vetorial sobre os candidatos.
    $vetorPergunta = null;
    try {
        $v = kbEmbed(array($pergunta), 'RETRIEVAL_QUERY');
        $vetorPergunta = $v[0];
    } catch (Exception $e) {
        // Sem a API, degrada para FULLTEXT puro em vez de quebrar a tela.
        error_log('[kb] embedding da pergunta falhou: ' . $e->getMessage());
    }

    foreach ($candidatos as $i => $row) {
        $candidatos[$i]['score_vec'] = ($vetorPergunta && $row['embedding'])
            ? kbDot($vetorPergunta, $row['embedding'])
            : 0.0;
        unset($candidatos[$i]['embedding']); // nao carrega BLOB para frente
    }

    // Ranking por cada sinal.
    $porFt = $candidatos;
    usort($porFt, function ($a, $b) {
        return ($b['score_ft'] < $a['score_ft']) ? -1 : (($b['score_ft'] > $a['score_ft']) ? 1 : 0);
    });
    $porVec = $candidatos;
    usort($porVec, function ($a, $b) {
        return ($b['score_vec'] < $a['score_vec']) ? -1 : (($b['score_vec'] > $a['score_vec']) ? 1 : 0);
    });

    $rank = array();
    foreach ($porFt as $pos => $r)  { $rank[$r['id']]['ft']  = $pos + 1; }
    foreach ($porVec as $pos => $r) { $rank[$r['id']]['vec'] = $pos + 1; }

    $anoAtual = (int) date('Y');
    foreach ($candidatos as $i => $r) {
        $id  = $r['id'];
        $rrf = 1 / (KB_RRF_K + $rank[$id]['ft']) + 1 / (KB_RRF_K + $rank[$id]['vec']);

        // Desempate por vigencia: entre trechos parecidos, norma mais nova pesa
        // mais. Num acervo normativo, texto revogado e resposta errada.
        $idade  = max(0, $anoAtual - (int) $r['ano']);
        $recencia = 1 / (1 + $idade / 12.0);

        $candidatos[$i]['score'] = $rrf * (1 + KB_PESO_RECENCIA * $recencia);
    }

    usort($candidatos, function ($a, $b) {
        return ($b['score'] < $a['score']) ? -1 : (($b['score'] > $a['score']) ? 1 : 0);
    });

    return array_slice($candidatos, 0, $k);
}

/**
 * Limpa a pergunta para o FULLTEXT: remove pontuacao de interrogacao e
 * palavras que aparecem em praticamente todo documento do acervo (senao o
 * MATCH pontua tudo igual).
 */
function kbTermosBusca($pergunta)
{
    $ruido = array('qual', 'quais', 'quando', 'como', 'onde', 'porque', 'por que',
        'sobre', 'que', 'fala', 'falam', 'diz', 'dizem', 'existe', 'preciso',
        'provimento', 'provimentos', 'resolucao', 'resolucoes', 'resolução', 'resoluções',
        'artigo', 'norma', 'para', 'pelo', 'pela', 'com', 'dos', 'das', 'uma', 'uns');

    $t = mb_strtolower($pergunta, 'UTF-8');
    $t = preg_replace('/[^\p{L}\p{N}\s\-\x{00BA}\x{00B0}]/u', ' ', $t);
    $palavras = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);

    $uteis = array();
    foreach ($palavras as $p) {
        if (mb_strlen($p) >= 3 && !in_array($p, $ruido, true)) {
            $uteis[] = $p;
        }
    }
    // Se sobrou pouco, devolve a pergunta inteira em vez de vazio.
    return $uteis ? implode(' ', $uteis) : trim($t);
}

// ===========================================================================
// 4b. RELACOES ENTRE NORMAS (revogacao / alteracao)
// ===========================================================================

/**
 * Frases que indicam que a norma mexe em outra. Serve de prefiltro barato:
 * so quem casa aqui vai para a extracao com IA.
 */
function kbTemAlteracao($texto)
{
    return (bool) preg_match(
        '/\b(revoga(m|-se|do|dos)?|fica(m)?\s+revogad|passa(m)?\s+a\s+vigorar|'
        . 'nova\s+reda\x{00E7}\x{00E3}o|fica(m)?\s+alterad|fica(m)?\s+acrescid|'
        . 'acrescente-se|altera\s+o\s+Provimento|d\x{00E1}-se\s+ao\s+art)/iu',
        $texto);
}

/**
 * Extrai as relacoes normativas de um provimento usando a IA.
 *
 * Grava sempre com status 'sugerida'. Marcar norma como revogada por
 * extracao automatica, sem revisao humana, seria irresponsavel num sistema
 * que orienta ato de cartorio.
 *
 * @return int quantidade de relacoes sugeridas
 */
function kbExtrairRelacoes(PDO $conn, $provimentoId, $texto)
{
    $texto = preg_replace('/\s+/u', ' ', trim($texto));
    if (!kbTemAlteracao($texto)) {
        return 0;
    }

    // Manda so os trechos com indicio, para nao gastar contexto a toa.
    $trechos = array();
    if (preg_match_all('/.{0,300}(?:revoga|passa a vigorar|nova reda\x{00E7}\x{00E3}o|'
        . 'fica alterad|fica acrescid|acrescente-se).{0,400}/iu', $texto, $m)) {
        $trechos = array_slice($m[0], 0, 12);
    }
    if (!$trechos) {
        return 0;
    }

    $sistema = "Voce extrai relacoes entre normas de cartorio brasileiras.\n"
        . "Responda SOMENTE com um array JSON, sem markdown, sem explicacao.\n"
        . "Cada item: {\"tipo\":\"revoga_total|revoga_parcial|altera\","
        . "\"numero\":\"243\",\"ano\":\"2026\",\"origem\":\"CNJ|CGJ/MA\","
        . "\"dispositivos\":\"Art. 5; Art. 12\",\"trecho\":\"citacao curta\"}\n"
        . "Regras:\n"
        . "- Só inclua relacoes explicitas no texto. Nao deduza.\n"
        . "- 'dispositivos' vazio quando a revogacao for da norma inteira.\n"
        . "- Se a norma alterada nao for identificavel, ignore o item.\n"
        . "- Sem nenhuma relacao, responda: []";

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('chat') . ':generateContent?key=' . kbApiKey();
    $resp = kbHttpPost($url, array(
        'systemInstruction' => array('parts' => array(array('text' => $sistema))),
        'contents' => array(array('role' => 'user', 'parts' => array(array(
            'text' => "TRECHOS:\n\n" . implode("\n---\n", $trechos))))),
        'generationConfig' => array('temperature' => 0.0, 'maxOutputTokens' => 2000),
    ));

    $bruto = '';
    if (isset($resp['candidates'][0]['content']['parts'])) {
        foreach ($resp['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) { $bruto .= $p['text']; }
        }
    }
    $bruto = trim(preg_replace('/^```(?:json)?|```$/m', '', $bruto));
    $itens = json_decode($bruto, true);
    if (!is_array($itens)) {
        return 0;
    }

    $ins = $conn->prepare(
        "INSERT INTO kb_relacoes
            (origem_id, destino_id, destino_texto, tipo, dispositivos, trecho, status, criado_em)
         VALUES (:o, :d, :dt, :t, :disp, :tr, 'sugerida', NOW())"
    );
    $busca = $conn->prepare(
        "SELECT id FROM provimentos
          WHERE numero_provimento = :n AND YEAR(data_provimento) = :a LIMIT 1"
    );

    $n = 0;
    foreach ($itens as $it) {
        if (empty($it['tipo']) || empty($it['numero'])) {
            continue;
        }
        if (!in_array($it['tipo'], array('revoga_total', 'revoga_parcial', 'altera'), true)) {
            continue;
        }

        // Tenta casar com um documento do acervo.
        $destinoId = null;
        if (!empty($it['ano'])) {
            $busca->execute(array(':n' => $it['numero'], ':a' => (int) $it['ano']));
            $achado = $busca->fetchColumn();
            $destinoId = $achado ? (int) $achado : null;
        }

        $rotulo = trim($it['numero'] . (empty($it['ano']) ? '' : '/' . $it['ano'])
                . (empty($it['origem']) ? '' : ' ' . $it['origem']));

        $ins->execute(array(
            ':o'    => $provimentoId,
            ':d'    => $destinoId,
            ':dt'   => $rotulo,
            ':t'    => $it['tipo'],
            ':disp' => isset($it['dispositivos']) ? mb_substr($it['dispositivos'], 0, 250) : null,
            ':tr'   => isset($it['trecho']) ? mb_substr($it['trecho'], 0, 1000) : null,
        ));
        $n++;
    }
    return $n;
}

/**
 * Recalcula kb_chunks.situacao a partir das relacoes CONFIRMADAS.
 * Roda inteiro: e barato e evita estado inconsistente por aplicacao parcial.
 */
function kbAplicarRelacoes(PDO $conn)
{
    $conn->exec("UPDATE kb_chunks SET situacao = 'vigente'");

    $rel = $conn->query(
        "SELECT r.*, p.data_provimento AS data_origem
           FROM kb_relacoes r
           JOIN provimentos p ON p.id = r.origem_id
          WHERE r.status = 'confirmada' AND r.destino_id IS NOT NULL
          ORDER BY p.data_provimento"
    )->fetchAll(PDO::FETCH_ASSOC);

    $todos = $conn->prepare("UPDATE kb_chunks SET situacao = :s WHERE provimento_id = :p");
    $um    = $conn->prepare(
        "UPDATE kb_chunks SET situacao = :s WHERE provimento_id = :p AND referencia = :r"
    );

    $afetados = 0;
    foreach ($rel as $r) {
        if ($r['tipo'] === 'revoga_total') {
            $todos->execute(array(':s' => 'revogado', ':p' => $r['destino_id']));
            $afetados += $todos->rowCount();
            continue;
        }

        $sit = ($r['tipo'] === 'revoga_parcial') ? 'revogado' : 'alterado';
        foreach (kbNormalizarDispositivos($r['dispositivos']) as $ref) {
            $um->execute(array(':s' => $sit, ':p' => $r['destino_id'], ':r' => $ref));
            $afetados += $um->rowCount();
        }
    }
    return $afetados;
}

/**
 * Converte a lista de dispositivos citada pela norma alteradora nas mesmas
 * referencias que kbReferencia() grava nos trechos.
 *
 * Precisa aguentar como advogado escreve de verdade:
 *   "Art. 5; Art. 12"      "art. 20-A"      "Art 1o e Art. 5o"
 *   "os arts. 12 e 20-A"   "arts. 3, 5 e 7"
 * O plural e a enumeracao (onde so o primeiro numero vem precedido de "art")
 * sao os casos que mais aparecem.
 */
function kbNormalizarDispositivos($texto)
{
    if (!$texto) {
        return array();
    }

    // Remove paragrafos e incisos: "Art. 5, § 2, inciso III" nao pode virar
    // os artigos 5 e 2.
    $t = preg_replace('/\x{00A7}\s*\d+\s*[\x{00BA}\x{00B0}o]?/u', ' ', $texto);
    $t = preg_replace('/\b(inciso|al\x{00ED}nea|par\x{00E1}grafo)\b[^,;]*/iu', ' ', $t);

    // Só considera o que vem depois de uma mencao a artigo.
    if (!preg_match('/\barts?\b\.?/iu', $t)) {
        return array();
    }
    $t = preg_replace('/^.*?\barts?\b\.?/iu', '', $t);

    $saida = array();
    if (preg_match_all('/(\d{1,3})\s*[\x{00BA}\x{00B0}\x{00AA}o]?\s*(?:[-\x{2010}-\x{2015}]\s*([A-Za-z])\b)?/u',
                       $t, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $num = (int) $x[1];
            if ($num < 1 || $num > 999) {
                continue;
            }
            if (!empty($x[2])) {
                $saida[] = 'Art. ' . $num . '-' . strtoupper($x[2]);
                continue;
            }
            // O chunker grava o ordinal quando ele aparece no texto original
            // ("Art. 1o") e sem ele quando nao aparece ("Art. 12"). Gera as
            // duas formas: o casamento e por igualdade exata.
            $saida[] = 'Art. ' . $num;
            $saida[] = 'Art. ' . $num . "\xc2\xba";
        }
    }
    return array_values(array_unique($saida));
}

// ===========================================================================
// 5. GERACAO DA RESPOSTA
// ===========================================================================

/**
 * Condensa a mensagem do usuario numa consulta autonoma e decide se e preciso
 * buscar no acervo.
 *
 * E a peca central do chat. "E no caso de menor?" nao tem termo buscavel
 * sozinha -- precisa virar "prazo para reconhecimento de paternidade de menor".
 * E "monte um checklist com isso" nao precisa de busca nenhuma: os trechos ja
 * estao na conversa, buscar de novo so traria ruido.
 *
 * @param array $historico [['papel'=>'user'|'assistant','conteudo'=>...], ...]
 * @return array ['buscar'=>bool, 'consulta'=>string]
 */
function kbCondensar(array $historico, $mensagem)
{
    // Primeira mensagem da conversa: nao ha o que condensar.
    if (empty($historico)) {
        return array('buscar' => true, 'consulta' => $mensagem);
    }

    $linhas = array();
    foreach (array_slice($historico, -6) as $h) {
        $quem = ($h['papel'] === 'user') ? 'USUARIO' : 'ARIA';
        $linhas[] = $quem . ': ' . mb_substr($h['conteudo'], 0, 700);
    }

    $sistema = "Voce prepara consultas para um sistema de busca em normas de cartorio.\n"
        . "Responda SOMENTE com JSON, sem markdown: "
        . "{\"buscar\": true|false, \"consulta\": \"...\"}\n\n"
        . "buscar = false quando a mensagem apenas reformata, resume, organiza ou "
        . "explica algo que ja esta na conversa (ex.: 'monte um checklist disso', "
        . "'resuma em topicos', 'e o que isso significa na pratica?').\n"
        . "buscar = true quando a mensagem pede informacao normativa nova.\n\n"
        . "Quando buscar = true, 'consulta' deve ser uma pergunta autonoma, "
        . "compreensivel sem o historico, usando a terminologia registral brasileira. "
        . "Resolva pronomes e referencias implicitas a partir do historico.";

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('chat') . ':generateContent?key=' . kbApiKey();

    try {
        $resp = kbHttpPost($url, array(
            'systemInstruction' => array('parts' => array(array('text' => $sistema))),
            'contents' => array(array('role' => 'user', 'parts' => array(array(
                'text' => "HISTORICO:\n" . implode("\n", $linhas)
                        . "\n\nNOVA MENSAGEM: " . $mensagem)))),
            'generationConfig' => array('temperature' => 0.0, 'maxOutputTokens' => 300),
        ), 2);
    } catch (Throwable $e) {
        // Falhou? Busca com a mensagem crua: pior consulta, mas nao trava o chat.
        error_log('[kb/condensar] ' . $e->getMessage());
        return array('buscar' => true, 'consulta' => $mensagem);
    }

    $bruto = '';
    if (isset($resp['candidates'][0]['content']['parts'])) {
        foreach ($resp['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) { $bruto .= $p['text']; }
        }
    }
    $bruto = trim(preg_replace('/^```(?:json)?|```$/m', '', $bruto));
    $j = json_decode($bruto, true);

    if (!is_array($j) || !isset($j['buscar'])) {
        return array('buscar' => true, 'consulta' => $mensagem);
    }
    $consulta = isset($j['consulta']) ? trim($j['consulta']) : '';
    return array(
        'buscar'   => (bool) $j['buscar'],
        'consulta' => ($consulta !== '' ? $consulta : $mensagem),
    );
}

/**
 * Gera a resposta do chat: historico + trechos recuperados.
 * Diferente de kbGerarResposta(), permite formatacao rica (listas, tabelas,
 * checklists) porque o usuario pode pedir material operacional.
 */
function kbResponderChat(array $historico, $mensagem, array $trechos)
{
    $sistema = "Voce e a Aria, assistente de pesquisa normativa de um cartorio "
        . "extrajudicial brasileiro. Seu publico sao escreventes, tabeliaes e "
        . "registradores.\n\n"
        . "FUNDAMENTACAO\n"
        . "1. Baseie-se nos trechos fornecidos. Se a resposta nao estiver neles, diga "
        . "que nao encontrou no acervo -- nunca preencha com conhecimento proprio.\n"
        . "2. Cite a fonte como [n], usando o numero do trecho. Uma citacao por "
        . "afirmacao normativa.\n"
        . "3. Nunca invente numero de artigo, paragrafo ou inciso. Use apenas os que "
        . "aparecem literalmente nos trechos.\n"
        . "4. Trecho marcado como alterado por norma posterior: avise expressamente "
        . "que a redacao pode nao ser a vigente.\n"
        . "5. Trechos em conflito: aponte a divergencia e destaque a norma mais recente.\n"
        . "6. O acervo tem leis federais e atos administrativos. Quando os dois "
        . "tratarem do tema, cite a lei como fundamento e o provimento como "
        . "regulamentacao -- provimento nao pode contrariar lei.\n\n"
        . "FORMATO\n"
        . "- Markdown. Use ## para secoes, ** para destaque, tabelas quando comparar.\n"
        . "- Checklist: use '- [ ] item'. Cada item deve ser uma acao verificavel, "
        . "com a fundamentacao entre parenteses e a citacao [n].\n"
        . "- Roteiro ou procedimento: lista numerada, na ordem de execucao.\n"
        . "- Nao repita o enunciado da pergunta. Va direto ao conteudo.\n"
        . "- Portugues do Brasil, tom profissional e direto.\n\n"
        . "LIMITES\n"
        . "- Voce apoia a pesquisa; a responsabilidade pelo ato e do profissional.\n"
        . "- Duvida entre duas interpretacoes: apresente as duas, nao escolha por ele.";

    $contents = array();
    foreach (array_slice($historico, -8) as $h) {
        $contents[] = array(
            'role'  => ($h['papel'] === 'user') ? 'user' : 'model',
            'parts' => array(array('text' => $h['conteudo'])),
        );
    }

    $atual = '';
    if (!empty($trechos)) {
        $atual = "TRECHOS DO ACERVO:\n\n" . kbMontarContexto($trechos) . "\n\n";
    } elseif (empty($historico)) {
        $atual = "Nenhum trecho encontrado no acervo para esta pergunta.\n\n";
    }
    $atual .= "MENSAGEM: " . $mensagem;

    $contents[] = array('role' => 'user', 'parts' => array(array('text' => $atual)));

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('chat') . ':generateContent?key=' . kbApiKey();
    $resp = kbHttpPost($url, array(
        'systemInstruction' => array('parts' => array(array('text' => $sistema))),
        'contents'          => $contents,
        'generationConfig'  => array(
            'temperature'     => 0.2,
            'maxOutputTokens' => 4000, // checklist longo precisa de espaco
        ),
    ));

    if (isset($resp['candidates'][0]['content']['parts'])) {
        $txt = '';
        foreach ($resp['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) { $txt .= $p['text']; }
        }
        if (trim($txt) !== '') {
            return trim($txt);
        }
    }
    if (isset($resp['candidates'][0]['finishReason'])
        && $resp['candidates'][0]['finishReason'] === 'MAX_TOKENS') {
        throw new RuntimeException('A resposta ficou longa demais. Peca em partes menores.');
    }
    throw new RuntimeException('Nao foi possivel gerar a resposta.');
}

/** Titulo curto para a conversa, a partir da primeira mensagem. */
function kbTituloConversa($mensagem)
{
    $t = preg_replace('/\s+/u', ' ', trim($mensagem));
    return mb_substr($t, 0, 70) . (mb_strlen($t) > 70 ? '...' : '');
}

function kbMontarContexto(array $trechos)
{
    $blocos = array();
    foreach ($trechos as $i => $t) {
        $ano = date('Y', strtotime($t['data_provimento']));
        $ref = $t['referencia'] ? ', ' . $t['referencia'] : '';
        $aviso = (isset($t['situacao']) && $t['situacao'] === 'alterado')
            ? " [ATENCAO: dispositivo alterado por norma posterior]" : '';
        $blocos[] = "[" . ($i + 1) . "] " . $t['tipo'] . " n. " . $t['numero_provimento']
            . "/" . $ano . " - " . $t['origem'] . $ref . $aviso . "\n" . $t['conteudo'];
    }
    return implode("\n\n---\n\n", $blocos);
}

function kbGerarResposta($pergunta, array $trechos)
{
    if (empty($trechos)) {
        return 'Nao encontrei nenhum dispositivo no acervo que trate desse assunto. '
             . 'Tente reformular com os termos usados na norma.';
    }

    $sistema = "Voce e um assistente de pesquisa normativa de cartorio extrajudicial "
        . "brasileiro. Responda EXCLUSIVAMENTE com base nos trechos fornecidos.\n\n"
        . "Regras obrigatorias:\n"
        . "1. Se a resposta nao estiver nos trechos, diga claramente que nao encontrou. "
        . "Nunca complete com conhecimento proprio.\n"
        . "2. Cite sempre a fonte no formato [n], referenciando o numero do trecho.\n"
        . "3. Nunca invente numero de artigo, paragrafo ou inciso. Use apenas os que "
        . "aparecem literalmente nos trechos.\n"
        . "4. Se dois trechos se contradisserem, aponte a divergencia e destaque qual "
        . "norma e mais recente.\n"
        . "5. Trecho marcado como alterado por norma posterior: avise o usuario "
        . "expressamente de que a redacao pode nao ser a vigente.\n"
        . "6. Seja direto e objetivo. Portugues do Brasil.\n";

    $conteudo = "TRECHOS DISPONIVEIS:\n\n" . kbMontarContexto($trechos)
        . "\n\nPERGUNTA: " . $pergunta;

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('chat') . ':generateContent?key=' . kbApiKey();
    $body = array(
        'systemInstruction' => array('parts' => array(array('text' => $sistema))),
        'contents' => array(
            array('role' => 'user', 'parts' => array(array('text' => $conteudo))),
        ),
        'generationConfig' => array(
            'temperature'     => 0.15, // baixa: e pesquisa normativa, nao redacao
            'maxOutputTokens' => 1600,
        ),
    );

    $resp = kbHttpPost($url, $body);
    if (isset($resp['candidates'][0]['content']['parts'])) {
        $txt = '';
        foreach ($resp['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) {
                $txt .= $p['text'];
            }
        }
        return trim($txt);
    }
    throw new RuntimeException('Nao foi possivel gerar a resposta: '
        . substr(json_encode($resp), 0, 300));
}

// ===========================================================================
// 6. HTTP com retry e backoff
// ===========================================================================

function kbHttpPost($url, array $body, $tentativas = 5)
{
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    for ($n = 1; $n <= $tentativas; $n++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => KB_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 15,
            // Alguns IIS/proxies de cliente quebram com HTTP/2; forcamos 1.1.
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ));
        $raw  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw !== false && $http >= 200 && $http < 300) {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // 429 (quota) e 5xx sao transitorios: espera e tenta de novo.
        $transitorio = ($raw === false || $http === 429 || $http >= 500);
        if (!$transitorio || $n === $tentativas) {
            throw new RuntimeException("HTTP {$http} apos {$n} tentativa(s). "
                . ($err ?: substr((string) $raw, 0, 300)));
        }
        sleep(min(30, (int) pow(2, $n)));
    }
    throw new RuntimeException('Falha na chamada HTTP.');
}
