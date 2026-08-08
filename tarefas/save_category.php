<?php
/**
 * Atlas · Tarefas — cadastro de categoria ou origem.
 *
 * Compatibilidade: sem o parâmetro `tipo`, grava em `categorias`, como fazia
 * a versão anterior. Com `tipo=origem`, grava na tabela `origem`. O campo
 * `status` passou a ser aceito; quando ausente, entra como 'Ativo'.
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
$titulo = entrada('titulo', '', $_POST);
$status = (strtolower(entrada('status', 'Ativo', $_POST)) === 'inativo') ? 'Inativo' : 'Ativo';

if ($titulo === '') {
    echo 'Informe o título.';
    exit;
}

try {
    db_exec("INSERT INTO `$tabela` (titulo, status) VALUES (?, ?)", array($titulo, $status));
    echo ($tabela === 'origem' ? 'Origem' : 'Categoria') . ' salva com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] save_category: ' . $e->getMessage());
    echo 'Erro ao salvar o registro.';
}
