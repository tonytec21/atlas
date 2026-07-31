<?php
/** Legado: redireciona a atualização para a API nova. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_avisar('update_ato.php');
$_POST['_csrf'] = arq_csrf_token();
require __DIR__ . '/api/salvar.php';
