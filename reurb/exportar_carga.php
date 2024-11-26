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
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
</head>

<body>
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h3>Exportar Carga</h3>
            </div>
            <div class="card-body">
                <form id="exportarCargaForm" method="POST" action="gerar_carga.php">
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
                        <i class="fa fa-download"></i> Exportar Carga
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
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
