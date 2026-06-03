<?php
/**
 * Segunda etapa do login (2FA / TOTP).
 * Só é alcançada quando o usuário tem a verificação em duas etapas ativada:
 * o check_login.php valida usuário/senha e redireciona para cá com um login pendente.
 */
session_start();
require_once __DIR__ . '/lib/totp.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Sem login pendente -> volta ao login
if (empty($_SESSION['tfa_pending']) || empty($_SESSION['tfa_pending']['username'])) {
    header('Location: login.php');
    exit;
}

$pending = $_SESSION['tfa_pending'];

// Expira a etapa pendente após 5 minutos
if (!isset($pending['ts']) || (time() - (int) $pending['ts']) > 300) {
    unset($_SESSION['tfa_pending']);
    header('Location: login.php?error=3'); // sessão da 2ª etapa expirada
    exit;
}

function tfaGetUserIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP']))       $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else                                          $ip = $_SERVER['REMOTE_ADDR'];
    return $ip === '::1' ? '127.0.0.1' : $ip;
}

function tfaSaveAccessLog($username, $nomeCompleto) {
    date_default_timezone_set('America/Sao_Paulo');
    $conn = new mysqli('localhost', 'root', '', 'atlas');
    if ($conn->connect_error) return;
    $ip = tfaGetUserIp();
    $dataHora = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('INSERT INTO logs_de_acesso (usuario, nome_completo, ip, data_hora) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('ssss', $username, $nomeCompleto, $ip, $dataHora);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancelar'])) {
        unset($_SESSION['tfa_pending']);
        header('Location: login.php');
        exit;
    }
    $code = $_POST['code'] ?? '';
    if (TOTP::verify($pending['secret'], $code, 1)) {
        // Sucesso: conclui o login (mesma sessão definida pelo check_login.php)
        $_SESSION['username']        = $pending['username'];
        $_SESSION['nome_completo']   = $pending['nome_completo'];
        $_SESSION['cargo']           = $pending['cargo'];
        $_SESSION['nivel_de_acesso'] = $pending['nivel_de_acesso'];
        $_SESSION['status']          = $pending['status'];

        tfaSaveAccessLog($pending['username'], $pending['nome_completo']);
        unset($_SESSION['tfa_pending']);

        header('Location: index.php');
        exit;
    }
    $erro = 'Código inválido. Verifique o aplicativo autenticador e tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Atlas - Verificação em duas etapas</title>
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/font-awesome.min.css">
<link rel="icon" href="style/img/favicon.png" type="image/png">
<style>
  body{ background:#0f141a; color:#e5e7eb; min-height:100vh; display:flex; align-items:center; justify-content:center; }
  .tfa-card{
    background:#1a2129; border:1px solid #2a3440; border-radius:16px;
    padding:28px 24px; width:100%; max-width:380px; box-shadow:0 10px 25px rgba(0,0,0,.35);
  }
  .tfa-card .icon{
    width:56px; height:56px; border-radius:14px; background:#262f3b; color:#c7d2fe;
    display:flex; align-items:center; justify-content:center; font-size:26px; margin:0 auto 14px;
  }
  .tfa-card h1{ font-size:1.25rem; font-weight:800; text-align:center; margin-bottom:4px; }
  .tfa-card .sub{ color:#9aa6b2; text-align:center; font-size:.9rem; margin-bottom:18px; }
  .tfa-card .form-control{
    background:transparent; color:#e5e7eb; border:1px solid #2a3440; border-radius:10px;
    text-align:center; letter-spacing:.5em; font-size:1.5rem; font-weight:700;
  }
  .tfa-card .form-control:focus{ border-color:#6366F1; box-shadow:0 0 0 .2rem rgba(99,102,241,.2); color:#fff; }
  .btn-grad{ background:linear-gradient(135deg,#4F46E5,#6366F1); color:#fff; border:none; }
  .btn-grad:hover{ filter:brightness(.96); color:#fff; }
  .link-cancel{ color:#9aa6b2; background:none; border:none; }
  .link-cancel:hover{ color:#e5e7eb; text-decoration:underline; }
  .alert{ border-radius:12px; }
</style>
</head>
<body>
  <div class="tfa-card">
    <div class="icon"><i class="fa fa-shield"></i></div>
    <h1>Verificação em duas etapas</h1>
    <div class="sub">Digite o código de 6 dígitos do seu aplicativo autenticador (Google Authenticator ou Microsoft Authenticator).</div>

    <?php if ($erro): ?>
      <div class="alert alert-danger py-2"><i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post" action="" autocomplete="off">
      <div class="form-group mb-3">
        <input type="text" class="form-control" name="code" id="code" inputmode="numeric"
               pattern="[0-9]*" maxlength="6" placeholder="••••••" required autofocus>
      </div>
      <button type="submit" class="btn btn-grad btn-block py-2"><i class="fa fa-check"></i> Verificar</button>
      <div class="text-center mt-3">
        <button type="submit" name="cancelar" value="1" class="link-cancel">Cancelar e voltar ao login</button>
      </div>
    </form>
  </div>

  <script src="script/jquery-3.5.1.min.js"></script>
  <script>
    // Mantém apenas dígitos e envia automaticamente ao completar 6
    $('#code').on('input', function(){
      this.value = this.value.replace(/\D/g,'').slice(0,6);
      if (this.value.length === 6) { $(this).closest('form').submit(); }
    });
  </script>
</body>
</html>
