<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','1024M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    forja_checar_post();     /* POST vazio por exceder post_max_size vira mensagem clara */
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    forja_job_iniciar($_POST['job'] ?? '');
    session_write_close();   /* libera o lock da sessão para o progresso.php */
    forja_gc();              /* remove o que passou da retenção (tmp e saida) */
    $ups  = forja_salvar_uploads(true, false);
    $src  = $ups[0]['path'];
    $orig = filesize($src);

    $opc  = ['cinza' => $_POST['cinza'] ?? 'auto'];
    $info = null;
    $out  = forja_comprimir_pdf($src, $_POST['nivel'] ?? 'recomendado', $opc, $info);
    $novo = filesize($out);

    $base  = preg_replace('~[^A-Za-z0-9_\-]~', '_', pathinfo($ups[0]['nome'], PATHINFO_FILENAME));
    $token = forja_registrar_saida($out, 'comprimido_' . $base . '.pdf');

    /* Token do original, só para a prévia comparativa. Acima de 300 MB a cópia
       custaria tempo e o dobro do disco — a prévia lado a lado é dispensada. */
    $tokenOrig = '';
    if ($orig <= 300 * 1048576) {
        $copia = forja_dir_tmp() . '/orig_' . bin2hex(random_bytes(5)) . '.pdf';
        if (@copy($src, $copia)) $tokenOrig = forja_registrar_saida($copia, 'original_' . $base . '.pdf');
    }

    echo json_encode([
        'status'      => 'success',
        'token'       => $token,
        'token_orig'  => $tokenOrig,
        'orig'        => $orig,
        'novo'        => $novo,
        'reducao'     => $info['reducao'] ?? 0,
        'nivel'       => $info['nivel'] ?? '',
        'rotulo'      => $info['rotulo'] ?? '',
        'dpi'         => $info['dpi'] ?? null,
        'paginas'     => $info['paginas'] ?? 0,
        'cinza'       => !empty($info['cinza']),
        'cinza_auto'  => !empty($info['cinza_auto']),
        'estrategia'  => $info['estrategia'] ?? '',
        'tentativas'  => $info['tentativas'] ?? 0,
        'ja_otimizado'=> !empty($info['ja_otimizado']),
        'avisos'      => $info['avisos'] ?? [],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
