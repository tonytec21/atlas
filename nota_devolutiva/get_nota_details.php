<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection2.php');  

// Configurar cabeçalhos para resposta JSON  
header('Content-Type: application/json');  

if (!isset($_GET['numero'])) {  
    echo json_encode(['success' => false, 'message' => 'Número da nota devolutiva não informado']);  
    exit;  
}  

$numero = $conn->real_escape_string($_GET['numero']);  

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

    $sql = "SELECT * FROM notas_devolutivas WHERE numero = '$numero'";  
    $result = $conn->query($sql);  
    
    if (!$result) {  
        throw new Exception("Erro ao consultar nota devolutiva: " . $conn->error);  
    }  
    
    if ($result->num_rows === 0) {  
        throw new Exception("Nota devolutiva não encontrada");  
    }  
    
    $nota = $result->fetch_assoc();  
    
    // Garantir que todos os campos existam no resultado (mesmo que vazios)  
    if (!isset($nota['prazo_cumprimento'])) {  
        $nota['prazo_cumprimento'] = '';  
    }  
    
    if (!isset($nota['cpf_cnpj'])) {  
        $nota['cpf_cnpj'] = '';  
    }  
    
    if (!isset($nota['origem_titulo'])) {  
        $nota['origem_titulo'] = '';  
    }  
    
    // Formatar a data para o padrão brasileiro antes de enviar  
    if (isset($nota['data']) && !empty($nota['data'])) {  
        $data_obj = new DateTime($nota['data']);  
        $nota['data_formatada'] = $data_obj->format('d/m/Y');  
    }  
    
    // Formatar a data do protocolo se existir  
    if (isset($nota['data_protocolo']) && !empty($nota['data_protocolo'])) {  
        $data_obj = new DateTime($nota['data_protocolo']);  
        $nota['data_protocolo_formatada'] = $data_obj->format('d/m/Y');  
    }  
    
    echo json_encode($nota);  
} catch (Exception $e) {  
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);  
}  

$conn->close();  
?>