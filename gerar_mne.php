<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Gerar Matrícula Notarial Eletrônica (MNE)</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
</head>
<body class="light-mode">
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <button class="mode-switch">🔄 Modo</button>
        <a href="index.php">Página Inicial</a>
        <a href="arquivamento/index.php">Acervo Cadastrado</a>
        <a href="arquivamento/cadastro.php">Cadastrar Acervo</a>
        <a href="arquivamento/categorias.php">Gerenciamento de Categorias</a>
        <a href="tarefas/index.php">Tarefas Cadastradas</a>
        <a href="tarefas/cadastro.php">Cadastrar Tarefa</a>
        <a href="cadastro_oficio.php">Cadastrar Ofício</a>
        <a href="gerar_mne.php">Gerar MNE</a>
    </div>

    <div id="main-content-wrapper">
        <button class="openbtn" onclick="openNav()">&#9776; Menu</button>
        <div id="system-name">Atlas</div>
        <div id="welcome-section">
            
            
        </div>
    </div>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Gerar Matrícula Notarial Eletrônica (MNE)</h3>
            <form method="post" action="">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="cns">Código Nacional de Serventia (CNS):</label>
                        <input type="text" class="form-control" id="cns" name="cns" value="<?php echo isset($_POST['cns']) ? htmlspecialchars($_POST['cns']) : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="ano">Ano:</label>
                        <input type="text" class="form-control" id="ano" name="ano" value="<?php echo isset($_POST['ano']) ? htmlspecialchars($_POST['ano']) : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="mes">Mês:</label>
                        <input type="text" class="form-control" id="mes" name="mes" value="<?php echo isset($_POST['mes']) ? htmlspecialchars($_POST['mes']) : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="dia">Dia:</label>
                        <input type="text" class="form-control" id="dia" name="dia" value="<?php echo isset($_POST['dia']) ? htmlspecialchars($_POST['dia']) : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="numeroSequencial">Número Sequencial:</label>
                        <input type="text" class="form-control" id="numeroSequencial" name="numeroSequencial" value="<?php echo isset($_POST['numeroSequencial']) ? htmlspecialchars($_POST['numeroSequencial']) : ''; ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Gerar MNE</button>
            </form>
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                function calcularDigitoVerificador($numero) {
                    $numero .= '00'; 

                    $resto = 0;
                    for ($i = 0; $i < strlen($numero); $i++) {
                        $resto = ($resto * 10 + intval($numero[$i])) % 97;
                    }

                    $digitoVerificador = 98 - $resto;

                    if ($digitoVerificador == 98) {
                        $digitoVerificador = 1;
                    }

                    return str_pad($digitoVerificador, 2, '0', STR_PAD_LEFT);
                }

                function gerarMNE($cns, $ano, $mes, $dia, $numeroSequencial) {
                    $cns = str_pad($cns, 6, '0', STR_PAD_LEFT);
                    $ano = str_pad($ano, 4, '0', STR_PAD_LEFT);
                    $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);
                    $dia = str_pad($dia, 2, '0', STR_PAD_LEFT);
                    $numeroSequencial = str_pad($numeroSequencial, 8, '0', STR_PAD_LEFT);

                    $mneSemDigito = $cns . $ano . $mes . $dia . $numeroSequencial;

                    $digitoVerificador = calcularDigitoVerificador($mneSemDigito);

                    $mne = "{$cns}.{$ano}.{$mes}.{$dia}.{$numeroSequencial}-{$digitoVerificador}";

                    return $mne;
                }

                $cns = $_POST['cns'];
                $ano = $_POST['ano'];
                $mes = $_POST['mes'];
                $dia = $_POST['dia'];
                $numeroSequencial = $_POST['numeroSequencial'];

                $mne = gerarMNE($cns, $ano, $mes, $dia, $numeroSequencial);

                echo "<div class='mt-3'><h2>A Matrícula Notarial Eletrônica (MNE) gerada é: <br>" . htmlspecialchars($mne) . "</h2></div>";
            }
            ?>
        </div>
    </div>

    <script src="script/jquery-3.5.1.min.js"></script>
    <script src="script/bootstrap.min.js"></script>
    <script>
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }

        $(document).ready(function() {
            $.ajax({
                url: 'load_mode.php',
                method: 'GET',
                success: function(mode) {
                    $('body').removeClass('light-mode dark-mode').addClass(mode);
                }
            });

            $('.mode-switch').on('click', function() {
                var body = $('body');
                body.toggleClass('dark-mode light-mode');

                var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
                $.ajax({
                    url: 'save_mode.php',
                    method: 'POST',
                    data: { mode: mode },
                    success: function(response) {
                        console.log(response);
                    }
                });
            });
        });
    </script>
</body>
</html>
