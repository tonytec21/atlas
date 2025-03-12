<?php  
include(__DIR__ . '/db_connection.php');  

if (isset($_GET['id'])) {  
    $conn = getDatabaseConnection();  
    $id = $_GET['id'];  

    try {  
        // Busca as informações do checklist  
        $stmt = $conn->prepare("SELECT titulo, observacoes FROM checklists WHERE id = :id");  
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);  
        $stmt->execute();  
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);  

        if ($checklist) {  
            // Busca os grupos de títulos  
            $stmt = $conn->prepare("SELECT id, titulo, observacoes FROM checklist_titulos WHERE checklist_id = :id");  
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);  
            $stmt->execute();  
            $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);  
            
            // Cria mapa de IDs de títulos existentes para verificação  
            $titulosExistentes = [];  
            foreach ($grupos as $grupo) {  
                $titulosExistentes[$grupo['id']] = true;  
            }  
            
            // Busca os itens com suas informações de grupo  
            $stmt = $conn->prepare("  
                SELECT id, item, titulo, checklist_titulos_id   
                FROM checklist_itens   
                WHERE checklist_id = :id  
                ORDER BY id ASC"); // Mantém a ordem original dos itens  
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);  
            $stmt->execute();  
            $itensDb = $stmt->fetchAll(PDO::FETCH_ASSOC);  
            
            // Para itens com título=sim mas sem grupo, vamos criar grupos automáticos  
            $itensParaGruposAutomaticos = [];  
            foreach ($itensDb as $item) {  
                if ($item['titulo'] == 'sim' &&   
                   (empty($item['checklist_titulos_id']) || !isset($titulosExistentes[$item['checklist_titulos_id']]))) {  
                    $itensParaGruposAutomaticos[] = $item;  
                }  
            }  
            
            // Cria grupos automáticos para itens com título=sim mas sem grupo  
            if (!empty($itensParaGruposAutomaticos)) {  
                // Agrupa itens que precisam de título  
                $gruposTitulo = [];  
                foreach ($itensParaGruposAutomaticos as $item) {  
                    $gruposTitulo[$item['id']] = $item['item'];  
                }  
                
                // Cria um grupo para cada item que precisa  
                foreach ($gruposTitulo as $itemId => $itemTexto) {  
                    $grupoNome = "Item Destaque";  
                    
                    // Insere o grupo na tabela  
                    $stmt = $conn->prepare("INSERT INTO checklist_titulos (checklist_id, titulo, observacoes) VALUES (:checklist_id, :titulo, :observacoes)");  
                    $stmt->bindParam(':checklist_id', $id);  
                    $stmt->bindParam(':titulo', $grupoNome);  
                    $observacoes = "Grupo criado automaticamente para item com destaque.";  
                    $stmt->bindParam(':observacoes', $observacoes);  
                    $stmt->execute();  
                    $novoGrupoId = $conn->lastInsertId();  
                    
                    // Adiciona o novo grupo à lista de grupos  
                    $grupos[] = [  
                        'id' => $novoGrupoId,  
                        'titulo' => $grupoNome,  
                        'observacoes' => $observacoes  
                    ];  
                    
                    // Atualiza o item para associá-lo ao novo grupo  
                    $stmt = $conn->prepare("UPDATE checklist_itens SET checklist_titulos_id = :titulo_id WHERE id = :item_id");  
                    $stmt->bindParam(':titulo_id', $novoGrupoId);  
                    $stmt->bindParam(':item_id', $itemId);  
                    $stmt->execute();  
                    
                    // Atualize também a matriz local para retornar para o frontend  
                    foreach ($itensDb as &$itemRef) {  
                        if ($itemRef['id'] == $itemId) {  
                            $itemRef['checklist_titulos_id'] = $novoGrupoId;  
                            break;  
                        }  
                    }  
                }  
                
                // Busque novamente os itens após atualizações  
                $stmt = $conn->prepare("  
                    SELECT id, item, titulo, checklist_titulos_id   
                    FROM checklist_itens   
                    WHERE checklist_id = :id  
                    ORDER BY id ASC");  
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);  
                $stmt->execute();  
                $itensDb = $stmt->fetchAll(PDO::FETCH_ASSOC);  
            }  
            
            // Formata os itens para o frontend  
            $itens = [];  
            foreach ($itensDb as $item) {  
                $itens[] = [  
                    'texto' => $item['item'],  
                    'titulo' => $item['titulo'] ?: 'não',  
                    'tituloId' => $item['checklist_titulos_id']  
                ];  
            }  

            echo json_encode([  
                "titulo" => $checklist['titulo'],   
                "observacoes" => $checklist['observacoes'],  
                "grupos" => $grupos,  
                "itens" => $itens  
            ]);  
        } else {  
            echo json_encode(["error" => "Checklist não encontrado"]);  
        }  
    } catch (Exception $e) {  
        echo json_encode(["error" => "Erro ao buscar dados: " . $e->getMessage()]);  
    }  
}  
?>