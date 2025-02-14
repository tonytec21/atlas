<?php
ob_start(); // Inicia o buffer de saída para evitar qualquer conteúdo inesperado

error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/db_connection.php');

// Obter os parâmetros de pesquisa
$numeroGuia = isset($_GET['numeroGuia']) ? $_GET['numeroGuia'] : '';
$numeroTarefa = isset($_GET['numeroTarefa']) ? $_GET['numeroTarefa'] : '';
$cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$documentoApresentante = isset($_GET['documentoApresentante']) ? $_GET['documentoApresentante'] : '';
$funcionario = isset($_GET['funcionario']) ? $_GET['funcionario'] : '';
$dataRecebimento = isset($_GET['dataRecebimento']) ? $_GET['dataRecebimento'] : '';
$nomePortador = isset($_GET['nomePortador']) ? $_GET['nomePortador'] : '';
$documentoPortador = isset($_GET['documentoPortador']) ? $_GET['documentoPortador'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'task_id_zero'; // Define como 'task_id_zero' na primeira carga da página

// Quando entrar na página: carregar apenas task_id = 0
if ($action === 'task_id_zero') {
    // Carregar somente registros com task_id = 0
    $sql = "SELECT guia.id, guia.task_id, guia.cliente, guia.documento_apresentante, 
                   guia.nome_portador, guia.documento_portador, guia.funcionario, 
                   guia.data_recebimento, guia.documentos_recebidos, guia.observacoes, 
                   tarefa.token AS task_token 
            FROM guia_de_recebimento AS guia
            LEFT JOIN tarefas AS tarefa ON guia.task_id = tarefa.id
            WHERE guia.task_id = 0";
} else {
    // Se clicar em "Filtrar" sem definir filtros: carregar todos os dados
    $sql = "SELECT guia.id, guia.task_id, guia.cliente, guia.documento_apresentante, 
                   guia.nome_portador, guia.documento_portador, guia.funcionario, 
                   guia.data_recebimento, guia.documentos_recebidos, guia.observacoes, 
                   tarefa.token AS task_token 
            FROM guia_de_recebimento AS guia
            LEFT JOIN tarefas AS tarefa ON guia.task_id = tarefa.id
            WHERE 1=1";

    // Adicionar condições de pesquisa com base nos filtros fornecidos pelo usuário
    if (!empty($numeroGuia)) {
        $sql .= " AND guia.id = '" . $conn->real_escape_string($numeroGuia) . "'";
    }

    if (!empty($numeroTarefa)) {
        $sql .= " AND guia.task_id = '" . $conn->real_escape_string($numeroTarefa) . "'";
    }

    if (!empty($cliente)) {
        $sql .= " AND guia.cliente LIKE '%" . $conn->real_escape_string($cliente) . "%'";
    }

    if (!empty($documentoApresentante)) {
        $sql .= " AND guia.documento_apresentante LIKE '%" . $conn->real_escape_string($documentoApresentante) . "%'";
    }

    if (!empty($funcionario)) {
        $sql .= " AND guia.funcionario LIKE '%" . $conn->real_escape_string($funcionario) . "%'";
    }

    if (!empty($dataRecebimento)) {
        $sql .= " AND DATE(guia.data_recebimento) = '" . $conn->real_escape_string($dataRecebimento) . "'";
    }

    if (!empty($nomePortador)) {
        $sql .= " AND guia.nome_portador LIKE '%" . $conn->real_escape_string($nomePortador) . "%'";
    }

    if (!empty($documentoPortador)) {
        $sql .= " AND guia.documento_portador LIKE '%" . $conn->real_escape_string($documentoPortador) . "%'";
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
