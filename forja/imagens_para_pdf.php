<?php
/**
 * Atlas Forja — Imagens → PDF
 * -----------------------------------------------------------------------------
 * Erros fatais (esgotamento de memória, GD ausente, crash da lib) NÃO são
 * capturáveis por try/catch: o PHP encerra o script e o Apache devolve HTTP 500
 * com corpo vazio — exatamente o "500 (Internal Server Error)" visto no console.
 * Por isso registramos um shutdown handler que converte o fatal em JSON legível
 * e grava o motivo real em forja/tmp/forja_erros.log.
 */
error_reporting(E_ALL);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';

header('Content-Type: application/json; charset=utf-8');

$FORJA_RESPONDIDO = false;

register_shutdown_function(function () use (&$FORJA_RESPONDIDO) {
    if ($FORJA_RESPONDIDO) return;
    $e = error_get_last();
    $fatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    while (ob_get_level() > 0) @ob_end_clean();
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
    }
    if ($e && in_array($e['type'], $fatais, true)) {
        forja_log('IMG2PDF FATAL: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
        echo json_encode([
            'status'   => 'error',
            'message'  => forja_msg_fatal($e['message']),
            'detalhe'  => $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')',
            'pico_mb'  => round(memory_get_peak_usage(true) / 1048576, 1),
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $ult = $e ? ($e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')') : '';
        forja_log('IMG2PDF: encerrado sem resposta. ' . $ult);
        echo json_encode([
            'status'  => 'error',
            'message' => 'O processamento foi interrompido pelo servidor antes de terminar (provável estouro de memória ou queda do processo do Apache). Rode diag_img2pdf.php e confira forja/tmp/forja_erros.log.',
            'detalhe' => $ult,
        ], JSON_UNESCAPED_UNICODE);
    }
});

ob_start();
try {
    forja_checar_post();     /* POST vazio por exceder post_max_size vira mensagem clara */
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
    forja_job_iniciar($_POST['job'] ?? '');
    session_write_close();   /* libera o lock da sessão para o progresso.php */
    forja_gc();              /* remove o que passou da retenção (tmp e saida) */

    $ups   = forja_salvar_uploads(false, true);
    $paths = array_column($ups, 'path');

    $r     = forja_imagens_para_pdf($ups, $_POST['modo'] ?? 'imagem');
    $token = forja_registrar_saida($r['path'], 'imagens_para_pdf.pdf');

    foreach ($paths as $p) @unlink($p);   /* uploads temporários */

    $resp = [
        'status'   => 'success',
        'token'    => $token,
        'paginas'  => $r['paginas'],
        'tamanho'  => filesize($r['path']),
        'pico_mb'  => $r['pico_mb'],
    ];
    if (!empty($r['ignoradas'])) $resp['aviso'] = 'Ignorada(s): ' . implode(' | ', $r['ignoradas']);

    $FORJA_RESPONDIDO = true;
    while (ob_get_level() > 0) @ob_end_clean();
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    $FORJA_RESPONDIDO = true;
    while (ob_get_level() > 0) @ob_end_clean();
    forja_log('IMG2PDF ERRO: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
        'detalhe' => basename($e->getFile()) . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}
