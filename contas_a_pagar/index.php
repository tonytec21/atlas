<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Função para atualizar recorrências automaticamente
function atualizarRecorrencias($conn) {
    // Atualiza contas com recorrência mensal
    $sql_mensal = "UPDATE contas_a_pagar 
                   SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 MONTH), status = 'Pendente'
                   WHERE recorrencia = 'Mensal' AND status = 'Pago' AND data_vencimento < CURDATE()";
    $conn->query($sql_mensal);

    // Atualiza contas com recorrência semanal
    $sql_semanal = "UPDATE contas_a_pagar 
                    SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 WEEK), status = 'Pendente'
                    WHERE recorrencia = 'Semanal' AND status = 'Pago' AND data_vencimento < CURDATE()";
    $conn->query($sql_semanal);

    // Atualiza contas com recorrência anual
    $sql_anual = "UPDATE contas_a_pagar 
                  SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 YEAR), status = 'Pendente'
                  WHERE recorrencia = 'Anual' AND status = 'Pago' AND data_vencimento < CURDATE()";
    $conn->query($sql_anual);
}

// Atualizar as contas recorrentes
atualizarRecorrencias($conn);

// Buscando contas prestes a vencer (com 1 dia de antecedência) e vencidas
$sql_prestes_vencer = "SELECT * FROM contas_a_pagar WHERE data_vencimento = CURDATE() + INTERVAL 1 DAY AND status = 'Pendente'";
$sql_vencidas = "SELECT * FROM contas_a_pagar WHERE data_vencimento < CURDATE() AND status = 'Pendente'";

$contas_prestes_vencer = $conn->query($sql_prestes_vencer)->fetch_all(MYSQLI_ASSOC);
$contas_vencidas = $conn->query($sql_vencidas)->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Pagar</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <style>
        .vencida {
            background-color: #f8d7da !important; /* Vermelha clara */
        }
        .prestes-vencer {
            background-color: #fff3cd !important; /* Amarela clara */
        }
        .btn-success2 {
            margin-bottom: 5px;
            font-size: 20px;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            border: none;
        }
    </style>

</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Contas a Pagar</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="titulo">Título:</label>
                        <input type="text" class="form-control" id="titulo" name="titulo">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="valor">Valor:</label>
                        <input type="text" class="form-control" id="valor" name="valor">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="data_vencimento">Data de Vencimento:</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="recorrencia">Recorrência:</label>
                        <select class="form-control" id="recorrencia" name="recorrencia">
                            <option value="">Todas</option>
                            <option value="Nenhuma">Nenhuma</option>
                            <option value="Mensal">Mensal</option>
                            <option value="Semanal">Semanal</option>
                            <option value="Anual">Anual</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="status">Status:</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Pendente</option>
                            <option value="Pago">Pago</option>
                        </select>
                    </div>
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                    </div>
                </div>
                <div class="row mb-12">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-primary">
                            <i class="fa fa-filter" aria-hidden="true"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" style="width: 100%; color: #fff!important" class="btn btn-success" onclick="window.location.href='cadastrar.php'">
                            <i class="fa fa-plus" aria-hidden="true"></i> Cadastrar Conta
                        </button>
                    </div>
                </div>

            </form>
            <hr>
            <div id="resultados">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 80%">
                    <thead>
                        <tr>
                       

                            <th style="width: 20%;">Título</th>
                            <th style="width: 10%;">Valor</th>
                            <th style="width: 15%;">Data de Vencimento</th>
                            <th style="width: 25%;">Descrição</th>
                            <th style="width: 10%;">Recorrência</th>
                            <th style="width: 8%;">Status</th>
                            <th style="width: 12%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conditions = [];
                        $params = [];
                        $filtered = false;

                        if (!empty($_GET['titulo'])) {
                            $conditions[] = "titulo LIKE ?";
                            $params[] = '%' . $_GET['titulo'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['valor'])) {
                            $conditions[] = "valor = ?";
                            $params[] = $_GET['valor'];
                            $filtered = true;
                        }
                        if (!empty($_GET['data_vencimento'])) {
                            $conditions[] = "data_vencimento = ?";
                            $params[] = $_GET['data_vencimento'];
                            $filtered = true;
                        }
                        if (!empty($_GET['recorrencia'])) {
                            $conditions[] = "recorrencia = ?";
                            $params[] = $_GET['recorrencia'];
                            $filtered = true;
                        }
                        if (!empty($_GET['status']) && $_GET['status'] == 'Pago') {
                            $sql = "SELECT * FROM contas_pagas WHERE 1=1";
                        } else {
                            $conditions[] = "status != 'Pago' AND status != 'Cancelado'";
                            $sql = "SELECT * FROM contas_a_pagar WHERE 1=1";
                        }

                        if (!empty($_GET['descricao'])) {
                            $conditions[] = "descricao LIKE ?";
                            $params[] = '%' . $_GET['descricao'] . '%';
                            $filtered = true;
                        }

                        if ($conditions) {
                            $sql .= ' AND ' . implode(' AND ', $conditions);
                        }
                        $sql .= ' ORDER BY data_vencimento ASC';

                        $stmt = $conn->prepare($sql);
                        if (!empty($params)) {
                            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($conta = $result->fetch_assoc()) {
                            ?>
                            <tr class="<?php echo (strtotime($conta['data_vencimento']) < strtotime('today')) ? 'vencida' : (strtotime($conta['data_vencimento']) == strtotime('+1 day') ? 'prestes-vencer' : ''); ?>">
                                <td><?php echo $conta['titulo']; ?></td>
                                <td><?php echo 'R$ ' . number_format($conta['valor'], 2, ',', '.'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                                <td><?php echo $conta['descricao']; ?></td>
                                <td><?php echo $conta['recorrencia']; ?></td>
                                <td><?php echo $conta['status']; ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" title="Visualizar Anexo" onclick="visualizarAnexo('<?php echo $conta['caminho_anexo']; ?>')" style="margin-bottom: 5px;"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-edit btn-sm" title="Editar Conta" onclick="editarConta('<?php echo $conta['id']; ?>')" style="margin-bottom: 5px;"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                    <button class="btn btn-delete btn-sm" title="Cancelar Conta" onclick="excluirConta('<?php echo $conta['id']; ?>')" style="margin-bottom: 5px;"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                    <button class="btn btn-success2 btn-sm" title="Definir como Pago" onclick="definirComoPago('<?php echo $conta['id']; ?>')" style="margin-bottom: 5px;"><i class="fa fa-check" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para visualização do anexo -->
    <div class="modal fade" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" style="max-width: 70%;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="visualizarModalLabel">Visualizar Anexo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="anexo_visualizacao" style="width: 100%; height: 600px;" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Contas Vencidas e Prestes a Vencer -->
    <div class="modal fade" id="contasModal" tabindex="-1" role="dialog" aria-labelledby="contasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contasModalLabel">Contas Vencidas e Prestes a Vencer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h5>Contas Vencidas</h5>
                    <?php if (!empty($contas_vencidas)) { ?>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Valor</th>
                                    <th>Data de Vencimento</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contas_vencidas as $conta) { ?>
                                    <tr class="vencida">
                                        <td><?php echo $conta['titulo']; ?></td>
                                        <td><?php echo 'R$ ' . number_format($conta['valor'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                                        <td><?php echo $conta['status']; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p>Nenhuma conta vencida.</p>
                    <?php } ?>

                    <h5>Contas Prestes a Vencer</h5>
                    <?php if (!empty($contas_prestes_vencer)) { ?>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Valor</th>
                                    <th>Data de Vencimento</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contas_prestes_vencer as $conta) { ?>
                                    <tr class="prestes-vencer">
                                        <td><?php echo $conta['titulo']; ?></td>
                                        <td><?php echo 'R$ ' . number_format($conta['valor'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                                        <td><?php echo $conta['status']; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p>Nenhuma conta prestes a vencer.</p>
                    <?php } ?>

                </div>


                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializa a máscara de moeda no campo de valor
            $('#valor').mask('000.000.000.000.000,00', {reverse: true});

        });

        $(document).ready(function() {
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                }
            });
        });

        // Função para visualizar o anexo em um modal
        function visualizarAnexo(caminhoAnexo) {
            if (caminhoAnexo) {
                $('#anexo_visualizacao').attr('src', caminhoAnexo);
                $('#visualizarModal').modal('show');
            } else {
                alert('Nenhum anexo disponível para esta conta.');
            }
        }

        // Função para editar a conta
        function editarConta(id) {
            window.location.href = 'editar_conta.php?id=' + id;
        }

        // Função para cancelar a conta (definir status como "Cancelado")
        function excluirConta(id) {
            if (confirm('Tem certeza que deseja cancelar esta conta?')) {
                $.ajax({
                    url: 'excluir_conta.php',
                    type: 'POST',
                    data: { id: id },
                    success: function(response) {
                        var res = JSON.parse(response);
                        if (res.success) {
                            alert('Conta cancelada com sucesso.');
                            window.location.reload();
                        } else {
                            alert('Erro ao cancelar a conta: ' + res.message);
                        }
                    },
                    error: function() {
                        alert('Erro ao cancelar a conta.');
                    }
                });
            }
        }

        // Função para definir a conta como paga
        function definirComoPago(id) {
            if (confirm('Tem certeza que deseja definir esta conta como paga?')) {
                $.ajax({
                    url: 'definir_pago.php',
                    type: 'POST',
                    data: { id: id },
                    success: function(response) {
                        var res = JSON.parse(response);
                        if (res.success) {
                            alert('Conta marcada como paga.');
                            window.location.reload();
                        } else {
                            alert('Erro ao marcar a conta como paga: ' + res.message);
                        }
                    },
                    error: function() {
                        alert('Erro ao marcar a conta como paga.');
                    }
                });
            }
        }

        $(document).ready(function() {
            // Abre o modal automaticamente ao carregar a página, se houver contas vencidas ou prestes a vencer
            <?php if (!empty($contas_prestes_vencer) || !empty($contas_vencidas)) { ?>
                $('#contasModal').modal('show');
            <?php } ?>
        });


    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>