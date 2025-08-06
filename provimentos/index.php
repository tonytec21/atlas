<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* Bases absolutas
   $appBase    → http(s)://{SERVIDOR}/atlas/provimentos/
   $viewerBase → http(s)://{SERVIDOR}/atlas/provimentos/pdfjs/web/viewer.html */
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$appBase    = $scheme . '://' . $host . $scriptDir . '/';
$viewerBase = $appBase . 'pdfjs/web/viewer.html';

/* Helper para preencher o formulário com os valores enviados */
$g = $_GET ?? [];
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
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

    <style>
        /* =========================================================
           Tema Light/Dark — variáveis do MODAL e dos CARDS
           =======================================================*/
        body.light-mode {
            --modal-bg: #f8fafc;
            --modal-panel: #ffffff;
            --modal-bar: #f1f5f9;
            --modal-border: #e5e7eb;
            --modal-text: #0f172a;
            --modal-muted: #64748b;
            --modal-header1: #2563eb;
            --modal-header2: #1e40af;
            --modal-badge-bg: rgba(0,0,0,.06);
            --modal-badge-brd: rgba(0,0,0,.12);
            --btn-outline: #0f172a;
            --btn-outline-hover: rgba(2,6,23,.06);
            --loader-fg: #0f172a;
            --loader-bg1: rgba(59,130,246,.12);
            --loader-bg2: rgba(29,78,216,.18);
            --input-bg: #ffffff;
            --input-brd: #d1d5db;
            --input-text:#0f172a;
            --input-ph:#6b7280;

            --card-bg:#ffffff;
            --card-brd:#e5e7eb;
            --card-text:#0f172a;
            --card-muted:#6b7280;
            --chip-bg:#f1f5f9;
            --chip-brd:#e5e7eb;
            --card-hover:rgba(2,6,23,.06);
            --accent:#2563eb;
            --accent-2:#1e40af;
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
            --input-bg: #0b1324;
            --input-brd: rgba(255,255,255,.15);
            --input-text:#e5e7eb;
            --input-ph:#9ca3af;

            --card-bg:#0b1324;
            --card-brd:rgba(255,255,255,.10);
            --card-text:#e5e7eb;
            --card-muted:#9ca3af;
            --chip-bg:#0e1627;
            --chip-brd:rgba(255,255,255,.15);
            --card-hover:rgba(255,255,255,.06);
            --accent:#60a5fa;
            --accent-2:#3b82f6;
        }

        .btn-adicionar { height: 38px; line-height: 24px; margin-left: 10px; }

        /* ======================= MODAL ======================= */
        .modal-modern.modal          { backdrop-filter: blur(4px); }
        .modal-modern .modal-dialog  { max-width:90vw!important; width:90vw!important; margin:2vh auto; }
        .modal-modern .modal-content {
            height:95vh; display:flex; flex-direction:column;
            border:1px solid var(--modal-border); border-radius:16px; overflow:hidden;
            box-shadow:0 20px 50px rgba(0,0,0,.35); background:var(--modal-panel); color:var(--modal-text);
        }
        .modal-modern .modal-header  { border:0; padding:10px 12px; background:linear-gradient(135deg,var(--modal-header1),var(--modal-header2)); color:#fff; }
        .modal-modern .modal-title   { display:flex; align-items:center; gap:.75rem; font-weight:600; }
        .modal-modern .modal-title .badge{ background:var(--modal-badge-bg); border:1px solid var(--modal-badge-brd); color:#fff; font-weight:500; }
        .modal-modern .close , .modal-modern .close:hover{ color:#fff; opacity:1; text-shadow:none; }

        /* Corpo do modal agora em layout de 2 colunas: esquerda infos, direita viewer */
        .modal-modern .modal-body{
            background:var(--modal-bg); color:var(--modal-text);
            border-top:1px solid var(--modal-border); border-bottom:1px solid var(--modal-border);
            padding:0; display:flex; flex-direction:column;
        }
        .doc-layout{
            display:grid; grid-template-columns: 380px 1fr; gap:0; flex:1 1 auto; min-height:0; height:100%;
        }
        .doc-sidebar{
            display:flex; flex-direction:column; min-width:0;
            background:var(--modal-bg); border-right:1px solid var(--modal-border);
        }
        .doc-sidebar .doc-title{
            padding:14px 16px; border-bottom:1px solid var(--modal-border);
            background: var(--modal-panel);
        }
        .doc-sidebar .doc-title .title-row{
            display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;
        }
        .doc-sidebar .doc-title .title-left{
            display:flex; align-items:center; gap:.75rem; font-weight:600;
        }

        .modal-modern .meta-bar{
            display:grid; grid-template-columns:1fr; /* empilhado na coluna esquerda */
            gap:12px; padding:14px 16px; background:var(--modal-bar); border-bottom:1px solid var(--modal-border);
        }
        .meta-item{ background:var(--modal-panel); border:1px solid var(--modal-border); border-radius:12px; padding:10px 12px;}
        .meta-item label{ display:block; font-size:.75rem; color:var(--modal-muted); margin-bottom:2px;}
        .meta-item .value{ font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .doc-toolbar{
            display:flex; flex-direction:column; gap:10px;
            padding:12px 16px; background:var(--modal-bar); border-top:1px solid var(--modal-border);
            border-bottom:1px solid var(--modal-border);
        }
        .meta-desc{
            max-width:100%; cursor:pointer; position:relative; line-height:1.25rem;
            max-height:8.4rem; overflow:hidden; display:-webkit-box; -webkit-line-clamp:6; -webkit-box-orient:vertical;
            transition:max-height .2s ease;
        }
        .meta-desc.expanded{
            background:var(--modal-panel); border:1px solid var(--modal-border); padding:8px 10px;
            border-radius:8px; max-height:18rem; overflow:auto; -webkit-line-clamp:unset;
        }
        .meta-desc .mdi{ vertical-align:middle; margin-right:6px; }

        .pdf-search .form-control{
            background:var(--input-bg); color:var(--input-text); border:1px solid var(--input-brd);
        }
        .pdf-search .form-control::placeholder{ color:var(--input-ph); }
        .pdf-search .input-group-append .btn{
            border:1px solid var(--btn-outline); color:var(--btn-outline); background:transparent; border-left:none;
        }
        .pdf-search .input-group-append .btn:hover{ background:var(--btn-outline-hover); }

        .doc-actions{ display:flex; gap:8px; flex-wrap:wrap; }
        .doc-actions .btn{ border:1px solid var(--btn-outline); color:var(--btn-outline); background:transparent; border-radius:10px; }
        .doc-actions .btn:hover{ background:var(--btn-outline-hover); }

        .viewer-area{ position:relative; background:var(--modal-panel); }
        .viewer-wrapper{ position:absolute; inset:0; width:100%; height:100%; background:var(--modal-panel);}
        .viewer-frame  { position:absolute; inset:0; width:100%; height:100%; border:0; background:var(--modal-panel);}
        .doc-loader{
            position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
            background:radial-gradient(1200px 600px at 30% -20%,var(--loader-bg1),transparent 60%),radial-gradient(800px 600px at 130% 120%,var(--loader-bg2),transparent 60%),var(--modal-panel);
            color:var(--loader-fg); font-weight:600; letter-spacing:.3px;
        }

        /* ======================= CARDS (Resultados) ======================= */
        .results-toolbar{
            display:flex; align-items:center; gap:12px; justify-content:space-between; flex-wrap:wrap;
            margin:10px 0 18px 0;
        }
        .results-toolbar .count{
            font-weight:600; color:var(--card-text);
        }
        .results-toolbar .controls{
            display:flex; gap:10px; align-items:center;
        }
        .results-toolbar .controls .form-control{
            background:var(--input-bg); color:var(--input-text); border:1px solid var(--input-brd);
        }
        .cards-grid{
            display:grid; gap:14px;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        }
        .prov-card{
            background:var(--card-bg);
            border:1px solid var(--card-brd);
            border-radius:14px;
            overflow:hidden;
            box-shadow:0 8px 24px rgba(0,0,0,.08);
            transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
            display:flex; flex-direction:column;
            cursor:pointer; /* card clicável */
        }
        .prov-card:hover{ transform: translateY(-2px); box-shadow:0 10px 28px rgba(0,0,0,.12); border-color:var(--accent-2); }
        .prov-card__header{
            padding:12px 14px; display:flex; align-items:center; justify-content:space-between; gap:8px;
            background:linear-gradient(180deg, rgba(37,99,235,.08), transparent);
            border-bottom:1px dashed var(--card-brd);
        }
        .chip{
            display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:.85rem;
            background:var(--chip-bg); border:1px solid var(--chip-brd); color:var(--card-text);
            padding:6px 10px; border-radius:999px;
        }
        .prov-card__num{ font-weight:700; color:var(--accent); }
        .prov-card__body{ padding:14px 16px; display:flex; flex-direction:column; gap:10px; }
        .prov-meta{ display:flex; gap:8px; flex-wrap:wrap; }
        .prov-meta .meta{
            display:inline-flex; align-items:center; gap:6px; font-size:.88rem; color:var(--card-muted);
            background:var(--chip-bg); border:1px solid var(--chip-brd); padding:6px 10px; border-radius:10px;
        }
        .prov-desc{
            color:var(--card-text);
            line-height:1.45rem;
            max-height:6.8rem;
            overflow:hidden;
            display:-webkit-box;
            -webkit-line-clamp:5;
            -webkit-box-orient:vertical;
            position:relative;
            transition:max-height .2s ease, padding .2s ease, background .2s ease, border-color .2s ease, box-shadow .2s ease;
            outline:none;
        }
        .prov-desc:hover,
        .prov-desc:focus{
            -webkit-line-clamp:unset;
            max-height:50vh;
            overflow:auto;
            background:var(--chip-bg);
            border:1px solid var(--chip-brd);
            padding:8px 10px;
            border-radius:10px;
            box-shadow:0 8px 20px rgba(0,0,0,.08) inset;
        }
        .prov-actions{
            display:flex; gap:8px; margin-top:2px; flex-wrap:wrap;
        }
        .btn-outline{
            border-radius:10px; border:1px solid var(--btn-outline); color:var(--btn-outline); background:transparent; height:34px;
        }
        .btn-outline:hover{ background:var(--btn-outline-hover); }
        .empty-state{
            border:2px dashed var(--card-brd); background:var(--card-bg); color:var(--card-muted);
            border-radius:12px; padding:28px; text-align:center;
        }
        .empty-state i{ font-size:32px; display:block; margin-bottom:10px; color:var(--accent); }

        mark{ padding:0 2px; border-radius:4px; background:rgba(250,204,21,.35); }
        @media (max-width:576px){
            .meta-desc{ max-width:100%; }
        }
    </style>
</head>

<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<!-- ================================ PÁGINA PRINCIPAL ================================ -->
<div id="main" class="main-content">
    <div class="container">
        <h3>Pesquisar Provimentos e Resoluções</h3>
        <hr>

        <!-- --------------------------- FORMULÁRIO DE FILTRO --------------------------- -->
        <form id="pesquisarForm" method="GET">
            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="tipo">Tipo:</label>
                    <select class="form-control" id="tipo" name="tipo">
                        <option value="" <?= (($g['tipo'] ?? '')==='' ? 'selected' : '') ?>>Todos</option>
                        <option value="Provimento" <?= (($g['tipo'] ?? '')==='Provimento' ? 'selected' : '') ?>>Provimento</option>
                        <option value="Resolução"  <?= (($g['tipo'] ?? '')==='Resolução'  ? 'selected' : '') ?>>Resolução</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="numero_provimento">Nº Prov./Resol.:</label>
                    <input type="text" class="form-control" id="numero_provimento" name="numero_provimento"
                           value="<?= e($g['numero_provimento'] ?? '') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="ano">Ano:</label>
                    <input type="text" class="form-control" id="ano" name="ano" pattern="\d{4}" maxlength="4"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4)" title="Digite um ano válido"
                           value="<?= e($g['ano'] ?? '') ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="origem">Origem:</label>
                    <select class="form-control" id="origem" name="origem">
                        <option value="" <?= (($g['origem'] ?? '')==='' ? 'selected' : '') ?>>Selecione</option>
                        <option value="CGJ/MA" <?= (($g['origem'] ?? '')==='CGJ/MA' ? 'selected' : '') ?>>CGJ/MA</option>
                        <option value="CNJ"    <?= (($g['origem'] ?? '')==='CNJ'    ? 'selected' : '') ?>>CNJ</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="data_provimento">Data:</label>
                    <input type="date" class="form-control" id="data_provimento" name="data_provimento"
                           value="<?= e($g['data_provimento'] ?? '') ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="descricao">Descrição (contém):</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"><?= e($g['descricao'] ?? '') ?></textarea>
                </div>
                <div class="form-group col-md-6">
                    <label for="conteudo_anexo">Conteúdo do anexo (contém):</label>
                    <textarea class="form-control" id="conteudo_anexo" name="conteudo_anexo" rows="3"><?= e($g['conteudo_anexo'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="row mb-12">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary" style="width:100%;color:#fff!important">
                        <i class="fa fa-filter"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>

        <hr>

        <!-- --------------------------- RESULTADOS EM CARDS --------------------------- -->
        <?php
        $conn = getDatabaseConnection();
        $conditions=[]; $params=[]; $filtered=false;

        if(!empty($_GET['numero_provimento'])){
            if(strpos($_GET['numero_provimento'],'/')!==false){
                list($numero,$ano)=explode('/',$_GET['numero_provimento']);
                $conditions[]='numero_provimento=:numero AND YEAR(data_provimento)=:ano';
                $params[':numero']=$numero; $params[':ano']=$ano;
            }else{
                $conditions[]='numero_provimento=:numero'; $params[':numero']=$_GET['numero_provimento'];
            }
            $filtered=true;
        }
        if(!empty($_GET['origem']))           { $conditions[]='origem=:origem';                    $params[':origem']=$_GET['origem'];               $filtered=true; }
        if(!empty($_GET['tipo']))             { $conditions[]='tipo=:tipo';                        $params[':tipo']  =$_GET['tipo'];                 $filtered=true; }
        if(!empty($_GET['ano']))              { $conditions[]='YEAR(data_provimento)=:ano';        $params[':ano']   =$_GET['ano'];                  $filtered=true; }
        if(!empty($_GET['data_provimento']))  { $conditions[]='data_provimento=:data_provimento';  $params[':data_provimento']=$_GET['data_provimento']; $filtered=true; }
        if(!empty($_GET['descricao']))        { $conditions[]='descricao LIKE :descricao';         $params[':descricao']='%'.$_GET['descricao'].'%'; $filtered=true; }
        if(!empty($_GET['conteudo_anexo']))   { $conditions[]='conteudo_anexo LIKE :conteudo_anexo';$params[':conteudo_anexo']='%'.$_GET['conteudo_anexo'].'%'; $filtered=true; }

        $sql='SELECT * FROM provimentos';
        if($conditions) $sql.=' WHERE '.implode(' AND ',$conditions);
        // Ordenação padrão por data desc — mantém comportamento anterior quando não filtrado
        if(!$filtered)  $sql.=' ORDER BY data_provimento DESC';

        $stmt=$conn->prepare($sql);
        foreach($params as $k=>$v){ $stmt->bindValue($k,$v); }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($rows);
        ?>

        <div class="results-toolbar">
            <div class="count">
                <i class="mdi mdi-format-list-bulleted-square"></i>
                <?= $total ?> resultado<?= $total==1?'':'s' ?>
            </div>
            <div class="controls">
                <select id="sortSelect" class="form-control">
                    <option value="date_desc">Ordenar: Data (mais recente)</option>
                    <option value="date_asc">Ordenar: Data (mais antiga)</option>
                    <option value="num_asc">Ordenar: Número (A→Z)</option>
                    <option value="num_desc">Ordenar: Número (Z→A)</option>
                    <option value="tipo_asc">Ordenar: Tipo (A→Z)</option>
                    <option value="origem_asc">Ordenar: Origem (A→Z)</option>
                </select>
            </div>
        </div>

        <?php if ($total === 0): ?>
            <div class="empty-state">
                <i class="mdi mdi-file-search-outline"></i>
                Nenhum documento encontrado com os filtros informados.
                <div style="margin-top:6px;font-size:.9rem;">Tente remover algum filtro ou buscar por outros termos.</div>
            </div>
        <?php else: ?>
            <div id="cardsContainer" class="cards-grid">
                <?php foreach($rows as $p):
                    $id       = (int)$p['id'];
                    $tipo     = $p['tipo']         ?? '';
                    $origem   = $p['origem']       ?? '';
                    $numero   = $p['numero_provimento'] ?? '';
                    $dataSQL  = $p['data_provimento'] ?? '';
                    $dataISO  = $dataSQL ? date('Y-m-d', strtotime($dataSQL)) : '';
                    $dataBR   = $dataSQL ? date('d/m/Y', strtotime($dataSQL)) : '—';
                    $anoDoc   = $dataSQL ? date('Y', strtotime($dataSQL)) : '';
                    $numAno   = trim($numero . '/' . $anoDoc, '/');
                    $desc     = $p['descricao']    ?? '';
                    $caminho  = $p['caminho_anexo'] ?? '';
                    // Mantém URL relativa; JS tornará absoluta via APP_BASE_URL
                ?>
                <article class="prov-card"
                         data-date="<?= e($dataISO) ?>"
                         data-num="<?= e($numero) ?>"
                         data-tipo="<?= e($tipo) ?>"
                         data-origem="<?= e($origem) ?>"
                         data-url="<?= e($caminho) ?>"
                         data-id="<?= $id ?>">
                    <div class="prov-card__header">
                        <span class="chip"><i class="mdi mdi-label-outline"></i><?= e($tipo ?: 'Documento') ?></span>
                        <div class="prov-card__num">nº <?= e($numAno ?: $numero) ?></div>
                    </div>
                    <div class="prov-card__body">
                        <div class="prov-meta">
                            <span class="meta" title="Origem"><i class="mdi mdi-source-branch"></i><?= e($origem ?: '—') ?></span>
                            <span class="meta" title="Data"><i class="mdi mdi-calendar-month-outline"></i><?= e($dataBR) ?></span>
                        </div>
                        <!-- Removido o title para evitar 'legenda' poluída ao passar o mouse -->
                        <div class="prov-desc js-desc" tabindex="0"><?= e($desc ?: '—') ?></div>
                        <div class="prov-actions">
                            <button class="btn btn-outline btn-sm js-visualizar"><i class="mdi mdi-eye-outline"></i> Visualizar</button>
                            <button class="btn btn-outline btn-sm js-abrir"><i class="mdi mdi-open-in-new"></i> Abrir</button>
                            <button class="btn btn-outline btn-sm js-baixar"><i class="mdi mdi-download"></i> Baixar</button>
                            <button class="btn btn-outline btn-sm js-copiar"><i class="mdi mdi-link-variant"></i> Copiar link</button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ================================ MODAL DE VISUALIZAÇÃO ================================ -->
<div class="modal fade modal-modern" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" aria-modal="true">
    <div class="modal-content">
      <!-- Header minimalista (somente o botão fechar); o título foi movido para a coluna esquerda -->
      <div class="modal-header">
        <div style="min-height:1px"></div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
        <div class="doc-layout">
          <!-- COLUNA ESQUERDA (informações e controles) -->
          <aside class="doc-sidebar">
            <!-- Título e badge (antes no header) -->
            <div class="doc-title">
              <div class="title-row">
                <div class="title-left" id="visualizarModalLabel">
                  <i class="mdi mdi-file-document-outline"></i>
                  <span class="title-text">Documento</span>
                </div>
                <span class="badge badge-pill ml-2" id="tagTipo">—</span>
              </div>
            </div>

            <!-- META BAR -->
            <div class="meta-bar">
              <div class="meta-item"><label>Tipo</label><div class="value" id="metaTipo">—</div></div>
              <div class="meta-item"><label>Número</label><div class="value" id="metaNumero">—</div></div>
              <div class="meta-item"><label>Origem</label><div class="value" id="metaOrigem">—</div></div>
              <div class="meta-item"><label>Data</label><div class="value" id="metaData">—</div></div>
            </div>

            <!-- TOOLBAR (descrição, busca no PDF e ações) -->
            <div class="doc-toolbar">
              <div id="metaDescricaoWrapper" class="meta-desc" tabindex="0" title="Clique para expandir/recolher a descrição">
                <i class="mdi mdi-text-long"></i> <span id="metaDescricao">—</span>
              </div>

              <div class="pdf-search">
                <div class="input-group input-group-sm">
                  <input type="text" id="pdfSearchInput" class="form-control" placeholder="Buscar frase no PDF">
                  <div class="input-group-append">
                    <button class="btn theme-outline btn-sm" id="pdfSearchBtn" title="Buscar no PDF">
                      <i class="mdi mdi-magnify"></i><span>Buscar</span>
                    </button>
                  </div>
                </div>
              </div>

              <div class="doc-actions">
                <button class="btn theme-outline btn-sm" id="btnOpenNew" title="Abrir em nova aba"><i class="mdi mdi-open-in-new"></i><span>Abrir</span></button>
                <button class="btn theme-outline btn-sm" id="btnDownload" title="Baixar documento"><i class="mdi mdi-download"></i><span>Baixar</span></button>
                <button class="btn theme-outline btn-sm" id="btnCopyLink" title="Copiar link"><i class="mdi mdi-link-variant"></i><span>Copiar</span></button>
              </div>
            </div>
          </aside>

          <!-- COLUNA DIREITA (PDF / viewer) -->
          <section class="viewer-area">
            <div class="viewer-wrapper">
              <div id="docLoader" class="doc-loader"><i class="mdi mdi-loading mdi-spin mr-2"></i> Carregando documento…</div>
              <iframe id="anexo_visualizacao" class="viewer-frame" frameborder="0"></iframe>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================ SCRIPTS ================================ -->
<script>
const APP_BASE_URL     = <?php echo json_encode($appBase,    JSON_UNESCAPED_SLASHES); ?>;
const PDFJS_VIEWER_URL = <?php echo json_encode($viewerBase, JSON_UNESCAPED_SLASHES); ?>;
function toAbsoluteUrl(u){ return !u ? '' : (/^(https?:)?\/\//i.test(u) ? u : APP_BASE_URL + u.replace(/^\/+/,'') ); }
</script>
<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>
$(document).ready(function(){
  // Descrição expandir/contrair no modal
  $('#metaDescricaoWrapper').on('click',e=>$(e.currentTarget).toggleClass('expanded'))
                            .on('mouseleave blur',e=>$(e.currentTarget).removeClass('expanded'));

  // Clique em QUALQUER parte do card abre a visualização
  $('#cardsContainer').on('click','.prov-card',function(e){
    // Se o clique foi em um botão/ação, não abrir duas vezes
    if($(e.target).closest('.js-abrir, .js-baixar, .js-copiar, .js-visualizar, .prov-actions button').length) return;
    visualizarProvimento($(this).data('id'));
  });

  // Botões dos cards (com stopPropagation para não disparar o click do card)
  $('#cardsContainer').on('click','.js-visualizar',function(e){
    e.stopPropagation();
    const card = $(this).closest('.prov-card');
    visualizarProvimento(card.data('id'));
  });
  $('#cardsContainer').on('click','.js-abrir',function(e){
    e.stopPropagation();
    const card = $(this).closest('.prov-card');
    const url  = toAbsoluteUrl(String(card.data('url')||''));
    if(url) window.open(url,'_blank');
  });
  $('#cardsContainer').on('click','.js-baixar',async function(e){
    e.stopPropagation();
    const card = $(this).closest('.prov-card');
    const url  = toAbsoluteUrl(String(card.data('url')||''));
    if(!url) return;
    // Monta nome amigável com base nos atributos do card
    const nomeBase = composeDownloadName({
      numero_provimento: String(card.data('num')||''),
      data_provimento: String(card.data('date')||''),
      tipo: String(card.data('tipo')||'Documento'),
      origem: String(card.data('origem')||'')
    });
    await baixarArquivo(url, nomeBase);
  });
  $('#cardsContainer').on('click','.js-copiar',async function(e){
    e.stopPropagation();
    const card = $(this).closest('.prov-card');
    const url  = toAbsoluteUrl(String(card.data('url')||''));
    if(!url) return;
    try{
      await navigator.clipboard.writeText(url);
      Swal.fire({icon:'success',title:'Link copiado!',timer:1200,showConfirmButton:false});
    }catch{
      Swal.fire({icon:'error',title:'Falha ao copiar link'});
    }
  });

  // NOVO: refinar resultados localmente (ignora acentos/maiúsculas)
  const removeDiacritics = s => (s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const debounce = (fn, wait=200) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), wait); }; };
  $('#refineInput').on('input', debounce(function(){
    refineCards(this.value, removeDiacritics);
  }, 200));

  // Ordenação client-side dos cards
  $('#sortSelect').on('change', function(){
    sortCards(this.value);
  });
  // Ordenação inicial: Data desc
  sortCards('date_desc');

  // Realce de termos buscados na descrição (da busca do formulário)
  highlightSearch();
  
  // Busca no PDF dentro do modal (frase exata)
  $('#pdfSearchBtn').on('click',triggerPdfSearch);
  $('#pdfSearchInput').on('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); triggerPdfSearch(); }});

  // Validação leve da data do filtro
  const currentYear=new Date().getFullYear();
  $('#data_provimento').on('change',function(){
    const sel=new Date(this.value);
    if(sel.getFullYear()>currentYear){
      Swal.fire({icon:'warning',title:'Data inválida',text:'O ano não pode ser maior que o ano atual.'});
      this.value='';
    }
  });
});

/* ---------- utilidades ---------- */
function composeDownloadName(p){
  const numero=p.numero_provimento||'';
  const ano   =p.ano_provimento||(p.data_provimento?new Date(p.data_provimento+'T00:00:00').getFullYear():'');
  const tipo  =p.tipo||'Documento';
  const origem=p.origem||'';
  return (`${tipo} nº ${numero}_${ano} - ${origem}`)
          .replace(/:/g,'').replace(/\//g,'_')
          .replace(/[<>:"/\\|?*\x00-\x1F]/g,'')
          .replace(/\s+/g,' ').trim().replace(/\.+$/,'');
}
async function baixarArquivo(url,nomeBase){
  try{
    const r=await fetch(url,{credentials:'same-origin'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    const b=await r.blob();
    let ext=''; const ct=(b.type||'').toLowerCase();
    const m=url.split('?')[0].match(/\.[a-z0-9]+$/i);
    if(m) ext=m[0];
    else if(ct.includes('pdf')) ext='.pdf'; else if(ct.includes('jpeg')) ext='.jpg';
    else if(ct.includes('png')) ext='.png'; else if(ct.includes('gif')) ext='.gif';
    else if(ct.includes('msword')) ext='.doc'; else if(ct.includes('wordprocessingml')) ext='.docx';
    else if(ct.includes('spreadsheetml')) ext='.xlsx'; else if(ct.includes('csv')) ext='.csv';
    else if(ct.includes('rtf')) ext='.rtf'; else if(ct.includes('text')) ext='.txt';
    const o=URL.createObjectURL(b);
    const a=document.createElement('a'); a.href=o; a.download=nomeBase+ext; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(o);
  }catch(e){ Swal.fire({icon:'error',title:'Falha ao baixar',text:e.message||'Erro desconhecido'}); }
}

/* ---------- visualização ---------- */
let __currentPdfUrl='';
function visualizarProvimento(id){
  $('#docLoader').show(); $('#anexo_visualizacao').attr('src','about:blank');
  $.get('obter_provimento.php',{id},res=>{
    try{
      const p=typeof res==='object'?res:JSON.parse(res);
      const numAno=(p.numero_provimento||'')+'/'+(p.ano_provimento||'');
      $('#metaTipo').text(p.tipo||'—'); $('#metaNumero').text(numAno||'—'); $('#metaOrigem').text(p.origem||'—');
      $('#metaData').text(p.data_provimento?new Date(p.data_provimento+'T00:00:00').toLocaleDateString('pt-BR'):'—');
      $('#metaDescricao').text(p.descricao||'—'); $('#metaDescricaoWrapper').removeClass('expanded');
      $('#tagTipo').text(p.tipo||'Documento'); $('.title-text').text(`${p.tipo||'Documento'} nº: ${numAno} - ${p.origem||'—'}`);
      __currentPdfUrl=toAbsoluteUrl(p.caminho_anexo||'');
      $('#anexo_visualizacao').off('load').on('load',()=>$('#docLoader').fadeOut(150)).attr('src',__currentPdfUrl);
      const nomePadrao=composeDownloadName(p);
      $('#btnOpenNew').off('click').on('click',()=>{ if(__currentPdfUrl) window.open(__currentPdfUrl,'_blank'); });
      $('#btnDownload').off('click').on('click',()=>{ if(__currentPdfUrl) baixarArquivo(__currentPdfUrl,nomePadrao); });
      $('#btnCopyLink').off('click').on('click',async()=>{
          try{ await navigator.clipboard.writeText(__currentPdfUrl); Swal.fire({icon:'success',title:'Link copiado!',timer:1200,showConfirmButton:false});}
          catch{ Swal.fire({icon:'error',title:'Falha ao copiar link'});}
      });
      $('#visualizarModal').modal('show');
    }catch(e){ console.error(e); alert('Erro ao processar resposta do servidor.'); }
  }).fail(()=>alert('Erro ao obter os dados do provimento.'));
}

/* ---------- busca no PDF: frase exata ---------- */
function buildPdfJsViewerUrl(fileUrl,search){
  let u=PDFJS_VIEWER_URL+'?file='+encodeURIComponent(fileUrl);
  if(search&&search.trim()) u+='#search='+encodeURIComponent(search.trim())+'&phrase=true';
  return u;
}
function triggerPdfSearch(){
  const term=($('#pdfSearchInput').val()||'').trim();
  if(!__currentPdfUrl)           { Swal.fire({icon:'warning',title:'Nenhum documento aberto'}); return; }
  if(!term)                      { Swal.fire({icon:'warning',title:'Digite um termo ou frase'}); return; }
  $('#docLoader').show();
  $('#anexo_visualizacao').off('load').on('load',()=>$('#docLoader').fadeOut(150))
                         .attr('src',buildPdfJsViewerUrl(__currentPdfUrl,term));
}

/* ---------- Ordenação dos cards ---------- */
function sortCards(mode){
  const container = document.getElementById('cardsContainer');
  if(!container) return;
  const cards = Array.from(container.children);
  const collator = new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' });

  cards.sort((a,b)=>{
    const da = a.getAttribute('data-date') || '';
    const db = b.getAttribute('data-date') || '';
    const na = (a.getAttribute('data-num') || '').toString();
    const nb = (b.getAttribute('data-num') || '').toString();
    const ta = (a.getAttribute('data-tipo') || '');
    const tb = (b.getAttribute('data-tipo') || '');
    const oa = (a.getAttribute('data-origem') || '');
    const ob = (b.getAttribute('data-origem') || '');

    switch(mode){
      case 'date_asc':  return collator.compare(da, db);
      case 'date_desc': return collator.compare(db, da);
      case 'num_asc':   return collator.compare(na, nb);
      case 'num_desc':  return collator.compare(nb, na);
      case 'tipo_asc':  return collator.compare(ta, tb);
      case 'origem_asc':return collator.compare(oa, ob);
      default:          return collator.compare(db, da);
    }
  });

  cards.forEach(c=>container.appendChild(c));
}

/* ---------- Filtrar (refinar) os cards já exibidos ---------- */
function refineCards(q, normalizer){
  const norm = normalizer || (s => (s||'').toLowerCase());
  const query = norm(q||'');
  const cards = $('#cardsContainer .prov-card');
  if(!query){
    cards.show();
    updateCount();
    return;
  }
  cards.each(function(){
    const el = $(this);
    const txtRaw = [
      el.find('.js-desc').text(),
      String(el.data('num')||''),
      String(el.data('tipo')||''),
      String(el.data('origem')||''),
      el.find('.prov-card__num').text()
    ].join(' ');
    const txt = norm(txtRaw);
    el.toggle(txt.indexOf(query) > -1);
  });
  updateCount();
}

/* ---------- Atualiza a contagem exibida ---------- */
function updateCount(){
  const visible = $('#cardsContainer .prov-card:visible').length;
  const label = `${visible} resultado${visible===1?'':'s'}`;
  $('.results-toolbar .count').html(`<i class="mdi mdi-format-list-bulleted-square"></i> ${label}`);
}

/* ---------- Realce dos termos buscados ---------- */
function highlightSearch(){
  const term1 = (document.getElementById('descricao')?.value || '').trim();
  const term2 = (document.getElementById('conteudo_anexo')?.value || '').trim();
  const term  = term1 || term2;
  if(!term) return;

  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
  const re  = new RegExp(esc(term), 'gi');

  document.querySelectorAll('.prov-card .js-desc').forEach(el=>{
    const txt = el.textContent;
    if(!txt) return;
    el.innerHTML = txt.replace(re, m => `<mark>${m}</mark>`);
  });
}
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
