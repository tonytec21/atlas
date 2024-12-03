<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include(__DIR__ . '/db_connection.php');

    // Desativar exibição de erros no navegador e habilitar log de erros
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error_log.txt');

    // Função para calcular os dígitos verificadores da matrícula
    function calcularDigitoVerificador($matriculaBase) {
        $multiplicadorFase1 = 32; // Peso inicial
        $soma = 0;

        for ($i = 0; $i < 30; $i++) {
            $multiplicadorFase1--;
            $soma += intval($matriculaBase[$i]) * $multiplicadorFase1;
        }

        $digito1 = ($soma * 10) % 11;
        $digito1 = ($digito1 == 10) ? 1 : $digito1;

        $multiplicadorFase2 = 33; // Peso inicial
        $soma2 = 0;

        for ($j = 0; $j < 30; $j++) {
            $multiplicadorFase2--;
            $soma2 += intval($matriculaBase[$j]) * $multiplicadorFase2;
        }

        $soma2 += $digito1 * 2; // Adiciona impacto do primeiro dígito
        $digito2 = ($soma2 * 10) % 11;
        $digito2 = ($digito2 == 10) ? 1 : $digito2;

        return $digito1 . $digito2;
    }

    // Obter o nome do funcionário da sessão
    session_start();
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado

    // Captura os dados sem fazer qualquer conversão de caracteres especiais
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $data_registro = $_POST['data_registro'];
    $data_nascimento = $_POST['data_nascimento'];
    $nome_registrado = mb_strtoupper(trim($_POST['nome_registrado']), 'UTF-8');
    $naturalidade = $_POST['naturalidade'];
    $ibge_naturalidade = $_POST['ibge_naturalidade'];
    $sexo = $_POST['sexo'];
    $nome_pai = mb_strtoupper(trim($_POST['nome_pai']), 'UTF-8');
    $nome_mae = mb_strtoupper(trim($_POST['nome_mae']), 'UTF-8');
    $status = 'ativo';

    // Verificar se já existe um registro com o mesmo termo, livro, folha e data de registro
    $stmt = $conn->prepare("SELECT nome_registrado FROM indexador_nascimento WHERE termo = ? AND livro = ? AND folha = ? AND data_registro = ? AND status = 'ativo'");
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
    $stmt = $conn->prepare("INSERT INTO indexador_nascimento (termo, livro, folha, data_registro, data_nascimento, nome_registrado, nome_pai, nome_mae, naturalidade, ibge_naturalidade, funcionario, status, sexo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssss", $termo, $livro, $folha, $data_registro, $data_nascimento, $nome_registrado, $nome_pai, $nome_mae, $naturalidade, $ibge_naturalidade, $funcionario, $status, $sexo);

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;

        // Gerar matrícula para o novo registro
        $cnsQuery = "SELECT cns FROM cadastro_serventia LIMIT 1";
        $cnsResult = $conn->query($cnsQuery);

        if ($cnsResult && $cnsResult->num_rows > 0) {
            $cnsRow = $cnsResult->fetch_assoc();
            $cns = str_pad($cnsRow['cns'], 6, "0", STR_PAD_LEFT); // Garante 6 dígitos

            // Monta os dados para a matrícula
            $livroFormatado = str_pad($livro, 5, "0", STR_PAD_LEFT);
            $folhaFormatada = str_pad($folha, 3, "0", STR_PAD_LEFT);
            $termoFormatado = str_pad($termo, 7, "0", STR_PAD_LEFT);
            $dataRegistroAno = explode("-", $data_registro)[0]; // Ano do registro
            $tipoLivro = '1'; // Padrão para nascimento
            $acervo = '01'; // Acervo próprio

            // Concatena os dados para formar a matrícula base
            $matriculaBase = $cns . $acervo . '55' . $dataRegistroAno . $tipoLivro . $livroFormatado . $folhaFormatada . $termoFormatado;

            // Calcula os dígitos verificadores
            $digitoVerificador = calcularDigitoVerificador($matriculaBase);

            // Forma a matrícula final
            $matriculaFinal = $matriculaBase . $digitoVerificador;

            // Atualiza a matrícula no banco
            $updateQuery = "UPDATE indexador_nascimento SET matricula = '$matriculaFinal' WHERE id = $last_id";
            if (!$conn->query($updateQuery)) {
                error_log("Erro ao atualizar matrícula para o ID $last_id: " . $conn->error);
            }
        } else {
            error_log("CNS não encontrado na tabela cadastro_serventia.");
        }

        // Mover anexos temporários para o diretório final e salvar no banco de dados
        if (!empty($_POST['arquivo_pdf_paths'])) {
            foreach ($_POST['arquivo_pdf_paths'] as $temp_file_path) {
                $dir = 'anexos/' . $last_id . '/';
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $file_name = basename($temp_file_path);
                $final_file_path = $dir . $file_name;
                if (rename($temp_file_path, $final_file_path)) {
                    $stmt_anexo = $conn->prepare("INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
                    $stmt_anexo->bind_param("isss", $last_id, $final_file_path, $funcionario, $status);
                    $stmt_anexo->execute();
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Registro salvo com sucesso!', 'matricula' => $matriculaFinal]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o registro: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
