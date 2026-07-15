<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
$id = (int)($_GET['id'] ?? 0);
$doc = asg_doc($id);
if (!$doc) { http_response_code(404); exit('não encontrado'); }
$p = asg_dir_sig() . '/' . basename($doc['arquivo']);
if (!is_file($p)) { http_response_code(404); exit('não encontrado'); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="assinado.pdf"');
header('Content-Length: ' . filesize($p));
readfile($p);
