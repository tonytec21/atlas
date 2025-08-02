<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vídeos Tutoriais</title>

    <!-- Styles -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <style>
        /* =========================================================
         *  Tema Light/Dark para os componentes desta página
         *  Compatível com body.light-mode / body.dark-mode
         *  e também com html[data-theme="dark"]
         * =======================================================*/

        /* ---------- valores padrão (herdados se não houver classe no body) ---------- */
        :root{
            --bg:            #ffffff;
            --surface:       #f8fafc;
            --card-bg:       #ffffff;
            --card-border:   #e5e7eb;
            --text:          #0f172a;
            --title:         #0b1324;
            --muted:         #6b7280;
            --chip-bg:       #f1f5f9;
            --chip-fg:       #0f172a;
            --accent:        #2563eb;
            --accent-hover:  #1d4ed8;
            --shadow:        0 1px 2px rgba(0,0,0,.08);

            /* cabeçalho do card de vídeo */
            --video-title-from:#1f2937;
            --video-title-to:  #111827;

            /* campos formulário */
            --input-bg:       #ffffff;
            --input-br:       #d1d5db;
            --input-fg:       #0f172a;
            --input-ph:       #6b7280;

            /* separadores */
            --divider:        #e5e7eb;
        }

        /* ---------- LIGHT MODE ---------- */
        body.light-mode{
            --bg:            #ffffff;
            --surface:       #f8fafc;
            --card-bg:       #ffffff;
            --card-border:   #e5e7eb;
            --text:          #0f172a;
            --title:         #0b1324;
            --muted:         #6b7280;
            --chip-bg:       #eef2f7;
            --chip-fg:       #0f172a;
            --accent:        #2563eb;
            --accent-hover:  #1d4ed8;
            --shadow:        0 1px 2px rgba(0,0,0,.06);
            --video-title-from:#1f2937;
            --video-title-to:  #111827;
            --input-bg:       #ffffff;
            --input-br:       #cbd5e1;
            --input-fg:       #0f172a;
            --input-ph:       #6b7280;
            --divider:        #e5e7eb;
        }

        /* ---------- DARK MODE ---------- */
        body.dark-mode,
        html[data-theme="dark"] body{
            --bg:            #0b1220;
            --surface:       #0f172a; /* barra de busca / áreas pegajosas */
            --card-bg:       #0b1220;
            --card-border:   #1f2a3a;
            --text:          #e5e7eb;
            --title:         #f1f5f9;
            --muted:         #9ca3af;
            --chip-bg:       #111827;
            --chip-fg:       #e5e7eb;
            --accent:        #60a5fa;
            --accent-hover:  #93c5fd;
            --shadow:        0 1px 3px rgba(0,0,0,.35);

            --video-title-from:#0f172a;
            --video-title-to:  #0b1220;

            --input-bg:       #0f172a;
            --input-br:       #223146;
            --input-fg:       #e5e7eb;
            --input-ph:       #9aa7b8;

            --divider:        #1f2a3a;
        }

        /* ---------- layout / tipografia ---------- */
        .page-header{
            display:flex; gap:.75rem; align-items:center; justify-content:space-between; flex-wrap:wrap;
            color:var(--title);
        }
        .page-header h3{margin:0; font-weight:600; color:var(--title);}

        .tools{ display:flex; gap:.5rem; flex-wrap:wrap; }

        /* ---------- barra de busca e ações ---------- */
        .search-wrap{
            position:sticky; top:0; z-index:3;
            background:var(--surface);
            padding:10px 0 6px;
            border-bottom:1px solid var(--divider);
        }
        .input-icon{ position:relative; }
        .input-icon .mdi{
            position:absolute; left:.75rem; top:50%; transform:translateY(-50%);
            font-size:20px; color:var(--muted);
        }
        .input-icon input{
            padding-left:2.25rem;
            background:var(--input-bg);
            color:var(--input-fg);
            border:1px solid var(--input-br);
        }
        .input-icon input::placeholder{ color:var(--input-ph); }
        .input-icon input:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 .2rem rgba(37,99,235,.15);
        }

        /* ---------- categorias ---------- */
        .category-section{
            margin-bottom:1.25rem;
            border:1px solid var(--card-border);
            border-radius:.75rem;
            overflow:hidden;
            background:var(--card-bg);
            box-shadow:var(--shadow);
        }
        .category-title{
            margin:0; padding:.9rem 1rem;
            display:flex; align-items:center; justify-content:space-between;
            cursor:pointer; user-select:none;
            font-weight:600; color:var(--title);
            background:linear-gradient(180deg, var(--card-bg), color-mix(in oklab, var(--card-bg) 85%, #000 15%));
            border-bottom:1px solid var(--card-border);
        }
        .category-title .fa{ color:var(--muted); }
        .category-meta{
            display:flex; align-items:center; gap:.65rem;
            cursor:pointer; outline: none;
            color:var(--muted);
        }
        .category-meta:focus-visible{
            box-shadow: 0 0 0 2px color-mix(in oklab, var(--accent) 35%, transparent 65%);
            border-radius:.5rem;
        }
        .badge-soft{
            background:var(--chip-bg); color:var(--chip-fg);
            border-radius:999px; padding:.2rem .6rem; font-size:.8rem;
        }
        .category-body{ padding: 1rem; }

        /* ---------- cards de vídeo ---------- */
        .video-card{height:100%}
        .video-card .card{
            height:100%; border:1px solid var(--card-border);
            border-radius:.75rem; overflow:hidden;
            background:var(--card-bg);
            display:flex; flex-direction:column;
            box-shadow:var(--shadow);
        }
        .video-card-title{
            background:linear-gradient(180deg, var(--video-title-from), var(--video-title-to));
            color:#fff; text-align:center;
            min-height:56px; display:flex; align-items:center; justify-content:center;
            padding:.65rem .75rem; font-size:16px; font-weight:600;
        }
        .video-wrapper{
            position:relative; width:100%;
            aspect-ratio:16/9;
            background:#000;
        }
        @supports not (aspect-ratio: 16/9) {
            .video-wrapper{padding-top:56.25%;}
            .video-wrapper > *{position:absolute; inset:0;}
        }
        .video-placeholder{
            position:absolute; inset:0; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
        }
        .video-placeholder::after{
            content:"";
            position:absolute; inset:0;
            background:linear-gradient(to bottom, rgba(0,0,0,.05), rgba(0,0,0,.35));
        }
        .video-placeholder i{
            position:relative; z-index:1; font-size:64px; color:#fff; opacity:.95;
            transition: transform .15s ease;
        }
        .video-placeholder:hover i{ transform:scale(1.06); }
        .video-iframe{
            width:100%; height:100%; border:0; display:none; border-radius:0;
        }
        .video-description{
            font-size:14px; line-height:1.45; text-align:justify;
            padding:.85rem 1rem 1.1rem; margin:0;
            color:var(--text);
            background:color-mix(in oklab, var(--card-bg) 92%, #000 8%);
            border-top:1px solid var(--card-border);
        }

        /* ---------- estados auxiliares ---------- */
        .no-results{
            display:none; text-align:center; color:var(--muted);
            padding:1.25rem .5rem;
        }

        /* ---------- responsividade ---------- */
        @media (max-width: 575.98px){
            .video-card-title{ font-size:15px; }
            .tools{ width:100%; justify-content:flex-start; }
        }
    </style>
</head>

<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container py-3">

        <div class="page-header mb-3">
            <h3 class="mb-2">Vídeos Tutoriais</h3>
            <div class="tools">
                <a href="manual-list.php" target="_blank" class="btn btn-primary d-flex align-items-center">
                    <i class="mdi mdi-file-document-edit-outline me-2"></i>
                    Criar / Editar Manuais & POPs
                </a>
            </div>
        </div>

        <div class="search-wrap">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="input-icon">
                        <i class="mdi mdi-magnify"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Pesquisar por título ou descrição…">
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-6 d-flex justify-content-md-end gap-2">
                    <button id="btnExpandAll" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-unfold-more-horizontal me-1"></i>Expandir tudo
                    </button>
                    <button id="btnCollapseAll" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-unfold-less-horizontal me-1"></i>Recolher tudo
                    </button>
                </div>
            </div>
        </div>

        <div id="noResults" class="no-results">
            <i class="mdi mdi-information-outline me-1"></i> Nenhum vídeo encontrado para a pesquisa.
        </div>

        <div id="categoriesContainer">
        <?php
            $conn = getDatabaseConnection();

            // Lista de categorias ativas
            $stmt = $conn->query("SELECT DISTINCT categoria FROM manuais WHERE status = 'ativo' ORDER BY categoria ASC");
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($categorias as $categoria) {
                $categoriaNome = $categoria['categoria'];
                $categoriaId = 'category-' . md5($categoriaNome);

                // Vídeos da categoria
                $stmtVideos = $conn->prepare("SELECT * FROM manuais WHERE categoria = :categoria AND status = 'ativo' ORDER BY ordem ASC");
                $stmtVideos->bindParam(':categoria', $categoriaNome);
                $stmtVideos->execute();
                $videos = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);
                $qtd = count($videos);
        ?>
            <section class="category-section" data-category="<?php echo htmlspecialchars($categoriaNome); ?>">
                <h5 class="category-title" role="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $categoriaId; ?>" aria-expanded="true" aria-controls="<?php echo $categoriaId; ?>">
                    <span class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-playlist-play"></i>
                        <?php echo htmlspecialchars($categoriaNome); ?>
                    </span>
                    <!-- Torna o meta focável e clicável para toggle -->
                    <span class="category-meta" tabindex="0" aria-label="Expandir/Recolher categoria">
                        <span class="badge-soft" title="Total de vídeos"><?php echo $qtd; ?> vídeo(s)</span>
                        <i class="fa fa-chevron-down"></i>
                    </span>
                </h5>

                <div id="<?php echo $categoriaId; ?>" class="collapse show">
                    <div class="category-body">
                        <div class="row g-3">
                            <?php foreach ($videos as $video): ?>
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="video-card">
                                        <div class="card">
                                            <div class="video-card-title">
                                                <?php echo htmlspecialchars($video['titulo']); ?>
                                            </div>

                                            <div class="video-wrapper">
                                                <div class="video-placeholder" tabindex="0" role="button" aria-label="Reproduzir vídeo"
                                                     data-src="<?php echo htmlspecialchars($video['caminho_video']); ?>">
                                                    <i class="fa fa-play-circle"></i>
                                                </div>
                                                <iframe class="video-iframe"
                                                        title="<?php echo htmlspecialchars($video['titulo']); ?>"
                                                        allow="autoplay; encrypted-media"
                                                        allowfullscreen></iframe>
                                            </div>

                                            <p class="video-description">
                                                <?php echo htmlspecialchars($video['descricao']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div> <!-- /row -->
                    </div> <!-- /category-body -->
                </div> <!-- /collapse -->
            </section>
        <?php } ?>
        </div><!-- /categoriesContainer -->

    </div>
</div>

<!-- Scripts -->
<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>

<script>
(function(){
    const $doc = $(document);
    const $search = $('#searchInput');
    const $noResults = $('#noResults');

    // --------- Helpers compatíveis com Bootstrap 4 e 5 ---------
    function bsShow(el){
        if (window.bootstrap && window.bootstrap.Collapse) {
            try { new bootstrap.Collapse(el, { toggle:false }).show(); return; } catch(e){}
        }
        $(el).collapse('show'); // BS4 fallback
    }
    function bsHide(el){
        if (window.bootstrap && window.bootstrap.Collapse) {
            try { new bootstrap.Collapse(el, { toggle:false }).hide(); return; } catch(e){}
        }
        $(el).collapse('hide'); // BS4 fallback
    }
    function bsToggle(el){
        const isShown = $(el).classList ? el.classList.contains('show') : $(el).hasClass('show');
        isShown ? bsHide(el) : bsShow(el);
    }

    // Debounce util
    function debounce(fn, delay=180){
        let t=null; return function(){
            const ctx=this, args=arguments;
            clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args),delay);
        }
    }

    // Atualiza ícone de seta nas categorias
    $doc.on('show.bs.collapse', '.collapse', function(){
        $(this).closest('.category-section').find('.category-title .fa')
            .removeClass('fa-chevron-down').addClass('fa-chevron-up');
    });
    $doc.on('hide.bs.collapse', '.collapse', function(){
        $(this).closest('.category-section').find('.category-title .fa')
            .removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });

    // Clique/tecla no "category-meta" deve alternar a seção (sem duplo toggle)
    $doc.on('click', '.category-meta', function(e){
        e.preventDefault();
        e.stopPropagation(); // evita disparar o data-bs-toggle do h5
        toggleCategoryFromMeta($(this));
    });
    $doc.on('keydown', '.category-meta', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            e.stopPropagation();
            toggleCategoryFromMeta($(this));
        }
    });
    function toggleCategoryFromMeta($meta){
        const $title = $meta.closest('.category-title');
        const targetSel = $title.attr('data-bs-target');
        if (!targetSel) return;
        const el = document.querySelector(targetSel);
        if (!el) return;
        bsToggle(el);
    }

    // Botões Expandir/Recolher tudo
    $('#btnExpandAll').on('click', function(){
        $('.category-section .collapse').each(function(){ bsShow(this); });
    });
    $('#btnCollapseAll').on('click', function(){
        $('.category-section .collapse').each(function(){ bsHide(this); });
    });

    // Pesquisa (título + descrição) com feedback quando vazio
    const doFilter = debounce(function(){
        const value = ($search.val() || '').toLowerCase().trim();
        let anyFound = false;

        $('.category-section').each(function(){
            const $section = $(this);
            let foundInSection = false;

            $section.find('.col-12.col-md-6.col-lg-4').each(function(){
                const $card = $(this);
                const title = $card.find('.video-card-title').text().toLowerCase();
                const desc  = $card.find('.video-description').text().toLowerCase();
                const match = !value || title.includes(value) || desc.includes(value);

                $card.toggle(match);
                if (match) foundInSection = true;
            });

            // Se encontrou itens, mostra seção e garante expandida; senão, oculta
            if (foundInSection){
                $section.show();
                const $collapse = $section.find('.collapse');
                bsShow($collapse[0]);
                anyFound = true;
            }else{
                $section.hide();
            }
        });

        $noResults.toggle(!anyFound);
    }, 120);

    $search.on('input', doFilter);

    // Reproduzir vídeo ao clicar no placeholder (ou Enter/Espaço focado)
    function playVideo($ph){
        const $iframe = $ph.closest('.video-wrapper').find('.video-iframe');
        const src = $ph.data('src');

        if (!src) return;

        // Pausa outros iframes abertos
        $('.video-iframe').each(function(){
            if (this !== $iframe[0]) {
                $(this).attr('src','').hide();
                $(this).closest('.video-wrapper').find('.video-placeholder').show();
            }
        });

        // Define src e exibe
        $iframe.attr('src', src).show();
        $ph.hide().attr('aria-hidden','true');
    }

    $('.video-placeholder').on('click', function(){ playVideo($(this)); });
    $('.video-placeholder').on('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            playVideo($(this));
        }
    });

    // Pausar vídeo ao recolher a categoria
    $doc.on('hide.bs.collapse', '.category-section .collapse', function(){
        $(this).find('.video-iframe').each(function(){
            $(this).attr('src','').hide();
        });
        $(this).find('.video-placeholder').show().removeAttr('aria-hidden');
    });

})();
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
