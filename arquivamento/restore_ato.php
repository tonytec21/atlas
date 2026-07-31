<?php
/** Legado: restaura da lixeira. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();
arq_compat_avisar('restore_ato.php');
$_POST['acao'] = 'restaurar';
$_POST['_csrf'] = arq_csrf_token();
$_REQUEST['acao'] = 'restaurar';
require __DIR__ . '/api/lixeira.php';
