<?php /* Botões do módulo Fluxo de Caixa — independentes do CSS global do Atlas */ ?>
<style>
        .cxf-btn{ display:inline-flex !important; align-items:center; justify-content:center; gap:8px;
                  width:auto !important; min-width:0 !important; height:auto !important; min-height:42px;
                  padding:9px 18px !important; margin:0 !important; border-radius:10px !important;
                  border:1px solid transparent !important; font-size:.95rem !important; font-weight:600 !important;
                  line-height:1.2 !important; white-space:nowrap !important; text-decoration:none !important;
                  cursor:pointer; transition:background .15s ease, border-color .15s ease, color .15s ease; box-shadow:none !important; }
        .cxf-btn .fa{ font-size:1rem; width:auto !important; height:auto !important; margin:0 !important; }
        .cxf-btn:disabled{ opacity:.55; cursor:not-allowed; }
        .cxf-btn-sm{ min-height:34px; padding:6px 12px !important; font-size:.85rem !important; }
        .cxf-btn-primary{ background:#2563eb !important; border-color:#2563eb !important; color:#fff !important; min-width:160px !important; }
        .cxf-btn-primary:hover{ background:#1d4ed8 !important; }
        .cxf-btn-light{ background:#fff !important; border-color:#cbd5e1 !important; color:#334155 !important; }
        .cxf-btn-light:hover{ background:#f1f5f9 !important; }
        .cxf-btn-dark{ background:#fff !important; border-color:#334155 !important; color:#0f172a !important; }
        .cxf-btn-dark:hover{ background:#0f172a !important; color:#fff !important; }
        .cxf-btn-green{ background:#16a34a !important; border-color:#16a34a !important; color:#fff !important; }
        .cxf-btn-green:hover{ background:#15803d !important; }
        .cxf-btn-green-outline{ background:#fff !important; border-color:#16a34a !important; color:#15803d !important; }
        .cxf-btn-green-outline:hover:not(:disabled){ background:#16a34a !important; color:#fff !important; }
        body.dark-mode .cxf-btn-light, body.dark-mode .cxf-btn-dark, body.dark-mode .cxf-btn-green-outline{ background:#0f172a !important; color:#e2e8f0 !important; border-color:#475569 !important; }
</style>
