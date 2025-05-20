<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Ativar manipulador de erros personalizado para capturar avisos  
set_error_handler(function($errno, $errstr, $errfile, $errline) {  
    // Armazenar erros para incluir na resposta  
    global $errorMessages;  
    $errorMessages[] = "$errstr em $errfile:$errline";  
    return true; // Impede que o PHP mostre o erro  
});  

// Garantir que a saída seja apenas o JSON no final  
ob_start();  

// Adicionar o PhpSpreadsheet via Composer  
require '../vendor/autoload.php';  

use PhpOffice\PhpSpreadsheet\IOFactory;  

$errorMessages = [];  
$result = [  
    'status' => 'error',  
    'message' => 'Nenhum arquivo foi processado.'  
];  

try {  
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {  
        $arquivos = $_FILES['arquivos'];  
        
        foreach ($arquivos['tmp_name'] as $index => $tmpName) {  
            // Verificar tipos de arquivo Excel  
            $allowedMimeTypes = [  
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',   
                'application/vnd.ms-excel'  
            ];  
            
            // Verificar extensão do arquivo também, já que o MIME pode não ser confiável  
            $fileExtension = strtolower(pathinfo($arquivos['name'][$index], PATHINFO_EXTENSION));  
            $validExtension = in_array($fileExtension, ['xlsx', 'xls']);  
            
            if ($validExtension) {  
                try {  
                    // Carregar o arquivo Excel com PhpSpreadsheet  
                    $spreadsheet = IOFactory::load($tmpName);  
                    $worksheet = $spreadsheet->getActiveSheet();  
                    $linhasProcessadas = 0;  

                    // Iterar pelas linhas da planilha  
                    foreach ($worksheet->getRowIterator() as $row) {  
                        // Pular a primeira linha se for cabeçalho  
                        if ($row->getRowIndex() == 1) {  
                            continue;  
                        }  
                        
                        $cellIterator = $row->getCellIterator();  
                        $cellIterator->setIterateOnlyExistingCells(false);  
                        
                        $dados = [];  
                        foreach ($cellIterator as $cell) {  
                            $dados[] = $cell->getValue();  
                        }  

                        // Verificar se temos dados suficientes  
                        if (count($dados) < 4 || empty($dados[0])) {  
                            continue;  
                        }  

                        $termo = $dados[0];  
                        $nome_registrado = $dados[1];  
                        $livro = $dados[2];  
                        $folha = $dados[3];  
                        $data_registro = '0000-00-00';  
                        $data_nascimento = '0000-00-00';  
                        $data_obito = '0000-00-00';  
                        $hora_obito = '00:00:00';  
                        $nome_pai = NULL;  
                        $nome_mae = NULL;  
                        $funcionario = $_SESSION['usuario_nome'] ?? 'Sistema';  
                        $status = 'A';  
                        $matricula = NULL;  
                        $cidade_endereco = NULL;  
                        $ibge_cidade_endereco = NULL;  
                        $cidade_obito = NULL;  
                        $ibge_cidade_obito = NULL;  

                        $sql = "INSERT INTO indexador_obito   
                                (termo, livro, folha, data_registro, data_nascimento, data_obito, hora_obito,   
                                nome_registrado, nome_pai, nome_mae, funcionario, status, matricula,   
                                cidade_endereco, ibge_cidade_endereco, cidade_obito, ibge_cidade_obito)   
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";  

                        $stmt = $conn->prepare($sql);  
                        $stmt->bind_param(  
                            'sssssssssssssssss',  
                            $termo,  
                            $livro,  
                            $folha,  
                            $data_registro,  
                            $data_nascimento,  
                            $data_obito,  
                            $hora_obito,  
                            $nome_registrado,  
                            $nome_pai,  
                            $nome_mae,  
                            $funcionario,  
                            $status,  
                            $matricula,  
                            $cidade_endereco,  
                            $ibge_cidade_endereco,  
                            $cidade_obito,  
                            $ibge_cidade_obito  
                        );  

                        if ($stmt->execute()) {  
                            $linhasProcessadas++;  
                        }  
                    }  

                    $result = [  
                        'status' => 'success',  
                        'message' => "$linhasProcessadas linhas processadas com sucesso no arquivo '{$arquivos['name'][$index]}'."  
                    ];  
                } catch (Exception $e) {  
                    $result = [  
                        'status' => 'error',  
                        'message' => "Erro ao processar o arquivo '{$arquivos['name'][$index]}': " . $e->getMessage()  
                    ];  
                }  
            } else {  
                $result = [  
                    'status' => 'error',  
                    'message' => "O arquivo '{$arquivos['name'][$index]}' não é um arquivo Excel válido."  
                ];  
            }  
        }  
    }  
} catch (Exception $e) {  
    $result = [  
        'status' => 'error',  
        'message' => 'Erro ao processar os arquivos: ' . $e->getMessage()  
    ];  
}   

// Limpar qualquer saída pendente  
ob_end_clean();  

// Adicionar quaisquer mensagens de erro no resultado  
if (!empty($errorMessages)) {  
    $result['errors'] = $errorMessages;  
}  

// Restaurar o manipulador de erros padrão  
restore_error_handler();  

// Garantir que os cabeçalhos estejam corretos para JSON  
header('Content-Type: application/json');  
echo json_encode($result);  
exit;  
?>