<?php
/**
 * atlas/kb/ingerir.php  --  somente CLI
 *
 *   php ingerir.php --chunk           gera os trechos (rapido, offline, sem API)
 *   php ingerir.php --embed           gera os embeddings pendentes (usa a API)
 *   php ingerir.php --chunk --embed   faz os dois
 *   php ingerir.php --status          mostra a situacao
 *   php ingerir.php --exportar        gera kb_dump.sql.gz para deploy
 *
 * Opcoes: --id=123 (um documento)  --limite=500  --forcar (reprocessa tudo)
 *
 * As duas fases sao retomaveis: se cair no meio, rode de novo que ele
 * continua de onde parou. O controle e por hash do conteudo.
 */

if (php_sapi_name() !== 'cli') {
    die("Este script roda apenas via linha de comando.\n");
}

require_once __DIR__ . '/lib_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

@set_time_limit(0);
ini_set('memory_limit', '512M');

$opts    = getopt('', array('chunk', 'embed', 'status', 'exportar', 'forcar', 'id::', 'limite::'));
$conn    = getDatabaseConnection();
$idAlvo  = isset($opts['id']) ? (int) $opts['id'] : null;
$limite  = isset($opts['limite']) ? (int) $opts['limite'] : 0;
$forcar  = isset($opts['forcar']);

if (isset($opts['status']) || empty($opts)) {
    mostrarStatus($conn);
    exit(0);
}
if (isset($opts['chunk']))    { faseChunk($conn, $idAlvo, $limite, $forcar); }
if (isset($opts['embed']))    { faseEmbed($conn, $limite); }
if (isset($opts['exportar'])) { exportar($conn); }

mostrarStatus($conn);

// ---------------------------------------------------------------------------

function faseChunk(PDO $conn, $idAlvo, $limite, $forcar)
{
    echo "\n=== Fase 1: chunking ===\n";

    $sql = "SELECT id, numero_provimento, origem, tipo, conteudo_anexo
              FROM provimentos
             WHERE status = 'Ativo'
               AND conteudo_anexo IS NOT NULL
               AND CHAR_LENGTH(conteudo_anexo) >= 500";
    if ($idAlvo) {
        $sql .= " AND id = " . (int) $idAlvo;
    } elseif (!$forcar) {
        $sql .= " AND (kb_indexado_em IS NULL OR kb_indexado_em < data_cadastro)";
    }
    $sql .= " ORDER BY id";
    if ($limite) {
        $sql .= " LIMIT " . (int) $limite;
    }

    $docs = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $total = count($docs);
    if (!$total) {
        echo "Nada pendente.\n";
        return;
    }
    echo "Documentos a processar: {$total}\n";

    $insere = $conn->prepare(
        "INSERT INTO kb_chunks (provimento_id, ordem, referencia, conteudo, hash_conteudo)
         VALUES (:pid, :ordem, :ref, :cont, :hash)
         ON DUPLICATE KEY UPDATE
            referencia    = VALUES(referencia),
            conteudo      = VALUES(conteudo),
            embedding     = IF(hash_conteudo = VALUES(hash_conteudo), embedding, NULL),
            hash_conteudo = VALUES(hash_conteudo)"
    );
    $limpa  = $conn->prepare("DELETE FROM kb_chunks WHERE provimento_id = ? AND ordem >= ?");
    $marca  = $conn->prepare("UPDATE provimentos SET kb_indexado_em = NOW() WHERE id = ?");

    $somaChunks = 0;
    $i = 0;
    foreach ($docs as $d) {
        $chunks = kbChunk($d['conteudo_anexo']);
        $conn->beginTransaction();
        try {
            foreach ($chunks as $c) {
                $insere->execute(array(
                    ':pid'   => $d['id'],
                    ':ordem' => $c['ordem'],
                    ':ref'   => $c['referencia'],
                    ':cont'  => $c['conteudo'],
                    ':hash'  => $c['hash'],
                ));
            }
            // Remove sobras se o documento encolheu numa reindexacao.
            $limpa->execute(array($d['id'], count($chunks)));
            $marca->execute(array($d['id']));
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            echo "\n[ERRO] doc {$d['id']} ({$d['numero_provimento']}): " . $e->getMessage() . "\n";
            continue;
        }

        $somaChunks += count($chunks);
        $i++;
        if ($i % 25 === 0 || $i === $total) {
            printf("\r  %d/%d documentos | %d trechos", $i, $total, $somaChunks);
        }
    }
    printf("\nMedia: %.1f trechos por documento.\n", $total ? $somaChunks / $total : 0);
}

function faseEmbed(PDO $conn, $limite)
{
    echo "\n=== Fase 2: embeddings ===\n";
    if (kbApiKey() === '') {
        die("GEMINI_API_KEY nao configurada. Veja config_kb.php.\n");
    }

    $sqlCount = "SELECT COUNT(*) FROM kb_chunks WHERE embedding IS NULL";
    $pendentes = (int) $conn->query($sqlCount)->fetchColumn();
    if (!$pendentes) {
        echo "Nenhum embedding pendente.\n";
        return;
    }
    if ($limite && $limite < $pendentes) {
        $pendentes = $limite;
    }

    $lotes = (int) ceil($pendentes / KB_LOTE_EMBED);
    echo "Trechos pendentes: {$pendentes} (em {$lotes} lotes de " . KB_LOTE_EMBED . ")\n";
    echo "Custo estimado: US$ " . number_format($pendentes * 0.00004, 2) . "\n\n";

    $atualiza = $conn->prepare(
        "UPDATE kb_chunks SET embedding = :emb, dim = :dim, indexado_em = NOW() WHERE id = :id"
    );

    $feitos = 0;
    $inicio = microtime(true);

    while ($feitos < $pendentes) {
        $lote = $conn->query(
            "SELECT id, conteudo FROM kb_chunks WHERE embedding IS NULL
             ORDER BY id LIMIT " . (int) KB_LOTE_EMBED
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lote)) {
            break;
        }

        $textos = array();
        foreach ($lote as $r) {
            $textos[] = $r['conteudo'];
        }

        try {
            $vetores = kbEmbed($textos, 'RETRIEVAL_DOCUMENT');
        } catch (Exception $e) {
            echo "\n[ERRO] lote falhou: " . $e->getMessage() . "\n";
            echo "Rode o comando de novo para retomar deste ponto.\n";
            return;
        }

        $conn->beginTransaction();
        foreach ($lote as $idx => $r) {
            if (!isset($vetores[$idx])) {
                continue;
            }
            $atualiza->execute(array(
                ':emb' => kbQuantizar($vetores[$idx]),
                ':dim' => kbEmbedDim(),
                ':id'  => $r['id'],
            ));
        }
        $conn->commit();

        $feitos += count($lote);
        $seg = microtime(true) - $inicio;
        $rest = $feitos ? ($seg / $feitos) * ($pendentes - $feitos) : 0;
        printf("\r  %d/%d trechos | %.1f/s | restam ~%dmin",
            $feitos, $pendentes, $feitos / max($seg, 0.001), (int) ceil($rest / 60));

        usleep(200000); // respiro para nao estourar quota por minuto
    }
    echo "\nConcluido.\n";
}

function exportar(PDO $conn)
{
    echo "\n=== Exportando dump ===\n";
    $arq = __DIR__ . '/kb_dump.sql';

    $fh = fopen($arq, 'w');
    fwrite($fh, "-- Base de conhecimento Aria | gerado em " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nTRUNCATE TABLE kb_chunks;\n");

    $st = $conn->query("SELECT * FROM kb_chunks ORDER BY id");
    $n = 0;
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $emb = $r['embedding'] === null ? 'NULL' : "0x" . bin2hex($r['embedding']);
        fwrite($fh, sprintf(
            "INSERT INTO kb_chunks (id,provimento_id,ordem,referencia,conteudo,embedding,dim,hash_conteudo,indexado_em) VALUES (%d,%d,%d,%s,%s,%s,%s,%s,%s);\n",
            $r['id'], $r['provimento_id'], $r['ordem'],
            $r['referencia'] === null ? 'NULL' : $conn->quote($r['referencia']),
            $conn->quote($r['conteudo']),
            $emb,
            $r['dim'] === null ? 'NULL' : (int) $r['dim'],
            $conn->quote($r['hash_conteudo']),
            $r['indexado_em'] === null ? 'NULL' : $conn->quote($r['indexado_em'])
        ));
        $n++;
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);

    // Comprime: BLOB em hex comprime muito bem.
    $gz = gzopen($arq . '.gz', 'wb9');
    $in = fopen($arq, 'rb');
    while (!feof($in)) {
        gzwrite($gz, fread($in, 262144));
    }
    fclose($in);
    gzclose($gz);
    unlink($arq);

    printf("%d trechos -> kb_dump.sql.gz (%.1f MB)\n", $n, filesize($arq . '.gz') / 1048576);
    echo "Hash: " . md5_file($arq . '.gz') . "\n";
}

function mostrarStatus(PDO $conn)
{
    echo "\n=== Situacao ===\n";
    $r = $conn->query("
        SELECT COUNT(*) total,
               SUM(embedding IS NOT NULL) com_vetor,
               COUNT(DISTINCT provimento_id) docs,
               ROUND(AVG(CHAR_LENGTH(conteudo))) media_chars
          FROM kb_chunks")->fetch(PDO::FETCH_ASSOC);

    printf("Trechos ............: %s\n", number_format($r['total'], 0, ',', '.'));
    printf("Com embedding ......: %s (%.1f%%)\n",
        number_format($r['com_vetor'], 0, ',', '.'),
        $r['total'] ? $r['com_vetor'] / $r['total'] * 100 : 0);
    printf("Documentos cobertos.: %s\n", number_format($r['docs'], 0, ',', '.'));
    printf("Tamanho medio ......: %s caracteres\n", $r['media_chars']);

    $falta = $conn->query("
        SELECT COUNT(*) FROM provimentos p
         WHERE p.status='Ativo' AND CHAR_LENGTH(p.conteudo_anexo) >= 500
           AND NOT EXISTS (SELECT 1 FROM kb_chunks c WHERE c.provimento_id = p.id)
    ")->fetchColumn();
    printf("Documentos sem trechos: %s\n\n", $falta);
}
