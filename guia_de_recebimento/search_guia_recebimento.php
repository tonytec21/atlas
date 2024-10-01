<?php
ob_start(); // Inicia o buffer de saída para evitar qualquer conteúdo inesperado

error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/db_connection.php');

// Obter os parâmetros de pesquisa
$cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$documentoApresentante = isset($_GET['documentoApresentante']) ? $_GET['documentoApresentante'] : ''; // Campo CPF/CNPJ
$funcionario = isset($_GET['funcionario']) ? $_GET['funcionario'] : '';
$dataRecebimento = isset($_GET['dataRecebimento']) ? $_GET['dataRecebimento'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'task_id_zero'; // Define como 'task_id_zero' na primeira carga da página

// 1. Quando entrar na página: carregar apenas task_id = 0
if ($action === 'task_id_zero') {
    // Carregar somente registros com task_id = 0
    $sql = "SELECT guia.id, guia.task_id, guia.cliente, guia.documento_apresentante, guia.funcionario, guia.data_recebimento, guia.documentos_recebidos, guia.observacoes, tarefa.token AS task_token 
            FROM guia_de_recebimento AS guia
            LEFT JOIN tarefas AS tarefa ON guia.task_id = tarefa.id
            WHERE guia.task_id = 0"; // Aqui carregamos somente task_id = 0
} else {
    // 2. Se clicar em "Filtrar" sem definir filtros: carregar todos os dados presentes no banco
    $sql = "SELECT guia.id, guia.task_id, guia.cliente, guia.documento_apresentante, guia.funcionario, guia.data_recebimento, guia.documentos_recebidos, guia.observacoes, tarefa.token AS task_token 
            FROM guia_de_recebimento AS guia
            LEFT JOIN tarefas AS tarefa ON guia.task_id = tarefa.id
            WHERE 1=1"; // Iniciar com uma condição sempre verdadeira para aplicar os filtros abaixo

    // Adicionar condições de pesquisa com base nos filtros fornecidos pelo usuário
    if (!empty($cliente)) {
        $sql .= " AND guia.cliente LIKE '%" . $conn->real_escape_string($cliente) . "%'";
    }

    // Filtro de CPF/CNPJ
    if (!empty($documentoApresentante)) {
        $sql .= " AND guia.documento_apresentante LIKE '%" . $conn->real_escape_string($documentoApresentante) . "%'";
    }

    if (!empty($funcionario)) {
        $sql .= " AND guia.funcionario LIKE '%" . $conn->real_escape_string($funcionario) . "%'";
    }

    if (!empty($dataRecebimento)) {
        $sql .= " AND DATE(guia.data_recebimento) = '" . $conn->real_escape_string($dataRecebimento) . "'";
    }
}

// Ordenar por task_id em ordem decrescente
$sql .= " ORDER BY guia.task_id DESC";

// Executar a consulta
$result = $conn->query($sql);

$guias = [];

if ($result->num_rows > 0) {
    // Converter os resultados em um array associativo
    while ($row = $result->fetch_assoc()) {
        $guias[] = $row;
    }
}

// Verifique se o JSON foi gerado corretamente
header('Content-Type: application/json');
echo json_encode($guias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

ob_end_flush(); // Libera qualquer conteúdo do buffer de saída
?>
