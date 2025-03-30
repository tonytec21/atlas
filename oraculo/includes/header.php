<?php  
$config = include __DIR__ . '/../config.php';  
$basePath = $config['base_path'];  
?>  
<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Oráculo Atlas</title>  
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='0.9em' font-size='90'>🔮</text></svg>">  
    <!-- Bootstrap Icons -->  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">  
    <!-- Google Fonts -->  
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">  
    <!-- Highlight.js para formatação de código -->  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>  
    <style>  
        :root {  
            /* Esquema de cores principal */  
            --primary: 111, 76, 255; /* #6F4CFF */  
            --primary-light: 140, 115, 255; /* #8C73FF */  
            --primary-dark: 88, 60, 204; /* #583CCC */  
            
            /* Cores de interface */  
            --surface: 255, 255, 255; /* #FFFFFF */  
            --surface-alt: 248, 250, 252; /* #F8FAFC */  
            --bg: 240, 242, 245; /* #F0F2F5 */  
            
            /* Cores de texto */  
            --text: 30, 41, 59; /* #1E293B */  
            --text-secondary: 71, 85, 105; /* #475569 */  
            --text-tertiary: 148, 163, 184; /* #94A3B8 */  
            
            /* Cores de destaque */  
            --success: 34, 197, 94; /* #22C55E */  
            --warning: 245, 158, 11; /* #F59E0B */  
            --error: 239, 68, 68; /* #EF4444 */  
            --info: 56, 189, 248; /* #38BDF8 */  
            
            /* Cores de borda e superfície */  
            --border: 226, 232, 240; /* #E2E8F0 */  
            --divider: 241, 245, 249; /* #F1F5F9 */  
            
            /* Sombras */  
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);  
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);  
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);  
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);  
            --shadow-focus: 0 0 0 4px rgba(var(--primary), 0.25);  
            
            /* Elevações */  
            --z-header: 100;  
            --z-settings: 90;  
            --z-dropdown: 80;  
            --z-overlay: 70;  
            
            /* Raios de borda */  
            --radius-sm: 0.375rem;  
            --radius-md: 0.5rem;  
            --radius-lg: 0.75rem;  
            --radius-xl: 1rem;  
            --radius-2xl: 1.25rem;  
            --radius-full: 9999px;  
            
            /* Animações e transições */  
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);  
            --transition-normal: 250ms cubic-bezier(0.4, 0, 0.2, 1);  
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);  
            
            /* Tamanhos */  
            --header-height: 4rem;  
            --max-content-width: 1280px;  
            --settings-height: 0px;  
        }  
        
        /* Configuração para tema escuro - pronto para ser implementado */  
        [data-theme="dark"] {  
            --surface: 15, 23, 42; /* #0F172A */  
            --surface-alt: 30, 41, 59; /* #1E293B */  
            --bg: 12, 18, 32; /* #0C1220 */  
            
            --text: 226, 232, 240; /* #E2E8F0 */  
            --text-secondary: 148, 163, 184; /* #94A3B8 */  
            --text-tertiary: 100, 116, 139; /* #64748B */  
            
            --border: 51, 65, 85; /* #334155 */  
            --divider: 30, 41, 59; /* #1E293B */  
        }  

        * {  
            box-sizing: border-box;  
            margin: 0;  
            padding: 0;  
        }  
        
        body {  
            font-family: 'DM Sans', -apple-system, system-ui, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;  
            line-height: 1.5;  
            color: rgb(var(--text));  
            background-color: rgb(var(--bg));  
            height: 100vh;  
            overflow: hidden;  
        }  
        
        /* Scroll customizado */  
        ::-webkit-scrollbar {  
            width: 6px;  
            height: 6px;  
        }  
        
        ::-webkit-scrollbar-track {  
            background: rgba(var(--border), 0.5);  
            border-radius: var(--radius-full);  
        }  
        
        ::-webkit-scrollbar-thumb {  
            background: rgba(var(--primary), 0.5);  
            border-radius: var(--radius-full);  
        }  
        
        ::-webkit-scrollbar-thumb:hover {  
            background: rgba(var(--primary), 0.7);  
        }  
        
        .container {  
            height: 100vh;  
            max-width: var(--max-content-width);  
            margin: 0 auto;  
            padding: 1rem;  
            display: flex;  
            flex-direction: column;  
        }  
        
        .chat-container {  
            margin: 0 auto;  
            width: 100%;  
            display: flex;  
            flex-direction: column;  
            height: 100%;  
            border-radius: var(--radius-xl);  
            overflow: hidden;  
            background-color: rgb(var(--surface));  
            box-shadow: var(--shadow-lg);  
            position: relative;  
        }  
        
        .chat-header {  
            background: linear-gradient(135deg, rgb(var(--primary)), rgb(var(--primary-dark)));  
            color: white;  
            padding: 0 1.5rem;  
            height: var(--header-height);  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            position: relative;  
            z-index: var(--z-header);  
        }  
        
        .logo-container {  
            display: flex;  
            align-items: center;  
            gap: 0.75rem;  
        }  
        
        .logo-placeholder {  
            width: 2.5rem;  
            height: 2.5rem;  
            background: rgba(255, 255, 255, 0.15);  
            color: white;  
            border-radius: var(--radius-lg);  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            font-weight: 700;  
            font-size: 1.125rem;  
            font-family: 'Plus Jakarta Sans', sans-serif;  
            backdrop-filter: blur(10px);  
        }  
        
        .chat-title {  
            font-family: 'Plus Jakarta Sans', sans-serif;  
            font-size: 1.25rem;  
            font-weight: 700;  
            letter-spacing: -0.01em;  
        }  
        
        .chat-messages-container {  
            height: calc(95% - var(--header-height));  
            display: flex;  
            flex-direction: column;  
            overflow: hidden;  
            position: relative;  
        }  
        
        .chat-messages {  
            flex: 1;  
            padding: 1.5rem 1.5rem 2rem;  
            overflow-y: auto;  
            background-color: rgb(var(--surface-alt));  
            transition: height var(--transition-normal);  
            scroll-behavior: smooth;  
            height: calc(100% - var(--settings-height));  
        }  
        
        .message {  
            margin-bottom: 1.5rem;  
            display: flex;  
            animation: fadeInUp 0.3s ease forwards;  
            opacity: 0;  
            transform: translateY(10px);  
        }  
        
        @keyframes fadeInUp {  
            to {  
                opacity: 1;  
                transform: translateY(0);  
            }  
        }  
        
        .message.user {  
            justify-content: flex-end;  
        }  
        
        .message.assistant {  
            justify-content: flex-start;  
        }  
        
        .message-content {  
            max-width: 80%;  
            padding: 1rem 1.25rem;  
            border-radius: var(--radius-xl);  
            box-shadow: var(--shadow-sm);  
            position: relative;  
        }  
        
        .user .message-content {  
            background: linear-gradient(135deg, rgb(var(--primary)), rgb(var(--primary-light)));  
            color: white;  
            border-bottom-right-radius: 0;  
        }  
        
        .assistant .message-content {  
            background-color: rgb(var(--surface));  
            border-bottom-left-radius: 0;  
            color: rgb(var(--text));  
            max-width: 85%;  
        }  

        /* Formatação elegante para respostas da IA */  
        .message-content p {  
            margin-bottom: 0.75rem;  
            line-height: 1.6;  
        }  
        
        .message-content p:last-child {  
            margin-bottom: 0;  
        }  

        .message-content h1,   
        .message-content h2,   
        .message-content h3,   
        .message-content h4 {  
            margin-top: 1.5rem;  
            margin-bottom: 0.75rem;  
            font-family: 'Plus Jakarta Sans', sans-serif;  
            font-weight: 600;  
            color: rgb(var(--primary));  
            line-height: 1.3;  
        }  

        .message-content h1 {  
            font-size: 1.5rem;  
            border-bottom: 1px solid rgba(var(--border), 0.5);  
            padding-bottom: 0.5rem;  
        }  

        .message-content h2 {  
            font-size: 1.25rem;  
        }  

        .message-content h3 {  
            font-size: 1.125rem;  
        }  

        .message-content h4 {  
            font-size: 1rem;  
        }  

        .message-content ul,   
        .message-content ol {  
            margin: 0.75rem 0;  
            padding-left: 1.5rem;  
        }  

        .message-content li {  
            margin-bottom: 0.5rem;  
        }  

        .message-content table {  
            width: 100%;  
            border-collapse: collapse;  
            margin: 1rem 0;  
            font-size: 0.9rem;  
        }  

        .message-content table th,  
        .message-content table td {  
            padding: 0.5rem 0.75rem;  
            border: 1px solid rgba(var(--border), 0.7);  
            text-align: left;  
        }  

        .message-content table th {  
            background-color: rgba(var(--primary), 0.1);  
            font-weight: 600;  
        }  

        .message-content table tr:nth-child(2n) {  
            background-color: rgba(var(--primary), 0.03);  
        }  
        
        .message-content a {  
            color: rgb(var(--primary));  
            text-decoration: none;  
            position: relative;  
            white-space: nowrap;  
            font-weight: 500;  
        }  

        .user .message-content a {  
            color: white;  
            text-decoration: underline;  
            text-decoration-color: rgba(255, 255, 255, 0.5);  
            text-underline-offset: 2px;  
        }  
        
        .message-content a::after {  
            content: "";  
            position: absolute;  
            bottom: -1px;  
            left: 0;  
            width: 100%;  
            height: 1px;  
            background-color: currentColor;  
            transform: scaleX(0);  
            transform-origin: right;  
            transition: transform 0.3s ease;  
        }  
        
        .message-content a:hover::after {  
            transform: scaleX(1);  
            transform-origin: left;  
        }  
        
        .message-content pre {  
            background-color: rgb(23, 23, 23);  
            color: rgb(var(--surface-alt));  
            padding: 1rem;  
            border-radius: var(--radius-md);  
            overflow-x: auto;  
            margin: 1rem 0;  
            position: relative;  
        }  
        
        .message-content pre code {  
            background-color: transparent;  
            padding: 0;  
            font-size: 0.85rem;  
            color: inherit;  
            font-family: 'Fira Code', 'Roboto Mono', monospace;  
            line-height: 1.5;  
        }  

        .message-content pre::before {  
            content: attr(data-language);  
            position: absolute;  
            top: 0;  
            right: 0;  
            font-size: 0.7rem;  
            background-color: rgba(255, 255, 255, 0.1);  
            padding: 0.25rem 0.5rem;  
            border-bottom-left-radius: var(--radius-sm);  
            font-family: 'DM Sans', sans-serif;
            text-transform: uppercase;  
            letter-spacing: 0.05em;  
            font-weight: 600;  
        }  

        .message-content code:not(pre code) {  
            background-color: rgba(var(--primary), 0.1);  
            padding: 0.2rem 0.4rem;  
            border-radius: var(--radius-sm);  
            font-family: 'Fira Code', 'Roboto Mono', monospace;  
            font-size: 0.85em;  
            color: rgb(var(--primary-dark));  
            white-space: nowrap;  
        }  
        
        /* Blockquotes e citações */  
        .message-content blockquote {  
            border-left: 4px solid rgba(var(--primary), 0.5);  
            padding: 0.5rem 0 0.5rem 1rem;  
            margin: 1rem 0;  
            background-color: rgba(var(--primary), 0.05);  
            border-radius: 0 var(--radius-md) var(--radius-md) 0;  
        }  

        .citation {  
            background-color: rgba(var(--primary), 0.05);  
            border-left: 3px solid rgb(var(--primary));  
            padding: 1rem 1.25rem;  
            margin: 1rem 0;  
            border-radius: var(--radius-md);  
        }  
        
        .citation-text {  
            font-style: italic;  
            margin-bottom: 0.75rem;  
            color: rgb(var(--text));  
            line-height: 1.6;  
        }  
        
        .citation-source {  
            display: flex;  
            align-items: center;  
            font-size: 0.8rem;  
            color: rgb(var(--text-secondary));  
        }  
        
        .citation-source a {  
            display: inline-flex;  
            align-items: center;  
            gap: 0.25rem;  
            margin-left: 0.5rem;  
        }  

        .citation-source a i {  
            font-size: 0.9rem;  
        }  
        
        /* Indicador de digitação animado */  
        .typing-container {  
            display: flex;  
            flex-direction: column;  
            align-items: flex-start;  
            margin-bottom: 1.5rem;  
            opacity: 0;  
            animation: fadeIn 0.3s forwards;  
        }  

        @keyframes fadeIn {  
            to { opacity: 1; }  
        }  
        
        .typing-indicator {  
            display: flex;  
            align-items: center;  
            padding: 0.75rem 1.25rem;  
            background-color: rgb(var(--surface));  
            border-radius: var(--radius-xl);  
            border-bottom-left-radius: 0;  
            box-shadow: var(--shadow-sm);  
        }  
        
        .typing-indicator span {  
            height: 0.5rem;  
            width: 0.5rem;  
            margin: 0 0.15rem;  
            display: block;  
            border-radius: var(--radius-full);  
            background-color: rgba(var(--primary), 0.7);  
            opacity: 0.6;  
        }  
        
        .typing-indicator span:nth-of-type(1) {  
            animation: typing 1.1s infinite 0s;  
        }  
        
        .typing-indicator span:nth-of-type(2) {  
            animation: typing 1.1s infinite 0.2s;  
        }  
        
        .typing-indicator span:nth-of-type(3) {  
            animation: typing 1.1s infinite 0.4s;  
        }  
        
        @keyframes typing {  
            0% { transform: translateY(0); }  
            50% { transform: translateY(-0.5rem); }  
            100% { transform: translateY(0); }  
        }  
        
        /* Campo de entrada e botões */  
        .chat-input {  
            padding: 1rem 1.5rem;  
            background-color: rgb(var(--surface));  
            border-top: 1px solid rgba(var(--border), 0.7);  
            position: relative;  
            z-index: 5;  
        }  
        
        .chat-form {  
            display: flex;  
            gap: 0.75rem;  
            position: relative;  
        }  
        
        .chat-input-field {  
            flex: 1;  
            border: 1px solid rgba(var(--border), 0.7);  
            border-radius: var(--radius-full);  
            padding: 0.875rem 1.25rem;  
            padding-right: 3rem;  
            font-size: 1rem;  
            outline: none;  
            transition: all var(--transition-normal);  
            box-shadow: var(--shadow-sm);  
            font-family: inherit;  
            background-color: rgb(var(--surface-alt));  
            color: rgb(var(--text));  
        }  
        
        .chat-input-field:focus {  
            border-color: rgb(var(--primary-light));  
            box-shadow: var(--shadow-focus);  
        }  

        .chat-input-field::placeholder {  
            color: rgb(var(--text-tertiary));  
        }  
        
        .chat-submit {  
            position: absolute;  
            right: 0.5rem;  
            top: 50%;  
            transform: translateY(-50%);  
            color: rgb(var(--primary));  
            border: none;  
            border-radius: var(--radius-full);  
            width: 2.5rem;  
            height: 2.5rem;  
            font-size: 1.25rem;  
            cursor: pointer;  
            transition: all var(--transition-fast);  
            background: rgb(var(--primary));  
            color: white;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
        }  
        
        .chat-submit:hover {  
            transform: translateY(-50%) scale(1.05);  
            box-shadow: 0 0 0 5px rgba(var(--primary), 0.2);  
        }  

        .chat-submit:active {  
            transform: translateY(-50%) scale(0.95);  
        }  

        .chat-submit i {  
            transition: transform var(--transition-fast);  
        }  

        .chat-submit:hover i {  
            transform: translateX(2px);  
        }  
        
        /* Loading indicator na área de mensagens */  
        #loading-indicator {  
            display: flex;  
            align-items: center;  
            margin-top: 0.75rem;  
            font-size: 0.875rem;  
            color: rgb(var(--text-secondary));  
            gap: 0.5rem;  
            justify-content: center;  
        }  
        
        .d-none {  
            display: none !important;  
        }  
        
        /* Controles da UI */  
        .chat-controls {  
            display: flex;  
            gap: 0.75rem;  
        }  
        
        .btn {  
            padding: 0.5rem 1rem;  
            border-radius: var(--radius-full);  
            font-size: 0.875rem;  
            cursor: pointer;  
            transition: all var(--transition-normal);  
            font-weight: 500;  
            display: flex;  
            align-items: center;  
            gap: 0.375rem;  
            border: none;  
            background: none;  
            font-family: inherit;  
        }  
        
        .btn-glass {  
            color: white;  
            background: rgba(255, 255, 255, 0.15);  
            backdrop-filter: blur(10px);  
            border: 1px solid rgba(255, 255, 255, 0.2);  
        }  
        
        .btn-glass:hover {  
            background: rgba(255, 255, 255, 0.25);  
            border-color: rgba(255, 255, 255, 0.3);  
            transform: translateY(-1px);  
        }  

        .btn-glass:active {  
            transform: translateY(1px);  
        }  
        
        .btn-glass.danger:hover {  
            background: rgba(239, 68, 68, 0.25);  
            border-color: rgba(239, 68, 68, 0.3);  
        }  

        /* Status do sistema */  
        .status {  
            display: flex;  
            align-items: center;  
            font-size: 0.75rem;  
            background: rgba(255, 255, 255, 0.15);  
            backdrop-filter: blur(10px);  
            padding: 0.25rem 0.75rem;  
            border-radius: var(--radius-full);  
            font-weight: 500;  
            margin-left: 1rem;  
            border: 1px solid rgba(255, 255, 255, 0.2);  
        }  
        
        .status-indicator {  
            width: 8px;  
            height: 8px;  
            border-radius: var(--radius-full);  
            background-color: rgb(var(--success));  
            margin-right: 0.5rem;  
            position: relative;  
        }  

        .status-indicator::after {  
            content: '';  
            position: absolute;  
            width: 100%;  
            height: 100%;  
            border-radius: 50%;  
            background-color: rgb(var(--success));  
            opacity: 0.4;  
            animation: pulse 1.5s ease-in-out infinite;  
        }  
        
        .status-indicator.thinking {  
            background-color: rgb(var(--warning));  
        }  
        
        .status-indicator.thinking::after {  
            background-color: rgb(var(--warning));  
        }  
        
        @keyframes pulse {  
            0% { transform: scale(1); opacity: 0.5; }  
            50% { transform: scale(2.5); opacity: 0; }  
            100% { transform: scale(1); opacity: 0; }  
        }  
        
        /* Painel de configurações animado */  
        .settings-panel {  
            background-color: rgb(var(--surface));  
            border-bottom: 1px solid rgba(var(--border), 0.7);  
            transition: max-height var(--transition-normal), opacity var(--transition-normal);  
            max-height: 0;  
            overflow: hidden;  
            opacity: 0;  
            padding: 0 1.5rem;  
        }  

        .settings-panel.show {  
            max-height: 350px;  
            opacity: 1;  
            padding: 1.5rem;  
        }  
        
        .settings-content {  
            display: flex;  
            flex-direction: column;  
            gap: 1.5rem;  
        }  

        .settings-section {  
            display: flex;  
            flex-direction: column;  
            gap: 1rem;  
        }  
        
        .settings-panel h5 {  
            font-family: 'Plus Jakarta Sans', sans-serif;  
            font-size: 1rem;  
            color: rgb(var(--text));  
            font-weight: 600;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
        }  

        .settings-panel h5 i {  
            color: rgb(var(--primary));  
            font-size: 1.125rem;  
        }  
        
        /* Radio buttons customizados */  
        .options-group {  
            display: flex;  
            gap: 1rem;  
            flex-wrap: wrap;  
        }  

        .option-card {  
            flex: 1;  
            min-width: 150px;  
        }  

        .option-card input[type="radio"] {  
            display: none;  
        }  

        .option-card label {  
            display: flex;  
            flex-direction: column;  
            gap: 0.5rem;  
            padding: 1rem;  
            border: 1px solid rgba(var(--border), 0.7);  
            border-radius: var(--radius-lg);  
            cursor: pointer;  
            transition: all var(--transition-normal);  
        }  

        .option-card input[type="radio"]:checked + label {  
            border-color: rgb(var(--primary));  
            background-color: rgba(var(--primary), 0.05);  
            box-shadow: var(--shadow-sm);  
        }  

        .option-title {  
            font-weight: 600;  
            color: rgb(var(--text));  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
        }  

        .option-title i {  
            color: rgb(var(--primary));  
        }  

        .option-card input[type="radio"]:checked + label .option-title {  
            color: rgb(var(--primary));  
        }  

        .option-description {  
            font-size: 0.8rem;  
            color: rgb(var(--text-secondary));  
            line-height: 1.4;  
        }  
        
        /* Layout para campos de localização */  
        .location-grid {  
            display: grid;  
            grid-template-columns: repeat(3, 1fr);  
            gap: 1rem;  
        }  

        .input-group {  
            display: flex;  
            flex-direction: column;  
            gap: 0.5rem;  
        }  

        .input-group label {  
            font-size: 0.85rem;  
            color: rgb(var(--text-secondary));  
            font-weight: 500;  
        }  

        .input-field {  
            width: 100%;  
            padding: 0.75rem 1rem;  
            border-radius: var(--radius-md);  
            border: 1px solid rgba(var(--border), 0.7);  
            font-family: inherit;  
            font-size: 0.9rem;  
            transition: all var(--transition-normal);  
            background-color: rgb(var(--surface-alt));  
            color: rgb(var(--text));  
        }  

        .input-field:focus {  
            border-color: rgb(var(--primary));  
            box-shadow: var(--shadow-focus);  
            outline: none;  
        }  

        .input-field::placeholder {  
            color: rgb(var(--text-tertiary));  
        }  

        /* Badges e elementos decorativos */  
        .badge {  
            display: inline-flex;  
            align-items: center;  
            padding: 0.25rem 0.5rem;  
            border-radius: var(--radius-full);  
            font-size: 0.7rem;  
            font-weight: 500;  
            text-transform: uppercase;  
            letter-spacing: 0.025em;  
        }  

        .badge-primary {  
            background-color: rgba(var(--primary), 0.1);  
            color: rgb(var(--primary));  
        }  

        .badge-success {  
            background-color: rgba(var(--success), 0.1);  
            color: rgb(var(--success));  
        }  

        /* Adaptações para responsividade */  
        @media (max-width: 768px) {  
            .container {  
                padding: 0;  
                height: 100vh;  
            }  
            
            .chat-container {  
                height: 100%;  
                max-width: 100%;  
                margin: 0;  
                width: 100%;  
                border-radius: 0;  
            }  
            
            .message-content {  
                max-width: 90%;  
            }  

            .logo-placeholder {  
                width: 2rem;  
                height: 2rem;  
                font-size: 1rem;  
            }  

            .chat-title {  
                font-size: 1.125rem;  
            }  

            .btn {  
                padding: 0.4rem 0.75rem;  
                font-size: 0.8rem;  
            }  

            .chat-header {  
                padding: 0 1rem;  
                height: 3.5rem;  
            }  

            .settings-panel {  
                padding: 0 1rem;  
            }  

            .settings-panel.show {  
                padding: 1rem;  
            }  

            .message-content {  
                padding: 0.875rem 1rem;  
            }  

            .chat-messages {  
                padding: 1rem 1rem 1.5rem;  
            }  

            .chat-input {  
                padding: 0.75rem 1rem;  
            }  

            .citation {  
                padding: 0.75rem 1rem;  
            }  

            .location-grid {  
                grid-template-columns: 1fr;  
                gap: 0.75rem;  
            }  

            .options-group {  
                flex-direction: column;  
                gap: 0.5rem;  
            }  

            .option-card {  
                width: 100%;  
            }  

            .status {  
                display: none;  
            }  
        }  

        @media (max-width: 480px) {  
            .chat-controls {  
                gap: 0.5rem;  
            }  

            .btn i {  
                font-size: 1rem;  
            }  

            .btn span {  
                display: none;  
            }  

            .btn {  
                width: 2.5rem;  
                height: 2.5rem;  
                padding: 0;  
                justify-content: center;  
            }  
        }  

        /* Utilidades */  
        .divider {  
            width: 100%;  
            height: 1px;  
            background-color: rgba(var(--border), 0.5);  
            margin: 0.5rem 0;  
        }  

        .highlight {  
            color: rgb(var(--primary));  
            font-weight: 500;  
        }  
    </style>  
    
    <!-- Scripts -->  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>  
</head>  
<body>  
    <div class="container">  
        <div class="chat-container">  
            <div class="chat-header">  
                <div class="logo-container">  
                    <div class="logo-placeholder">🔮</div>  
                    <h1 class="chat-title">Oráculo Atlas</h1>  
                    <div class="status">  
                        <div class="status-indicator"></div>  
                        <span>Online</span>  
                    </div>  
                </div>  
                <div class="chat-controls">  
                    <button id="toggle-settings" class="btn btn-glass">  
                        <i class="bi bi-sliders"></i>  
                        <span>Configurar</span>  
                    </button>  
                    <button id="clear-chat" class="btn btn-glass danger">  
                        <i class="bi bi-trash3"></i>  
                        <span>Limpar</span>  
                    </button>  
                </div>  
            </div>  
            
            <div class="chat-messages-container">  
                <!-- Painel de configurações avançado -->  
                <div id="settings-panel" class="settings-panel">  
                    <div class="settings-content">  
                        <div class="settings-section">  
                            <h5><i class="bi bi-search"></i> Modo de busca</h5>  
                            <div class="options-group">  
                                <div class="option-card">  
                                    <input type="radio" name="search_context_size" id="context-small" value="small">  
                                    <label for="context-small">  
                                        <div class="option-title"><i class="bi bi-lightning-charge"></i> Rápido</div>  
                                        <div class="option-description">Respostas concisas e diretas com tempo de processamento mínimo</div>  
                                    </label>  
                                </div>  
                                <div class="option-card">  
                                    <input type="radio" name="search_context_size" id="context-medium" value="medium" checked>  
                                    <label for="context-medium">  
                                        <div class="option-title"><i class="bi bi-check2-circle"></i> Equilibrado</div>  
                                        <div class="option-description">Balanço ideal entre velocidade e profundidade nas respostas</div>  
                                    </label>  
                                </div>  
                                <div class="option-card">  
                                    <input type="radio" name="search_context_size" id="context-large" value="large">  
                                    <label for="context-large">  
                                        <div class="option-title"><i class="bi bi-stars"></i> Detalhado</div>  
                                        <div class="option-description">Respostas aprofundadas com máximo de contexto e informação</div>  
                                    </label>  
                                </div>  
                            </div>  
                        </div>  

                        <div class="settings-section">  
                            <h5><i class="bi bi-geo-alt"></i> Sua localização</h5>  
                            <div class="location-grid">  
                                <div class="input-group">  
                                    <label for="city">Cidade</label>  
                                    <input type="text" id="city" placeholder="São Paulo" class="input-field">  
                                </div>  
                                <div class="input-group">  
                                    <label for="region">Estado</label>  
                                    <input type="text" id="region" placeholder="SP" class="input-field">  
                                </div>  
                                <div class="input-group">  
                                    <label for="country">País</label>  
                                    <input type="text" id="country" placeholder="Brasil" class="input-field">  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                </div>  

                <div class="chat-messages" id="chat-messages">  
                    <div class="message assistant">  
                        <div class="message-content">  
                            <p>👋 Olá! Sou o <span class="highlight">Oráculo Atlas</span>, seu assistente virtual com acesso à internet. Como posso ajudar você hoje?</p>  
                            <p>Você pode me perguntar sobre notícias atuais, pesquisar informações ou pedir ajuda com qualquer tópico. Estou aqui para fornecer respostas precisas e atualizadas.</p>  
                        </div>  
                    </div>  
                </div>  
                
                <div class="chat-input">  
                    <form id="message-form" class="chat-form">  
                        <input type="text" id="message-input" class="chat-input-field" placeholder="Digite sua mensagem..." autocomplete="off">  
                        <button type="submit" class="chat-submit">  
                            <i class="bi bi-send-fill"></i>  
                        </button>  
                    </form>  
                </div>  
            </div>  
        </div>  
    </div>  

   