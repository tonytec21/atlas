<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
$token = preg_replace('~[^a-f0-9]~', '', $_GET['token'] ?? '');
$p = asg_dir_tmp() . '/' . $token . '.pdf';
if ($token === '' || !is_file($p)) { http_response_code(404); exit('não encontrado'); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview.pdf"');
header('Content-Length: ' . filesize($p));
readfile($p);
