<?php
// Verificar se as novas colunas existem  
$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'prazo_cumprimento'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN prazo_cumprimento TEXT AFTER corpo");  
}  

$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'cpf_cnpj'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN cpf_cnpj VARCHAR(20) AFTER apresentante");  
}  

$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'data_protocolo'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN data_protocolo DATE AFTER protocolo");  
}  

$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'origem_titulo'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN origem_titulo VARCHAR(200) AFTER titulo");  
}  

// Verificar se a coluna status existe  
$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'status'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN status VARCHAR(50) DEFAULT 'Pendente' AFTER dados_complementares");  
}  

// Verificar se há filtros aplicados  
$filters = [];  
$filterQuery = "";  

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['numero']) || isset($_GET['data']) || isset($_GET['titulo']) ||   
    isset($_GET['apresentante']) || isset($_GET['protocolo']) || isset($_GET['cpf_cnpj']) ||   
    isset($_GET['origem_titulo']) || isset($_GET['data_protocolo']) || isset($_GET['status']))) {  
    
    if (!empty($_GET['numero'])) {  
        $filters[] = "numero LIKE '%" . $conn->real_escape_string($_GET['numero']) . "%'";  
    }  
    if (!empty($_GET['data'])) {  
        $filters[] = "data = '" . $conn->real_escape_string($_GET['data']) . "'";  
    }  
    if (!empty($_GET['titulo'])) {  
        $filters[] = "titulo LIKE '%" . $conn->real_escape_string($_GET['titulo']) . "%'";  
    }  
    if (!empty($_GET['apresentante'])) {  
        $filters[] = "apresentante LIKE '%" . $conn->real_escape_string($_GET['apresentante']) . "%'";  
    }  
    if (!empty($_GET['protocolo'])) {  
        $filters[] = "protocolo LIKE '%" . $conn->real_escape_string($_GET['protocolo']) . "%'";  
    }  
    if (!empty($_GET['cpf_cnpj'])) {  
        // Remover caracteres não numéricos para a consulta  
        $cpf_cnpj_clean = preg_replace('/[^0-9]/', '', $_GET['cpf_cnpj']);  
        $filters[] = "REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE '%" . $conn->real_escape_string($cpf_cnpj_clean) . "%'";  
    }  
    if (!empty($_GET['origem_titulo'])) {  
        $filters[] = "origem_titulo LIKE '%" . $conn->real_escape_string($_GET['origem_titulo']) . "%'";  
    }  
    if (!empty($_GET['data_protocolo'])) {  
        $filters[] = "data_protocolo = '" . $conn->real_escape_string($_GET['data_protocolo']) . "'";  
    }  
    if (!empty($_GET['status'])) {  
        $filters[] = "status = '" . $conn->real_escape_string($_GET['status']) . "'";  
    }  

    if (count($filters) > 0) {  
        $filterQuery = "WHERE " . implode(" AND ", $filters);  
    }  
}  

// Consulta as notas devolutivas  
$sql = "SELECT * FROM notas_devolutivas $filterQuery ORDER BY id DESC";  
$result = $conn->query($sql);  

$notas = [];  
if ($result && $result->num_rows > 0) {  
    while ($row = $result->fetch_assoc()) {  
        $notas[] = $row;  
    }  
}  

// Lista de status disponíveis  
$statusOptions = [  
    'Pendente' => '#ffc107',  // Amarelo  
    'Exigência Cumprida' => '#28a745',  // Verde  
    'Exigência Não Cumprida' => '#dc3545',  // Vermelho  
    'Prazo Expirado' => '#fd7e14',  // Laranja  
    'Em Análise' => '#007bff',  // Azul  
    'Cancelada' => '#6c757d',  // Cinza  
    'Aguardando Documentação' => '#6f42c1'  // Roxo  
];  

$conn->close();  

?>