<?php
/* ------------------------------------------------------------------
   salvar_agendamento.php
   • INSERT: criado_por + data_reagendamento
   • UPDATE: inclui data_reagendamento
------------------------------------------------------------------ */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: text/plain');

/* ---------- dados do formulário ---------- */
$id          = $_POST['agendamento_id']   ?? '';
$nome        = $_POST['nome']             ?? '';
$servico     = $_POST['servico']          ?? '';
$data_hora   = $_POST['data_hora']        ?? '';
$status      = $_POST['status']           ?? 'ativo';
$observacoes = $_POST['observacoes']      ?? '';
$reag        = $_POST['data_reagendamento'] ?? null;
$reag        = $reag === '' ? null : $reag;          // converte string vazia em NULL

/* usuário logado */
$username = $_SESSION['username'] ?? 'desconhecido';

/* ------------------------------------------------------------------
   INSERT  – novo agendamento
------------------------------------------------------------------ */
if (empty($id)) {

    $stmt = $conn->prepare("
        INSERT INTO agendamentos
            (criado_por, nome_solicitante, servico,
             data_hora, status, observacoes, data_reagendamento)
        VALUES (?,?,?,?,?,?,?)");

    $stmt->bind_param(
        'sssssss',
        $username,
        $nome,
        $servico,
        $data_hora,
        $status,
        $observacoes,
        $reag
    );

    if ($stmt->execute()) {
        echo $conn->insert_id;   // devolve novo ID
        exit;
    }

/* ------------------------------------------------------------------
   UPDATE  – edição
------------------------------------------------------------------ */
} else {

    $stmt = $conn->prepare("
        UPDATE agendamentos
        SET nome_solicitante   = ?,
            servico            = ?,
            data_hora          = ?,
            status             = ?,
            observacoes        = ?,
            data_reagendamento = ?
        WHERE id = ?");

    $stmt->bind_param(
        'ssssssi',
        $nome,
        $servico,
        $data_hora,
        $status,
        $observacoes,
        $reag,
        $id
    );

    if ($stmt->execute()) {
        echo intval($id);        // devolve o mesmo ID
        exit;
    }
}

/* se chegou aqui, ocorreu erro */
http_response_code(500);
echo 'Erro ao salvar';
