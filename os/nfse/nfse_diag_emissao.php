<?php
/**
 * ATLAS O.S. — Diagnóstico da EMISSÃO de NFS-e (SEFIN Nacional)
 * ---------------------------------------------------------------------
 * Serve para descobrir a causa de falhas na emissão — em especial os
 * HTTP 500 genéricos ("An error has occurred."), que o SDK não detalha
 * porque a Guzzle só devolve a mensagem resumida.
 *
 * O que a ferramenta faz, em ordem:
 *   1. Mostra a configuração e a validade do certificado A1.
 *   2. Levanta no banco DESDE QUANDO a emissão está falhando e se a
 *      falha é de 100% das tentativas ou intermitente.
 *   3. Testa a conectividade com a SEFIN (consulta de uma nota já
 *      autorizada) e com o ADN (parâmetros do município).
 *   4. Monta e assina o DPS de uma O.S. SEM enviar (simulação), e
 *      mostra o XML exato que sairia.
 *   5. Opcionalmente envia de verdade, capturando status HTTP,
 *      cabeçalhos e corpo bruto da resposta.
 *
 * Acesse por: .../os/nfse/nfse_diag_emissao.php
 * Restrito a administradores. Pode ficar instalado: nada é alterado
 * sem que o operador marque explicitamente "enviar de verdade".
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(180);

$osId    = isset($_POST['os_id']) ? (int) $_POST['os_id'] : 0;
$enviar  = isset($_POST['enviar']) && $_POST['enviar'] === '1';
$rodar   = ($_SERVER['REQUEST_METHOD'] === 'POST');

/* =====================================================================
 * Helpers
 * ================================================================== */

function d_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Bloco de resultado com semáforo. */
function d_bloco(string $titulo, string $nivel, string $resumo, string $detalhe = ''): void
{
    $cor = ['ok' => 'ok', 'alerta' => 'alerta', 'erro' => 'erro', 'info' => 'info'][$nivel] ?? 'info';
    echo '<div class="bloco ' . $cor . '">';
    echo '<h3>' . d_h($titulo) . '</h3>';
    echo '<p class="resumo">' . $resumo . '</p>';
    if ($detalhe !== '') {
        echo '<pre>' . $detalhe . '</pre>';
    }
    echo '</div>';
}

/**
 * Requisição HTTP crua com o certificado A1, devolvendo TUDO:
 * status, cabeçalhos, corpo e tempo. É aqui que aparece o detalhe
 * que o SDK esconde.
 */
function d_http(string $metodo, string $url, ?string $jsonBody, string $pfx, string $senha): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'nfse_diag_');
    file_put_contents($tmp, $pfx);

    $ch = curl_init();
    $headersResp = [];

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSLCERTTYPE    => 'P12',
        CURLOPT_SSLCERT        => $tmp,
        CURLOPT_SSLCERTPASSWD  => $senha,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$headersResp) {
            $t = trim($linha);
            if ($t !== '') { $headersResp[] = $t; }
            return strlen($linha);
        },
    ];

    if ($metodo === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $jsonBody;
    }

    curl_setopt_array($ch, $opts);

    $ini  = microtime(true);
    $body = curl_exec($ch);
    $ms   = (int) round((microtime(true) - $ini) * 1000);

    $res = [
        'status'   => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'body'     => $body === false ? '' : $body,
        'headers'  => $headersResp,
        'erro'     => curl_error($ch),
        'errno'    => curl_errno($ch),
        'ms'       => $ms,
        'ssl_verify' => curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT),
    ];

    curl_close($ch);
    @unlink($tmp);

    return $res;
}

/** Formata a resposta crua para exibição. */
function d_formatar_resposta(array $r): string
{
    $out  = 'HTTP ' . $r['status'] . '   (' . $r['ms'] . ' ms)' . "\n";
    if ($r['errno']) {
        $out .= 'cURL erro ' . $r['errno'] . ': ' . $r['erro'] . "\n";
    }
    $out .= "\n--- cabeçalhos ---\n" . implode("\n", $r['headers']);
    $out .= "\n\n--- corpo ---\n" . ($r['body'] !== '' ? $r['body'] : '(vazio)');

    return d_h($out);
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico da emissão de NFS-e — Atlas O.S.</title>
<style>
    :root{
        --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --fundo:#f8fafc;
        --ok:#16a34a; --alerta:#d97706; --erro:#dc2626; --info:#0284c7;
    }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:var(--fundo); color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.5; }
    .wrap{ max-width:1100px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 20px; color:var(--cinza); }
    h3{ font-size:15px; margin:0 0 6px; }
    form.painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px; margin-bottom:20px; }
    label{ font-weight:600; display:block; margin-bottom:4px; }
    input[type=number]{ padding:8px 10px; border:1px solid var(--borda); border-radius:6px; width:180px; font-size:14px; }
    .linha{ display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap; }
    .check{ display:flex; align-items:center; gap:8px; padding-bottom:8px; }
    .check label{ margin:0; font-weight:400; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:10px 20px;
            font-size:14px; font-weight:600; cursor:pointer; }
    button:hover{ background:#1e3a8a; }
    .aviso{ background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:10px 12px; margin-top:12px; color:#78350f; }
    .bloco{ background:#fff; border:1px solid var(--borda); border-left-width:5px; border-radius:8px;
            padding:14px 16px; margin-bottom:14px; }
    .bloco.ok{ border-left-color:var(--ok); }
    .bloco.alerta{ border-left-color:var(--alerta); }
    .bloco.erro{ border-left-color:var(--erro); }
    .bloco.info{ border-left-color:var(--info); }
    .resumo{ margin:0; }
    pre{ background:#0f172a; color:#e2e8f0; padding:12px; border-radius:6px; overflow:auto;
         max-height:460px; font-size:12px; line-height:1.45; margin:10px 0 0; white-space:pre-wrap; word-break:break-word; }
    table{ border-collapse:collapse; width:100%; margin-top:10px; font-size:13px; }
    th,td{ border:1px solid var(--borda); padding:6px 8px; text-align:left; vertical-align:top; }
    th{ background:#f1f5f9; }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Diagnóstico da emissão de NFS-e</h1>
<p>Mostra o que a integração está realmente enviando à SEFIN Nacional e o que ela responde, sem o resumo do SDK.</p>

<form class="painel" method="post">
    <div class="linha">
        <div>
            <label for="os_id">Nº da O.S. para simular</label>
            <input type="number" id="os_id" name="os_id" min="1" value="<?php echo $osId ?: ''; ?>" placeholder="ex.: 1234">
        </div>
        <div class="check">
            <input type="checkbox" id="enviar" name="enviar" value="1" <?php echo $enviar ? 'checked' : ''; ?>>
            <label for="enviar">Enviar de verdade (consome um número de DPS e grava a nota)</label>
        </div>
        <div class="check">
            <button type="submit">Executar diagnóstico</button>
        </div>
    </div>
    <div class="aviso">
        Sem marcar <strong>“enviar de verdade”</strong> nada é transmitido: o DPS é montado e assinado apenas para
        conferência. Os testes 1 a 4 rodam mesmo sem informar uma O.S.
    </div>
</form>

<?php if ($rodar): ?>
<?php
/* =====================================================================
 * 1. CONFIGURAÇÃO E CERTIFICADO
 * ================================================================== */
try {
    $cfg = nfse_config(true);

    $amb = ($cfg['ambiente'] === '1') ? 'PRODUÇÃO' : 'Homologação (produção restrita)';
    $base = ($cfg['ambiente'] === '1')
        ? 'https://sefin.nfse.gov.br/SefinNacional'
        : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';

    $txt = "Ambiente               : {$amb}\n"
         . "Endpoint               : {$base}\n"
         . "Município (IBGE)       : " . ($cfg['cod_municipio'] ?: '(não informado)') . "\n"
         . "Prestador              : " . ($cfg['prest_tipo'] . ' ' . $cfg['prest_doc']) . "\n"
         . "Inscrição municipal    : " . ($cfg['prest_im'] ?: '(não informada)') . "\n"
         . "Série / último nº DPS  : " . $cfg['serie_dps'] . ' / ' . $cfg['ultimo_numero_dps'] . "\n"
         . "Código de tributação   : " . $cfg['ctrib_nac'] . "\n"
         . "Alíquota ISS           : " . $cfg['aliquota_iss'] . "%\n"
         . "Regime especial        : " . $cfg['reg_esp_trib'] . " (4 = notário/registrador)\n"
         . "Simples Nacional       : " . $cfg['op_simp_nac'] . " (1 = não optante)\n"
         . "Modo de emissão        : " . $cfg['modo_emissao'] . "\n"
         . "Emissão automática     : " . (!empty($cfg['emissao_automatica']) ? 'sim' : 'não') . "\n";

    $nivel = 'ok';
    $resumo = 'Configuração carregada.';

    if (!empty($cfg['cert_validade'])) {
        $venc = strtotime($cfg['cert_validade']);
        $dias = (int) floor(($venc - time()) / 86400);
        $txt .= "\nCertificado            : " . ($cfg['cert_titular'] ?: $cfg['cert_nome']) . "\n"
              . "Validade               : " . date('d/m/Y H:i', $venc) . "  ({$dias} dia(s))\n";

        if ($dias < 0) {
            $nivel = 'erro';
            $resumo = '<strong>Certificado A1 VENCIDO.</strong> Enquanto não for substituído, nenhuma emissão passa.';
        } elseif ($dias <= 15) {
            $nivel = 'alerta';
            $resumo = "Certificado A1 vence em {$dias} dia(s) — providencie a renovação.";
        }
    } else {
        $txt .= "\nCertificado            : (validade não registrada)\n";
        $nivel = 'alerta';
        $resumo = 'A validade do certificado não está registrada na configuração.';
    }

    d_bloco('1. Configuração e certificado', $nivel, $resumo, d_h($txt));
} catch (Throwable $e) {
    d_bloco('1. Configuração e certificado', 'erro', 'Falha ao ler a configuração.', d_h($e->getMessage()));
    $cfg = null;
}

/* =====================================================================
 * 2. HISTÓRICO: DESDE QUANDO ESTÁ FALHANDO
 * ================================================================== */
try {
    $pdo = nfse_pdo();

    $hist = $pdo->query(
        "SELECT DATE(criado_em) AS dia,
                SUM(status = 'autorizada') AS autorizadas,
                SUM(status = 'rejeitada')  AS rejeitadas,
                COUNT(*)                   AS total
           FROM nfse_notas
          WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY DATE(criado_em)
          ORDER BY dia DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $tab = '';
    $ultimoDiaOk = null;
    $primeiroDiaRuim = null;

    if ($hist) {
        $tab = '<table><tr><th>Dia</th><th>Autorizadas</th><th>Rejeitadas</th><th>Total</th></tr>';
        foreach ($hist as $h) {
            $tab .= '<tr><td>' . date('d/m/Y', strtotime($h['dia'])) . '</td>'
                  . '<td>' . (int) $h['autorizadas'] . '</td>'
                  . '<td>' . (int) $h['rejeitadas'] . '</td>'
                  . '<td>' . (int) $h['total'] . '</td></tr>';
        }
        $tab .= '</table>';

        // Percorre do mais antigo para o mais novo
        foreach (array_reverse($hist) as $h) {
            if ((int) $h['autorizadas'] > 0) { $ultimoDiaOk = $h['dia']; }
            if ((int) $h['rejeitadas'] > 0 && $primeiroDiaRuim === null
                && ($ultimoDiaOk !== null || (int) $h['autorizadas'] === 0)) {
                $primeiroDiaRuim = $h['dia'];
            }
        }
    }

    $resumo = 'Sem emissões nos últimos 30 dias.';
    $nivel  = 'info';

    if ($hist) {
        $nivel  = 'info';
        $resumo = 'Última autorização com sucesso: <strong>'
                . ($ultimoDiaOk ? date('d/m/Y', strtotime($ultimoDiaOk)) : 'nenhuma nos últimos 30 dias')
                . '</strong>. Se as autorizações param de um dia para o outro e as rejeições passam a ser 100%, '
                . 'a causa é externa (mudança de leiaute ou indisponibilidade), não a O.S. em si.';
    }

    // Últimas rejeições, com a mensagem completa
    $rej = $pdo->query(
        "SELECT id, ordem_servico_id, numero_dps, criado_em, mensagem
           FROM nfse_notas
          WHERE status = 'rejeitada'
          ORDER BY id DESC
          LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    $det = '';
    foreach ($rej as $r) {
        $det .= '#' . $r['id'] . '  O.S. ' . $r['ordem_servico_id']
              . '  DPS ' . $r['numero_dps']
              . '  ' . date('d/m/Y H:i', strtotime($r['criado_em'])) . "\n"
              . $r['mensagem'] . "\n\n" . str_repeat('-', 70) . "\n\n";
    }

    d_bloco('2. Histórico de emissões (30 dias)', $nivel, $resumo . $tab, $det ? d_h($det) : '');
} catch (Throwable $e) {
    d_bloco('2. Histórico de emissões', 'erro', 'Falha ao consultar o histórico.', d_h($e->getMessage()));
}

/* =====================================================================
 * 3. CONECTIVIDADE COM A SEFIN (consulta de nota já autorizada)
 * ================================================================== */
$pfx = null; $senhaCert = '';
try {
    if ($cfg && !empty($cfg['cert_blob'])) {
        $ctx = nfse_context($cfg);
        $pfx = $ctx->certificateContent;
        $senhaCert = (string) $ctx->certificatePassword;
    }
} catch (Throwable $e) {
    d_bloco('3. Conectividade com a SEFIN', 'erro',
        'Não foi possível preparar o certificado para o teste.', d_h($e->getMessage()));
}

if ($pfx !== null) {
    try {
        $baseUrl = ($cfg['ambiente'] === '1')
            ? 'https://sefin.nfse.gov.br/SefinNacional'
            : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';

        $chave = nfse_pdo()->query(
            "SELECT chave_acesso FROM nfse_notas
              WHERE status = 'autorizada' AND chave_acesso IS NOT NULL AND chave_acesso <> ''
              ORDER BY id DESC LIMIT 1"
        )->fetchColumn();

        if ($chave) {
            $r = d_http('GET', $baseUrl . '/nfse/' . rawurlencode($chave), null, $pfx, $senhaCert);

            if ($r['status'] === 200) {
                $nivel = 'ok';
                $resumo = 'A SEFIN respondeu <strong>200</strong> na consulta. TLS, certificado e serviço de '
                        . 'consulta estão de pé — o problema está no <em>conteúdo</em> do DPS enviado ou '
                        . 'especificamente no serviço de recepção.';
            } elseif ($r['status'] >= 500) {
                $nivel = 'erro';
                $resumo = 'A consulta também devolveu <strong>' . $r['status'] . '</strong>. '
                        . 'Isso aponta para <strong>indisponibilidade do ambiente nacional</strong>, e não para o '
                        . 'seu XML. Nesse caso a emissão volta sozinha quando a SEFIN normalizar.';
            } elseif (in_array($r['status'], [401, 403], true)) {
                $nivel = 'erro';
                $resumo = 'A SEFIN devolveu <strong>' . $r['status'] . '</strong> — problema de '
                        . '<strong>certificado ou credenciamento</strong> (certificado vencido, revogado, '
                        . 'ou CNPJ sem convênio ativo no município).';
            } else {
                $nivel = 'alerta';
                $resumo = 'A consulta devolveu HTTP ' . $r['status'] . '. Veja o corpo abaixo.';
            }

            d_bloco('3. Conectividade com a SEFIN (consulta da chave ' . substr($chave, 0, 12) . '…)',
                $nivel, $resumo, d_formatar_resposta($r));
        } else {
            d_bloco('3. Conectividade com a SEFIN', 'info',
                'Nenhuma nota autorizada no banco para usar como referência de consulta. '
                . 'O teste 4 (convênio) ainda vale.');
        }
    } catch (Throwable $e) {
        d_bloco('3. Conectividade com a SEFIN', 'erro', 'Falha no teste.', d_h($e->getMessage()));
    }
}

/* =====================================================================
 * 4. CONVÊNIO DO MUNICÍPIO (ADN)
 * ================================================================== */
try {
    $res = nfse_testar_convenio();
    d_bloco('4. Convênio do município no ADN', 'ok',
        'O município ' . d_h($cfg['cod_municipio']) . ' respondeu — convênio ativo e certificado aceito pelo ADN.',
        d_h(json_encode($res['parametros'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
} catch (Throwable $e) {
    d_bloco('4. Convênio do município no ADN', 'erro',
        'O ADN não respondeu como esperado. Se este teste falha e o teste 3 também, é ambiente nacional fora do ar.',
        d_h($e->getMessage()));
}

/* =====================================================================
 * 5. MONTAGEM E ASSINATURA DO DPS (simulação)
 * ================================================================== */
$payloadB64 = null;
$xmlAssinado = null;
$montado = null;
$apuracao = null;

if ($osId > 0 && $cfg && $pfx !== null) {
    try {
        $apuracao = nfse_apurar_os($osId, $cfg);

        $resumoAp = "Totalmente liquidada : " . ($apuracao['totalmente_liquidada'] ? 'sim' : 'NÃO') . "\n"
                  . "Valor do serviço     : R$ " . number_format($apuracao['valor_servico'], 2, ',', '.') . "\n"
                  . "Alíquota             : " . $apuracao['aliquota'] . "%\n"
                  . "Itens                : " . count($apuracao['itens']) . "\n";

        if (!$apuracao['totalmente_liquidada']) {
            d_bloco('5. Apuração da O.S. ' . $osId, 'alerta',
                'A O.S. ainda não está totalmente liquidada — a emissão normal seria recusada antes de chegar à SEFIN.',
                d_h($resumoAp));
        } else {
            d_bloco('5. Apuração da O.S. ' . $osId, 'ok', 'Apuração concluída.', d_h($resumoAp));
        }

        // Número de DPS: na simulação apenas espia o próximo, sem consumir.
        $proximo = (int) nfse_pdo()->query('SELECT ultimo_numero_dps FROM nfse_config WHERE id = 1')->fetchColumn() + 1;

        $montado = nfse_montar_dps($cfg, $apuracao, $proximo, null);

        nfse_autoload();
        $builder = new \Nfse\Xml\DpsXmlBuilder;
        $xml     = $builder->build($montado['dps']);

        $cert    = \Nfse\Signer\Certificate::fromContent($pfx, $senhaCert);
        $signer  = new \Nfse\Signer\XmlSigner($cert);
        $xmlAssinado = $signer->sign($xml, 'infDPS');

        $gz = gzencode($xmlAssinado);
        $payloadB64 = base64_encode($gz);

        // Formata o XML para leitura
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        @$dom->loadXML($xmlAssinado);
        $bonito = $dom->saveXML();

        $medidas = "ID do DPS       : " . $montado['id_dps'] . "\n"
                 . "Número do DPS   : {$proximo}  (simulado; ainda não consumido)\n"
                 . "XML assinado    : " . strlen($xmlAssinado) . " bytes\n"
                 . "Após gzip       : " . strlen($gz) . " bytes\n"
                 . "Base64 (envio)  : " . strlen($payloadB64) . " caracteres\n"
                 . "Tem <Signature> : " . (strpos($xmlAssinado, '<Signature') !== false ? 'sim' : 'NÃO') . "\n"
                 . "versao do DPS   : " . (preg_match('/<DPS[^>]*versao="([^"]+)"/', $xmlAssinado, $__mv) ? $__mv[1] : '?') . "\n"
                 . "Tem grupo IBSCBS: " . (strpos($xmlAssinado, 'IBSCBS') !== false ? 'sim' : 'não') . "\n"
                 . "Tem grupo <toma>: " . (strpos($xmlAssinado, '<toma>') !== false ? 'SIM' : 'não') . "\n";

        d_bloco('6. DPS montado e assinado (não enviado)', 'ok',
            'Este é exatamente o XML que sairia para a SEFIN.',
            d_h($medidas) . '<pre>' . d_h($bonito) . '</pre>');
    } catch (Throwable $e) {
        d_bloco('6. DPS montado e assinado', 'erro',
            'Falhou antes mesmo de sair para a rede — o problema é local.',
            d_h($e->getMessage() . "\n\n" . $e->getTraceAsString()));
    }
} elseif ($osId <= 0) {
    d_bloco('5–6. Simulação do DPS', 'info',
        'Informe o número de uma O.S. totalmente liquidada para montar o DPS e ver o XML.');
}

/* =====================================================================
 * 7. ENVIO REAL COM CAPTURA BRUTA
 * ================================================================== */
if ($enviar && $payloadB64 !== null && $montado !== null) {
    try {
        $pdo = nfse_pdo();

        // Agora sim consome um número de DPS de verdade e remonta,
        // para que o Id assinado corresponda ao número gravado.
        $numero  = nfse_proximo_numero_dps($pdo);
        $montado = nfse_montar_dps($cfg, $apuracao, $numero, null);

        $builder = new \Nfse\Xml\DpsXmlBuilder;
        $cert    = \Nfse\Signer\Certificate::fromContent($pfx, $senhaCert);
        $signer  = new \Nfse\Signer\XmlSigner($cert);
        $xmlAssinado = $signer->sign($builder->build($montado['dps']), 'infDPS');
        $payloadB64  = base64_encode(gzencode($xmlAssinado));

        $ins = $pdo->prepare(
            "INSERT INTO nfse_notas
             (ordem_servico_id, ambiente, serie, numero_dps, id_dps, status,
              valor_servico, valor_reducao, base_calculo, aliquota, valor_iss,
              tomador_doc, tomador_nome, discriminacao, xml_dps, criado_em, criado_por)
             VALUES (:os, :amb, :serie, :num, :iddps, 'processando',
                     :vs, :vr, :bc, :aliq, :iss, :tdoc, :tnome, :disc, :xml, NOW(), :usr)"
        );
        $ins->execute([
            ':os'    => $osId,
            ':amb'   => $cfg['ambiente'],
            ':serie' => $cfg['serie_dps'],
            ':num'   => $numero,
            ':iddps' => $montado['id_dps'],
            ':vs'    => $montado['valor_servico'],
            ':vr'    => $montado['valor_reducao'],
            ':bc'    => $montado['base_calculo'],
            ':aliq'  => $apuracao['aliquota'],
            ':iss'   => $montado['valor_iss'],
            ':tdoc'  => $montado['tomador_doc'],
            ':tnome' => $montado['tomador_nome'],
            ':disc'  => $montado['discriminacao'],
            ':xml'   => $xmlAssinado,
            ':usr'   => ($_SESSION['username'] ?? 'diagnostico'),
        ]);
        $notaId = (int) $pdo->lastInsertId();

        $baseUrl = ($cfg['ambiente'] === '1')
            ? 'https://sefin.nfse.gov.br/SefinNacional'
            : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';

        $r = d_http('POST', $baseUrl . '/nfse',
            json_encode(['dpsXmlGZipB64' => $payloadB64]), $pfx, $senhaCert);

        $decodificado = json_decode($r['body'], true);

        if ($r['status'] === 200 && is_array($decodificado) && !empty($decodificado['nfseXmlGZipB64'])) {
            $nfseXml = gzdecode(base64_decode($decodificado['nfseXmlGZipB64']));
            $parser  = new \Nfse\Xml\NfseXmlParser;
            $nota    = $parser->parse($nfseXml);
            $chaveNova = nfse_chave50($nota->infNfse->id ?? null) ?: null;

            $pdo->prepare(
                "UPDATE nfse_notas
                    SET status='autorizada', chave_acesso=:c, numero_nfse=:n,
                        cod_verificacao=:cv, xml_nfse=:x, mensagem=NULL
                  WHERE id=:id"
            )->execute([
                ':c'  => $chaveNova,
                ':n'  => $nota->infNfse->numeroNfse ?? null,
                ':cv' => $nota->infNfse->codigoVerificacao ?? null,
                ':x'  => $nfseXml,
                ':id' => $notaId,
            ]);

            nfse_log('emissao', "NFS-e autorizada via diagnóstico. Chave: {$chaveNova}", 'info', $osId, $notaId);

            d_bloco('7. Envio real', 'ok',
                'Autorizada. Chave <code>' . d_h($chaveNova) . '</code> — nota #' . $notaId . ' gravada.',
                d_formatar_resposta($r));
        } else {
            $msg = 'HTTP ' . $r['status'] . ' — ' . ($r['body'] !== '' ? $r['body'] : $r['erro']);
            $pdo->prepare("UPDATE nfse_notas SET status='rejeitada', mensagem=:m WHERE id=:id")
                ->execute([':m' => mb_substr($msg, 0, 4000, 'UTF-8'), ':id' => $notaId]);

            nfse_log('emissao', 'Falha (diagnóstico): ' . $msg, 'error', $osId, $notaId);

            $dica = '';
            if ($r['status'] >= 500) {
                $dica = 'Um 500 genérico é uma <strong>exceção não tratada no servidor da SEFIN</strong> — '
                      . 'ele não valida e devolve erro estruturado, ele simplesmente quebra. As causas típicas são '
                      . 'indisponibilidade do ambiente ou leiaute recusado pela camada de recepção. '
                      . 'Compare com o resultado do teste 3: se a consulta respondeu 200 e só a recepção dá 500, '
                      . 'é o leiaute; se ambos dão 500, é o ambiente.';
            } elseif ($r['status'] === 400 && is_array($decodificado) && !empty($decodificado['erros'])) {
                $dica = 'Rejeição estruturada — os códigos abaixo dizem exatamente qual campo está errado.';
            }

            d_bloco('7. Envio real', 'erro',
                'Não autorizada (nota #' . $notaId . ' marcada como rejeitada). ' . $dica,
                d_formatar_resposta($r));
        }
    } catch (Throwable $e) {
        d_bloco('7. Envio real', 'erro', 'Falha durante o envio.',
            d_h($e->getMessage() . "\n\n" . $e->getTraceAsString()));
    }
} elseif ($enviar) {
    d_bloco('7. Envio real', 'alerta',
        'O envio foi solicitado, mas o DPS não pôde ser montado — corrija os itens acima antes.');
}
?>
<?php endif; ?>

</div>
</body>
</html>
