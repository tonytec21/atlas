<?php
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();
arq_compat_avisar('save_category.php');
$_POST['acao'] = 'criar';
$_POST['nome'] = isset($_POST['category']) ? $_POST['category'] : (isset($_POST['nome']) ? $_POST['nome'] : '');
$_POST['_csrf'] = arq_csrf_token();
$_REQUEST['acao'] = 'criar';
require __DIR__ . '/api/categorias.php';
