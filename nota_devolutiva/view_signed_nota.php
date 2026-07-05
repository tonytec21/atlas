<?php
/** view_signed_nota.php — serve o PDF assinado (inline) com checagem de sessão e path-guard. */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';

$numero = isset($_GET['numero']) ? trim((string)$_GET['numero']) : '';
if ($numero === '') { http_response_code(400); die('Número não informado.'); }

$conn = nd_db();
$stmt = $conn->prepare("SELECT assinatura_arquivo FROM notas_devolutivas WHERE numero = ? LIMIT 1");
$stmt->bind_param('s', $numero); $stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { http_response_code(404); die('Nota não encontrada.'); }
$rel = $res->fetch_assoc()['assinatura_arquivo'] ?? '';
$stmt->close();
if (!$rel) { http_response_code(404); die('Esta nota não possui PDF assinado.'); }

$rel = rawurldecode($rel);
$base = realpath(nd_dir_assinados());
$path = realpath(__DIR__ . '/' . $rel);
if ($path === false || strncmp($path, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0 || !is_file($path)) {
    http_response_code(404); die('Arquivo assinado não encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
