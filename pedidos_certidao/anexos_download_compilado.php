<?php
// pedidos_certidao/anexos_download_compilado.php
include(__DIR__ . '/../os/session_check.php');
checkSession();

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$pedidoId = (int)($_GET['pedido'] ?? 0);
$file     = $_GET['file'] ?? '';
$csrf     = $_GET['csrf'] ?? '';

if ($pedidoId<=0 || $file==='') { http_response_code(400); echo 'Parâmetros inválidos'; exit; }
if (empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $csrf)) {
  http_response_code(403); echo 'CSRF inválido'; exit;
}

$base = realpath(__DIR__ . '/uploads/' . $pedidoId . '/compilados');
if ($base === false) { http_response_code(404); echo 'Arquivo não encontrado'; exit; }
$path = realpath($base . '/' . $file);

// Evita path traversal
if ($path === false || strpos($path, $base) !== 0 || !file_exists($path)) {
  http_response_code(404); echo 'Arquivo não encontrado'; exit;
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . rawurlencode($file) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
