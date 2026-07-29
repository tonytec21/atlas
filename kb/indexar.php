<?php
/**
 * atlas/kb/indexar.php
 * Worker AJAX da indexacao. Processa UM lote por requisicao e devolve o
 * progresso -- o navegador chama em sequencia ate concluir.
 *
 * Assim nunca esbarra em max_execution_time, mostra progresso real e pode
 * ser interrompido a qualquer momento sem corromper nada.
 *
 * Acoes: status | iniciar | lote | parar
 */

include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
require_once __DIR__ . '/lib_kb.php';
require_once __DIR__ . '/schema_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
kbBlindarJson();
date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(120);

$conn  = getDatabaseConnection();
$acao  = isset($_POST['acao']) ? $_POST['acao'] : 'status';
$token = isset($_POST['token']) ? preg_replace('/[^a-f0-9]/', '', $_POST['token']) : '';
$quem  = isset($_SESSION['username']) ? $_SESSION['username'] : 'desconhecido';

// Segundos sem heartbeat para considerar a trava abandonada (aba fechada).
define('KB_TRAVA_TTL', 90);

try {
    if (!kbSchemaExiste($conn)) {
        kbGarantirSchema($conn);
    }

    switch ($acao) {
        case 'iniciar': responder(iniciar($conn, $quem)); break;
        case 'lote':    responder(lote($conn, $token));   break;
        case 'parar':   responder(parar($conn, $token));  break;
        default:        responder(status($conn));
    }
} catch (Throwable $e) {
    error_log('[kb/indexar] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

// ---------------------------------------------------------------------------

function responder($dados)
{
    $dados['success'] = isset($dados['success']) ? $dados['success'] : true;
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Numeros do acervo e do que ja foi indexado. */
function status(PDO $conn)
{
    $docs = $conn->query("
        SELECT COUNT(*) total,
               SUM(conteudo_anexo IS NOT NULL AND CHAR_LENGTH(conteudo_anexo) >= 500) aproveitaveis
          FROM provimentos WHERE status = 'Ativo'")->fetch(PDO::FETCH_ASSOC);

    $ch = $conn->query("
        SELECT COUNT(*) total,
               SUM(embedding IS NOT NULL) com_vetor,
               COUNT(DISTINCT provimento_id) docs
          FROM kb_chunks")->fetch(PDO::FETCH_ASSOC);

    // Hash e o gatilho: pega provimento novo E provimento editado. A data_cadastro
    // nao muda quando o texto e corrigido, entao ela nao servia.
    $pendentesChunk = (int) $conn->query("
        SELECT COUNT(*) FROM provimentos p
         WHERE p.status='Ativo' AND CHAR_LENGTH(p.conteudo_anexo) >= 500
           AND (p.kb_hash IS NULL OR p.kb_hash <> MD5(p.conteudo_anexo))")->fetchColumn();

    $pendentesRel = (int) $conn->query("
        SELECT COUNT(*) FROM provimentos p
         WHERE p.status='Ativo' AND CHAR_LENGTH(p.conteudo_anexo) >= 500
           AND p.kb_relacoes_em IS NULL")->fetchColumn();

    $relSugeridas = (int) $conn->query(
        "SELECT COUNT(*) FROM kb_relacoes WHERE status = 'sugerida'")->fetchColumn();

    $pendentesEmbed = (int) $conn->query(
        "SELECT COUNT(*) FROM kb_chunks WHERE embedding IS NULL")->fetchColumn();

    $trava = $conn->query("SELECT * FROM kb_indexacao WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $ocupado = travaAtiva($trava);

    return array(
        'docs_total'       => (int) $docs['total'],
        'docs_aproveitaveis' => (int) $docs['aproveitaveis'],
        'docs_indexados'   => (int) $ch['docs'],
        'chunks_total'     => (int) $ch['total'],
        'chunks_com_vetor' => (int) $ch['com_vetor'],
        'pendentes_chunk'  => $pendentesChunk,
        'pendentes_rel'    => $pendentesRel,
        'rel_sugeridas'    => $relSugeridas,
        'pendentes_embed'  => $pendentesEmbed,
        'custo_estimado'   => round($pendentesEmbed * 0.00004, 2),
        'tem_chave'        => kbApiKey() !== '',
        'ocupado'          => $ocupado,
        'trava'            => $ocupado ? array(
            'funcionario' => $trava['funcionario'],
            'fase'        => $trava['fase'],
            'processados' => (int) $trava['processados'],
            'total'       => (int) $trava['total'],
        ) : null,
    );
}

function travaAtiva($trava)
{
    if (!$trava || $trava['status'] !== 'rodando') {
        return false;
    }
    $idade = time() - strtotime($trava['atualizado_em']);
    return $idade < KB_TRAVA_TTL; // trava velha = aba fechada, libera sozinha
}

/** Assume a trava e calcula o total de trabalho. */
function iniciar(PDO $conn, $quem)
{
    $trava = $conn->query("SELECT * FROM kb_indexacao WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (travaAtiva($trava)) {
        return array(
            'success' => false,
            'message' => 'Já existe uma indexação em andamento, iniciada por '
                       . $trava['funcionario'] . '. Aguarde a conclusão.',
        );
    }

    $meuToken = md5(uniqid((string) mt_rand(), true));
    $st = $conn->prepare(
        "UPDATE kb_indexacao
            SET status='rodando', fase='chunk', token=:t, funcionario=:f,
                processados=0, total=0, mensagem=NULL,
                iniciado_em=NOW(), atualizado_em=NOW()
          WHERE id=1"
    );
    $st->execute(array(':t' => $meuToken, ':f' => $quem));

    $s = status($conn);
    return array(
        'token'  => $meuToken,
        'status' => $s,
    );
}

/** Processa um lote. Decide sozinho a fase: primeiro chunk, depois embed. */
function lote(PDO $conn, $token)
{
    $trava = $conn->query("SELECT * FROM kb_indexacao WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$trava || $trava['token'] !== $token || $trava['status'] !== 'rodando') {
        return array('success' => false, 'message' => 'Indexação interrompida ou assumida por outro usuário.', 'parar' => true);
    }
    heartbeat($conn, $token);

    // --- Fase 1: chunking ---
    $docs = $conn->query("
        SELECT id, conteudo_anexo FROM provimentos
         WHERE status='Ativo' AND CHAR_LENGTH(conteudo_anexo) >= 500
           AND (kb_hash IS NULL OR kb_hash <> MD5(conteudo_anexo))
         ORDER BY id LIMIT " . (int) KB_LOTE_CHUNK)->fetchAll(PDO::FETCH_ASSOC);

    if ($docs) {
        $criados = processarChunk($conn, $docs);
        avancar($conn, $token, 'chunk', count($docs));
        return array(
            'fase'      => 'chunk',
            'feitos'    => count($docs),
            'criados'   => $criados,
            'concluido' => false,
            'status'    => status($conn),
        );
    }

    // --- Fase 2: embeddings ---
    if (kbApiKey() === '') {
        finalizar($conn, $token, 'Chave da API não configurada. Trechos gerados, mas sem embeddings.');
        return array('fase' => 'embed', 'concluido' => true, 'aviso' => true,
                     'message' => 'Trechos gerados. Configure a chave da API para habilitar a busca semântica.',
                     'status' => status($conn));
    }

    $chunks = $conn->query("
        SELECT id, conteudo FROM kb_chunks WHERE embedding IS NULL
         ORDER BY id LIMIT " . (int) KB_LOTE_EMBED)->fetchAll(PDO::FETCH_ASSOC);

    if (!$chunks) {
        // --- Fase 3: relacoes entre normas ---
        $docsRel = $conn->query("
            SELECT id, conteudo_anexo FROM provimentos
             WHERE status='Ativo' AND CHAR_LENGTH(conteudo_anexo) >= 500
               AND kb_relacoes_em IS NULL
             ORDER BY data_provimento DESC LIMIT " . (int) KB_LOTE_RELACOES)
            ->fetchAll(PDO::FETCH_ASSOC);

        if ($docsRel) {
            $sugeridas = 0;
            $marcaRel = $conn->prepare("UPDATE provimentos SET kb_relacoes_em = NOW() WHERE id = ?");
            foreach ($docsRel as $d) {
                try {
                    $sugeridas += kbExtrairRelacoes($conn, $d['id'], $d['conteudo_anexo']);
                } catch (Throwable $e) {
                    error_log('[kb/relacoes] doc ' . $d['id'] . ': ' . $e->getMessage());
                }
                $marcaRel->execute(array($d['id'])); // marca mesmo se falhou: nao trava a fila
            }
            avancar($conn, $token, 'relacoes', count($docsRel));
            return array(
                'fase'      => 'relacoes',
                'feitos'    => count($docsRel),
                'sugeridas' => $sugeridas,
                'concluido' => false,
                'status'    => status($conn),
            );
        }

        finalizar($conn, $token, null);
        return array('fase' => 'embed', 'concluido' => true, 'status' => status($conn));
    }

    $textos = array();
    foreach ($chunks as $c) {
        $textos[] = $c['conteudo'];
    }

    try {
        $vetores = kbEmbed($textos, 'RETRIEVAL_DOCUMENT');
    } catch (Throwable $e) {
        // Nao finaliza: mantem a trava para o usuario poder retomar.
        return array('success' => false, 'fase' => 'embed',
                     'message' => 'Falha na API: ' . $e->getMessage()
                                . ' Clique em Retomar para continuar de onde parou.',
                     'status' => status($conn));
    }

    $upd = $conn->prepare("UPDATE kb_chunks SET embedding=:e, dim=:d, indexado_em=NOW() WHERE id=:id");
    $conn->beginTransaction();
    foreach ($chunks as $i => $c) {
        if (isset($vetores[$i])) {
            $upd->execute(array(':e' => kbQuantizar($vetores[$i]), ':d' => kbEmbedDim(), ':id' => $c['id']));
        }
    }
    $conn->commit();

    avancar($conn, $token, 'embed', count($chunks));
    return array(
        'fase'      => 'embed',
        'feitos'    => count($chunks),
        'concluido' => false,
        'status'    => status($conn),
    );
}

function processarChunk(PDO $conn, array $docs)
{
    $ins = $conn->prepare(
        "INSERT INTO kb_chunks (provimento_id, ordem, referencia, conteudo, hash_conteudo)
         VALUES (:pid,:ordem,:ref,:cont,:hash)
         ON DUPLICATE KEY UPDATE
            referencia    = VALUES(referencia),
            conteudo      = VALUES(conteudo),
            embedding     = IF(hash_conteudo = VALUES(hash_conteudo), embedding, NULL),
            hash_conteudo = VALUES(hash_conteudo)"
    );
    $del   = $conn->prepare("DELETE FROM kb_chunks WHERE provimento_id=? AND ordem>=?");
    $marca = $conn->prepare(
        "UPDATE provimentos SET kb_indexado_em = NOW(), kb_hash = MD5(conteudo_anexo) WHERE id = ?");

    $total = 0;
    foreach ($docs as $d) {
        $chunks = kbChunk($d['conteudo_anexo']);
        $conn->beginTransaction();
        try {
            foreach ($chunks as $c) {
                $ins->execute(array(
                    ':pid' => $d['id'], ':ordem' => $c['ordem'], ':ref' => $c['referencia'],
                    ':cont' => $c['conteudo'], ':hash' => $c['hash'],
                ));
            }
            $del->execute(array($d['id'], count($chunks)));
            $marca->execute(array($d['id']));
            $conn->commit();
            $total += count($chunks);
        } catch (Throwable $e) {
            $conn->rollBack();
            error_log('[kb/indexar] doc ' . $d['id'] . ': ' . $e->getMessage());
        }
    }
    return $total;
}

function heartbeat(PDO $conn, $token)
{
    $st = $conn->prepare("UPDATE kb_indexacao SET atualizado_em=NOW() WHERE id=1 AND token=:t");
    $st->execute(array(':t' => $token));
}

function avancar(PDO $conn, $token, $fase, $qtd)
{
    $st = $conn->prepare(
        "UPDATE kb_indexacao SET fase=:f, processados=processados+:q, atualizado_em=NOW()
          WHERE id=1 AND token=:t"
    );
    $st->execute(array(':f' => $fase, ':q' => (int) $qtd, ':t' => $token));
}

function finalizar(PDO $conn, $token, $mensagem)
{
    $st = $conn->prepare(
        "UPDATE kb_indexacao SET status='ocioso', fase=NULL, token=NULL,
                mensagem=:m, atualizado_em=NOW()
          WHERE id=1 AND token=:t"
    );
    $st->execute(array(':m' => $mensagem, ':t' => $token));
}

function parar(PDO $conn, $token)
{
    finalizar($conn, $token, 'Interrompida pelo usuário.');
    return array('status' => status($conn));
}
