<?php  
if ($_SERVER['REQUEST_METHOD'] == 'POST') {  
    include(__DIR__ . '/db_connection.php');  
    date_default_timezone_set('America/Sao_Paulo');

    // Desativar exibição de erros no navegador e habilitar log de erros  
    ini_set('display_errors', 0);  
    ini_set('log_errors', 1);  
    ini_set('error_log', __DIR__ . '/error_log.txt');  

    function calcularDigitoVerificador($matriculaBase) {  
        $multiplicadorFase1 = 32;  
        $soma = 0;  

        for ($i = 0; $i < 30; $i++) {  
            $multiplicadorFase1--;  
            $soma += intval($matriculaBase[$i]) * $multiplicadorFase1;  
        }  

        $digito1 = ($soma * 10) % 11;  
        $digito1 = ($digito1 == 10) ? 1 : $digito1;  

        $multiplicadorFase2 = 33;  
        $soma2 = 0;  

        for ($j = 0; $j < 30; $j++) {  
            $multiplicadorFase2--;  
            $soma2 += intval($matriculaBase[$j]) * $multiplicadorFase2;  
        }  

        $soma2 += $digito1 * 2;  
        $digito2 = ($soma2 * 10) % 11;  
        $digito2 = ($digito2 == 10) ? 1 : $digito2;  

        return $digito1 . $digito2;  
    }  

    session_start();  
    $funcionario = $_SESSION['username'];  

    $termo = $_POST['termo'];  
    $livro = $_POST['livro'];  
    $folha = $_POST['folha'];  
    $data_registro = $_POST['data_registro'];  
    $data_nascimento = $_POST['data_nascimento'];  
    $data_obito = $_POST['data_obito'];  
    $hora_obito = $_POST['hora_obito'];  
    $nome_registrado = mb_strtoupper(trim($_POST['nome_registrado']), 'UTF-8');  
    $nome_pai = mb_strtoupper(trim($_POST['nome_pai']), 'UTF-8');  
    $nome_mae = mb_strtoupper(trim($_POST['nome_mae']), 'UTF-8');  
    $cidade_endereco = $_POST['cidade_endereco'];  
    $ibge_cidade_endereco = $_POST['ibge_cidade_endereco'];  
    $cidade_obito = $_POST['cidade_obito'];  
    $ibge_cidade_obito = $_POST['ibge_cidade_obito'];  
    $status = 'A';  

    // Verificar duplicatas  
    $stmt = $conn->prepare("SELECT nome_registrado FROM indexador_obito WHERE termo = ? AND livro = ? AND folha = ? AND data_registro = ? AND status = 'A'");  
    $stmt->bind_param("ssss", $termo, $livro, $folha, $data_registro);  
    $stmt->execute();  
    $stmt->store_result();  

    if ($stmt->num_rows > 0) {  
        $stmt->bind_result($nome_registrado_existente);  
        $stmt->fetch();  
        echo json_encode([  
            'status' => 'duplicate',  
            'message' => 'Já existe um registro com o mesmo livro, folha, termo e data de registro.',  
            'nome_registrado' => $nome_registrado_existente  
        ]);  
        exit;  
    }  
    $stmt->close();  

    // Inserir registro no banco de dados  
    $stmt = $conn->prepare("INSERT INTO indexador_obito (  
        termo, livro, folha, data_registro, data_nascimento,  
        data_obito, hora_obito, nome_registrado, nome_pai, nome_mae,  
        cidade_endereco, ibge_cidade_endereco, cidade_obito,  
        ibge_cidade_obito, funcionario, status  
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");  

    $stmt->bind_param("ssssssssssssssss",  
        $termo, $livro, $folha, $data_registro, $data_nascimento,  
        $data_obito, $hora_obito, $nome_registrado, $nome_pai, $nome_mae,  
        $cidade_endereco, $ibge_cidade_endereco, $cidade_obito,  
        $ibge_cidade_obito, $funcionario, $status  
    );  

    if ($stmt->execute()) {  
        $last_id = $stmt->insert_id;  

        // Gerar matrícula  
        $cnsQuery = "SELECT cns FROM cadastro_serventia LIMIT 1";  
        $cnsResult = $conn->query($cnsQuery);  

        if ($cnsResult && $cnsResult->num_rows > 0) {  
            $cnsRow = $cnsResult->fetch_assoc();  
            $cns = str_pad($cnsRow['cns'], 6, "0", STR_PAD_LEFT);  

            $livroFormatado = str_pad($livro, 5, "0", STR_PAD_LEFT);  
            $folhaFormatada = str_pad($folha, 3, "0", STR_PAD_LEFT);  
            $termoFormatado = str_pad($termo, 7, "0", STR_PAD_LEFT);  
            $dataRegistroAno = explode("-", $data_registro)[0];  
            $tipoLivro = '4';  
            $acervo = '01';  

            $matriculaBase = $cns . $acervo . '55' . $dataRegistroAno . $tipoLivro . $livroFormatado . $folhaFormatada . $termoFormatado;  
            $digitoVerificador = calcularDigitoVerificador($matriculaBase);  
            $matriculaFinal = $matriculaBase . $digitoVerificador;  

            $updateQuery = "UPDATE indexador_obito SET matricula = '$matriculaFinal' WHERE id = $last_id";  
            if (!$conn->query($updateQuery)) {  
                error_log("Erro ao atualizar matrícula para o ID $last_id: " . $conn->error);  
            }  
        } else {  
            error_log("CNS não encontrado na tabela cadastro_serventia.");  
        }  

        // Processar anexos  
        if (!empty($_FILES['anexos']['name'][0])) {  
            $uploadDir = 'anexos/obitos/' . $last_id . '/';  
            
            // Criar diretório se não existir  
            if (!file_exists($uploadDir)) {  
                mkdir($uploadDir, 0777, true);  
            }  

            // Preparar statement para inserção dos anexos  
            $stmt_anexo = $conn->prepare("INSERT INTO indexador_obito_anexos (id_obito, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");  

            foreach ($_FILES['anexos']['tmp_name'] as $key => $tmp_name) {  
                if ($_FILES['anexos']['error'][$key] === UPLOAD_ERR_OK) {  
                    $fileName = $_FILES['anexos']['name'][$key];  
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));  
                    
                    // Verificar se é um arquivo PDF  
                    if ($fileExt === 'pdf') {  
                        // Gerar nome único para o arquivo  
                        $uniqueName = uniqid() . '_' . $fileName;  
                        $finalPath = $uploadDir . $uniqueName;  

                        // Mover arquivo para diretório final  
                        if (move_uploaded_file($tmp_name, $finalPath)) {  
                            // Salvar informações do anexo no banco  
                            $stmt_anexo->bind_param("isss", $last_id, $finalPath, $funcionario, $status);  
                            if (!$stmt_anexo->execute()) {  
                                error_log("Erro ao salvar anexo no banco: " . $stmt_anexo->error);  
                            }  
                        } else {  
                            error_log("Erro ao mover arquivo: " . $fileName);  
                        }  
                    } else {  
                        error_log("Tipo de arquivo inválido: " . $fileName);  
                    }  
                }  
            }  
            $stmt_anexo->close();  
        }  

        echo json_encode([  
            'status' => 'success',  
            'message' => 'Registro de óbito salvo com sucesso!',  
            'matricula' => $matriculaFinal  
        ]);  
    } else {  
        echo json_encode([  
            'status' => 'error',  
            'message' => 'Erro ao salvar o registro de óbito: ' . $stmt->error  
        ]);  
    }  

    $stmt->close();  
    $conn->close();  
}  
?>