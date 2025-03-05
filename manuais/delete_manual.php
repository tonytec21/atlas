<?php  
require_once 'conexao_bd.php';  

// Verificar se foi fornecido um ID  
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    header('Location: manual-list.php');  
    exit;  
}  

$id = (int)$_GET['id'];  

// Iniciar a transação  
$conexao->beginTransaction();  

try {  
    // Primeiro, excluir os passos relacionados ao manual  
    $stmt = $conexao->prepare("DELETE FROM passos WHERE manual_id = ?");  
    $stmt->execute([$id]);  
    
    // Em seguida, excluir o manual  
    $stmt = $conexao->prepare("DELETE FROM manuais WHERE id = ?");  
    $stmt->execute([$id]);  
    
    // Commit da transação  
    $conexao->commit();  
    
    // Redirecionar com mensagem de sucesso  
    header('Location: manual-list.php?status=deleted');  
    exit;  
    
} catch (PDOException $e) {  
    // Rollback em caso de erro  
    $conexao->rollBack();  
    
    // Log do erro  
    error_log("Erro ao excluir manual: " . $e->getMessage());  
    
    // Redirecionar com mensagem de erro  
    header('Location: manual-list.php?status=error');  
    exit;  
}  
?>