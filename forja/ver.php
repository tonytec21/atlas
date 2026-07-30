<?php
/**
 * Exibe um PDF gerado pelo módulo DENTRO do navegador (visualizador nativo),
 * em vez de forçar download. Usado pela "Prévia da qualidade".
 *
 * Diferenças em relação ao baixar.php:
 *  - Content-Disposition: inline
 *  - suporte a Range (byte serving), para o visualizador do Chrome/Edge/Firefox
 *    carregar só as páginas que o usuário está vendo, em vez do arquivo inteiro.
 */
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0);
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
// libera o lock da sessão: permite carregar os dois PDFs da comparação em paralelo
@session_write_close();

$m = forja_saida($_GET['token'] ?? '');
if (!$m) { http_response_code(404); exit('Arquivo não encontrado ou expirado.'); }

$path  = $m['path'];
$nome  = $m['nome'] ?: basename($path);
$tam   = filesize($path);
$etag  = '"' . md5($path . '|' . $tam . '|' . @filemtime($path)) . '"';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($nome) . '"');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, max-age=900');
header('ETag: ' . $etag);
header('Accept-Ranges: bytes');

if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }

$ini = 0; $fim = $tam - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('~bytes=(\d*)-(\d*)~', $range, $r)) {
    if ($r[1] === '' && $r[2] !== '') {            // sufixo: bytes=-500
        $ini = max(0, $tam - (int)$r[2]);
    } else {
        $ini = (int)$r[1];
        if ($r[2] !== '') $fim = min((int)$r[2], $tam - 1);
    }
    if ($ini > $fim || $ini >= $tam) {
        http_response_code(416);
        header('Content-Range: bytes */' . $tam);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $ini . '-' . $fim . '/' . $tam);
}

header('Content-Length: ' . ($fim - $ini + 1));

$fp = @fopen($path, 'rb');
if (!$fp) { http_response_code(500); exit; }
if ($ini > 0) fseek($fp, $ini);
$resta = $fim - $ini + 1;
while ($resta > 0 && !feof($fp)) {
    $bloco = fread($fp, min(262144, $resta));
    if ($bloco === false || $bloco === '') break;
    echo $bloco;
    $resta -= strlen($bloco);
    @ob_flush(); @flush();
}
fclose($fp);
