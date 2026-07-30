<?php
/**
 * atlas/kb/conversar.php
 * Endpoint do chat. Substitui o consultar.php de pergunta unica.
 *
 * Acoes: enviar | historico | listar | nova | excluir | avaliar
 */

include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
require_once __DIR__ . '/lib_kb.php';
require_once __DIR__ . '/schema_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
kbBlindarJson();
date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(180);

$conn = getDatabaseConnection();
$quem = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');

try {
    if (!kbSchemaExiste($conn)) {
        kbGarantirSchema($conn);
    }

    switch ($acao) {
        case 'enviar':   responder(enviar($conn, $quem));   break;
        case 'historico':responder(historico($conn, $quem));break;
        case 'listar':   responder(listar($conn, $quem));   break;
        case 'nova':     responder(array('conversa_id' => null)); break;
        case 'excluir':  responder(excluir($conn, $quem));  break;
        case 'avaliar':  responder(avaliar($conn));         break;
        default:
            responder(array('ok' => false, 'mensagem' => 'Ação desconhecida.'));
    }
} catch (Throwable $e) {
    error_log('[kb/conversar] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('ok' => false, 'mensagem' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

// ---------------------------------------------------------------------------

function responder($d)
{
    if (!isset($d['ok'])) {
        $d['ok'] = true;
    }
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Confere que a conversa pertence a quem esta pedindo. */
function conversaDo(PDO $conn, $id, $quem)
{
    $st = $conn->prepare("SELECT * FROM kb_conversas WHERE id = :id");
    $st->execute(array(':id' => (int) $id));
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        return null;
    }
    if ($c['funcionario'] !== null && $quem !== null && $c['funcionario'] !== $quem) {
        return null;
    }
    return $c;
}

function enviar(PDO $conn, $quem)
{
    $mensagem = trim(isset($_POST['mensagem']) ? $_POST['mensagem'] : '');
    if (mb_strlen($mensagem) < 2) {
        return array('ok' => false, 'mensagem' => 'Escreva sua pergunta.');
    }
    if (mb_strlen($mensagem) > 4000) {
        $mensagem = mb_substr($mensagem, 0, 4000);
    }

    $conversaId = isset($_POST['conversa_id']) ? (int) $_POST['conversa_id'] : 0;
    $filtros = array(
        'origem'  => isset($_POST['origem'])  ? trim($_POST['origem'])  : '',
        'tipo'    => isset($_POST['tipo'])    ? trim($_POST['tipo'])    : '',
        'ano_min' => isset($_POST['ano_min']) ? (int) $_POST['ano_min'] : 0,
    );

    $t0 = microtime(true);

    // --- conversa ---
    if ($conversaId && conversaDo($conn, $conversaId, $quem)) {
        $conn->prepare("UPDATE kb_conversas SET atualizado_em = NOW() WHERE id = :id")
             ->execute(array(':id' => $conversaId));
    } else {
        $st = $conn->prepare(
            "INSERT INTO kb_conversas (funcionario, titulo, criado_em, atualizado_em)
             VALUES (:f, :t, NOW(), NOW())"
        );
        $st->execute(array(':f' => $quem, ':t' => kbTituloConversa($mensagem)));
        $conversaId = (int) $conn->lastInsertId();
    }

    // --- historico ---
    $st = $conn->prepare(
        "SELECT papel, conteudo, fontes FROM kb_mensagens
          WHERE conversa_id = :c ORDER BY id LIMIT 20"
    );
    $st->execute(array(':c' => $conversaId));
    $hist = $st->fetchAll(PDO::FETCH_ASSOC);

    // --- decide se busca, e o que busca ---
    $plano = kbCondensar($hist, $mensagem);

    $trechos = array();
    if ($plano['buscar']) {
        $trechos = kbBuscar($conn, $plano['consulta'], KB_TOP_K, $filtros);
    } else {
        // Reaproveita as fontes do ultimo turno: o pedido e de reformatacao.
        for ($i = count($hist) - 1; $i >= 0; $i--) {
            if ($hist[$i]['papel'] === 'assistant' && $hist[$i]['fontes']) {
                $anteriores = json_decode($hist[$i]['fontes'], true);
                if (is_array($anteriores)) {
                    $trechos = reidratar($conn, $anteriores);
                }
                break;
            }
        }
    }

    // --- grava a mensagem do usuario ---
    $ins = $conn->prepare(
        "INSERT INTO kb_mensagens (conversa_id, papel, conteudo, criado_em)
         VALUES (:c, 'user', :m, NOW())"
    );
    $ins->execute(array(':c' => $conversaId, ':m' => $mensagem));

    // --- gera ---
    $resposta = kbResponderChat($hist, $mensagem, $trechos);
    $ms = (int) round((microtime(true) - $t0) * 1000);

    $fontes = formatarFontes($trechos);

    $ins = $conn->prepare(
        "INSERT INTO kb_mensagens (conversa_id, papel, conteudo, fontes, busca_usada, ms_total, criado_em)
         VALUES (:c, 'assistant', :m, :f, :b, :ms, NOW())"
    );
    $ins->execute(array(
        ':c'  => $conversaId,
        ':m'  => $resposta,
        ':f'  => $fontes ? json_encode($fontes, JSON_UNESCAPED_UNICODE) : null,
        ':b'  => $plano['buscar'] ? mb_substr($plano['consulta'], 0, 480) : null,
        ':ms' => $ms,
    ));

    return array(
        'conversa_id' => $conversaId,
        'mensagem_id' => (int) $conn->lastInsertId(),
        'resposta'    => $resposta,
        'fontes'      => $fontes,
        'buscou'      => (bool) $plano['buscar'],
        'consulta'    => $plano['buscar'] ? $plano['consulta'] : null,
        'ms'          => $ms,
    );
}

/** Recarrega os trechos citados no turno anterior, a partir dos ids. */
function reidratar(PDO $conn, array $fontes)
{
    $ids = array();
    foreach ($fontes as $f) {
        if (!empty($f['id'])) {
            $ids[] = (int) $f['id'];
        }
    }
    if (!$ids) {
        return array();
    }
    $in = implode(',', array_map('intval', $ids));
    return $conn->query(
        "SELECT c.id, c.referencia, c.conteudo, c.situacao,
                p.numero_provimento, p.origem, p.tipo, p.data_provimento,
                p.descricao, p.caminho_anexo
           FROM kb_chunks c JOIN provimentos p ON p.id = c.provimento_id
          WHERE c.id IN ({$in})
          ORDER BY FIELD(c.id, {$in})"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function formatarFontes(array $trechos)
{
    $out = array();
    foreach ($trechos as $i => $t) {
        $ano = date('Y', strtotime($t['data_provimento']));
        $out[] = array(
            'n'          => $i + 1,
            'id'         => (int) $t['id'],
            'provimento' => $t['tipo'] . ' n. ' . $t['numero_provimento'] . '/' . $ano,
            'origem'     => $t['origem'],
            'referencia' => $t['referencia'],
            'data'       => date('d/m/Y', strtotime($t['data_provimento'])),
            'situacao'   => isset($t['situacao']) ? $t['situacao'] : 'vigente',
            // Lei federal guarda a URL do Planalto; provimento guarda caminho
            // relativo. Concatenar sempre quebraria o link das leis.
            'anexo'      => (strpos((string) $t['caminho_anexo'], 'http') === 0)
                            ? $t['caminho_anexo']
                            : '../provimentos/' . $t['caminho_anexo'],
            'trecho'     => mb_substr($t['conteudo'], 0, 700)
                          . (mb_strlen($t['conteudo']) > 700 ? '...' : ''),
        );
    }
    return $out;
}

function historico(PDO $conn, $quem)
{
    $id = isset($_POST['conversa_id']) ? (int) $_POST['conversa_id'] : 0;
    if (!conversaDo($conn, $id, $quem)) {
        return array('ok' => false, 'mensagem' => 'Conversa não encontrada.');
    }
    $st = $conn->prepare(
        "SELECT id, papel, conteudo, fontes, util, criado_em
           FROM kb_mensagens WHERE conversa_id = :c ORDER BY id"
    );
    $st->execute(array(':c' => $id));

    $msgs = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $msgs[] = array(
            'id'       => (int) $m['id'],
            'papel'    => $m['papel'],
            'conteudo' => $m['conteudo'],
            'fontes'   => $m['fontes'] ? json_decode($m['fontes'], true) : array(),
            'util'     => $m['util'] === null ? null : (int) $m['util'],
        );
    }
    return array('conversa_id' => $id, 'mensagens' => $msgs);
}

function listar(PDO $conn, $quem)
{
    $st = $conn->prepare(
        "SELECT c.id, c.titulo, c.atualizado_em, COUNT(m.id) AS n
           FROM kb_conversas c
           LEFT JOIN kb_mensagens m ON m.conversa_id = c.id
          WHERE (:f IS NULL OR c.funcionario = :f2)
          GROUP BY c.id
         HAVING n > 0
          ORDER BY c.atualizado_em DESC LIMIT 40"
    );
    $st->execute(array(':f' => $quem, ':f2' => $quem));

    $out = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $out[] = array(
            'id'     => (int) $c['id'],
            'titulo' => $c['titulo'],
            'quando' => date('d/m/Y H:i', strtotime($c['atualizado_em'])),
        );
    }
    return array('conversas' => $out);
}

function excluir(PDO $conn, $quem)
{
    $id = isset($_POST['conversa_id']) ? (int) $_POST['conversa_id'] : 0;
    if (!conversaDo($conn, $id, $quem)) {
        return array('ok' => false, 'mensagem' => 'Conversa não encontrada.');
    }
    $conn->prepare("DELETE FROM kb_conversas WHERE id = :id")->execute(array(':id' => $id));
    return array('mensagem' => 'Conversa excluída.');
}

function avaliar(PDO $conn)
{
    $id   = isset($_POST['mensagem_id']) ? (int) $_POST['mensagem_id'] : 0;
    $util = isset($_POST['util']) ? (int) $_POST['util'] : null;
    if (!$id || ($util !== 0 && $util !== 1)) {
        return array('ok' => false);
    }
    $conn->prepare("UPDATE kb_mensagens SET util = :u WHERE id = :id")
         ->execute(array(':u' => $util, ':id' => $id));
    return array('mensagem' => 'Obrigado pelo retorno.');
}
