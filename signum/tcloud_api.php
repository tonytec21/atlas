<?php
/**
 * tcloud_api.php — endpoint AJAX do Atlas Signum para o TCloud Assinador (tela → Signum).
 * O app da estação, no modo local, fala com tcloud_estacao.php (sem sessão).
 * Ações (POST, com csrf): situacao | testar | iniciar | status | cancelar
 */
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
asg_tc_lib();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function tc_saida($dados) { echo json_encode($dados, JSON_UNESCAPED_UNICODE); exit; }

try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    $u  = (string)$_SESSION['username'];
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    session_write_close();                 // não prende a sessão enquanto conversa com o serviço
    asg_ensure_schema();
    $acao = (string)($_POST['acao'] ?? '');

    switch ($acao) {
        case 'situacao':
            $modo = (string)($_POST['modo'] ?? '');   // Configurar pode checar o modo ainda não salvo
            tc_saida(['success' => true] + asg_tc_situacao(in_array($modo, ['local', 'servidor'], true) ? $modo : null));

        case 'testar':
            $r = asg_tc_cliente(60)->testarAct();
            tc_saida(['success' => true, 'act' => (string)($r['act'] ?? ''),
                      'instante' => !empty($r['instante']) ? date('d/m/Y H:i:s', strtotime($r['instante'])) : '']);

        case 'iniciar':
            $token = preg_replace('~[^a-f0-9]~', '', (string)($_POST['token'] ?? ''));
            $src = asg_dir_tmp() . '/' . $token . '.pdf';
            if ($token === '' || !is_file($src)) throw new RuntimeException('Envie o PDF novamente.');
            $nome = @file_get_contents(asg_dir_tmp() . '/' . $token . '.nome') ?: 'documento.pdf';
            $pos = ['pagina' => (int)($_POST['page'] ?? 1),
                    'x' => isset($_POST['xn']) && $_POST['xn'] !== '' ? (float)$_POST['xn'] : null,
                    'y' => isset($_POST['yn']) && $_POST['yn'] !== '' ? (float)$_POST['yn'] : null,
                    'w' => isset($_POST['wn']) && $_POST['wn'] !== '' ? (float)$_POST['wn'] : 0.30];
            tc_saida(['success' => true] + asg_tc_iniciar($u, $src, $nome, $pos, $token, $ip, (string)($_SERVER['HTTP_HOST'] ?? '')));

        case 'sondar':          // "Verificar agora": o TCloud Assinador está neste computador?
            asg_tc_lib();
            tc_saida(['success' => true] + asg_tcl_sonda_criar($u, $ip, (string)($_SERVER['HTTP_HOST'] ?? '')));

        case 'sonda_status':
            asg_tc_lib();
            tc_saida(['success' => true] + asg_tcl_sonda_status($u, (string)($_POST['id'] ?? '')));

        case 'sonda_cancelar':
            asg_tc_lib();
            tc_saida(['success' => true] + asg_tcl_sonda_cancelar($u, (string)($_POST['id'] ?? '')));

        case 'status':
            tc_saida(['success' => true] + asg_tc_acompanhar($u, (string)($_POST['id'] ?? '')));

        case 'cancelar':
            tc_saida(['success' => true] + asg_tc_cancelar($u, (string)($_POST['id'] ?? '')));

        default:
            throw new RuntimeException('Ação inválida.');
    }
} catch (TCloudAssinadorException $e) {
    tc_saida(['success' => false, 'message' => $e->getMessage(), 'codigo' => $e->codigo, 'http' => $e->status]);
} catch (Throwable $e) {
    tc_saida(['success' => false, 'message' => $e->getMessage(), 'codigo' => 'erro']);
}
