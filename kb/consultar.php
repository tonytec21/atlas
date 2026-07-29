<?php
/**
 * atlas/kb/consultar.php
 * Endpoint AJAX: recebe a pergunta, busca os trechos e devolve a resposta.
 */

include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
require_once __DIR__ . '/lib_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
kbBlindarJson();
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Metodo nao permitido.'));
    exit;
}

$pergunta = trim(isset($_POST['pergunta']) ? $_POST['pergunta'] : '');
if (mb_strlen($pergunta) < 5) {
    echo json_encode(array('success' => false, 'message' => 'Escreva uma pergunta com pelo menos 5 caracteres.'));
    exit;
}
if (mb_strlen($pergunta) > 1000) {
    $pergunta = mb_substr($pergunta, 0, 1000);
}

$filtros = array(
    'origem'  => isset($_POST['origem'])  ? trim($_POST['origem'])  : '',
    'tipo'    => isset($_POST['tipo'])    ? trim($_POST['tipo'])    : '',
    'ano_min' => isset($_POST['ano_min']) ? (int) $_POST['ano_min'] : 0,
);
$somenteBusca = !empty($_POST['somente_busca']);

try {
    $conn = getDatabaseConnection();

    $t0 = microtime(true);
    $trechos = kbBuscar($conn, $pergunta, KB_TOP_K, $filtros);
    $msBusca = (int) round((microtime(true) - $t0) * 1000);

    // Monta as fontes para a tela (sem o texto completo, que vai no prompt).
    $fontes = array();
    foreach ($trechos as $i => $t) {
        $ano = date('Y', strtotime($t['data_provimento']));
        $fontes[] = array(
            'n'          => $i + 1,
            'id'         => (int) $t['id'],
            'provimento' => $t['tipo'] . ' n. ' . $t['numero_provimento'] . '/' . $ano,
            'origem'     => $t['origem'],
            'referencia' => $t['referencia'],
            'data'       => date('d/m/Y', strtotime($t['data_provimento'])),
            'descricao'  => $t['descricao'],
            'anexo'      => '../provimentos/' . $t['caminho_anexo'],
            'trecho'     => mb_substr($t['conteudo'], 0, 600)
                          . (mb_strlen($t['conteudo']) > 600 ? '...' : ''),
            'situacao'   => isset($t['situacao']) ? $t['situacao'] : 'vigente',
            'score'      => round($t['score'], 5),
        );
    }

    $resposta = null;
    $msGeracao = 0;
    if (!$somenteBusca) {
        $t1 = microtime(true);
        $resposta = kbGerarResposta($pergunta, $trechos);
        $msGeracao = (int) round((microtime(true) - $t1) * 1000);
    }

    // Log (auditoria e material para medir qualidade depois).
    $ids = array();
    foreach ($trechos as $t) {
        $ids[] = $t['id'];
    }
    $log = $conn->prepare(
        "INSERT INTO kb_consultas (funcionario, pergunta, chunks_ids, resposta, ms_busca, ms_geracao, criado_em)
         VALUES (:f, :p, :c, :r, :mb, :mg, NOW())"
    );
    $log->execute(array(
        ':f'  => isset($_SESSION['username']) ? $_SESSION['username'] : null,
        ':p'  => $pergunta,
        ':c'  => implode(',', $ids),
        ':r'  => $resposta,
        ':mb' => $msBusca,
        ':mg' => $msGeracao,
    ));

    echo json_encode(array(
        'success'    => true,
        'consulta_id'=> (int) $conn->lastInsertId(),
        'resposta'   => $resposta,
        'fontes'     => $fontes,
        'ms_busca'   => $msBusca,
        'ms_geracao' => $msGeracao,
    ), JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[kb/consultar] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Nao foi possivel concluir a consulta. Detalhe: ' . $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE);
}
