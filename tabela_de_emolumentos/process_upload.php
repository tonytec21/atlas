<?php
include(__DIR__ . '/db_connection.php');

function atoExists($conn, $ato) {
    $query = $conn->prepare("SELECT COUNT(*) FROM tabela_emolumentos WHERE ATO = ?");
    $query->execute([$ato]);
    return $query->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $handle = fopen($file, "r");
    if ($handle) {
        $conn = getDatabaseConnection();
        $ignoredAtos = [];

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (!empty($line)) {
                list($ato, $descricao, $emolumentos, $ferc, $fadep, $femp, $total) = explode(";", $line);

                // Substitui vírgulas por pontos nos valores
                $emolumentos = str_replace(',', '.', $emolumentos);
                $ferc = str_replace(',', '.', $ferc);
                $fadep = str_replace(',', '.', $fadep);
                $femp = str_replace(',', '.', $femp);
                $total = str_replace(',', '.', $total);

                if (!atoExists($conn, $ato)) {
                    $query = $conn->prepare("INSERT INTO tabela_emolumentos (ATO, DESCRICAO, EMOLUMENTOS, FERC, FADEP, FEMP, TOTAL) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$query->execute([$ato, $descricao, $emolumentos, $ferc, $fadep, $femp, $total])) {
                        echo "Erro ao inserir os dados para ATO='$ato': " . $query->errorInfo()[2] . "<br>";
                    }
                } else {
                    $ignoredAtos[] = $ato;
                }
            }
        }

        fclose($handle);
        $conn = null; // Close the connection

        if (!empty($ignoredAtos)) {
            echo json_encode(['status' => 'ignored', 'ignoredAtos' => $ignoredAtos]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Arquivo processado com sucesso.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao abrir o arquivo.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Por favor, envie um arquivo válido.']);
}
?>
