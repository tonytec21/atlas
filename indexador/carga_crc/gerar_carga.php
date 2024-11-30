<?php
// Conexão com o banco de dados
require_once __DIR__ . '/db_connection.php';

// Obtenção do Número do CNJ (CNS) do cartório
$queryCNS = "SELECT cns FROM cadastro_serventia LIMIT 1";
$resultCNS = $conn->query($queryCNS);
$rowCNS = $resultCNS->fetch_assoc();
$numeroCNS = $rowCNS['cns'] ?? '000000'; // Padrão se não encontrar

// Função para formatar a data no padrão brasileiro (DD/MM/AAAA)
function formatarData($data) {
    return $data ? date('d/m/Y', strtotime($data)) : ''; // Retorna vazio se a data for nula
}

// Query para buscar os registros de nascimento
$query = "SELECT * FROM indexador_nascimento WHERE status = 'ativo'";
$result = $conn->query($query);

// Verificação se há registros
if ($result->num_rows > 0) {
    $nomeArquivo = 'carga_nascimento.txt';
    $arquivoCarga = fopen($nomeArquivo, 'w');

    while ($row = $result->fetch_assoc()) {
        // Montagem da linha no formato especificado
        $linha = implode(";", [
            "N", // Tipo de registro
            $numeroCNS, // Número do CNJ (CNS)
            $row['nome_registrado'], // Nome do registrado
            '', // CPF do registrado (não informado)
            $row['nome_pai'] ?? '', // Nome do pai
            $row['nome_mae'] ?? '', // Nome da mãe
            '', // CPF do pai (não informado)
            '', // CPF da mãe (não informado)
            $row['matricula'] ?? '', // Matrícula
            formatarData($row['data_nascimento']), // Data de nascimento formatada
            formatarData($row['data_registro']), // Data de registro formatada
            "I", // Código de ação (Inclusão)
            '', // Código do motivo
            '' // Data da averbação (não aplicável)
        ]);
        
        // Adiciona delimitador de registro
        $linha .= ";*";
        
        // Escreve a linha no arquivo
        fwrite($arquivoCarga, $linha . PHP_EOL);
    }

    fclose($arquivoCarga);

    // Configurações para download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    readfile($nomeArquivo);

    // Exclui o arquivo do servidor após download
    unlink($nomeArquivo);
    exit;
} else {
    echo "<script>alert('Nenhum registro encontrado para gerar a carga.'); window.history.back();</script>";
}

// Fechando a conexão
$conn->close();
?>
