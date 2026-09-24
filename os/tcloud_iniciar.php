<?php
/**
 * os/tcloud_iniciar.php — assinatura de documentos da O.S. (recibo A4, O.S./orçamento) pelo TCloud Assinador
 * (via Atlas Signum). Gera o PDF com o selo (mesma rotina do fluxo SERPRO) e cria o pedido no Signum.
 * POST: csrf (do Signum), tipo, os_id, page, xn, yn, wn
 */
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/assinatura_os_config.php';
require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';
if (!defined('ATLAS_SELO_ASSINADOR')) define('ATLAS_SELO_ASSINADOR', 'TCloud Assinador');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new RuntimeException('Método inválido.');
    asg_tcm_checar_csrf();
    $signer = os_signer_info();                      // usa $_SESSION['username']
    session_write_close();
    os_ensure_schema();

    $tipo = trim((string)($_POST['tipo'] ?? ''));
    $osId = (int)($_POST['os_id'] ?? 0);
    if (!os_tipo_valido($tipo) || $osId <= 0) throw new RuntimeException('Parâmetros inválidos.');
    // Reassinatura é permitida (a nova versão substitui a anterior), como no fluxo SERPRO.

    $page = max(1, (int)($_POST['page'] ?? 1));
    $xn = (float)($_POST['xn'] ?? 0.55); $yn = (float)($_POST['yn'] ?? 0.80); $wn = (float)($_POST['wn'] ?? 0.24);

    $baseBytes = os_generate_pdf_bytes($tipo, $osId);
    $codigo = implode('-', str_split(strtoupper(substr(hash('sha256', $baseBytes), 0, 16)), 4));
    $sealed = os_stamp_seal($baseBytes, [
        'page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn,
        'nome' => $signer['nome'], 'cargo' => $signer['cargo'], 'codigo' => $codigo, 'quando' => date('d/m/Y H:i:s'),
    ]);
    if (!$sealed || strncmp(ltrim($sealed), '%PDF', 4) !== 0) throw new RuntimeException('Falha ao gerar o PDF com o selo.');

    $titulo = os_tipos()[$tipo]['titulo'] ?? 'Documento';
    asg_tcm_json(asg_tcm_iniciar($sealed, $titulo . ' - O.S. ' . $osId . '.pdf', [
        'modulo'   => 'os',
        'gravador' => __DIR__ . '/tcloud_gravar.php',
        'funcao'   => 'tcm_os_gravar',
        'dados'    => ['tipo' => $tipo, 'os_id' => $osId, 'codigo' => $codigo, 'pos' => ['page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn]],
    ]));
} catch (Throwable $e) {
    if (function_exists('os_log')) os_log('TCLOUD-INICIAR ERR: ' . $e->getMessage());
    asg_tcm_json(['success' => false, 'message' => $e->getMessage(), 'codigo' => 'erro']);
}
