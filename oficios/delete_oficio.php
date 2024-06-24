<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $_POST['numero'];
    $oficioFilePath = __DIR__ . "/meta-dados/$numero.json";

    if (file_exists($oficioFilePath)) {
        if (unlink($oficioFilePath)) {
            echo "Ofício excluído com sucesso.";
        } else {
            http_response_code(500);
            echo "Erro ao excluir o ofício.";
        }
    } else {
        http_response_code(404);
        echo "Ofício não encontrado.";
    }
} else {
    http_response_code(405);
    echo "Método não permitido.";
}
