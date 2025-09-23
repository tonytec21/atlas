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
    <title>Pesquisar Ordens de Serviço</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <!-- Tentativa local -->
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <!-- CDN oficial (corrige ícones MDI que não carregavam localmente) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        /* ==============================
           Tema base (Light/Dark ready)
        ===============================*/
        body.light-mode{
            --bg: #ffffff;
            --fg: #0f172a;
            --muted:#6b7280;
            --panel:#ffffff;
            --panel-brd:#e5e7eb;
            --soft:#f8fafc;
            --accent:#2563eb;
            --accent-2:#1e40af;
            --success:#28a745;
            --warning:#ffa907;
            --danger:#dc3545;

            --chip-bg:#eef2ff;
            --chip-fg:#1e3a8a;
        }
        body.dark-mode{
            --bg:#0b1220;
            --fg:#e5e7eb;
            --muted:#9ca3af;
            --panel:#0b1324;
            --panel-brd:rgba(255,255,255,.12);
            --soft:#0e1627;
            --accent:#60a5fa;
            --accent-2:#3b82f6;
            --success:#19c37d;
            --warning:#f4b740;
            --danger:#ef4444;

            --chip-bg:#0e1627;
            --chip-fg:#c7d2fe;
        }

        /* ==============================
           Barra de ações superior
        ===============================*/
        .top-actions .btn{ border-radius:10px; }
        .btn-info2{ background:#17a2b8; color:#fff; }
        .btn-info2:hover{ filter:brightness(0.95); color:#fff; }

        /* ==============================
           Formulário (card leve)
        ===============================*/
        .filter-card{
            background:var(--panel);
            border:1px solid var(--panel-brd);
            border-radius:14px;
            padding:16px;
            box-shadow:0 8px 24px rgba(0,0,0,.06);
        }
        #pesquisarForm .form-group{ margin-bottom:12px; }
        .w-100 { margin-top: 5px; }

        /* ==============================
           Tabela responsiva
        ===============================*/
        .table-wrap{
            background:var(--panel);
            border:1px solid var(--panel-brd);
            border-radius:14px;
            padding:16px;
            box-shadow:0 8px 24px rgba(0,0,0,.06);
        }
        table.dataTable thead th{ white-space:nowrap; }

        /* ==============================
           Badges de situação
        ===============================*/
        .situacao-pago,
        .situacao-ativo,
        .situacao-cancelado{
            color: #fff; width: 90px; text-align: center; padding: 6px 10px; border-radius: 8px; display:inline-block; font-size: 13px; font-weight:600;
        }
        .situacao-pago{ background-color: var(--success); }
        .situacao-ativo{ background-color: var(--warning); }
        .situacao-cancelado{ background-color: var(--danger); }

        .status-label{
            padding: 6px 10px; border-radius: 8px; color: #fff; display:inline-block; font-weight:600;
        }
        .status-pendente{ background-color: var(--danger); min-width:80px; text-align:center; }
        .status-parcialmente{ background-color: var(--warning); min-width:80px; text-align:center; }
        .status-liquidado{ background-color: var(--success); min-width:80px; text-align:center; }

        /* ==============================
           Botão fechar dos modais
        ===============================*/
        .btn-close { outline: none; border: none; background: none; padding: 0; font-size: 1.6rem; cursor: pointer; transition: transform .2s ease; color:#fff; }
        .btn-close:hover { transform: scale(1.15); }
        .btn-adicionar { height: 38px; line-height: 24px; margin-left: 10px; }

        /* ==============================
           Modais modernos e responsivos
        ===============================*/
        .modal-modern .modal-content{
            border-radius: 14px;
            border:1px solid var(--panel-brd);
            background: var(--panel);
            color: var(--fg);
            box-shadow: 0 25px 60px rgba(0,0,0,.35);
            /* Torna o conteúdo flexível e com rolagem interna em telas menores */
            display: flex;
            flex-direction: column;
            max-height: min(90vh, 100dvh);
        }
        .modal-modern .modal-header{
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color:#fff; border-top-left-radius:14px; border-top-right-radius:14px; border-bottom:0;
            display:flex; justify-content:space-between; align-items:center;
            flex: 0 0 auto;
        }
        .modal-modern .modal-title{ font-weight:700; }
        .modal-modern .modal-body{ overflow:auto; }
        .modal-modern .modal-footer{ border-top:1px solid var(--panel-brd); flex: 0 0 auto; }

        /* Larguras responsivas padrão (desktop e tablets) */
        .modal-dialog{ margin: 1.25rem auto; }
        #pagamentoModal .modal-dialog{ max-width: 900px; }
        #devolucaoModal .modal-dialog{ max-width: 520px; }
        #anexoModal .modal-dialog{ max-width: 700px; }
        #mensagemModal .modal-dialog{ max-width: 520px; }

        @media (max-width: 992px){
            #pagamentoModal .modal-dialog{ max-width: 95vw; }
            #anexoModal .modal-dialog{ max-width: 95vw; }
        }

        /* Mobile-first: ocupar 100% da tela com rolagem suave */
        @media (max-width: 576px){
            .modal-dialog{
                max-width: 100vw !important;
                width: 100vw !important;
                margin: 0 !important;
                height: 100dvh;
            }
            .modal-content{
                border-radius: 0 !important;
                height: 100dvh;
                max-height: 100dvh;
            }
            .modal-modern .modal-body{
                padding: 12px;
            }
            /* Evita "zoom/overflow" lateral em botões/ícones dentro da tabela */
            .action-btn{ width: 36px; height: 36px; font-size: 18px; }
        }

        /* Cards/inputs finos nos modais */
        .modal-modern .form-control{ border-radius: 10px; }

        /* ==============================
           Dropzone (Anexos)
        ===============================*/
        .dropzone{
            border:2px dashed var(--panel-brd);
            background: var(--soft);
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            cursor: pointer;
            transition: background .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .dropzone:hover{ background: rgba(37,99,235,.06); }
        .dropzone.dragover{
            background: rgba(37,99,235,.10);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(37,99,235,.08) inset;
        }
        .dropzone .dz-icon{
            width:46px;height:46px;border-radius:12px;
            background:var(--chip-bg); color:var(--chip-fg);
            display:inline-flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:8px;
        }
        .dropzone .dz-title{ font-weight:700; }
        .dropzone .dz-sub{ color:var(--muted); font-size:.92rem; }

        .file-list{ margin-top:12px; text-align:left; }
        .file-list .file-item{
            display:flex; align-items:center; justify-content:space-between; gap:10px;
            padding:8px 10px; background:var(--panel); border:1px solid var(--panel-brd); border-radius:10px; margin-bottom:8px;
            word-break:break-all;
        }
        .file-name{ color:var(--fg); }
        .file-size{ color:var(--muted); font-size:.9rem; }

        /* ==============================
           Toasters (feedback)
        ===============================*/
        .toast { min-width: 250px; margin-top: 0px; }
        .toast .toast-header{ color:#fff; }
        .toast .bg-success{ background-color: var(--success)!important; }
        .toast .bg-danger{ background-color: var(--danger)!important; }

        /* Ícones grandes (ações tabela) */
        .action-btn{
            margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 8px; border: none;
            display:inline-flex; align-items:center; justify-content:center;
        }

        /* Pequenos ajustes */
        .btn-info:hover, .btn-success:hover, .btn-secondary:hover, .btn-primary:hover { color:#fff!important; }

        /* ==============================
           Modal de visualização (90% desktop; 100% mobile)
        ===============================*/
        #viewerModal{ z-index: 1060; }
        #viewerModal .modal-dialog{
            max-width: 90vw;
            width: 90vw;
        }
        #viewerModal .modal-content{
            height: 90vh;
            max-height: min(90vh, 100dvh);
            display: flex;
            flex-direction: column;
        }
        #viewerModal .viewer-body{
            flex: 1;
            display: flex;
            min-height: 0;
            padding: 0;
            background: var(--panel);
        }
        .viewer-frame{
            border: 0;
            width: 100%;
            height: 100%;
        }
        .viewer-img{
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }
        .viewer-fallback{
            padding: 16px;
        }
        @media (max-width: 768px){
            #viewerModal .modal-dialog{
                max-width: 100vw;
                width: 100vw;
                margin: 0;
                height: 100dvh;
            }
            #viewerModal .modal-content{
                height: 100dvh;
                max-height: 100dvh;
                border-radius: 0;
            }
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">

            <!-- HERO / TÍTULO -->
            <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="mdi mdi-clipboard-list-outline" aria-hidden="true"></i></div>
                <div>
                <h1>Pesquisar Ordens de Serviço</h1>
                <div class="subtitle muted">Filtre por número, apresentante, CPF/CNPJ, data, valores e status.</div>
                </div>
            </div>
            </section>

            <!-- Ações principais -->
            <div class="d-flex flex-wrap justify-content-center justify-content-md-between align-items-center text-center mb-3 top-actions">
                <div class="col-md-auto mb-2">
                    <button id="add-button" type="button" class="btn btn-secondary text-white"
                            onclick="window.location.href='tabela_de_emolumentos.php'">
                        <i class="fa fa-table" aria-hidden="true"></i> Tabela de Emolumentos
                    </button>
                </div>
                <div class="col-md-auto mb-2">
                    <button id="add-button" type="button" class="btn btn-info2 text-white"
                            onclick="window.location.href='criar_os.php'">
                        <i class="fa fa-plus" aria-hidden="true"></i> Criar Ordem de Serviço
                    </button>
                </div>
                <div class="col-md-auto mb-2">
                    <button id="add-button" type="button" class="btn btn-success text-white"
                            onclick="window.location.href='../caixa/index.php'">
                        <i class="fa fa-university" aria-hidden="true"></i> Controle de Caixa
                    </button>
                </div>
                <div class="col-md-auto mb-2">
                    <a href="../liberar_os.php" class="btn btn-secondary">
                        <i class="fa fa-undo" aria-hidden="true"></i> Desfazer Liquidações
                    </a>
                </div>
                <div class="col-md-auto mb-2">
                    <a href="modelos_orcamento.php" class="btn btn-primary">
                        <i class="fa fa-folder-open"></i> Modelos O.S
                    </a>
                </div>
            </div>

            <!-- Formulário de filtro -->
            <div class="filter-card">
                <form id="pesquisarForm" method="GET">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2">
                            <label for="os_id">Nº OS:</label>
                            <input type="number" class="form-control" id="os_id" name="os_id" min="1">
                        </div>
                        <div class="form-group col-md-5">
                            <label for="cliente">Apresentante:</label>
                            <input type="text" class="form-control" id="cliente" name="cliente">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="cpf_cliente">CPF/CNPJ:</label>
                            <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="total_os">Valor Total:</label>
                            <input type="text" class="form-control" id="total_os" name="total_os">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="funcionario">Funcionário:</label>
                            <select class="form-control" id="funcionario" name="funcionario">
                                <option value="">Selecione o Funcionário</option>
                                <?php
                                $conn = getDatabaseConnection();
                                $stmt = $conn->query("SELECT DISTINCT criado_por FROM ordens_de_servico");
                                $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($funcionarios as $funcionario) {
                                    echo '<option value="' . $funcionario['criado_por'] . '">' . $funcionario['criado_por'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="situacao">Situação:</label>
                            <select class="form-control" id="situacao" name="situacao">
                                <option value="">Selecione a Situação</option>
                                <option value="Ativo">Ativo</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="data_inicial">Data Inicial:</label>
                            <input type="date" class="form-control" id="data_inicial" name="data_inicial">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="data_final">Data Final:</label>
                            <input type="date" class="form-control" id="data_final" name="data_final">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="descricao_os">Título da O.S:</label>
                            <input type="text" class="form-control" id="descricao_os" name="descricao_os">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="observacoes">Observações:</label>
                            <input type="text" class="form-control" id="observacoes" name="observacoes">
                        </div>

                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 text-white">
                                <i class="fa fa-filter" aria-hidden="true"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <hr>

            <!-- Resultados -->
            <div class="table-responsive table-wrap">
                <h5 class="mb-3" style="font-weight:700;">Resultados da Pesquisa</h5>
                <div class="table-responsive">
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 85%">
                    <thead>
                        <tr>
                            <th style="width: 7%;">Nº OS</th>
                            <th>Funcionário</th>
                            <th style="width: 11%;">Apresentante</th>
                            <th style="width: 11%;">CPF/CNPJ</th>
                            <th style="width: 13%;">Título da OS</th>
                            <th style="width: 10%;">Valor Total</th>
                            <th style="width: 10%;">Dep. Prévio</th>
                            <th style="width: 10%;">Liquidado</th>
                            <!-- <th style="width: 12%;">Observações</th> -->
                            <th>Data</th>
                            <th>Status</th>
                            <th style="width: 5%;">Situação</th>
                            <th style="width: 7%!important;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDatabaseConnection();
                        $conditions = [];
                        $params = [];
                        $filtered = false;

                        if (!empty($_GET['os_id'])) {
                            $conditions[] = 'id = :os_id';
                            $params[':os_id'] = $_GET['os_id'];
                            $filtered = true;
                        }
                        if (!empty($_GET['cliente'])) {
                            $conditions[] = 'cliente LIKE :cliente';
                            $params[':cliente'] = '%' . $_GET['cliente'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['cpf_cliente'])) {
                            $conditions[] = 'cpf_cliente LIKE :cpf_cliente';
                            $params[':cpf_cliente'] = '%' . $_GET['cpf_cliente'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['total_os'])) {
                            $conditions[] = 'total_os = :total_os';
                            $params[':total_os'] = str_replace(',', '.', str_replace('.', '', $_GET['total_os']));
                            $filtered = true;
                        }
                        if (!empty($_GET['data_inicial']) && !empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data_criacao) BETWEEN :data_inicial AND :data_final';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_inicial'])) {
                            $conditions[] = 'DATE(data_criacao) >= :data_inicial';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data_criacao) <= :data_final';
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        }
                        if (!empty($_GET['funcionario'])) {
                            $conditions[] = 'criado_por LIKE :funcionario';
                            $params[':funcionario'] = $_GET['funcionario'];
                            $filtered = true;
                        }
                        if (!empty($_GET['situacao'])) {
                            $conditions[] = 'status = :situacao';
                            $params[':situacao'] = $_GET['situacao'];
                            $filtered = true;
                        }
                        if (!empty($_GET['descricao_os'])) {
                            $conditions[] = 'descricao_os LIKE :descricao_os';
                            $params[':descricao_os'] = '%' . $_GET['descricao_os'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['observacoes'])) {
                            $conditions[] = 'observacoes LIKE :observacoes';
                            $params[':observacoes'] = '%' . $_GET['observacoes'] . '%';
                            $filtered = true;
                        }
                        $sql = 'SELECT * FROM ordens_de_servico';
                        if ($conditions) {
                            $sql .= ' WHERE ' . implode(' AND ', $conditions);
                        }

                        if (!$filtered) {
                            $sql .= ' ORDER BY data_criacao DESC LIMIT 100';
                        }

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($ordens as $ordem) {
                            // Calcula o depósito prévio
                            $stmt = $conn->prepare('SELECT SUM(total_pagamento) as deposito_previo FROM pagamento_os WHERE ordem_de_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $deposito_previo = $stmt->fetchColumn() ?: 0;

                            // Total dos atos liquidados
                            $stmt = $conn->prepare('
                            SELECT 
                                COALESCE(SUM(total), 0) AS total_liquidado 
                            FROM atos_liquidados 
                            WHERE ordem_servico_id = :os_id
                            ');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_liquidado_1 = $stmt->fetchColumn() ?: 0;

                            // Total dos atos manuais liquidados
                            $stmt = $conn->prepare('
                            SELECT 
                                COALESCE(SUM(total), 0) AS total_liquidado 
                            FROM atos_manuais_liquidados 
                            WHERE ordem_servico_id = :os_id
                            ');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_liquidado_2 = $stmt->fetchColumn() ?: 0;

                            // Soma dos atos liquidados em ambas as tabelas
                            $total_liquidado = $total_liquidado_1 + $total_liquidado_2;

                            // Calcula o total devolvido
                            $stmt = $conn->prepare('SELECT SUM(total_devolucao) as total_devolvido FROM devolucao_os WHERE ordem_de_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_devolvido = $stmt->fetchColumn() ?: 0;

                            // Verificar status dos atos
                            $stmt = $conn->prepare('SELECT status FROM ordens_de_servico_itens WHERE ordem_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $atos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $statusOS = 'Pendente';
                            $statusClasses = [
                                'Pendente' => 'status-pendente',
                                'Parcial' => 'status-parcialmente',
                                'Liquidado' => 'status-liquidado',
                                'Cancelado' => 'situacao-cancelado'
                            ];

                            if ($ordem['status'] === 'Cancelado') {
                                $statusOS = 'Cancelado';
                            } else {
                                $allLiquidado = true;
                                $hasParcialmenteLiquidado = false;
                                $allPendente = true;

                                foreach ($atos as $ato) {
                                    if ($ato['status'] == 'liquidado') {
                                        $allPendente = false;
                                    } elseif ($ato['status'] == 'parcialmente liquidado') {
                                        $hasParcialmenteLiquidado = true;
                                        $allPendente = false;
                                        $allLiquidado = false;
                                    } elseif ($ato['status'] == null) {
                                        $allLiquidado = false;
                                    }
                                }

                                if ($allLiquidado && count($atos) > 0) {
                                    $statusOS = 'Liquidado';
                                } elseif ($hasParcialmenteLiquidado || !$allPendente) {
                                    $statusOS = 'Parcial';
                                }
                            }

                            // Verificar pagamentos relevantes e anexos
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM pagamento_os WHERE ordem_de_servico_id = :os_id AND forma_de_pagamento IN ('PIX', 'Transferência Bancária', 'Boleto', 'Cheque')");
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $temPagamentoRelevante = $stmt->fetchColumn() > 0;

                            $stmt = $conn->prepare("SELECT COUNT(*) FROM anexos_os WHERE ordem_servico_id = :os_id AND status = 'ativo'");
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $temAnexos = $stmt->fetchColumn() > 0;

                            $botaoAnexoClasses = "btn btn-secondary btn-sm action-btn";
                            $botaoAnexoIcone = '<i class="fa fa-paperclip" aria-hidden="true"></i>';
                            if ($temPagamentoRelevante && !$temAnexos) {
                                $botaoAnexoClasses .= " btn-danger";
                                $botaoAnexoIcone = '<i class="fa fa-exclamation-circle" aria-hidden="true"></i>';
                            }

                            // Saldo (considerando devoluções)
                            $saldo = ($deposito_previo - $total_devolvido) - $ordem['total_os'];
                            ?>
                            <tr>
                                <td><?php echo $ordem['id']; ?></td>
                                <td><?php echo $ordem['criado_por']; ?></td>
                                <td><?php echo $ordem['cliente']; ?></td>
                                <td><?php echo $ordem['cpf_cliente']; ?></td>
                                <td><?php echo $ordem['descricao_os']; ?></td>
                                <td><?php echo 'R$ ' . number_format($ordem['total_os'], 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($deposito_previo, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?></td>
                                <!-- <td><?php echo strlen($ordem['observacoes']) > 100 ? substr($ordem['observacoes'], 0, 100) . '...' : $ordem['observacoes']; ?></td> -->
                                <td data-order="<?php echo date('Y-m-d', strtotime($ordem['data_criacao'])); ?>"><?php echo date('d/m/Y', strtotime($ordem['data_criacao'])); ?></td>
                                <td><span style="font-size: 13px" class="status-label <?php echo $statusClasses[$statusOS]; ?>"><?php echo $statusOS; ?></span></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';

                                    if ($ordem['status'] === 'Cancelado') {
                                        $statusClass = 'situacao-cancelado';
                                        $statusText = 'Cancelada';
                                    } elseif ($deposito_previo > 0) {
                                        $statusClass = 'situacao-pago';
                                        $statusText = 'Pago';
                                    } elseif ($ordem['status'] === 'Ativo') {
                                        $statusClass = 'situacao-ativo';
                                        $statusText = 'Ativa';
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>

                                <td style="width: 7%!important; zoom: 88%">
                                    <button type="button" class="btn btn-info2 btn-sm action-btn" title="Visualizar OS"
                                        onclick="location.href='visualizar_os.php?id=<?php echo $ordem['id']; ?>'">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($ordem['status'] !== 'Cancelado') : ?>
                                    <button class="btn btn-success btn-sm action-btn" title="Pagamentos e Devoluções"
                                        onclick="abrirPagamentoModal(<?php echo $ordem['id']; ?>, '<?php echo $ordem['cliente']; ?>', <?php echo $ordem['total_os']; ?>, <?php echo $deposito_previo; ?>, <?php echo $total_liquidado; ?>, <?php echo $total_devolvido; ?>, <?php echo $saldo; ?>, '<?php echo $statusOS; ?>')">
                                        <i class="fa fa-money" aria-hidden="true"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" title="Imprimir OS" class="btn btn-primary btn-sm action-btn" onclick="verificarTimbrado(<?php echo $ordem['id']; ?>)"><i class="fa fa-print" aria-hidden="true"></i></button>
                                    <button class="<?php echo $botaoAnexoClasses; ?>" title="Anexos" onclick="abrirAnexoModal(<?php echo $ordem['id']; ?>)">
                                        <?php echo $botaoAnexoIcone; ?>
                                    </button>
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
    </div>

    <!-- Modal de Pagamento -->
    <div class="modal fade modal-modern" id="pagamentoModal" tabindex="-1" role="dialog" aria-labelledby="pagamentoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="pagamentoModalLabel">Efetuar Pagamento</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="total_os_modal">Valor Total da OS</label>
                        <input type="text" class="form-control" id="total_os_modal" readonly>
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
                        <div class="form-group col-md-3">
                            <label for="total_pagamento">Valor Pago</label>
                            <input type="text" class="form-control" id="total_pagamento" readonly>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="valor_liquidado_modal">Valor Liquidado</label>
                            <input type="text" class="form-control" id="valor_liquidado_modal" readonly>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="saldo_modal">Saldo</label>
                            <input type="text" class="form-control" id="saldo_modal" readonly>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="valor_devolvido_modal">Valor Devolvido</label>
                            <input type="text" class="form-control" id="valor_devolvido_modal" readonly>
                        </div>
                    </div>

                    <!-- Botão controlado por JS conforme saldo -->
                    <button type="button" class="btn btn-warning" id="btnDevolver" onclick="abrirDevolucaoModal()">Devolver valores</button>

                    <div id="pagamentosAdicionados" class="mt-3">
                        <h5>Pagamentos Adicionados</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">Forma de Pagamento</th>
                                    <th style="width: 40%;">Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="pagamentosTable">
                                <!-- Pagamentos adicionados serão listados aqui -->
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

    <!-- Modal de Devolução -->
    <div class="modal fade modal-modern" id="devolucaoModal" tabindex="-1" role="dialog" aria-labelledby="devolucaoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="devolucaoModalLabel">Devolver Valores</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
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
                        <input type="text" class="form-control" id="valor_devolucao">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="salvarDevolucao()">Salvar</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Anexos -->
    <div class="modal fade modal-modern" id="anexoModal" tabindex="-1" role="dialog" aria-labelledby="anexoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="anexoModalLabel">Anexos</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formAnexos" enctype="multipart/form-data">
                        <!-- Input original (oculto) para compatibilidade com backend -->
                        <input type="file" id="novo_anexo" name="novo_anexo[]" multiple style="display:none">

                        <!-- Dropzone elegante -->
                        <div id="dropArea" class="dropzone" tabindex="0">
                            <div class="dz-icon"><i class="mdi mdi-cloud-upload-outline"></i></div>
                            <div class="dz-title">Arraste e solte os arquivos aqui</div>
                            <div class="dz-sub">ou clique para selecionar</div>
                        </div>
                        <div id="fileList" class="file-list" aria-live="polite"></div>

                        <button type="button" class="btn btn-success mt-3 w-100" onclick="salvarAnexo()">
                            <i class="fa fa-paperclip" aria-hidden="true"></i> Anexar
                        </button>
                    </form>

                    <hr>
                    <div id="anexosAdicionados">
                        <h5>Anexos Adicionados</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th style="width: 14%">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="anexosTable">
                                <!-- Anexos adicionados serão listados aqui -->
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

    <!-- Modal de Visualização do Anexo (90% / 100% mobile) -->
    <div class="modal fade modal-modern" id="viewerModal" tabindex="-1" role="dialog" aria-labelledby="viewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="viewerModalLabel">Visualização do Anexo</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body viewer-body">
                    <div id="viewerContainer" style="flex:1; width:100%; height:100%; display:flex;"></div>
                </div>
                <div class="modal-footer">
                    <a id="viewerDownload" class="btn btn-secondary" href="#" download target="_blank">
                        <i class="fa fa-download" aria-hidden="true"></i> Baixar arquivo
                    </a>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Mensagem -->
    <div class="modal fade modal-modern" id="mensagemModal" tabindex="-1" role="dialog" aria-labelledby="mensagemModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Mantém classes .error/.success para compatibilidade -->
                <div class="modal-header error" style="background:var(--danger);">
                    <h5 class="modal-title mb-0" id="mensagemModalLabel">Erro</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
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

    <!-- Toasters -->
    <div aria-live="polite" aria-atomic="true" style="position: relative; z-index: 1050;">
        <div id="toastContainer" style="position: absolute; top: 16px; right: 0;"></div>
    </div>

    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            // Máscaras
            $('#cpf_cliente').mask('000.000.000-00', { reverse: true }).on('blur', function() {
                var cpfCnpj = $(this).val().replace(/\D/g, '');
                if (cpfCnpj.length === 11) {
                    $(this).mask('000.000.000-00', { reverse: true });
                } else if (cpfCnpj.length === 14) {
                    $(this).mask('00.000.000/0000-00', { reverse: true });
                }
            });
            $('#total_os').mask('#.##0,00', { reverse: true });
            $('#valor_pagamento').mask('#.##0,00', { reverse: true });
            $('#valor_devolucao').mask('#.##0,00', { reverse: true });

            // DataTable
            $('#tabelaResultados').DataTable({
                "language": { "url": "../style/Portuguese-Brasil.json" },
                "order": [[0, 'desc']],
                "pageLength": 10
            });
        });

        function verificarTimbrado(id) {
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false,
                success: function(data) {
                    var url = '';
                    if (data.timbrado === 'S') {
                        url = 'imprimir_os.php?id=' + id;
                    } else if (data.timbrado === 'N') {
                        url = 'imprimir-os.php?id=' + id;
                    }
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        }

        function abrirPagamentoModal(osId, cliente, totalOs, totalPagamentos, totalLiquidado, totalDevolvido, saldo, statusOS) {
            // Guarda de segurança extra: bloquear se cancelado
            if (statusOS === 'Cancelado') {
                Swal.fire({ icon:'warning', title:'Operação não permitida', text:'Esta OS está cancelada.' });
                return;
            }

            $('#total_os_modal').val('R$ ' + totalOs.toFixed(2).replace('.', ','));
            $('#valor_liquidado_modal').val('R$ ' + totalLiquidado.toFixed(2).replace('.', ','));
            $('#valor_devolvido_modal').val('R$ ' + totalDevolvido.toFixed(2).replace('.', ','));
            $('#valor_pagamento').val('');
            $('#forma_pagamento').val('');
            $('#pagamentosTable').empty();
            $('#total_pagamento').val('R$ ' + totalPagamentos.toFixed(2).replace('.', ','));
            $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));

            // Controla o botão "Devolver Valores"
            if (saldo <= 0) { $('#btnDevolver').hide(); } else { $('#btnDevolver').show(); }

            $('#pagamentoModal').modal('show');

            // Persistir informações
            window.currentOsId = osId;
            window.currentClient = cliente;
            window.statusOS = statusOS;

            // Atualizar tabela de pagamentos existentes
            atualizarTabelaPagamentos();
        }

        function adicionarPagamento() {
            var formaPagamento = $('#forma_pagamento').val();
            var valorPagamento = parseFloat($('#valor_pagamento').val().replace(/\./g, '').replace(',', '.'));

            if (formaPagamento === "") {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Por favor, selecione uma forma de pagamento.' });
                return;
            }
            if (isNaN(valorPagamento) || valorPagamento <= 0) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Por favor, insira um valor válido para o pagamento.' });
                return;
            }

            // Regra para "Espécie": somente valores terminados em 0 ou 5 centavos (múltiplos de R$ 0,05)
            if (formaPagamento === 'Espécie') {
                // Evita problemas de ponto flutuante
                var cents = Math.round((valorPagamento + Number.EPSILON) * 100);
                if (cents % 5 !== 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Valor inválido para espécie',
                        text: 'Para pagamentos em espécie, o valor deve terminar em 0 ou 5 centavos (ex.: 2,05 • 10,50 • 10,55).'
                    });
                    return;
                }
            }

            Swal.fire({
                title: 'Confirmar Pagamento',
                text: `Deseja realmente adicionar o pagamento de R$ ${valorPagamento.toFixed(2).replace('.', ',')} na forma de ${formaPagamento}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Não'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'salvar_pagamento.php',
                        type: 'POST',
                        data: {
                            os_id: window.currentOsId,
                            cliente: window.currentClient,
                            total_os: parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.')),
                            funcionario: '<?php echo $_SESSION['username']; ?>',
                            forma_pagamento: formaPagamento,
                            valor_pagamento: valorPagamento
                        },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    atualizarTabelaPagamentos();
                                    $('#valor_pagamento').val('');
                                    Swal.fire({ icon: 'success', title: 'Sucesso', text: 'Pagamento adicionado com sucesso!' });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao adicionar pagamento.' });
                                }
                            } catch (e) {
                                Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao processar resposta do servidor.' });
                            }
                        },
                        error: function() {
                            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao adicionar pagamento.' });
                        }
                    });
                }
            });
        }

        function atualizarTabelaPagamentos() {
            var pagamentosTable = $('#pagamentosTable');
            pagamentosTable.empty();

            $.ajax({
                url: 'obter_pagamentos.php',
                type: 'POST',
                data: { os_id: window.currentOsId },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        var total = 0;
                        var canDelete = (window.statusOS === 'Pendente');

                        response.pagamentos.forEach(function(pagamento) {
                            total += parseFloat(pagamento.total_pagamento);
                            pagamentosTable.append(`
                                <tr>
                                    <td>${pagamento.forma_de_pagamento}</td>
                                    <td>R$ ${parseFloat(pagamento.total_pagamento).toFixed(2).replace('.', ',')}</td>
                                    <td>
                                        ${canDelete ? `<button type="button" class="btn btn-delete btn-sm" onclick="removerPagamento(${pagamento.id})"><i class="fa fa-trash-o" aria-hidden="true"></i></button>` : ''}
                                    </td>
                                </tr>
                            `);
                        });

                        $('#total_pagamento').val('R$ ' + total.toFixed(2).replace('.', ','));
                        var saldo = total - parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.')) - parseFloat($('#valor_devolvido_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.'));
                        $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));

                        if (saldo <= 0) { $('#btnDevolver').hide(); } else { $('#btnDevolver').show(); }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao atualizar tabela de pagamentos.', 'error');
                }
            });
        }

        function removerPagamento(pagamentoId) {
            $.ajax({
                url: 'remover_pagamento.php',
                type: 'POST',
                data: { pagamento_id: pagamentoId },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            atualizarTabelaPagamentos();
                            exibirMensagem('Pagamento removido com sucesso!', 'success');
                        } else {
                            exibirMensagem('Erro ao remover pagamento.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao remover pagamento.', 'error');
                }
            });
        }

        function exibirMensagem(mensagem, tipo) {
            var toastContainer = $('#toastContainer');
            var toastId = 'toast-' + new Date().getTime();
            var toastHTML = `
                <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
                    <div class="toast-header ${tipo === 'success' ? 'bg-success text-white' : 'bg-danger text-white'}">
                        <strong class="mr-auto">${tipo === 'success' ? 'Sucesso' : 'Erro'}</strong>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="toast-body">
                        ${mensagem}
                    </div>
                </div>
            `;
            toastContainer.append(toastHTML);
            $('#' + toastId).toast('show').on('hidden.bs.toast', function () {
                $(this).remove();
            });
        }

        function abrirDevolucaoModal() {
            $('#devolucaoModal').modal('show');
        }

        function salvarDevolucao() {
            var formaDevolucao = $('#forma_devolucao').val();
            var valorDevolucao = parseFloat($('#valor_devolucao').val().replace(/\./g, '').replace(',', '.'));
            var saldoAtual = parseFloat($('#saldo_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.'));

            if (formaDevolucao === "") {
                exibirMensagem('Por favor, selecione uma forma de devolução.', 'error');
                return;
            }
            if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > saldoAtual) {
                exibirMensagem('Insira um valor válido que não seja maior que o saldo disponível.', 'error');
                return;
            }

            $.ajax({
                url: 'salvar_devolucao.php',
                type: 'POST',
                data: {
                    os_id: window.currentOsId,
                    cliente: window.currentClient,
                    total_os: parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.')),
                    total_devolucao: valorDevolucao,
                    forma_devolucao: formaDevolucao,
                    funcionario: '<?php echo $_SESSION['username']; ?>'
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $('#devolucaoModal').modal('hide');
                            exibirMensagem('Devolução salva com sucesso!', 'success');
                            atualizarTabelaPagamentos();
                        } else {
                            exibirMensagem('Erro ao salvar devolução.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao salvar devolução.', 'error');
                }
            });
        }

        function abrirAnexoModal(osId) {
            $('#anexoModal').modal('show');
            window.currentOsId = osId;
            atualizarTabelaAnexos();
        }

        // DROPZONE: arrastar/soltar + clique
        (function initDropzone(){
            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('novo_anexo');
            const fileList = document.getElementById('fileList');

            function humanSize(bytes){
                if(bytes === 0) return '0 B';
                const k = 1024, sizes = ['B','KB','MB','GB','TB'];
                const i = Math.floor(Math.log(bytes)/Math.log(k));
                return (bytes/Math.pow(k,i)).toFixed(1)+' '+sizes[i];
            }

            function renderList(files){
                fileList.innerHTML = '';
                Array.from(files).forEach(f=>{
                    const row = document.createElement('div');
                    row.className = 'file-item';
                    row.innerHTML = `<span class="file-name"><i class="mdi mdi-file-outline"></i> ${f.name}</span><span class="file-size">${humanSize(f.size)}</span>`;
                    fileList.appendChild(row);
                });
            }

            function setFiles(files){
                const dt = new DataTransfer();
                Array.from(files).forEach(f => dt.items.add(f));
                fileInput.files = dt.files;
                renderList(fileInput.files);
            }

            dropArea.addEventListener('click', ()=> fileInput.click());
            dropArea.addEventListener('keydown', (e)=>{ if(e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }});
            dropArea.addEventListener('dragover', (e)=>{ e.preventDefault(); dropArea.classList.add('dragover'); });
            dropArea.addEventListener('dragleave', ()=> dropArea.classList.remove('dragover'));
            dropArea.addEventListener('drop', (e)=>{
                e.preventDefault();
                dropArea.classList.remove('dragover');
                if(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length){
                    setFiles(e.dataTransfer.files);
                }
            });
            fileInput.addEventListener('change', ()=> renderList(fileInput.files));
        })();

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
                            $('#fileList').empty();
                            Swal.fire({ icon: 'success', title: 'Sucesso', text: 'Anexo salvo com sucesso!' });
                            atualizarTabelaAnexos();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao salvar anexo.' });
                        }
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao processar resposta do servidor.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao salvar anexo.' });
                }
            });
        }

        function atualizarTabelaAnexos() {
            var anexosTable = $('#anexosTable');
            anexosTable.empty();

            $.ajax({
                url: 'obter_anexos.php',
                type: 'POST',
                data: { os_id: window.currentOsId },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        response.anexos.forEach(function(anexo) {
                            var caminhoCompleto = 'anexos/' + window.currentOsId + '/' + anexo.caminho_anexo;
                            anexosTable.append(`
                                <tr>
                                    <td>${anexo.caminho_anexo}</td>
                                    <td>
                                        <button type="button" class="btn btn-info btn-sm" onclick="visualizarAnexo('${caminhoCompleto}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                        
                                    </td>
                                </tr>
                            `);
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

        // Utilitário para obter URL absoluta (mesmo domínio)
        function absoluteUrl(path){
            try{
                return new URL(path, window.location.origin + window.location.pathname).href;
            }catch(e){
                return path;
            }
        }

        // Visualizar anexo dentro de modal (PDF com PDF.js; imagens; outros via iframe)
        function visualizarAnexo(caminho) {
            const abs = absoluteUrl(caminho);
            const name = caminho.split('/').pop();
            const ext = (name.split('.').pop() || '').toLowerCase();
            const container = document.getElementById('viewerContainer');
            const downloadBtn = document.getElementById('viewerDownload');
            const title = document.getElementById('viewerModalLabel');

            title.textContent = 'Visualizando: ' + name;
            downloadBtn.href = abs;
            container.innerHTML = '';

            if (ext === 'pdf') {
                const viewerUrl = absoluteUrl('../provimentos/pdfjs/web/viewer.html') + '?file=' + encodeURIComponent(abs);
                const iframe = document.createElement('iframe');
                iframe.className = 'viewer-frame';
                iframe.src = viewerUrl;
                iframe.setAttribute('title','Visualizador PDF');
                container.appendChild(iframe);
            } else if (['png','jpg','jpeg','gif','webp','bmp','svg'].includes(ext)) {
                const img = document.createElement('img');
                img.className = 'viewer-img';
                img.src = abs;
                img.alt = name;
                container.appendChild(img);
            } else {
                const iframe = document.createElement('iframe');
                iframe.className = 'viewer-frame';
                iframe.src = abs;
                iframe.setAttribute('title','Visualização do arquivo');
                container.appendChild(iframe);

                const fallback = document.createElement('div');
                fallback.className = 'viewer-fallback';
                fallback.innerHTML = '<small>Se a visualização não carregar, utilize o botão "Baixar arquivo".</small>';
                container.appendChild(fallback);
            }

            // Mostrar modal do visualizador
            $('#viewerModal').modal('show');
        }

        // Ajuste de z-index/backdrop para modal aninhado + restaurar rolagem do modal anterior
        $('#viewerModal').on('shown.bs.modal', function () {
            const backdrops = $('.modal-backdrop');
            backdrops.last().css('z-index', 1055);
            $(this).css('z-index', 1060);
        });
        $('#viewerModal').on('hidden.bs.modal', function () {
            // Se o modal de anexos ainda estiver aberto, mantém o body com overflow escondido
            if ($('#anexoModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
            // Limpa o viewer para liberar memória
            $('#viewerContainer').empty();
        });

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
                        data: { anexo_id: anexoId },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    Swal.fire({ icon: 'success', title: 'Sucesso', text: 'Anexo removido com sucesso!' });
                                    atualizarTabelaAnexos();
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao remover anexo.' });
                                }
                            } catch (e) {
                                Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao processar resposta do servidor.' });
                            }
                        },
                        error: function() {
                            Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao remover anexo.' });
                        }
                    });
                }
            });
        }

        // Atualiza label do input (fallback se usar input visível por algum motivo)
        document.addEventListener('change', function(e){
            if(e.target && e.target.id === 'novo_anexo'){
                const lbl = document.querySelector('label[for="novo_anexo"]');
                if(!lbl) return;
                const files = e.target.files || [];
                if (files.length === 1) { lbl.textContent = files[0].name; }
                else if (files.length > 1) { lbl.textContent = files.length + ' arquivos selecionados'; }
                else { lbl.textContent = 'Selecione os arquivos para anexar'; }
            }
        });

        // Recarrega a página ao fechar modal de anexos (mantido do original)
        $('#anexoModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        // Validação de datas (não permite ano futuro)
        $(document).ready(function() {
            var currentYear = new Date().getFullYear();
            function validateDate(input) {
                var selectedDate = new Date($(input).val());
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
            $('#data_inicial, #data_final').on('change', function() {
                if ($(this).val()) { validateDate(this); }
            });
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
