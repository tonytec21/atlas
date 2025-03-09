<?php
include(__DIR__ . '/db_connection.php');

$conn = getDatabaseConnection();

// Modifica a query para excluir os checklists com status "removido"
$stmt = $conn->prepare("SELECT * FROM checklists WHERE status != 'removido' ORDER BY id DESC");
$stmt->execute();
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
  
    <!-- Barra de pesquisa -->
    <input id="searchInput" class="form-control mb-2" type="text" placeholder="Pesquisar Checklist..." onkeyup="searchTable()">

    <table id="checklistTable"
        class="table table-striped table-bordered"
        data-toggle="table"
        data-pagination="true"
        data-page-size="5"
        data-search="false">
        
        <thead>
            <tr>
                <th data-field="titulo" data-sortable="true">Título</th>
                <th data-field="acoes" class="text-center">Ações</th>
            </tr>
        </thead>
        
        <tbody>
            <?php foreach ($checklists as $checklist): ?>
                <tr>
                    <td><?= htmlspecialchars($checklist['titulo']); ?></td>
                    <td class="text-center">
                        <button class="btn btn-info btn-sm" title="Visualizar" onclick="visualizarChecklist(<?= $checklist['id']; ?>)">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="btn btn-edit btn-sm" title="Editar" onclick="editarChecklist(<?= $checklist['id']; ?>)">
                            <i class="fa fa-pencil"></i>
                        </button>
                        <button class="btn btn-delete btn-sm" title="Excluir" onclick="excluirChecklist(<?= $checklist['id']; ?>)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<!-- Script para pesquisa personalizada -->
<script>
function searchTable() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("checklistTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[0]; // Pega a primeira coluna (título)
        if (td) {
            txtValue = td.textContent || td.innerText;
            tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        }
    }
}
</script>
