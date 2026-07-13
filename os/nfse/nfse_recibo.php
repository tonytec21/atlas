<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-13n-recibo-danfse
 * Recibo "Nota Consolidada" da NFS-e para impressora térmica (58/80mm), com
 * QR Code de consulta pública no Portal Nacional. Imprime via navegador.
 */
include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/nfse_lib.php';

$notaId = (int) ($_GET['nota_id'] ?? 0);
$largura = ($_GET['w'] ?? '80') === '58' ? 58 : 80; // mm

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
$dataEmissao = !empty($nota['criado_em']) ? date('d/m/Y H:i', strtotime($nota['criado_em'])) : '';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recibo NFS-e <?= $h($nota['numero_nfse'] ?: $notaId) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #f3f4f6; }
  .paper {
    width: <?= $largura ?>mm;
    margin: 8px auto;
    background: #fff;
    padding: 3mm 3mm 5mm;
    font-family: "Consolas", "Courier New", monospace;
    font-size: <?= $largura === 58 ? '10' : '11' ?>px;
    color: #000;
    line-height: 1.35;
  }
  .center { text-align: center; }
  .b { font-weight: 700; }
  .lg { font-size: <?= $largura === 58 ? '12' : '14' ?>px; }
  .hr { border: none; border-top: 1px dashed #000; margin: 4px 0; }
  .row { display: flex; justify-content: space-between; gap: 6px; }
  .row > span:last-child { text-align: right; }
  .muted { color: #333; }
  .chave { word-break: break-all; letter-spacing: .5px; font-size: <?= $largura === 58 ? '9' : '10' ?>px; }
  .aviso { border: 1px solid #000; padding: 2px 4px; margin: 4px 0; text-align: center; font-weight: 700; }
  .cancel { color: #b91c1c; border-color: #b91c1c; }
  #qr { display: flex; justify-content: center; margin: 6px 0 2px; }
  #qr img, #qr canvas { width: 30mm !important; height: 30mm !important; }
  .toolbar { text-align: center; margin: 10px; }
  .toolbar button, .toolbar a {
    font: 13px system-ui; padding: 8px 14px; margin: 0 4px; border-radius: 6px;
    border: 1px solid #2563eb; background: #2563eb; color: #fff; cursor: pointer; text-decoration: none;
  }
  .toolbar a { background: #fff; color: #2563eb; }
  @media print {
    html, body { background: #fff; }
    .toolbar { display: none; }
    .paper { margin: 0; width: <?= $largura ?>mm; padding: 0 2mm; }
    @page { margin: 0; size: <?= $largura ?>mm auto; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">Imprimir</button>
  <a href="?nota_id=<?= (int) $notaId ?>&w=<?= $largura === 58 ? 80 : 58 ?>">Trocar p/ <?= $largura === 58 ? '80mm' : '58mm' ?></a>
</div>

<div class="paper">
  <div class="center b lg"><?= $h($cfg['prest_nome'] ?: 'NFS-e') ?></div>
  <div class="center muted">
    <?= $h($doc($cfg['prest_doc'])) ?><?php if (!empty($cfg['prest_im'])): ?> · IM <?= $h($cfg['prest_im']) ?><?php endif; ?>
  </div>
  <div class="center b">NFS-e Nacional</div>

  <?php if ($d['homologacao']): ?><div class="aviso">NFS-e SEM VALIDADE JURÍDICA (HOMOLOGAÇÃO)</div><?php endif; ?>
  <?php if ($d['cancelada']): ?><div class="aviso cancel">*** NFS-e CANCELADA ***</div><?php endif; ?>

  <hr class="hr">
  <div class="row"><span>NFS-e nº</span><span class="b"><?= $h($nota['numero_nfse'] ?: '—') ?></span></div>
  <div class="row"><span>Série/DPS</span><span><?= $h(($nota['serie'] ?: '1') . '/' . $nota['numero_dps']) ?></span></div>
  <?php if (!empty($nota['cod_verificacao'])): ?>
  <div class="row"><span>Cód. verif.</span><span><?= $h($nota['cod_verificacao']) ?></span></div>
  <?php endif; ?>
  <div class="row"><span>Emissão</span><span><?= $h($dataEmissao) ?></span></div>

  <hr class="hr">
  <div class="b">TOMADOR</div>
  <div><?= $h($nota['tomador_nome'] ?: 'Não identificado') ?></div>
  <?php if (!empty($nota['tomador_doc'])): ?><div class="muted"><?= $h($doc($nota['tomador_doc'])) ?></div><?php endif; ?>

  <hr class="hr">
  <div class="b">SERVIÇO</div>
  <div class="muted" style="white-space: pre-wrap;"><?= $h(mb_substr((string) $nota['discriminacao'], 0, 400, 'UTF-8')) ?></div>

  <hr class="hr">
  <div class="row"><span>Valor do serviço</span><span><?= $h($brl($nota['valor_servico'])) ?></span></div>
  <div class="row"><span>Base de cálculo</span><span><?= $h($brl($nota['base_calculo'])) ?></span></div>
  <div class="row"><span>Alíquota ISSQN</span><span><?= $h(number_format((float) $nota['aliquota'], 2, ',', '.')) ?>%</span></div>
  <div class="row b"><span>ISSQN</span><span><?= $h($brl($nota['valor_iss'])) ?></span></div>

  <?php if ($d['chave'] !== ''): ?>
  <hr class="hr">
  <div class="center b">CONSULTE ESTA NFS-e</div>
  <div id="qr"></div>
  <div class="center" style="font-size: 9px;">Portal Nacional da NFS-e — leia o QR ou use a chave:</div>
  <div class="chave center"><?= $h($chaveBlocos) ?></div>
  <div class="center" style="font-size: 8px; word-break: break-all;">nfse.gov.br/ConsultaPublica</div>
  <?php else: ?>
  <hr class="hr">
  <div class="center muted">Chave indisponível — sincronize a nota.</div>
  <?php endif; ?>

  <hr class="hr">
  <div class="center muted" style="font-size: 9px;">Documento auxiliar. Validade fiscal: XML da NFS-e.</div>
</div>

<?php if ($d['consulta_url'] !== ''): ?>
<script>
  (function () {
    try {
      new QRCode(document.getElementById('qr'), {
        text: <?= json_encode($d['consulta_url']) ?>,
        width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M
      });
    } catch (e) {
      document.getElementById('qr').textContent = '[QR indisponível]';
    }
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
  })();
</script>
<?php endif; ?>
</body>
</html>
