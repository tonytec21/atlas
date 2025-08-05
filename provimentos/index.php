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
    <title>Provimentos e Resoluções</title>

    <!-- CSS base -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">

    <!-- Favicon -->
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <!-- Material Design Icons (CDN para corrigir erro de fontes 404) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">

    <style>
        /* =========================================================
           Tema Light/Dark — variáveis apenas do MODAL de visualização
           O sistema já controla body.light-mode / body.dark-mode
           =======================================================*/
        body.light-mode {
            --modal-bg: #f8fafc;           /* fundo do body do modal */
            --modal-panel: #ffffff;        /* cartões/áreas internas */
            --modal-bar: #f1f5f9;          /* barras (metadados/toolbar) */
            --modal-border: #e5e7eb;
            --modal-text: #0f172a;
            --modal-muted: #64748b;
            --modal-header1: #2563eb;      /* gradiente topo */
            --modal-header2: #1e40af;
            --modal-badge-bg: rgba(0,0,0,.06);
            --modal-badge-brd: rgba(0,0,0,.12);
            --btn-outline: #0f172a;        /* cor de contorno dos botões da toolbar */
            --btn-outline-hover: rgba(2,6,23,.06);
            --loader-fg: #0f172a;
            --loader-bg1: rgba(59,130,246,.12);
            --loader-bg2: rgba(29,78,216,.18);
        }
        body.dark-mode {
            --modal-bg: #0b1220;
            --modal-panel: #0b1324;
            --modal-bar: #0e1627;
            --modal-border: rgba(255,255,255,.08);
            --modal-text: #e5e7eb;
            --modal-muted: #9ca3af;
            --modal-header1: #2563eb;
            --modal-header2: #1e40af;
            --modal-badge-bg: rgba(255,255,255,.15);
            --modal-badge-brd: rgba(255,255,255,.25);
            --btn-outline: #e5e7eb;
            --btn-outline-hover: rgba(255,255,255,.08);
            --loader-fg: #dbeafe;
            --loader-bg1: rgba(59,130,246,.12);
            --loader-bg2: rgba(29,78,216,.18);
        }

        .btn-adicionar {
            height: 38px;
            line-height: 24px;
            margin-left: 10px;
        }

        .table th:nth-child(1), .table td:nth-child(1) { width: 7%; }  /* Tipo    */
        .table th:nth-child(2), .table td:nth-child(2) { width: 7%; }  /* Nº      */
        .table th:nth-child(3), .table td:nth-child(3) { width: 5%; }  /* Origem  */
        .table th:nth-child(4), .table td:nth-child(4) { width: 8%; }  /* Data    */
        .table th:nth-child(5), .table td:nth-child(5) { width: 68%; } /* Descrição */
        .table th:nth-child(6), .table td:nth-child(6) { width: 5%; }  /* Ações   */

        /* ===== Modal moderno 90% viewport, responsivo e com tema ===== */
        .modal-modern.modal {
            backdrop-filter: blur(4px);
        }
        .modal-modern .modal-dialog {
            max-width: 90vw !important;
            width: 90vw !important;
            margin: 2vh auto;
        }
        .modal-modern .modal-content {
            height: 100vh;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--modal-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
            background: var(--modal-panel);
            color: var(--modal-text);
        }
        .modal-modern .modal-header {
            border: 0;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--modal-header1), var(--modal-header2));
            color: #fff;
        }
        .modal-modern .modal-title {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-weight: 600;
        }
        .modal-modern .modal-title .badge {
            background: var(--modal-badge-bg);
            border: 1px solid var(--modal-badge-brd);
            color: #fff;
            font-weight: 500;
        }
        .modal-modern .close, .modal-modern .close:hover {
            color:#fff;
            opacity:1;
            text-shadow:none;
        }
        .modal-modern .modal-body {
            background: var(--modal-bg);
            color: var(--modal-text);
            border-top: 1px solid var(--modal-border);
            border-bottom: 1px solid var(--modal-border);
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .modal-modern .meta-bar {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 12px;
            padding: 14px 16px;
            background: var(--modal-bar);
            border-bottom: 1px solid var(--modal-border);
        }
        .meta-item {
            background: var(--modal-panel);
            border: 1px solid var(--modal-border);
            border-radius: 12px;
            padding: 10px 12px;
        }
        .meta-item label {
            display:block;
            font-size:.75rem;
            color: var(--modal-muted);
            margin-bottom: 2px;
        }
        .meta-item .value {
            font-weight: 600;
            color: var(--modal-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .modal-modern .doc-toolbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 10px;
            padding: 10px 16px;
            background: var(--modal-bar);
            border-top:1px solid var(--modal-border);
            border-bottom:1px solid var(--modal-border);
            color: var(--modal-text);
        }
        .doc-actions .btn.theme-outline {
            border-radius: 10px;
            border: 1px solid var(--btn-outline);
            color: var(--btn-outline);
            background: transparent;
        }
        .doc-actions .btn.theme-outline i {
            margin-right:6px;
            font-size: 16px;
        }
        .doc-actions .btn.theme-outline:hover {
            background: var(--btn-outline-hover);
        }

        .viewer-wrapper {
            position: relative;
            flex: 1 1 auto;
            min-height: 200px;
            background: var(--modal-panel);
        }
        .viewer-frame {
            position:absolute;
            inset:0;
            width:100%;
            height:100%;
            border:0;
            background: var(--modal-panel);
        }
        .doc-loader {
            position:absolute;
            inset:0;
            display:flex;
            align-items:center;
            justify-content:center;
            background:
                radial-gradient(1200px 600px at 30% -20%, var(--loader-bg1), transparent 60%),
                radial-gradient(800px 600px at 130% 120%, var(--loader-bg2), transparent 60%),
                var(--modal-panel);
            color: var(--loader-fg);
            font-weight:600;
            letter-spacing:.3px;
        }

        .modal-modern .modal-footer {
            background: var(--modal-bar);
            border-top: 1px solid var(--modal-border);
        }

        @media (max-width: 992px){
            .modal-modern .meta-bar{
                grid-template-columns: repeat(2,minmax(0,1fr));
            }
        }
        @media (max-width: 576px){
            .modal-modern .modal-dialog {
                width: 96vw !important;
                max-width: 96vw !important;
                margin: 2vh auto;
            }
            .modal-modern .modal-content{
                height: 92vh;
            }
            .modal-modern .meta-bar{
                grid-template-columns: 1fr;
            }
            .doc-actions .btn.theme-outline span{
                display:none;
            }
        }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Provimentos e Resoluções</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="tipo">Tipo:</label>
                        <select class="form-control" id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="Provimento">Provimento</option>
                            <option value="Resolução">Resolução</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="numero_provimento">Nº Prov./Resol.:</label>
                        <input type="text" class="form-control" id="numero_provimento" name="numero_provimento">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="ano">Ano:</label>
                        <input type="text" class="form-control" id="ano" name="ano" pattern="\d{4}" title="Digite um ano válido (ex: 2023)" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="origem">Origem:</label>
                        <select class="form-control" id="origem" name="origem">
                            <option value="">Selecione</option>
                            <option value="CGJ/MA">CGJ/MA</option>
                            <option value="CNJ">CNJ</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="data_provimento">Data:</label>
                        <input type="date" class="form-control" id="data_provimento" name="data_provimento">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="conteudo_anexo">Conteúdo:</label>
                        <textarea class="form-control" id="conteudo_anexo" name="conteudo_anexo" rows="3"></textarea>
                    </div>
                </div>
                <div class="row mb-12">
                    <div class="col-md-12">
                        <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-primary">
                            <i class="fa fa-filter" aria-hidden="true"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 100%">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Nº</th>
                            <th>Origem</th>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDatabaseConnection();
                        $conditions = [];
                        $params = [];
                        $filtered = false;

                        if (!empty($_GET['numero_provimento'])) {
                            if (strpos($_GET['numero_provimento'], '/') !== false) {
                                list($numero, $ano) = explode('/', $_GET['numero_provimento']);
                                $conditions[] = 'numero_provimento = :numero AND YEAR(data_provimento) = :ano';
                                $params[':numero'] = $numero;
                                $params[':ano'] = $ano;
                            } else {
                                $conditions[] = 'numero_provimento = :numero';
                                $params[':numero'] = $_GET['numero_provimento'];
                            }
                            $filtered = true;
                        }
                        if (!empty($_GET['origem'])) {
                            $conditions[] = 'origem = :origem';
                            $params[':origem'] = $_GET['origem'];
                            $filtered = true;
                        }
                        if (!empty($_GET['tipo'])) {
                            $conditions[] = 'tipo = :tipo';
                            $params[':tipo'] = $_GET['tipo'];
                            $filtered = true;
                        }
                        if (!empty($_GET['ano'])) {
                            $conditions[] = 'YEAR(data_provimento) = :ano';
                            $params[':ano'] = $_GET['ano'];
                            $filtered = true;
                        }
                        if (!empty($_GET['data_provimento'])) {
                            $conditions[] = 'data_provimento = :data_provimento';
                            $params[':data_provimento'] = $_GET['data_provimento'];
                            $filtered = true;
                        }
                        if (!empty($_GET['descricao'])) {
                            $conditions[] = 'descricao LIKE :descricao';
                            $params[':descricao'] = '%' . $_GET['descricao'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['conteudo_anexo'])) {
                            $conditions[] = 'conteudo_anexo LIKE :conteudo_anexo';
                            $params[':conteudo_anexo'] = '%' . $_GET['conteudo_anexo'] . '%';
                            $filtered = true;
                        }

                        $sql = 'SELECT * FROM provimentos';
                        if ($conditions) {
                            $sql .= ' WHERE ' . implode(' AND ', $conditions);
                        }
                        if (!$filtered) {
                            $sql .= ' ORDER BY data_provimento DESC';
                        }

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $provimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($provimentos as $provimento) {
                            $numero_provimento_ano = $provimento['numero_provimento'] . '/' . date('Y', strtotime($provimento['data_provimento']));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($provimento['tipo']); ?></td>
                                <td><?php echo htmlspecialchars($numero_provimento_ano); ?></td>
                                <td><?php echo htmlspecialchars($provimento['origem']); ?></td>
                                <td data-order="<?php echo date('Y-m-d', strtotime($provimento['data_provimento'])); ?>"><?php echo date('d/m/Y', strtotime($provimento['data_provimento'])); ?></td>
                                <td><?php echo htmlspecialchars($provimento['descricao']); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" title="Visualizar Provimento" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="visualizarProvimento('<?php echo $provimento['id']; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
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

    <!-- Modal de Visualização (moderno 90%, respeita light/dark) -->
    <div class="modal fade modal-modern" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document" aria-modal="true">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title" id="visualizarModalLabel">
                        <i class="mdi mdi-file-document-outline"></i>
                        <span class="title-text">Documento</span>
                        <span class="badge badge-pill ml-2" id="tagTipo">—</span>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Barra de metadados -->
                    <div class="meta-bar">
                        <div class="meta-item">
                            <label>Tipo</label>
                            <div class="value" id="metaTipo">—</div>
                        </div>
                        <div class="meta-item">
                            <label>Número</label>
                            <div class="value" id="metaNumero">—</div>
                        </div>
                        <div class="meta-item">
                            <label>Origem</label>
                            <div class="value" id="metaOrigem">—</div>
                        </div>
                        <div class="meta-item">
                            <label>Data</label>
                            <div class="value" id="metaData">—</div>
                        </div>
                    </div>

                    <!-- Toolbar de ações -->
                    <div class="doc-toolbar">
                        <div class="text-truncate" style="max-width:70%;">
                            <i class="mdi mdi-text-long"></i>
                            <span id="metaDescricao" class="ml-1">—</span>
                        </div>
                        <div class="doc-actions">
                            <button type="button" class="btn theme-outline btn-sm" id="btnOpenNew" title="Abrir em uma nova aba">
                                <i class="mdi mdi-open-in-new"></i><span>Abrir</span>
                            </button>
                            <button type="button" class="btn theme-outline btn-sm" id="btnDownload" title="Baixar PDF">
                                <i class="mdi mdi-download"></i><span>Baixar</span>
                            </button>
                            <button type="button" class="btn theme-outline btn-sm" id="btnCopyLink" title="Copiar link do anexo">
                                <i class="mdi mdi-link-variant"></i><span>Copiar link</span>
                            </button>
                        </div>
                    </div>

                    <!-- Viewer -->
                    <div class="viewer-wrapper">
                        <div id="docLoader" class="doc-loader">
                            <i class="mdi mdi-loading mdi-spin mr-2"></i> Carregando documento…
                        </div>
                        <iframe id="anexo_visualizacao" class="viewer-frame" frameborder="0"></iframe>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [[3, 'desc']]
            });
        });

        // --- Helpers para nome e download padronizados -------------------
        function composeDownloadName(p) {
            const numero = p.numero_provimento || '';
            const ano = p.ano_provimento || (p.data_provimento ? new Date(p.data_provimento + 'T00:00:00').getFullYear() : '');
            const tipo = p.tipo || 'Documento';
            const origem = p.origem || '';
            let name = `${tipo} nº ${numero}_${ano} - ${origem}`;
            // Mapeamento solicitado: remover ":" e trocar "/" por "_"
            name = name.replace(/:/g, '').replace(/\//g, '_');
            // Remover caracteres inválidos em nomes de arquivo no Windows/macOS
            name = name.replace(/[<>:"/\\|?*\x00-\x1F]/g, '');
            // Espaços duplos -> simples e trim
            name = name.replace(/\s+/g, ' ').trim();
            // Evitar ponto final
            name = name.replace(/\.+$/, '');
            return name;
        }

        async function baixarArquivo(url, nomeBase) {
            try {
                const resp = await fetch(url, { credentials: 'same-origin' });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const blob = await resp.blob();

                // Tenta definir extensão a partir do Content-Type, se não existir na URL
                let ext = '';
                const ct = (blob.type || '').toLowerCase();
                if (/\.(pdf|jpg|jpeg|png|gif|doc|docx|xls|xlsx|odt|rtf|txt|csv)$/i.test(url.split('?')[0])) {
                    // mantém a extensão da URL
                    ext = url.split('?')[0].match(/\.[a-z0-9]+$/i)[0];
                } else if (ct.includes('pdf')) ext = '.pdf';
                else if (ct.includes('jpeg')) ext = '.jpg';
                else if (ct.includes('png')) ext = '.png';
                else if (ct.includes('gif')) ext = '.gif';
                else if (ct.includes('msword')) ext = '.doc';
                else if (ct.includes('officedocument.wordprocessingml')) ext = '.docx';
                else if (ct.includes('spreadsheetml')) ext = '.xlsx';
                else if (ct.includes('csv')) ext = '.csv';
                else if (ct.includes('rtf')) ext = '.rtf';
                else if (ct.includes('text')) ext = '.txt';

                const objectUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = objectUrl;
                a.download = nomeBase + (ext || '');
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(objectUrl);
            } catch (e) {
                Swal.fire({icon:'error', title:'Falha ao baixar', text:e.message || 'Erro desconhecido'});
            }
        }
        // ------------------------------------------------------------------

        function visualizarProvimento(id) {
            // Reset do loader e frame
            $('#docLoader').show();
            $('#anexo_visualizacao').attr('src', 'about:blank');

            $.ajax({
                url: 'obter_provimento.php',
                type: 'GET',
                data: { id: id },
                success: function(response) {
                    try {
                        var provimento = (typeof response === 'object') ? response : JSON.parse(response);

                        var numero_provimento_ano = (provimento.numero_provimento || '') + '/' + (provimento.ano_provimento || '');

                        // Metadados (topo)
                        $('#metaTipo').text(provimento.tipo || '—');
                        $('#metaNumero').text(numero_provimento_ano || '—');
                        $('#metaOrigem').text(provimento.origem || '—');
                        let dataProvimento = provimento.data_provimento ? new Date(provimento.data_provimento + 'T00:00:00') : null;
                        $('#metaData').text(dataProvimento ? dataProvimento.toLocaleDateString('pt-BR') : '—');

                        // Descrição/toolbar
                        $('#metaDescricao').text(provimento.descricao || '—');

                        // Tag tipo no título
                        $('#tagTipo').text(provimento.tipo || 'Documento');

                        // Título do modal
                        var modalTitle = (provimento.tipo || 'Documento') + ' nº: ' + (numero_provimento_ano || '—') + ' - ' + (provimento.origem || '—');
                        $('.title-text').text(modalTitle);

                        // Viewer
                        const url = provimento.caminho_anexo || '';
                        $('#anexo_visualizacao')
                            .off('load')
                            .on('load', function(){ $('#docLoader').fadeOut(150); })
                            .attr('src', url);

                        // Nome padrão para download conforme solicitado
                        const nomePadrao = composeDownloadName(provimento);

                        // Botões de ação
                        $('#btnOpenNew').off('click').on('click', function(){
                            if (url) window.open(url, '_blank');
                        });
                        $('#btnDownload').off('click').on('click', function(){
                            if (!url) return;
                            baixarArquivo(url, nomePadrao);
                        });
                        $('#btnCopyLink').off('click').on('click', async function(){
                            try{
                                await navigator.clipboard.writeText(url || '');
                                Swal.fire({icon:'success', title:'Link copiado!', timer:1200, showConfirmButton:false});
                            }catch(e){
                                Swal.fire({icon:'error', title:'Falha ao copiar link'});
                            }
                        });

                        // Exibe o modal
                        $('#visualizarModal').modal('show');
                    } catch (e) {
                        console.error(e);
                        alert('Erro ao processar resposta do servidor.');
                    }
                },
                error: function() {
                    alert('Erro ao obter os dados do provimento.');
                }
            });
        }

        // Ajusta o iframe para sempre ocupar o espaço disponível
        function ajustarAlturaViewer(){
            const $content = $('#visualizarModal .modal-content');
            const $header  = $('#visualizarModal .modal-header');
            const $footer  = $('#visualizarModal .modal-footer');
            const $body    = $('#visualizarModal .modal-body');
            const total = $content.height() || 0;
            const head = $header.outerHeight(true) || 0;
            const foot = $footer.outerHeight(true) || 0;
            const bodyAvailable = total - head - foot;
            $body.height(bodyAvailable);
        }

        $('#visualizarModal').on('shown.bs.modal', function(){
            ajustarAlturaViewer();
        });
        $(window).on('resize', function(){
            if ($('#visualizarModal').hasClass('show')) ajustarAlturaViewer();
        });

        // Validação do campo Data (não permite ano futuro)
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

            $('#data_provimento').on('change', function() {
                if ($(this).val()) {
                    validateDate(this);
                }
            });
        });
    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
