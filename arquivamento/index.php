<?php
/**
 * Atlas · Arquivamento Digital — acervo.
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$csrf        = arq_csrf_token();
$atribuicoes = arq_atribuicoes();
$pdfjs       = is_file(__DIR__ . '/../provimentos/pdfjs/web/viewer.html') ? '../provimentos/pdfjs/web/viewer.html' : '';
?>
<?php include(__DIR__ . '/../os/guia/guia.php'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Atlas · Arquivamento Digital</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/css/arquivamento.css?v=8">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container arq">

    <!-- Cabeçalho ================================================== -->
    <header class="arq-topo">
      <div class="arq-titulo-linha">
        <div class="arq-icone-titulo"><i class="fa fa-archive"></i></div>
        <div>
        <div class="arq-sobretitulo">Acervo digital</div>
        <h1>Arquivamento</h1>
        <p>Consulta, guarda e compilação dos documentos arquivados pela serventia.</p>
        </div>
      </div>
      <div class="arq-kpis">
        <div class="arq-kpi"><b id="kpi-total">—</b><span>no acervo</span></div>
        <div class="arq-kpi"><b id="kpi-mes">—</b><span>neste mês</span></div>
        <div class="arq-kpi"><b id="kpi-anexos">—</b><span>documentos</span></div>
        <div class="arq-kpi"><b id="kpi-espaco">—</b><span>em disco</span></div>
      </div>
    </header>

    <!-- Barra de comando ========================================== -->
    <section class="arq-comando" aria-label="Busca e filtros">
      <div class="arq-busca">
        <i class="fa fa-search" aria-hidden="true"></i>
        <input type="search" id="arq-q" placeholder="Buscar por parte, categoria, protocolo, matrícula, anexo…"
               aria-label="Buscar no acervo" autocomplete="off">
        <button class="arq-limpar" id="arq-q-limpar" type="button" aria-label="Limpar busca"><i class="fa fa-times"></i></button>
      </div>

      <div class="arq-periodo" id="arq-periodo" role="group" aria-label="Período do ato">
        <button class="arq-fatia" data-periodo="hoje" aria-pressed="false">Hoje</button>
        <button class="arq-fatia" data-periodo="7d" aria-pressed="false">7 dias</button>
        <button class="arq-fatia" data-periodo="30d" aria-pressed="true">30 dias</button>
        <button class="arq-fatia" data-periodo="ano" aria-pressed="false">Este ano</button>
        <button class="arq-fatia" data-periodo="tudo" aria-pressed="false">Tudo</button>
      </div>

      <button class="arq-btn" id="arq-alternar-filtros" aria-expanded="false" aria-controls="arq-filtros">
        <i class="fa fa-sliders"></i> Filtros
      </button>

      <div class="arq-seg" id="arq-visao" role="group" aria-label="Modo de exibição">
        <button data-visao="cards" title="Fichas" aria-pressed="true"><i class="fa fa-th-large"></i></button>
        <button data-visao="tabela" title="Tabela" aria-pressed="false"><i class="fa fa-list"></i></button>
      </div>

      <a class="arq-btn arq-btn-p" href="cadastro.php"><i class="fa fa-plus"></i> Novo arquivamento</a>

      <!-- Filtros avançados -->
      <div class="arq-filtros" id="arq-filtros">
        <div>
          <label class="arq-rot" for="arq-f-atribuicao">Atribuição</label>
          <select id="arq-f-atribuicao" data-filtro="atribuicao">
            <option value="">Todas</option>
            <?php foreach ($atribuicoes as $a): ?>
              <option value="<?= arq_e($a) ?>"><?= arq_e($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="arq-rot" for="arq-f-categoria">Categoria</label>
          <select id="arq-f-categoria" data-filtro="categoria"><option value="">Todas</option></select>
        </div>
        <div>
          <label class="arq-rot" for="arq-f-nome">Nome da parte</label>
          <input type="text" id="arq-f-nome" data-filtro="nome" placeholder="Nome completo">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-cpf">CPF/CNPJ</label>
          <input type="text" id="arq-f-cpf" data-filtro="cpf" inputmode="numeric" placeholder="Somente números">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-livro">Livro</label>
          <input type="text" id="arq-f-livro" data-filtro="livro">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-folha">Folha</label>
          <input type="text" id="arq-f-folha" data-filtro="folha">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-termo">Termo/Ordem</label>
          <input type="text" id="arq-f-termo" data-filtro="termo">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-protocolo">Protocolo</label>
          <input type="text" id="arq-f-protocolo" data-filtro="protocolo">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-matricula">Matrícula</label>
          <input type="text" id="arq-f-matricula" data-filtro="matricula">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-descricao">Descrição</label>
          <input type="text" id="arq-f-descricao" data-filtro="descricao">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-data">Data exata do ato</label>
          <input type="date" id="arq-f-data" data-filtro="data">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-de">Período · de</label>
          <input type="date" id="arq-f-de" data-filtro="de">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-ate">Período · até</label>
          <input type="date" id="arq-f-ate" data-filtro="ate">
        </div>
        <div>
          <label class="arq-rot" for="arq-f-anexo">Documentos</label>
          <select id="arq-f-anexo" data-filtro="com_anexo">
            <option value="">Tanto faz</option>
            <option value="sim">Com anexo</option>
            <option value="nao">Sem anexo</option>
          </select>
        </div>
        <div>
          <label class="arq-rot" for="arq-ordenar">Ordenar por</label>
          <select id="arq-ordenar">
            <option value="data_ato:desc">Data do ato (mais recente)</option>
            <option value="data_ato:asc">Data do ato (mais antiga)</option>
            <option value="data_cadastro:desc">Cadastro (mais recente)</option>
            <option value="modificado_em:desc">Última alteração</option>
            <option value="categoria:asc">Categoria (A–Z)</option>
            <option value="anexos_qtd:desc">Mais anexos</option>
          </select>
        </div>
        <div class="arq-full">
          <a class="arq-btn arq-btn-sm" href="categorias.php"><i class="fa fa-tags"></i> Categorias</a>
          <a class="arq-btn arq-btn-sm" id="arq-link-lixeira" href="lixeira.php"><i class="fa fa-trash-o"></i> Lixeira</a>
        </div>
      </div>
    </section>

    <div class="arq-ativos" id="arq-ativos"></div>

    <div class="arq-resumo">
      <h2>Resultados</h2>
      <div class="arq-contagem" id="arq-contagem"></div>
    </div>

    <div id="arq-resultados"></div>
    <div class="arq-paginacao" id="arq-paginacao"></div>
  </div>
</div>

<!-- Barra de seleção ============================================== -->
<div class="arq-selecao arq" id="arq-selecao" role="region" aria-label="Ações em lote">
  <span><b id="arq-selecao-n">0</b> <span id="arq-selecao-rot">arquivamentos selecionados</span></span>
  <button class="arq-btn arq-btn-sm" id="arq-sel-todos"><i class="fa fa-check-square-o"></i> Todo o resultado</button>
  <button class="arq-btn arq-btn-sm arq-btn-p" id="arq-sel-compilar"><i class="fa fa-files-o"></i> Compilar PDF</button>
  <button class="arq-btn arq-btn-sm" id="arq-sel-zip"><i class="fa fa-file-archive-o"></i> ZIP</button>
  <button class="arq-btn arq-btn-sm" id="arq-sel-csv"><i class="fa fa-table"></i> CSV</button>
  <button class="arq-btn arq-btn-sm arq-btn-ic" id="arq-sel-limpar" title="Limpar seleção"><i class="fa fa-times"></i></button>
</div>

<!-- Diálogo · detalhe ============================================= -->
<div class="arq-fundo arq" id="arq-dlg-detalhe" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="arq-detalhe-titulo">
  <div class="arq-dialogo largo">
    <div class="arq-dlg-topo">
      <h2 id="arq-detalhe-titulo">Arquivamento</h2>
      <span class="arq-num" id="arq-detalhe-num"></span>
      <button class="arq-fechar" data-fechar aria-label="Fechar"><i class="fa fa-times"></i></button>
    </div>
    <div class="arq-dlg-corpo" id="arq-detalhe-corpo"></div>
    <div class="arq-dlg-pe">
      <span class="arq-esq">Todo acesso a documentos fica registrado na trilha de auditoria.</span>
      <a class="arq-btn" id="arq-detalhe-capa" target="_blank" rel="noopener"><i class="fa fa-print"></i> Capa de arquivamento</a>
      <button class="arq-btn" id="arq-detalhe-compilar" data-compilar=""><i class="fa fa-files-o"></i> Compilar</button>
      <a class="arq-btn arq-btn-p" id="arq-detalhe-editar"><i class="fa fa-pencil"></i> Editar</a>
    </div>
  </div>
</div>

<!-- Diálogo · visualizador ======================================== -->
<div class="arq-fundo arq" id="arq-dlg-visor" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="arq-visor-titulo">
  <div class="arq-dialogo largo">
    <div class="arq-dlg-topo">
      <h2 id="arq-visor-titulo">Documento</h2>
      <a class="arq-btn arq-btn-sm" id="arq-visor-aba" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Nova aba</a>
      <a class="arq-btn arq-btn-sm" id="arq-visor-baixar"><i class="fa fa-download"></i> Baixar</a>
      <button class="arq-fechar" data-fechar aria-label="Fechar"><i class="fa fa-times"></i></button>
    </div>
    <div class="arq-visor"><div class="arq-visor-palco" id="arq-visor-palco"></div></div>
  </div>
</div>

<!-- Diálogo · compilação ========================================== -->
<div class="arq-fundo arq" id="arq-dlg-compilar" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="arq-compilar-titulo">
  <div class="arq-dialogo">
    <div class="arq-dlg-topo">
      <h2 id="arq-compilar-titulo">Compilar dossiê</h2>
      <button class="arq-fechar" data-fechar aria-label="Fechar"><i class="fa fa-times"></i></button>
    </div>
    <div class="arq-dlg-corpo">
      <div class="arq-compilar-cab">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <div>Os documentos abaixo viram um único PDF, na ordem da lista. Arraste para reordenar
             e desmarque o que não deve entrar. O arquivo começa com uma capa e um índice de folhas.</div>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px">
        <strong style="font-size:.85rem" id="arq-compilar-resumo">—</strong>
        <div style="display:flex;gap:10px;align-items:center">
          <label style="display:flex;align-items:center;gap:7px;font-size:.83rem;margin:0;cursor:pointer">
            <input type="checkbox" id="arq-carimbar" checked> Carimbar numeração de folhas
          </label>
          <button class="arq-btn arq-btn-sm" id="arq-compilar-marcar"><i class="fa fa-check-square-o"></i> Marcar/desmarcar</button>
        </div>
      </div>

      <ul class="arq-pilha" id="arq-pilha"></ul>

      <div class="arq-progresso" id="arq-barra"><div></div></div>
      <div class="arq-etapa" id="arq-compilar-etapa"></div>
    </div>
    <div class="arq-dlg-pe">
      <span class="arq-esq">Formatos fora do PDF (Word, Excel, XML) saem no pacote ZIP.</span>
      <a class="arq-btn" id="arq-compilar-zip"><i class="fa fa-file-archive-o"></i> Baixar ZIP</a>
      <button class="arq-btn" id="arq-compilar-fechar" data-fechar>Fechar</button>
      <button class="arq-btn arq-btn-p" id="arq-compilar-gerar" data-foco><i class="fa fa-file-pdf-o"></i> Gerar PDF único</button>
    </div>
  </div>
</div>

<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="assets/js/dialogos.js?v=6"></script>
<script src="assets/vendor/pdf-lib.min.js"></script>
<script>
window.ARQ_CFG = {
  csrf: <?= json_encode($csrf) ?>,
  usuario: <?= json_encode(arq_usuario_nome()) ?>,
  pdfjs: <?= json_encode($pdfjs) ?>,
  retencao: <?= (int) ARQ_LIXEIRA_DIAS ?>
};
</script>
<script src="assets/js/compilador.js?v=6"></script>
<script src="assets/js/arquivamento.js?v=7"></script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
