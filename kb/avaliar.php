<?php
/**
 * atlas/kb/avaliar.php
 * Registra se a resposta foi util. Alimenta a medicao de qualidade.
 */
include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
include(__DIR__ . '/../provimentos/db_connection.php');

header('Content-Type: application/json; charset=utf-8');

$id   = isset($_POST['id'])   ? (int) $_POST['id']   : 0;
$util = isset($_POST['util']) ? (int) $_POST['util'] : null;

if (!$id || ($util !== 0 && $util !== 1)) {
    echo json_encode(array('success' => false));
    exit;
}

$conn = getDatabaseConnection();
$st = $conn->prepare("UPDATE kb_consultas SET util = :u WHERE id = :id");
$st->execute(array(':u' => $util, ':id' => $id));

echo json_encode(array('success' => true));
