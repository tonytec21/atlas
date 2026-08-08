<?php
/**
 * Atlas · Tarefas — compatibilidade: exclusão de anexo de comentário.
 *
 * Mesma correção de caminho aplicada em delete_attachment.php, mais a
 * verificação de autoria do comentário.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Método inválido.';
    exit;
}

$u    = usuario_atual();
$id   = entrada_int('commentId', 0, $_POST);
$alvo = entrada('file', '', $_POST);

$c = db_one('SELECT * FROM comentarios WHERE id = ?', array($id));
if (!$c) {
    echo 'Comentário não encontrado.';
    exit;
}
if (!usuario_ve_tudo() && $c['funcionario'] !== $u['usuario']) {
    echo 'Você só pode alterar os próprios comentários.';
    exit;
}

$restantes = array();
$removido  = null;
foreach (preg_split('/[;\r\n]+/', (string) $c['caminho_anexo']) as $p) {
    $p = trim($p);
    if ($p === '') { continue; }
    if ($p === $alvo || basename(str_replace('\\', '/', $p)) === basename(str_replace('\\', '/', $alvo))) {
        $removido = $p;
        continue;
    }
    $restantes[] = $p;
}

db_exec('UPDATE comentarios SET caminho_anexo = ? WHERE id = ?',
    array(implode(';', $restantes), $id));

if ($removido !== null) {
    $lista = anexos_lista($removido);
    if ($lista) {
        $abs  = realpath(TAREFAS_DIR . '/' . $lista[0]['rel']);
        $base = realpath(TAREFAS_DIR_ARQUIVOS);
        if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
            @unlink($abs);
        }
    }
}

echo 'Anexo de comentário excluído com sucesso.';
