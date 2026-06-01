<?php
/**
 * relatorios/index.php (recriado) — Central de Relatórios
 * Estética alinhada aos relatórios (tema + dark mode + responsivo).
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$username = $_SESSION['username'];
$mode = 'light-mode';
$mq = $conn->prepare("SELECT modo FROM modo_usuario WHERE usuario = ?");
$mq->bind_param("s", $username); $mq->execute();
$mr = $mq->get_result();
if ($mr->num_rows > 0) { $mode = $mr->fetch_assoc()['modo']; }
$mq->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas — Central de Relatórios</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link href="../style/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/css/style.css">
    <?php include(__DIR__ . '/style.php'); ?>
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/jquery-ui.min.js"></script>

    <style>
        :root{
            --rel-bg:#f3f5f9; --rel-panel:#ffffff; --rel-border:rgba(17,24,39,.07);
            --rel-shadow:0 1px 3px rgba(16,24,40,.06),0 8px 24px rgba(16,24,40,.06);
            --rel-text:#1f2937; --rel-muted:#6b7280; --rel-input-bg:#fff; --rel-input-bd:#d7dce4;
            --rel-accent:#4e73df;
        }
        body.dark-mode{
            --rel-bg:#121212; --rel-panel:#1d1d24; --rel-border:rgba(255,255,255,.08);
            --rel-shadow:0 1px 3px rgba(0,0,0,.4),0 10px 28px rgba(0,0,0,.5);
            --rel-text:#e7e8ec; --rel-muted:#9aa1ad; --rel-input-bg:#262630; --rel-input-bd:#3a3a46;
            --rel-accent:#6f93ff;
        }
        body{ background:var(--rel-bg); }
        #main.main-content{ background:var(--rel-bg); padding:84px 0 40px; min-height:100vh; }

        .central-header{ text-align:center; margin-bottom:8px; }
        .central-header h2{ color:var(--rel-text); font-weight:800; letter-spacing:-.4px; }
        .central-header .sub{ color:var(--rel-muted); font-size:.95rem; }
        .header-rule{ width:74px; height:4px; border-radius:6px; margin:14px auto 30px;
            background:linear-gradient(90deg,var(--rel-accent),#1cc88a); }

        .search-box{ position:relative; max-width:520px; margin:0 auto 34px; }
        .search-box i{ position:absolute; left:18px; top:50%; transform:translateY(-50%); color:var(--rel-muted); }
        .search-box input{
            height:48px; width:100%; border-radius:999px; padding:0 20px 0 46px;
            background:var(--rel-input-bg); border:1px solid var(--rel-input-bd); color:var(--rel-text);
            box-shadow:var(--rel-shadow); transition:border-color .15s, box-shadow .15s;
        }
        .search-box input:focus{ outline:none; border-color:var(--rel-accent); box-shadow:0 0 0 3px rgba(78,115,223,.18); }
        .search-box input::placeholder{ color:var(--rel-muted); }

        .report-card{
            position:relative; background:var(--rel-panel); border:1px solid var(--rel-border);
            border-radius:18px; box-shadow:var(--rel-shadow); padding:26px 22px; height:100%;
            display:flex; flex-direction:column; align-items:center; text-align:center;
            transition:transform .25s ease, box-shadow .25s ease;
        }
        .report-card:hover{ transform:translateY(-6px); box-shadow:0 14px 34px rgba(16,24,40,.14); }
        body.dark-mode .report-card:hover{ box-shadow:0 14px 34px rgba(0,0,0,.55); }

        .report-card .chip{
            position:absolute; top:16px; right:16px; font-size:.66rem; font-weight:700;
            padding:.2rem .55rem; border-radius:999px; text-transform:uppercase; letter-spacing:.4px;
        }
        .chip-op{ background:#e7edff; color:#3754b5; }
        .chip-fin{ background:#e7f7f0; color:#0f8a52; }
        .chip-soon{ background:#f1f1f4; color:#7a8190; }
        body.dark-mode .chip-op{ background:#2a335c; color:#9db4ff; }
        body.dark-mode .chip-fin{ background:#1d4633; color:#6ee7a8; }
        body.dark-mode .chip-soon{ background:#33373f; color:#aab1bd; }

        .report-icon{
            width:74px; height:74px; border-radius:20px; display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:1.9rem; margin:6px 0 18px; box-shadow:0 8px 18px rgba(0,0,0,.18);
        }
        .ic-blue{ background:linear-gradient(135deg,#4e73df,#6f9bff); }
        .ic-teal{ background:linear-gradient(135deg,#00897b,#1cc88a); }
        .ic-purple{ background:linear-gradient(135deg,#5e35b1,#9575cd); }
        .ic-orange{ background:linear-gradient(135deg,#ff9800,#ffb74d); }

        .report-card h5{ color:var(--rel-text); font-weight:700; margin-bottom:8px; }
        .report-card p{ color:var(--rel-muted); font-size:.9rem; flex-grow:1; }
        .report-card .btn{
            width:100%; border-radius:12px; font-weight:600; padding:.6rem 1rem; border:none; color:#fff;
            display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
        }
        .btn-blue{ background:linear-gradient(135deg,#4e73df,#6f9bff); }
        .btn-teal{ background:linear-gradient(135deg,#00897b,#1cc88a); }
        .btn-purple{ background:linear-gradient(135deg,#5e35b1,#9575cd); }
        .btn-soon{ background:var(--rel-input-bd); color:var(--rel-muted); cursor:not-allowed; }
        .report-card .btn:not(.btn-soon):hover{ filter:brightness(1.06); }

        .report-col{ cursor:grab; margin-bottom:24px; }
        .report-col:active{ cursor:grabbing; }
        .ui-sortable-helper .report-card{ box-shadow:0 18px 40px rgba(0,0,0,.22); }
        .ui-state-highlight{ height:100%; min-height:280px; border-radius:18px;
            background:transparent; border:2px dashed var(--rel-input-bd); }

        @keyframes fadeInUp{ from{opacity:0; transform:translateY(18px);} to{opacity:1; transform:translateY(0);} }
        .animate-up{ animation:fadeInUp .5s ease forwards; }

        @media (max-width:575.98px){ #main.main-content{ padding:74px 0 40px; } .central-header h2{ font-size:1.6rem; } }
    </style>
</head>
<body class="<?php echo htmlspecialchars($mode); ?>">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <div class="central-header">
            <h2>Central de Relatórios</h2>
            <div class="sub">Acesse os painéis analíticos da serventia</div>
        </div>
        <div class="header-rule"></div>

        <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="searchReports" placeholder="Buscar relatórios...">
        </div>

        <div id="sortable-cards" class="row justify-content-center">

            <div class="col-12 col-sm-6 col-xl-3 report-col animate-up" id="card-tarefas">
                <div class="report-card">
                    <span class="chip chip-op">Operacional</span>
                    <div class="report-icon ic-blue"><i class="fa fa-tasks"></i></div>
                    <h5>Relatório de Tarefas</h5>
                    <p>Tarefas internas e pedidos de certidão unificados: status, prazos, atrasos e produtividade.</p>
                    <a href="relatorio_tarefas.php" class="btn btn-blue"><i class="fa fa-chart-bar"></i> Acessar</a>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 report-col animate-up" id="card-os" style="animation-delay:.08s;">
                <div class="report-card">
                    <span class="chip chip-op">Operacional</span>
                    <div class="report-icon ic-teal"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h5>Relatório de O.S.</h5>
                    <p>Faturamento por atribuição, desempenho dos funcionários, atos liquidados e depósito prévio.</p>
                    <a href="relatorio_os.php" class="btn btn-teal"><i class="fa fa-file-text"></i> Acessar</a>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 report-col animate-up" id="card-deposito" style="animation-delay:.16s;">
                <div class="report-card">
                    <span class="chip chip-fin">Financeiro</span>
                    <div class="report-icon ic-purple"><i class="fa fa-book"></i></div>
                    <h5>Livro de Depósito Prévio</h5>
                    <p>Geração do livro oficial de depósito prévio em PDF, pronto para impressão e arquivamento.</p>
                    <a href="livro_dep_previo.php" class="btn btn-purple"><i class="fa fa-print"></i> Gerar livro</a>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 report-col animate-up" id="card-contas" style="animation-delay:.24s;">
                <div class="report-card">
                    <span class="chip chip-soon">Em breve</span>
                    <div class="report-icon ic-orange"><i class="fa fa-credit-card"></i></div>
                    <h5>Contas a Pagar</h5>
                    <p>Controle de despesas e vencimentos com acompanhamento de pagamentos. Em desenvolvimento.</p>
                    <a href="#" class="btn btn-soon" onclick="return false;"><i class="fa fa-clock-o"></i> Em breve</a>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $("#sortable-cards").sortable({
            placeholder: "ui-state-highlight",
            helper: 'clone',
            tolerance: 'pointer',
            update: function () { saveCardOrder(); }
        });

        function saveCardOrder() {
            let order = [];
            $("#sortable-cards .report-col").each(function () { order.push($(this).attr('id')); });
            $.ajax({ url:'save_order.php', type:'POST', data:{ order:order } });
        }
        function loadCardOrder() {
            $.ajax({ url:'load_order.php', type:'GET', dataType:'json',
                success:function (data) {
                    if (data && data.order && Array.isArray(data.order)) {
                        $.each(data.order, function (i, id) { $("#" + id).appendTo("#sortable-cards"); });
                    }
                }
            });
        }
        $('#searchReports').on('keyup', function () {
            const v = $(this).val().toLowerCase();
            $("#sortable-cards .report-col").filter(function () {
                const txt = $(this).find('.report-card h5,.report-card p').text().toLowerCase();
                $(this).toggle(txt.indexOf(v) > -1);
            });
        });
        loadCardOrder();
    });
</script>
</body>
</html>
