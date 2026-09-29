<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
iris_ensure_schema();

$CSRF = iris_csrf();
$temChave = iris_tem_chave();
$isAdmin = iris_is_admin();
$pub = iris_config_publica();
$modelos = array_map(function ($m) { return ['id' => $m['identificador'], 'rotulo' => $m['rotulo'], 'descricao' => $m['descricao'] ?? '', 'padrao' => (int)$m['padrao'] === 1]; }, iris_modelos(true));
$tipos = array_map(function ($t) { return ['slug' => $t['slug'], 'nome' => $t['nome'], 'descricao' => $t['descricao'] ?? '']; }, iris_tipos(true));
$abrir = (int)($_GET['abrir'] ?? 0);
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$v = rawurlencode(IRIS_VERSAO);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Iris · Extração de Texto e Dados</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/iris.css?v=<?php echo $v; ?>">
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container ir-container">

    <section class="ir-hero">
      <div class="ir-title-row">
        <div class="ir-ic"><i class="fa fa-eye"></i></div>
        <div style="min-width:0">
          <h1>Atlas Iris <span class="ir-ver">v<?php echo eh(IRIS_VERSAO); ?></span></h1>
          <div class="ir-sub">Transcrição fiel de impressos e manuscritos, com extração de dados para conferência.</div>
        </div>
        <div class="ir-actions">
          <span class="chip <?php echo $temChave ? 'on' : 'warn'; ?>"><i class="fa fa-key"></i> <?php echo $temChave ? 'Pronto para extrair' : ($isAdmin ? 'Configure a API' : 'API não configurada'); ?></span>
          <a class="ir-pill ir-soft" href="historico.php"><i class="fa fa-history"></i> Histórico</a>
          <?php if ($isAdmin): ?><a class="ir-pill ir-soft" href="configurar.php"><i class="fa fa-cog"></i> Configurar</a><?php endif; ?>
        </div>
      </div>
    </section>

    <?php if (!$temChave): ?>
    <div class="ir-card ir-alerta">
      <i class="fa fa-exclamation-triangle"></i>
      <div><?php if ($isAdmin): ?>É preciso cadastrar a <b>chave da API do Gemini</b> antes de extrair. <a href="configurar.php">Ir para Configurar</a>.<?php else: ?>A extração ainda não está disponível: peça ao <b>administrador</b> para configurar a chave da API.<?php endif; ?></div>
    </div>
    <?php endif; ?>

    <!-- Upload -->
    <div class="ir-card" id="uploadCard">
      <div class="dz" id="dz" tabindex="0" role="button" aria-label="Escolher arquivos">
        <div class="dz-ic"><i class="fa fa-cloud-upload"></i></div>
        <div class="dz-t">Arraste imagens ou PDFs aqui, ou clique para escolher</div>
        <div class="dz-s">PDF, TIFF (inclusive multipágina), JPG, PNG, WEBP ou HEIC · vários arquivos de uma vez formam uma fila · também aceita colar (Ctrl+V)</div>
        <input type="file" id="fileInput" multiple accept="application/pdf,image/png,image/jpeg,image/webp,image/tiff,image/heic,image/heif,.tif,.tiff,.heic,.heif" hidden>
      </div>
    </div>

    <!-- Fila -->
    <div class="ir-fila" id="fila" style="display:none"></div>

    <!-- Área de trabalho -->
    <div class="ws" id="ws" style="display:none">

      <!-- Visualizador -->
      <div class="ir-card vw-card">
        <div class="vw-head">
          <div class="arq-ic" id="arqIc"><i class="fa fa-file-o"></i></div>
          <div style="min-width:0;flex:1">
            <div class="arq-nome" id="arqNome"></div>
            <div class="arq-meta" id="arqMeta"></div>
          </div>
          <button class="ib" id="btnFechar" title="Fechar este documento"><i class="fa fa-times"></i></button>
        </div>
        <div class="vw-toolbar">
          <button class="ib" id="vwPrev" title="Página anterior"><i class="fa fa-chevron-left"></i></button>
          <span class="vw-pag" id="vwPag">1 / 1</span>
          <button class="ib" id="vwNext" title="Próxima página"><i class="fa fa-chevron-right"></i></button>
          <span class="vw-sep"></span>
          <button class="ib" id="vwZoomOut" title="Diminuir zoom"><i class="fa fa-search-minus"></i></button>
          <button class="ib" id="vwFit" title="Ajustar à largura"><i class="fa fa-arrows-h"></i></button>
          <button class="ib" id="vwZoomIn" title="Aumentar zoom"><i class="fa fa-search-plus"></i></button>
          <span class="vw-sep"></span>
          <button class="ib" id="vwRotL" title="Girar página para a esquerda"><i class="fa fa-rotate-left"></i></button>
          <button class="ib" id="vwRotR" title="Girar página para a direita"><i class="fa fa-rotate-right"></i></button>
          <button class="ib tgl" id="vwRealce" title="Realçar tinta apagada (cinza + contraste + nitidez) — também vale para o envio à IA"><i class="fa fa-adjust"></i></button>
          <span class="vw-sep"></span>
          <button class="tbtn tgl" id="vwRegiao" title="Desenhe um retângulo sobre um trecho difícil para relê-lo ampliado"><i class="fa fa-crop"></i> Reler região</button>
          <button class="tbtn" id="vwReextrair" title="Extrair novamente só esta página"><i class="fa fa-refresh"></i> Reextrair página</button>
        </div>
        <div class="vw-stage" id="vwStage">
          <div class="vw-canvas-wrap" id="vwWrap">
            <canvas id="vwCanvas"></canvas>
            <div class="vw-sel" id="vwSel" style="display:none"></div>
            <div class="vw-selbar" id="vwSelBar" style="display:none">
              <button class="tbtn pri" id="vwSelReler"><i class="fa fa-magic"></i> Reler este trecho</button>
              <button class="ib" id="vwSelCancel" title="Cancelar"><i class="fa fa-times"></i></button>
            </div>
          </div>
          <div class="vw-msg" id="vwMsg" style="display:none"></div>
        </div>
        <div class="vw-thumbs-head">
          <span id="thumbsInfo"></span>
          <span class="vw-sep"></span>
          <button class="lnk" id="thTodas">marcar todas</button> · <button class="lnk" id="thNenhuma">desmarcar todas</button>
        </div>
        <div class="vw-thumbs" id="vwThumbs"></div>
      </div>

      <!-- Opções + resultado -->
      <div class="rs-col">
        <div class="ir-card" id="optCard">
          <div class="opt-grid">
            <div class="opt">
              <label>O que extrair</label>
              <div class="seg" id="segModo">
                <button data-v="transcricao" title="Texto integral, fiel ao original"><i class="fa fa-file-text-o"></i> Texto</button>
                <button data-v="dados" title="Somente os campos do documento"><i class="fa fa-table"></i> Dados</button>
                <button data-v="completo" title="Texto integral e campos (os campos usam o texto como apoio)"><i class="fa fa-clone"></i> Texto + dados</button>
              </div>
            </div>
            <div class="opt">
              <label>Precisão</label>
              <div class="seg" id="segPrec">
                <button data-v="padrao" title="Uma leitura em alta resolução por página"><i class="fa fa-bolt"></i> Padrão</button>
                <button data-v="maxima" title="Página + ampliações + segunda leitura de verificação. Recomendado para manuscritos e livros antigos."><i class="fa fa-pencil"></i> Máxima (manuscritos)</button>
              </div>
            </div>
            <div class="opt" id="optTipoBox">
              <label for="selTipo">Tipo de documento</label>
              <select class="inp" id="selTipo">
                <option value="auto">Detectar automaticamente</option>
                <option value="livre">Livre (todos os campos que encontrar)</option>
                <?php foreach ($tipos as $t): ?><option value="<?php echo eh($t['slug']); ?>"><?php echo eh($t['nome']); ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="opt-checks">
            <label class="ck" id="ckDigitalBox" style="display:none"><input type="checkbox" id="ckDigital"> Usar o texto digital do PDF nas páginas que já o têm <span class="muted" id="ckDigitalInfo"></span></label>
            <label class="ck" id="ckMarcadoresBox"><input type="checkbox" id="ckMarcadores" checked> Separar páginas com [Página N]</label>
          </div>
          <div class="dup-aviso" id="dupAviso" style="display:none"></div>
          <div class="opt-acao">
            <div class="muted" id="optResumo"></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <button class="ir-pill ir-soft" id="btnExtrairTodos" style="display:none"><i class="fa fa-list"></i> Extrair a fila toda</button>
              <button class="ir-pill ir-pri" id="btnExtrair"><i class="fa fa-magic"></i> Extrair</button>
            </div>
          </div>
          <div class="prog" id="prog" style="display:none">
            <div class="prog-top"><span id="progMsg">Preparando…</span><button class="lnk" id="btnCancelar">cancelar</button></div>
            <div class="prog-bar"><div id="progBar"></div></div>
            <div class="muted" id="progSub"></div>
          </div>
        </div>

        <div class="ir-card" id="resCard" style="display:none">
          <div class="tabs" id="tabs">
            <button data-tab="texto" class="on"><i class="fa fa-file-text-o"></i> Texto</button>
            <button data-tab="duvidas"><i class="fa fa-question-circle"></i> Dúvidas <span class="cnt" id="cntDuv">0</span></button>
            <button data-tab="dados"><i class="fa fa-table"></i> Dados <span class="cnt" id="cntDados" style="display:none"></span></button>
            <span class="vw-sep"></span>
            <span class="salvo" id="salvoInfo"></span>
            <button class="tbtn pri" id="btnSalvar" title="Salvar alterações no histórico (Ctrl+S)"><i class="fa fa-floppy-o"></i> Salvar</button>
          </div>

          <!-- Texto -->
          <div class="tab" id="tab-texto">
            <div class="ed-toolbar">
              <button class="tbtn" id="btnUnir" title="Remover quebras de linha simples (mantém parágrafos)"><i class="fa fa-align-left"></i> Unir linhas</button>
              <button class="tbtn" id="btnMono" title="Fonte monoespaçada"><i class="fa fa-font"></i> Mono</button>
              <button class="tbtn" id="btnBuscar" title="Localizar e substituir (Ctrl+F no editor)"><i class="fa fa-search"></i> Localizar</button>
              <div class="dd">
                <button class="tbtn" id="btnMarc"><i class="fa fa-eraser"></i> Marcações <i class="fa fa-caret-down"></i></button>
                <div class="dd-menu">
                  <button data-limpar="duvidas">Aceitar leituras prováveis — remove [? ?] e mantém a palavra</button>
                  <button data-limpar="especiais">Remover [assinatura], [carimbo: …], [selo: …] e afins</button>
                  <button data-limpar="paginas">Remover os separadores [Página N]</button>
                  <button data-limpar="tudo">Remover todas as marcações</button>
                </div>
              </div>
              <span class="sep"></span>
              <button class="tbtn" id="btnCopiar"><i class="fa fa-clipboard"></i> Copiar</button>
              <div class="dd">
                <button class="tbtn"><i class="fa fa-download"></i> Baixar <i class="fa fa-caret-down"></i></button>
                <div class="dd-menu dd-dir">
                  <button data-baixar="txt">Texto (.txt)</button>
                  <button data-baixar="docx">Word (.docx) — dúvidas realçadas</button>
                  <button data-baixar="json">Tudo em JSON (.json)</button>
                </div>
              </div>
            </div>
            <div class="busca" id="buscaBar" style="display:none">
              <input class="inp" id="bLoc" placeholder="Localizar">
              <input class="inp" id="bSub" placeholder="Substituir por">
              <button class="tbtn" id="bProx">Próximo</button>
              <button class="tbtn" id="bSubUm">Substituir</button>
              <button class="tbtn" id="bSubTodos">Substituir todos</button>
              <span class="muted" id="bInfo"></span>
              <button class="ib" id="bFechar"><i class="fa fa-times"></i></button>
            </div>
            <div class="aviso" id="avisoTexto" style="display:none"></div>
            <div class="ed-wrap" id="edWrap">
              <div class="ed-back" id="edBack" aria-hidden="true"></div>
              <textarea class="editor" id="editor" spellcheck="true" placeholder="O texto extraído aparecerá aqui para revisão…"></textarea>
            </div>
            <div class="ed-foot">
              <span id="edInfo"></span>
              <span class="legenda"><mark class="m-duv">[?leitura provável?]</mark> <mark class="m-ileg">[ilegível]</mark> <mark class="m-esp">[assinatura] [carimbo: …]</mark></span>
            </div>
          </div>

          <!-- Dúvidas -->
          <div class="tab" id="tab-duvidas" style="display:none">
            <div class="duv-head">
              <div class="muted">Confira cada ponto contra a imagem. Clique para localizar no texto e na página; use <b>Reler região</b> no visualizador para trechos difíceis.</div>
              <button class="tbtn" id="btnAceitarTodas"><i class="fa fa-check"></i> Aceitar todas as prováveis</button>
            </div>
            <div id="duvLista"></div>
          </div>

          <!-- Dados -->
          <div class="tab" id="tab-dados" style="display:none">
            <div class="ed-toolbar">
              <strong id="dadosTitulo"></strong>
              <span class="sep"></span>
              <button class="tbtn" id="btnDadosExtrair" title="Extrair (ou extrair de novo) os dados deste documento"><i class="fa fa-refresh"></i> Extrair dados</button>
              <button class="tbtn" id="btnDadosCopiar"><i class="fa fa-clipboard"></i> Copiar JSON</button>
              <div class="dd">
                <button class="tbtn"><i class="fa fa-download"></i> Baixar <i class="fa fa-caret-down"></i></button>
                <div class="dd-menu dd-dir">
                  <button data-baixar-dados="json">Dados (.json)</button>
                  <button data-baixar-dados="csv">Planilha (.csv)</button>
                </div>
              </div>
            </div>
            <div id="dadosCorpo"><div class="vazio">Os dados aparecem aqui quando o modo <b>Dados</b> ou <b>Texto + dados</b> é usado — ou clique em <b>Extrair dados</b>.</div></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="vendor/pdfjs/pdf.min.js"></script>
<script src="vendor/pako/pako_inflate.min.js"></script>
<script src="vendor/utif/UTIF.js"></script>
<script>
window.IRIS = <?php echo json_encode([
    'csrf' => $CSRF, 'versao' => IRIS_VERSAO, 'temChave' => $temChave, 'isAdmin' => $isAdmin, 'cfg' => $pub,
    'modelos' => $modelos, 'tipos' => $tipos, 'abrir' => $abrir ?: null, 'usuario' => iris_usuario(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="assets/iris.js?v=<?php echo $v; ?>"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
