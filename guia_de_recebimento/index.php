<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisa de Guias de Recebimento</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../style/sweetalert2.min.css">
    <style>
        /* Remover a borda de foco no botão de fechar */
        .btn-close {
            outline: none;
            border: none;
            background: none;
            padding: 0; 
            font-size: 1.5rem;
            cursor: pointer; 
            transition: transform 0.2s ease; 
        }

        /* Aumentar o tamanho do botão em 5% no hover */
        .btn-close:hover {
            transform: scale(2.10);
        }

        /* Opcional: Adicionar foco suave sem borda visível */
        .btn-close:focus {
            outline: none; 
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisa de Guias de Recebimento</h3>
            <hr>
            <form id="searchForm">
    <div class="form-row">
        <!-- Campo para Número da Guia -->
        <div class="form-group col-md-1">
            <label for="numeroGuia">Nº Guia:</label>
            <input type="text" class="form-control" id="numeroGuia" name="numeroGuia" placeholder="Digite o Nº da Guia">
        </div>

        <!-- Campo para Número da Tarefa -->
        <div class="form-group col-md-1">
            <label for="numeroTarefa">Nº Tarefa:</label>
            <input type="text" class="form-control" id="numeroTarefa" name="numeroTarefa" placeholder="Digite o Nº da Tarefa">
        </div>
        <div class="form-group col-md-3">
            <label for="cliente">Apresentante:</label>
            <input type="text" class="form-control" id="cliente" name="cliente" placeholder="Nome do Cliente">
        </div>
        <div class="form-group col-md-2">
            <label for="documentoApresentante">CPF/CNPJ:</label>
            <input type="text" class="form-control" id="documentoApresentante" name="documentoApresentante" placeholder="Digite o CPF ou CNPJ">
        </div>

        <div class="form-group col-md-3">
            <label for="funcionario">Funcionário:</label>
            <select class="form-control" id="funcionario" name="funcionario">
                <option value="">Selecione o Funcionário</option>
                <?php
                // Consulta para obter a lista de funcionários únicos
                $sqlFuncionarios = "SELECT DISTINCT funcionario FROM guia_de_recebimento WHERE funcionario IS NOT NULL AND funcionario != ''";
                $resultFuncionarios = $conn->query($sqlFuncionarios);

                // Verifica se há resultados
                if ($resultFuncionarios->num_rows > 0) {
                    // Exibe cada funcionário como uma opção no select
                    while ($row = $resultFuncionarios->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($row['funcionario'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['funcionario'], ENT_QUOTES, 'UTF-8') . "</option>";
                    }
                } else {
                    echo "<option value=''>Nenhum funcionário encontrado</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group col-md-2">
            <label for="dataRecebimento">Data de Recebimento:</label>
            <input type="date" class="form-control" id="dataRecebimento" name="dataRecebimento">
        </div>

        <div class="form-group col-md-6">
            <!-- Botão de Filtrar (mantém o tipo submit) -->
            <button type="submit" class="btn btn-primary w-100" style="margin-top: 2px;"><i class="fa fa-filter"></i> Filtrar</button>
        </div>
        <div class="form-group col-md-6">
            <!-- Botão Criar Guia de Recebimento com type="button" para evitar o envio do formulário -->
            <button type="button" class="btn btn-success w-100" style="margin-top: 2px;" data-toggle="modal" data-target="#modalCriarGuia"><i class="fa fa-plus"></i> Criar Guia de Recebimento</button>
        </div>
    </div>
</form>


            <hr>
            <div class="mt-3">
                <h5>Resultados da Pesquisa</h5>
                <table id="resultadosTabela" class="table table-striped table-bordered" style="zoom: 82%">
                    <thead>
                        <tr>
                            <th style="width: 6%">Nº Guia</th>
                            <th style="width: 7%">Nº Tarefa</th>
                            <th style="width: 15%">Apresentante</th>
                            <th style="width: 11%">CPF/CNPJ</th>
                            <th style="width: 15%">Funcionário</th>
                            <th style="width: 12%">Data de Recebimento</th>
                            <th style="width: 16%">Documentos Recebidos</th>
                            <th style="width: 10%">Observações</th>
                            <th style="width: 8%">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="resultados">
                        <!-- Resultados serão inseridos aqui -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para criação de Tarefa -->
    <div class="modal fade" id="tarefaModal" tabindex="-1" role="dialog" aria-labelledby="tarefaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document"> <!-- Modal ajustado para ser grande -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tarefaModalLabel">Criar Tarefa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" style="position: absolute; top: 5px; right: 15px;">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <form id="taskForm" method="POST" action="save_task.php" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="title">Título da Tarefa</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="category">Categoria</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";
                                    $result = $conn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="deadline">Data Limite para Conclusão</label>
                                <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="employee">Funcionário Responsável</label>
                                <select class="form-control" id="employee" name="employee" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";
                                    $result = $conn->query($sql);
                                    $loggedInUser = $_SESSION['username'];

                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $selected = ($row['nome_completo'] == $loggedInUser) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="origin">Origem</label>
                                <select class="form-control" id="origin" name="origin" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";
                                    $result = $conn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="attachments">Anexos</label>
                            <input type="file" class="form-control-file" id="attachments" name="attachments[]" multiple>
                        </div>
                        <input type="hidden" id="createdBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">
                        <input type="hidden" id="createdAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">
                        <input type="hidden" id="guiaId" name="guiaId"> <!-- Campo oculto para armazenar o ID da guia -->
                        <button type="submit" class="btn btn-primary w-100">Criar Tarefa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Vincular Tarefa -->
    <div class="modal fade" id="vincularTarefaModal" tabindex="-1" role="dialog" aria-labelledby="vincularTarefaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document" style="max-width: 20%!important">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vincularTarefaLabel">Vincular Tarefa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" style="position: absolute; top: 5px; right: 15px;">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <form id="vincularTarefaForm">
                        <div class="form-group">
                            <label for="protocolo-geral">Número do Protocolo Geral</label>
                            <input type="text" class="form-control" id="protocolo-geral" inputmode="numeric" pattern="[0-9]*" placeholder="Digite o número do protocolo geral" maxlength="10">
                        </div>
                        <input type="hidden" id="modal-guia-id" value="">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="vincularTarefa()"><i class="fa fa-link" aria-hidden="true"></i> Vincular</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Criar Guia-->
    <div class="modal fade" id="modalCriarGuia" tabindex="-1" role="dialog" aria-labelledby="modalCriarGuiaLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCriarGuiaLabel">Criar Guia de Recebimento</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formCriarGuia">
                        <!-- Linha para Apresentante e CPF/CNPJ -->
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="cliente">Apresentante:</label>
                                <input type="text" class="form-control" id="cliente" name="cliente" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="documentoApresentante">CPF/CNPJ:</label>
                                <input type="text" class="form-control" id="documentoApresentante" name="documentoApresentante">
                            </div>
                        </div>

                        <!-- Campo para Documentos Recebidos -->
                        <div class="form-group">
                            <label for="documentosRecebidos">Documentos Recebidos:</label>
                            <textarea class="form-control" id="documentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>
                        </div>

                        <!-- Campo para Observações -->
                        <div class="form-group">
                            <label for="observacoes">Observações:</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="salvarGuiaBtn">Salvar Guia</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Guia -->
    <div class="modal fade" id="modalEditarGuia" tabindex="-1" role="dialog" aria-labelledby="modalEditarGuiaLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarGuiaLabel">Editar Guia de Recebimento</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formEditarGuia">
                        <input type="hidden" id="editarGuiaId" name="guiaId">
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="editarCliente">Apresentante:</label>
                                <input type="text" class="form-control" id="editarCliente" name="cliente" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarDocumentoApresentante">CPF/CNPJ:</label>
                                <input type="text" class="form-control" id="editarDocumentoApresentante" name="documentoApresentante">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="editarDocumentosRecebidos">Documentos Recebidos:</label>
                            <textarea class="form-control" id="editarDocumentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editarObservacoes">Observações:</label>
                            <textarea class="form-control" id="editarObservacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="salvarEdicaoGuiaBtn">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var deadlineInput = document.getElementById('deadline');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            deadlineInput.min = minDateTime;
        });
        
        $(document).ready(function() {
            var dataTable = $('#resultadosTabela').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [[0, 'desc']],  // Ordenar por ID de forma decrescente
            });

            // Carregar apenas guias com task_id = 0 ao carregar a página
            loadGuias('task_id_zero');

            // Quando o formulário de pesquisa for submetido
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serialize(); // Captura todos os campos, incluindo CPF/CNPJ

                console.log(formData); // Verifique no console se o CPF/CNPJ está sendo enviado corretamente

                var hasFilters = $('#cliente').val() || $('#funcionario').val() || $('#dataRecebimento').val() || $('#documentoApresentante').val()|| $('#numeroGuia').val() || $('#numeroTarefa').val();

                if (!hasFilters) {
                    loadGuias('all');  // Se nenhum filtro foi aplicado, carregar todos os dados
                } else {
                    loadGuias(null, formData);  // Se houver filtros, aplicar a pesquisa
                }
            });

            // Função para carregar guias de recebimento
            function loadGuias(action = 'task_id_zero', formData = '') {
                $.ajax({
                    url: 'search_guia_recebimento.php',
                    type: 'GET',
                    dataType: 'json',
                    data: formData ? formData + '&action=' + action : 'action=' + action,
                    success: function(response) {
                        console.log(response);
                        dataTable.clear().draw();

                        if (response.length > 0) {
                            response.forEach(function(guia) {
                                // Função para formatar a data no formato brasileiro
                                function formatarDataBrasileira(dataString) {
                                    // Substitui o espaço entre a data e a hora por 'T' para que o JavaScript entenda
                                    var data = new Date(dataString.replace(' ', 'T'));

                                    // Verifica se a data é válida
                                    if (isNaN(data.getTime())) {
                                        return dataString; // Retorna a string original se a data for inválida
                                    }

                                    // Retorna a data no formato brasileiro
                                    return data.toLocaleString('pt-BR', {
                                        year: 'numeric',
                                        month: '2-digit',
                                        day: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit'
                                    });
                                }

                                // Função assíncrona para verificar o valor do campo "timbrado" no arquivo configuracao.json
                                async function verificarTimbrado() {
                                    try {
                                        const response = await fetch('../style/configuracao.json');
                                        const config = await response.json();
                                        return config.timbrado;
                                    } catch (error) {
                                        console.error('Erro ao carregar o arquivo de configuração:', error);
                                        return null;
                                    }
                                }

                                // Função para abrir a guia de impressão com a URL correta
                                async function visualizarGuia(taskId, guiaId) {
                                    const timbrado = await verificarTimbrado();
                                    
                                    // Verifica se taskId é 0 ou NULL, e abre com o guiaId
                                    if (!taskId || taskId == 0) {
                                        if (timbrado === 'S') {
                                            window.open(`guia_recebimento.php?id=${guiaId}`, '_blank');
                                        } else if (timbrado === 'N') {
                                            window.open(`guia-recebimento.php?id=${guiaId}`, '_blank');
                                        } else {
                                            alert('Erro: Não foi possível determinar o tipo de guia de recebimento.');
                                        }
                                    } else {
                                        // Caso contrário, usa taskId na URL
                                        if (timbrado === 'S') {
                                            window.open(`../tarefas/guia_recebimento.php?id=${taskId}`, '_blank');
                                        } else if (timbrado === 'N') {
                                            window.open(`../tarefas/guia-recebimento.php?id=${taskId}`, '_blank');
                                        } else {
                                            alert('Erro: Não foi possível determinar o tipo de guia de recebimento.');
                                        }
                                    }
                                }

                                // Código principal que insere as ações no DataTable
                                var acoes = '';

                                // Verifica se o task_id é 0 ou NULL, caso seja, exibe o botão "Criar Tarefa"
                                if (!guia.task_id || guia.task_id == 0) {
                                    acoes = `
                                        <button class="btn btn-primary btn-sm btn-print" data-task-id="${guia.task_id}" data-guia-id="${guia.id}" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Imprimir Guia de Recebimento"><i class="fa fa-print" aria-hidden="true"></i></button>
                                        <button class="btn btn-success btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Criar Tarefa" onclick='abrirModalTarefa(${guia.id}, ${JSON.stringify(guia.cliente)}, ${JSON.stringify(guia.documentos_recebidos)})'><i class="fa fa-clock-o" aria-hidden="true"></i></button>
                                        <button class="btn btn-secondary btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Vincular Tarefa" onclick="abrirModalVincularTarefa(${guia.id})"><i class="fa fa-link" aria-hidden="true"></i></button>
                                        <button class="btn btn-warning btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Editar Guia" onclick="abrirModalEditarGuia(${guia.id})"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                    `;

                                } else {
                                    // Caso já exista um task_id, exibe o botão "Visualizar Tarefa" com o link para a página da tarefa
                                    acoes = `
                                        <button class="btn btn-primary btn-sm btn-print" data-task-id="${guia.task_id}" data-guia-id="${guia.id}" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Imprimir Guia de Recebimento"><i class="fa fa-print" aria-hidden="true"></i></button>
                                        <button class="btn btn-info btn-sm" title="Visualizar Tarefa" onclick="window.location.href='../tarefas/index_tarefa.php?token=${guia.task_token}'"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                        <button class="btn btn-warning btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Editar Guia" onclick="abrirModalEditarGuia(${guia.id})"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                    `;
                                }

                               // Adiciona o conteúdo ao DataTable
                                dataTable.row.add([
                                    guia.id,  // ID da guia
                                    guia.task_id || '-',  // Protocolo Tarefa
                                    guia.cliente,  // Cliente
                                    guia.documento_apresentante,  // Cliente
                                    guia.funcionario,  // Funcionário
                                    formatarDataBrasileira(guia.data_recebimento),  // Data de Recebimento formatada
                                    guia.documentos_recebidos,  // Documentos Recebidos
                                    guia.observacoes,  // Observações
                                    acoes  // Coluna de Ações
                                ]).draw();

                                // Após a tabela ser desenhada, remove event listeners antigos e adiciona novos para os botões de impressão
                                $('.btn-print').off('click').on('click', function() {
                                    const taskId = $(this).data('task-id');
                                    const guiaId = $(this).data('guia-id');
                                    visualizarGuia(taskId, guiaId);
                                });

                            });
                        } else {
                            // alert('Nenhum registro encontrado.');
                        }
                    },
                    error: function() {
                        alert('Erro ao buscar os dados.');
                    }
                });
            }
        });

        function abrirModalTarefa(guiaId, cliente, documentosRecebidos) {
            // Preencher o título da tarefa com o nome do cliente
            $('#title').val(' - ' + cliente);
            
            // Preencher a descrição da tarefa com os documentos recebidos
            $('#description').val('Documentos Recebidos: ' + documentosRecebidos);

            // Definir o ID da guia no modal para uso posterior
            $('#guiaId').val(guiaId);

            // Abrir o modal
            $('#tarefaModal').modal('show');
        }

        // Função para abrir o modal de vincular tarefa
        function abrirModalVincularTarefa(guiaId) {
            document.getElementById('modal-guia-id').value = guiaId; // Define o guiaId no campo oculto
            $('#vincularTarefaModal').modal('show'); // Abre o modal
        }

        // Função para enviar o número do protocolo geral via AJAX
        function vincularTarefa() {
            var guiaId = document.getElementById('modal-guia-id').value;
            var protocolo = document.getElementById('protocolo-geral').value;

            $.ajax({
                url: 'vincular_tarefa.php',
                method: 'POST',
                data: {
                    guia_id: guiaId,
                    task_id: protocolo
                },
                success: function(response) {
                    $('#vincularTarefaModal').modal('hide'); // Fecha o modal após a operação
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: response,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload(); // Recarrega a página para refletir as alterações
                    });
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao vincular tarefa.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }


        $(document).ready(function() {
            $('#salvarGuiaBtn').click(function() {
                var formData = $('#formCriarGuia').serialize(); // Pega os dados do formulário

                $.ajax({
                    url: 'salvar_guia.php',
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Fechar o modal
                            $('#modalCriarGuia').modal('hide');
                            
                            // Abrir a guia de impressão em uma nova aba
                            window.open(response.url, '_blank');
                            
                            // Exibir mensagem de sucesso
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Guia de recebimento salva com sucesso!',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro: ' + response.message,
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao salvar a guia de recebimento.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });
        });


        $(document).ready(function() {
            $('#modalCriarGuia').on('hidden.bs.modal', function () {
                // Recarregar a página ao fechar o modal
                location.reload();
            });
        });

        function abrirModalEditarGuia(guiaId) {
            // Obter os dados da guia via AJAX
            $.ajax({
                url: 'get_edit.php',
                type: 'GET',
                dataType: 'json',
                data: { id: guiaId },
                success: function(response) {
                    if (response.success) {
                        // Preencher os campos do modal com os dados da guia
                        $('#editarGuiaId').val(response.data.id);
                        $('#editarCliente').val(response.data.cliente);
                        $('#editarDocumentoApresentante').val(response.data.documento_apresentante);
                        $('#editarDocumentosRecebidos').val(response.data.documentos_recebidos);
                        $('#editarObservacoes').val(response.data.observacoes);

                        // Abrir o modal
                        $('#modalEditarGuia').modal('show');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao buscar os dados da guia: ' + response.message,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao buscar os dados da guia.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }


    // Salvar as alterações do guia
    $(document).ready(function() {
        $('#salvarEdicaoGuiaBtn').click(function() {
            var formData = $('#formEditarGuia').serialize(); // Pega os dados do formulário

            $.ajax({
                url: 'salvar_edicao_guia.php',
                type: 'POST',
                dataType: 'json',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Fechar o modal
                        $('#modalEditarGuia').modal('hide');
                        
                        // Exibir mensagem de sucesso
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Alterações salvas com sucesso!',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Recarregar os dados
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro: ' + response.message,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao salvar as alterações da guia.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    });


    $(document).ready(function() {
        // Função para aplicar a máscara de CPF/CNPJ
        function aplicarMascaraCPF_CNPJ(element) {
            $(document).on('input', element, function() {
                var valor = $(this).val().replace(/\D/g, ''); // Remove todos os caracteres não numéricos
                $(this).val(valor);
            });

            // Aplicar a máscara ao perder o foco
            $(document).on('blur', element, function() {
                var valor = $(this).val().replace(/\D/g, ''); // Remove todos os caracteres não numéricos

                if (valor.length === 11) {
                    // Aplica a máscara de CPF se o valor tiver 11 dígitos
                    $(this).val(valor).mask('000.000.000-00');
                } else if (valor.length === 14) {
                    // Aplica a máscara de CNPJ se o valor tiver 14 dígitos
                    $(this).val(valor).mask('00.000.000/0000-00');
                } else {
                    // Se o valor não tiver 11 ou 14 dígitos, limpa o campo ou mostra uma mensagem
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Por favor, insira um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.',
                        confirmButtonText: 'OK'
                    });
                    $(this).val('');
                }
            });
        }


        // Aplicar a máscara ao campo CPF/CNPJ no modal "Criar Guia"
        aplicarMascaraCPF_CNPJ('#documentoApresentante');

        // Aplicar a máscara ao campo CPF/CNPJ no modal "Editar Guia"
        aplicarMascaraCPF_CNPJ('#editarDocumentoApresentante');
    });

    // Botão Salvar Guia
    document.getElementById('salvarGuiaBtn').addEventListener('click', function() {
        let form = document.getElementById('formCriarGuia');
        if (form.checkValidity()) {
            // Salvar os dados
            form.submit();
        } else {
            form.reportValidity(); // Exibe os avisos de campos obrigatórios
        }
    });

    // Botão Salvar Alterações
    document.getElementById('salvarEdicaoGuiaBtn').addEventListener('click', function() {
        let form = document.getElementById('formEditarGuia');
        if (form.checkValidity()) {
            // Salvar as alterações
            form.submit();
        } else {
            form.reportValidity(); // Exibe os avisos de campos obrigatórios
        }
    });


    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
