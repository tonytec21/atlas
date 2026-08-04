<?php
/**
 * Atlas Forja — consulta do andamento de um job.
 * O navegador chama a cada ~700 ms: progresso.php?job=xxxx
 * Responde rápido e fecha a sessão imediatamente, para não segurar o lock
 * do arquivo de sessão enquanto a conversão está rodando.
 */
error_reporting(0); @ini_set('display_errors', '0');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $job = forja_job_sanitize($_GET['job'] ?? '');
    if ($job === '') { echo json_encode(['status' => 'error', 'message' => 'job inválido']); exit; }
    $d = forja_prog_ler($job);
    if (!$d) { echo json_encode(['status' => 'waiting']); exit; }
    echo json_encode(['status' => 'success', 'pct' => $d['pct'], 'texto' => $d['texto']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'falha ao ler o progresso']);
}
