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
                        $nome_pai = NULL;
                        $nome_mae = NULL;
                        $funcionario = $_SESSION['usuario_nome'] ?? 'Sistema';
                        $matricula = NULL;
                        $naturalidade = NULL;
                        $ibge_naturalidade = NULL;
                        $sexo = NULL;
                        $status = 'ativo';

                        $sql = "INSERT INTO indexador_nascimento 
                                (termo, livro, folha, data_registro, data_nascimento, nome_registrado, nome_pai, nome_mae, funcionario, matricula, naturalidade, ibge_naturalidade, sexo, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param(
                            'ssssssssssssss',
                            $termo,
                            $livro,
                            $folha,
                            $data_registro,
                            $data_nascimento,
                            $nome_registrado,
                            $nome_pai,
                            $nome_mae,
                            $funcionario,
                            $matricula,
                            $naturalidade,
                            $ibge_naturalidade,
                            $sexo,
                            $status
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
