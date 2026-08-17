<?php
/**
 * ATLAS O.S. — Teste de emissão com certificado alternativo
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-teste-certificado
 *
 * Para que serve
 * --------------
 * Quando a emissão para de funcionar logo depois de o A1 ser trocado, a
 * pergunta é simples: volta a funcionar com o certificado anterior?
 *
 * Responder isso trocando o certificado na configuração é arriscado —
 * se der errado, ficou-se sem o novo e sem o antigo, no meio do
 * expediente. Esta tela responde a mesma pergunta sem tocar em nada:
 * o .pfx enviado aqui é usado só nesta requisição, para assinar e
 * transmitir um DPS, e é descartado em seguida.
 *
 * O que ela NÃO faz: não grava o certificado, não altera a configuração
 * e não interfere na emissão normal.
 *
 * Acesse: .../os/nfse/nfse_teste_certificado.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(180);

function tc_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/**
 * Igual a nfse_http_sefin(), mas com um PFX avulso em vez do que está
 * gravado na configuração.
 */
function tc_post_sefin(array $cfg, string $pfx, string $senha, array $corpo): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'nfse_tst_');
    file_put_contents($tmp, $pfx);

    $cab = [];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => rtrim(nfse_sefin_base($cfg), '/') . '/nfse',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($corpo, JSON_UNESCAPED_SLASHES),
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
        CURLOPT_HEADERFUNCTION => function ($ch, $l) use (&$cab) {
            $t = trim($l); if ($t !== '') { $cab[] = $t; } return strlen($l);
        },
    ]);

    $ini  = microtime(true);
    $body = curl_exec($ch);
    $r = [
        'status'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'body'    => $body === false ? '' : (string) $body,
        'headers' => $cab,
        'erro'    => curl_error($ch),
        'errno'   => curl_errno($ch),
        'ms'      => (int) round((microtime(true) - $ini) * 1000),
    ];
    curl_close($ch);
    @unlink($tmp);

    return $r;
}

$resultado = null;
$erro = null;
$osId = isset($_POST['os_id']) ? (int) $_POST['os_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($osId <= 0) {
            throw new RuntimeException('Informe o número da O.S.');
        }
        if (empty($_FILES['pfx']['tmp_name']) || !is_uploaded_file($_FILES['pfx']['tmp_name'])) {
            throw new RuntimeException('Envie o arquivo .pfx do certificado a testar.');
        }

        $senha = (string) ($_POST['senha'] ?? '');
        $pfx   = (string) file_get_contents($_FILES['pfx']['tmp_name']);
        @unlink($_FILES['pfx']['tmp_name']);

        // Reempacota se vier com cifra legada, como no fluxo normal.
        [$pfx] = nfse_normalizar_pfx($pfx, $senha);

        $bag = [];
        if (!openssl_pkcs12_read($pfx, $bag, $senha)) {
            throw new RuntimeException('Não consegui abrir o .pfx — confira a senha.');
        }

        $info = openssl_x509_parse($bag['cert']);
        $impressao = trim(chunk_split(strtoupper(openssl_x509_fingerprint($bag['cert'], 'sha1')), 2, ':'), ':');

        nfse_migrar();
        $cfg = nfse_config(true);
        $pdo = nfse_pdo();

        $apuracao = nfse_apurar_os($osId, $cfg);
        if (!$apuracao['totalmente_liquidada']) {
            throw new RuntimeException('A O.S. ' . $osId . ' não está totalmente liquidada.');
        }
        if ($apuracao['valor_servico'] <= 0) {
            throw new RuntimeException('A O.S. ' . $osId . ' não tem valor tributável.');
        }

        $numero  = nfse_proximo_numero_dps($pdo);
        $montado = nfse_montar_dps($cfg, $apuracao, $numero, null);

        // Assina com o certificado ENVIADO, não com o instalado.
        nfse_autoload();
        $cert   = \Nfse\Signer\Certificate::fromContent($pfx, $senha);
        $signer = new \Nfse\Signer\XmlSigner($cert);
        $xml    = $signer->sign((new \Nfse\Xml\DpsXmlBuilder)->build($montado['dps']), 'infDPS');

        $r   = tc_post_sefin($cfg, $pfx, $senha, ['dpsXmlGZipB64' => base64_encode(gzencode($xml))]);
        $dec = json_decode($r['body'], true);
        $autorizou = ($r['status'] >= 200 && $r['status'] < 300
            && is_array($dec) && !empty($dec['nfseXmlGZipB64']));

        // Registra a tentativa como qualquer outra
        $ins = $pdo->prepare(
            "INSERT INTO nfse_notas
             (ordem_servico_id, ambiente, serie, numero_dps, id_dps, status,
              valor_servico, valor_reducao, base_calculo, aliquota, valor_iss,
              tomador_doc, tomador_nome, discriminacao, xml_dps, http_status,
              tentativas, ultima_tentativa, criado_em, criado_por)
             VALUES (:os, :amb, :serie, :num, :iddps, :st, :vs, :vr, :bc, :aliq, :iss,
                     :tdoc, :tnome, :disc, :xml, :http, 1, NOW(), NOW(), :usr)"
        );
        $ins->execute([
            ':os' => $osId, ':amb' => $cfg['ambiente'], ':serie' => $cfg['serie_dps'],
            ':num' => $numero, ':iddps' => $montado['id_dps'],
            ':st' => $autorizou ? 'autorizada' : 'rejeitada',
            ':vs' => $montado['valor_servico'], ':vr' => $montado['valor_reducao'],
            ':bc' => $montado['base_calculo'], ':aliq' => $apuracao['aliquota'],
            ':iss' => $montado['valor_iss'], ':tdoc' => $montado['tomador_doc'],
            ':tnome' => $montado['tomador_nome'], ':disc' => $montado['discriminacao'],
            ':xml' => $xml, ':http' => $r['status'] ?: null,
            ':usr' => ($_SESSION['username'] ?? 'teste-certificado'),
        ]);
        $notaId = (int) $pdo->lastInsertId();

        $chave = null;
        if ($autorizou) {
            $nfseXml = gzdecode(base64_decode($dec['nfseXmlGZipB64']));
            $nota    = (new \Nfse\Xml\NfseXmlParser)->parse($nfseXml);
            $chave   = nfse_chave50($nota->infNfse->id ?? null) ?: null;

            $pdo->prepare(
                "UPDATE nfse_notas SET chave_acesso=:c, numero_nfse=:n, cod_verificacao=:cv,
                        xml_nfse=:x, mensagem='Autorizada no teste de certificado alternativo.'
                  WHERE id=:id"
            )->execute([
                ':c' => $chave, ':n' => $nota->infNfse->numeroNfse ?? null,
                ':cv' => $nota->infNfse->codigoVerificacao ?? null, ':x' => $nfseXml, ':id' => $notaId,
            ]);
            nfse_log('emissao', 'Teste de certificado: AUTORIZADA com o A1 de série ' . ($info['serialNumberHex'] ?? '?'),
                'info', $osId, $notaId);
        } else {
            $pdo->prepare("UPDATE nfse_notas SET mensagem=:m WHERE id=:id")
                ->execute([':m' => mb_substr('Teste de certificado: ' . nfse_resumir_resposta($r), 0, 4000, 'UTF-8'), ':id' => $notaId]);
        }

        $resultado = [
            'ok' => $autorizou, 'r' => $r, 'chave' => $chave, 'nota' => $notaId,
            'numero' => $numero, 'impressao' => $impressao,
            'titular' => $info['subject']['CN'] ?? '?',
            'serial'  => $info['serialNumberHex'] ?? '?',
            'de'      => $info['validFrom_time_t'] ?? null,
            'ate'     => $info['validTo_time_t'] ?? null,
        ];
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teste de certificado — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --erro:#dc2626; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.55; }
    .wrap{ max-width:960px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 20px; color:var(--cinza); }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    label{ font-weight:600; display:block; margin-bottom:4px; }
    input[type=number],input[type=password],input[type=file]{ padding:8px 10px; border:1px solid var(--borda);
        border-radius:6px; font-size:14px; width:100%; max-width:340px; background:#fff; }
    .campo{ margin-bottom:14px; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:10px 22px;
            font-size:14px; font-weight:600; cursor:pointer; }
    button:hover{ background:#1e3a8a; }
    .aviso{ background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:10px 12px; color:#78350f; margin:12px 0; }
    .veredito{ border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .v-ok{ background:#dcfce7; border:1px solid var(--ok); }
    .v-erro{ background:#fef2f2; border:1px solid var(--erro); }
    pre{ background:#0f172a; color:#e2e8f0; padding:12px; border-radius:6px; overflow:auto; max-height:340px;
         font-size:12px; margin:8px 0 0; white-space:pre-wrap; word-break:break-word; }
    table{ border-collapse:collapse; width:100%; font-size:13px; }
    th,td{ border:1px solid var(--borda); padding:7px 9px; text-align:left; }
    th{ background:#f1f5f9; width:30%; }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Teste de emissão com outro certificado</h1>
<p>Usa o .pfx enviado apenas nesta requisição. A configuração do módulo não é alterada.</p>

<form class="painel" method="post" enctype="multipart/form-data">
    <div class="campo">
        <label for="os_id">Nº da O.S. (totalmente liquidada, sem nota)</label>
        <input type="number" id="os_id" name="os_id" min="1" value="<?php echo $osId ?: ''; ?>" required>
    </div>
    <div class="campo">
        <label for="pfx">Arquivo .pfx do certificado a testar</label>
        <input type="file" id="pfx" name="pfx" accept=".pfx,.p12" required>
    </div>
    <div class="campo">
        <label for="senha">Senha do certificado</label>
        <input type="password" id="senha" name="senha" autocomplete="off" required>
    </div>

    <div class="aviso">
        O arquivo é lido em memória, usado para assinar e transmitir um DPS, e apagado ao fim da
        requisição — não fica gravado em lugar nenhum. A tentativa consome um número de DPS e é
        registrada normalmente; se for autorizada, a NFS-e é válida e fica vinculada à O.S.
    </div>

    <button type="submit">Emitir com este certificado</button>
</form>

<?php if ($erro): ?>
    <div class="veredito v-erro"><strong>Falhou:</strong> <?php echo tc_h($erro); ?></div>
<?php endif; ?>

<?php if ($resultado): ?>
    <?php if ($resultado['ok']): ?>
        <div class="veredito v-ok">
            <h3 style="margin:0 0 6px">Autorizada.</h3>
            <p style="margin:0">
                Este certificado emite normalmente — chave <code><?php echo tc_h($resultado['chave']); ?></code>.
                Está confirmado que a causa da falha é o certificado, e não o leiaute nem a SEFIN.
                O passo seguinte é instalar este mesmo .pfx na configuração do módulo, em
                <em>Configuração → Certificado A1</em>, para que a emissão normal volte a funcionar.
            </p>
        </div>
    <?php else: ?>
        <div class="veredito v-erro">
            <h3 style="margin:0 0 6px">Também não passou (HTTP <?php echo (int) $resultado['r']['status']; ?>).</h3>
            <p style="margin:0">
                Se este é o certificado antigo, que emitia normalmente até a semana passada, então a
                troca do A1 não é a causa e a investigação precisa seguir por outro caminho.
            </p>
        </div>
    <?php endif; ?>

    <div class="painel">
        <h3 style="margin:0 0 10px">Certificado usado no teste</h3>
        <table>
            <tr><th>Titular</th><td><?php echo tc_h($resultado['titular']); ?></td></tr>
            <tr><th>Número de série</th><td><code><?php echo tc_h($resultado['serial']); ?></code></td></tr>
            <tr><th>Impressão digital (SHA-1)</th><td><code><?php echo tc_h($resultado['impressao']); ?></code></td></tr>
            <tr><th>Validade</th><td>
                <?php echo $resultado['de'] ? date('d/m/Y', $resultado['de']) : '?'; ?>
                até <?php echo $resultado['ate'] ? date('d/m/Y', $resultado['ate']) : '?'; ?>
            </td></tr>
            <tr><th>DPS gerado</th><td><?php echo (int) $resultado['numero']; ?> · nota #<?php echo (int) $resultado['nota']; ?></td></tr>
        </table>
    </div>

    <div class="painel">
        <h3 style="margin:0 0 6px">Resposta da SEFIN</h3>
        <pre><?php
            echo tc_h('HTTP ' . $resultado['r']['status'] . '   (' . $resultado['r']['ms'] . " ms)\n\n"
                . implode("\n", $resultado['r']['headers']) . "\n\n"
                . ($resultado['r']['body'] !== '' ? mb_substr($resultado['r']['body'], 0, 4000, 'UTF-8') : '(vazio)'));
        ?></pre>
    </div>
<?php endif; ?>

</div>
</body>
</html>
