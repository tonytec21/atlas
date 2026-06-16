<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($conn)) {
            throw new Exception('Erro ao conectar ao banco de dados.');
        }

        $devolucao_id = intval($_POST['devolucao_id'] ?? 0);
        if ($devolucao_id <= 0) {
            throw new Exception('Devolução inválida.');
        }

        // Busca a devolução
        $st = $conn->prepare("SELECT data_devolucao FROM devolucao_os WHERE id = ?");
        $st->bind_param("i", $devolucao_id);
        $st->execute();
        $res = $st->get_result();
        if (!$res || $res->num_rows === 0) {
            throw new Exception('Devolução não encontrada.');
        }
        $row = $res->fetch_assoc();

        // Só permite excluir devoluções realizadas no dia atual
        if (date('Y-m-d', strtotime($row['data_devolucao'])) !== date('Y-m-d')) {
            throw new Exception('Não é possível excluir devoluções realizadas em dias anteriores à data atual.');
        }

        $del = $conn->prepare("DELETE FROM devolucao_os WHERE id = ?");
        $del->bind_param("i", $devolucao_id);
        if (!$del->execute()) {
            throw new Exception('Erro ao remover devolução: ' . $conn->error);
        }

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
}
?>
