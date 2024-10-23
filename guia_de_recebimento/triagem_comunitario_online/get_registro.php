<?php
include(__DIR__ . '/db_connection.php');

$id = $_GET['id'] ?? 0;

$query = "SELECT * FROM triagem_comunitario WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$registro = $result->fetch_assoc();

// Busca os anexos relacionados
$anexos = [];
if (!empty($registro['caminho_anexo'])) {
    $caminhos = explode(';', $registro['caminho_anexo']);
    foreach ($caminhos as $caminho) {
        $anexos[] = [
            'nome' => basename($caminho),
            'caminho' => $caminho
        ];
    }
}

// Adiciona anexos ao registro
$registro['anexos'] = $anexos;

echo json_encode($registro);
?>
