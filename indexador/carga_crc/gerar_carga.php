<?php
require_once __DIR__ . '/db_connection.php';

// Obtenção do CNS
$queryCNS = "SELECT cns FROM cadastro_serventia LIMIT 1";
$resultCNS = $conn->query($queryCNS);
$rowCNS = $resultCNS->fetch_assoc();
$numeroCNS = $rowCNS['cns'] ?? '000000';

// Função para formatar a data no padrão brasileiro
function formatarData($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '';
}

// Verifica se os IDs selecionados foram enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    // Obtém os IDs selecionados
    $ids = implode(',', array_map('intval', $_POST['selected_ids']));

    // Query para buscar os registros selecionados
    $query = "SELECT * FROM indexador_nascimento WHERE id IN ($ids) AND status = 'ativo'";
    $result = $conn->query($query);

    // Verificação se há registros
    if ($result->num_rows > 0) {
        $nomeArquivo = 'carga_nascimento.txt';
        $arquivoCarga = fopen($nomeArquivo, 'w');

        while ($row = $result->fetch_assoc()) {
            // Monta a linha do arquivo de carga
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
                '', // Código do motivo da modificação (vazio para inclusão)
                '' // Data da averbação (não aplicável)
            ]);
            
            // Adiciona o delimitador de registro
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
        echo "<script>alert('Nenhum registro selecionado foi encontrado para gerar a carga.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Nenhum registro selecionado.'); window.history.back();</script>";
}

$conn->close();
?>
