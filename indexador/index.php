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
    <title>Atlas - Central de Acesso - Indexador</title>

    <!-- CSS base (mesmos do index.php raiz) -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <!-- Estilos do hub (mesmo bundle usado pelo index.php principal) -->
    <?php include(__DIR__ . '/../style/style_index.php'); ?>

    <style>
        /* ======================= BUSCA / GRID ======================= */
        .search-container { margin-bottom: 30px; }

        .search-box {
        width: 100%;
        max-width: 800px;
        padding: 12px 20px;
        border-radius: 100px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        font-size: 16px;
        background-image: url('../style/img/search-icon.png');
        background-repeat: no-repeat;
        background-position: 15px center;
        background-size: 16px;
        padding-left: 45px;
        display: block;
        margin: 0 auto;
        }
        .search-box:focus {
        outline: none;
        border-color: #0d6efd;
        box-shadow: 0 2px 8px rgba(13,110,253,0.15);
        }
        body.dark-mode .search-box {
        background-color: #22272e;
        border-color: #2f3a46;
        color: #e0e0e0;
        box-shadow: none;
        }
    </style>
</head>
<body class="light-mode">

<?php include(__DIR__ . '/../menu.php'); ?>

<div class="main-container">
    <h1 class="page-title"></h1>
    <div class="title-divider"></div>

    <div class="search-container">
        <input type="text" class="search-box" id="searchModules" placeholder="Buscar módulos...">
    </div>

    <div id="sortable-cards">
        <!-- Nascimento -->
        <div class="module-card" id="card-nascimento">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-agenda">
                    <i class="fa fa-child"></i>
                </div>
            </div>
            <h3 class="card-title">Nascimento</h3>
            <p class="card-description">Indexe e pesquise registros de nascimento.</p>
            <button class="card-button btn-anotacoes" onclick="window.location.href='nascimento/index.php'">
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Casamento -->
        <div class="module-card" id="card-casamento">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-contas">
                    <i class="fa fa-heart"></i>
                </div>
            </div>
            <h3 class="card-title">Casamento</h3>
            <p class="card-description">Indexe e pesquise registros de casamento.</p>
            <button class="card-button btn-5" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Óbito -->
        <div class="module-card" id="card-obito">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-tarefas">
                    <i class="fa fa-book"></i>
                </div>
            </div>
            <h3 class="card-title">Óbito</h3>
            <p class="card-description">Indexe e pesquise registros de óbito.</p>
            <button class="card-button btn-secondary" onclick="window.location.href='obito/index.php'">
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Notas -->
        <div class="module-card" id="card-notas">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-indexador">
                    <i class="fa fa-file-text-o"></i>
                </div>
            </div>
            <h3 class="card-title">Notas</h3>
            <p class="card-description">Indexe e pesquise atos de notas.</p>
            <button class="card-button btn-indexador" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Imóveis -->
        <div class="module-card" id="card-imoveis">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-os">
                    <i class="fa fa-home"></i>
                </div>
            </div>
            <h3 class="card-title">Imóveis</h3>
            <p class="card-description">Indexe e pesquise matrículas de imóveis.</p>
            <button class="card-button btn-os" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Protesto -->
        <div class="module-card" id="card-protesto">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-caixa">
                    <i class="fa fa-gavel"></i>
                </div>
            </div>
            <h3 class="card-title">Protesto</h3>
            <p class="card-description">Indexe e pesquise títulos de protesto.</p>
            <button class="card-button btn-success" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Títulos e Documentos -->
        <div class="module-card" id="card-titulos-e-documentos">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-guia">
                    <i class="fa fa-briefcase"></i>
                </div>
            </div>
            <h3 class="card-title">Títulos e Documentos</h3>
            <p class="card-description">Indexe e pesquise registros de títulos e documentos.</p>
            <button class="card-button btn-4" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Pessoas Jurídicas -->
        <div class="module-card" id="card-pessoas-juridicas">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-manuais">
                    <i class="fa fa-building"></i>
                </div>
            </div>
            <h3 class="card-title">Pessoas Jurídicas</h3>
            <p class="card-description">Indexe e pesquise registros de pessoas jurídicas.</p>
            <button class="card-button btn-manuais" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Contratos Marítimos -->
        <div class="module-card" id="card-contratos-maritimos">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-arquivamento">
                    <i class="fa fa-ship"></i>
                </div>
            </div>
            <h3 class="card-title">Contratos Marítimos</h3>
            <p class="card-description">Indexe e pesquise registros e contratos marítimos.</p>
            <button class="card-button btn-arquivamento" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>
    </div>
</div>

<!-- Scripts (mesmos do index.php principal) -->
<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/jquery-ui.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>

<script>
$(document).ready(function () {

    // Busca de módulos (filtra cards pelo texto)
    $("#searchModules").on("keyup", function () {
        const value = $(this).val().toLowerCase();
        $("#sortable-cards .module-card").filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Inicializa o sortable para os cards (arrastar pelo cabeçalho)
    $("#sortable-cards").sortable({
        placeholder: "ui-state-highlight",
        handle: ".card-header",
        cursor: "move",
        update: function (event, ui) {
            saveCardOrder();
        }
    });

    // Salvar ordem dos cards (arquivo JSON no próprio diretório do indexador)
    function saveCardOrder() {
        let order = [];
        $("#sortable-cards .module-card").each(function () {
            order.push($(this).attr('id'));
        });

        $.ajax({
            url: '../save_order.php', 
            type: 'POST',
            data: { order: order },
            success: function () {
                console.log('Ordem salva com sucesso!');
            },
            error: function (xhr, status, error) {
                console.error('Erro ao salvar a ordem:', error);
            }
        });
    }

    // Carregar ordem salva
    function loadCardOrder() {
        $.ajax({
            url: '../load_order.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data && data.order) {
                    $.each(data.order, function (index, cardId) {
                        $("#" + cardId).appendTo("#sortable-cards");
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro ao carregar a ordem:', error);
            }
        });
    }

    // Carrega ordem ao iniciar
    loadCardOrder();

    // Alternância de tema (a classe do <body> também é atualizada pelo menu)
    $('.mode-switch').on('click', function() {
        $('body').toggleClass('dark-mode light-mode');
    });

});
</script>

<br><br><br>
<?php include(__DIR__ . '/../rodape.php'); ?>

</body>
</html>
