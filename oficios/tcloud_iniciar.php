<?php
/**
 * oficios/tcloud_iniciar.php — assinatura do ofício pelo TCloud Assinador (centralizado no Atlas Signum).
 * Gera o PDF com o selo do ofício na posição escolhida (mesma rotina do fluxo SERPRO) e cria o pedido
 * tcloudsign://local no Signum. A gravação acontece em tcloud_gravar.php, chamada pelo Signum ao concluir.
 * POST: csrf (do Signum), numero, page, xn, yn, wn, timbrado (opcional)
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';
require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';
if (!defined('ATLAS_SELO_ASSINADOR')) define('ATLAS_SELO_ASSINADOR', 'TCloud Assinador');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new RuntimeException('Método inválido.');
    asg_tcm_checar_csrf();
    session_write_close();                          // não prende a sessão durante a geração do PDF
    assin_ensure_schema();

    $numero = trim((string)($_POST['numero'] ?? ''));
    if ($numero === '') throw new RuntimeException('Número do ofício não informado.');
    $page = max(1, (int)($_POST['page'] ?? 1));
    $xn = (float)($_POST['xn'] ?? 0.55); $yn = (float)($_POST['yn'] ?? 0.80); $wn = (float)($_POST['wn'] ?? 0.38);
    $timbrado = assin_timbrado_flag($_POST['timbrado'] ?? null);

    $conn = assin_db();
    $stmt = $conn->prepare("SELECT assinante, cargo_assinante, assinado FROM oficios WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new RuntimeException('Ofício não encontrado.');
    $of = $res->fetch_assoc(); $stmt->close();
    if ((int)($of['assinado'] ?? 0) === 1) throw new RuntimeException('Este ofício já está assinado.');

    $baseBytes = assin_generate_pdf_bytes($numero, $timbrado);
    $codigo = implode('-', str_split(strtoupper(substr(hash('sha256', $baseBytes), 0, 16)), 4));
    $sealed = assin_stamp_seal($baseBytes, [
        'page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn,
        'nome' => $of['assinante'] ?? '', 'cargo' => $of['cargo_assinante'] ?? '',
        'numero' => $numero, 'codigo' => $codigo, 'quando' => date('d/m/Y H:i:s'),
    ]);
    if (!$sealed || strncmp(ltrim($sealed), '%PDF', 4) !== 0) throw new RuntimeException('Falha ao gerar o PDF com o selo.');

    asg_tcm_json(asg_tcm_iniciar($sealed, 'Ofício ' . $numero . '.pdf', [
        'modulo'   => 'oficio',
        'gravador' => __DIR__ . '/tcloud_gravar.php',
        'funcao'   => 'tcm_oficio_gravar',
        'dados'    => ['numero' => $numero, 'codigo' => $codigo, 'pos' => ['page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn]],
    ]));
} catch (Throwable $e) {
    if (function_exists('assin_log')) assin_log('TCLOUD-INICIAR ERROR: ' . $e->getMessage());
    asg_tcm_json(['success' => false, 'message' => $e->getMessage(), 'codigo' => 'erro']);
}
