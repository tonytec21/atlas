<?php
include(__DIR__ . '/session_check.php');
checkSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $os_id = $_POST['os_id'];

    // Configuração do banco de dados
    $servername = "localhost";
    $username = "root"; 
    $password = ""; 
    $dbname = "atlas";

    try {
        // Criar conexão com o banco de dados
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Verificar conexão
        if ($conn->connect_error) {
            throw new Exception("Erro ao conectar ao banco de dados: " . $conn->connect_error);
        }

        // Buscar o ano de criação da OS
        $stmt = $conn->prepare("SELECT YEAR(data_criacao) AS ano_criacao FROM ordens_de_servico WHERE id = ?");
        $stmt->bind_param("i", $os_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['ano_criacao' => $row['ano_criacao']]);
        } else {
            echo json_encode(['error' => 'Ordem de Serviço não encontrada.']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro ao buscar o ano de criação: ' . $e->getMessage()]);
    } finally {
        // Fechar conexão
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
} else {
    echo json_encode(['error' => 'Método inválido.']);
}
