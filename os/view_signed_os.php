<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/assinatura_os_config.php';
$tipo = trim((string)($_GET['tipo'] ?? '')); $osId = (int)($_GET['os_id'] ?? $_GET['id'] ?? 0);
if (!os_tipo_valido($tipo) || $osId <= 0) { http_response_code(400); die('Parâmetros inválidos.'); }
$info = os_doc_info($tipo, $osId);
if (!$info || empty($info['assinatura_arquivo'])) { http_response_code(404); die('Documento assinado não encontrado.'); }
$rel = rawurldecode($info['assinatura_arquivo']);
$base = realpath(os_dir_assinados());
$path = realpath(__DIR__ . '/' . $rel);
if ($path === false || strncmp($path, $base . DIRECTORY_SEPARATOR, strlen($base)+1) !== 0 || !is_file($path)) { http_response_code(404); die('Arquivo não encontrado.'); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.basename($path).'"');
header('Content-Length: '.filesize($path));
readfile($path);
