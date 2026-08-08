<?php
/**
 * Atlas · Tarefas — dados de uma tarefa para o modal de visualização.
 *
 * Este endpoint é consumido por JSON.parse() no index.php. Qualquer aviso,
 * deprecation ou erro fatal do PHP impresso antes da resposta contamina o
 * corpo e quebra o parse no navegador com a mensagem
 * "Unexpected token '<', "<br /> <b>"... is not valid JSON".
 *
 * Por isso aqui: erros nunca vão para a tela, a saída é limpa antes de
 * imprimir, e toda falha vira um JSON com a chave "error" — assim o
 * front-end recebe algo que consegue interpretar e mostrar.
 */

ini_set('display_errors', '0');   // nada de HTML de erro no meio do JSON
ini_set('log_errors', '1');
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Resposta única e limpa. */
function tarefa_responder($dados, $codigo = 200)
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Fatal vira JSON, não HTML. */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode([
            'error'   => 'Erro interno ao carregar a tarefa.',
            'detalhe' => $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')',
        ], JSON_UNESCAPED_UNICODE);
    }
});

ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tarefa_responder(['error' => 'Método não permitido.'], 405);
}

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
if ($token === '') {
    tarefa_responder(['error' => 'Token da tarefa não informado.'], 400);
}

/**
 * Executa um SELECT preparado devolvendo o resultado, ou null se a tabela
 * não existir / a query falhar. Era aqui que quebrava: quando o prepare()
 * falha, o mysqli devolve false, e chamar bind_param() em false é fatal no
 * PHP 8 — o fatal saía como HTML e contaminava o JSON.
 */
function tarefa_consultar($conn, $sql, $tipos = '', $valores = [])
{
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log('[tarefas] view_task: prepare falhou — ' . $conn->error . ' | SQL: ' . $sql);
            return null;
        }
        if ($tipos !== '') {
            $stmt->bind_param($tipos, ...$valores);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $linhas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $linhas;
    } catch (Throwable $e) {
        // A partir do PHP 8.1 o mysqli LANÇA mysqli_sql_exception em vez de
        // devolver false. Sem este catch, uma tabela ausente virava erro fatal
        // impresso como HTML — a origem do "Unexpected token '<'".
        error_log('[tarefas] view_task: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return null;
    }
}

/* ---------- Tarefa ---------- */
$linhas = tarefa_consultar(
    $conn,
    'SELECT t.*, c.titulo AS categoria_titulo, o.titulo AS origem_titulo
       FROM tarefas t
       LEFT JOIN categorias c ON t.categoria = c.id
       LEFT JOIN origem o ON t.origem = o.id
      WHERE t.token = ?',
    's',
    [$token]
);

if ($linhas === null) {
    tarefa_responder(['error' => 'Não foi possível consultar a tarefa.'], 500);
}
if (!$linhas) {
    tarefa_responder([]);   // contrato antigo: array vazio quando não encontra
}

$task   = $linhas[0];
$taskId = (int) $task['id'];

/* ---------- Comentários ---------- */
$comentarios = tarefa_consultar(
    $conn,
    'SELECT * FROM comentarios WHERE hash_tarefa = ? OR id_tarefa_principal = ?',
    'si',
    [$token, $taskId]
);

$task['comentarios'] = [];
if (is_array($comentarios)) {
    foreach ($comentarios as $c) {
        // Marca de onde veio o comentário: da tarefa principal ou de uma subtarefa.
        $c['is_subtask'] = isset($c['id_tarefa_principal']) && (int) $c['id_tarefa_principal'] === $taskId;
        $task['comentarios'][] = $c;
    }
}

/* ---------- Recibo e guia ----------
   Se essas tabelas não existirem no banco, o módulo segue funcionando com
   os campos em false, em vez de derrubar a tela inteira. */
$recibo = tarefa_consultar($conn, 'SELECT id FROM recibos_de_entrega WHERE task_id = ?', 'i', [$taskId]);
$task['recibo_gerado'] = is_array($recibo) && count($recibo) > 0;

$guia = tarefa_consultar($conn, 'SELECT id FROM guia_de_recebimento WHERE task_id = ?', 'i', [$taskId]);
$task['guia_gerada'] = is_array($guia) && count($guia) > 0;

$conn->close();

tarefa_responder($task);
