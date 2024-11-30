<?php
require_once __DIR__ . '/db_connection.php';

$queryCNS = "SELECT cns FROM cadastro_serventia LIMIT 1";
$resultCNS = $conn->query($queryCNS);
$rowCNS = $resultCNS->fetch_assoc();
$numeroCNS = $rowCNS['cns'] ?? '000000';

function formatarData($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '';
}

$query = "SELECT * FROM indexador_nascimento WHERE status = 'ativo'";
$result = $conn->query($query);

// Verificação se há registros
if ($result->num_rows > 0) {
    $nomeArquivo = 'carga_nascimento.txt';
    $arquivoCarga = fopen($nomeArquivo, 'w');

    while ($row = $result->fetch_assoc()) {
        $linha = implode(";", [
            "N", // Tipo de registro
            $numeroCNS, // Número do CNS
            $row['nome_registrado'], // Nome do registrado
            '', // CPF do registrado (não informado)
            $row['nome_pai'] ?? '', // Nome do pai
            $row['nome_mae'] ?? '', // Nome da mãe
            '', // CPF do pai (não informado)
            '', // CPF da mãe (não informado)
            $row['matricula'] ?? '', // Matrícula
            formatarData($row['data_nascimento']), // Data de nascimento
            formatarData($row['data_registro']), // Data de registro
            "I", // Código de ação (Inclusão)
            '', // Código do motivo da modificação
            '' // Data da averbação (não aplicável)
        ]);
        
        // Delimitador de registro
        $linha .= ";*";
        
        fwrite($arquivoCarga, $linha . PHP_EOL);
    }

    fclose($arquivoCarga);

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    readfile($nomeArquivo);

    // Exclui o arquivo do servidor após download
    unlink($nomeArquivo);
    exit;
} else {
    echo "<script>alert('Nenhum registro encontrado para gerar a carga.'); window.history.back();</script>";
}

$conn->close();
?>
