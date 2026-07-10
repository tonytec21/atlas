<?php
/** ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nfse_json(['ok' => false, 'mensagem' => 'Método inválido.'], 405);
}

try {
    nfse_migrar();

    // ---------- Remoção ----------
    if (($_POST['acao'] ?? '') === 'remover') {
        nfse_remover_certificado();
        nfse_json(['ok' => true, 'mensagem' => 'Certificado removido e emissão desativada.']);
    }

    // ---------- Upload ----------
    if (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
        $mapa = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que o limite do PHP (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'O envio foi interrompido.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo temporário.',
        ];
        $cod = $_FILES['certificado']['error'] ?? UPLOAD_ERR_NO_FILE;
        nfse_json(['ok' => false, 'mensagem' => $mapa[$cod] ?? 'Falha no envio do arquivo.']);
    }

    $arquivo = $_FILES['certificado'];
    $nome    = basename($arquivo['name']);
    $ext     = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

    if (!in_array($ext, ['pfx', 'p12'], true)) {
        nfse_json(['ok' => false, 'mensagem' => 'Formato inválido. Envie o certificado A1 em .pfx ou .p12.']);
    }
    if ($arquivo['size'] > 512 * 1024) {
        nfse_json(['ok' => false, 'mensagem' => 'Arquivo muito grande para um certificado A1 (limite 512 KB).']);
    }
    if (!is_uploaded_file($arquivo['tmp_name'])) {
        nfse_json(['ok' => false, 'mensagem' => 'Upload inválido.'], 400);
    }

    $senha = (string) ($_POST['senha'] ?? '');
    if ($senha === '') {
        nfse_json(['ok' => false, 'mensagem' => 'Informe a senha do certificado.']);
    }

    $conteudo = file_get_contents($arquivo['tmp_name']);
    @unlink($arquivo['tmp_name']);

    if ($conteudo === false || $conteudo === '') {
        nfse_json(['ok' => false, 'mensagem' => 'Não foi possível ler o arquivo enviado.']);
    }

    $meta = nfse_salvar_certificado($conteudo, $senha, $nome);

    // Zera a variável sensível o quanto antes.
    $conteudo = null;
    $senha = null;

    $aviso = '';
    if ($meta['dias_para_vencer'] !== null && $meta['dias_para_vencer'] <= 30) {
        $aviso = ' Atenção: vence em ' . $meta['dias_para_vencer'] . ' dia(s).';
    }

    nfse_json([
        'ok'         => true,
        'titular'    => $meta['titular'],
        'valido_ate' => $meta['valido_ate'] ? date('d/m/Y H:i', strtotime($meta['valido_ate'])) : '—',
        'mensagem'   => 'Certificado validado e armazenado cifrado.' . $aviso,
    ]);
} catch (Throwable $e) {
    error_log('[nfse_upload_certificado] ' . $e->getMessage());
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()]);
}
