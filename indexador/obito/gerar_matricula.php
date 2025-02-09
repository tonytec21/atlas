<?php
// Inclui o arquivo de conexão com o banco de dados
require_once 'db_connection.php';

// Função para calcular os dígitos verificadores
function calcularDigitoVerificador($matriculaBase) {
    # Cálculo do Primeiro Dígito #
    $multiplicadorFase1 = 32; // Peso inicial
    $soma = 0;

    for ($i = 0; $i < 30; $i++) {
        $multiplicadorFase1--;
        $soma += intval($matriculaBase[$i]) * $multiplicadorFase1;
    }

    $digito1 = ($soma * 10) % 11;
    $digito1 = ($digito1 == 10) ? 1 : $digito1;

    # Cálculo do Segundo Dígito #
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

// Obtém o CNS da tabela cadastro_serventia
$cnsQuery = "SELECT cns FROM cadastro_serventia LIMIT 1";
$cnsResult = $conn->query($cnsQuery);

if ($cnsResult && $cnsResult->num_rows > 0) {
    $cnsRow = $cnsResult->fetch_assoc();
    $cns = str_pad($cnsRow['cns'], 6, "0", STR_PAD_LEFT); // Garante 6 dígitos
} else {
    die("CNS não encontrado na tabela cadastro_serventia.");
}

// Consulta para obter registros de nascimento
$query = "
    SELECT id, termo, livro, folha, data_registro
    FROM indexador_obito
    WHERE status = 'A'
";

$result = $conn->query($query);

// Verifica se existem registros
if ($result && $result->num_rows > 0) {
    echo "<h1>Matrículas Geradas</h1>";
    echo "<table border='1'>
            <tr>
                <th>ID</th>
                <th>Matrícula</th>
            </tr>";

    while ($row = $result->fetch_assoc()) {
        // Monta os dados para a matrícula
        $livro = str_pad($row['livro'], 5, "0", STR_PAD_LEFT);
        $folha = str_pad($row['folha'], 3, "0", STR_PAD_LEFT);
        $termo = str_pad($row['termo'], 7, "0", STR_PAD_LEFT);
        $dataRegistro = explode("-", $row['data_registro'])[0]; // Ano do registro
        $tipoLivro = '4'; // Padrão para nascimento
        $acervo = '01'; // Acervo próprio

        // Concatena os dados para formar a matrícula base
        $matriculaBase = $cns . $acervo . '55' . $dataRegistro . $tipoLivro . $livro . $folha . $termo;

        // Calcula os dígitos verificadores
        $digitoVerificador = calcularDigitoVerificador($matriculaBase);

        // Forma a matrícula final
        $matriculaFinal = $matriculaBase . $digitoVerificador;

        // Atualiza a matrícula na tabela
        $updateQuery = "UPDATE indexador_obito SET matricula = '$matriculaFinal' WHERE id = {$row['id']}";
        if ($conn->query($updateQuery) === TRUE) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>$matriculaFinal</td>
                  </tr>";
        } else {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>Erro ao salvar matrícula</td>
                  </tr>";
        }
    }
    echo "</table>";
} else {
    echo "Nenhum registro encontrado.";
}

// Fecha a conexão com o banco de dados
$conn->close();
?>
