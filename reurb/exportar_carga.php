<?php
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/session_check.php');
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Carga</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <style>
        .custom-container {
            max-width: 1000px;
            margin: auto;
        }

        table th,
        table td {
            text-align: center;
            vertical-align: middle;
        }

        .w-100 {
            margin-bottom: 31px;
            margin-top: 0px;
        }
        .btn-central {
            background: #6c927c;
            border: #6c927c;
            color: #fff;
        }
        .btn-central:hover {
            background: #638873;
            border: #638873;
            color: #fff;
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Exportar Carga</h3>
            <div>
                <button type="button" class="btn btn-central" onclick="window.location.href='index.php'">
                    <i class="fa fa-desktop" aria-hidden="true"></i> Central de Acesso
                </button>
            </div>
            </div>
            <hr>
        <div class="row">
            <!-- Card para Exportar Carga de Imóveis -->
            <div class="col-md-6 d-flex">
                <div class="card h-100 w-100">
                    <div class="card-header">
                        <h3>Exportar Carga de Imóveis</h3>
                    </div>
                    <div class="card-body">
                        <form id="exportarCargaImoveisForm" method="POST" action="gerar_carga.php">
                            <div class="form-group">
                                <label for="processoAdm">Selecione o Processo Administrativo</label>
                                <select class="form-control" id="processoAdm" name="processo_adm" required>
                                    <option value="">Selecione um processo</option>
                                    <?php
                                    // Consulta para listar os processos administrativos
                                    $query = $conn->query("SELECT processo_adm FROM cadastro_de_processo_adm WHERE status = 'ativo' ORDER BY processo_adm");
                                    while ($row = $query->fetch_assoc()) {
                                        echo "<option value='{$row['processo_adm']}'>{$row['processo_adm']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-download"></i> Exportar Carga de Imóveis
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card para Exportar Carga de Pessoas -->
            <div class="col-md-6 d-flex">
                <div class="card h-100 w-100">
                    <div class="card-header">
                        <h3>Exportar Carga de Pessoas</h3>
                    </div>
                    <div class="card-body">
                        <form id="exportarCargaPessoasForm" method="POST" action="gerar_carga_pessoas.php">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa fa-download"></i> Exportar Carga de Pessoas
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <script>
        $(document).ready(function () {
            $('#exportarCargaForm').on('submit', function (e) {
                const tipoExportacao = $('#tipoExportacao').val();

                if (tipoExportacao === 'pessoas') {
                    $(this).attr('action', 'gerar_carga_pessoas.php');
                } else {
                    $(this).attr('action', 'gerar_carga.php');
                }
            });

            // Mensagem de erro ou sucesso com SweetAlert2
            <?php if (isset($_GET['error'])): ?>
                Swal.fire('Erro!', '<?= htmlspecialchars($_GET['error']) ?>', 'error');
            <?php elseif (isset($_GET['success'])): ?>
                Swal.fire('Sucesso!', 'Arquivo exportado com sucesso.', 'success');
            <?php endif; ?>
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
