<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

if ($_SERVER["REQUEST_METHOD"] == "POST") {  
    $conn = getDatabaseConnection();  

    // Recebendo os campos do formulário  
    $titulo = $_POST['titulo'];  
    $observacoes = $_POST['observacoes'] ?? null;  
    $criado_por = $_SESSION['username'];  
    $itens = json_decode($_POST['itens'], true);  
    $grupos = json_decode($_POST['grupos'], true);  

    try {  
        $conn->beginTransaction();  

        // Salva o checklist  
        $stmt = $conn->prepare("INSERT INTO checklists (titulo, observacoes, criado_por) VALUES (:titulo, :observacoes, :criado_por)");  
        $stmt->bindParam(':titulo', $titulo);  
        $stmt->bindParam(':observacoes', $observacoes);  
        $stmt->bindParam(':criado_por', $criado_por);  
        $stmt->execute();  
        $checklist_id = $conn->lastInsertId();  

        // Mapeamento de IDs temporários para IDs reais no banco  
        $mapaTitulosId = [];  

        // Salva os grupos de títulos  
        if (!empty($grupos)) {  
            $stmt = $conn->prepare("INSERT INTO checklist_titulos (checklist_id, titulo, observacoes) VALUES (:checklist_id, :titulo, :observacoes)");  
            foreach ($grupos as $grupo) {  
                $stmt->bindParam(':checklist_id', $checklist_id);  
                $stmt->bindParam(':titulo', $grupo['titulo']);  
                $stmt->bindParam(':observacoes', $grupo['observacoes']);  
                $stmt->execute();  
                $titulo_id = $conn->lastInsertId();  
                $mapaTitulosId[$grupo['id']] = $titulo_id;  
            }  
        }  

        // Salva os itens do checklist  
        $stmt = $conn->prepare("INSERT INTO checklist_itens (checklist_id, item, titulo, checklist_titulos_id) VALUES (:checklist_id, :item, :titulo, :titulo_id)");  
        foreach ($itens as $item) {  
            $stmt->bindParam(':checklist_id', $checklist_id);  
            $stmt->bindParam(':item', $item['texto']);  
            $stmt->bindParam(':titulo', $item['titulo']);  
            
            // Define o ID do título se existir  
            $titulo_id = null;  
            if ($item['titulo'] == 'sim' && !empty($item['tituloId']) && isset($mapaTitulosId[$item['tituloId']])) {  
                $titulo_id = $mapaTitulosId[$item['tituloId']];  
            }  
            $stmt->bindParam(':titulo_id', $titulo_id);  
            
            $stmt->execute();  
        }  

        $conn->commit();  
        echo json_encode(["success" => true, "message" => "Checklist salvo com sucesso!"]);  
    } catch (Exception $e) {  
        $conn->rollBack();  
        echo json_encode(["error" => "Erro ao salvar checklist: " . $e->getMessage()]);  
    }  
}  
?>