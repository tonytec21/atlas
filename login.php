<?php
/* ======================================================================
 * ATLAS • Login  —  página com alertas tipados e carregamento otimizado
 * ====================================================================== */

/* ---------- Cabeçalhos anti-cache (evita página/modais “antigos”) ---------- */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

/* ---------- 0. Utilitários ---------- */
/** Busca JSON de uma URL, com cURL e fallback. Retorna array ou null. */
function fetchJsonFast($url, $timeoutConnect = 2, $timeoutTotal = 4) {
  // sempre cache-buster
  $url .= (str_contains($url,'?') ? '&' : '?') . 'ts=' . rawurlencode(microtime(true));

  // 1) cURL (preferido)
  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => $timeoutConnect,
      CURLOPT_TIMEOUT        => $timeoutTotal,
      CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Connection: close',
      ],
      CURLOPT_USERAGENT      => 'AtlasLogin/1.0',
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($resp && $status >= 200 && $status < 300) {
      $arr = json_decode($resp, true);
      if (is_array($arr)) return $arr;
    }
  }

  // 2) fallback file_get_contents com contexto e timeout
  @ini_set('default_socket_timeout', (string)$timeoutTotal);
  $ctx = stream_context_create([
    'http' => [
      'method'  => 'GET',
      'header'  => "Accept: application/json\r\nCache-Control: no-cache\r\nPragma: no-cache\r\nConnection: close\r\n",
      'timeout' => $timeoutTotal,
    ]
  ]);
  $resp = @file_get_contents($url, false, $ctx);
  if ($resp) {
    $arr = json_decode($resp, true);
    if (is_array($arr)) return $arr;
  }
  return null;
}

/* 1. git pull automático -------------------------------- */
shell_exec('git config --global --add safe.directory C:/xampp/htdocs/atlas');
$out = shell_exec('git pull 2>&1');
$mensagemAtualizacao =
    str_contains($out,'Already up to date.') ? 'Sistema atualizado. Nenhuma atualização pendente.' :
    (str_contains($out,'Updating')           ? 'Atualização do código aplicada com sucesso.'       :
                                               'Erro ao executar a atualização via git: '.$out );

/* 2. CNS local ------------------------------------------------------------- */
require_once __DIR__.'/db_connection.php';     // $conn (mysqli)
$cns = '';
$res = $conn->query("SELECT cns FROM cadastro_serventia LIMIT 1");
if ($res) $cns = $res->fetch_assoc()['cns'] ?? '';

/* 3. Chama a API ----------------------------------------------------------- */
$apiUrl   = 'https://api.sistemaatlas.com.br/api.php';
$apiToken = 'CHAVE-SECRETA-GERADA';

$statusResp = ['status'=>'ativo','allow_emergency'=>false,'emergency_active'=>false];
if ($cns) {
  $url = $apiUrl . '?action=status&cns=' . urlencode($cns) . '&token=' . urlencode($apiToken);
  $got = fetchJsonFast($url);
  if (is_array($got)) {
    $statusResp = $got;
  }
}

/* flags para uso no HTML */
$isBlocked        = ($statusResp['status'] ?? 'ativo') === 'bloqueado';
$allowEmergency   = $statusResp['allow_emergency']  ?? false;
$emergencyActive  = $statusResp['emergency_active'] ?? false;

/* ====== Mensagens tipadas (com fallback para legado) ======
   API nova: pendencia_msg, boleto_msg, importante_msg, boleto_link
   API antiga: mensagem (-> pendência) e boleto_link
*/
$pendenciaMsg  = $statusResp['pendencia_msg']  ?? ($statusResp['mensagem'] ?? '');
$boletoMsg     = $statusResp['boleto_msg']     ?? '';
$importanteMsg = $statusResp['importante_msg'] ?? '';
$boletoLink    = $statusResp['boleto_link']    ?? '';

/* valores pendentes (caso o login tenha falhado anteriormente)  */
$prevUser = $_GET['u'] ?? '';
$prevPass = $_GET['p'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ATLAS &bull; Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" href="style/img/favicon.png">

<style>
/* ============================================================
   ATLAS Login — tema, tokens e layout
   ============================================================ */
:root{
  --bg:            #f1f5f9;
  --bg-soft:       #e2e8f0;
  --panel:         #ffffff;
  --panel-glass:   rgba(255,255,255,.82);
  --border:        #d6dee8;
  --border-soft:   #e5ecf3;
  --text:          #0f1b2d;
  --text-dim:      #5b6b7f;
  --accent:        #4f46e5;
  --accent-2:      #6366f1;
  --accent-soft:   rgba(99,102,241,.12);
  --accent-ring:   rgba(99,102,241,.22);
  --success:       #10b981;
  --danger:        #ef4444;
  --contour:       rgba(79,70,229,.16);
  --grid:          rgba(79,70,229,.07);
  --shadow:        0 24px 60px -18px rgba(15,27,45,.25);
}
:root[data-theme="dark"]{
  --bg:            #0b1220;
  --bg-soft:       #0e1728;
  --panel:         #121c2e;
  --panel-glass:   rgba(18,28,46,.72);
  --border:        #223148;
  --border-soft:   #1a2740;
  --text:          #e6ecf5;
  --text-dim:      #8fa0b5;
  --accent:        #6366f1;
  --accent-2:      #818cf8;
  --accent-soft:   rgba(129,140,248,.14);
  --accent-ring:   rgba(129,140,248,.28);
  --contour:       rgba(129,140,248,.20);
  --grid:          rgba(129,140,248,.06);
  --shadow:        0 24px 60px -18px rgba(0,0,0,.6);
}

*{ box-sizing:border-box; }
html,body{ height:100%; }
body{
  margin:0;
  background:var(--bg);
  color:var(--text);
  font-family:'Inter',system-ui,-apple-system,'Segoe UI',Arial,sans-serif;
  -webkit-font-smoothing:antialiased;
  overflow-x:hidden;
}
h1,h2,h3,h4,h5,.font-display{ font-family:'Space Grotesk','Inter',sans-serif; }

/* ---------- Estrutura: split screen ---------- */
.login-shell{
  min-height:100dvh;
  display:grid;
  grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);
}

/* ---------- Painel de marca (esquerda) ---------- */
.brand-pane{
  position:relative;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  padding:clamp(2rem,4vw,3.5rem);
  background:
    radial-gradient(1200px 700px at -10% -10%, rgba(99,102,241,.28), transparent 55%),
    radial-gradient(900px 600px at 110% 110%, rgba(56,189,248,.14), transparent 55%),
    linear-gradient(160deg,#0b1220 0%,#101a30 55%,#0d1526 100%);
  color:#e6ecf5;
}
:root:not([data-theme="dark"]) .brand-pane{
  background:
    radial-gradient(1200px 700px at -10% -10%, rgba(99,102,241,.30), transparent 55%),
    radial-gradient(900px 600px at 110% 110%, rgba(56,189,248,.18), transparent 55%),
    linear-gradient(160deg,#101a30 0%,#16224066 140%),
    #101a30;
}

/* Curvas de nível topográficas + grade cartográfica (assinatura visual) */
.brand-pane .topo,
.brand-pane .gridlines{
  position:absolute; inset:0;
  pointer-events:none;
}
.brand-pane .gridlines{
  background-image:
    linear-gradient(var(--grid) 1px, transparent 1px),
    linear-gradient(90deg, var(--grid) 1px, transparent 1px);
  background-size:56px 56px;
  mask-image:radial-gradient(120% 120% at 30% 30%, #000 30%, transparent 78%);
  -webkit-mask-image:radial-gradient(120% 120% at 30% 30%, #000 30%, transparent 78%);
}
.brand-pane .topo svg{ width:100%; height:100%; display:block; }
.brand-pane .topo path{
  fill:none;
  stroke:var(--contour);
  stroke-width:1.4;
  stroke-dasharray:6 10;
  animation:contourFlow 60s linear infinite;
}
.brand-pane .topo path:nth-child(2n){ animation-duration:90s; animation-direction:reverse; opacity:.7; }
.brand-pane .topo path:nth-child(3n){ animation-duration:120s; opacity:.5; }
@keyframes contourFlow{ to{ stroke-dashoffset:-640; } }

.brand-top{ position:relative; z-index:2; }
.brand-logo img{ max-width:200px; height:auto; filter:drop-shadow(0 6px 18px rgba(0,0,0,.35)); }

.brand-hero{ position:relative; z-index:2; max-width:34rem; }
.brand-eyebrow{
  display:inline-flex; align-items:center; gap:.5rem;
  font-size:.72rem; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
  color:#c7d2fe;
  padding:.4rem .8rem;
  border:1px solid rgba(199,210,254,.28);
  border-radius:999px;
  background:rgba(99,102,241,.12);
  backdrop-filter:blur(4px);
}
.brand-eyebrow .dot{
  width:.5rem; height:.5rem; border-radius:50%;
  background:var(--success);
  box-shadow:0 0 0 4px rgba(16,185,129,.18);
  animation:pulseDot 2.4s ease-in-out infinite;
}
@keyframes pulseDot{ 50%{ box-shadow:0 0 0 7px rgba(16,185,129,.05); } }
.brand-hero h1{
  margin:1.1rem 0 .6rem;
  font-size:clamp(1.7rem,2.6vw,2.5rem);
  font-weight:700;
  line-height:1.15;
  letter-spacing:-.01em;
}
.brand-hero h1 .grad{
  background:linear-gradient(92deg,#a5b4fc,#7dd3fc);
  -webkit-background-clip:text; background-clip:text;
  -webkit-text-fill-color:transparent; color:transparent;
}
.brand-hero p{ color:#9db0c9; font-size:.98rem; line-height:1.65; margin:0; }

.brand-foot{
  position:relative; z-index:2;
  display:flex; align-items:center; gap:.9rem; flex-wrap:wrap;
  color:#7f93ad; font-size:.8rem;
}
.brand-foot .chip{
  display:inline-flex; align-items:center; gap:.45rem;
  padding:.42rem .75rem;
  border:1px solid rgba(159,175,200,.22);
  border-radius:.6rem;
  background:rgba(255,255,255,.04);
}
.brand-foot .chip i{ color:#a5b4fc; }

/* ---------- Painel do formulário (direita) ---------- */
.form-pane{
  display:flex;
  align-items:center;
  justify-content:center;
  padding:clamp(1.25rem,4vw,3rem);
  position:relative;
}
.card-login{
  width:100%;
  max-width:420px;
  background:var(--panel-glass);
  backdrop-filter:blur(14px);
  -webkit-backdrop-filter:blur(14px);
  border:1px solid var(--border);
  border-radius:1.25rem;
  padding:clamp(1.6rem,3vw,2.4rem);
  box-shadow:var(--shadow);
  animation:cardIn .55s cubic-bezier(.22,.9,.3,1) both;
}
@keyframes cardIn{
  from{ opacity:0; transform:translateY(16px) scale(.985); }
  to  { opacity:1; transform:none; }
}

.card-head{ margin-bottom:1.6rem; }
.card-head .mobile-logo{ display:none; text-align:center; margin-bottom:1.1rem; }
.card-head .mobile-logo img{ max-width:150px; }
.card-head h2{ font-size:1.35rem; font-weight:700; margin:0 0 .25rem; letter-spacing:-.01em; }
.card-head p{ margin:0; color:var(--text-dim); font-size:.92rem; }

/* ---------- Campos ---------- */
.field{ margin-bottom:1.05rem; }
.field label{
  display:block;
  font-size:.78rem; font-weight:600;
  letter-spacing:.06em; text-transform:uppercase;
  color:var(--text-dim);
  margin-bottom:.45rem;
}
.field .control{
  position:relative;
  display:flex; align-items:center;
  border:1.5px solid var(--border);
  border-radius:.8rem;
  background:var(--panel);
  transition:border-color .18s, box-shadow .18s, transform .18s;
}
.field .control:focus-within{
  border-color:var(--accent);
  box-shadow:0 0 0 4px var(--accent-ring);
}
.field .control > i.lead-icon{
  padding:0 .35rem 0 .95rem;
  color:var(--text-dim);
  font-size:1.05rem;
  transition:color .18s;
}
.field .control:focus-within > i.lead-icon{ color:var(--accent-2); }
.field input.form-control{
  border:none; background:transparent; box-shadow:none !important;
  color:var(--text);
  padding:.85rem .95rem .85rem .5rem;
  font-size:.98rem;
}
.field input.form-control::placeholder{ color:var(--text-dim); opacity:.55; }
.field .tail-btn{
  border:none; background:transparent;
  color:var(--text-dim);
  padding:0 1rem;
  cursor:pointer;
  font-size:1.05rem;
  transition:color .15s;
}
.field .tail-btn:hover{ color:var(--accent-2); }

.capslock-hint{
  display:none;
  align-items:center; gap:.4rem;
  margin-top:.4rem;
  font-size:.78rem; color:#f59e0b;
}
.capslock-hint.show{ display:flex; }

/* ---------- Botão principal ---------- */
.btn-atlas{
  --g1:var(--accent); --g2:var(--accent-2);
  position:relative;
  width:100%;
  border:none;
  border-radius:.85rem;
  padding:.9rem 1rem;
  font-weight:600; font-size:1rem;
  color:#fff;
  background:linear-gradient(135deg,var(--g1),var(--g2));
  box-shadow:0 10px 24px -10px rgba(99,102,241,.65);
  transition:transform .16s, box-shadow .16s, filter .16s;
  overflow:hidden;
}
.btn-atlas:hover{ transform:translateY(-1px); box-shadow:0 14px 28px -10px rgba(99,102,241,.75); filter:brightness(1.04); }
.btn-atlas:active{ transform:translateY(0); }
.btn-atlas:disabled{ opacity:.75; cursor:not-allowed; transform:none; }
.btn-atlas .spinner-border{ width:1.1rem; height:1.1rem; border-width:.16em; }

/* ---------- Alertas ---------- */
.alert{ font-size:.9rem; padding:.8rem 1rem; border-radius:.8rem; border-width:1px; }
.alert-soft-danger{
  background:rgba(239,68,68,.10);
  border:1px solid rgba(239,68,68,.35);
  color:#fca5a5;
}
:root:not([data-theme="dark"]) .alert-soft-danger{ color:#b91c1c; }
.alert-soft-danger i{ margin-right:.35rem; }

/* ---------- Bloqueio ---------- */
.block-panel{
  text-align:center;
  border:1px solid rgba(239,68,68,.35);
  background:rgba(239,68,68,.07);
  border-radius:1rem;
  padding:1.4rem 1.2rem;
}
.block-panel .block-icon{
  width:58px; height:58px; margin:0 auto .8rem;
  border-radius:1rem;
  display:flex; align-items:center; justify-content:center;
  background:rgba(239,68,68,.14);
  color:#f87171;
  font-size:1.6rem;
}
.block-panel h5{ font-weight:700; margin-bottom:.35rem; }
.block-panel p.desc{ color:var(--text-dim); font-size:.88rem; }
.block-panel .btn{ white-space:normal; }
.emergency-ok{
  display:flex; align-items:center; justify-content:center; gap:.5rem;
  margin-top:.9rem; color:var(--success); font-size:.9rem; font-weight:600;
}

/* ---------- Rodapé do card / toggle de tema ---------- */
.card-foot{
  margin-top:1.4rem;
  display:flex; align-items:center; justify-content:space-between;
  color:var(--text-dim); font-size:.78rem;
}
.theme-toggle{
  border:1px solid var(--border);
  background:var(--panel);
  color:var(--text-dim);
  border-radius:.65rem;
  width:38px; height:38px;
  display:inline-flex; align-items:center; justify-content:center;
  cursor:pointer;
  transition:color .15s, border-color .15s, transform .15s;
}
.theme-toggle:hover{ color:var(--accent-2); border-color:var(--accent); transform:translateY(-1px); }

/* ---------- Modais tonalizados (3 tipos) ---------- */
.modal-content{ border-radius:1rem; overflow:hidden; }
.modal-tone-danger .modal-content{
  background:#1c0b0b; color:#fde8e8;
  border:1px solid #dc2626;
  box-shadow:0 0 18px rgba(220,38,38,.5),0 24px 60px rgba(0,0,0,.5);
  animation:pulseDanger 1.8s infinite;
}
.modal-tone-danger .modal-header{ background:linear-gradient(135deg,#dc2626,#b91c1c); border:none; color:#fff; }
.modal-tone-danger .btn-close{ filter:invert(1); }
@keyframes pulseDanger{
  0%  { box-shadow:0 0 0 0 rgba(220,38,38,.55); }
  70% { box-shadow:0 0 0 18px rgba(220,38,38,0); }
  100%{ box-shadow:0 0 0 0 rgba(220,38,38,0); }
}
.modal-tone-warning .modal-content{
  background:#1f1808; color:#fff7ed;
  border:1px solid #f59e0b;
  box-shadow:0 0 18px rgba(245,158,11,.4),0 24px 60px rgba(0,0,0,.5);
}
.modal-tone-warning .modal-header{ background:linear-gradient(135deg,#f59e0b,#d97706); border:none; color:#1f1808; }
.modal-tone-warning .btn-outline-light{ color:#fff; border-color:#ffffff88; }
.modal-tone-info .modal-content{
  background:#081422; color:#dbeafe;
  border:1px solid #3b82f6;
  box-shadow:0 0 18px rgba(59,130,246,.4),0 24px 60px rgba(0,0,0,.5);
}
.modal-tone-info .modal-header{ background:linear-gradient(135deg,#3b82f6,#2563eb); border:none; color:#fff; }
.modal-footer{ border-top:1px solid rgba(255,255,255,.08); }

/* ---------- Responsividade ---------- */
@media (max-width: 991.98px){
  .login-shell{ grid-template-columns:1fr; }
  .brand-pane{ display:none; }
  .card-head .mobile-logo{ display:block; }
  body{
    background:
      radial-gradient(900px 520px at 85% -10%, var(--accent-soft), transparent 60%),
      radial-gradient(700px 480px at -10% 110%, rgba(56,189,248,.10), transparent 60%),
      var(--bg);
  }
}
@media (max-width: 420px){
  .card-login{ border-radius:1rem; }
  .card-foot{ flex-direction:column; gap:.7rem; }
}

/* ---------- Acessibilidade / movimento reduzido ---------- */
@media (prefers-reduced-motion: reduce){
  *,*::before,*::after{ animation:none !important; transition:none !important; }
}
</style>
</head>
<body>

<div class="login-shell">

  <!-- ================= Painel de marca ================= -->
  <aside class="brand-pane" aria-hidden="true">
    <div class="gridlines"></div>
    <div class="topo">
      <svg viewBox="0 0 900 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <path d="M-50,720 C120,650 240,700 360,640 C500,570 560,610 700,540 C820,480 900,510 980,460"/>
        <path d="M-50,640 C130,580 250,630 370,570 C510,500 570,540 710,470 C830,410 900,440 980,390"/>
        <path d="M-50,560 C140,510 260,560 380,500 C520,430 580,470 720,400 C840,340 900,370 980,320"/>
        <path d="M-50,480 C150,440 270,490 390,430 C530,360 590,400 730,330 C850,270 900,300 980,250"/>
        <path d="M-50,400 C160,370 280,420 400,360 C540,290 600,330 740,260 C860,200 900,230 980,180"/>
        <path d="M-50,800 C110,720 230,770 350,710 C490,640 550,680 690,610 C810,550 900,580 980,530"/>
        <path d="M-50,880 C100,790 220,840 340,780 C480,710 540,750 680,680 C800,620 900,650 980,600"/>
      </svg>
    </div>

    <div class="brand-top">
      <div class="brand-logo">
        <img src="<?= 'http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/atlas_2026.png'?>" alt="Atlas">
      </div>
    </div>

    <div class="brand-hero">
      <span class="brand-eyebrow"><span class="dot"></span> Central de Acesso</span>
      <h1>Toda a rotina do cartório,<br><span class="grad">em um só lugar.</span></h1>
      <p>Ordens de serviço, notas devolutivas, ofícios, caixa, assinatura digital, mapas e muito mais. O Atlas reúne as ferramentas do dia a dia da serventia em uma única central.</p>
    </div>

    <div class="brand-foot">
      <span class="chip"><i class="bi bi-shield-lock-fill"></i> Acesso monitorado e registrado</span>
      <span class="chip"><i class="bi bi-grid-fill"></i> Mais de 20 módulos integrados</span>
      <span class="chip"><i class="bi bi-pen-fill"></i> Assinatura digital ICP-Brasil</span>
    </div>
  </aside>

  <!-- ================= Painel do formulário ================= -->
  <main class="form-pane">
    <div class="card-login">

      <div class="card-head">
        <!-- logomarca (aparece somente no mobile, quando o painel esquerdo some) -->
        <div class="mobile-logo">
          <img src="<?= 'http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/atlas_2026.png'?>" alt="Atlas">
        </div>
        <h2>Bem-vindo de volta</h2>
        <p>Entre com suas credenciais para acessar o sistema.</p>
      </div>

      <!-- mensagem git pull (opcional) -->
      <!-- <div class="alert alert-info text-center"><?= htmlspecialchars($mensagemAtualizacao) ?></div> -->

      <?php if ($isBlocked): ?>
          <div class="block-panel">
            <div class="block-icon"><i class="bi bi-lock-fill"></i></div>
            <h5>Acesso temporariamente bloqueado</h5>
            <p class="desc">Há uma pendência que impede o acesso ao sistema neste momento.</p>

            <?php if ($boletoLink): ?>
              <p class="mb-2">
                <a href="<?= htmlspecialchars($boletoLink) ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
                  <i class="bi bi-receipt"></i> Baixar boleto para regularização
                </a>
              </p>
            <?php endif; ?>

            <?php if ($allowEmergency && !$emergencyActive): ?>
              <button id="reqUnlock" class="btn btn-atlas">
                <i class="bi bi-unlock"></i> Solicitar desbloqueio emergencial
              </button>
              <small id="reqMsg" class="d-block mt-2 text-body-secondary"></small>
            <?php elseif ($emergencyActive): ?>
              <span class="emergency-ok">
                <i class="bi bi-check-circle-fill"></i> Liberação emergencial ativa — faça o login normalmente.
              </span>
            <?php endif; ?>
          </div>

      <?php else: ?>
          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-soft-danger text-center mb-4" role="alert">
              <i class="bi bi-exclamation-circle-fill"></i>
              <?= $_GET['error']==1 ? 'Usuário ou senha incorretos. Tente novamente.'
                : ($_GET['error']==3 ? 'A verificação em duas etapas expirou. Entre novamente.'
                                      : 'Usuário inativo. Contate o administrador.') ?>
            </div>
          <?php endif; ?>

          <!-- ---------- Form ---------- -->
          <form id="loginForm" action="check_login.php" method="POST" autocomplete="off" novalidate>
            <div class="field">
              <label for="username">Usuário</label>
              <div class="control">
                <i class="bi bi-person-fill lead-icon" aria-hidden="true"></i>
                <input type="text"
                       id="username"
                       name="username"
                       value="<?= htmlspecialchars($prevUser) ?>"
                       class="form-control"
                       placeholder="Digite seu usuário"
                       required autofocus>
              </div>
            </div>

            <div class="field mb-2">
              <label for="password">Senha</label>
              <div class="control">
                <i class="bi bi-key-fill lead-icon" aria-hidden="true"></i>
                <input type="password"
                       id="password"
                       name="password"
                       value="<?= htmlspecialchars($prevPass) ?>"
                       class="form-control"
                       placeholder="Digite sua senha"
                       required>
                <button type="button" class="tail-btn" id="togglePwd" title="Mostrar/Ocultar senha" aria-label="Mostrar ou ocultar senha">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <div class="capslock-hint" id="capsHint">
                <i class="bi bi-capslock-fill"></i> Caps Lock está ativado
              </div>
            </div>

            <button class="btn-atlas mt-3" id="btnEntrar" type="submit">
              <span class="btn-label"><i class="bi bi-box-arrow-in-right me-1"></i> Entrar</span>
            </button>
          </form>
      <?php endif; ?>

      <div class="card-foot">
        <span><i class="bi bi-shield-check me-1"></i>Ambiente seguro &bull; Atlas &copy; <?= date('Y') ?></span>
        <button class="theme-toggle" id="themeToggle" type="button" title="Alternar tema claro/escuro" aria-label="Alternar tema">
          <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
      </div>

    </div>
  </main>
</div>


<!-- ======= Modais (podem existir simultaneamente; aparecem em sequência) ======= -->

<?php if ($pendenciaMsg): /* Vermelho - aceita HTML */ ?>
<div class="modal fade modal-tone-danger" id="modalPendencia" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-1"></i> Aviso de Pendência</h5>
      </div>
      <div class="modal-body">
        <div class="mb-0"><?= $pendenciaMsg ?></div>
        <?php if ($boletoLink): ?>
          <hr>
          <a href="<?= htmlspecialchars($boletoLink) ?>" target="_blank" class="btn btn-outline-light w-100 fw-semibold">
            <i class="bi bi-receipt"></i> Baixar boleto para regularização
          </a>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button data-next type="button" class="btn btn-light w-100 text-danger fw-semibold">Ok</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($boletoLink || $boletoMsg): /* Âmbar - aceita HTML */ ?>
<div class="modal fade modal-tone-warning" id="modalBoleto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-coin me-1"></i> Boleto de Cobrança</h5>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <?= $boletoMsg ?: '<p>Há um boleto em aberto para regularização.</p>' ?>
        </div>
        <?php if ($boletoLink): ?>
          <a href="<?= htmlspecialchars($boletoLink) ?>" target="_blank" class="btn btn-outline-light w-100 fw-semibold">
            <i class="bi bi-receipt"></i> Abrir Boleto
          </a>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button data-next type="button" class="btn btn-light w-100">Ok</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($importanteMsg): /* Azul - aceita HTML */ ?>
<div class="modal fade modal-tone-info" id="modalImportante" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-info-circle-fill me-1"></i> Comunicado Importante</h5>
      </div>
      <div class="modal-body">
        <div class="mb-0"><?= $importanteMsg ?></div>
      </div>
      <div class="modal-footer">
        <button data-next type="button" class="btn btn-light w-100">Ok</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


<script src="script/jquery-3.6.0.min.js"></script>
<script src="script/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

  /* ========= Tema claro/escuro (persistido no navegador) ========= */
  const root       = document.documentElement;
  const themeBtn   = document.getElementById('themeToggle');
  const themeIcon  = document.getElementById('themeIcon');

  function applyTheme(t){
    root.setAttribute('data-theme', t);
    localStorage.setItem('atlasTheme', t);
    if (themeIcon) themeIcon.className = t === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
  }
  applyTheme(localStorage.getItem('atlasTheme') || 'dark');
  themeBtn?.addEventListener('click', () =>
    applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

  /* ========= Persistência de credenciais (somente no navegador) ========== */
  const urlParams = new URLSearchParams(location.search);
  const erro      = urlParams.get('error');        // error=1 | 2 | 3
  const usrInput  = document.getElementById('username');
  const pwdInput  = document.getElementById('password');

  if (erro && sessionStorage.getItem('atlasUsr')) {
      usrInput && (usrInput.value = sessionStorage.getItem('atlasUsr'));
      pwdInput && (pwdInput.value = sessionStorage.getItem('atlasPwd'));
  } else {
      sessionStorage.removeItem('atlasUsr');
      sessionStorage.removeItem('atlasPwd');
  }

  /* ========= Envio: salva credenciais + estado de carregamento ========= */
  const form      = document.getElementById('loginForm');
  const btnEntrar = document.getElementById('btnEntrar');
  form?.addEventListener('submit', (e) => {
      if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
      sessionStorage.setItem('atlasUsr', usrInput?.value || '');
      sessionStorage.setItem('atlasPwd', pwdInput?.value || '');
      if (btnEntrar) {
        btnEntrar.disabled = true;
        btnEntrar.innerHTML =
          '<span class="spinner-border me-2" role="status" aria-hidden="true"></span>Entrando...';
      }
  });

  /* ========== Mostrar / ocultar senha ========== */
  const toggle = document.getElementById('togglePwd');
  toggle?.addEventListener('click', () => {
      const icon = toggle.querySelector('i');
      if (pwdInput.type === 'password') {
          pwdInput.type = 'text';
          icon.classList.replace('bi-eye','bi-eye-slash');
      } else {
          pwdInput.type = 'password';
          icon.classList.replace('bi-eye-slash','bi-eye');
      }
      pwdInput.focus();
  });

  /* ========== Aviso de Caps Lock ========== */
  const capsHint = document.getElementById('capsHint');
  pwdInput?.addEventListener('keyup', (ev) => {
      if (typeof ev.getModifierState === 'function')
        capsHint?.classList.toggle('show', ev.getModifierState('CapsLock'));
  });
  pwdInput?.addEventListener('blur', () => capsHint?.classList.remove('show'));

  /* ======== Solicitar liberação emergencial ======== */
  const btn = document.getElementById('reqUnlock');
  if (btn) {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      const msgSpan = document.getElementById('reqMsg');
      if (msgSpan) msgSpan.textContent = 'Enviando...';

      try {
        const fd = new FormData();
        fd.append('action','request_emergency');
        fd.append('cns','<?= htmlspecialchars($cns) ?>');

        await fetch('<?= $apiUrl ?>?token=<?= $apiToken ?>',{
          method:'POST',
          body:fd,
          mode:'no-cors'
        });

        Swal.fire({
          icon:'success',
          title:'Solicitação enviada!',
          text:'Recarregando...',
          timer:1200,
          showConfirmButton:false,
          didOpen: () => { setTimeout(()=>location.reload(), 1000); }
        });

      } catch(e) {
        Swal.fire({ icon:'error', title:'Erro', text:'Falha de rede. Tente novamente.' });
        btn.disabled=false;
        if (msgSpan) msgSpan.textContent='';
      }
    });
  }

  /* ========== Fila de modais (ordem de prioridade) ========== */
  // Sempre abre na ordem: Pendência -> Boleto -> Importante
  const queue = ['modalPendencia','modalBoleto','modalImportante']
    .map(id => document.getElementById(id))
    .filter(Boolean);

  function showSequential(index){
    if (index >= queue.length) return;
    const el = queue[index];
    const modal = new bootstrap.Modal(el, {backdrop: index===0 ? 'static' : true});
    modal.show();

    // botões data-next avançam a fila
    el.querySelectorAll('[data-next]').forEach(b=>{
      b.addEventListener('click', ()=> modal.hide());
    });

    el.addEventListener('hidden.bs.modal', () => {
      modal.dispose();
      showSequential(index+1);
    }, {once:true});
  }
  if (queue.length) showSequential(0);
});
</script>
</body>
</html>
