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
      --brand-2: #10B981;
      --focus: rgba(99,102,241,.22);
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
       FORM WRAPPER / CARDS
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

    .actions-bar{
      display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;
    }

    /* =======================================================================
       PARTES ENVOLVIDAS TABLE (responsivo)
    ======================================================================= */
    .table-responsive { border-radius: 10px; border: 1px solid var(--border); }
    .table thead th{ background:rgba(148,163,184,.12); border-bottom:1px solid var(--border); }
    .btn-delete{
      background:#ef4444; color:#fff; border:none; padding:.35rem .55rem; border-radius:8px;
    }
    .btn-delete:hover{ filter:brightness(.95); }

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
    .dz-icon{
      font-size:28px;color:#6366f1;margin-bottom:6px;display:block;
    }
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
      border:none; background:#ef4444; color:#fff; border-radius:8px; padding:.35rem .6rem;
    }
    .file-remove:hover{ filter:brightness(.95); }

    /* =======================================================================
       BUTTONS
    ======================================================================= */
    .btn-primary{ background:#4F46E5; border-color:#4F46E5; }
    .btn-primary:hover{ filter:brightness(.95); }
    .btn-secondary{ background:#4B5563; border-color:#4B5563; }
    .btn-secondary:hover{ filter:brightness(.95); }
    .btn-success{ background:#10b981; border-color:#10b981; }
    .btn-success:hover{ filter:brightness(.95); }

    /* spacing */
    .row.g-grid > [class^="col-"], .row.g-grid > [class*=" col-"]{ margin-bottom:12px; }

  </style>