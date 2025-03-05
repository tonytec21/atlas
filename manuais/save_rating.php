<?php  
// Iniciar buffer de saída para capturar qualquer saída indevida  
ob_start();  

// Iniciar sessão se ainda não estiver iniciada  
if (session_status() === PHP_SESSION_NONE) {  
    session_start();  
}  

// Configurar para exibir erros durante debugging  
// error_reporting(E_ALL);  
// ini_set('display_errors', 1);  

// Função para responder em JSON e encerrar o script  
function responderJSON($dados) {  
    // Limpar qualquer saída anterior  
    if (ob_get_length()) ob_clean();  
    
    // Configurar cabeçalho para JSON  
    header('Content-Type: application/json');  
    
    // Enviar resposta  
    echo json_encode($dados);  
    exit;  
}  

// Verificar se o usuário está logado  
if (!isset($_SESSION['username'])) {  
    responderJSON([  
        'success' => false,  
        'message' => 'Você precisa estar logado para avaliar um manual.'  
    ]);  
}  

// Verificar se a requisição é do tipo POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  
    responderJSON([  
        'success' => false,  
        'message' => 'Método de requisição inválido.'  
    ]);  
}  

// Obter e decodificar os dados JSON enviados  
$inputJSON = file_get_contents('php://input');  
$input = json_decode($inputJSON, true);  

// Verificar se os dados necessários foram enviados  
if (!isset($input['manual_id']) || !isset($input['rating'])) {  
    responderJSON([  
        'success' => false,  
        'message' => 'Dados incompletos.'  
    ]);  
}  

// Validar classificação  
$rating = intval($input['rating']);  
if ($rating < 1 || $rating > 5) {  
    responderJSON([  
        'success' => false,  
        'message' => 'A classificação deve ser entre 1 e 5 estrelas.'  
    ]);  
}  

try {  
    // Incluir os arquivos de conexão  
    require_once 'db_connection.php';  
    require_once 'conexao_bd.php';  
    
    // Obter conexão para o banco 'atlas' usando a função do arquivo  
    $conn = getDatabaseConnection();  
    
    // Verificar se as conexões estão disponíveis  
    if (!isset($conn) || !isset($conexao)) {  
        throw new Exception("Conexões com o banco de dados não estão disponíveis");  
    }  
    
    // Obter o ID do usuário usando a conexão com o banco 'atlas'  
    $username = $_SESSION['username'];  
    
    $stmt = $conn->prepare("SELECT id FROM funcionarios WHERE usuario = ?");  
    $stmt->execute([$username]);  
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    if (!$usuario) {  
        responderJSON([  
            'success' => false,  
            'message' => 'Usuário não encontrado no sistema.'  
        ]);  
    }  
    
    $usuario_id = $usuario['id'];  
    
    // Verificar se o manual existe  
    $manual_id = $input['manual_id'];  
    $stmt = $conexao->prepare("SELECT id FROM manuais WHERE id = ?");  
    $stmt->execute([$manual_id]);  
    
    if (!$stmt->fetch()) {  
        responderJSON([  
            'success' => false,  
            'message' => 'Manual não encontrado.'  
        ]);  
    }  
    
    // Verificar se a tabela de avaliações existe  
    try {  
        $conexao->query("SELECT 1 FROM avaliacoes LIMIT 1");  
    } catch (PDOException $e) {  
        // Criar tabela se não existir  
        $sql = "CREATE TABLE IF NOT EXISTS avaliacoes (  
            id INT AUTO_INCREMENT PRIMARY KEY,  
            manual_id INT NOT NULL,  
            usuario_id INT NOT NULL,  
            classificacao INT NOT NULL,  
            comentario TEXT,  
            data_criacao DATETIME NOT NULL,  
            data_atualizacao DATETIME NULL  
        )";  
        $conexao->exec($sql);  
    }  
    
    // Verificar se o usuário já avaliou este manual  
    $stmt = $conexao->prepare("  
        SELECT id FROM avaliacoes   
        WHERE manual_id = ? AND usuario_id = ?  
    ");  
    $stmt->execute([$manual_id, $usuario_id]);  
    $existingRating = $stmt->fetch();  
    
    if ($existingRating) {  
        // Atualizar avaliação existente  
        $stmt = $conexao->prepare("  
            UPDATE avaliacoes   
            SET classificacao = ?, comentario = ?, data_atualizacao = NOW()   
            WHERE manual_id = ? AND usuario_id = ?  
        ");  
        $stmt->execute([  
            $rating,  
            $input['comentario'] ?? '',  
            $manual_id,  
            $usuario_id  
        ]);  
        
        $message = 'Avaliação atualizada com sucesso!';  
    } else {  
        // Inserir nova avaliação  
        $stmt = $conexao->prepare("  
            INSERT INTO avaliacoes (manual_id, usuario_id, classificacao, comentario, data_criacao)   
            VALUES (?, ?, ?, ?, NOW())  
        ");  
        $stmt->execute([  
            $manual_id,  
            $usuario_id,  
            $rating,  
            $input['comentario'] ?? ''  
        ]);  
        
        $message = 'Avaliação enviada com sucesso!';  
    }  
    
    // Verificar se a coluna classificacao existe na tabela manuais  
    try {  
        $conexao->query("SELECT classificacao FROM manuais LIMIT 1");  
        
        // Atualizar a classificação média do manual  
        $stmt = $conexao->prepare("  
            UPDATE manuais SET classificacao = (  
                SELECT AVG(classificacao)   
                FROM avaliacoes   
                WHERE manual_id = ?  
            )   
            WHERE id = ?  
        ");  
        $stmt->execute([$manual_id, $manual_id]);  
    } catch (PDOException $e) {  
        // A coluna não existe - podemos criar, mas não é essencial para a funcionalidade básica  
        // de salvar avaliações  
    }  
    
    // Responder com sucesso  
    responderJSON([  
        'success' => true,  
        'message' => $message  
    ]);  
    
} catch (Exception $e) {  
    // Registrar erro detalhado no arquivo de log  
    $errorMessage = "Erro ao salvar avaliação: " . $e->getMessage();  
    error_log($errorMessage);  
    
    // Retornar erro com detalhes  
    responderJSON([  
        'success' => false,  
        'message' => 'Erro: ' . $e->getMessage()  
    ]);  
}  

// Se chegou aqui, algo deu errado  
responderJSON([  
    'success' => false,  
    'message' => 'Ocorreu um erro inesperado.'  
]);  
?>