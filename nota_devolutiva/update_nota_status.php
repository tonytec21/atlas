<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  

// Conexão com o banco usando db_connection2.php  
require_once(__DIR__ . '/db_connection2.php');  

// Verifica se a requisição é um POST  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    // Checa se os parâmetros necessários estão presentes  
    if (isset($_POST['numero']) && isset($_POST['status'])) {  
        $numero = $conn->real_escape_string($_POST['numero']);  
        $status = $conn->real_escape_string($_POST['status']);  
        
        // Validar o status para garantir que está na lista de opções aceitas  
        $statusValidos = [  
            'Pendente',   
            'Exigência Cumprida',   
            'Exigência Não Cumprida',   
            'Prazo Expirado',   
            'Em Análise',   
            'Cancelada',   
            'Aguardando Documentação'  
        ];  
        
        if (!in_array($status, $statusValidos)) {  
            echo json_encode(['success' => false, 'message' => 'Status inválido']);  
            exit;  
        }  
        
        // Atualizar o status no banco de dados  
        $sql = "UPDATE notas_devolutivas SET status = ? WHERE numero = ?";  
        $stmt = $conn->prepare($sql);  
        $stmt->bind_param("ss", $status, $numero);  
        
        if ($stmt->execute()) {  
            echo json_encode(['success' => true]);  
        } else {  
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status: ' . $conn->error]);  
        }  
        
        $stmt->close();  
    } else {  
        echo json_encode(['success' => false, 'message' => 'Parâmetros incompletos']);  
    }  
} else {  
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido']);  
}  

$conn->close();  
?>