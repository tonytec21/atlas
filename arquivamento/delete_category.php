<?php
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();
arq_compat_avisar('delete_category.php');
$cats = arq_categorias();
$i = isset($_POST['id']) ? (int) $_POST['id'] : -1;
$_POST['nome']  = isset($cats[$i]) ? $cats[$i] : (isset($_POST['nome']) ? $_POST['nome'] : '');
$_POST['acao']  = 'excluir';
$_POST['_csrf'] = arq_csrf_token();
$_REQUEST['acao'] = 'excluir';
require __DIR__ . '/api/categorias.php';
