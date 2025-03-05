<?php  
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
    // Tags permitidas  
    $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><table><thead><tbody><tr><th><td><div><span>';  
    
    // Limpa o HTML mantendo apenas as tags permitidas  
    $text = strip_tags($text, $allowed_tags);  
    
    // Remove atributos potencialmente perigosos  
    $text = preg_replace('/(<[^>]+)(?:\s|\t|\n)+(on\w+)(\s*=\s*["\'][^"\']*["\'])/i', '$1', $text);  
    $text = preg_replace('/(<[^>]+)(?:\s|\t|\n)+(href|src|style)(\s*=\s*["\']javascript:[^"\']*["\'])/i', '$1', $text);  
    
    return $text;  
}  

// Função para verificar e formatar strings base64  
function formatBase64Image($base64String) {  
    // Se estiver vazio, retorna vazio  
    if (empty($base64String)) {  
        return '';  
    }  
    
    // Verifica se a string já tem o prefixo data:image  
    if (strpos($base64String, 'data:image/') === 0) {  
        return $base64String; // Já está formatado corretamente  
    }  
    
    // Verificar se é uma string base64 válida  
    if (base64_encode(base64_decode($base64String, true)) === $base64String) {  
        // Determinar o tipo de imagem (simplificado)  
        $imageType = 'jpeg'; // Padrão para quando não conseguimos determinar  
        
        // Tenta detectar o tipo da imagem pelos primeiros bytes  
        $decodedData = base64_decode($base64String);  
        if (strlen($decodedData) > 2) {  
            if (strpos($decodedData, "\xFF\xD8") === 0) {  
                $imageType = 'jpeg';  
            } elseif (strpos($decodedData, "\x89PNG") === 0 || substr($decodedData, 0, 4) === "\x89PNG") {  
                $imageType = 'png';  
            } elseif (strpos($decodedData, "GIF") === 0) {  
                $imageType = 'gif';  
            }  
        }  
        
        // Adicionar prefixo adequado  
        return 'data:image/' . $imageType . ';base64,' . $base64String;  
    }  
    
    // Se não for base64 válido, retorna a string original  
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
    
    // Buscar passos do manual  
    $stmt = $conexao->prepare("SELECT * FROM passos WHERE manual_id = ? ORDER BY numero ASC");  
    $stmt->execute([$id]);  
    $passos = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    
    // Incrementar contador de visualizações (exceto em modo preview)  
    if (!$preview) {  
        $stmt = $conexao->prepare("UPDATE manuais SET visualizacoes = visualizacoes + 1 WHERE id = ?");  
        $stmt->execute([$id]);  
        
        // Registrar log de acesso  
        if (isset($_SESSION['usuario_id'])) {  
            $stmt = $conexao->prepare("  
                INSERT INTO logs_acesso (usuario_id, manual_id, acao, ip, user_agent)   
                VALUES (?, ?, 'visualizar', ?, ?)  
            ");  
            $stmt->execute([  
                $_SESSION['usuario_id'],  
                $id,  
                $_SERVER['REMOTE_ADDR'],  
                $_SERVER['HTTP_USER_AGENT']  
            ]);  
        }  
    }  
    
} catch (PDOException $e) {  
    error_log("Erro ao carregar manual: " . $e->getMessage());  
    $erro = "Ocorreu um erro ao carregar o manual.";  
}  

// Função para sanitizar valores para exibição em HTML  
function h($string) {  
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');  
}  

// Função para formatar data  
function formatarData($data) {  
    if (!$data) return '';  
    $timestamp = strtotime($data);  
    return date('d/m/Y', $timestamp);  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title><?php echo h($manual['titulo']); ?> - Sistema de Manuais</title>  
    
    <!-- Bootstrap CSS -->  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">  
    
    <!-- Font Awesome -->  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">  
    
    <!-- Custom CSS -->  
    <style>  
        :root {  
            --sidebar-width: 250px;  
            --topbar-height: 60px;  
            --primary-color: #0d6efd;  
            --secondary-color: #6c757d;  
            --light-bg: #f8f9fa;  
            --border-color: #dee2e6;  
            --step-number-size: 40px;  
        }  
        
        body {  
            background-color: #f5f5f5;  
            padding-bottom: 60px;  
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;  
        }  
        
        /* Topbar */  
        .topbar {  
            position: fixed;  
            top: 0;  
            left: 0;  
            right: 0;  
            height: var(--topbar-height);  
            background-color: white;  
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);  
            z-index: 101;  
            display: flex;  
            align-items: center;  
            padding: 0 1rem;  
        }  
        
        /* Main Content */  
        .main-content {  
            margin-top: calc(var(--topbar-height) + 20px);  
            padding: 0 20px;  
        }  
        
        @media (min-width: 992px) {  
            .main-content {  
                padding: 0 40px;  
            }  
        }  
        
        /* Manual Header */  
        .manual-header {  
            background-color: white;  
            border-radius: 10px;  
            padding: 30px;  
            margin-bottom: 30px;  
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);  
        }  
        
        .manual-cover {  
            width: 100%;  
            height: 300px;  
            border-radius: 10px;  
            overflow: hidden;  
            margin-bottom: 20px;  
            background-color: var(--light-bg);  
        }  
        
        .manual-cover img {  
            width: 100%;  
            height: 100%;  
            object-fit: cover;  
        }  
        
        .manual-meta {  
            color: var(--secondary-color);  
            font-size: 0.9rem;  
            margin-bottom: 15px;  
        }  
        
        .manual-meta i {  
            width: 20px;  
            text-align: center;  
            margin-right: 5px;  
        }  
        
        .manual-meta span {  
            margin-right: 15px;  
        }  
        
        .badge-category {  
            background-color: #e9ecef;  
            color: #495057;  
            font-weight: 500;  
            padding: 5px 10px;  
            border-radius: 50px;  
            display: inline-flex;  
            align-items: center;  
        }  
        
        .badge-category i {  
            margin-right: 5px;  
        }  
        
        /* Steps */  
        .steps-container {  
            position: relative;  
            margin-bottom: 40px;  
        }  
        
        .step-timeline {  
            position: absolute;  
            left: 20px;  
            top: 70px;  
            bottom: 0;  
            width: 2px;  
            background-color: var(--border-color);  
            z-index: 0;  
        }  
        
        @media (min-width: 768px) {  
            .step-timeline {  
                left: calc(var(--step-number-size) / 2);  
            }  
        }  
        
        .step-card {  
            background-color: white;  
            border-radius: 10px;  
            padding: 0;  
            margin-bottom: 30px;  
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);  
            position: relative;  
            z-index: 1;  
            overflow: hidden;  
        }  
        
        .step-header {  
            display: flex;  
            align-items: center;  
            padding: 20px;  
            border-bottom: 1px solid var(--border-color);  
            background-color: var(--light-bg);  
        }  
        
        .step-number {  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            min-width: var(--step-number-size);  
            height: var(--step-number-size);  
            border-radius: 50%;  
            background-color: var(--primary-color);  
            color: white;  
            font-weight: bold;  
            font-size: 1.2rem;  
            margin-right: 15px;  
        }  
        
        .step-title {  
            font-size: 1.3rem;  
            font-weight: 500;  
            margin: 0;  
        }  
        
        .step-content {  
            padding: 25px;  
        }  
        
        .step-text {  
            margin-bottom: 20px;  
            line-height: 1.6;  
        }  
        
        .step-image-container {  
            margin-top: 20px;  
            margin-bottom: 10px;  
            text-align: center;  
            border-radius: 5px;  
            overflow: hidden;  
        }  
        
        .step-image {  
            max-width: 100%;  
            border-radius: 5px;  
            box-shadow: 0 0 10px rgba(0,0,0,0.1);  
        }  
        
        .step-image-caption {  
            margin-top: 10px;  
            color: var(--secondary-color);  
            font-style: italic;  
            text-align: center;  
        }  
        
        /* Action Buttons */  
        .action-buttons {  
            position: fixed;  
            bottom: 0;  
            left: 0;  
            right: 0;  
            background-color: white;  
            padding: 15px 0;  
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);  
            z-index: 99;  
        }  
   
        .action-buttons .container {  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
        }  
        
        /* Table of Contents */  
        .toc {  
            background-color: white;  
            border-radius: 10px;  
            padding: 20px;  
            margin-bottom: 30px;  
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);  
            position: sticky;  
            top: calc(var(--topbar-height) + 20px);  
        }  
        
        .toc-title {  
            font-size: 1.2rem;  
            font-weight: 500;  
            margin-bottom: 15px;  
            padding-bottom: 10px;  
            border-bottom: 1px solid var(--border-color);  
        }  
        
        .toc-list {  
            list-style: none;  
            padding: 0;  
            margin: 0;  
        }  
        
        .toc-item {  
            margin-bottom: 10px;  
        }  
        
        .toc-link {  
            display: flex;  
            align-items: center;  
            color: var(--secondary-color);  
            text-decoration: none;  
            padding: 8px 10px;  
            border-radius: 5px;  
            transition: all 0.2s;  
        }  
        
        .toc-link:hover {  
            background-color: var(--light-bg);  
            color: var(--primary-color);  
        }  
        
        .toc-link.active {  
            background-color: var(--primary-color);  
            color: white;  
        }  
        
        .toc-number {  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            width: 25px;  
            height: 25px;  
            border-radius: 50%;  
            background-color: var(--light-bg);  
            color: var(--secondary-color);  
            font-weight: bold;  
            font-size: 0.8rem;  
            margin-right: 10px;  
        }  
        
        .toc-link.active .toc-number {  
            background-color: rgba(255, 255, 255, 0.3);  
            color: white;  
        }  
        
        /* Print Styles */  
        @media print {  
            .topbar, .action-buttons, .toc {  
                display: none !important;  
            }  
            
            .main-content {  
                margin-top: 0;  
                padding: 0;  
            }  
            
            .manual-header, .step-card {  
                box-shadow: none;  
                border: 1px solid #ddd;  
                break-inside: avoid;  
            }  
            
            .step-timeline {  
                display: none;  
            }  
        }  
    </style>  
</head>  
<body>  
    <!-- Topbar -->  
    <div class="topbar">  
        <div class="d-flex align-items-center">  
            <a href="manual-list.php" class="btn btn-sm btn-outline-secondary me-3">  
                <i class="fas fa-arrow-left"></i>  
            </a>  
            <h4 class="m-0">Sistema de Manuais</h4>  
        </div>  
        
        <div class="ms-auto d-flex gap-2">  
            <?php if (!$preview && isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin'): ?>  
                <a href="manual-creator.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">  
                    <i class="fas fa-edit me-1"></i> Editar  
                </a>  
            <?php endif; ?>  
            <button id="printManual" class="btn btn-sm btn-outline-secondary">  
                <i class="fas fa-print me-1"></i> Imprimir  
            </button>  
        </div>  
    </div>  

    <!-- Depurador para imagens base64 -->  
    <?php if (isset($_GET['debug']) && isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin'): ?>  
    <div class="container mt-5 pt-3 alert alert-info">  
        <h5>Informações de Depuração de Imagens Base64</h5>  
        <p>Imagem de capa:   
            <?php   
            if (!empty($manual['imagem_capa'])) {  
                $length = strlen($manual['imagem_capa']);  
                $is_base64 = true;  
                $has_prefix = strpos($manual['imagem_capa'], 'data:image/') === 0;  
                
                echo "Comprimento: $length caracteres, ";  
                echo "Tem prefixo: " . ($has_prefix ? 'Sim' : 'Não');  
                
                // Mostra os primeiros 50 caracteres  
                echo "<br>Início: " . h(substr($manual['imagem_capa'], 0, 50)) . "...";  
            } else {  
                echo "Não definida";  
            }  
            ?>  
        </p>  
        <hr>  
        <h6>Informações de imagens dos passos:</h6>  
        <ul>  
        <?php foreach ($passos as $passo):   
            if (!empty($passo['imagem'])):  
                $length = strlen($passo['imagem']);  
                $has_prefix = strpos($passo['imagem'], 'data:image/') === 0;  
        ?>  
            <li>  
                Passo <?php echo $passo['numero']; ?>:   
                Comprimento: <?php echo $length; ?> caracteres,   
                Tem prefixo: <?php echo $has_prefix ? 'Sim' : 'Não'; ?>  
            </li>  
        <?php endif; endforeach; ?>  
        </ul>  
    </div>  
    <?php endif; ?>  

    <!-- Main Content -->  
    <div class="main-content">  
        <div class="container-fluid">  
            <div class="row">  
                <!-- Manual Content -->  
                <div class="col-lg-9">  
                    <!-- Manual Header -->  
                    <div class="manual-header">  
                        <?php if (!empty($manual['imagem_capa'])): ?>  
                        <div class="manual-cover">  
                            <img src="<?php echo h(formatBase64Image($manual['imagem_capa'])); ?>" alt="<?php echo h($manual['titulo']); ?>">  
                        </div>  
                        <?php endif; ?>  
                        
                        <h1 class="mb-3"><?php echo h($manual['titulo']); ?></h1>  
                        
                        <div class="manual-meta">  
                            <?php if (!empty($manual['categoria_nome'])): ?>  
                            <span class="badge-category me-3">  
                                <i class="fas fa-tag"></i> <?php echo h($manual['categoria_nome']); ?>  
                            </span>  
                            <?php endif; ?>  
                            
                            <span><i class="fas fa-user"></i> <?php echo h($manual['autor_nome'] ?? 'Administrador'); ?></span>  
                            
                            <span><i class="fas fa-calendar"></i> <?php echo formatarData($manual['data_criacao']); ?></span>  
                            
                            <?php if (!empty($manual['versao'])): ?>  
                            <span><i class="fas fa-code-branch"></i> v<?php echo h($manual['versao']); ?></span>  
                            <?php endif; ?>  
                            
                            <?php if (!$preview): ?>  
                            <span><i class="fas fa-eye"></i> <?php echo number_format($manual['visualizacoes']); ?> visualizações</span>  
                            <?php endif; ?>  
                        </div>  
                        
                        <?php if (!empty($manual['descricao'])): ?>  
                        <div class="manual-description mt-4">  
                            <h5>Descrição</h5>  
                            <div><?php echo sanitize_html($manual['descricao']); ?></div>  
                        </div>  
                        <?php endif; ?>  
                    </div>  
                    
                    <!-- Steps -->  
                    <div class="steps-container">  
                        <div class="step-timeline"></div>  
                        
                        <?php foreach ($passos as $index => $passo): ?>  
                        <div class="step-card" id="step-<?php echo $passo['numero']; ?>">  
                            <div class="step-header">  
                                <div class="step-number"><?php echo $passo['numero']; ?></div>  
                                <h3 class="step-title"><?php echo h($passo['titulo']); ?></h3>  
                            </div>  
                            <div class="step-content">  
                                <div class="step-text">  
                                    <?php echo sanitize_html($passo['texto']); ?>  
                                </div>  
                                
                                <?php if (!empty($passo['imagem'])): ?>  
                                <div class="step-image-container">  
                                    <img src="<?php echo h(formatBase64Image($passo['imagem'])); ?>" alt="<?php echo h($passo['titulo']); ?>" class="step-image">  
                                    <?php if (!empty($passo['legenda'])): ?>  
                                    <div class="step-image-caption">  
                                        <?php echo h($passo['legenda']); ?>  
                                    </div>  
                                    <?php endif; ?>  
                                </div>  
                                <?php endif; ?>  
                            </div>  
                        </div>  
                        <?php endforeach; ?>  
                    </div>  
                </div>  

                <!-- Table of Contents -->  
                <div class="col-lg-3">  
                    <div class="toc">  
                        <h4 class="toc-title">Índice</h4>  
                        <ul class="toc-list">  
                            <?php foreach ($passos as $passo): ?>  
                            <li class="toc-item">  
                                <a href="#step-<?php echo $passo['numero']; ?>" class="toc-link">  
                                    <span class="toc-number"><?php echo $passo['numero']; ?></span>  
                                    <span class="toc-text"><?php echo h($passo['titulo']); ?></span>  
                                </a>  
                            </li>  
                            <?php endforeach; ?>  
                        </ul>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
    
    <!-- Action Buttons -->  
    <div class="action-buttons">  
        <div class="container">  
            <div class="step-navigation">  
                <button id="prevStep" class="btn btn-outline-secondary" disabled>  
                    <i class="fas fa-arrow-left me-1"></i> Passo Anterior  
                </button>  
                <button id="nextStep" class="btn btn-outline-primary ms-2">  
                    Próximo Passo <i class="fas fa-arrow-right ms-1"></i>  
                </button>  
            </div>  
            <div>  
                <?php if (!$preview): ?>  
                    <?php if (!empty($manual['arquivo_pdf'])): ?>  
                    <a href="<?php echo h($manual['arquivo_pdf']); ?>" class="btn btn-outline-danger" download>  
                        <i class="fas fa-file-pdf me-1"></i> Baixar PDF  
                    </a>  
                    <?php endif; ?>  
                    
                    <button id="rateManual" class="btn btn-outline-warning ms-2">  
                        <i class="fas fa-star me-1"></i> Avaliar  
                    </button>  
                <?php endif; ?>  
            </div>  
        </div>  
    </div>  
    
    <!-- Bootstrap JS and dependencies -->  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>  
    <!-- jQuery -->  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <!-- SweetAlert2 -->  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
    
    <script>  
        $(document).ready(function() {  
            // Configurar links externos para abrir em nova guia  
            $('a[href^="http"]').attr('target', '_blank').attr('rel', 'noopener noreferrer');  
            
            // Ativar o item do TOC atual durante o scroll  
            $(window).scroll(function() {  
                highlightTocItem();  
            });  
            
            function highlightTocItem() {  
                const scrollPosition = $(window).scrollTop();  
                
                // Determinar qual passo está visível atualmente  
                $('.step-card').each(function() {  
                    const stepTop = $(this).offset().top;  
                    const stepHeight = $(this).height();  
                    const stepId = $(this).attr('id');  
                    
                    if (scrollPosition >= stepTop - 100 &&   
                        scrollPosition < stepTop + stepHeight - 100) {  
                        // Remover classe 'active' de todos os links  
                        $('.toc-link').removeClass('active');  
                        
                        // Adicionar 'active' ao link correspondente  
                        $('.toc-link[href="#' + stepId + '"]').addClass('active');  
                        
                        // Atualizar botões de navegação  
                        updateNavigationButtons(stepId);  
                        
                        return false; // Sair do loop  
                    }  
                });  
            }  
            
            // Inicializar o TOC  
            highlightTocItem();  
            
            // Rolagem suave para os links do TOC  
            $('.toc-link').on('click', function(e) {  
                e.preventDefault();  
                
                const targetId = $(this).attr('href');  
                const targetPosition = $(targetId).offset().top - 80;  
                
                $('html, body').animate({  
                    scrollTop: targetPosition  
                }, 600);  
            });  
            
            // Botões de navegação entre passos  
            function updateNavigationButtons(currentStepId) {  
                const currentIndex = $('.step-card').index($('#' + currentStepId));  
                const totalSteps = $('.step-card').length;  
                
                $('#prevStep').prop('disabled', currentIndex <= 0);  
                $('#nextStep').prop('disabled', currentIndex >= totalSteps - 1);  
            }  
            
            $('#prevStep').on('click', function() {  
                const activeStep = $('.toc-link.active').attr('href');  
                const prevStep = $(activeStep).prev('.step-card');  
                
                if (prevStep.length) {  
                    const targetPosition = prevStep.offset().top - 80;  
                    
                    $('html, body').animate({  
                        scrollTop: targetPosition  
                    }, 600);  
                }  
            });  
            
            $('#nextStep').on('click', function() {  
                const activeStep = $('.toc-link.active').attr('href');  
                const nextStep = $(activeStep).next('.step-card');  
                
                if (nextStep.length) {  
                    const targetPosition = nextStep.offset().top - 80;  
                    
                    $('html, body').animate({  
                        scrollTop: targetPosition  
                    }, 600);  
                }  
            });  
            
            // Impressão  
            $('#printManual').on('click', function() {  
                window.print();  
                
                // Registrar o download/impressão  
                fetch('register_download.php?id=<?php echo $id; ?>', {  
                    method: 'POST'  
                }).catch(function(error) {  
                    console.error('Erro ao registrar download:', error);  
                });  
            });  
            
            <?php if (!$preview): ?>  
            // Avaliação  
            $('#rateManual').on('click', function() {  
                Swal.fire({  
                    title: 'Avaliar Manual',  
                    html: `  
                        <div class="text-center mb-4">  
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
                    showLoaderOnConfirm: true,  
                    didOpen: () => {  
                        const stars = Swal.getContainer().querySelectorAll('.star');  
                        let selectedRating = 0;  
                        
                        stars.forEach(star => {  
                            star.style.cursor = 'pointer';  
                            star.style.margin = '0 5px';  
                            star.style.color = '#ffc107';  
                            
                            star.addEventListener('mouseover', function() {  
                                const rating = parseInt(this.dataset.rating);  
                                
                                stars.forEach((s, index) => {  
                                    if (index < rating) {  
                                        s.classList.remove('far');  
                                        s.classList.add('fas');  
                                    } else {  
                                        s.classList.remove('fas');  
                                        s.classList.add('far');  
                                    }  
                                });  
                                
                                const ratingText = Swal.getContainer().querySelector('.rating-text');  
                                ratingText.textContent = `${rating} ${rating === 1 ? 'estrela' : 'estrelas'}`;  
                            });  
                            
                            star.addEventListener('click', function() {  
                                selectedRating = parseInt(this.dataset.rating);  
                            });  
                            
                            star.addEventListener('mouseleave', function() {  
                                stars.forEach((s, index) => {  
                                    if (index < selectedRating) {  
                                        s.classList.remove('far');  
                                        s.classList.add('fas');  
                                    } else {  
                                        s.classList.remove('fas');  
                                        s.classList.add('far');  
                                    }  
                                });  
                                
                                const ratingText = Swal.getContainer().querySelector('.rating-text');  
                                ratingText.textContent = selectedRating > 0 ?   
                                    `${selectedRating} ${selectedRating === 1 ? 'estrela' : 'estrelas'}` :   
                                    'Selecione uma nota';  
                            });  
                        });  
                    },  
                    preConfirm: () => {  
                        const selectedRating = Swal.getContainer().querySelector('.fas') ?   
                            parseInt(Swal.getContainer().querySelectorAll('.fas').length) : 0;  
                        const comment = Swal.getContainer().querySelector('#rating-comment').value;  
                        
                        if (selectedRating === 0) {  
                            Swal.showValidationMessage('Por favor, selecione uma nota');  
                            return false;  
                        }  
                        
                        return fetch('save_rating.php', {  
                            method: 'POST',  
                            headers: {  
                                'Content-Type': 'application/json',  
                            },  
                            body: JSON.stringify({  
                                manual_id: <?php echo $id; ?>,  
                                rating: selectedRating,  
                                comentario: comment  
                            })  
                        })  
                        .then(response => response.json())  
                        .then(data => {  
                            if (data.success) {  
                                return data;  
                            } else {  
                                throw new Error(data.message || 'Erro ao salvar avaliação');  
                            }  
                        })  
                        .catch(error => {  
                            Swal.showValidationMessage(`Falha: ${error.message}`);  
                        });  
                    }  
                }).then((result) => {  
                    if (result.isConfirmed && result.value.success) {  
                        Swal.fire(  
                            'Obrigado!',  
                            'Sua avaliação foi salva com sucesso.',  
                            'success'  
                        );  
                    }  
                });  
            });  
            <?php endif; ?>  
            
            // Teclas de atalho para navegação  
            $(document).keydown(function(e) {  
                // Setas direcionais para navegação  
                if (e.which === 37) { // Seta para esquerda  
                    $('#prevStep').click();  
                } else if (e.which === 39) { // Seta para direita  
                    $('#nextStep').click();  
                }  
            });  
        });  
    </script>  
</body>  
</html>