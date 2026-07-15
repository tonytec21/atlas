<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
iris_ensure_schema();
if (!iris_is_admin()) { header('Location: index.php'); exit; }

$CSRF = iris_csrf();
$cfg = iris_config();
$modelos = iris_modelos();
$temChave = iris_tem_chave();
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Iris · Configurar</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{ --ir-primary:#c026d3; --ir-primary2:#7e22ce; --ir-bg:#f1f5f9; --ir-text:#0f172a; --ir-muted:#64748b; --ir-card:#fff; --ir-border:#e5e9f0; }
body.dark-mode{ --ir-bg:#0f1216; --ir-text:#e5e7eb; --ir-muted:#9aa4b2; --ir-card:#1c2126; --ir-border:rgba(255,255,255,.08); }
#main .container{ max-width:860px; padding-bottom:120px; }
.ir-hero{ background:var(--ir-card); border:1px solid var(--ir-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.ir-title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.ir-ic{ width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg,var(--ir-primary),var(--ir-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.ir-hero h1{ font-size:1.4rem; font-weight:800; margin:0; color:var(--ir-text); } .ir-sub{ color:var(--ir-muted); font-size:.9rem; margin-top:2px; }
.ir-pill{ border-radius:999px; font-weight:600; padding:9px 16px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.16s; text-decoration:none; font-size:.9rem; }
.ir-pri{ background:linear-gradient(135deg,var(--ir-primary),var(--ir-primary2)); color:#fff; } .ir-pri:hover{ transform:translateY(-2px); color:#fff; }
.ir-soft{ background:var(--ir-bg); color:var(--ir-text); border:1px solid var(--ir-border); } .ir-soft:hover{ background:var(--ir-border); color:var(--ir-text); }
.card-blk{ background:var(--ir-card); border:1px solid var(--ir-border); border-radius:18px; padding:22px; box-shadow:0 8px 24px rgba(15,23,42,.05); margin-bottom:18px; }
.card-blk h5{ font-weight:800; font-size:1rem; color:var(--ir-text); margin:0 0 4px; display:flex; align-items:center; gap:9px; }
.card-blk .hint{ color:var(--ir-muted); font-size:.85rem; margin-bottom:16px; }
.field{ margin-bottom:14px; } .field label{ font-size:.82rem; font-weight:700; color:var(--ir-muted); margin-bottom:6px; display:block; }
.inp{ width:100%; border:1px solid var(--ir-border); border-radius:12px; padding:11px 14px; font-size:.94rem; color:var(--ir-text); background:var(--ir-card); outline:none; }
.inp:focus{ border-color:var(--ir-primary); box-shadow:0 0 0 3px rgba(192,38,211,.14); }
textarea.inp{ resize:vertical; min-height:80px; }
.row2{ display:grid; grid-template-columns:1fr 1fr; gap:14px; } @media(max-width:640px){ .row2{ grid-template-columns:1fr; } }
.mdl{ display:flex; align-items:center; gap:12px; padding:14px; border:1px solid var(--ir-border); border-radius:14px; margin-bottom:10px; flex-wrap:wrap; }
.mdl.padrao{ border-color:var(--ir-primary); background:rgba(192,38,211,.05); }
.mdl-ic{ width:40px;height:40px;border-radius:10px;background:rgba(192,38,211,.12);color:var(--ir-primary);display:flex;align-items:center;justify-content:center;flex:0 0 auto; }
.mdl-nome{ font-weight:700; color:var(--ir-text); } .mdl-id{ font-size:.8rem; color:var(--ir-muted); font-family:monospace; } .mdl-desc{ font-size:.82rem; color:var(--ir-muted); }
.badge-padrao{ font-size:.72rem; font-weight:700; padding:4px 10px; border-radius:999px; background:var(--ir-primary); color:#fff; }
.mdl-acts{ margin-left:auto; display:flex; gap:6px; }
.ib{ width:36px;height:36px;border:1px solid var(--ir-border);background:var(--ir-card);border-radius:9px;cursor:pointer;color:var(--ir-text);display:inline-flex;align-items:center;justify-content:center;transition:.14s; }
.ib:hover{ border-color:var(--ir-primary); color:var(--ir-primary); } .ib.rm:hover{ background:#fee2e2;color:#b91c1c;border-color:transparent; } .ib.star:hover{ background:rgba(192,38,211,.12); }
.addform{ display:grid; grid-template-columns:1.2fr 1fr auto; gap:10px; align-items:end; margin-top:8px; } @media(max-width:640px){ .addform{ grid-template-columns:1fr; } }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="ir-hero">
      <div class="ir-title-row">
        <div class="ir-ic"><i class="fa fa-cog"></i></div>
        <div style="min-width:0"><h1>Configurar · Atlas Iris</h1><div class="ir-sub">Chave da API do Gemini e modelos de extração.</div></div>
        <div style="margin-left:auto"><a class="ir-pill ir-soft" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a></div>
      </div>
    </section>

    <!-- API -->
    <div class="card-blk">
      <h5><i class="fa fa-key" style="color:var(--ir-primary)"></i> Chave da API do Gemini</h5>
      <div class="hint">Obtenha em <b>Google AI Studio</b> (aistudio.google.com/apikey). A chave é guardada criptografada.</div>
      <form id="apiForm">
        <input type="hidden" name="csrf" value="<?php echo eh($CSRF); ?>">
        <div class="field">
          <label>Chave da API</label>
          <input class="inp" type="password" name="api_key" id="apiKey" autocomplete="off"
                 placeholder="<?php echo $temChave ? '•••••••••••••••• (em branco para manter a atual)' : 'AIza...'; ?>">
        </div>
        <div class="field">
          <label>Instruções adicionais para a extração (opcional)</label>
          <textarea class="inp" name="prompt_extra" placeholder="Ex.: manter cabeçalhos e rodapés; ignorar carimbos; etc."><?php echo eh($cfg['prompt_extra'] ?? ''); ?></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="ir-pill ir-pri"><i class="fa fa-check"></i> Salvar</button>
        </div>
      </form>
    </div>

    <!-- Modelos -->
    <div class="card-blk">
      <h5><i class="fa fa-cubes" style="color:var(--ir-primary)"></i> Modelos de extração</h5>
      <div class="hint">Defina o modelo <b>padrão</b> (usado por default), cadastre novos ou remova. O identificador é o nome de API do Gemini.</div>

      <div id="listaModelos">
        <?php foreach ($modelos as $m): ?>
          <div class="mdl <?php echo $m['padrao']?'padrao':''; ?>" data-id="<?php echo (int)$m['id']; ?>">
            <div class="mdl-ic"><i class="fa fa-cube"></i></div>
            <div style="min-width:0">
              <div class="mdl-nome"><?php echo eh($m['rotulo']); ?> <?php echo $m['padrao']?'<span class="badge-padrao">PADRÃO</span>':''; ?></div>
              <div class="mdl-id"><?php echo eh($m['identificador']); ?></div>
              <?php if (!empty($m['descricao'])): ?><div class="mdl-desc"><?php echo eh($m['descricao']); ?></div><?php endif; ?>
            </div>
            <div class="mdl-acts">
              <?php if (!$m['padrao']): ?><button class="ib star js-padrao" title="Definir como padrão"><i class="fa fa-star-o"></i></button><?php endif; ?>
              <button class="ib js-editar" title="Editar" data-rotulo="<?php echo eh($m['rotulo']); ?>" data-desc="<?php echo eh($m['descricao'] ?? ''); ?>"><i class="fa fa-pencil"></i></button>
              <button class="ib rm js-excluir" title="Excluir"><i class="fa fa-trash-o"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="border-top:1px solid var(--ir-border);margin-top:14px;padding-top:14px">
        <label class="field" style="font-size:.82rem;font-weight:700;color:var(--ir-muted);margin-bottom:8px;display:block">Cadastrar novo modelo</label>
        <div class="addform">
          <div><input class="inp" id="novoId" placeholder="Identificador (ex.: gemini-3.1-pro)"></div>
          <div><input class="inp" id="novoRotulo" placeholder="Nome amigável (ex.: Gemini 3.1 Pro)"></div>
          <button class="ir-pill ir-pri" id="btnAddModelo" type="button" style="justify-content:center"><i class="fa fa-plus"></i> Adicionar</button>
        </div>
        <input class="inp" id="novoDesc" placeholder="Descrição (opcional)" style="margin-top:10px">
      </div>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
(function(){
  var CSRF=<?php echo json_encode($CSRF); ?>;
  function $(s){ return document.querySelector(s); }
  async function post(url,data){
    data.csrf=CSRF;
    var r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data).toString(),credentials:'same-origin'});
    var t=await r.text(); try{ return JSON.parse(t); }catch(e){ throw new Error('Resposta inválida: '+t.slice(0,160)); }
  }

  // salvar API/prompt
  $('#apiForm').addEventListener('submit',async function(ev){
    ev.preventDefault();
    var btn=this.querySelector('button[type=submit]'); btn.disabled=true; var h=btn.innerHTML; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Salvando...';
    try{
      var r=await fetch('salvar_config.php',{method:'POST',body:new FormData(this),credentials:'same-origin'}); var j=await r.json();
      if(j.status!=='success') throw new Error(j.message||'Falha.');
      await Swal.fire({icon:'success',title:'Salvo!',text:j.message,timer:1500,showConfirmButton:false});
      location.reload();
    }catch(e){ btn.disabled=false; btn.innerHTML=h; Swal.fire('Erro',e.message,'error'); }
  });

  // adicionar modelo
  $('#btnAddModelo').addEventListener('click',async function(){
    var id=$('#novoId').value.trim(), rot=$('#novoRotulo').value.trim(), desc=$('#novoDesc').value.trim();
    if(!id){ Swal.fire('Atenção','Informe o identificador do modelo.','warning'); return; }
    try{ var r=await post('modelo_salvar.php',{identificador:id,rotulo:rot,descricao:desc});
      if(r.status!=='success') throw new Error(r.message);
      location.reload();
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });

  // ações na lista (delegação)
  document.getElementById('listaModelos').addEventListener('click',async function(ev){
    var card=ev.target.closest('.mdl'); if(!card) return; var id=card.dataset.id;
    if(ev.target.closest('.js-padrao')){
      try{ var r=await post('modelo_padrao.php',{id:id}); if(r.status!=='success') throw new Error(r.message); location.reload(); }
      catch(e){ Swal.fire('Erro',e.message,'error'); }
    }
    else if(ev.target.closest('.js-excluir')){
      var q=await Swal.fire({icon:'warning',title:'Excluir modelo?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'});
      if(!q.isConfirmed) return;
      try{ var r=await post('modelo_excluir.php',{id:id}); if(r.status!=='success') throw new Error(r.message); location.reload(); }
      catch(e){ Swal.fire('Erro',e.message,'error'); }
    }
    else if(ev.target.closest('.js-editar')){
      var b=ev.target.closest('.js-editar');
      var res=await Swal.fire({
        title:'Editar modelo', html:
          '<input id="sw-rot" class="swal2-input" placeholder="Nome amigável" value="'+(b.dataset.rotulo||'').replace(/"/g,'&quot;')+'">'+
          '<input id="sw-desc" class="swal2-input" placeholder="Descrição" value="'+(b.dataset.desc||'').replace(/"/g,'&quot;')+'">',
        showCancelButton:true, confirmButtonText:'Salvar', cancelButtonText:'Cancelar',
        preConfirm:function(){ return { rotulo:document.getElementById('sw-rot').value, descricao:document.getElementById('sw-desc').value }; }
      });
      if(!res.isConfirmed) return;
      try{ var r=await post('modelo_salvar.php',{id:id,rotulo:res.value.rotulo,descricao:res.value.descricao}); if(r.status!=='success') throw new Error(r.message); location.reload(); }
      catch(e){ Swal.fire('Erro',e.message,'error'); }
    }
  });
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
