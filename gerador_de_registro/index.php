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
    <title>Atlas - Pesquisa de Cédulas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
    <style>
        .status-label {
            display: inline-block;
            padding: 0.2em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 2;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25em;
            width: 100px;
        }

        /* Dark mode styles */
        body.dark-mode .timeline::before { background: #444; }
        body.dark-mode .timeline-item .timeline-panel { background: #333; border-color: #444; color: #ddd; }
        body.dark-mode .timeline-item .timeline-panel::before { border-left-color: #444; }
        body.dark-mode .timeline-item .timeline-panel::after { border-left-color: #333; }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Pesquisa de Cédulas</h3>
        <hr>
        <form id="searchForm">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="n_cedula">Número da Cédula:</label>
                    <input type="text" class="form-control" id="n_cedula" name="n_cedula">
                </div>
                <div class="form-group col-md-3">
                    <label for="credor">Credor:</label>
                    <input type="text" class="form-control" id="credor" name="credor">
                </div>
                <div class="form-group col-md-3">
                    <label for="emitente">Emitente:</label>
                    <input type="text" class="form-control" id="emitente" name="emitente">
                </div>
                <div class="form-group col-md-3">
                    <label for="data_emissao">Data de Emissão:</label>
                    <input type="date" class="form-control" id="data_emissao" name="data_emissao">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="data_vencimento">Data de Vencimento:</label>
                    <input type="date" class="form-control" id="data_vencimento" name="data_vencimento">
                </div>
                <div class="form-group col-md-3">
                    <label for="valor_cedula">Valor da Cédula:</label>
                    <input type="text" class="form-control" id="valor_cedula" name="valor_cedula">
                </div>
                <div class="form-group col-md-3">
                    <label for="matricula">Matrícula:</label>
                    <input type="text" class="form-control" id="matricula" name="matricula">
                </div>
                <div class="form-group col-md-3">
                    <label for="funcionario">Funcionário:</label>
                    <select id="funcionario" name="funcionario" class="form-control">
                        <option value="">Selecione</option>
                        <?php
                        $sql = "SELECT DISTINCT funcionario FROM registros_cedulas";
                        $result = $conn->query($sql);
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['funcionario'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['funcionario'], ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="registro_garantia">Registro de Garantia:</label>
                    <input type="text" class="form-control" id="registro_garantia" name="registro_garantia">
                </div>
                <div class="form-group col-md-4">
                    <label for="forma_de_pagamento">Forma de Pagamento:</label>
                    <input type="text" class="form-control" id="forma_de_pagamento" name="forma_de_pagamento">
                </div>
                <div class="form-group col-md-4">
                    <label for="vencimento_antecipado">Vencimento Antecipado:</label>
                    <input type="text" class="form-control" id="vencimento_antecipado" name="vencimento_antecipado">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="juros">Juros:</label>
                    <input type="text" class="form-control" id="juros" name="juros">
                </div>
                <div class="form-group col-md-4">
                    <label for="titulo_cedula">Título da Cédula:</label>
                    <input type="text" class="form-control" id="titulo_cedula" name="titulo_cedula">
                </div>
                <div class="form-group col-md-4">
                    <label for="avalista">Avalista:</label>
                    <input type="text" class="form-control" id="avalista" name="avalista">
                </div>
            </div>
            <div class="form-group">
                <label for="imovel_localizacao">Imóvel de Localização dos Bens:</label>
                <input type="text" class="form-control" id="imovel_localizacao" name="imovel_localizacao">
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                </div>
                <div class="col-md-6 text-right">
                    <button id="add-button" type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='cadastro.php'"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar</button>
                </div>
            </div>
        </form>
        <div class="mt-3">
            <table class="table" style="zoom: 85%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Número da Cédula</th>
                        <th>Data de Emissão</th>
                        <th>Data de Vencimento</th>
                        <th>Valor</th>
                        <th>Credor</th>
                        <th>Emitente</th>
                        <th>Funcionário</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="cedulaTable">
                    <!-- Dados das cédulas serão inseridos aqui -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para visualizar detalhes da cédula -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">Detalhes da Cédula</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="view_n_cedula">Número da Cédula:</label>
                        <input type="text" class="form-control" id="view_n_cedula" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="view_emissao">Data de Emissão:</label>
                        <input type="text" class="form-control" id="view_emissao" readonly>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="view_valor">Valor da Cédula:</label>
                        <input type="text" class="form-control" id="view_valor" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="view_emitente">Emitente:</label>
                        <input type="text" class="form-control" id="view_emitente" readonly>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="view_credor">Credor:</label>
                        <input type="text" class="form-control" id="view_credor" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="view_registro">Registro:</label>
                    <div class="border p-3" id="view_registro" style="background-color: #f8f9fa; text-align: justify; border-radius: 5px; overflow-y: auto; height: 300px;"></div>
                </div>
                <button type="button" class="btn btn-primary" onclick="copyToClipboard()">Copiar</button>
                <button type="button" class="btn btn-primary" onclick="copyToClipboardHTML()">Copiar código fonte</button>
                <!-- <h4>Comentários e Anexos</h4>
                <div id="commentTimeline" class="timeline">
                    
                </div>
                <button type="button" class="btn btn-primary" id="addCommentButton" data-toggle="modal" data-target="#addCommentModal">Adicionar Comentário</button> -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script>

    $(document).ready(function() {
        // Carregar o modo do usuário
        $.ajax({
            url: '../load_mode.php',
            method: 'GET',
            success: function(mode) {
                $('body').removeClass('light-mode dark-mode').addClass(mode);
            }
        });

        // Função para alternar modos claro e escuro
        $('.mode-switch').on('click', function() {
            var body = $('body');
            body.toggleClass('dark-mode light-mode');

            var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
            $.ajax({
                url: '../save_mode.php',
                method: 'POST',
                data: { mode: mode },
                success: function(response) {
                    console.log(response);
                }
            });
        });

        // Aplicar máscara ao campo Valor da Cédula
        $('#valor_cedula').mask('#.##0,00', {reverse: true});

        // Enviar formulário de pesquisa
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: 'search_cedulas.php',
                type: 'GET',
                data: formData,
                success: function(response) {
                    var cedulas = JSON.parse(response);
                    var cedulaTable = $('#cedulaTable');
                    cedulaTable.empty();
                    cedulas.forEach(function(cedula) {
                        var actions = '<button class="btn btn-info btn-sm" onclick="viewCedula(' + cedula.id + ')"><i class="fa fa-eye" aria-hidden="true"></i></button> ';
                        actions += '<button class="btn btn-edit btn-sm" onclick="editCedula(' + cedula.id + ')"><i class="fa fa-pencil" aria-hidden="true"></i></button> ';
                        actions += '<button class="btn btn-delete btn-sm" onclick="deleteCedula(' + cedula.id + ')"><i class="fa fa-trash" aria-hidden="true"></i></button>';

                        var row = '<tr>' +
                            '<td>' + cedula.id + '</td>' +
                            '<td>' + cedula.n_cedula + '</td>' +
                            '<td>' + formatDate(cedula.emissao_cedula) + '</td>' +
                            '<td>' + formatDate(cedula.vencimento_cedula) + '</td>' +
                            '<td>' + parseFloat(cedula.valor_cedula).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + '</td>' +
                            '<td>' + cedula.credor + '</td>' +
                            '<td>' + cedula.emitente + '</td>' +
                            '<td>' + cedula.funcionario + '</td>' +
                            '<td>' + actions + '</td>' +
                            '</tr>';
                        cedulaTable.append(row);
                    });
                },
                error: function() {
                    alert('Erro ao buscar as cédulas');
                }
            });
        });
    });

    function formatDate(dateString) {
        var date = new Date(dateString);
        return ("0" + date.getDate()).slice(-2) + "/" + ("0" + (date.getMonth() + 1)).slice(-2) + "/" + date.getFullYear();
    }

    function viewCedula(cedulaId) {
        $.ajax({
            url: 'get_cedula.php',
            type: 'GET',
            data: { id: cedulaId },
            success: function(response) {
                var cedula = JSON.parse(response);
                $('#view_n_cedula').val(cedula.n_cedula);
                $('#view_credor').val(cedula.credor);
                $('#view_emitente').val(cedula.emitente);
                $('#view_emissao').val(formatDate(cedula.emissao_cedula));
                $('#view_valor').val(parseFloat(cedula.valor_cedula).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
                
                var registroContent = 'Nos termos do art. 178, da Lei n.°6.015/1973, procedo ao registro do Penhor Rural, conforme consta, por extrato, do título de crédito a seguir descriminado: <b>1. Título: ' + cedula.titulo_cedula + 
                    ' - Nr.: ' + cedula.n_cedula + 
                    '</b>, emitida em: ' + formatDate(cedula.emissao_cedula) + 
                    '; <b>2. Emitente:</b> ' + cedula.emitente + 
                    '; <b>3. Vencimento:</b> Em ' + formatDate(cedula.vencimento_cedula) + 
                    '; <b>4. Valor:</b> ' + parseFloat(cedula.valor_cedula).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + 
                    '; <b>5. Forma de Pagamento:</b> ' + cleanText(cedula.forma_de_pagamento) + 
                    '; <b>6. Credor:</b> ' + cedula.credor + 
                    '; <b>7. Encargos:</b> ' + cleanText(cedula.juros) + 
                    '; <b>8. Garantias:</b> ' + cleanText(cedula.registro_garantia) + 
                    '; <b>9. Vencimento Antecipado:</b> ' + cleanText(cedula.vencimento_antecipado) + 
                    '; <b>10. Avalista:</b> ' + cleanText(cedula.avalista) + 
                    '; <b>11. Imóvel de Localização dos Bens:</b> ' + cleanText(cedula.imovel_localizacao) + 
                    '; <b>12. Demais condições:</b> fazem parte do presente registro todas as cláusulas e demais condições constantes do referido título e aqui não transcritas, vez que via não negociável ficará arquivada nesta serventia. Para fins de cálculos dos Emolumentos foi utilizado o valor nominal da cédula ' + parseFloat(cedula.valor_cedula).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + '. O referido é verdade e dou fé. '  + cedula.funcionario;
                
                $('#view_registro').html(registroContent);
                $('#viewModal').modal('show');
            },
            error: function() {
                alert('Erro ao buscar detalhes da cédula');
            }
        });
    }

    function cleanText(text) {
        return text.replace(/(\r\n|\n|\r)/gm, " ").replace(/(<([^>]+)>)/gi, "").replace(/\s+/g, ' ').trim();
    }

    function copyToClipboard() {
        var content = document.getElementById('view_registro').innerText;
        navigator.clipboard.writeText(content).then(function() {
            alert('Texto copiado para a área de transferência');
        }, function(err) {
            alert('Erro ao copiar o texto: ', err);
        });
    }

    function copyToClipboardHTML() {
        var content = document.getElementById('view_registro').innerHTML;
        navigator.clipboard.writeText(content).then(function() {
            alert('Código fonte copiado para a área de transferência');
        }, function(err) {
            alert('Erro ao copiar o código fonte: ', err);
        });
    }

    function editCedula(cedulaId) {
        window.location.href = 'edit_cedula.php?id=' + cedulaId;
    }

    function deleteCedula(cedulaId) {
        if (confirm('Tem certeza que deseja excluir esta cédula?')) {
            $.ajax({
                url: 'delete_cedula.php',
                type: 'POST',
                data: { id: cedulaId },
                success: function(response) {
                    alert('Cédula excluída com sucesso');
                    $('#searchForm').submit(); // Recarregar a lista de cédulas
                },
                error: function() {
                    alert('Erro ao excluir a cédula');
                }
            });
        }
    }
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
