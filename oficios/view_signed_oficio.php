<?php
/**
 * oficios/view_signed_oficio.php
 * ---------------------------------------------------------------------------
 * Entrega, inline no navegador, o PDF JÁ ASSINADO digitalmente do ofício.
 * Usado pela visualização quando o ofício está assinado — NÃO regenera o
 * documento; devolve exatamente os bytes assinados (com selo + assinatura
 * criptográfica ICP-Brasil embutida).
 *
 * GET: numero (string) obrigatório
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';

function vs_die($code, $msg)
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$numero = isset($_GET['numero']) ? trim((string)$_GET['numero']) : '';
if ($numero === '') {
    vs_die(400, 'Número do ofício não informado.');
}

try {
    assin_ensure_schema();
    $conn = assin_db();
    $stmt = $conn->prepare("SELECT assinado, assinatura_arquivo FROM oficios WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
} catch (Throwable $e) {
    vs_die(500, 'Erro ao consultar o ofício.');
}

if (!$row || empty($row['assinado'])) {
    vs_die(404, 'Ofício não está assinado.');
}

$baseDir     = realpath(assin_dir_assinados());
$numeroSafe  = preg_replace('~[^0-9A-Za-z_\-]~', '_', $numero);

// 1) tenta o arquivo exato gravado no banco; 2) cai no ponteiro estável.
$candidates = [];
if (!empty($row['assinatura_arquivo'])) {
    $candidates[] = __DIR__ . '/' . ltrim($row['assinatura_arquivo'], '/');
}
$candidates[] = $baseDir . '/' . $numeroSafe . '/' . $numeroSafe . '.pdf';

$file = null;
$baseGuard = $baseDir ? rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR : null;
foreach ($candidates as $cand) {
    $real = realpath($cand);
    // Proteção contra path traversal: precisa estar dentro de assinados/.
    if ($real && $baseGuard && strpos($real, $baseGuard) === 0 && is_file($real)) {
        $file = $real;
        break;
    }
}

if (!$file) {
    vs_die(404, 'Arquivo assinado não encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $numeroSafe . '_assinado.pdf"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
