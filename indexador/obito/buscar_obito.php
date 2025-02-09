<?php  
header('Content-Type: application/json');  
include(__DIR__ . '/session_check.php');  
include(__DIR__ . '/db_connection.php');  

try {  
    if (!isset($_GET['id'])) {  
        throw new Exception('ID não fornecido');  
    }  

    $id = intval($_GET['id']);  

    $query = "SELECT * FROM indexador_obito WHERE id = ? AND status = 'A'";  
    
    $stmt = $conn->prepare($query);  
    if (!$stmt) {  
        throw new Exception("Erro na preparação da query: " . $conn->error);  
    }  

    $stmt->bind_param('i', $id);  
    if (!$stmt->execute()) {  
        throw new Exception("Erro na execução da query: " . $stmt->error);  
    }  

    $result = $stmt->get_result();  
    
    if ($result->num_rows === 0) {  
        throw new Exception("Registro não encontrado");  
    }  

    $data = $result->fetch_assoc();  
    
    // Formata as datas  
    if (!empty($data['data_registro'])) {  
        $data['data_registro'] = date('d/m/Y', strtotime($data['data_registro']));  
    }  
    if (!empty($data['data_obito'])) {  
        $data['data_obito'] = date('d/m/Y', strtotime($data['data_obito']));  
    }  
    if (!empty($data['data_nascimento'])) {  
        $data['data_nascimento'] = date('d/m/Y', strtotime($data['data_nascimento']));  
    }  

    echo json_encode(['success' => true, 'data' => $data]);  

} catch (Exception $e) {  
    error_log("Erro em buscar_obito.php: " . $e->getMessage());  
    http_response_code(400);  
    echo json_encode([  
        'success' => false,  
        'error' => $e->getMessage()  
    ]);  
} finally {  
    if (isset($stmt)) $stmt->close();  
    if (isset($conn)) $conn->close();  
}  
?>