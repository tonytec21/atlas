<?php
/**
 * ATLAS O.S. — Laboratório de variantes do DPS
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-lab-variantes
 *
 * Para que serve
 * --------------
 * O diagnóstico já mostrou que a SEFIN está de pé (a consulta responde
 * 200) e que só a RECEPÇÃO devolve 500. Quer dizer: o ambiente aceita
 * conversa, mas engasga com este DPS específico.
 *
 * O problema é que a API não diz onde engasgou. Quando o conteúdo do
 * XML é algo que o parser dela não sabe tratar, ela não devolve uma
 * rejeição catalogada — ela estoura com "An error has occurred.". É um
 * comportamento conhecido do Ambiente Nacional, relatado por várias
 * casas de software.
 *
 * Sem mensagem, o único caminho é experimental: enviar o MESMO DPS com
 * uma alteração de cada vez e ver qual delas devolve 200. Como a
 * resposta vem em ~300 ms, o que seria dias de tentativa e erro vira
 * alguns minutos.
 *
 * Segurança
 * ---------
 *  - Cada variante consome um número de DPS e grava a nota, como uma
 *    emissão normal. Buraco na sequência não é problema.
 *  - A rodada PARA no primeiro 200. Assim nunca se emite duas notas
 *    autorizadas para a mesma O.S.
 *  - Um 500 não gera nota do outro lado — mas, por precaução, antes de
 *    cada variante consulta-se a DPS anterior para garantir que ela não
 *    virou nota.
 *
 * Acesse: .../os/nfse/nfse_lab_dps.php   (restrito a administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(300);

const LAB_NS = 'http://www.sped.fazenda.gov.br/nfse';

/* =====================================================================
 * Catálogo de variantes
 *
 * A ordem importa: as primeiras são as mais prováveis, considerando o
 * que mudou no leiaute em agosto/2026 (NT 004 + tpRetPisCofins da
 * NT 007) e o que este DPS tem de incomum.
 * ================================================================== */
function lab_variantes(): array
{
    return [
        'baseline' => [
            'rotulo' => 'Como está hoje (referência)',
            'porque' => 'Confirma que a falha se repete no momento do teste. Sem isso, um 200 numa '
                      . 'variante seguinte poderia ser só a SEFIN tendo voltado ao ar.',
        ],
        'versao_101' => [
            'rotulo' => 'DPS com versao="1.01"',
            'porque' => 'Em 10/08/2026 — o dia exato em que a emissão parou — entrou em Produção o '
                      . 'CNPJ Alfanumérico, "com atualização dos schemas XML" (comunicado do Portal '
                      . 'NFS-e de 28/07/2026). Os novos esquemas publicados são da série v1.01, e o '
                      . 'Atlas ainda declara versao="1.00". Se a recepção passou a carregar só o '
                      . 'schema novo, o documento antigo não casa com contrato nenhum e a aplicação '
                      . 'estoura antes de validar — que é exatamente a cara de um 500 sem corpo.',
        ],
        'sem_toma' => [
            'rotulo' => 'Sem o grupo toma (tomador não informado)',
            'porque' => 'A principal suspeita. Os erros E0206 e E0207 mostram que a SEFIN consulta '
                      . 'o CPF do tomador no cadastro dela durante a recepção — e essa consulta é a '
                      . 'única parte da DPS que depende de um serviço externo ao processamento.',
        ],
        'sem_piscofins' => [
            'rotulo' => 'Sem o grupo tribFed/piscofins',
            'porque' => 'A NT 007 mexeu no domínio do CST de PIS/COFINS e acrescentou o campo '
                      . 'tpRetPisCofins ao grupo. Se o schema em produção não aceita mais o grupo '
                      . 'na forma antiga, removê-lo faz a nota passar.',
        ],
        'com_tpretpiscofins' => [
            'rotulo' => 'Com tpRetPisCofins = 0 no piscofins',
            'porque' => 'O leiaute exigido em produção desde 03/08/2026 é o da NT 004 acrescido '
                      . 'deste campo. Se ele virou obrigatório, é a correção mais direta.',
        ],
        'sem_cintcontrib' => [
            'rotulo' => 'Sem cIntContrib',
            'porque' => 'Campo opcional de uso interno (aqui vai "OS1376"). Campos opcionais são '
                      . 'candidatos frequentes quando o parser muda de versão.',
        ],
        'regesp_4' => [
            'rotulo' => 'regEspTrib = 4 (notário/registrador)',
            'porque' => 'A configuração atual manda 0 (nenhum). Para cartório o correto é 4, e o '
                      . 'valor influencia o cálculo do ISS pela Calculadora de Tributos da SEFIN.',
        ],
        'sem_tottrib' => [
            'rotulo' => 'Sem o grupo totTrib',
            'porque' => 'Outro grupo tocado pelas notas técnicas da Reforma. Isola a última parte '
                      . 'do bloco de tributos.',
        ],
    ];
}

/* =====================================================================
 * Transformações — aplicadas no XML ANTES da assinatura
 * ================================================================== */
function lab_aplicar(string $xml, string $variante): string
{
    if ($variante === 'baseline') {
        return $xml;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = true;
    $dom->loadXML($xml);

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('n', LAB_NS);

    $remover = function (string $caminho) use ($xp) {
        foreach ($xp->query($caminho) as $no) {
            $no->parentNode->removeChild($no);
        }
    };

    switch ($variante) {
        case 'versao_101':
            $raiz = $dom->documentElement;
            if ($raiz && $raiz->localName === 'DPS') {
                $raiz->setAttribute('versao', '1.01');
            }
            break;

        case 'sem_piscofins':
            $remover('//n:valores/n:trib/n:tribFed');
            break;

        case 'com_tpretpiscofins':
            $lista = $xp->query('//n:valores/n:trib/n:tribFed/n:piscofins');
            if ($lista->length > 0) {
                $pis = $lista->item(0);
                // Só acrescenta se ainda não existir
                if ($xp->query('n:tpRetPisCofins', $pis)->length === 0) {
                    $novo = $dom->createElementNS(LAB_NS, 'tpRetPisCofins', '0');
                    $pis->appendChild($novo);   // vem depois do CST, conforme a NT 007
                }
            }
            break;

        case 'sem_cintcontrib':
            $remover('//n:serv/n:cServ/n:cIntContrib');
            break;

        case 'regesp_4':
            $lista = $xp->query('//n:prest/n:regTrib/n:regEspTrib');
            if ($lista->length > 0) {
                $lista->item(0)->nodeValue = '4';
            }
            break;

        case 'sem_toma':
            $remover('//n:infDPS/n:toma');
            break;

        case 'sem_tottrib':
            $remover('//n:valores/n:trib/n:totTrib');
            break;
    }

    return $dom->saveXML();
}

/* =====================================================================
 * Conferência local da assinatura
 *
 * Se o XML sai daqui com assinatura inválida, o erro é nosso e nenhuma
 * variante vai passar. Vale conferir antes de sair testando.
 * ================================================================== */
function lab_conferir_assinatura(string $xmlAssinado): array
{
    $r = ['digest' => null, 'assinatura' => null, 'detalhe' => ''];

    try {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xmlAssinado);

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xp->registerNamespace('n', LAB_NS);

        $sig = $xp->query('//ds:Signature')->item(0);
        if (!$sig) {
            $r['detalhe'] = 'Não há elemento Signature no XML.';
            return $r;
        }

        $digestInformado = trim($xp->query('.//ds:Reference/ds:DigestValue', $sig)->item(0)->nodeValue ?? '');
        $valorAssinatura = trim($xp->query('.//ds:SignatureValue', $sig)->item(0)->nodeValue ?? '');
        $certB64         = trim($xp->query('.//ds:X509Certificate', $sig)->item(0)->nodeValue ?? '');
        $uri             = $xp->query('.//ds:Reference', $sig)->item(0)->getAttribute('URI');
        $idAlvo          = ltrim((string) $uri, '#');

        // --- 1) Digest: canoniza o infDPS já sem a Signature (enveloped) ---
        $copia = new DOMDocument('1.0', 'UTF-8');
        $copia->preserveWhiteSpace = true;
        $copia->loadXML($xmlAssinado);

        $xpC = new DOMXPath($copia);
        $xpC->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpC->registerNamespace('n', LAB_NS);

        foreach ($xpC->query('//ds:Signature') as $s) {
            $s->parentNode->removeChild($s);
        }

        $alvo = null;
        foreach ($xpC->query('//n:infDPS') as $no) {
            if ($no->getAttribute('Id') === $idAlvo) { $alvo = $no; break; }
        }

        if ($alvo) {
            $digestCalculado = base64_encode(sha1($alvo->C14N(false, false), true));
            $r['digest'] = hash_equals($digestInformado, $digestCalculado);
            if (!$r['digest']) {
                $r['detalhe'] .= "DigestValue informado : {$digestInformado}\n"
                               . "DigestValue calculado : {$digestCalculado}\n";
            }
        } else {
            $r['detalhe'] .= "Não achei o elemento referenciado por URI=\"{$uri}\".\n";
        }

        // --- 2) SignatureValue sobre o SignedInfo canonizado ---
        $signedInfo = $xp->query('.//ds:SignedInfo', $sig)->item(0);
        if ($signedInfo && $certB64 !== '') {
            $pem = "-----BEGIN CERTIFICATE-----\n"
                 . chunk_split(preg_replace('/\s+/', '', $certB64), 64, "\n")
                 . "-----END CERTIFICATE-----\n";

            $pub = openssl_pkey_get_public($pem);
            if ($pub) {
                $ok = openssl_verify(
                    $signedInfo->C14N(false, false),
                    base64_decode($valorAssinatura),
                    $pub,
                    OPENSSL_ALGO_SHA1
                );
                $r['assinatura'] = ($ok === 1);
            } else {
                $r['detalhe'] .= "Não consegui ler a chave pública do X509 embutido.\n";
            }
        }

        // Dados do certificado usado na assinatura
        if ($certB64 !== '') {
            $pem = "-----BEGIN CERTIFICATE-----\n"
                 . chunk_split(preg_replace('/\s+/', '', $certB64), 64, "\n")
                 . "-----END CERTIFICATE-----\n";
            $info = @openssl_x509_parse($pem);
            if ($info) {
                $r['detalhe'] .= 'Titular  : ' . ($info['subject']['CN'] ?? '?') . "\n"
                               . 'Emitido  : ' . date('d/m/Y', $info['validFrom_time_t']) . "\n"
                               . 'Expira   : ' . date('d/m/Y', $info['validTo_time_t']) . "\n";
            }
        }
    } catch (Throwable $e) {
        $r['detalhe'] .= 'Erro ao conferir: ' . $e->getMessage();
    }

    return $r;
}

/* =====================================================================
 * Execução
 * ================================================================== */
$osId       = isset($_POST['os_id']) ? (int) $_POST['os_id'] : 0;
$escolhidas = isset($_POST['variantes']) && is_array($_POST['variantes']) ? $_POST['variantes'] : [];
$executar   = ($_SERVER['REQUEST_METHOD'] === 'POST') && $osId > 0 && $escolhidas;
$simular    = isset($_POST['simular']) && $_POST['simular'] === '1';

function lab_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laboratório de variantes do DPS — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --erro:#dc2626; --alerta:#d97706; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.5; }
    .wrap{ max-width:1100px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 20px; color:var(--cinza); }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px; margin-bottom:20px; }
    label{ font-weight:600; }
    input[type=number]{ padding:8px 10px; border:1px solid var(--borda); border-radius:6px; width:180px; font-size:14px; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:10px 22px;
            font-size:14px; font-weight:600; cursor:pointer; margin-top:14px; }
    button:hover{ background:#1e3a8a; }
    .var{ border-top:1px solid var(--borda); padding:10px 0; display:flex; gap:10px; align-items:flex-start; }
    .var:first-of-type{ border-top:0; }
    .var input{ margin-top:3px; }
    .var b{ display:block; }
    .var span{ color:var(--cinza); font-size:13px; }
    .aviso{ background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:10px 12px; color:#78350f; margin:12px 0; }
    table{ border-collapse:collapse; width:100%; margin-top:8px; font-size:13px; background:#fff; }
    th,td{ border:1px solid var(--borda); padding:8px; text-align:left; vertical-align:top; }
    th{ background:#f1f5f9; }
    .pill{ display:inline-block; padding:2px 9px; border-radius:12px; font-weight:600; font-size:12px; color:#fff; }
    .p-ok{ background:var(--ok); } .p-erro{ background:var(--erro); } .p-alerta{ background:var(--alerta); }
    pre{ background:#0f172a; color:#e2e8f0; padding:12px; border-radius:6px; overflow:auto; max-height:320px;
         font-size:12px; margin:8px 0 0; white-space:pre-wrap; word-break:break-word; }
    .achado{ background:#dcfce7; border:1px solid var(--ok); border-radius:8px; padding:14px 16px; margin:16px 0; }
</style>
</head>
<body>
<div class="wrap">

<h1>Laboratório de variantes do DPS</h1>
<p>Envia o mesmo DPS com uma alteração de cada vez, para descobrir qual delas a SEFIN aceita.</p>

<form class="painel" method="post">
    <div>
        <label for="os_id">Nº da O.S. (totalmente liquidada)</label><br>
        <input type="number" id="os_id" name="os_id" min="1" value="<?php echo $osId ?: ''; ?>" required>
    </div>

    <div class="aviso">
        Cada variante consome um número de DPS e grava a nota. A rodada <strong>para no primeiro
        200</strong>, então nunca sai mais de uma nota autorizada para a mesma O.S.
        Use uma O.S. que realmente precise de nota — se uma variante der certo, a NFS-e é válida.
    </div>

    <p style="margin:14px 0 4px"><label>Variantes a testar, na ordem:</label></p>
    <?php foreach (lab_variantes() as $k => $v): ?>
        <div class="var">
            <input type="checkbox" id="v_<?php echo $k; ?>" name="variantes[]" value="<?php echo $k; ?>"
                   <?php echo (!$escolhidas || in_array($k, $escolhidas, true)) ? 'checked' : ''; ?>>
            <label for="v_<?php echo $k; ?>" style="font-weight:400">
                <b><?php echo lab_h($v['rotulo']); ?></b>
                <span><?php echo lab_h($v['porque']); ?></span>
            </label>
        </div>
    <?php endforeach; ?>

    <div class="var">
        <input type="checkbox" id="simular" name="simular" value="1" <?php echo $simular ? 'checked' : ''; ?>>
        <label for="simular" style="font-weight:400">
            <b>Só montar, sem enviar</b>
            <span>Gera e assina cada variante para conferência, sem transmitir e sem consumir número de DPS.</span>
        </label>
    </div>

    <button type="submit">Rodar</button>
</form>

<?php if ($executar): ?>
<?php
try {
    $cfg = nfse_config(true);
    nfse_migrar();

    $apuracao = nfse_apurar_os($osId, $cfg);

    if (!$apuracao['totalmente_liquidada']) {
        throw new RuntimeException('A O.S. ' . $osId . ' não está totalmente liquidada.');
    }
    if ($apuracao['valor_servico'] <= 0) {
        throw new RuntimeException('A O.S. ' . $osId . ' não tem valor tributável.');
    }

    $pdo   = nfse_pdo();
    $ctx   = nfse_context($cfg);
    $cert  = \Nfse\Signer\Certificate::fromContent($ctx->certificateContent, (string) $ctx->certificatePassword);
    $signer = new \Nfse\Signer\XmlSigner($cert);
    $builder = new \Nfse\Xml\DpsXmlBuilder;

    $catalogo  = lab_variantes();
    $resultados = [];
    $vencedora = null;
    $conferencia = null;

    foreach ($escolhidas as $variante) {
        if (!isset($catalogo[$variante])) { continue; }

        // Número: espia sem consumir na simulação; consome de verdade no envio.
        if ($simular) {
            $numero = (int) $pdo->query('SELECT ultimo_numero_dps FROM nfse_config WHERE id = 1')->fetchColumn() + 1;
        } else {
            $numero = nfse_proximo_numero_dps($pdo);
        }

        $montado = nfse_montar_dps($cfg, $apuracao, $numero, null);
        $xmlBase = $builder->build($montado['dps']);
        $xmlVar  = lab_aplicar($xmlBase, $variante);
        $assinado = $signer->sign($xmlVar, 'infDPS');

        // Confere a assinatura uma única vez (a rotina é a mesma em todas)
        if ($conferencia === null) {
            $conferencia = lab_conferir_assinatura($assinado);
        }

        if ($simular) {
            $resultados[] = [
                'variante' => $variante, 'numero' => $numero, 'status' => null,
                'mensagem' => '(não enviado)', 'xml' => $assinado, 'ok' => null,
            ];
            continue;
        }

        $r = nfse_http_sefin('POST', 'nfse', ['dpsXmlGZipB64' => base64_encode(gzencode($assinado))], $cfg);
        $dec = json_decode($r['body'], true);
        $autorizou = ($r['status'] === 200 && is_array($dec) && !empty($dec['nfseXmlGZipB64']));

        // Registra a tentativa
        $ins = $pdo->prepare(
            "INSERT INTO nfse_notas
             (ordem_servico_id, ambiente, serie, numero_dps, id_dps, status,
              valor_servico, valor_reducao, base_calculo, aliquota, valor_iss,
              tomador_doc, tomador_nome, discriminacao, xml_dps, http_status,
              tentativas, ultima_tentativa, criado_em, criado_por)
             VALUES (:os, :amb, :serie, :num, :iddps, :st,
                     :vs, :vr, :bc, :aliq, :iss, :tdoc, :tnome, :disc, :xml, :http,
                     1, NOW(), NOW(), :usr)"
        );
        $ins->execute([
            ':os' => $osId, ':amb' => $cfg['ambiente'], ':serie' => $cfg['serie_dps'],
            ':num' => $numero, ':iddps' => $montado['id_dps'],
            ':st' => $autorizou ? 'autorizada' : 'rejeitada',
            ':vs' => $montado['valor_servico'], ':vr' => $montado['valor_reducao'],
            ':bc' => $montado['base_calculo'], ':aliq' => $apuracao['aliquota'],
            ':iss' => $montado['valor_iss'], ':tdoc' => $montado['tomador_doc'],
            ':tnome' => $montado['tomador_nome'], ':disc' => $montado['discriminacao'],
            ':xml' => $assinado, ':http' => $r['status'] ?: null,
            ':usr' => ($_SESSION['username'] ?? 'laboratorio'),
        ]);
        $notaId = (int) $pdo->lastInsertId();

        if ($autorizou) {
            $nfseXml = gzdecode(base64_decode($dec['nfseXmlGZipB64']));
            $nota    = (new \Nfse\Xml\NfseXmlParser)->parse($nfseXml);
            $chave   = nfse_chave50($nota->infNfse->id ?? null) ?: null;

            $pdo->prepare(
                "UPDATE nfse_notas SET chave_acesso=:c, numero_nfse=:n, cod_verificacao=:cv,
                        xml_nfse=:x, mensagem='Autorizada pelo laboratório de variantes.'
                  WHERE id=:id"
            )->execute([
                ':c' => $chave, ':n' => $nota->infNfse->numeroNfse ?? null,
                ':cv' => $nota->infNfse->codigoVerificacao ?? null, ':x' => $nfseXml, ':id' => $notaId,
            ]);

            nfse_log('emissao', 'Laboratório: variante "' . $variante . '" AUTORIZADA. Chave: ' . $chave,
                'info', $osId, $notaId);
        } else {
            $pdo->prepare("UPDATE nfse_notas SET mensagem=:m WHERE id=:id")
                ->execute([':m' => mb_substr('Laboratório (' . $variante . '): ' . nfse_resumir_resposta($r), 0, 4000, 'UTF-8'), ':id' => $notaId]);
        }

        $resultados[] = [
            'variante' => $variante, 'numero' => $numero, 'status' => $r['status'],
            'mensagem' => nfse_resumir_resposta($r), 'xml' => $assinado, 'ok' => $autorizou,
            'chave' => $autorizou ? ($chave ?? null) : null, 'nota' => $notaId,
        ];

        if ($autorizou) {
            $vencedora = $variante;
            break;   // não emite uma segunda nota para a mesma O.S.
        }

        usleep(400000);   // respiro entre tentativas
    }

    /* ---------- Conferência da assinatura ---------- */
    if ($conferencia) {
        $selo = function ($v) {
            if ($v === null) { return '<span class="pill p-alerta">não verificado</span>'; }
            return $v ? '<span class="pill p-ok">confere</span>' : '<span class="pill p-erro">NÃO confere</span>';
        };
        echo '<div class="painel"><h3 style="margin-top:0">Assinatura digital (conferida aqui, sem rede)</h3>';
        echo '<p>Digest do infDPS: ' . $selo($conferencia['digest'])
           . ' &nbsp;·&nbsp; SignatureValue: ' . $selo($conferencia['assinatura']) . '</p>';
        if (trim($conferencia['detalhe']) !== '') {
            echo '<pre>' . lab_h($conferencia['detalhe']) . '</pre>';
        }
        if ($conferencia['digest'] === false || $conferencia['assinatura'] === false) {
            echo '<p style="color:#b91c1c"><strong>A assinatura não fecha localmente.</strong> '
               . 'Enquanto isso não for corrigido, nenhuma variante vai passar — o problema é a '
               . 'assinatura, não o leiaute.</p>';
        }
        echo '</div>';
    }

    /* ---------- Resultados ---------- */
    if ($vencedora) {
        echo '<div class="achado"><h3 style="margin-top:0">Achamos.</h3>'
           . '<p>A variante <strong>' . lab_h($catalogo[$vencedora]['rotulo']) . '</strong> foi aceita. '
           . 'É essa diferença que a SEFIN está recusando — a correção definitiva é aplicá-la em '
           . '<code>nfse_montar_dps()</code>, no <code>nfse_lib.php</code>. Me mande este resultado que eu faço a alteração.</p></div>';
    } elseif (!$simular) {
        echo '<div class="painel"><h3 style="margin-top:0">Nenhuma variante passou.</h3>'
           . '<p>Se todas devolveram 500, a diferença está em algum ponto que estas variantes não '
           . 'tocam — o candidato seguinte é o grupo <code>IBSCBS</code>, que exige o Anexo VI da '
           . 'NT 009 e a definição do CST e da classificação tributária do serviço notarial junto '
           . 'ao contador.</p></div>';
    }

    echo '<table><tr><th style="width:34%">Variante</th><th style="width:90px">DPS</th>'
       . '<th style="width:80px">HTTP</th><th>Resposta</th></tr>';

    foreach ($resultados as $res) {
        $pill = $res['ok'] === true
            ? '<span class="pill p-ok">200</span>'
            : ($res['status'] === null ? '<span class="pill p-alerta">—</span>'
                                       : '<span class="pill p-erro">' . (int) $res['status'] . '</span>');

        echo '<tr><td><b>' . lab_h($catalogo[$res['variante']]['rotulo']) . '</b></td>'
           . '<td>' . (int) $res['numero'] . '</td>'
           . '<td>' . $pill . '</td>'
           . '<td>' . lab_h(mb_substr($res['mensagem'], 0, 400, 'UTF-8'))
           . (!empty($res['chave']) ? '<br><strong>Chave:</strong> <code>' . lab_h($res['chave']) . '</code>' : '')
           . '</td></tr>';
    }
    echo '</table>';

    if ($simular && $resultados) {
        echo '<div class="painel"><h3 style="margin-top:0">XML da primeira variante</h3><pre>'
           . lab_h($resultados[0]['xml']) . '</pre></div>';
    }
} catch (Throwable $e) {
    echo '<div class="painel"><h3 style="margin-top:0;color:#b91c1c">Falhou</h3><pre>'
       . lab_h($e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre></div>';
}
?>
<?php endif; ?>

</div>
</body>
</html>
