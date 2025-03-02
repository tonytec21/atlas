<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/db_connection.php');

// Obter parâmetros
$numeroGuia           = isset($_GET['numeroGuia']) ? $_GET['numeroGuia'] : '';
$numeroTarefa         = isset($_GET['numeroTarefa']) ? $_GET['numeroTarefa'] : '';
$cliente              = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$documentoApresentante= isset($_GET['documentoApresentante']) ? $_GET['documentoApresentante'] : '';
$funcionario          = isset($_GET['funcionario']) ? $_GET['funcionario'] : '';
$dataRecebimento      = isset($_GET['dataRecebimento']) ? $_GET['dataRecebimento'] : '';
$nomePortador         = isset($_GET['nomePortador']) ? $_GET['nomePortador'] : '';
$documentoPortador    = isset($_GET['documentoPortador']) ? $_GET['documentoPortador'] : '';
$action               = isset($_GET['action']) ? $_GET['action'] : 'task_id_zero';

// Montar a query base
$sql = "
    SELECT 
       guia.id,
       guia.task_id,
       guia.cliente,
       guia.documento_apresentante,
       guia.nome_portador,
       guia.documento_portador,
       guia.funcionario,
       guia.data_recebimento,
       guia.documentos_recebidos,
       guia.observacoes,
       tarefa.token AS task_token
    FROM guia_de_recebimento AS guia
    LEFT JOIN tarefas AS tarefa ON guia.task_id = tarefa.id
    WHERE 1=1
";

// Array para condições dinâmicas e para parâmetros do prepared statement
$conditions = [];
$params = [];
$types  = '';

// Se a ação for "task_id_zero", forçamos a busca pelo task_id = 0
if ($action === 'task_id_zero') {
    $conditions[] = "guia.task_id = 0";
}

// Filtros opcionais
if (!empty($numeroGuia)) {
    // Se $numeroGuia for numérico, use inteiro; senão, string
    // Aqui assumindo que guia.id é PK e do tipo INT
    $conditions[] = "guia.id = ?";
    $params[] = (int)$numeroGuia;
    $types  .= 'i';
}

if (!empty($numeroTarefa)) {
    // Também assumindo que é um INT
    $conditions[] = "guia.task_id = ?";
    $params[] = (int)$numeroTarefa;
    $types  .= 'i';
}

if (!empty($cliente)) {
    // LIKE com wildcard nos dois lados não utiliza índice de forma eficiente,
    // mas se for necessário, é assim mesmo.
    $conditions[] = "guia.cliente LIKE ?";
    $params[] = "%{$cliente}%";
    $types  .= 's';
}

if (!empty($documentoApresentante)) {
    $conditions[] = "guia.documento_apresentante LIKE ?";
    $params[] = "%{$documentoApresentante}%";
    $types  .= 's';
}

if (!empty($funcionario)) {
    $conditions[] = "guia.funcionario LIKE ?";
    $params[] = "%{$funcionario}%";
    $types  .= 's';
}

// Para dataRecebimento, evitar usar DATE(guia.data_recebimento)
if (!empty($dataRecebimento)) {
    // Construir intervalo do dia inteiro, se for data no formato YYYY-MM-DD
    $startDate = $dataRecebimento . ' 00:00:00';
    $endDate   = $dataRecebimento . ' 23:59:59';

    $conditions[] = "guia.data_recebimento BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types  .= 'ss';
}

if (!empty($nomePortador)) {
    $conditions[] = "guia.nome_portador LIKE ?";
    $params[] = "%{$nomePortador}%";
    $types  .= 's';
}

if (!empty($documentoPortador)) {
    $conditions[] = "guia.documento_portador LIKE ?";
    $params[] = "%{$documentoPortador}%";
    $types  .= 's';
}

// Se houver condições acumuladas, adicionamos na query
if (!empty($conditions)) {
    $sql .= " AND " . implode(' AND ', $conditions);
}

// Ordenação
$sql .= " ORDER BY guia.task_id DESC";

// Preparar a consulta (Prepared Statement)
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Erro ao preparar statement: " . $conn->error);
}

// Se houver parâmetros, fazemos bind
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

// Executar a consulta
$stmt->execute();

// Obter resultado
$result = $stmt->get_result();
$guias = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $guias[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($guias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

ob_end_flush();
?>
