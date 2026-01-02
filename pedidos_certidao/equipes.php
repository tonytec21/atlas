<?php  
// pedidos_certidao/equipes.php  
// Página única: UI + endpoints AJAX para cadastro de equipes, membros e regras de distribuição.  
// Cria/garante automaticamente as tabelas necessárias (MySQL 8+).  

include(__DIR__ . '/../os/session_check.php');  
checkSession();  
include(__DIR__ . '/../os/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  
header_remove('X-Powered-By');  

/* ========================================================================  
   SCHEMA - MySQL 8+  
   ======================================================================== */  
function ensureSchemaDistribuicao(PDO $conn) {  
  $sqls = [];  

  // Equipes  
  $sqls[] = "CREATE TABLE IF NOT EXISTS equipes (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    nome VARCHAR(120) NOT NULL,  
    descricao VARCHAR(500) NULL,  
    ativa TINYINT(1) NOT NULL DEFAULT 1,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    UNIQUE KEY uq_equipe_nome (nome),  
    INDEX idx_ativa (ativa)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";  

  // Membros da equipe (ligação com funcionarios.id)  
  $sqls[] = "CREATE TABLE IF NOT EXISTS equipe_membros (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    equipe_id INT NOT NULL,  
    funcionario_id INT NOT NULL,  
    papel VARCHAR(60) NULL,  
    ordem INT NOT NULL DEFAULT 1,  
    ativo TINYINT(1) NOT NULL DEFAULT 1,  
    carga_maxima_diaria INT NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    CONSTRAINT fk_membro_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,  
    CONSTRAINT fk_membro_func FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,  
    UNIQUE KEY uq_equipe_func (equipe_id, funcionario_id),  
    INDEX idx_equipe_ativo (equipe_id, ativo),  
    INDEX idx_ordem (ordem)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";  

  // Regras de distribuição (match por Atribuição + Tipo)  
  // tipo='*' atua como curinga para qualquer tipo daquela atribuição.  
  $sqls[] = "CREATE TABLE IF NOT EXISTS equipe_regras (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    equipe_id INT NOT NULL,  
    atribuicao VARCHAR(50) NOT NULL,  
    tipo VARCHAR(80) NOT NULL,  
    prioridade INT NOT NULL DEFAULT 10,  
    ativa TINYINT(1) NOT NULL DEFAULT 1,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    CONSTRAINT fk_regra_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,  
    INDEX idx_match (atribuicao, tipo, ativa, prioridade),  
    INDEX idx_equipe (equipe_id)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";  

  // Tarefas geradas por pedidos (quem vai executar)  
  $sqls[] = "CREATE TABLE IF NOT EXISTS tarefas_pedido (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    pedido_id INT NOT NULL,  
    equipe_id INT NOT NULL,  
    funcionario_id INT NULL,  
    status ENUM('pendente','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'pendente',  
    observacao VARCHAR(500) NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    CONSTRAINT fk_tarefa_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE RESTRICT,  
    CONSTRAINT fk_tarefa_func FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE SET NULL,  
    INDEX idx_pedido (pedido_id),  
    INDEX idx_func_status (funcionario_id, status),  
    INDEX idx_equipe (equipe_id),  
    INDEX idx_status (status)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";  

  foreach ($sqls as $sql) { $conn->exec($sql); }  
}  

try {  
  $conn = getDatabaseConnection();  
  ensureSchemaDistribuicao($conn);  
} catch (Throwable $e) {  
  // Mostra erro somente em endpoints; na UI exibimos aviso simples  
}  

/* ========================================================================  
   ENDPOINTS AJAX (JSON)  
   ======================================================================== */  
$isAjax = (isset($_GET['action']) || isset($_POST['action']))  
          || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest');  

if ($isAjax) {  
  header('Content-Type: application/json; charset=utf-8');  

  function jsonOut($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }  
  try {  
    $action = $_REQUEST['action'] ?? '';  

    if ($action === 'list_funcionarios') {  
      $stmt = $conn->query("SELECT id, usuario, nome_completo, cargo, nivel_de_acesso, status, e_mail  
                            FROM funcionarios  
                            WHERE status IS NULL OR status IN ('ativo','ATIVO','1','true','TRUE','ok','OK')  
                            ORDER BY nome_completo");  
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);  
      jsonOut(['success'=>true,'data'=>$rows]);  
    }  

    if ($action === 'list_equipes') {  
      $stmt = $conn->query("SELECT e.*,  
           (SELECT COUNT(*) FROM equipe_membros m WHERE m.equipe_id = e.id AND m.ativo=1) AS membros_ativos,  
           (SELECT COUNT(*) FROM equipe_regras r WHERE r.equipe_id = e.id AND r.ativa=1) AS regras_ativas  
         FROM equipes e  
         ORDER BY e.ativa DESC, e.nome ASC");  
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);  
      jsonOut(['success'=>true,'data'=>$rows]);  
    }  

    if ($action === 'salvar_equipe') {  
      $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;  
      $nome = trim($_POST['nome'] ?? '');  
      $desc = trim($_POST['descricao'] ?? '');  
      $ativa= isset($_POST['ativa']) ? (int)($_POST['ativa'] ? 1 : 0) : 1;  
      if ($nome==='') jsonOut(['success'=>false,'error'=>'Informe o nome da equipe.']);  

      if ($id>0) {  
        $st = $conn->prepare("UPDATE equipes SET nome=?, descricao=?, ativa=? WHERE id=?");  
        $st->execute([$nome,$desc,$ativa,$id]);  
      } else {  
        $st = $conn->prepare("INSERT INTO equipes (nome, descricao, ativa) VALUES (?,?,?)");  
        $st->execute([$nome,$desc,$ativa]);  
        $id = (int)$conn->lastInsertId();  
      }  
      jsonOut(['success'=>true,'id'=>$id]);  
    }  

    if ($action === 'remover_equipe') {  
      $id = (int)($_POST['id'] ?? 0);  
      if ($id<=0) jsonOut(['success'=>false,'error'=>'ID inválido.']);  
      $conn->prepare("DELETE FROM equipes WHERE id=?")->execute([$id]);  
      jsonOut(['success'=>true]);  
    }  

    if ($action === 'listar_membros') {  
      $equipe_id = (int)($_GET['equipe_id'] ?? 0);  
      $st = $conn->prepare("SELECT m.*, f.nome_completo, f.usuario, f.cargo  
                            FROM equipe_membros m  
                            JOIN funcionarios f ON f.id = m.funcionario_id  
                            WHERE m.equipe_id = ?  
                            ORDER BY m.ativo DESC, m.ordem ASC, f.nome_completo ASC");  
      $st->execute([$equipe_id]);  
      jsonOut(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
    }  

    if ($action === 'salvar_membro') {  
      $equipe_id     = (int)($_POST['equipe_id'] ?? 0);  
      $funcionario_id= (int)($_POST['funcionario_id'] ?? 0);  
      $papel         = trim($_POST['papel'] ?? '');  
      $ordem         = (int)($_POST['ordem'] ?? 1);  
      $ativo         = isset($_POST['ativo']) ? (int)($_POST['ativo']?1:0) : 1;  
      $cargaMax      = isset($_POST['carga_maxima_diaria']) && $_POST['carga_maxima_diaria']!=='' ? (int)$_POST['carga_maxima_diaria'] : null;  

      if ($equipe_id<=0 || $funcionario_id<=0) jsonOut(['success'=>false,'error'=>'Informe equipe e funcionário.']);  

      // UPSERT simples (se já existir o par, atualiza)  
      $st = $conn->prepare("SELECT id FROM equipe_membros WHERE equipe_id=? AND funcionario_id=?");  
      $st->execute([$equipe_id, $funcionario_id]);  
      $row = $st->fetch(PDO::FETCH_ASSOC);  
      if ($row) {  
        $st2 = $conn->prepare("UPDATE equipe_membros SET papel=?, ordem=?, ativo=?, carga_maxima_diaria=? WHERE id=?");  
        $st2->execute([$papel, $ordem, $ativo, $cargaMax, $row['id']]);  
        $id = (int)$row['id'];  
      } else {  
        $st2 = $conn->prepare("INSERT INTO equipe_membros (equipe_id, funcionario_id, papel, ordem, ativo, carga_maxima_diaria)  
                               VALUES (?,?,?,?,?,?)");  
        $st2->execute([$equipe_id, $funcionario_id, $papel, $ordem, $ativo, $cargaMax]);  
        $id = (int)$conn->lastInsertId();  
      }  
      jsonOut(['success'=>true,'id'=>$id]);  
    }  

    if ($action === 'remover_membro') {  
      $id = (int)($_POST['id'] ?? 0);  
      if ($id<=0) jsonOut(['success'=>false,'error'=>'ID inválido.']);  
      $conn->prepare("DELETE FROM equipe_membros WHERE id=?")->execute([$id]);  
      jsonOut(['success'=>true]);  
    }  

    if ($action === 'listar_regras') {  
      $equipe_id = (int)($_GET['equipe_id'] ?? 0);  
      $st = $conn->prepare("SELECT * FROM equipe_regras WHERE equipe_id=? ORDER BY ativa DESC, prioridade ASC, atribuicao ASC, tipo ASC");  
      $st->execute([$equipe_id]);  
      jsonOut(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
    }  

    if ($action === 'salvar_regra') {  
      $id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;  
      $equipe_id = (int)($_POST['equipe_id'] ?? 0);  
      $atr       = trim($_POST['atribuicao'] ?? '');  
      $tipo      = trim($_POST['tipo'] ?? '');  
      $prior     = (int)($_POST['prioridade'] ?? 10);  
      $ativa     = isset($_POST['ativa']) ? (int)($_POST['ativa']?1:0) : 1;  

      if ($equipe_id<=0 || $atr==='' || $tipo==='') jsonOut(['success'=>false,'error'=>'Informe equipe, atribuição e tipo.']);  
      if ($id>0) {  
        $st = $conn->prepare("UPDATE equipe_regras SET atribuicao=?, tipo=?, prioridade=?, ativa=? WHERE id=?");  
        $st->execute([$atr,$tipo,$prior,$ativa,$id]);  
      } else {  
        $st = $conn->prepare("INSERT INTO equipe_regras (equipe_id, atribuicao, tipo, prioridade, ativa) VALUES (?,?,?,?,?)");  
        $st->execute([$equipe_id, $atr, $tipo, $prior, $ativa]);  
        $id = (int)$conn->lastInsertId();  
      }  
      jsonOut(['success'=>true,'id'=>$id]);  
    }  

    if ($action === 'remover_regra') {  
      $id = (int)($_POST['id'] ?? 0);  
      if ($id<=0) jsonOut(['success'=>false,'error'=>'ID inválido.']);  
      $conn->prepare("DELETE FROM equipe_regras WHERE id=?")->execute([$id]);  
      jsonOut(['success'=>true]);  
    }  

    jsonOut(['success'=>false,'error'=>'Ação inválida.']);  
  } catch (Throwable $e) {  
    jsonOut(['success'=>false,'error'=>'Falha: '.$e->getMessage()]);  
  }  
  exit;  
}  

?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
<meta charset="utf-8">  
<meta name="viewport" content="width=device-width, initial-scale=1">  
<title>Gerenciamento de Equipes e Distribuição</title>  

<!-- Fontes & Ícones -->  
<link rel="preconnect" href="https://fonts.googleapis.com">  
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>  
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">  
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">  

<link rel="stylesheet" href="../style/css/bootstrap.min.css">  
<link rel="icon" href="../style/img/favicon.png" type="image/png">  

<style>  
/* ===================== CSS VARIABLES ===================== */  
:root {  
  /* Typography */  
  --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;  
  
  /* Spacing Scale */  
  --space-xs: 4px;  
  --space-sm: 8px;  
  --space-md: 16px;  
  --space-lg: 24px;  
  --space-xl: 32px;  
  --space-2xl: 48px;  
  
  /* Border Radius */  
  --radius-xs: 6px;  
  --radius-sm: 10px;  
  --radius-md: 14px;  
  --radius-lg: 20px;  
  --radius-xl: 28px;  
  --radius-full: 9999px;  
  
  /* Shadows */  
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);  
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04);  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12), 0 4px 16px rgba(0, 0, 0, 0.08);  
  --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.16), 0 8px 24px rgba(0, 0, 0, 0.12);  
  
  /* Light Theme */  
  --bg-primary: #fafbfc;  
  --bg-secondary: #f4f6f8;  
  --bg-tertiary: #ffffff;  
  --text-primary: #0d1117;  
  --text-secondary: #424a53;  
  --text-tertiary: #656d76;  
  --text-quaternary: #8b949e;  
  --border-primary: rgba(13, 17, 23, 0.08);  
  --border-secondary: rgba(13, 17, 23, 0.12);  
  --surface: rgba(255, 255, 255, 0.92);  
  --surface-hover: rgba(248, 250, 252, 0.96);  
  
  /* Brand Colors */  
  --brand-primary: #6366f1;  
  --brand-primary-light: #818cf8;  
  --brand-primary-dark: #4f46e5;  
  --brand-success: #10b981;  
  --brand-warning: #f59e0b;  
  --brand-error: #ef4444;  
  --brand-info: #06b6d4;  
  
  /* Gradients */  
  --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);  
  --gradient-surface: linear-gradient(145deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 251, 252, 0.98) 100%);  
  --gradient-mesh:  
    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.06) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(244, 114, 182, 0.06) 0px, transparent 50%);  
}  

/* ===================== DARK MODE VARIABLES ===================== */  
.dark-mode {  
  --bg-primary: #0d1117;  
  --bg-secondary: #161b22;  
  --bg-tertiary: #21262d;  
  --text-primary: #f0f6fc;  
  --text-secondary: #c9d1d9;  
  --text-tertiary: #8b949e;  
  --text-quaternary: #6e7681;  
  --border-primary: rgba(240, 246, 252, 0.10);  
  --border-secondary: rgba(240, 246, 252, 0.14);  
  --surface: rgba(33, 38, 45, 0.92);  
  --surface-hover: rgba(48, 54, 61, 0.96);  
  
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.6);  
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.7), 0 1px 4px rgba(0, 0, 0, 0.6);  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.8), 0 2px 8px rgba(0, 0, 0, 0.7);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.85), 0 4px 16px rgba(0, 0, 0, 0.8);  
  --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.9), 0 8px 24px rgba(0, 0, 0, 0.85);  
  
  --gradient-surface: linear-gradient(145deg, rgba(33, 38, 45, 0.95) 0%, rgba(22, 27, 34, 0.98) 100%);  
  --gradient-mesh:  
    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.12) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(244, 114, 182, 0.12) 0px, transparent 50%);  
}  

/* ===================== BASE STYLES ===================== */  
* {  
  margin: 0;  
  padding: 0;  
  box-sizing: border-box;  
}  

body {  
  font-family: var(--font-primary) !important;  
  background: var(--bg-primary) !important;  
  color: var(--text-primary) !important;  
  transition: background-color 0.3s ease, color 0.3s ease;  
  min-height: 100vh;  
  display: flex;  
  flex-direction: column;  
}  

.main-content {  
  position: relative;  
  flex: 1;  
  padding: var(--space-xl) var(--space-lg);  
}  

.main-content::before {  
  content: '';  
  position: fixed;  
  top: 0;  
  left: 0;  
  right: 0;  
  bottom: 0;  
  background: var(--gradient-mesh);  
  pointer-events: none;  
  z-index: 0;  
  opacity: 0.4;  
}  

.container {  
  position: relative;  
  z-index: 1;  
  max-width: 1400px;  
}  

/* ===================== PAGE HERO ===================== */  
.page-hero {  
  position: relative;  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-xl);  
  overflow: hidden;  
  animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);  
}  

.page-hero::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 5px;  
  background: var(--gradient-primary);  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;  
}  

@keyframes fadeInUp {  
  from {  
    opacity: 0;  
    transform: translateY(30px);  
  }  
  to {  
    opacity: 1;  
    transform: translateY(0);  
  }  
}  

.page-title-row {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  gap: var(--space-lg);  
  flex-wrap: wrap;  
}  

.page-title-left {  
  display: flex;  
  align-items: center;  
  gap: var(--space-lg);  
}  

.title-icon {  
  position: relative;  
  width: 64px;  
  height: 64px;  
  flex-shrink: 0;  
  border-radius: var(--radius-lg);  
  background: var(--gradient-primary);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  box-shadow: var(--shadow-xl);  
  overflow: hidden;  
}  

.title-icon i {  
  font-size: 32px;  
  color: var(--text-primary);  
  position: relative;  
  z-index: 1;  
}  

.dark-mode .title-icon {
  color: white; 
}

.page-title-text h2 {  
  font-size: 32px;  
  font-weight: 800;  
  letter-spacing: -0.03em;  
  color: var(--text-primary) !important;  
  margin: 0 0 4px 0;  
  line-height: 1.2;  
}  

.page-subtitle {  
  font-size: 14px;  
  color: var(--text-secondary);  
  margin: 0;  
  line-height: 1.6;  
}  

/* ===================== CARDS ===================== */  
.card {  
  background: var(--gradient-surface) !important;  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary) !important;  
  border-radius: var(--radius-xl) !important;  
  box-shadow: var(--shadow-lg);  
  margin-bottom: var(--space-lg);  
  overflow: hidden;  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1);  
  transition: all 0.3s ease;  
}  

.card:hover {  
  box-shadow: var(--shadow-xl);  
}  

@keyframes fadeIn {  
  from { opacity: 0; }  
  to { opacity: 1; }  
}  

.card-header {  
  background: var(--bg-secondary) !important;  
  border-bottom: 2px solid var(--border-primary) !important;  
  padding: var(--space-lg) !important;  
  font-weight: 700;  
  font-size: 16px;  
  color: var(--text-primary) !important;  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  position: relative;  
}  

.card-header::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 4px;  
  background: var(--gradient-primary);  
}  

.card-header strong {  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
  font-size: 18px;  
  color: var(--text-primary);  
}  

.card-header strong i {  
  font-size: 20px;  
  color: var(--brand-primary);  
}  

.card-body {  
  padding: var(--space-xl) !important;  
  background: transparent !important;  
}  

.card-body.p-0 {  
  padding: 0 !important;  
}  

/* ===================== FORM ELEMENTS ===================== */  
.form-label {  
  font-size: 13px;  
  font-weight: 700;  
  color: var(--text-secondary) !important;  
  margin-bottom: var(--space-sm);  
  margin-top: 10px;
  letter-spacing: -0.01em;  
  text-transform: uppercase;  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.form-label i {  
  font-size: 14px;  
  color: var(--brand-primary);  
}  

.form-control,  
.form-select {  
  background: var(--bg-tertiary) !important;  
  border: 2px solid var(--border-primary) !important;  
  border-radius: var(--radius-md) !important;  
  padding: 12px 16px !important;  
  font-size: 15px !important;  
  color: var(--text-primary) !important;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  font-weight: 500;  
  font-family: var(--font-primary);  
  line-height: 1.5 !important;  
}  

.form-control::placeholder {  
  color: var(--text-quaternary) !important;  
  opacity: 1;  
}  

.form-control:focus,  
.form-select:focus {  
  outline: none !important;  
  border-color: var(--brand-primary) !important;  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;  
  background: var(--surface) !important;  
  color: var(--text-primary) !important;  
}  

.form-control:hover,  
.form-select:hover {  
  border-color: var(--border-secondary) !important;  
}  

.form-select {  
  cursor: pointer;  
  appearance: none;  
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236366f1'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") !important;  
  background-repeat: no-repeat !important;  
  background-position: right 12px center !important;  
  background-size: 24px !important;  
  padding-right: 40px !important;  
}  

.form-select option {  
  background: var(--bg-tertiary) !important;  
  color: var(--text-primary) !important;  
  padding: 8px;  
}  

.form-check {  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
  padding-left: 0 !important;  
}  

.form-check-input {  
  width: 20px;  
  height: 20px;  
  border: 2px solid var(--border-primary) !important;  
  border-radius: var(--radius-xs) !important;  
  background-color: var(--bg-tertiary) !important;  
  cursor: pointer;  
  margin: 0 !important;  
  transition: all 0.3s ease;  
}  

.form-check-input:checked {  
  background-color: var(--brand-primary) !important;  
  border-color: var(--brand-primary) !important;  
}  

.form-check-input:focus {  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;  
  border-color: var(--brand-primary) !important;  
}  

.form-check-label {  
  color: var(--text-secondary) !important;  
  font-weight: 600;  
  font-size: 14px;  
  cursor: pointer;  
  user-select: none;  
  margin: 0;  
}  

/* ===================== BUTTONS ===================== */  
.btn {  
  position: relative;  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  gap: var(--space-sm);  
  padding: 12px 24px;  
  font-family: var(--font-primary);  
  font-size: 15px;  
  font-weight: 700;  
  line-height: 1;  
  border: none;  
  border-radius: var(--radius-md) !important;  
  cursor: pointer;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  white-space: nowrap;  
  text-decoration: none;  
}  

.btn i {  
  font-size: 16px;  
  transition: transform 0.3s ease;  
}  

.btn-primary {  
  background: var(--gradient-primary) !important;  
  color: white !important;  
  box-shadow: var(--shadow-md), inset 0 1px 0 rgba(255, 255, 255, 0.1);  
  border: none !important;  
}  

.btn-primary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl), inset 0 1px 0 rgba(255, 255, 255, 0.1);  
  color: white !important;  
}  

.btn-secondary,  
.btn-outline-light {  
  background: var(--bg-tertiary) !important;  
  color: var(--text-primary) !important;  
  border: 2px solid var(--border-primary) !important;  
  box-shadow: var(--shadow-sm);  
}  

.btn-secondary:hover,  
.btn-outline-light:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  border-color: var(--brand-primary) !important;  
  color: var(--text-primary) !important;  
}  

.btn-sm {  
  padding: 8px 16px !important;  
  font-size: 13px !important;  
}  

.btn-group {  
  display: inline-flex;  
  gap: var(--space-xs);  
}  

.btn:active {  
  transform: translateY(0) !important;  
}  

.btn:disabled {  
  opacity: 0.6;  
  cursor: not-allowed;  
  transform: none !important;  
}  

.text-danger {  
  color: var(--brand-error) !important;  
}  

.text-danger:hover {  
  color: #dc2626 !important;  
}  

/* ===================== TABLE ===================== */  
.table-responsive {  
  border-radius: var(--radius-md);  
  overflow: hidden;  
  background: transparent;  
}  

.table {  
  color: var(--text-primary) !important;  
  margin-bottom: 0;  
  background: transparent !important;  
}  

.table thead th {  
  background: var(--bg-secondary) !important;  
  color: var(--text-primary) !important;  
  font-weight: 700;  
  font-size: 13px;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  padding: 16px 12px !important;  
  border-bottom: 2px solid var(--border-primary) !important;  
  border-top: none !important;  
  vertical-align: middle;  
  white-space: nowrap;  
}  

.table tbody td {  
  padding: 16px 12px !important;  
  vertical-align: middle;  
  border-bottom: 1px solid var(--border-primary) !important;  
  font-size: 14px;  
  color: var(--text-secondary) !important;  
  background: transparent !important;  
  border-left: none !important;  
  border-right: none !important;  
  border-top: none !important;  
}  

.table tbody tr {  
  transition: all 0.2s ease;  
  background: transparent !important;  
}  

.table tbody tr:hover {  
  background: var(--surface-hover) !important;  
  transform: scale(1.002);  
}  

.table tbody tr:last-child td {  
  border-bottom: none !important;  
}  

/* ===================== BADGES ===================== */  
.badge {  
  display: inline-flex;  
  align-items: center;  
  gap: 4px;  
  padding: 6px 12px;  
  border-radius: var(--radius-full);  
  font-size: 12px;  
  font-weight: 700;  
  letter-spacing: 0.02em;  
  border: 1.5px solid transparent;  
}  

.badge i {  
  font-size: 13px;  
}  

.bg-success {  
  background: rgba(16, 185, 129, 0.15) !important;  
  color: var(--brand-success) !important;  
  border-color: rgba(16, 185, 129, 0.3) !important;  
}  

.bg-secondary {  
  background: rgba(107, 114, 128, 0.15) !important;  
  color: var(--text-secondary) !important;  
  border-color: rgba(107, 114, 128, 0.3) !important;  
}  

.bg-info {  
  background: rgba(6, 182, 212, 0.15) !important;  
  color: var(--brand-info) !important;  
  border-color: rgba(6, 182, 212, 0.3) !important;  
}  

.bg-warning {  
  background: rgba(245, 158, 11, 0.15) !important;  
  color: var(--brand-warning) !important;  
  border-color: rgba(245, 158, 11, 0.3) !important;  
}  

/* ===================== MODALS ===================== */  
.modal-content {  
  background: var(--gradient-surface) !important;  
  backdrop-filter: blur(32px) saturate(180%);  
  border: 1px solid var(--border-primary) !important;  
  border-radius: var(--radius-xl) !important;  
  box-shadow: var(--shadow-xl);  
  color: var(--text-primary) !important;  
}  

.modal-header {  
  background: var(--bg-secondary) !important;  
  border-bottom: 2px solid var(--border-primary) !important;  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0 !important;  
  padding: var(--space-lg) !important;  
  position: relative;  
}  

.modal-header::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 4px;  
  background: var(--gradient-primary);  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;  
}  

.modal-title {  
  font-size: 20px;  
  font-weight: 800;  
  color: var(--text-primary) !important;  
  margin: 0;  
}  

.modal-body {  
  padding: var(--space-xl) !important;  
  background: transparent !important;  
}  

.modal-footer {  
  background: var(--bg-secondary) !important;  
  border-top: 2px solid var(--border-primary) !important;  
  border-radius: 0 0 var(--radius-xl) var(--radius-xl) !important;  
  padding: var(--space-lg) !important;  
  display: flex;  
  justify-content: flex-end;  
  gap: var(--space-sm);  
}  

.btn-close {  
  background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23999'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat !important;  
  opacity: 0.7;  
  transition: opacity 0.2s ease, transform 0.2s ease;  
  width: 32px;  
  height: 32px;  
  border-radius: var(--radius-sm);  
}  

.btn-close:hover {  
  opacity: 1;  
  transform: rotate(90deg);  
  background-color: rgba(239, 68, 68, 0.1) !important;  
}  

.dark-mode .btn-close {  
  filter: invert(1) grayscale(100%) brightness(200%);  
}  

.modal-backdrop {  
  background: rgba(0, 0, 0, 0.75);  
  backdrop-filter: blur(8px);  
}  

.dark-mode .modal-backdrop {  
  background: rgba(0, 0, 0, 0.85);  
}  

/* ===================== ALERT SECTIONS ===================== */  
.alert-info {  
  background: rgba(6, 182, 212, 0.12);  
  border: 1.5px solid rgba(6, 182, 212, 0.3);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  color: var(--text-secondary);  
  font-size: 13px;  
  line-height: 1.6;  
  display: flex;  
  align-items: flex-start;  
  gap: var(--space-sm);  
  margin-top: var(--space-md);  
}  

.alert-info i {  
  color: var(--brand-info);  
  font-size: 18px;  
  margin-top: 2px;  
  flex-shrink: 0;  
}  

.alert-info code {  
  background: rgba(6, 182, 212, 0.15);  
  color: var(--brand-info);  
  padding: 2px 8px;  
  border-radius: var(--radius-xs);  
  font-family: 'Courier New', monospace;  
  font-weight: 700;  
  font-size: 12px;  
}  

.alert-info strong {  
  color: var(--text-primary);  
  font-weight: 700;  
}  

/* ===================== UTILITY CLASSES ===================== */  
.text-center {  
  text-align: center;  
}  

.text-end {  
  text-align: right;  
}  

.muted,  
small.muted {  
  color: var(--text-tertiary) !important;  
  font-size: 13px;  
}  

.d-flex {  
  display: flex !important;  
}  

.align-items-center {  
  align-items: center !important;  
}  

.align-items-end {  
  align-items: flex-end !important;  
}  

.justify-content-between {  
  justify-content: space-between !important;  
}  

.gap-2 {  
  gap: var(--space-sm) !important;  
}  

.mb-0 { margin-bottom: 0 !important; }  
.mb-2 { margin-bottom: var(--space-sm) !important; }  
.mb-3 { margin-bottom: var(--space-md) !important; }  
.mt-1 { margin-top: var(--space-xs) !important; }  
.mt-2 { margin-top: var(--space-sm) !important; }  
.mt-3 { margin-top: var(--space-md) !important; }  
.me-1 { margin-right: var(--space-xs) !important; }  
.ms-1 { margin-left: var(--space-xs) !important; }  

/* ===================== EMPTY STATE ===================== */  
.empty-state {  
  text-align: center;  
  padding: var(--space-2xl) var(--space-lg);  
  color: var(--text-tertiary);  
}  

.empty-state i {  
  font-size: 64px;  
  opacity: 0.2;  
  margin-bottom: var(--space-md);  
  color: var(--brand-primary);  
}  

.empty-state p {  
  font-size: 16px;  
  font-weight: 600;  
  margin: 0;  
}  

/* ===================== RESPONSIVE ===================== */  
@media (max-width: 992px) {  
  .page-title-row {  
    flex-direction: column;  
    align-items: flex-start;  
  }  
  
  .page-title-left {  
    width: 100%;  
  }  
  
  .card-header {  
    flex-direction: column;  
    gap: var(--space-md);  
    align-items: flex-start;  
  }  
  
  .card-header .btn {  
    width: 100%;  
  }  
}  

@media (max-width: 768px) {  
  .main-content {  
    padding: var(--space-md);  
  }  
  
  .page-hero {  
    padding: var(--space-lg);  
  }  
  
  .page-title-text h2 {  
    font-size: 24px;  
  }  
  
  .title-icon {  
    width: 56px;  
    height: 56px;  
  }  
  
  .title-icon i {  
    font-size: 28px;  
  }  
  
  .card-body {  
    padding: var(--space-lg) !important;  
  }  
  
  .table thead th,  
  .table tbody td {  
    padding: 12px 8px !important;  
    font-size: 13px;  
  }  
  
  .btn {  
    width: 100%;  
    justify-content: center;  
  }  
  
  .modal-dialog {  
    margin: var(--space-md);  
  }  
}  

@media (max-width: 576px) {  
  .table-responsive {  
    font-size: 12px;  
  }  
  
  .badge {  
    font-size: 11px;  
    padding: 4px 8px;  
  }  
  
  .btn-sm {  
    padding: 6px 12px !important;  
    font-size: 12px !important;  
  }  
}  

/* ===================== SCROLLBAR ===================== */  
::-webkit-scrollbar {  
  width: 12px;  
  height: 12px;  
}  

::-webkit-scrollbar-track {  
  background: var(--bg-secondary);  
  border-radius: var(--radius-sm);  
}  

::-webkit-scrollbar-thumb {  
  background: var(--brand-primary);  
  border-radius: var(--radius-sm);  
  border: 2px solid var(--bg-secondary);  
}  

::-webkit-scrollbar-thumb:hover {  
  background: var(--brand-primary-dark);  
}  

/* ===================== LOADING STATE ===================== */  
.spinner-border {  
  border-color: var(--brand-primary);  
  border-right-color: transparent;  
}  

/* ===================== ANIMATIONS ===================== */  
@keyframes slideIn {  
  from {  
    opacity: 0;  
    transform: translateX(-20px);  
  }  
  to {  
    opacity: 1;  
    transform: translateX(0);  
  }  
}  

.table tbody tr {  
  animation: slideIn 0.3s ease backwards;  
}  

.table tbody tr:nth-child(1) { animation-delay: 0.05s; }  
.table tbody tr:nth-child(2) { animation-delay: 0.1s; }  
.table tbody tr:nth-child(3) { animation-delay: 0.15s; }  
.table tbody tr:nth-child(4) { animation-delay: 0.2s; }  
.table tbody tr:nth-child(5) { animation-delay: 0.25s; }  
</style>  
</head>  
<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<main class="main-content">  
  <div class="container py-4">  

    <!-- PAGE HERO -->  
    <section class="page-hero">  
      <div class="page-title-row">  
        <div class="page-title-left">  
          <div class="title-icon">  
            <i class="fas fa-users-cog"></i>  
          </div>  
          <div class="page-title-text">  
            <h2>Gerenciamento de Equipes</h2>  
            <p class="page-subtitle">  
              Organize equipes, membros e defina regras de distribuição automática por Atribuição e Tipo de pedido.  
            </p>  
          </div>  
        </div>  
        <div>  
          <a href="tarefas.php" class="btn btn-outline-light">  
            <i class="fas fa-tasks"></i> Ver Fila de Tarefas  
          </a>  
        </div>  
      </div>  
    </section>  

    <!-- CONTENT GRID -->  
    <div class="row g-3">  
      
      <!-- COLUNA ESQUERDA: EQUIPES -->  
      <div class="col-lg-5">  
        <div class="card">  
          <div class="card-header">  
            <strong><i class="fas fa-users"></i> Equipes Cadastradas</strong>  
            <button class="btn btn-sm btn-primary" id="btnNovaEquipe">  
              <i class="fas fa-plus"></i> Nova Equipe  
            </button>  
          </div>  
          <div class="card-body p-0">  
            <div class="table-responsive">  
              <table class="table table-sm table-hover mb-0" id="tEquipes">  
                <thead>  
                  <tr>  
                    <th style="width: 50px;">#</th>  
                    <th>Nome</th>  
                    <th style="width: 100px;">Status</th>  
                    <th style="width: 80px;">Membros</th>  
                    <th style="width: 80px;">Regras</th>  
                    <th style="width: 180px;"></th>  
                  </tr>  
                </thead>  
                <tbody>  
                  <!-- Preenchido por JavaScript -->  
                </tbody>  
              </table>  
            </div>  
          </div>  
        </div>  
      </div>  

      <!-- COLUNA DIREITA: MEMBROS E REGRAS -->  
      <div class="col-lg-7">  
        
        <!-- MEMBROS DA EQUIPE -->  
        <div class="card mb-3">  
          <div class="card-header">  
            <strong><i class="fas fa-user-friends"></i> Membros da Equipe</strong>  
            <span class="muted" id="equipeNomeSelecionada">Selecione uma equipe para gerenciar</span>  
          </div>  
          <div class="card-body">  
            <form class="row g-3 align-items-end" id="formMembro">  
              <input type="hidden" id="m_equipe_id">  
              
              <div class="col-md-8">  
                <label class="form-label">  
                  <i class="fas fa-user"></i> Funcionário  
                </label>  
                <select class="form-select" id="m_func_id" required>  
                  <option value="">Selecione...</option>  
                </select>  
              </div>  
              
              <div class="col-md-4">  
                <label class="form-label">  
                  <i class="fas fa-id-badge"></i> Papel  
                </label>  
                <input class="form-control" id="m_papel" placeholder="Ex.: Emissor">  
              </div>  
              
              <div class="col-md-2">  
                <label class="form-label">  
                  <i class="fas fa-sort-numeric-down"></i> Ordem  
                </label>  
                <input type="number" class="form-control" id="m_ordem" value="1" min="1">  
              </div>  
              
              <div class="col-md-3">  
                <label class="form-label">  
                  <i class="fas fa-tachometer-alt"></i> Carga Máx.  
                </label>  
                <input type="number" class="form-control" id="m_carga" min="0" placeholder="Dia">  
              </div>  
              
              <div class="col-md-3">  
                <div class="form-check">  
                  <input class="form-check-input" type="checkbox" id="m_ativo" checked>  
                  <label class="form-check-label" for="m_ativo">Membro Ativo</label>  
                </div>  
              </div>  
              
              <div class="col-md-4 text-end">  
                <button class="btn btn-primary" type="submit">  
                  <i class="fas fa-plus"></i> Adicionar/Atualizar  
                </button>  
              </div>  
            </form>  

            <div class="table-responsive mt-3">  
              <table class="table table-sm table-hover mb-0" id="tMembros">  
                <thead>  
                  <tr>  
                    <th style="width: 50px;">#</th>  
                    <th>Funcionário</th>  
                    <th style="width: 120px;">Papel</th>  
                    <th style="width: 80px;">Ordem</th>  
                    <th style="width: 100px;">Ativo</th>  
                    <th style="width: 100px;">Carga Máx.</th>  
                    <th style="width: 80px;"></th>  
                  </tr>  
                </thead>  
                <tbody>  
                  <tr>  
                    <td colspan="7" class="text-center">  
                      <div class="empty-state">  
                        <i class="fas fa-user-plus"></i>  
                        <p>Selecione uma equipe</p>  
                      </div>  
                    </td>  
                  </tr>  
                </tbody>  
              </table>  
            </div>  
          </div>  
        </div>  

        <!-- REGRAS DE DISTRIBUIÇÃO -->  
        <div class="card">  
          <div class="card-header">  
            <strong><i class="fas fa-sitemap"></i> Regras de Distribuição</strong>  
          </div>  
          <div class="card-body">  
            <form class="row g-3 align-items-end" id="formRegra">  
              <input type="hidden" id="r_equipe_id">  
              <input type="hidden" id="r_id">  
              
              <div class="col-md-4">  
                <label class="form-label">  
                    <i class="fas fa-building"></i> Atribuição  
                </label>  
                <select class="form-select" id="r_atr" required>  
                    <option value="">Selecione...</option>  
                </select>  
              </div>
 
              
              <div class="col-md-6">  
                <label class="form-label">  
                    <i class="fas fa-tag"></i> Tipo  
                </label>  
                <select class="form-select" id="r_tipo" required>
                    <option value="">Selecione...</option>
                </select>
              </div>
 
              <div class="col-md-2">  
                <label class="form-label">  
                  <i class="fas fa-sort-amount-down"></i> Prioridade  
                </label>  
                <input type="number" class="form-control" id="r_prior" value="10" min="0">  
              </div>  
              
              <div class="col-md-3">  
                <div class="form-check mt-4">  
                  <input class="form-check-input" type="checkbox" id="r_ativa" checked>  
                  <label class="form-check-label" for="r_ativa">Regra Ativa</label>  
                </div>  
              </div>  
              
              <div class="col-8 text-end">  
                <button class="btn btn-secondary me-2" type="button" id="btnLimparRegra">  
                  <i class="fas fa-eraser"></i> Limpar Formulário  
                </button>  
                <button class="btn btn-primary" type="submit">  
                  <i class="fas fa-save"></i> Salvar Regra  
                </button>  
              </div>  
            </form>  

            <div class="table-responsive mt-3">  
              <table class="table table-sm table-hover mb-0" id="tRegras">  
                <thead>  
                  <tr>  
                    <th style="width: 50px;">#</th>  
                    <th>Atribuição</th>  
                    <th>Tipo</th>  
                    <th style="width: 100px;">Prioridade</th>  
                    <th style="width: 100px;">Ativa</th>  
                    <th style="width: 140px;"></th>  
                  </tr>  
                </thead>  
                <tbody>  
                  <tr>  
                    <td colspan="6" class="text-center">  
                      <div class="empty-state">  
                        <i class="fas fa-cogs"></i>  
                        <p>Selecione uma equipe</p>  
                      </div>  
                    </td>  
                  </tr>  
                </tbody>  
              </table>  
            </div>  

            <div class="alert-info">  
              <i class="fas fa-info-circle"></i>  
              <div>  
                <strong>Dica:</strong> Use <code>*</code> no campo <strong>Tipo</strong> para capturar qualquer tipo dentro da Atribuição (ex.: <em>Registro Civil + *</em>).  
                Quando múltiplas regras coincidirem, a de <strong>menor prioridade</strong> (número menor) vence.  
              </div>  
            </div>  
          </div>  
        </div>  

      </div>  
    </div>  

  </div>  
</main>  

<!-- Modal Equipe -->  
<div class="modal fade" id="modalEquipe" tabindex="-1" aria-hidden="true">  
  <div class="modal-dialog modal-dialog-centered">  
    <form class="modal-content" id="formEquipe">  
      <div class="modal-header">  
        <h5 class="modal-title">  
          <i class="fas fa-users"></i> Cadastro de Equipe  
        </h5>  
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
      </div>  
      <div class="modal-body">  
        <input type="hidden" name="id" id="eq_id">  
        
        <div class="mb-3">  
          <label class="form-label">  
            <i class="fas fa-signature"></i> Nome da Equipe  
          </label>  
          <input class="form-control" name="nome" id="eq_nome" placeholder="Ex.: Equipe de Registros Civis" required>  
        </div>  
        
        <div class="mb-3">  
          <label class="form-label">  
            <i class="fas fa-align-left"></i> Descrição  
          </label>  
          <textarea class="form-control" name="descricao" id="eq_desc" rows="3" placeholder="Descreva o objetivo e responsabilidades desta equipe..."></textarea>  
        </div>  
        
        <div class="form-check">  
          <input class="form-check-input" type="checkbox" id="eq_ativa" checked>  
          <label class="form-check-label" for="eq_ativa">Equipe Ativa</label>  
        </div>  
      </div>  
      <div class="modal-footer">  
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">  
          <i class="fas fa-times"></i> Cancelar  
        </button>  
        <button class="btn btn-primary" type="submit">  
          <i class="fas fa-save"></i> Salvar Equipe  
        </button>  
      </div>  
    </form>  
  </div>  
</div>  

<?php include(__DIR__ . '/../rodape.php'); ?>  

<script src="../script/jquery-3.5.1.min.js"></script>  
<script src="../script/bootstrap.bundle.min.js"></script>  
<script src="../script/sweetalert2.js"></script>  
<script>  
// ===================== VARIÁVEIS GLOBAIS =====================  
let MODAL_EQ = null;  
let EQUIPE_SELECIONADA = null;  

// ===================== MAPA (mesmo do novo_pedido.php, mas apenas os TIPO/nomes) =====================
const MAPEAMENTO_REGRAS = {
  "Registro Civil": [
    "2ª via de Nascimento",
    "Inteiro Teor de Nascimento",
    "Retificação Administrativa de Nascimento",
    "Restauração de Nascimento",
"Suprimento Total de Nascimento",
"Suprimento Parcial de Nascimento",
    "Busca de Nascimento",
    "2ª via de Casamento",
    "Inteiro Teor de Casamento",
    "Retificação Administrativa de Casamento",
    "Restauração de Casamento",
"Suprimento Total de Casamento",
"Suprimento Parcial de Casamento",
    "Busca de Casamento",
    "Divórcio",
    "2ª via de Óbito",
    "Inteiro Teor de Óbito",
    "Retificação Administrativa de Óbito",
    "Restauração de Óbito",
"Suprimento Total de Óbito",
"Suprimento Parcial de Óbito",    
"Busca de Óbito",
"Reconhecimento de Filiação",
"Alteração de Patronímico",
"Resposta CRC de Nascimento",
"Resposta CRC de Casamento",
"Resposta CRC de Óbito"

  ],
  "Pessoas Jurídicas": [
    "Estatuto",
    "Atas",
    "Outros"
  ],
  "Títulos e Documentos": [
    "Contratos",
    "Cédulas",
    "Outros"
  ],
  "Registro de Imóveis": [
    "Matrícula Livro 2",
    "Registro Livro 3",
    "Ônus",
    "Penhor",
    "Negativa",
    "Situação Jurídica"
  ],
  "Notas": [
    "Escrituras",
    "Testamentos",
    "Procurações",
    "Ata Notarial"
  ]
};

// Preenche a lista de atribuições
function popularAtribuicoes() {
  const $atr = $('#r_atr').empty();
  $atr.append('<option value="">Selecione...</option>');
  Object.keys(MAPEAMENTO_REGRAS).forEach(nome => {
    $atr.append(new Option(nome, nome));
  });
}

// Preenche a lista de tipos conforme a atribuição selecionada
function popularTipos(atribuicao, valorSelecionado) {
  const $tipo = $('#r_tipo').empty();
  $tipo.append('<option value="">Selecione...</option>');
  // sempre oferece curinga
  $tipo.append(new Option('* (qualquer tipo)', '*'));

  const lista = MAPEAMENTO_REGRAS[atribuicao] || [];
  lista.forEach(tp => $tipo.append(new Option(tp, tp)));

  if (valorSelecionado) {
    // se o valor não estiver na lista, mantém criando option ad-hoc (para regras antigas fora do mapa)
    if ($tipo.find(`option[value="${valorSelecionado.replace(/"/g, '\\"')}"]`).length === 0) {
      $tipo.append(new Option(valorSelecionado, valorSelecionado));
    }
    $tipo.val(valorSelecionado);
  } else {
    $tipo.val('');
  }
}

// ===================== INICIALIZAÇÃO =====================  
$(function(){  
  // Carrega tema  
  $.get('../load_mode.php', function(mode){  
    $('body').removeClass('light-mode dark-mode').addClass(mode);  
  });  

  // Inicializa modal  
  MODAL_EQ = new bootstrap.Modal(document.getElementById('modalEquipe'));  
  
  // Carrega dados iniciais  
  carregarFuncionarios();  
  carregarEquipes();  

    // Event Listeners  
  $('#btnNovaEquipe').on('click', abrirModalNovaEquipe);  
  $('#formEquipe').on('submit', salvarEquipe);  
  $('#formMembro').on('submit', salvarMembro);  
  $('#formRegra').on('submit', salvarRegra);  
  $('#btnLimparRegra').on('click', limparFormularioRegra);  

  // Atribuição/Tipo dinâmicos
  popularAtribuicoes();
  $('#r_atr').on('change', function() {
    const atr = $(this).val();
    popularTipos(atr);
  });
});  

// ===================== MODAL NOVA EQUIPE =====================  
function abrirModalNovaEquipe() {  
  $('#eq_id').val('');  
  $('#eq_nome').val('');  
  $('#eq_desc').val('');  
  $('#eq_ativa').prop('checked', true);  
  MODAL_EQ.show();  
}  

// ===================== SALVAR EQUIPE =====================
function salvarEquipe(e) {
  e.preventDefault();
  
  const payload = {
    action: 'salvar_equipe',
    id: $('#eq_id').val(),
    nome: $('#eq_nome').val(),
    descricao: $('#eq_desc').val(),
    ativa: $('#eq_ativa').is(':checked') ? 1 : 0
  };

  $.ajax({
    url: 'equipes.php',
    method: 'POST',
    data: payload,
    dataType: 'json',
    success: function(r) {
      if (r.success) {
        MODAL_EQ.hide();
        Swal.fire({
          icon: 'success',
          title: 'Equipe Salva!',
          text: 'A equipe foi cadastrada com sucesso.',
          timer: 2000,
          showConfirmButton: false
        });
        carregarEquipes();
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao Salvar',
          text: r.error || 'Falha ao salvar equipe.',
          confirmButtonColor: '#6366f1'
        });
      }
    },
    error: function(xhr, status, error) {
      console.error('Erro AJAX:', error);
      Swal.fire({
        icon: 'error',
        title: 'Erro de Conexão',
        text: 'Não foi possível comunicar com o servidor.',
        confirmButtonColor: '#6366f1'
      });
    }
  });
}

// ===================== CARREGAR FUNCIONÁRIOS =====================
function carregarFuncionarios() {
  $.getJSON('equipes.php', { action: 'list_funcionarios' }, function(r) {
    if (!r.success) {
      console.error('Erro ao carregar funcionários');
      return;
    }
    
    const $sel = $('#m_func_id').empty();
    $sel.append('<option value="">Selecione um funcionário...</option>');
    
    r.data.forEach(f => {
      const label = `${f.nome_completo} (${f.usuario})`;
      $sel.append(new Option(label, f.id));
    });
  });
}

// ===================== CARREGAR EQUIPES =====================
function carregarEquipes() {
  $.getJSON('equipes.php', { action: 'list_equipes' }, function(r) {
    const $tb = $('#tEquipes tbody').empty();
    
    if (!r.success) {
      $tb.append(`
        <tr>
          <td colspan="6" class="text-center">
            <div class="empty-state">
              <i class="fas fa-exclamation-triangle"></i>
              <p>Falha ao carregar equipes</p>
            </div>
          </td>
        </tr>
      `);
      return;
    }
    
    if (!r.data.length) {
      $tb.append(`
        <tr>
          <td colspan="6" class="text-center">
            <div class="empty-state">
              <i class="fas fa-users-slash"></i>
              <p>Nenhuma equipe cadastrada</p>
            </div>
          </td>
        </tr>
      `);
      return;
    }
    
    r.data.forEach((e, idx) => {
      const badge = e.ativa 
        ? '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Ativa</span>' 
        : '<span class="badge bg-secondary"><i class="fas fa-pause-circle"></i> Inativa</span>';
      
      const tr = $(`
        <tr>
          <td><strong>${idx + 1}</strong></td>
          <td>
            <strong style="color: var(--text-primary);">${escapeHtml(e.nome)}</strong>
            ${e.descricao ? `<br><small class="muted">${escapeHtml(e.descricao)}</small>` : ''}
          </td>
          <td>${badge}</td>
          <td>
            <span class="badge bg-info">
              <i class="fas fa-users"></i> ${e.membros_ativos || 0}
            </span>
          </td>
          <td>
            <span class="badge bg-warning">
              <i class="fas fa-cogs"></i> ${e.regras_ativas || 0}
            </span>
          </td>
          <td class="text-end">
            <div class="btn-group">
              <button class="btn btn-sm btn-outline-light btn-editar" title="Editar equipe">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-light btn-selecionar" title="Selecionar equipe">
                <i class="fas fa-hand-pointer"></i>
              </button>
              <button class="btn btn-sm btn-outline-light text-danger btn-excluir" title="Excluir equipe">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `);
      
      // Editar
      tr.find('.btn-editar').on('click', function() {
        $('#eq_id').val(e.id);
        $('#eq_nome').val(e.nome);
        $('#eq_desc').val(e.descricao || '');
        $('#eq_ativa').prop('checked', !!Number(e.ativa));
        MODAL_EQ.show();
      });
      
      // Selecionar
      tr.find('.btn-selecionar').on('click', function() {
        EQUIPE_SELECIONADA = e;
        selecionarEquipe(e);
      });
      
      // Excluir
      tr.find('.btn-excluir').on('click', function() {
        excluirEquipe(e);
      });
      
      $tb.append(tr);
    });
  });
}

// ===================== SELECIONAR EQUIPE =====================
function selecionarEquipe(equipe) {
  $('#m_equipe_id').val(equipe.id);
  $('#r_equipe_id').val(equipe.id);
  $('#equipeNomeSelecionada').html(`
    <i class="fas fa-check-circle" style="color: var(--brand-success);"></i>
    Gerenciando: <strong>${escapeHtml(equipe.nome)}</strong>
  `);
  
  // Destaca linha selecionada
  $('#tEquipes tbody tr').removeClass('table-active');
  $('#tEquipes tbody tr').each(function() {
    if ($(this).find('td:nth-child(2) strong').text() === equipe.nome) {
      $(this).addClass('table-active');
    }
  });
  
  listarMembros(equipe.id);
  listarRegras(equipe.id);
  
  // Smooth scroll para seção de membros
  $('html, body').animate({
    scrollTop: $('#formMembro').offset().top - 100
  }, 500);
}

// ===================== EXCLUIR EQUIPE =====================
function excluirEquipe(equipe) {
  Swal.fire({
    title: 'Excluir Equipe?',
    html: `
      <div style="text-align: center;">
        <i class="fas fa-exclamation-triangle" style="font-size: 64px; color: var(--brand-error); margin-bottom: 16px;"></i>
        <p>Tem certeza que deseja excluir a equipe <strong>"${escapeHtml(equipe.nome)}"</strong>?</p>
        <p style="color: var(--text-tertiary); font-size: 13px; margin-top: 12px;">
          Esta ação é <strong>irreversível</strong> e removerá todos os membros e regras associados.
        </p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<i class="fas fa-trash"></i> Sim, excluir',
    cancelButtonText: '<i class="fas fa-times"></i> Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'equipes.php',
        method: 'POST',
        data: {
          action: 'remover_equipe',
          id: equipe.id
        },
        dataType: 'json',
        success: function(rs) {
          if (rs.success) {
            Swal.fire({
              icon: 'success',
              title: 'Equipe Excluída!',
              text: 'A equipe foi removida com sucesso.',
              timer: 2000,
              showConfirmButton: false
            });
            
            carregarEquipes();
            
            // Limpa seleção se era a equipe excluída
            if (EQUIPE_SELECIONADA && EQUIPE_SELECIONADA.id === equipe.id) {
              $('#m_equipe_id').val('');
              $('#r_equipe_id').val('');
              $('#equipeNomeSelecionada').text('Selecione uma equipe para gerenciar');
              $('#tMembros tbody').html(`
                <tr>
                  <td colspan="7" class="text-center">
                    <div class="empty-state">
                      <i class="fas fa-user-plus"></i>
                      <p>Selecione uma equipe</p>
                    </div>
                  </td>
                </tr>
              `);
              $('#tRegras tbody').html(`
                <tr>
                  <td colspan="6" class="text-center">
                    <div class="empty-state">
                      <i class="fas fa-cogs"></i>
                      <p>Selecione uma equipe</p>
                    </div>
                  </td>
                </tr>
              `);
              EQUIPE_SELECIONADA = null;
            }
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Erro ao Excluir',
              text: rs.error || 'Falha ao excluir equipe.',
              confirmButtonColor: '#6366f1'
            });
          }
        },
        error: function(xhr, status, error) {
          console.error('Erro AJAX:', error);
          Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Não foi possível excluir a equipe.',
            confirmButtonColor: '#6366f1'
          });
        }
      });
    }
  });
}

// ===================== SALVAR MEMBRO =====================
function salvarMembro(e) {
  e.preventDefault();
  
  const equipeId = $('#m_equipe_id').val();
  if (!equipeId) {
    Swal.fire({
      icon: 'warning',
      title: 'Atenção',
      text: 'Selecione uma equipe primeiro.',
      confirmButtonColor: '#6366f1'
    });
    return;
  }
  
  const payload = {
    action: 'salvar_membro',
    equipe_id: equipeId,
    funcionario_id: $('#m_func_id').val(),
    papel: $('#m_papel').val(),
    ordem: $('#m_ordem').val(),
    ativo: $('#m_ativo').is(':checked') ? 1 : 0,
    carga_maxima_diaria: $('#m_carga').val()
  };

  $.ajax({
    url: 'equipes.php',
    method: 'POST',
    data: payload,
    dataType: 'json',
    success: function(r) {
      if (r.success) {
        Swal.fire({
          icon: 'success',
          title: 'Membro Adicionado!',
          text: 'O membro foi adicionado/atualizado com sucesso.',
          timer: 2000,
          showConfirmButton: false
        });
        
        listarMembros(equipeId);
        carregarEquipes(); // Atualiza contadores
        
        // Limpa formulário
        $('#formMembro')[0].reset();
        $('#m_func_id').val('');
        $('#m_ordem').val('1');
        $('#m_ativo').prop('checked', true);
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao Adicionar',
          text: r.error || 'Falha ao adicionar membro.',
          confirmButtonColor: '#6366f1'
        });
      }
    },
    error: function(xhr, status, error) {
      console.error('Erro AJAX:', error);
      Swal.fire({
        icon: 'error',
        title: 'Erro de Conexão',
        text: 'Não foi possível adicionar o membro.',
        confirmButtonColor: '#6366f1'
      });
    }
  });
}

// ===================== LISTAR MEMBROS =====================
function listarMembros(equipeId) {
  $.getJSON('equipes.php', { action: 'listar_membros', equipe_id: equipeId }, function(r) {
    const $tb = $('#tMembros tbody').empty();
    
    if (!r.success) {
      $tb.append(`
        <tr>
          <td colspan="7" class="text-center">
            <div class="empty-state">
              <i class="fas fa-exclamation-triangle"></i>
              <p>Falha ao listar membros</p>
            </div>
          </td>
        </tr>
      `);
      return;
    }
    
    if (!r.data.length) {
      $tb.append(`
        <tr>
          <td colspan="7" class="text-center">
            <div class="empty-state">
              <i class="fas fa-user-plus"></i>
              <p>Nenhum membro cadastrado nesta equipe</p>
            </div>
          </td>
        </tr>
      `);
      return;
    }
    
    r.data.forEach((m, i) => {
      const ativo = m.ativo 
        ? '<span class="badge bg-success"><i class="fas fa-check"></i> Sim</span>' 
        : '<span class="badge bg-secondary"><i class="fas fa-times"></i> Não</span>';
      
      const carga = m.carga_maxima_diaria 
        ? `<span class="badge bg-info">${m.carga_maxima_diaria}/dia</span>`
        : '<span class="muted">—</span>';
      
      const tr = $(`
        <tr>
          <td><strong>${i + 1}</strong></td>
          <td>
            <strong style="color: var(--text-primary);">${escapeHtml(m.nome_completo)}</strong>
            <br><small class="muted"><i class="fas fa-user-circle"></i> ${escapeHtml(m.usuario)}</small>
          </td>
          <td>${escapeHtml(m.papel || '—')}</td>
          <td><span class="badge bg-secondary">${m.ordem}</span></td>
          <td>${ativo}</td>
          <td>${carga}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-light text-danger" title="Remover membro">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      `);
      
      tr.find('button').on('click', function() {
        Swal.fire({
          title: 'Remover Membro?',
          html: `
            <div style="text-align: center;">
              <i class="fas fa-user-times" style="font-size: 64px; color: var(--brand-error); margin-bottom: 16px;"></i>
              <p>Deseja remover <strong>${escapeHtml(m.nome_completo)}</strong> desta equipe?</p>
            </div>
          `,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#ef4444',
          cancelButtonColor: '#6b7280',
          confirmButtonText: '<i class="fas fa-trash"></i> Sim, remover',
          cancelButtonText: '<i class="fas fa-times"></i> Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'equipes.php',
              method: 'POST',
              data: {
                action: 'remover_membro',
                id: m.id
              },
              dataType: 'json',
              success: function(rs) {
                if (rs.success) {
                  Swal.fire({
                    icon: 'success',
                    title: 'Membro Removido!',
                    text: 'O membro foi removido da equipe.',
                    timer: 2000,
                    showConfirmButton: false
                  });
                  listarMembros(equipeId);
                  carregarEquipes();
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Erro ao Remover',
                    text: rs.error || 'Falha ao remover membro.',
                    confirmButtonColor: '#6366f1'
                  });
                }
              },
              error: function(xhr, status, error) {
                console.error('Erro AJAX:', error);
                Swal.fire({
                  icon: 'error',
                  title: 'Erro de Conexão',
                  text: 'Não foi possível remover o membro.',
                  confirmButtonColor: '#6366f1'
                });
              }
            });
          }
        });
      });
      
      $tb.append(tr);
    });
  });
}

// ===================== SALVAR REGRA =====================
function salvarRegra(e) {
  e.preventDefault();
  
  const equipeId = $('#r_equipe_id').val();
  if (!equipeId) {
    Swal.fire({
      icon: 'warning',
      title: 'Atenção',
      text: 'Selecione uma equipe primeiro.',
      confirmButtonColor: '#6366f1'
    });
    return;
  }
  
    const payload = {
        action: 'salvar_regra',
        id: $('#r_id').val(),
        equipe_id: equipeId,
        atribuicao: $('#r_atr').val(),
        tipo: $('#r_tipo').val(),            // <- agora vem do <select>
        prioridade: $('#r_prior').val(),
        ativa: $('#r_ativa').is(':checked') ? 1 : 0
    };


  $.ajax({
    url: 'equipes.php',
    method: 'POST',
    data: payload,
    dataType: 'json',
    success: function(r) {
      if (r.success) {
        Swal.fire({
          icon: 'success',
          title: 'Regra Salva!',
          text: 'A regra foi salva com sucesso.',
          timer: 2000,
          showConfirmButton: false
        });
        
        listarRegras(equipeId);
        carregarEquipes(); // Atualiza contadores
        limparFormularioRegra();
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao Salvar',
          text: r.error || 'Falha ao salvar regra.',
          confirmButtonColor: '#6366f1'
        });
      }
    },
    error: function(xhr, status, error) {
      console.error('Erro AJAX:', error);
      Swal.fire({
        icon: 'error',
        title: 'Erro de Conexão',
        text: 'Não foi possível salvar a regra.',
        confirmButtonColor: '#6366f1'
      });
    }
  });
}

// ===================== LISTAR REGRAS =====================
function listarRegras(equipeId) {
  $.getJSON('equipes.php', { action: 'listar_regras', equipe_id: equipeId }, function(r) {
    const $tb = $('#tRegras tbody').empty();
    
    if (!r.success) {
      $tb.append(`
        <tr>
          <td colspan="6" class="text-center">
            <div class="empty-state">
              <i class="fas fa-exclamation-triangle"></i>
              <p>Falha ao listar regras</p>
            </div>
          </td>
        </tr>
      `);
      return;
    }
    
    if (!r.data.length) {
      $tb.append(`
        <tr>
          <td colspan="6" class="text-center">
            <div class="empty-state">
              <i class="fas fa-cogs"></i>
              <p>Nenhuma regra cadastrada nesta equipe</p>
            </div>
          </td>
        </tr>
      `);
      return;
    }
    
    r.data.forEach((rg, i) => {
      const ativa = rg.ativa 
        ? '<span class="badge bg-success"><i class="fas fa-check"></i> Sim</span>' 
        : '<span class="badge bg-secondary"><i class="fas fa-times"></i> Não</span>';
      
      const tr = $(`
        <tr>
          <td><strong>${i + 1}</strong></td>
          <td>
            <span class="badge bg-info" style="font-size: 13px;">
              <i class="fas fa-building"></i> ${escapeHtml(rg.atribuicao)}
            </span>
          </td>
          <td>
            <code style="background: var(--bg-tertiary); color: var(--brand-primary); padding: 4px 10px; border-radius: var(--radius-xs); font-weight: 700; font-size: 13px;">
              ${escapeHtml(rg.tipo)}
            </code>
          </td>
          <td>
            <span class="badge bg-warning">
              <i class="fas fa-sort-amount-down"></i> ${rg.prioridade}
            </span>
          </td>
          <td>${ativa}</td>
          <td class="text-end">
            <div class="btn-group">
              <button class="btn btn-sm btn-outline-light btn-editar-regra" title="Editar regra">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-light text-danger btn-excluir-regra" title="Excluir regra">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `);
      
      // Editar
      tr.find('.btn-editar-regra').on('click', function() {
        $('#r_id').val(rg.id);
        $('#r_equipe_id').val(rg.equipe_id);

        // seta atribuição e popula os tipos correspondentes (inclui '*')
        $('#r_atr').val(rg.atribuicao);
        popularTipos(rg.atribuicao, rg.tipo);

        $('#r_prior').val(rg.prioridade);
        $('#r_ativa').prop('checked', !!Number(rg.ativa));
        
        // Smooth scroll para formulário
        $('html, body').animate({
            scrollTop: $('#formRegra').offset().top - 100
        }, 500);
        
        // Destaca formulário
        $('#formRegra').addClass('highlight-form');
        setTimeout(() => {
            $('#formRegra').removeClass('highlight-form');
        }, 2000);
      });

      
      // Excluir
      tr.find('.btn-excluir-regra').on('click', function() {
        Swal.fire({
          title: 'Excluir Regra?',
          html: `
            <div style="text-align: center;">
              <i class="fas fa-times-circle" style="font-size: 64px; color: var(--brand-error); margin-bottom: 16px;"></i>
              <p>Deseja excluir esta regra de distribuição?</p>
              <p style="margin-top: 12px;">
                <span class="badge bg-info" style="font-size: 14px;">${escapeHtml(rg.atribuicao)}</span>
                <i class="fas fa-arrow-right mx-2"></i>
                <code style="background: var(--bg-tertiary); color: var(--brand-primary); padding: 4px 10px; border-radius: 6px; font-weight: 700;">${escapeHtml(rg.tipo)}</code>
              </p>
            </div>
          `,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#ef4444',
          cancelButtonColor: '#6b7280',
          confirmButtonText: '<i class="fas fa-trash"></i> Sim, excluir',
          cancelButtonText: '<i class="fas fa-times"></i> Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'equipes.php',
              method: 'POST',
              data: {
                action: 'remover_regra',
                id: rg.id
              },
              dataType: 'json',
              success: function(rs) {
                if (rs.success) {
                  Swal.fire({
                    icon: 'success',
                    title: 'Regra Excluída!',
                    text: 'A regra foi removida com sucesso.',
                    timer: 2000,
                    showConfirmButton: false
                  });
                  listarRegras(equipeId);
                  carregarEquipes();
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Erro ao Excluir',
                    text: rs.error || 'Falha ao excluir regra.',
                    confirmButtonColor: '#6366f1'
                  });
                }
              },
              error: function(xhr, status, error) {
                console.error('Erro AJAX:', error);
                Swal.fire({
                  icon: 'error',
                  title: 'Erro de Conexão',
                  text: 'Não foi possível excluir a regra.',
                  confirmButtonColor: '#6366f1'
                });
              }
            });
          }
        });
      });
      
      $tb.append(tr);
    });
  });
}

// ===================== LIMPAR FORMULÁRIO REGRA =====================
function limparFormularioRegra() {
  $('#r_id').val('');
  $('#r_atr').val('');
  popularTipos('', ''); // reseta a lista de tipos (placeholder + '*')
  $('#r_prior').val('10');
  $('#r_ativa').prop('checked', true);
}

// ===================== UTILITY FUNCTIONS =====================
function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[m]));
}
</script>

<style>
/* Highlight form quando editando */
.highlight-form {
  animation: highlightPulse 2s ease;
}

@keyframes highlightPulse {
  0%, 100% { 
    background: transparent; 
  }
  50% { 
    background: rgba(99, 102, 241, 0.08); 
    border-radius: var(--radius-md);
  }
}

/* Linha ativa da tabela */
.table-active {
  background: rgba(99, 102, 241, 0.1) !important;
  border-left: 4px solid var(--brand-primary) !important;
}

/* Melhoria nos badges dentro das tabelas */
.table tbody td .badge {
  font-size: 12px;
  padding: 5px 10px;
}

/* Efeito hover nos botões de ação */
.btn-group .btn:hover {
  z-index: 2;
}
</style>

</body>
</html>