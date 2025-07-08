<?php
/* -----------------------------------------------------------
   upload_anexo_agendamento.php  –  versão atualizada
   • Grava usuário que anexou (anexado_por)
   • Mantém validações e diretório /anexos/{id}/
----------------------------------------------------------- */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

/* ---------- parâmetros ---------- */
$agendamentoId = intval($_POST['agendamento_id'] ?? 0);
$username      = $_SESSION['username'] ?? 'desconhecido';

if (!$agendamentoId) { http_response_code(400); exit; }
if (!isset($_FILES['arquivo'])) { http_response_code(400); exit; }

/* ---------- prepara diretório ---------- */
$dir = __DIR__ . "/anexos/$agendamentoId/";
if (!is_dir($dir)) mkdir($dir, 0775, true);

/* ---------- move arquivo ---------- */
$tmp   = $_FILES['arquivo']['tmp_name'];
$orig  = basename($_FILES['arquivo']['name']);
$type  = mime_content_type($tmp);
$nome  = uniqid() . '_' . $orig;       // nome único
$path  = "anexos/$agendamentoId/$nome";

if (move_uploaded_file($tmp, $dir . $nome)) {

    /* ---------- grava no banco ---------- */
    $stmt = $conn->prepare("
        INSERT INTO agendamento_anexos
            (agendamento_id, anexado_por, caminho, nome_original, tipo_mime)
        VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss',
        $agendamentoId,
        $username,
        $path,
        $orig,
        $type
    );
    $stmt->execute();
    echo 'ok';

} else {
    http_response_code(500);
}
