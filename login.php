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

/* ---------- Modais tonalizados (3 tipos) ---------- */
/* Danger (Pendência crítica) */
.modal-tone-danger .modal-content{
  background:#2a0f0f;
  color:#fde8e8;
  border:2px solid #dc2626;
  box-shadow:0 0 15px rgba(252,165,165,.6),0 0 30px rgba(220,38,38,.75);
  animation:pulseDanger 1.6s infinite;
}
.modal-tone-danger .modal-header{
  background:#dc2626;
  border-bottom:2px solid #b91c1c;
}
.modal-tone-danger .btn-close{filter:invert(1)}
@keyframes pulseDanger{
  0%   {box-shadow:0 0 0 0 rgba(220,38,38,.8);}
  70%  {box-shadow:0 0 0 20px rgba(220,38,38,0);}
  100% {box-shadow:0 0 0 0 rgba(220,38,38,0);}
}

/* Warning (Boleto/financeiro) */
.modal-tone-warning .modal-content{
  background:#2a230f;
  color:#fff7ed;
  border:2px solid #f59e0b;
  box-shadow:0 0 15px rgba(245,158,11,.45),0 0 30px rgba(245,158,11,.6);
}
.modal-tone-warning .modal-header{
  background:#f59e0b;
  border-bottom:2px solid #b45309;
}
.modal-tone-warning .btn-outline-light{
  color:#fff;background:transparent;border-color:#fff
}

/* Info (Mensagem importante) */
.modal-tone-info .modal-content{
  background:#0f1f2a;
  color:#dbeafe;
  border:2px solid #3b82f6;
  box-shadow:0 0 15px rgba(59,130,246,.45),0 0 30px rgba(59,130,246,.6);
}
.modal-tone-info .modal-header{
  background:#3b82f6;
  border-bottom:2px solid #1d4ed8;
}

/* Bloqueio card */
.block-alert .btn{white-space:normal}
</style>
</head>
<body>

<div class="card-login">

  <!-- logomarca -->
  <div class="brand text-center mb-4">
    <img src="<?= 'http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/atlas_logo_nova_login.png'?>" alt="Atlas">
  </div>

  <!-- mensagem git pull (opcional) -->
  <!-- <div class="alert alert-info text-center"><?= htmlspecialchars($mensagemAtualizacao) ?></div> -->

  <?php if ($isBlocked): ?>
      <div class="alert alert-danger text-center block-alert">
        <h5 class="mb-3 fw-semibold">Acesso temporariamente bloqueado</h5>

        <?php if ($boletoLink): ?>
          <p>
            <a href="<?= htmlspecialchars($boletoLink) ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
              <i class="bi bi-receipt"></i> Baixar boleto para regularização
            </a>
          </p>
        <?php endif; ?>

        <?php if ($allowEmergency && !$emergencyActive): ?>
          <button id="reqUnlock" class="btn btn-primary w-100">
            <i class="bi bi-unlock"></i> Solicitar desbloqueio emergencial
          </button>
          <small id="reqMsg" class="d-block mt-2"></small>
        <?php elseif ($emergencyActive): ?>
          <span class="d-block mt-3 text-success">
            Liberação emergencial ativa — faça o login normalmente.
          </span>
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
              <span class="input-group-text" id="togglePwd" title="Mostrar/Ocultar senha">
                  <i class="bi bi-eye" aria-hidden="true"></i>
              </span>
          </div>
        </div>

        <button class="btn btn-primary w-100">Entrar</button>
      </form>
  <?php endif; ?>
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

  /* ========= Persistência de credenciais (somente no navegador) ========== */
  const urlParams = new URLSearchParams(location.search);
  const erro      = urlParams.get('error');        // error=1 | 2 (login.php redirecionado)
  const usrInput  = document.getElementById('username');
  const pwdInput  = document.getElementById('password');

  if (erro && sessionStorage.getItem('atlasUsr')) {
      usrInput && (usrInput.value = sessionStorage.getItem('atlasUsr'));
      pwdInput && (pwdInput.value = sessionStorage.getItem('atlasPwd'));
  } else {
      sessionStorage.removeItem('atlasUsr');
      sessionStorage.removeItem('atlasPwd');
  }

  document.getElementById('loginForm')?.addEventListener('submit', () => {
      sessionStorage.setItem('atlasUsr', usrInput?.value || '');
      sessionStorage.setItem('atlasPwd', pwdInput?.value || '');
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
