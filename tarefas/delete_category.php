<?php
/**
 * Atlas · Tarefas — exclusão de categoria ou origem.
 *
 * A exclusão não toca nas tarefas já cadastradas: elas mantêm o id gravado e
 * apenas deixam de exibir o rótulo. A tela avisa o usuário sobre isso antes
 * de confirmar.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if (!usuario_ve_tudo()) {
    echo 'Acesso restrito aos administradores.';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Método inválido.';
    exit;
}

$tabela = (entrada('tipo', 'categoria', $_POST) === 'origem') ? 'origem' : 'categorias';
$id     = entrada_int('id', 0, $_POST);

if ($id <= 0) {
    echo 'Registro não informado.';
    exit;
}

try {
    db_exec("DELETE FROM `$tabela` WHERE id = ?", array($id));
    echo ($tabela === 'origem' ? 'Origem' : 'Categoria') . ' excluída com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] delete_category: ' . $e->getMessage());
    echo 'Erro ao excluir o registro.';
}
