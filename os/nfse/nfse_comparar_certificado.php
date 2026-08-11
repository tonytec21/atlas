<?php
/**
 * ATLAS O.S. — Comparação de certificados de assinatura
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-comparar-certificado
 *
 * Por que esta tela existe
 * -----------------------
 * O XML da última NFS-e autorizada guarda, dentro da assinatura do DPS,
 * o certificado que a assinou. Isso permite responder a pergunta mais
 * útil de todas quando a emissão para de funcionar de um dia para o
 * outro: "o certificado que está assinando agora é o mesmo que assinava
 * quando dava certo?"
 *
 * Se não for, e nada mais tiver mudado, achamos a causa — porque a
 * autenticação TLS e a assinatura do XML usam o mesmo certificado, mas
 * são validadas em momentos diferentes pela SEFIN. Um certificado pode
 * ser aceito no handshake (a consulta responde 200) e ainda assim
 * derrubar a recepção da DPS, que é onde a assinatura é conferida.
 *
 * Acesse: .../os/nfse/nfse_comparar_certificado.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

date_default_timezone_set('America/Sao_Paulo');

function cc_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/** Converte o Base64 do <X509Certificate> em PEM. */
function cc_pem(string $b64): string
{
    return "-----BEGIN CERTIFICATE-----\n"
         . chunk_split(preg_replace('/\s+/', '', $b64), 64, "\n")
         . "-----END CERTIFICATE-----\n";
}

/** Resume um certificado PEM nos campos que interessam à comparação. */
function cc_resumir(?string $pem): ?array
{
    if (!$pem) { return null; }

    $info = @openssl_x509_parse($pem);
    if (!$info) { return null; }

    $impressao = null;
    if (function_exists('openssl_x509_fingerprint')) {
        $impressao = strtoupper(openssl_x509_fingerprint($pem, 'sha1'));
        $impressao = trim(chunk_split($impressao, 2, ':'), ':');
    }

    return [
        'titular'   => $info['subject']['CN'] ?? '(?)',
        'ac'        => $info['issuer']['CN'] ?? '(?)',
        'serial'    => $info['serialNumberHex'] ?? ($info['serialNumber'] ?? '(?)'),
        'de'        => $info['validFrom_time_t'] ?? null,
        'ate'       => $info['validTo_time_t'] ?? null,
        'impressao' => $impressao,
    ];
}

/** Extrai o primeiro <X509Certificate> de um XML assinado. */
function cc_extrair_x509(?string $xml): ?string
{
    if (!$xml) { return null; }
    if (preg_match('~<X509Certificate>(.*?)</X509Certificate>~s', $xml, $m)) {
        return cc_pem($m[1]);
    }
    return null;
}

$erro = null;
$atual = null; $anterior = null; $linha = [];

try {
    nfse_migrar();
    $cfg = nfse_config(true);
    $pdo = nfse_pdo();

    /* ---- Certificado instalado agora ---- */
    $ctx = nfse_context($cfg);
    $pfx = $ctx->certificateContent;
    $bag = [];
    if (@openssl_pkcs12_read($pfx, $bag, (string) $ctx->certificatePassword)) {
        $atual = cc_resumir($bag['cert'] ?? null);
    }

    /* ---- Certificado que assinou a última nota autorizada ---- */
    $ult = $pdo->query(
        "SELECT id, ordem_servico_id, numero_dps, criado_em, xml_nfse, xml_dps
           FROM nfse_notas
          WHERE status = 'autorizada'
            AND (xml_nfse IS NOT NULL OR xml_dps IS NOT NULL)
          ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if ($ult) {
        $anterior = cc_resumir(cc_extrair_x509($ult['xml_nfse'] ?: $ult['xml_dps']));
        $linha['ultima_ok'] = $ult;
    }

    /* ---- Primeira rejeição depois da última autorização ---- */
    if ($ult) {
        $st = $pdo->prepare(
            "SELECT id, ordem_servico_id, numero_dps, criado_em, http_status
               FROM nfse_notas
              WHERE status = 'rejeitada' AND id > :id
              ORDER BY id ASC LIMIT 1"
        );
        $st->execute([':id' => $ult['id']]);
        $linha['primeira_falha'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$mesmo = ($atual && $anterior && $atual['impressao'] === $anterior['impressao']);

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificado de assinatura — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --erro:#dc2626; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.55; }
    .wrap{ max-width:1000px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 20px; color:var(--cinza); }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    table{ border-collapse:collapse; width:100%; font-size:13px; }
    th,td{ border:1px solid var(--borda); padding:8px 10px; text-align:left; vertical-align:top; }
    th{ background:#f1f5f9; width:22%; }
    td.dif{ background:#fef2f2; font-weight:600; }
    .veredito{ border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .v-erro{ background:#fef2f2; border:1px solid var(--erro); }
    .v-ok{ background:#dcfce7; border:1px solid var(--ok); }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Certificado de assinatura</h1>
<p>Compara o certificado instalado agora com o que assinou a última NFS-e que a SEFIN autorizou.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo cc_h($erro); ?></div>
<?php elseif (!$atual): ?>
    <div class="painel">Não consegui ler o certificado instalado. Confira a senha do A1 na configuração.</div>
<?php elseif (!$anterior): ?>
    <div class="painel">
        Não há nota autorizada com XML guardado para comparar. O XML do DPS só passou a ser
        gravado na versão mais recente do módulo — a partir da próxima autorização esta tela funciona.
    </div>
<?php else: ?>

    <?php if (!$mesmo): ?>
        <div class="veredito v-erro">
            <h3 style="margin:0 0 6px">São certificados diferentes.</h3>
            <p style="margin:0">
                A última NFS-e autorizada foi assinada por um certificado que <strong>não é</strong> o que
                está instalado agora. Como a autenticação TLS e a assinatura do XML usam o mesmo arquivo
                mas são conferidas em momentos distintos, um certificado pode passar no handshake — e a
                consulta responder 200 — e ainda assim derrubar a recepção da DPS, que é onde a
                assinatura é validada.
            </p>
        </div>
    <?php else: ?>
        <div class="veredito v-ok">
            <h3 style="margin:0 0 6px">É o mesmo certificado.</h3>
            <p style="margin:0">
                O certificado não mudou desde a última autorização. A causa da falha está em outro lugar —
                volte ao <code>nfse_lab_dps.php</code> para isolar o conteúdo do DPS.
            </p>
        </div>
    <?php endif; ?>

    <div class="painel">
        <table>
            <tr>
                <th></th>
                <th>Assinou a última nota autorizada</th>
                <th>Instalado agora</th>
            </tr>
            <?php
            $campos = [
                'titular'   => 'Titular',
                'ac'        => 'Autoridade certificadora',
                'serial'    => 'Número de série',
                'impressao' => 'Impressão digital (SHA-1)',
            ];
            foreach ($campos as $k => $rot) {
                $dif = ($anterior[$k] !== $atual[$k]) ? ' class="dif"' : '';
                echo '<tr><th>' . cc_h($rot) . '</th>'
                   . '<td' . $dif . '>' . cc_h($anterior[$k]) . '</td>'
                   . '<td' . $dif . '>' . cc_h($atual[$k]) . '</td></tr>';
            }
            $difD = ($anterior['de'] !== $atual['de']) ? ' class="dif"' : '';
            echo '<tr><th>Emitido em</th>'
               . '<td' . $difD . '>' . date('d/m/Y', $anterior['de']) . '</td>'
               . '<td' . $difD . '>' . date('d/m/Y', $atual['de']) . '</td></tr>';
            $difA = ($anterior['ate'] !== $atual['ate']) ? ' class="dif"' : '';
            echo '<tr><th>Expira em</th>'
               . '<td' . $difA . '>' . date('d/m/Y', $anterior['ate'])
               . ' <small>(' . (int) floor(($anterior['ate'] - time()) / 86400) . ' dias)</small></td>'
               . '<td' . $difA . '>' . date('d/m/Y', $atual['ate'])
               . ' <small>(' . (int) floor(($atual['ate'] - time()) / 86400) . ' dias)</small></td></tr>';
            ?>
        </table>
    </div>

    <div class="painel">
        <h3 style="margin:0 0 8px">Quando virou</h3>
        <table>
            <?php if (!empty($linha['ultima_ok'])): $u = $linha['ultima_ok']; ?>
            <tr><th>Última autorizada</th>
                <td colspan="2">DPS <?php echo (int) $u['numero_dps']; ?> ·
                    O.S. <?php echo (int) $u['ordem_servico_id']; ?> ·
                    <?php echo date('d/m/Y H:i', strtotime($u['criado_em'])); ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($linha['primeira_falha'])): $f = $linha['primeira_falha']; ?>
            <tr><th>Primeira rejeição depois dela</th>
                <td colspan="2">DPS <?php echo (int) $f['numero_dps']; ?> ·
                    O.S. <?php echo (int) $f['ordem_servico_id']; ?> ·
                    <?php echo date('d/m/Y H:i', strtotime($f['criado_em'])); ?>
                    <?php echo $f['http_status'] ? ' · HTTP ' . (int) $f['http_status'] : ''; ?></td></tr>
            <?php endif; ?>
        </table>
        <p style="margin:12px 0 0; color:var(--cinza)">
            A troca do certificado aconteceu em algum ponto entre essas duas linhas. Se o intervalo for
            de poucas horas, é praticamente certo que uma coisa causou a outra.
        </p>
    </div>

    <?php if (!$mesmo && $anterior['ate'] > time()): ?>
    <div class="painel">
        <h3 style="margin:0 0 8px">O caminho mais curto</h3>
        <p style="margin:0 0 8px">
            O certificado antigo ainda é válido por
            <strong><?php echo (int) floor(($anterior['ate'] - time()) / 86400); ?> dias</strong>
            (até <?php echo date('d/m/Y', $anterior['ate']); ?>). Reinstalá-lo na configuração do módulo
            é um teste de um passo: se a emissão voltar, a causa está confirmada e o cartório volta a
            emitir hoje mesmo, com tempo de sobra para tratar o certificado novo sem pressa.
        </p>
        <p style="margin:0; color:var(--cinza)">
            Se voltar a funcionar, o passo seguinte é descobrir por que o A1 novo é recusado — o
            candidato mais comum é o arquivo <code>.pfx</code> ter sido exportado sem a cadeia completa
            da AC, ou conter mais de um par de chaves.
        </p>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
