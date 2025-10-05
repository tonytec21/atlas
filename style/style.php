<style>
/* =================== BASE / THEME VARIABLES =================== */
:root {
    /* Light Theme (default) */
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --bg-tertiary: #e9ecef;
    --text-primary: #2c3e50;
    --text-secondary: #6c757d;
    --border-color: #dee2e6;
    --input-bg: #ffffff;
    --input-border: #ced4da;
    --card-shadow: 0 2px 15px rgba(0,0,0,0.08);
    --hover-bg: #f8f9fa;
    --accent-color: #2196F3;
    --accent-hover: #1976D2;
    --danger-color: #dc3545;
    --success-color: #28a745;

    /* Layout tokens usados no menu */
    --sidebar-width: 280px;
    --mini-sidebar-width: 60px;
    --header-height: 60px;
    --transition: all 0.3s ease;
}

/* Dark overrides via body.dark-mode */
body.dark-mode {
    --bg-primary: #1a1d21;
    --bg-secondary: #242832;
    --bg-tertiary: #2d3238;
    --text-primary: #e9ecef;
    --text-secondary: #adb5bd;
    --border-color: #2d3238;
    --input-bg: #2d3238;
    --input-border: #404650;
    --card-shadow: 0 2px 15px rgba(0,0,0,0.2);
    --hover-bg: #2d3238;
    --accent-color: #3498db;
    --accent-hover: #2980b9;
}

/* =================== GLOBAL =================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

html, body {
    height: 100%;
}

body {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    line-height: 1.6;
    transition: var(--transition);
}

/* Ajuste containers bootstrap */
.container, .container-lg, .container-md, .container-sm, .container-xl {
    max-width: 100% !important;
}

/* Readonly em dark */
body.dark-mode .form-control[readonly] {
    background-color: #474747;
}

/* =================== TIPOGRAFIA E BOTÕES GERAIS =================== */
.page-title {
    justify-content: space-around !important;
    margin-bottom: 30px !important;
    font-size: 2rem !important;
}
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}
.page-title i { color: var(--accent-color); font-size: 1.75rem; }

.btn-close {
    outline: none;
    border: none;
    background: none;
    padding: 0;
    font-size: 1.5rem;
    cursor: pointer;
    transition: transform 0.2s ease;
}
.btn-close:hover { transform: scale(1.5); }
.btn-close:focus { outline: none; }

.btn {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: none;
}
.btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.btn-primary { background: linear-gradient(45deg, var(--accent-color), var(--accent-hover)); color: #fff; }
.btn-success { background: linear-gradient(45deg, var(--success-color), #218838); color: #fff; }

/* =================== FORM / TABELA =================== */
.filter-section {
    background-color: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.form-label { color: var(--text-secondary); font-weight: 500; font-size: 0.875rem; margin-bottom: 0.5rem; }
.form-control {
    background-color: var(--input-bg);
    border: 1.5px solid var(--input-border);
    color: var(--text-primary);
    border-radius: 8px;
    padding: 0.625rem 1rem;
    transition: all 0.2s ease;
    height: auto;
}
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.15);
    background-color: var(--input-bg);
    color: var(--text-primary);
}

.table-container { background-color: var(--bg-primary); border-radius: 12px; overflow: hidden; margin-top: 2rem; }
.table { margin-bottom: 0; color: #000; border-collapse: separate; border-spacing: 0; width: 100%; }
.table th {
    background-color: var(--bg-secondary);
    font-weight: 600;
    padding: 1rem;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-top: none;
    vertical-align: middle !important;
}
.table td { padding: 1rem; vertical-align: middle; border-top: 1px solid var(--border-color); }
.table tbody tr:hover { background-color: var(--hover-bg); }

.checkbox-column { width: 40px; text-align: center; vertical-align: middle !important; }
.form-check { margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: auto; }
.form-check-input { margin: 0; position: relative; }

/* DataTables */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
    color: var(--text-secondary);
    margin: 1rem;
}
.dataTables_wrapper .dataTables_filter input {
    background-color: var(--input-bg);
    border: 1.5px solid var(--input-border);
    color: var(--text-primary);
    border-radius: 8px;
    padding: 0.4rem 1rem;
    margin-left: 0.5rem;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 6px;
    padding: 0.5rem 1rem;
    margin: 0 2px;
    border: none;
    background: var(--bg-secondary);
    color: var(--text-primary) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--accent-color);
    color: #fff !important;
}

/* Loading */
.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid var(--bg-primary);
    border-top: 5px solid var(--accent-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }

/* =================== LAYOUT PRINCIPAL (MENU / HEADER) =================== */
/* Sidebar */
.sidebar {
    height: 100%;
    width: var(--mini-sidebar-width);
    position: fixed;
    top: 0;
    left: 0;
    background-color: var(--bg-tertiary);
    overflow: hidden;
    transition: var(--transition);
    padding-top: var(--header-height);
    z-index: 1000;
    box-shadow: 4px 0 10px rgba(0,0,0,0.1);
}
.sidebar:hover,
.sidebar.active {
    width: var(--sidebar-width);
}
.sidebar a, .dropdown-btn {
    padding: 12px 20px;
    text-decoration: none;
    font-size: 14px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    white-space: nowrap;
    transition: var(--transition);
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
}
.sidebar i { min-width: 20px; margin-right: 30px; }
.sidebar a:hover, .dropdown-btn:hover {
    color: var(--text-primary);
    background-color: rgba(255,255,255,0.06);
}

/* Dropdown */
.dropdown-btn { position: relative; width: 100%; text-align: left; padding: 12px 20px; color: var(--text-secondary); display: flex; align-items: center; transition: var(--transition); }
.dropdown-container {
    display: none;
    background-color: rgba(0,0,0,0.12);
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transform-origin: top;
    transform: scaleY(0.96);
    transition: all .25s cubic-bezier(0.4,0,0.2,1);
}
.dropdown-btn:hover + .dropdown-container,
.dropdown-container:hover {
    display: block;
    max-height: 500px;
    opacity: 1;
    transform: scaleY(1);
}
.dropdown-container a {
    position: relative;
    padding: 10px 20px 10px 55px;
    display: flex;
    align-items: center;
    transform: translateY(-5px);
    opacity: 0;
    transition: all .25s cubic-bezier(0.4,0,0.2,1);
}
.dropdown-btn:hover + .dropdown-container a,
.dropdown-container:hover a {
    transform: translateY(0);
    opacity: 1;
}
.dropdown-container a:nth-child(1){ transition-delay:.05s;}
.dropdown-container a:nth-child(2){ transition-delay:.1s;}
.dropdown-container a:nth-child(3){ transition-delay:.15s;}
.dropdown-container a:nth-child(4){ transition-delay:.2s;}
.dropdown-btn .fa-chevron-down { margin-left: auto; font-size: .8em; transition: transform .25s cubic-bezier(0.4,0,0.2,1); }
.dropdown-btn:hover .fa-chevron-down { transform: rotate(180deg); }
.dropdown-container a:before {
    content:'';
    position:absolute; left:0; top:0; height:100%; width:0;
    background-color: rgba(255,255,255,0.04);
    transition: width .25s cubic-bezier(0.4,0,0.2,1);
}
.dropdown-container a:hover:before { width: 100%; }
.dropdown-container a i { margin-right:10px; width:20px; text-align:center; opacity:.85; transition: opacity .25s ease; }
.dropdown-container a:hover i { opacity: 1; }

/* Header fixo (logo) */
#system-name {
    position: fixed !important;
    top: 0 !important;
    /* left: var(--mini-sidebar-width) !important; */
    right: 0 !important;
    height: var(--header-height) !important;
    background-color: var(--bg-primary) !important;
    display: flex !important;
    align-items: center !important;
    padding: 0 20px !important;
    z-index: 900 !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
    transition: var(--transition) !important;
}
.sidebar:hover ~ #system-name,
.sidebar.active ~ #system-name {
    left: var(--sidebar-width);
}

/* Header direito (welcome / ações) */
#welcome-section {
    position: fixed !important;
    top: 0 !important;
    right: 0 !important;
    height: var(--header-height) !important;
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
    padding: 0 20px !important;
    z-index: 901 !important;
}
.mode-switch {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 8px;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    border-radius: 50%;
    width: 38px;
}
.mode-switch:hover { background-color: rgba(0,0,0,0.06); }
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background-color: rgba(0,0,0,0.06);
    padding: 5px 12px 5px 5px;
    border-radius: 50px;
    text-decoration: none;
    transition: transform .2s ease;
}
.user-info:hover { transform: translateY(-1px); text-decoration: none; }
.user-avatar {
    width: 35px; height: 35px;
    background-color: #2563EB; color: #fff;
    border-radius: 50%;
    display:flex; align-items:center; justify-content:center;
    font-weight: 600; font-size: 16px;
}
.user-details { display: flex; flex-direction: column; line-height: 1.2; }
.user-role { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.user-type { font-size: 13px; color: var(--text-secondary); }

.logout-button {
    background-color: #DC2626;
    color: #fff;
    padding: 8px 16px;
    border-radius: 50px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background-color .2s;
}
.logout-button:hover { background-color: #B91C1C; color: #fff; }
.logout-button i { font-size: 14px; text-decoration: none; }

/* Conteúdo principal quando existir numa página com menu */
.main-content {
    /* margin-left: var(--mini-sidebar-width); */
    padding: calc(var(--header-height) + 20px) 20px 20px;
    transition: var(--transition);
}
.sidebar:hover ~ .main-content,
.sidebar.active ~ .main-content {
    margin-left: var(--sidebar-width);
}

/* =================== MOBILE / RESPONSIVO =================== */
.nav-toggle {
    display: none;
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 10px;
    transition: color .3s ease;
}
.nav-toggle:hover { color: var(--text-secondary); }

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 998;
}
.sidebar-overlay.active { display: block; }

/* Correções de tema explícitas */
body.dark-mode .card { background-color: #3b4149; }
body.dark-mode .card-header { background-color: rgb(255 0 0 / 3%); }

/* Header/mobile */
@media (max-width: 768px) {
    #system-name {
        display: flex !important;
        align-items: center !important;
        padding: 10px !important;
        position: fixed !important;
        top: 0 !important; left: 0 !important; right: 0 !important;
        background-color: var(--bg-primary) !important;
        z-index: 1000 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }
    #welcome-section {
        position: fixed !important;
        top: 0 !important; right: 0 !important;
        padding: 10px !important;
        z-index: 1001 !important;
        display: flex !important;
        gap: 10px !important;
    }
    .nav-toggle { display: block; }
    .mode-switch { padding: 8px; background: none; border: none; color: var(--text-primary); font-size: 1.2rem; }
    .user-info { display: none; } /* compacta o header em telas pequenas */
    .logout-button {
        background-color: #DC2626 !important;
        color: #fff !important;
        padding: 8px !important;
        border: none !important;
        cursor: pointer !important;
        font-size: .87rem !important;
    }
    /* Sidebar móvel abre sobre o conteúdo */
    .sidebar {
        position: fixed;
        left: -280px;
        top: 0; bottom: 0;
        width: 280px;
        transition: all .3s ease;
        z-index: 999;
        padding-top: var(--header-height);
    }
    .sidebar.active { left: 0; }
    .sidebar:hover { width: 280px; } /* impede expand por hover no mobile */
    /* Conteúdo empurrado corretamente */
    .main-content {
        margin-left: 0;
        padding-top: calc(var(--header-height) + 12px);
    }
}

/* Pequenos ajustes de contraste em light mode explícito */
body.light-mode { background-color: var(--bg-secondary); color: var(--text-primary); }

        /* =======================================================================
           HERO / TÍTULO
        ======================================================================= */
        .page-hero{
          background: linear-gradient(180deg, rgba(79,70,229,.12), rgba(79,70,229,0));
          border-radius: 18px;
          padding: 18px 18px 6px 18px;
          margin: 20px 0 12px;
          box-shadow: var(--soft-shadow);
        }
        .page-hero .title-row{ display:flex;align-items:center;gap:14px;flex-wrap:wrap; }
        .page-hero .title-icon{
          width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;
          display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .page-hero .title-icon{ background:#262f3b;color:#c7d2fe; }
        .page-hero h1{ font-size: clamp(1.25rem, .9rem + 2vw, 1.75rem); font-weight: 800;margin:0;letter-spacing:.2px; }
        .page-hero .subtitle{ font-size:.95rem;margin-top:2px; }
</style>
