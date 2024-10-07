<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

// Verifique se a conexão está definida
if (!isset($conn)) {
    die("Erro ao conectar ao banco de dados");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['title'];
    $categoria = $_POST['category'];
    $data_limite = $_POST['deadline'];
    $funcionario_responsavel = $_POST['employee'];
    $origem = $_POST['origin'];
    $descricao = $_POST['description'];
    $criado_por = $_POST['createdBy'];
    $data_criacao = $_POST['createdAt'];
    $token = md5(uniqid(rand(), true));
    $caminho_anexo = '';

    // Verifica se há arquivos anexados
    if (!empty($_FILES['attachments']['name'][0])) {
        $targetDir = "../tarefas/arquivos/$token/";
        $fullTargetDir = __DIR__ . $targetDir;
        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }

        foreach ($_FILES['attachments']['name'] as $key => $name) {
            $targetFile = $fullTargetDir . basename($name);
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $targetFile)) {
                $caminho_anexo .= "$targetDir" . basename($name) . ";";
            }
        }
        // Remover o ponto e vírgula final
        $caminho_anexo = rtrim($caminho_anexo, ';');
    }

    // Inserir dados da tarefa no banco de dados
    $sql = "INSERT INTO tarefas (token, titulo, categoria, origem, descricao, data_limite, funcionario_responsavel, criado_por, data_criacao, caminho_anexo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $token, $titulo, $categoria, $origem, $descricao, $data_limite, $funcionario_responsavel, $criado_por, $data_criacao, $caminho_anexo);

    if ($stmt->execute()) {
        // Capturar o ID da tarefa recém-inserida
        $last_id = $stmt->insert_id;
        header("Location: edit_task.php?id=$last_id");
    } else {
        echo "Erro ao salvar a tarefa: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

// Buscar dados da OS
$os_id = $_GET['id'];
$os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
$os_query->bind_param("i", $os_id);
$os_query->execute();
$os_result = $os_query->get_result();
$ordem_servico = $os_result->fetch_assoc();

// Buscar dados dos itens da OS
$os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
$os_items_query->bind_param("i", $os_id);
$os_items_query->execute();
$os_items_result = $os_items_query->get_result();
$ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados dos pagamentos
$pagamentos_query = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
$pagamentos_query->bind_param("i", $os_id);
$pagamentos_query->execute();
$pagamentos_result = $pagamentos_query->get_result();
$pagamentos = $pagamentos_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados das devoluções
$devolucoes_query = $conn->prepare("SELECT * FROM devolucao_os WHERE ordem_de_servico_id = ?");
$devolucoes_query->bind_param("i", $os_id);
$devolucoes_query->execute();
$devolucoes_result = $devolucoes_query->get_result();
$devolucoes = $devolucoes_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados dos atos liquidados
$atos_liquidados_query = $conn->prepare("SELECT SUM(total) as total_liquidado FROM atos_liquidados WHERE ordem_servico_id = ?");
$atos_liquidados_query->bind_param("i", $os_id);
$atos_liquidados_query->execute();
$atos_liquidados_result = $atos_liquidados_query->get_result();
$atos_liquidados = $atos_liquidados_result->fetch_assoc();
$total_liquidado = $atos_liquidados['total_liquidado'] ?? 0.0; // Valor padrão 0.0 se nenhum ato for liquidado

// Verificar se há itens liquidados
$has_liquidated = false;
$has_ato_17 = false;
foreach ($ordem_servico_itens as $item) {
    if ($item['status'] == 'liquidado') {
        $has_liquidated = true;
    }
    if (strpos($item['ato'], '17.') === 0) {
        $has_ato_17 = true;
    }
}

// Calcular total dos pagamentos
$total_pagamentos = 0;
foreach ($pagamentos as $pagamento) {
    $total_pagamentos += $pagamento['total_pagamento'];
}

// Calcular total das devoluções
$total_devolucoes = 0;
foreach ($devolucoes as $devolucao) {
    $total_devolucoes += $devolucao['total_devolucao'];
}

// Calcular total dos repasses
$total_repasses = 0;
$repasse_query = $conn->prepare("SELECT total_repasse FROM repasse_credor WHERE ordem_de_servico_id = ?");
$repasse_query->bind_param("i", $os_id);
$repasse_query->execute();
$repasse_result = $repasse_query->get_result();
while ($repasse = $repasse_result->fetch_assoc()) {
    $total_repasses += $repasse['total_repasse'];
}

// Calcular valor líquido pago
$valor_pago_liquido = $total_pagamentos - $total_devolucoes;

// Calcular saldo
$saldo = $valor_pago_liquido - $ordem_servico['total_os'] - $total_repasses;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Ordem de Serviço</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../style/sweetalert2.min.css">
    <style>
        .situacao-pago {
            background-color: #28a745;
            color: white;
            width: 290px;
            text-align: center;
            padding: 2px 10px;
            border-radius: 5px;
            display: inline-block;
            font-size: 13px;
        }

        .custom-file-input ~ .custom-file-label::after {
            content: "Escolher";
        }

        .situacao-ativo {
            background-color: #ffa907; 
            color: white;
            width: 290px;
            text-align: center;
            padding: 2px 10px;
            border-radius: 5px;
            display: inline-block;
            font-size: 13px;
        }

        .situacao-cancelado {
            background-color: #dc3545;
            color: white;
            width: 290px;
            text-align: center;
            padding: 2px 10px;
            border-radius: 5px;
            display: inline-block;
            font-size: 13px;
        }
        /* Remover a borda de foco no botão de fechar */
        .btn-close {
            outline: none; /* Remove a borda ao clicar */
            border: none; /* Remove qualquer borda padrão */
            background: none; /* Remove o fundo padrão */
            padding: 0; /* Remove o espaçamento extra */
            font-size: 1.5rem; /* Ajuste o tamanho do ícone */
            cursor: pointer; /* Mostra o ponteiro de clique */
            transition: transform 0.2s ease; /* Suaviza a transição do hover */
        }

        /* Aumentar o tamanho do botão em 5% no hover */
        .btn-close:hover {
            transform: scale(2.10); /* Aumenta 5% */
        }

        /* Opcional: Adicionar foco suave sem borda visível */
        .btn-close:focus {
            outline: none; /* Remove a borda ao foco */
        }
        .btn-print, .btn-payment, .btn-repasse {
            margin-left: 10px;
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-dialog {
            max-width: 400px;
            margin: 1.75rem auto;
        }
        .modal-header {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .modal-footer {
            border-top: none;
        }
        .modal-header.error {
            background-color: #dc3545; /* cor de fundo vermelha para erros */
            color: white;
        }
        .modal-header.success {
            background-color: #28a745; /* cor de fundo verde para sucessos */
            color: white;
        }
        .status-label {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            text-align: center;
            white-space: nowrap; /* Adicionado para evitar quebra de linha */
        }
        .status-pendente {
            background-color: #dc3545; /* vermelho */
        }
        .status-liquidado {
            background-color: #28a745; /* verde */
        }
        .status-parcial {
            background-color: #ffa907; /* amarelo */
        }
        /* CSS personalizado para aumentar o tamanho do modal de criação de tarefas */
        #tarefaModal .modal-dialog {
            max-width: 60%; /* Define a largura do modal para 60% da página */
        }

        /* Ajuste a altura automática se necessário */
        #tarefaModal .modal-content {
            height: auto;
        }
        .btn-4 {
            background: #34495e;
            color: #fff;
        }
        .btn-4:hover {
            background: #2c3e50;
            color: #fff;
        }
        .btn-edit2 {
            background: #ffa907;
            color: #fff;
        }
        .btn-edit2:hover {
            background: #ff9707;
            color: #fff;
        }
        .btn-info2 {
            background: #085f6d;
            color: #fff;
            border-color: #085f6d;
        }
        .btn-info2:hover {
            background: #085460;
            color: #fff;
            border-color: #085f6d;
        }
        .btn-info3 {
            background: #17a2b8;
            color: #fff;
        }
        .btn-info3:hover {
            background: #138496;
            color: #fff;
        }
    </style>
</head>
<body>
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-center align-items-center">
            <button style="margin-bottom: 5px!important;" type="button" class="btn btn-primary btn-print" onclick="imprimirOS()"><i class="fa fa-print" aria-hidden="true"></i> Imprimir OS</button>
            <button style="width: 100px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px; color: #fff!important" type="button" class="btn btn-info2 btn-print" onclick="imprimirRecibo()"><i class="fa fa-print" aria-hidden="true"></i> Recibo</button>
            <button style="margin-bottom: 5px!important;" type="button" class="btn btn-success btn-payment" data-toggle="modal" data-target="#pagamentoModal" <?php echo ($ordem_servico['status'] == 'Cancelado') ? 'disabled' : ''; ?>><i class="fa fa-money" aria-hidden="true"></i> Pagamentos</button>
            <button style="width: 100px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px; color: #fff!important" type="button" class="btn btn-secondary" onclick="$('#anexoModal').modal('show');"><i class="fa fa-paperclip" aria-hidden="true"></i> Anexos</button>
            <button style="width: 100px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-edit2 btn-sm" onclick="editarOS()" <?php echo ($ordem_servico['status'] == 'Cancelado') ? 'disabled' : ''; ?>><i class="fa fa-pencil" aria-hidden="true"></i> Editar OS</button>
            <button style="height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-danger btn-cancel btn-sm" onclick="cancelarOS()" <?php echo ($has_liquidated || $ordem_servico['status'] == 'Cancelado') ? 'disabled' : ''; ?>><i class="fa fa-ban" aria-hidden="true"></i> Cancelar OS</button>
            <button type="button" class="btn btn-4 btn-sm" style="width: 120px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" data-toggle="modal" data-target="#tarefaModal"><i class="fa fa-clock-o" aria-hidden="true"></i> Criar Tarefa</button>
            <button style="width: 120px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='index.php'"><i class="fa fa-search" aria-hidden="true"></i> Pesquisar OS</button>
            <button type="button" class="btn btn-info3 btn-sm" style="width: 190px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px; color: #fff!important" onclick="window.location.href='criar_os.php'"><i class="fa fa-plus" aria-hidden="true"></i> Criar Ordem de Serviço</button>
        </div>
        <hr>
        <div class="text-center">
            <h4>ORDEM DE SERVIÇO Nº: <?php echo $ordem_servico['id']; ?></h4>
            <!-- Legenda do Status da OS -->
            <div style="margin-top: -9px;">
                <?php
                // Definir a legenda do status da OS e a classe CSS
                $statusLegenda = '';
                $statusClass = '';

                if ($ordem_servico['status'] === 'Cancelado') {
                    $statusLegenda = 'Cancelada';
                    $statusClass = 'situacao-cancelado';
                } elseif ($total_pagamentos > 0) {
                    $statusLegenda = 'Pago (Depósito Prévio)';
                    $statusClass = 'situacao-pago';
                } elseif ($ordem_servico['status'] === 'Ativo') {
                    $statusLegenda = 'Ativa (Pendente de Pagamento)';
                    $statusClass = 'situacao-ativo';
                }

                // Exibir a legenda se estiver definida
                if ($statusLegenda) {
                    echo '<small class="' . $statusClass . '">' . $statusLegenda . '</small>';
                }
                ?>
            </div>
        </div>



        <hr>
        <form id="osForm" method="POST">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="cliente">Apresentante:</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo $ordem_servico['cliente']; ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="cpf_cliente">CPF/CNPJ do Apresentante:</label>
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente" value="<?php echo $ordem_servico['cpf_cliente']; ?>" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="total_os">Base de Cálculo:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo 'R$ ' . number_format($ordem_servico['base_de_calculo'], 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="total_os">Valor Total:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo 'R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="deposito_previo">Depósito Prévio:</label>
                    <input type="text" class="form-control" id="deposito_previo" name="deposito_previo" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>
                </div>
                <?php if ($total_liquidado > 0): ?>
                <div class="form-group col-md-3">
                    <label for="valor_liquidado">Valor Liquidado:</label>
                    <input type="text" class="form-control" id="valor_liquidado" name="valor_liquidado" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_devolucoes > 0): ?>
                <div class="form-group col-md-3">
                    <label for="valor_devolvido">Valor Devolvido:</label>
                    <input type="text" class="form-control" id="valor_devolvido" name="valor_devolvido" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_repasses > 0): ?>
                <div class="form-group col-md-3">
                    <label for="total_repasses">Repasse Credor:</label>
                    <input type="text" class="form-control" id="total_repasses" name="total_repasses" value="<?php echo 'R$ ' . number_format($total_repasses, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($saldo > 0): ?>
                <div class="form-group col-md-3">
                    <label for="saldo">Saldo:</label>
                    <input type="text" class="form-control" id="saldo" name="saldo" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group col-md-10">
                    <label for="descricao_os">Título da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo $ordem_servico['descricao_os']; ?>" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="descricao_os">Data da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo date('d/m/Y', strtotime($ordem_servico['data_criacao'])); ?>" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="observacoes">Observações:</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4" readonly><?php echo $ordem_servico['observacoes']; ?></textarea>
                </div>
            </div>
        </form>
        <div id="osItens" class="mt-4">
            <h4>Itens da Ordem de Serviço</h4>
            <table id="tabelaItensOS" class="table table-striped table-bordered" style="zoom: 80%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ato</th>
                        <th>Qtd</th>
                        <th>Desconto Legal (%)</th>
                        <th>Descrição</th>
                        <th>Emolumentos</th>
                        <th>FERC</th>
                        <th>FADEP</th>
                        <th>FEMP</th>
                        <th>Total</th>
                        <th>Qtd Liquidada</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="itensTable">
                    <?php foreach ($ordem_servico_itens as $item): ?>
                    <tr>
                        <td><?php echo $item['ordem_exibicao']; ?></td>
                        <td><?php echo $item['ato']; ?></td>
                        <td><?php echo $item['quantidade']; ?></td>
                        <td><?php echo $item['desconto_legal']; ?></td>
                        <td><?php echo $item['descricao']; ?></td>
                        <td><?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['ferc'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['fadep'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['femp'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['total'], 2, ',', '.'); ?></td>
                        <td><?php echo $item['quantidade_liquidada']; ?></td>
                        <td>
                            <?php if ($item['status'] == 'liquidado'): ?>
                                <span class="status-label status-liquidado">Liquidado</span>
                            <?php elseif ($item['status'] == 'Cancelado'): ?>
                                <span class="status-label status-pendente">Cancelado</span>
                            <?php elseif ($item['status'] == 'parcialmente liquidado'): ?>
                                <span class="status-label status-parcial">Liq. Parcialmente</span>
                            <?php else: ?>
                                <span class="status-label status-pendente">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['status'] != 'Cancelado' && $item['status'] != 'liquidado'): ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="liquidarAto(<?php echo $item['id']; ?>, <?php echo $item['quantidade']; ?>, <?php echo $item['quantidade_liquidada'] !== null ? $item['quantidade_liquidada'] : 0; ?>, '<?php echo $item['status'] !== null ? addslashes($item['status']) : ''; ?>')">Liquidar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Pagamento -->
<div class="modal fade" id="pagamentoModal" tabindex="-1" role="dialog" aria-labelledby="pagamentoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 40%">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pagamentoModalLabel">Efetuar Pagamento</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="total_os_modal">Valor Total da OS</label>
                    <input type="text" class="form-control" id="total_os_modal" value="<?php echo 'R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="forma_pagamento">Forma de Pagamento</label>
                        <select class="form-control" id="forma_pagamento">
                            <option value="">Selecione</option>
                            <option value="Espécie">Espécie</option>
                            <option value="Crédito">Crédito</option>
                            <option value="Débito">Débito</option>
                            <option value="PIX">PIX</option>
                            <option value="Transferência Bancária">Transferência Bancária</option>
                            <option value="Boleto">Boleto</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="valor_pagamento">Valor do Pagamento</label>
                        <input type="text" class="form-control" id="valor_pagamento">
                    </div>
                </div>
                <button type="button" class="btn btn-primary w-100" onclick="adicionarPagamento()">Adicionar</button>
                <hr>
                <div class="form-row">
                    <?php if ($total_pagamentos > 0): ?>
                        <div class="form-group col-md-3">
                            <label for="total_pagamento_modal">Valor Pago</label>
                            <input type="text" class="form-control" id="total_pagamento_modal" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($total_liquidado > 0): ?>
                        <div class="form-group col-md-3">
                            <label for="valor_liquidado_modal">Valor Liquidado</label>
                            <input type="text" class="form-control" id="valor_liquidado_modal" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($saldo > 0): ?>
                        <div class="form-group col-md-3">
                            <label for="saldo_modal">Saldo</label>
                            <input type="text" class="form-control" id="saldo_modal" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($total_devolucoes > 0): ?>
                        <div class="form-group col-md-3">
                            <label for="valor_devolvido_modal">Valor Devolvido</label>
                            <input type="text" class="form-control" id="valor_devolvido_modal" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($total_repasses > 0): ?>
                        <div class="form-group col-md-3">
                            <label for="total_repasses_modal">Repasse Credor</label>
                            <input type="text" class="form-control" id="total_repasses_modal" value="<?php echo 'R$ ' . number_format($total_repasses, 2, ',', '.'); ?>" readonly>
                        </div>
                    <?php endif; ?>
                </div>

                
                <div class="form-row">
                    <?php if ($saldo > 0.01 && $has_ato_17): ?>
                        <div class="<?php echo ($saldo > 0.01) ? 'col-md-6' : 'col-12'; ?>">
                            <button type="button" class="btn btn-warning w-100" onclick="abrirRepasseModal()">Repasse Credor</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($saldo > 0.01): ?>
                        <div class="<?php echo ($has_ato_17) ? 'col-md-6' : 'col-12'; ?>">
                            <button type="button" class="btn btn-warning w-100" onclick="abrirDevolucaoModal()">Devolver valores</button>
                        </div>
                    <?php endif; ?>
                </div>

                    <hr>
                <div class="table-responsive">
                    <h5>Pagamentos Adicionados</h5>
                    <table id="tabelaIPagamentoOS" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Forma de Pagamento</th>
                                    <th>Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="pagamentosTable">
                                <!-- Pagamentos adicionados serão listados aqui -->
                                <?php foreach ($pagamentos as $pagamento): ?>
                                <tr>
                                    <td><?php echo $pagamento['forma_de_pagamento']; ?></td>
                                    <td><?php echo 'R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.'); ?></td>
                                    <td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="confirmarRemocaoPagamento(<?php echo $pagamento['id']; ?>)" <?php echo $has_liquidated ? 'disabled' : ''; ?>><i class="fa fa-trash" aria-hidden="true"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Repasse -->
<div class="modal fade" id="repasseModal" tabindex="-1" role="dialog" aria-labelledby="repasseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="repasseModalLabel">Repasse Credor</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="forma_repasse">Forma de Repasse</label>
                    <select class="form-control" id="forma_repasse">
                        <option value="">Selecione</option>
                        <option value="Espécie">Espécie</option>
                        <option value="PIX">PIX</option>
                        <option value="Transferência Bancária">Transferência Bancária</option>
                        <option value="Boleto">Boleto</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="valor_repasse">Valor do Repasse</label>
                    <input type="text" class="form-control" id="valor_repasse" placeholder="0,00">
                </div>
                <button type="button" class="btn btn-primary" onclick="salvarRepasse()">Salvar</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Liquidação -->
<div class="modal fade" id="liquidacaoModal" tabindex="-1" role="dialog" aria-labelledby="liquidacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="liquidacaoModalLabel">Liquidar Ato</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="quantidade_liquidar">Quantidade a Liquidar</label>
                    <input type="number" class="form-control" id="quantidade_liquidar" min="1">
                </div>
                <button type="button" class="btn btn-primary" onclick="confirmarLiquidacao()">Confirmar</button>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Devolução -->
<div class="modal fade" id="devolucaoModal" tabindex="-1" role="dialog" aria-labelledby="devolucaoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="devolucaoModalLabel">Devolver Valores</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="forma_devolucao">Forma de Devolução</label>
                    <select class="form-control" id="forma_devolucao">
                        <option value="">Selecione</option>
                        <option value="Espécie">Espécie</option>
                        <option value="PIX">PIX</option>
                        <option value="Transferência Bancária">Transferência Bancária</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="valor_devolucao">Valor da Devolução</label>
                    <input type="text" class="form-control" id="valor_devolucao" placeholder="0,00">
                </div>
                <button type="button" class="btn btn-primary w-100" onclick="salvarDevolucao()">Salvar</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Mensagem -->
<div class="modal fade" id="mensagemModal" tabindex="-1" role="dialog" aria-labelledby="mensagemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header error">
                <h5 class="modal-title" id="mensagemModalLabel">Erro</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <p id="mensagemTexto"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para criação de Tarefa -->
<div class="modal fade" id="tarefaModal" tabindex="-1" role="dialog" aria-labelledby="tarefaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"> <!-- Modal ajustado para ser grande -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tarefaModalLabel">Criar Tarefa</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <form id="taskForm" method="POST" action="save_task.php" enctype="multipart/form-data">
                    <div class="form-row">    
                        <div class="form-group col-md-8">
                            <label for="title">Título da Tarefa</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo $ordem_servico['descricao_os'] . ' - ' . $ordem_servico['cliente']; ?>" required>
                        </div>

                    <!-- Alinhando os campos na mesma linha -->
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
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo $ordem_servico['observacoes']; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachments">Anexos</label>
                        <input type="file" class="form-control-file" id="attachments" name="attachments[]" multiple>
                    </div>
                    <input type="hidden" id="createdBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">
                    <input type="hidden" id="createdAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">
                    <button type="submit" class="btn btn-primary w-100">Criar Tarefa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Anexos -->
<div class="modal fade" id="anexoModal" tabindex="-1" role="dialog" aria-labelledby="anexoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 30%">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="anexoModalLabel">Anexos</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <form id="formAnexos" enctype="multipart/form-data">
                    <div class="custom-file mb-3">
                        <input type="file" class="custom-file-input" id="novo_anexo" name="novo_anexo[]" multiple>
                        <label class="custom-file-label" for="novo_anexo">Selecione os arquivos para anexar</label>
                    </div>
                    <button type="button" style="width: 100%" class="btn btn-success" onclick="salvarAnexo()"><i class="fa fa-paperclip" aria-hidden="true"></i> Anexar</button>
                </form>
                <hr>
                <div class="table-responsive">
                    <h5>Anexos Adicionados</h5>
                    <table id="anexosTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 85%">Anexo</th>
                                <th style="width: 15%">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- O conteúdo será preenchido pelo JavaScript -->
                        </tbody>
                    </table>
                </div>

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
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
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

    var pagamentos = <?php echo json_encode($pagamentos); ?>;
    var liquidacaoItemId = null;
    var quantidadeTotal = 0;
    var quantidadeLiquidada = 0;

    $(document).ready(function() {
        $('#valor_pagamento').mask('#.##0,00', { reverse: true });
        $('#valor_devolucao').mask('#.##0,00', { reverse: true });
        $('#valor_repasse').mask('#.##0,00', { reverse: true });

        atualizarTabelaPagamentos(); // Chamada para exibir os pagamentos existentes ao carregar a página
    });

    // Inicializar DataTable
    $('#tabelaItensOS').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "order": [[0, 'asc']], // Ordena pela segunda coluna de forma ascendente
    });

    $('#tabelaPagamentoOS').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "order": [], // Sem ordenação inicial
    });

    $('#tabelaIPagamentoOS').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "order": [], // Sem ordenação inicial
    });

    function imprimirOS() {
        // Gerar um timestamp para evitar cache
        const timestamp = new Date().getTime();
        
        // Fazer a requisição para o arquivo JSON com o timestamp
        fetch(`../style/configuracao.json?nocache=${timestamp}`)
            .then(response => response.json())
            .then(data => {
                const osId = '<?php echo $os_id; ?>'; // Usando PHP para pegar o ID
                let url = '';
                
                if (data.timbrado === 'S') {
                    url = `imprimir_os.php?id=${osId}`;
                } else {
                    url = `imprimir-os.php?id=${osId}`;
                }
                
                // Abrir o link correspondente em uma nova aba
                window.open(url, '_blank');
            })
            .catch(error => {
                console.error('Erro ao carregar o arquivo JSON:', error);
            });
    }


    function imprimirRecibo() {
        window.open('recibo.php?id=<?php echo $os_id; ?>', '_blank');
    }

    function editarOS() {
        window.location.href = 'editar_os.php?id=<?php echo $os_id; ?>';
    }

    // Função para adicionar pagamento
    function adicionarPagamento() {
        var formaPagamento = $('#forma_pagamento').val();
        var valorPagamento = parseFloat($('#valor_pagamento').val().replace('.', '').replace(',', '.'));

        if (formaPagamento === "") {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, selecione uma forma de pagamento.',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (isNaN(valorPagamento) || valorPagamento <= 0) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, insira um valor válido para o pagamento.',
                confirmButtonText: 'OK'
            });
            return;
        }

        $.ajax({
            url: 'salvar_pagamento.php',
            type: 'POST',
            data: {
                os_id: <?php echo $os_id; ?>,
                cliente: '<?php echo $ordem_servico['cliente']; ?>',
                total_os: <?php echo $ordem_servico['total_os']; ?>,
                funcionario: '<?php echo $_SESSION['username']; ?>',
                forma_pagamento: formaPagamento,
                valor_pagamento: valorPagamento
            },
            success: function(response) {
                response = JSON.parse(response);
                if (response.success) {
                    pagamentos.push({ forma_de_pagamento: formaPagamento, total_pagamento: valorPagamento });
                    atualizarTabelaPagamentos();
                    $('#valor_pagamento').val('');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Pagamento adicionado com sucesso!',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao adicionar pagamento.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao adicionar pagamento.',
                    confirmButtonText: 'OK'
                });
            }
        });
    }


    // Função para atualizar a tabela de pagamentos
    function atualizarTabelaPagamentos() {
        var pagamentosTable = $('#pagamentosTable');
        pagamentosTable.empty();

        var total = 0;

        pagamentos.forEach(function(pagamento, index) {
            total += parseFloat(pagamento.total_pagamento);

            pagamentosTable.append(`
                <tr>
                    <td>${pagamento.forma_de_pagamento}</td>
                    <td>R$ ${parseFloat(pagamento.total_pagamento).toFixed(2).replace('.', ',')}</td>
                    <td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="confirmarRemocaoPagamento(${pagamento.id})" <?php echo $has_liquidated ? 'disabled' : ''; ?>><i class="fa fa-trash" aria-hidden="true"></i></button></td>
                </tr>
            `);
        });

        $('#total_pagamento').val('R$ ' + total.toFixed(2).replace('.', ','));
    }

    // Função para remover pagamento
    function confirmarRemocaoPagamento(pagamentoId) {
        if (<?php echo $has_liquidated ? 'true' : 'false'; ?>) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Não é possível remover pagamentos após a liquidação de atos.',
                confirmButtonText: 'OK'
            });
            return;
        }

        Swal.fire({
            title: 'Tem certeza?',
            text: 'Deseja realmente remover este pagamento?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'remover_pagamento.php',
                    type: 'POST',
                    data: {
                        pagamento_id: pagamentoId
                    },
                    success: function(response) {
                        response = JSON.parse(response);
                        if (response.success) {
                            pagamentos = pagamentos.filter(pagamento => pagamento.id !== pagamentoId);
                            atualizarTabelaPagamentos();
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Pagamento removido com sucesso!',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro ao remover pagamento.',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao remover pagamento.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    }

    // Função para liquidar ato
    function liquidarAto(itemId, quantidade, quantidadeLiquidada, status) {
        liquidacaoItemId = itemId;
        quantidadeTotal = quantidade;
        quantidadeLiquidada = quantidadeLiquidada;

        var quantidadeRestante = quantidadeTotal - quantidadeLiquidada;
        $('#quantidade_liquidar').val(quantidadeRestante);

        if (quantidadeRestante === 1) {
            $('#quantidade_liquidar').prop('readonly', true);
        } else {
            $('#quantidade_liquidar').prop('readonly', false);
        }

        $('#liquidacaoModal').modal('show');
    }

    // Função para confirmar liquidação
    function confirmarLiquidacao() {
        var quantidadeALiquidar = parseInt($('#quantidade_liquidar').val());

        if (quantidadeALiquidar <= 0 || (quantidadeLiquidada + quantidadeALiquidar) > quantidadeTotal) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Quantidade inválida para liquidação.',
                confirmButtonText: 'OK'
            });
            return;
        }

        $.ajax({
            url: 'liquidar_ato.php',
            type: 'POST',
            data: {
                item_id: liquidacaoItemId,
                quantidade_liquidar: quantidadeALiquidar
            },
            success: function(response) {
                console.log(response); // Adiciona log para verificar a resposta
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Ato liquidado com sucesso!',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            $('#liquidacaoModal').modal('hide');
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: response.error || 'Erro ao liquidar ato.',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (e) {
                    console.error('Erro ao analisar resposta JSON:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao analisar resposta do servidor.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao liquidar ato: ' + error,
                    confirmButtonText: 'OK'
                });
            }
        });
    }


    // Função para abrir modal de devolução
    function abrirDevolucaoModal() {
        $('#devolucaoModal').modal('show');
    }

    // Função para salvar devolução
    function salvarDevolucao() {
        var formaDevolucao = $('#forma_devolucao').val();
        var valorDevolucao = parseFloat($('#valor_devolucao').val().replace('.', '').replace(',', '.'));
        var valorPago = parseFloat('<?php echo $valor_pago_liquido; ?>');
        var valorMaximoDevolucao = valorPago - parseFloat('<?php echo $total_liquidado; ?>');

        if (formaDevolucao === "") {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, selecione uma forma de devolução.',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > valorMaximoDevolucao + 0.01) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, insira um valor válido para a devolução que não seja maior que o saldo disponível.',
                confirmButtonText: 'OK'
            });
            return;
        }

        var osId = <?php echo $os_id; ?>;
        var cliente = '<?php echo $ordem_servico['cliente']; ?>';
        var totalOs = '<?php echo $ordem_servico['total_os']; ?>';
        var funcionario = '<?php echo $_SESSION['username']; ?>';

        $.ajax({
            url: 'salvar_devolucao.php',
            type: 'POST',
            data: {
                os_id: osId,
                cliente: cliente,
                total_os: totalOs,
                total_devolucao: valorDevolucao,
                forma_devolucao: formaDevolucao,
                funcionario: funcionario
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: 'Devolução salva com sucesso!',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#devolucaoModal').modal('hide');
                    window.location.reload();
                });
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao salvar devolução: ' + error,
                    confirmButtonText: 'OK'
                });
            }
        });
    }


    // Função para abrir modal de repasse
    function abrirRepasseModal() {
        $('#repasseModal').modal('show');
    }

    // Função para salvar repasse
    function salvarRepasse() {
        var formaRepasse = $('#forma_repasse').val();
        var valorRepasse = parseFloat($('#valor_repasse').val().replace('.', '').replace(',', '.'));
        var saldoAtual = parseFloat('<?php echo $saldo; ?>');

        if (formaRepasse === "") {
            exibirMensagem('Por favor, selecione uma forma de repasse.', 'error');
            return;
        }

        if (isNaN(valorRepasse) || valorRepasse <= 0 || valorRepasse > saldoAtual + 0.01) {
            exibirMensagem('Por favor, insira um valor válido para o repasse que não seja maior que o saldo disponível.', 'error');
            return;
        }

        var osId = <?php echo $os_id; ?>;
        var cliente = '<?php echo $ordem_servico['cliente']; ?>';
        var totalOs = '<?php echo $ordem_servico['total_os']; ?>';
        var dataOs = '<?php echo $ordem_servico['data_criacao']; ?>';
        var funcionario = '<?php echo $_SESSION['username']; ?>';

        $.ajax({
            url: 'salvar_repasse.php',
            type: 'POST',
            data: {
                os_id: osId,
                cliente: cliente,
                total_os: totalOs,
                total_repasse: valorRepasse,
                forma_repasse: formaRepasse,
                data_os: dataOs,
                funcionario: funcionario
            },
            success: function(response) {
                console.log("Server response:", response); // Adicione esta linha para verificar a resposta do servidor
                try {
                    // Verifique se a resposta já é um objeto, se for, não tente analisá-la
                    if (typeof response === 'object') {
                        processarResposta(response, saldoAtual, valorRepasse);
                    } else {
                        response = JSON.parse(response);
                        processarResposta(response, saldoAtual, valorRepasse);
                    }
                } catch (e) {
                    console.error('Erro ao analisar resposta JSON:', e);
                    exibirMensagem('Erro ao processar a resposta do servidor: ' + e.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error); // Adicione esta linha para logar erros de AJAX
                exibirMensagem('Erro ao salvar repasse: ' + error, 'error');
            }
        });
    }

    function processarResposta(response, saldoAtual, valorRepasse) {
        if (response.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Repasse salvo com sucesso!',
                confirmButtonText: 'OK'
            }).then(() => {
                // Fechar o modal e recarregar a página
                $('#repasseModal').modal('hide');
                // Recalcular o saldo após o repasse
                var novoSaldo = saldoAtual - valorRepasse;
                $('#saldo').val('R$ ' + novoSaldo.toFixed(2).replace('.', ','));
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Erro ao salvar repasse: ' + response.error,
                confirmButtonText: 'OK'
            });
        }
    }

    // Função para exibir mensagem
    function exibirMensagem(mensagem, tipo) {
        var modalHeader = $('#mensagemModal .modal-header');
        var mensagemTexto = $('#mensagemTexto');

        if (tipo === 'success') {
            modalHeader.removeClass('error').addClass('success');
            $('#mensagemModalLabel').text('Sucesso');
        } else if (tipo === 'error') {
            modalHeader.removeClass('success').addClass('error');
            $('#mensagemModalLabel').text('Erro');
        }

        mensagemTexto.text(mensagem);
        $('#mensagemModal').modal('show');
    }

    // Adicionar evento para recarregar a página ao fechar os modais
    $('#pagamentoModal').on('hidden.bs.modal', function () {
        location.reload();
    });

    function cancelarOS() {
        var totalPagamentos = parseFloat('<?php echo $total_pagamentos; ?>');
        var totalDevolucoes = parseFloat('<?php echo $total_devolucoes; ?>');

        // Se o total de pagamentos for maior que o total de devoluções, alertar e abrir o modal de devolução
        if (totalPagamentos > totalDevolucoes) {
            Swal.fire({
                icon: 'error',
                title: 'Atenção',
                text: 'Há pagamentos nesta OS que ainda não foram totalmente devolvidos. Você precisa devolver o saldo antes de cancelar a OS.'
            }).then(() => {
                abrirDevolucaoModal(); // Abrir o modal de devolução
            });
            return;
        }

        // Exibir confirmação com SweetAlert2
        Swal.fire({
            title: 'Tem certeza?',
            text: "Tem certeza de que deseja cancelar esta Ordem de Serviço? Esta ação não pode ser desfeita.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, cancelar',
            cancelButtonText: 'Não, manter'
        }).then((result) => {
            if (result.isConfirmed) {
                // Realizar o cancelamento da OS
                $.ajax({
                    url: 'cancelar_os.php',
                    type: 'POST',
                    data: {
                        os_id: <?php echo $os_id; ?>
                    },
                    success: function(response) {
                        try {
                            response = JSON.parse(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso',
                                    text: 'Ordem de Serviço cancelada com sucesso!'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: 'Erro ao cancelar a Ordem de Serviço.'
                                });
                            }
                        } catch (e) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao processar resposta do servidor.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao cancelar a Ordem de Serviço.'
                        });
                    }
                });
            }
        });
    }

    $(document).on('show.bs.modal', function () {
        // Desativa a rolagem do fundo
        $('body').css('overflow', 'hidden');
    });

    $(document).on('hidden.bs.modal', function () {
        // Restaura a rolagem do fundo apenas se não houver mais modais abertos
        if ($('.modal.show').length === 0) {
            $('body').css('overflow', 'auto');
        }
    });

    // Adicionar rolagem ao modal principal após fechar o secundário
    $('#devolucaoModal').on('hidden.bs.modal', function () {
        $('#pagamentoModal').css('overflow-y', 'auto');
    });


    // Carregar anexos quando o modal for aberto
    $('#anexoModal').on('show.bs.modal', function() {
        window.currentOsId = <?php echo $os_id; ?>; // Define o ID da OS atual
        atualizarTabelaAnexos();
    });

    function salvarAnexo() {
        var formData = new FormData($('#formAnexos')[0]);
        formData.append('os_id', window.currentOsId);
        formData.append('funcionario', '<?php echo $_SESSION['username']; ?>');

        $.ajax({
            url: 'salvar_anexo.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        $('#novo_anexo').val('');
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso',
                            text: 'Anexo salvo com sucesso!'
                        }).then(() => {
                            // Recarregar a página quando a mensagem de sucesso for fechada
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao salvar anexo.'
                        });
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao processar resposta do servidor.'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao salvar anexo.'
                });
            }
        });
    }

    function atualizarTabelaAnexos() {
        var anexosTableBody = $('#anexosTable tbody');
        anexosTableBody.empty();

        $.ajax({
            url: 'obter_anexos.php',
            type: 'POST',
            data: {
                os_id: window.currentOsId
            },
            success: function(response) {
                try {
                    response = JSON.parse(response);

                    response.anexos.forEach(function(anexo, index) {
                        var caminhoCompleto = 'anexos/' + window.currentOsId + '/' + anexo.caminho_anexo;
                        anexosTableBody.append(`
                            <tr>
                                <td>${anexo.caminho_anexo}</td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" onclick="visualizarAnexo('${caminhoCompleto}')">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="btn btn-delete btn-sm" onclick="removerAnexo(${anexo.id})">
                                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });

                    // Inicializar ou re-inicializar o DataTable
                    if ($.fn.DataTable.isDataTable('#anexosTable')) {
                        $('#anexosTable').DataTable().clear().destroy();
                    }

                    $('#anexosTable').DataTable({
                        paging: true,
                        searching: true,
                        ordering: true,
                        info: true,
                        autoWidth: false, // Desabilitar a largura automática
                        responsive: true, // Torna a tabela responsiva
                        language: {
                            url: '../style/Portuguese-Brasil.json'
                        }
                    });
                } catch (e) {
                    exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                }
            },
            error: function() {
                exibirMensagem('Erro ao atualizar tabela de anexos.', 'error');
            }
        });
    }

        function visualizarAnexo(caminho) {
            window.open(caminho, '_blank');
        }

        function removerAnexo(anexoId) {
            Swal.fire({
                title: 'Confirmar Remoção',
                text: 'Deseja realmente remover este anexo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'remover_anexo.php',
                        type: 'POST',
                        data: {
                            anexo_id: anexoId
                        },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Sucesso',
                                        text: 'Anexo removido com sucesso!'
                                    }).then(() => {
                                        // Recarregar a página quando a mensagem de sucesso for fechada
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Erro',
                                        text: 'Erro ao remover anexo.'
                                    });
                                }
                            } catch (e) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: 'Erro ao processar resposta do servidor.'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao remover anexo.'
                            });
                        }
                    });
                }
            });
        }


        document.getElementById('novo_anexo').addEventListener('change', function() {
            var input = this;
            var label = input.nextElementSibling;
            var files = input.files;

            if (files.length === 1) {
                // Exibir o nome do arquivo selecionado
                label.textContent = files[0].name;
            } else if (files.length > 1) {
                // Exibir a quantidade de arquivos selecionados
                label.textContent = files.length + ' arquivos selecionados';
            } else {
                // Voltar ao texto padrão
                label.textContent = 'Selecione os arquivos para anexar';
            }
        });

        
        $('#anexoModal').on('hidden.bs.modal', function () {
            location.reload(); // Recarrega a página quando o modal for fechado
        });


</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
