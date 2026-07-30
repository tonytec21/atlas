<?php
/**
 * provimentos/sync_lib.php
 * Sincronizacao com os portais do CNJ e da CGJ/MA.
 *
 * Cadeia de extracao de texto, do mais confiavel ao ultimo recurso:
 *   1. HTML da ficha do ato (os dois portais publicam o texto integral)
 *   2. pdftotext sobre o PDF anexo
 *   3. Gemini lendo o PDF (usa a mesma chave do modulo Aria)
 *
 * As relacoes de revogacao/alteracao declaradas pelo proprio portal entram
 * como CONFIRMADAS -- e a fonte oficial dizendo, nao inferencia de IA.
 */

require_once __DIR__ . '/../kb/lib_kb.php';
require_once __DIR__ . '/db_connection.php';

define('SYNC_UA', 'Mozilla/5.0 (compatible; AtlasProvimentos/1.0)');
// Incrementar SEMPRE que mudar tabela ou coluna. E o que dispara a migracao
// em base ja instalada -- conferir "tabela existe" nao basta, foi assim que
// 'prioridade' e depois 'pagina' ficaram faltando.
define('SYNC_SCHEMA_VERSAO', 14);

define('SYNC_TIMEOUT', 90);   // tjma.jus.br chega a passar de 45s
// O acervo guarda os PDFs em anexo/{ORIGEM}/{TIPO}/{ANO}/{NUMERO}.pdf
// (ex.: anexo/CGJ_MA/Provimento/2016/5.pdf). Manter o padrao e o que faz o
// botao "Abrir documento" funcionar igual para importado e cadastrado a mao.
define('SYNC_PASTA', __DIR__ . '/anexo');

// ===========================================================================
// SCHEMA
// ===========================================================================
function syncGarantirSchema(PDO $conn)
{
    $log = array();
    $ddl = function ($sql, $rot) use ($conn, &$log) {
        try { $conn->exec($sql); $log[] = "[OK]   {$rot}"; }
        catch (PDOException $e) {
            $c = isset($e->errorInfo[1]) ? $e->errorInfo[1] : 0;
            $log[] = in_array($c, array(1050,1060,1061,1022,1826), true)
                ? "[SKIP] {$rot}" : "[ERRO] {$rot}: " . $e->getMessage();
        }
    };

    $ddl("
CREATE TABLE kb_fontes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  adaptador    VARCHAR(20) NOT NULL,
  nome         VARCHAR(120) NOT NULL,
  origem       VARCHAR(60) NOT NULL,
  url_base     VARCHAR(255) NOT NULL,
  url_listagem VARCHAR(255) NULL,
  ativo        TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_id    INT NULL,
  pagina       INT NULL,
  lacuna_cursor INT NULL,
  ultima_verif DATETIME NULL,
  ultimo_erro  VARCHAR(500) NULL,
  UNIQUE KEY uq_adaptador (adaptador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_fontes');

    // Leis federais: lista curada, nao ha listagem para varrer.
    $ddl("
CREATE TABLE kb_leis (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  url           VARCHAR(500) NOT NULL,
  apelido       VARCHAR(160) NULL,
  numero        VARCHAR(20) NULL,
  ano           SMALLINT NULL,
  data_lei      DATE NULL,
  ementa        TEXT NULL,
  provimento_id INT NULL,
  chars         INT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_erro   VARCHAR(500) NULL,
  atualizado_em DATETIME NULL,
  criado_em     DATETIME NOT NULL,
  UNIQUE KEY uq_url (url(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_leis');

    // A coluna 'tipo' do acervo so aceitava Provimento e Resolucao. Sem abrir
    // espaco para 'Lei', o MySQL rejeita ou trunca em silencio.
    try {
        $st = $conn->query("SHOW COLUMNS FROM provimentos LIKE 'tipo'");
        $col = $st->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos($col['Type'], "'Lei'") === false) {
            $conn->exec("ALTER TABLE provimentos
                         MODIFY tipo ENUM('Provimento','Resolu\xc3\xa7\xc3\xa3o','Lei') NOT NULL");
            $log[] = '[OK]   coluna provimentos.tipo agora aceita Lei';
        } else {
            $log[] = '[SKIP] coluna provimentos.tipo ja aceita Lei';
        }
    } catch (PDOException $e) {
        $log[] = '[ERRO] ampliar provimentos.tipo: ' . $e->getMessage();
    }

    // Leis de interesse do foro extrajudicial.
    try {
        $conn->exec("INSERT IGNORE INTO kb_leis (url, apelido, ativo, criado_em) VALUES
            ('https://www.planalto.gov.br/ccivil_03/leis/l6015compilada.htm',
             'Lei de Registros Publicos', 1, NOW()),
            ('https://www.planalto.gov.br/ccivil_03/leis/l9492.htm',
             'Lei do Protesto de Titulos', 1, NOW()),
            ('https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/lei/l14382.htm',
             'SERP - Sistema Eletronico dos Registros Publicos', 1, NOW()),
            ('https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm',
             'LGPD - Protecao de Dados Pessoais', 1, NOW())");
    } catch (PDOException $e) { /* tabela pode nao existir ainda */ }

    $ddl("
CREATE TABLE kb_sync_itens (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  fonte_id      INT NOT NULL,
  id_externo    VARCHAR(60) NOT NULL,
  url           VARCHAR(500) NOT NULL,
  tipo          VARCHAR(40) NULL,
  numero        VARCHAR(20) NULL,
  ano           SMALLINT NULL,
  data_ato      DATE NULL,
  ementa        TEXT NULL,
  situacao      VARCHAR(40) NULL,
  prioridade    TINYINT NOT NULL DEFAULT 1,
  status        ENUM('novo','atualizado','importado','ignorado','erro') NOT NULL DEFAULT 'novo',
  provimento_id INT NULL,
  mensagem      VARCHAR(500) NULL,
  origem_texto  VARCHAR(20) NULL,
  visto_em      DATETIME NOT NULL,
  importado_em  DATETIME NULL,
  UNIQUE KEY uq_item (fonte_id, id_externo),
  KEY idx_status (status),
  KEY idx_ordem (prioridade, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_sync_itens');

    try {
        $conn->exec("INSERT IGNORE INTO kb_fontes
            (adaptador, nome, origem, url_base, url_listagem, ativo) VALUES
            ('cnj', 'CNJ - Provimentos', 'CNJ',
             'https://atos.cnj.jus.br/atos/detalhar/',
             'https://atos.cnj.jus.br/atos?atos=sim&tipoAto%5B0%5D=20', 1),
            ('cgjma', 'CGJ/MA - Provimentos COGEX', 'CGJ/MA',
             'https://www.tjma.jus.br/atos/extrajudicial/geral/',
             'https://www.tjma.jus.br/atos/extrajudicial/geral/0/5657/pnao/provimentos-cogex', 1)");
    } catch (PDOException $e) { /* tabela pode nao existir */ }

    // ---- Colunas incrementais ----
    // Obrigatorio: quando a tabela ja existe, o CREATE acima e pulado e uma
    // coluna nova nunca entraria. Foi exatamente o que aconteceu com
    // 'prioridade'.
    $coluna = function ($tabela, $col, $sql) use ($conn, $ddl, &$log) {
        try {
            $st = $conn->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
            $st->execute(array(':t' => $tabela, ':c' => $col));
            if ((int) $st->fetchColumn() === 0) {
                $ddl($sql, "coluna {$tabela}.{$col}");
            } else {
                $log[] = "[SKIP] coluna {$tabela}.{$col}";
            }
        } catch (PDOException $e) {
            $log[] = "[ERRO] coluna {$tabela}.{$col}: " . $e->getMessage();
        }
    };

    $coluna('kb_fontes', 'lacuna_cursor',
        "ALTER TABLE kb_fontes ADD COLUMN lacuna_cursor INT NULL");

    $coluna('kb_fontes', 'pagina',
        "ALTER TABLE kb_fontes ADD COLUMN pagina INT NULL");

    // Passa o CNJ a usar a listagem paginada. Com tipoAto[0]=20 os resultados
    // vem no HTML; a varredura de ids so existia porque eu nao tinha este
    // caminho.
    try {
        $conn->exec("UPDATE kb_fontes
                        SET nome = 'CNJ - Provimentos',
                            url_listagem = 'https://atos.cnj.jus.br/atos?atos=sim&tipoAto%5B0%5D=20',
                            pagina = NULL, lacuna_cursor = NULL, ultimo_erro = NULL
                      WHERE adaptador = 'cnj'
                        AND (url_listagem IS NULL OR url_listagem NOT LIKE '%tipoAto%')");
    } catch (PDOException $e) { /* ignora */ }

    // Corrige a URL da CGJ/MA: a versao anterior apontava para os provimentos
    // da CGJ judicial, nao para os do foro extrajudicial (COGEX), que sao os
    // que interessam a cartorio.
    try {
        $conn->exec("UPDATE kb_fontes
                        SET nome = 'CGJ/MA - Provimentos COGEX',
                            url_base = 'https://www.tjma.jus.br/atos/extrajudicial/geral/',
                            url_listagem = 'https://www.tjma.jus.br/atos/extrajudicial/geral/0/5657/pnao/provimentos-cogex',
                            pagina = NULL, ultimo_erro = NULL
                      WHERE adaptador = 'cgjma'
                        AND url_listagem LIKE '%/cgj/geral/0/205/%'");
    } catch (PDOException $e) { /* ignora */ }

    $coluna('kb_sync_itens', 'prioridade',
        "ALTER TABLE kb_sync_itens ADD COLUMN prioridade TINYINT NOT NULL DEFAULT 1");
    $coluna('kb_sync_itens', 'rechecado_em',
        "ALTER TABLE kb_sync_itens ADD COLUMN rechecado_em DATETIME NULL");

    $coluna('kb_sync_itens', 'origem_texto',
        "ALTER TABLE kb_sync_itens ADD COLUMN origem_texto VARCHAR(20) NULL");
    $ddl("ALTER TABLE kb_sync_itens ADD KEY idx_ordem (prioridade, ano)",
         'indice kb_sync_itens.idx_ordem');

    // Corrige acervo de sincronizacao anterior ao filtro por fonte: Resolucoes
    // do CNJ nao devem constar. So mexe no que ainda nao foi importado.
    try {
        $conn->exec("UPDATE kb_sync_itens i
                       JOIN kb_fontes f ON f.id = i.fonte_id
                        SET i.status = 'ignorado',
                            i.mensagem = 'Fora do escopo: no CNJ, apenas Provimentos.'
                      WHERE f.adaptador = 'cnj'
                        AND i.tipo <> 'Provimento'
                        AND i.status IN ('novo','atualizado','erro')");
        $conn->exec("UPDATE kb_sync_itens SET prioridade =
                        CASE WHEN tipo = 'Provimento' THEN 1 ELSE 2 END
                      WHERE prioridade IS NULL OR prioridade = 0");
    } catch (PDOException $e) { /* tabelas podem nao existir ainda */ }

    syncMarcarVersao($conn);

    return $log;
}

/**
 * Verifica tabelas E colunas. Checar so as tabelas fazia a migracao ser pulada
 * em instalacao ja existente, que e como a coluna 'prioridade' ficou faltando.
 */
/**
 * Compara a versao gravada com SYNC_SCHEMA_VERSAO. Enquanto forem diferentes,
 * a migracao roda. Assim uma coluna nova nunca fica para tras.
 */
function syncSchemaExiste(PDO $conn)
{
    try {
        $st = $conn->prepare(
            "SELECT valor FROM kb_config WHERE chave = 'sync_schema_versao'");
        $st->execute();
        return (int) $st->fetchColumn() >= SYNC_SCHEMA_VERSAO;
    } catch (PDOException $e) {
        return false;   // kb_config ainda nao existe: precisa migrar
    }
}

/** Grava a versao aplicada. Chamado ao fim de syncGarantirSchema(). */
function syncMarcarVersao(PDO $conn)
{
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS kb_config (
            chave VARCHAR(50) PRIMARY KEY, valor TEXT NULL,
            funcionario VARCHAR(120) NULL, atualizado_em DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $st = $conn->prepare(
            "INSERT INTO kb_config (chave, valor, atualizado_em)
             VALUES ('sync_schema_versao', :v, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()");
        $st->execute(array(':v' => SYNC_SCHEMA_VERSAO));
    } catch (PDOException $e) { /* nao impede o uso */ }
}

// ===========================================================================
// HTTP
// ===========================================================================
function syncGet($url, &$http = null)
{
    // Cookie de sessao entre as requisicoes: portais Laravel costumam emitir
    // um na primeira visita e servir conteudo diferente sem ele.
    static $cookies = null;
    if ($cookies === null) {
        $cookies = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atlas_sync_cookies.txt';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => SYNC_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => SYNC_UA,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_COOKIEJAR      => $cookies,
        CURLOPT_COOKIEFILE     => $cookies,
        CURLOPT_HTTPHEADER     => array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
            'Upgrade-Insecure-Requests: 1',
        ),
    ));
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Falha de rede: ' . $erro);
    }
    return syncParaUtf8($body);
}

/**
 * Converte a resposta para UTF-8 quando o site declara outra codificacao.
 * O Planalto ainda serve as leis em ISO-8859-1; sem converter, todo acento
 * vira lixo no acervo.
 */
function syncParaUtf8($html)
{
    if (strncmp($html, '%PDF', 4) === 0) {
        return $html;   // binario: nao mexe
    }

    $cs = null;
    if (preg_match('#charset\s*=\s*["\']?([\w-]+)#i', substr($html, 0, 3000), $m)) {
        $cs = strtoupper($m[1]);
    }
    if ($cs === null) {
        $cs = mb_check_encoding($html, 'UTF-8') ? 'UTF-8' : 'ISO-8859-1';
    }
    if (in_array($cs, array('UTF-8', 'UTF8'), true)) {
        return $html;
    }

    $conv = @iconv($cs, 'UTF-8//TRANSLIT', $html);
    if ($conv !== false && $conv !== '') {
        return $conv;
    }
    return mb_convert_encoding($html, 'UTF-8', $cs);
}

/** HTML -> texto limpo, no formato que o modulo ja usa (linha unica). */
function syncHtmlParaTexto($html)
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
    $html = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6])\s*/?>#i', "\n", $html);

    // Toda tag vira ESPACO, nao vazio. Removendo sem separador, o fim de um
    // elemento cola no inicio do proximo -- "...CONOSCO</a><a>PROVIMENTO..."
    // vira "CONOSCOPROVIMENTO" e o \b dos regex deixa de casar ali.
    $html = preg_replace('#<[^>]+>#', ' ', $html);

    $txt = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = str_replace("\xc2\xa0", ' ', $txt);
    return trim(preg_replace('/\s+/u', ' ', $txt));
}

// ===========================================================================
// ADAPTADOR: CNJ  (atos.cnj.jus.br)
// ===========================================================================

/**
 * Descoberta por varredura de IDs.
 *
 * Escolha deliberada: a pagina de busca do CNJ e um app JS cujo HTML muda sem
 * aviso, enquanto /atos/detalhar/{id} e estavel ha anos. Caminhar nos IDs
 * custa mais requisicoes, mas nao quebra quando o portal e redesenhado.
 */
/**
 * Descoberta pela listagem paginada de Provimentos do CNJ.
 *
 *   /atos?atos=sim&tipoAto[0]=20&page=N
 *
 * O detalhe que faz diferenca e o indice no parametro: com tipoAto[0]=20 a
 * tabela vem montada no HTML; com tipoAto[]=20 ela chega vazia, porque o
 * portal so renderiza por JavaScript. Foi o que me levou, antes, a varrer
 * identificadores um a um.
 *
 * Sao ~24 paginas de 10 para o acervo inteiro. A varredura de ids, a fila de
 * links e a interpolacao existiam so para contornar a falta deste caminho.
 */
function syncCnjDescobrir(PDO $conn, array $fonte, $limite = 30, $modo = 'novos')
{
    $pagina = max(1, (int) $fonte['pagina']);
    $url = sprintf('%s&page=%d', rtrim($fonte['url_listagem'], '&'), $pagina);

    $html = syncGet($url, $http);
    if ($http !== 200) {
        throw new RuntimeException('Listagem retornou HTTP ' . $http);
    }

    $linhas = syncCnjLinhasListagem($html);
    if (!$linhas && $pagina === 1) {
        // Mensagem com evidencia: sem saber o que chegou, "layout mudou" nao
        // ajuda ninguem a consertar.
        $links = preg_match_all('#\bdetalhar/\d+#i', $html);
        $msg = 'Nenhum ato reconhecido na listagem. Resposta de '
             . round(strlen($html) / 1024) . ' KB com ' . $links . ' link(s) de ficha';
        if ($links === 0) {
            $texto = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
            $msg .= '. A pagina nao traz atos - possivel bloqueio ou redirecionamento. '
                  . 'Inicio do conteudo: "' . mb_substr($texto, 0, 160) . '"';
        } else {
            $msg .= ', mas nao consegui interpretar as celulas. '
                  . 'Use o estetoscopio da fonte para ver o HTML recebido.';
        }
        throw new RuntimeException($msg);
    }

    $achados = 0;
    $novos   = 0;
    foreach ($linhas as $l) {
        if (syncPrioridade($l['tipo'], 'cnj') !== 1) { continue; }
        $meta = array(
            'tipo'     => $l['tipo'],
            'numero'   => $l['numero'],
            'ano'      => $l['ano'],
            'data'     => $l['data'],
            'situacao' => $l['situacao'],
            'ementa'   => $l['ementa'],
            'alteracoes' => array(),
        );
        $st = syncRegistrarItem($conn, $fonte, (string) $l['id'],
            'https://atos.cnj.jus.br/atos/detalhar/' . $l['id'], $meta, 1);
        $achados++;
        if ($st === 'novo' || $st === 'atualizado') { $novos++; }
    }

    // Total de paginas, lido da propria paginacao. Contar ate o total e mais
    // confiavel que perguntar "existe link para a proxima": quando a barra de
    // paginacao usa janela deslizante ou reticencias, o link seguinte pode nao
    // estar visivel e a varredura parava no meio.
    $total = $pagina;
    if (preg_match_all('#[?&]page=(\d+)#', $html, $mp)) {
        $total = max(array_map('intval', $mp[1]));
    }
    $total = max($total, $pagina);

    $concluido = ($pagina >= $total) || !$linhas;

    // No modo rapido a listagem vem da mais recente para a mais antiga, entao
    // paginas sem novidade indicam que ja alcancamos o acervo. Exige duas
    // paginas: a primeira sozinha pode nao conter nada novo por coincidencia.
    if ($modo === 'novos' && $novos === 0 && $achados > 0 && $pagina >= 2) {
        $concluido = true;
    }

    $conn->prepare("UPDATE kb_fontes SET pagina = :p, ultima_verif = NOW(), ultimo_erro = NULL
                     WHERE id = :id")
         ->execute(array(':p' => $concluido ? null : $pagina + 1, ':id' => $fonte['id']));

    return array('achados' => $achados, 'novos' => $novos,
                 'ate_id' => 'pagina ' . $pagina . ' de ' . $total,
                 'concluido' => $concluido);
}

/**
 * Le os atos da listagem do CNJ.
 *
 * Nao depende da estrutura da tabela. Na listagem, TODAS as celulas de uma
 * linha sao links para a mesma ficha, entao basta agrupar ancoras
 * consecutivas com o mesmo identificador -- cada grupo e uma linha.
 *
 * Foi preciso abandonar o casamento por <tr>...</tr>: o </tr> e opcional em
 * HTML5 e, numa pagina de ~190 KB, o .*? entre as tags pode estourar o limite
 * de retrocesso do PCRE. Nos dois casos preg_match_all devolve zero calado.
 *
 * As celulas sao classificadas pelo conteudo, nao pela posicao, para o leitor
 * sobreviver a uma reordenacao de colunas.
 */
function syncCnjLinhasListagem($html)
{
    $out = array();

    // Estrategia 1: linhas de tabela. O identificador e procurado em QUALQUER
    // lugar da linha -- href absoluto, href relativo, onclick, data-*. Exigir
    // "/atos/detalhar/" com barra inicial fazia a leitura falhar quando o
    // portal emite o link relativo.
    // Captura a linha INTEIRA, com os atributos da tag <tr>: o identificador
    // pode estar num onclick ou data-href da propria linha, nao so dentro dela.
    if (preg_match_all('#<tr[^>]*>.*?</tr>#is', $html, $trs)) {
        foreach ($trs[0] as $tr) {
            if (!preg_match('#\bdetalhar/(\d+)#i', $tr, $mid)) { continue; }
            $celulas = array();
            if (preg_match_all('#<t[dh][^>]*>(.*?)</t[dh]>#is', $tr, $tds)) {
                $celulas = array_map('syncHtmlParaTexto', $tds[1]);
            }
            if (count($celulas) < 3) { continue; }
            $l = syncCnjMontarLinha((int) $mid[1], $celulas);
            if ($l) { $out[] = $l; }
        }
    }
    if ($out) { return $out; }

    // Estrategia 2: ancoras agrupadas pelo identificador. Serve quando cada
    // celula e um <a> proprio e a pagina nao usa <table>.
    $porId = array();
    if (preg_match_all('#<a\b[^>]*(?:href|data-href)\s*=\s*["\'][^"\']*?detalhar/(\d+)[^"\']*["\'][^>]*>(.*?)</a>#is',
                       $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $txt = syncHtmlParaTexto($a[2]);
            if ($txt === '') { continue; }
            $porId[(int) $a[1]][] = $txt;
        }
    }
    foreach ($porId as $id => $celulas) {
        $l = syncCnjMontarLinha($id, $celulas);
        if ($l) { $out[] = $l; }
    }
    return $out;
}

/**
 * Monta a linha a partir das celulas, sem depender da posicao exata:
 * procura o tipo, o numero e a data onde quer que estejam.
 */
function syncCnjMontarLinha($id, array $celulas)
{
    $tipo = null; $numero = null; $data = null; $situacao = null; $ementa = null;

    foreach ($celulas as $c) {
        $c = trim($c);
        if ($c === '') { continue; }

        if ($tipo === null) {
            $t = syncNormalizarTipo($c);
            // So aceita se a celula for exatamente o tipo, nao um texto que o cite.
            if ($t !== null && mb_strlen($c) <= 20) { $tipo = $t; continue; }
        }
        if ($data === null && preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $c, $md)) {
            $data = array($md[3], $md[2], $md[1]);
            continue;
        }
        if ($numero === null && preg_match('#^\d{1,4}$#', $c)) {
            $numero = syncNormalizarNumero($c);
            continue;
        }
        if ($situacao === null && preg_match('#^(Vigente|Alterado|Revogado.*|Sem efeito|Suspenso|Exaurido)$#iu', $c)) {
            $situacao = $c;
            continue;
        }
        if (mb_strlen($c) > 40 && $ementa === null) {
            $ementa = mb_substr($c, 0, 900);
        }
    }

    if ($tipo === null || $numero === null || $numero === '' || $data === null) {
        return null;
    }
    return array(
        'id'       => $id,
        'tipo'     => $tipo,
        'numero'   => $numero,
        'data'     => $data[0] . '-' . $data[1] . '-' . $data[2],
        'ano'      => (int) $data[0],
        'situacao' => $situacao,
        'ementa'   => $ementa,
    );
}

/**
 * Classifica os PDFs da ficha pelo TEXTO da ancora ("Texto Original",
 * "Texto Compilado"). Foi removida por engano na limpeza da varredura de ids,
 * e syncCnjFicha() depende dela.
 * @return array ['compilado'=>url, 'original'=>url, 'qualquer'=>url]
 */
function syncCnjLinksTexto($html)
{
    $out = array();
    if (!preg_match_all('#<a\b([^>]*)>(.*?)</a>#is', $html, $links, PREG_SET_ORDER)) {
        return $out;
    }

    foreach ($links as $a) {
        if (!preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $a[1], $mh)) { continue; }
        $url = trim(html_entity_decode($mh[1], ENT_QUOTES, 'UTF-8'));
        if (!preg_match('#\.pdf(?:[?\#].*)?$#i', $url)) { continue; }
        if (strpos($url, 'http') !== 0) {
            $url = 'https://atos.cnj.jus.br/' . ltrim($url, '/');
        }

        $rotulo = mb_strtolower(trim(preg_replace('/\s+/u', ' ', strip_tags($a[2]))), 'UTF-8');
        if (strpos($rotulo, 'compilado') !== false && empty($out['compilado'])) {
            $out['compilado'] = $url;
        } elseif (strpos($rotulo, 'original') !== false && empty($out['original'])) {
            $out['original'] = $url;
        }
        if (empty($out['qualquer'])) { $out['qualquer'] = $url; }
    }

    if (empty($out['compilado']) && empty($out['original'])
        && preg_match('#["\']((?:https?://[^"\']+)?/?files/(compilado|original)[^"\']*?\.pdf)["\']#i',
                      $html, $mf)) {
        $u = (strpos($mf[1], 'http') === 0) ? $mf[1] : 'https://atos.cnj.jus.br/' . ltrim($mf[1], '/');
        $out[mb_strtolower($mf[2], 'UTF-8')] = $u;
        if (empty($out['qualquer'])) { $out['qualquer'] = $u; }
    }
    return $out;
}

/** Extrai os campos da ficha do CNJ. */
function syncCnjFicha($html)
{
    $texto = syncHtmlParaTexto($html);

    // "Provimento Nº 149 de 30/08/2023"
    if (!preg_match('/\b(Provimento|Resolu\x{00E7}\x{00E3}o|Recomenda\x{00E7}\x{00E3}o|Instru\x{00E7}\x{00E3}o Normativa|Portaria)\s*'
        . 'N[\x{00BA}\x{00B0}o\.]*\s*([\d\.]+)\s*de\s*(\d{2})\/(\d{2})\/(\d{4})/iu', $texto, $m)) {
        return null;
    }

    $meta = array(
        'tipo'   => syncNormalizarTipo($m[1]),
        'numero' => syncNormalizarNumero($m[2]),
        'data'   => $m[5] . '-' . $m[4] . '-' . $m[3],
        'ano'    => (int) $m[5],
    );
    if (!$meta['tipo'] || $meta['numero'] === '') {
        return null;
    }

    if (preg_match('/Ementa\s+(.{20,900}?)\s+Situa\x{00E7}\x{00E3}o/su', $texto, $me)) {
        $meta['ementa'] = trim($me[1]);
    }
    if (preg_match('/Situa\x{00E7}\x{00E3}o\s+(Vigente|Alterado|Revogado[^\s]*|Sem efeito|Suspenso)/iu', $texto, $ms)) {
        $meta['situacao'] = ucfirst(mb_strtolower($ms[1], 'UTF-8'));
    }

    // PDF do texto compilado (preferido) ou original
    // O link do PDF esta ancorado nas frases "Texto Original" e "Texto
    // Compilado". Procurar pelo TEXTO da ancora e mais robusto que casar o
    // formato do href, que varia em aspas, protocolo e ordem de atributos.
    $meta['pdf'] = null;
    $meta['pdf_candidatos'] = syncCnjLinksTexto($html);
    // Compilado primeiro: e a redacao consolidada, mais util na consulta.
    // Original como reserva -- atos nunca alterados so tem esse.
    foreach (array('compilado', 'original', 'qualquer') as $pref) {
        if (!empty($meta['pdf_candidatos'][$pref])) {
            $meta['pdf'] = $meta['pdf_candidatos'][$pref];
            break;
        }
    }

    // Corpo normativo: comeca no "RESOLVE:" / "CONSIDERANDO"
    $corpo = $texto;
    if (preg_match('/(O\s+CORREGEDOR|CONSIDERANDO|RESOLVE\s*:)/u', $texto, $mc, PREG_OFFSET_CAPTURE)) {
        $corpo = substr($texto, $mc[0][1]);
    }
    $meta['texto'] = $corpo;

    // Atos que alteram este -- declarados pelo proprio portal
    $meta['alteracoes'] = array();
    if (preg_match('/Altera\x{00E7}\x{00E3}o(.{0,20000}?)(Legisla\x{00E7}\x{00E3}o Correlata|Observa\x{00E7}\x{00E3}o|Texto)/su', $texto, $ma)) {
        if (preg_match_all('/(Provimento|Resolu\x{00E7}\x{00E3}o)\s*n\.?\s*(\d+),\s*de\s*\d+\s*de\s*\w+\s*de\s*(\d{4})/iu',
                           $ma[1], $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $meta['alteracoes'][] = array('numero' => $x[2], 'ano' => (int) $x[3]);
            }
        }
    }
    return $meta;
}

// ===========================================================================
// ADAPTADOR: CGJ/MA  (tjma.jus.br)
// ===========================================================================
/**
 * Descoberta na listagem paginada dos Provimentos COGEX.
 *
 * A propria listagem ja traz numero, data, situacao e ementa de cada ato, entao
 * a descoberta nao precisa abrir 150 fichas -- sao 15 requisicoes em vez de 150.
 * A ficha so e aberta na hora de importar, para pegar o texto integral.
 *
 * Percorre uma pagina por chamada e devolve 'concluido' quando chega ao fim.
 */
function syncCgjmaDescobrir(PDO $conn, array $fonte, $limite = 40, $modo = 'novos')
{
    $pagina = max(1, (int) $fonte['pagina']);
    $url = ($pagina <= 1)
        ? $fonte['url_listagem']
        : preg_replace('#/pnao/provimentos-cogex$#', '/pnao/' . $pagina . '/provimentos-cogex',
                       $fonte['url_listagem']);

    $html = syncGet($url, $http);
    if ($http !== 200) {
        throw new RuntimeException('Listagem retornou HTTP ' . $http);
    }

    // <a href=".../atos/extrajudicial/geral/{id}/5657/pnao">...texto do item...</a>
    if (!preg_match_all(
            '#<a[^>]+href="([^"]*?/atos/extrajudicial/geral/(\d+)/5657/pnao[^"]*)"[^>]*>(.*?)</a>#is',
            $html, $itens, PREG_SET_ORDER)) {
        throw new RuntimeException('Nenhum item reconhecido na pagina ' . $pagina
            . '. O layout do portal pode ter mudado.');
    }

    $achados = 0;
    $novos   = 0;
    $vistos  = array();
    foreach ($itens as $it) {
        $idExt = $it[2];
        if (isset($vistos[$idExt])) { continue; }
        $vistos[$idExt] = true;

        $meta = syncCgjmaItemListagem($it[3]);
        if (!$meta) { continue; }                       // link de menu, nao e ato
        $pri = syncPrioridade($meta['tipo'], 'cgjma');
        if ($pri === false) { continue; }

        $urlItem = (strpos($it[1], 'http') === 0) ? $it[1] : 'https://www.tjma.jus.br' . $it[1];
        $st = syncRegistrarItem($conn, $fonte, $idExt, $urlItem, $meta, $pri);
        $achados++;
        if ($st === 'novo' || $st === 'atualizado') { $novos++; }
    }

    // Total de paginas pelo maior numero citado na paginacao.
    $total = $pagina;
    if (preg_match_all('#/pnao/(\d+)/provimentos-cogex#', $html, $mp)) {
        $total = max(array_map('intval', $mp[1]));
    }
    $total = max($total, $pagina);

    $concluido = ($pagina >= $total) || $achados === 0;

    // A listagem vem da mais recente para a mais antiga. No modo rapido, duas
    // paginas sem novidade indicam que ja alcancamos o acervo -- poupa 13 das
    // 15 paginas. Uma pagina so seria criterio fragil demais.
    if ($modo === 'novos' && $novos === 0 && $achados > 0 && $pagina >= 2) {
        $concluido = true;
    }

    $conn->prepare("UPDATE kb_fontes SET pagina = :p, ultima_verif = NOW(), ultimo_erro = NULL
                     WHERE id = :id")
         ->execute(array(':p' => $concluido ? null : $pagina + 1, ':id' => $fonte['id']));

    return array('achados' => $achados, 'novos' => $novos,
                 'ate_id' => 'pagina ' . $pagina . ' de ' . $total, 'concluido' => $concluido);
}

/**
 * Le um item da listagem COGEX. O texto do link vem assim:
 *   "SETOR PROVIMENTO Nº 29, DE 24 DE ABRIL DE 2026. Vigente Institui o Manual..."
 */
function syncCgjmaItemListagem($textoHtml)
{
    $t = syncHtmlParaTexto($textoHtml);

    if (!preg_match('/\b(PROVIMENTO|RESOLU\x{00C7}\x{00C3}O)\s*N[\x{00BA}\x{00B0}o\.\s]*\s*(\d+)[\s,]*'
        . 'DE\s*(\d{1,2})\s*DE\s*([A-Z\x{00C0}-\x{00DC}]+)\s*DE\s*(\d{4})/iu', $t, $m)) {
        return null;
    }

    $meses = array('janeiro'=>'01','fevereiro'=>'02','marco'=>'03','abril'=>'04','maio'=>'05',
        'junho'=>'06','julho'=>'07','agosto'=>'08','setembro'=>'09','outubro'=>'10',
        'novembro'=>'11','dezembro'=>'12');
    $mes = mb_strtolower($m[4], 'UTF-8');
    $mes = strtr($mes, array("\xc3\xa7" => 'c', "\xc3\xa3" => 'a'));
    if (!isset($meses[$mes])) { return null; }

    $meta = array(
        'tipo'       => syncNormalizarTipo($m[1]),
        'numero'     => syncNormalizarNumero($m[2]),
        'ano'        => (int) $m[5],
        'data'       => $m[5] . '-' . $meses[$mes] . '-' . str_pad($m[3], 2, '0', STR_PAD_LEFT),
        'alteracoes' => array(),
    );
    if (!$meta['tipo'] || $meta['numero'] === '') { return null; }

    if (preg_match('/\b(Vigente|Revogad[oa]|Sem efeito)\b/iu', $t, $ms)) {
        $meta['situacao'] = ucfirst(mb_strtolower($ms[1], 'UTF-8'));
    }
    // A ementa vem depois da situacao.
    if (preg_match('/(?:Vigente|Revogad[oa]|Sem efeito)\s+(.{15,600})$/iu', $t, $me)) {
        $meta['ementa'] = trim($me[1]);
    }
    return $meta;
}

/**
 * Ficha do Provimento COGEX.
 *
 * A pagina e um portal inteiro: menu lateral, noticias, rodape. Pegar o texto
 * bruto encheria conteudo_anexo de item de menu. Por isso o corpo e recortado
 * entre "Assunto:" e "DOWNLOADS".
 *
 * E o texto do ato normalmente NAO esta na pagina -- so a ementa e, as vezes,
 * um resumo. O conteudo de verdade esta no PDF da secao DOWNLOADS, que costuma
 * ser digitalizado. Dai a importancia da etapa de OCR.
 */
function syncCgjmaFicha($html)
{
    $meta = array('alteracoes' => array());

    // ---- Titulo: "PROVIMENTO Nº 29, DE 24 DE ABRIL DE 2026." ----
    // O <h1> primeiro: o corpo do ato costuma citar OUTROS provimentos
    // ("Revogam-se... do Provimento nº 16, de 28 de abril de 2022"), e pegar
    // a primeira ocorrencia do texto corrido leva ao ato errado.
    $cabecalho = '';
    if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $mh1)) {
        $cabecalho = syncHtmlParaTexto($mh1[1]) . ' ';
    }
    $cabecalho .= syncHtmlParaTexto(mb_substr($html, 0, 200000));
    if (!preg_match('/\b(PROVIMENTO|RESOLU\x{00C7}\x{00C3}O)\s*N[\x{00BA}\x{00B0}o\.\s]*\s*(\d+)[\s,]*'
        . 'DE\s*(\d{1,2})\s*DE\s*([A-Z\x{00C0}-\x{00DC}a-z\x{00E0}-\x{00FC}]+)\s*DE\s*(\d{4})/iu',
        $cabecalho, $m)) {
        return null;
    }

    $meses = array('janeiro'=>'01','fevereiro'=>'02','marco'=>'03','abril'=>'04','maio'=>'05',
        'junho'=>'06','julho'=>'07','agosto'=>'08','setembro'=>'09','outubro'=>'10',
        'novembro'=>'11','dezembro'=>'12');
    $mes = strtr(mb_strtolower($m[4], 'UTF-8'), array("\xc3\xa7" => 'c', "\xc3\xa3" => 'a'));
    if (!isset($meses[$mes])) {
        return null;
    }

    $meta['tipo']   = syncNormalizarTipo($m[1]);
    $meta['numero'] = syncNormalizarNumero($m[2]);
    $meta['ano']    = (int) $m[5];
    $meta['data']   = $m[5] . '-' . $meses[$mes] . '-' . str_pad($m[3], 2, '0', STR_PAD_LEFT);
    if (!$meta['tipo'] || $meta['numero'] === '') {
        return null;
    }
    $meta['titulo'] = trim($m[0]);

    // ---- Recorte do miolo: de "Setor Origem"/"Assunto" ate "DOWNLOADS" ----
    $corpoHtml = $html;
    $ini = false;
    foreach (array('Setor Origem', 'Situa&ccedil;&atilde;o', 'Situação', 'Assunto') as $marca) {
        $p = stripos($html, $marca);
        if ($p !== false) { $ini = $p; break; }
    }
    $fim = false;
    if (preg_match('#class\s*=\s*["\'][^"\']*general-link-list[^"\']*downloads#i',
                   $html, $mfc, PREG_OFFSET_CAPTURE)) {
        $fim = $mfc[0][1];
    }
    if ($fim === false) {
        $fim = stripos($html, 'DOWNLOADS');
    }
    if ($ini !== false && $fim !== false && $fim > $ini) {
        $corpoHtml = substr($html, $ini, $fim - $ini);
    }
    $corpo = syncHtmlParaTexto($corpoHtml);

    if (preg_match('/\b(Vigente|Revogad[oa]|Sem efeito|Alterad[oa])\b/iu', $corpo, $ms)) {
        $meta['situacao'] = ucfirst(mb_strtolower($ms[1], 'UTF-8'));
    }
    if (preg_match('/Assunto\s*:?\s*(.{15,600}?)(?:RESOLVE|CONSIDERANDO|Art\.|$)/su', $corpo, $me)) {
        $meta['ementa'] = trim($me[1]);
    }

    // Fica so o dispositivo, sem os rotulos da ficha.
    if (preg_match('/(RESOLVE|CONSIDERANDO|Art\.\s*1)/u', $corpo, $mc, PREG_OFFSET_CAPTURE)) {
        $meta['texto'] = trim(substr($corpo, $mc[0][1]));
    } else {
        $meta['texto'] = '';   // sem dispositivo na pagina: vai depender do PDF
    }

    // ---- PDF: secao DOWNLOADS ----
    $meta['pdf_candidatos'] = syncCgjmaLinksDownload($html, $meta['titulo']);
    foreach (array('downloads', 'storage', 'marcador', 'titulo', 'pdf') as $pref) {
        if (!empty($meta['pdf_candidatos'][$pref])) {
            $meta['pdf'] = $meta['pdf_candidatos'][$pref];
            break;
        }
    }
    return $meta;
}

/**
 * Acha o anexo na secao DOWNLOADS. O link costuma estar ancorado no proprio
 * nome do provimento, e nem sempre a URL termina em .pdf (as vezes e uma rota
 * de download), entao a busca e por posicao na pagina, nao por extensao.
 */
function syncCgjmaLinksDownload($html, $titulo)
{
    $out = array();

    $absoluta = function ($u) {
        $u = trim(html_entity_decode($u, ENT_QUOTES, 'UTF-8'));
        if ($u === '' || strpos($u, '#') === 0 || stripos($u, 'javascript:') === 0) {
            return null;
        }
        return (strpos($u, 'http') === 0) ? $u : 'https://www.tjma.jus.br/' . ltrim($u, '/');
    };

    // Descarta o que e navegacao do portal, nao arquivo.
    $ehArquivo = function ($u) {
        if (preg_match('#/(portal|site|midia|institucional|hotsite|links|atos|legislacao)/#i', $u)) {
            return false;
        }
        return true;
    };

    // 1. Bloco de downloads, identificado pela classe do container.
    //    E o ponto mais estavel da pagina: o texto "DOWNLOADS" e apresentacao,
    //    a classe e estrutura.
    if (preg_match('#class\s*=\s*["\'][^"\']*general-link-list[^"\']*downloads[^"\']*["\']#i',
                   $html, $mc, PREG_OFFSET_CAPTURE)) {
        $trecho = substr($html, $mc[0][1], 12000);
        if (preg_match_all('#href\s*=\s*["\']([^"\']+)["\']#i', $trecho, $mh)) {
            foreach ($mh[1] as $u) {
                $u = $absoluta($u);
                if ($u && $ehArquivo($u)) { $out['downloads'] = $u; break; }
            }
        }
    }

    // 2. Host de arquivos do TJMA em qualquer lugar da pagina.
    if (empty($out['downloads'])
        && preg_match('#["\']([^"\']*?(?:novogerenciador|gerenciador)\.tjma\.jus\.br/[^"\']+)["\']#i',
                      $html, $mp)) {
        $u = $absoluta($mp[1]);
        if ($u) { $out['storage'] = $u; }
    }

    // 3. Marcador textual DOWNLOADS, para layouts sem a classe.
    if (empty($out['downloads']) && empty($out['storage'])) {
        $pos = stripos($html, 'DOWNLOADS');
        if ($pos !== false) {
            $trecho = substr($html, $pos, 8000);
            if (preg_match_all('#href\s*=\s*["\']([^"\']+)["\']#i', $trecho, $mh)) {
                foreach ($mh[1] as $u) {
                    $u = $absoluta($u);
                    if ($u && $ehArquivo($u)) { $out['marcador'] = $u; break; }
                }
            }
        }
    }

    // 4. Ancora cujo texto repete o titulo do ato.
    if (!$out && $titulo
        && preg_match_all('#<a\b([^>]*)>(.*?)</a>#is', $html, $links, PREG_SET_ORDER)) {
        $alvo = preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($titulo, 'UTF-8'));
        foreach ($links as $a) {
            $rot = preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(strip_tags($a[2]), 'UTF-8'));
            if ($rot === '' || $alvo === '' || strpos($rot, $alvo) === false) { continue; }
            if (!preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $a[1], $mh)) { continue; }
            $u = $absoluta($mh[1]);
            if ($u && $ehArquivo($u)) { $out['titulo'] = $u; break; }
        }
    }

    // 5. Ultimo recurso: qualquer .pdf.
    if (!$out && preg_match('#["\']([^"\']+\.pdf)["\']#i', $html, $mp)) {
        $u = $absoluta($mp[1]);
        if ($u) { $out['pdf'] = $u; }
    }
    return $out;
}

// ===========================================================================
// LEIS FEDERAIS (planalto.gov.br)
// ===========================================================================

/**
 * Le uma pagina de lei do Planalto.
 *
 * As paginas sao antigas e servidas em ISO-8859-1 -- syncGet() ja converte.
 * A estrutura e estavel ha decadas: titulo, ementa, "O PRESIDENTE DA
 * REPUBLICA", corpo, e o rodape do D.O.U.
 */
function syncLeiFicha($html)
{
    $texto = syncHtmlParaTexto($html);

    if (!preg_match('/\bLEI\s+(?:COMPLEMENTAR\s+)?N[\x{00BA}\x{00B0}o\.\s]*\s*([\d\.]+)\s*,?\s*'
        . 'DE\s*(\d{1,2})\s*DE\s*([A-Z\x{00C0}-\x{00DC}a-z\x{00E0}-\x{00FC}]+)\s*DE\s*(\d{4})/iu',
        $texto, $m)) {
        return null;
    }

    $meses = array('janeiro'=>'01','fevereiro'=>'02','marco'=>'03','abril'=>'04','maio'=>'05',
        'junho'=>'06','julho'=>'07','agosto'=>'08','setembro'=>'09','outubro'=>'10',
        'novembro'=>'11','dezembro'=>'12');
    $mes = strtr(mb_strtolower($m[3], 'UTF-8'), array("\xc3\xa7" => 'c', "\xc3\xa3" => 'a'));
    if (!isset($meses[$mes])) {
        return null;
    }

    $meta = array(
        'tipo'   => 'Lei',
        'numero' => syncNormalizarNumero($m[1]),
        'ano'    => (int) $m[4],
        'data'   => $m[4] . '-' . $meses[$mes] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT),
        'titulo' => trim($m[0]),
    );
    if ($meta['numero'] === '') {
        return null;
    }

    // Ementa: entre o titulo e o preambulo presidencial.
    $depoisTitulo = mb_substr($texto, mb_strpos($texto, $m[0]) + mb_strlen($m[0]));
    if (preg_match('/^(.{20,900}?)\s*O\s+PRESIDENTE\s+DA\s+REP/su', $depoisTitulo, $me)) {
        $meta['ementa'] = trim($me[1], " .-\t\n");
    }

    // Corpo: do preambulo ate o rodape do D.O.U.
    $corpo = $depoisTitulo;
    if (preg_match('/O\s+PRESIDENTE\s+DA\s+REP/u', $corpo, $mp, PREG_OFFSET_CAPTURE)) {
        $corpo = substr($corpo, $mp[0][1]);
    }
    $corte = preg_split('/Este\s+texto\s+n\x{00E3}o\s+substitui/u', $corpo);
    $meta['texto'] = trim($corte[0]);

    return $meta;
}

/**
 * Baixa a lei e grava no acervo como tipo 'Lei', origem 'Federal'.
 * Reaproveita a mesma tabela dos provimentos, entao a indexacao, a busca e a
 * citacao continuam funcionando sem nenhuma adaptacao.
 */
function syncLeiImportar(PDO $conn, $leiId, $funcionario = null)
{
    $st = $conn->prepare("SELECT * FROM kb_leis WHERE id = :id");
    $st->execute(array(':id' => (int) $leiId));
    $lei = $st->fetch(PDO::FETCH_ASSOC);
    if (!$lei) {
        return array('ok' => false, 'mensagem' => 'Lei não cadastrada.');
    }

    try {
        $html = syncGet($lei['url'], $http);
        if ($http !== 200) {
            throw new RuntimeException('A página retornou HTTP ' . $http);
        }
        $meta = syncLeiFicha($html);
        if (!$meta) {
            throw new RuntimeException('Não reconheci o número e a data da lei nessa página.');
        }
        if (mb_strlen($meta['texto']) < 1000) {
            throw new RuntimeException('Texto muito curto ('
                . mb_strlen($meta['texto']) . ' caracteres).');
        }

        $texto = trim(preg_replace('/\s+/u', ' ', $meta['texto']));

        // Ja existe no acervo?
        $bus = $conn->prepare(
            "SELECT id FROM provimentos
              WHERE tipo = 'Lei' AND numero_provimento = :n AND YEAR(data_provimento) = :a
              LIMIT 1");
        $bus->execute(array(':n' => $meta['numero'], ':a' => $meta['ano']));
        $provId = $bus->fetchColumn();

        $descricao = isset($meta['ementa']) ? mb_substr($meta['ementa'], 0, 900)
                   : ($lei['apelido'] ?: 'Lei federal');

        if ($provId) {
            $conn->prepare(
                "UPDATE provimentos SET conteudo_anexo = :c, descricao = :d,
                        caminho_anexo = :a WHERE id = :id")
                 ->execute(array(':c' => $texto, ':d' => $descricao,
                                 ':a' => $lei['url'], ':id' => $provId));
        } else {
            $conn->prepare(
                "INSERT INTO provimentos
                    (numero_provimento, origem, descricao, data_provimento, caminho_anexo,
                     tipo, funcionario, data_cadastro, status, conteudo_anexo)
                 VALUES (:n,'Federal',:d,:dt,:a,'Lei',:f,NOW(),'Ativo',:c)")
                 ->execute(array(':n' => $meta['numero'], ':d' => $descricao,
                                 ':dt' => $meta['data'], ':a' => $lei['url'],
                                 ':f' => $funcionario, ':c' => $texto));
            $provId = (int) $conn->lastInsertId();
        }

        $conn->prepare(
            "UPDATE kb_leis SET numero = :n, ano = :a, data_lei = :dt, ementa = :e,
                    provimento_id = :p, chars = :c, ultimo_erro = NULL, atualizado_em = NOW()
              WHERE id = :id")
             ->execute(array(':n' => $meta['numero'], ':a' => $meta['ano'], ':dt' => $meta['data'],
                             ':e' => $descricao, ':p' => $provId,
                             ':c' => mb_strlen($texto), ':id' => $lei['id']));

        return array(
            'ok' => true,
            'ato' => 'Lei ' . $meta['numero'] . '/' . $meta['ano'],
            'provimento_id' => (int) $provId,
            'mensagem' => 'Importada (' . number_format(mb_strlen($texto), 0, ',', '.')
                        . ' caracteres).',
        );

    } catch (Throwable $e) {
        $conn->prepare("UPDATE kb_leis SET ultimo_erro = :e, atualizado_em = NOW() WHERE id = :id")
             ->execute(array(':e' => mb_substr($e->getMessage(), 0, 480), ':id' => (int) $leiId));
        return array('ok' => false, 'mensagem' => $e->getMessage());
    }
}

// ===========================================================================
// COMUM
// ===========================================================================

/**
 * Normaliza para o padrao gravado no acervo.
 *
 * O acervo guarda numero sem zeros a esquerda ('5', nao '005') e a coluna
 * 'tipo' e um ENUM que so aceita 'Provimento' e 'Resolucao' (com cedilha e til).
 * Gravar fora disso faz o MySQL rejeitar ou truncar em silencio.
 */
function syncNormalizarNumero($numero)
{
    $n = preg_replace('/\D+/', '', (string) $numero);
    return ($n === '') ? '' : (string) (int) $n;
}

function syncNormalizarTipo($tipo)
{
    // Remove acentos por bytes UTF-8 explicitos. Em aspas simples o PHP nao
    // interpreta \xNN, entao a versao anterior comparava literais errados e
    // descartava "Resolução" acentuado.
    $t = mb_strtolower((string) $tipo, 'UTF-8');
    $t = strtr($t, array(
        "\xc3\xa7" => 'c',   // ç
        "\xc3\xa3" => 'a',   // ã
        "\xc3\xb5" => 'o',   // õ
        "\xc3\xa9" => 'e',   // é
        "\xc3\xad" => 'i',   // í
    ));

    if (strpos($t, 'provimento') !== false) {
        return 'Provimento';
    }
    if (strpos($t, 'resolucao') !== false) {
        return "Resolu\xc3\xa7\xc3\xa3o";   // exatamente como no ENUM: Resolução
    }
    return null;   // fora do ENUM da coluna 'tipo': nao entra no acervo
}

/**
 * O que cada fonte deve trazer.
 *
 * CNJ: exclusivamente Provimentos. As Resolucoes do CNJ sao atos do Plenario,
 * de alcance institucional amplo, e poluem o acervo cartorario.
 * CGJ/MA: Provimentos e tambem Resolucoes, que ali tratam de materia registral.
 *
 * @return int|false prioridade (1 = mais relevante) ou false se nao interessa
 */
function syncPrioridade($tipo, $adaptador)
{
    $t = mb_strtolower((string) $tipo, 'UTF-8');
    $t = strtr($t, array('ç' => 'c', 'ã' => 'a', 'õ' => 'o', 'é' => 'e'));

    if ($adaptador === 'cnj') {
        return ($t === 'provimento') ? 1 : false;
    }
    if ($t === 'provimento') { return 1; }
    if ($t === 'resolucao')  { return 2; }
    return false;
}

/** Grava/atualiza o candidato, comparando com o acervo. */
function syncRegistrarItem(PDO $conn, array $fonte, $idExterno, $url, array $meta, $prioridade = 1)
{
    // Ja existe no acervo?
    $st = $conn->prepare(
        "SELECT id FROM provimentos
          WHERE numero_provimento = :n AND origem = :o AND YEAR(data_provimento) = :a LIMIT 1");
    $st->execute(array(':n' => $meta['numero'], ':o' => $fonte['origem'], ':a' => $meta['ano']));
    $existe = $st->fetchColumn();

    $status = $existe ? 'ignorado' : 'novo';
    // Situacao mudou (revogado/alterado) num que ja temos: vale reimportar.
    if ($existe && !empty($meta['situacao'])
        && preg_match('/revogad|alterad|sem efeito/iu', $meta['situacao'])) {
        $status = 'atualizado';
    }

    $st = $conn->prepare(
        "INSERT INTO kb_sync_itens
            (fonte_id, id_externo, url, tipo, numero, ano, data_ato, ementa, situacao,
             status, provimento_id, prioridade, visto_em)
         VALUES (:f,:ie,:u,:t,:n,:a,:d,:e,:s,:st,:pid,:pr,NOW())
         ON DUPLICATE KEY UPDATE
            situacao = VALUES(situacao),
            ementa   = VALUES(ementa),
            status     = IF(status = 'importado', 'importado', VALUES(status)),
            prioridade = VALUES(prioridade),
            visto_em   = NOW()");
    // Sem isto, um INSERT rejeitado (coluna curta, ENUM invalido, modo estrito)
    // e ignorado em silencio e a funcao devolve 'novo' para uma linha que
    // nunca existiu -- o contador diz 10, a lista mostra zero.
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $st->execute(array(
        ':f'  => $fonte['id'],
        ':ie' => $idExterno,
        ':u'  => $url,
        ':t'  => $meta['tipo'],
        ':n'  => $meta['numero'],
        ':a'  => $meta['ano'],
        ':d'  => $meta['data'],
        ':e'  => isset($meta['ementa']) ? mb_substr($meta['ementa'], 0, 900) : null,
        ':s'  => isset($meta['situacao']) ? $meta['situacao'] : null,
        ':st' => $status,
        ':pid'=> $existe ?: null,
        ':pr' => (int) $prioridade,
    ));

    // Confere que a linha existe mesmo. Barato e elimina a duvida.
    $conf = $conn->prepare(
        "SELECT status FROM kb_sync_itens WHERE fonte_id = :f AND id_externo = :i");
    $conf->execute(array(':f' => $fonte['id'], ':i' => $idExterno));
    $gravado = $conf->fetchColumn();

    if ($gravado === false) {
        throw new RuntimeException('Nao consegui gravar o candidato '
            . $meta['tipo'] . ' ' . $meta['numero'] . '/' . $meta['ano']
            . ' (id externo ' . $idExterno . ').');
    }
    return $gravado;   // 'novo' | 'atualizado' | 'ignorado' | 'importado'
}

/**
 * Importa um candidato: busca o texto, baixa o PDF e grava em provimentos.
 * @return array ['ok'=>bool,'mensagem'=>string,'origem_texto'=>string]
 */
function syncImportar(PDO $conn, $itemId, $funcionario = null)
{
    $st = $conn->prepare(
        "SELECT i.*, f.adaptador, f.origem, f.nome AS fonte_nome
           FROM kb_sync_itens i JOIN kb_fontes f ON f.id = i.fonte_id
          WHERE i.id = :id");
    $st->execute(array(':id' => (int) $itemId));
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return array('ok' => false, 'mensagem' => 'Item não encontrado.');
    }

    try {
        $html = syncGet($item['url'], $http);
        if ($http !== 200) {
            throw new RuntimeException('Ficha retornou HTTP ' . $http);
        }
        $meta = ($item['adaptador'] === 'cnj') ? syncCnjFicha($html) : syncCgjmaFicha($html);
        if (!$meta) {
            throw new RuntimeException('Não consegui ler a ficha do ato.');
        }

        // ---- cadeia de extracao de texto ----
        $texto  = isset($meta['texto']) ? $meta['texto'] : '';
        $fonteTexto = 'html';
        $pdfLocal = null;
        $pdfRel   = null;
        $avisos   = array();

        if (!empty($meta['pdf'])) {
            $pdfRel = syncBaixarPdf($meta['pdf'], $item['origem'], $meta['tipo'],
                                    $meta['numero'], $meta['ano']);
            if ($pdfRel) {
                $pdfLocal = __DIR__ . DIRECTORY_SEPARATOR
                          . str_replace('/', DIRECTORY_SEPARATOR, $pdfRel);
            } else {
                $avisos[] = 'PDF nao pode ser baixado do portal';
            }
        } else {
            $avisos[] = 'nenhum link de PDF na ficha';
        }

        // pdftotext e local e gratuito: roda sempre e vence se trouxer mais
        // texto que a pagina. Na COGEX isso e a regra, porque a ficha traz so
        // a ementa -- o ato inteiro esta no PDF.
        if ($pdfLocal) {
            $t2 = syncPdfTexto($pdfLocal);
            if (mb_strlen($t2) > mb_strlen($texto)) {
                $texto = $t2;
                $fonteTexto = 'pdftotext';
            }
        }

        // Gemini so quando ainda falta texto: PDF digitalizado, sem camada
        // de texto, e onde o pdftotext devolve vazio. Custa dinheiro, entao
        // fica por ultimo.
        if (mb_strlen($texto) < 1500 && $pdfLocal && kbApiKey() !== '') {
            $t3 = syncPdfGemini($pdfLocal);
            if (mb_strlen($t3) > mb_strlen($texto)) {
                $texto = $t3;
                $fonteTexto = 'gemini';
                if (strpos($texto, 'TRANSCRICAO TRUNCADA') !== false) {
                    $avisos[] = 'transcricao truncada pelo limite do modelo';
                }
            }
        }
        if (mb_strlen($texto) < 1500 && $pdfLocal && kbApiKey() === '') {
            $avisos[] = 'texto curto e sem chave do Gemini para ler o PDF';
        }
        if (mb_strlen($texto) < 500) {
            throw new RuntimeException('Texto muito curto (' . mb_strlen($texto)
                . ' caracteres). Verifique manualmente.');
        }

        // Normaliza como o cadastro manual faz (linha unica).
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));

        // ---- grava ----
        $caminho = isset($pdfRel) ? $pdfRel : null;
        $conn->beginTransaction();

        if ($item['provimento_id']) {
            $up = $conn->prepare(
                "UPDATE provimentos SET conteudo_anexo = :c, descricao = COALESCE(:d, descricao),
                        caminho_anexo = COALESCE(:a, caminho_anexo)
                  WHERE id = :id");
            $up->execute(array(
                ':c' => $texto,
                ':d' => isset($meta['ementa']) ? $meta['ementa'] : null,
                ':a' => $caminho,
                ':id'=> $item['provimento_id'],
            ));
            $provId = (int) $item['provimento_id'];
        } else {
            $ins = $conn->prepare(
                "INSERT INTO provimentos
                    (numero_provimento, origem, descricao, data_provimento, caminho_anexo,
                     tipo, funcionario, data_cadastro, status, conteudo_anexo)
                 VALUES (:n,:o,:d,:dt,:a,:t,:f,NOW(),'Ativo',:c)");
            $ins->execute(array(
                ':n' => $meta['numero'],
                ':o' => $item['origem'],
                ':d' => isset($meta['ementa']) ? $meta['ementa'] : ('Importado de ' . $item['fonte_nome']),
                ':dt'=> $meta['data'],
                ':a' => (string) $caminho,   // coluna e NOT NULL no acervo
                ':t' => $meta['tipo'],
                ':f' => $funcionario,
                ':c' => $texto,
            ));
            $provId = (int) $conn->lastInsertId();
        }

        // Relacoes declaradas pelo portal: entram confirmadas.
        $rel = syncGravarRelacoes($conn, $provId, $meta, $item['origem']);

        $conn->prepare(
            "UPDATE kb_sync_itens SET status='importado', provimento_id=:p, origem_texto=:ot,
                    mensagem=:m, importado_em=NOW() WHERE id=:id")
             ->execute(array(':p' => $provId, ':ot' => $fonteTexto,
                             ':m' => $avisos ? 'Sem anexo: ' . implode('; ', $avisos) : null,
                             ':id' => $item['id']));

        $conn->commit();

        return array(
            'ok' => true,
            'provimento_id' => $provId,
            'origem_texto'  => $fonteTexto,
            'chars'         => mb_strlen($texto),
            'relacoes'      => $rel,
            'mensagem'      => 'Importado (' . number_format(mb_strlen($texto), 0, ',', '.')
                             . ' caracteres via ' . $fonteTexto . ')'
                             . ($avisos ? ' - ATENCAO: ' . implode('; ', $avisos) : '') . '.',
        );

    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $conn->prepare("UPDATE kb_sync_itens SET status='erro', mensagem=:m WHERE id=:id")
             ->execute(array(':m' => mb_substr($e->getMessage(), 0, 480), ':id' => (int) $itemId));
        return array('ok' => false, 'mensagem' => $e->getMessage());
    }
}

/**
 * Aponta buracos na numeracao: provimentos ausentes entre o menor e o maior
 * numero conhecido de cada origem/ano.
 *
 * Serve para responder "esta faltando alguma coisa?" sem depender de a
 * varredura ter alcancado tudo. A numeracao e sequencial por ano, entao o
 * buraco e evidencia direta.
 */
/**
 * Como cada origem numera seus provimentos.
 *
 * Isto e conhecimento de dominio, nao algo a deduzir dos dados: tentar
 * inferir comparando anos falhava assim que aparecia um registro com numero
 * baixo em ano recente. Novas origens caem no padrao anual.
 */
function syncPadraoNumeracao($origem)
{
    $o = mb_strtoupper((string) $origem, 'UTF-8');

    // CNJ: sequencia unica que atravessa os anos (209/2025, 211/2026).
    if (strpos($o, 'CNJ') !== false) {
        return 'continua';
    }
    // CGJ/MA e COGEX: a numeracao reinicia em 1 a cada ano (31/2025, 6/2026).
    return 'anual';
}

/**
 * Aponta buracos na numeracao dos provimentos.
 *
 * Numeracao anual  -> de 1 ate o maior daquele ano.
 * Numeracao continua -> do menor ao maior dentro da janela recente; comecar
 * do 1 listaria duas decadas de atos que nunca fizeram parte do acervo.
 */
function syncLacunas(PDO $conn, $anoMinimo = null)
{
    if ($anoMinimo === null) {
        $anoMinimo = (int) date('Y') - 2;
    }

    // Sem filtro de ano aqui: numa numeracao continua, o ato 166 pode ser de
    // 2023 e ainda assim cair dentro da faixa recente. Filtrar antes o fazia
    // constar como ausente mesmo estando no acervo.
    $st = $conn->prepare(
        "SELECT origem, YEAR(data_provimento) ano,
                CAST(numero_provimento AS UNSIGNED) num
           FROM provimentos
          WHERE status = 'Ativo' AND tipo = 'Provimento'
            AND numero_provimento REGEXP '^[0-9]+$'
          ORDER BY origem, ano, num");
    $st->execute();

    $porOrigem = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $porOrigem[$r['origem']][(int) $r['ano']][] = (int) $r['num'];
    }

    // O que o portal expoe, para separar "falta importar" de "nao existe".
    $noPortal = array();
    try {
        $sp = $conn->query(
            "SELECT f.origem, CAST(i.numero AS UNSIGNED) num, YEAR(i.data_ato) ano
               FROM kb_sync_itens i JOIN kb_fontes f ON f.id = i.fonte_id
              WHERE i.tipo = 'Provimento' AND i.numero REGEXP '^[0-9]+$'");
        foreach ($sp->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $noPortal[$r['origem']][(int) $r['ano']][(int) $r['num']] = true;
            $noPortal[$r['origem']]['todos'][(int) $r['num']] = true;
        }
    } catch (PDOException $e) { /* sincronizacao ainda nao rodou */ }

    $out = array();
    foreach ($porOrigem as $origem => $anos) {
        ksort($anos);

        if (syncPadraoNumeracao($origem) === 'anual') {
            foreach ($anos as $ano => $nums) {
                if ($ano < $anoMinimo || count($nums) < 2) { continue; }
                $vistos = isset($noPortal[$origem][$ano]) ? $noPortal[$origem][$ano] : array();
                $b = syncLacunaBloco($origem, (string) $ano, $nums, 1, max($nums), $vistos);
                if ($b) { $out[] = $b; }
            }
            continue;
        }

        // Continua: o conjunto do que existe usa TODOS os anos; so a faixa
        // examinada e limitada aos anos recentes.
        $todos = array();
        $recentes = array();
        foreach ($anos as $ano => $nums) {
            $todos = array_merge($todos, $nums);
            if ($ano >= $anoMinimo) { $recentes = array_merge($recentes, $nums); }
        }
        if (count($recentes) < 2) { continue; }

        $vistos = isset($noPortal[$origem]['todos']) ? $noPortal[$origem]['todos'] : array();
        $b = syncLacunaBloco($origem, $anoMinimo . ' a ' . date('Y') . ' (sequência contínua)',
                             $todos, min($recentes), max($recentes), $vistos);
        if ($b) { $out[] = $b; }
    }
    return $out;
}

/** Monta a linha do relatorio para uma faixa de numeracao. */
function syncLacunaBloco($origem, $rotuloAno, array $nums, $de, $ate, array $vistosNoPortal = array())
{
    $tem = array_flip($nums);
    $faltam = array();
    $importaveis = array();   // o portal tem, o acervo nao
    $inexistentes = array();  // nao aparecem nem no portal

    for ($n = $de; $n <= $ate; $n++) {
        if (isset($tem[$n])) { continue; }
        $faltam[] = $n;
        if (isset($vistosNoPortal[$n])) {
            $importaveis[] = $n;
        } else {
            $inexistentes[] = $n;
        }
    }
    if (!$faltam) { return null; }

    $resumo = count($faltam) . ' ausente(s) entre ' . $de . ' e ' . $ate;
    if ($vistosNoPortal) {
        $resumo .= $importaveis
            ? ' | NO PORTAL (da para importar): ' . implode(', ', array_slice($importaveis, 0, 40))
            : ' | nenhum deles aparece na listagem do portal';
        if ($inexistentes) {
            $resumo .= ' | fora da listagem: ' . implode(', ', array_slice($inexistentes, 0, 40))
                     . (count($inexistentes) > 40 ? ' ...' : '');
        }
    } else {
        $resumo .= ': ' . implode(', ', array_slice($faltam, 0, 40))
                 . (count($faltam) > 40 ? ' ...' : '');
    }

    return array(
        'origem'       => $origem,
        'ano'          => $rotuloAno,
        'maior'        => $ate,
        'tem'          => count($nums),
        'faltam'       => $faltam,
        'importaveis'  => $importaveis,
        'inexistentes' => $inexistentes,
        'resumo'       => $resumo,
    );
}

/**
 * Numeros ausentes de uma fonte, no formato que a busca dirigida consome.
 * @return array numeros faltantes
 */
function syncNumerosFaltantes(PDO $conn, $origem, $anoMinimo = null)
{
    $faltam = array();
    foreach (syncLacunas($conn, $anoMinimo) as $l) {
        if ($l['origem'] === $origem) {
            $faltam = array_merge($faltam, $l['faltam']);
        }
    }
    return array_values(array_unique($faltam));
}

/**
 * Procura especificamente os atos que faltam, em vez de varrer tudo.
 *
 * CGJ/MA: percorre as paginas da listagem, que sao poucas e trazem o numero
 * de cada ato -- basta chegar a pagina onde ele esta.
 *
 * CNJ: nao ha caminho de numero para endereco, entao varre o espaco de ids
 * pulando os que ja foram conferidos, e para assim que os numeros procurados
 * acabam. O ganho vem de nao repetir trabalho e de parar cedo.
 */
/**
 * Devolve a Pendentes os atos que o portal tem, o acervo nao, e que por algum
 * motivo ficaram marcados como ignorados ou com erro.
 *
 * Fecha o ciclo do relatorio de lacunas: de nada adianta saber que o 226 esta
 * no portal se ele nao aparece na fila de importacao.
 */
function syncReabrirLacunas(PDO $conn)
{
    $lacunas = syncLacunas($conn);
    $reabertos = 0;
    $detalhe = array();

    $upd = $conn->prepare(
        "UPDATE kb_sync_itens i
           JOIN kb_fontes f ON f.id = i.fonte_id
            SET i.status = 'novo', i.mensagem = NULL
          WHERE f.origem = :o AND i.tipo = 'Provimento'
            AND CAST(i.numero AS UNSIGNED) = :n
            AND i.status IN ('ignorado', 'erro')");

    foreach ($lacunas as $l) {
        foreach ($l['importaveis'] as $n) {
            $upd->execute(array(':o' => $l['origem'], ':n' => $n));
            if ($upd->rowCount() > 0) {
                $reabertos += $upd->rowCount();
                $detalhe[] = $l['origem'] . ' ' . $n;
            }
        }
    }

    // Quantos ja estavam pendentes e so precisam ser importados.
    $pendentes = 0;
    foreach ($lacunas as $l) {
        if (!$l['importaveis']) { continue; }
        $in = implode(',', array_map('intval', $l['importaveis']));
        $st = $conn->prepare(
            "SELECT COUNT(*) FROM kb_sync_itens i JOIN kb_fontes f ON f.id = i.fonte_id
              WHERE f.origem = :o AND i.tipo = 'Provimento'
                AND CAST(i.numero AS UNSIGNED) IN ({$in})
                AND i.status IN ('novo','atualizado')");
        $st->execute(array(':o' => $l['origem']));
        $pendentes += (int) $st->fetchColumn();
    }

    return array(
        'reabertos' => $reabertos,
        'pendentes' => $pendentes,
        'detalhe'   => array_slice($detalhe, 0, 30),
    );
}

/**
 * Procura os atos ausentes percorrendo a listagem da fonte.
 *
 * Com as duas fontes expondo listagem paginada, "buscar o que falta" virou
 * simplesmente percorrer as paginas ate encontrar. As 24 paginas do CNJ e as
 * 15 da COGEX cobrem o acervo inteiro -- nao ha mais o que interpolar.
 */
function syncBuscarLacunas(PDO $conn, array $fonte, $limite = 40)
{
    $faltam = syncNumerosFaltantes($conn, $fonte['origem']);
    if (!$faltam) {
        return array('achados' => 0, 'novos' => 0, 'faltam' => 0,
                     'ate_id' => 'nada faltando', 'concluido' => true);
    }

    $r = ($fonte['adaptador'] === 'cnj')
        ? syncCnjDescobrir($conn, $fonte, $limite, 'completo')
        : syncCgjmaDescobrir($conn, $fonte, $limite, 'completo');

    $restam = count(syncNumerosFaltantes($conn, $fonte['origem']));

    return array(
        'achados'   => $r['achados'],
        'novos'     => $r['novos'],
        'faltam'    => $restam,
        'ate_id'    => $r['ate_id'],
        'concluido' => !empty($r['concluido']) || $restam === 0,
    );
}

/**
 * Importa um ato a partir do endereco da ficha (ou so do numero do id, no
 * caso do CNJ). Saida de emergencia para o que a varredura nao alcancar --
 * no CNJ os ids recentes nao sao sequenciais e nem tudo esta linkado.
 */
function syncImportarPorUrl(PDO $conn, $entrada, $funcionario = null)
{
    $entrada = trim($entrada);
    if ($entrada === '') {
        return array('ok' => false, 'mensagem' => 'Informe o endereço ou o número do ato no portal.');
    }

    // So digitos: e um id do CNJ.
    if (ctype_digit($entrada)) {
        $entrada = 'https://atos.cnj.jus.br/atos/detalhar/' . $entrada;
    }
    if (!preg_match('#^https?://#i', $entrada)) {
        return array('ok' => false, 'mensagem' => 'Endereço inválido.');
    }

    // Descobre a fonte pelo dominio.
    $adaptador = (stripos($entrada, 'cnj.jus.br') !== false) ? 'cnj'
               : ((stripos($entrada, 'tjma.jus.br') !== false) ? 'cgjma' : null);
    if (!$adaptador) {
        return array('ok' => false,
            'mensagem' => 'Endereço não reconhecido. Use uma ficha do CNJ ou da CGJ/MA.');
    }

    $st = $conn->prepare("SELECT * FROM kb_fontes WHERE adaptador = :a");
    $st->execute(array(':a' => $adaptador));
    $fonte = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fonte) {
        return array('ok' => false, 'mensagem' => 'Fonte não cadastrada.');
    }

    try {
        $html = syncGet($entrada, $http);
        if ($http !== 200) {
            throw new RuntimeException('A ficha retornou HTTP ' . $http);
        }
        $meta = ($adaptador === 'cnj') ? syncCnjFicha($html) : syncCgjmaFicha($html);
        if (!$meta) {
            throw new RuntimeException('Não reconheci tipo, número e data nessa página.');
        }
        if (syncPrioridade($meta['tipo'], $adaptador) === false) {
            throw new RuntimeException($meta['tipo'] . ' não entra no acervo desta fonte.');
        }

        // id externo: ultimo trecho numerico da URL
        preg_match('#(\d+)(?:/[^/]*)?$#', rtrim($entrada, '/'), $mi);
        $idExt = isset($mi[1]) ? $mi[1] : substr(md5($entrada), 0, 12);

        syncRegistrarItem($conn, $fonte, $idExt, $entrada, $meta,
                          syncPrioridade($meta['tipo'], $adaptador));

        $st = $conn->prepare(
            "SELECT id FROM kb_sync_itens WHERE fonte_id = :f AND id_externo = :i");
        $st->execute(array(':f' => $fonte['id'], ':i' => $idExt));
        $itemId = (int) $st->fetchColumn();

        $r = syncImportar($conn, $itemId, $funcionario);
        $r['ato'] = $meta['tipo'] . ' ' . $meta['numero'] . '/' . $meta['ano'];
        return $r;

    } catch (Throwable $e) {
        return array('ok' => false, 'mensagem' => $e->getMessage());
    }
}

/**
 * Reconfere a situacao dos atos que ja conhecemos, para detectar revogacao
 * ou alteracao superveniente.
 *
 * E uma varredura de natureza oposta a busca de novidades: em vez de procurar
 * o que nao temos, revisita o que temos. Por isso vale separar -- misturar as
 * duas fazia toda verificacao pagar o custo das duas.
 *
 * Alcance: atos com ficha conhecida (importados pela sincronizacao). Os
 * cadastrados a mao nao tem URL de origem gravada e ficam de fora.
 */
function syncChecarAlteracoes(PDO $conn, $limite = 10)
{
    $st = $conn->prepare(
        "SELECT i.*, f.adaptador, f.origem
           FROM kb_sync_itens i
           JOIN kb_fontes f ON f.id = i.fonte_id
          WHERE i.status = 'importado' AND i.provimento_id IS NOT NULL
            AND f.ativo = 1
          ORDER BY COALESCE(i.rechecado_em, '2000-01-01'), i.ano DESC
          LIMIT :lim");
    $st->bindValue(':lim', (int) $limite, PDO::PARAM_INT);
    $st->execute();
    $itens = $st->fetchAll(PDO::FETCH_ASSOC);

    $mudou   = array();
    $marca   = $conn->prepare("UPDATE kb_sync_itens SET rechecado_em = NOW(),
                                      situacao = :s WHERE id = :id");
    $reabrir = $conn->prepare("UPDATE kb_sync_itens SET status = 'atualizado' WHERE id = :id");

    foreach ($itens as $it) {
        try {
            $html = syncGet($it['url'], $http);
            if ($http !== 200) { throw new RuntimeException('HTTP ' . $http); }
            $meta = ($it['adaptador'] === 'cnj') ? syncCnjFicha($html) : syncCgjmaFicha($html);
        } catch (Throwable $e) {
            $marca->execute(array(':s' => $it['situacao'], ':id' => $it['id']));
            continue;
        }
        if (!$meta) {
            $marca->execute(array(':s' => $it['situacao'], ':id' => $it['id']));
            continue;
        }

        $nova = isset($meta['situacao']) ? $meta['situacao'] : null;
        $marca->execute(array(':s' => $nova, ':id' => $it['id']));

        // Relacoes declaradas pelo portal continuam sendo registradas.
        if (!empty($meta['alteracoes'])) {
            syncGravarRelacoes($conn, (int) $it['provimento_id'], $meta, $it['origem']);
        }

        if ($nova !== null && $nova !== $it['situacao']) {
            $mudou[] = array(
                'ato'  => $it['tipo'] . ' ' . $it['numero'] . '/' . $it['ano'] . ' ' . $it['origem'],
                'de'   => $it['situacao'] ?: '(sem informacao)',
                'para' => $nova,
            );
            // Revogado ou alterado: o texto pode ter mudado, entao vale
            // reimportar. Volta para a fila como 'atualizado'.
            if (preg_match('/revogad|alterad|sem efeito/iu', $nova)) {
                $reabrir->execute(array(':id' => $it['id']));
            }
        }
    }

    $restam = (int) $conn->query(
        "SELECT COUNT(*) FROM kb_sync_itens i JOIN kb_fontes f ON f.id = i.fonte_id
          WHERE i.status = 'importado' AND f.ativo = 1
            AND (i.rechecado_em IS NULL OR i.rechecado_em < DATE_SUB(NOW(), INTERVAL 1 DAY))")
        ->fetchColumn();

    return array('conferidos' => count($itens), 'mudaram' => $mudou,
                 'restam' => $restam, 'concluido' => count($itens) === 0);
}

/**
 * Mostra o que a listagem da fonte devolve de fato.
 *
 * Existe porque "nao localiza" pode ser dezenas de coisas: bloqueio por
 * user-agent, redirecionamento para pagina de cookie, HTML diferente do
 * esperado. Sem ver a resposta, qualquer conserto e chute.
 */
function syncTestarListagem(PDO $conn, array $fonte, $pagina = 1)
{
    $d = array();
    $d['fonte'] = $fonte['nome'];

    $url = ($fonte['adaptador'] === 'cnj')
        ? sprintf('%s&page=%d', rtrim((string) $fonte['url_listagem'], '&'), $pagina)
        : (($pagina <= 1) ? $fonte['url_listagem']
            : preg_replace('#/pnao/provimentos-cogex$#',
                           '/pnao/' . $pagina . '/provimentos-cogex', $fonte['url_listagem']));
    $d['url'] = $url;

    if (!$url) {
        $d['erro'] = 'A fonte nao tem URL de listagem gravada. Clique em Reiniciar.';
        return $d;
    }

    try {
        $t0 = microtime(true);
        $html = syncGet($url, $http);
        $d['resposta'] = sprintf('HTTP %d, %d KB, %.1fs', $http, strlen($html) / 1024,
                                 microtime(true) - $t0);
    } catch (Throwable $e) {
        $d['resposta'] = 'FALHOU: ' . $e->getMessage();
        return $d;
    }
    if ($http !== 200) {
        $d['resultado'] = 'FALHOU: o portal nao devolveu a pagina';
        return $d;
    }

    $d['linhas_tr']  = preg_match_all('#<tr[^>]*>#i', $html) . ' tag(s) <tr>';
    $d['links_ato']  = preg_match_all('#\bdetalhar/\d+#i', $html) . ' referencia(s) a ficha do CNJ';
    $d['links_cogex']= preg_match_all('#/atos/extrajudicial/geral/\d+/5657#', $html)
                     . ' link(s) para ficha COGEX';

    $linhas = ($fonte['adaptador'] === 'cnj')
        ? syncCnjLinhasListagem($html)
        : array();
    if ($fonte['adaptador'] === 'cnj') {
        $d['linhas_lidas'] = count($linhas) . ' ato(s) interpretado(s)';
        if ($linhas) {
            $amostra = array();
            foreach (array_slice($linhas, 0, 4) as $l) {
                $amostra[] = $l['tipo'] . ' ' . $l['numero'] . '/' . $l['ano']
                           . ' (id ' . $l['id'] . ', ' . $l['situacao'] . ')';
            }
            $d['amostra'] = implode(' | ', $amostra);
        }
    } else {
        if (preg_match_all('#<a[^>]+href="([^"]*?/atos/extrajudicial/geral/(\d+)/5657/pnao[^"]*)"[^>]*>(.*?)</a>#is',
                           $html, $m, PREG_SET_ORDER)) {
            $ok = 0; $amostra = array();
            foreach ($m as $it) {
                $meta = syncCgjmaItemListagem($it[3]);
                if ($meta) {
                    $ok++;
                    if (count($amostra) < 4) {
                        $amostra[] = $meta['tipo'] . ' ' . $meta['numero'] . '/' . $meta['ano'];
                    }
                }
            }
            $d['linhas_lidas'] = $ok . ' ato(s) interpretado(s)';
            if ($amostra) { $d['amostra'] = implode(' | ', $amostra); }
        } else {
            $d['linhas_lidas'] = '0 ato(s) interpretado(s)';
        }
    }

    // Se nada foi lido, mostra o HTML em volta do primeiro link -- e ali que
    // esta a diferenca entre o que o portal manda e o que eu espero.
    if (strpos($d['linhas_lidas'], '0 ato') === 0) {
        // Mostra a segunda linha da tabela inteira: e nela que esta a diferenca
        // entre o que o portal emite e o que o leitor espera.
        if (preg_match_all('#<tr[^>]*>.*?</tr>#is', $html, $trs) && count($trs[0]) > 1) {
            $d['html_linha_2'] = mb_substr(preg_replace('/\s+/', ' ', $trs[0][1]), 0, 1200);
            if (isset($trs[0][2])) {
                $d['html_linha_3'] = mb_substr(preg_replace('/\s+/', ' ', $trs[0][2]), 0, 800);
            }
            $d['resultado'] = 'A tabela chegou, mas nao consegui interpretar as linhas.';
        } else {
            $d['html_inicio'] = mb_substr(preg_replace('/\s+/', ' ', strip_tags($html)), 0, 400);
            $d['resultado'] = 'A pagina nao traz tabela de resultados. '
                            . 'Provavel bloqueio, redirecionamento ou aviso de cookie.';
        }
    } else {
        $d['resultado'] = 'Listagem lida corretamente.';
    }
    return $d;
}

/**
 * Conta passo a passo o que acontece ao buscar o anexo de um item.
 * Serve para transformar "nao baixou" em uma causa concreta.
 */
function syncTestarAnexo(PDO $conn, $itemId)
{
    $d = array();
    $st = $conn->prepare(
        "SELECT i.*, f.adaptador, f.origem FROM kb_sync_itens i
           JOIN kb_fontes f ON f.id = i.fonte_id WHERE i.id = :id");
    $st->execute(array(':id' => (int) $itemId));
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return array('erro' => 'Item nao encontrado.');
    }
    $d['ato'] = $item['tipo'] . ' ' . $item['numero'] . '/' . $item['ano'];
    $d['url_ficha'] = $item['url'];

    try {
        $t0 = microtime(true);
        $html = syncGet($item['url'], $http);
        $d['ficha'] = sprintf('HTTP %d, %d KB, %.1fs', $http, strlen($html) / 1024,
                              microtime(true) - $t0);
    } catch (Throwable $e) {
        $d['ficha'] = 'FALHOU: ' . $e->getMessage();
        return $d;
    }

    $meta = ($item['adaptador'] === 'cnj') ? syncCnjFicha($html) : syncCgjmaFicha($html);
    if (!$meta) {
        $d['leitura_ficha'] = 'FALHOU: nao reconheci tipo/numero/data na pagina';
        return $d;
    }
    $d['leitura_ficha'] = 'ok (' . $meta['tipo'] . ' ' . $meta['numero'] . '/' . $meta['ano'] . ')';

    if (!empty($meta['pdf_candidatos'])) {
        foreach ($meta['pdf_candidatos'] as $k => $u) {
            $d['link_' . $k] = $u;
        }
    }
    if (empty($meta['pdf'])) {
        $d['link_escolhido'] = 'NENHUM link de PDF encontrado na ficha';
        return $d;
    }
    $d['link_escolhido'] = $meta['pdf'];

    try {
        $t0 = microtime(true);
        $bytes = syncGet($meta['pdf'], $h2);
        $d['download'] = sprintf('HTTP %d, %d KB, %.1fs, comeca com "%s"',
            $h2, strlen($bytes) / 1024, microtime(true) - $t0,
            htmlspecialchars(substr($bytes, 0, 8), ENT_QUOTES, 'UTF-8'));
        if ($h2 !== 200) {
            $d['resultado'] = 'FALHOU: o servidor recusou o arquivo';
            return $d;
        }
        if (strncmp($bytes, '%PDF', 4) !== 0) {
            $d['resultado'] = 'FALHOU: o conteudo baixado nao e um PDF';
            return $d;
        }
    } catch (Throwable $e) {
        $d['download'] = 'FALHOU: ' . $e->getMessage();
        return $d;
    }

    $pastaOrigem = str_replace(array('/', '\\', ' '), '_', $item['origem']);
    $rel = 'anexo/' . $pastaOrigem . '/' . $meta['tipo'] . '/' . (int) $meta['ano']
         . '/' . $meta['numero'] . '.pdf';
    $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $d['destino'] = $rel;

    $dir = dirname($abs);
    if (!is_dir($dir)) {
        $d['pasta'] = @mkdir($dir, 0775, true) ? 'criada agora' : 'NAO CONSEGUI CRIAR: ' . $dir;
    } else {
        $d['pasta'] = is_writable($dir) ? 'existe e e gravavel' : 'SEM PERMISSAO DE ESCRITA: ' . $dir;
    }
    if (is_dir($dir) && is_writable($dir)) {
        $d['resultado'] = (file_put_contents($abs, $bytes) !== false)
            ? 'GRAVADO (' . round(strlen($bytes) / 1024) . ' KB)'
            : 'FALHOU ao gravar o arquivo';
        if (strpos($d['resultado'], 'GRAVADO') === 0 && $item['provimento_id']) {
            $conn->prepare("UPDATE provimentos SET caminho_anexo = :a WHERE id = :id")
                 ->execute(array(':a' => $rel, ':id' => $item['provimento_id']));
            $d['banco'] = 'caminho_anexo atualizado';
        }
    }
    return $d;
}

/**
 * Rebaixa apenas o PDF de um item ja importado, sem mexer no texto.
 */
function syncReanexar(PDO $conn, $itemId)
{
    $st = $conn->prepare(
        "SELECT i.*, f.adaptador, f.origem FROM kb_sync_itens i
           JOIN kb_fontes f ON f.id = i.fonte_id
          WHERE i.id = :id");
    $st->execute(array(':id' => (int) $itemId));
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item || !$item['provimento_id']) {
        return array('ok' => false, 'mensagem' => 'Item sem provimento vinculado.');
    }

    try {
        $html = syncGet($item['url'], $http);
        if ($http !== 200) {
            throw new RuntimeException('Ficha retornou HTTP ' . $http);
        }
        $meta = ($item['adaptador'] === 'cnj') ? syncCnjFicha($html) : syncCgjmaFicha($html);
        if (!$meta || empty($meta['pdf'])) {
            throw new RuntimeException($item['tipo'] . ' ' . $item['numero'] . '/'
                . $item['ano'] . ': ficha sem link de PDF.');
        }

        $rel = syncBaixarPdf($meta['pdf'], $item['origem'], $meta['tipo'],
                             $meta['numero'], $meta['ano']);
        if (!$rel) {
            throw new RuntimeException('Download do PDF falhou.');
        }

        $conn->prepare("UPDATE provimentos SET caminho_anexo = :a WHERE id = :id")
             ->execute(array(':a' => $rel, ':id' => $item['provimento_id']));
        $conn->prepare("UPDATE kb_sync_itens SET mensagem = NULL WHERE id = :id")
             ->execute(array(':id' => $item['id']));

        return array('ok' => true, 'caminho' => $rel);
    } catch (Throwable $e) {
        $conn->prepare("UPDATE kb_sync_itens SET mensagem = :m WHERE id = :id")
             ->execute(array(':m' => mb_substr('Anexo: ' . $e->getMessage(), 0, 480),
                             ':id' => (int) $itemId));
        return array('ok' => false, 'mensagem' => $e->getMessage());
    }
}

/**
 * Relacoes vindas do portal entram como 'confirmada': quem afirma e a fonte
 * oficial. So a extracao por IA (kbExtrairRelacoes) fica como 'sugerida'.
 */
function syncGravarRelacoes(PDO $conn, $provId, array $meta, $origem)
{
    if (empty($meta['alteracoes'])) {
        return 0;
    }
    $busca = $conn->prepare(
        "SELECT id FROM provimentos
          WHERE numero_provimento = :n AND origem = :o AND YEAR(data_provimento) = :a LIMIT 1");
    $ins = $conn->prepare(
        "INSERT IGNORE INTO kb_relacoes
            (origem_id, destino_id, destino_texto, tipo, dispositivos, trecho,
             status, confirmado_por, confirmado_em, criado_em)
         VALUES (:o,:d,:dt,'altera',NULL,:tr,'confirmada','portal',NOW(),NOW())");

    $n = 0;
    foreach ($meta['alteracoes'] as $alt) {
        $busca->execute(array(':n' => $alt['numero'], ':o' => $origem, ':a' => $alt['ano']));
        $altId = $busca->fetchColumn();
        if (!$altId) { continue; }   // o alterador ainda nao esta no acervo

        // origem = quem altera; destino = este ato
        $ins->execute(array(
            ':o'  => (int) $altId,
            ':d'  => $provId,
            ':dt' => $alt['numero'] . '/' . $alt['ano'] . ' ' . $origem,
            ':tr' => 'Relação declarada na ficha oficial do ato.',
        ));
        $n++;
    }
    return $n;
}

// ===========================================================================
// PDF
// ===========================================================================
/**
 * Baixa o PDF no mesmo esquema de pastas do cadastro manual.
 * @return string|null caminho relativo gravado em provimentos.caminho_anexo
 */
function syncBaixarPdf($url, $origem, $tipo, $numero, $ano)
{
    $bytes = syncGet($url, $http);
    if ($http !== 200 || strncmp($bytes, '%PDF', 4) !== 0) {
        return null;
    }

    // 'CGJ/MA' -> 'CGJ_MA'; 'Resolução' -> 'Resolução' (mantido, como no acervo)
    $pastaOrigem = str_replace(array('/', '\\', ' '), '_', $origem);
    $rel = 'anexo/' . $pastaOrigem . '/' . $tipo . '/' . (int) $ano . '/' . $numero . '.pdf';

    $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($abs);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Nao consegui criar a pasta ' . $dir);
    }
    if (file_put_contents($abs, $bytes) === false) {
        throw new RuntimeException('Sem permissao de escrita em ' . $dir);
    }
    return $rel;
}

/** Extracao local via Poppler, se disponivel. */
function syncPdfTexto($caminho)
{
    foreach (array('pdftotext', 'C:\\Program Files\\poppler\\bin\\pdftotext.exe') as $bin) {
        $saida = @shell_exec(escapeshellarg($bin) . ' -enc UTF-8 -layout '
               . escapeshellarg($caminho) . ' - 2>&1');
        if ($saida && mb_strlen(trim($saida)) > 200 && stripos($saida, 'not recognized') === false) {
            return trim($saida);
        }
    }
    return '';
}

/**
 * Ultimo recurso: Gemini lendo o PDF. Serve para digitalizado sem camada de
 * texto, que e onde pdftotext devolve vazio.
 */
function syncPdfGemini($caminho)
{
    $bytes = @file_get_contents($caminho);
    if (!$bytes || strlen($bytes) > 18 * 1024 * 1024) {
        return '';
    }

    $url = KB_GEMINI_BASE . '/models/' . kbModelo('chat') . ':generateContent?key=' . kbApiKey();
    $resp = kbHttpPost($url, array(
        'systemInstruction' => array('parts' => array(array('text' =>
            "Transcreva o ato normativo do documento, integralmente e sem resumir.\n"
            . "- Preserve numeracao de artigos, paragrafos e incisos exatamente como estao.\n"
            . "- Nao adicione comentarios, titulos ou marcacao.\n"
            . "- Ignore cabecalhos, rodapes e numeros de pagina.\n"
            . "- Devolva apenas o texto do ato."))),
        'contents' => array(array('role' => 'user', 'parts' => array(
            array('inline_data' => array('mime_type' => 'application/pdf',
                                         'data' => base64_encode($bytes))),
            array('text' => 'Transcreva o texto integral deste ato normativo.'),
        ))),
        'generationConfig' => array('temperature' => 0.0, 'maxOutputTokens' => 60000),
    ), 2);

    $txt = '';
    if (isset($resp['candidates'][0]['content']['parts'])) {
        foreach ($resp['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) { $txt .= $p['text']; }
        }
    }

    // Ato muito longo (um Codigo de Normas passa de 60 mil tokens) sai
    // truncado. Melhor registrar do que gravar meio texto sem aviso.
    if (isset($resp['candidates'][0]['finishReason'])
        && $resp['candidates'][0]['finishReason'] === 'MAX_TOKENS') {
        error_log('[sync] transcricao truncada em ' . mb_strlen($txt) . ' caracteres');
        $txt .= "\n\n[TRANSCRICAO TRUNCADA: o ato excedeu o limite do modelo. "
              . "Consulte o PDF anexo para o texto completo.]";
    }
    return trim($txt);
}
