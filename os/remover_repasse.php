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

        $repasse_id = intval($_POST['repasse_id'] ?? 0);
        if ($repasse_id <= 0) {
            throw new Exception('Repasse inválido.');
        }

        // Busca o repasse
        $st = $conn->prepare("SELECT data_repasse FROM repasse_credor WHERE id = ?");
        $st->bind_param("i", $repasse_id);
        $st->execute();
        $res = $st->get_result();
        if (!$res || $res->num_rows === 0) {
            throw new Exception('Repasse não encontrado.');
        }
        $row = $res->fetch_assoc();

        // Só permite excluir repasses realizados no dia atual
        if (date('Y-m-d', strtotime($row['data_repasse'])) !== date('Y-m-d')) {
            throw new Exception('Não é possível excluir repasses realizados em dias anteriores à data atual.');
        }

        $del = $conn->prepare("DELETE FROM repasse_credor WHERE id = ?");
        $del->bind_param("i", $repasse_id);
        if (!$del->execute()) {
            throw new Exception('Erro ao remover repasse: ' . $conn->error);
        }

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
}
?>
