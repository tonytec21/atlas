<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT id, nome_modelo, descricao, data_criacao FROM modelos_de_orcamento ORDER BY id DESC");
    $stmt->execute();
    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Erro ao listar modelos: " . $e->getMessage();
    exit;
}

if ($modelos) {
    echo '<table class="table table-bordered">';
    echo '<thead><tr>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Data Criação</th>
            <th>Ações</th>
          </tr></thead>';
    echo '<tbody>';
    foreach ($modelos as $mod) {
        echo '<tr>';
        echo '<td>' . $mod['nome_modelo'] . '</td>';
        echo '<td>' . $mod['descricao'] . '</td>';
        echo '<td>' . $mod['data_criacao'] . '</td>';
        echo '<td>
                <button class="btn btn-info btn-sm" title="Visualizar" onclick="visualizarModelo('.$mod['id'].')">
                  <i class="fa fa-eye" aria-hidden="true"></i>
                </button>
                <button class="btn btn-edit btn-sm" title="Editar" onclick="editarModelo('.$mod['id'].')">
                  <i class="fa fa-pencil" aria-hidden="true"></i>
                </button>
                <button class="btn btn-delete btn-sm" title="Excluir" onclick="excluirModelo('.$mod['id'].')">
                  <i class="fa fa-trash" aria-hidden="true"></i>
                </button>
              </td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>Nenhum modelo encontrado.</p>';
}
?>
