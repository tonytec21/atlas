<?php
// Verificar se o número da nota foi fornecido  
if (!isset($_GET['numero']) || empty($_GET['numero'])) {  
    header('Location: listar_notas_devolutivas.php?erro=numero_nao_informado');  
    exit;  
}  

$numero = $_GET['numero'];  

// Buscar dados da nota devolutiva existente  
$query = "SELECT * FROM notas_devolutivas WHERE numero = ?";  
$stmt = $conn->prepare($query);  
$stmt->bind_param("s", $numero);  
$stmt->execute();  
$result = $stmt->get_result();  

if ($result->num_rows === 0) {  
    header('Location: listar_notas_devolutivas.php?erro=nota_nao_encontrada');  
    exit;  
}  

$nota = $result->fetch_assoc();  

// Registrar acesso à página de edição na tabela de logs - VERSÃO CORRIGIDA  
$usuario = $_SESSION['username'];
$log_query = "INSERT INTO logs_notas_devolutivas (  
    nota_id, numero, apresentante, cpf_cnpj, titulo, origem_titulo,   
    corpo, prazo_cumprimento, assinante, data, tratamento, protocolo,   
    data_protocolo, cargo_assinante, dados_complementares, status,   
    processo_referencia, created_at, data_atualizacao,   
    usuario_log, acao_log  
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACESSO_EDIÇÃO')";  

$log_stmt = $conn->prepare($log_query);  

// Corrigido para 21 parâmetros - 20 do banco de dados + 1 do usuário atual  
$log_stmt->bind_param(  
    "isssssssssssssssssss",  // 1i + 20s = 21 parâmetros  
    $nota['id'],   
    $nota['numero'],   
    $nota['apresentante'],   
    $nota['cpf_cnpj'],  
    $nota['titulo'],   
    $nota['origem_titulo'],   
    $nota['corpo'],   
    $nota['prazo_cumprimento'],  
    $nota['assinante'],   
    $nota['data'],   
    $nota['tratamento'],   
    $nota['protocolo'],  
    $nota['data_protocolo'],   
    $nota['cargo_assinante'],   
    $nota['dados_complementares'],  
    $nota['status'],   
    $nota['processo_referencia'],   
    $nota['created_at'],  
    $nota['data_atualizacao'],   
    $usuario  
);  

// Verificar se houve erro ao executar o log, mas não bloquear o fluxo  
if (!$log_stmt->execute()) {  
    // Apenas registre o erro, não interrompa o fluxo  
    error_log("Erro ao registrar log de acesso: " . $log_stmt->error);  
}  
$log_stmt->close(); 

// API proxy para consulta CNPJ  
if(isset($_GET['action']) && $_GET['action'] == 'consultar') {  
    header('Content-Type: application/json');  
    
    // Obter e limpar o documento  
    $documento = isset($_GET['documento']) ? preg_replace('/[^0-9]/', '', $_GET['documento']) : '';  
    
    if(empty($documento)) {  
        echo json_encode(['erro' => true, 'mensagem' => 'Documento não informado']);  
        exit;  
    }  
    
    // Validar se é um CNPJ (14 dígitos)  
    if(strlen($documento) != 14) {  
        echo json_encode(['erro' => true, 'mensagem' => 'Documento inválido. Apenas consulta de CNPJ é suportada.']);  
        exit;  
    }  
    
    try {  
        // CONSULTA DE CNPJ - Brasil API  
        $url = "https://brasilapi.com.br/api/cnpj/v1/{$documento}";  
        
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);  
        
        $response = curl_exec($ch);  
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
        $curl_error = curl_error($ch);  
        curl_close($ch);  
        
        if($curl_error) {  
            echo json_encode(['erro' => true, 'mensagem' => 'Erro de conexão: ' . $curl_error]);  
            exit;  
        }  
        
        if($http_code == 200) {  
            echo $response; // Retorna a resposta original da API  
        } else {  
            // Decodifica a resposta para verificar se há mensagem de erro  
            $respData = json_decode($response, true);  
            $mensagem = isset($respData['message']) ? $respData['message'] : 'CNPJ não encontrado ou erro na consulta.';  
            echo json_encode(['erro' => true, 'mensagem' => $mensagem, 'http_code' => $http_code]);  
        }  
    } catch (Exception $e) {  
        echo json_encode(['erro' => true, 'mensagem' => 'Erro interno: ' . $e->getMessage()]);  
    }  
    exit;  
}  
?>