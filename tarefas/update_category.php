<?php
/** Atlas · Tarefas — alteração de categoria ou origem. */

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
$titulo = entrada('titulo', '', $_POST);

if ($id <= 0 || $titulo === '') {
    echo 'Informe o registro e o título.';
    exit;
}

try {
    if (isset($_POST['status'])) {
        $status = (strtolower(entrada('status', 'Ativo', $_POST)) === 'inativo') ? 'Inativo' : 'Ativo';
        db_exec("UPDATE `$tabela` SET titulo = ?, status = ? WHERE id = ?", array($titulo, $status, $id));
    } else {
        db_exec("UPDATE `$tabela` SET titulo = ? WHERE id = ?", array($titulo, $id));
    }
    echo ($tabela === 'origem' ? 'Origem' : 'Categoria') . ' atualizada com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] update_category: ' . $e->getMessage());
    echo 'Erro ao atualizar o registro.';
}
