<?php
session_start();
require_once 'conexao_bd.php';

// Obter ID do manual
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$preview = isset($_GET['preview']) ? (int)$_GET['preview'] : 0;

if ($id <= 0) {
    header('Location: manual-list.php');
    exit;
}

// Função para sanitizar HTML permitindo tags específicas
function sanitize_html($text) {
    $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><table><thead><tbody><tr><th><td><div><span><a><img>';
    $text = strip_tags((string)$text, $allowed_tags);

    // Remove atributos potencialmente perigosos (on*, javascript:)
    $text = preg_replace('/(<[^>]+)(?:\s|\t|\n)+(on\w+)(\s*=\s*["\'][^"\']*["\'])/i', '$1', $text);
    $text = preg_replace('/(<[^>]+)(?:\s|\t|\n)+(href|src|style)(\s*=\s*["\']\s*javascript:[^"\']*["\'])/i', '$1', $text);
    return $text;
}

// Função para verificar e formatar strings base64 de imagens
function formatBase64Image($base64String) {
    if (empty($base64String)) return '';
    if (strpos($base64String, 'data:image/') === 0) return $base64String;

    if (base64_encode(base64_decode($base64String, true)) === $base64String) {
        $imageType = 'jpeg';
        $decodedData = base64_decode($base64String);
        if (strlen($decodedData) > 4) {
            if (strncmp($decodedData, "\xFF\xD8", 2) === 0)      $imageType = 'jpeg';
            elseif (strncmp($decodedData, "\x89PNG", 4) === 0)    $imageType = 'png';
            elseif (strncmp($decodedData, "GIF", 3) === 0)        $imageType = 'gif';
            elseif (strncmp($decodedData, "BM", 2) === 0)         $imageType = 'bmp';
            elseif (strncmp($decodedData, "RIFF", 4) === 0)       $imageType = 'webp';
        }
        return 'data:image/' . $imageType . ';base64,' . $base64String;
    }
    return $base64String;
}

try {
    // Buscar dados do manual
    $stmt = $conexao->prepare("
        SELECT m.*, c.nome as categoria_nome, u.nome as autor_nome
        FROM manuais m
        LEFT JOIN categorias c ON m.categoria_id = c.id
        LEFT JOIN usuarios u ON m.autor_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $manual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$manual) {
        header('Location: manual-list.php');
        exit;
    }

    // Buscar passos
    $stmt = $conexao->prepare("SELECT * FROM passos WHERE manual_id = ? ORDER BY numero ASC");
    $stmt->execute([$id]);
    $passos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Incrementar visualizações e registrar log (exceto preview)
    if (!$preview) {
        $stmt = $conexao->prepare("UPDATE manuais SET visualizacoes = visualizacoes + 1 WHERE id = ?");
        $stmt->execute([$id]);

        if (isset($_SESSION['usuario_id'])) {
            $stmt = $conexao->prepare("
                INSERT INTO logs_acesso (usuario_id, manual_id, acao, ip, user_agent)
                VALUES (?, ?, 'visualizar', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['usuario_id'],
                $id,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar manual: " . $e->getMessage());
    $erro = "Ocorreu um erro ao carregar o manual.";
}

// Helpers de exibição
function h($string) { return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8'); }
function formatarData($data) { if (!$data) return ''; $t = strtotime($data); return date('d/m/Y', $t); }
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($manual['titulo']) ?> - Sistema de Manuais</title>

    <!-- Bootstrap 5 / FA -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        /* =========================================================
         *  Tema Light / Dark — UI moderna (consistente com as demais páginas)
         * =======================================================*/
        :root{
            --bg:#f6f7fb; --surface:#ffffff; --surface-2:#f1f3f9;
            --text:#0f172a; --muted:#6b7280; --border:#e5e7eb;
            --primary:#3b82f6; --primary-600:#2563eb; --accent:#22c55e;
            --warning:#f59e0b; --danger:#ef4444; --info:#06b6d4;
            --shadow:0 10px 30px rgba(2,6,23,.07); --radius:14px;
            --topbar-h:64px;
            --step-number-size:44px;
        }
        html[data-theme="dark"]{
            --bg:#0b1220; --surface:#0f172a; --surface-2:#0b1220;
            --text:#e5e7eb; --muted:#9aa4b2; --border:#1f2937;
            --primary:#60a5fa; --primary-600:#3b82f6; --accent:#34d399;
            --warning:#fbbf24; --danger:#f87171; --info:#22d3ee;
            --shadow:0 12px 40px rgba(2,6,23,.45);
        }
        *{box-sizing:border-box}
        body{
            background: radial-gradient(1200px 800px at 10% -10%, rgba(59,130,246,.15), transparent 40%),
                        radial-gradient(1000px 700px at 110% 10%, rgba(34,211,238,.12), transparent 42%),
                        var(--bg);
            color:var(--text); min-height:100vh; padding-bottom:80px;
        }

        /* Topbar */
        .topbar{
            position:fixed; inset:0 0 auto 0; height:var(--topbar-h);
            background:linear-gradient(180deg, rgba(255,255,255,.6), rgba(255,255,255,.2));
            backdrop-filter:blur(10px); border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:12px; padding:0 16px; z-index:101;
        }
        html[data-theme="dark"] .topbar{ background:linear-gradient(180deg, rgba(15,23,42,.7), rgba(15,23,42,.35)); }
        .btn-ghost{ display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border);
            background:var(--surface); padding:8px 12px; border-radius:999px; color:var(--text); }
        .brand h4{ margin:0; letter-spacing:.2px; }
        .theme-btn{ width:42px;height:42px;border-radius:50%;border:1px solid var(--border);background:var(--surface);display:grid;place-items:center;color:var(--text); }

        /* Main */
        .main-content{ padding:calc(var(--topbar-h) + 20px) 16px 16px; }
        @media(min-width:992px){ .main-content{ padding:calc(var(--topbar-h) + 24px) 32px 24px; } }
        .panel{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); }

        /* Manual header */
        .manual-header{ padding:24px; margin-bottom:24px; }
        .manual-title{ font-size:1.75rem; font-weight:800; margin:0 0 10px 0; letter-spacing:.2px; }
        .manual-cover{ width:100%; height:320px; border-radius:12px; overflow:hidden; background:var(--surface-2); border:1px solid var(--border); }
        .manual-cover img{ width:100%; height:100%; object-fit:cover; }
        .manual-meta{ color:var(--muted); font-size:.95rem; display:flex; flex-wrap:wrap; gap:12px; }
        .badge-category{ background:rgba(59,130,246,.12); color:var(--primary-600); border:1px solid var(--border); padding:6px 10px; border-radius:999px; }

        /* Índice (TOC) */
        .toc{ position:sticky; top:calc(var(--topbar-h) + 20px); padding:16px; }
        .toc .toc-title{ font-weight:700; padding-bottom:8px; border-bottom:1px solid var(--border); margin-bottom:12px; }
        .toc-list{ list-style:none; margin:0; padding:0; }
        .toc-link{ display:flex; align-items:center; gap:10px; color:var(--muted); text-decoration:none; padding:8px 10px; border-radius:10px; transition:.15s; }
        .toc-link:hover{ background:var(--surface-2); color:var(--text); }
        .toc-link.active{ background:linear-gradient(135deg, rgba(59,130,246,.12), rgba(34,197,94,.12)); color:var(--text); border:1px solid var(--border); }
        .toc-number{ width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:var(--surface-2); color:var(--muted); font-weight:700; }

        /* Passos */
        .steps-container{ position:relative; }
        .step-timeline{ position:absolute; left:calc(var(--step-number-size)/2); top:0; bottom:0; width:2px; background:var(--border); z-index:0; display:none; }
        @media(min-width:768px){ .step-timeline{ display:block; } }
        .step-card{ position:relative; z-index:1; overflow:hidden; margin-bottom:20px; border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); background:var(--surface); }
        .step-header{ display:flex; align-items:center; gap:14px; padding:16px 18px; border-bottom:1px solid var(--border); background:linear-gradient(180deg, var(--surface-2), var(--surface)); }
        .step-number{ min-width:var(--step-number-size); height:var(--step-number-size); border-radius:50%; display:grid; place-items:center; font-weight:800; color:#fff;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%); }
        .step-title{ margin:0; font-size:1.15rem; }
        .step-content{ padding:18px; }
        .step-text{ line-height:1.65; }
        .step-image-container{ margin-top:16px; text-align:center; }
        .step-image{ max-width:100%; border-radius:10px; border:1px solid var(--border); box-shadow:var(--shadow); }
        .step-image-caption{ color:var(--muted); font-style:italic; margin-top:8px; }

        /* Barra de ações fixa */
        .action-bar{ position:fixed; left:0; right:0; bottom:0; background:linear-gradient(0deg, rgba(0,0,0,.06), rgba(0,0,0,0));
            z-index:102; }
        .action-inner{ background:var(--surface); border-top:1px solid var(--border); padding:12px 16px; }

        /* ====== Impressão (1 passo por página) ====== */
        @media print{
            @page { size: A4; margin: 12mm; }
            .topbar, .action-bar, .toc{ display:none !important; }
            body{ background:#fff !important; padding:0 !important; }
            .panel, .step-card{ box-shadow:none !important; border:1px solid #ddd !important; }
            .main-content{ padding:0 !important; }
            .step-timeline{ display:none !important; }

            /* Mostra título e mantém cabeçalho do manual completo na 1ª página */
            .manual-title{ display:block !important; page-break-inside: avoid !important; }
            .manual-header{
                page-break-after: always !important;
                break-after: page !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .manual-cover, .manual-meta, .manual-description{
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            /* Evita quebras dentro do passo e força cada passo em uma página */
            .step-card{
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                -webkit-column-break-inside: avoid !important;
                -moz-page-break-inside: avoid;
                page-break-after: always !important;
                break-after: page !important;
            }
            /* Último passo não gera página em branco */
            .step-card:last-of-type{
                page-break-after: auto !important;
                break-after: auto !important;
            }
            /* Mantém cabeçalho + conteúdo juntos */
            .step-header, .step-content, .step-image-container, .step-text{
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                -webkit-column-break-inside: avoid !important;
            }
            /* Melhorar contraste de cores no print */
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <!-- Topbar -->
    <div class="topbar">
        <a href="manual-list.php" class="btn btn-ghost" title="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="brand">
            <h4 class="m-0"><?= h($manual['titulo']) ?></h4>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if (!$preview && isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'admin'): ?>
                <a href="manual-creator.php?id=<?= (int)$id ?>" class="btn btn-ghost">
                    <i class="fa-solid fa-pen-to-square me-1"></i>Editar
                </a>
            <?php endif; ?>
            <button id="printManual" class="btn btn-ghost" title="Imprimir">
                <i class="fa-solid fa-print"></i>
            </button>
            <button class="theme-btn" id="themeToggle" title="Alternar tema">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <!-- Depurador para imagens base64 -->
    <?php if (isset($_GET['debug']) && isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin'): ?>
        <div class="container mt-5 pt-4">
            <div class="alert alert-info panel">
                <h5 class="mb-3">Informações de Depuração de Imagens Base64</h5>
                <p class="mb-2">Imagem de capa:
                <?php
                    if (!empty($manual['imagem_capa'])) {
                        $length = strlen($manual['imagem_capa']);
                        $has_prefix = strpos($manual['imagem_capa'], 'data:image/') === 0;
                        echo " Comprimento: $length, Tem prefixo: " . ($has_prefix ? 'Sim' : 'Não');
                        echo "<br>Início: " . h(substr($manual['imagem_capa'], 0, 50)) . "...";
                    } else {
                        echo " Não definida";
                    }
                ?>
                </p>
                <hr>
                <h6>Imagens dos passos:</h6>
                <ul class="mb-0">
                <?php foreach ($passos as $passo):
                    if (!empty($passo['imagem'])):
                        $length = strlen($passo['imagem']);
                        $has_prefix = strpos($passo['imagem'], 'data:image/') === 0;
                ?>
                    <li>Passo <?= (int)$passo['numero'] ?>: Comprimento: <?= (int)$length ?>, Tem prefixo: <?= $has_prefix ? 'Sim' : 'Não' ?></li>
                <?php endif; endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Conteúdo -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-3">
                <!-- Coluna principal -->
                <div class="col-lg-9">
                    <div class="panel manual-header">
                        <!-- Título do Manual (visível na tela e garantido na impressão) -->
                        <h1 class="manual-title"><?= h($manual['titulo']) ?></h1>

                        <?php if (!empty($manual['imagem_capa'])): ?>
                            <div class="manual-cover mb-3">
                                <img src="<?= h(formatBase64Image($manual['imagem_capa'])) ?>" alt="<?= h($manual['titulo']) ?>">
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="manual-meta">
                                <?php if (!empty($manual['categoria_nome'])): ?>
                                    <span class="badge-category"><i class="fa-solid fa-tag me-2"></i><?= h($manual['categoria_nome']) ?></span>
                                <?php endif; ?>
                                <span><i class="fa-solid fa-user me-2"></i><?= h($manual['autor_nome'] ?? 'Administrador') ?></span>
                                <span><i class="fa-solid fa-calendar me-2"></i><?= formatarData($manual['data_criacao']) ?></span>
                                <?php if (!empty($manual['versao'])): ?>
                                    <span><i class="fa-solid fa-code-branch me-2"></i>v<?= h($manual['versao']) ?></span>
                                <?php endif; ?>
                                <?php if (!$preview): ?>
                                    <span><i class="fa-solid fa-eye me-2"></i><?= number_format((float)$manual['visualizacoes']) ?> visualizações</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$preview && !empty($manual['arquivo_pdf'])): ?>
                                <a href="<?= h($manual['arquivo_pdf']) ?>" class="btn btn-outline-danger" download>
                                    <i class="fa-solid fa-file-pdf me-1"></i>Baixar PDF
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($manual['descricao'])): ?>
                            <div class="mt-3 manual-description">
                                <h5 class="fw-bold mb-2">Descrição</h5>
                                <div><?= sanitize_html($manual['descricao']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Passos -->
                    <div class="steps-container">
                        <div class="step-timeline"></div>
                        <?php foreach ($passos as $passo): ?>
                            <section class="step-card" id="step-<?= (int)$passo['numero'] ?>">
                                <header class="step-header">
                                    <div class="step-number"><?= (int)$passo['numero'] ?></div>
                                    <h3 class="step-title"><?= h($passo['titulo']) ?></h3>
                                </header>
                                <div class="step-content">
                                    <div class="step-text"><?= sanitize_html($passo['texto']) ?></div>

                                    <?php if (!empty($passo['imagem'])): ?>
                                        <div class="step-image-container">
                                            <img src="<?= h(formatBase64Image($passo['imagem'])) ?>" alt="<?= h($passo['titulo']) ?>" class="step-image">
                                            <?php if (!empty($passo['legenda'])): ?>
                                                <div class="step-image-caption"><?= h($passo['legenda']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Índice -->
                <div class="col-lg-3">
                    <aside class="panel toc">
                        <h4 class="toc-title">Índice</h4>
                        <ul class="toc-list">
                            <?php foreach ($passos as $passo): ?>
                                <li class="toc-item">
                                    <a href="#step-<?= (int)$passo['numero'] ?>" class="toc-link">
                                        <span class="toc-number"><?= (int)$passo['numero'] ?></span>
                                        <span class="toc-text"><?= h($passo['titulo']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de ações -->
    <div class="action-bar">
        <div class="action-inner">
            <div class="container d-flex align-items-center justify-content-between">
                <div>
                    <button id="prevStep" class="btn btn-outline-secondary" disabled>
                        <i class="fa-solid fa-arrow-left me-1"></i>Passo Anterior
                    </button>
                    <button id="nextStep" class="btn btn-outline-primary ms-2">
                        Próximo Passo<i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
                <div>
                    <?php if (!$preview): ?>
                        <button id="rateManual" class="btn btn-outline-warning">
                            <i class="fa-solid fa-star me-1"></i>Avaliar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        (function(){
            // Alternância de tema
            const htmlEl = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon   = document.getElementById('themeIcon');

            function applyTheme(mode){
                htmlEl.setAttribute('data-theme', mode);
                if (mode === 'dark'){ themeIcon.classList.remove('fa-moon'); themeIcon.classList.add('fa-sun'); }
                else { themeIcon.classList.remove('fa-sun'); themeIcon.classList.add('fa-moon'); }
                localStorage.setItem('theme', mode);
            }
            const saved = localStorage.getItem('theme');
            if (saved) applyTheme(saved); else {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                applyTheme(prefersDark ? 'dark' : 'light');
            }
            themeToggle.addEventListener('click', ()=> {
                const current = htmlEl.getAttribute('data-theme');
                applyTheme(current === 'dark' ? 'light' : 'dark');
            });

            // Links externos
            $('a[href^="http"]').attr('target','_blank').attr('rel','noopener noreferrer');

            // Destaque do item do índice (TOC) conforme scroll
            function highlightTocItem(){
                const scrollPosition = $(window).scrollTop();
                const offsetTop = 100;

                $('.step-card').each(function(){
                    const $el = $(this);
                    const top = $el.offset().top - offsetTop;
                    const bottom = top + $el.outerHeight();

                    if (scrollPosition >= top && scrollPosition < bottom){
                        const id = $el.attr('id');
                        $('.toc-link').removeClass('active');
                        $('.toc-link[href="#'+id+'"]').addClass('active');
                        updateNavigationButtons('#'+id);
                        return false;
                    }
                });
            }
            $(window).on('scroll', highlightTocItem);
            highlightTocItem();

            // Rolagem suave TOC
            $('.toc-link').on('click', function(e){
                e.preventDefault();
                const targetId = $(this).attr('href');
                const target = $(targetId);
                if (!target.length) return;
                const targetPosition = target.offset().top - 80;
                $('html, body').animate({ scrollTop: targetPosition }, 600);
            });

            // Navegação próxima/anterior
            function updateNavigationButtons(currentSelector){
                const cards = $('.step-card');
                const idx = cards.index($(currentSelector));
                $('#prevStep').prop('disabled', idx <= 0);
                $('#nextStep').prop('disabled', idx >= cards.length - 1);
            }

            $('#prevStep').on('click', function(){
                const activeHref = $('.toc-link.active').attr('href');
                const prev = $(activeHref).prev('.step-card');
                if (prev.length){
                    $('html, body').animate({ scrollTop: prev.offset().top - 80 }, 600);
                }
            });

            $('#nextStep').on('click', function(){
                const activeHref = $('.toc-link.active').attr('href');
                const next = $(activeHref).next('.step-card');
                if (next.length){
                    $('html, body').animate({ scrollTop: next.offset().top - 80 }, 600);
                }
            });

            // Teclas de atalho
            $(document).on('keydown', function(e){
                if (e.which === 37) $('#prevStep').click();
                else if (e.which === 39) $('#nextStep').click();
            });

            // Impressão + registro
            $('#printManual').on('click', function(){
                window.print();
                fetch('register_download.php?id=<?= (int)$id ?>', { method:'POST' })
                    .catch(err => console.error('Erro ao registrar download:', err));
            });

            // Avaliação
            <?php if (!$preview): ?>
            $('#rateManual').on('click', function(){
                Swal.fire({
                    title: 'Avaliar Manual',
                    html: `
                        <div class="text-center mb-3">
                            <div class="rating-stars">
                                <i class="far fa-star fa-2x star" data-rating="1"></i>
                                <i class="far fa-star fa-2x star" data-rating="2"></i>
                                <i class="far fa-star fa-2x star" data-rating="3"></i>
                                <i class="far fa-star fa-2x star" data-rating="4"></i>
                                <i class="far fa-star fa-2x star" data-rating="5"></i>
                            </div>
                            <div class="rating-text mt-2">Selecione uma nota</div>
                        </div>
                        <textarea id="rating-comment" class="form-control" placeholder="Comentário (opcional)" rows="3"></textarea>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Enviar Avaliação',
                    cancelButtonText: 'Cancelar',
                    didOpen: () => {
                        const stars = Swal.getContainer().querySelectorAll('.star');
                        let selected = 0;
                        stars.forEach((star, i) => {
                            star.style.cursor = 'pointer';
                            star.style.margin = '0 5px';
                            star.style.color = '#ffc107';
                            star.addEventListener('mouseover', function(){
                                const r = parseInt(this.dataset.rating);
                                stars.forEach((s, idx) => {
                                    if (idx < r) { s.classList.remove('far'); s.classList.add('fas'); }
                                    else { s.classList.remove('fas'); s.classList.add('far'); }
                                });
                                const txt = Swal.getContainer().querySelector('.rating-text');
                                txt.textContent = `${r} ${r===1?'estrela':'estrelas'}`;
                            });
                            star.addEventListener('click', function(){ selected = parseInt(this.dataset.rating); });
                            star.addEventListener('mouseleave', function(){
                                stars.forEach((s, idx) => {
                                    if (idx < selected) { s.classList.remove('far'); s.classList.add('fas'); }
                                    else { s.classList.remove('fas'); s.classList.add('far'); }
                                });
                                const txt = Swal.getContainer().querySelector('.rating-text');
                                txt.textContent = selected ? `${selected} ${selected===1?'estrela':'estrelas'}` : 'Selecione uma nota';
                            });
                        });
                    },
                    preConfirm: () => {
                        const selected = Swal.getContainer().querySelectorAll('.rating-stars .fas').length;
                        const comment = Swal.getContainer().querySelector('#rating-comment').value;
                        if (!selected) {
                            Swal.showValidationMessage('Por favor, selecione uma nota');
                            return false;
                        }
                        return fetch('save_rating.php', {
                            method: 'POST',
                            headers: { 'Content-Type':'application/json' },
                            body: JSON.stringify({
                                manual_id: <?= (int)$id ?>,
                                rating: selected,
                                comentario: comment
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao salvar avaliação');
                            return data;
                        })
                        .catch(err => { Swal.showValidationMessage(`Falha: ${err.message}`); });
                    }
                }).then(res => {
                    if (res.isConfirmed && res.value && res.value.success) {
                        Swal.fire('Obrigado!', 'Sua avaliação foi salva com sucesso.', 'success');
                    }
                });
            });
            <?php endif; ?>
        })();
    </script>
</body>
</html>
