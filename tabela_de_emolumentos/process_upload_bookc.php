<?php
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_error_log.txt');
error_reporting(E_ALL);

include(__DIR__ . '/db_connection.php');

function atoExists($conn, $ato) {
    $query = $conn->prepare("SELECT COUNT(*) FROM ato_novo WHERE strCodigoLei = ?");
    $query->execute([$ato]);
    return $query->fetchColumn() > 0;
}

$response = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== false) {
            $conn = getDatabaseConnection();
            $ignoredAtos = [];
            $insertErrors = [];
            $insertedCount = 0;


            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (!empty($line)) {
                    list($ato, $descricao, $emolumentos, $ferc, $fadep, $femp, $total) = explode("#", $line);

                    $emolumentos = str_replace(',', '.', $emolumentos);
                    $ferc = str_replace(',', '.', $ferc);
                    $fadep = str_replace(',', '.', $fadep);
                    $femp = str_replace(',', '.', $femp);
                    $total = str_replace(',', '.', $total);

                    if (!atoExists($conn, $ato)) {
                        $query = $conn->prepare("INSERT INTO ato_novo (strCodigoLei, strTipoAto, monValor, monValorFerc, FEMP, FADEP, TOTAL) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if ($query->execute([$ato, $descricao, $emolumentos, $ferc, $fadep, $femp, $total])) {
                            $insertedCount++;
                        } else {
                            $insertErrors[] = "Erro ao inserir ATO '$ato': " . $query->errorInfo()[2];
                        }
                    } else {
                        $ignoredAtos[] = $ato;
                    }
                    
                }
            }

            fclose($handle);
            $conn = null;

            if (!empty($insertErrors)) {
                $response = ['status' => 'error', 'message' => 'Ocorreram erros durante o processamento.', 'details' => $insertErrors];
            } elseif (!empty($ignoredAtos)) {
                $response = ['status' => 'ignored', 'ignoredAtos' => $ignoredAtos];
            } else {
                $response = ['status' => 'success', 'message' => 'Arquivo processado com sucesso.', 'insertedCount' => $insertedCount];
            }            
        } else {
            $response = ['status' => 'error', 'message' => 'Erro ao abrir o arquivo.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Por favor, envie um arquivo válido.'];
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'Erro inesperado: ' . $e->getMessage()];
}

echo json_encode($response);
?>
