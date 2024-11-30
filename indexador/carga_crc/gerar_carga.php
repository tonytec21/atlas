<?php
require_once __DIR__ . '/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['selected_ids']));

    $query = "SELECT * FROM indexador_nascimento WHERE id IN ($ids) AND status = 'ativo'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $nomeArquivo = 'carga_nascimento.txt';
        $arquivoCarga = fopen($nomeArquivo, 'w');

        while ($row = $result->fetch_assoc()) {
            $linha = implode(";", [
                "N", // Tipo de registro
                '000000', // Substitua pelo número CNS
                $row['nome_registrado'], 
                '', '', '', '', '', 
                $row['matricula'] ?? '',
                date('d/m/Y', strtotime($row['data_nascimento'])),
                date('d/m/Y', strtotime($row['data_registro'])),
                "I", '', ''
            ]);
            $linha .= ";*";
            fwrite($arquivoCarga, $linha . PHP_EOL);
        }

        fclose($arquivoCarga);

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        readfile($nomeArquivo);
        unlink($nomeArquivo);
        exit;
    } else {
        echo "<script>alert('Nenhum registro selecionado foi encontrado.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Nenhum registro selecionado.'); window.history.back();</script>";
}

$conn->close();
?>
