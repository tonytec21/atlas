<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection2.php');  

// Configurar cabeçalhos para resposta JSON  
header('Content-Type: application/json');  

try {  
    // Verificar se as novas colunas existem na tabela antes de consultar  
    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'prazo_cumprimento'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN prazo_cumprimento TEXT AFTER corpo");  
    }  

    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'cpf_cnpj'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN cpf_cnpj VARCHAR(20) AFTER apresentante");  
    }  

    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'data_protocolo'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN data_protocolo DATE AFTER protocolo");  
    }  

    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'origem_titulo'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN origem_titulo VARCHAR(200) AFTER titulo");  
    }  

    // Buscar todas as notas devolutivas ordenadas por data (mais recentes primeiro)  
    // Mantendo os campos originais para a listagem na tabela  
    $sql = "SELECT numero, DATE_FORMAT(data, '%d/%m/%Y') as data, apresentante, titulo, assinante FROM notas_devolutivas ORDER BY data DESC";  
    $result = $conn->query($sql);  
    
    if (!$result) {  
        throw new Exception("Erro ao consultar notas devolutivas: " . $conn->error);  
    }  
    
    $notas = [];  
    while ($row = $result->fetch_assoc()) {  
        $notas[] = $row;  
    }  
    
    echo json_encode(['success' => true, 'data' => $notas]);  
} catch (Exception $e) {  
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);  
}  

$conn->close();  
?>