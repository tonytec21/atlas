<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-13n-recibo-danfse
 * DANFSe (Documento Auxiliar da NFS-e) em A4, gerado internamente — a API oficial
 * de geração foi descontinuada em 01/07/2026 (NT 008/2026). Impressão/PDF via
 * navegador (Ctrl+P -> Salvar como PDF). Inclui QR de consulta pública nacional.
 */
include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/nfse_lib.php';

$notaId = (int) ($_GET['nota_id'] ?? 0);

try {
    $d = nfse_nota_impressao($notaId);
} catch (Throwable $e) {
    http_response_code(404);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$nota = $d['nota'];
$cfg  = $d['cfg'];

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$brl = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$doc = static function ($v) {
    $v = preg_replace('/\D/', '', (string) $v);
    if (strlen($v) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
    if (strlen($v) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
    return $v;
};
$chaveBlocos = trim(chunk_split($d['chave'], 4, ' '));
$dataEmissao = !empty($nota['criado_em']) ? date('d/m/Y H:i:s', strtotime($nota['criado_em'])) : '';
$competencia = !empty($nota['criado_em']) ? date('m/Y', strtotime($nota['criado_em'])) : '';
$municipio   = $cfg['prest_logradouro'] ?? '';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DANFSe <?= $h($nota['numero_nfse'] ?: $notaId) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; background: #e5e7eb; font-family: Arial, "Microsoft Sans Serif", sans-serif; color: #000; }
  .sheet {
    width: 210mm; min-height: 297mm; margin: 10px auto; background: #fff; padding: 10mm;
    position: relative; box-shadow: 0 1px 6px rgba(0,0,0,.2);
  }
  .wm {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    font-size: 90px; color: rgba(185,28,28,.14); transform: rotate(-30deg);
    font-weight: 800; letter-spacing: 6px; pointer-events: none; z-index: 0;
  }
  .content { position: relative; z-index: 1; }
  .head { display: flex; align-items: center; gap: 10px; border: 1px solid #000; padding: 6px 10px; background: #f2f2f2; }
  .head .t { flex: 1; }
  .head .t .title { font-size: 15px; font-weight: 800; }
  .head .t .sub { font-size: 11px; }
  .head .badge { font-size: 10px; border: 1px solid #000; padding: 2px 6px; font-weight: 700; }
  .homolog { text-align: center; font-weight: 800; color: #b91c1c; border: 1px dashed #b91c1c; padding: 3px; margin-top: 6px; font-size: 12px; }
  .block { border: 1px solid #000; border-top: none; }
  .block:first-of-type { border-top: 1px solid #000; }
  .block h3 { margin: 0; font-size: 10px; background: #f2f2f2; padding: 3px 8px; border-bottom: 1px solid #000; text-transform: uppercase; letter-spacing: .5px; }
  .grid { display: grid; grid-template-columns: repeat(4, 1fr); }
  .cell { padding: 4px 8px; border-right: 1px dotted #999; font-size: 11px; }
  .cell:last-child { border-right: none; }
  .cell.wfull { grid-column: 1 / -1; border-right: none; }
  .cell.w2 { grid-column: span 2; }
  .cell .lbl { font-size: 8px; color: #444; text-transform: uppercase; display: block; }
  .cell .val { font-size: 11px; }
  .chavebox { display: flex; align-items: center; gap: 10px; padding: 6px 8px; }
  .chavebox .k { flex: 1; font-family: monospace; font-size: 13px; letter-spacing: 1px; word-break: break-all; font-weight: 700; }
  #qr { width: 24mm; height: 24mm; }
  #qr img, #qr canvas { width: 24mm !important; height: 24mm !important; }
  .valores .grid { grid-template-columns: repeat(4, 1fr); }
  .destaque { background: #f2f2f2; font-weight: 800; }
  .foot { font-size: 9px; color: #333; margin-top: 6px; text-align: center; }
  .toolbar { text-align: center; margin: 10px; }
  .toolbar button { font: 14px system-ui; padding: 9px 18px; border-radius: 6px; border: 1px solid #2563eb; background: #2563eb; color: #fff; cursor: pointer; }
  @media print {
    html, body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 8mm; }
    @page { size: A4; margin: 6mm; }
  }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Imprimir / Salvar PDF</button></div>

<div class="sheet">
  <?php if ($d['cancelada']): ?><div class="wm">CANCELADA</div><?php endif; ?>
  <div class="content">

    <div class="head">
      <div class="t">
        <div class="title">DANFSe — Documento Auxiliar da NFS-e</div>
        <div class="sub">NFS-e de Padrão Nacional · v2.0</div>
      </div>
      <div class="badge"><?= $d['homologacao'] ? 'HOMOLOGAÇÃO' : 'PRODUÇÃO' ?></div>
    </div>
    <?php if ($d['homologacao']): ?><div class="homolog">NFS-e SEM VALIDADE JURÍDICA</div><?php endif; ?>

    <div class="block" style="margin-top:6px;">
      <h3>Chave de Acesso da NFS-e</h3>
      <div class="chavebox">
        <div class="k"><?= $h($chaveBlocos ?: '—') ?></div>
        <div id="qr"></div>
      </div>
    </div>

    <div class="block">
      <h3>Identificação da NFS-e</h3>
      <div class="grid">
        <div class="cell"><span class="lbl">Número da NFS-e</span><span class="val"><?= $h($nota['numero_nfse'] ?: '—') ?></span></div>
        <div class="cell"><span class="lbl">Competência</span><span class="val"><?= $h($competencia) ?></span></div>
        <div class="cell"><span class="lbl">Data/Hora Emissão</span><span class="val"><?= $h($dataEmissao) ?></span></div>
        <div class="cell"><span class="lbl">Cód. Verificação</span><span class="val"><?= $h($nota['cod_verificacao'] ?: '—') ?></span></div>
        <div class="cell"><span class="lbl">Série da DPS</span><span class="val"><?= $h($nota['serie'] ?: '1') ?></span></div>
        <div class="cell"><span class="lbl">Número da DPS</span><span class="val"><?= $h($nota['numero_dps']) ?></span></div>
        <div class="cell w2"><span class="lbl">Situação</span><span class="val"><?= $d['cancelada'] ? 'CANCELADA' : 'NFS-e regular' ?></span></div>
      </div>
    </div>

    <div class="block">
      <h3>Prestador do Serviço (Emitente)</h3>
      <div class="grid">
        <div class="cell w2"><span class="lbl">Nome / Razão Social</span><span class="val"><?= $h($cfg['prest_nome']) ?></span></div>
        <div class="cell"><span class="lbl">CNPJ / CPF</span><span class="val"><?= $h($doc($cfg['prest_doc'])) ?></span></div>
        <div class="cell"><span class="lbl">Inscrição Municipal</span><span class="val"><?= $h($cfg['prest_im'] ?: '—') ?></span></div>
        <div class="cell wfull"><span class="lbl">Município Emissor (IBGE)</span><span class="val"><?= $h($cfg['cod_municipio']) ?></span></div>
      </div>
    </div>

    <div class="block">
      <h3>Tomador do Serviço</h3>
      <div class="grid">
        <div class="cell w2"><span class="lbl">Nome / Razão Social</span><span class="val"><?= $h($nota['tomador_nome'] ?: 'Não identificado') ?></span></div>
        <div class="cell w2"><span class="lbl">CNPJ / CPF</span><span class="val"><?= $h($nota['tomador_doc'] ? $doc($nota['tomador_doc']) : '—') ?></span></div>
      </div>
    </div>

    <div class="block">
      <h3>Serviço Prestado</h3>
      <div class="grid">
        <div class="cell"><span class="lbl">Cód. Tributação Nacional</span><span class="val"><?= $h($cfg['ctrib_nac'] ?: '—') ?></span></div>
        <div class="cell w2"><span class="lbl">Código Municipal / CNAE</span><span class="val"><?= $h(($cfg['ctrib_mun'] ?: '—') . ' / ' . ($cfg['cnae'] ?: '—')) ?></span></div>
        <div class="cell"><span class="lbl">Local da Prestação (IBGE)</span><span class="val"><?= $h($cfg['cod_municipio']) ?></span></div>
        <div class="cell wfull"><span class="lbl">Descrição do Serviço</span><span class="val" style="white-space: pre-wrap;"><?= $h($nota['discriminacao']) ?></span></div>
      </div>
    </div>

    <div class="block valores">
      <h3>Valores da NFS-e</h3>
      <div class="grid">
        <div class="cell"><span class="lbl">Valor do Serviço</span><span class="val"><?= $h($brl($nota['valor_servico'])) ?></span></div>
        <div class="cell"><span class="lbl">Deduções/Reduções</span><span class="val"><?= $h($brl($nota['valor_reducao'] ?? 0)) ?></span></div>
        <div class="cell"><span class="lbl">Base de Cálculo</span><span class="val"><?= $h($brl($nota['base_calculo'])) ?></span></div>
        <div class="cell"><span class="lbl">Alíquota ISSQN</span><span class="val"><?= $h(number_format((float) $nota['aliquota'], 2, ',', '.')) ?>%</span></div>
        <div class="cell destaque w2"><span class="lbl">Valor do ISSQN</span><span class="val"><?= $h($brl($nota['valor_iss'])) ?></span></div>
        <div class="cell destaque w2"><span class="lbl">Valor Líquido da NFS-e</span><span class="val"><?= $h($brl($nota['valor_servico'])) ?></span></div>
      </div>
    </div>

    <div class="block">
      <h3>Informações Complementares</h3>
      <div class="grid">
        <div class="cell wfull" style="font-size:10px;">
          Tributos aproximados (Lei 12.741/2012): conforme regime tributário do prestador.
          Consulte a autenticidade desta NFS-e pela leitura do QR Code ou pela chave de acesso
          em <b>nfse.gov.br/ConsultaPublica</b>. Este documento é auxiliar; o valor jurídico é do XML da NFS-e.
        </div>
      </div>
    </div>

    <div class="foot">Gerado por Atlas · <?= date('d/m/Y H:i') ?> · DANFSe gerado internamente (NT 008/2026).</div>
  </div>
</div>

<?php if ($d['consulta_url'] !== ''): ?>
<script>
  (function () {
    try {
      new QRCode(document.getElementById('qr'), {
        text: <?= json_encode($d['consulta_url']) ?>,
        width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M
      });
    } catch (e) { document.getElementById('qr').textContent = '[QR]'; }
  })();
</script>
<?php endif; ?>
</body>
</html>
