<!-- Scripts -->  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/showdown/2.1.0/showdown.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/marked@5.1.0/marked.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>  
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">  

<script>  
// Inicializar app apenas após DOM estar completamente carregado  
document.addEventListener('DOMContentLoaded', function() {  
    // Função segura para obter elementos do DOM  
    function getElement(id) {  
        return document.getElementById(id);  
    }  
    
    // Converter de Markdown para HTML  
    const converter = new showdown.Converter({  
        tables: true,   
        strikethrough: true,  
        tasklists: true,  
        smartIndentationFix: true,  
        openLinksInNewWindow: true  
    });  
    
    // Obter elementos da interface  
    const chatMessages = getElement('chat-messages');  
    const messageForm = getElement('message-form');  
    const messageInput = getElement('message-input');  
    const loadingIndicator = getElement('loading-indicator');  
    const clearChatButton = getElement('clear-chat');  
    const toggleSettingsButton = getElement('toggle-settings');  
    const settingsPanel = getElement('settings-panel');  
    const statusIndicator = document.querySelector('.status-indicator');  
    const statusText = document.querySelector('.status span');  
    
    // Verificar elementos essenciais  
    if (!chatMessages) {  
        console.warn('Elemento #chat-messages não encontrado no DOM');  
    }  
    
    if (!messageForm) {  
        console.warn('Elemento #message-form não encontrado no DOM');  
    }  
    
    if (!messageInput) {  
        console.warn('Elemento #message-input não encontrado no DOM');  
    }  
    
    // Histórico de mensagens  
    let messageHistory = [];  
    
    // Configurações de pesquisa (padrão)  
    let searchSettings = {  
        search_context_size: 'medium',  
        user_location: {  
            city: '',  
            region: '',  
            country: ''  
        }  
    };  
    
    // Carregar histórico do localStorage  
    function loadChatHistory() {  
        const savedMessages = localStorage.getItem('chatHistory');  
        if (savedMessages && chatMessages) {  
            try {  
                const parsedMessages = JSON.parse(savedMessages);  
                messageHistory = parsedMessages.messages || [];  
                
                // Restaurar mensagens na interface  
                if (messageHistory.length > 0) {  
                    messageHistory.forEach(msg => {  
                        if (msg.role === 'user' || msg.role === 'assistant') {  
                            addMessageToChat(msg.content, msg.role, msg.annotations || []);  
                        }  
                    });  
                    
                    // Rolar para a última mensagem  
                    scrollToBottom();  
                }  
            } catch (e) {  
                console.error('Erro ao carregar histórico:', e);  
                localStorage.removeItem('chatHistory');  
                messageHistory = [];  
            }  
        }  
    }  
    
    // Carregar configurações salvas  
    function loadSettings() {  
        const savedSettings = localStorage.getItem('searchSettings');  
        if (savedSettings) {  
            try {  
                const parsed = JSON.parse(savedSettings);  
                searchSettings = Object.assign({}, searchSettings, parsed);  
                
                // Aplicar configurações na interface  
                applySettingsToUI();  
            } catch (e) {  
                console.error('Erro ao carregar configurações:', e);  
            }  
        }  
    }  
    
    // Aplicar configurações à interface  
    function applySettingsToUI() {  
        // Contexto de busca  
        if (searchSettings.search_context_size) {  
            const radio = document.querySelector(`input[name="search_context_size"][value="${searchSettings.search_context_size}"]`);  
            if (radio) radio.checked = true;  
        }  
        
        // Localização  
        const cityInput = getElement('city');  
        const regionInput = getElement('region');  
        const countryInput = getElement('country');  
        
        if (cityInput && searchSettings.user_location.city) {  
            cityInput.value = searchSettings.user_location.city;  
        }  
        
        if (regionInput && searchSettings.user_location.region) {  
            regionInput.value = searchSettings.user_location.region;  
        }  
        
        if (countryInput && searchSettings.user_location.country) {  
            countryInput.value = searchSettings.user_location.country;  
        }  
    }  
    
    // Inicializar eventos  
    function setupEventListeners() {  
        // Formulário de mensagens  
        if (messageForm) {  
            messageForm.addEventListener('submit', async (e) => {  
                e.preventDefault();  
                
                if (!messageInput) return;  
                
                const message = messageInput.value.trim();  
                if (!message) return;  
                
                // Limpar campo de input  
                messageInput.value = '';  
                
                // Adicionar mensagem ao chat  
                addMessageToChat(message, 'user');  
                
                // Mostrar indicador de digitação  
                const typingIndicator = showTypingIndicator();  
                
                // Enviar mensagem para API e obter resposta  
                await sendMessageToAPI(message);  
                
                // Remover indicador de digitação  
                hideTypingIndicator(typingIndicator);  
                
                // Salvar histórico  
                saveHistory();  
            });  
        }  
        
        // Limpar chat  
        if (clearChatButton) {  
            clearChatButton.addEventListener('click', () => {  
                if (confirm('Tem certeza que deseja limpar o histórico de chat?')) {  
                    if (chatMessages) {  
                        chatMessages.innerHTML = '';  
                    }  
                    
                    messageHistory = [];  
                    localStorage.removeItem('chatHistory');  
                    
                    // Adicionar mensagem de boas-vindas novamente  
                    addWelcomeMessage();  
                }  
            });  
        }  
        
        // Toggle de configurações  
        if (toggleSettingsButton && settingsPanel) {  
            toggleSettingsButton.addEventListener('click', () => {  
                settingsPanel.classList.toggle('show');  
                
                // Atualizar a altura do painel de configurações  
                if (settingsPanel.classList.contains('show')) {  
                    settingsPanel.style.maxHeight = settingsPanel.scrollHeight + 'px';  
                } else {  
                    settingsPanel.style.maxHeight = '0';  
                }  
            });  
        }  
        
        // Configurações: Tamanho do contexto  
        const searchSizeRadios = document.querySelectorAll('input[name="search_context_size"]');  
        searchSizeRadios.forEach(radio => {  
            if (radio) {  
                radio.addEventListener('change', () => {  
                    if (radio.checked) {  
                        searchSettings.search_context_size = radio.value;  
                        saveSettings();  
                    }  
                });  
            }  
        });  
        
        // Configurações: Localização  
        const cityInput = getElement('city');  
        const regionInput = getElement('region');  
        const countryInput = getElement('country');  
        
        [cityInput, regionInput, countryInput].forEach(input => {  
            if (input) {  
                input.addEventListener('change', updateLocationSettings);  
            }  
        });  
        
        // Tecla Enter para enviar (mas permitir Shift+Enter para quebras de linha)  
        if (messageInput) {  
            messageInput.addEventListener('keydown', function(e) {  
                if (e.key === 'Enter' && !e.shiftKey && messageForm) {  
                    e.preventDefault();  
                    messageForm.dispatchEvent(new Event('submit'));  
                }  
            });  
        }  
    }  
    
    // Atualizar configurações de localização  
    function updateLocationSettings() {  
        const cityInput = getElement('city');  
        const regionInput = getElement('region');  
        const countryInput = getElement('country');  
        
        searchSettings.user_location = {  
            city: cityInput ? cityInput.value.trim() : '',  
            region: regionInput ? regionInput.value.trim() : '',  
            country: countryInput ? countryInput.value.trim() : ''  
        };  
        
        saveSettings();  
    }  
    
    // Salvar configurações  
    function saveSettings() {  
        localStorage.setItem('searchSettings', JSON.stringify(searchSettings));  
    }  
    
    // Salvar histórico de chat  
    function saveHistory() {  
        localStorage.setItem('chatHistory', JSON.stringify({  
            messages: messageHistory,  
            timestamp: new Date().toISOString()  
        }));  
    }  
    
    // Mostrar indicador de digitação  
    function showTypingIndicator() {  
        if (statusIndicator && statusText) {  
            statusIndicator.classList.add('thinking');  
            statusText.textContent = 'Processando';  
        }  
        
        const typingContainer = document.createElement('div');  
        typingContainer.className = 'message assistant typing-message';  
        
        const contentElement = document.createElement('div');  
        contentElement.className = 'message-content';  
        
        const typingIndicator = document.createElement('div');  
        typingIndicator.className = 'typing-indicator';  
        typingIndicator.innerHTML = '<span></span><span></span><span></span>';  
        
        contentElement.appendChild(typingIndicator);  
        typingContainer.appendChild(contentElement);  
        
        chatMessages.appendChild(typingContainer);  
        scrollToBottom();  
        
        return typingContainer;  
    }  
    
    // Esconder indicador de digitação  
    function hideTypingIndicator(element) {  
        if (element && element.parentNode) {  
            element.parentNode.removeChild(element);  
        }  
        
        if (statusIndicator && statusText) {  
            statusIndicator.classList.remove('thinking');  
            statusText.textContent = 'Online';  
        }  
    }  
    
    // Adicionar mensagem ao chat  
    function addMessageToChat(message, role, annotations = []) {  
        if (!chatMessages) {  
            console.error('Container de mensagens (#chat-messages) não encontrado');  
            return;  
        }  
        
        // Criar elemento de mensagem  
        const messageElement = document.createElement('div');  
        messageElement.classList.add('message', role);  
        
        // Adicionar efeito de entrada com animação  
        messageElement.style.opacity = '0';  
        messageElement.style.transform = 'translateY(10px)';  
        
        // Adicionar conteúdo  
        const contentElement = document.createElement('div');  
        contentElement.classList.add('message-content');  
        
        // Converter markdown para HTML (apenas para mensagens do assistente)  
        let htmlContent = '';  
        if (role === 'assistant') {  
            // Processar citações no texto  
            let processedMessage = message;  
            if (annotations && annotations.length > 0) {  
                annotations.forEach((annotation, index) => {  
                    if (annotation.text && processedMessage.includes(annotation.text)) {  
                        const marker = `[citation:${index}]`;  
                        processedMessage = processedMessage.replace(annotation.text, marker);  
                    }  
                });  
            }  
            
            // Converter markdown para HTML  
            htmlContent = converter.makeHtml(processedMessage);  
            
            // Substituir marcadores de citação com citações HTML  
            if (annotations && annotations.length > 0) {  
                annotations.forEach((annotation, index) => {  
                    const marker = `[citation:${index}]`;  
                    const citationHTML = createCitationHTML(annotation);  
                    htmlContent = htmlContent.replace(marker, citationHTML);  
                });  
            }  
            
            // Aplicar highlight.js para blocos de código  
            setTimeout(() => {  
                contentElement.querySelectorAll('pre code').forEach((block) => {  
                    hljs.highlightElement(block);  
                    
                    // Adicionar label de linguagem ao bloco de código  
                    const language = block.className.replace(/^.*language-(\w+).*$/, '$1');  
                    if (language && language !== 'language-') {  
                        const preElement = block.parentNode;  
                        if (preElement) {  
                            preElement.setAttribute('data-language', language);  
                            
                            // Adicionar o nome da linguagem como label  
                            const languageLabel = document.createElement('div');  
                            languageLabel.className = 'code-language';  
                            languageLabel.textContent = language;  
                            preElement.appendChild(languageLabel);  
                        }  
                    }  
                });  
            }, 0);  
        } else {  
            // Para mensagens do usuário, escapar HTML e manter quebras de linha  
            htmlContent = message  
                .replace(/&/g, '&amp;')  
                .replace(/</g, '&lt;')  
                .replace(/>/g, '&gt;')  
                .replace(/\n/g, '<br>');  
        }  
        
        contentElement.innerHTML = htmlContent;  
        messageElement.appendChild(contentElement);  
        
        // Adicionar ao container de mensagens  
        chatMessages.appendChild(messageElement);  
        
        // Forçar reflow para garantir que a animação funcione  
        void messageElement.offsetWidth;  
        
        // Aplicar animação  
        messageElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';  
        messageElement.style.opacity = '1';  
        messageElement.style.transform = 'translateY(0)';  
        
        // Rolar para o final  
        scrollToBottom();  
    }  
    
    // Criar HTML para citações  
    function createCitationHTML(annotation) {  
        if (!annotation || !annotation.file_citation || !annotation.file_citation.metadata) {  
            return '';  
        }  
        
        const metadata = annotation.file_citation.metadata;  
        const url = metadata.url || '#';  
        const title = metadata.title || 'Fonte Web';  
        
        return `  
            <div class="citation">  
                <div class="citation-text">${annotation.text}</div>  
                <div class="citation-source">  
                    <a href="${url}" target="_blank" rel="noopener noreferrer">  
                        <i class="bi bi-link-45deg">
                                        <div class="citation-source">  
                    <span>Fonte:</span>  
                    <a href="${url}" target="_blank" rel="noopener noreferrer">  
                        <i class="bi bi-link-45deg"></i> ${title}  
                    </a>  
                </div>  
            </div>  
        `;  
    }  
    
    // Rolar chat para o final  
    function scrollToBottom() {  
        if (chatMessages) {  
            chatMessages.scrollTop = chatMessages.scrollHeight;  
        }  
    }  
    
    // Enviar mensagem para a API  
    async function sendMessageToAPI(message) {  
        try {  
            if (!chatMessages) {  
                console.error('Container de mensagens (#chat-messages) não encontrado');  
                return null;  
            }  
            
            // Mostrar indicador de carregamento  
            if (loadingIndicator) {  
                loadingIndicator.classList.remove('d-none');  
            }  
            
            // Atualizar status de processamento  
            if (statusIndicator && statusText) {  
                statusIndicator.classList.add('thinking');  
                statusText.textContent = 'Processando';  
            }  
            
            // Adicionar mensagem ao histórico  
            messageHistory.push({ role: 'user', content: message });  
            
            // Preparar dados para a API  
            const requestData = {   
                messages: messageHistory,  
                search_context_size: searchSettings.search_context_size || 'medium',  
                user_location: searchSettings.user_location  
            };  
            
            console.log("Enviando dados:", requestData);  
            
            // Requisição para API  
            const response = await fetch('api/chat.php', {  
                method: 'POST',  
                headers: {  
                    'Content-Type': 'application/json'  
                },  
                body: JSON.stringify(requestData)  
            });  
            
            // Obter resposta  
            const responseText = await response.text();  
            
            let data;  
            try {  
                // Tentar analisar como JSON  
                data = JSON.parse(responseText);  
            } catch (e) {  
                console.error("Resposta não é JSON válido:", responseText);  
                throw new Error(`Resposta inválida do servidor: ${responseText.substring(0, 100)}...`);  
            }  
            
            // Verificar erros  
            if (!response.ok) {  
                const errorMsg = data.error || data.message || 'Erro desconhecido';  
                throw new Error(`Erro na API (${response.status}): ${errorMsg}`);  
            }  
            
            // Adicionar resposta ao histórico  
            messageHistory.push({   
                role: 'assistant',   
                content: data.message,  
                annotations: data.annotations || []  
            });  
            
            // Atualizar interface  
            addMessageToChat(data.message, 'assistant', data.annotations);  
            
            // Registrar uso de busca web (para debug)  
            if (data.used_search) {  
                console.log('A resposta utilizou busca na web');  
            }  
            
            // Registrar uso de tokens (para debug)  
            if (data.usage) {  
                console.log(`Tokens utilizados: ${data.usage.total_tokens} (${data.usage.prompt_tokens} prompt + ${data.usage.completion_tokens} resposta)`);  
            }  
            
            return data.message;  
        } catch (error) {  
            console.error('Erro ao enviar mensagem:', error);  
            
            // Adicionar mensagem de erro formatada  
            const errorMessage = `  
## Ocorreu um erro ao processar sua solicitação  

Desculpe pelo inconveniente. O sistema encontrou um problema ao processar sua mensagem.  

**Detalhes do erro:** ${error.message}  

Por favor, tente novamente em alguns instantes ou reformule sua pergunta.  
`;  
            
            addMessageToChat(errorMessage, 'assistant');  
            return null;  
        } finally {  
            // Esconder indicador de carregamento  
            if (loadingIndicator) {  
                loadingIndicator.classList.add('d-none');  
            }  
            
            // Restaurar status  
            if (statusIndicator && statusText) {  
                statusIndicator.classList.remove('thinking');  
                statusText.textContent = 'Online';  
            }  
        }  
    }  
    
    // Adicionar mensagem de boas-vindas  
    function addWelcomeMessage() {  
        if (chatMessages && chatMessages.childElementCount === 0) {  
            const welcomeMessage = `  
# Bem-vindo ao Oráculo Atlas! 👋  

Eu sou seu assistente inteligente com acesso em tempo real à internet.  

## Como posso ajudar você?  

* Responder perguntas sobre **notícias atuais** e eventos recentes  
* Fornecer **informações precisas** sobre qualquer tópico  
* Ajudar com **pesquisas** e fornecer dados verificados  
* Criar **conteúdo estruturado** com formatação rica  

Estou pronto para auxiliar em suas dúvidas. O que deseja saber hoje?  
`;  
            addMessageToChat(welcomeMessage, "assistant");  
        }  
    }  
    
    // Inicializar a aplicação  
    function initApp() {  
        // Carregar histórico e configurações  
        loadChatHistory();  
        loadSettings();  
        
        // Configurar eventos  
        setupEventListeners();  
        
        // Adicionar mensagem de boas-vindas (se for a primeira vez)  
        addWelcomeMessage();  
    }  
    
    // Iniciar a aplicação  
    initApp();  
});  
</script>  

 
</body>  
</html>