<?php
/**
 * =====================================================================
 * auditoria_os_impresso.php — Impresso da O.S. numa versão registrada
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-10-03-auditoria
 * RESTRITO A ADMINISTRADOR.
 *
 * Reproduz o impresso da O.S. (o mesmo gerador da tela: imprimir_os.php
 * com timbrado ou imprimir-os.php sem) usando o retrato gravado na
 * auditoria, e não os dados atuais. Cada página leva uma faixa vermelha
 * identificando a versão.
 *
 *   ?tipo=evento&id=N&lado=antes|depois
 *   ?tipo=legado&id=N            (cópia do histórico antigo)
 *   ?tipo=atual&os=N             (estado atual, para comparação)
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/../checar_acesso_de_administrador.php');
require_once __DIR__ . '/auditoria_os_lib.php';

$__aud_snap  = null;
$__aud_marca = '';
$__aud_erro  = null;

try {
    $__aud_pdo  = osaud_pdo();
    osaud_migrar($__aud_pdo);
    $__aud_tipo = (string) ($_GET['tipo'] ?? 'evento');

    if ($__aud_tipo === 'evento') {
        $__aud_ev = osaud_evento($__aud_pdo, (int) ($_GET['id'] ?? 0));
        $__aud_lado = ($_GET['lado'] ?? 'depois') === 'antes' ? 'antes' : 'depois';
        if (!$__aud_ev) {
            throw new RuntimeException('Registro de auditoria não encontrado.');
        }
        $__aud_snap = $__aud_ev[$__aud_lado];
        if (!$__aud_snap) {
            throw new RuntimeException($__aud_lado === 'antes'
                ? 'Não há versão anterior: a O.S. foi criada nesta operação.'
                : 'Não há versão posterior registrada.');
        }
        $__aud_marca = 'AUDITORIA · O.S. Nº ' . (int) $__aud_ev['os_id'] . ' · VERSÃO '
            . ($__aud_lado === 'antes' ? 'ANTES' : 'DEPOIS') . ' DA ALTERAÇÃO DE '
            . date('d/m/Y H:i:s', strtotime($__aud_ev['criado_em'])) . ' · REGISTRO #' . (int) $__aud_ev['id']
            . ' · ' . mb_strtoupper(osaud_acao_info($__aud_ev['acao'])['rotulo'], 'UTF-8');
    } elseif ($__aud_tipo === 'legado') {
        $__aud_snap = osaud_legado_snapshot($__aud_pdo, (int) ($_GET['id'] ?? 0));
        if (!$__aud_snap) {
            throw new RuntimeException('Registro antigo não encontrado.');
        }
        $__aud_marca = 'AUDITORIA · O.S. Nº ' . (int) $__aud_snap['os']['id'] . ' · CÓPIA DO HISTÓRICO ANTIGO DE '
            . date('d/m/Y H:i:s', strtotime((string) $__aud_snap['capturado_em'])) . ' · VALORES PAGOS = ATUAIS';
    } elseif ($__aud_tipo === 'atual') {
        $__aud_snap = osaud_snapshot($__aud_pdo, (int) ($_GET['os'] ?? 0));
        if (!$__aud_snap) {
            throw new RuntimeException('O.S. não encontrada.');
        }
        $__aud_marca = 'AUDITORIA · O.S. Nº ' . (int) $__aud_snap['os']['id'] . ' · ESTADO ATUAL EM ' . date('d/m/Y H:i:s');
    } else {
        throw new RuntimeException('Tipo inválido.');
    }
} catch (Throwable $e) {
    $__aud_erro = $e->getMessage();
}

if ($__aud_erro !== null) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Impresso indisponível</title>'
       . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;display:flex;align-items:center;justify-content:center;'
       . 'min-height:90vh;margin:0;background:#f8fafc;color:#475569}div{text-align:center;max-width:420px;padding:24px}'
       . 'b{display:block;font-size:15px;color:#0f172a;margin-bottom:6px}</style></head><body><div><b>Impresso indisponível</b>'
       . htmlspecialchars($__aud_erro, ENT_QUOTES, 'UTF-8') . '</div></body></html>';
    exit;
}

osaud_preparar_impressao($__aud_snap, $__aud_marca);

/* Mesmo gerador usado pela tela (configuração "timbrado"). */
$__aud_cfg = @json_decode((string) @file_get_contents(__DIR__ . '/../style/configuracao.json'), true);
$__aud_gerador = (is_array($__aud_cfg) && ($__aud_cfg['timbrado'] ?? 'S') === 'N') ? 'imprimir-os.php' : 'imprimir_os.php';

$_GET = ['id' => (int) $__aud_snap['os']['id']];

/* Incluído no escopo GLOBAL: os geradores usam "global $isCanceled". */
include __DIR__ . '/' . $__aud_gerador;
