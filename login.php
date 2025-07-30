<?php
/* 1. git pull automático -------------------------------- */
shell_exec('git config --global --add safe.directory C:/xampp/htdocs/atlas');
$out = shell_exec('git pull 2>&1');
$mensagemAtualizacao =
    str_contains($out,'Already up to date.') ? 'Sistema atualizado. Nenhuma atualização pendente.' :
    (str_contains($out,'Updating')           ? 'Atualização do código aplicada com sucesso.'       :
                                               'Erro ao executar a atualização via git: '.$out );

/* 2. CNS local ----------------------------------------- */
require_once __DIR__.'/db_connection.php';     // $conn (mysqli)
$cns = '';
$res = $conn->query("SELECT cns FROM cadastro_serventia LIMIT 1");
if ($res) $cns = $res->fetch_assoc()['cns'] ?? '';

/* 3. Chama a API --------------------------------------- */
$apiUrl   = 'https://api.sistemaatlas.com.br/api.php';
$apiToken = 'CHAVE-SECRETA-GERADA';

$statusResp = ['status'=>'ativo','allow_emergency'=>false,'emergency_active'=>false];
if ($cns) {
    $json = @file_get_contents($apiUrl.'?action=status&cns='.urlencode($cns).'&token='.$apiToken);
    if ($json) $statusResp = json_decode($json, true);
}

/* flags para uso no HTML */
$isBlocked        = $statusResp['status'] === 'bloqueado';
$allowEmergency   = $statusResp['allow_emergency'] ?? false;
$emergencyActive  = $statusResp['emergency_active'] ?? false;
$modalMensagem    = $statusResp['mensagem'] ?? '';
$modalBoletoLink  = $statusResp['boleto_link'] ?? '';

/* valores pendentes (caso o login tenha falhado anteriormente)  */
$prevUser = $_GET['u'] ?? '';
$prevPass = $_GET['p'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ATLAS • Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1/font/bootstrap-icons.css"  rel="stylesheet">
<link rel="icon" href="style/img/favicon.png">

<style>
/* =========== Tema =========== */
:root{
  --bg:            #ffffff;
  --panel:         #f8fafc;
  --border:        #cbd5e1;
  --text:          #1e293b;
  --accent:        #4f46e5;
  --accent-hover:  #6366f1;
}
:root[data-theme="dark"]{
  --bg:            #0f172a;
  --panel:         #1e293b;
  --border:        #334155;
  --text:          #e2e8f0;
  --accent:        #6366f1;
  --accent-hover:  #818cf8;
}
html,body{height:100%}
body{
  display:flex;
  align-items:center;
  justify-content:center;
  background:var(--bg);
  color:var(--text);
  font-family:'Poppins',system-ui,Arial,Helvetica,sans-serif;
}

/* ---------- Card ---------- */
.card-login{
  width:100%;
  max-width:390px;
  border:1px solid var(--border);
  background:var(--panel);
  border-radius:1rem;
  padding:2.25rem 2rem 2rem;
  box-shadow:0 6px 24px rgba(0,0,0,.15);
}
.brand img{max-width:170px}
.alert{font-size:.95rem;padding:.8rem 1rem;border-radius:.65rem}

/* ---------- Buttons ---------- */
.btn-primary{
  background:var(--accent);
  border:none;
}
.btn-primary:hover{background:var(--accent-hover)}
.input-group-text{
  background:transparent;
  border-left:none;
  cursor:pointer;
  color:var(--text);
}
.form-control{
  background:transparent;
  color:var(--text);
}
.form-control:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 .15rem rgba(99,102,241,.25);
}

/* ---------- Modal de Pendência (ALARMANTE) ---------- */
#licenseModal .modal-content{
  background:#2a0f0f;
  color:#fde8e8;
  border:2px solid #dc2626;
  box-shadow:0 0 15px rgba(252,165,165,.6),0 0 30px rgba(220,38,38,.75);
  animation:pulseDanger 1.6s infinite;
}
#licenseModal .modal-header{
  background:#dc2626;
  border-bottom:2px solid #b91c1c;
}
#licenseModal .modal-header .modal-title{
  display:flex;
  align-items:center;
  gap:.5rem;
  font-weight:600;
  font-size:1.25rem;
}
#licenseModal .btn-close{filter:invert(1)}
#licenseModal .modal-footer{border-top:none}
@keyframes pulseDanger{
  0%   {box-shadow:0 0 0 0 rgba(220,38,38,.8);}
  70%  {box-shadow:0 0 0 20px rgba(220,38,38,0);}
  100% {box-shadow:0 0 0 0 rgba(220,38,38,0);}
}
</style>
</head>
<body>

<div class="card-login">

  <!-- logomarca -->
  <div class="brand text-center mb-4">
    <img src="<?= 'http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/atlas_logo_nova_login.png'?>" alt="Atlas">
  </div>

  <!-- mensagem git pull (opcional) -->
  <!-- <div class="alert alert-info text-center"><?= $mensagemAtualizacao ?></div> -->

  <?php if ($isBlocked): ?>
      <div class="alert alert-danger text-center">
        <h5 class="mb-3 fw-semibold">Acesso temporariamente bloqueado</h5>

        <?php if ($modalBoletoLink): ?>
        <p><a href="<?= $modalBoletoLink ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
             Baixar boleto para regularização
        </a></p>
        <?php endif; ?>

        <?php if ($allowEmergency && !$emergencyActive): ?>
          <button id="reqUnlock" class="btn btn-primary w-100">Solicitar desbloqueio emergencial</button>
          <small id="reqMsg" class="d-block mt-2"></small>
        <?php elseif ($emergencyActive): ?>
          <span class="d-block mt-3 text-success">Liberação emergencial ativa — faça o login normalmente.</span>
        <?php endif; ?>
      </div>

  <?php else: ?>
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger text-center mb-4">
          <?= $_GET['error']==1 ? 'Usuário ou senha incorretos. Tente novamente.'
                                : 'Usuário inativo. Contate o administrador.' ?>
        </div>
      <?php endif; ?>

      <!-- ---------- Form ---------- -->
      <form id="loginForm" action="check_login.php" method="POST" autocomplete="off">
        <div class="mb-3">
          <label for="username" class="form-label">Usuário</label>
          <input type="text"
                 id="username"
                 name="username"
                 value="<?= htmlspecialchars($prevUser) ?>"
                 class="form-control"
                 required autofocus>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label">Senha</label>
          <div class="input-group">
              <input type="password"
                     id="password"
                     name="password"
                     value="<?= htmlspecialchars($prevPass) ?>"
                     class="form-control"
                     required>
              <span class="input-group-text" id="togglePwd">
                  <i class="bi bi-eye" aria-hidden="true"></i>
              </span>
          </div>
        </div>

        <button class="btn btn-primary w-100">Entrar</button>
      </form>
  <?php endif; ?>
</div>


<!-- Modal de inadimplência -->
<?php if (!$isBlocked && $modalMensagem): ?>
<div class="modal fade" id="licenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Aviso de Pendência</h5>
        <!-- <button type="button" class="btn-close" data-bs-dismiss="modal"></button> -->
      </div>
      <div class="modal-body">
        <p class="mb-0"><?= $modalMensagem ?></p>
        <?php if ($modalBoletoLink): ?>
          <hr>
          <a href="<?= $modalBoletoLink ?>" target="_blank" class="btn btn-outline-light w-100 fw-semibold">
              Baixar boleto para regularização
          </a>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button id="okBtn" type="button" class="btn btn-light w-100 text-danger fw-semibold">Ok</button>
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

  /* ========= Persistência de credenciais (somente no navegador) ========== */
  const urlParams = new URLSearchParams(location.search);
  const erro      = urlParams.get('error');        // error=1 | 2 (login.php redirecionado)
  const usrInput  = document.getElementById('username');
  const pwdInput  = document.getElementById('password');

  if (erro && sessionStorage.getItem('atlasUsr')) {
      usrInput.value = sessionStorage.getItem('atlasUsr');
      pwdInput.value = sessionStorage.getItem('atlasPwd');
  } else {
      sessionStorage.removeItem('atlasUsr');
      sessionStorage.removeItem('atlasPwd');
  }

  document.getElementById('loginForm')?.addEventListener('submit', () => {
      sessionStorage.setItem('atlasUsr', usrInput.value);
      sessionStorage.setItem('atlasPwd', pwdInput.value);
  });

  /* ========== Show / Hide Password ========== */
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

  /* ========== Modal pendência ========== */
  const modalEl = document.getElementById('licenseModal');
  if (modalEl) {
    const okBtn = document.getElementById('okBtn');
    const inst  = new bootstrap.Modal(modalEl,{backdrop:'static'});
    inst.show();
    okBtn.addEventListener('click', () => inst.hide());
    modalEl.addEventListener('hidden.bs.modal', () => {
      inst.dispose();
      document.querySelectorAll('.modal-backdrop').forEach(el=>el.remove());
      document.body.classList.remove('modal-open');
      document.body.style='';
    });
  }

  /* ======== Solicitar liberação emergencial ======== */
  const btn = document.getElementById('reqUnlock');
  if (btn) {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      const msgSpan = document.getElementById('reqMsg');
      msgSpan.textContent = 'Enviando...';

      try {
        const fd = new FormData();
        fd.append('action','request_emergency');
        fd.append('cns','<?= $cns ?>');

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
        Swal.fire({
          icon:'error',
          title:'Erro',
          text:'Falha de rede. Tente novamente.',
        });
        btn.disabled=false;
        msgSpan.textContent='';
      }
    });
  }
});
</script>
</body>
</html>
