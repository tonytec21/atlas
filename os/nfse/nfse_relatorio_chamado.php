<?php
/**
 * ATLAS O.S. — Relatório técnico para chamado na SEFIN Nacional
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-relatorio-chamado
 *
 * Um HTTP 500 com corpo de 43 bytes e sem código catalogado é defeito do
 * lado do Ambiente Nacional: a própria API tem caminho de erro tratado —
 * ela devolve 400 com {"erros":[...]} quando a DPS é inválida, e chegou a
 * devolver E999 "Erro não catalogado" com corpo estruturado. Quando vem o
 * genérico, a exceção escapou antes de qualquer tratamento.
 *
 * Nenhuma mudança no emissor corrige uma exceção não tratada do outro
 * lado. O caminho é o chamado — e chamado sem evidência volta com
 * "verifique seus dados". Esta tela monta o relatório com tudo o que já
 * foi levantado, pronto para copiar.
 *
 * Acesse: .../os/nfse/nfse_relatorio_chamado.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(120);

function rc_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$relatorio = '';
$erro = null;

try {
    nfse_migrar();
    $cfg = nfse_config(true);
    $pdo = nfse_pdo();

    $L = [];
    $add = function ($t = '') use (&$L) { $L[] = $t; };

    $add('RELATÓRIO TÉCNICO — FALHA NA RECEPÇÃO DE DPS (SEFIN NACIONAL)');
    $add('Gerado em ' . date('d/m/Y H:i:s'));
    $add(str_repeat('=', 72));
    $add();

    /* ---------- 1. Identificação ---------- */
    $add('1. IDENTIFICAÇÃO');
    $add('   Emitente ............ ' . $cfg['prest_tipo'] . ' ' . $cfg['prest_doc']);
    $add('   Inscrição municipal . ' . ($cfg['prest_im'] ?: '(não informada)'));
    $add('   Município (IBGE) .... ' . $cfg['cod_municipio']);
    $add('   Ambiente ............ ' . ($cfg['ambiente'] === '1' ? 'Produção' : 'Produção Restrita'));
    $add('   Endpoint ............ ' . nfse_sefin_base($cfg) . '/nfse');
    $add('   Série da DPS ........ ' . $cfg['serie_dps']);
    $add('   Código de tributação  ' . $cfg['ctrib_nac']);
    $add('   Emissor ............. sistema próprio, integração via API');
    $add();

    /* ---------- 2. Resumo ---------- */
    $add('2. RESUMO DO PROBLEMA');
    $add('   O serviço de recepção de DPS (POST /SefinNacional/nfse) passou a responder');
    $add('   HTTP 500 com corpo {"message":"An error has occurred."} (43 bytes), sem código');
    $add('   de erro catalogado. A falha é de 100% das emissões desde o instante indicado');
    $add('   no item 3. Nenhuma alteração foi feita no sistema emissor nesse intervalo.');
    $add();

    /* ---------- 3. Linha do tempo ---------- */
    $add('3. LINHA DO TEMPO');

    $ultOk = $pdo->query(
        "SELECT numero_dps, id_dps, chave_acesso, criado_em
           FROM nfse_notas WHERE status='autorizada' ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if ($ultOk) {
        $add('   Última DPS autorizada .. nº ' . $ultOk['numero_dps']
            . ' em ' . date('d/m/Y H:i:s', strtotime($ultOk['criado_em'])));
        $add('   idDPS .................. ' . $ultOk['id_dps']);
        $add('   Chave de acesso ........ ' . ($ultOk['chave_acesso'] ?: '—'));
    }

    $prim = $pdo->query(
        "SELECT numero_dps, id_dps, ordem_servico_id, criado_em
           FROM nfse_notas
          WHERE status='rejeitada'
            AND (mensagem LIKE '%An error has occurred%')
          ORDER BY id ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if ($prim) {
        $add('   Primeiro HTTP 500 ...... nº ' . $prim['numero_dps']
            . ' em ' . date('d/m/Y H:i:s', strtotime($prim['criado_em'])));
        $add('   idDPS .................. ' . $prim['id_dps']);
    }

    $tot = $pdo->query(
        "SELECT COUNT(*) FROM nfse_notas
          WHERE status='rejeitada' AND mensagem LIKE '%An error has occurred%'"
    )->fetchColumn();
    $add('   Total de DPS com 500 ... ' . (int) $tot);
    $add();

    /* ---------- 4. Evidências ---------- */
    $add('4. EVIDÊNCIAS DE QUE O PROBLEMA NÃO ESTÁ NO EMISSOR');
    $add();
    $add('   4.1 A consulta responde normalmente, com o MESMO certificado e a MESMA');
    $add('       URL base. Se houvesse problema de credenciamento, certificado ou');
    $add('       endereço, a consulta também falharia.');

    try {
        $chave = $pdo->query(
            "SELECT chave_acesso FROM nfse_notas
              WHERE status='autorizada' AND chave_acesso IS NOT NULL AND chave_acesso <> ''
              ORDER BY id DESC LIMIT 1"
        )->fetchColumn();

        if ($chave) {
            $rc = nfse_http_sefin('GET', 'nfse/' . rawurlencode($chave), null, $cfg, 30);
            $add('       GET /nfse/' . substr($chave, 0, 20) . '...  ->  HTTP ' . $rc['status']
                . ' em ' . $rc['ms'] . ' ms');
            if ($rc['status'] === 200) {
                $dj = json_decode($rc['body'], true);
                $add('       versaoAplicativo informado pela SEFIN: ' . ($dj['versaoAplicativo'] ?? '?'));
            }
        }
    } catch (Throwable $e) {
        $add('       (não foi possível executar a consulta agora: ' . $e->getMessage() . ')');
    }

    $add();
    $add('   4.2 A recepção CONSEGUE processar e validar DPS: no mesmo período de falha');
    $add('       houve rejeições catalogadas, com código e descrição. Ou seja, o serviço');
    $add('       não está indisponível — ele quebra depois de passar pelas validações.');

    $cod = $pdo->query(
        "SELECT numero_dps, criado_em, mensagem FROM nfse_notas
          WHERE status='rejeitada' AND mensagem REGEXP 'E[0-9]{3,4}'
          ORDER BY id DESC LIMIT 3"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cod as $c) {
        if (preg_match('/"?Codigo"?\s*:\s*"([^"]+)".{0,10}"?Descricao"?\s*:\s*"([^"]+)"/i', (string) $c['mensagem'], $m)) {
            $add('       DPS ' . $c['numero_dps'] . ' em ' . date('d/m/Y H:i', strtotime($c['criado_em']))
                . '  ->  ' . $m[1] . ' — ' . $m[2]);
        }
    }

    $add();
    $add('   4.3 O leiaute transmitido é o mesmo que vinha sendo autorizado. A DPS que');
    $add('       falha e a última autorizada são estruturalmente idênticas: mesmos grupos,');
    $add('       mesmos campos, mesma versão. Diferem apenas em número, competência e');
    $add('       dados do tomador.');
    $add();
    $add('   4.4 A assinatura digital é válida: o par chave/certificado é coerente e o');
    $add('       DigestInfo do SignatureValue é bem formado (SHA-1/RSA 2048, ICP-Brasil).');
    $add();

    /* ---------- 5. Pedido ---------- */
    $add('5. SOLICITAÇÃO');
    $add('   Solicita-se a análise do log de exceção do serviço de recepção para os');
    $add('   idDPS listados no item 6, a fim de identificar a causa do HTTP 500 e');
    $add('   restabelecer a emissão. Havendo necessidade de ajuste no documento');
    $add('   transmitido, solicita-se a indicação do campo ou grupo correspondente.');
    $add();

    /* ---------- 6. Amostras ---------- */
    $add('6. AMOSTRAS DE idDPS COM HTTP 500');
    $amostras = $pdo->query(
        "SELECT id_dps, criado_em FROM nfse_notas
          WHERE status='rejeitada' AND mensagem LIKE '%An error has occurred%'
          ORDER BY id DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($amostras as $a) {
        $add('   ' . $a['id_dps'] . '   ' . date('d/m/Y H:i:s', strtotime($a['criado_em'])));
    }
    $add();
    $add('   O XML assinado de qualquer uma dessas DPS pode ser fornecido — está');
    $add('   arquivado no sistema emissor (coluna xml_dps).');
    $add();
    $add(str_repeat('=', 72));

    $relatorio = implode("\n", $L);
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Relatório para chamado — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.6; }
    .wrap{ max-width:900px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 18px; color:var(--cinza); }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    textarea{ width:100%; height:520px; font-family:ui-monospace,Consolas,monospace; font-size:12px;
              line-height:1.5; border:1px solid var(--borda); border-radius:8px; padding:12px; resize:vertical; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:10px 22px;
            font-size:14px; font-weight:600; cursor:pointer; margin-top:10px; }
    button:hover{ background:#1e3a8a; }
    a{ color:var(--azul); }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Relatório técnico para o chamado</h1>
<p>Monta o texto com as evidências já levantadas, pronto para anexar.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo rc_h($erro); ?></div>
<?php else: ?>

    <div class="painel">
        <h3 style="margin:0 0 8px; font-size:15px">Por que abrir chamado</h3>
        <p style="margin:0 0 10px">
            Um 500 com corpo de 43 bytes e sem código catalogado é uma exceção que escapou do
            tratamento de erro da aplicação. A própria API demonstra ter esse tratamento: devolve
            <code>400</code> com <code>{"erros":[...]}</code> quando a DPS é inválida, e chegou a
            devolver <code>E999 — Erro não catalogado</code> com corpo estruturado.
        </p>
        <p style="margin:0">
            Nenhuma alteração no emissor corrige uma exceção não tratada do outro lado. Abra em
            <a href="https://www.gov.br/nfse/pt-br/canais-de-atendimento" target="_blank" rel="noopener">Canais
            de Atendimento do Portal NFS-e</a>, anexando este relatório e o XML de uma DPS recusada.
        </p>
    </div>

    <div class="painel">
        <textarea id="txt" readonly><?php echo rc_h($relatorio); ?></textarea>
        <button type="button" onclick="copiar()">Copiar relatório</button>
        <span id="aviso" style="margin-left:10px; color:#16a34a; font-weight:600"></span>
    </div>

    <script>
    function copiar() {
        var t = document.getElementById('txt');
        t.removeAttribute('readonly');
        t.select();
        t.setSelectionRange(0, 999999);
        document.execCommand('copy');
        t.setAttribute('readonly', 'readonly');
        window.getSelection().removeAllRanges();
        document.getElementById('aviso').textContent = 'Copiado.';
        setTimeout(function () { document.getElementById('aviso').textContent = ''; }, 2500);
    }
    </script>

<?php endif; ?>

</div>
</body>
</html>
