<?php
/* Prévia de qualidade: renderiza uma página do PDF gerado (ou do original) como JPEG. */
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(180);
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
// libera o lock da sessão: permite carregar os dois PDFs da comparação em paralelo
@session_write_close();
try {
    $m = forja_saida($_GET['token'] ?? '');
    if (!$m) { http_response_code(404); exit; }
    $pg  = max(1, (int)($_GET['pg'] ?? 1));
    $dpi = (int)($_GET['dpi'] ?? 110);
    if ($dpi < 60 || $dpi > 200) $dpi = 110;

    $cache = forja_dir_tmp() . '/prevcache_' . md5($m['path'] . '|' . $pg . '|' . $dpi) . '.jpg';
    if (!is_file($cache)) {
        $jpg = forja_pdf_previa_jpeg($m['path'], $pg, $dpi);
        @rename($jpg, $cache) || @copy($jpg, $cache);
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=600');
    header('Content-Length: ' . filesize($cache));
    readfile($cache);
} catch (Throwable $e) { http_response_code(500); }
