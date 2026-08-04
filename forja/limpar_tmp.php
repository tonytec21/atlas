<?php
/**
 * Atlas Forja — limpeza manual dos arquivos de trabalho (admin).
 *  GET  → devolve o espaço ocupado por forja/tmp e forja/saida
 *  POST → executa a limpeza (modo=retencao usa as horas configuradas; modo=tudo apaga tudo)
 */
error_reporting(0); @ini_set('display_errors', '0'); @set_time_limit(0);
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');

try {
    forja_require_admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $u = forja_uso_disco();
        echo json_encode([
            'status'    => 'success',
            'tmp'       => forja_human($u['tmp']),
            'saida'     => forja_human($u['saida']),
            'total'     => forja_human($u['total']),
            'retencao'  => forja_retencao_horas(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $tudo = (($_POST['modo'] ?? 'retencao') === 'tudo');
    $r    = forja_gc($tudo ? 0 : null);
    $u    = forja_uso_disco();

    echo json_encode([
        'status'    => 'success',
        'message'   => $r['arquivos'] . ' item(ns) removido(s) · ' . forja_human($r['bytes']) . ' liberado(s).',
        'arquivos'  => $r['arquivos'],
        'liberado'  => forja_human($r['bytes']),
        'tmp'       => forja_human($u['tmp']),
        'saida'     => forja_human($u['saida']),
        'total'     => forja_human($u['total']),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
