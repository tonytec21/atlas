<?php
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();
arq_compat_avisar('update_category.php');
$cats = arq_categorias();
$i = isset($_POST['id']) ? (int) $_POST['id'] : -1;
$_POST['antigo'] = isset($cats[$i]) ? $cats[$i] : '';
$_POST['nome']   = isset($_POST['category']) ? $_POST['category'] : '';
$_POST['acao']   = 'renomear';
$_POST['_csrf']  = arq_csrf_token();
$_REQUEST['acao'] = 'renomear';
require __DIR__ . '/api/categorias.php';
