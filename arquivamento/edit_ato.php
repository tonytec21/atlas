<?php
/** Compatibilidade: a edição passou a ser feita em cadastro.php. */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();
$id = arq_id_valido(isset($_GET['id']) ? $_GET['id'] : '');
header('Location: cadastro.php' . ($id !== '' ? '?id=' . rawurlencode($id) : ''), true, 301);
exit;
