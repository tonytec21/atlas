<style>
    /* =======================================================================
       DESIGN TOKENS / THEME
    ======================================================================= */
    :root{
      --bg: #f6f7fb;
      --card: #ffffff;
      --muted: #6b7280;
      --text: #111827;
      --border: #e5e7eb;
      --shadow: 0 10px 25px rgba(16,24,40,.06);
      --soft-shadow: 0 6px 18px rgba(16,24,40,.08);
      --brand: #4F46E5;
      --focus: rgba(99,102,241,.22);
      --danger: #ef4444;
      --success: #10b981;
    }
    body.light-mode{ background:var(--bg); color:var(--text); }
    body.dark-mode{
      --bg:#0f141a; --card:#1a2129; --text:#e5e7eb; --muted:#9aa6b2; --border:#2a3440;
      --shadow: 0 10px 25px rgba(0,0,0,.35);
      --soft-shadow: 0 6px 18px rgba(0,0,0,.4);
      --focus: rgba(99,102,241,.28);
      background:var(--bg); color:var(--text);
    }
    .muted{ color:var(--muted)!important; }
    .soft-divider{ height:1px;background:var(--border);margin:1rem 0; }

    /* =======================================================================
       HERO / TÍTULO (consistente com suas outras telas)
    ======================================================================= */
    .page-hero{
      background: linear-gradient(180deg, rgba(79,70,229,.12), rgba(79,70,229,0));
      border-radius: 18px;
      padding: 18px 18px 6px 18px;
      margin: 20px 0 12px;
      box-shadow: var(--soft-shadow);
    }
    .page-hero .row-top{
      display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    }
    .page-hero .hero-left{
      display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    }
    .page-hero .title-icon{
      width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;
      display:flex;align-items:center;justify-content:center;font-size:20px;
    }
    body.dark-mode .page-hero .title-icon{ background:#262f3b;color:#c7d2fe; }
    .page-hero h1{
      font-size: clamp(1.25rem, .9rem + 2vw, 1.75rem);
      font-weight: 800; margin:0; letter-spacing:.2px;
    }

    .hero-actions{ display:flex; gap:8px; flex-wrap:wrap; }
    .btn{ border-radius:10px; }
    .btn-primary{ background:#4F46E5; border-color:#4F46E5; }
    .btn-primary:hover{ filter:brightness(.95); }
    .btn-secondary{ background:#4B5563; border-color:#4B5563; }
    .btn-secondary:hover{ filter:brightness(.95); }
    .btn-success{ background:var(--success); border-color:var(--success); }
    .btn-success:hover{ filter:brightness(.95); }
    .btn-delete{ background:var(--danger); color:#fff; border:none; }

    /* =======================================================================
       FORM CARDS
    ======================================================================= */
    .form-card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      box-shadow:var(--shadow);
      padding:16px;
      margin-bottom:16px;
    }
    .form-card h4{
      font-weight:800; margin:4px 0 14px;
      font-size: clamp(1.05rem,0.9rem + .6vw,1.2rem);
    }
    .form-card label{
      font-size:.78rem; text-transform:uppercase; letter-spacing:.04em;
      color:var(--muted); margin-bottom:6px; font-weight:700;
    }
    .form-control, .form-select{
      background: transparent; color: var(--text);
      border:1px solid var(--border); border-radius:10px;
    }
    .form-control:focus, .form-select:focus{
      border-color:#a5b4fc; box-shadow:0 0 0 .2rem var(--focus);
    }
    .row.g-grid > [class^="col-"], .row.g-grid > [class*=" col-"]{ margin-bottom:12px; }

    .table-responsive { border-radius: 10px; border: 1px solid var(--border); }
    .table thead th{ background:rgba(148,163,184,.12); border-bottom:1px solid var(--border); }
    .attachment-item .btn{ border-radius:8px; }

    /* =======================================================================
       DROPZONE
    ======================================================================= */
    .dropzone{
      position: relative;
      border:2px dashed #c7d2fe;
      background: linear-gradient(180deg, rgba(148,163,184,.08), rgba(148,163,184,0));
      border-radius:16px;
      padding:22px;
      text-align:center;
      transition:border-color .2s, background .2s, transform .15s;
    }
    .dropzone.dragover{
      border-color:#818cf8;
      background: linear-gradient(180deg, rgba(129,140,248,.15), rgba(129,140,248,.05));
      transform: translateY(-2px);
    }
    .dz-icon{ font-size:28px;color:#6366f1;margin-bottom:6px;display:block; }
    .dz-title{ font-weight:800; }
    .dz-help{ font-size:.9rem; color:var(--muted); }
    .dz-btn{
      margin-top:10px; border-radius: 999px; padding:.5rem .9rem; border:1px solid var(--border);
      background:#EEF2FF; color:#3730A3; font-weight:700;
    }
    body.dark-mode .dz-btn{ background:#263041; color:#cbd5e1; border-color:#344154; }

    .files-list{
      margin-top:12px; display:grid; gap:8px;
      grid-template-columns: repeat(auto-fit,minmax(260px,1fr));
    }
    .file-item{
      display:flex; align-items:center; gap:10px;
      border:1px solid var(--border); border-radius:12px; padding:10px 12px; background:var(--card);
      box-shadow: var(--soft-shadow);
    }
    .file-icon{
      width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;
      background:#eef2ff;color:#3730A3;
    }
    body.dark-mode .file-icon{ background:#263041; color:#cbd5e1; }
    .file-meta{ flex:1 1 auto; min-width:0; }
    .file-name{ font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .file-size{ font-size:.8rem; color:var(--muted); }
    .file-remove{
      border:none; background:var(--danger); color:#fff; border-radius:8px; padding:.35rem .6rem;
    }
    .file-remove:hover{ filter:brightness(.95); }

    /* =======================================================================
       ANEXOS EXISTENTES (GRID)
    ======================================================================= */
    .attachments-grid{
      display:grid; grid-template-columns: repeat(auto-fit,minmax(280px,1fr));
      gap:10px;
    }
    .attachment-card{
      border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--card); box-shadow:var(--soft-shadow);
      display:flex; align-items:center; gap:12px;
    }
    .attachment-card .att-icon{
      width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;
      background:#eef2ff;color:#3730A3;font-size:18px;
    }
    body.dark-mode .attachment-card .att-icon{ background:#263041; color:#cbd5e1; }
    .attachment-name{ font-weight:700; word-break:break-all; }
    .attachment-actions{ margin-left:auto; display:flex; gap:6px; }

    /* =======================================================================
       MODAIS
    ======================================================================= */
    .modal-content{ background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); }
    .modal-header{ border-bottom:1px solid var(--border); }
    .modal-footer{ border-top:1px solid var(--border); }

    /* Preview Modal (90% da página) */
    #previewModal .modal-dialog{
      max-width: min(1400px, 95vw);
    }
    #previewModal .modal-content{
      border-radius:18px;
      border:1px solid var(--border);
      background:var(--card);
    }
    #previewModal .modal-header{
      position:sticky; top:0; z-index:3; background:var(--card);
      border-bottom:1px solid var(--border); padding:12px 14px;
      display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    }
    #previewModal .modal-title{
      font-weight:800; letter-spacing:.2px; margin-right:auto; display:flex; align-items:center; gap:8px;
    }
    .preview-toolbar .btn{
      border-radius:10px; border:1px solid var(--border); background:transparent; color:var(--text);
    }
    .preview-toolbar .btn:hover{ background:rgba(148,163,184,.15); }
    #previewContainer{
      height: calc(90vh - 62px);
      display:flex; align-items:center; justify-content:center;
      background:linear-gradient(180deg, rgba(148,163,184,.06), rgba(148,163,184,.02));
      padding:0; margin:0;
    }
    #previewFrame{ width:100%; height:100%; border:0; display:none; }
    #previewImage{ max-width:100%; max-height:100%; object-fit:contain; display:none; border-radius:12px; }

    /* Acessibilidade/UX para mobile: botão de fechar maior */
    .btn-close-lg{
      border:1px solid var(--border);
      border-radius:10px;
      padding:.35rem .6rem;
      background:transparent;
    }
    .btn-close-lg:hover{ background:rgba(148,163,184,.15); }

    @media (max-width: 576px){
      #previewModal .modal-header{ gap:8px; }
      .preview-toolbar{ width:100%; display:flex; gap:8px; }
      .preview-toolbar .btn{ flex:1; }
    }

    /* =======================================================================
       SELO – layout responsivo
    ======================================================================= */
    .seal-wrapper .hint{
      font-size:.95rem; color:var(--muted); margin-bottom:10px;
    }
    .seal-card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      box-shadow:var(--soft-shadow);
      padding:14px;
    }
    .seal-head{
      display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
      margin-bottom:10px;
    }
    .seal-title{ font-weight:800; letter-spacing:.2px; }
    .seal-pill{
      display:inline-flex; align-items:center; gap:6px;
      background:#e0f2fe; color:#075985; border:1px solid #bae6fd;
      padding:.35rem .6rem; border-radius:999px; font-weight:700; font-size:.75rem;
    }
    body.dark-mode .seal-pill{ background:#17384a; color:#cfefff; border-color:#1e4b61; }

    .seal-grid{ display:grid; grid-template-columns: 140px 1fr; gap:14px; align-items:flex-start; }
    .seal-qr{
      width:140px; max-width:100%; aspect-ratio:1/1; border-radius:12px;
      background:#fff; display:flex; align-items:center; justify-content:center;
      overflow:hidden; border:1px solid var(--border); box-shadow: var(--soft-shadow);
    }
    .seal-qr img{ width:100%; height:100%; object-fit:contain; }
    .seal-meta .seal-number{ font-size:.95rem; margin-bottom:6px; }
    .seal-meta .seal-number b{ font-size:1rem; }
    .seal-text{
      margin:0; white-space:pre-wrap; word-break:break-word; overflow-wrap:anywhere; hyphens:auto; line-height:1.45;
    }
    @media (max-width: 640px){
      .seal-grid{ grid-template-columns: 1fr; }
      .seal-qr{ margin:0 auto; }
      .seal-head{ align-items:flex-start; }
    }
    .seal-actions{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .seal-copy-btn{
      border-radius:10px; border:1px solid var(--border); background:#EEF2FF; color:#3730A3; font-weight:700;
      padding:.45rem .7rem;
    }
    body.dark-mode .seal-copy-btn{ background:#263041; color:#cbd5e1; border-color:#344154; }

    /* ===== SELO – formulário elegante e 100% responsivo ===== */
    .seal-wrapper .selo-form .form-control,
    .seal-wrapper .selo-form select{
      height: 46px;
      border-radius: 12px;
      transition: box-shadow .15s, border-color .15s, transform .06s;
    }
    .seal-wrapper .selo-form .form-control:focus,
    .seal-wrapper .selo-form select:focus{
      border-color:#a5b4fc;
      box-shadow:0 0 0 .2rem var(--focus);
    }

    .seal-wrapper .selo-form .field-help{
      font-size:.82rem; color:var(--muted); margin-top:6px;
    }

    /* Botão com visual premium só aqui dentro (não afeta o resto do sistema) */
    .seal-wrapper #solicitar-selo-btn{
      height:48px; border:0;
      background: linear-gradient(135deg, #4F46E5, #6366F1);
      box-shadow: 0 8px 20px rgba(79,70,229,.25);
      border-radius: 12px;
      font-weight:800; letter-spacing:.2px;
    }
    .seal-wrapper #solicitar-selo-btn:hover{ transform: translateY(-1px); filter:brightness(.98); }
    .seal-wrapper #solicitar-selo-btn:active{ transform: translateY(0); }

    /* Switch moderno para “Selo isento?” */
    .switch{
      position: relative; display:inline-flex; align-items:center; gap:10px; cursor:pointer;
      user-select:none;
    }
    .switch input{ position:absolute; opacity:0; pointer-events:none; }
    .switch .slider{
      width:48px; height:28px; border-radius:999px;
      background:#e5e7eb; border:1px solid var(--border);
      position:relative; transition:background .2s, border-color .2s;
    }
    .switch .slider::after{
      content:""; position:absolute; top:50%; left:3px; transform:translateY(-50%);
      width:22px; height:22px; border-radius:50%; background:#fff;
      box-shadow:0 1px 3px rgba(0,0,0,.18); transition:left .2s;
    }
    .switch input:checked + .slider{
      background:#4F46E5; border-color:#4F46E5;
    }
    .switch input:checked + .slider::after{ left:23px; }
    .switch .switch-text{ font-weight:700; color:var(--text); font-size:.9rem; }

    /* Layout: aperta melhor nos breakpoints */
    .seal-wrapper .selo-form{ row-gap:12px; }
    @media (min-width: 992px){
      .seal-wrapper .selo-form{ align-items:end; }
    }

    /* === Overlay de processamento centralizado e responsivo === */
    .upload-overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background: rgba(0,0,0,.45);
      z-index: 12000;
    }
    .upload-overlay[aria-hidden="false"] { display: flex !important; }

    .upload-card {
      width: 100%;
      max-width: 560px;
      margin: 0;
      padding: clamp(16px, 2.2vw, 28px);
      border-radius: 18px;
      box-sizing: border-box;
    }
    .upload-title   { font-size: clamp(1rem, 1.2vw + .6rem, 1.15rem); font-weight: 600; display:flex; gap:8px; align-items:center; margin-bottom:10px; }
    .upload-subtitle{ font-size: clamp(.9rem, 1vw + .45rem, 1rem); opacity:.85; margin-bottom:14px; }

    .progress { height: 12px; border-radius: 999px; overflow: hidden; background: rgba(0,0,0,.08); }
    .progress-bar { height: 100%; transition: width .2s ease; }
    .progress-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top:10px; }
    .progress-value { font-variant-numeric: tabular-nums; font-weight: 600; }
    .upload-footer { margin-top: 12px; font-size: .85rem; opacity:.8; }

    @media (max-width: 480px) {
      .progress      { height: 10px; }
      .upload-footer { font-size: .8rem; }
    }

    /* Temas */
    body.dark-mode  .upload-card { background: #161b22; color: #e6edf3; }
    body.light-mode .upload-card { background: #fff;    color: #111; }
  </style>