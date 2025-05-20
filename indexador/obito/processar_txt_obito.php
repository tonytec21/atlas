<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {  
    $arquivos = $_FILES['arquivos'];  
    $result = [  
        'status' => 'error',  
        'message' => 'Nenhum arquivo foi processado.'  
    ];  

    try {  
        foreach ($arquivos['tmp_name'] as $index => $tmpName) {  
            if ($arquivos['type'][$index] === 'text/plain') {  
                $file = fopen($tmpName, 'r');  
                if ($file) {  
                    $linhasProcessadas = 0;  

                    while (($linha = fgets($file)) !== false) {  
                        $dados = explode(';', trim($linha));  

                        if (count($dados) < 4) {  
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

                    fclose($file);  

                    $result = [  
                        'status' => 'success',  
                        'message' => "$linhasProcessadas linhas processadas com sucesso no arquivo '{$arquivos['name'][$index]}'."  
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

    echo json_encode($result);  
    exit;  
}  
?>