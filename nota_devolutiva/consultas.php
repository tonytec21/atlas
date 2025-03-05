<?php
// Função para obter o próximo número de nota devolutiva  
function getNextNotaNumber($conn) {  
    $currentYear = date('Y');  
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero, '/', 1) AS UNSIGNED)) AS max_numero FROM notas_devolutivas WHERE YEAR(data) = $currentYear");  
    $row = $result->fetch_assoc();  
    $lastNumero = $row['max_numero'];  

    if ($lastNumero) {  
        $nextSequence = (int)$lastNumero + 1;  
    } else {  
        $nextSequence = 1;  
    }  

    return $nextSequence . '/' . $currentYear;  
}  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $numero = getNextNotaNumber($conn);  
    $apresentante = $conn->real_escape_string($_POST['apresentante']);  
    $cpf_cnpj = $conn->real_escape_string($_POST['cpf_cnpj']);  
    $titulo = $conn->real_escape_string($_POST['titulo']);  
    $origem_titulo = $conn->real_escape_string($_POST['origem_titulo']);  
    $corpo = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['corpo'])));  
    $prazo_cumprimento = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['prazo_cumprimento'])));  
    $assinante = $conn->real_escape_string($_POST['assinante']);  
    $data = $conn->real_escape_string($_POST['data']);  
    $tratamento = ''; // Campo removido, definindo valor vazio  
    $protocolo = $conn->real_escape_string($_POST['protocolo']);  
    $data_protocolo = $conn->real_escape_string($_POST['data_protocolo']);  
    $cargo_assinante = $conn->real_escape_string($_POST['cargo_assinante']);  
    $dados_complementares = $conn->real_escape_string($_POST['dados_complementares']);  
    $processo_referencia = ''; // Campo removido, definindo valor vazio  

    // Verificar se as colunas existem na tabela antes de inserir  
    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'cpf_cnpj'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN cpf_cnpj VARCHAR(20) AFTER apresentante");  
    }  
    
    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'data_protocolo'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN data_protocolo DATE AFTER protocolo");  
    }  
    
    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'origem_titulo'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN origem_titulo VARCHAR(200) AFTER titulo");  
    }  
    
    // Verificar se a coluna prazo_cumprimento existe na tabela  
    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'prazo_cumprimento'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN prazo_cumprimento TEXT AFTER corpo");  
    }  

    // Preparar a consulta SQL incluindo os novos campos  
    $stmt = $conn->prepare("INSERT INTO notas_devolutivas (apresentante, cpf_cnpj, titulo, origem_titulo, corpo, prazo_cumprimento, assinante, data, numero, tratamento, protocolo, data_protocolo, cargo_assinante, dados_complementares, processo_referencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");  
    $stmt->bind_param("sssssssssssssss", $apresentante, $cpf_cnpj, $titulo, $origem_titulo, $corpo, $prazo_cumprimento, $assinante, $data, $numero, $tratamento, $protocolo, $data_protocolo, $cargo_assinante, $dados_complementares, $processo_referencia);  
    $stmt->execute();  

    $stmt->close();  

    // A saída precisa estar em conformidade com o DOM e JavaScript  
    echo "<script src='../script/sweetalert2.js'></script>  
          <script>  
              document.addEventListener('DOMContentLoaded', function() {  
                  Swal.fire({  
                      icon: 'success',  
                      title: 'Nota Devolutiva salva com sucesso!',  
                      showConfirmButton: true,  
                      confirmButtonText: 'OK'  
                  }).then((result) => {  
                      if (result.isConfirmed) {  
                          window.location.href = 'index.php';  
                      }  
                  });  
              });  
          </script>";  
}  

// Buscar funcionários do banco de dados  
$sql = "SELECT id, nome_completo, cargo FROM funcionarios WHERE status = 'ativo'";  
$result = $conn->query($sql);  
$employees = [];  
while ($row = $result->fetch_assoc()) {  
    $employees[] = $row;  
}  

// Usuário logado  
$loggedUser = $_SESSION['username'];  

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