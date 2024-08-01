<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Categorias</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        .table th:nth-child(1), .table td:nth-child(1) {
            width: 5%;
        }
        .table th:nth-child(3), .table td:nth-child(3) {
            width: 10%;
        }
        .table th:nth-child(2), .table td:nth-child(2) {
            width: 85%;
        }
        .col-md-6 {
            display: flex;
            align-items: flex-end;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
include(__DIR__ . '/db_connection.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Gerenciamento de categorias de tarefas</h3>
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="search-term" class="form-control" placeholder="Pesquisar categoria">
            </div>
            <div class="col-md-3">
                <button id="search-button" class="btn btn-primary w-100">Pesquisar</button>
            </div>
            <div class="col-md-3">
                <button id="add-button" class="btn btn-success w-100" data-toggle="modal" data-target="#addCategoryModal">Cadastrar</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Categoria</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="category-table-body">
                    <!-- Linhas serão adicionadas dinamicamente -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Adição de Categoria -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Cadastrar Categoria</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="category-form">
                    <div class="form-group">
                        <label for="category-name">Nome da Categoria</label>
                        <input type="text" class="form-control" id="category-name" name="category-name" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edição de Categoria -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Editar Categoria</h5>
                <button type="button" the close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="edit-category-form">
                    <input type="hidden" id="edit-category-id" name="edit-category-id">
                    <div class="form-group">
                        <label for="edit-category-name">Nome da Categoria</label>
                        <input type="text" class="form-control" id="edit-category-name" name="edit-category-name" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
        function loadCategories() {
            $.ajax({
                url: 'load_categories.php',
                type: 'GET',
                success: function(response) {
                    const categories = JSON.parse(response);
                    $('#category-table-body').empty();
                    categories.forEach(function(category) {
                        const row = `<tr>
                            <td>${category.id}</td>
                            <td>${category.titulo}</td>
                            <td>
                                <button class="btn btn-edit btn-sm edit-category" data-id="${category.id}" data-name="${category.titulo}"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                <button class="btn btn-delete btn-sm delete-category" data-id="${category.id}"><i class="fa fa-trash" aria-hidden="true"></i></button>
                            </td>
                        </tr>`;
                        $('#category-table-body').append(row);
                    });
                }
            });
        }

        loadCategories();

        $('#search-button').on('click', function() {
            var searchTerm = $('#search-term').val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(searchTerm) > -1);
            });
        });

        $('#category-form').on('submit', function(e) {
            e.preventDefault();
            const categoryName = $('#category-name').val();
            $.ajax({
                url: 'save_category.php',
                type: 'POST',
                data: { titulo: categoryName },
                success: function() {
                    $('#addCategoryModal').modal('hide');
                    loadCategories();
                }
            });
        });

        $(document).on('click', '.edit-category', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            $('#edit-category-id').val(id);
            $('#edit-category-name').val(name);
            $('#editCategoryModal').modal('show');
        });

        $('#edit-category-form').on('submit', function(e) {
            e.preventDefault();
            const id = $('#edit-category-id').val();
            const name = $('#edit-category-name').val();
            $.ajax({
                url: 'update_category.php',
                type: 'POST',
                data: { id: id, titulo: name },
                success: function() {
                    $('#editCategoryModal').modal('hide');
                    loadCategories();
                }
            });
        });

        $(document).on('click', '.delete-category', function() {
            const id = $(this).data('id');
            if (confirm('Tem certeza que deseja excluir esta categoria?')) {
                $.ajax({
                    url: 'delete_category.php',
                    type: 'POST',
                    data: { id: id },
                    success: function() {
                        loadCategories();
                    }
                });
            }
        });
    });
</script>

<script>

    function normalizeText(text) {
        return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function formatDateTime(dateTime) {
        var date = new Date(dateTime);
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
    }

    $(document).ready(function() {

    });
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
