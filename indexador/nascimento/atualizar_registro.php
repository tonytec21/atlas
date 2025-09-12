<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');

    // Obter o nome do funcionário da sessão
    session_start();
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado

    // Captura os dados enviados pelo formulário
    $id = $_POST['id'];
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $nome_registrado = mb_strtoupper(trim($_POST['nome_registrado']), 'UTF-8');
    $data_nascimento = $_POST['data_nascimento'];
    $nome_pai = mb_strtoupper(trim($_POST['nome_pai']), 'UTF-8');
    $nome_mae = mb_strtoupper(trim($_POST['nome_mae']), 'UTF-8');
    $data_registro = $_POST['data_registro'];
    $naturalidade = $_POST['naturalidade'];
    $ibge_naturalidade = $_POST['ibge_naturalidade'];
    $sexo = $_POST['sexo'];
    $status = 'ativo';

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

    // Obter o CNS da serventia
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
        $matricula = $matriculaBase . $digitoVerificador;
    } else {
        error_log("CNS não encontrado na tabela cadastro_serventia.");
        $matricula = null; // Caso não seja possível gerar a matrícula
    }

    // Atualizar registro no banco de dados
    $stmt = $conn->prepare("UPDATE indexador_nascimento SET termo = ?, livro = ?, folha = ?, nome_registrado = ?, data_nascimento = ?, nome_pai = ?, nome_mae = ?, data_registro = ?, naturalidade = ?, ibge_naturalidade = ?, matricula = ?, sexo = ? WHERE id = ?");
    $stmt->bind_param("ssssssssssssi", $termo, $livro, $folha, $nome_registrado, $data_nascimento, $nome_pai, $nome_mae, $data_registro, $naturalidade, $ibge_naturalidade, $matricula, $sexo, $id);

    if ($stmt->execute()) {
        // Processar anexo se enviado
        if (!empty($_FILES['arquivo_pdf']['name'])) {
            $dir = 'anexos/' . $id . '/';
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $arquivo_pdf = $dir . basename($_FILES['arquivo_pdf']['name']);
            if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf)) {
                // Atualizar anexo no banco de dados
                $stmt_anexo = $conn->prepare("INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
                $stmt_anexo->bind_param("isss", $id, $arquivo_pdf, $funcionario, $status);
                $stmt_anexo->execute();
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Registro atualizado com sucesso!', 'matricula' => $matricula]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar o registro: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
