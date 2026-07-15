<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
$m = forja_saida($_GET['token'] ?? '');
if (!$m) { http_response_code(404); exit('Arquivo não encontrado ou expirado.'); }
$path = $m['path']; $nome = $m['nome'] ?: basename($path);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$ct = $ext === 'pdf' ? 'application/pdf' : ($ext === 'zip' ? 'application/zip' : 'application/octet-stream');
header('Content-Type: ' . $ct);
header('Content-Disposition: attachment; filename="' . rawurlencode($nome) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
