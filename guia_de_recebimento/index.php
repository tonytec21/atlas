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

        #checklistSelect {
            max-width: 100%; /* Garante que o select não ultrapasse o modal */
            overflow: hidden; /* Esconde o conteúdo extra */
            text-overflow: ellipsis; /* Adiciona reticências (...) quando necessário */
            white-space: nowrap; /* Mantém o texto em uma única linha */
            display: block;
        }


    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-center align-items-center text-center mb-3">
                <div class="col-md-auto mb-2">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalCriarGuia">
                        <i class="fa fa-plus"></i> Criar Guia de Recebimento</button>
                </div>
                <div class="col-md-auto mb-2">
                    <a href="../checklist/checklist.php" class="btn btn-primary">
                        <i class="fa fa-folder-open"></i> Checklists
                    </a>
                </div>

                <div class="col-md-auto mb-2">
                    <a href="../tarefas/consulta-tarefas.php" class="btn btn-secondary mx-2">
                        <i class="fa fa-search" aria-hidden="true"></i> Pesquisar Tarefas
                    </a>
                </div>
            </div>
        <hr> 

            <div class="d-flex justify-content-center align-items-center text-center mb-3">
                <h3>Pesquisa de Guias de Recebimento</h3>
                <button id="btnTriagemComunitario" style="display: none;" class="btn btn-secondary" onclick="abrirTriagem()">Triagem Comunitário</button>
            </div>
            <hr>
            <form id="searchForm">
    <div class="form-row">
        <!-- Campo para Número da Guia -->
        <div class="form-group col-md-2">
            <label for="numeroGuia">Nº Guia:</label>
            <input type="text" class="form-control" id="numeroGuia" name="numeroGuia" placeholder="Nº da Guia">
        </div>

        <!-- Campo para Número da Tarefa -->
        <div class="form-group col-md-2">
            <label for="numeroTarefa">Nº Tarefa:</label>
            <input type="text" class="form-control" id="numeroTarefa" name="numeroTarefa" placeholder="Nº da Tarefa">
        </div>
        <div class="form-group col-md-3">
            <label for="dataRecebimento">Data de Recebimento:</label>
            <input type="date" class="form-control" id="dataRecebimento" name="dataRecebimento">
        </div>
        <div class="form-group col-md-5">
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

        <div class="form-group col-md-8">
            <label for="cliente">Apresentante:</label>
            <input type="text" class="form-control" id="cliente" name="cliente" placeholder="Nome do Cliente">
        </div>
        <div class="form-group col-md-4">
            <label for="documentoApresentante">CPF/CNPJ:</label>
            <input type="text" class="form-control" id="documentoApresentante" name="documentoApresentante" placeholder="Digite o CPF ou CNPJ">
        </div>
        <div class="form-group col-md-8">
            <label for="nomePortador">Nome do Portador de Dados:</label>
            <input type="text" class="form-control" id="nomePortador" name="nomePortador" placeholder="Nome do Portador de Dados">
        </div>
        <div class="form-group col-md-4">
            <label for="documentoPortador">CPF/CNPJ do Portador de Dados:</label>
            <input type="text" class="form-control" id="documentoPortador" name="documentoPortador" placeholder="Digite o CPF ou CNPJ">
        </div>

        <div class="form-group col-md-12">
            <!-- Botão de Filtrar (mantém o tipo submit) -->
            <button type="submit" class="btn btn-primary w-100" style="margin-top: 2px;"><i class="fa fa-filter"></i> Filtrar</button>
        </div>
    </div>
</form>


            <hr>
            <div class="table-responsive">
                <h5>Resultados da Pesquisa</h5>
                <table id="resultadosTabela" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th style="width: 6%">Nº Guia</th>
                            <th style="width: 7%">Nº Tarefa</th>
                            <th style="width: 15%">Apresentante</th>
                            <th style="width: 11%">CPF/CNPJ</th>
                            <th style="width: 15%">Portador de Dados</th>
                            <th style="width: 11%">Documento do Portador</th>
                            <th style="width: 15%">Funcionário</th>
                            <th style="width: 12%">Data de Recebimento</th>
                            <!-- <th style="width: 16%">Documentos Recebidos</th> -->
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
        <div class="modal-dialog modal-lg" role="document">
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
                        <input type="hidden" id="guiaId" name="guiaId">
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

    <!-- Modal Criar Guia -->
    <div class="modal fade" id="modalCriarGuia" tabindex="-1" aria-labelledby="modalCriarGuiaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xxl"> <!-- Aumentado para um tamanho extra grande -->
            <div class="modal-content shadow-lg rounded">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalCriarGuiaLabel"><i class="fa fa-file-text"></i> Criar Guia de Recebimento</h5>
                    <button type="button" class="btn-close text-white" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formCriarGuia">
                        
                        <!-- Seleção de Checklist -->
                        <div class="form-group">
                            <label for="checklistSelect"><i class="fa fa-list-alt"></i> Utilizar Checklist:</label>
                            <select class="form-control custom-select" id="checklistSelect" name="checklistSelect">
                                <option value="">Selecione um Checklist</option>
                                <?php
                                $sqlChecklists = "SELECT id, titulo FROM checklists WHERE status != 'removido' ORDER BY titulo ASC";
                                $resultChecklists = $conn->query($sqlChecklists);
                                if ($resultChecklists->num_rows > 0) {
                                    while ($row = $resultChecklists->fetch_assoc()) {
                                        echo "<option value='" . $row['id'] . "' title='" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "'>" . 
                                            htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . 
                                            "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Informações do Apresentante -->
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="cliente"><i class="fa fa-user"></i> Apresentante:</label>
                                <input type="text" class="form-control" id="cliente" name="cliente" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="documentoApresentante"><i class="fa fa-id-card"></i> CPF/CNPJ:</label>
                                <input type="text" class="form-control" id="documentoApresentante" name="documentoApresentante">
                            </div>
                        </div>

                        <!-- Informações do Portador -->
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="nome_portador"><i class="fa fa-user-circle"></i> Portador de Dados:</label>
                                <input type="text" class="form-control" id="nome_portador" name="nome_portador" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="documento_portador"><i class="fa fa-id-badge"></i> CPF/CNPJ:</label>
                                <input type="text" class="form-control" id="documento_portador" name="documento_portador">
                            </div>
                        </div>

                        <!-- Campo para Documentos Recebidos -->
                        <div class="form-group">
                            <label for="documentosRecebidos"><i class="fa fa-folder-open"></i> Documentos Recebidos:</label>
                            <textarea class="form-control" id="documentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>
                        </div>

                        <!-- Campo para Observações -->
                        <div class="form-group">
                            <label for="observacoes"><i class="fa fa-sticky-note"></i> Observações:</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>

                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal"><i class="fa fa-times"></i> Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" id="salvarGuiaBtn"><i class="fa fa-save"></i> Salvar Guia</button>
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

                        <!-- Linha para Nome e Documento do Portador -->
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="editarNomePortador">Portador de Dados:</label>
                                <input type="text" class="form-control" id="editarNomePortador" name="nome_portador">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarDocumentoPortador">CPF/CNPJ:</label>
                                <input type="text" class="form-control" id="editarDocumentoPortador" name="documento_portador">
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
                var formData = $(this).serialize(); // Captura todos os campos, incluindo CPF/CNPJ e Nome do Portador

                console.log(formData); // Verifique no console se todos os filtros estão sendo enviados corretamente

                var hasFilters = $('#cliente').val() || $('#funcionario').val() || $('#dataRecebimento').val() || $('#documentoApresentante').val() || $('#numeroGuia').val() || $('#numeroTarefa').val() || $('#nomePortador').val() || $('#documentoPortador').val();

                if (!hasFilters) {
                    loadGuias('all');  // Se nenhum filtro foi aplicado, carregar todos os dados
                } else {
                    loadGuias(null, formData);  // Se houver filtros, aplicar a pesquisa corretamente
                }
            });

            // Função para carregar guias de recebimento
            function loadGuias(action = 'task_id_zero', formData = '') {
            if (!formData) {
                formData = $('#searchForm').serialize();
            }

            $.ajax({
                url: 'search_guia_recebimento.php',
                type: 'GET',
                dataType: 'json',
                data: formData + '&action=' + action,
                success: function(response) {
                    console.log(response);
                    dataTable.clear().draw();

                        if (response.length > 0) {
                            response.forEach(function(guia) {

                                function formatarDataBrasileira(dataString) {
                                    var data = new Date(dataString.replace(' ', 'T'));
                                    if (isNaN(data.getTime())) {
                                        return dataString;
                                    }
                                    return data.toLocaleString('pt-BR', {
                                        year: 'numeric',
                                        month: '2-digit',
                                        day: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit'
                                    });
                                }

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

                                async function visualizarGuia(taskId, guiaId) {
                                    const timbrado = await verificarTimbrado();
                                    if (!taskId || taskId == 0) {
                                        if (timbrado === 'S') {
                                            window.open(`guia_recebimento.php?id=${guiaId}`, '_blank');
                                        } else if (timbrado === 'N') {
                                            window.open(`guia-recebimento.php?id=${guiaId}`, '_blank');
                                        } else {
                                            alert('Erro: Não foi possível determinar o tipo de guia de recebimento.');
                                        }
                                    } else {
                                        if (timbrado === 'S') {
                                            window.open(`guia_recebimento.php?id=${guiaId}`, '_blank');
                                        } else if (timbrado === 'N') {
                                            window.open(`guia-recebimento.php?id=${guiaId}`, '_blank');
                                        } else {
                                            alert('Erro: Não foi possível determinar o tipo de guia de recebimento.');
                                        }
                                    }
                                }

                                var acoes = '';

                                // Verificar o acesso do usuário antes de exibir os botões
                                $.ajax({
                                    url: 'verificar_acesso.php',
                                    type: 'GET',
                                    dataType: 'json',
                                    success: function(accessResponse) {
                                        const temAcesso = accessResponse.tem_acesso;

                                        if (!guia.task_id || guia.task_id == 0) {
                                            acoes = `
                                                <button class="btn btn-primary btn-sm btn-print" data-task-id="${guia.task_id}" data-guia-id="${guia.id}" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Imprimir Guia de Recebimento"><i class="fa fa-print" aria-hidden="true"></i></button>
                                            `;
                                            if (temAcesso) {
                                                acoes += `
                                                    <button class="btn btn-success btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Criar Tarefa" onclick='abrirModalTarefa(${guia.id}, ${JSON.stringify(guia.cliente)}, ${JSON.stringify(guia.documentos_recebidos)})'><i class="fa fa-clock-o" aria-hidden="true"></i></button>
                                                    <button class="btn btn-secondary btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Vincular Tarefa" onclick="abrirModalVincularTarefa(${guia.id})"><i class="fa fa-link" aria-hidden="true"></i></button>
                                                `;
                                            }
                                            acoes += `
                                                <button class="btn btn-warning btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Editar Guia" onclick="abrirModalEditarGuia(${guia.id})"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                            `;
                                        } else {
                                            acoes = `
                                                <button class="btn btn-primary btn-sm btn-print" data-task-id="${guia.task_id}" data-guia-id="${guia.id}" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Imprimir Guia de Recebimento"><i class="fa fa-print" aria-hidden="true"></i></button>
                                                <button class="btn btn-info btn-sm" title="Visualizar Tarefa" onclick="window.location.href='../tarefas/index_tarefa.php?token=${guia.task_token}'"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                                <button class="btn btn-warning btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" title="Editar Guia" onclick="abrirModalEditarGuia(${guia.id})"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                            `;
                                        }

                                        dataTable.row.add([
                                            guia.id,
                                            guia.task_id || '-',
                                            guia.cliente,
                                            guia.documento_apresentante,
                                            guia.nome_portador || '-',
                                            guia.documento_portador || '-',
                                            guia.funcionario,
                                            formatarDataBrasileira(guia.data_recebimento),
                                            // guia.documentos_recebidos,
                                            guia.observacoes,
                                            acoes
                                        ]).draw();

                                        $('.btn-print').off('click').on('click', function() {
                                            const taskId = $(this).data('task-id');
                                            const guiaId = $(this).data('guia-id');
                                            visualizarGuia(taskId, guiaId);
                                        });
                                    },
                                    error: function() {
                                        console.error('Erro ao verificar o acesso do usuário.');
                                    }
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
                    if (response.includes('Sucesso')) {
                        $('#vincularTarefaModal').modal('hide'); // Fecha o modal após a operação
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: response,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload(); // Recarrega a página para refletir as alterações
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: response,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao conectar com o servidor.',
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
                        $('#editarNomePortador').val(response.data.nome_portador);
                        $('#editarDocumentoPortador').val(response.data.documento_portador);
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
        aplicarMascaraCPF_CNPJ('#documento_portador');
        aplicarMascaraCPF_CNPJ('#documentoPortador')

        // Aplicar a máscara ao campo CPF/CNPJ no modal "Editar Guia"
        aplicarMascaraCPF_CNPJ('#editarDocumentoApresentante');
        aplicarMascaraCPF_CNPJ('#editarDocumentoPortador');
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

    $(document).ready(function() {
        var currentYear = new Date().getFullYear();

        // Função de validação de data
        function validateDate(input) {
            var selectedDate = new Date($(input).val());
            if (selectedDate.getFullYear() > currentYear) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data inválida',
                    text: 'O ano não pode ser maior que o ano atual.',
                    confirmButtonText: 'Ok'
                });
                $(input).val(''); // Limpa o campo da data
            }
        }

        // Aplicar a validação de data nos campos de filtro de pesquisa
        $('#dataRecebimento').on('change', function() {
            // Certifique-se de que há um valor antes de validar
            if ($(this).val()) {
                validateDate(this);
            }
        });
    });

    // Função para carregar e verificar o JSON
    document.addEventListener('DOMContentLoaded', function() {
        fetch('config_guia_de_recebimento.json')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao carregar o JSON.');
                }
                return response.json();
            })
            .then(config => {
                if (config.triagem_comunitario_ativo === 'S') {
                    document.getElementById('btnTriagemComunitario').style.display = 'inline-block';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            });
    });

    // Função para abrir o diretório correto com base no tipo de conexão
    function abrirTriagem() {
        fetch('config_guia_de_recebimento.json')
            .then(response => response.json())
            .then(config => {
                if (config.tipo_de_conexao === 'local') {
                    window.location.href = 'triagem_comunitario/index.php';
                } else if (config.tipo_de_conexao === 'online') {
                    window.location.href = 'triagem_comunitario_online/index.php';
                }
            })
            .catch(error => {
                console.error('Erro ao abrir o diretório:', error);
            });
    }


    $(document).ready(function () {
        $('#checklistSelect').change(function () {
            var checklistId = $(this).val();

            if (checklistId !== '') {
                $.ajax({
                    url: '../checklist/carregar_checklist.php',
                    type: 'GET',
                    data: { id: checklistId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.itens && response.itens.length > 0) {
                            // Formata os itens substituindo quebras de linha por ", "
                            var documentosRecebidos = response.itens.join("; ");

                            // Verifica se já há documentos inseridos manualmente
                            var campoDocumentos = $('#documentosRecebidos').val().trim();
                            if (campoDocumentos !== '') {
                                // Se já houver conteúdo, adiciona os novos itens sem sobrescrever
                                $('#documentosRecebidos').val(campoDocumentos + ", " + documentosRecebidos);
                            } else {
                                $('#documentosRecebidos').val(documentosRecebidos);
                            }
                        } else {
                            Swal.fire('Atenção', 'Este checklist não possui itens cadastrados.', 'warning');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro', 'Erro ao carregar os itens do checklist.', 'error');
                    }
                });
            }
        });
    });

    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
