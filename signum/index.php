<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
asg_ensure_schema();

$username = $_SESSION['username'];
$CSRF = asg_csrf();
$cfg = asg_config();
$u   = asg_ucfg($username);
$metodo = $u['metodo'] ?? 'a3';
$assinador = ($metodo === 'a3') ? asg_a3_assinador($u) : '';
$isTc = ($assinador === 'tcloud');
$certInfo = asg_cert_info($username);
$first = asg_listar_filtrado(['page' => 1, 'per' => 20]);
$docs = $first['rows'];
// Nome + CPF que vão no carimbo (para a pré-visualização)
if ($metodo === 'a1' && ($cload = asg_cert_load($username))) {
    $pessoa = asg_cert_pessoa($cload['cert']);
    $nomeCarimbo = (!empty($u['usar_cn_titular']) && $pessoa['nome']) ? $pessoa['nome'] : ($u['assinante_nome'] ?: $pessoa['nome']);
    $cpfCarimbo  = $pessoa['cpf'] ?: ($u['assinante_cpf'] ?? '');
} elseif ($isTc) {
    // TCloud: a chancela usa o nome e o CPF do certificado escolhido na hora de assinar
    $nomeCarimbo = 'Titular do certificado';
    $cpfCarimbo  = '';
} else {
    $nomeCarimbo = $u['assinante_nome'] ?: ($certInfo['cn'] ?? '(titular do certificado)');
    $cpfCarimbo  = $u['assinante_cpf'] ?? '';
}
$cpfCarimboFmt = asg_cpf_fmt($cpfCarimbo);
$prontoA1 = ($metodo === 'a1') ? (bool)$certInfo : true;
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<?php include(__DIR__ . '/../os/guia/guia.php'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Signum · Assinatura Eletrônica</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{ --sg-primary:#2563eb; --sg-primary2:#1e40af; --sg-bg:#f1f5f9; --sg-text:#0f172a; --sg-muted:#64748b; --sg-card:#fff; --sg-border:#e5e9f0; }
body.dark-mode{ --sg-bg:#0f1216; --sg-text:#e5e7eb; --sg-muted:#9aa4b2; --sg-card:#1c2126; --sg-border:rgba(255,255,255,.08); }
#main .container{ max-width:1200px; padding-bottom:120px; }
.sg-hero{ background:var(--sg-card); border:1px solid var(--sg-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.sg-title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.sg-ic{ width:56px; height:56px; border-radius:16px; flex:0 0 auto; background:linear-gradient(135deg,var(--sg-primary),var(--sg-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 10px 24px rgba(37,99,235,.34); }
.sg-hero h1{ font-size:1.5rem; font-weight:800; margin:0; color:var(--sg-text); }
.sg-sub{ color:var(--sg-muted); font-size:.92rem; margin-top:2px; }
.sg-actions{ margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.sg-pill{ border-radius:999px; font-weight:600; padding:9px 16px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.16s; text-decoration:none; font-size:.9rem; }
.sg-pri{ background:linear-gradient(135deg,var(--sg-primary),var(--sg-primary2)); color:#fff; box-shadow:0 10px 24px rgba(37,99,235,.30); }
.sg-pri:hover{ transform:translateY(-2px); color:#fff; }
.sg-soft{ background:var(--sg-bg); color:var(--sg-text); border:1px solid var(--sg-border); }
.sg-soft:hover{ background:var(--sg-border); color:var(--sg-text); }
.sg-card{ background:var(--sg-card); border:1px solid var(--sg-border); border-radius:18px; padding:20px; box-shadow:0 8px 24px rgba(15,23,42,.05); margin-bottom:18px; }
.chip{ display:inline-flex; align-items:center; gap:7px; font-size:.8rem; font-weight:600; padding:6px 12px; border-radius:999px; background:var(--sg-bg); color:var(--sg-muted); }
.chip.on{ background:#dcfce7; color:#166534; } .chip.off{ background:#fee2e2; color:#991b1b; } .chip.wait{ background:#fef3c7; color:#92400e; }
/* dropzone */
.dz{ border:2px dashed var(--sg-border); border-radius:16px; background:var(--sg-bg); padding:38px 20px; text-align:center; cursor:pointer; transition:.16s; }
.dz:hover,.dz.drag{ border-color:var(--sg-primary); background:rgba(37,99,235,.05); }
.dz-ic{ width:64px; height:64px; margin:0 auto 12px; border-radius:50%; background:linear-gradient(135deg,var(--sg-primary),var(--sg-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.7rem; }
.dz-t{ font-weight:700; color:var(--sg-text); } .dz-s{ color:var(--sg-muted); font-size:.88rem; margin-top:3px; }
/* assinatura */
#signPanel{ display:none; }
.sig-grid{ display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:18px; }
.sig-grid > *{ min-width:0; }                       /* a página do PDF nunca alarga a grade */
@media(max-width:1100px){ .sig-grid{ grid-template-columns:minmax(0,1fr) 280px; } }
@media(max-width:900px){ .sig-grid{ grid-template-columns:minmax(0,1fr); } }
.pdf-scroll{ background:#0f172a; border-radius:12px; padding:14px; max-height:70vh; overflow-y:auto; overflow-x:hidden; }
.pagewrap{ position:relative; margin:0 auto 14px; width:100%; max-width:900px; }
.pagewrap canvas{ display:block; width:100%; height:auto; box-shadow:0 8px 30px rgba(0,0,0,.4); border-radius:2px; }
.overlay{ position:absolute; inset:0; cursor:crosshair; }
.hint-place{ position:absolute; left:50%; top:12px; transform:translateX(-50%); background:rgba(37,99,235,.92); color:#fff; font-size:.78rem; font-weight:600; padding:5px 12px; border-radius:999px; pointer-events:none; }
.sealbox{ touch-action:none; position:absolute; border:2px solid var(--sg-primary); background:rgba(37,99,235,.12); border-radius:4px; cursor:move; overflow:hidden; box-shadow:0 4px 14px rgba(37,99,235,.3); }
.sealbox .s-bar{ height:4px; background:var(--sg-primary); }
.sealbox .s-body{ padding:3px 5px; color:#1e1b4b; }
.sealbox .s-title{ font-weight:800; color:var(--sg-primary); letter-spacing:.02em; }
.sealbox .s-name{ font-weight:700; } .sealbox .s-role,.sealbox .s-foot{ opacity:.75; }
.sealbox .grip{ position:absolute; right:0; bottom:0; width:12px; height:12px; background:var(--sg-primary); cursor:default; }
.side h6{ font-weight:800; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:var(--sg-muted); margin:0 0 8px; }
.side .box{ font-size:.86rem; color:var(--sg-text); background:var(--sg-bg); border:1px solid var(--sg-border); border-radius:12px; padding:12px; margin-bottom:14px; word-break:break-word; }
.sig-astat{ display:flex; align-items:center; gap:10px; padding:12px; border-radius:12px; border:1px solid var(--sg-border); background:var(--sg-bg); margin-bottom:10px; }
.sig-astat .dot{ width:12px; height:12px; border-radius:50%; background:#eab308; flex:0 0 auto; }
.sig-astat.on .dot{ background:#22c55e; } .sig-astat.off .dot{ background:#ef4444; }
.sig-astat b{ font-size:.9rem; color:var(--sg-text); } .sig-astat small{ display:block; color:var(--sg-muted); }
.steps{ list-style:none; padding:0; margin:0 0 12px; }
.sig-step{ display:flex; gap:10px; align-items:flex-start; padding:7px 0; color:var(--sg-muted); }
.sig-step .n{ width:22px; height:22px; border-radius:50%; background:var(--sg-border); color:var(--sg-muted); display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; flex:0 0 auto; }
.sig-step.active{ color:var(--sg-text); } .sig-step.active .n{ background:var(--sg-primary); color:#fff; }
.sig-step.done .n{ background:#22c55e; color:#fff; }
.sig-step small{ display:block; color:var(--sg-muted); font-size:.76rem; }
.ident{ display:flex; align-items:center; gap:10px; padding:12px; border-radius:12px; border:1px solid var(--sg-border); background:var(--sg-bg); margin-bottom:10px; }
.ident.ok{ border-color:#22c55e; background:rgba(34,197,94,.12); }
.ident-ic{ width:38px; height:38px; border-radius:10px; background:var(--sg-border); color:var(--sg-muted); display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
.ident.ok .ident-ic{ background:#22c55e; color:#fff; }
.ident b{ font-size:.9rem; color:var(--sg-text); display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; } .ident small{ display:block; color:var(--sg-muted); }
.sw{ display:flex; align-items:center; gap:8px; margin:10px 0; font-size:.85rem; color:var(--sg-muted); flex-wrap:wrap; }
.sw .sw-lbl{ white-space:nowrap; }
.sw .sw-ctl{ display:flex; align-items:center; gap:8px; flex:1 1 220px; min-width:0; }
.sw input[type=range]{ flex:1; min-width:0; width:100%; }
.szbtn{ width:30px; height:30px; border-radius:8px; border:1px solid var(--sg-border); background:var(--sg-card); color:var(--sg-text); font-size:1.1rem; font-weight:700; line-height:1; cursor:pointer; flex:0 0 auto; transition:.14s; }
.szbtn:hover{ border-color:var(--sg-primary); color:var(--sg-primary); }
/* tabela */
.doc-table{ width:100%; border-collapse:collapse; }
.doc-table th{ text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:var(--sg-muted); padding:10px 12px; border-bottom:2px solid var(--sg-border); }
.doc-table td{ padding:12px; border-bottom:1px solid var(--sg-border); font-size:.9rem; color:var(--sg-text); vertical-align:middle; }
.doc-table tr:hover td{ background:var(--sg-bg); }
.filters{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
.f-search{ position:relative; flex:1 1 260px; min-width:220px; display:flex; align-items:center; }
.f-search i.fa-search{ position:absolute; left:12px; color:var(--sg-muted); font-size:.9rem; }
.f-search input{ width:100%; border:1px solid var(--sg-border); border-radius:12px; padding:10px 34px; font-size:.9rem; color:var(--sg-text); background:var(--sg-card); outline:none; }
.f-search input:focus{ border-color:var(--sg-primary); box-shadow:0 0 0 3px rgba(37,99,235,.14); }
.f-search button{ position:absolute; right:8px; border:0; background:transparent; color:var(--sg-muted); cursor:pointer; padding:4px; }
.inp-sm{ border:1px solid var(--sg-border); border-radius:10px; padding:9px 10px; font-size:.86rem; color:var(--sg-text); background:var(--sg-card); outline:none; }
.inp-sm:focus{ border-color:var(--sg-primary); }
.f-date{ display:inline-flex; align-items:center; gap:6px; font-size:.82rem; color:var(--sg-muted); }
.pager{ display:flex; align-items:center; justify-content:center; gap:14px; margin-top:16px; }
.doc-name{ font-weight:600; display:flex; align-items:center; gap:9px; }
.doc-name .fic{ width:34px;height:34px;border-radius:9px;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;flex:0 0 auto; }
.tbtn{ width:34px;height:34px;border:1px solid var(--sg-border); background:var(--sg-card); border-radius:9px; cursor:pointer; color:var(--sg-text); display:inline-flex; align-items:center; justify-content:center; transition:.14s; text-decoration:none; }
.tbtn:hover{ border-color:var(--sg-primary); color:var(--sg-primary); }
.tbtn.dl:hover{ background:#2563eb; color:#fff; border-color:transparent; }
.tbtn.rm:hover{ background:#fee2e2; color:#b91c1c; border-color:transparent; }
.sg-ver{ font-size:.72rem; font-weight:700; color:var(--sg-muted); background:var(--sg-bg); border:1px solid var(--sg-border); border-radius:999px; padding:2px 8px; margin-left:6px; vertical-align:middle; }
.tc-banner{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; background:#fffbeb; border:1px solid #fcd34d; border-radius:18px; padding:16px 20px; margin-bottom:18px; }
.tc-banner-ic{ width:44px; height:44px; border-radius:12px; background:#f59e0b; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex:0 0 auto; }
.tc-banner-tx{ flex:1 1 280px; min-width:0; } .tc-banner-tx b{ display:block; color:#78350f; } .tc-banner-tx small{ display:block; color:#92400e; font-size:.84rem; margin-top:2px; }
.tc-banner-bt{ display:flex; gap:8px; flex-wrap:wrap; }
@media(max-width:760px){ .tc-banner{ padding:14px; } .tc-banner-bt{ width:100%; } .tc-banner-bt .sg-pill{ flex:1 1 auto; justify-content:center; } }
.tc-note{ font-size:.8rem; color:var(--sg-muted); background:var(--sg-bg); border:1px solid var(--sg-border); border-radius:12px; padding:10px 12px; margin-bottom:14px; }
/* ---------- responsividade ---------- */
#main .container{ min-width:0; }
.sg-hero,.sg-card{ min-width:0; max-width:100%; }
.sg-title-row > div:first-child{ min-width:0; }
.side img,.side canvas{ max-width:100%; }
#docNome{ min-width:0; overflow-wrap:anywhere; }
.doc-name span:last-child{ overflow-wrap:anywhere; }
@media(max-width:900px){
  .pdf-scroll{ max-height:none; }                    /* no celular a página rola junto com a tela */
  .side{ border-top:1px solid var(--sg-border); padding-top:16px; }
}
@media(max-width:760px){
  #main .container{ padding-left:10px; padding-right:10px; }
  .sg-hero{ padding:16px; border-radius:16px; }
  .sg-hero h1{ font-size:1.25rem; }
  .sg-ic{ width:46px; height:46px; font-size:1.25rem; border-radius:13px; }
  .sg-actions{ margin-left:0; width:100%; }
  .sg-actions .chip{ max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .sg-card{ padding:14px; border-radius:14px; }
  .pdf-scroll{ padding:8px; }
  .dz{ padding:26px 14px; }
  .filters{ gap:8px; }
  .f-search{ flex:1 1 100%; min-width:0; }
  .filters .inp-sm,.filters select{ flex:1 1 140px; min-width:0; }
  .f-date{ flex:1 1 140px; min-width:0; } .f-date input{ flex:1; min-width:0; width:100%; }
  .filters #fLimpar{ flex:1 1 100%; justify-content:center; }
  /* tabela vira cartões */
  .doc-wrap{ overflow:visible !important; }
  .doc-table thead{ display:none; }
  .doc-table, .doc-table tbody, .doc-table tr, .doc-table td{ display:block; width:100%; }
  .doc-table tr{ border:1px solid var(--sg-border); border-radius:12px; padding:10px 12px; margin-bottom:10px; background:var(--sg-card); }
  .doc-table tr:hover td{ background:transparent; }
  .doc-table td{ border:0; padding:5px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; text-align:right !important; }
  .doc-table td::before{ content:attr(data-label); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--sg-muted); text-align:left; flex:0 0 auto; }
  .doc-table td:first-child{ display:block; text-align:left !important; padding-bottom:8px; border-bottom:1px solid var(--sg-border); margin-bottom:4px; }
  .doc-table td:first-child::before{ display:none; }
  .doc-table td[colspan]{ display:block; } .doc-table td[colspan]::before{ display:none; }
  .doc-table tr:has(td[colspan]){ border:0; background:transparent; }
  .pager{ gap:8px; flex-wrap:wrap; }
}
@media(max-width:420px){
  .sg-pill{ padding:8px 12px; font-size:.84rem; }
  .sealbox .grip{ width:16px; height:16px; }         /* alça maior para o dedo */
}
.empty{ text-align:center; padding:50px 20px; color:var(--sg-muted); } .empty i{ font-size:2.4rem; opacity:.35; margin-bottom:10px; }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="sg-hero">
      <div class="sg-title-row">
        <div class="sg-ic"><i class="fa fa-pencil-square-o"></i></div>
        <div style="min-width:0">
          <h1>Atlas Signum</h1>
          <div class="sg-sub">Anexe um PDF, posicione o carimbo e assine com seu certificado digital. <span class="sg-ver">v<?php echo eh(ASG_VERSAO); ?></span></div>
        </div>
        <div class="sg-actions">
          <?php if ($metodo === 'a1'): ?>
            <span class="chip <?php echo $certInfo ? 'on':'wait'; ?>"><i class="fa fa-shield"></i> A1 <?php echo $certInfo ? '· '.eh($certInfo['cn']) : 'não configurado'; ?></span>
          <?php elseif ($isTc): ?>
            <span class="chip" id="topChip"><i class="fa fa-cloud"></i> TCloud Assinador</span>
          <?php else: ?>
            <span class="chip" id="topChip"><i class="fa fa-plug"></i> Assinador SERPRO</span>
          <?php endif; ?>
          <a class="sg-pill sg-soft" href="configurar.php"><i class="fa fa-cog"></i> Configurar</a>
        </div>
      </div>
    </section>

    <?php if ($isTc): ?>
    <!-- aparece enquanto o TCloud Assinador não foi confirmado neste computador -->
    <div class="tc-banner" id="tcBanner" style="display:none">
      <div class="tc-banner-ic"><i class="fa fa-download"></i></div>
      <div class="tc-banner-tx">
        <b id="tcBanTit">TCloud Assinador ainda não verificado neste computador</b>
        <small id="tcBanTxt">Para assinar com o seu token, ele precisa estar instalado aqui. A instalação leva menos de um minuto.</small>
      </div>
      <div class="tc-banner-bt">
        <button type="button" class="sg-pill" id="tcBanInstalar" style="background:#d97706;color:#fff;border:0"><i class="fa fa-download"></i> Instalar neste computador</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Upload -->
    <div class="sg-card" id="uploadCard">
      <div class="dz" id="dz">
        <div class="dz-ic"><i class="fa fa-cloud-upload"></i></div>
        <div class="dz-t">Arraste um PDF aqui ou clique para escolher</div>
        <div class="dz-s">Você poderá posicionar o carimbo e assinar. (máx. 30MB)</div>
        <input type="file" id="fileInput" accept="application/pdf" hidden>
      </div>
    </div>

    <!-- Painel de assinatura -->
    <div class="sg-card" id="signPanel">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">
        <strong id="docNome" style="font-size:1rem;color:var(--sg-text)"></strong>
        <button class="sg-pill sg-soft" id="btnCancelar" style="margin-left:auto;padding:7px 14px"><i class="fa fa-times"></i> Cancelar</button>
      </div>
      <div class="sig-grid">
        <div>
          <div class="pdf-scroll" id="pages"></div>
          <div class="sw"><span class="sw-lbl"><i class="fa fa-arrows-h"></i> Tamanho do carimbo</span>
            <span class="sw-ctl">
              <button type="button" class="szbtn" id="szMinus" title="Diminuir">−</button>
              <input type="range" id="sealW" min="0.16" max="0.5" step="0.01" value="0.30">
              <button type="button" class="szbtn" id="szPlus" title="Aumentar">+</button>
              <span id="wVal" style="min-width:44px;text-align:right;font-weight:700;color:var(--sg-text)">30%</span>
            </span>
          </div>
          <div id="statusLine" style="color:var(--sg-muted);font-size:.86rem"></div>
        </div>
        <div class="side">
          <?php if ($metodo === 'a1'): ?>
            <h6>Método</h6>
            <div class="box"><b>A1 (arquivo)</b><br>
              <?php if ($certInfo): ?><?php echo eh($certInfo['cn']); ?><br><small style="color:var(--sg-muted)">válido até <?php echo eh($certInfo['ate']); ?></small>
              <?php else: ?><span style="color:#b91c1c">Sem certificado. <a href="configurar.php">Configurar</a></span><?php endif; ?>
            </div>
          <?php elseif ($isTc): ?>
            <h6>TCloud Assinador</h6>
            <div class="sig-astat" id="tcAstat"><span class="dot"></span><div><b id="tcState">Verificando…</b><small id="tcHelp">Consultando o servidor de assinatura…</small></div></div>
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
              <?php if (asg_tc_modo() === 'local'): ?>
              <button id="tcAtualizar" class="sg-pill sg-soft" type="button" style="padding:7px 12px;font-size:.82rem"><i class="fa fa-search"></i> Testar agora</button>
              <?php else: ?>
              <button id="tcAtualizar" class="sg-pill sg-soft" type="button" style="padding:7px 12px;font-size:.82rem"><i class="fa fa-refresh"></i> Atualizar</button>
              <?php endif; ?>
              <button id="tcInstalar" class="sg-pill" type="button" style="display:none;padding:7px 12px;font-size:.82rem;background:#d97706;color:#fff;border:0"><i class="fa fa-download"></i> Instalar nesta estação</button>
            </div>

            <h6>Como assinar</h6>
            <ul class="steps">
              <li class="sig-step" id="tst1"><div class="n">1</div><div>Posicionar a chancela<small>Clique no documento</small></div></li>
              <li class="sig-step" id="tst2"><div class="n">2</div><div>Escolher o certificado<small>O TCloud Assinador abre neste computador; digite o PIN</small></div></li>
              <li class="sig-step" id="tst3"><div class="n">3</div><div>Pronto<small>Documento assinado com o seu certificado</small></div></li>
            </ul>
            <div class="tc-note"><i class="fa fa-info-circle"></i> <?php echo asg_tc_modo()==='local' ? 'A assinatura é feita pelo TCloud Assinador deste computador.' : 'O PDF não sai do servidor.'; ?> Nome e CPF da chancela vêm do certificado que você escolher.</div>
          <?php else: ?>
            <h6>Assinador SERPRO</h6>
            <div class="sig-astat" id="sAstat"><span class="dot"></span><div><b id="sState">Verificando…</b><small id="sHelp">Procurando o Assinador…</small></div></div>
            <div style="display:flex;gap:8px;margin-bottom:12px">
              <button id="btnReconnect" class="sg-pill sg-soft" type="button" style="padding:7px 12px;font-size:.82rem"><i class="fa fa-refresh"></i> Reconectar</button>
              <a class="sg-pill sg-soft" href="http://127.0.0.1:65056/" target="_blank" rel="noopener" style="padding:7px 12px;font-size:.82rem"><i class="fa fa-unlock-alt"></i> Autorizar</a>
            </div>

            <h6>Certificado</h6>
            <div class="ident" id="identBox">
              <div class="ident-ic"><i class="fa fa-user-o"></i></div>
              <div style="min-width:0"><b id="identNome">Certificado não autenticado</b><small id="identCpf">Clique em “Autenticar” para ler seu token.</small></div>
            </div>
            <button class="sg-pill sg-soft" id="btnAuth" type="button" style="width:100%;justify-content:center;margin-bottom:14px" disabled><i class="fa fa-id-card-o"></i> Autenticar certificado</button>

            <h6>Como assinar</h6>
            <ul class="steps">
              <li class="sig-step" id="st1"><div class="n">1</div><div>Conectar o token<small>Assinador aberto e autorizado</small></div></li>
              <li class="sig-step" id="st2"><div class="n">2</div><div>Autenticar certificado<small>Confirme o PIN — lê nome e CPF</small></div></li>
              <li class="sig-step" id="st3"><div class="n">3</div><div>Posicionar o carimbo<small>Clique no documento</small></div></li>
              <li class="sig-step" id="st4"><div class="n">4</div><div>Assinar<small>Grava a assinatura no PDF</small></div></li>
            </ul>
          <?php endif; ?>
          <button class="sg-pill sg-pri" id="btnAssinar" style="width:100%;justify-content:center" disabled><i class="fa fa-pencil"></i> Assinar documento</button>
        </div>
      </div>
    </div>

    <!-- Lista -->
    <div class="sg-card">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">
        <strong style="font-size:1rem;color:var(--sg-text)"><i class="fa fa-check-square-o" style="color:var(--sg-primary)"></i> Documentos assinados</strong>
        <span class="chip" id="docCount" style="margin-left:auto"><?php echo (int)$first['total']; ?> documento(s)</span>
      </div>

      <div class="filters">
        <div class="f-search">
          <i class="fa fa-search"></i>
          <input type="text" id="fq" placeholder="Buscar por documento, assinante ou código…">
          <button type="button" id="fqClear" title="Limpar" style="display:none"><i class="fa fa-times"></i></button>
        </div>
        <select class="inp-sm" id="fmetodo" title="Método">
          <option value="">Método: todos</option>
          <option value="a3">A3 (token)</option>
          <option value="a1">A1 (arquivo)</option>
        </select>
        <label class="f-date">De <input type="date" class="inp-sm" id="fde"></label>
        <label class="f-date">Até <input type="date" class="inp-sm" id="fate"></label>
        <button type="button" class="sg-pill sg-soft" id="fLimpar" style="padding:8px 14px;font-size:.84rem"><i class="fa fa-eraser"></i> Limpar</button>
      </div>

      <div class="doc-wrap" style="overflow-x:auto;position:relative">
        <div id="docLoading" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.6);z-index:2;align-items:center;justify-content:center">
          <i class="fa fa-spinner fa-spin" style="font-size:1.5rem;color:var(--sg-primary)"></i>
        </div>
        <table class="doc-table">
          <thead><tr><th>Documento</th><th>Assinante</th><th>Método</th><th>Data</th><th>Código</th><th>Tamanho</th><th style="text-align:right">Ações</th></tr></thead>
          <tbody id="docBody">
          <?php if (!$docs): ?>
            <tr><td colspan="7"><div class="empty"><i class="fa fa-folder-open-o"></i><div>Nenhum documento assinado ainda.</div></div></td></tr>
          <?php else: foreach ($docs as $d): ?>
            <tr data-id="<?php echo (int)$d['id']; ?>">
              <td><div class="doc-name"><span class="fic"><i class="fa fa-file-pdf-o"></i></span><span><?php echo eh($d['nome_original']); ?></span></div></td>
              <td data-label="Assinante"><?php echo eh($d['titular'] ?: '—'); ?></td>
              <td data-label="Método"><span class="chip"><?php echo eh(asg_metodo_rotulo($d)); ?></span></td>
              <td data-label="Data"><?php echo eh(date('d/m/Y H:i', strtotime($d['assinado_em']))); ?></td>
              <td data-label="Código"><span class="chip"><?php echo eh($d['codigo']); ?></span></td>
              <td data-label="Tamanho"><?php echo eh(asg_human($d['tamanho'])); ?></td>
              <td data-label="Ações" style="text-align:right"><span style="display:inline-flex;gap:6px">
                <a class="tbtn" href="ver.php?id=<?php echo (int)$d['id']; ?>" target="_blank" title="Visualizar"><i class="fa fa-eye"></i></a>
                <a class="tbtn dl" href="baixar.php?id=<?php echo (int)$d['id']; ?>" title="Baixar"><i class="fa fa-download"></i></a>
                <button class="tbtn rm js-del" title="Excluir"><i class="fa fa-trash-o"></i></button>
              </span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="pager" id="pager" style="<?php echo $first['pages']>1?'':'display:none'; ?>">
        <button type="button" class="sg-pill sg-soft" id="pgPrev" style="padding:7px 14px"><i class="fa fa-chevron-left"></i> Anterior</button>
        <span id="pgInfo" style="color:var(--sg-muted);font-size:.86rem">Página 1 de <?php echo (int)$first['pages']; ?></span>
        <button type="button" class="sg-pill sg-soft" id="pgNext" style="padding:7px 14px">Próxima <i class="fa fa-chevron-right"></i></button>
      </div>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../oficios/pdfjs/pdf.min.js"></script>
<?php if ($isTc): ?>
<script src="js/tcloud_signum.js?v=<?php echo eh(ASG_VERSAO); ?>"></script>
<?php elseif ($metodo !== 'a1'): ?>
<script src="../oficios/serpro/serpro-signer-promise.js"></script>
<script src="../oficios/serpro/serpro-signer-client.js"></script>
<?php endif; ?>
<script>
(function(){
  "use strict";
  var CSRF=<?php echo json_encode($CSRF); ?>;
  var METODO=<?php echo json_encode($metodo); ?>;
  var TCLOUD=<?php echo $isTc ? 'true' : 'false'; ?>;
  var TC_MODO=<?php echo json_encode($isTc ? asg_tc_modo() : ''); ?>;   // 'local' (a estação assina) ou 'servidor'
  var TC_NIVEL=<?php echo json_encode(ASG_TC_NIVEL); ?>;
  var RATIO=<?php echo $isTc ? json_encode(asg_tc_proporcao()) : '0.40'; ?>;   // altura/largura do carimbo
  var tcPronto=null;   // TCloud: null = verificando, true = serviço ok, false = indisponível
  var PRONTO_A1=<?php echo $prontoA1 ? 'true':'false'; ?>;
  var NOME=<?php echo json_encode($nomeCarimbo); ?>;
  var CPF=<?php echo json_encode($cpfCarimboFmt); ?>;
  var CARGO=<?php echo json_encode($u['assinante_cargo'] ?? ''); ?>;
  var USAR_CN=<?php echo !empty($u['usar_cn_titular']) ? 'true':'false'; ?>;
  var C = window.SerproSignerClient || null;
  var serproOnline=false;
  var authed=false, authSubject='';
  var token=null, pdfDoc=null;
  var seal={ page:null, xn:null, yn:null, wn:0.30 };

  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc='../oficios/pdfjs/pdf.worker.min.js';
  function el(id){ return document.getElementById(id); }
  function status(m){ var e=el('statusLine'); if(e) e.textContent=m||''; }
  function b64ToU8(b64){ var bin=atob(b64),a=new Uint8Array(bin.length); for(var i=0;i<bin.length;i++)a[i]=bin.charCodeAt(i); return a; }
  async function postForm(url,data){
    data.csrf=CSRF;
    var r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data).toString(),credentials:'same-origin'});
    var t=await r.text(); try{ return JSON.parse(t); }catch(e){ throw new Error('Resposta inválida do servidor: '+t.slice(0,180)); }
  }

  /* ---------- Upload ---------- */
  var dz=el('dz'), fi=el('fileInput');
  dz.addEventListener('click',function(){ fi.click(); });
  ['dragover','dragenter'].forEach(function(e){ dz.addEventListener(e,function(ev){ ev.preventDefault(); dz.classList.add('drag'); }); });
  ['dragleave','drop'].forEach(function(e){ dz.addEventListener(e,function(ev){ ev.preventDefault(); dz.classList.remove('drag'); }); });
  dz.addEventListener('drop',function(ev){ if(ev.dataTransfer.files[0]) enviar(ev.dataTransfer.files[0]); });
  fi.addEventListener('change',function(){ if(fi.files[0]) enviar(fi.files[0]); });

  async function enviar(file){
    if(METODO==='a1' && !PRONTO_A1){ Swal.fire('Configuração necessária','Configure seu certificado A1 em "Configurar".','warning'); return; }
    if(file.type!=='application/pdf'){ Swal.fire('Atenção','Selecione um arquivo PDF.','warning'); return; }
    var fd=new FormData(); fd.append('csrf',CSRF); fd.append('pdf',file);
    Swal.fire({title:'Enviando...',didOpen:function(){Swal.showLoading();},allowOutsideClick:false});
    try{
      var r=await fetch('upload.php',{method:'POST',body:fd,credentials:'same-origin'}); var j=await r.json();
      Swal.close();
      if(!j.success) throw new Error(j.message||'Falha no envio.');
      token=j.token; el('docNome').textContent=j.nome;
      el('uploadCard').style.display='none'; el('signPanel').style.display='block';
      el('signPanel').scrollIntoView({behavior:'smooth'});
      try{
        await loadPreview(token);
      }catch(pe){
        // Fallback: nunca deixa o painel em branco — mostra o PDF num iframe e usa posição padrão
        previewFallback(token, pe && pe.message);
      }
      updateSteps(); refreshAssinarBtn();
    }catch(e){ Swal.close(); Swal.fire('Erro', e.message, 'error'); }
  }

  /* ---------- Preview + selo ---------- */
  async function loadPreview(tk){
    status('Gerando pré-visualização…');
    var r=await postForm('preview_pdf.php',{token:tk});
    if(r.status!=='success') throw new Error(r.message||'Falha na pré-visualização.');
    if(!window.pdfjsLib) throw new Error('Visualizador PDF (pdf.js) não carregou.');
    pdfDoc=await pdfjsLib.getDocument({data:b64ToU8(r.pdf_base64)}).promise;
    var box=el('pages'); box.innerHTML='';
    // resolução pela largura real disponível (máx. 900 px de exibição) × densidade da tela
    var cssW=Math.max(280, Math.min(900, box.clientWidth-28 || 900));
    var dpr=Math.min(2, window.devicePixelRatio||1);
    for(var p=1;p<=pdfDoc.numPages;p++){
      var page=await pdfDoc.getPage(p);
      var vp1=page.getViewport({scale:1});
      var vp=page.getViewport({scale:(cssW/vp1.width)*dpr});
      var wrap=document.createElement('div'); wrap.className='pagewrap'; wrap.dataset.page=p;
      var cv=document.createElement('canvas'); cv.width=Math.floor(vp.width); cv.height=Math.floor(vp.height);
      cv.style.aspectRatio=vp1.width+' / '+vp1.height;
      var ov=document.createElement('div'); ov.className='overlay';
      wrap.appendChild(cv); wrap.appendChild(ov); box.appendChild(wrap);
      if(p===1){ var hp=document.createElement('div'); hp.className='hint-place'; hp.id='hintPlace'; hp.textContent='Clique para posicionar o carimbo'; wrap.appendChild(hp); }
      await page.render({canvasContext:cv.getContext('2d'),viewport:vp}).promise;
      bindOverlay(p,ov);
    }
    status('Clique no documento para posicionar o carimbo.');
  }
  // Fallback quando o pdf.js não renderiza: mostra o documento num iframe e define posição padrão.
  function previewFallback(tk, msg){
    var box=el('pages');
    box.innerHTML='<div style="background:#fef3c7;color:#92400e;border-radius:10px;padding:10px 12px;font-size:.85rem;margin-bottom:10px">'
      +'<i class="fa fa-info-circle"></i> Não foi possível montar a pré-visualização interativa'
      +(msg?(' ('+msg+')'):'')+'. O carimbo será aplicado no <b>rodapé da última página</b> — você pode assinar normalmente.</div>'
      +'<iframe src="preview_tmp.php?token='+encodeURIComponent(tk)+'" style="width:100%;height:60vh;border:0;background:#fff;border-radius:8px"></iframe>';
    seal.page=1; seal.xn=0.60; seal.yn=0.86; seal.wn=0.30;   // posição padrão (rodapé direito)
    status('Pré-visualização simples. Clique em Assinar para continuar.');
  }
  // 'click' (e não pointerdown): no celular, arrastar o dedo para rolar a página não reposiciona o carimbo
  function bindOverlay(p,ov){ ov.addEventListener('click',function(ev){ if(ev.target.closest('.sealbox')) return; place(p,ov,ev); }); }
  function pageOverlay(p){ var w=document.querySelector('.pagewrap[data-page="'+p+'"]'); return w?w.querySelector('.overlay'):null; }
  function place(p,ov,ev){
    var r=ov.getBoundingClientRect();
    seal.page=p;
    seal.xn=Math.min(0.98,Math.max(0,(ev.clientX-r.left)/r.width));
    seal.yn=Math.min(0.98,Math.max(0,(ev.clientY-r.top)/r.height));
    var hp=el('hintPlace'); if(hp) hp.style.display='none';
    drawSeal(); updateSteps(); refreshAssinarBtn();
    status('Carimbo na página '+p+'. Arraste para ajustar ou clique em Assinar.');
  }
  function drawSeal(){
    document.querySelectorAll('.sealbox').forEach(function(b){ b.remove(); });
    if(seal.page==null) return;
    var ov=pageOverlay(seal.page); if(!ov) return;
    var W=ov.clientWidth, H=ov.clientHeight;
    var w=seal.wn*W, hgt=w*RATIO;
    var left=Math.max(2,Math.min(seal.xn*W, W-w-2));
    var top =Math.max(2,Math.min(seal.yn*H, H-hgt-2));
    var box=document.createElement('div'); box.className='sealbox';
    box.style.left=left+'px'; box.style.top=top+'px'; box.style.width=w+'px'; box.style.height=hgt+'px';
    var bar=document.createElement('div'); bar.className='s-bar';
    var body=document.createElement('div'); body.className='s-body';
    var t=document.createElement('div'); t.className='s-title'; t.textContent='ASSINADO DIGITALMENTE';
    var nm=document.createElement('div'); nm.className='s-name'; nm.textContent=NOME||'';
    body.appendChild(t); body.appendChild(nm);
    if(CPF){ var cp=document.createElement('div'); cp.className='s-role'; cp.textContent='CPF: '+CPF; body.appendChild(cp); }
    if(CARGO){ var cg=document.createElement('div'); cg.className='s-role'; cg.textContent=CARGO; body.appendChild(cg); }
    var grip=document.createElement('div'); grip.className='grip';
    box.appendChild(bar); box.appendChild(body); box.appendChild(grip);
    t.style.fontSize=(w*0.052)+'px'; nm.style.fontSize=(w*0.058)+'px';
    box.querySelectorAll('.s-role').forEach(function(e){ e.style.fontSize=(w*0.046)+'px'; });
    enableDrag(box, ov);
    ov.appendChild(box);
  }
  function enableDrag(box, ov){
    box.addEventListener('pointerdown',function(ev){
      ev.preventDefault(); ev.stopPropagation();
      var bx=box.getBoundingClientRect(); var offX=ev.clientX-bx.left, offY=ev.clientY-bx.top;
      box.setPointerCapture(ev.pointerId);
      function move(e){
        var r=ov.getBoundingClientRect(); var W=r.width,H=r.height,w=seal.wn*W,hgt=w*RATIO;
        seal.xn=Math.min(0.98,Math.max(0,(e.clientX-r.left-offX)/W));
        seal.yn=Math.min(0.98,Math.max(0,(e.clientY-r.top-offY)/H));
        box.style.left=Math.max(2,Math.min(seal.xn*W,W-w-2))+'px';
        box.style.top =Math.max(2,Math.min(seal.yn*H,H-hgt-2))+'px';
      }
      function up(){ try{box.releasePointerCapture(ev.pointerId);}catch(_){} box.removeEventListener('pointermove',move); box.removeEventListener('pointerup',up); box.removeEventListener('pointercancel',up); }
      box.addEventListener('pointermove',move); box.addEventListener('pointerup',up); box.addEventListener('pointercancel',up);
    });
  }
  function setSealW(v){
    v=Math.max(0.16, Math.min(0.5, Math.round(v*100)/100));
    seal.wn=v; el('sealW').value=v; el('wVal').textContent=Math.round(v*100)+'%'; drawSeal();
  }
  el('sealW').addEventListener('input',function(){ setSealW(parseFloat(this.value)); });
  el('szMinus').addEventListener('click',function(){ setSealW(seal.wn-0.02); });
  el('szPlus').addEventListener('click',function(){ setSealW(seal.wn+0.02); });
  el('btnCancelar').addEventListener('click',function(){ location.reload(); });

  /* ---------- Passos / estado ---------- */
  function setStep(id,cls){ var e=el(id); if(!e) return; e.classList.remove('active','done'); if(cls) e.classList.add(cls); }
  function updateSteps(){
    if(METODO==='a1') return;
    if(TCLOUD){
      setStep('tst1', seal.page==null?'active':'done');
      setStep('tst2', seal.page==null?'':'active');
      setStep('tst3','');
      return;
    }
    setStep('st1', serproOnline?'done':'active');
    if(!serproOnline){ setStep('st2',''); setStep('st3',''); setStep('st4',''); return; }
    if(!authed){ setStep('st2','active'); setStep('st3',''); setStep('st4',''); return; }
    setStep('st2','done');
    if(seal.page==null){ setStep('st3','active'); setStep('st4',''); }
    else { setStep('st3','done'); setStep('st4','active'); }
  }
  function refreshAssinarBtn(){
    var b=el('btnAssinar');
    if(METODO==='a1'){ b.disabled = (seal.page==null); return; }
    if(TCLOUD){ b.disabled = (seal.page==null || tcPronto===false); return; }
    var ab=el('btnAuth'); if(ab) ab.disabled = !serproOnline;
    b.disabled = (!serproOnline || !authed || seal.page==null);
  }

  /* ---------- Assinador SERPRO ---------- */
  function setConn(state,label,help){
    var a=el('sAstat'); if(a){ a.className='sig-astat '+(state||''); el('sState').textContent=label; if(help) el('sHelp').textContent=help; }
    var tc=el('topChip'); if(tc){ tc.className='chip '+(state==='on'?'on':(state==='off'?'off':'wait')); tc.innerHTML='<i class="fa fa-plug"></i> Assinador '+(state==='on'?'online':(state==='off'?'offline':'…')); }
    refreshAssinarBtn(); updateSteps();
  }
  function verifyAndConnect(){
    if(!C){ setConn('off','Cliente indisponível','Arquivos do Assinador não carregaram.'); return; }
    setConn('','Verificando Assinador…','Procurando o Assinador…');
    C.verifyIsInstalledAndRunning().success(connect).error(function(){
      serproOnline=false; setConn('off','Não está em execução','Abra o Assinador SERPRO e clique em Reconectar.');
    });
  }
  function connect(){
    try{ C.connect(
      function(){ serproOnline=true; setConn('on','Assinador conectado','Autentique o certificado para continuar.'); },
      function(){ serproOnline=false; authed=false; setConn('off','Conexão encerrada','Clique em Reconectar.'); },
      function(){ serproOnline=false; setConn('','Autorização pendente','Clique em “Autorizar” e reconecte.'); }
    ); }catch(e){ setConn('off','Falha ao conectar',''); }
  }
  var rb=el('btnReconnect'); if(rb) rb.addEventListener('click',verifyAndConnect);

  function serproSignHash(x){
    return new Promise(function(resolve,reject){
      try{ C.sign('hash',x).success(function(r){
        if(r&&r.actionCanceled) return reject(new Error('Operação cancelada no token.'));
        if(r&&r.hasError) return reject(new Error(r.errorMessage||r.message||'Erro no Assinador.'));
        resolve(r);
      }).error(function(){ reject(new Error('Falha na comunicação com o token.')); });
      }catch(e){ reject(e); }
    });
  }

  /* ---------- Autenticação do certificado (lê nome + CPF do token) ---------- */
  function randHashB64(){ var a=new Uint8Array(32); (window.crypto||window.msCrypto).getRandomValues(a); var s=''; for(var i=0;i<32;i++)s+=String.fromCharCode(a[i]); return btoa(s); }
  function fmtCpf(d){ d=(d||'').replace(/\D/g,''); return d.length===11 ? d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4') : (d||''); }
  function parseSubject(subj){
    var nome='', cpf='';
    if(subj){
      var m=subj.match(/CN\s*=\s*([^,\/]+)/i);
      if(m){ var cn=m[1].trim(); var mm=cn.match(/^(.*?):(\d{11})\b/); if(mm){ nome=mm[1].trim(); cpf=mm[2]; } else { nome=cn; } }
      if(!cpf){ var d=subj.match(/(?<!\d)(\d{11})(?!\d)/); if(d) cpf=d[1]; }
    }
    return { nome:nome, cpf:cpf };
  }
  function updateAuthUI(){
    var box=el('identBox');
    if(authed){
      box.classList.add('ok');
      box.querySelector('.ident-ic').innerHTML='<i class="fa fa-check"></i>';
      el('identNome').textContent=NOME||'(certificado)';
      el('identCpf').textContent=CPF?('CPF: '+CPF):'';
      el('btnAuth').innerHTML='<i class="fa fa-refresh"></i> Reautenticar';
    }
  }
  async function autenticar(){
    if(!serproOnline){ Swal.fire('Assinador','Conecte o Assinador SERPRO primeiro.','warning'); return false; }
    try{
      Swal.fire({title:'Autenticando certificado…',html:'Confirme o PIN no seu token/cartão.',didOpen:function(){Swal.showLoading();},allowOutsideClick:false});
      var r=await serproSignHash(randHashB64());
      var subj=(r&&r.by&&r.by.subject)||'';
      var p=parseSubject(subj);
      if(!p.nome && !p.cpf) throw new Error('Não foi possível ler os dados do certificado.');
      authed=true; authSubject=subj;
      if(p.nome) NOME=p.nome;
      if(p.cpf)  CPF=fmtCpf(p.cpf);
      Swal.close();
      updateAuthUI(); drawSeal(); updateSteps(); refreshAssinarBtn();
      status('Certificado autenticado: '+(NOME||'')+(CPF?(' · CPF '+CPF):''));
      return true;
    }catch(e){ Swal.fire('Autenticação', e.message, 'error'); return false; }
  }
  var ba=el('btnAuth'); if(ba) ba.addEventListener('click',autenticar);

  /* ---------- Assinar ---------- */
  el('btnAssinar').addEventListener('click',async function(){
    if(METODO==='a1'){ if(seal.page==null){ status('Clique no documento para posicionar o carimbo.'); return; } return assinarA1(); }
    if(TCLOUD){ if(seal.page==null){ status('Clique no documento para posicionar a chancela.'); return; } return assinarTCloud(); }
    if(!serproOnline){ status('Assinador SERPRO não conectado.'); return; }
    if(!authed){ var ok=await autenticar(); if(!ok) return; }
    if(seal.page==null){ status('Clique no documento para posicionar o carimbo.'); return; }
    try{
      Swal.fire({title:'Preparando o documento…',didOpen:function(){Swal.showLoading();},allowOutsideClick:false});
      var prep=await postForm('preparar_serpro.php',{token:token,page:seal.page,xn:seal.xn,yn:seal.yn,wn:seal.wn,titular:(NOME||''),cpf:(CPF||'')});
      if(prep.status!=='success') throw new Error(prep.message||'Falha ao preparar.');
      Swal.update({title:'Assinando…',html:'Gravando a assinatura (pode pedir o PIN).'}); Swal.showLoading();
      var resp=await serproSignHash(prep.to_sign);
      var cms=resp.signature, subject=(resp.by&&resp.by.subject)||authSubject;
      if(!cms) throw new Error('O Assinador não retornou a assinatura.');
      Swal.update({title:'Finalizando…'}); Swal.showLoading();
      var fin=await postForm('finalizar_serpro.php',{session:prep.session,signature_b64:cms,cert_subject:subject,token:token});
      if(fin.status!=='success') throw new Error(fin.message||'Falha ao finalizar.');
      await Swal.fire({icon:'success',title:'Documento assinado!',html:'Código: <b>'+(fin.doc&&fin.doc.codigo||prep.codigo)+'</b>',confirmButtonText:'Ver documentos'});
      location.reload();
    }catch(e){ Swal.fire('Não foi possível assinar', e.message, 'error'); }
  });

  /* ---------- TCloud Assinador ---------- */
  function tcConn(state,label,help,chip){
    var a=el('tcAstat'); if(a){ a.className='sig-astat '+(state||''); el('tcState').textContent=label; el('tcHelp').textContent=help||''; }
    if(chip==null) chip=(state==='on'?'pronto':(state==='off'?'indisponível':(state==='wait'?'atenção':'')));
    var tc=el('topChip'); if(tc){ tc.className='chip '+(state==='on'?'on':(state==='off'?'off':'wait')); tc.innerHTML='<i class="fa fa-cloud"></i> TCloud Assinador'+(chip?' · '+chip:''); }
    refreshAssinarBtn();
  }
  function tcDataHora(d){ function z(n){ return (n<10?'0':'')+n; } return z(d.getDate())+'/'+z(d.getMonth()+1)+' às '+z(d.getHours())+':'+z(d.getMinutes()); }
  /* Modo na estação: não há nada na VM para consultar e o navegador não enxerga programas instalados.
     A tela mostra só o que sabe de fato: a última vez que o app respondeu aqui, ou que não respondeu. */
  function tcStatusLocal(){
    tcPronto=true;   // nunca bloqueia o botão: a tentativa de assinar também serve de verificação
    var i=TcSignum.appInfo();
    if(i.status==='ok') tcConn('on','Detectado neste computador', (i.em?'Respondeu pela última vez em '+tcDataHora(i.em)+'. ':'')+'Se ele foi desinstalado, use “Testar agora”.', 'detectado');
    else if(i.status==='nao') tcConn('off','Não detectado neste computador','Instale o TCloud Assinador (botão abaixo) ou use “Testar agora”.','não detectado');
    else tcConn('wait','Ainda não verificado neste computador','Ao assinar, ele abre aqui. Use “Testar agora” para testar antes.','não verificado');
  }
  async function tcVerificar(){
    if(!window.TcSignum){ tcPronto=false; tcConn('off','Script não carregou','Arquivo js/tcloud_signum.js ausente.'); return; }
    tcConn('','Verificando…','Consultando o servidor de assinatura…');
    if(TC_MODO==='local'){ tcStatusLocal(); return; }
    try{
      var s=await TcSignum.situacao();
      if(!s.success) throw new Error(s.message||'Falha.');
      if(!s.instalado){ tcPronto=false; tcConn('off','Não instalado nesta VM', s.mensagem||''); return; }
      if(!s.disponivel){ tcPronto=false; tcConn('off','Serviço parado', 'Peça ao suporte para iniciar o serviço "TCloud Assinador - Servidor".'); return; }
      if(!s.token_ok){ tcPronto=false; tcConn('off','Acesso recusado', s.mensagem||''); return; }
      if(!s.versao_ok){ tcPronto=false; tcConn('off','Servidor desatualizado', s.mensagem||''); return; }
      tcPronto=true;
      tcConn('on','Pronto para assinar','Ao assinar, o TCloud Assinador abre neste computador.');
    }catch(e){ tcPronto=false; tcConn('off','Erro',e.message); }
  }
  async function assinarTCloud(){
    // TcSignum.assinar() só abre o tcloudsign:// com o app detectado ou confirmado; senão, oferece o comando
    try{
      var r=await TcSignum.assinar({token:token,page:seal.page,xn:seal.xn,yn:seal.yn,wn:seal.wn});
      if(!r||!r.doc){ status('Assinatura não concluída. Você pode tentar de novo.'); tcVerificar(); return; }
      var d=r.doc, extra='';
      if(d.titular) extra+='<br>Assinante: <b>'+TcSignum.esc(d.titular)+'</b>';
      if(TC_NIVEL!=='basico') extra+='<br><small>'+(d.carimbo?'Carimbo de tempo'+(d.act?' ('+TcSignum.esc(d.act)+')':'')+' ✔':'Sem carimbo de tempo')+(d.ltv?' · LTV ✔':'')+'</small>';
      if(d.avisos&&d.avisos.length) extra+='<div style="margin-top:8px;font-size:.8rem;color:#92400e">'+d.avisos.map(TcSignum.esc).join('<br>')+'</div>';
      await Swal.fire({icon:'success',title:'Documento assinado!',html:'Código: <b>'+TcSignum.esc(d.codigo)+'</b>'+extra,confirmButtonText:'Ver documentos'});
      location.reload();
    }catch(e){ Swal.fire('Não foi possível assinar', e.message, 'error'); }
  }
  // botão "Instalar nesta estação": só aparece se o assinador não foi detectado neste computador
  // "Instalar": aparece enquanto o app não foi confirmado neste computador (não detectado ou não verificado)
  function mostraInstalar(st){
    var b=el('tcInstalar'); if(b) b.style.display=(st==='ok')?'none':'inline-flex';
    var bn=el('tcBanner'); if(!bn) return;
    bn.style.display=(st==='ok')?'none':'flex';
    el('tcBanTit').textContent = st==='nao' ? 'O TCloud Assinador não foi detectado neste computador' : 'TCloud Assinador ainda não verificado neste computador';
    el('tcBanTxt').textContent = st==='nao'
      ? 'Ele não respondeu da última vez. Instale (ou atualize) para assinar com o seu token — leva menos de um minuto.'
      : 'Para assinar com o seu token, ele precisa estar instalado aqui. A instalação leva menos de um minuto.';
  }
  if(TCLOUD){
    var tbi=el('tcBanInstalar'); if(tbi) tbi.addEventListener('click',function(){ if(window.TcSignum) TcSignum.instalar(); });
    var tcA=el('tcAtualizar'); if(tcA) tcA.addEventListener('click',async function(){
      if(TC_MODO==='local' && window.TcSignum){ await TcSignum.verificar(); tcStatusLocal(); } else tcVerificar();
    });
  }

  async function assinarA1(){
    try{
      Swal.fire({title:'Assinando…',didOpen:function(){Swal.showLoading();},allowOutsideClick:false});
      var r=await postForm('assinar.php',{token:token,pagina:seal.page,x:seal.xn,y:seal.yn,w:seal.wn});
      if(!r.success) throw new Error(r.message||'Falha ao assinar.');
      await Swal.fire({icon:'success',title:'Documento assinado!',html:'Código: <b>'+r.doc.codigo+'</b>',confirmButtonText:'Ver documentos'});
      location.reload();
    }catch(e){ Swal.fire('Erro', e.message, 'error'); }
  }

  /* ---------- Excluir ---------- */
  document.addEventListener('click',async function(ev){
    var b=ev.target.closest('.js-del'); if(!b) return;
    var tr=ev.target.closest('tr'), id=tr.dataset.id;
    var q=await Swal.fire({icon:'warning',title:'Excluir documento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'});
    if(!q.isConfirmed) return;
    try{ var r=await postForm('excluir.php',{id:id}); if(!r.success) throw new Error(r.message);
      carregarDocs(docPage); // recarrega mantendo filtros/página
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });

  /* ---------- Filtros + paginação da lista ---------- */
  var docPage=1, docPages=<?php echo (int)$first['pages']; ?>, buscaTimer=null;
  function escHtml(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function getFiltros(){
    return { q:el('fq').value.trim(), metodo:el('fmetodo').value, de:el('fde').value, ate:el('fate').value };
  }
  function rowHTML(d){
    return '<tr data-id="'+d.id+'">'
      +'<td><div class="doc-name"><span class="fic"><i class="fa fa-file-pdf-o"></i></span><span>'+escHtml(d.nome)+'</span></div></td>'
      +'<td data-label="Assinante">'+(d.titular?escHtml(d.titular):'—')+'</td>'
      +'<td data-label="Método"><span class="chip">'+escHtml(d.metodo)+'</span></td>'
      +'<td data-label="Data">'+escHtml(d.data)+'</td>'
      +'<td data-label="Código"><span class="chip">'+escHtml(d.codigo)+'</span></td>'
      +'<td data-label="Tamanho">'+escHtml(d.tam)+'</td>'
      +'<td data-label="Ações" style="text-align:right"><span style="display:inline-flex;gap:6px">'
        +'<a class="tbtn" href="ver.php?id='+d.id+'" target="_blank" title="Visualizar"><i class="fa fa-eye"></i></a>'
        +'<a class="tbtn dl" href="baixar.php?id='+d.id+'" title="Baixar"><i class="fa fa-download"></i></a>'
        +'<button class="tbtn rm js-del" title="Excluir"><i class="fa fa-trash-o"></i></button>'
      +'</span></td></tr>';
  }
  async function carregarDocs(page){
    var f=getFiltros(); docPage=page||1;
    var qs='q='+encodeURIComponent(f.q)+'&metodo='+encodeURIComponent(f.metodo)+'&de='+encodeURIComponent(f.de)+'&ate='+encodeURIComponent(f.ate)+'&page='+docPage+'&per=20';
    el('docLoading').style.display='flex';
    try{
      var r=await fetch('listar_docs.php?'+qs,{credentials:'same-origin'}); var j=await r.json();
      if(j.status!=='success') throw new Error(j.message||'Falha ao listar.');
      docPages=j.pages; docPage=j.page;
      var tb=el('docBody');
      if(!j.rows.length){
        var vazio = (f.q||f.metodo||f.de||f.ate) ? 'Nenhum documento encontrado para o filtro.' : 'Nenhum documento assinado ainda.';
        tb.innerHTML='<tr><td colspan="7"><div class="empty"><i class="fa fa-folder-open-o"></i><div>'+vazio+'</div></div></td></tr>';
      } else {
        tb.innerHTML=j.rows.map(rowHTML).join('');
      }
      el('docCount').textContent=j.total+' documento(s)';
      el('pgInfo').textContent='Página '+j.page+' de '+j.pages;
      el('pager').style.display = j.pages>1 ? 'flex' : 'none';
      el('pgPrev').disabled = j.page<=1;
      el('pgNext').disabled = j.page>=j.pages;
    }catch(e){ Swal.fire('Erro', e.message, 'error'); }
    finally{ el('docLoading').style.display='none'; }
  }
  el('fq').addEventListener('input',function(){
    el('fqClear').style.display=this.value?'block':'none';
    clearTimeout(buscaTimer); buscaTimer=setTimeout(function(){ carregarDocs(1); },350);
  });
  el('fqClear').addEventListener('click',function(){ el('fq').value=''; this.style.display='none'; carregarDocs(1); });
  el('fmetodo').addEventListener('change',function(){ carregarDocs(1); });
  el('fde').addEventListener('change',function(){ carregarDocs(1); });
  el('fate').addEventListener('change',function(){ carregarDocs(1); });
  el('fLimpar').addEventListener('click',function(){ el('fq').value=''; el('fqClear').style.display='none'; el('fmetodo').value=''; el('fde').value=''; el('fate').value=''; carregarDocs(1); });
  el('pgPrev').addEventListener('click',function(){ if(docPage>1) carregarDocs(docPage-1); });
  el('pgNext').addEventListener('click',function(){ if(docPage<docPages) carregarDocs(docPage+1); });

  window.addEventListener('resize', function(){ if(seal.page!=null) drawSeal(); });
  // a área do PDF também muda sem resize da janela (menu lateral do Atlas, rotação do celular)
  if(window.ResizeObserver){ var roT=null; new ResizeObserver(function(){ clearTimeout(roT); roT=setTimeout(function(){ if(seal.page!=null) drawSeal(); },60); }).observe(el('pages')); }

  if(TCLOUD){
    if(window.TcSignum){
      TcSignum.init({ csrf: CSRF, endpoint: 'tcloud_api.php', urlInstalacao: <?php echo json_encode(ASG_TC_URL_INSTALACAO); ?>,
                      cmdExecutar: <?php echo json_encode(ASG_TC_CMD_EXECUTAR); ?>, cmdMac: <?php echo json_encode(ASG_TC_CMD_MAC); ?>,
                      cmdLinux: <?php echo json_encode(ASG_TC_CMD_LINUX); ?>,
                      aoMudarApp: function(st){ mostraInstalar(st); if(TC_MODO==='local') tcStatusLocal(); } });
      mostraInstalar(TcSignum.appStatus());
      var tcI=el('tcInstalar'); if(tcI) tcI.addEventListener('click', function(){ TcSignum.instalar(); });
    }
    tcVerificar();
  } else if(METODO!=='a1'){ verifyAndConnect(); }
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
