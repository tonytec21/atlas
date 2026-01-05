<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
date_default_timezone_set('America/Sao_Paulo');

// CSRF para chamadas ao módulo de pedidos (usado no AJAX abaixo)
if (empty($_SESSION['csrf_pedidos'])) {
    $_SESSION['csrf_pedidos'] = bin2hex(random_bytes(32));
}

// Verifique se a conexão está definida
if (!isset($conn)) {
    die("Erro ao conectar ao banco de dados");
}

// ========== VERIFICAR E ADICIONAR COLUNA FERRFIS SE NÃO EXISTIR ==========
// Tabela atos_liquidados
$checkColumn = $conn->query("SHOW COLUMNS FROM atos_liquidados LIKE 'ferrfis'");
if ($checkColumn && $checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE atos_liquidados ADD COLUMN ferrfis DECIMAL(10,2) DEFAULT 0.00 AFTER femp");
}

// Tabela atos_manuais_liquidados
$checkColumn2 = $conn->query("SHOW COLUMNS FROM atos_manuais_liquidados LIKE 'ferrfis'");
if ($checkColumn2 && $checkColumn2->num_rows == 0) {
    $conn->query("ALTER TABLE atos_manuais_liquidados ADD COLUMN ferrfis DECIMAL(10,2) DEFAULT 0.00 AFTER femp");
}
// ========================================================================

/* ===================== CONTROLE DO BOTÃO "PAGAMENTOS" POR JSON ===================== */
// Lê a flag no JSON (usa o mesmo configuracao.json já usado no projeto)
$__configPath = __DIR__ . '/../style/config_os.json';
$__controlarPorAcessosAdicionais = false;

if (is_file($__configPath)) {
    $cfgRaw = file_get_contents($__configPath);
    $cfgArr = json_decode($cfgRaw, true);
    // Aceita "S", true ou "1"
    $val = $cfgArr['controlar_pagamentos_por_acessos_adicionais'] ?? null;
    $__controlarPorAcessosAdicionais = ($val === 'S' || $val === true || $val === '1');
}

// Por padrão, sem controle adicional o botão aparece normalmente
$podeVerBotaoPagamentos = true;

if ($__controlarPorAcessosAdicionais) {
    // Busca perfil e acessos adicionais do usuário logado
    $stmtUsr = $conn->prepare("SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?");
    $stmtUsr->bind_param("s", $_SESSION['username']);
    $stmtUsr->execute();
    $resUsr = $stmtUsr->get_result();
    $usrRow = $resUsr->fetch_assoc();
    $stmtUsr->close();

    $nivel = strtolower(trim($usrRow['nivel_de_acesso'] ?? ''));
    $adicionaisStr = trim($usrRow['acesso_adicional'] ?? '');
    $adicionais = $adicionaisStr !== '' ? array_map('trim', explode(',', $adicionaisStr)) : [];

    $isAdmin = ($nivel === 'administrador' || $nivel === 'admin');
    $temFluxoDeCaixa = in_array('Fluxo de Caixa', $adicionais, true);

    // Só mostra para Administrador OU quem tem “Fluxo de Caixa”
    $podeVerBotaoPagamentos = ($isAdmin || $temFluxoDeCaixa);
}
/* ================================================================================ */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['title'];
    $categoria = $_POST['category'];
    $data_limite = $_POST['deadline'];
    $funcionario_responsavel = $_POST['employee'];
    $origem = $_POST['origin'];
    $descricao = $_POST['description'];
    $criado_por = $_POST['createdBy'];
    $data_criacao = $_POST['createdAt'];
    $token = md5(uniqid(rand(), true));
    $caminho_anexo = '';

    // Verifica se há arquivos anexados
    if (!empty($_FILES['attachments']['name'][0])) {
        $targetDir = "../tarefas/arquivos/$token/";
        $fullTargetDir = __DIR__ . $targetDir;
        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }

        foreach ($_FILES['attachments']['name'] as $key => $name) {
            $targetFile = $fullTargetDir . basename($name);
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $targetFile)) {
                $caminho_anexo .= "$targetDir" . basename($name) . ";";
            }
        }
        // Remover o ponto e vírgula final
        $caminho_anexo = rtrim($caminho_anexo, ';');
    }

    // Inserir dados da tarefa no banco de dados
    $sql = "INSERT INTO tarefas (token, titulo, categoria, origem, descricao, data_limite, funcionario_responsavel, criado_por, data_criacao, caminho_anexo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $token, $titulo, $categoria, $origem, $descricao, $data_limite, $funcionario_responsavel, $criado_por, $data_criacao, $caminho_anexo);

    if ($stmt->execute()) {
        // Capturar o ID da tarefa recém-inserida
        $last_id = $stmt->insert_id;
        header("Location: edit_task.php?id=$last_id");
    } else {
        echo "Erro ao salvar a tarefa: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

// Buscar dados da OS
$os_id = $_GET['id'];
$os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
$os_query->bind_param("i", $os_id);
$os_query->execute();
$os_result = $os_query->get_result();
$ordem_servico = $os_result->fetch_assoc();

// Buscar dados dos itens da OS
$os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
$os_items_query->bind_param("i", $os_id);
$os_items_query->execute();
$os_items_result = $os_items_query->get_result();
$ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados dos pagamentos
$pagamentos_query = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
$pagamentos_query->bind_param("i", $os_id);
$pagamentos_query->execute();
$pagamentos_result = $pagamentos_query->get_result();
$pagamentos = $pagamentos_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados das devoluções
$devolucoes_query = $conn->prepare("SELECT * FROM devolucao_os WHERE ordem_de_servico_id = ?");
$devolucoes_query->bind_param("i", $os_id);
$devolucoes_query->execute();
$devolucoes_result = $devolucoes_query->get_result();
$devolucoes = $devolucoes_result->fetch_all(MYSQLI_ASSOC);

// Consulta para atos liquidados normais
$atos_liquidados_query = $conn->prepare("SELECT SUM(total) as total_liquidado FROM atos_liquidados WHERE ordem_servico_id = ?");
$atos_liquidados_query->bind_param("i", $os_id);
$atos_liquidados_query->execute();
$atos_liquidados_result = $atos_liquidados_query->get_result();
$atos_liquidados = $atos_liquidados_result->fetch_assoc();
$total_liquidado_normal = $atos_liquidados['total_liquidado'] ?? 0.0;

// Consulta para atos manuais liquidados
$atos_manuais_query = $conn->prepare("SELECT SUM(total) as total_manuais_liquidado FROM atos_manuais_liquidados WHERE ordem_servico_id = ?");
$atos_manuais_query->bind_param("i", $os_id);
$atos_manuais_query->execute();
$atos_manuais_result = $atos_manuais_query->get_result();
$atos_manuais = $atos_manuais_result->fetch_assoc();
$total_manuais_liquidado = $atos_manuais['total_manuais_liquidado'] ?? 0.0;

// Soma dos atos liquidados de ambas as tabelas
$total_liquidado = $total_liquidado_normal + $total_manuais_liquidado;

// Verificar se há itens liquidados
$has_liquidated = false;
$has_ato_17 = false;
foreach ($ordem_servico_itens as $item) {
    if ($item['status'] == 'liquidado') {
        $has_liquidated = true;
    }
    if (strpos($item['ato'], '17.') === 0) {
        $has_ato_17 = true;
    }
}

// Calcular total dos pagamentos
$total_pagamentos = 0;
foreach ($pagamentos as $pagamento) {
    $total_pagamentos += $pagamento['total_pagamento'];
}

// Quantidade de pagamentos (conta entradas, inclusive de valor 0)
$qtde_pagamentos = is_array($pagamentos) ? count($pagamentos) : 0;

/* ===== FLAG ISENTO =====
   Agora só fica TRUE se existir ao menos um pagamento
   cuja forma_de_pagamento seja exatamente "Isento de Pagamento". */
$isIsento = false;

if (is_array($pagamentos)) {
    foreach ($pagamentos as $p) {
        $fp = trim($p['forma_de_pagamento'] ?? '');
        if (strcasecmp($fp, 'Isento de Pagamento') === 0) {
            $isIsento = true;
            break;
        }
    }
}


// Calcular total das devoluções
$total_devolucoes = 0;
foreach ($devolucoes as $devolucao) {
    $total_devolucoes += $devolucao['total_devolucao'];
}

// Calcular total dos repasses
$total_repasses = 0;
$repasse_query = $conn->prepare("SELECT total_repasse FROM repasse_credor WHERE ordem_de_servico_id = ?");
$repasse_query->bind_param("i", $os_id);
$repasse_query->execute();
$repasse_result = $repasse_query->get_result();
while ($repasse = $repasse_result->fetch_assoc()) {
    $total_repasses += $repasse['total_repasse'];
}

// Calcular valor líquido pago
$valor_pago_liquido = $total_pagamentos - $total_devolucoes;

// Calcular saldo
$saldo = $valor_pago_liquido - $ordem_servico['total_os'] - $total_repasses;

$temItensNaoLiquidados = false;
foreach ($ordem_servico_itens as $item) {
    if ($item['status'] != 'liquidado') {
        $temItensNaoLiquidados = true;
        break;
    }
}

/* ===================== LOGS DE LIQUIDAÇÃO (atos liquidados + manuais) ===================== */
$logs_liquidacao = [];

// 1) Atos liquidados "normais"
$stmtLogs1 = $conn->prepare("SELECT * FROM atos_liquidados WHERE ordem_servico_id = ?");
$stmtLogs1->bind_param("i", $os_id);
$stmtLogs1->execute();
$resLogs1 = $stmtLogs1->get_result();
while ($r = $resLogs1->fetch_assoc()) {
    // Tenta inferir um campo de data (fallbacks comuns em seus módulos)
    $r['data_log'] = $r['data_liquidacao']
        ?? $r['created_at']
        ?? $r['data_cadastro']
        ?? $r['data']
        ?? $r['momento']
        ?? null;
    $r['origem'] = 'atos';
    $logs_liquidacao[] = $r;
}
$stmtLogs1->close();

// 2) Atos liquidados "manuais"
$stmtLogs2 = $conn->prepare("SELECT * FROM atos_manuais_liquidados WHERE ordem_servico_id = ?");
$stmtLogs2->bind_param("i", $os_id);
$stmtLogs2->execute();
$resLogs2 = $stmtLogs2->get_result();
while ($r = $resLogs2->fetch_assoc()) {
    $r['data_log'] = $r['data_liquidacao']
        ?? $r['created_at']
        ?? $r['data_cadastro']
        ?? $r['data']
        ?? $r['momento']
        ?? null;
    $r['origem'] = 'manuais';
    $logs_liquidacao[] = $r;
}
$stmtLogs2->close();

// Ordena decrescente pela data (quando existir)
usort($logs_liquidacao, function($a, $b){
    $da = $a['data_log'] ?? '';
    $db = $b['data_log'] ?? '';
    return strcmp($db, $da);
});

/* ================================================================================ */

// ===== Detectar Protocolo nas Observações e localizar o pedido de certidão =====
$pedido_id = null;
$pedido_token = null;
$pedido_protocolo = null;


// Procura "Protocolo: XXXXX" nas observações (aceita letras, números e hifens)
if (!empty($ordem_servico['observacoes']) &&
    preg_match('/Protocolo:\s*([A-Za-z0-9\-]+)/u', $ordem_servico['observacoes'], $m)) {

    $pedido_protocolo = $m[1];

    // Buscar pedido por protocolo
    $stmtPedido = $conn->prepare("SELECT id, token_publico, status FROM pedidos_certidao WHERE protocolo = ? LIMIT 1");
    $stmtPedido->bind_param("s", $pedido_protocolo);
    if ($stmtPedido->execute()) {
        $resPedido = $stmtPedido->get_result();
        if ($row = $resPedido->fetch_assoc()) {
            $pedido_id            = (int)$row['id'];
            $pedido_token         = $row['token_publico'];
            $pedido_status_atual  = $row['status']; // <-- NOVO
        }
    }
    $stmtPedido->close();

    // Em caso de não achar, garante variável definida
    if (!isset($pedido_status_atual)) { $pedido_status_atual = null; }

}

// Sinalizadores para JS
$todos_itens_liquidados = !$temItensNaoLiquidados && count($ordem_servico_itens) > 0;
$algum_item_liquidado   = $has_liquidated || ($total_liquidado > 0);

?>
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Visualizar Ordem de Serviço</title>  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">  
    <style>  
        /* ===================== DESIGN SYSTEM ===================== */  
        :root {  
            --brand-primary: #4f46e5;  
            --brand-secondary: #818cf8;  
            --brand-success: #10b981;  
            --brand-warning: #f59e0b;  
            --brand-error: #ef4444;  
            --brand-info: #06b6d4;  

            --text-primary: #111827;  
            --text-secondary: #4b5563;  
            --text-tertiary: #9ca3af;  

            --bg-primary: #ffffff;  
            --bg-secondary: #f9fafb;  
            --bg-tertiary: #f3f4f6;  
            --bg-elevated: #ffffff;  

            --border-primary: #e5e7eb;  
            --border-secondary: #d1d5db;  

            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);  
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);  
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);  
            --gradient-error: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);  
            --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);  

            --shadow: 0 1px 3px rgba(16,24,40,.1), 0 1px 2px rgba(16,24,40,.06);  
            --shadow-md: 0 4px 6px -1px rgba(16,24,40,.1), 0 2px 4px -1px rgba(16,24,40,.06);  
            --shadow-lg: 0 10px 15px -3px rgba(16,24,40,.1), 0 4px 6px -2px rgba(16,24,40,.05);  
            --shadow-xl: 0 20px 25px -5px rgba(16,24,40,.1), 0 10px 10px -5px rgba(16,24,40,.04);  

            --radius-sm: 6px;  
            --radius-md: 10px;  
            --radius-lg: 14px;  
            --radius-xl: 18px;  

            --space-xs: 4px;  
            --space-sm: 8px;  
            --space-md: 16px;  
            --space-lg: 24px;  
            --space-xl: 32px;  
            --space-2xl: 48px;  
        }  

        .dark-mode {  
            --text-primary: #f9fafb;  
            --text-secondary: #d1d5db;  
            --text-tertiary: #9ca3af;  
            --bg-primary: #111827;  
            --bg-secondary: #1f2937;  
            --bg-tertiary: #374151;  
            --bg-elevated: #1f2937;  
            --border-primary: #374151;  
            --border-secondary: #4b5563;  
        }  

        /* ===================== BADGES STATUS ===================== */  
        .situacao-pago, .situacao-ativo, .situacao-cancelado, .situacao-isento {  
            padding: 6px 16px;  
            border-radius: var(--radius-lg);  
            display: inline-flex;  
            align-items: center;  
            justify-content: center;  
            font-size: 13px;  
            font-weight: 700;  
            letter-spacing: 0.02em;  
            white-space: nowrap;  
            box-shadow: var(--shadow);  
            transition: all 0.3s ease;  
        }  

        .situacao-pago {  
            background: var(--gradient-success);  
            color: white;  
        }  

        .situacao-ativo {  
            background: var(--gradient-warning);  
            color: white;  
        }  

        .situacao-cancelado {  
            background: var(--gradient-error);  
            color: white;  
        }  

        /* ISENTO (cinza) */
        .situacao-isento{
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color:#fff;
        }

        /* ===================== STATUS LABELS ===================== */  
        .status-label {  
            padding: 6px 14px;  
            border-radius: var(--radius-md);  
            color: white;  
            text-align: center;  
            white-space: nowrap;  
            font-size: 12px;  
            font-weight: 700;  
            text-transform: uppercase;  
            letter-spacing: 0.05em;  
            display: inline-block;  
            box-shadow: var(--shadow-sm);  
        }  

        .status-pendente {  
            background: var(--gradient-error);  
        }  

        .status-liquidado {  
            background: var(--gradient-success);  
        }  

        .status-parcial {  
            background: var(--gradient-warning);  
        }  

        /* ===================== BOTÕES MODERNOS ===================== */  
        .btn {  
            border-radius: var(--radius-md);  
            font-weight: 600;  
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
            box-shadow: var(--shadow);  
            position: relative;  
            overflow: hidden;  
        }  

        .btn:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-lg);  
            color: white;
        }  

        .btn:active {  
            transform: translateY(0);  
            box-shadow: var(--shadow);  
        }  

        .btn-primary {  
            background: var(--gradient-primary);  
            border: none;  
            color: white;  
        }  

        .btn-success {  
            background: var(--gradient-success);  
            border: none;  
            color: white;  
        }  

        .btn-warning {  
            background: var(--gradient-warning);  
            border: none;  
            color: white;  
        }  

        .btn-danger {  
            background: var(--gradient-error);  
            border: none;  
            color: white;  
        }  

        .btn-info2 {  
            background: linear-gradient(135deg, #085f6d 0%, #064652 100%);  
            color: white;  
            border: none;  
        }  

        .btn-info3 {  
            background: var(--gradient-info);  
            color: white;  
            border: none;  
        }  

        .btn-edit2 {  
            background: var(--gradient-warning);  
            color: white;  
            border: none;  
        }  

        .btn-4 {  
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);  
            color: white;  
            border: none;  
        }  

        .btn-receipt-a4 {  
            background: linear-gradient(135deg, #006d98 0%, #004d6d 100%);  
            color: white;  
            border: none;  
        }  

        .btn-liquidartudo {  
            background: linear-gradient(135deg, #1f009e 0%, #160070 100%);  
            color: white;  
            border: none;  
        }  

        /* ===================== MODAIS MODERNOS ===================== */  
        .modal-modern .modal-content {  
            border-radius: 16px;  
            border: 1px solid var(--border-primary);  
            box-shadow: var(--shadow-xl);  
            background: var(--bg-elevated);  
        }  

        .modal-modern .modal-header.modern {  
            background: linear-gradient(135deg, rgba(79,70,229,.08), rgba(99,102,241,.08));  
            border-bottom: 1px solid var(--border-primary);  
            padding: 14px 20px;  
            border-radius: 16px 16px 0 0;  
        }  

        .modal-modern .md-title-icon {  
            width: 40px;  
            height: 40px;  
            border-radius: 12px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            background: var(--gradient-primary);  
            color: white;  
            box-shadow: var(--shadow-md);  
        }  

        .modal-modern .btn-close.modern {  
            font-size: 1.8rem;  
            line-height: 1;  
            opacity: 0.7;  
            border: 0;  
            background: transparent;  
            transition: all 0.2s ease;  
            cursor: pointer;  
            padding: 0;  
            width: 32px;  
            height: 32px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            border-radius: 8px;  
        }  

        .modal-modern .btn-close.modern:hover {  
            opacity: 1;  
            transform: scale(1.1);  
            background: rgba(239,68,68,0.1);  
            color: var(--brand-error);  
        }  

        /* Larguras responsivas */  
        #pagamentoModal .modal-dialog {  
            max-width: min(900px, 95vw);  
        }  

        #anexoModal .modal-dialog {  
            max-width: min(800px, 92vw);  
        }  

        #tarefaModal .modal-dialog {  
            max-width: min(900px, 90vw);  
        }  

        /* ===================== STATS GRID ===================== */  
        .stats-grid {  
            display: grid;  
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));  
            gap: 14px;  
            margin-bottom: 16px;  
        }  

        .stat-card {  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-lg);  
            padding: 14px 16px;  
            background: linear-gradient(180deg, rgba(148,163,184,.04), rgba(148,163,184,0));  
            transition: all 0.3s ease;  
        }  

        .stat-card:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-md);  
            border-color: var(--brand-primary);  
        }  

        .stat-label {  
            font-size: 11px;  
            color: var(--text-tertiary);  
            font-weight: 700;  
            letter-spacing: 0.05em;  
            text-transform: uppercase;  
            margin-bottom: 6px;  
        }  

        .stat-card .form-control {  
            border: 0;  
            background: transparent;  
            padding: 0;  
            height: auto;  
            font-weight: 800;  
            font-size: 1.1rem;  
            color: var(--text-primary);  
        }  

        /* ===================== FORM GRID ===================== */  
        .form-grid {  
            display: grid;  
            grid-template-columns: 1fr;  
            gap: 14px;  
        }  

        @media (min-width: 768px) {  
            .form-grid {  
                grid-template-columns: 1fr 1fr;  
            }  
            .grid-span-2 {  
                grid-column: span 2;  
            }  
        }  

        .input-group-text {  
            background: var(--bg-tertiary);  
            border-color: var(--border-primary);  
            font-weight: 600;  
        }  

        /* ===================== SECTION TITLE ===================== */  
        .section-title {  
            font-weight: 800;  
            font-size: 15px;  
            margin: 20px 0 12px;  
            color: var(--text-primary);  
            letter-spacing: -0.01em;  
        }  

        /* ===================== TABELAS MODERNAS ===================== */  
        .table-modern {  
            border-collapse: separate;  
            border-spacing: 0;  
            border-radius: var(--radius-lg);  
            overflow: hidden;  
            box-shadow: var(--shadow);  
        }  

        .table-modern thead th {  
            background: var(--bg-tertiary);  
            border-bottom: 2px solid var(--border-primary);  
            font-weight: 700;  
            text-transform: uppercase;  
            font-size: 11px;  
            letter-spacing: 0.05em;  
            color: var(--text-secondary);  
            padding: 12px 16px;  
        }  

        .table-modern tbody tr {  
            transition: all 0.2s ease;  
        }  

        .table-modern tbody tr:hover {  
            background: var(--bg-secondary);  
        }  

        .table-modern tbody td {  
            padding: 12px 16px;  
            border-bottom: 1px solid var(--border-primary);  
            color: var(--text-primary);  
        }  

        /* ===================== UPLOAD CARD ===================== */  
        .upload-card {  
            display: flex;  
            align-items: center;  
            gap: 14px;  
            border: 2px dashed var(--brand-primary);  
            background: linear-gradient(135deg, rgba(79,70,229,.05), rgba(99,102,241,.05));  
            border-radius: var(--radius-lg);  
            padding: 16px;  
            transition: all 0.3s ease;  
        }  

        .upload-card:hover {  
            background: linear-gradient(135deg, rgba(79,70,229,.08), rgba(99,102,241,.08));  
            border-color: var(--brand-secondary);  
        }  

        .upload-card i {  
            font-size: 28px;  
            color: var(--brand-primary);  
        }  

        /* ===================== CUSTOM FILE ===================== */  
        #anexoModal .custom-file-label {  
            border: 2px solid var(--border-primary);  
            background: var(--bg-secondary);  
            color: var(--text-primary);  
            border-radius: var(--radius-md);  
            font-weight: 600;  
            cursor: pointer;  
            transition: all 0.3s ease;  
        }  

        #anexoModal .custom-file-label:hover {  
            border-color: var(--brand-primary);  
            background: var(--bg-tertiary);  
        }  

        #anexoModal .custom-file-input {  
            cursor: pointer;  
        }  

        .custom-file-input ~ .custom-file-label::after {  
            content: "Escolher";  
            background: var(--gradient-primary);  
            color: white;  
            border-radius: 0 var(--radius-md) var(--radius-md) 0;  
            font-weight: 700;  
        }  

        /* ===================== HEADER SECTION ===================== */  
        .header-actions {  
            display: flex;  
            flex-wrap: wrap;  
            gap: 10px;  
            justify-content: center;  
            margin-bottom: var(--space-xl);  
        }  

        .header-actions .col-auto {  
            flex: 0 0 auto;  
        }  

        /* ===================== OS HEADER ===================== */  
        .os-header {  
            text-align: center;  
            margin: var(--space-xl) 0;  
        }  

        .os-header h4 {  
            font-weight: 800;  
            font-size: 28px;  
            color: var(--text-primary);  
            margin-bottom: var(--space-sm);  
            letter-spacing: -0.02em;  
        }  

        /* ===================== FORM CONTROLS ===================== */  
        .form-control {  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-md);  
            padding: 10px 14px;  
            font-size: 14px;  
            transition: all 0.3s ease;  
            background: var(--bg-primary);  
            color: var(--text-primary);  
        }  

        .form-control:focus {  
            border-color: var(--brand-primary);  
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);  
            outline: none;  
        }  

        .form-control[readonly] {  
            background: var(--bg-secondary);  
            cursor: not-allowed;  
        }  

        /* ===================== RESPONSIVIDADE ===================== */  
        @media (max-width: 768px) {  
            .btn-sm {  
                font-size: 13px;  
                padding: 8px 12px;  
            }  

            .os-header h4 {  
                font-size: 22px;  
            }  

            .stats-grid {  
                grid-template-columns: 1fr;  
            }  

            .table-responsive {  
                zoom: 85%;  
            }  

            .situacao-pago, .situacao-ativo, .situacao-cancelado {  
                width: 100%;  
                max-width: 280px;  
            }  
        }  

        /* ===================== ANIMAÇÕES ===================== */  
        @keyframes fadeInUp {  
            from {  
                opacity: 0;  
                transform: translateY(20px);  
            }  
            to {  
                opacity: 1;  
                transform: translateY(0);  
            }  
        }  

        .modal.show .modal-content {  
            animation: fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);  
        }  

        /* ===================== BTN DELETE ===================== */  
        .btn-delete {  
            background: var(--gradient-error);  
            color: white;  
            border: none;  
            padding: 6px 12px;  
            border-radius: var(--radius-md);  
            font-size: 13px;  
            transition: all 0.3s ease;  
        }  

        .btn-delete:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-lg);  
        }  

        /* ===================== SWEET ALERT ===================== */  
        .swal2-deny, .swal2-cancel {  
            margin-top: 10px !important;  
            border: 0 !important;  
            border-radius: var(--radius-md) !important;  
            color: #fff !important;  
            font-size: 1em !important;  
            font-weight: 600 !important;  
        }  

        /* ===================== SCROLL TO TOP ===================== */  
        #scrollTop {  
            position: fixed;  
            bottom: 30px;  
            right: 30px;  
            width: 50px;  
            height: 50px;  
            border-radius: 50%;  
            background: var(--gradient-primary);  
            color: white;  
            border: none;  
            box-shadow: var(--shadow-lg);  
            cursor: pointer;  
            opacity: 0;  
            transition: all 0.3s ease;  
            z-index: 1000;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
        }  

        #scrollTop:hover {  
            transform: translateY(-4px);  
            box-shadow: var(--shadow-xl);  
        }  

        /* ===================== HR MODERNO ===================== */  
        hr {  
            border: 0;  
            height: 2px;  
            background: linear-gradient(90deg, transparent, var(--border-primary), transparent);  
            margin: var(--space-xl) 0;  
        }  

        /* ===================== MODAL BODY ===================== */  
        .modal-body {  
            padding: 24px;  
        }  

        .modal-footer {  
            padding: 16px 24px;  
            border-top: 1px solid var(--border-primary);  
        }  

        /* ===================== TÍTULO MODAL ===================== */  
               /* ===================== TÍTULO MODAL ===================== */
        .modal-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
        }

        /* ===================== CARDS MOBILE ===================== */
        @media (max-width: 768px) {
            /* Ocultar tabelas no mobile */
            .table-responsive.mobile-hidden {
                display: none;
            }

            /* Container de cards */
            .mobile-cards {
                display: block;
            }

            /* Card individual */
            .item-card, .log-card {
                background: var(--bg-elevated);
                border: 2px solid var(--border-primary);
                border-radius: var(--radius-lg);
                padding: 16px;
                margin-bottom: 16px;
                box-shadow: var(--shadow-md);
                transition: all 0.3s ease;
            }

            .item-card:hover, .log-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
                border-color: var(--brand-primary);
            }

            /* Header do card */
            .card-header-mobile {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 2px solid var(--border-primary);
            }

            .card-number {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: var(--gradient-primary);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 16px;
                box-shadow: var(--shadow);
            }

            .card-status-mobile {
                font-size: 11px;
                padding: 4px 10px;
            }

            /* Informações do card */
            .card-info {
                display: grid;
                gap: 10px;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
            }

            .info-label {
                font-size: 11px;
                font-weight: 700;
                color: var(--text-tertiary);
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .info-value {
                font-size: 14px;
                font-weight: 600;
                color: var(--text-primary);
                text-align: right;
            }

            .info-value.highlight {
                color: var(--brand-primary);
                font-weight: 800;
            }

            /* Ato destacado */
            .card-ato {
                font-size: 15px;
                font-weight: 800;
                color: var(--brand-primary);
                margin-bottom: 8px;
                display: block;
            }

            /* Descrição */
            .card-description {
                font-size: 13px;
                color: var(--text-secondary);
                margin-bottom: 12px;
                line-height: 1.5;
            }

            /* Valores em grid */
            .valores-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin: 12px 0;
                padding: 12px;
                background: var(--bg-secondary);
                border-radius: var(--radius-md);
            }

            .valor-item {
                text-align: center;
            }

            .valor-label {
                font-size: 10px;
                font-weight: 700;
                color: var(--text-tertiary);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                display: block;
                margin-bottom: 4px;
            }

            .valor-value {
                font-size: 13px;
                font-weight: 700;
                color: var(--text-primary);
                display: block;
            }

            /* Ações do card */
            .card-actions {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 2px solid var(--border-primary);
            }

            /* Botão full width no card */
            .card-actions .btn {
                width: 100%;
                font-weight: 700;
                padding: 12px;
            }

            /* Badge de quantidade */
            .badge-qty {
                background: var(--gradient-info);
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            /* Data no log card */
            .log-date {
                font-size: 11px;
                color: var(--text-tertiary);
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .log-date i {
                color: var(--brand-primary);
            }

            /* Usuário no log card */
            .log-user {
                font-size: 12px;
                color: var(--text-secondary);
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 6px;
                margin-top: 8px;
            }

            .log-user i {
                color: var(--brand-success);
            }
        }

        /* Ocultar cards no desktop */
        @media (min-width: 769px) {
            .mobile-cards {
                display: none;
            }
        }
    </style>  
</head>  
<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<div id="main" class="main-content">  
    <div class="container">  
        <!-- ===================== HEADER ACTIONS ===================== -->  
        <div class="container-fluid">  
            <div class="row justify-content-center align-items-center g-2 header-actions">  
                <div class="col-auto">  
                    <button type="button" class="btn btn-primary btn-sm" onclick="imprimirOS()">  
                        <i class="fa fa-print" aria-hidden="true"></i> Imprimir OS  
                    </button>  
                </div>  
                <div class="col-auto" id="receiptButtons" style="<?php echo ($total_pagamentos > 0) ? '' : 'display:none'; ?>">
                    <div class="btn-group" role="group" aria-label="Recibos">
                        <button type="button" class="btn btn-info2 btn-sm" onclick="imprimirRecibo()">
                            <i class="fa fa-print" aria-hidden="true"></i> Recibo
                        </button>
                        <button type="button" class="btn btn-receipt-a4 btn-sm" onclick="imprimirReciboA4()">
                            <i class="fa fa-print" aria-hidden="true"></i> Recibo A4
                        </button>
                    </div>
                </div>

                <?php if ($ordem_servico['status'] !== 'Cancelado' && $podeVerBotaoPagamentos): ?>
                    <div class="col-auto">  
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#pagamentoModal">  
                            <i class="fa fa-money" aria-hidden="true"></i> Pagamentos  
                        </button>  
                    </div>
                <?php endif; ?>
 
                <div class="col-auto">  
                    <button type="button" class="btn btn-secondary btn-sm" onclick="$('#anexoModal').modal('show');">  
                        <i class="fa fa-paperclip" aria-hidden="true"></i> Anexos  
                    </button>  
                </div>  
                <?php if ($ordem_servico['status'] !== 'Cancelado'): ?>
                    <div class="col-auto">  
                        <button type="button" class="btn btn-edit2 btn-sm" onclick="editarOS()">  
                            <i class="fa fa-pencil" aria-hidden="true"></i> Editar OS  
                        </button>  
                    </div>
                <?php endif; ?>

                <?php if (!$has_liquidated && $ordem_servico['status'] !== 'Cancelado'): ?>
                    <div class="col-auto">  
                        <button type="button" class="btn btn-danger btn-sm" onclick="cancelarOS()">  
                            <i class="fa fa-ban" aria-hidden="true"></i> Cancelar OS  
                        </button>  
                    </div>
                <?php endif; ?>

                <div class="col-auto">  
                    <button type="button" class="btn btn-4 btn-sm" data-toggle="modal" data-target="#tarefaModal">  
                        <i class="fa fa-clock-o" aria-hidden="true"></i> Criar Tarefa  
                    </button>  
                </div>  
                <div class="col-auto">  
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='index.php'">  
                        <i class="fa fa-search" aria-hidden="true"></i> Pesquisar OS  
                    </button>  
                </div>  
                <div class="col-auto">  
                    <button type="button" class="btn btn-info3 btn-sm" onclick="window.location.href='criar_os.php'">  
                        <i class="fa fa-plus" aria-hidden="true"></i> Criar Ordem de Serviço  
                    </button>  
                </div>    
            </div>  
        </div>  

        <hr>  

        <!-- ===================== OS HEADER ===================== -->  
        <div class="os-header">  
            <h4>ORDEM DE SERVIÇO Nº: <?php echo $ordem_servico['id']; ?></h4>  
            <div>  
                <?php
                    $statusLegenda = '';
                    $statusClass = '';

                    if ($ordem_servico['status'] === 'Cancelado') {
                        $statusLegenda = 'Cancelada';
                        $statusClass = 'situacao-cancelado';

                    } elseif ($isIsento) {
                        $statusLegenda = 'Isento';
                        $statusClass = 'situacao-isento';

                    } elseif ($total_pagamentos > 0) {
                        $statusLegenda = 'Pago (Depósito Prévio)';
                        $statusClass = 'situacao-pago';

                    } elseif ($ordem_servico['status'] === 'Ativo') {
                        $statusLegenda = 'Ativa (Pendente de Pagamento)';
                        $statusClass = 'situacao-ativo';
                    }

                    if ($statusLegenda) {
                        // adiciona id + data-os-status + data-isento para o JS
                        echo '<span id="osStatusBadge" class="' . $statusClass
                            . '" data-os-status="' . htmlspecialchars($ordem_servico['status'])
                            . '" data-isento="' . ($isIsento ? '1' : '0') . '">'
                            . $statusLegenda . '</span>';
                    }
                ?>

            </div>  
            <?php if ($pedido_id): ?>  
                    <div class="col-auto">  
                        <!-- <button   
                            type="button"   
                            class="btn btn-outline-primary btn-sm"   
                            id="btnAtualizarPedido"   
                            onclick="atualizarStatusPedido()">  
                            <i class="fa fa-refresh" aria-hidden="true"></i> Atualizar status do pedido de certidão  
                        </button>   -->
                        <div class="text-center mt-1">  
                            <small class="text-muted">  
                                Protocolo: <?php echo htmlspecialchars($pedido_protocolo); ?>  
                            </small>  
                        </div>  
                    </div>  
            <?php endif; ?>
        </div>  

        <hr>  

        <!-- ===================== FORMULÁRIO OS ===================== -->  
        <form id="osForm" method="POST">  
            <div class="form-row">  
                <div class="form-group col-md-5">  
                    <label for="cliente">Apresentante:</label>  
                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo $ordem_servico['cliente']; ?>" readonly>  
                </div>  
                <div class="form-group col-md-3">  
                    <label for="cpf_cliente">CPF/CNPJ do Apresentante:</label>  
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente" value="<?php echo $ordem_servico['cpf_cliente']; ?>" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="base_calculo">Base de Cálculo:</label>  
                    <input type="text" class="form-control" id="base_calculo" name="base_calculo" value="<?php echo 'R$ ' . number_format($ordem_servico['base_de_calculo'], 2, ',', '.'); ?>" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="total_os">Valor Total:</label>  
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo 'R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.'); ?>" readonly>  
                </div>  
                <div class="form-group col-md-3">  
                    <label for="deposito_previo">Depósito Prévio:</label>  
                    <input type="text" class="form-control" id="deposito_previo" name="deposito_previo" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>  
                </div>  
                <?php if ($total_liquidado > 0): ?>  
                <div class="form-group col-md-3">  
                    <label for="valor_liquidado">Valor Liquidado:</label>  
                    <input type="text" class="form-control" id="valor_liquidado" name="valor_liquidado" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>  
                </div>  
                <?php endif; ?>  
                <?php if ($total_devolucoes > 0): ?>  
                <div class="form-group col-md-3">  
                    <label for="valor_devolvido">Valor Devolvido:</label>  
                    <input type="text" class="form-control" id="valor_devolvido" name="valor_devolvido" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>  
                </div>  
                <?php endif; ?>  
                <?php if ($total_repasses > 0): ?>  
                <div class="form-group col-md-3">  
                    <label for="total_repasses">Repasse Credor:</label>  
                    <input type="text" class="form-control" id="total_repasses" name="total_repasses" value="<?php echo 'R$ ' . number_format($total_repasses, 2, ',', '.'); ?>" readonly>  
                </div>  
                <?php endif; ?>  
                <?php if ($saldo != 0): ?>  
                <div class="form-group col-md-3">  
                    <label for="saldo">Saldo:</label>  
                    <input type="text" class="form-control" id="saldo" name="saldo" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>  
                </div>  
                <?php endif; ?>  
            </div>  
            <div class="form-row">  
                <div class="form-group col-md-10">  
                    <label for="descricao_os">Título da OS:</label>  
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo $ordem_servico['descricao_os']; ?>" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="data_os">Data da OS:</label>  
                    <input type="text" class="form-control" id="data_os" name="data_os" value="<?php echo date('d/m/Y', strtotime($ordem_servico['data_criacao'])); ?>" readonly>  
                </div>  
            </div>  
            <div class="form-row">  
                <div class="form-group col-md-12">  
                    <label for="observacoes">Observações:</label>  
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4" readonly><?php echo $ordem_servico['observacoes']; ?></textarea>  
                </div>  
            </div>  
        </form>  

        <!-- ===================== ITENS DA OS ===================== -->  
        <div id="osItens" class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="m-0">Itens da Ordem de Serviço</h4>

                <?php if ($temItensNaoLiquidados && $qtde_pagamentos > 0): ?>
                <button 
                    type="button" 
                    class="btn btn-liquidartudo btn-sm" 
                    onclick="liquidarTudo()" 
                    id="btnLiquidarTudo">
                    <i class="fa fa-check-circle"></i> Liquidar Tudo
                </button>
                <?php endif; ?>

            </div>

            <!-- TABELA DESKTOP -->
            <div class="table-responsive mobile-hidden"> 
                <table id="tabelaItensOS" class="table table-striped table-bordered table-modern">  
                    <thead>  
                        <tr>  
                            <th>#</th>  
                            <th>Ato</th>  
                            <th>Qtd</th>  
                            <th>Desconto Legal (%)</th>  
                            <th>Descrição</th>  
                            <th>Emolumentos</th>  
                            <th>FERC</th>  
                            <th>FADEP</th>  
                            <th>FEMP</th>  
                            <th>FERRFIS</th>  
                            <th>Total</th>  
                            <th>Qtd Liquidada</th>  
                            <th>Status</th>  
                            <th>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody id="itensTable">  
                        <?php foreach ($ordem_servico_itens as $item): ?>  
                        <tr>  
                            <td><?php echo $item['ordem_exibicao']; ?></td>  
                            <td><?php echo $item['ato']; ?></td>  
                            <td><?php echo $item['quantidade']; ?></td>  
                            <td><?php echo $item['desconto_legal']; ?></td>  
                            <td><?php echo $item['descricao']; ?></td>  
                            <td><?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></td>  
                            <td><?php echo number_format($item['ferc'], 2, ',', '.'); ?></td>  
                            <td><?php echo number_format($item['fadep'], 2, ',', '.'); ?></td>  
                            <td><?php echo number_format($item['femp'], 2, ',', '.'); ?></td>  
                            <td><?php echo number_format($item['ferrfis'] ?? 0, 2, ',', '.'); ?></td>  
                            <td><?php echo number_format($item['total'], 2, ',', '.'); ?></td>  
                            <td><?php echo $item['quantidade_liquidada']; ?></td>  
                            <td>  
                                <?php if ($item['status'] == 'liquidado'): ?>  
                                    <span class="status-label status-liquidado">Liquidado</span>  
                                <?php elseif ($item['status'] == 'Cancelado'): ?>  
                                    <span class="status-label status-pendente">Cancelado</span>  
                                <?php elseif ($item['status'] == 'parcialmente liquidado'): ?>  
                                    <span class="status-label status-parcial">Liq. Parcialmente</span>  
                                <?php else: ?>  
                                    <span class="status-label status-pendente">Pendente</span>  
                                <?php endif; ?>  
                            </td>  
                            <td>  
                                <?php if ($item['status'] != 'Cancelado' && $item['status'] != 'liquidado' && $qtde_pagamentos > 0): ?>  
                                    <button type="button" class="btn btn-primary btn-sm"  
                                    onclick="liquidarAto(  
                                        <?php echo $item['id']; ?>,  
                                        <?php echo $item['quantidade']; ?>,  
                                        <?php echo $item['quantidade_liquidada'] !== null ? $item['quantidade_liquidada'] : 0; ?>,  
                                        <?php echo floatval($item['total']); ?>  
                                    )">  
                                        <i class="fa fa-check"></i> Liquidar  
                                    </button>  
                                <?php endif; ?>
                            </td>  
                        </tr>  
                        <?php endforeach; ?>  
                                        </tbody>
                </table>
            </div>

            <!-- CARDS MOBILE -->
            <div class="mobile-cards">
                <?php foreach ($ordem_servico_itens as $item): ?>
                <div class="item-card">
                    <!-- Header do Card -->
                    <div class="card-header-mobile">
                        <span class="card-number"><?php echo $item['ordem_exibicao']; ?></span>
                        <span class="card-status-mobile 
                            <?php 
                                if ($item['status'] == 'liquidado') echo 'status-label status-liquidado';
                                elseif ($item['status'] == 'Cancelado') echo 'status-label status-pendente';
                                elseif ($item['status'] == 'parcialmente liquidado') echo 'status-label status-parcial';
                                else echo 'status-label status-pendente';
                            ?>">
                            <?php 
                                if ($item['status'] == 'liquidado') echo 'Liquidado';
                                elseif ($item['status'] == 'Cancelado') echo 'Cancelado';
                                elseif ($item['status'] == 'parcialmente liquidado') echo 'Liq. Parcialmente';
                                else echo 'Pendente';
                            ?>
                        </span>
                    </div>

                    <!-- Ato -->
                    <span class="card-ato">
                        <i class="fa fa-file-text-o"></i> <?php echo $item['ato']; ?>
                    </span>

                    <!-- Descrição -->
                    <?php if (!empty($item['descricao'])): ?>
                    <div class="card-description">
                        <?php echo $item['descricao']; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Informações Principais -->
                    <div class="info-row">
                        <span class="info-label">Quantidade</span>
                        <span class="info-value">
                            <span class="badge-qty">
                                <i class="fa fa-cubes"></i>
                                <?php echo $item['quantidade']; ?>
                            </span>
                        </span>
                    </div>

                    <?php if ($item['quantidade_liquidada'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Qtd Liquidada</span>
                        <span class="info-value highlight">
                            <i class="fa fa-check-circle"></i>
                            <?php echo $item['quantidade_liquidada']; ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($item['desconto_legal'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Desconto Legal</span>
                        <span class="info-value"><?php echo $item['desconto_legal']; ?>%</span>
                    </div>
                    <?php endif; ?>

                    <!-- Grid de Valores -->
                    <div class="valores-grid">
                        <div class="valor-item">
                            <span class="valor-label">Emolumentos</span>
                            <span class="valor-value">R$ <?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="valor-item">
                            <span class="valor-label">FERC</span>
                            <span class="valor-value">R$ <?php echo number_format($item['ferc'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="valor-item">
                            <span class="valor-label">FADEP</span>
                            <span class="valor-value">R$ <?php echo number_format($item['fadep'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="valor-item">
                            <span class="valor-label">FEMP</span>
                            <span class="valor-value">R$ <?php echo number_format($item['femp'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="valor-item">
                            <span class="valor-label">FERRFIS</span>
                            <span class="valor-value">R$ <?php echo number_format($item['ferrfis'] ?? 0, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="info-row" style="margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--border-primary);">
                        <span class="info-label" style="font-size: 13px;">Valor Total</span>
                        <span class="info-value highlight" style="font-size: 18px;">
                            R$ <?php echo number_format($item['total'], 2, ',', '.'); ?>
                        </span>
                    </div>

                    <!-- Ações -->
                    <?php if ($item['status'] != 'Cancelado' && $item['status'] != 'liquidado' && $qtde_pagamentos > 0): ?>
                    <div class="card-actions">
                        <button type="button" class="btn btn-primary btn-sm"
                        onclick="liquidarAto(
                            <?php echo $item['id']; ?>,
                            <?php echo $item['quantidade']; ?>,
                            <?php echo $item['quantidade_liquidada'] !== null ? $item['quantidade_liquidada'] : 0; ?>,
                            <?php echo floatval($item['total']); ?>
                        )">
                            <i class="fa fa-check"></i> Liquidar Ato
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===================== LOGS DE LIQUIDAÇÃO ===================== -->  
        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="m-0">Logs de Liquidação</h4>
            </div>

            <!-- TABELA DESKTOP -->
            <div class="table-responsive mobile-hidden">  
                <table id="tabelaLogsLiquidacao" class="table table-striped table-bordered table-modern">  
                    <thead>  
                        <tr>  
                            <th>#</th>  
                            <th>Ato</th>  
                            <th>Qtd</th>  
                            <th>Emolumentos</th>  
                            <th>FERC</th>  
                            <th>FADEP</th>  
                            <th>FEMP</th>  
                            <th>FERRFIS</th>  
                            <th>Total</th>  
                            <th>Usuário</th>  
                            <th>Data</th>  
                        </tr>  
                    </thead>  
                    <tbody>  
                        <?php if (!empty($logs_liquidacao)): ?>  
                            <?php foreach ($logs_liquidacao as $idx => $log):   
                                $dataFmt = '-';  
                                if (!empty($log['data_log'])) {  
                                    $t = strtotime($log['data_log']);  
                                    if ($t) { $dataFmt = date('d/m/Y H:i', $t); }  
                                }  
                            ?>  
                            <tr>  
                                <td><?php echo $idx + 1; ?></td>  
                                <td><?php echo htmlspecialchars($log['ato']); ?></td>  
                                <td><?php echo (int)($log['quantidade_liquidada'] ?? 0); ?></td>  
                                <td><?php echo 'R$ ' . number_format((float)($log['emolumentos'] ?? 0), 2, ',', '.'); ?></td>  
                                <td><?php echo 'R$ ' . number_format((float)($log['ferc'] ?? 0), 2, ',', '.'); ?></td>  
                                <td><?php echo 'R$ ' . number_format((float)($log['fadep'] ?? 0), 2, ',', '.'); ?></td>  
                                <td><?php echo 'R$ ' . number_format((float)($log['femp'] ?? 0), 2, ',', '.'); ?></td>  
                                <td><?php echo 'R$ ' . number_format((float)($log['ferrfis'] ?? 0), 2, ',', '.'); ?></td>  
                                <td><?php echo 'R$ ' . number_format((float)($log['total'] ?? 0), 2, ',', '.'); ?></td>  
                                <td><?php echo htmlspecialchars($log['funcionario'] ?? ''); ?></td>  
                                <td><?php echo $dataFmt; ?></td>  
                            </tr>  
                            <?php endforeach; ?>  
                        <?php else: ?>
                            <!-- tbody vazio; DataTables exibirá a mensagem configured em "emptyTable" -->
                        <?php endif; ?>
                                        </tbody>
                </table>
            </div>

            <!-- CARDS MOBILE -->
            <div class="mobile-cards">
                <?php if (!empty($logs_liquidacao)): ?>
                    <?php foreach ($logs_liquidacao as $idx => $log): 
                        $dataFmt = '-';
                        if (!empty($log['data_log'])) {
                            $t = strtotime($log['data_log']);
                            if ($t) { $dataFmt = date('d/m/Y H:i', $t); }
                        }
                    ?>
                    <div class="log-card">
                        <!-- Header do Card -->
                        <div class="card-header-mobile">
                            <span class="card-number"><?php echo $idx + 1; ?></span>
                            <span class="log-date">
                                <i class="fa fa-calendar"></i>
                                <?php echo $dataFmt; ?>
                            </span>
                        </div>

                        <!-- Ato -->
                        <span class="card-ato">
                            <i class="fa fa-file-text-o"></i> <?php echo htmlspecialchars($log['ato']); ?>
                        </span>

                        <!-- Quantidade -->
                        <div class="info-row">
                            <span class="info-label">Quantidade Liquidada</span>
                            <span class="info-value">
                                <span class="badge-qty">
                                    <i class="fa fa-check-circle"></i>
                                    <?php echo (int)($log['quantidade_liquidada'] ?? 0); ?>
                                </span>
                            </span>
                        </div>

                        <!-- Grid de Valores -->
                        <div class="valores-grid">
                            <div class="valor-item">
                                <span class="valor-label">Emolumentos</span>
                                <span class="valor-value">R$ <?php echo number_format((float)($log['emolumentos'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                            <div class="valor-item">
                                <span class="valor-label">FERC</span>
                                <span class="valor-value">R$ <?php echo number_format((float)($log['ferc'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                            <div class="valor-item">
                                <span class="valor-label">FADEP</span>
                                <span class="valor-value">R$ <?php echo number_format((float)($log['fadep'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                            <div class="valor-item">
                                <span class="valor-label">FEMP</span>
                                <span class="valor-value">R$ <?php echo number_format((float)($log['femp'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                            <div class="valor-item">
                                <span class="valor-label">FERRFIS</span>
                                <span class="valor-value">R$ <?php echo number_format((float)($log['ferrfis'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="info-row" style="margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--border-primary);">
                            <span class="info-label" style="font-size: 13px;">Valor Total</span>
                            <span class="info-value highlight" style="font-size: 18px;">
                                R$ <?php echo number_format((float)($log['total'] ?? 0), 2, ',', '.'); ?>
                            </span>
                        </div>

                        <!-- Funcionário -->
                        <div class="log-user">
                            <i class="fa fa-user"></i>
                            Liquidado por: <strong><?php echo htmlspecialchars($log['funcionario'] ?? ''); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item-card" style="text-align: center; padding: 32px;">
                        <i class="fa fa-info-circle" style="font-size: 48px; color: var(--text-tertiary); margin-bottom: 16px;"></i>
                        <p class="text-muted m-0">Nenhum ato liquidado até o momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div> 
    </div>  
</div>  

<!-- ===================== MODAL DE PAGAMENTO ===================== -->  
<div class="modal fade modal-modern" id="pagamentoModal" tabindex="-1" role="dialog" aria-labelledby="pagamentoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">  
        <div class="modal-content">  
            <div class="modal-header modern">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-money"></i></span>  
                    <h5 class="modal-title m-0" id="pagamentoModalLabel">Efetuar Pagamento</h5>  
                </div>  
                <button type="button" class="btn-close modern" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  

            <div class="modal-body">  
                <!-- Stats Grid -->  
                <div class="stats-grid">  
                    <div class="stat-card">  
                        <div class="stat-label">Valor Total da OS</div>  
                        <input type="text" class="form-control" id="total_os_modal" value="<?php echo 'R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.'); ?>" readonly>

                        <?php if ((float)$ordem_servico['total_os'] == 0.0 && (int)$qtde_pagamentos === 0): ?>
                            <button type="button"
                                    class="btn btn-warning w-100 mt-2"
                                    id="btnIsentoPagamento"
                                    onclick="isentarPagamento()">
                                <i class="fa fa-ban"></i> Isento de pagamento
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_pagamentos > 0): ?>  
                    <div class="stat-card">  
                        <div class="stat-label">Valor Pago</div>  
                        <input type="text" class="form-control" id="total_pagamento_modal" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>  
                    </div>  
                    <?php endif; ?>  

                    <?php if ($total_liquidado > 0): ?>  
                    <div class="stat-card">  
                        <div class="stat-label">Valor Liquidado</div>  
                        <input type="text" class="form-control" id="valor_liquidado_modal" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>  
                    </div>  
                    <?php endif; ?>  

                    <?php if ($saldo != 0): ?>  
                    <div class="stat-card">  
                        <div class="stat-label">Saldo</div>  
                        <input type="text" class="form-control" id="saldo_modal" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>  
                    </div>  
                    <?php endif; ?>  

                    <?php if ($total_devolucoes > 0): ?>  
                    <div class="stat-card">  
                        <div class="stat-label">Valor Devolvido</div>  
                        <input type="text" class="form-control" id="valor_devolvido_modal" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>  
                    </div>  
                    <?php endif; ?>  

                    <?php if ($total_repasses > 0): ?>  
                    <div class="stat-card">  
                        <div class="stat-label">Repasse Credor</div>  
                        <input type="text" class="form-control" id="total_repasses_modal" value="<?php echo 'R$ ' . number_format($total_repasses, 2, ',', '.'); ?>" readonly>  
                    </div>  
                    <?php endif; ?>  
                </div>  

                <?php if ((float)$ordem_servico['total_os'] > 0.0): ?>
                <h6 class="section-title">Adicionar pagamento</h6>  
                <div class="form-grid">  
                    <div class="form-group">  
                        <label for="forma_pagamento">Forma de Pagamento</label>  
                        <select class="form-control" id="forma_pagamento">  
                            <option value="">Selecione</option>  
                            <option value="Espécie">Espécie</option>  
                            <option value="Crédito">Crédito</option>  
                            <option value="Débito">Débito</option>  
                            <option value="PIX">PIX</option>  
                            <option value="Centrais Eletrônicas">Centrais Eletrônicas</option>  
                            <option value="Transferência Bancária">Transferência Bancária</option>  
                            <option value="Depósito Bancário">Depósito Bancário</option>  
                            <option value="Boleto">Boleto</option>  
                            <option value="Cheque">Cheque</option>  
                            <?php if ($__controlarPorAcessosAdicionais): ?>
                                <option value="Ato Isento">Ato Isento</option>
                            <?php endif; ?>
                        </select>  
                    </div>  

                    <div class="form-group">  
                        <label for="valor_pagamento">Valor do Pagamento</label>  
                        <div class="input-group">  
                            <div class="input-group-prepend"><span class="input-group-text">R$</span></div>  
                            <input type="text" class="form-control" id="valor_pagamento">  
                        </div>  
                    </div>  

                    <div class="grid-span-2">  
                        <button type="button" id="btnAdicionarPagamento" class="btn btn-primary w-100" onclick="adicionarPagamento()">  
                            <i class="fa fa-plus"></i> Adicionar Pagamento  
                        </button>  
                    </div>  
                </div>
                <?php endif; ?>

                <?php if ($saldo > 0.01 || $has_ato_17): ?>  
                <h6 class="section-title">Ações rápidas</h6>  
                <div class="form-grid">  
                    <?php if ($saldo > 0.01 && $has_ato_17): ?>  
                        <button type="button" class="btn btn-warning" onclick="abrirRepasseModal()">  
                            <i class="fa fa-exchange"></i> Repasse Credor  
                        </button>  
                    <?php endif; ?>  
                    <?php if ($saldo > 0.01): ?>  
                        <button type="button" class="btn btn-warning" onclick="abrirDevolucaoModal()">  
                            <i class="fa fa-undo"></i> Devolver valores  
                        </button>  
                    <?php endif; ?>  
                </div>  
                <?php endif; ?>  

                <h6 class="section-title">Pagamentos adicionados</h6>  
                <div class="table-responsive">  
                    <table id="tabelaIPagamentoOS" class="table table-striped table-bordered table-modern">  
                        <thead>  
                            <tr>  
                                <th>Forma de Pagamento</th>  
                                <th>Valor</th>  
                                <th>Data Pagamento</th>  
                                <th>Funcionário</th>  
                                <th>Ações</th>  
                            </tr>  
                        </thead>  
                        <tbody id="pagamentosTable">  
                            <?php foreach ($pagamentos as $pagamento):  
                                $dataPagtoBr = date('d/m/Y H:i', strtotime($pagamento['data_pagamento']));  
                                $isToday   = (date('Y-m-d', strtotime($pagamento['data_pagamento'])) === date('Y-m-d'));  
                                $canDelete = !$has_liquidated && $isToday;  
                            ?>  
                            <tr>  
                                <td><?php echo htmlspecialchars($pagamento['forma_de_pagamento']); ?></td>  
                                <td><?php echo 'R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.'); ?></td>  
                                <td><?php echo $dataPagtoBr; ?></td>  
                                <td><?php echo htmlspecialchars($pagamento['funcionario']); ?></td>  
                                <td>  
                                    <?php if ($canDelete): ?>  
                                        <button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="confirmarRemocaoPagamento(<?php echo $pagamento['id']; ?>)">  
                                            <i class="fa fa-trash" aria-hidden="true"></i>  
                                        </button>  
                                    <?php endif; ?>  
                                </td>  
                            </tr>  
                            <?php endforeach; ?>  
                        </tbody>  
                    </table>  
                </div>  
            </div>  

            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE REPASSE ===================== -->  
<div class="modal fade modal-modern" id="repasseModal" tabindex="-1" role="dialog" aria-labelledby="repasseModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header modern">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-exchange"></i></span>  
                    <h5 class="modal-title m-0" id="repasseModalLabel">Repasse Credor</h5>  
                </div>  
                <button type="button" class="btn-close modern" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  
            <div class="modal-body">  
                <div class="form-group">  
                    <label for="forma_repasse">Forma de Repasse</label>  
                    <select class="form-control" id="forma_repasse">  
                        <option value="">Selecione</option>  
                        <option value="Espécie">Espécie</option>  
                        <option value="PIX">PIX</option>  
                        <option value="Transferência Bancária">Transferência Bancária</option>  
                        <option value="Boleto">Boleto</option>  
                    </select>  
                </div>  
                <div class="form-group">  
                    <label for="valor_repasse">Valor do Repasse</label>  
                    <input type="text" class="form-control" id="valor_repasse" placeholder="0,00">  
                </div>  
                <button type="button" class="btn btn-primary w-100" onclick="salvarRepasse()">  
                    <i class="fa fa-save"></i> Salvar Repasse  
                </button>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE LIQUIDAÇÃO ===================== -->  
<div class="modal fade modal-modern" id="liquidacaoModal" tabindex="-1" role="dialog" aria-labelledby="liquidacaoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header modern">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-check-circle"></i></span>  
                    <h5 class="modal-title m-0" id="liquidacaoModalLabel">Liquidar Ato</h5>  
                </div>  
                <button type="button" class="btn-close modern" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  
            <div class="modal-body">  
                <div class="form-group">  
                    <label for="quantidade_liquidar">Quantidade a Liquidar</label>  
                    <input type="number" class="form-control" id="quantidade_liquidar" min="1">  
                </div>  
                <button type="button" class="btn btn-primary w-100" onclick="confirmarLiquidacao()">  
                    <i class="fa fa-check"></i> Confirmar Liquidação  
                </button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE DEVOLUÇÃO ===================== -->  
<div class="modal fade modal-modern" id="devolucaoModal" tabindex="-1" role="dialog" aria-labelledby="devolucaoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header modern">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-undo"></i></span>  
                    <h5 class="modal-title m-0" id="devolucaoModalLabel">Devolver Valores</h5>  
                </div>  
                <button type="button" class="btn-close modern" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  
            <div class="modal-body">  
                <div class="form-group">  
                    <label for="forma_devolucao">Forma de Devolução</label>  
                    <select class="form-control" id="forma_devolucao">  
                        <option value="">Selecione</option>  
                        <option value="Espécie">Espécie</option>  
                        <option value="PIX">PIX</option>  
                        <option value="Transferência Bancária">Transferência Bancária</option>  
                    </select>  
                </div>  
                <div class="form-group">  
                    <label for="valor_devolucao">Valor da Devolução</label>  
                    <input type="text" class="form-control" id="valor_devolucao" placeholder="0,00">  
                </div>  
                <button type="button" class="btn btn-primary w-100" onclick="salvarDevolucao()">  
                    <i class="fa fa-save"></i> Salvar Devolução  
                </button>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE MENSAGEM ===================== -->  
<div class="modal fade modal-modern" id="mensagemModal" tabindex="-1" role="dialog" aria-labelledby="mensagemModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header error" style="background: var(--gradient-error);">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-exclamation-triangle"></i></span>  
                    <h5 class="modal-title m-0 text-white" id="mensagemModalLabel">Erro</h5>  
                </div>  
                <button type="button" class="btn-close modern text-white" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  
            <div class="modal-body">  
                <p id="mensagemTexto"></p>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE TAREFA ===================== -->  
<div class="modal fade modal-modern" id="tarefaModal" tabindex="-1" role="dialog" aria-labelledby="tarefaModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header modern">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-clock-o"></i></span>  
                    <h5 class="modal-title m-0" id="tarefaModalLabel">Criar Tarefa</h5>  
                </div>  
                <button type="button" class="btn-close modern" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  
            <div class="modal-body">  
                <form id="taskForm" method="POST" action="save_task.php" enctype="multipart/form-data">  
                    <div class="form-row">    
                        <div class="form-group col-md-8">  
                            <label for="title">Título da Tarefa</label>  
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo $ordem_servico['descricao_os'] . ' - OS nº. ' . $ordem_servico['id'] . ' - ' . $ordem_servico['cliente']; ?>" required>  
                        </div>  

                        <div class="form-group col-md-4">  
                            <label for="category">Categoria</label>  
                            <select class="form-control" id="category" name="category" required>  
                                <option value="">Selecione</option>  
                                <?php  
                                $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>  
                        </div>  

                        <div class="form-group col-md-4">  
                            <label for="deadline">Data Limite para Conclusão</label>  
                            <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>  
                        </div>  

                        <div class="form-group col-md-4">  
                            <label for="employee">Funcionário Responsável</label>  
                            <select class="form-control" id="employee" name="employee" required>  
                                <option value="">Selecione</option>  
                                <?php  
                                $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                $loggedInUser = $_SESSION['username'];  

                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        $selected = ($row['nome_completo'] == $loggedInUser) ? 'selected' : '';  
                                        echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>  
                        </div>  

                        <div class="form-group col-md-4">  
                            <label for="origin">Origem</label>  
                            <select class="form-control" id="origin" name="origin" required>  
                                <option value="">Selecione</option>  
                                <?php  
                                $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>  
                        </div>  
                    </div>  

                    <div class="form-group">  
                        <label for="description">Descrição</label>  
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo $ordem_servico['observacoes']; ?></textarea>  
                    </div>  
                    <div class="form-group">  
                        <label for="attachments">Anexos</label>  
                        <input type="file" class="form-control-file" id="attachments" name="attachments[]" multiple>  
                    </div>  
                    <input type="hidden" id="createdBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">  
                    <input type="hidden" id="createdAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">  
                    <button type="submit" class="btn btn-primary w-100">  
                        <i class="fa fa-plus"></i> Criar Tarefa  
                    </button>  
                </form>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE ANEXOS ===================== -->  
<div class="modal fade modal-modern" id="anexoModal" tabindex="-1" role="dialog" aria-labelledby="anexoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header modern">  
                <div class="d-flex align-items-center">  
                    <span class="md-title-icon mr-2"><i class="fa fa-paperclip"></i></span>  
                    <h5 class="modal-title m-0" id="anexoModalLabel">Anexos</h5>  
                </div>  
                <button type="button" class="btn-close modern" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  

            <div class="modal-body">  
                <form id="formAnexos" enctype="multipart/form-data">  
                    <div class="upload-card mb-3">  
                        <i class="fa fa-cloud-upload" aria-hidden="true"></i>  
                        <div>  
                            <strong>Enviar anexos</strong><br>  
                            <small class="text-muted">Selecione um ou mais arquivos para anexar à OS.</small>  
                        </div>  
                    </div>  

                    <div class="custom-file mb-3">  
                        <input type="file" class="custom-file-input" id="novo_anexo" name="novo_anexo[]" multiple>  
                        <label class="custom-file-label" for="novo_anexo">Clique para escolher os arquivos</label>  
                    </div>  

                    <button type="button" class="btn btn-success w-100" onclick="salvarAnexo()">  
                        <i class="fa fa-paperclip" aria-hidden="true"></i> Anexar Arquivos  
                    </button>  
                </form>  

                <hr class="my-4">  

                <h6 class="section-title">Anexos adicionados</h6>  
                <div class="table-responsive">  
                    <table id="anexosTable" class="table table-striped table-bordered table-modern">  
                        <thead>  
                            <tr>  
                                <th style="width:85%">Anexo</th>  
                                <th style="width:15%">Ações</th>  
                            </tr>  
                        </thead>  
                        <tbody>  
                            <!-- Preenchido via JavaScript -->  
                        </tbody>  
                    </table>  
                </div>  
            </div>  

            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>

    document.addEventListener('DOMContentLoaded', function() {
        var deadlineInput = document.getElementById('deadline');
        var now = new Date();
        var year = now.getFullYear();
        var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
        var day = ('0' + now.getDate()).slice(-2);
        var hours = ('0' + now.getHours()).slice(-2);
        var minutes = ('0' + now.getMinutes()).slice(-2);

        // Formato YYYY-MM-DDTHH:MM
        var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        deadlineInput.min = minDateTime;
        
        // Desabilita botão quando não há protocolo detectado
        // const btn = document.getElementById('btnAtualizarPedido');
        // if (btn && !PEDIDO_PROTOCOLO) {
        //     btn.disabled = true;
        //     btn.title = 'Sem protocolo nas observações da O.S.';
        // }
    });

var pagamentos = <?php echo json_encode($pagamentos); ?>;
var liquidacaoItemId = null;
var quantidadeTotal = 0;
var quantidadeLiquidadaAtual = 0;

// ===== Constantes vindas do PHP para cálculos em tempo real =====
const OS_STATUS_SERVER   = <?php echo json_encode($ordem_servico['status']); ?>; // "Ativo" | "Cancelado"
const TOTAL_OS           = parseFloat('<?php echo $ordem_servico['total_os']; ?>');
const TOTAL_DEVOLUCOES   = parseFloat('<?php echo $total_devolucoes; ?>');
const TOTAL_REPASSES     = parseFloat('<?php echo $total_repasses; ?>');
const ISENTO_SERVER = <?php echo $isIsento ? 'true' : 'false'; ?>;

// ===== Utilidades de formatação =====
function formatCurrencyBRL(n) {
    if (isNaN(n)) n = 0;
    return 'R$ ' + n.toFixed(2).replace('.', ',');
}
function sumPagamentos() {
    return pagamentos.reduce((acc, p) => acc + parseFloat(p.total_pagamento || 0), 0);
}

function temAlgumPagamento() {
    return Array.isArray(pagamentos) && pagamentos.length > 0;
}

// ===== Atualiza os campos do topo e o status da O.S. sem recarregar =====
function refreshOsHeaderAndStats() {
    const totalPagos = sumPagamentos();
    const saldo = totalPagos - TOTAL_DEVOLUCOES - TOTAL_OS - TOTAL_REPASSES;

    // Atualiza inputs do formulário principal
    const depositoEl = document.getElementById('deposito_previo');
    if (depositoEl) depositoEl.value = formatCurrencyBRL(totalPagos);

    const saldoEl = document.getElementById('saldo');
    if (saldoEl)   saldoEl.value = formatCurrencyBRL(saldo);

    // Atualiza o badge do status (se não estiver cancelado)
    const badge = document.getElementById('osStatusBadge');
    if (badge) {
        const osStatusAtual = badge.getAttribute('data-os-status') || OS_STATUS_SERVER;
        const isentoAttr    = badge.getAttribute('data-isento') === '1';
        const isentoFlag    = isentoAttr || ISENTO_SERVER;

        if (osStatusAtual === 'Cancelado') {
            badge.className = 'situacao-cancelado';
            badge.textContent = 'Cancelada';
        } else {
            // prioridade: Isento > Pago > Ativa
            if (isentoFlag) {
                badge.className = 'situacao-isento';
                badge.textContent = 'Isento de Pagamento';
            } else if (sumPagamentos() > 0) {
                badge.className = 'situacao-pago';
                badge.textContent = 'Pago (Depósito Prévio)';
            } else {
                badge.className = 'situacao-ativo';
                badge.textContent = 'Ativa (Pendente de Pagamento)';
            }
        }
    }

    // (Opcional) alterna visibilidade dos botões de recibo
    const receiptGroup = document.getElementById('receiptButtons');
    if (receiptGroup) {
        receiptGroup.style.display = (totalPagos > 0) ? '' : 'none';
    }
}
    // ===== Variáveis do Pedido (se houver protocolo nas observações) =====
    const PEDIDO_ID             = <?php echo json_encode($pedido_id); ?>;
    const PEDIDO_PROTOCOLO      = <?php echo json_encode($pedido_protocolo); ?>;
    const CSRF_PEDIDOS          = <?php echo json_encode($_SESSION['csrf_pedidos']); ?>;
    const PEDIDO_STATUS_ATUAL   = <?php echo json_encode($pedido_status_atual ?? null); ?>; // <-- NOVO

    // ===== Sinalizadores globais (defensivos) =====
    if (typeof window.TODOS_ITENS_LIQUIDADOS === 'undefined') {
        window.TODOS_ITENS_LIQUIDADOS = <?php echo $todos_itens_liquidados ? 'true' : 'false'; ?>;
    }
    if (typeof window.ALGUM_ITEM_LIQUIDADO === 'undefined') {
        window.ALGUM_ITEM_LIQUIDADO = <?php echo $algum_item_liquidado ? 'true' : 'false'; ?>;
    }

    // ===== Fallback para sugerirStatusPedido() =====
    if (typeof window.sugerirStatusPedido !== 'function') {
        window.sugerirStatusPedido = function sugerirStatusPedido() {
            if (window.TODOS_ITENS_LIQUIDADOS) return 'emitida';
            if (window.ALGUM_ITEM_LIQUIDADO)   return 'em_andamento';
            return 'pendente';
        };
    }


    // Sinalizadores de liquidação (vindos do PHP)
    // Dispara uma notificação para atualizar o pedido de certidão quando tudo estiver liquidado (com fallbacks)
    function tentarNotificarAtualizacaoPedido() {
        // Valores base (com fallback do PHP)
        const PED_ID   = (typeof PEDIDO_ID !== 'undefined' && PEDIDO_ID) 
            ? PEDIDO_ID 
            : <?php echo json_encode($pedido_id); ?>;

        const PED_PROTO = (typeof PEDIDO_PROTOCOLO !== 'undefined' && PEDIDO_PROTOCOLO) 
            ? PEDIDO_PROTOCOLO 
            : <?php echo json_encode($pedido_protocolo); ?>;

        const ALL_DONE = (typeof TODOS_ITENS_LIQUIDADOS !== 'undefined') 
            ? TODOS_ITENS_LIQUIDADOS 
            : <?php echo $todos_itens_liquidados ? 'true' : 'false'; ?>;

        const SOME_DONE = (typeof ALGUM_ITEM_LIQUIDADO !== 'undefined') 
            ? ALGUM_ITEM_LIQUIDADO 
            : <?php echo $algum_item_liquidado ? 'true' : 'false'; ?>;

        // Só prossegue se existe pedido identificado e todos os atos estão liquidados
        if (!PED_ID || !PED_PROTO || !ALL_DONE) return;

        // Se já estiver emitida/cancelada no servidor, nem mostra
        const serverStatus = (typeof PEDIDO_STATUS_ATUAL !== 'undefined' && PEDIDO_STATUS_ATUAL) 
            ? ('' + PEDIDO_STATUS_ATUAL).toLowerCase()
            : null;

        if (serverStatus === 'emitida' || serverStatus === 'cancelada') return;

        // Status sugerido local (fallback se não houver função externa)
        const statusSugerido = (typeof sugerirStatusPedido === 'function') 
            ? sugerirStatusPedido() 
            : (ALL_DONE ? 'emitida' : (SOME_DONE ? 'em_andamento' : 'pendente'));

        // Só avisa quando a sugestão for "emitida"
        if (statusSugerido !== 'emitida') return;

        // Mostra SEM usar sessionStorage (aparece a cada entrada na página)
        Swal.fire({
            icon: 'info',
            title: 'Pedido de certidão pode ser atualizado',
            html: `Foi detectado que todos os atos desta O.S. foram liquidados.<br><br>
                Deseja atualizar o pedido de certidão (protocolo <b>${PED_PROTO}</b>) para o status <b>${statusSugerido}</b>?`,
            showCancelButton: true,
            confirmButtonText: 'Sim, atualizar',
            cancelButtonText: 'Agora não'
        }).then(r => {
            if (r.isConfirmed && typeof atualizarStatusPedido === 'function') {
                atualizarStatusPedido();
            }
        });
    }

    // Chama o endpoint que atualiza status do pedido por protocolo (sem exigir anexo)
    function atualizarStatusPedido() {
        if (!PEDIDO_PROTOCOLO) {
            Swal.fire({ icon: 'error', title: 'Sem protocolo', text: 'Não há protocolo nas observações desta O.S.' });
            return;
        }

        // Calcula o status de forma resiliente
        const ALL_DONE = (typeof window.TODOS_ITENS_LIQUIDADOS !== 'undefined')
            ? window.TODOS_ITENS_LIQUIDADOS
            : <?php echo $todos_itens_liquidados ? 'true' : 'false'; ?>;

        const SOME_DONE = (typeof window.ALGUM_ITEM_LIQUIDADO !== 'undefined')
            ? window.ALGUM_ITEM_LIQUIDADO
            : <?php echo $algum_item_liquidado ? 'true' : 'false'; ?>;

        const status = (typeof window.sugerirStatusPedido === 'function')
            ? window.sugerirStatusPedido()
            : (ALL_DONE ? 'emitida' : (SOME_DONE ? 'em_andamento' : 'pendente'));

        if (status === 'pendente') {
            Swal.fire({ icon: 'info', title: 'Sem liquidação', text: 'A O.S. ainda não foi liquidada. Status sugerido: pendente.' });
            return;
        }

        // Confirmação
        Swal.fire({
            icon: 'question',
            title: 'Atualizar status do pedido?',
            html: `Protocolo <b>${PEDIDO_PROTOCOLO}</b><br> Novo status: <b>${status}</b>`,
            showCancelButton: true,
            confirmButtonText: 'Atualizar',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (!r.isConfirmed) return;

            $('#btnAtualizarPedido').prop('disabled', true);

            $.ajax({
                url: '../pedidos_certidao/alterar_status_auto.php',
                method: 'POST',
                data: {
                    csrf: CSRF_PEDIDOS,
                    protocolo: PEDIDO_PROTOCOLO,
                    novo_status: status,
                    observacao: `Status atualizado automaticamente pela O.S. nº <?php echo (int)$os_id; ?>`
                },
                success: function(resp) {
                    try {
                        if (typeof resp === 'string') resp = JSON.parse(resp);
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Erro', text: 'Resposta inválida do servidor.' });
                        return;
                    }

                    if (resp && resp.success) {
                        Swal.fire({ icon: 'success', title: 'Status atualizado!', text: 'O pedido de certidão foi atualizado com sucesso.' })
                        .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Falha ao atualizar', text: (resp && resp.error) ? resp.error : 'Erro desconhecido.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível atualizar o status.' });
                },
                complete: function() {
                    $('#btnAtualizarPedido').prop('disabled', false);
                }
            });
        });
    }



$(document).ready(function() {
    $('#valor_pagamento').mask('#.##0,00', { reverse: true });
    $('#valor_devolucao').mask('#.##0,00', { reverse: true });
    $('#valor_repasse').mask('#.##0,00', { reverse: true });

    atualizarTabelaPagamentos();
    atualizarSaldo();
    refreshOsHeaderAndStats();

    // Se há protocolo e a O.S. está 100% liquidada, sugere atualizar o pedido
    try { 
        tentarNotificarAtualizacaoPedido(); 
    } catch (e) { 
        console.warn('Aviso de atualização do pedido não pôde ser exibido:', e); 
    }
});


    // Inicializar DataTable
    $('#tabelaItensOS').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "order": [[0, 'asc']], // Ordena pela segunda coluna de forma ascendente
    });

    // REMOVIDO: $('#tabelaPagamentoOS').DataTable({ ... });

    $('#tabelaIPagamentoOS').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "order": [], // Sem ordenação inicial
    });

    // ===== Logs de Liquidação
    $('#tabelaLogsLiquidacao').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json",
            "emptyTable": "Nenhum ato liquidado até o momento."
        },
        "pageLength": 50,
        "order": [[9, 'desc']], // ordena pela coluna Data (índice 9) desc
        "columnDefs": [
            { "orderable": false, "targets": [0] } // não ordenar pela coluna #
        ]
    });


    function imprimirOS() {
        // Gerar um timestamp para evitar cache
        const timestamp = new Date().getTime();
        
        // Fazer a requisição para o arquivo JSON com o timestamp
        fetch(`../style/configuracao.json?nocache=${timestamp}`)
            .then(response => response.json())
            .then(data => {
                const osId = '<?php echo $os_id; ?>';
                let url = '';
                
                if (data.timbrado === 'S') {
                    url = `imprimir_os.php?id=${osId}`;
                } else {
                    url = `imprimir-os.php?id=${osId}`;
                }
                
                // Abrir o link correspondente em uma nova aba
                window.open(url, '_blank');
            })
            .catch(error => {
                console.error('Erro ao carregar o arquivo JSON:', error);
            });
    }


    function imprimirRecibo() {
        if (<?php echo (float)$total_pagamentos; ?> <= 0) return; 
        window.open('recibo.php?id=<?php echo $os_id; ?>', '_blank');
    }

    function imprimirReciboA4() {
        if (<?php echo (float)$total_pagamentos; ?> <= 0) return; 
        window.open('recibo_a4.php?id=<?php echo $os_id; ?>', '_blank');
    }

    function editarOS() {
        window.location.href = 'editar_os.php?id=<?php echo $os_id; ?>';
    }

    // Função para adicionar pagamento
    function adicionarPagamento() {
        const addBtn = $('#btnAdicionarPagamento');
        addBtn.prop('disabled', true);

        var formaPagamento = $('#forma_pagamento').val();
        var valorPagamento = parseFloat($('#valor_pagamento').val().replace('.', '').replace(',', '.'));

        if (formaPagamento === "") {
            Swal.fire({ icon: 'error', title: 'Erro!', text: 'Por favor, selecione uma forma de pagamento.' })
                .then(() => addBtn.prop('disabled', false));
            return;
        }

        if (isNaN(valorPagamento) || valorPagamento <= 0) {
            Swal.fire({ icon: 'error', title: 'Erro!', text: 'Insira um valor válido para o pagamento.' })
                .then(() => addBtn.prop('disabled', false));
            return;
        }

        if (formaPagamento === 'Espécie') {
            const centavos = Math.round((valorPagamento * 100) % 100);
            if (centavos % 5 !== 0) {
                Swal.fire({ icon: 'error', title: 'Valor inválido', text: 'Em espécie, os centavos devem terminar em 0 ou 5.' })
                    .then(() => addBtn.prop('disabled', false));
                return;
            }
        }

        var existeDuplicado = pagamentos.some(function(p) {
            return p.forma_de_pagamento === formaPagamento && parseFloat(p.total_pagamento) === valorPagamento;
        });

        if (existeDuplicado) {
            Swal.fire({
                title: 'Pagamento Duplicado',
                text: 'Já existe um pagamento com essa forma e valor. Deseja adicionar mesmo assim?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, adicionar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    efetuarPagamento(formaPagamento, valorPagamento);
                } else {
                    addBtn.prop('disabled', false);
                }
            });
        } else {
            efetuarPagamento(formaPagamento, valorPagamento);
        }
    }

    function efetuarPagamento(formaPagamento, valorPagamento) {
        $('#btnAdicionarPagamento').prop('disabled', true);

        $.ajax({
            url: 'salvar_pagamento.php',
            type: 'POST',
            data: {
                os_id: <?php echo $os_id; ?>,
                cliente: '<?php echo $ordem_servico['cliente']; ?>',
                total_os: <?php echo $ordem_servico['total_os']; ?>,
                funcionario: '<?php echo $_SESSION['username']; ?>',
                forma_pagamento: formaPagamento,
                valor_pagamento: valorPagamento
            },
            success: function(response) {
                response = JSON.parse(response);
                if (response.success) {
                    pagamentos.push({
                        id: response.pagamento_id,                          
                        forma_de_pagamento: formaPagamento,
                        total_pagamento: valorPagamento,
                        data_pagamento:  response.data_pagamento || new Date().toISOString().slice(0,19).replace('T',' '),
                        funcionario:     '<?php echo $_SESSION['username']; ?>'
                    });

                    atualizarTabelaPagamentos();
                    atualizarSaldo();
                    refreshOsHeaderAndStats(); // <-- atualiza status + totais do topo

                    $('#valor_pagamento').val('');
                    $('#forma_pagamento').val('');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Pagamento adicionado com sucesso!',
                        confirmButtonText: 'OK'
                    });


                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao adicionar pagamento.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao adicionar pagamento.',
                    confirmButtonText: 'OK'
                });
            },
            complete: function() {
                $('#btnAdicionarPagamento').prop('disabled', false);
            }
        });
    }


    function atualizarSaldo() {
        var totalPagamentos = pagamentos.reduce((acc, pagamento) => acc + parseFloat(pagamento.total_pagamento), 0);
        var totalDevolucoes = <?php echo $total_devolucoes; ?>;
        var totalRepasses = <?php echo $total_repasses; ?>;
        var totalOS = <?php echo $ordem_servico['total_os']; ?>;

        var saldo = totalPagamentos - totalDevolucoes - totalOS - totalRepasses;

        $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));
    }


    // Função para atualizar a tabela de pagamentos
    function atualizarTabelaPagamentos() {
        // se a tabela já está “possuída” pelo DataTables, use a API; caso contrário, preencha o tbody
        var dt = $.fn.DataTable.isDataTable('#tabelaIPagamentoOS') ? $('#tabelaIPagamentoOS').DataTable() : null;
        var $tbody = $('#pagamentosTable');
        var total = 0;

        function formatarDataBr(datetimeSql) {
            const d = new Date((datetimeSql || '').replace(' ', 'T'));
            return isNaN(d.getTime()) ? '' : d.toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
        }

        function ymdInSaoPaulo(d) {
            const parts = d.toLocaleDateString('pt-BR', { timeZone: 'America/Sao_Paulo' }).split('/'); // dd/mm/aaaa
            return `${parts[2]}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}`; // aaaa-mm-dd
        }

        if (dt) dt.clear(); else $tbody.empty();

        pagamentos.forEach(function(pagamento) {
            const valor = parseFloat(pagamento.total_pagamento || 0);
            total += valor;

            const todayYmd  = ymdInSaoPaulo(new Date());
            const linhaDate = new Date((pagamento.data_pagamento || '').replace(' ', 'T'));
            const linhaYmd  = isNaN(linhaDate.getTime()) ? '' : ymdInSaoPaulo(linhaDate);
            const canDelete = !<?php echo $has_liquidated ? 'true':'false'; ?> && (linhaYmd === todayYmd);

            var cols = [
                pagamento.forma_de_pagamento || '',
                'R$ ' + valor.toFixed(2).replace('.', ','),
                formatarDataBr(pagamento.data_pagamento || ''),
                pagamento.funcionario || '',
                canDelete ? `<button type="button" title="Remover" class="btn btn-delete btn-sm"
                                onclick="confirmarRemocaoPagamento(${pagamento.id})">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>` : ''
            ];

            if (dt) {
                dt.row.add(cols);
            } else {
                $tbody.append(`<tr>
                    <td>${cols[0]}</td>
                    <td>${cols[1]}</td>
                    <td>${cols[2]}</td>
                    <td>${cols[3]}</td>
                    <td>${cols[4]}</td>
                </tr>`);
            }
        });

        if (dt) dt.draw();

        $('#total_pagamento_modal').val('R$ ' + total.toFixed(2).replace('.', ','));
    }



    // Função para remover pagamento
    function confirmarRemocaoPagamento(pagamentoId) {
        if (<?php echo $has_liquidated ? 'true' : 'false'; ?>) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Não é possível remover pagamentos após a liquidação de atos.',
                confirmButtonText: 'OK'
            });
            return;
        }

        Swal.fire({
            title: 'Tem certeza?',
            text: 'Deseja realmente remover este pagamento?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, remover!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'remover_pagamento.php',
                    type: 'POST',
                    data: {
                        pagamento_id: pagamentoId
                    },
                    success: function(response) {
                        response = JSON.parse(response);
                        if (response.success) {
                            pagamentos = pagamentos.filter(pagamento => pagamento.id !== pagamentoId);
                            atualizarTabelaPagamentos();
                            atualizarSaldo();
                            refreshOsHeaderAndStats(); // <-- mantém o topo coerente

                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Pagamento removido com sucesso!',
                                confirmButtonText: 'OK'
                            });


                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro ao remover pagamento.',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao remover pagamento.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    }

    function liquidarAto(itemId, quantidade, quantidadeLiquidada, totalAto) {
        const valorPago = parseFloat('<?php echo $valor_pago_liquido; ?>');
        const valorLiquidado = parseFloat('<?php echo $total_liquidado; ?>');
        const saldoDisponivel = valorPago - valorLiquidado;

        const quantidadeRestante = quantidade - quantidadeLiquidada;
        const valorAtoALiquidar = (totalAto / quantidade) * quantidadeRestante;

        if (!temAlgumPagamento()) {
            Swal.fire({
                icon: 'warning',
                title: 'Pagamento ausente',
                text: 'Não há nenhum pagamento registrado nesta OS. Deseja adicionar?',
                showCancelButton: true,
                confirmButtonText: 'Adicionar Pagamento',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#pagamentoModal').modal('show');
                }
            });
        } else if (saldoDisponivel < valorAtoALiquidar - 0.01) {
            Swal.fire({
                icon: 'warning',
                title: 'Saldo insuficiente',
                html: `O valor disponível em depósito (<b>R$ ${saldoDisponivel.toFixed(2).replace('.', ',')}</b>) não é suficiente para liquidar este ato que custa <b>R$ ${valorAtoALiquidar.toFixed(2).replace('.', ',')}</b>.<br><br>O que deseja fazer?`,
                showDenyButton: false,        // ocultar "Continuar assim mesmo"
                showCancelButton: true,
                confirmButtonText: 'Adicionar Pagamento',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#pagamentoModal').modal('show');
                } else if (result.isDenied) {
                    abrirLiquidacaoModal(itemId, quantidade, quantidadeLiquidada);
                }
            });
        } else {
            abrirLiquidacaoModal(itemId, quantidade, quantidadeLiquidada);
        }
    }

    function abrirLiquidacaoModal(itemId, quantidade, quantidadeLiquidada) {
        liquidacaoItemId = itemId;
        quantidadeTotal = quantidade;
        quantidadeLiquidadaAtual = quantidadeLiquidada; // usar o global renomeado

        var quantidadeRestante = quantidadeTotal - quantidadeLiquidadaAtual;
        $('#quantidade_liquidar').val(quantidadeRestante);

        $('#liquidacaoModal').modal('show');
    }

    // Função para confirmar liquidação
    function confirmarLiquidacao() {
        var quantidadeALiquidar = parseInt($('#quantidade_liquidar').val());

        if (quantidadeALiquidar <= 0 || (quantidadeLiquidadaAtual + quantidadeALiquidar) > quantidadeTotal) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Quantidade inválida para liquidação.',
                confirmButtonText: 'OK'
            });
            return;
        }

        $.ajax({
            url: 'liquidar_ato.php',
            type: 'POST',
            data: {
                item_id: liquidacaoItemId,
                quantidade_liquidar: quantidadeALiquidar
            },
            success: function(response) {
                console.log(response); // Adiciona log para verificar a resposta
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Ato liquidado com sucesso!',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            $('#liquidacaoModal').modal('hide');
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: response.error || 'Erro ao liquidar ato.',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (e) {
                    console.error('Erro ao analisar resposta JSON:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao analisar resposta do servidor.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao liquidar ato: ' + error,
                    confirmButtonText: 'OK'
                });
            }
        });
    }


    // Função para abrir modal de devolução
    function abrirDevolucaoModal() {
        $('#devolucaoModal').modal('show');
    }

    // Função para salvar devolução
    function salvarDevolucao() {
        var formaDevolucao = $('#forma_devolucao').val();
        var valorDevolucao = parseFloat($('#valor_devolucao').val().replace('.', '').replace(',', '.'));
        var valorPago = parseFloat('<?php echo $valor_pago_liquido; ?>');
        var valorMaximoDevolucao = valorPago - parseFloat('<?php echo $total_liquidado; ?>');

        if (formaDevolucao === "") {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, selecione uma forma de devolução.',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > valorMaximoDevolucao + 0.01) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, insira um valor válido para a devolução que não seja maior que o saldo disponível.',
                confirmButtonText: 'OK'
            });
            return;
        }

        var osId = <?php echo $os_id; ?>;
        var cliente = '<?php echo $ordem_servico['cliente']; ?>';
        var totalOs = '<?php echo $ordem_servico['total_os']; ?>';
        var funcionario = '<?php echo $_SESSION['username']; ?>';

        $.ajax({
            url: 'salvar_devolucao.php',
            type: 'POST',
            data: {
                os_id: osId,
                cliente: cliente,
                total_os: totalOs,
                total_devolucao: valorDevolucao,
                forma_devolucao: formaDevolucao,
                funcionario: funcionario
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: 'Devolução salva com sucesso!',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#devolucaoModal').modal('hide');
                    window.location.reload();
                });
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao salvar devolução: ' + error,
                    confirmButtonText: 'OK'
                });
            }
        });
    }


    // Função para abrir modal de repasse
    function abrirRepasseModal() {
        $('#repasseModal').modal('show');
    }

    // Função para salvar repasse
    function salvarRepasse() {
        var formaRepasse = $('#forma_repasse').val();
        var valorRepasse = parseFloat($('#valor_repasse').val().replace('.', '').replace(',', '.'));
        var saldoAtual = parseFloat('<?php echo $saldo; ?>');

        if (formaRepasse === "") {
            exibirMensagem('Por favor, selecione uma forma de repasse.', 'error');
            return;
        }

        if (isNaN(valorRepasse) || valorRepasse <= 0 || valorRepasse > saldoAtual + 0.01) {
            exibirMensagem('Por favor, insira um valor válido para o repasse que não seja maior que o saldo disponível.', 'error');
            return;
        }

        var osId = <?php echo $os_id; ?>;
        var cliente = '<?php echo $ordem_servico['cliente']; ?>';
        var totalOs = '<?php echo $ordem_servico['total_os']; ?>';
        var dataOs = '<?php echo $ordem_servico['data_criacao']; ?>';
        var funcionario = '<?php echo $_SESSION['username']; ?>';

        $.ajax({
            url: 'salvar_repasse.php',
            type: 'POST',
            data: {
                os_id: osId,
                cliente: cliente,
                total_os: totalOs,
                total_repasse: valorRepasse,
                forma_repasse: formaRepasse,
                data_os: dataOs,
                funcionario: funcionario
            },
            success: function(response) {
                console.log("Server response:", response); // Adicione esta linha para verificar a resposta do servidor
                try {
                    // Verifique se a resposta já é um objeto, se for, não tente analisá-la
                    if (typeof response === 'object') {
                        processarResposta(response, saldoAtual, valorRepasse);
                    } else {
                        response = JSON.parse(response);
                        processarResposta(response, saldoAtual, valorRepasse);
                    }
                } catch (e) {
                    console.error('Erro ao analisar resposta JSON:', e);
                    exibirMensagem('Erro ao processar a resposta do servidor: ' + e.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error); // Adicione esta linha para logar erros de AJAX
                exibirMensagem('Erro ao salvar repasse: ' + error, 'error');
            }
        });
    }

    function processarResposta(response, saldoAtual, valorRepasse) {
        if (response.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Repasse salvo com sucesso!',
                confirmButtonText: 'OK'
            }).then(() => {
                // Fechar o modal e recarregar a página
                $('#repasseModal').modal('hide');
                // Recalcular o saldo após o repasse
                var novoSaldo = saldoAtual - valorRepasse;
                $('#saldo').val('R$ ' + novoSaldo.toFixed(2).replace('.', ','));
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Erro ao salvar repasse: ' + response.error,
                confirmButtonText: 'OK'
            });
        }
    }

    // Função para exibir mensagem
    function exibirMensagem(mensagem, tipo) {
        var modalHeader = $('#mensagemModal .modal-header');
        var mensagemTexto = $('#mensagemTexto');

        if (tipo === 'success') {
            modalHeader.removeClass('error').addClass('success');
            $('#mensagemModalLabel').text('Sucesso');
        } else if (tipo === 'error') {
            modalHeader.removeClass('success').addClass('error');
            $('#mensagemModalLabel').text('Erro');
        }

        mensagemTexto.text(mensagem);
        $('#mensagemModal').modal('show');
    }

    // Ao fechar o modal de pagamento, recarrega a página
    $('#pagamentoModal').on('hidden.bs.modal', function () {
        window.location.reload();
    });

    function cancelarOS() {
        var totalPagamentos = parseFloat('<?php echo $total_pagamentos; ?>');
        var totalDevolucoes = parseFloat('<?php echo $total_devolucoes; ?>');

        // Se o total de pagamentos for maior que o total de devoluções, alertar e abrir o modal de devolução
        if (totalPagamentos > totalDevolucoes) {
            Swal.fire({
                icon: 'error',
                title: 'Atenção',
                text: 'Há pagamentos nesta OS que ainda não foram totalmente devolvidos. Você precisa devolver o saldo antes de cancelar a OS.'
            }).then(() => {
                abrirDevolucaoModal(); // Abrir o modal de devolução
            });
            return;
        }

        // Exibir confirmação com SweetAlert2
        Swal.fire({
            title: 'Tem certeza?',
            text: "Tem certeza de que deseja cancelar esta Ordem de Serviço? Esta ação não pode ser desfeita.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, cancelar',
            cancelButtonText: 'Não, manter'
        }).then((result) => {
            if (result.isConfirmed) {
                // Realizar o cancelamento da OS
                $.ajax({
                    url: 'cancelar_os.php',
                    type: 'POST',
                    data: {
                        os_id: <?php echo $os_id; ?>
                    },
                    success: function(response) {
                        try {
                            response = JSON.parse(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso',
                                    text: 'Ordem de Serviço cancelada com sucesso!'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: 'Erro ao cancelar a Ordem de Serviço.'
                                });
                            }
                        } catch (e) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao processar resposta do servidor.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao cancelar a Ordem de Serviço.'
                        });
                    }
                });
            }
        });
    }

    $(document).on('show.bs.modal', function () {
        // Desativa a rolagem do fundo
        $('body').css('overflow', 'hidden');
    });

    $(document).on('hidden.bs.modal', function () {
        // Restaura a rolagem do fundo apenas se não houver mais modais abertos
        if ($('.modal.show').length === 0) {
            $('body').css('overflow', 'auto');
        }
    });

    // Adicionar rolagem ao modal principal após fechar o secundário
    $('#devolucaoModal').on('hidden.bs.modal', function () {
        $('#pagamentoModal').css('overflow-y', 'auto');
    });


    // Carregar anexos quando o modal for aberto
    $('#anexoModal').on('show.bs.modal', function() {
        window.currentOsId = <?php echo $os_id; ?>; // Define o ID da OS atual
        atualizarTabelaAnexos();
    });

    function salvarAnexo() {
        var formData = new FormData($('#formAnexos')[0]);
        formData.append('os_id', window.currentOsId);
        formData.append('funcionario', '<?php echo $_SESSION['username']; ?>');

        $.ajax({
            url: 'salvar_anexo.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        $('#novo_anexo').val('');
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso',
                            text: 'Anexo salvo com sucesso!'
                        }).then(() => {
                            // Recarregar a página quando a mensagem de sucesso for fechada
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao salvar anexo.'
                        });
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao processar resposta do servidor.'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao salvar anexo.'
                });
            }
        });
    }

    function atualizarTabelaAnexos() {
        var anexosTableBody = $('#anexosTable tbody');
        anexosTableBody.empty();

        $.ajax({
            url: 'obter_anexos.php',
            type: 'POST',
            data: {
                os_id: window.currentOsId
            },
            success: function(response) {
                try {
                    response = JSON.parse(response);

                    response.anexos.forEach(function(anexo, index) {
                        var caminhoCompleto = 'anexos/' + window.currentOsId + '/' + anexo.caminho_anexo;
                        anexosTableBody.append(`
                            <tr>
                                <td>${anexo.caminho_anexo}</td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" onclick="visualizarAnexo('${caminhoCompleto}')">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });

                    // Inicializar ou re-inicializar o DataTable
                    if ($.fn.DataTable.isDataTable('#anexosTable')) {
                        $('#anexosTable').DataTable().clear().destroy();
                    }

                    $('#anexosTable').DataTable({
                        paging: true,
                        searching: true,
                        ordering: true,
                        info: true,
                        autoWidth: false, // Desabilitar a largura automática
                        responsive: true, // Torna a tabela responsiva
                        language: {
                            url: '../style/Portuguese-Brasil.json'
                        }
                    });
                } catch (e) {
                    exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                }
            },
            error: function() {
                exibirMensagem('Erro ao atualizar tabela de anexos.', 'error');
            }
        });
    }

        function visualizarAnexo(caminho) {
            window.open(caminho, '_blank');
        }

        function removerAnexo(anexoId) {
            Swal.fire({
                title: 'Confirmar Remoção',
                text: 'Deseja realmente remover este anexo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'remover_anexo.php',
                        type: 'POST',
                        data: {
                            anexo_id: anexoId
                        },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Sucesso',
                                        text: 'Anexo removido com sucesso!'
                                    }).then(() => {
                                        // Recarregar a página quando a mensagem de sucesso for fechada
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Erro',
                                        text: 'Erro ao remover anexo.'
                                    });
                                }
                            } catch (e) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: 'Erro ao processar resposta do servidor.'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao remover anexo.'
                            });
                        }
                    });
                }
            });
        }


        document.getElementById('novo_anexo').addEventListener('change', function() {
            var input = this;
            var label = input.nextElementSibling;
            var files = input.files;

            if (files.length === 1) {
                // Exibir o nome do arquivo selecionado
                label.textContent = files[0].name;
            } else if (files.length > 1) {
                // Exibir a quantidade de arquivos selecionados
                label.textContent = files.length + ' arquivos selecionados';
            } else {
                // Voltar ao texto padrão
                label.textContent = 'Selecione os arquivos para anexar';
            }
        });

        
        $('#anexoModal').on('hidden.bs.modal', function () {
            location.reload(); // Recarrega a página quando o modal for fechado
        });

        function liquidarTudo() {
            const valorPago = parseFloat('<?php echo $valor_pago_liquido; ?>');
            const valorLiquidado = parseFloat('<?php echo $total_liquidado; ?>');
            const saldoDisponivel = valorPago - valorLiquidado;
            const totalRestante = <?php echo $ordem_servico['total_os']; ?> - valorLiquidado;

            if (!temAlgumPagamento()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pagamento ausente',
                    text: 'Não há nenhum pagamento registrado nesta OS. Deseja adicionar?',
                    showCancelButton: true,
                    confirmButtonText: 'Adicionar Pagamento',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#pagamentoModal').modal('show');
                    }
                });
            } else if (saldoDisponivel < totalRestante - 0.01) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Saldo insuficiente',
                    html: `O valor disponível em depósito (<b>R$ ${saldoDisponivel.toFixed(2).replace('.', ',')}</b>) não é suficiente para liquidar todos os atos restantes que somam <b>R$ ${totalRestante.toFixed(2).replace('.', ',')}</b>.<br><br>O que deseja fazer?`,
                    showDenyButton: false, // ocultar "Continuar assim mesmo"
                    showCancelButton: true,
                    confirmButtonText: 'Adicionar Pagamento',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#pagamentoModal').modal('show');
                    } else if (result.isDenied) {
                        confirmarLiquidacaoTudo();
                    }
                });
            } else {
                confirmarLiquidacaoTudo();
            }
        }

        function confirmarLiquidacaoTudo() {
            Swal.fire({
                title: 'Confirmação',
                text: 'Deseja realmente liquidar todos os atos desta Ordem de Serviço?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, liquidar tudo',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'liquidar_os.php',
                        type: 'POST',
                        data: { os_id: <?php echo $os_id; ?> },
                        success: function(response) {
                            response = JSON.parse(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso',
                                    text: 'Todos os atos foram liquidados com sucesso!',
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: response.error || 'Erro ao liquidar atos.',
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao processar a solicitação.',
                            });
                        }
                    });
                }
            });
        }

        function isentarPagamento() {
            const btn = document.getElementById('btnIsentoPagamento');
            if (btn) btn.disabled = true;

            $.ajax({
                url: 'salvar_pagamento.php',
                type: 'POST',
                data: {
                    os_id: <?php echo $os_id; ?>,
                    cliente: '<?php echo $ordem_servico['cliente']; ?>',
                    total_os: <?php echo $ordem_servico['total_os']; ?>,
                    funcionario: '<?php echo $_SESSION['username']; ?>',
                    forma_pagamento: 'Isento de Pagamento',
                    valor_pagamento: 0
                },
                success: function(response) {
                    // Tenta converter para objeto caso venha como string
                    try { response = (typeof response === 'object') ? response : JSON.parse(response); } catch(e) { response = null; }

                    if (response && response.success) {
                        // Atualiza a lista em memória (mostra na tabela do modal imediatamente)
                        pagamentos.push({
                            id: response.pagamento_id || null,
                            forma_de_pagamento: 'Isento de Pagamento',
                            total_pagamento: 0,
                            data_pagamento:  response.data_pagamento || new Date().toISOString().slice(0,19).replace('T',' '),
                            funcionario:     '<?php echo $_SESSION['username']; ?>'
                        });

                        atualizarTabelaPagamentos();
                        atualizarSaldo();
                        refreshOsHeaderAndStats();

                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Marcado como "Isento de Pagamento".'
                        }).then(() => {
                            // Fechar modal e recarregar para re-renderizar botões de liquidação (PHP usa $qtde_pagamentos)
                            $('#pagamentoModal').modal('hide');
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: (response && response.error) ? response.error : 'Falha ao isentar.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({ icon:'error', title:'Erro', text:'Falha na solicitação.' });
                },
                complete: function() {
                    if (btn) btn.disabled = false;
                }
            });
        }

</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>