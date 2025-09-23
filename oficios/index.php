<?php
include(__DIR__ . '/session_check.php');
checkSession();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Verificar se há filtros aplicados
$filters = [];
$filterQuery = "";

// Detecta se há qualquer filtro/consulta ativa
$hasFilter = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['numero']) || isset($_GET['data']) || isset($_GET['assunto']) || isset($_GET['destinatario']) || isset($_GET['dados_complementares']))) {
    if (!empty($_GET['numero'])) {
        $filters[] = "numero LIKE '%" . $conn->real_escape_string($_GET['numero']) . "%'";
    }
    if (!empty($_GET['data'])) {
        $filters[] = "data = '" . $conn->real_escape_string($_GET['data']) . "'";
    }
    if (!empty($_GET['assunto'])) {
        $filters[] = "assunto LIKE '%" . $conn->real_escape_string($_GET['assunto']) . "%'";
    }
    if (!empty($_GET['destinatario'])) {
        $filters[] = "destinatario LIKE '%" . $conn->real_escape_string($_GET['destinatario']) . "%'";
    }
    if (!empty($_GET['dados_complementares'])) {
        $filters[] = "dados_complementares LIKE '%" . $conn->real_escape_string($_GET['dados_complementares']) . "%'";
    }   

    if (count($filters) > 0) {
        $filterQuery = "WHERE " . implode(" AND ", $filters);
        $hasFilter = true;
    }
}

// Por padrão (sem filtros) limitar a 20 registros mais recentes
$limitClause = $hasFilter ? "" : " LIMIT 20";

$sql = "SELECT * FROM oficios $filterQuery ORDER BY id DESC$limitClause";
$result = $conn->query($sql);

$oficios = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $oficios[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Pesquisa de Ofícios</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        /* =======================================================================
           HERO
        ======================================================================= */
        .page-hero .title-row{ display:flex; align-items:center; gap:12px; }
        .page-hero{
          background: linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
          border-radius: 18px; padding: 18px 18px 10px; margin: 20px 0 12px; box-shadow: var(--soft-shadow, 0 8px 22px rgba(0,0,0,.06));
        }
        .title-icon{
          width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .title-icon{ background:#262f3b;color:#c7d2fe; }
        .page-hero h1{ font-weight:800; margin:0; }
        .page-hero .subtitle{ font-size:.95rem; opacity:.9; margin-top:2px;}
        .chip{
            display:inline-flex; align-items:center; gap:8px;
            background: rgba(99,102,241,.12); color:#3730A3;
            padding:6px 10px; border-radius:999px; font-weight:600; font-size:.85rem;
        }
        body.dark-mode .chip{ background: rgba(99,102,241,.18); color:#c7d2fe; }
        .chip .fa{ font-size:.9rem; }

        /* ===== Botão fechar (X) no modal ===== */
        .btn-close {
            outline: none;
            border: none; 
            background: none;
            padding: 0; 
            font-size: 1.5rem;
            cursor: pointer; 
            transition: transform 0.2s ease;
            line-height: 1;
        }
        .btn {margin-top: 5px}
        .btn-close:hover { transform: scale(1.15); }
        .btn-close:focus { outline: none; }

        .table-bordered {border-radius: 15px;}

        /* ===== Modal de Visualização – versão slim e ampla ===== */
        #viewOficioModal .modal-dialog {
            max-width: min(96vw, 1400px);
            margin: 0.55rem auto;
        }
        #viewOficioModal .modal-content {
            border: 1px solid rgba(0,0,0,.08);
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0,0,0,.18);
            overflow: hidden;
            transition: background-color .3s ease, box-shadow .3s ease, border-color .3s ease;
        }
        #viewOficioModal .modal-header {
            background: rgba(255,255,255,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: 0rem 1.1rem;
        }
        #viewOficioModal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
        }
        #viewOficioModal .modal-body {
            padding: 0rem 0rem 0rem 0rem;
            background: #fafafa;
        }
        #viewOficioModal .toolbar {
            display: flex;
            gap: .5rem;
            align-items: center;
            margin-left: auto;
        }
        /* iFrame ocupa o máximo de área */
        #viewOficioModal iframe#oficioPDF {
            width: 100%;
            height: calc(100vh - 120px); /* cabeçalho+rodapé */
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 10px;
            background: #f3f4f6;
        }
        #viewOficioModal .modal-footer {
            border-top: 1px solid rgba(0,0,0,.06);
            background: rgba(250,250,250,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            padding: .0rem .0rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ===== Botões modernos (pílula) ===== */
        .btn-pill {
            border-radius: 10px !important;
            padding: .55rem 1rem;
            font-weight: 600;
            border: 1px solid transparent;
            transition: transform .08s ease, box-shadow .2s ease, background-color .2s ease, border-color .2s ease;
        }
        .btn-pill:focus { box-shadow: 0 0 0 .2rem rgba(0,123,255,.15); outline: none; }
        .btn-pill:hover { transform: translateY(-1px); }

        .btn-ghost {
            background: transparent;
            border-color: var(--line, rgba(0,0,0,.12));
        }

        /* Reforço de cores para temas */
        .btn-primary.btn-pill { box-shadow: 0 4px 10px rgba(0,123,255,.18); }
        .btn-success.btn-pill { box-shadow: 0 4px 10px rgba(40,167,69,.18); }
        .btn-info.btn-pill    { box-shadow: 0 4px 10px rgba(23,162,184,.18); }
        .btn-secondary.btn-pill { box-shadow: 0 4px 10px rgba(108,117,125,.18); }
        .btn-info { margin-bottom: 0px!important; }

        /* ===== Dark Mode do Modal de Visualização ===== */
        body.dark-mode #viewOficioModal .modal-content {
            border-color: rgba(255,255,255,.08);
            box-shadow: 0 8px 28px rgba(0,0,0,.55);
        }
        body.dark-mode #viewOficioModal .modal-header {
            background: rgba(35,39,42,.8);
            border-bottom-color: rgba(255,255,255,.06);
        }
        body.dark-mode #viewOficioModal .modal-title { color: #fff; }
        body.dark-mode #viewOficioModal .modal-body { background: #1f2326; }
        body.dark-mode #viewOficioModal iframe#oficioPDF { background: #2a2f34; border-color: rgba(255,255,255,.06); }
        body.dark-mode #viewOficioModal .modal-footer { background: rgba(35,39,42,.8); border-top-color: rgba(255,255,255,.06); }
        body.dark-mode .btn-ghost { border-color: rgba(255,255,255,.18); color: #e8e8e8; }
        body.dark-mode .btn-ghost:hover { background: rgba(255,255,255,.04); }

        /* ======= FORM DE FILTRO (UI/UX MODERNO) ======= */
        .filter-card{
            background: linear-gradient(180deg, rgba(15,23,42,.02), rgba(15,23,42,0));
            border:1px solid rgba(0,0,0,.06);
            border-radius:16px; padding:16px; box-shadow: 0 6px 18px rgba(0,0,0,.06);
        }
        body.dark-mode .filter-card{
            background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,0));
            border-color: rgba(255,255,255,.10); box-shadow: 0 8px 26px rgba(0,0,0,.45);
        }
        .filter-card .section-title{
            font-weight:800; font-size:1.05rem; margin-bottom:.35rem;
        }
        .filter-card .section-sub{
            font-size:.92rem; opacity:.85; margin-bottom:.75rem;
        }
        .input-chip{
            display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid rgba(0,0,0,.08); border-radius:12px; background:#fff;
        }
        .input-chip input{ border:none; outline:none; width:100%; }
        .input-chip .fa{ opacity:.7; }
        body.dark-mode .input-chip{ background:#1f2326; border-color: rgba(255,255,255,.12); color:#e9ecef;}
        .filter-actions{
            display:flex; gap:10px; flex-wrap:wrap;
        }
        .btn-soft{
            background: #f3f4f6; border:1px solid rgba(0,0,0,.08); color:#111827;
        }
        .btn-soft:hover{ background:#e9eaee; }
        body.dark-mode .btn-soft{ background:#2a2f34; border-color:rgba(255,255,255,.10); color:#f3f4f6; }
        body.dark-mode .btn-soft:hover{ background:#32373d; }

        .hint{
            font-size:.85rem; opacity:.85;
        }

        /* ===== Tabela -> Cards (Mobile) ===== */
        .table-wrap { width: 100%; }
        table.data-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        /* Cabeçalho fixo no desktop */
        @media (min-width: 768px) {
            table.data-layout thead th {
                position: sticky;
                top: 0;
                z-index: 1;
                background: var(--thead-bg, #fff);
            }
            body.dark-mode table.data-layout thead th { --thead-bg: #2c2f33; color: #fff; }
        }

        /* Aparência padrão da tabela */
        table.data-layout thead th {
            font-weight: 700;
            border-bottom: 1px solid rgba(0,0,0,.08);
        }
        body.dark-mode table.data-layout thead th {
            border-bottom-color: rgba(255,255,255,.08);
        }

        /* LINHAS como cards no mobile */
        @media (max-width: 767.98px) {
            table.data-layout thead { display: none; }
            table.data-layout, 
            table.data-layout tbody, 
            table.data-layout tr, 
            table.data-layout td { display: block; width: 100%; }
            table.data-layout tr {
                background: rgba(255,255,255,.9);
                border: 1px solid rgba(0,0,0,.06);
                border-radius: 14px;
                box-shadow: 0 8px 20px rgba(0,0,0,.06);
                padding: .75rem .9rem;
                margin-bottom: .9rem;
            }
            body.dark-mode table.data-layout tr {
                background: rgba(35,39,42,.85);
                border-color: rgba(255,255,255,.08);
                box-shadow: 0 8px 24px rgba(0,0,0,.45);
            }
            table.data-layout td {
                border: 0;
                padding: .25rem 0;
                position: relative;
            }
            table.data-layout td::before {
                content: attr(data-label);
                display: block;
                font-size: .78rem;
                opacity: .8;
                margin-bottom: .1rem;
                text-transform: uppercase;
                letter-spacing: .02em;
            }
            /* Ações em grid */
            td[data-cell="acoes"] {
                display: grid;
                grid-template-columns: repeat(3, minmax(40px, 1fr));
                gap: .4rem;
                margin-top: .35rem;
            }
        }

        /* Botões de ação na tabela */
        .btn-table {
            width: 40px; height: 40px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,.08);
            transition: transform .08s ease, box-shadow .2s ease, background-color .2s ease, border-color .2s ease;
        }
        .btn-table:hover { transform: translateY(-1px); }
        body.dark-mode .btn-table { border-color: rgba(255,255,255,.12); }

        /* Ajuste do DataTables para caber melhor */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 999px; padding: .4rem .8rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 10px !important;
            margin: 0 .1rem;
        }

        /* ===== Modal de Anexos – Estética moderna ===== */
        #viewAttachmentsModal .modal-dialog {
            max-width: min(95vw, 1200px);
        }
        #viewAttachmentsModal .modal-content {
            border-radius: 14px;
            border: 1px solid rgba(0,0,0,.08);
            box-shadow: 0 8px 28px rgba(0,0,0,.18);
            overflow: hidden;
        }
        #viewAttachmentsModal .modal-header {
            background: rgba(255,255,255,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: .9rem 1.1rem;
        }
        #viewAttachmentsModal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
        }
        #viewAttachmentsModal .modal-body {
            background: #fafafa;
        }
        #viewAttachmentsModal .modal-footer {
            background: rgba(250,250,250,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            border-top: 1px solid rgba(0,0,0,.06);
        }

        body.dark-mode #viewAttachmentsModal .modal-content { border-color: rgba(255,255,255,.08); box-shadow: 0 8px 28px rgba(0,0,0,.55); }
        body.dark-mode #viewAttachmentsModal .modal-header { background: rgba(35,39,42,.8); border-bottom-color: rgba(255,255,255,.06); }
        body.dark-mode #viewAttachmentsModal .modal-title { color: #fff; }
        body.dark-mode #viewAttachmentsModal .modal-body { background: #1f2326; }
        body.dark-mode #viewAttachmentsModal .modal-footer { background: rgba(35,39,42,.8); border-top-color: rgba(255,255,255,.06); }

        /* ===== Dropzone custom (sem lib externa) ===== */
        .dropzone {
            border: 2px dashed rgba(0,0,0,.15);
            border-radius: 14px;
            background: #fff;
            padding: 20px;
            display: grid;
            place-items: center;
            text-align: center;
            transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
            cursor: pointer;
        }
        .dropzone:hover { border-color: rgba(0,0,0,.25); }
        .dropzone.dragover {
            background: #f3f7ff;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,.15) inset;
        }
        .dropzone .dz-icon {
            font-size: 36px;
            margin-bottom: 8px;
            opacity: .9;
        }
        .dropzone .dz-text {
            font-weight: 600;
        }
        .dropzone .dz-hint {
            font-size: .9rem;
            opacity: .8;
        }
        .hidden-input {
            display: none;
        }
        .upload-progress {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: rgba(0,0,0,.08);
            overflow: hidden;
            margin-top: 10px;
        }
        .upload-progress > div {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #0d6efd, #17a2b8);
            transition: width .15s ease;
        }
        .upload-status {
            font-size: .9rem;
            margin-top: 6px;
            opacity: .85;
        }

        body.dark-mode .dropzone { background: #23272a; border-color: rgba(255,255,255,.15); }
        body.dark-mode .dropzone:hover { border-color: rgba(255,255,255,.25); }
        body.dark-mode .dropzone.dragover {
            background: #1e2a3d;
            border-color: #66b0ff;
            box-shadow: 0 0 0 3px rgba(102,176,255,.15) inset;
        }
        body.dark-mode .upload-progress { background: rgba(255,255,255,.12); }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">

            <!-- HERO / TÍTULO -->
            <section class="page-hero">
                <div class="title-row">
                    <div class="title-icon"><i class="fa fa-file-text-o"></i></div>
                    <div>
                        <h1>Pesquisa de Ofícios</h1>
                        <div class="subtitle muted">
                            Consulta e gestão de ofícios com filtros rápidos, visualização em PDF e anexos.
                        </div>
                        <?php if (!$hasFilter): ?>
                            <div class="mt-2">
                                <span class="chip"><i class="fa fa-info-circle"></i> Exibindo os 20 mais recentes. Use os filtros para pesquisar mais.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- FILTROS (UI/UX MODERNO) -->
            <form id="searchForm" method="GET" class="filter-card">
                <div class="section-title">Filtros de pesquisa</div>
                <div class="section-sub">Refine sua busca por número, data, assunto, destinatário e dados complementares.</div>

                <div class="row">
                    <div class="col-6 col-md-2 mb-3">
                        <label for="numero" class="sr-only">Número</label>
                        <div class="input-chip">
                            <i class="fa fa-hashtag" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="numero" name="numero" placeholder="Número"
                                   value="<?php echo htmlspecialchars($_GET['numero'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label for="data" class="sr-only">Data</label>
                        <div class="input-chip">
                            <i class="fa fa-calendar" aria-hidden="true"></i>
                            <input type="date" class="form-control" id="data" name="data"
                                   value="<?php echo htmlspecialchars($_GET['data'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label for="assunto" class="sr-only">Assunto</label>
                        <div class="input-chip">
                            <i class="fa fa-book" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="assunto" name="assunto" placeholder="Assunto"
                                   value="<?php echo htmlspecialchars($_GET['assunto'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label for="destinatario" class="sr-only">Destinatário</label>
                        <div class="input-chip">
                            <i class="fa fa-user" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="destinatario" name="destinatario" placeholder="Destinatário"
                                   value="<?php echo htmlspecialchars($_GET['destinatario'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-12 mb-3">
                        <label for="dados_complementares" class="sr-only">Dados Complementares</label>
                        <div class="input-chip">
                            <i class="fa fa-file-text" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="dados_complementares" name="dados_complementares" placeholder="Dados Complementares"
                                   value="<?php echo htmlspecialchars($_GET['dados_complementares'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="filter-actions mt-2">
                    <button type="submit" class="btn btn-primary btn-pill">
                        <i class="fa fa-filter" aria-hidden="true"></i> Filtrar
                    </button>
                    <button id="add-button" type="button" class="btn btn-success btn-pill" onclick="window.location.href='cadastrar-oficio.php'">
                        <i class="fa fa-plus" aria-hidden="true"></i> Novo Ofício
                    </button>
                    <?php if ($hasFilter): ?>
                        <a href="index.php" class="btn btn-soft btn-pill">
                            <i class="fa fa-times" aria-hidden="true"></i> Limpar filtros
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!$hasFilter): ?>
                    <div class="mt-2 hint text-muted">
                        Dica: para ver mais resultados, use os campos acima e clique em <strong>Filtrar</strong>.
                    </div>
                <?php endif; ?>
            </form>

            <div class="table-responsive table-wrap mt-3">
                <h5 class="mb-2">Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered data-layout">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Data</th>
                            <th>Assunto</th>
                            <th>Destinatário</th>
                            <th>Cargo</th>
                            <th style="width: 15%;">Dados Complementares</th>
                            <th style="width: 10%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="oficioTable">
                        <?php foreach ($oficios as $oficio) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($oficio['numero']); ?></td>
                                <td data-order="<?php echo date('Y-m-d', strtotime($oficio['data'])); ?>"><?php echo date('d/m/Y', strtotime($oficio['data'])); ?></td>
                                <td><?php echo htmlspecialchars($oficio['assunto']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['destinatario']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['cargo']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['dados_complementares']); ?></td>
                                <td data-cell="acoes">
                                    <button class="btn btn-info btn-sm btn-table" title="Visualizar ofício" onclick="viewOficio('<?php echo $oficio['numero']; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-sm btn-table <?php echo ($oficio['status'] == 1 ? 'btn-secondary' : 'btn-warning'); ?>" title="Editar ofício" onclick="editOficio('<?php echo $oficio['numero']; ?>')" <?php if ($oficio['status'] == 1) echo 'disabled'; ?>><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                    <button class="btn btn-sm btn-primary btn-table" title="Anexos" onclick="viewAttachments('<?php echo $oficio['numero']; ?>')"><i class="fa fa-paperclip" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Modal Visualizar Ofício -->
    <div class="modal fade" id="viewOficioModal" tabindex="-1" role="dialog" aria-labelledby="viewOficioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <h5 class="modal-title" id="viewOficioModalLabel">Visualizar Ofício</h5>
                    <div class="toolbar">
                        <button type="button" class="btn btn-ghost btn-pill" id="refreshPdfBtn" title="Recarregar visualização">
                            <i class="fa fa-refresh" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" title="Fechar">
                            &times;
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <iframe id="oficioPDF" src="" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="left-actions">
                        <button type="button" class="btn btn-primary btn-pill" id="lockButton">
                            <i class="fa fa-lock" aria-hidden="true"></i> Travar Edição
                        </button>
                    </div>
                    <div class="right-actions">
                        <button type="button" class="btn btn-secondary btn-pill" data-dismiss="modal">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Anexos -->
    <div class="modal fade" id="viewAttachmentsModal" tabindex="-1" role="dialog" aria-labelledby="viewAttachmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <h5 class="modal-title" id="viewAttachmentsModalLabel">Anexos do Ofício</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" title="Fechar">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="oficioDetails" class="mb-3">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label for="detNumero">Nº do Ofício:</label>
                                <input type="text" class="form-control" id="detNumero" disabled>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="detTarefaId">Nº da Tarefa:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="detTarefaId" disabled>
                                    <div class="input-group-append">
                                        <button id="viewTaskButton" class="btn btn-info btn-sm" style="height: 38px; display: none;" title="Ver tarefa">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="detData">Data:</label>
                                <input type="text" class="form-control" id="detData" disabled>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="detAssunto">Assunto:</label>
                                <input type="text" class="form-control" id="detAssunto" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="detDestinatario">Destinatário:</label>
                                <input type="text" class="form-control" id="detDestinatario" disabled>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="detCargo">Cargo:</label>
                                <input type="text" class="form-control" id="detCargo" disabled>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="detDadosComplementares">Dados Complementares:</label>
                            <textarea class="form-control" id="detDadosComplementares" rows="5" disabled></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="detAssinante">Assinante:</label>
                                <input type="text" class="form-control" id="detAssinante" disabled>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="detCargoAssinante">Cargo do Assinante:</label>
                                <input type="text" class="form-control" id="detCargoAssinante" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="mb-0">Anexos</h4>
                        <small class="text-muted">Formatos aceitos dependerão do endpoint atual.</small>
                    </div>
                    <div id="attachmentsContent" class="mb-3"></div>

                    <h6 class="mb-2">Anexar Novo Arquivo</h6>
                    <!-- Dropzone custom -->
                    <div id="dropzone" class="dropzone" tabindex="0">
                        <div class="dz-icon">
                            <i class="fa fa-cloud-upload" aria-hidden="true"></i>
                        </div>
                        <div class="dz-text">Arraste e solte aqui</div>
                        <div class="dz-hint">ou clique para selecionar um arquivo</div>
                        <input type="file" id="fileInput" class="hidden-input" name="file" />
                        <input type="hidden" name="numero" id="numeroOficio">
                        <div class="upload-progress" style="display:none;"><div></div></div>
                        <div class="upload-status text-muted" style="display:none;"></div>
                    </div>
                    <small class="text-muted d-block mt-2">* Ao concluir o upload, a lista de anexos é atualizada automaticamente.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-pill" data-dismiss="modal">Fechar</button>
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
        $(document).ready(function() {
            // Ajuste de altura total do modal ao abrir (fallback)
            $('#viewOficioModal').on('shown.bs.modal', function() {
                $(this).find('.modal-dialog').css({
                    'max-height': '100vh',
                    'height': '100%'
                });
            });

            // DataTables
            $('#tabelaResultados').DataTable({
                "language": { "url": "../style/Portuguese-Brasil.json" },
                "order": [[1, 'desc']],
                "autoWidth": false
            });

            // Adicionar labels automáticos para o layout de cards no mobile
            (function applyDataLabels(){
                const headers = [];
                $('#tabelaResultados thead th').each(function(){ headers.push($(this).text().trim()); });
                $('#tabelaResultados tbody tr').each(function(){
                    $(this).find('td').each(function(i){
                        $(this).attr('data-label', headers[i] || '');
                    });
                });
            })();

            // Recarregar o iframe rapidamente
            $('#refreshPdfBtn').on('click', function(){
                const $iframe = $('#oficioPDF');
                const src = $iframe.attr('src');
                if(!src) return;
                // força recarga (cache-buster)
                const glue = src.includes('?') ? '&' : '?';
                $iframe.attr('src', src + glue + 't=' + Date.now());
            });

            /* ========= Dropzone custom ========= */
            const dz = document.getElementById('dropzone');
            const fileInput = document.getElementById('fileInput');
            const progWrap = dz.querySelector('.upload-progress');
            const progBar  = progWrap.querySelector('div');
            const statusEl = dz.querySelector('.upload-status');

            function resetProgress(){
                progBar.style.width = '0%';
                progWrap.style.display = 'none';
                statusEl.style.display = 'none';
                statusEl.textContent = '';
            }

            function startProgress(){
                progWrap.style.display = 'block';
                progBar.style.width = '0%';
                statusEl.style.display = 'block';
                statusEl.textContent = 'Enviando...';
            }

            function setProgress(p){
                progBar.style.width = p + '%';
            }

            function finishProgress(msg, isOk){
                statusEl.textContent = msg || (isOk ? 'Upload concluído.' : 'Falha no upload.');
                if(isOk){
                    // pequena animação de preenchimento
                    progBar.style.width = '100%';
                }
            }

            function uploadFile(file){
                if(!file) return;

                // Se o endpoint aceitar apenas 1 arquivo, rejeitar múltiplos
                // (pegamos somente o primeiro arquivo)
                resetProgress();
                startProgress();

                const numero = $('#numeroOficio').val();
                const formData = new FormData();
                formData.append('file', file);
                formData.append('numero', numero);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_attachment.php', true);

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        setProgress(percent);
                    }
                });

                xhr.onreadystatechange = function(){
                    if(xhr.readyState === 4){
                        if(xhr.status >= 200 && xhr.status < 300){
                            finishProgress('Arquivo anexado com sucesso.', true);
                            // feedback visual
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Arquivo anexado com sucesso.',
                                showConfirmButton: false,
                                timer: 1600
                            }).then(() => {
                                loadAttachments(numero);
                                resetProgress();
                            });
                        } else {
                            finishProgress('Erro ao anexar arquivo.', false);
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro ao anexar arquivo.',
                                showConfirmButton: false,
                                timer: 1800
                            });
                        }
                    }
                };

                xhr.send(formData);
            }

            // Eventos da dropzone
            dz.addEventListener('click', () => fileInput.click());
            dz.addEventListener('keydown', (e) => {
                if(e.key === 'Enter' || e.key === ' '){
                    e.preventDefault();
                    fileInput.click();
                }
            });
            dz.addEventListener('dragover', (e) => {
                e.preventDefault();
                dz.classList.add('dragover');
            });
            dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
            dz.addEventListener('drop', (e) => {
                e.preventDefault();
                dz.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if(!files || !files.length) return;
                // Pega somente o primeiro arquivo (ajuste se o endpoint passar a aceitar múltiplos)
                uploadFile(files[0]);
            });
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files && e.target.files[0];
                if(file) uploadFile(file);
                // Reseta input para permitir mesmo arquivo novamente
                e.target.value = '';
            });
        });

        function viewOficio(numero) {
            // Primeiro, verificamos o JSON de configuração
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false,
                success: function(data) {
                    var url = '';
                    if (data.timbrado === 'S') {
                        url = 'view_oficio.php?numero=' + numero;
                    } else if (data.timbrado === 'N') {
                        url = 'view-oficio.php?numero=' + numero;
                    }

                    // Define a URL no iframe e abre o modal
                    $('#oficioPDF').attr('src', url);
                    $('#viewOficioModal').modal('show');
                    
                    // Verifica o status do ofício
                    $.ajax({
                        url: 'get_status.php',
                        type: 'GET',
                        data: { numero: numero },
                        success: function(response) {
                            var status = JSON.parse(response).status;
                            if (status == 1) {
                                $('#lockButton').hide();
                            } else {
                                $('#lockButton').show();
                            }
                        },
                        error: function() {
                            alert('Erro ao verificar status do ofício.');
                        }
                    });

                    // Define a ação para o botão de bloqueio
                    $('#lockButton').off('click').on('click', function() {
                        lockOficio(numero);
                    });

                },
                error: function() {
                    alert('Erro ao carregar a configuração de timbrado.');
                }
            });
        }

        function viewAttachments(numero) {
            $.ajax({
                url: 'get_oficio_details.php',
                type: 'GET',
                data: { numero: numero },
                success: function(response) {
                    var data = JSON.parse(response);
                    $('#detNumero').val(data.numero);
                    $('#detData').val(formatDateToBrazilian(data.data));
                    $('#detAssunto').val(data.assunto);
                    $('#detDestinatario').val(data.destinatario);
                    $('#detCargo').val(data.cargo);
                    $('#detAssinante').val(data.assinante);
                    $('#detCargoAssinante').val(data.cargo_assinante);
                    $('#detDadosComplementares').val(data.dados_complementares);
                    $('#numeroOficio').val(data.numero);
                    loadAttachments(data.numero);

                    // Verificar se o número do ofício existe na tabela tarefas
                    $.ajax({
                        url: 'verificar_tarefa.php',
                        type: 'GET',
                        data: { numero_oficio: numero },
                        success: function(response) {
                            var result = JSON.parse(response);
                            if (result.status === 'success') {
                                $('#detTarefaId').val(result.id);
                                // Obter o token da tarefa e atualizar o botão
                                $.ajax({
                                    url: 'get_tarefa_token.php',
                                    type: 'GET',
                                    data: { id: result.id },
                                    success: function(response) {
                                        var tarefa = JSON.parse(response);
                                        if (tarefa.status === 'success') {
                                            $('#viewTaskButton').attr('onclick', `window.location.href='../tarefas/index_tarefa.php?token=${tarefa.token}'`);
                                            $('#viewTaskButton').show();
                                        } else {
                                            $('#viewTaskButton').hide();
                                        }
                                    },
                                    error: function() {
                                        alert('Erro ao obter o token da tarefa.');
                                        $('#viewTaskButton').hide();
                                    }
                                });
                            } else {
                                $('#detTarefaId').val('Não encontrado');
                                $('#viewTaskButton').hide();
                                console.log(result.message);
                            }
                        },
                        error: function() {
                            alert('Erro ao verificar a tarefa.');
                            $('#viewTaskButton').hide();
                        }
                    });

                    $('#viewAttachmentsModal').modal('show');
                },
                error: function() {
                    alert('Erro ao obter detalhes do ofício.');
                }
            });
        }

        function formatDateToBrazilian(date) {
            var dateParts = date.split('-');
            return dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
        }

        function loadAttachments(numero) {
            $.ajax({
                url: 'get_attachments.php',
                type: 'GET',
                data: { numero: numero },
                success: function(response) {
                    $('#attachmentsContent').html(response);
                },
                error: function(xhr, status, error) {
                    console.log("Erro na requisição:", error);
                    alert('Erro ao carregar anexos.');
                }
            });
        }

        $(document).on('click', '.visualizar-anexo', function() {
            var filePath = $(this).data('file');
            window.open(filePath, '_blank');
        });

        // Removeu-se o formulário antigo; upload agora é via dropzone custom (XHR).

        function editOficio(numero) {
            window.location.href = 'edit_oficio.php?numero=' + numero;
        }

        function lockOficio(numero) {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Tem certeza que deseja travar a edição deste ofício?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, travar',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'lock_oficio.php',
                        type: 'POST',
                        data: { numero: numero },
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Edição do ofício travada com sucesso.',
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro ao travar a edição do ofício.',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    });
                }
            });
        }

        $(document).on('click', '.excluir-anexo', function() {
            var filePath = $(this).data('file'); 
            var numero = $(this).data('numero'); 

            Swal.fire({
                title: 'Tem certeza?',
                text: "Você quer excluir esse anexo?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, excluir!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'mover_para_lixeira.php',
                        type: 'POST',
                        data: { file: filePath, numero: numero },
                        success: function(response) {
                            var data = JSON.parse(response);
                            if (data.status === 'success') {
                                Swal.fire('Excluído!', data.message, 'success');
                                loadAttachments(numero);
                            } else {
                                Swal.fire('Erro', data.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Erro', 'Falha ao excluir o anexo.', 'error');
                        }
                    });
                }
            });
        });

        $(document).ready(function() {
            var currentYear = new Date().getFullYear();

            function validateDate(input) {
                var val = $(input).val();
                if (!val) return;
                var selectedDate = new Date(val + 'T00:00:00');
                if (selectedDate.getFullYear() > currentYear) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Data inválida',
                        text: 'O ano não pode ser maior que o ano atual.',
                        confirmButtonText: 'Ok'
                    });
                    $(input).val('');
                }
            }

            $('#data').on('change', function() {
                validateDate(this);
            });
        });
    </script>

<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
