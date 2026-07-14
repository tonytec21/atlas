<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Termos e documentos */
require_once __DIR__ . '/p213_docs.php';

$docs   = p213_documentos();
$cfg    = p213_config();
$classe = (int)$cfg['classe'];

// ---------------------------------------------------------------------------
// Geração direta (PDF ou HTML imprimível)
// ---------------------------------------------------------------------------
$gerar = isset($_GET['gerar']) ? $_GET['gerar'] : '';
if ($gerar !== '' && isset($docs[$gerar])) {
    $doc  = $docs[$gerar];
    $html = p213_render($doc['html']);
    $modo = isset($_GET['modo']) ? $_GET['modo'] : 'pdf';

    if ($modo === 'pdf' && p213_tcpdf()) {
        p213_log('termo', $gerar);
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Atlas — Módulo Provimento 213');
        $pdf->SetAuthor($cfg['serventia'] ?: 'Serventia extrajudicial');
        $pdf->SetTitle($doc['titulo']);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $css = '<style>h2{font-size:13pt}h3{font-size:11pt;color:#312e81}h4{font-size:10pt}'
             . 'p{line-height:1.5;text-align:justify}td,th{font-size:8pt}</style>';
        $pdf->writeHTML($css . $html, true, false, true, false, '');
        $pdf->Output(preg_replace('/[^a-z0-9_]/', '', $gerar) . '.pdf', 'I');
        exit;
    }

    p213_log('termo_html', $gerar);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . p213_esc($doc['titulo']) . '</title><style>'
       . 'body{font-family:Georgia,serif;max-width:820px;margin:30px auto;padding:0 24px;line-height:1.6;color:#111}'
       . 'h2{text-align:center;font-size:1.15rem}h3{font-size:1rem;color:#312e81}p{text-align:justify}'
       . 'table{border-collapse:collapse;width:100%;font-size:.82rem}td,th{border:1px solid #999;padding:6px}'
       . '.bar{position:sticky;top:0;background:#fff;padding:10px 0;text-align:right;border-bottom:1px solid #eee}'
       . 'button{background:#4f46e5;color:#fff;border:0;padding:9px 16px;border-radius:8px;'
       . 'font:500 14px system-ui;cursor:pointer}@media print{.bar{display:none}}</style></head><body>'
       . '<div class="bar"><button onclick="window.print()">Imprimir / salvar em PDF</button></div>'
       . $html . '</body></html>';
    exit;
}

$temTcpdf = p213_tcpdf();
p213_head('Termos e documentos — Provimento 213');
p213_hero('Termos e documentos exigidos',
    'Minutas geradas com os dados da serventia &middot; ajuste, imprima e assine');
p213_nav('termos.php');
?>

<?php if (!$temTcpdf): ?>
  <div class="p213-alert warn">
    <i class="fa fa-exclamation-triangle"></i>
    <div>TCPDF não localizado nos caminhos configurados. Os documentos abrirão em versão imprimível
      (<em>Imprimir → Salvar como PDF</em>). Para gerar PDF diretamente, ajuste
      <code>$P213_TCPDF_CANDIDATES</code> em <code>config.php</code>.</div>
  </div>
<?php endif; ?>

<?php if (trim($cfg['titular']) === '' || trim($cfg['serventia']) === ''): ?>
  <div class="p213-alert info">
    <i class="fa fa-info-circle"></i>
    <div>Preencha a <a href="configuracao.php">configuração</a> da serventia. Os campos não informados
      sairão como linhas para preenchimento manual.</div>
  </div>
<?php endif; ?>

<div class="p213-grid g3">
<?php foreach ($docs as $id => $d):
      $et = $d['etapa'];
      $dispensado = ($id === 'dpo' && $classe === 1);
      $soC1 = ($id === 'relatorio_simplificado' && $classe !== 1);
?>
  <article class="p213-doc">
    <div class="p213-doc__top">
      <h3 class="p213-doc__title"><?= p213_esc($d['titulo']) ?></h3>
      <span class="p213-tag <?= $et > 0 ? 'c1' : 'info' ?>">
        <?= $et > 0 ? 'Etapa ' . $et : 'Todas' ?></span>
    </div>
    <div class="p213-doc__body">
      <p class="p213-muted" style="margin:0 0 10px"><?= p213_esc($d['resumo']) ?></p>
      <div class="p213-q__base" style="margin-bottom:12px"><i class="fa fa-book"></i> <?= p213_esc($d['base']) ?></div>

      <?php if ($dispensado): ?>
        <div class="p213-alert neutral" style="margin-bottom:12px;padding:9px 11px;font-size:.78rem">
          <div>Classe 1 dispensada da designação de encarregado (Prov. 214/2026). O modelo permanece disponível.</div>
        </div>
      <?php endif; ?>
      <?php if ($soC1): ?>
        <div class="p213-alert neutral" style="margin-bottom:12px;padding:9px 11px;font-size:.78rem">
          <div>Forma de comprovação da Classe 1. Sua serventia é Classe <?= $classe ?>: use o dossiê técnico
            com lista de hashes assinada.</div>
        </div>
      <?php endif; ?>
    </div>
    <div class="p213-actions" style="margin-top:14px">
      <a class="p213-btn p213-btn--pri p213-btn--sm" href="termos.php?gerar=<?= $id ?>&modo=pdf" target="_blank">
        <i class="fa fa-file-pdf-o"></i> Gerar PDF</a>
      <a class="p213-btn p213-btn--ghost p213-btn--sm" href="termos.php?gerar=<?= $id ?>&modo=html" target="_blank">
        <i class="fa fa-eye"></i> Pré-visualizar</a>
    </div>
  </article>
<?php endforeach; ?>
</div>

<div class="p213-alert neutral" style="margin-top:20px">
  <i class="fa fa-shield"></i>
  <div><strong>Sobre a força probatória.</strong> As minutas são um ponto de partida, não um substituto do
    dossiê técnico. Para as Classes 2 e 3, as evidências de conclusão de etapa devem vir acompanhadas de
    mecanismo idôneo de verificação de integridade — lista de <em>hashes</em> assinada digitalmente e guarda em
    repositório com registro auditável, ou documento eletrônico único assinado com relatório de hash
    (Anexo IV, Disposições gerais, IV e VIII). Para a Classe 1, admite-se o mecanismo simplificado (V, VI e VII).</div>
</div>

<?php p213_foot(); ?>
