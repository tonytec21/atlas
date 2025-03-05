<?php  
/**  
 * Processador de salvamento de manuais  
 * Recebe os dados do formulário e salva no banco de dados  
 */  

require_once 'conexao_bd.php';  

// Verificar método da requisição  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  
    header('Location: manual-list.php');  
    exit;  
}  

// Iniciar a transação  
$conexao->beginTransaction();  

try {  
    // Dados básicos do manual  
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;  
    $titulo = trim($_POST['titulo']);  
    $descricao = trim($_POST['descricao']);  
    $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;  
    $versao = trim($_POST['versao']);  
    $remover_capa = (isset($_POST['remover_capa']) && $_POST['remover_capa'] == '1');  
    $imagem_capa_atual = !empty($_POST['imagem_capa_atual']) ? $_POST['imagem_capa_atual'] : '';  
    
    // Verificar imagem de capa  
    if (isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] === UPLOAD_ERR_OK) {  
        $imagem_capa = converterImagemParaBase64($_FILES['imagem_capa']);  
    } elseif ($remover_capa) {  
        $imagem_capa = '';  
    } else {  
        $imagem_capa = $imagem_capa_atual;  
    }  
    
    // Data atual  
    $data_atual = date('Y-m-d H:i:s');  
    
    // Se for uma atualização  
    if ($id > 0) {  
        $stmt = $conexao->prepare("  
            UPDATE manuais SET   
                titulo = ?,   
                descricao = ?,   
                categoria_id = ?,   
                versao = ?,   
                imagem_capa = ?,  
                data_atualizacao = ?  
            WHERE id = ?  
        ");  
        
        $stmt->execute([  
            $titulo,  
            $descricao,  
            $categoria_id,  
            $versao,  
            $imagem_capa,  
            $data_atual,  
            $id  
        ]);  
    }   
    // Se for um novo manual  
    else {  
        $stmt = $conexao->prepare("  
            INSERT INTO manuais (  
                titulo, descricao, categoria_id, versao, imagem_capa,   
                autor_id, data_criacao, data_atualizacao, visualizacoes, downloads  
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)  
        ");  
        
        // O autor_id seria obtido da sessão em um sistema real  
        $autor_id = 1; // Valor para exemplo  
        
        $stmt->execute([  
            $titulo,  
            $descricao,  
            $categoria_id,  
            $versao,  
            $imagem_capa,  
            $autor_id,  
            $data_atual,  
            $data_atual  
        ]);  
        
        $id = $conexao->lastInsertId();  
    }  
    
    // Processar os passos  
    if ($id > 0) {  
        // Arrays com os dados dos passos  
        $passo_ids = isset($_POST['passo_id']) ? $_POST['passo_id'] : [];  
        $passo_titulos = isset($_POST['passo_titulo']) ? $_POST['passo_titulo'] : [];  
        $passo_textos = isset($_POST['passo_texto']) ? $_POST['passo_texto'] : [];  
        $passo_legendas = isset($_POST['passo_legenda']) ? $_POST['passo_legenda'] : [];  
        $passo_imagens_atuais = isset($_POST['passo_imagem_atual']) ? $_POST['passo_imagem_atual'] : [];  
        $remover_imagens = isset($_POST['remover_imagem_passo']) ? $_POST['remover_imagem_passo'] : [];  
        
        // Obter os IDs de passos existentes para este manual  
        $stmt = $conexao->prepare("SELECT id FROM passos WHERE manual_id = ?");  
        $stmt->execute([$id]);  
        $passos_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);  
        $passos_atualizados = [];  
        
        // Processar cada passo  
        for ($i = 0; $i < count($passo_titulos); $i++) {  
            $passo_id = !empty($passo_ids[$i]) ? (int)$passo_ids[$i] : 0;  
            $passo_titulo = trim($passo_titulos[$i]);  
            $passo_texto = trim($passo_textos[$i]);  
            $passo_legenda = trim($passo_legendas[$i] ?? '');  
            $passo_imagem_atual = $passo_imagens_atuais[$i] ?? '';  
            $remover_imagem = isset($remover_imagens[$i]) && $remover_imagens[$i] == '1';  
            
            // Verificar imagem do passo  
            $passo_imagem = $passo_imagem_atual;  
            if (isset($_FILES['passo_imagem']['tmp_name'][$i]) && $_FILES['passo_imagem']['error'][$i] === UPLOAD_ERR_OK) {  
                // Criar um array para simular a estrutura esperada pela função  
                $arquivo = [  
                    'name' => $_FILES['passo_imagem']['name'][$i],  
                    'type' => $_FILES['passo_imagem']['type'][$i],  
                    'tmp_name' => $_FILES['passo_imagem']['tmp_name'][$i],  
                    'error' => $_FILES['passo_imagem']['error'][$i],  
                    'size' => $_FILES['passo_imagem']['size'][$i]  
                ];  
                
                $passo_imagem = converterImagemParaBase64($arquivo);  
            } elseif ($remover_imagem) {  
                $passo_imagem = '';  
            }  
            
            // Determinar o número do passo (posição)  
            $numero = $i + 1;  
            
            // Se for um passo existente, atualizar  
            if ($passo_id > 0) {  
                $stmt = $conexao->prepare("  
                    UPDATE passos SET   
                        numero = ?,   
                        titulo = ?,   
                        texto = ?,   
                        imagem = ?,   
                        legenda = ?  
                    WHERE id = ? AND manual_id = ?  
                ");  
                
                $stmt->execute([  
                    $numero,  
                    $passo_titulo,  
                    $passo_texto,  
                    $passo_imagem,  
                    $passo_legenda,  
                    $passo_id,  
                    $id  
                ]);  
                
                $passos_atualizados[] = $passo_id;  
            }   
            // Se for um novo passo, inserir  
            else {  
                $stmt = $conexao->prepare("  
                    INSERT INTO passos (  
                        manual_id, numero, titulo, texto, imagem, legenda  
                    ) VALUES (?, ?, ?, ?, ?, ?)  
                ");  
                
                $stmt->execute([  
                    $id,  
                    $numero,  
                    $passo_titulo,  
                    $passo_texto,  
                    $passo_imagem,  
                    $passo_legenda  
                ]);  
                
                $passos_atualizados[] = $conexao->lastInsertId();  
            }  
        }  
        
        // Remover passos que não estão mais presentes  
        foreach ($passos_existentes as $passo_existente) {  
            if (!in_array($passo_existente, $passos_atualizados)) {  
                $stmt = $conexao->prepare("DELETE FROM passos WHERE id = ?");  
                $stmt->execute([$passo_existente]);  
            }  
        }  
    }  
    
    // Commit da transação  
    $conexao->commit();  
    
    // Redirecionamento com sucesso  
    header("Location: manual-creator.php?id=$id&status=success");  
    exit;  
    
} catch (PDOException $e) {  
    // Reverter transação em caso de erro  
    $conexao->rollBack();  
    
    // Log do erro  
    error_log("Erro ao salvar manual: " . $e->getMessage());  
    
    // Redirecionamento com erro  
    if ($id > 0) {  
        header("Location: manual-creator.php?id=$id&status=error");  
    } else {  
        header("Location: manual-creator.php?status=error");  
    }  
    exit;  
}  
?>