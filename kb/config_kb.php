<?php
/**
 * atlas/kb/config_kb.php
 * Parametros da base de conhecimento.
 *
 * A chave da API deve ficar FORA do controle de versao. Em producao,
 * defina a variavel de ambiente GEMINI_API_KEY (httpd.conf: SetEnv) ou
 * crie config_kb.local.php ao lado deste arquivo -- ja no .gitignore.
 */

/**
 * Devolve a chave da API. Ordem de prioridade:
 *   1. variavel de ambiente GEMINI_API_KEY
 *   2. tabela kb_config (definida pela tela Configurar)
 *   3. arquivo config_kb.local.php
 */
function kbApiKey()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $env = getenv('GEMINI_API_KEY');
    if ($env) {
        return $cache = trim($env);
    }

    try {
        if (!function_exists('getDatabaseConnection')) {
            require_once __DIR__ . '/../provimentos/db_connection.php';
        }
        $conn = getDatabaseConnection();
        $st = $conn->prepare("SELECT valor FROM kb_config WHERE chave = 'gemini_api_key'");
        $st->execute();
        $v = $st->fetchColumn();
        if ($v) {
            return $cache = trim($v);
        }
    } catch (Exception $e) {
        // tabela ainda nao existe ou banco indisponivel: segue para o arquivo
    }

    if (is_file(__DIR__ . '/config_kb.local.php')) {
        $v = include __DIR__ . '/config_kb.local.php';
        if (is_string($v) && trim($v) !== '') {
            return $cache = trim($v);
        }
    }

    return $cache = '';
}

/** Grava a chave na tabela kb_config. */
function kbSalvarApiKey(PDO $conn, $chave, $funcionario = null)
{
    $st = $conn->prepare(
        "INSERT INTO kb_config (chave, valor, funcionario, atualizado_em)
         VALUES ('gemini_api_key', :v, :f, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor),
                                 funcionario = VALUES(funcionario),
                                 atualizado_em = NOW()"
    );
    return $st->execute(array(':v' => trim($chave), ':f' => $funcionario));
}

define('KB_GEMINI_BASE', 'https://generativelanguage.googleapis.com/v1beta');

// Padroes de fabrica. O que vale de verdade e a tabela kb_modelos, editavel
// pela tela Configuracoes. Estes valores so entram se a tabela estiver vazia
// ou inacessivel.
define('KB_EMBED_MODEL_PADRAO', 'gemini-embedding-001');
define('KB_CHAT_MODEL_PADRAO',  'gemini-3.1-flash-lite');
define('KB_EMBED_DIM_PADRAO',   768);

/**
 * Modelo ativo de um tipo ('chat' ou 'embedding').
 * Resolve pela tabela kb_modelos; cai no padrao se nao houver nada.
 */
function kbModelo($tipo)
{
    $m = kbModeloAtivo($tipo);
    if ($m) {
        return $m['nome'];
    }
    return $tipo === 'chat' ? KB_CHAT_MODEL_PADRAO : KB_EMBED_MODEL_PADRAO;
}

/** Dimensao do modelo de embedding ativo. */
function kbEmbedDim()
{
    $m = kbModeloAtivo('embedding');
    return ($m && $m['dimensao']) ? (int) $m['dimensao'] : KB_EMBED_DIM_PADRAO;
}

/** Linha completa do modelo ativo, com cache por requisicao. */
function kbModeloAtivo($tipo)
{
    static $cache = array();
    if (array_key_exists($tipo, $cache)) {
        return $cache[$tipo];
    }
    try {
        if (!function_exists('getDatabaseConnection')) {
            require_once __DIR__ . '/../provimentos/db_connection.php';
        }
        $conn = getDatabaseConnection();
        $st = $conn->prepare("SELECT * FROM kb_modelos WHERE tipo = :t AND ativo = 1 LIMIT 1");
        $st->execute(array(':t' => $tipo));
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $cache[$tipo] = ($r ?: null);
    } catch (Exception $e) {
        return $cache[$tipo] = null;
    }
}

// ---------------------------------------------------------------------------
// Chunking
// ---------------------------------------------------------------------------
define('KB_CHUNK_MIN', 200);   // abaixo disso, funde no trecho anterior
define('KB_CHUNK_MAX', 3000);  // ~750 tokens, folgado no limite de 2048 da API

// ---------------------------------------------------------------------------
// Busca
// ---------------------------------------------------------------------------
define('KB_CANDIDATOS',    300);  // quantos o FULLTEXT entrega para o rerank
define('KB_TOP_K',           8);  // quantos trechos vao para o prompt
define('KB_RRF_K',          60);  // constante padrao do Reciprocal Rank Fusion
define('KB_PESO_RECENCIA', 0.15); // 0 desliga o desempate por vigencia

// ---------------------------------------------------------------------------
// Ingestao
// ---------------------------------------------------------------------------
define('KB_LOTE_CHUNK',  40);  // documentos por requisicao AJAX no chunking
define('KB_LOTE_RELACOES', 3);   // documentos por requisicao na extracao de relacoes
define('KB_LOTE_EMBED', 100);  // limite da API e 250; 100 da margem de seguranca
define('KB_TIMEOUT',    120);
