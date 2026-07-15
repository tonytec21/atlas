<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
$id = (int)($_GET['id'] ?? 0);
$doc = asg_doc($id);
if (!$doc) { http_response_code(404); exit('Documento não encontrado.'); }
$p = asg_dir_sig() . '/' . basename($doc['arquivo']);
if (!is_file($p)) { http_response_code(404); exit('Arquivo não encontrado.'); }
$baixe = pathinfo($doc['nome_original'], PATHINFO_FILENAME) . ' (assinado).pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $baixe) . '"');
header('Content-Length: ' . filesize($p));
readfile($p);
