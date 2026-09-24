<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
asg_ensure_schema();

$username = $_SESSION['username'];
$CSRF = asg_csrf();
$cfg = asg_config();
$u   = asg_ucfg($username);
$certInfo = asg_cert_info($username);
$logo = asg_logo_path();
$metodo = $u['metodo'] ?? 'a3';
$assinador = asg_a3_assinador($u);
$tcCon = asg_tc_conexao();
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<?php include(__DIR__ . '/../os/guia/guia.php'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Signum · Configurar</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{ --sg-primary:#2563eb; --sg-primary2:#1e40af; --sg-bg:#f1f5f9; --sg-text:#0f172a; --sg-muted:#64748b; --sg-card:#fff; --sg-border:#e5e9f0; }
body.dark-mode{ --sg-bg:#0f1216; --sg-text:#e5e7eb; --sg-muted:#9aa4b2; --sg-card:#1c2126; --sg-border:rgba(255,255,255,.08); }
#main .container{ max-width:900px; padding-bottom:120px; }
.sg-hero{ background:var(--sg-card); border:1px solid var(--sg-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.sg-title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.sg-ic{ width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg,var(--sg-primary),var(--sg-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.sg-hero h1{ font-size:1.4rem; font-weight:800; margin:0; color:var(--sg-text); } .sg-sub{ color:var(--sg-muted); font-size:.9rem; margin-top:2px; }
.sg-pill{ border-radius:999px; font-weight:600; padding:9px 16px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.16s; text-decoration:none; font-size:.9rem; }
.sg-pri{ background:linear-gradient(135deg,var(--sg-primary),var(--sg-primary2)); color:#fff; } .sg-pri:hover{ transform:translateY(-2px); color:#fff; }
.sg-soft{ background:var(--sg-bg); color:var(--sg-text); border:1px solid var(--sg-border); }
.card-blk{ background:var(--sg-card); border:1px solid var(--sg-border); border-radius:18px; padding:22px; box-shadow:0 8px 24px rgba(15,23,42,.05); margin-bottom:18px; }
.card-blk h5{ font-weight:800; font-size:1rem; color:var(--sg-text); margin:0 0 4px; display:flex; align-items:center; gap:9px; }
.card-blk .hint{ color:var(--sg-muted); font-size:.85rem; margin-bottom:16px; }
.field{ margin-bottom:14px; } .field label{ font-size:.82rem; font-weight:700; color:var(--sg-muted); margin-bottom:6px; display:block; }
.inp{ width:100%; border:1px solid var(--sg-border); border-radius:12px; padding:11px 14px; font-size:.94rem; color:var(--sg-text); background:var(--sg-card); outline:none; }
.inp:focus{ border-color:var(--sg-primary); box-shadow:0 0 0 3px rgba(37,99,235,.14); }
.row2{ display:grid; grid-template-columns:1fr 1fr; gap:14px; } @media(max-width:640px){ .row2{ grid-template-columns:1fr; } }
.cert-badge{ display:inline-flex; align-items:center; gap:8px; font-size:.84rem; font-weight:600; padding:8px 14px; border-radius:12px; }
.cert-ok{ background:#dcfce7; color:#166534; } .cert-warn{ background:#fef3c7; color:#92400e; } .cert-err{ background:#fee2e2; color:#991b1b; }
.logo-prev{ width:90px; height:90px; border-radius:14px; border:1px solid var(--sg-border); background:var(--sg-bg) center/contain no-repeat; flex:0 0 auto; }
.file-btn{ display:inline-flex; align-items:center; gap:8px; border:1px dashed var(--sg-border); background:var(--sg-bg); color:var(--sg-text); border-radius:12px; padding:10px 16px; cursor:pointer; font-weight:600; font-size:.9rem; }
.file-btn:hover{ border-color:var(--sg-primary); color:var(--sg-primary); }
.switch{ display:inline-flex; align-items:center; gap:8px; font-size:.9rem; color:var(--sg-text); }
.methods{ display:grid; grid-template-columns:1fr 1fr; gap:14px; } @media(max-width:640px){ .methods{ grid-template-columns:1fr; } }
.method{ border:2px solid var(--sg-border); border-radius:16px; padding:16px; cursor:pointer; transition:.16s; position:relative; }
.method:hover{ border-color:var(--sg-primary); } .method.sel{ border-color:var(--sg-primary); background:rgba(37,99,235,.05); }
.method .mt{ font-weight:800; color:var(--sg-text); display:flex; align-items:center; gap:8px; } .method .md{ font-size:.82rem; color:var(--sg-muted); margin-top:4px; }
.method .badge-sel{ position:absolute; top:12px; right:12px; color:var(--sg-primary); display:none; } .method.sel .badge-sel{ display:block; }
.only-a1,.only-a3,.only-serpro,.only-tcloud{ display:none; }
.tc-inst{ margin-top:16px; border:1px solid #fcd34d; background:#fffbeb; border-radius:14px; padding:16px; }
.tc-inst-tit{ font-weight:800; color:#78350f; margin-bottom:10px; }
.tc-inst-passos{ list-style:none; padding:0; margin:0 0 12px; color:#334155; font-size:.9rem; }
.tc-inst-passos li{ border-bottom-color:#fde68a !important; }
.methods.sub .method{ padding:14px; } .methods.sub .method .mt{ font-size:.95rem; }
.tc-cmd{ display:flex; align-items:center; gap:8px; background:#0f172a; border-radius:10px; padding:10px 10px 10px 14px; overflow-x:auto; }
.tc-cmd code{ color:#e2e8f0; font-family:Consolas,monospace; font-size:.84rem; white-space:nowrap; flex:1; }
.tc-copiar{ flex:0 0 auto; width:32px; height:32px; border:0; border-radius:8px; background:#1e293b; color:#cbd5e1; cursor:pointer; }
.tc-os{ font-size:.8rem; color:var(--sg-muted); margin:10px 0 6px; } .tc-os:first-child{ margin-top:0; }
.tc-copiar:hover{ background:#334155; color:#fff; }
details.adv{ margin-top:16px; border-top:1px solid var(--sg-border); padding-top:12px; }
details.adv summary{ cursor:pointer; font-weight:700; font-size:.86rem; color:var(--sg-muted); outline:none; }
details.adv[open] summary{ margin-bottom:12px; }
.sg-ver{ font-size:.72rem; font-weight:700; color:var(--sg-muted); background:var(--sg-bg); border:1px solid var(--sg-border); border-radius:999px; padding:2px 8px; margin-left:6px; vertical-align:middle; }
/* ---------- responsividade ---------- */
#main .container{ min-width:0; }
.sg-hero,.card-blk{ min-width:0; max-width:100%; }
.tc-cmd code{ min-width:0; }
.card-blk .inp{ min-width:0; }
.method{ min-width:0; } .method .md{ overflow-wrap:anywhere; }
.card-blk code{ overflow-wrap:anywhere; }
@media(max-width:760px){
  #main .container{ padding-left:10px; padding-right:10px; }
  .sg-hero{ padding:16px; border-radius:16px; }
  .sg-hero h1{ font-size:1.2rem; }
  .sg-ic{ width:46px; height:46px; font-size:1.25rem; border-radius:13px; }
  .card-blk{ padding:16px; border-radius:14px; }
  .methods{ gap:10px; }
  .method{ padding:14px; }
  .file-btn{ flex:1 1 auto; justify-content:center; }
  .cert-badge{ max-width:100%; }
}
@media(max-width:420px){
  .sg-pill{ padding:8px 12px; font-size:.84rem; }
  .logo-prev{ width:72px; height:72px; }
}
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="sg-hero">
      <div class="sg-title-row">
        <div class="sg-ic"><i class="fa fa-cog"></i></div>
        <div style="min-width:0"><h1>Configurar · Atlas Signum</h1><div class="sg-sub">Método de assinatura + carimbo do cartório. <span class="sg-ver">v<?php echo eh(ASG_VERSAO); ?></span></div></div>
        <div style="margin-left:auto"><a class="sg-pill sg-soft" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a></div>
      </div>
    </section>

    <form id="cfgForm" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo eh($CSRF); ?>">
      <input type="hidden" name="metodo" id="metodoField" value="<?php echo eh($metodo); ?>">
      <input type="hidden" name="a3_assinador" id="assinadorField" value="<?php echo eh($assinador); ?>">

      <div class="card-blk">
        <h5><i class="fa fa-id-card-o" style="color:var(--sg-primary)"></i> Seu método de assinatura</h5>
        <div class="hint">Escolha como <b>você</b> assina (preferência individual).</div>
        <div class="methods">
          <div class="method <?php echo $metodo==='a3'?'sel':''; ?>" data-m="a3">
            <i class="fa fa-check-circle badge-sel"></i>
            <div class="mt"><i class="fa fa-hdd-o"></i> Certificado A3 (token/cartão)</div>
            <div class="md">Padrão. Assina no momento com o token conectado, pelo <b>Assinador SERPRO</b> ou pelo <b>TCloud Assinador</b>.</div>
          </div>
          <div class="method <?php echo $metodo==='a1'?'sel':''; ?>" data-m="a1">
            <i class="fa fa-check-circle badge-sel"></i>
            <div class="mt"><i class="fa fa-shield"></i> Certificado A1 (arquivo)</div>
            <div class="md">Você envia seu <b>.pfx</b> e senha (guardado só para o seu login). Assinatura direta, sem token.</div>
          </div>
        </div>
      </div>

      <div class="card-blk only-a3">
        <h5><i class="fa fa-plug" style="color:var(--sg-primary)"></i> Assinador do token (A3)</h5>
        <div class="hint">Escolha qual programa vai usar o seu token/cartão para assinar. Vale também para a assinatura de <b>ofícios</b>, <b>notas devolutivas</b> e <b>O.S.</b></div>
        <div class="methods sub">
          <div class="method prov <?php echo $assinador==='tcloud'?'sel':''; ?>" data-a="tcloud">
            <i class="fa fa-check-circle badge-sel"></i>
            <div class="mt"><i class="fa fa-cloud"></i> TCloud Assinador <small style="font-weight:700;color:#16a34a">padrão</small></div>
            <div class="md">Você confirma na janela do TCloud Assinador do seu computador e digita o PIN. Sem pareamento, em qualquer navegador (Windows, Mac e Linux).</div>
          </div>
          <div class="method prov <?php echo $assinador==='serpro'?'sel':''; ?>" data-a="serpro">
            <i class="fa fa-check-circle badge-sel"></i>
            <div class="mt"><i class="fa fa-desktop"></i> Assinador SERPRO</div>
            <div class="md">Programa do SERPRO aberto na sua máquina, conversando direto com o navegador.</div>
          </div>
        </div>
      </div>

      <div class="card-blk only-serpro">
        <h5><i class="fa fa-hdd-o" style="color:var(--sg-primary)"></i> Assinador SERPRO</h5>
        <div class="hint">A assinatura A3 usa o <b>Assinador SERPRO</b> na sua máquina (o mesmo dos ofícios). Basta tê-lo aberto ao assinar; o token pedirá o PIN.</div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <span class="cert-badge cert-warn" id="cfgAssBadge"><i class="fa fa-spinner fa-spin"></i> verificando…</span>
          <button type="button" class="file-btn" id="cfgTestar" style="border-style:solid"><i class="fa fa-refresh"></i> Testar</button>
          <a class="file-btn" href="http://127.0.0.1:65056/" target="_blank" rel="noopener" style="border-style:solid"><i class="fa fa-unlock-alt"></i> Autorizar</a>
        </div>
        <div class="hint" style="margin:12px 0 0"><i class="fa fa-info-circle"></i> Se aparecer offline: abra o Assinador e autorize o navegador (a página precisa estar em HTTPS pela política do Chrome).</div>
      </div>

      <div class="card-blk only-tcloud">
        <h5><i class="fa fa-cloud" style="color:var(--sg-primary)"></i> TCloud Assinador</h5>
        <div class="hint">Ao clicar em <b>Assinar</b>, o navegador abre o TCloud Assinador <b>no computador de quem clicou</b> — sem pareamento e sem programa na bandeja.</div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <span class="cert-badge cert-warn" id="tcBadge"><i class="fa fa-question-circle"></i> ainda não verificado neste computador</span>
          <button type="button" class="file-btn" id="tcVerificarAqui" style="border-style:solid"><i class="fa fa-search"></i> Testar neste computador</button>
        </div>
        <div class="hint" id="tcInfo" style="margin:10px 0 0"></div>

        <!-- instruções: aparecem só enquanto o TCloud Assinador não foi detectado neste computador -->
        <div class="tc-inst" id="tcInstBox" style="display:none">
          <div class="tc-inst-tit"><i class="fa fa-download"></i> Instalar o TCloud Assinador <span id="tcInstSo"></span></div>
          <div id="tcInstCorpo">
            <ol class="tc-inst-passos" id="tcInstPassos"></ol>
            <div class="tc-cmd"><code id="tcInstCmd"></code>
              <button type="button" class="tc-copiar" data-alvo="tcInstCmd" title="Copiar"><i class="fa fa-clipboard"></i></button></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
              <button type="button" class="file-btn" id="tcInstCopiar" style="border-style:solid"><i class="fa fa-clipboard"></i> Copiar comando</button>
              <button type="button" class="file-btn" id="tcInstTestar" style="border-style:solid;background:#16a34a;color:#fff;border-color:#16a34a"><i class="fa fa-check"></i> Já instalei — testar</button>
            </div>
            <div class="hint" style="margin:10px 0 0">O computador precisa também do driver do token. Na primeira assinatura, marque <i>sempre permitir</i> quando o navegador perguntar se pode abrir o TCloud Assinador e responda <b>Sim</b> quando ele perguntar se confia neste servidor.</div>
          </div>
          <div id="tcInstOutro" class="hint" style="display:none;margin:0">O TCloud Assinador funciona em computadores com <b>Windows</b>, <b>Mac</b> ou <b>Linux</b>. Para assinar com o token, use um desses computadores.</div>
        </div>

        <details class="adv">
          <summary><i class="fa fa-terminal"></i> Comandos de instalação para outros computadores</summary>
          <div class="tc-os"><i class="fa fa-windows"></i> Windows — <b>PowerShell</b></div>
          <div class="tc-cmd"><code id="tcCmd"><?php echo eh(ASG_TC_CMD_WINDOWS); ?></code>
            <button type="button" class="tc-copiar" data-alvo="tcCmd" title="Copiar"><i class="fa fa-clipboard"></i></button></div>
          <div class="tc-os"><i class="fa fa-apple"></i> Mac — <b>Terminal</b></div>
          <div class="tc-cmd"><code id="tcCmdMac"><?php echo eh(ASG_TC_CMD_MAC); ?></code>
            <button type="button" class="tc-copiar" data-alvo="tcCmdMac" title="Copiar"><i class="fa fa-clipboard"></i></button></div>
          <div class="tc-os"><i class="fa fa-linux"></i> Linux — <b>Terminal</b></div>
          <div class="tc-cmd"><code id="tcCmdLinux"><?php echo eh(ASG_TC_CMD_LINUX); ?></code>
            <button type="button" class="tc-copiar" data-alvo="tcCmdLinux" title="Copiar"><i class="fa fa-clipboard"></i></button></div>
          <div class="hint" style="margin:8px 0 0">Também em <a href="<?php echo eh(ASG_TC_URL_INSTALACAO); ?>" target="_blank" rel="noopener"><?php echo eh(ASG_TC_URL_INSTALACAO); ?></a>. Para atualizar um computador, rode o mesmo comando.</div>
        </details>
      </div>

      <div class="card-blk only-a1">
        <h5><i class="fa fa-certificate" style="color:var(--sg-primary)"></i> Seu certificado A1 (.pfx)</h5>
        <div class="hint">Enviado apenas para o seu usuário. Pasta protegida; senha criptografada.</div>
        <?php if ($certInfo): ?>
          <div class="cert-badge <?php echo $certInfo['expirado']?'cert-err':'cert-ok'; ?>" style="margin-bottom:14px">
            <i class="fa fa-check-circle"></i> <?php echo eh($certInfo['cn']); ?> — <?php echo $certInfo['expirado']?'EXPIRADO':'válido até '.eh($certInfo['ate']); ?>
          </div>
        <?php endif; ?>
        <div class="row2">
          <div class="field"><label>Arquivo (.pfx/.p12)</label>
            <label class="file-btn"><i class="fa fa-upload"></i> <span id="certName">Escolher arquivo…</span><input type="file" name="cert" id="certInput" accept=".pfx,.p12" hidden></label></div>
          <div class="field"><label>Senha do certificado</label>
            <input class="inp" type="password" name="cert_senha" placeholder="<?php echo $certInfo?'•••••• (em branco p/ manter)':'senha do .pfx'; ?>" autocomplete="new-password"></div>
        </div>
      </div>

      <div class="card-blk">
        <h5><i class="fa fa-picture-o" style="color:var(--sg-primary)"></i> Logomarca do carimbo</h5>
        <div class="hint">PNG ou JPG que aparece no carimbo (compartilhada pelo cartório).</div>
        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap">
          <div class="logo-prev" id="logoPrev" style="background-image:url('<?php echo $logo?'logo_img.php?t='.time():''; ?>')"></div>
          <label class="file-btn"><i class="fa fa-upload"></i> <span id="logoName">Escolher imagem…</span><input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg" hidden></label>
        </div>
      </div>

      <div class="card-blk">
        <h5><i class="fa fa-pencil-square-o" style="color:var(--sg-primary)"></i> Dados do carimbo</h5>
        <div class="field"><label class="switch"><input type="checkbox" name="usar_cn_titular" value="1" <?php echo !empty($u['usar_cn_titular'])?'checked':''; ?>> Usar o nome do titular do certificado como assinante</label></div>
        <div class="row2">
          <div class="field"><label>Nome do assinante (conforme o certificado)</label><input class="inp" name="assinante_nome" value="<?php echo eh($u['assinante_nome'] ?? ''); ?>" placeholder="Ex.: João da Silva"></div>
          <div class="field"><label>CPF (aparece no carimbo)</label><input class="inp" name="assinante_cpf" id="cpfInp" value="<?php echo eh(asg_cpf_fmt($u['assinante_cpf'] ?? '')); ?>" placeholder="000.000.000-00" maxlength="14"></div>
        </div>
        <div class="row2">
          <div class="field"><label>Cargo / função</label><input class="inp" name="assinante_cargo" value="<?php echo eh($u['assinante_cargo'] ?? ''); ?>" placeholder="Ex.: Tabelião"></div>
          <div class="field"><label>Local (cidade/UF)</label><input class="inp" name="assinante_local" value="<?php echo eh($u['assinante_local'] ?? ''); ?>" placeholder="Ex.: Bom Jardim/MA"></div>
        </div>
        <div class="field" style="font-size:.82rem;color:var(--sg-muted)"><i class="fa fa-info-circle"></i> No <b>A1</b>, o nome e o CPF são lidos automaticamente do seu certificado. No <b>A3 com o Assinador SERPRO</b>, informe aqui o CPF que deve constar no carimbo (o Assinador só revela o certificado no momento da assinatura). No <b>TCloud Assinador</b>, nome e CPF saem do próprio certificado usado; cargo e local daqui entram na chancela.</div>
        <div class="row2">
          <div class="field"><label>Título do carimbo</label><input class="inp" name="carimbo_titulo" value="<?php echo eh($cfg['carimbo_titulo'] ?? 'Assinado digitalmente'); ?>"></div>
          <div class="field"><label>Motivo da assinatura</label><input class="inp" name="motivo" value="<?php echo eh($cfg['motivo'] ?? ''); ?>" placeholder="Assinatura eletrônica de documento"></div>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px">
        <a class="sg-pill sg-soft" href="index.php">Cancelar</a>
        <button type="submit" class="sg-pill sg-pri"><i class="fa fa-check"></i> Salvar configurações</button>
      </div>
    </form>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../oficios/serpro/serpro-signer-promise.js"></script>
<script src="../oficios/serpro/serpro-signer-client.js"></script>
<script src="js/tcloud_signum.js?v=<?php echo eh(ASG_VERSAO); ?>"></script>
<script>
(function(){
  var $=function(s){return document.querySelector(s);}, $$=function(s){return Array.prototype.slice.call(document.querySelectorAll(s));};
  // display explícito ('block'/'none'): com a classe em display:none, style.display='' não mostra
  function mostra(sel,on){ $$(sel).forEach(function(e){ e.style.display=on?'block':'none'; }); }
  function aplicaVisibilidade(){
    var m=$('#metodoField').value, a=$('#assinadorField').value;
    mostra('.only-a1', m==='a1');
    mostra('.only-a3', m==='a3');
    mostra('.only-serpro', m==='a3' && a==='serpro');
    mostra('.only-tcloud', m==='a3' && a==='tcloud');
    if(m==='a3' && a==='serpro') testarAssinador(true);
    if(m==='a3' && a==='tcloud') tcAtualizar();
  }
  function aplicaMetodo(m){
    $('#metodoField').value=m;
    $$('.method[data-m]').forEach(function(x){ x.classList.toggle('sel', x.dataset.m===m); });
    aplicaVisibilidade();
  }
  function aplicaAssinador(a){
    $('#assinadorField').value=a;
    $$('.method.prov').forEach(function(x){ x.classList.toggle('sel', x.dataset.a===a); });
    aplicaVisibilidade();
  }
  $$('.method[data-m]').forEach(function(x){ x.addEventListener('click',function(){ aplicaMetodo(x.dataset.m); }); });
  $$('.method.prov').forEach(function(x){ x.addEventListener('click',function(){ aplicaAssinador(x.dataset.a); }); });

  $('#certInput').addEventListener('change',function(){ $('#certName').textContent=this.files[0]?this.files[0].name:'Escolher arquivo…'; });
  var cpfInp=$('#cpfInp'); if(cpfInp) cpfInp.addEventListener('input',function(){
    var d=this.value.replace(/\D/g,'').slice(0,11), o=d;
    if(d.length>9) o=d.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/,'$1.$2.$3-$4');
    else if(d.length>6) o=d.replace(/(\d{3})(\d{3})(\d{1,3})/,'$1.$2.$3');
    else if(d.length>3) o=d.replace(/(\d{3})(\d{1,3})/,'$1.$2');
    this.value=o;
  });
  $('#logoInput').addEventListener('change',function(){ if(this.files[0]){ $('#logoName').textContent=this.files[0].name; $('#logoPrev').style.backgroundImage="url('"+URL.createObjectURL(this.files[0])+"')"; } });

  $('#cfgForm').addEventListener('submit',async function(ev){
    ev.preventDefault();
    var btn=this.querySelector('button[type=submit]'); btn.disabled=true; var h=btn.innerHTML; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Salvando...';
    try{
      var r=await fetch('salvar_config.php',{method:'POST',body:new FormData(this),credentials:'same-origin'}); var j=await r.json();
      if(!j.success) throw new Error(j.message||'Falha.');
      await Swal.fire({icon:'success',title:'Salvo!',text:j.message,timer:1600,showConfirmButton:false});
      location.reload();
    }catch(e){ btn.disabled=false; btn.innerHTML=h; Swal.fire('Erro', e.message, 'error'); }
  });

  // ---- Teste do Assinador SERPRO (API oficial) ----
  var C=window.SerproSignerClient||null;
  function serproVerificarEConectar(){ return new Promise(function(res){ if(!C) return res(false);
    var d=false; function fim(v){ if(!d){d=true;res(v);} }
    try{ C.verifyIsInstalledAndRunning().success(function(){
        try{ C.connect(function(){fim(true);},function(){},function(){fim(false);}); }catch(e){ fim(false); }
      }).error(function(){ fim(false); });
    }catch(e){ fim(false); } setTimeout(function(){ fim(false); },7000); }); }
  async function testarAssinador(silencioso){
    var b=$('#cfgAssBadge'); if(!b) return;
    function set(ok,txt){ b.className='cert-badge '+(ok?'cert-ok':'cert-err'); b.innerHTML='<i class="fa '+(ok?'fa-check-circle':'fa-times-circle')+'"></i> '+txt; }
    b.className='cert-badge cert-warn'; b.innerHTML='<i class="fa fa-spinner fa-spin"></i> verificando…';
    if(!C){ set(false,'cliente indisponível'); if(!silencioso) Swal.fire('Assinador','Arquivos do Assinador não carregaram (../oficios/serpro).','warning'); return; }
    var ok=await serproVerificarEConectar(); set(ok, ok?'online':'offline');
    if(!ok && !silencioso) Swal.fire('Assinador offline','Abra o Assinador SERPRO e clique em "Autorizar" (a página precisa estar em HTTPS).','warning');
  }
  var ct=$('#cfgTestar'); if(ct) ct.addEventListener('click',function(){ testarAssinador(false); });

  // ---- TCloud Assinador ----
  var TC=window.TcSignum||null;
  if(TC) TC.init({ csrf: <?php echo json_encode($CSRF); ?>, endpoint: 'tcloud_api.php', urlInstalacao: <?php echo json_encode(ASG_TC_URL_INSTALACAO); ?>,
                   cmdExecutar: <?php echo json_encode(ASG_TC_CMD_EXECUTAR); ?>, cmdMac: <?php echo json_encode(ASG_TC_CMD_MAC); ?>,
                   cmdLinux: <?php echo json_encode(ASG_TC_CMD_LINUX); ?>,
                   aoMudarApp: function(){ tcAtualizar(); } });
  var tva=$('#tcVerificarAqui'); if(tva) tva.addEventListener('click',async function(){ if(TC){ await TC.verificar(); tcAtualizar(); } });
  var tic=$('#tcInstCopiar'); if(tic) tic.addEventListener('click',function(){
    if(!TC) return; var ok=TC.copiar($('#tcInstCmd').textContent);
    tic.innerHTML=ok?'<i class="fa fa-check"></i> Copiado':'<i class="fa fa-clipboard"></i> Copiar comando';
    setTimeout(function(){ tic.innerHTML='<i class="fa fa-clipboard"></i> Copiar comando'; },1800);
  });
  var tit=$('#tcInstTestar'); if(tit) tit.addEventListener('click',async function(){ if(TC){ await TC.verificar(true); tcAtualizar(); } });
  function tcBadge(cls,ic,txt){ var b=$('#tcBadge'); b.className='cert-badge '+cls; b.innerHTML='<i class="fa '+ic+'"></i> '+txt; }
  function tcDataHora(d){ function z(n){ return (n<10?'0':'')+n; } return z(d.getDate())+'/'+z(d.getMonth()+1)+' às '+z(d.getHours())+':'+z(d.getMinutes()); }
  var tcInstPronto=false;
  function tcMontaInstrucoes(){
    if(tcInstPronto||!TC) return; tcInstPronto=true;
    var ins=TC.instrucoes();
    if(!ins.cmd){ $('#tcInstCorpo').style.display='none'; $('#tcInstOutro').style.display='block'; $('#tcInstSo').textContent=''; return; }
    $('#tcInstSo').textContent='neste computador ('+ins.nome+')';
    $('#tcInstPassos').innerHTML=ins.passos;
    $('#tcInstCmd').textContent=ins.cmd;
  }
  /* O navegador não enxerga programas instalados: o status é o que este navegador sabe
     (o app respondeu num teste ou numa assinatura, ou não respondeu). */
  function tcAtualizar(){
    if(!TC){ tcBadge('cert-err','fa-times-circle','script não carregou'); return; }
    var i=TC.appInfo(), info=$('#tcInfo'), box=$('#tcInstBox');
    if(i.status==='ok'){
      tcBadge('cert-ok','fa-check-circle','detectado neste computador');
      info.textContent=(i.em?'Respondeu pela última vez em '+tcDataHora(i.em)+'. ':'')+'Se ele foi desinstalado, use “Testar neste computador”.';
      box.style.display='none';
    } else {
      if(i.status==='nao'){ tcBadge('cert-err','fa-times-circle','não detectado neste computador'); info.textContent='Ele não respondeu da última vez. Instale (ou atualize) com as instruções abaixo.'; }
      else { tcBadge('cert-warn','fa-question-circle','ainda não verificado neste computador'); info.textContent='Se ainda não está instalado, siga as instruções abaixo; se já está, use “Testar neste computador”.'; }
      tcMontaInstrucoes(); box.style.display='block';
    }
  }
  $$('.tc-copiar').forEach(function(tcc){ tcc.addEventListener('click',function(){
    var alvo=document.getElementById(tcc.dataset.alvo); if(!alvo) return;
    var ok=function(){ tcc.innerHTML='<i class="fa fa-check"></i>'; setTimeout(function(){ tcc.innerHTML='<i class="fa fa-clipboard"></i>'; },1500); };
    if(navigator.clipboard&&window.isSecureContext) navigator.clipboard.writeText(alvo.textContent).then(ok);
    else { var r=document.createRange(); r.selectNodeContents(alvo); var sel=getSelection(); sel.removeAllRanges(); sel.addRange(r); try{ document.execCommand('copy'); ok(); }catch(e){} sel.removeAllRanges(); }
  }); });

  aplicaMetodo($('#metodoField').value);
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
