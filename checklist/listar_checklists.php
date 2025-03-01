<?php
include(__DIR__ . '/db_connection.php');

$conn = getDatabaseConnection();

// Modifica a query para excluir os checklists com status "removido"
$stmt = $conn->prepare("SELECT * FROM checklists WHERE status != 'removido' ORDER BY id DESC");
$stmt->execute();
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<ul class="list-group">
    <?php foreach ($checklists as $checklist): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= htmlspecialchars($checklist['titulo']); ?>
            <div class="btn-actions">
                <button class="btn btn-info btn-sm" title="Visualizar" onclick="visualizarChecklist(<?= $checklist['id']; ?>)">
                    <i class="fa fa-eye"></i>
                </button>
                <button class="btn btn-edit btn-sm" title="Editar" onclick="editarChecklist(<?= $checklist['id']; ?>)">
                    <i class="fa fa-pencil"></i>
                </button>
                <button class="btn btn-delete btn-sm" title="Excluir" onclick="excluirChecklist(<?= $checklist['id']; ?>)">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
