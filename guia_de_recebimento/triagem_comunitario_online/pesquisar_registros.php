<?php
include(__DIR__ . '/db_connection.php');

// Recebendo filtros via GET
$cidade = $_GET['filtroCidade'] ?? '';
$nomeNoivo = $_GET['filtroNomeNoivo'] ?? '';
$nomeNoiva = $_GET['filtroNomeNoiva'] ?? '';
$proclamas = $_GET['filtroProclamas'] ?? '';
$pedidoDeferido = $_GET['filtroPedidoDeferido'] ?? '';
$cadastroEfetivado = $_GET['filtroCadastroEfetivado'] ?? '';
$processoConcluido = $_GET['filtroProcessoConcluido'] ?? '';
$habilitacaoConcluida = $_GET['filtroHabilitacaoConcluida'] ?? '';

// Construindo a query dinâmica
$query = "SELECT * FROM triagem_comunitario WHERE 1=1";
$params = [];
$types = '';

// Verificando cada filtro e adicionando à query se necessário
if (!empty($cidade)) {
    $query .= " AND cidade = ?";
    $params[] = $cidade;
    $types .= 's';
}
if (!empty($nomeNoivo)) {
    $query .= " AND nome_do_noivo LIKE ?";
    $params[] = "%$nomeNoivo%";
    $types .= 's';
}
if (!empty($nomeNoiva)) {
    $query .= " AND nome_da_noiva LIKE ?";
    $params[] = "%$nomeNoiva%";
    $types .= 's';
}
if (!empty($proclamas)) {
    $query .= " AND numero_proclamas = ?";
    $params[] = $proclamas;
    $types .= 's';
}
if ($pedidoDeferido !== '') {
    $query .= " AND pedido_deferido = ?";
    $params[] = $pedidoDeferido;
    $types .= 'i';
}
if ($cadastroEfetivado !== '') {
    $query .= " AND cadastro_efetivado = ?";
    $params[] = $cadastroEfetivado;
    $types .= 'i';
}
if ($processoConcluido !== '') {
    $query .= " AND processo_concluido = ?";
    $params[] = $processoConcluido;
    $types .= 'i';
}
if ($habilitacaoConcluida !== '') {
    $query .= " AND habilitacao_concluida = ?";
    $params[] = $habilitacaoConcluida;
    $types .= 'i';
}

// Preparando e executando a query
$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Gerando a tabela com os resultados
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$row['cidade']}</td>
            <td>{$row['n_protocolo']}</td>
            <td>{$row['nome_do_noivo']}</td>
            <td>" . ($row['noivo_menor'] ? 'Sim' : 'Não') . "</td>
            <td>{$row['nome_da_noiva']}</td>
            <td>" . ($row['noiva_menor'] ? 'Sim' : 'Não') . "</td>
            <td>
                <span class='status-span " . ($row['pedido_deferido'] ? 'status-sim' : 'status-nao') . "'>
                    " . ($row['pedido_deferido'] ? 'Sim' : 'Não') . "
                </span>
            </td>
            <td>
                <span class='status-span " . ($row['cadastro_efetivado'] ? 'status-sim' : 'status-nao') . "'>
                    " . ($row['cadastro_efetivado'] ? 'Sim' : 'Não') . "
                </span>
            </td>
            <td>
                <span class='status-span " . ($row['processo_concluido'] ? 'status-sim' : 'status-nao') . "'>
                    " . ($row['processo_concluido'] ? 'Sim' : 'Não') . "
                </span>
            </td>
            <td>
                <span class='status-span " . ($row['habilitacao_concluida'] ? 'status-sim' : 'status-nao') . "'>
                    " . ($row['habilitacao_concluida'] ? 'Sim' : 'Não') . "
                </span>
            </td>

            <td>
                <button class='btn btn-info' onclick='abrirModalVisualizar({$row['id']})'><i class='fa fa-eye' aria-hidden='true'></i></button>
                <button class='btn btn-warning' onclick='abrirModalEditar({$row['id']})'><i class='fa fa-pencil' aria-hidden='true'></i></button>
                <button class='btn btn-secondary' onclick='abrirModalSituacao({$row['id']})'><i class='fa fa-bell' aria-hidden='true'></i></button>
                <button class='btn btn-success' onclick='imprimirGuia({$row['id']})'><i class='fa fa-print' aria-hidden='true'></i></button>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='10' class='text-center'>Nenhum registro encontrado.</td></tr>";
}
?>
