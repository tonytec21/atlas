<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

// Verifique se a conexão está definida
if (!isset($conn)) {
    die("Erro ao conectar ao banco de dados");
}

// Fetch OS data
$os_id = $_GET['id'];
$os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
$os_query->bind_param("i", $os_id);
$os_query->execute();
$os_result = $os_query->get_result();
$ordem_servico = $os_result->fetch_assoc();

// Fetch OS items data
$os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
$os_items_query->bind_param("i", $os_id);
$os_items_query->execute();
$os_items_result = $os_items_query->get_result();
$ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
    <style>
        .btn-print {
            margin-top: 20px;
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
    </style>
</head>
<body>
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Visualizar Ordem de Serviço</h3>
        <hr>
        <form id="osForm" method="POST">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="cliente">Cliente:</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo $ordem_servico['cliente']; ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="cpf_cliente">CPF/CNPJ do Cliente:</label>
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente" value="<?php echo $ordem_servico['cpf_cliente']; ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="total_os">Total OS:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo $ordem_servico['total_os']; ?>" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="descricao_os">Título da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo $ordem_servico['descricao_os']; ?>" readonly>
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
            <table class="table">
                <thead>
                    <tr>
                        <th>Ato</th>
                        <th>Quantidade</th>
                        <th>Desconto Legal (%)</th>
                        <th>Descrição</th>
                        <th>Emolumentos</th>
                        <th>FERC</th>
                        <th>FADEP</th>
                        <th>FEMP</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="itensTable">
                    <?php foreach ($ordem_servico_itens as $item): ?>
                    <tr>
                        <td><?php echo $item['ato']; ?></td>
                        <td><?php echo $item['quantidade']; ?></td>
                        <td><?php echo $item['desconto_legal']; ?></td>
                        <td><?php echo $item['descricao']; ?></td>
                        <td><?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['ferc'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['fadep'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['femp'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['total'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-primary btn-print" onclick="imprimirOS()">Imprimir OS</button>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script>
    function imprimirOS() {
        window.open('gerar_pdf.php?id=<?php echo $os_id; ?>', '_blank');
    }
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
