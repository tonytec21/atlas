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
    <style>  
        :root {  
            --primary-color: #007bff;  
            --secondary-color: #6c757d;  
            --light-color: #f8f9fa;  
            --dark-color: #343a40;  
            --success-color: #28a745;  
            --border-color: #dee2e6;  
            --bg-color: #ffffff;  
            --text-color: #212529;  
        }  
        
        * {  
            box-sizing: border-box;  
            margin: 0;  
            padding: 0;  
        }  
        
        body {  
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;  
            line-height: 1.6;  
            color: var(--text-color);  
            background-color: var(--bg-color);  
            height: 100vh;  
            display: flex;  
            flex-direction: column;  
        }  
        
        .chat-container {  
            max-width: 1200px;  
            margin: 0 auto;  
            width: 100%;  
            display: flex;  
            flex-direction: column;  
            height: 100%;  
            box-shadow: 0 0 10px rgba(0,0,0,0.1);  
            border-radius: 8px;  
            overflow: hidden;  
        }  
        
        .chat-header {  
            background-color: var(--primary-color);  
            color: white;  
            padding: 15px 20px;  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
        }  
        
        .logo-container {  
            display: flex;  
            align-items: center;  
        }  
        
        .logo-placeholder {  
            width: 40px;  
            height: 40px;  
            background-color: rgba(255, 255, 255, 0.2);  
            color: white;  
            border-radius: 50%;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            font-weight: bold;  
            font-size: 16px;  
            margin-right: 10px;  
        }  
        
        .chat-title {  
            font-size: 18px;  
            font-weight: 600;  
        }  
        
        .status {  
            display: flex;  
            align-items: center;  
            font-size: 14px;  
        }  
        
        .status-indicator {  
            width: 10px;  
            height: 10px;  
            border-radius: 50%;  
            background-color: var(--success-color);  
            margin-right: 5px;  
        }  
        
        .status-indicator.thinking {  
            background-color: #ff9800;  
            box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.2);  
        }  
        
        .chat-messages {  
            flex: 1;  
            padding: 20px;  
            overflow-y: auto;  
            background-color: #f5f7f9;  
        }  
        
        .message {  
            margin-bottom: 20px;  
            display: flex;  
            flex-direction: column;  
        }  
        
        .message.user {  
            align-items: flex-end;  
        }  
        
        .message.assistant {  
            align-items: flex-start;  
        }  
        
        .message-content {  
            max-width: 80%;  
            padding: 12px 16px;  
            border-radius: 18px;  
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);  
            font-size: 15px;  
        }  
        
        .user .message-content {  
            background-color: var(--primary-color);  
            color: white;  
            border-bottom-right-radius: 4px;  
        }  
        
        .assistant .message-content {  
            background-color: white;  
            border-bottom-left-radius: 4px;  
        }  
        
        .typing-indicator {  
            display: flex;  
            align-items: center;  
            margin-bottom: 20px;  
        }  
        
        .typing-indicator span {  
            height: 8px;  
            width: 8px;  
            float: left;  
            margin: 0 1px;  
            background-color: #9E9EA1;  
            display: block;  
            border-radius: 50%;  
            opacity: 0.4;  
        }  
        
        .typing-indicator span:nth-of-type(1) {  
            animation: 1s typing infinite 0s;  
        }  
        
        .typing-indicator span:nth-of-type(2) {  
            animation: 1s typing infinite 0.2s;  
        }  
        
        .typing-indicator span:nth-of-type(3) {  
            animation: 1s typing infinite 0.4s;  
        }  
        
        @keyframes typing {  
            0% { transform: translateY(0px); }  
            33% { transform: translateY(-5px); }  
            66% { transform: translateY(5px); }  
            100% { transform: translateY(0px); }  
        }  
        
        @keyframes pulse {  
            0% { transform: scale(1); opacity: 1; }  
            50% { transform: scale(1.1); opacity: 0.7; }  
            100% { transform: scale(1); opacity: 1; }  
        }  
        
        .message-content p {  
            margin-bottom: 10px;  
        }  
        
        .message-content p:last-child {  
            margin-bottom: 0;  
        }  
        
        .message-content a {  
            color: #0066cc;  
            text-decoration: none;  
        }  
        
        .message-content a:hover {  
            text-decoration: underline;  
        }  
        
        .message-content code {  
            background-color: #f0f0f0;  
            padding: 2px 4px;  
            border-radius: 4px;  
            font-family: 'Courier New', monospace;  
            font-size: 90%;  
        }  
        
        .message-content pre {  
            background-color: #f0f0f0;  
            padding: 10px;  
            border-radius: 4px;  
            overflow-x: auto;  
            margin: 10px 0;  
            font-family: 'Courier New', monospace;  
            font-size: 90%;  
        }  
        
        .chat-input {  
            padding: 15px 20px;  
            background-color: white;  
            border-top: 1px solid var(--border-color);  
        }  
        
        .chat-form {  
            display: flex;  
            gap: 10px;  
        }  
        
        .chat-input-field {  
            flex: 1;  
            border: 1px solid var(--border-color);  
            border-radius: 24px;  
            padding: 12px 18px;  
            font-size: 15px;  
            outline: none;  
            transition: border-color 0.2s;  
        }  
        
        .chat-input-field:focus {  
            border-color: var(--primary-color);  
        }  
        
        .chat-submit {  
            background-color: var(--primary-color);  
            color: white;  
            border: none;  
            border-radius: 24px;  
            padding: 12px 20px;  
            font-size: 15px;  
            cursor: pointer;  
            transition: background-color 0.2s;  
        }  
        
        .chat-submit:hover {  
            background-color: #0069d9;  
        }  
        
        /* Estilos para o painel de configurações */  
        .settings-panel {  
            display: none;  
            padding: 15px 20px;  
            background-color: #f8f9fa;  
            border-bottom: 1px solid var(--border-color);  
        }  
        
        .settings-panel.show {  
            display: block;  
        }  
        
        .settings-panel h5 {  
            font-size: 16px;  
            margin-bottom: 10px;  
        }  
        
        .settings-panel .form-label {  
            font-weight: 500;  
            margin-bottom: 5px;  
        }  
        
        .settings-panel .form-check {  
            margin-bottom: 5px;  
        }  
        
        /* Estilo para o indicador de carregamento */  
        #loading-indicator {  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            margin-top: 10px;  
            font-size: 14px;  
            color: var(--secondary-color);  
        }  
        
        .spinner-border {  
            width: 1rem;  
            height: 1rem;  
            margin-right: 0.5rem;  
            border-width: 0.15em;  
        }  
        
        .d-none {  
            display: none !important;  
        }  
        
        /* Estilos para citações */  
        .citation {  
            background-color: rgba(0, 123, 255, 0.05);  
            border-left: 3px solid var(--primary-color);  
            padding: 10px;  
            margin: 10px 0;  
            border-radius: 4px;  
        }  
        
        .citation-text {  
            font-style: italic;  
            margin-bottom: 5px;  
        }  
        
        .citation-source {  
            font-size: 0.9em;  
            opacity: 0.8;  
        }  
        
        .citation-source a {  
            color: var(--primary-color);  
            text-decoration: none;  
        }  
        
        .citation-source a:hover {  
            text-decoration: underline;  
        }  
        
        /* Botões de interface */  
        .chat-controls {  
            display: flex;  
            gap: 10px;  
        }  
        
        .btn {  
            padding: 6px 12px;  
            border-radius: 4px;  
            font-size: 14px;  
            cursor: pointer;  
            transition: all 0.2s;  
        }  
        
        .btn-outline-secondary {  
            color: var(--secondary-color);  
            border: 1px solid var(--secondary-color);  
            background: transparent;  
        }  
        
        .btn-outline-secondary:hover {  
            background-color: var(--secondary-color);  
            color: white;  
        }  
        
        .btn-outline-danger {  
            color: #dc3545;  
            border: 1px solid #dc3545;  
            background: transparent;  
        }  
        
        .btn-outline-danger:hover {  
            background-color: #dc3545;  
            color: white;  
        }  
        
        @media (max-width: 768px) {  
            .chat-container {  
                height: 100%;  
                max-width: 100%;  
                border-radius: 0;  
            }  
            
            .message-content {  
                max-width: 90%;  
            }  
        }  
    </style>  
</head>  
<body>  
    <div class="chat-container">  
        <div class="chat-header">  
            <div class="logo-container">  
                <div class="logo-placeholder">OA</div>  
                <h1 class="chat-title">Oráculo Atlas</h1>  
            </div>  
            <div class="chat-controls">  
                <button id="toggle-settings" class="btn btn-outline-secondary">  
                    <i class="bi bi-gear"></i> Configurações  
                </button>  
                <button id="clear-chat" class="btn btn-outline-danger">  
                    <i class="bi bi-trash"></i> Limpar  
                </button>  
            </div>  
        </div>  
        
        <!-- Painel de configurações -->  
        <div id="settings-panel" class="settings-panel">  
            <h5>Configurações de Busca</h5>  
            <div style="margin-bottom: 15px;">  
                <label class="form-label">Contexto de busca:</label>  
                <div class="form-check">  
                    <input type="radio" name="search_context_size" id="context-small" value="small">  
                    <label for="context-small">Pequeno</label>  
                </div>  
                <div class="form-check">  
                    <input type="radio" name="search_context_size" id="context-medium" value="medium" checked>  
                    <label for="context-medium">Médio</label>  
                </div>  
                <div class="form-check">  
                    <input type="radio" name="search_context_size" id="context-large" value="large">  
                    <label for="context-large">Grande</label>  
                </div>  
            </div>  
            
            <h5>Sua Localização</h5>  
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">  
                <div>  
                    <label for="city" class="form-label">Cidade</label>  
                    <input type="text" id="city" placeholder="São Paulo" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border-color);">  
                </div>  
                <div>  
                    <label for="region" class="form-label">Estado</label>  
                    <input type="text" id="region" placeholder="SP" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border-color);">  
                </div>  
                <div>  
                    <label for="country" class="form-label">País</label>  
                    <input type="text" id="country" placeholder="Brasil" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border-color);">  
                </div>  
            </div>  
        </div>  

        <div class="chat-messages" id="chat-messages">  
            <div class="message assistant">  
                <div class="message-content">  
                <p>Olá! Sou o Oráculo Atlas, seu assistente virtual. Como posso ajudar você hoje?</p>  
                </div>  
            </div>  
        </div>  
        
        <div class="chat-input">  
            <form id="message-form" class="chat-form">  
                <input type="text" id="message-input" class="chat-input-field" placeholder="Digite sua mensagem...">  
                <button type="submit" class="chat-submit">Enviar</button>  
            </form>  
            <div id="loading-indicator" class="d-none">  
                <div class="typing-indicator">  
                    <span></span>  
                    <span></span>  
                    <span></span>  
                </div>  
                <span>Processando sua pergunta...</span>  
            </div>  
        </div>  
    </div>