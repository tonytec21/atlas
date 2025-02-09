<?php  
header('Content-Type: application/json');  
include(__DIR__ . '/session_check.php');  
include(__DIR__ . '/db_connection.php');  

try {  
    if (!isset($_GET['id_obito'])) {  
        throw new Exception('ID do óbito não fornecido');  
    }  

    $id_obito = intval($_GET['id_obito']);  

    // Query corrigida para a tabela indexador_obito_anexos  
    $query = "SELECT id, caminho_anexo as nome_arquivo   
             FROM indexador_obito_anexos   
             WHERE id_obito = ?   
             AND status = 'A'";  
    
    $stmt = $conn->prepare($query);  
    if (!$stmt) {  
        throw new Exception("Erro na preparação da query: " . $conn->error);  
    }  

    $stmt->bind_param('i', $id_obito);  
    if (!$stmt->execute()) {  
        throw new Exception("Erro na execução da query: " . $stmt->error);  
    }  

    $result = $stmt->get_result();  
    $anexos = $result->fetch_all(MYSQLI_ASSOC);  
    
    echo json_encode(['success' => true, 'anexos' => $anexos]);  

} catch (Exception $e) {  
    error_log("Erro em buscar_anexos.php: " . $e->getMessage());  
    http_response_code(200); // Mantemos 200 mesmo com erro pois é uma situação esperada  
    echo json_encode([  
        'success' => false,  
        'error' => $e->getMessage(),  
        'anexos' => []  
    ]);  
} finally {  
    if (isset($stmt)) $stmt->close();  
    if (isset($conn)) $conn->close();  
}  
?>