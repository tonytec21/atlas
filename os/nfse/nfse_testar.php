<?php
/** ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

$tipo = $_GET['tipo'] ?? 'convenio';

try {
    $cfg = nfse_config(true);

    if (empty($cfg['cert_blob'])) {
        nfse_json(['ok' => false, 'mensagem' => 'Instale o certificado A1 antes de testar a conexão.']);
    }
    if (empty($cfg['cod_municipio'])) {
        nfse_json(['ok' => false, 'mensagem' => 'Informe e salve o código IBGE do município antes de testar.']);
    }

    if ($tipo === 'aliquota') {
        $res = nfse_consultar_aliquota();
        nfse_json([
            'ok'      => true,
            'titulo'  => 'Alíquota do serviço ' . ($cfg['ctrib_nac'] ?: '210101'),
            'detalhe' => htmlspecialchars(
                json_encode($res['aliquotas'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ENT_QUOTES, 'UTF-8'
            ),
        ]);
    }

    $res = nfse_testar_convenio();
    nfse_json([
        'ok'      => true,
        'titulo'  => 'Município ' . $cfg['cod_municipio'] . ' respondeu',
        'detalhe' => htmlspecialchars(
            json_encode($res['parametros'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES, 'UTF-8'
        ),
    ]);
} catch (Throwable $e) {
    error_log('[nfse_testar] ' . $e->getMessage());
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()]);
}
