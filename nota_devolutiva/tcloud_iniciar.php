<?php
/**
 * nota_devolutiva/tcloud_iniciar.php — assinatura da nota devolutiva pelo TCloud Assinador (via Atlas Signum).
 * Gera o PDF com o selo da nota (mesma rotina do fluxo SERPRO) e cria o pedido tcloudsign://local no Signum.
 * POST: csrf (do Signum), numero, page, xn, yn, wn
 */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';
if (!defined('ATLAS_SELO_ASSINADOR')) define('ATLAS_SELO_ASSINADOR', 'TCloud Assinador');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new RuntimeException('Método inválido.');
    asg_tcm_checar_csrf();
    session_write_close();
    nd_ensure_schema();

    $numero = trim((string)($_POST['numero'] ?? ''));
    if ($numero === '') throw new RuntimeException('Número da nota não informado.');
    $page = max(1, (int)($_POST['page'] ?? 1));
    $xn = (float)($_POST['xn'] ?? 0.55); $yn = (float)($_POST['yn'] ?? 0.80); $wn = (float)($_POST['wn'] ?? 0.38);

    $conn = nd_db();
    $stmt = $conn->prepare("SELECT assinante, cargo_assinante, assinado FROM notas_devolutivas WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new RuntimeException('Nota devolutiva não encontrada.');
    $nota = $res->fetch_assoc(); $stmt->close();
    if ((int)($nota['assinado'] ?? 0) === 1) throw new RuntimeException('Esta nota já está assinada.');

    $baseBytes = nd_generate_pdf_bytes($numero);
    $codigo = implode('-', str_split(strtoupper(substr(hash('sha256', $baseBytes), 0, 16)), 4));
    $sealed = nd_stamp_seal($baseBytes, [
        'page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn,
        'nome' => $nota['assinante'] ?? '', 'cargo' => $nota['cargo_assinante'] ?? '',
        'codigo' => $codigo, 'quando' => date('d/m/Y H:i:s'),
    ]);
    if (!$sealed || strncmp(ltrim($sealed), '%PDF', 4) !== 0) throw new RuntimeException('Falha ao gerar o PDF com o selo.');

    asg_tcm_json(asg_tcm_iniciar($sealed, 'Nota devolutiva ' . $numero . '.pdf', [
        'modulo'   => 'nota_devolutiva',
        'gravador' => __DIR__ . '/tcloud_gravar.php',
        'funcao'   => 'tcm_nota_gravar',
        'dados'    => ['numero' => $numero, 'codigo' => $codigo, 'pos' => ['page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn]],
    ]));
} catch (Throwable $e) {
    if (function_exists('nd_log')) nd_log('TCLOUD-INICIAR ERROR: ' . $e->getMessage());
    asg_tcm_json(['success' => false, 'message' => $e->getMessage(), 'codigo' => 'erro']);
}
