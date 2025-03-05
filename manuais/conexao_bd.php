<?php  
/**  
 * Arquivo de conexão com o banco de dados  
 * Estabelece a conexão PDO e define funções auxiliares para o sistema  
 */  

// Evitar cache  
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");  
header("Cache-Control: post-check=0, pre-check=0", false);  
header("Pragma: no-cache");  

// Configurações do Banco de Dados  
$host = 'localhost';  
$banco = 'sistema_manuais';  
$usuario = 'root';  
$senha = '';  
$charset = 'utf8mb4';  

// Opções do PDO  
$opcoes = [  
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  
    PDO::ATTR_EMULATE_PREPARES => false,  
];  

// String de conexão  
$dsn = "mysql:host=$host;dbname=$banco;charset=$charset";  

// Estabelecer a conexão  
try {  
    $conexao = new PDO($dsn, $usuario, $senha, $opcoes);  
} catch (PDOException $e) {  
    // Registrar erro no log e exibir mensagem genérica  
    error_log("Erro de conexão: " . $e->getMessage());  
    die("Erro: Não foi possível conectar ao banco de dados.");  
}  

/**  
 * Converte imagem para base64  
 *   
 * @param array $arquivo Arquivo do $_FILES  
 * @return string String base64 ou string vazia em caso de erro  
 */  
function converterImagemParaBase64($arquivo) {  
    // Verifica se há arquivo e se é uma imagem válida  
    if (empty($arquivo) || $arquivo['error'] !== UPLOAD_ERR_OK) {  
        return '';  
    }  
    
    // Tipos de imagem permitidos  
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];  
    
    // Verificar o tipo do arquivo  
    if (!in_array($arquivo['type'], $tiposPermitidos)) {  
        return '';  
    }  
    
    // Limitar tamanho (5MB)  
    if ($arquivo['size'] > 5 * 1024 * 1024) {  
        return '';  
    }  
    
    // Ler o conteúdo do arquivo e converter para base64  
    $conteudo = file_get_contents($arquivo['tmp_name']);  
    if ($conteudo === false) {  
        return '';  
    }  
    
    // Criar string base64 com formato data URI  
    return 'data:' . $arquivo['type'] . ';base64,' . base64_encode($conteudo);  
}  

/**  
 * Sanitiza strings para prevenir XSS  
 *   
 * @param string $texto Texto a ser sanitizado  
 * @return string Texto sanitizado  
 */  
function sanitizar($texto) {  
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');  
}  

/**  
 * Registra erro no log e retorna mensagem de erro  
 *   
 * @param Exception $e Exceção capturada  
 * @return string Mensagem de erro  
 */  
function tratarErro($e) {  
    error_log("Erro: " . $e->getMessage());  
    return "Ocorreu um erro durante a operação. Por favor, tente novamente.";  
}  
?>