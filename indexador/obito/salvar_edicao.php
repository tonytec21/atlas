<?php
/* ---------------------------------------------------------------
   Atualizado em 20-mai-2025 para gerar e gravar matrícula no UPDATE
---------------------------------------------------------------- */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido!']);
    exit;
}

include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

session_start();
$funcionario = $_SESSION['username'] ?? '';
$status      = 'A';

/* ---------------------------- validação ---------------------------- */
if (empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID do registro não fornecido.']);
    exit;
}
$id = intval($_POST['id']);

/* --------------------------- dados do POST ------------------------- */
$livro               = $_POST['livro']               ?? '';
$folha               = $_POST['folha']               ?? '';
$termo               = $_POST['termo']               ?? '';
$data_registro       = $_POST['data_registro']       ?? '';
$data_obito          = $_POST['data_obito']          ?? '';
$hora_obito          = $_POST['hora_obito']          ?? '';
$nome_registrado     = mb_strtoupper(trim($_POST['nome_registrado']), 'UTF-8');
$data_nascimento     = $_POST['data_nascimento']     ?? '';
$nome_pai            = mb_strtoupper(trim($_POST['nome_pai']  ?? ''), 'UTF-8');
$nome_mae            = mb_strtoupper(trim($_POST['nome_mae']  ?? ''), 'UTF-8');
$cidade_endereco     = $_POST['cidade_endereco']     ?? '';
$ibge_cidade_endereco= $_POST['ibge_cidade_endereco']?? '';
$cidade_obito        = $_POST['cidade_obito']        ?? '';
$ibge_cidade_obito   = $_POST['ibge_cidade_obito']   ?? '';

/* ------------------------- gera matrícula -------------------------- */
function gerarMatricula(mysqli $conn, $livro, $folha, $termo, $data_registro)
{
    // CNS da serventia
    $cnsRow = $conn->query("SELECT cns FROM cadastro_serventia LIMIT 1")->fetch_assoc();
    $cns = str_pad($cnsRow['cns'] ?? '', 6, '0', STR_PAD_LEFT);

    // Formatações
    $livro  = str_pad($livro, 5, '0', STR_PAD_LEFT);
    $folha  = str_pad($folha, 3, '0', STR_PAD_LEFT);
    $termo  = str_pad($termo, 7, '0', STR_PAD_LEFT);
    $ano    = explode('-', $data_registro)[0];  // AAAA
    $base   = $cns . '01' /*acervo*/ . '55' /*ato*/ . $ano . '4' /*tipo*/ .
              $livro . $folha . $termo;

    // Dígitos verificadores – mesmo algoritmo do CNJ
    $s = 0; $m = 32;
    for ($i = 0; $i < 30; $i++) $s += intval($base[$i]) * ($m--);
    $d1 = ($s * 10) % 11; if ($d1 == 10) $d1 = 1;

    $s = 0; $m = 33;
    for ($i = 0; $i < 30; $i++) $s += intval($base[$i]) * ($m--);
    $s += $d1 * 2;
    $d2 = ($s * 10) % 11; if ($d2 == 10) $d2 = 1;

    return $base . $d1 . $d2;
}

$matriculaFinal = gerarMatricula($conn, $livro, $folha, $termo, $data_registro);

/* ------------------- atualiza registro principal ------------------- */
$sql = "UPDATE indexador_obito
        SET livro = ?, folha = ?, termo = ?, data_registro = ?, data_obito = ?, hora_obito = ?,
            nome_registrado = ?, data_nascimento = ?, nome_pai = ?, nome_mae = ?, cidade_endereco = ?,
            ibge_cidade_endereco = ?, cidade_obito = ?, ibge_cidade_obito = ?, funcionario = ?,
            matricula = ?
        WHERE id = ? AND status = 'A'";

if (!$stmt = $conn->prepare($sql)) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao preparar query: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    "ssssssssssssssssi",
    $livro, $folha, $termo, $data_registro, $data_obito, $hora_obito,
    $nome_registrado, $data_nascimento, $nome_pai, $nome_mae, $cidade_endereco,
    $ibge_cidade_endereco, $cidade_obito, $ibge_cidade_obito, $funcionario,
    $matriculaFinal,
    $id
);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar: ' . $stmt->error]);
    exit;
}
$stmt->close();

/* ----------------------- upload de novos anexos -------------------- */
if (!empty($_FILES['anexos']['name'][0])) {
    $uploadDir = 'anexos/obitos/' . $id . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $stmt_anexo = $conn->prepare(
        "INSERT INTO indexador_obito_anexos (id_obito, caminho_anexo, funcionario, status)
         VALUES (?, ?, ?, ?)"
    );
    if (!$stmt_anexo) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao preparar query de anexos: ' . $conn->error]);
        exit;
    }

    foreach ($_FILES['anexos']['tmp_name'] as $k => $tmp_name) {
        if ($_FILES['anexos']['error'][$k] !== UPLOAD_ERR_OK) continue;

        $fileName = $_FILES['anexos']['name'][$k];
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') continue;

        $uniqueName = uniqid() . '_' . $fileName;
        $destPath   = $uploadDir . $uniqueName;

        if (move_uploaded_file($tmp_name, $destPath)) {
            $stmt_anexo->bind_param("isss", $id, $destPath, $funcionario, $status);
            $stmt_anexo->execute();  // em caso de erro, só loga
        }
    }
    $stmt_anexo->close();
}

/* --------------------------- resposta OK --------------------------- */
echo json_encode([
    'status'    => 'success',
    'message'   => 'Registro atualizado com sucesso!',
    'matricula' => $matriculaFinal
]);
exit;
?>
