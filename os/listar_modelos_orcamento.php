<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT id, nome_modelo, descricao, data_criacao FROM modelos_de_orcamento ORDER BY id DESC");
    $stmt->execute();
    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao listar modelos: " . $e->getMessage();
    exit;
}
?>

<input id="searchInput" class="form-control mb-2" type="text" placeholder="Pesquisar modelo..." onkeyup="searchTable()">

<table id="modelosTable"
    class="table table-striped table-bordered"
    data-toggle="table"
    data-pagination="true"
    data-page-size="5"
    data-search="false">
    
    <thead>
        <tr>
            <th data-field="nome" data-sortable="true">Nome</th>
            <th data-field="descricao" data-sortable="false">Descrição</th>
            <th data-field="data_criacao" data-sortable="true">Data Criação</th>
            <th data-field="acoes" class="text-center">Ações</th>
        </tr>
    </thead>
    
    <tbody>
        <?php foreach ($modelos as $mod): ?>
            <tr>
                <td><?= htmlspecialchars($mod['nome_modelo']); ?></td>
                <td><?= htmlspecialchars($mod['descricao']); ?></td>
                <td><?= date('d/m/Y', strtotime($mod['data_criacao'])); ?></td>
                <td class="text-center">
                    <button class="btn btn-info btn-sm" title="Visualizar" onclick="visualizarModelo(<?= $mod['id']; ?>)">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button class="btn btn-edit btn-sm" title="Editar" onclick="editarModelo(<?= $mod['id']; ?>)">
                        <i class="fa fa-pencil"></i>
                    </button>
                    <button class="btn btn-delete btn-sm" title="Excluir" onclick="excluirModelo(<?= $mod['id']; ?>)">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
function searchTable() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("modelosTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[0];
        if (td) {
            txtValue = td.textContent || td.innerText;
            tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        }
    }
}
</script>
