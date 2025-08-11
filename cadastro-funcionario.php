<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/checar_acesso_cadastro.php');
date_default_timezone_set('America/Sao_Paulo');

$notificationMessage = null;
$notificationType = null;

/* ============================================================
   0) IMPORTAÇÃO VIA XLSX (AJAX)
   ------------------------------------------------------------ */
if (isset($_POST['import_xlsx']) && isset($_FILES['xlsx_file'])) {
    // LIMPA QUALQUER BUFFER PARA NÃO QUEBRAR O JSON
    if (function_exists('ob_get_level')) {
        while (ob_get_level()) { @ob_end_clean(); }
    }

    // DESATIVA AVISOS/NOTICES APENAS NESTE ENDPOINT
    $prevDisplay = ini_get('display_errors');
    $prevReporting = error_reporting();
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    @ini_set('error_log', __DIR__ . '/logs/import_xlsx.log'); // opcional
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

    header('Content-Type: application/json; charset=utf-8');

    $result = [
        'ok' => false,
        'message' => '',
        'total' => 0,
        'importados' => 0,
        'falhas' => 0,
        'logs' => []
    ];

    // Verificar autoload do PhpSpreadsheet
    $autoloadPath = __DIR__ . '/indexador/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        $result['message'] = 'Bibliotecas não encontradas em "indexador/vendor/autoload.php".';
        echo json_encode($result);
        // RESTAURA NÍVEL DE ERRO
        error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay);
        exit;
    }

    require_once $autoloadPath;

    try {
        $file = $_FILES['xlsx_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['message'] = 'Falha no upload do arquivo (código ' . $file['error'] . ').';
            echo json_encode($result);
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay);
            exit;
        }

        // Validação simples por extensão e MIME
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $validMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
            'application/zip'
        ];
        if ($ext !== 'xlsx' || (!in_array($file['type'], $validMimes, true))) {
            $result['message'] = 'Envie um arquivo .xlsx válido.';
            echo json_encode($result);
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay);
            exit;
        }

        // Leitura via PhpSpreadsheet: só dados (ignora estilos, evitando warnings no Styles.php)
        \PhpOffice\PhpSpreadsheet\Settings::setLibXmlLoaderOptions(LIBXML_NONET);
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        // Validar cabeçalho: USUARIO;SENHA;NOME;CARGO (nessa ordem)
        $h1 = strtoupper(trim((string)$sheet->getCell('A1')->getValue()));
        $h2 = strtoupper(trim((string)$sheet->getCell('B1')->getValue()));
        $h3 = strtoupper(trim((string)$sheet->getCell('C1')->getValue()));
        $h4 = strtoupper(trim((string)$sheet->getCell('D1')->getValue()));

        if (!($h1 === 'USUARIO' && $h2 === 'SENHA' && $h3 === 'NOME' && $h4 === 'CARGO')) {
            $result['message'] = 'Cabeçalho inválido. Esperado: USUARIO;SENHA;NOME;CARGO (nessa ordem).';
            echo json_encode($result);
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay);
            exit;
        }

        // Importar linhas (da 2 até a última com dados)
        $total = 0; $okCount = 0; $failCount = 0; $logs = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $usuario = trim((string)$sheet->getCell('A' . $row)->getValue());
            $senha   = trim((string)$sheet->getCell('B' . $row)->getValue());
            $nome    = trim((string)$sheet->getCell('C' . $row)->getValue());
            $cargo   = trim((string)$sheet->getCell('D' . $row)->getValue());

            // Linha completamente vazia → pula
            if ($usuario === '' && $senha === '' && $nome === '' && $cargo === '') { continue; }

            $total++;

            // Validações mínimas
            if (!preg_match('/^[a-zA-Z0-9]+$/', $usuario)) {
                $failCount++;
                $logs[] = ['linha'=>$row,'usuario'=>$usuario,'status'=>'erro','mensagem'=>'Usuário inválido (apenas letras e números).'];
                continue;
            }
            if ($senha === '' || $nome === '' || $cargo === '') {
                $failCount++;
                $logs[] = ['linha'=>$row,'usuario'=>$usuario,'status'=>'erro','mensagem'=>'Campos obrigatórios ausentes (SENHA/NOME/CARGO).'];
                continue;
            }

            // Usa a função existente para salvar (nível padrão: usuario)
            $err = saveFuncionario(null, $usuario, $senha, $nome, $cargo, 'usuario', null, null);

            if ($err) {
                $failCount++;
                $logs[] = ['linha'=>$row,'usuario'=>$usuario,'status'=>'erro','mensagem'=>$err];
            } else {
                $okCount++;
                $logs[] = ['linha'=>$row,'usuario'=>$usuario,'status'=>'ok','mensagem'=>'Importado com sucesso.'];
            }
        }

        $result['ok'] = true;
        $result['message'] = 'Importação concluída.';
        $result['total'] = $total;
        $result['importados'] = $okCount;
        $result['falhas'] = $failCount;
        $result['logs'] = $logs;

        echo json_encode($result);

    } catch (\Throwable $e) {
        echo json_encode([
            'ok' => false,
            'message' => 'Erro ao processar o XLSX: ' . $e->getMessage(),
            'total' => 0,
            'importados' => 0,
            'falhas' => 0,
            'logs' => []
        ]);
    }

    // RESTAURA CONFIGURAÇÃO DE ERROS
    error_reporting($prevReporting);
    @ini_set('display_errors', $prevDisplay);
    exit;
}

/* ============================================================
   1) CADASTRO/EDIÇÃO NORMAL (FORM)
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['import_xlsx'])) {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $usuario = $_POST['usuario'];
    $senha = isset($_POST['senha']) ? $_POST['senha'] : null;
    $nome_completo = $_POST['nome_completo'];
    $cargo = $_POST['cargo'];
    $nivel_de_acesso = $_POST['nivel_de_acesso'];
    $e_mail = !empty($_POST['e_mail']) ? $_POST['e_mail'] : null;
    $acesso_adicional = !empty($_POST['acesso_adicional']) ? implode(',', $_POST['acesso_adicional']) : null;

    // Validação para permitir apenas letras e números no campo "Usuário"
    if (!preg_match('/^[a-zA-Z0-9]+$/', $usuario)) {
        $notificationMessage = "O campo Usuário deve conter apenas letras e números, sem espaços ou caracteres especiais.";
        $notificationType = 'danger';
    } else {
        $errorMessage = saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail);
        if ($errorMessage) {
            $notificationMessage = $errorMessage;
            $notificationType = 'danger';
        } else {
            $notificationMessage = "Funcionário " . ($id ? "atualizado" : "cadastrado") . " com sucesso!";
            $notificationType = 'success';
        }
    }
}

/* ============================================================
   2) FUNÇÃO saveFuncionario (usada pelo form e pelo import)
   ------------------------------------------------------------ */
function saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail) {
    // Verificar se uma nova senha foi fornecida
    $senha_base64 = !empty($senha) ? base64_encode($senha) : null;

    // Conexão com o banco de dados "atlas"
    $connAtlas = new mysqli("localhost", "root", "", "atlas");
    if ($connAtlas->connect_error) {
        return "Falha na conexão com o banco atlas: " . $connAtlas->connect_error;
    }
    $connAtlas->set_charset("utf8");

    // Conexão com o banco de dados "oficios_db"
    $connOficios = new mysqli("localhost", "root", "", "oficios_db");
    if ($connOficios->connect_error) {
        $connAtlas->close();
        return "Falha na conexão com o banco oficios_db: " . $connOficios->connect_error;
    }
    $connOficios->set_charset("utf8");

    // Verificar se já existe um usuário com o mesmo nome de login
    $checkStmtAtlas = $connAtlas->prepare("SELECT id FROM funcionarios WHERE usuario = ? AND id != ?");
    $safeId = $id ? (int)$id : 0;
    $checkStmtAtlas->bind_param("si", $usuario, $safeId);
    $checkStmtAtlas->execute();
    $checkStmtAtlas->store_result();

    if ($checkStmtAtlas->num_rows > 0) {
        $checkStmtAtlas->close();
        $connAtlas->close();
        $connOficios->close();
        return "Já existe um cadastro com esse nome de usuário!";
    }
    $checkStmtAtlas->close();

    // Verificar se é um novo cadastro ou atualização
    if ($id) {
        // Atualização: verificar se a senha foi fornecida
        if ($senha_base64) {
            $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtAtlas->bind_param("sssssssi", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);

            $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtOficios->bind_param("sssssssi", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);
        } else {
            // Se não houver nova senha, não altere a senha
            $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtAtlas->bind_param("ssssssi", $usuario, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);

            $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtOficios->bind_param("ssssssi", $usuario, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);
        }
    } else {
        // Inserção de um novo funcionário
        $stmtAtlas = $connAtlas->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo, nivel_de_acesso, acesso_adicional, e_mail, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo')");
        $stmtAtlas->bind_param("sssssss", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail);

        $stmtOficios = $connOficios->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo, nivel_de_acesso, acesso_adicional, e_mail, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo')");
        $stmtOficios->bind_param("sssssss", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail);
    }

    $ok1 = $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    $ok2 = $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();

    if (!$ok1 || !$ok2) {
        return "Erro ao salvar o funcionário em um dos bancos.";
    }

    return null;
}

/* ============================================================
   3) EXCLUSÃO (status = removido)
   ------------------------------------------------------------ */
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

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

    // Atualizar o status do funcionário para "removido" no banco "atlas"
    $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET status = 'removido' WHERE id = ?");
    $stmtAtlas->bind_param("i", $id);
    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    // Atualizar o status do funcionário para "removido" no banco "oficios_db"
    $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET status = 'removido' WHERE id = ?");
    $stmtOficios->bind_param("i", $id);
    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();

    $notificationMessage = "Funcionário excluído com sucesso!";
    $notificationType = 'success';
}

/* ============================================================
   4) LISTAGEM
   ------------------------------------------------------------ */
// Conexão com o banco de dados "atlas"
$connAtlas = new mysqli("localhost", "root", "", "atlas");
if ($connAtlas->connect_error) {
    die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
}
$connAtlas->set_charset("utf8");

// Buscar todos os funcionários ativos no banco "atlas"
$result = $connAtlas->query("SELECT * FROM funcionarios WHERE status = 'ativo'");
$funcionarios = $result->fetch_all(MYSQLI_ASSOC);
$connAtlas->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Cadastro de Funcionários</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">

    <!-- MDI via CDN (corrige ícones e 404 de fontes locais) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

    <link rel="stylesheet" href="style/css/dataTables.bootstrap4.min.css">
    <link href="style/css/select2.min.css" rel="stylesheet" />
    <style>
        /* =======================================================================
           TOKENS / THEME (alinhado ao layout moderno)
        ======================================================================= */
        :root{
            --brand:#4F46E5;
            --brand-2:#6366F1;
            --brand-start:#0ea5e9;
            --brand-end:#7c3aed;
            --bg:#f6f7fb;
            --card:#ffffff;
            --muted:#6b7280;
            --text:#1f2937;
            --border:#e5e7eb;
            --shadow:0 10px 25px rgba(16,24,40,.06);
            --soft-shadow:0 6px 18px rgba(16,24,40,.08);
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
        .page-hero .title-row{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .title-icon{
            width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;
            display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .title-icon{ background:#262f3b;color:#c7d2fe; }
        .page-hero h1{ font-weight:800; margin:0; }

        /* =======================================================================
           CARDS
        ======================================================================= */
        .form-card, .list-card{
            background:var(--card); border:1px solid var(--border);
            border-radius:16px; padding:16px; box-shadow:var(--shadow);
        }
        .form-card label{
            font-size:.78rem; text-transform:uppercase; letter-spacing:.04em;
            color:var(--muted); margin-bottom:6px; font-weight:700;
        }
        .form-card .form-control, .form-card select{
            background:transparent; color:var(--text);
            border:1px solid var(--border); border-radius:10px;
        }
        .form-card .form-control:focus, .form-card select:focus{
            border-color:#a5b4fc; box-shadow:0 0 0 .2rem rgba(99,102,241,.15);
        }

        .toolbar-actions{ display:flex; gap:.5rem; flex-wrap:wrap; }
        .btn-gradient{
            background:linear-gradient(135deg, var(--brand), var(--brand-2));
            color:#fff; border:none;
        }
        .btn-gradient:hover{ filter:brightness(.96); color:#fff; }
        .btn-outline-secondary{ border-radius:10px; }
        .btn-warning, .btn-danger{ border:none; }

        /* Input-group (olho de senha) */
        .input-group .btn-eye{
            border:1px solid var(--border); border-left:0; background:transparent; color:var(--muted);
        }
        .input-group .btn-eye:hover{ color:var(--text); }

        /* Select2 – aparência integrada */
        .select2-container--default .select2-selection--multiple{
            display:block; width:100%; min-height: calc(2.25rem + 2px);
            padding:.2rem .5rem; font-size:1rem; line-height:1.5; color:var(--text);
            background-color:transparent; border:1px solid var(--border); border-radius:10px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice{
            background-color:#e0e7ff; color:#3730A3; border:1px solid #c7d2fe;
            border-radius:999px; padding:.2rem .6rem; margin:.2rem;
            font-weight:700; font-size:.78rem;
        }
        body.dark-mode .select2-container--default .select2-selection--multiple{
            border-color:var(--border); color:var(--text); background:transparent;
        }

        /* DataTable */
        table.dataTable thead th{ white-space:nowrap; }

        /* ===================== MODAL DE IMPORTAÇÃO ===================== */
        .modal-modern .modal-content{
            background: linear-gradient(180deg, var(--card) 0%, var(--card) 100%);
            border:1px solid var(--border); border-radius:16px; overflow:hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,.18);
        }
        .modal-modern .modal-header{
            border-bottom:1px solid var(--border);
            background: linear-gradient(90deg, var(--brand-start), var(--brand-end));
            color:#fff;
        }
        .modal-modern .modal-title{ font-weight:700; letter-spacing:.3px; }
        .modal-modern .modal-body{ background:var(--card); color:var(--text); }
        .modal-modern .modal-footer{ border-top:1px solid var(--border); background:var(--card); }

        .dropzone{
            border:2px dashed var(--border); border-radius:16px; padding:28px; text-align:center;
            transition: all .2s ease; background: rgba(0,0,0,0.02);
        }
        body.dark-mode .dropzone{ background: rgba(255,255,255,0.03); }
        .dropzone.dragover{ border-color: var(--brand-start); background: rgba(14,165,233,0.06); }
        .dropzone .icon{ font-size:48px; margin-bottom:8px; opacity:.85; }
        .file-info{ font-size:.95rem; color:var(--muted); }
        .progress{ height:10px; border-radius:999px; overflow:hidden; }
        .log-table{ width:100%; }
        .badge-soft{ display:inline-block; padding:.25rem .5rem; border-radius:999px; font-size:.75rem; }
        .badge-soft-success{ background:rgba(16,185,129,.15); color:#10b981; }
        .badge-soft-danger{ background:rgba(239,68,68,.15); color:#ef4444; }

        /* Notificação antiga (mantida, mas usamos SweetAlert2) */
        .notification{
            position:fixed; bottom:20px; right:20px; background-color:#343a40; color:#fff;
            padding:15px; border-radius:5px; box-shadow:0 2px 10px rgba(0,0,0,.1); z-index:1000; display:none;
        }
        .notification .close-btn{ cursor:pointer; float:right; margin-left:10px; }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">

        <!-- HERO / TÍTULO -->
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="mdi mdi-account-group"></i></div>
                <div>
                    <h1>Cadastro de Funcionários</h1>
                    <div class="subtitle muted">Gerencie usuários, níveis de acesso e permissões. Importe em massa via planilha XLSX.</div>
                </div>
                <div class="ml-auto toolbar-actions">
                    <button id="novo-funcionario" type="button" class="btn btn-outline-secondary">
                        <i class="mdi mdi-account-plus-outline"></i> Novo Funcionário
                    </button>
                    <button id="btnImportarXlsx" type="button" class="btn btn-gradient" data-toggle="modal" data-target="#modalImportXlsx">
                        <i class="mdi mdi-file-excel"></i> Importar XLSX
                    </button>
                </div>
            </div>
        </section>

        <!-- FORM CARD -->
        <div class="form-card mb-3">
            <form method="post" action="" id="formCadastro">
                <input type="hidden" name="id" id="funcionario-id">

                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="usuario">Usuário</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" required>
                    </div>

                    <div class="form-group col-md-4">
                        <label for="senha">Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="senha" name="senha" aria-describedby="toggleSenhaMain">
                            <div class="input-group-append">
                                <button class="btn btn-eye" type="button" id="toggleSenhaMain" aria-label="Mostrar senha" aria-pressed="false">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small id="senha-help-text" class="form-text text-muted">Obrigatório ao cadastrar.</small>
                    </div>

                    <div class="form-group col-md-4">
                        <label for="nivel_de_acesso">Nível de Acesso</label>
                        <select class="form-control" id="nivel_de_acesso" name="nivel_de_acesso" required>
                            <option value="usuario">Usuário</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-8">
                        <label for="nome_completo">Nome Completo</label>
                        <input type="text" class="form-control" id="nome_completo" name="nome_completo" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cargo">Cargo</label>
                        <input type="text" class="form-control" id="cargo" name="cargo" required>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-8" id="email-container">
                        <label for="e_mail">E-mail</label>
                        <input type="email" class="form-control" id="e_mail" name="e_mail">
                    </div>
                    <div class="form-group col-md-4" id="acesso-adicional-container">
                        <label for="acesso_adicional">Acesso Adicional</label>
                        <select class="form-control select2" id="acesso_adicional" name="acesso_adicional[]" multiple="multiple">
                            <option value="Controle de Tarefas">Controle de Tarefas</option>
                            <option value="Fluxo de Caixa">Fluxo de Caixa</option>
                            <option value="Controle de Contas a Pagar">Controle de Contas a Pagar</option>
                            <option value="Cadastro de Funcionários">Cadastro de Funcionários</option>
                            <!-- <option value="Configuração de Contas">Configuração de Contas</option> -->
                        </select>
                    </div>
                </div>

                <button type="submit" id="submit-button" class="btn btn-gradient" style="width: 100%">
                    <i class="fa fa-floppy-o" aria-hidden="true"></i> Cadastrar
                </button>
            </form>
        </div>

        <!-- LIST CARD -->
        <div class="list-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h5 class="mb-2" style="font-weight:800;">Funcionários Cadastrados</h5>
            </div>
            <div class="table-responsive">
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 100%">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Nome Completo</th>
                            <th>Cargo</th>
                            <th>Nível de Acesso</th>
                            <th>E-mail</th>
                            <th>Acesso Adicional</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($funcionarios as $funcionario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($funcionario['nivel_de_acesso']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['e_mail'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($funcionario['acesso_adicional'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm btn-edit"
                                        data-id="<?php echo $funcionario['id']; ?>"
                                        data-usuario="<?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-nome="<?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-cargo="<?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-nivel_de_acesso="<?php echo htmlspecialchars($funcionario['nivel_de_acesso'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-e_mail="<?php echo htmlspecialchars($funcionario['e_mail'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-acesso_adicional="<?php echo htmlspecialchars($funcionario['acesso_adicional'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fa fa-pencil" aria-hidden="true"></i>
                                </button>
                                <a href="#" class="btn btn-danger btn-sm btn-delete" onclick="confirmDelete('<?php echo $funcionario['id']; ?>'); return false;">
                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ======================= MODAL IMPORTAÇÃO XLSX ======================= -->
<div class="modal fade modal-modern" id="modalImportXlsx" tabindex="-1" role="dialog" aria-labelledby="modalImportXlsxLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 880px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalImportXlsxLabel"><i class="mdi mdi-file-excel"></i> Importar Funcionários via XLSX</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true" class="text-white">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="mb-2" style="color:var(--muted);">
            Envie um arquivo <strong>.xlsx</strong> com as colunas <code>USUARIO;SENHA;NOME;CARGO</code>, exatamente nessa ordem.
        </p>

        <div id="dropArea" class="dropzone mb-3">
            <div class="icon"><i class="mdi mdi-cloud-upload-outline"></i></div>
            <div class="mb-2">Arraste e solte o arquivo aqui</div>
            <div class="file-info">ou <label for="xlsxInput" class="font-weight-bold" style="cursor:pointer; text-decoration:underline;">clique para selecionar</label></div>
            <input type="file" id="xlsxInput" accept=".xlsx" style="display:none;">
            <div id="fileName" class="mt-2 text-muted small"></div>
        </div>

        <div class="mb-3">
            <div class="progress" style="display:none;" id="uploadProgress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>
            </div>
        </div>

        <div id="importSummary" class="mb-2" style="display:none;">
            <div class="d-flex flex-wrap" style="gap:12px;">
                <span class="badge-soft"><strong>Total:</strong> <span id="sumTotal">0</span></span>
                <span class="badge-soft badge-soft-success"><strong>Importados:</strong> <span id="sumOk">0</span></span>
                <span class="badge-soft badge-soft-danger"><strong>Falhas:</strong> <span id="sumFail">0</span></span>
            </div>
        </div>

        <div class="table-responsive" id="importLogsWrap" style="display:none; max-height: 300px;">
            <table class="table table-sm table-striped log-table">
                <thead>
                    <tr>
                        <th style="width:80px;">Linha</th>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
            <tbody id="importLogsBody"></tbody>
            </table>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" id="btnProcessarXlsx" class="btn btn-gradient" disabled>
            <i class="mdi mdi-play-circle-outline"></i> Processar
        </button>
        <button type="button" id="btnRecarregar" class="btn btn-outline-secondary" style="display:none;">
            <i class="mdi mdi-refresh"></i> Atualizar lista
        </button>
        <button type="button" class="btn btn-outline-dark" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="notification">
    <span class="close-btn">&times;</span>
    <span id="notification-message"></span>
</div>

<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/bootstrap.bundle.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script src="script/jquery.dataTables.min.js"></script>
<script src="script/dataTables.bootstrap4.min.js"></script>
<script src="script/select2.min.js"></script>
<script src="script/sweetalert2.js"></script>
<script>
    // SweetAlert helper
    function showNotification(message, type) {
        if (type === 'success') {
            Swal.fire({ icon: 'success', title: 'Sucesso!', text: message, showConfirmButton: false, timer: 5000 });
        } else {
            Swal.fire({ icon: 'error', title: 'Erro!', text: message, showConfirmButton: false, timer: 5000 });
        }
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Tem certeza?',
            text: 'Tem certeza que deseja excluir este funcionário?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Não, cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete_id=' + id;
            }
        });
    }

    // Alternar tipo do campo de senha (mostrar/ocultar)
    function bindToggle(btnId, inputId){
        var $btn = $('#' + btnId), $inp = $('#' + inputId);
        $btn.on('click', function(){
            if ($btn.prop('disabled')) return;
            var isText = $inp.attr('type') === 'text';
            $inp.attr('type', isText ? 'password' : 'text');
            var $icon = $(this).find('i');
            $icon.toggleClass('fa-eye fa-eye-slash');
            $(this).attr('aria-pressed', (!isText).toString());
        });
    }

    // Habilita/Desabilita o olho conforme o estado do input
    function syncEyeWithInput(inputSelector, btnSelector){
        var disabled = $(inputSelector).is(':disabled');
        $(btnSelector).prop('disabled', disabled).toggleClass('disabled', disabled);
    }

    $(document).ready(function() {
        bindToggle('toggleSenhaMain','senha');
        syncEyeWithInput('#senha', '#toggleSenhaMain');

        // Mostrar notificação se existir
        <?php if ($notificationMessage): ?>
            showNotification('<?php echo $notificationMessage; ?>', '<?php echo $notificationType; ?>');
        <?php endif; ?>

        // Carregar dados do funcionário ao clicar em "Editar"
        $('.btn-edit').on('click', function() {
            $('#funcionario-id').val($(this).data('id'));
            $('#usuario').val($(this).data('usuario'));
            $('#nome_completo').val($(this).data('nome'));
            $('#cargo').val($(this).data('cargo'));
            $('#nivel_de_acesso').val($(this).data('nivel_de_acesso'));
            $('#e_mail').val($(this).data('e_mail'));

            // Desabilitar o campo de senha e remover obrigatoriedade ao editar
            $('#senha').val('');
            $('#senha').prop('disabled', true);
            $('#senha').removeAttr('required');
            $('#senha-help-text').text('Deixe em branco para não alterar a senha.');
            syncEyeWithInput('#senha', '#toggleSenhaMain');

            // Acessos adicionais
            $('#acesso_adicional').val(null).trigger('change');
            var acessoAdicional = $(this).data('acesso_adicional');
            if (acessoAdicional) {
                var acessos = acessoAdicional.split(',');
                $('#acesso_adicional').val(acessos).trigger('change');
            }

            // Botão
            $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Salvar Alterações');
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        // Resetar formulário (Novo Funcionário)
        $('#novo-funcionario').on('click', function() {
            $('#funcionario-id').val('');
            $('#usuario').val('');
            $('#nome_completo').val('');
            $('#cargo').val('');
            $('#nivel_de_acesso').val('usuario');
            $('#e_mail').val('');

            $('#senha').val('').prop('disabled', false).attr('required', true);
            $('#senha-help-text').text('Obrigatório ao cadastrar.');
            syncEyeWithInput('#senha', '#toggleSenhaMain');

            $('#acesso_adicional').val(null).trigger('change');

            $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Cadastrar');
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        // DataTable (idioma via CDN para evitar 404)
        $('#tabelaResultados').DataTable({
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
            "order": []
        });

        // Notificação close (fallback antigo)
        $('.notification .close-btn').on('click', function() { $(this).parent().fadeOut(); });

        // Limpar botão após submit
        $('form').on('submit', function() {
            setTimeout(function() {
                $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Cadastrar');
            }, 1000);
        });

        // Validação do campo Usuário
        $('#usuario').on('input', function() {
            var usuario = $(this).val();
            var sanitizedUsuario = usuario.replace(/[^a-zA-Z0-9]/g, '');
            if (usuario !== sanitizedUsuario) {
                $(this).val(sanitizedUsuario);
            }
        });

        // Select2
        $('.select2').select2({
            placeholder: "Selecione os acessos adicionais",
            allowClear: true,
            width: '100%'
        });

        function toggleAcessoAdicional() {
            var nivelDeAcesso = $('#nivel_de_acesso').val();
            if (nivelDeAcesso === 'usuario') {
                $('#acesso-adicional-container').show();
                $('#email-container').removeClass('col-md-12').addClass('col-md-8');
            } else {
                $('#acesso-adicional-container').hide();
                $('#email-container').removeClass('col-md-8').addClass('col-md-12');
            }
        }
        toggleAcessoAdicional();
        $('#nivel_de_acesso').on('change', toggleAcessoAdicional);
    });

    /* ========================== IMPORTAÇÃO XLSX (UI) ========================== */
    (function(){
        var dropArea = document.getElementById('dropArea');
        var input = document.getElementById('xlsxInput');
        var fileName = document.getElementById('fileName');
        var btnProcessar = document.getElementById('btnProcessarXlsx');
        var progressWrap = document.getElementById('uploadProgress');
        var progressBar = progressWrap ? progressWrap.querySelector('.progress-bar') : null;

        var sumWrap = document.getElementById('importSummary');
        var sumTotal = document.getElementById('sumTotal');
        var sumOk = document.getElementById('sumOk');
        var sumFail = document.getElementById('sumFail');
        var logsWrap = document.getElementById('importLogsWrap');
        var logsBody = document.getElementById('importLogsBody');
        var btnRecarregar = document.getElementById('btnRecarregar');

        var selectedFile = null;

        function enableProcess(enabled){
            btnProcessar.disabled = !enabled;
        }

        function resetResults(){
            if (sumWrap) sumWrap.style.display = 'none';
            if (logsWrap) logsWrap.style.display = 'none';
            if (logsBody) logsBody.innerHTML = '';
            if (btnRecarregar) btnRecarregar.style.display = 'none';
        }

        function setFile(file){
            selectedFile = file;
            fileName.textContent = file ? 'Selecionado: ' + file.name : '';
            enableProcess(!!file);
            resetResults();
        }

        // Eventos drag & drop
        if (dropArea){
            ;['dragenter','dragover'].forEach(evtName=>{
                dropArea.addEventListener(evtName, function(e){
                    e.preventDefault(); e.stopPropagation();
                    dropArea.classList.add('dragover');
                }, false);
            });
            ;['dragleave','drop'].forEach(evtName=>{
                dropArea.addEventListener(evtName, function(e){
                    e.preventDefault(); e.stopPropagation();
                    dropArea.classList.remove('dragover');
                }, false);
            });
            dropArea.addEventListener('drop', function(e){
                var dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length){ setFile(dt.files[0]); }
            }, false);

            // Clique para selecionar
            dropArea.addEventListener('click', function(){ input.click(); });
        }

        // Input file
        if (input){
            input.addEventListener('change', function(e){
                if (input.files && input.files.length){ setFile(input.files[0]); }
                else { setFile(null); }
            });
        }

        // Processar
        if (btnProcessar){
            btnProcessar.addEventListener('click', function(){
                if (!selectedFile){
                    Swal.fire({icon:'warning', title:'Atenção', text:'Selecione um arquivo .xlsx antes de processar.'});
                    return;
                }
                if (!/\.xlsx$/i.test(selectedFile.name)){
                    Swal.fire({icon:'error', title:'Arquivo inválido', text:'Envie um arquivo com extensão .xlsx.'});
                    return;
                }

                var formData = new FormData();
                formData.append('import_xlsx', '1');
                formData.append('xlsx_file', selectedFile);

                // UI durante envio
                progressWrap.style.display = 'block';
                progressBar.style.width = '0%';
                btnProcessar.disabled = true;

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);

                xhr.upload.addEventListener('progress', function(e){
                    if (e.lengthComputable){
                        var percent = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percent + '%';
                    }
                });

                xhr.onreadystatechange = function(){
                    if (xhr.readyState === 4){
                        btnProcessar.disabled = false;
                        progressBar.style.width = '100%';
                        setTimeout(function(){ progressWrap.style.display = 'none'; }, 500);

                        if (xhr.status !== 200){
                            Swal.fire({icon:'error', title:'Erro HTTP', html:'Status: ' + xhr.status + '<br>Detalhe:<br><pre style="white-space:pre-wrap;word-break:break-word;">' + (xhr.responseText || '(vazio)') + '</pre>'});
                            return;
                        }

                        try{
                            var json = JSON.parse(xhr.responseText || '{}');
                            if (!json.ok){
                                Swal.fire({icon:'error', title:'Erro', text: json.message || 'Falha ao importar.'});
                                return;
                            }
                            // Exibir resumo + logs
                            sumTotal.textContent = json.total || 0;
                            sumOk.textContent = json.importados || 0;
                            sumFail.textContent = json.falhas || 0;
                            sumWrap.style.display = 'block';

                            if (Array.isArray(json.logs) && json.logs.length){
                                logsBody.innerHTML = '';
                                json.logs.forEach(function(item){
                                    var tr = document.createElement('tr');

                                    var tdLinha = document.createElement('td');
                                    tdLinha.textContent = item.linha;
                                    tr.appendChild(tdLinha);

                                    var tdUser = document.createElement('td');
                                    tdUser.textContent = item.usuario || '';
                                    tr.appendChild(tdUser);

                                    var tdStatus = document.createElement('td');
                                    var span = document.createElement('span');
                                    span.className = 'badge-soft ' + (item.status === 'ok' ? 'badge-soft-success' : 'badge-soft-danger');
                                    span.textContent = (item.status === 'ok' ? 'OK' : 'ERRO');
                                    tdStatus.appendChild(span);
                                    tr.appendChild(tdStatus);

                                    var tdMsg = document.createElement('td');
                                    tdMsg.textContent = item.mensagem || '';
                                    tr.appendChild(tdMsg);

                                    logsBody.appendChild(tr);
                                });
                                logsWrap.style.display = 'block';
                            } else {
                                logsWrap.style.display = 'none';
                            }

                            btnRecarregar.style.display = 'inline-block';
                            Swal.fire({icon:'success', title:'Importação concluída', text:'Resumo exibido no modal.'});
                        } catch(e){
                            // Mostra resposta bruta quando JSON inválido (para debug)
                            Swal.fire({icon:'error', title:'Resposta inesperada do servidor', html:'<pre style="white-space:pre-wrap;word-break:break-word;">' + (xhr.responseText || '(vazio)') + '</pre>'});
                        }
                    }
                };

                xhr.send(formData);
            });
        }

        if (btnRecarregar){
            btnRecarregar.addEventListener('click', function(){ window.location.reload(); });
        }

        // Reset ao abrir o modal
        $('#modalImportXlsx').on('show.bs.modal', function(){
            setFile(null);
            fileName.textContent = '';
            input.value = '';
            progressWrap.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
            resetResults();
        });
    })();
</script>

<br><br><br>
<?php include(__DIR__ . '/rodape.php'); ?>

</body>
</html>
