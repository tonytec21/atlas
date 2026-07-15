<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    asg_ensure_schema();
    $u = $_SESSION['username'];

    // GLOBAL (aparência do carimbo do cartório)
    asg_config_set([
        'carimbo_titulo' => trim($_POST['carimbo_titulo'] ?? '') ?: 'Assinado digitalmente',
        'motivo'         => trim($_POST['motivo'] ?? '') ?: 'Assinatura eletrônica de documento',
    ]);
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        asg_salvar_logo($_FILES['logo']['tmp_name'], $_FILES['logo']['name']);
    }

    // POR USUÁRIO
    $metodo = ($_POST['metodo'] ?? 'a1') === 'a3' ? 'a3' : 'a1';
    asg_ucfg_set($u, [
        'metodo'          => $metodo,
        'assinante_nome'  => trim($_POST['assinante_nome'] ?? ''),
        'assinante_cpf'   => preg_replace('~\D~', '', $_POST['assinante_cpf'] ?? ''),
        'assinante_cargo' => trim($_POST['assinante_cargo'] ?? ''),
        'assinante_local' => trim($_POST['assinante_local'] ?? ''),
        'usar_cn_titular' => !empty($_POST['usar_cn_titular']) ? 1 : 0,
        'a3_agente_url'   => trim($_POST['a3_agente_url'] ?? ''),
    ]);

    // Certificado A1 do usuário
    if (!empty($_FILES['cert']) && $_FILES['cert']['error'] === UPLOAD_ERR_OK) {
        asg_salvar_certificado($u, $_FILES['cert']['tmp_name'], $_POST['cert_senha'] ?? '');
    } elseif (($_POST['cert_senha'] ?? '') !== '' && !empty(asg_ucfg($u)['cert_arquivo'])) {
        $path = asg_dir_cert() . '/' . basename(asg_ucfg($u)['cert_arquivo']);
        $certs = [];
        if (!openssl_pkcs12_read(file_get_contents($path), $certs, $_POST['cert_senha'])) throw new RuntimeException('Senha do certificado incorreta.');
        asg_ucfg_set($u, ['cert_senha_enc' => asg_enc($_POST['cert_senha'])]);
    }

    echo json_encode(['success' => true, 'message' => 'Configurações salvas.', 'cert' => asg_cert_info($u)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
