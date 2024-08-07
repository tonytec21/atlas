<?php
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ip'])) {
    $ip = $_POST['ip'];

    // Adicionar o prefixo https:// se não estiver presente
    if (strpos($ip, 'https://') !== 0) {
        $ip = 'https://' . $ip;
    }

    $query = "UPDATE conexao_selador SET url_base = ? WHERE id = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $ip);
    
    if ($stmt->execute()) {
        echo 'IP atualizado com sucesso.';
    } else {
        echo 'Erro ao atualizar o IP.';
    }
    $stmt->close();
}
?>