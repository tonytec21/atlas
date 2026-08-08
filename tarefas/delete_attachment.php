<?php
/**
 * Atlas · Tarefas — compatibilidade: exclusão de anexo da tarefa.
 *
 * Correção de segurança: a versão anterior montava o caminho do arquivo a
 * partir do que vinha no POST e chamava unlink() direto, o que permitia
 * apagar arquivos fora da pasta de anexos. Agora o caminho é normalizado e
 * conferido contra a pasta arquivos/ antes de qualquer remoção.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Método inválido.';
    exit;
}

$u        = usuario_atual();
$tarefaId = entrada_int('taskId', 0, $_POST);
$alvo     = entrada('file', '', $_POST);

$t = db_one('SELECT * FROM tarefas WHERE id = ?', array($tarefaId));
if (!$t) {
    echo 'Tarefa não encontrada.';
    exit;
}
if (!usuario_ve_tudo()
    && $t['funcionario_responsavel'] !== $u['nome']
    && $t['criado_por'] !== $u['usuario']) {
    echo 'Sem permissão para remover anexos desta tarefa.';
    exit;
}

$restantes = array();
$removido  = null;
foreach (preg_split('/[;\r\n]+/', (string) $t['caminho_anexo']) as $p) {
    $p = trim($p);
    if ($p === '') { continue; }
    if ($p === $alvo || basename(str_replace('\\', '/', $p)) === basename(str_replace('\\', '/', $alvo))) {
        $removido = $p;
        continue;
    }
    $restantes[] = $p;
}

if ($removido === null) {
    echo 'Anexo não encontrado nesta tarefa.';
    exit;
}

db_exec('UPDATE tarefas SET caminho_anexo = ? WHERE id = ?',
    array(implode(';', $restantes), $tarefaId));

$lista = anexos_lista($removido);
if ($lista) {
    $abs  = realpath(TAREFAS_DIR . '/' . $lista[0]['rel']);
    $base = realpath(TAREFAS_DIR_ARQUIVOS);
    if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}

registrar_historico($tarefaId, 'anexo', 'Anexo removido: ' . basename($removido));
echo 'Anexo excluído com sucesso.';
