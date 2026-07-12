<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
notas_ensure_schema();

$username = $_SESSION['username'];
$CSRF     = notas_csrf();
$org      = notas_org_get($username);
$cats     = $org['cats'];
$minhas        = notas_listar_proprias($username);
$compartilhadas = notas_listar_compartilhadas_comigo($username);
$paleta = notas_paleta();

function nh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function render_postit($n, $modo) {
    $rot = (crc32($n['id']) % 5) - 2;
    $data = date('d/m/Y', $n['updated']);
    $canEdit = ($modo === 'own') || !empty($n['can_edit']);
    $cat = $n['cat'] ?? '';
    $html = $n['content_html'] ?? '';
    $text = $n['content_text'] ?? '';
    ?>
    <article class="postit" style="--cor: <?php echo nh($n['color']); ?>; --rot: <?php echo $rot; ?>deg;"
             data-id="<?php echo nh($n['id']); ?>" data-owner="<?php echo nh($n['owner']); ?>"
             data-color="<?php echo nh($n['color']); ?>" data-mode="<?php echo $modo; ?>"
             data-canedit="<?php echo $canEdit ? '1' : '0'; ?>" data-cat="<?php echo nh($cat); ?>"
             data-title="<?php echo nh($n['title']); ?>" data-content="<?php echo nh($html); ?>"
             data-text="<?php echo nh($text); ?>">
        <span class="tape" aria-hidden="true"></span>
        <div class="postit-in">
            <?php if ($modo === 'shared'): ?>
                <div class="pin-shared"><i class="fa-solid fa-user-group"></i> <?php echo nh(notas_nome_usuario($n['owner'])); ?></div>
            <?php elseif ($cat !== ''): ?>
                <div class="pin-cat"><i class="fa-solid fa-tag"></i> <?php echo nh($cat); ?></div>
            <?php endif; ?>
            <h3 class="postit-title"><?php echo nh($n['title'] !== '' ? $n['title'] : 'Sem título'); ?></h3>
            <div class="postit-body"><?php echo $html !== '' ? $html : '<span class="muted">(sem conteúdo)</span>'; ?></div>
            <div class="postit-foot">
                <span class="postit-date"><i class="fa-regular fa-clock"></i> <?php echo $data; ?></span>
                <div class="postit-actions">
                    <button class="pa js-view" title="Abrir"><i class="fa-regular fa-eye"></i></button>
                    <?php if ($modo === 'own'): ?>
                        <button class="pa js-color" title="Cor"><i class="fa-solid fa-palette"></i></button>
                        <button class="pa js-share" title="Compartilhar"><i class="fa-solid fa-share-nodes"></i></button>
                    <?php endif; ?>
                    <button class="pa js-del" title="<?php echo $modo === 'own' ? 'Excluir' : 'Remover da minha lista'; ?>"><i class="fa-regular fa-trash-can"></i></button>
                </div>
            </div>
        </div>
        <span class="dogear" aria-hidden="true"></span>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas · Anotações</title>
<link rel="icon" href="../style/img/favicon.png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Caveat:wght@600;700&family=Kalam:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{ --nt-primary:#4f46e5; --nt-primary2:#7c3aed; --nt-bg:#f1f5f9; --nt-text:#0f172a; --nt-muted:#64748b; --nt-card:#ffffff; --nt-border:#e5e9f0; }
body.dark-mode{ --nt-bg:#0f1216; --nt-text:#e5e7eb; --nt-muted:#9aa4b2; --nt-card:#1c2126; --nt-border:rgba(255,255,255,.08); }
#main .container{ max-width:1320px; padding-bottom:120px; }
.page-hero{ background:var(--nt-card); border:1px solid var(--nt-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.page-hero .title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.page-hero .title-icon{ width:56px; height:56px; border-radius:16px; flex:0 0 auto; background:linear-gradient(135deg,var(--nt-primary),var(--nt-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 10px 24px rgba(79,70,229,.34); }
.page-hero h1{ font-size:1.5rem; font-weight:800; margin:0; color:var(--nt-text); letter-spacing:-.02em; }
.page-hero .subtitle{ color:var(--nt-muted); font-size:.92rem; margin-top:2px; }
.hero-actions{ margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; }
.btn-pill{ border-radius:999px; font-weight:600; padding:10px 18px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.18s; }
.btn-primary-g{ background:linear-gradient(135deg,var(--nt-primary),var(--nt-primary2)); color:#fff; box-shadow:0 10px 24px rgba(79,70,229,.32); }
.btn-primary-g:hover{ transform:translateY(-2px); box-shadow:0 14px 30px rgba(79,70,229,.42); color:#fff; }
.btn-soft{ background:var(--nt-bg); color:var(--nt-text); border:1px solid var(--nt-border); }
.btn-soft:hover{ background:var(--nt-border); }
.nt-controls{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:14px; }
.nt-tabs{ display:inline-flex; background:var(--nt-card); border:1px solid var(--nt-border); border-radius:999px; padding:4px; box-shadow:0 6px 18px rgba(15,23,42,.05); }
.nt-tab{ border:0; background:transparent; color:var(--nt-muted); font-weight:700; font-size:.9rem; padding:9px 18px; border-radius:999px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:.16s; }
.nt-tab .cnt{ background:var(--nt-bg); color:var(--nt-muted); border-radius:999px; font-size:.74rem; padding:1px 8px; font-weight:800; }
.nt-tab.active{ background:linear-gradient(135deg,var(--nt-primary),var(--nt-primary2)); color:#fff; box-shadow:0 8px 18px rgba(79,70,229,.3); }
.nt-tab.active .cnt{ background:rgba(255,255,255,.25); color:#fff; }
.nt-search{ margin-left:auto; display:flex; align-items:center; gap:8px; background:var(--nt-card); border:1px solid var(--nt-border); border-radius:999px; padding:8px 16px; min-width:230px; }
.nt-search i{ color:var(--nt-muted); } .nt-search input{ border:0; outline:0; background:transparent; color:var(--nt-text); width:100%; font-size:.9rem; }
.nt-cats{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
.nt-cat{ position:relative; display:inline-flex; align-items:center; gap:7px; background:var(--nt-card); border:1px solid var(--nt-border); color:var(--nt-text); font-weight:600; font-size:.85rem; padding:7px 14px; border-radius:999px; cursor:pointer; transition:.15s; user-select:none; }
.nt-cat:hover{ border-color:var(--nt-primary); }
.nt-cat.active{ background:linear-gradient(135deg,var(--nt-primary),var(--nt-primary2)); color:#fff; border-color:transparent; box-shadow:0 6px 14px rgba(79,70,229,.28); }
.nt-cat.drop-hover{ outline:2px dashed var(--nt-primary); outline-offset:2px; transform:scale(1.05); }
.nt-cat .cat-tools{ display:none; gap:2px; margin-left:2px; }
.nt-cat:hover .cat-tools{ display:inline-flex; }
.nt-cat .ct{ width:18px; height:18px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; background:rgba(0,0,0,.06); }
.nt-cat.active .ct{ background:rgba(255,255,255,.25); }
.nt-cat-new{ border-style:dashed; color:var(--nt-muted); }
.board{ display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:24px; align-items:start; min-height:60px; }
.postit{ position:relative; }
.postit.sortable-ghost{ opacity:.35; }
.postit.sortable-chosen .postit-in{ transform:rotate(0) scale(1.03); }
.postit-in{ position:relative; background:linear-gradient(180deg, rgba(255,255,255,.35), rgba(0,0,0,.02)), var(--cor); border-radius:3px 3px 5px 5px; padding:20px 18px 14px; min-height:120px; transform:rotate(var(--rot)); transform-origin:center top; box-shadow:0 1px 1px rgba(0,0,0,.05),0 6px 10px -4px rgba(0,0,0,.18),0 16px 30px -12px rgba(0,0,0,.28); transition:transform .28s cubic-bezier(.2,.8,.2,1), box-shadow .28s; color:#1f2937; cursor:grab; }
.postit-in::before{ content:""; position:absolute; inset:0; border-radius:inherit; pointer-events:none; background-image:repeating-linear-gradient(180deg, transparent 0 27px, rgba(0,0,0,.045) 27px 28px); opacity:.5; mix-blend-mode:multiply; }
.postit:hover .postit-in{ transform:rotate(0deg) translateY(-6px) scale(1.02); box-shadow:0 2px 2px rgba(0,0,0,.06),0 14px 20px -8px rgba(0,0,0,.24),0 30px 50px -16px rgba(0,0,0,.36); z-index:3; }
.tape{ position:absolute; top:-11px; left:50%; transform:translateX(-50%) rotate(-2.5deg); width:96px; height:26px; z-index:4; background:linear-gradient(135deg, rgba(255,255,255,.55), rgba(203,213,225,.35)); border:1px solid rgba(255,255,255,.4); box-shadow:0 2px 6px rgba(0,0,0,.12); border-radius:2px; }
.tape::after{ content:""; position:absolute; inset:0; background:repeating-linear-gradient(90deg, rgba(255,255,255,.25) 0 6px, transparent 6px 12px); }
.dogear{ position:absolute; right:0; bottom:0; width:0; height:0; border-style:solid; border-width:0 0 22px 22px; border-color:transparent transparent rgba(0,0,0,.10) transparent; border-radius:0 0 4px 0; transform:rotate(var(--rot)); }
.postit-title{ font-family:'Caveat',cursive; font-weight:700; font-size:1.55rem; line-height:1.05; margin:2px 0 8px; color:#111827; word-break:break-word; }
.postit-body{ font-family:'Kalam',cursive; font-size:.98rem; line-height:1.4; color:#374151; word-break:break-word; margin:0 0 12px; max-height:220px; overflow:hidden; -webkit-mask-image:linear-gradient(180deg,#000 80%, transparent); mask-image:linear-gradient(180deg,#000 80%, transparent); }
.postit-body b, .postit-body strong{ font-weight:700; } .postit-body u{ text-decoration:underline; } .postit-body i, .postit-body em{ font-style:italic; }
.postit-body .muted{ color:rgba(0,0,0,.4); }
.postit-foot{ display:flex; align-items:center; justify-content:space-between; gap:8px; border-top:1px dashed rgba(0,0,0,.14); padding-top:8px; }
.postit-date{ font-size:.72rem; color:rgba(0,0,0,.5); font-weight:600; display:inline-flex; align-items:center; gap:5px; }
.postit-actions{ display:flex; gap:4px; opacity:0; transform:translateY(4px); transition:.2s; }
.postit:hover .postit-actions{ opacity:1; transform:none; }
.pa{ width:30px; height:30px; border:0; border-radius:9px; background:rgba(255,255,255,.55); color:#334155; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:.15s; }
.pa:hover{ background:#fff; color:var(--nt-primary); transform:translateY(-1px); box-shadow:0 4px 10px rgba(0,0,0,.14); }
.pin-shared, .pin-cat{ display:inline-flex; align-items:center; gap:6px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; color:#3730a3; background:rgba(255,255,255,.55); border-radius:999px; padding:3px 9px; margin-bottom:8px; }
.pin-cat{ color:#334155; }
.empty{ grid-column:1/-1; text-align:center; padding:70px 20px; color:var(--nt-muted); }
.empty i{ font-size:3rem; opacity:.35; margin-bottom:14px; }
.empty h5{ font-weight:800; color:var(--nt-text); }
.nt-modal .modal-content{ border:0; border-radius:20px; overflow:hidden; box-shadow:0 30px 70px rgba(2,6,23,.4); }
.nt-modal .modal-header{ background:linear-gradient(135deg,var(--nt-primary),var(--nt-primary2)); color:#fff; border:0; padding:16px 22px; }
.nt-modal .modal-title{ font-weight:800; display:flex; align-items:center; gap:10px; }
.nt-close{ border:0; width:38px; height:38px; border-radius:11px; background:rgba(255,255,255,.18); color:#fff; cursor:pointer; transition:.15s; }
.nt-close:hover{ background:rgba(255,255,255,.34); transform:rotate(90deg); }
.nt-field{ margin-bottom:14px; }
.nt-field label{ font-size:.82rem; font-weight:700; color:var(--nt-muted); margin-bottom:6px; display:block; }
.nt-input{ width:100%; border:1px solid var(--nt-border); border-radius:12px; padding:11px 14px; font-size:.95rem; color:var(--nt-text); background:var(--nt-card); outline:none; transition:.15s; font-family:'Inter',sans-serif; }
.nt-input:focus{ border-color:var(--nt-primary); box-shadow:0 0 0 3px rgba(79,70,229,.14); }
.rt-toolbar{ display:flex; gap:6px; margin-bottom:8px; }
.rt-btn{ width:36px; height:34px; border:1px solid var(--nt-border); background:var(--nt-card); color:var(--nt-text); border-radius:9px; cursor:pointer; font-weight:800; transition:.14s; display:inline-flex; align-items:center; justify-content:center; }
.rt-btn:hover{ border-color:var(--nt-primary); color:var(--nt-primary); }
.rt-btn.on{ background:var(--nt-primary); color:#fff; border-color:transparent; }
.rt-editor{ width:100%; min-height:160px; max-height:340px; overflow:auto; border:1px solid var(--nt-border); border-radius:12px; padding:12px 14px; font-size:.98rem; line-height:1.5; color:var(--nt-text); background:var(--nt-card); outline:none; font-family:'Inter',sans-serif; }
.rt-editor:focus{ border-color:var(--nt-primary); box-shadow:0 0 0 3px rgba(79,70,229,.14); }
.rt-editor:empty:before{ content:attr(data-ph); color:var(--nt-muted); }
.rt-editor u{ text-decoration:underline; } .rt-editor b{ font-weight:700; } .rt-editor i{ font-style:italic; }
.swatches{ display:flex; flex-wrap:wrap; gap:10px; }
.swatch{ width:34px; height:34px; border-radius:50%; cursor:pointer; border:2px solid rgba(0,0,0,.08); box-shadow:0 3px 8px rgba(0,0,0,.12); transition:.15s; position:relative; }
.swatch:hover{ transform:scale(1.12); }
.swatch.sel{ border-color:var(--nt-primary); box-shadow:0 0 0 3px rgba(79,70,229,.28); }
.swatch.sel::after{ content:"\f00c"; font-family:"Font Awesome 6 Free"; font-weight:900; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:rgba(0,0,0,.55); font-size:.8rem; }
.share-list{ display:flex; flex-direction:column; gap:8px; margin-top:6px; max-height:210px; overflow:auto; }
.share-row{ display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid var(--nt-border); border-radius:12px; }
.share-row .av{ width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--nt-primary),var(--nt-primary2));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 auto; }
.share-row .nm{ flex:1; min-width:0; font-weight:600; color:var(--nt-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.view-paper{ background:var(--cor,#FEF08A); border-radius:8px; padding:22px; box-shadow:inset 0 0 0 1px rgba(0,0,0,.05); }
.view-title{ font-family:'Caveat',cursive; font-weight:700; font-size:2rem; color:#111827; margin:0 0 10px; }
.view-body{ font-family:'Kalam',cursive; color:#1f2937; line-height:1.5; }
.view-body b{ font-weight:700; } .view-body u{ text-decoration:underline; } .view-body i{ font-style:italic; }
.drag-hint{ font-size:.78rem; color:var(--nt-muted); margin-left:auto; display:inline-flex; align-items:center; gap:6px; }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fa-solid fa-note-sticky"></i></div>
        <div style="min-width:0">
          <h1>Anotações</h1>
          <div class="subtitle">Post-its coloridos com categorias. Arraste para organizar, formate o texto e compartilhe.</div>
        </div>
        <div class="hero-actions">
          <button class="btn-pill btn-primary-g" id="btnNova"><i class="fa-solid fa-plus"></i> Nova anotação</button>
        </div>
      </div>
    </section>

    <div class="nt-controls">
      <div class="nt-tabs">
        <button class="nt-tab active" data-tab="minhas"><i class="fa-solid fa-user"></i> Minhas <span class="cnt"><?php echo count($minhas); ?></span></button>
        <button class="nt-tab" data-tab="compartilhadas"><i class="fa-solid fa-user-group"></i> Compartilhadas <span class="cnt"><?php echo count($compartilhadas); ?></span></button>
      </div>
      <div class="nt-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="busca" placeholder="Buscar anotação..."></div>
    </div>

    <div class="nt-cats" id="catBar">
      <div class="nt-cat active" data-cat="__all"><i class="fa-solid fa-layer-group"></i> Todas</div>
      <?php foreach ($cats as $c): ?>
        <div class="nt-cat" data-cat="<?php echo nh($c); ?>">
          <i class="fa-solid fa-tag"></i> <span class="cat-name"><?php echo nh($c); ?></span>
          <span class="cat-tools">
            <span class="ct js-cat-edit" title="Renomear"><i class="fa-solid fa-pen"></i></span>
            <span class="ct js-cat-del" title="Excluir"><i class="fa-solid fa-xmark"></i></span>
          </span>
        </div>
      <?php endforeach; ?>
      <div class="nt-cat nt-cat-new" id="btnNovaCat"><i class="fa-solid fa-plus"></i> Nova categoria</div>
      <span class="drag-hint"><i class="fa-solid fa-arrows-up-down-left-right"></i> Arraste uma nota até uma categoria</span>
    </div>

    <div class="board" id="board-minhas">
      <?php if (!$minhas): ?>
        <div class="empty"><i class="fa-regular fa-note-sticky"></i><h5>Nenhuma anotação ainda</h5><p>Clique em “Nova anotação” para começar.</p></div>
      <?php else: foreach ($minhas as $n): render_postit($n, 'own'); endforeach; endif; ?>
    </div>

    <div class="board" id="board-compartilhadas" style="display:none">
      <?php if (!$compartilhadas): ?>
        <div class="empty"><i class="fa-regular fa-share-from-square"></i><h5>Nada compartilhado com você ainda</h5><p>Quando alguém compartilhar uma anotação, ela aparece aqui.</p></div>
      <?php else: foreach ($compartilhadas as $n): render_postit($n, 'shared'); endforeach; endif; ?>
    </div>

  </div>
</div>

<!-- MODAL CRIAR/EDITAR -->
<div class="modal fade nt-modal" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-pen-to-square"></i> <span id="editTitle">Nova anotação</span></div>
        <button class="nt-close" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="f_id"><input type="hidden" id="f_owner">
        <div class="nt-field"><label>Título</label><input class="nt-input" id="f_title" maxlength="180" placeholder="Ex.: Ligar para o cartório"></div>
        <div class="nt-field"><label>Conteúdo</label>
          <div class="rt-toolbar">
            <button type="button" class="rt-btn" data-cmd="bold" title="Negrito (Ctrl+B)" style="font-family:serif">B</button>
            <button type="button" class="rt-btn" data-cmd="italic" title="Itálico (Ctrl+I)" style="font-style:italic;font-family:serif">I</button>
            <button type="button" class="rt-btn" data-cmd="underline" title="Sublinhado (Ctrl+U)" style="text-decoration:underline">U</button>
          </div>
          <div class="rt-editor" id="f_content" contenteditable="true" data-ph="Escreva sua anotação..."></div>
        </div>
        <div class="nt-field" id="f_color_wrap"><label>Cor do post-it</label>
          <div class="swatches">
            <?php foreach ($paleta as $nome => $hex): ?>
              <span class="swatch" data-color="<?php echo nh($hex); ?>" style="background:<?php echo nh($hex); ?>" title="<?php echo nh(ucfirst($nome)); ?>"></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border:0;padding:0 22px 20px">
        <button class="btn-pill btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-pill btn-primary-g" id="btnSalvar"><i class="fa-solid fa-check"></i> Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL COMPARTILHAR -->
<div class="modal fade nt-modal" id="shareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><div class="modal-title"><i class="fa-solid fa-share-nodes"></i> Compartilhar anotação</div>
        <button class="nt-close" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button></div>
      <div class="modal-body">
        <input type="hidden" id="sh_id">
        <div class="nt-field"><label>Adicionar usuário</label>
          <div style="display:flex; gap:8px">
            <select class="nt-input" id="sh_user" style="flex:1"><option value="">Carregando…</option></select>
            <label style="display:flex;align-items:center;gap:6px;font-size:.82rem;color:var(--nt-muted);white-space:nowrap"><input type="checkbox" id="sh_edit"> pode editar</label>
            <button class="btn-pill btn-primary-g" id="btnAddShare" style="padding:10px 14px"><i class="fa-solid fa-plus"></i></button>
          </div>
        </div>
        <label style="font-size:.82rem;font-weight:700;color:var(--nt-muted)">Compartilhada com</label>
        <div class="share-list" id="sh_list"><div class="text-muted" style="font-size:.85rem;padding:8px">Ninguém ainda.</div></div>
      </div>
      <div class="modal-footer" style="border:0;padding:0 22px 20px">
        <button class="btn-pill btn-soft" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL COR -->
<div class="modal fade nt-modal" id="colorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content">
      <div class="modal-header"><div class="modal-title"><i class="fa-solid fa-palette"></i> Cor do post-it</div>
        <button class="nt-close" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button></div>
      <div class="modal-body">
        <input type="hidden" id="c_id">
        <div class="swatches" id="c_swatches">
          <?php foreach ($paleta as $nome => $hex): ?>
            <span class="swatch" data-color="<?php echo nh($hex); ?>" style="background:<?php echo nh($hex); ?>" title="<?php echo nh(ucfirst($nome)); ?>"></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL VISUALIZAR -->
<div class="modal fade nt-modal" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><div class="modal-title"><i class="fa-regular fa-eye"></i> Anotação</div>
        <button class="nt-close" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button></div>
      <div class="modal-body">
        <div class="view-paper" id="v_paper"><h2 class="view-title" id="v_title"></h2><div class="view-body" id="v_body"></div></div>
      </div>
      <div class="modal-footer" style="border:0;padding:0 22px 20px">
        <button class="btn-pill btn-soft" data-bs-dismiss="modal">Fechar</button>
        <button class="btn-pill btn-primary-g" id="v_edit" style="display:none"><i class="fa-solid fa-pen-to-square"></i> Editar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function(){
  "use strict";
  var CSRF = <?php echo json_encode($CSRF); ?>;
  var ME   = <?php echo json_encode($username); ?>;
  var $  = function(s,c){ return (c||document).querySelector(s); };
  var $$ = function(s,c){ return Array.prototype.slice.call((c||document).querySelectorAll(s)); };
  function modal(id){ return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(m){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];}); }
  async function post(url,data){ data.csrf=CSRF; var r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data).toString(),credentials:'same-origin'}); var t=await r.text(); try{return JSON.parse(t);}catch(e){throw new Error('Resposta inválida: '+t.slice(0,140));} }

  var boardMinhas = $('#board-minhas');
  var catAtual = '__all';

  $$('.nt-tab').forEach(function(t){
    t.addEventListener('click', function(){
      $$('.nt-tab').forEach(function(x){ x.classList.remove('active'); }); t.classList.add('active');
      var tab=t.dataset.tab, minhas=(tab==='minhas');
      $('#board-minhas').style.display=minhas?'':'none';
      $('#board-compartilhadas').style.display=minhas?'none':'';
      $('#catBar').style.display=minhas?'':'none';
      aplicaFiltro();
    });
  });

  function aplicaFiltro(){
    var q=($('#busca').value||'').toLowerCase().trim();
    var minhas=$('.nt-tab.active').dataset.tab==='minhas';
    var board=minhas?'#board-minhas':'#board-compartilhadas';
    $$('.postit',$(board)).forEach(function(p){
      var okCat=!minhas || catAtual==='__all' || (p.dataset.cat||'')===catAtual;
      var hay=((p.dataset.title||'')+' '+(p.dataset.text||'')).toLowerCase();
      var okQ=!q || hay.indexOf(q)>=0;
      p.style.display=(okCat && okQ)?'':'none';
    });
  }
  $('#busca').addEventListener('input', aplicaFiltro);

  function atualizarPinCat(card, cat){
    var inner=card.querySelector('.postit-in'); var pin=card.querySelector('.pin-cat');
    if(cat===''){ if(pin) pin.remove(); return; }
    if(!pin){ pin=document.createElement('div'); pin.className='pin-cat'; inner.insertBefore(pin, inner.querySelector('.postit-title')); }
    pin.innerHTML='<i class="fa-solid fa-tag"></i> '+esc(cat);
  }
  function bindCatChip(chip){
    chip.addEventListener('click', function(ev){
      if(ev.target.closest('.cat-tools')) return;
      $$('.nt-cat',$('#catBar')).forEach(function(x){ x.classList.remove('active'); });
      chip.classList.add('active'); catAtual=chip.dataset.cat; aplicaFiltro();
    });
    var ed=chip.querySelector('.js-cat-edit'), dl=chip.querySelector('.js-cat-del');
    if(ed) ed.addEventListener('click', function(e){ e.stopPropagation(); renomearCat(chip.dataset.cat); });
    if(dl) dl.addEventListener('click', function(e){ e.stopPropagation(); excluirCat(chip.dataset.cat); });
    Sortable.create(chip, { group:{name:'notas', pull:true, put:true}, sort:false,
      onAdd:function(evt){
        var card=evt.item, cat=(chip.dataset.cat==='__all'?'':chip.dataset.cat);
        card.dataset.cat=cat; boardMinhas.appendChild(card);
        atualizarPinCat(card, cat); salvarOrg(); aplicaFiltro();
        Swal.fire({toast:true,position:'bottom-end',icon:'success',title:'Movida para “'+(cat===''?'Todas':cat)+'”',timer:1500,showConfirmButton:false});
      }
    });
  }
  $$('.nt-cat',$('#catBar')).forEach(function(c){ if(c.id!=='btnNovaCat') bindCatChip(c); });

  $('#btnNovaCat').addEventListener('click', async function(){
    var r=await Swal.fire({title:'Nova categoria',input:'text',inputPlaceholder:'Ex.: Trabalho',showCancelButton:true,confirmButtonText:'Criar',cancelButtonText:'Cancelar',confirmButtonColor:'#4f46e5'});
    if(!r.isConfirmed || !r.value) return;
    try{ var res=await post('cat_criar.php',{nome:r.value}); if(!res.success) throw new Error(res.message||'Falha.'); location.reload(); }catch(e){ Swal.fire('Erro', e.message, 'error'); }
  });
  async function renomearCat(nome){
    var r=await Swal.fire({title:'Renomear categoria',input:'text',inputValue:nome,showCancelButton:true,confirmButtonText:'Salvar',cancelButtonText:'Cancelar',confirmButtonColor:'#4f46e5'});
    if(!r.isConfirmed || !r.value || r.value===nome) return;
    try{ var res=await post('cat_renomear.php',{de:nome,para:r.value}); if(!res.success) throw new Error(res.message); location.reload(); }catch(e){ Swal.fire('Erro',e.message,'error'); }
  }
  async function excluirCat(nome){
    var r=await Swal.fire({icon:'warning',title:'Excluir categoria “'+nome+'”?',text:'As anotações dela ficarão sem categoria (não serão apagadas).',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'});
    if(!r.isConfirmed) return;
    try{ var res=await post('cat_excluir.php',{nome:nome}); if(!res.success) throw new Error(res.message); location.reload(); }catch(e){ Swal.fire('Erro',e.message,'error'); }
  }

  if(boardMinhas){
    Sortable.create(boardMinhas, { group:{name:'notas', pull:true, put:true}, animation:160, filter:'.empty',
      draggable:'.postit', ghostClass:'sortable-ghost', chosenClass:'sortable-chosen', onEnd:function(){ salvarOrg(); } });
  }
  function salvarOrg(){
    var itens=$$('.postit',boardMinhas).map(function(p,i){ return {id:p.dataset.id, cat:p.dataset.cat||'', ord:i}; });
    post('org_salvar.php',{itens:JSON.stringify(itens)}).catch(function(){});
  }

  var editor=$('#f_content');
  $$('.rt-btn').forEach(function(b){
    b.addEventListener('mousedown', function(e){ e.preventDefault(); });
    b.addEventListener('click', function(){
      editor.focus();
      try{ document.execCommand('styleWithCSS', false, false); }catch(_){}
      document.execCommand(b.dataset.cmd, false, null);
      sincronizaBotoes();
    });
  });
  function sincronizaBotoes(){ $$('.rt-btn').forEach(function(b){ try{ b.classList.toggle('on', document.queryCommandState(b.dataset.cmd)); }catch(_){} }); }
  editor.addEventListener('keyup', sincronizaBotoes);
  editor.addEventListener('mouseup', sincronizaBotoes);

  function bindSwatches(container,onPick){
    $$('.swatch',container).forEach(function(s){ s.addEventListener('click', function(){ $$('.swatch',container).forEach(function(x){x.classList.remove('sel');}); s.classList.add('sel'); if(onPick) onPick(s.dataset.color); }); });
  }
  var novaCor='<?php echo nh(array_values($paleta)[0]); ?>';
  bindSwatches($('#f_color_wrap'), function(c){ novaCor=c; });

  $('#btnNova').addEventListener('click', function(){
    $('#editTitle').textContent='Nova anotação';
    $('#f_id').value=''; $('#f_owner').value=ME; $('#f_title').value=''; editor.innerHTML='';
    $('#f_color_wrap').style.display='';
    $$('.swatch',$('#f_color_wrap')).forEach(function(x,i){ x.classList.toggle('sel', i===0); });
    novaCor=$$('.swatch',$('#f_color_wrap'))[0].dataset.color;
    sincronizaBotoes(); modal('editModal').show();
    setTimeout(function(){ $('#f_title').focus(); }, 300);
  });
  function abrirEdicao(card){
    $('#editTitle').textContent='Editar anotação';
    $('#f_id').value=card.dataset.id; $('#f_owner').value=card.dataset.owner;
    $('#f_title').value=card.dataset.title; editor.innerHTML=card.dataset.content||'';
    $('#f_color_wrap').style.display=(card.dataset.mode==='own')?'':'none';
    sincronizaBotoes(); modal('editModal').show();
  }
  $('#btnSalvar').addEventListener('click', async function(){
    var id=$('#f_id').value, owner=$('#f_owner').value||ME;
    var payload={ title:$('#f_title').value, content:editor.innerHTML, owner:owner };
    try{
      var r; if(!id){ payload.color=novaCor; r=await post('nota_criar.php',payload); }
      else { payload.id=id; r=await post('nota_salvar.php',payload); }
      if(!r.success) throw new Error(r.message||'Falha.');
      modal('editModal').hide(); location.reload();
    }catch(e){ Swal.fire('Erro', e.message, 'error'); }
  });

  document.addEventListener('click', function(ev){
    var btn=ev.target.closest('.pa');
    if(btn){ var card=ev.target.closest('.postit');
      if(btn.classList.contains('js-view'))  return verNota(card);
      if(btn.classList.contains('js-color')) return abrirCor(card);
      if(btn.classList.contains('js-share')) return abrirShare(card);
      if(btn.classList.contains('js-del'))   return excluir(card);
      return;
    }
    var card=ev.target.closest('.postit'); if(!card) return;
    verNota(card);
  });

  var cardView=null;
  function verNota(card){
    cardView=card;
    $('#v_title').textContent=card.dataset.title||'Sem título';
    $('#v_body').innerHTML=card.dataset.content||'';
    $('#v_paper').style.setProperty('--cor', card.dataset.color);
    $('#v_edit').style.display=(card.dataset.canedit==='1')?'':'none';
    modal('viewModal').show();
  }
  $('#v_edit').addEventListener('click', function(){
    if(!cardView) return;
    var c=cardView;
    modal('viewModal').hide();
    setTimeout(function(){ abrirEdicao(c); }, 250);
  });

  function abrirCor(card){
    $('#c_id').value=card.dataset.id;
    $$('.swatch',$('#c_swatches')).forEach(function(s){ s.classList.toggle('sel', s.dataset.color.toUpperCase()===(card.dataset.color||'').toUpperCase()); });
    modal('colorModal').show();
  }
  bindSwatches($('#c_swatches'), async function(c){
    var id=$('#c_id').value;
    try{ var r=await post('nota_cor.php',{id:id,color:c,owner:ME}); if(!r.success) throw new Error(r.message);
      var card=$('.postit[data-id="'+CSS.escape(id)+'"]'); if(card){ card.style.setProperty('--cor',r.color); card.dataset.color=r.color; }
      modal('colorModal').hide();
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });

  async function excluir(card){
    var own=card.dataset.mode==='own';
    var q=await Swal.fire({icon:'warning',title:own?'Excluir anotação?':'Remover da sua lista?',
      text:own?'Ela irá para a lixeira e deixará de ser compartilhada.':'Você deixará de ver esta anotação.',
      showCancelButton:true,confirmButtonText:own?'Excluir':'Remover',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'});
    if(!q.isConfirmed) return;
    try{ var r=await post('nota_excluir.php',{id:card.dataset.id,owner:card.dataset.owner}); if(!r.success) throw new Error(r.message);
      card.style.transition='.25s'; card.style.opacity='0'; card.style.transform='scale(.9)'; setTimeout(function(){ location.reload(); },260);
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  }

  var usuariosCache=null;
  async function carregarUsuarios(){ if(usuariosCache) return usuariosCache; var r=await fetch('nota_usuarios.php',{credentials:'same-origin'}); var j=await r.json(); usuariosCache=(j.success?j.usuarios:[]); return usuariosCache; }
  async function abrirShare(card){
    $('#sh_id').value=card.dataset.id;
    var sel=$('#sh_user'); sel.innerHTML='<option value="">Carregando…</option>';
    var us=await carregarUsuarios();
    sel.innerHTML='<option value="">Selecione um usuário…</option>'+us.map(function(u){return '<option value="'+esc(u.usuario)+'">'+esc(u.nome)+'</option>';}).join('');
    await renderShareList(card.dataset.id); modal('shareModal').show();
  }
  async function renderShareList(id){
    var box=$('#sh_list'); box.innerHTML='<div class="text-muted" style="font-size:.85rem;padding:8px">Carregando…</div>';
    var r=await fetch('nota_compartilhamentos.php?id='+encodeURIComponent(id),{credentials:'same-origin'}); var j=await r.json();
    var arr=(j.success?j.compartilhamentos:[]);
    if(!arr.length){ box.innerHTML='<div class="text-muted" style="font-size:.85rem;padding:8px">Ninguém ainda.</div>'; return; }
    box.innerHTML='';
    arr.forEach(function(s){
      var row=document.createElement('div'); row.className='share-row';
      var ini=(s.nome||s.shared_with||'?').trim().charAt(0).toUpperCase();
      row.innerHTML='<div class="av">'+esc(ini)+'</div><div class="nm">'+esc(s.nome||s.shared_with)+(parseInt(s.can_edit,10)?' <span style="font-size:.7rem;color:var(--nt-muted)">(edita)</span>':'')+'</div>';
      var del=document.createElement('button'); del.className='pa'; del.title='Remover'; del.style.background='#fee2e2'; del.style.color='#b91c1c'; del.innerHTML='<i class="fa-solid fa-xmark"></i>';
      del.addEventListener('click', async function(){ try{ var rr=await post('nota_descompartilhar.php',{id:id,usuario:s.shared_with}); if(!rr.success) throw new Error(rr.message); renderShareList(id); }catch(e){ Swal.fire('Erro',e.message,'error'); } });
      row.appendChild(del); box.appendChild(row);
    });
  }
  $('#btnAddShare').addEventListener('click', async function(){
    var id=$('#sh_id').value, user=$('#sh_user').value, canEdit=$('#sh_edit').checked?1:0;
    if(!user){ Swal.fire('Atenção','Escolha um usuário.','warning'); return; }
    try{ var r=await post('nota_compartilhar.php',{id:id,usuario:user,can_edit:canEdit}); if(!r.success) throw new Error(r.message);
      $('#sh_user').value=''; $('#sh_edit').checked=false; renderShareList(id);
      Swal.fire({icon:'success',title:'Compartilhada',text:r.message,timer:1800,showConfirmButton:false});
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });

})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
