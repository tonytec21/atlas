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
    <title>Pesquisa de Guias de Recebimento</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <!-- Sem DataTables: resultados agora em cards -->

    <style>
        :root{
            --bg: #0b1020;
            --card: #0f172a;
            --muted: #94a3b8;
            --text: #e2e8f0;
            --primary: #2563eb;
            --primary-600:#1d4ed8;
            --success:#16a34a;
            --warning:#f59e0b;
            --danger:#ef4444;
            --surface:#111827;
            --chip:#1f2937;
            --border:#1f2937;
            --shadow: 0 10px 25px rgba(0,0,0,.35);
            --card-fixed-h: 320px; /* altura uniforme dos cards */
        }
        body.light-mode {
            --bg: #f6f7fb;
            --card:#ffffff;
            --muted:#6b7280;
            --text:#0f172a;
            --primary:#2563eb;
            --primary-600:#1d4ed8;
            --success:#16a34a;
            --warning:#f59e0b;
            --danger:#ef4444;
            --surface:#ffffff;
            --chip:#eef2ff;
            --border:#e5e7eb;
            --shadow: 0 10px 20px rgba(2,6,23,.08);
            --card-fixed-h: 320px;
        }

        /* área principal */
        #main.main-content{ background: var(--bg); }

        .page-title{ color: var(--text); margin: 8px 0 2px; font-weight: 700; letter-spacing:.2px; }
        .sub-title{ color: var(--muted); font-size: .95rem; }

        .top-actions .btn{
            display: inline-flex; align-items: center; gap: .5rem;
            border-radius: 12px; padding: .6rem 1rem; font-weight: 600;
            transition: transform .15s ease, box-shadow .2s ease, background .2s;
            box-shadow: 0 6px 16px rgba(37,99,235,.18);
        }
        .top-actions .btn:hover{ transform: translateY(-1px); }
        .btn-success{ box-shadow: 0 8px 18px rgba(22,163,74,.18); }
        .btn-secondary{ box-shadow: 0 8px 18px rgba(15,23,42,.12); }

        .filter-card{
            background: var(--card); border: 1px solid var(--border);
            border-radius: 16px; padding: 18px; box-shadow: var(--shadow);
        }
        .filter-card label{ color: var(--muted); font-weight: 600; font-size: .85rem; }
        .filter-card .form-control, .filter-card .form-select{
            background: var(--surface); color: var(--text);
            border: 1px solid var(--border); border-radius: 12px;
        }
        .filter-actions{ display:flex; gap:.75rem; align-items:center; }
        .filter-actions .btn{ border-radius: 12px; padding:.65rem 1rem; font-weight:700; }
        .quick-filters{ display:flex; flex-wrap:wrap; gap:.5rem; }
        .chip{
            background: var(--chip); color: var(--text);
            border:1px solid var(--border); padding:.4rem .7rem;
            border-radius:999px; cursor:pointer; font-size:.85rem; user-select:none;
        }
        .chip.active, .chip:hover{ background: var(--primary); color:#fff; border-color: var(--primary-600); }

        .results-wrap{ margin-top: 16px; }
        .results-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .results-count{ color: var(--muted); font-weight:600; }

        .cards-grid{ display:grid; grid-template-columns: repeat(12,1fr); grid-gap: 16px; }
        @media (max-width: 1399px){ .cards-grid{ grid-template-columns: repeat(8,1fr); } }
        @media (max-width: 991px){ .cards-grid{ grid-template-columns: repeat(6,1fr); } }
        @media (max-width: 767px){ .cards-grid{ grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 480px){ .cards-grid{ grid-template-columns: repeat(1,1fr); } }

        .guia-card{
            grid-column: span 4; background: var(--card); border: 1px solid var(--border);
            border-radius: 16px; box-shadow: var(--shadow); padding: 14px 14px 12px; position: relative; overflow: hidden;
            display:flex; flex-direction:column; height: var(--card-fixed-h); /* altura fixa p/ simetria */
        }
        .guia-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
        .badge-status{ padding:.3rem .55rem; border-radius: 999px; font-size:.75rem; font-weight:700; letter-spacing:.2px; }
        .badge-success{ background: rgba(22,163,74,.14); color: #16a34a; }
        .badge-warning{ background: rgba(245,158,11,.14); color: #f59e0b; }
        .badge-info{ background: rgba(37,99,235,.12); color: #2563eb; }
        .badge-muted{ background: rgba(148,163,184,.18); color: #64748b; }

        .guia-title{ color: var(--text); font-size: 1.05rem; font-weight: 800; margin:0; }
        .guia-meta{ display:flex; flex-wrap:wrap; gap:.5rem .75rem; margin: 6px 0 10px; }
        .meta-item{ color: var(--muted); font-size:.86rem; }
        .meta-item i{ margin-right:.4rem; opacity:.8; }

        .guia-body{
            background: var(--surface); border:1px dashed var(--border); border-radius:12px;
            padding:10px 12px; color: var(--text); flex:1; overflow:hidden; /* garante simetria */
        }
        .guia-actions{ display:flex; gap:.5rem; align-items:center; justify-content:flex-end; margin-top: 10px; }

        .icon-btn{
            width:42px; height:42px; display:inline-flex; align-items:center; justify-content:center;
            border:none; border-radius:10px; background: var(--surface); color: var(--text);
            transition: transform .08s ease, background .15s ease, box-shadow .15s ease;
        }
        .icon-btn:hover{ transform: translateY(-1px); background: #0ea5e9; color:#fff; }
        .icon-btn.print:hover{ background: var(--primary); }
        .icon-btn.link:hover{ background: #14b8a6; }
        .icon-btn.task:hover{ background: #f59e0b; }
        .icon-btn.edit:hover{ background: #f97316; }

        /* Truncamento elegante + clique para expansão */
        .doc-snippet{
            margin-top: 4px;
            padding:8px 10px;
            background: rgba(37,99,235,.06);
            border:1px dashed var(--border);
            border-radius:10px;
            cursor: pointer;
            position: relative;
            color: var(--text);
            max-height: 60px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .doc-snippet:hover{ background: rgba(37,99,235,.12); }
        .doc-snippet::after{
            /* content:"Clique para visualizar tudo"; */
            position:absolute; right:8px; bottom:6px;
            font-size:.75rem; color: var(--muted);
        }

        .clamp-1{
            display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
        }
        .clamp-2{
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        .skeleton{ position:relative; overflow:hidden; border-radius:16px; height:140px;
            background: linear-gradient(90deg, rgba(0,0,0,.08), rgba(255,255,255,.08), rgba(0,0,0,.08));
            background-size: 600% 600%; animation: shimmer 1.2s infinite; border:1px solid var(--border);
        }
        @keyframes shimmer { 0%{ background-position: 0% 50%; } 100%{ background-position: 100% 50%; } }
        .skeleton-grid{ display:grid; grid-template-columns: repeat(12,1fr); grid-gap:16px; }
        .skeleton-item{ grid-column: span 4; height:160px; border-radius:16px; }

        .empty-state{ background: var(--card); border:1px dashed var(--border); border-radius:16px; padding:30px; text-align:center; color: var(--muted); }
        .empty-state i{ font-size: 40px; opacity:.6; margin-bottom:10px; display:block; }

        .modal-content{ border-radius:20px; border:1px solid var(--border); overflow:hidden; box-shadow: var(--shadow); background: var(--card); }
        .modal-header{ background: linear-gradient(135deg, var(--primary), var(--primary-600)); color: #fff; border-bottom:none; }
        .modal-header .modal-title{ font-weight:800; letter-spacing:.3px; }
        .modal-body{ background: var(--surface); }
        .modal-footer{ border-top: none; background: var(--card); }

        .btn-close{ outline:none; border:none; background:none; padding:0; font-size:1.7rem; cursor:pointer; transition: transform .2s ease; color:#fff; }
        .btn-close:hover{ transform: scale(1.15); }
        .btn-close:focus{ outline:none; }

        .form-control, .form-select, .form-control-file{ background: var(--card); color: var(--text); border: 1px solid var(--border); border-radius: 12px; }
        .form-control:focus, .form-select:focus{ border-color: var(--primary); box-shadow: 0 0 0 .15rem rgba(37,99,235,.2); }

        .modal-xxl{ max-width: 95%!important; }

        #checklistSelect{ max-width:100%; overflow:hidden; text-overflow: ellipsis; white-space: nowrap; display:block; }

        /* Modal Visualização - itens de informação */
        .info-box{
            background: var(--card);
            border:1px solid var(--border);
            border-radius:14px;
            padding:12px 14px;
            color: var(--text);
        }
        .info-label{ color: var(--muted); font-size:.8rem; margin-bottom:4px; text-transform: uppercase; letter-spacing:.4px; }
        .info-value{ font-weight:700; }
        .pill{
            display:inline-flex; align-items:center; gap:.4rem;
            border-radius:999px; padding:.35rem .6rem; font-size:.8rem;
            border:1px solid var(--border); background: var(--surface);
            color: var(--text);
        }
        .btn-soft{
            border-radius:12px; border:1px solid var(--border); background: var(--surface);
            color: var(--text); padding:.6rem .9rem; font-weight:700;
        }
        .btn-soft:hover{ background: rgba(255,255,255,.06); }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <main id="main" class="main-content">
        <div class="container">

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 top-actions">
                <div>
                    <h3 class="page-title">Guias de Recebimento</h3>
                    <div class="sub-title">Pesquise, crie e vincule tarefas de forma rápida e organizada.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-success mr-2 mb-2" data-toggle="modal" data-target="#modalCriarGuia">
                        <i class="fa fa-plus"></i> Criar Guia
                    </button>
                    <a href="../checklist/checklist.php" class="btn btn-primary mr-2 mb-2">
                        <i class="fa fa-list-alt"></i> Checklists
                    </a>
                    <a href="../tarefas/consulta-tarefas.php" class="btn btn-secondary mb-2">
                        <i class="fa fa-search" aria-hidden="true"></i> Pesquisar Tarefas
                    </a>
                    <button id="btnTriagemComunitario" style="display:none" class="btn btn-secondary mb-2" onclick="abrirTriagem()">
                        <i class="fa fa-users"></i> Triagem Comunitário
                    </button>
                </div>
            </div>

            <div class="filter-card mb-3">
                <form id="searchForm">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="numeroGuia">Nº Guia</label>
                            <input type="text" class="form-control" id="numeroGuia" name="numeroGuia" placeholder="Ex.: 1024" inputmode="numeric" pattern="[0-9]*">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="numeroTarefa">Nº Tarefa</label>
                            <input type="text" class="form-control" id="numeroTarefa" name="numeroTarefa" placeholder="Ex.: 38427" inputmode="numeric" pattern="[0-9]*">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="dataRecebimento">Data de Recebimento</label>
                            <input type="date" class="form-control" id="dataRecebimento" name="dataRecebimento">
                        </div>
                        <div class="form-group col-md-5">
                            <label for="funcionario">Funcionário</label>
                            <select class="form-control" id="funcionario" name="funcionario">
                                <option value="">Selecione o Funcionário</option>
                                <?php
                                $sqlFuncionarios = "SELECT DISTINCT funcionario FROM guia_de_recebimento WHERE funcionario IS NOT NULL AND funcionario != ''";
                                $resultFuncionarios = $conn->query($sqlFuncionarios);
                                if ($resultFuncionarios && $resultFuncionarios->num_rows > 0) {
                                    while ($row = $resultFuncionarios->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($row['funcionario'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['funcionario'], ENT_QUOTES, 'UTF-8') . "</option>";
                                    }
                                } else {
                                    echo "<option value=''>Nenhum funcionário encontrado</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group col-md-8">
                            <label for="cliente">Apresentante</label>
                            <input type="text" class="form-control" id="cliente" name="cliente" placeholder="Nome do Apresentante">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="documentoApresentante">CPF/CNPJ</label>
                            <input type="text" class="form-control" id="documentoApresentante" name="documentoApresentante" placeholder="CPF ou CNPJ">
                        </div>

                        <div class="form-group col-md-8">
                            <label for="nomePortador">Portador de Dados</label>
                            <input type="text" class="form-control" id="nomePortador" name="nomePortador" placeholder="Nome do Portador de Dados">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="documentoPortador">CPF/CNPJ do Portador</label>
                            <input type="text" class="form-control" id="documentoPortador" name="documentoPortador" placeholder="CPF ou CNPJ">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="quick-filters">
                            <!-- data-action => ação a enviar ao backend | data-rel => janela temporal para filtrar no cliente -->
                            <span class="chip active" data-action="task_id_zero" data-rel="">Sem tarefa</span>
                            <span class="chip" data-action="all" data-rel="">Todos</span>
                            <span class="chip" data-action="all" data-rel="today">Hoje</span>
                            <span class="chip" data-action="all" data-rel="week">Últimos 7 dias</span>
                        </div>
                        <div class="filter-actions">
                            <button type="button" id="btnLimpar" class="btn btn-outline-secondary">
                                <i class="fa fa-undo"></i> Limpar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-filter"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="results-wrap">
                <div class="results-head">
                    <h5 class="page-title" style="font-size:1.1rem">Resultados</h5>
                    <div class="results-count" id="resultsCount">—</div>
                </div>

                <div id="skeleton" class="skeleton-grid" style="display:none">
                    <div class="skeleton skeleton-item"></div>
                    <div class="skeleton skeleton-item"></div>
                    <div class="skeleton skeleton-item"></div>
                    <div class="skeleton skeleton-item"></div>
                    <div class="skeleton skeleton-item"></div>
                    <div class="skeleton skeleton-item"></div>
                </div>

                <div id="resultadosCards" class="cards-grid"></div>

                <div id="emptyState" class="empty-state" style="display:none">
                    <i class="fa fa-inbox"></i>
                    Nenhuma guia encontrada para os filtros selecionados.
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL: Criar Tarefa -->
    <div class="modal fade" id="tarefaModal" tabindex="-1" role="dialog" aria-labelledby="tarefaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tarefaModalLabel"><i class="fa fa-clock-o"></i> Criar Tarefa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="taskForm" method="POST" action="save_task.php" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="title">Título da Tarefa</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="category">Categoria</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";
                                    $result = $conn->query($sql);
                                    if ($result && $result->num_rows > 0) {
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
                                    $loggedInUser = $_SESSION['username'] ?? '';
                                    if ($result && $result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $selected = ($row['nome_completo'] === $loggedInUser) ? 'selected' : '';
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
                                    if ($result && $result->num_rows > 0) {
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
                            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="attachments">Anexos</label>
                            <input type="file" class="form-control-file" id="attachments" name="attachments[]" multiple>
                        </div>
                        <input type="hidden" id="createdBy" name="createdBy" value="<?php echo $_SESSION['username'] ?? ''; ?>">
                        <input type="hidden" id="createdAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">
                        <input type="hidden" id="guiaId" name="guiaId">
                        <button type="submit" class="btn btn-primary w-100">Criar Tarefa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Vincular Tarefa -->
    <div class="modal fade" id="vincularTarefaModal" tabindex="-1" role="dialog" aria-labelledby="vincularTarefaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document" style="max-width: 380px!important">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vincularTarefaLabel"><i class="fa fa-link"></i> Vincular Tarefa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="vincularTarefaForm">
                        <div class="form-group">
                            <label for="protocolo-geral">Nº do Protocolo Geral</label>
                            <input type="text" class="form-control" id="protocolo-geral" inputmode="numeric" pattern="[0-9]*" placeholder="Digite o nº do protocolo geral" maxlength="12">
                        </div>
                        <input type="hidden" id="modal-guia-id" value="">
                    </form>
                    <small class="text-muted">Informe o número do protocolo já existente para vincular.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="vincularTarefa()"><i class="fa fa-link" aria-hidden="true"></i> Vincular</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Criar Guia -->
    <div class="modal fade" id="modalCriarGuia" tabindex="-1" aria-labelledby="modalCriarGuiaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content shadow-lg rounded">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCriarGuiaLabel"><i class="fa fa-file-text"></i> Criar Guia de Recebimento</h5>
                    <button type="button" class="btn-close text-white" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formCriarGuia">
                        <div class="form-group">
                            <label for="checklistSelect"><i class="fa fa-list-alt"></i> Utilizar Checklist</label>
                            <select class="form-control custom-select" id="checklistSelect" name="checklistSelect">
                                <option value="">Selecione um Checklist</option>
                                <?php
                                $sqlChecklists = "SELECT id, titulo FROM checklists WHERE status != 'removido' ORDER BY titulo ASC";
                                $resultChecklists = $conn->query($sqlChecklists);
                                if ($resultChecklists && $resultChecklists->num_rows > 0) {
                                    while ($row = $resultChecklists->fetch_assoc()) {
                                        echo "<option value='" . $row['id'] . "' title='" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "'>" . 
                                            htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . 
                                            "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="cliente_modal"><i class="fa fa-user"></i> Apresentante</label>
                                <input type="text" class="form-control" id="cliente_modal" name="cliente" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="documentoApresentante_modal"><i class="fa fa-id-card"></i> CPF/CNPJ</label>
                                <input type="text" class="form-control" id="documentoApresentante_modal" name="documentoApresentante" placeholder="Somente números">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="nome_portador_modal"><i class="fa fa-user-circle"></i> Portador de Dados</label>
                                <input type="text" class="form-control" id="nome_portador_modal" name="nome_portador" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="documento_portador_modal"><i class="fa fa-id-badge"></i> CPF/CNPJ</label>
                                <input type="text" class="form-control" id="documento_portador_modal" name="documento_portador" placeholder="Somente números">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="documentosRecebidos"><i class="fa fa-folder-open"></i> Documentos Recebidos</label>
                            <textarea class="form-control" id="documentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="observacoes"><i class="fa fa-sticky-note"></i> Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>

                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-4" data-dismiss="modal"><i class="fa fa-times"></i> Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" id="salvarGuiaBtn"><i class="fa fa-save"></i> Salvar Guia</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Editar Guia -->
    <div class="modal fade" id="modalEditarGuia" tabindex="-1" role="dialog" aria-labelledby="modalEditarGuiaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarGuiaLabel"><i class="fa fa-edit"></i> Editar Guia de Recebimento</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formEditarGuia">
                        <input type="hidden" id="editarGuiaId" name="guiaId">
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="editarCliente">Apresentante</label>
                                <input type="text" class="form-control" id="editarCliente" name="cliente" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarDocumentoApresentante">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="editarDocumentoApresentante" name="documentoApresentante" placeholder="Somente números">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="editarNomePortador">Portador de Dados</label>
                                <input type="text" class="form-control" id="editarNomePortador" name="nome_portador">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarDocumentoPortador">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="editarDocumentoPortador" name="documento_portador" placeholder="Somente números">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="editarDocumentosRecebidos">Documentos Recebidos</label>
                            <textarea class="form-control" id="editarDocumentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editarObservacoes">Observações</label>
                            <textarea class="form-control" id="editarObservacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="salvarEdicaoGuiaBtn">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Visualizar Guia (expansão dos Documentos) -->
    <div class="modal fade" id="visualizarGuiaModal" tabindex="-1" role="dialog" aria-labelledby="visualizarGuiaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="visualizarGuiaLabel"><i class="fa fa-file-text-o"></i> Detalhes da Guia</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">Nº Guia</div>
                                <div class="info-value" id="vg-id">-</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">Nº Tarefa</div>
                                <div class="info-value" id="vg-task">-</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">Funcionário</div>
                                <div class="info-value" id="vg-func">-</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">Recebida em</div>
                                <div class="info-value" id="vg-data">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Apresentante</div>
                                <div class="info-value" id="vg-cliente">-</div>
                                <div class="pill mt-2"><i class="fa fa-id-card-o"></i> <span id="vg-doc-apres">-</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Portador de Dados</div>
                                <div class="info-value" id="vg-portador">-</div>
                                <div class="pill mt-2"><i class="fa fa-id-badge"></i> <span id="vg-doc-port">-</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="info-box">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="info-label m-0">Documentos Recebidos</div>
                                    <button type="button" class="btn btn-soft btn-sm" id="btnCopyDocs" title="Copiar documentos">
                                        <i class="fa fa-clone"></i> Copiar
                                    </button>
                                </div>
                                <div class="mt-2" id="vg-docs" style="white-space:pre-wrap"></div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="info-box">
                                <div class="info-label">Observações</div>
                                <div id="vg-obs" style="white-space:pre-wrap"></div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <div class="mr-auto" id="vg-status-pill"></div>
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-warning" id="vg-btn-editar"><i class="fa fa-edit"></i> Editar</button>
                    <button type="button" class="btn btn-info d-none" id="vg-btn-ver-tarefa"><i class="fa fa-eye"></i> Ver Tarefa</button>
                    <button type="button" class="btn btn-primary" id="vg-btn-imprimir"><i class="fa fa-print"></i> Imprimir</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        // ===== Estado de filtros rápidos =====
        let currentAction = 'task_id_zero'; // ação enviada ao backend
        let currentRel = '';                // janela temporal (cliente): '', 'today', 'week'

        const $resultsCount = $('#resultsCount');
        const $cards = $('#resultadosCards');
        const $skeleton = $('#skeleton');
        const $empty = $('#emptyState');

        function showSkeleton(show=true){ $skeleton.toggle(show); }
        function showEmpty(show=true){ $empty.toggle(show); }
        function setCount(n){
            $resultsCount.text(n===0 ? 'Nenhuma guia encontrada' : (n===1 ? '1 guia encontrada' : `${n} guias encontradas`));
        }
        function escapeHtml(s){
            if (s===null || s===undefined) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        // deadline mínimo = agora
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('deadline');
            if (input){
                const now = new Date();
                const pad = n => ('0'+n).slice(-2);
                const minDateTime = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                input.min = minDateTime;
            }
        });

        // ===== Quick Filters =====
        document.querySelectorAll('.chip[data-action]').forEach(ch => {
            ch.addEventListener('click', () => {
                document.querySelectorAll('.chip[data-action]').forEach(x => x.classList.remove('active'));
                ch.classList.add('active');
                currentAction = ch.getAttribute('data-action') || 'all';
                currentRel = ch.getAttribute('data-rel') || '';
                $('#searchForm').trigger('submit');
            });
        });

        // ===== Máscara CPF/CNPJ =====
        function aplicarMascaraCPF_CNPJ($el){
            $el.on('input', function(){ this.value = this.value.replace(/\D+/g,''); });
            $el.on('blur', function(){
                const v = (this.value||'').replace(/\D+/g,'');
                if (v.length===11){ $(this).val(v).mask('000.000.000-00'); }
                else if (v.length===14){ $(this).val(v).mask('00.000.000/0000-00'); }
                else if (v.length){
                    Swal.fire({icon:'warning', title:'Documento inválido', text:'Digite um CPF (11) ou CNPJ (14) válido.', confirmButtonText:'OK'});
                    $(this).val('');
                }
            });
        }
        $(document).ready(function(){
            aplicarMascaraCPF_CNPJ($('#documentoApresentante'));
            aplicarMascaraCPF_CNPJ($('#documentoPortador'));
            aplicarMascaraCPF_CNPJ($('#modalCriarGuia input[name="documentoApresentante"]'));
            aplicarMascaraCPF_CNPJ($('#modalCriarGuia input[name="documento_portador"]'));
            aplicarMascaraCPF_CNPJ($('#editarDocumentoApresentante'));
            aplicarMascaraCPF_CNPJ($('#editarDocumentoPortador'));
        });

        // ===== Config Triagem =====
        document.addEventListener('DOMContentLoaded', function(){
            fetch('config_guia_de_recebimento.json')
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(config => {
                    if (config.triagem_comunitario_ativo === 'S'){
                        document.getElementById('btnTriagemComunitario').style.display = 'inline-block';
                    }
                }).catch(()=>{});
        });
        function abrirTriagem(){
            fetch('config_guia_de_recebimento.json')
                .then(r => r.json())
                .then(config => {
                    if (config.tipo_de_conexao === 'local'){ window.location.href = 'triagem_comunitario/index.php'; }
                    else if (config.tipo_de_conexao === 'online'){ window.location.href = 'triagem_comunitario_online/index.php'; }
                }).catch(err => console.error('Erro ao abrir triagem:', err));
        }

        async function verificarTimbrado(){
            try{
                const r = await fetch('../style/configuracao.json');
                const config = await r.json();
                return config.timbrado;
            }catch(e){ console.error('Erro ao carregar configuracao.json', e); return null; }
        }
        async function visualizarGuia(taskId, guiaId){
            const timbrado = await verificarTimbrado();
            const urlS = `guia_recebimento.php?id=${guiaId}`;
            const urlN = `guia-recebimento.php?id=${guiaId}`;
            if (timbrado === 'S') window.open(urlS, '_blank');
            else if (timbrado === 'N') window.open(urlN, '_blank');
            else Swal.fire({icon:'error', title:'Erro', text:'Não foi possível determinar o tipo de guia.'});
        }

        // ===== Helpers de data (filtro cliente) =====
        function toLocalDateOnly(d){
            const dt = new Date(String(d).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return null;
            return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
        }
        function isToday(d){
            const x = toLocalDateOnly(d); if (!x) return false;
            const now = new Date(); const t = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            return x.getTime() === t.getTime();
        }
        function isInLast7Days(d){
            const x = toLocalDateOnly(d); if (!x) return false;
            const now = new Date();
            const end = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23,59,59,999);
            const start = new Date(end); start.setDate(end.getDate() - 6);
            return x.getTime() >= new Date(start.getFullYear(),start.getMonth(),start.getDate()).getTime() && x.getTime() <= end.getTime();
        }

        function buildBadge(task_id){
            if (!task_id || Number(task_id)===0) return '<span class="badge-status badge-warning">Sem tarefa</span>';
            return '<span class="badge-status badge-success">Vinculada</span>';
        }
        function formatarDataBrasileira(dt){
            if (!dt) return '-';
            const d = new Date(String(dt).replace(' ', 'T'));
            if (isNaN(d.getTime())) return dt;
            return d.toLocaleString('pt-BR', {
                year:'numeric', month:'2-digit', day:'2-digit',
                hour:'2-digit', minute:'2-digit', second:'2-digit'
            });
        }

        // Renderização do card
        function renderCard(guia, temAcesso){
            const badge = buildBadge(guia.task_id);
            const apresentante = escapeHtml(guia.cliente || '-');
            const docApres = escapeHtml(guia.documento_apresentante || '-');
            const portador = escapeHtml(guia.nome_portador || '-');
            const docPort = escapeHtml(guia.documento_portador || '-');
            const func = escapeHtml(guia.funcionario || '-');
            const data = formatarDataBrasileira(guia.data_recebimento);
            const docs = escapeHtml(guia.documentos_recebidos || '');
            const obs = escapeHtml(guia.observacoes || '');

            let acoes = `
                <button class="icon-btn print" title="Imprimir Guia" data-action="print" data-task-id="${guia.task_id||0}" data-guia-id="${guia.id}">
                    <i class="fa fa-print"></i>
                </button>
            `;
            if (!guia.task_id || Number(guia.task_id)===0){
                if (temAcesso){
                    acoes += `
                        <button class="icon-btn link" title="Vincular Tarefa" data-action="vincular" data-guia-id="${guia.id}">
                            <i class="fa fa-link"></i>
                        </button>
                    `;
                }
                acoes += `
                    <button class="icon-btn task" title="Criar Tarefa" data-action="criar-tarefa" data-guia-id="${guia.id}" data-cliente="${apresentante}" data-docs="${docs}">
                        <i class="fa fa-clock-o"></i>
                    </button>
                    <button class="icon-btn edit" title="Editar Guia" data-action="editar" data-guia-id="${guia.id}">
                        <i class="fa fa-edit"></i>
                    </button>
                `;
            } else {
                acoes += `
                    <button class="icon-btn" title="Visualizar Tarefa" data-action="ver-tarefa" data-token="${escapeHtml(guia.task_token || '')}">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button class="icon-btn edit" title="Editar Guia" data-action="editar" data-guia-id="${guia.id}">
                        <i class="fa fa-edit"></i>
                    </button>
                `;
            }

            return `
            <div class="guia-card">
                <div class="guia-header">
                    <h6 class="guia-title">Guia #${guia.id}</h6>
                    ${badge}
                </div>
                <div class="guia-meta">
                    <span class="meta-item" title="Nº Tarefa">
                        <i class="fa fa-hashtag"></i> ${guia.task_id ? escapeHtml(guia.task_id) : '-'}
                    </span>
                    <span class="meta-item" title="Funcionário">
                        <i class="fa fa-user"></i> ${func}
                    </span>
                    <span class="meta-item" title="Data de Recebimento">
                        <i class="fa fa-calendar"></i> ${data}
                    </span>
                </div>
                <div class="guia-body">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <strong>Apresentante:</strong> ${apresentante}<br>
                            <strong>CPF/CNPJ:</strong> ${docApres}<br>
                            <strong>Portador:</strong> ${portador}<br>
                            <strong>Doc. Portador:</strong> ${docPort}
                        </div>
                        <div class="col-12 col-md-6 mt-2 mt-md-0">
                            <strong>Documentos:</strong>
                            <div class="doc-snippet"
                                 title="Clique para ver os documentos completos"
                                 data-action="abrir-visualizacao"
                                 data-id="${guia.id}"
                                 data-task-id="${guia.task_id || ''}"
                                 data-task-token="${escapeHtml(guia.task_token || '')}"
                                 data-func="${func}"
                                 data-data="${escapeHtml(guia.data_recebimento)}"
                                 data-cliente="${apresentante}"
                                 data-doc-apres="${docApres}"
                                 data-portador="${portador}"
                                 data-doc-port="${docPort}"
                                 data-docs="${docs}"
                                 data-obs="${obs}">
                                 ${docs || '-'}
                            </div>
                            <div class="mt-2"><strong>Obs.:</strong> <span class="clamp-2">${obs || '-'}</span></div>
                        </div>
                    </div>
                </div>
                <div class="guia-actions">
                    ${acoes}
                </div>
            </div>
            `;
        }

        // Delegação de eventos nos cards
        $('#resultadosCards').on('click', '.icon-btn', function(){
            const $btn = $(this);
            const action = $btn.data('action');
            const guiaId = $btn.data('guia-id');
            if (action === 'print'){
                const taskId = $btn.data('task-id'); visualizarGuia(taskId, guiaId);
            }
            if (action === 'vincular'){ abrirModalVincularTarefa(guiaId); }
            if (action === 'criar-tarefa'){
                const cliente = $btn.data('cliente') || '';
                const docs = $btn.data('docs') || '';
                abrirModalTarefa(guiaId, cliente, docs);
            }
            if (action === 'editar'){ abrirModalEditarGuia(guiaId); }
            if (action === 'ver-tarefa'){
                const token = $btn.data('token') || '';
                if (token){ window.location.href = `../tarefas/index_tarefa.php?token=${token}`; }
                else{ Swal.fire({icon:'error', title:'Erro', text:'Token de tarefa não encontrado.'}); }
            }
        });

        // Clique na área de Documentos => abrir modal de visualização
        $('#resultadosCards').on('click', '.doc-snippet', function(){
            const el = this.dataset;
            abrirVisualizarGuiaModal({
                id: el.id,
                task_id: el.taskId || '',
                task_token: el.taskToken || '',
                funcionario: el.func || '',
                data_recebimento: el.data || '',
                cliente: el.cliente || '',
                doc_apresentante: el.docApres || '',
                portador: el.portador || '',
                doc_portador: el.docPort || '',
                documentos: el.docs || '',
                observacoes: el.obs || ''
            });
        });

        // Verifica acesso
        async function getAcesso(){
            try{
                const r = await fetch('verificar_acesso.php');
                const j = await r.json();
                return !!j.tem_acesso;
            }catch(e){
                console.error('verificar_acesso falhou', e);
                return false;
            }
        }

        // ===== Busca com filtro temporal no cliente =====
        async function loadGuias(action, formData=''){
            if (!formData) formData = $('#searchForm').serialize();

            const act = action || currentAction || 'task_id_zero';

            showEmpty(false);
            setCount(0);
            $cards.empty();
            showSkeleton(true);

            try{
                const hasAccess = await getAcesso();
                const url = 'search_guia_recebimento.php?' + formData + '&action=' + encodeURIComponent(act);
                const r = await fetch(url, {headers:{'Accept':'application/json'}});
                let data = await r.json();

                if (Array.isArray(data) && data.length){
                    if (currentRel === 'today'){
                        data = data.filter(g => isToday(g.data_recebimento));
                    } else if (currentRel === 'week'){
                        data = data.filter(g => isInLast7Days(g.data_recebimento));
                    }
                }

                $cards.empty();
                if (Array.isArray(data) && data.length>0){
                    const html = data.map(guia => renderCard(guia, hasAccess)).join('');
                    $cards.html(html);
                    setCount(data.length);
                }else{
                    setCount(0);
                    showEmpty(true);
                }
            }catch(e){
                console.error('Erro ao buscar dados:', e);
                Swal.fire({icon:'error', title:'Erro', text:'Erro ao buscar os dados.'});
                showEmpty(true);
            }finally{
                showSkeleton(false);
            }
        }

        // Carregar inicial: sem tarefa
        $(document).ready(function(){ loadGuias('task_id_zero'); });

        // Submissão do filtro
        $('#searchForm').on('submit', function(e){
            e.preventDefault();
            loadGuias(currentAction);
        });

        // Limpar filtros
        $('#btnLimpar').on('click', function(){
            $('#searchForm')[0].reset();
            currentAction = 'task_id_zero';
            currentRel = '';
            document.querySelectorAll('.chip[data-action]').forEach(x => x.classList.remove('active'));
            document.querySelector('.chip[data-action="task_id_zero"]').classList.add('active');
            loadGuias(currentAction);
        });

        // Validação de data futura
        $(document).ready(function(){
            const currentYear = new Date().getFullYear();
            $('#dataRecebimento').on('change', function(){
                if (!this.value) return;
                const d = new Date(this.value);
                if (d.getFullYear() > currentYear){
                    Swal.fire({icon:'warning', title:'Data inválida', text:'O ano não pode ser maior que o atual.'});
                    this.value = '';
                }
            });
        });

        // ===== Ações/Modais =====
        function abrirModalTarefa(guiaId, cliente, documentosRecebidos){
            $('#title').val(' - ' + (cliente||''));
            $('#description').val('Documentos Recebidos: ' + (documentosRecebidos||''));
            $('#guiaId').val(guiaId);
            $('#tarefaModal').modal('show');
        }
        function abrirModalVincularTarefa(guiaId){
            $('#modal-guia-id').val(guiaId);
            $('#vincularTarefaModal').modal('show');
        }
        function vincularTarefa(){
            const guiaId = $('#modal-guia-id').val();
            const protocolo = $('#protocolo-geral').val();
            $.ajax({
                url:'vincular_tarefa.php', method:'POST',
                data:{ guia_id: guiaId, task_id: protocolo },
                success:function(resp){
                    if (String(resp).includes('Sucesso')){
                        $('#vincularTarefaModal').modal('hide');
                        Swal.fire({icon:'success', title:'Vinculado!', text: resp}).then(()=> location.reload());
                    }else{
                        Swal.fire({icon:'error', title:'Erro!', text: resp});
                    }
                },
                error:function(){ Swal.fire({icon:'error', title:'Erro!', text:'Erro ao conectar com o servidor.'}); }
            });
        }
        window.vincularTarefa = vincularTarefa;

        // Criar Guia
        $(document).ready(function(){
            $('#salvarGuiaBtn').off('click').on('click', function(){
                const form = document.getElementById('formCriarGuia');
                if (!form.checkValidity()){ form.reportValidity(); return; }
                const formData = $('#formCriarGuia').serialize();
                $.ajax({
                    url: 'salvar_guia.php',
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    beforeSend: ()=> Swal.fire({title:'Salvando...', html:'Aguarde, registrando guia.', allowOutsideClick:false, didOpen:()=>Swal.showLoading()}),
                    success: function(response){
                        Swal.close();
                        if (response.success){
                            $('#modalCriarGuia').modal('hide');
                            if (response.url) window.open(response.url, '_blank');
                            Swal.fire({icon:'success', title:'Sucesso!', text:'Guia de recebimento salva com sucesso!'});
                            loadGuias(currentAction);
                        }else{
                            Swal.fire({icon:'error', title:'Erro!', text:'Erro: ' + (response.message||'Falha ao salvar.')});
                        }
                    },
                    error: function(){
                        Swal.close();
                        Swal.fire({icon:'error', title:'Erro!', text:'Erro ao salvar a guia de recebimento.'});
                    }
                });
            });
            $('#modalCriarGuia').on('hidden.bs.modal', function(){ loadGuias(currentAction); });
        });

        // Editar Guia
        function abrirModalEditarGuia(guiaId){
            $.ajax({
                url:'get_edit.php', type:'GET', dataType:'json', data:{ id: guiaId },
                beforeSend: ()=> Swal.fire({title:'Carregando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()}),
                success: function(resp){
                    Swal.close();
                    if (resp.success){
                        const d = resp.data||{};
                        $('#editarGuiaId').val(d.id||'');
                        $('#editarCliente').val(d.cliente||'');
                        $('#editarDocumentoApresentante').val(d.documento_apresentante||'');
                        $('#editarNomePortador').val(d.nome_portador||'');
                        $('#editarDocumentoPortador').val(d.documento_portador||'');
                        $('#editarDocumentosRecebidos').val(d.documentos_recebidos||'');
                        $('#editarObservacoes').val(d.observacoes||'');
                        $('#modalEditarGuia').modal('show');
                    }else{
                        Swal.fire({icon:'error', title:'Erro!', text:'Erro ao buscar os dados da guia: ' + (resp.message||'')});
                    }
                },
                error: function(){
                    Swal.close();
                    Swal.fire({icon:'error', title:'Erro!', text:'Erro ao buscar os dados da guia.'});
                }
            });
        }
        window.abrirModalEditarGuia = abrirModalEditarGuia;

        $(document).ready(function(){
            $('#salvarEdicaoGuiaBtn').off('click').on('click', function(){
                const form = document.getElementById('formEditarGuia');
                if (!form.checkValidity()){ form.reportValidity(); return; }
                const formData = $('#formEditarGuia').serialize();
                $.ajax({
                    url:'salvar_edicao_guia.php', type:'POST', dataType:'json', data: formData,
                    beforeSend: ()=> Swal.fire({title:'Salvando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()}),
                    success: function(resp){
                        Swal.close();
                        if (resp.success){
                            $('#modalEditarGuia').modal('hide');
                            Swal.fire({icon:'success', title:'Sucesso!', text:'Alterações salvas com sucesso!'}).then(()=> loadGuias(currentAction));
                        }else{
                            Swal.fire({icon:'error', title:'Erro!', text:'Erro: ' + (resp.message||'Falha ao salvar.')});
                        }
                    },
                    error: function(){
                        Swal.close();
                        Swal.fire({icon:'error', title:'Erro!', text:'Erro ao salvar as alterações da guia.'});
                    }
                });
            });
        });

        // Checklist -> preencher Documentos Recebidos
        $(document).ready(function(){
            $('#checklistSelect').change(function(){
                const checklistId = $(this).val();
                if (!checklistId) return;
                $.ajax({
                    url:'../checklist/carregar_checklist.php', type:'GET', data:{ id: checklistId }, dataType:'json',
                    success: function(resp){
                        if (resp.itens && resp.itens.length>0){
                            const itensStr = resp.itens.join('; ');
                            const atual = ($('#documentosRecebidos').val()||'').trim();
                            $('#documentosRecebidos').val(atual ? (atual + '; ' + itensStr) : itensStr);
                        }else{
                            Swal.fire('Atenção','Este checklist não possui itens cadastrados.','warning');
                        }
                    },
                    error: function(){ Swal.fire('Erro','Erro ao carregar os itens do checklist.','error'); }
                });
            });
        });

        // ===== Modal de Visualização (Documentos expandido) =====
        function abrirVisualizarGuiaModal(obj){
            // Preenche campos
            $('#vg-id').text(obj.id || '-');
            $('#vg-task').text(obj.task_id ? obj.task_id : '-');
            $('#vg-func').text(obj.funcionario || '-');
            $('#vg-data').text(formatarDataBrasileira(obj.data_recebimento) || '-');

            $('#vg-cliente').text(obj.cliente || '-');
            $('#vg-doc-apres').text(obj.doc_apresentante || '-');
            $('#vg-portador').text(obj.portador || '-');
            $('#vg-doc-port').text(obj.doc_portador || '-');

            $('#vg-docs').text(obj.documentos || '-');
            $('#vg-obs').text(obj.observacoes || '-');

            // Status pill
            const statusHtml = (!obj.task_id || Number(obj.task_id)===0)
                ? '<span class="pill"><i class="fa fa-warning"></i> Sem tarefa</span>'
                : '<span class="pill" style="background:rgba(22,163,74,.12);border-color:rgba(22,163,74,.25);color:#16a34a;"><i class="fa fa-check-circle"></i> Vinculada</span>';
            $('#vg-status-pill').html(statusHtml);

            // Ações
            $('#vg-btn-imprimir').off('click').on('click', () => visualizarGuia(obj.task_id, obj.id));
            $('#vg-btn-editar').off('click').on('click', () => {
                $('#visualizarGuiaModal').modal('hide');
                abrirModalEditarGuia(obj.id);
            });

            if (obj.task_token && obj.task_token.length){
                $('#vg-btn-ver-tarefa').removeClass('d-none').off('click').on('click', () => {
                    window.location.href = `../tarefas/index_tarefa.php?token=${obj.task_token}`;
                });
            }else{
                $('#vg-btn-ver-tarefa').addClass('d-none').off('click');
            }

            // Copiar (com fallback para HTTP / estações sem contexto seguro)
            $('#btnCopyDocs').off('click').on('click', async () => {
                const texto = obj.documentos || '';

                async function copiar(text) {
                    // 1) Tenta a API moderna (só funciona em HTTPS ou localhost)
                    if (navigator.clipboard && window.isSecureContext) {
                        try {
                            await navigator.clipboard.writeText(text);
                            return true;
                        } catch (e) { /* continua para fallback */ }
                    }

                    // 2) Fallback para ambientes sem permissão / HTTP
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.top = '0';
                        ta.style.left = '0';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.focus();
                        ta.select();
                        const ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                        if (ok) return true;
                    } catch (e) { /* continua */ }

                    // 3) Último recurso (IE antigo)
                    if (window.clipboardData && window.clipboardData.setData) {
                        try {
                            window.clipboardData.setData('Text', text);
                            return true;
                        } catch (e) {}
                    }

                    return false;
                }

                const ok = await copiar(texto);
                if (ok) {
                    Swal.fire({icon:'success', title:'Copiado!', timer:1200, showConfirmButton:false});
                } else {
                    Swal.fire({icon:'error', title:'Não foi possível copiar. Selecione e copie manualmente.'});
                }
            });


            // Abre modal
            $('#visualizarGuiaModal').modal('show');
        }
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
