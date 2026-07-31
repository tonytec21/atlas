<?php
/** Legado: move para a lixeira. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();
arq_compat_avisar('delete_ato.php');
$_POST['acao'] = 'excluir';
$_POST['_csrf'] = arq_csrf_token();
$_REQUEST['acao'] = 'excluir';
require __DIR__ . '/api/lixeira.php';
