<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
if (!forja_is_admin()) { header('Location: index.php'); exit; }

$CSRF = forja_csrf();
$cfg = forja_config();
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Forja · Configurar</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{ --fj-primary:#ea580c; --fj-primary2:#c2410c; --fj-bg:#f1f5f9; --fj-text:#0f172a; --fj-muted:#64748b; --fj-card:#fff; --fj-border:#e5e9f0; }
body.dark-mode{ --fj-bg:#0f1216; --fj-text:#e5e7eb; --fj-muted:#9aa4b2; --fj-card:#1c2126; --fj-border:rgba(255,255,255,.08); }
#main .container{ max-width:820px; padding-bottom:120px; }
.fj-hero{ background:var(--fj-card); border:1px solid var(--fj-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.fj-title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.fj-ic{ width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.fj-hero h1{ font-size:1.4rem; font-weight:800; margin:0; color:var(--fj-text); } .fj-sub{ color:var(--fj-muted); font-size:.9rem; margin-top:2px; }
.fj-pill{ border-radius:999px; font-weight:600; padding:9px 16px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.16s; text-decoration:none; font-size:.9rem; }
.fj-pri{ background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; } .fj-pri:hover{ transform:translateY(-2px); color:#fff; }
.fj-soft{ background:var(--fj-bg); color:var(--fj-text); border:1px solid var(--fj-border); } .fj-soft:hover{ background:var(--fj-border); color:var(--fj-text); }
.card-blk{ background:var(--fj-card); border:1px solid var(--fj-border); border-radius:18px; padding:22px; box-shadow:0 8px 24px rgba(15,23,42,.05); margin-bottom:18px; }
.card-blk h5{ font-weight:800; font-size:1rem; color:var(--fj-text); margin:0 0 4px; display:flex; align-items:center; gap:9px; }
.card-blk .hint{ color:var(--fj-muted); font-size:.85rem; margin-bottom:16px; }
.field{ margin-bottom:14px; } .field label{ font-size:.82rem; font-weight:700; color:var(--fj-muted); margin-bottom:6px; display:block; }
.inp{ width:100%; border:1px solid var(--fj-border); border-radius:12px; padding:11px 14px; font-size:.92rem; color:var(--fj-text); background:var(--fj-card); outline:none; font-family:monospace; }
.inp:focus{ border-color:var(--fj-primary); box-shadow:0 0 0 3px rgba(234,88,12,.14); }
.stat{ display:flex; align-items:center; gap:10px; padding:12px; border-radius:12px; border:1px solid var(--fj-border); background:var(--fj-bg); margin-bottom:10px; }
.stat .d{ width:12px;height:12px;border-radius:50%;background:#eab308;flex:0 0 auto; } .stat.ok .d{ background:#22c55e; } .stat.no .d{ background:#ef4444; }
.stat b{ color:var(--fj-text); font-size:.9rem; } .stat small{ display:block; color:var(--fj-muted); font-family:monospace; word-break:break-all; }
.switch{ display:inline-flex; align-items:center; gap:8px; font-size:.9rem; color:var(--fj-text); }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="fj-hero">
      <div class="fj-title-row">
        <div class="fj-ic"><i class="fa fa-cog"></i></div>
        <div style="min-width:0"><h1>Configurar · Atlas Forja</h1><div class="fj-sub">Ferramentas de conversão e ativação do módulo.</div></div>
        <div style="margin-left:auto"><a class="fj-pill fj-soft" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a></div>
      </div>
    </section>

    <div class="card-blk">
      <h5><i class="fa fa-cogs" style="color:var(--fj-primary)"></i> Ferramentas externas</h5>
      <div class="hint">O <b>Ghostscript</b> comprime e converte PDF→imagens. O <b>LibreOffice</b> faz Word ↔ PDF. O <b>ImageMagick</b> é alternativa. Deixe em branco para detecção automática ou informe o caminho.</div>

      <div id="statusFerramentas">
        <div class="stat"><span class="d"></span><div><b>Verificando ferramentas…</b></div></div>
      </div>
      <button type="button" class="fj-pill fj-soft" id="btnTestar" style="margin-bottom:16px"><i class="fa fa-refresh"></i> Testar ferramentas</button>

      <form id="cfgForm">
        <input type="hidden" name="csrf" value="<?php echo eh($CSRF); ?>">
        <div class="field">
          <label>Caminho do Ghostscript (gswin64c.exe)</label>
          <input class="inp" name="gs_path" value="<?php echo eh($cfg['gs_path'] ?? ''); ?>" placeholder="C:\Program Files\gs\gs10.02.1\bin\gswin64c.exe">
        </div>
        <div class="field">
          <label>Caminho do ImageMagick (magick.exe) — opcional</label>
          <input class="inp" name="magick_path" value="<?php echo eh($cfg['magick_path'] ?? ''); ?>" placeholder="C:\Program Files\ImageMagick-7.1.1-Q16-HDRI\magick.exe">
        </div>
        <div class="field">
          <label>Caminho do LibreOffice (soffice.exe) — para Word ↔ PDF</label>
          <input class="inp" name="lo_path" value="<?php echo eh($cfg['lo_path'] ?? ''); ?>" placeholder="C:\Program Files\LibreOffice\program\soffice.exe">
        </div>
        <div class="field">
          <label>Tamanho máximo de arquivo enviado (MB)</label>
          <input class="inp" type="number" min="10" max="4096" step="10" name="limite_upload_mb" value="<?php echo eh($cfg['limite_upload_mb'] ?? 2048); ?>">
          <?php $L = forja_limites_php(); ?>
          <div class="hint" style="margin:6px 0 0">
            Limite em vigor agora: <b><?php echo eh(forja_human($L['efetivo'])); ?></b>
            (php.ini: upload_max_filesize <b><?php echo eh(forja_human($L['upload'])); ?></b>,
             post_max_size <b><?php echo eh(forja_human($L['post'])); ?></b>,
             max_execution_time <b><?php echo (int)$L['tempo']; ?>s</b>).
            <?php if ($L['efetivo'] < $L['config']): ?>
              <br><b style="color:#c2410c">O php.ini está segurando o limite.</b> Ajuste os dois valores em
              <code>C:\xampp\php\php.ini</code> e reinicie o Apache — o <code>.htaccess</code> do módulo só
              funciona quando o PHP roda como módulo do Apache.
            <?php endif; ?>
            <?php if (!$L['x64']): ?>
              <br><b style="color:#c2410c">Este PHP é de 32 bits</b> e não trata arquivos acima de 2 GB.
            <?php endif; ?>
          </div>
        </div>
        <div class="field">
          <label>Retenção dos arquivos gerados (horas)</label>
          <input class="inp" type="number" min="1" max="72" name="retencao_horas" value="<?php echo eh($cfg['retencao_horas'] ?? 3); ?>">
          <div class="hint" style="margin:6px 0 0">Depois desse prazo os PDFs/ZIPs gerados e os temporários são apagados sozinhos. Os temporários de cada conversão já são removidos assim que o processamento termina.</div>
        </div>
        <div class="field">
          <label>Resolução máxima em Imagens → PDF (pixels no maior lado)</label>
          <input class="inp" type="number" min="600" max="6000" step="100" name="img2pdf_max_px" value="<?php echo eh($cfg['img2pdf_max_px'] ?? 2600); ?>">
          <div class="hint" style="margin:6px 0 0">Padrão <b>2600</b>. Valores maiores deixam o PDF mais pesado e a conversão mais lenta; imagens abaixo do limite passam direto, sem reprocessar.</div>
        </div>
        <div class="field">
          <label class="switch"><input type="checkbox" name="forja_ativo" value="S" <?php echo (($cfg['forja_ativo'] ?? 'S')==='S')?'checked':''; ?>> Módulo ativo (mostrar o card no início do Atlas)</label>
        </div>
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="fj-pill fj-pri"><i class="fa fa-check"></i> Salvar</button>
        </div>
      </form>
    </div>

    <div class="card-blk">
      <h5><i class="fa fa-magic" style="color:var(--fj-primary)"></i> Instalar LibreOffice automaticamente (portátil)</h5>
      <div class="hint">Sem entrar no servidor: cole a URL de um <b>.zip do LibreOffice portátil</b> (recomendado — você hospeda internamente) ou de um <b>.msi oficial</b> (Windows). O módulo baixa e extrai para dentro de <code>forja/libreoffice/</code> e configura o caminho sozinho. Não requer instalação nem administrador.</div>
      <div class="field">
        <label>URL do pacote (.zip ou .msi)</label>
        <input class="inp" id="loUrl" placeholder="https://seu-servidor/LibreOfficePortable.zip">
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <button type="button" class="fj-pill fj-pri" id="btnInstalarLO"><i class="fa fa-download"></i> Baixar e instalar</button>
        <span class="hint" style="margin:0">Pode levar alguns minutos (pacote grande).</span>
      </div>
    </div>

    <div class="card-blk">
      <h5><i class="fa fa-eraser" style="color:var(--fj-primary)"></i> Espaço em disco</h5>
      <div class="hint">Os temporários de cada conversão são apagados automaticamente ao fim do processamento. Aqui você vê o que ainda está guardado (downloads recentes) e pode limpar na hora.</div>
      <div id="usoDisco" class="stat" style="margin-bottom:14px"><span class="d"></span><div><b>Calculando…</b></div></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="fj-pill fj-soft" id="btnLimparVencidos"><i class="fa fa-history"></i> Limpar vencidos</button>
        <button type="button" class="fj-pill fj-pri" id="btnLimparTudo"><i class="fa fa-trash"></i> Limpar tudo agora</button>
      </div>
    </div>

    <div class="card-blk">
      <h5><i class="fa fa-download" style="color:var(--fj-primary)"></i> Onde obter</h5>
      <div class="hint" style="margin:0">
        Ghostscript: <b>ghostscript.com/releases/gsdnld.html</b> (instale a versão 64-bit).<br>
        ImageMagick (opcional): <b>imagemagick.org</b>.<br>LibreOffice (Word ↔ PDF): <b>libreoffice.org/download</b>.<br>Depois de instalar, clique em <b>Testar ferramentas</b>.
      </div>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
(function(){
  function $(s){ return document.querySelector(s); }
  async function testar(){
    var box=$('#statusFerramentas');
    box.innerHTML='<div class="stat"><span class="d"></span><div><b>Verificando…</b></div></div>';
    try{
      var r=await fetch('testar_ferramentas.php',{credentials:'same-origin'}); var j=await r.json();
      if(j.status!=='success') throw new Error(j.message||'Falha.');
      function linha(nome,info,obs){
        var ok=info&&info.ok;
        return '<div class="stat '+(ok?'ok':'no')+'"><span class="d"></span><div><b>'+nome+': '+(ok?'encontrado':'não encontrado')+'</b>'
          +(ok&&info.path?'<small>'+info.path+(info.versao?(' · v'+info.versao):'')+'</small>':(obs?'<small>'+obs+'</small>':''))+'</div></div>';
      }
      box.innerHTML =
        linha('Ghostscript', j.ghostscript, 'necessário para comprimir e PDF→imagens')
       +linha('ImageMagick', j.imagemagick, 'alternativa opcional')
       +linha('LibreOffice', j.libreoffice, 'necessário para Word ↔ PDF')
       +'<div class="stat '+(j.zip?'ok':'no')+'"><span class="d"></span><div><b>Extensão ZIP do PHP: '+(j.zip?'ok':'ausente')+'</b>'+(j.zip?'':'<small>necessária para PDF→imagens</small>')+'</div></div>';
    }catch(e){ box.innerHTML='<div class="stat no"><span class="d"></span><div><b>Erro ao testar</b><small>'+e.message+'</small></div></div>'; }
  }
  $('#btnTestar').addEventListener('click',testar);

  $('#cfgForm').addEventListener('submit',async function(ev){
    ev.preventDefault();
    var btn=this.querySelector('button[type=submit]'); btn.disabled=true; var h=btn.innerHTML; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Salvando...';
    try{
      var fd=new FormData(this);
      if(!this.forja_ativo.checked) fd.set('forja_ativo','N');
      var r=await fetch('salvar_config.php',{method:'POST',body:fd,credentials:'same-origin'}); var j=await r.json();
      if(j.status!=='success') throw new Error(j.message||'Falha.');
      await Swal.fire({icon:'success',title:'Salvo!',text:j.message,timer:1400,showConfirmButton:false});
      testar();
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
    finally{ btn.disabled=false; btn.innerHTML=h; }
  });

  var btnLO=document.getElementById('btnInstalarLO');
  if(btnLO) btnLO.addEventListener('click',async function(){
    var url=document.getElementById('loUrl').value.trim();
    if(!/^https?:\/\//i.test(url)){ Swal.fire('Atenção','Informe uma URL http(s) para o .zip ou .msi.','warning'); return; }
    Swal.fire({title:'Baixando e instalando…',html:'Isso pode levar vários minutos. Não feche a página.',didOpen:function(){Swal.showLoading();},allowOutsideClick:false});
    try{
      var fd=new FormData(); fd.append('csrf',CSRF); fd.append('url',url);
      var r=await fetch('instalar_libreoffice.php',{method:'POST',body:fd,credentials:'same-origin'});
      var t=await r.text(); var j; try{ j=JSON.parse(t); }catch(e){ throw new Error('Resposta inválida: '+t.slice(0,160)); }
      if(j.status!=='success') throw new Error(j.message||'Falha.');
      await Swal.fire({icon:'success',title:'Instalado!',html:j.message+'<br><small>'+j.path+'</small>'});
      testar();
    }catch(e){ Swal.fire('Não foi possível instalar', e.message, 'error'); }
  });
  /* ---------- Espaço em disco ---------- */
  async function verUso(){
    var box=document.getElementById('usoDisco'); if(!box) return;
    try{
      var r=await fetch('limpar_tmp.php',{credentials:'same-origin'}); var j=await r.json();
      if(j.status!=='success') throw new Error(j.message||'Falha.');
      box.innerHTML='<span class="d"></span><div><b>'+j.total+'</b> em uso · temporários: '+j.tmp+' · gerados: '+j.saida+'<br><small>Retenção atual: '+j.retencao+' h</small></div>';
    }catch(e){ box.innerHTML='<span class="d"></span><div><b>Não foi possível calcular</b><br><small>'+e.message+'</small></div>'; }
  }
  async function limpar(modo){
    var conf = modo==='tudo'
      ? await Swal.fire({icon:'warning',title:'Limpar tudo?',text:'Downloads ainda não baixados serão perdidos.',showCancelButton:true,confirmButtonText:'Limpar',cancelButtonText:'Cancelar'})
      : {isConfirmed:true};
    if(!conf.isConfirmed) return;
    try{
      var fd=new FormData(); fd.append('csrf',CSRF); fd.append('modo',modo);
      var r=await fetch('limpar_tmp.php',{method:'POST',body:fd,credentials:'same-origin'});
      var t=await r.text(); var j; try{ j=JSON.parse(t); }catch(e){ throw new Error('Resposta inválida: '+t.slice(0,160)); }
      if(j.status!=='success') throw new Error(j.message||'Falha.');
      Swal.fire('Pronto', j.message, 'success');
      verUso();
    }catch(e){ Swal.fire('Erro', e.message, 'error'); }
  }
  var bV=document.getElementById('btnLimparVencidos'); if(bV) bV.addEventListener('click',function(){ limpar('retencao'); });
  var bT=document.getElementById('btnLimparTudo');     if(bT) bT.addEventListener('click',function(){ limpar('tudo'); });

  testar(); verUso();
})();

</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
