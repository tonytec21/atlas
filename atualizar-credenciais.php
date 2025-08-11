<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo'); 

// Função para cadastrar ou atualizar funcionários nos dois bancos de dados
function saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo) {
    $senha_base64 = base64_encode($senha);

    // Conexão com o banco de dados "atlas"
    $connAtlas = new mysqli("localhost", "root", "", "atlas");
    if ($connAtlas->connect_error) {
        die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
    }
    $connAtlas->set_charset("utf8");

    // Conexão com o banco de dados "oficios_db"
    $connOficios = new mysqli("localhost", "root", "", "oficios_db");
    if ($connOficios->connect_error) {
        $connAtlas->close();
        die("Falha na conexão com o banco oficios_db: " . $connOficios->connect_error);
    }
    $connOficios->set_charset("utf8");

    // Verificar se é um novo cadastro ou atualização
    if ($id) {
        $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ? WHERE id = ?");
        $stmtAtlas->bind_param("ssssi", $usuario, $senha_base64, $nome_completo, $cargo, $id);

        $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ? WHERE id = ?");
        $stmtOficios->bind_param("ssssi", $usuario, $senha_base64, $nome_completo, $cargo, $id);
    } else {
        $stmtAtlas = $connAtlas->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo) VALUES (?, ?, ?, ?)");
        $stmtAtlas->bind_param("ssss", $usuario, $senha_base64, $nome_completo, $cargo);

        $stmtOficios = $connOficios->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo) VALUES (?, ?, ?, ?)");
        $stmtOficios->bind_param("ssss", $usuario, $senha_base64, $nome_completo, $cargo);
    }

    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['username'])) {
    die("Usuário não está logado.");
}
$usuarioLogado = $_SESSION['username'];

// POST: atualizar senha
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id            = isset($_POST['id']) ? $_POST['id'] : null;
    $usuario       = $_POST['usuario'];
    $senha         = $_POST['senha'];
    $confirm_senha = $_POST['confirm_senha'];
    $nome_completo = $_POST['nome_completo'];
    $cargo         = $_POST['cargo'];

    if ($senha !== $confirm_senha) {
        $errorMessage = "As senhas não coincidem. Tente novamente.";
    } else {
        saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo);
        $successMessage = "Credenciais " . ($id ? "atualizadas" : "cadastradas") . " com sucesso!";
    }
}

// Carregar dados do funcionário logado (atlas)
$connAtlas = new mysqli("localhost", "root", "", "atlas");
if ($connAtlas->connect_error) {
    die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
}
$connAtlas->set_charset("utf8");

$stmt = $connAtlas->prepare("SELECT * FROM funcionarios WHERE usuario = ?");
$stmt->bind_param("s", $usuarioLogado);
$stmt->execute();
$result = $stmt->get_result();
$funcionario = $result->fetch_assoc();
$stmt->close();
$connAtlas->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Atlas - Minhas Credenciais</title>

<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/font-awesome.min.css">
<link rel="stylesheet" href="style/css/style.css">
<link rel="icon" href="style/img/favicon.png" type="image/png">

<style>
/* =======================================================================
   TOKENS / THEME (iguais ao padrão que você enviou)
======================================================================= */
:root{
  --bg:#f6f7fb; --card:#ffffff; --muted:#6b7280; --text:#1f2937; --border:#e5e7eb;
  --shadow:0 10px 25px rgba(16,24,40,.06); --soft-shadow:0 6px 18px rgba(16,24,40,.08);
  --brand:#4F46E5; --brand-2:#6366F1; --success:#10b981;
}
body.light-mode{ background:var(--bg); color:var(--text); }
body.dark-mode{
  --bg:#0f141a; --card:#1a2129; --text:#e5e7eb; --muted:#9aa6b2; --border:#2a3440;
  --shadow:0 10px 25px rgba(0,0,0,.35); --soft-shadow:0 6px 18px rgba(0,0,0,.4);
  background:var(--bg); color:var(--text);
}
.muted{ color:var(--muted)!important; }

/* =======================================================================
   HERO
======================================================================= */
.page-hero{
  background:linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
  border-radius:18px; padding:18px 18px 10px; margin:20px 0 12px; box-shadow:var(--soft-shadow);
}
.page-hero .title-row{ display:flex; align-items:center; gap:12px; }
.title-icon{
  width:44px; height:44px; border-radius:12px; background:#EEF2FF; color:#3730A3;
  display:flex; align-items:center; justify-content:center; font-size:20px;
}
body.dark-mode .title-icon{ background:#262f3b; color:#c7d2fe; }
.page-hero h1{ font-weight:800; margin:0; }

/* =======================================================================
   CARD DO FORM (reusa o conceito de .filter-card)
======================================================================= */
.cred-card{
  background:var(--card); border:1px solid var(--border);
  border-radius:16px; padding:16px; box-shadow:var(--shadow);
}
.cred-card label{
  font-size:.78rem; text-transform:uppercase; letter-spacing:.04em;
  color:var(--muted); margin-bottom:6px; font-weight:700;
}
.cred-card .form-control{
  background:transparent; color:var(--text);
  border:1px solid var(--border); border-radius:10px;
}
.cred-card .form-control:focus{
  border-color:#a5b4fc; box-shadow:0 0 0 .2rem rgba(99,102,241,.15);
}

/* Botões */
.btn-primary{ background:#4F46E5; border-color:#4F46E5; }
.btn-primary:hover{ filter:brightness(.95); }
.btn-gradient{
  background:linear-gradient(135deg, var(--brand), var(--brand-2));
  color:#fff; border:none;
}
.btn-gradient:hover{ filter:brightness(.96); color:#fff; }

/* Input-group (olho de senha) */
.input-group .btn-eye{
  border:1px solid var(--border); border-left:0; background:transparent; color:var(--muted);
}
.input-group .btn-eye:hover{ color:var(--text); }

/* Alerts arredondados (caso use em outro ponto) */
.alert{ border-radius:12px; }

/* Responsividade */
@media (max-width: 575.98px){
  .input-group .form-control{ font-size:16px; }
}
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <!-- HERO / TÍTULO -->
    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fa fa-user-circle"></i></div>
        <div>
          <h1>Minhas Credenciais</h1>
          <div class="subtitle muted">Atualize sua senha com segurança. Usuário, nome e cargo são exibidos para conferência.</div>
        </div>
      </div>
    </section>

    <!-- CARD: FORMULÁRIO --------------------------------------------------- -->
    <div class="cred-card">
      <form method="post" action="" id="formAtualizarSenha" novalidate>
        <input type="hidden" name="id" id="funcionario-id" value="<?php echo isset($funcionario['id']) ? $funcionario['id'] : ''; ?>">

        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="usuario">Usuário</label>
            <input type="text" class="form-control" id="usuario" name="usuario"
              value="<?php echo isset($funcionario['usuario']) ? htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8') : ''; ?>"
              required readonly>
          </div>
          <div class="col-md-4 mb-3">
            <label for="nome_completo">Nome Completo</label>
            <input type="text" class="form-control" id="nome_completo" name="nome_completo"
              value="<?php echo isset($funcionario['nome_completo']) ? htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8') : ''; ?>"
              required readonly>
          </div>
          <div class="col-md-4 mb-3">
            <label for="cargo">Cargo</label>
            <input type="text" class="form-control" id="cargo" name="cargo"
              value="<?php echo isset($funcionario['cargo']) ? htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8') : ''; ?>"
              required readonly>
          </div>
        </div>

        <div class="row">
          <!-- Senha -->
          <div class="col-md-6 mb-3">
            <label for="senha">Senha</label>
            <div class="input-group">
              <input type="password" class="form-control" id="senha" name="senha" required minlength="4" aria-describedby="toggleSenha">
              <div class="input-group-append">
                <button class="btn btn-eye" type="button" id="toggleSenha" aria-label="Mostrar senha" aria-pressed="false">
                  <i class="fa fa-eye"></i>
                </button>
              </div>
              <div class="invalid-feedback">Informe uma senha (mín. 4 caracteres).</div>
            </div>
          </div>

          <!-- Confirmar Senha -->
          <div class="col-md-6 mb-3">
            <label for="confirm_senha">Confirmar Senha</label>
            <div class="input-group">
              <input type="password" class="form-control" id="confirm_senha" name="confirm_senha" required minlength="4" aria-describedby="toggleConfirm">
              <div class="input-group-append">
                <button class="btn btn-eye" type="button" id="toggleConfirm" aria-label="Mostrar confirmação" aria-pressed="false">
                  <i class="fa fa-eye"></i>
                </button>
              </div>
              <div class="invalid-feedback" id="confirmFeedback">As senhas devem coincidir.</div>
            </div>
          </div>
        </div>

        <div class="row mt-1">
          <div class="col-12">
            <button type="submit" class="btn btn-gradient btn-block py-2">
              <i class="fa fa-save"></i> Atualizar
            </button>
          </div>
        </div>
      </form>
    </div>

  </div>
</div>

<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script src="script/sweetalert2.js"></script>
<script>
  // Alternar senha/confirmar (mostrar/ocultar)
  function bindToggle(btnId, inputId){
    const $btn = $('#'+btnId), $inp = $('#'+inputId);
    $btn.on('click', function(){
      const isText = $inp.attr('type') === 'text';
      $inp.attr('type', isText ? 'password' : 'text');
      const $icon = $(this).find('i');
      $icon.toggleClass('fa-eye fa-eye-slash');
      $(this).attr('aria-pressed', (!isText).toString());
    });
  }

  function validateConfirm(){
    const s1 = $('#senha').val(), s2 = $('#confirm_senha').val();
    if (!s2) {
      $('#confirm_senha').removeClass('is-valid').addClass('is-invalid');
      $('#confirmFeedback').text('Confirme sua senha.');
      return false;
    }
    if (s1 !== s2) {
      $('#confirm_senha').removeClass('is-valid').addClass('is-invalid');
      $('#confirmFeedback').text('As senhas devem coincidir.');
      return false;
    }
    $('#confirm_senha').removeClass('is-invalid').addClass('is-valid');
    return true;
  }

  $(function(){
    bindToggle('toggleSenha','senha');
    bindToggle('toggleConfirm','confirm_senha');

    $('#senha, #confirm_senha').on('input', validateConfirm);

    // Validação do form (Bootstrap)
    $('#formAtualizarSenha').on('submit', function(e){
      const form = this;
      if (form.checkValidity() === false || !validateConfirm()){
        e.preventDefault(); e.stopPropagation();
      }
      $(form).addClass('was-validated');
    });
  });
</script>

<?php if (isset($successMessage) || isset($errorMessage)) : ?>
<script>
  $(function(){
    <?php if (isset($successMessage)) : ?>
      Swal.fire({
        icon: 'success',
        title: 'Sucesso',
        text: <?php echo json_encode($successMessage); ?>,
        timer: 3000,
        showConfirmButton: false
      });
    <?php else: ?>
      Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: <?php echo json_encode($errorMessage); ?>,
        showConfirmButton: true
      });
    <?php endif; ?>
  });
</script>
<?php endif; ?>

<br><br><br>
<?php include(__DIR__ . '/rodape.php'); ?>
</body>
</html>
