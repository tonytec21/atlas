<?php
error_reporting(0); @ini_set('display_errors','0');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    forja_require_admin();
    $gs = forja_gs_bin(); $mk = forja_magick_bin(); $lo = forja_libreoffice_bin();
    $gsVer = ''; if ($gs) { $r = forja_exec(escapeshellarg($gs) . ' --version'); $gsVer = trim($r['out']); }
    echo json_encode(['status' => 'success',
        'ghostscript' => $gs ? ['ok' => true, 'path' => $gs, 'versao' => $gsVer] : ['ok' => false],
        'imagemagick' => $mk ? ['ok' => true, 'path' => $mk] : ['ok' => false],
        'libreoffice' => $lo ? ['ok' => true, 'path' => $lo] : ['ok' => false],
        'zip' => class_exists('ZipArchive')]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
