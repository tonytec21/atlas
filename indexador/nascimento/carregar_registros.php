<?php
include(__DIR__ . '/db_connection.php');

$searchTerm = isset($_GET['searchTerm']) ? $_GET['searchTerm'] : '';
$searchTermTerm = isset($_GET['searchTermTerm']) ? $_GET['searchTermTerm'] : '';
$searchTermBook = isset($_GET['searchTermBook']) ? $_GET['searchTermBook'] : '';
$searchTermPage = isset($_GET['searchTermPage']) ? $_GET['searchTermPage'] : '';
$searchFather = isset($_GET['searchFather']) ? $_GET['searchFather'] : '';
$searchMother = isset($_GET['searchMother']) ? $_GET['searchMother'] : '';
$birthDate = isset($_GET['birthDate']) ? $_GET['birthDate'] : '';
$registryDate = isset($_GET['registryDate']) ? $_GET['registryDate'] : '';

$query = "SELECT * FROM indexador_nascimento WHERE status = 'ativo'";

// Condições dinâmicas
$conditions = [];
if ($searchTerm) {
    $conditions[] = "LOWER(nome_registrado) LIKE '%" . $conn->real_escape_string($searchTerm) . "%'";
}
if ($searchTermTerm) {
    $conditions[] = "termo = " . $conn->real_escape_string($searchTermTerm);
}
if ($searchTermBook) {
    $conditions[] = "livro = " . $conn->real_escape_string($searchTermBook);
}
if ($searchTermPage) {
    $conditions[] = "folha = " . $conn->real_escape_string($searchTermPage);
}
if ($searchFather) {
    $conditions[] = "LOWER(nome_pai) LIKE '%" . $conn->real_escape_string($searchFather) . "%'";
}
if ($searchMother) {
    $conditions[] = "LOWER(nome_mae) LIKE '%" . $conn->real_escape_string($searchMother) . "%'";
}
if ($birthDate) {
    $conditions[] = "data_nascimento = '" . $conn->real_escape_string($birthDate) . "'";
}
if ($registryDate) {
    $conditions[] = "data_registro = '" . $conn->real_escape_string($registryDate) . "'";
}

// Se houver condições, adicione ao SQL
if (count($conditions) > 0) {
    $query .= " AND " . implode(' AND ', $conditions);
}

$result = $conn->query($query);

$registries = [];
while ($row = $result->fetch_assoc()) {
    $registries[] = $row;
}

echo json_encode($registries);

$conn->close();
