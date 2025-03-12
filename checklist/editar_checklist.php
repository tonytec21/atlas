<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

if ($_SERVER["REQUEST_METHOD"] == "POST") {  
    $conn = getDatabaseConnection();  
    $id = $_POST['id'];  
    $titulo = $_POST['titulo'];  
    $observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : null;  
    $itens = json_decode($_POST['itens'], true);  
    $grupos = json_decode($_POST['grupos'], true);  

    try {  
        $conn->beginTransaction();  

        // Atualiza o título e as observações  
        $stmt = $conn->prepare("UPDATE checklists SET titulo = :titulo, observacoes = :observacoes WHERE id = :id");  
        $stmt->bindParam(':titulo', $titulo);  
        $stmt->bindParam(':observacoes', $observacoes);  
        $stmt->bindParam(':id', $id);  
        $stmt->execute();  

        // Limpa os grupos e itens antigos  
        $stmt = $conn->prepare("DELETE FROM checklist_itens WHERE checklist_id = :id");  
        $stmt->bindParam(':id', $id);  
        $stmt->execute();  
        
        $stmt = $conn->prepare("DELETE FROM checklist_titulos WHERE checklist_id = :id");  
        $stmt->bindParam(':id', $id);  
        $stmt->execute();  

        // Mapeamento de IDs temporários para IDs reais no banco  
        $mapaTitulosId = [];  

        // Salva os grupos de títulos  
        if (!empty($grupos)) {  
            $stmt = $conn->prepare("INSERT INTO checklist_titulos (checklist_id, titulo, observacoes) VALUES (:checklist_id, :titulo, :observacoes)");  
            foreach ($grupos as $grupo) {  
                $stmt->bindParam(':checklist_id', $id);  
                $stmt->bindParam(':titulo', $grupo['titulo']);  
                $stmt->bindParam(':observacoes', $grupo['observacoes']);  
                $stmt->execute();  
                $titulo_id = $conn->lastInsertId();  
                
                // Para grupos existentes que possam ter um ID no banco já  
                if (is_numeric($grupo['id'])) {  
                    $mapaTitulosId[$grupo['id']] = $titulo_id; // Atualiza para o novo ID  
                } else {  
                    $mapaTitulosId[$grupo['id']] = $titulo_id;  
                }  
            }  
        }  

        // Tratamento especial para itens com titulo='sim' mas sem grupo  
        $itensComTituloSemGrupo = [];  
        foreach ($itens as $item) {  
            if ($item['titulo'] == 'sim' && empty($item['tituloId'])) {  
                $itensComTituloSemGrupo[] = $item;  
            }  
        }  

        // Cria grupos automáticos para itens com título=sim mas sem grupo  
        if (!empty($itensComTituloSemGrupo)) {  
            $stmt = $conn->prepare("INSERT INTO checklist_titulos (checklist_id, titulo, observacoes) VALUES (:checklist_id, :titulo, :observacoes)");  

            foreach ($itensComTituloSemGrupo as $key => $item) {  
                $grupoNome = "Item Destaque " . ($key + 1);  
                $observacaoGrupo = "Grupo criado automaticamente para item com destaque.";  
                
                $stmt->bindParam(':checklist_id', $id);  
                $stmt->bindParam(':titulo', $grupoNome);  
                $stmt->bindParam(':observacoes', $observacaoGrupo);  
                $stmt->execute();  
                $novoGrupoId = $conn->lastInsertId();  
                
                // Cria um ID temporário para esse item  
                $tempId = 'auto_' . $key;  
                $mapaTitulosId[$tempId] = $novoGrupoId;  
                
                // Atualiza o item na lista para usar esse grupo  
                foreach ($itens as &$itemRef) {  
                    if ($itemRef === $item) {  
                        $itemRef['tituloId'] = $tempId;  
                        break;  
                    }  
                }  
            }  
        }  

        // Insere os novos itens  
        $stmt = $conn->prepare("INSERT INTO checklist_itens (checklist_id, item, titulo, checklist_titulos_id) VALUES (:checklist_id, :item, :titulo, :titulo_id)");  
        foreach ($itens as $item) {  
            $stmt->bindParam(':checklist_id', $id);  
            $stmt->bindParam(':item', $item['texto']);  
            $stmt->bindParam(':titulo', $item['titulo']);  
            
            // Define o ID do título se existir  
            $titulo_id = null;  
            if ($item['titulo'] == 'sim' && !empty($item['tituloId'])) {  
                $titulo_id = isset($mapaTitulosId[$item['tituloId']]) ? $mapaTitulosId[$item['tituloId']] : null;  
            }  
            $stmt->bindParam(':titulo_id', $titulo_id);  
            
            $stmt->execute();  
        }  

        $conn->commit();  
        echo json_encode(["success" => true, "message" => "Checklist atualizado com sucesso!"]);  
    } catch (Exception $e) {  
        $conn->rollBack();  
        echo json_encode(["error" => "Erro ao atualizar checklist: " . $e->getMessage()]);  
    }  
}  
?>