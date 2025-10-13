<?php  
// pedidos_certidao/tarefas.php  
// Kanban + Lista de tarefas geradas em tarefas_pedido, com filtros, reatribuição e log.  
// Cria/garante automaticamente as tabelas necessárias (MySQL 8+).  

include(__DIR__ . '/../os/session_check.php');  
checkSession();  
include(__DIR__ . '/../os/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  
header_remove('X-Powered-By');  

/* ========================================================================  
   SCHEMA - MySQL 8+ (mesmo padrão dos outros arquivos)  
   ======================================================================== */  
function ensureSchemaDistribuicao(PDO $conn) {  
  // equipes  
  $conn->exec("CREATE TABLE IF NOT EXISTS equipes (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    nome VARCHAR(120) NOT NULL,  
    descricao VARCHAR(500) NULL,  
    ativa TINYINT(1) NOT NULL DEFAULT 1,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    UNIQUE KEY uq_equipe_nome (nome),  
    INDEX idx_ativa (ativa)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  // equipe_membros  
  $conn->exec("CREATE TABLE IF NOT EXISTS equipe_membros (  
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  // regras  
  $conn->exec("CREATE TABLE IF NOT EXISTS equipe_regras (  
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  // tarefas  
  $conn->exec("CREATE TABLE IF NOT EXISTS tarefas_pedido (  
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  // log de mudanças da tarefa (auditoria)  
  $conn->exec("CREATE TABLE IF NOT EXISTS tarefas_pedido_log (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    tarefa_id BIGINT NOT NULL,  
    acao ENUM('status','reatribuicao','observacao') NOT NULL,  
    de_valor VARCHAR(255) NULL,  
    para_valor VARCHAR(255) NULL,  
    observacao VARCHAR(500) NULL,  
    usuario VARCHAR(120) NOT NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    CONSTRAINT fk_log_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas_pedido(id) ON DELETE CASCADE,  
    INDEX idx_tarefa (tarefa_id),  
    INDEX idx_acao (acao)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  
}  

try {  
  $conn = getDatabaseConnection();  
  ensureSchemaDistribuicao($conn);  
} catch (Throwable $e) {  
  // UI mostra aviso simples se necessário  
}  

/* ========================================================================  
   ENDPOINTS AJAX (JSON)  
   ======================================================================== */  
$isAjax = (isset($_GET['action']) || isset($_POST['action']))  
          || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest');  

if ($isAjax) {  
  header('Content-Type: application/json; charset=utf-8');  
  function J($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }  

  try {  
    $action = $_REQUEST['action'] ?? '';

    // ===== Contexto de acesso do usuário =====
    // 1) Tenta obter do BD os dados do usuário logado
    $usuarioSessao = trim((string)($_SESSION['username'] ?? ''));
    $nivelAcesso = '';
    $acessoAdicionalRaw = '';
    $funcionarioIdAtual = (int)($_SESSION['funcionario_id'] ?? 0);

    if ($usuarioSessao !== '') {
      try {
        $stUser = $conn->prepare("SELECT id, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ? LIMIT 1");
        $stUser->execute([$usuarioSessao]);
        if ($rowU = $stUser->fetch(PDO::FETCH_ASSOC)) {
          $funcionarioIdAtual = (int)$rowU['id'];
          $nivelAcesso        = (string)($rowU['nivel_de_acesso'] ?? '');
          $acessoAdicionalRaw = (string)($rowU['acesso_adicional'] ?? '');
          // Cache leve em sessão
          $_SESSION['funcionario_id']    = $funcionarioIdAtual;
          $_SESSION['nivel_de_acesso']   = $nivelAcesso;
          $_SESSION['acesso_adicional']  = $acessoAdicionalRaw;
        }
      } catch (Throwable $e) {
        // se falhar, cai no fallback da sessão
      }
    }

    // 2) Fallbacks da sessão caso algo não tenha sido preenchido
    if ($nivelAcesso === '') {
      $nivelAcesso = (string)($_SESSION['nivel_de_acesso'] ?? '');
    }
    if ($acessoAdicionalRaw === '') {
      $acessoAdicionalRaw = $_SESSION['acesso_adicional'] ?? '';
      if (is_array($acessoAdicionalRaw)) {
        $acessoAdicionalRaw = implode(',', $acessoAdicionalRaw);
      } else {
        $acessoAdicionalRaw = (string)$acessoAdicionalRaw;
      }
    }
    if (!$funcionarioIdAtual) {
      $funcionarioIdAtual = isset($_SESSION['id_funcionario']) ? (int)$_SESSION['id_funcionario'] : 0;
    }

    // Normaliza nível
    $nivelAcesso = mb_strtolower(trim($nivelAcesso), 'UTF-8');

    /**
     * Normaliza string para comparação:
     * - trim
     * - lower-case
     * - remove acentos (quando possível)
     * - comprime espaços múltiplos
     */
    $__normalize = function(string $s): string {
      $s = trim($s);
      $s = mb_strtolower($s, 'UTF-8');
      $noAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
      if ($noAccents !== false && $noAccents !== null) {
        $s = $noAccents;
      }
      $s = preg_replace('/\s+/u', ' ', $s);
      return trim($s);
    };

    // Quebra acesso_adicional em tokens (CSV/; /|)
    $tokens = [];
    if (is_array($acessoAdicionalRaw)) {
      $tokens = $acessoAdicionalRaw;
    } elseif (is_string($acessoAdicionalRaw)) {
      // JSON?
      $first = strlen($acessoAdicionalRaw) ? $acessoAdicionalRaw[0] : '';
      if ($first === '[' || $first === '{') {
        $decoded = json_decode($acessoAdicionalRaw, true);
        if (is_array($decoded)) $tokens = array_values((array)$decoded);
      }
      if (!$tokens) {
        $tokens = preg_split('/[,\|;]+/u', $acessoAdicionalRaw) ?: [];
      }
    }

    $tokensNorm = array_map(function($v) use ($__normalize){
      return $__normalize((string)$v);
    }, $tokens);

    $alvo = $__normalize('Controle de Tarefas');
    $temControleTarefas = in_array($alvo, $tokensNorm, true);
    if (!$temControleTarefas && is_string($acessoAdicionalRaw)) {
      $temControleTarefas = (strpos($__normalize($acessoAdicionalRaw), $alvo) !== false);
    }

    // Admin OU quem tem "Controle de Tarefas" enxerga tudo
    $adminOuControle = ($nivelAcesso === 'administrador') || $temControleTarefas;

    if ($action === 'list_equipes') {  
      $st = $conn->query("SELECT id, nome, ativa FROM equipes ORDER BY ativa DESC, nome ASC");  
      J(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
    }  

    if ($action === 'list_funcionarios') {  
      $equipeId = isset($_GET['equipe_id']) ? (int)$_GET['equipe_id'] : 0;  

      // Se NÃO for admin/controle, retorna apenas o próprio usuário
      if (!$adminOuControle) {
        if ($funcionarioIdAtual > 0) {
          $st = $conn->prepare("SELECT id, nome_completo, usuario FROM funcionarios WHERE id = ? LIMIT 1");
          $st->execute([$funcionarioIdAtual]);
          J(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } else {
          J(['success'=>true,'data'=>[]]);
        }
      }

      // Admin/controle: lista normal
      if ($equipeId>0) {  
        $st = $conn->prepare("SELECT f.id, f.nome_completo, f.usuario
                              FROM equipe_membros m
                              JOIN funcionarios f ON f.id = m.funcionario_id
                              WHERE m.equipe_id = ? AND m.ativo=1
                              ORDER BY m.ordem ASC, f.nome_completo ASC");
        $st->execute([$equipeId]);  
        J(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
      } else {  
        $st = $conn->query("SELECT id, nome_completo, usuario FROM funcionarios ORDER BY nome_completo ASC");  
        J(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
      }  
    }

    if ($action === 'counts') {  
      $equipeId = isset($_GET['equipe_id']) ? (int)$_GET['equipe_id'] : 0;  
      $funcId   = isset($_GET['funcionario_id']) ? (int)$_GET['funcionario_id'] : 0;

      // Se não for admin/controle, força contagem apenas das próprias tarefas
      if (!$adminOuControle) {
        $funcId = ($funcionarioIdAtual > 0) ? $funcionarioIdAtual : -1;
        $equipeId = 0; // evita "vazar" contagens por equipe
      }

      $where = [];
      $bind  = [];

      if ($equipeId>0){ $where[]="t.equipe_id=:eq"; $bind[':eq']=$equipeId; }
      if ($funcId>0)  { $where[]="t.funcionario_id=:fu"; $bind[':fu']=$funcId; }

      $di = trim((string)($_GET['data_ini'] ?? ''));
      $df = trim((string)($_GET['data_fim'] ?? ''));
      $pr = trim((string)($_GET['protocolo'] ?? ''));

      if ($di !== '') {
        $where[] = "t.criado_em >= :di";
        $bind[':di'] = $di . ' 00:00:00';
      }
      if ($df !== '') {
        $where[] = "t.criado_em <= :df";
        $bind[':df'] = $df . ' 23:59:59';
      }
      if ($pr !== '') {
        $where[] = "p.protocolo LIKE :pr";
        $bind[':pr'] = '%' . $pr . '%';
      }

      $wsql = $where ? ("WHERE ".implode(" AND ", $where)) : '';

      $sql = "SELECT t.status, COUNT(*) as qtd
          FROM tarefas_pedido t
          LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id
          $wsql
          GROUP BY t.status";
      $st = $conn->prepare($sql);  
      $st->execute($bind);  
      $out = ['pendente'=>0,'em_andamento'=>0,'concluida'=>0,'cancelada'=>0];  
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $out[$r['status']] = (int)$r['qtd']; }  
      J(['success'=>true,'data'=>$out]);  
    }

    if ($action === 'list_tarefas') {  
      $equipeId  = isset($_GET['equipe_id']) ? (int)$_GET['equipe_id'] : 0;
      $funcId    = isset($_GET['funcionario_id']) ? (int)$_GET['funcionario_id'] : 0;
      $status    = trim((string)($_GET['status'] ?? ''));
      $dataIni   = trim((string)($_GET['data_ini'] ?? ''));
      $dataFim   = trim((string)($_GET['data_fim'] ?? ''));
      $protocolo = trim((string)($_GET['protocolo'] ?? ''));

      // Se não for admin/controle, FORÇA listar apenas as tarefas do próprio usuário
      if (!$adminOuControle) {
        $funcId = ($funcionarioIdAtual > 0) ? $funcionarioIdAtual : -1;
        $equipeId = 0; // não deixa filtrar por equipe
      }

      $where = [];
      $bind  = [];

      if ($equipeId>0){ $where[]="t.equipe_id=:eq"; $bind[':eq']=$equipeId; }
      if ($funcId>0)  { $where[]="t.funcionario_id=:fu"; $bind[':fu']=$funcId; }
      if ($status!==''){ $where[]="t.status=:st"; $bind[':st']=$status; }

      if ($dataIni !== '') {
        $where[] = "t.criado_em >= :di";
        $bind[':di'] = $dataIni . ' 00:00:00';
      }
      if ($dataFim !== '') {
        $where[] = "t.criado_em <= :df";
        $bind[':df'] = $dataFim . ' 23:59:59';
      }
      if ($protocolo !== '') {
        $where[] = "p.protocolo LIKE :pr";
        $bind[':pr'] = '%' . $protocolo . '%';
      }

      $wsql = $where ? ("WHERE ".implode(" AND ", $where)) : ""; 

      $sql = "SELECT t.*, f.nome_completo, f.usuario, e.nome AS equipe_nome, p.protocolo  
              FROM tarefas_pedido t  
              LEFT JOIN funcionarios f ON f.id = t.funcionario_id  
              LEFT JOIN equipes e ON e.id = t.equipe_id  
              LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id  
              $wsql  
              ORDER BY   
                  CASE t.status   
                  WHEN 'pendente' THEN 1  
                  WHEN 'em_andamento' THEN 2  
                  WHEN 'concluida' THEN 3  
                  ELSE 4  
                  END,  
                  t.atualizado_em DESC, t.criado_em DESC";  
      $st = $conn->prepare($sql);  
      $st->execute($bind);  
      J(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
    }

    if ($action === 'update_status') {
      $id   = (int)($_POST['id'] ?? 0);
      $novo = trim((string)($_POST['status'] ?? ''));
      $obs  = trim((string)($_POST['observacao'] ?? ''));
      $user = $_SESSION['username'] ?? 'sistema';

      if (!$id || !in_array($novo, ['pendente','em_andamento','concluida','cancelada'], true)) {
        J(['success'=>false,'error'=>'Parâmetros inválidos.']);
      }

      $conn->beginTransaction();

      // Carrega status atual da tarefa + pedido + status da O.S. (com lock)
      $stInfo = $conn->prepare("
        SELECT 
          t.status             AS status_atual,
          p.id                 AS pedido_id,
          p.ordem_servico_id   AS os_id,
          os.status            AS os_status
        FROM tarefas_pedido t
        LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id
        LEFT JOIN ordens_de_servico os ON os.id = p.ordem_servico_id
        WHERE t.id = ?
        FOR UPDATE
      ");
      $stInfo->execute([$id]);
      $row = $stInfo->fetch(PDO::FETCH_ASSOC);

      if (!$row) { 
        $conn->rollBack(); 
        J(['success'=>false,'error'=>'Tarefa não encontrada.']); 
      }

      $ant        = (string)$row['status_atual'];
      $pedidoId   = (int)($row['pedido_id'] ?? 0);
      $osStatus   = $row['os_status'] ?? null;   // pode ser NULL quando isento (sem O.S.)

      // >>> Regra: NÃO permitir iniciar tarefa (em_andamento) se o pedido ainda não tem pagamento liberado na O.S.
      if ($novo === 'em_andamento') {
        // Se não há O.S. (isento), libera.
        $temPagamentoLiberado = true;
        if (!is_null($osStatus)) {
          // normaliza e aceita variações comuns de "pago/quitado/finalizado"
          $norm = mb_strtolower(trim((string)$osStatus), 'UTF-8');

          // Considera pago se conter "pago" (pago, pago parcial), "quit" (quitado/quitada) ou "finaliz" (finalizada/o)
          $temPagamentoLiberado = (
            (strpos($norm, 'pago') !== false) ||
            (strpos($norm, 'quit') !== false) ||
            (strpos($norm, 'finaliz') !== false)
          );
        }

        if (!$temPagamentoLiberado) {
          $conn->rollBack();
          J([
            'success' => false,
            'error'   => 'Não é possível iniciar a tarefa agora: existe pendência de pagamento na O.S. do pedido.'
          ]);
        }
      }

      // Prossegue com a atualização do status da tarefa
      $upd = $conn->prepare("
        UPDATE tarefas_pedido 
          SET status = ?, 
              observacao = IFNULL(NULLIF(?,''), observacao),
              atualizado_em = NOW()
        WHERE id = ?
      ");
      $upd->execute([$novo, $obs, $id]);

      $log = $conn->prepare("
        INSERT INTO tarefas_pedido_log (tarefa_id, acao, de_valor, para_valor, observacao, usuario)
        VALUES (?,?,?,?,?,?)
      ");
      $log->execute([$id, 'status', $ant, $novo, $obs, $user]);

      $conn->commit();
      J(['success'=>true]);
    }


    if ($action === 'reassign') {  
      $id   = (int)$_POST['id'] ?? 0;  
      $func = isset($_POST['funcionario_id']) && $_POST['funcionario_id']!=='' ? (int)$_POST['funcionario_id'] : null;  
      $obs  = trim($_POST['observacao'] ?? '');  
      $user = $_SESSION['username'] ?? 'sistema';  
      if (!$id) J(['success'=>false,'error'=>'ID inválido.']);  

      $conn->beginTransaction();  
      $ant_stmt = $conn->prepare("SELECT funcionario_id FROM tarefas_pedido WHERE id=? FOR UPDATE");  
      $ant_stmt->execute([$id]);  
      $ant = $ant_stmt->fetchColumn();  
      if ($ant===false){ $conn->rollBack(); J(['success'=>false,'error'=>'Tarefa não encontrada.']); }  

      if (!is_null($func)) {  
        // valida existência do funcionário  
        $chk = $conn->prepare("SELECT 1 FROM funcionarios WHERE id=?");  
        $chk->execute([$func]);  
        if (!$chk->fetchColumn()) { $conn->rollBack(); J(['success'=>false,'error'=>'Funcionário inexistente.']); }  
      }  

      $upd = $conn->prepare("UPDATE tarefas_pedido SET funcionario_id=? WHERE id=?");  
      $upd->execute([$func, $id]);  

      $log = $conn->prepare("INSERT INTO tarefas_pedido_log (tarefa_id, acao, de_valor, para_valor, observacao, usuario)  
                             VALUES (?,?,?,?,?,?)");  
      $log->execute([$id,'reatribuicao', (string)($ant ?? ''), (string)($func ?? ''), $obs, $user]);  

      $conn->commit();  
      J(['success'=>true]);  
    }  

    if ($action === 'append_note') {  
      $id   = (int)$_POST['id'] ?? 0;  
      $note = trim($_POST['observacao'] ?? '');  
      $user = $_SESSION['username'] ?? 'sistema';  
      if (!$id || $note===''){ J(['success'=>false,'error'=>'Informe a observação.']); }  

      $conn->beginTransaction();  
      $upd = $conn->prepare("UPDATE tarefas_pedido SET observacao = CONCAT(COALESCE(observacao,''), CASE WHEN COALESCE(observacao,'')='' THEN '' ELSE ' | ' END, ?) WHERE id=?");  
      $upd->execute([$note, $id]);  

      $log = $conn->prepare("INSERT INTO tarefas_pedido_log (tarefa_id, acao, observacao, usuario)  
                             VALUES (?,?,?,?)");  
      $log->execute([$id,'observacao',$note,$user]);  

      $conn->commit();  
      J(['success'=>true]);  
    }  

    if ($action === 'get_log') {  
      $id = (int)$_GET['id'] ?? 0;  
      $st = $conn->prepare("SELECT * FROM tarefas_pedido_log WHERE tarefa_id=? ORDER BY id DESC");  
      $st->execute([$id]);  
      J(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);  
    }  

    if ($action === 'get_tarefa') {  
        $id = (int)$_GET['id'] ?? 0;  
        $st = $conn->prepare("SELECT t.*, f.nome_completo, f.usuario, e.nome AS equipe_nome, p.protocolo  
                                FROM tarefas_pedido t  
                                LEFT JOIN funcionarios f ON f.id = t.funcionario_id  
                                LEFT JOIN equipes e ON e.id = t.equipe_id  
                                LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id  
                                WHERE t.id=?");  
        $st->execute([$id]);  
        $row = $st->fetch(PDO::FETCH_ASSOC);  
        if (!$row) J(['success'=>false,'error'=>'Tarefa não encontrada.']);  
        J(['success'=>true,'data'=>$row]);  
        }

        // ===================== NOTIFICAÇÃO: Novas tarefas atribuídas =====================
        if ($action === 'notify_new_assignments') {
        // Garante que identificamos o funcionário da sessão (mesma lógica já usada acima)
        $usuarioSessao = trim((string)($_SESSION['username'] ?? ''));
        $funcionarioId = (int)($_SESSION['funcionario_id'] ?? 0);
        if (!$funcionarioId && $usuarioSessao !== '') {
            $stUserFunc = $conn->prepare("SELECT id FROM funcionarios WHERE usuario = ? LIMIT 1");
            $stUserFunc->execute([$usuarioSessao]);
            $funcionarioId = (int)$stUserFunc->fetchColumn();
            if ($funcionarioId) $_SESSION['funcionario_id'] = $funcionarioId;
        }

        if ($funcionarioId <= 0) {
            J(['success'=>true, 'count'=>0, 'latest_ts'=>null, 'items'=>[]]);
        }

        // 'since' pode vir como ISO (ex.: 2025-01-30T10:00:00Z) ou timestamp numérico (ms/s)
        $sinceRaw = trim((string)($_GET['since'] ?? $_POST['since'] ?? ''));
        $sinceSql = '1970-01-01 00:00:00';

        if ($sinceRaw !== '') {
            // tenta normalizar
            if (ctype_digit($sinceRaw)) {
            // epoch (ms ou s)
            if (strlen($sinceRaw) > 10) { // ms
                $ts = (int)floor(((int)$sinceRaw)/1000);
            } else { // s
                $ts = (int)$sinceRaw;
            }
            $sinceSql = date('Y-m-d H:i:s', $ts);
            } else {
            $ts = strtotime($sinceRaw);
            if ($ts !== false) $sinceSql = date('Y-m-d H:i:s', $ts);
            }
        }

        // Busca últimas 10 tarefas atribuídas ao usuário desde 'since'
        $st = $conn->prepare("
            SELECT t.id, t.pedido_id, t.criado_em, p.protocolo
            FROM tarefas_pedido t
            LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id
            WHERE t.funcionario_id = ?
            AND t.criado_em > ?
            ORDER BY t.criado_em DESC
            LIMIT 10
        ");
        $st->execute([$funcionarioId, $sinceSql]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $count = count($rows);
        $latest = null;
        if ($count > 0) {
            // pega o mais recente para avançar o ponteiro do cliente
            $latest = $rows[0]['criado_em'];
        }

        J([
            'success'   => true,
            'count'     => $count,
            'latest_ts' => $latest, // formato 'Y-m-d H:i:s' (servidor)
            'items'     => $rows
        ]);
    }

    J(['success'=>false,'error'=>'Ação inválida.']);  
  
  } catch (Throwable $e) {  
    J(['success'=>false,'error'=>'Falha: '.$e->getMessage()]);  
  }  
  exit;  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
<meta charset="utf-8">  
<meta name="viewport" content="width=device-width, initial-scale=1">  
<title>Fila de Tarefas — Pedidos</title>  

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
  
  /* Status Colors */  
  --status-pendente: #0ea5e9;  
  --status-pendente-bg: rgba(14, 165, 233, 0.12);  
  --status-em-andamento: #f59e0b;  
  --status-em-andamento-bg: rgba(245, 158, 11, 0.12);  
  --status-concluida: #10b981;  
  --status-concluida-bg: rgba(16, 185, 129, 0.12);  
  --status-cancelada: #ef4444;  
  --status-cancelada-bg: rgba(239, 68, 68, 0.12);  
  
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
.filter-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-lg);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.2s backwards;  
}  

@keyframes fadeIn {  
  from { opacity: 0; }  
  to { opacity: 1; }  
}  

.filter-title {  
  font-size: 18px;  
  font-weight: 700;  
  margin-bottom: var(--space-lg);  
  color: var(--text-primary);  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.filter-title i {  
  font-size: 22px;  
  color: var(--brand-primary);  
}  

/* ===================== FORM ELEMENTS ===================== */  
.form-label {  
  font-size: 13px;  
  font-weight: 700;  
  color: var(--text-secondary) !important;  
  margin-bottom: var(--space-sm);  
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
  padding: 12px 16px;  
  font-size: 15px;  
  color: var(--text-primary) !important;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  font-weight: 500;  
  font-family: var(--font-primary);  
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

/* .form-select {  
  cursor: pointer;  
  appearance: none;  
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236366f1'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") !important;  
  background-repeat: no-repeat;  
  background-position: right 12px center;  
  background-size: 24px;  
  padding-right: 40px;  
}   */

.form-select option {  
  background: var(--bg-tertiary);  
  color: var(--text-primary);  
  padding: 8px;  
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

.btn-group .btn {  
  border-radius: var(--radius-sm) !important;  
}  

.btn:active {  
  transform: translateY(0) !important;  
}  

.btn:disabled {  
  opacity: 0.6;  
  cursor: not-allowed;  
  transform: none !important;  
}  

/* ===================== STATS CARDS ===================== */  
.stats-grid {  
  display: grid;  
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));  
  gap: var(--space-md);  
  margin-bottom: var(--space-xl);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s backwards;  
}  

.stat-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-lg);  
  padding: var(--space-lg);  
  box-shadow: var(--shadow-md);  
  transition: all 0.3s ease;  
  position: relative;  
  overflow: hidden;  
}  

.stat-card::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 4px;  
  background: currentColor;  
}  

.stat-card:hover {  
  transform: translateY(-4px);  
  box-shadow: var(--shadow-lg);  
}  

.stat-card.pendente {  
  color: var(--status-em-andamento);  
}  

.stat-card.pendente::before {  
    background: var(--status-em-andamento);
}  

.stat-card.em-andamento {  
  color: var(--status-pendente);  

}  

.stat-card.em-andamento::before {  
   background: var(--status-pendente); 
  
}  

.stat-card.concluida {  
  color: var(--status-concluida);  
}  

.stat-card.concluida::before {  
  background: var(--status-concluida);  
}  

.stat-card.cancelada {  
  color: var(--status-cancelada);  
}  

.stat-card.cancelada::before {  
  background: var(--status-cancelada);  
}  

.stat-label {  
  font-size: 13px;  
  font-weight: 700;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  margin-bottom: var(--space-sm);  
  color: currentColor;  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.stat-label i {  
  font-size: 16px;  
}  

.stat-value {  
  font-size: 36px;  
  font-weight: 900;  
  color: var(--text-primary);  
  line-height: 1;  
}  

/* ===================== KANBAN ===================== */  
.kanban {  
  display: grid;  
  grid-template-columns: repeat(4, minmax(0, 1fr));  
  gap: var(--space-md);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.4s backwards;  
}  

.kanban-col {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 2px dashed var(--border-secondary);  
  border-radius: var(--radius-lg);  
  padding: var(--space-md);  
  min-height: 500px;  
  box-shadow: var(--shadow-md);  
  transition: all 0.3s ease;  
}  

.kanban-col.ui-sortable-hover {  
  border-color: var(--brand-primary);  
  box-shadow: var(--shadow-lg);  
  background: var(--surface-hover);  
}  

.kanban-col-header {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  margin-bottom: var(--space-md);  
  padding-bottom: var(--space-sm);  
  border-bottom: 2px solid var(--border-primary);  
}  

.kanban-col-title {  
  font-size: 16px;  
  font-weight: 800;  
  letter-spacing: 0.02em;  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.kanban-col-title i {  
  font-size: 18px;  
}  

.kanban-col-count {  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  min-width: 28px;  
  height: 28px;  
  padding: 0 8px;  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-full);  
  font-size: 13px;  
  font-weight: 800;  
  color: var(--text-primary);  
}  

.col-pendente .kanban-col-title {  
 color: var(--status-em-andamento);   
}  

.col-em-andamento .kanban-col-title {  
   color: var(--status-pendente); 
}  

.col-concluida .kanban-col-title {  
  color: var(--status-concluida);  
}  

.col-cancelada .kanban-col-title {  
  color: var(--status-cancelada);  
}  

.col-body {  
  display: flex;  
  flex-direction: column;  
  gap: var(--space-sm);  
}  

/* ===================== TASK CARDS ===================== */  
.task {  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  box-shadow: var(--shadow-sm);  
  transition: all 0.25s ease;  
  cursor: grab;  
  animation: taskSlideIn 0.35s ease both;  
}  

@keyframes taskSlideIn {  
  from {  
    opacity: 0;  
    transform: translateY(20px);  
  }  
  to {  
    opacity: 1;  
    transform: translateY(0);  
  }  
}  

.task:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  border-color: var(--brand-primary);  
}  

.task:active {  
  cursor: grabbing;  
}  

.task.ui-sortable-helper {  
  transform: rotate(3deg);  
  box-shadow: var(--shadow-xl);  
  opacity: 0.95;  
}  

.ui-state-highlight {  
  background: var(--status-pendente-bg);  
  border: 2px dashed var(--brand-primary);  
  border-radius: var(--radius-md);  
  height: 120px;  
  margin-bottom: var(--space-sm);  
}  

.task-header {  
  display: flex;  
  align-items: flex-start;  
  justify-content: space-between;  
  gap: var(--space-sm);  
  margin-bottom: var(--space-sm);  
}  

.task-title {  
  flex: 1;  
  font-weight: 700;  
  font-size: 15px;  
  color: var(--text-primary);  
}  

.task-title a {  
  color: var(--brand-primary);  
  text-decoration: none;  
  transition: color 0.2s ease;  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.task-title a:hover {  
  color: var(--brand-primary-light);  
  text-decoration: underline;  
}  

.task-title i {  
  font-size: 14px;  
}  

.task-protocol {  
  font-size: 13px;  
  color: var(--text-tertiary);  
  margin-top: 4px;  
}  

.task-meta {  
  display: flex;  
  flex-direction: column;  
  gap: 4px;  
  margin-bottom: var(--space-sm);  
  padding-bottom: var(--space-sm);  
  border-bottom: 1px solid var(--border-primary);  
}  

.task-meta-item {  
  font-size: 13px;  
  color: var(--text-secondary);  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.task-meta-item i {  
  font-size: 14px;  
  color: var(--brand-primary);  
  width: 16px;  
  text-align: center;  
}  

.task-meta-item strong {  
  color: var(--text-primary);  
}  

.task-obs {  
  background: var(--bg-secondary);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-sm);  
  padding: var(--space-sm);  
  margin-bottom: var(--space-sm);  
  font-size: 13px;  
  color: var(--text-secondary);  
  line-height: 1.5;  
}  

.task-obs i {  
  color: var(--brand-warning);  
  margin-right: 4px;  
}  

.task-actions {  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  gap: var(--space-sm);  
  flex-wrap: wrap;  
}  

.task-actions .btn-group {  
  flex: 1;  
  justify-content: flex-start;  
}  

/* ===================== BADGES ===================== */  
.badge {  
  display: inline-flex;  
  align-items: center;  
  gap: 4px;  
  padding: 4px 10px;  
  border-radius: var(--radius-full);  
  font-size: 11px;  
  font-weight: 700;  
  letter-spacing: 0.02em;  
}  

.badge i {  
  font-size: 12px;  
}  

.badge-success {  
  background: rgba(16, 185, 129, 0.15);  
  color: var(--brand-success);  
  border: 1.5px solid rgba(16, 185, 129, 0.3);  
}  

.badge-warning {  
  background: rgba(245, 158, 11, 0.15);  
  color: var(--brand-warning);  
  border: 1.5px solid rgba(245, 158, 11, 0.3);  
}  

.badge-danger {  
  background: rgba(239, 68, 68, 0.15);  
  color: var(--brand-error);  
  border: 1.5px solid rgba(239, 68, 68, 0.3);  
}  

.badge-info {  
  background: rgba(6, 182, 212, 0.15);  
  color: var(--brand-info);  
  border: 1.5px solid rgba(6, 182, 212, 0.3);  
}  

.badge-secondary {  
  background: rgba(107, 114, 128, 0.15);  
  color: var(--text-secondary);  
  border: 1.5px solid rgba(107, 114, 128, 0.3);  
}  

/* ===================== MODALS ===================== */  
.modal-content {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(32px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  box-shadow: var(--shadow-2xl);  
  color: var(--text-primary);  
}  

.modal-header {  
  background: var(--bg-secondary);  
  border-bottom: 2px solid var(--border-primary);  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;  
  padding: var(--space-lg);  
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
  color: var(--text-primary);  
  margin: 0;  
}  

.modal-body {  
  padding: var(--space-xl);  
}  

.modal-footer {  
  background: var(--bg-secondary);  
  border-top: 2px solid var(--border-primary);  
  border-radius: 0 0 var(--radius-xl) var(--radius-xl);  
  padding: var(--space-lg);  
  display: flex;  
  justify-content: flex-end;  
  gap: var(--space-sm);  
}  

.btn-close {  
  filter: brightness(0) invert(1);  
  opacity: 0.7;  
  transition: opacity 0.2s ease;  
}  

.btn-close:hover {  
  opacity: 1;  
}  

.dark-mode .btn-close {  
  filter: brightness(1) invert(0);  
}  

.modal-backdrop {  
  background: rgba(0, 0, 0, 0.75);  
  backdrop-filter: blur(8px);  
}  

.dark-mode .modal-backdrop {  
  background: rgba(0, 0, 0, 0.85);  
}  

/* ===================== TABLE ===================== */  
.table {  
  color: var(--text-primary) !important;  
  margin-bottom: 0;  
}  

.table thead th {  
  background: var(--bg-secondary) !important;  
  color: var(--text-primary) !important;  
  font-weight: 700;  
  font-size: 13px;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  padding: 12px;  
  border-bottom: 2px solid var(--border-primary) !important;  
  border-top: none !important;  
}  

.table tbody td {  
  padding: 12px;  
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

.table-responsive {  
  border-radius: var(--radius-md);  
  overflow: hidden;  
  background: var(--bg-tertiary);  
  border: 1px solid var(--border-primary);  
}  

/* ===================== LOADING & EMPTY STATES ===================== */  
.text-center {  
  text-align: center;  
}  

.text-secondary {  
  color: var(--text-secondary) !important;  
}  

.text-danger {  
  color: var(--brand-error) !important;  
}  

.text-info {  
  color: var(--brand-info) !important;  
}  

.text-warning {  
  color: var(--brand-warning) !important;  
}  

.text-success {  
  color: var(--brand-success) !important;  
}  

.py-2 {  
  padding-top: var(--space-sm);  
  padding-bottom: var(--space-sm);  
}  

.py-3 {  
  padding-top: var(--space-md);  
  padding-bottom: var(--space-md);  
}  

.mb-0 { margin-bottom: 0 !important; }  
.mb-1 { margin-bottom: 0.25rem !important; }  
.mb-2 { margin-bottom: 0.5rem !important; }  
.mb-3 { margin-bottom: 1rem !important; }  

/* ===================== RESPONSIVE ===================== */  
@media (max-width: 1200px) {  
  .kanban {  
    grid-template-columns: repeat(2, minmax(0, 1fr));  
  }  
}  

@media (max-width: 768px) {  
  .kanban {  
    grid-template-columns: 1fr;  
  }  
  
  .page-title-row {  
    flex-direction: column;  
    align-items: flex-start;  
  }  
  
  .page-title-left {  
    width: 100%;  
  }  
  
  .stats-grid {  
    grid-template-columns: repeat(2, minmax(0, 1fr));  
  }  
  
  .task-actions {  
    flex-direction: column;  
    align-items: stretch;  
  }  
  
  .task-actions .btn-group {  
    width: 100%;  
    justify-content: center;  
  }  
  
  .btn {  
    width: 100%;  
    justify-content: center;  
  }  
}  

@media (max-width: 576px) {  
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
  
  .stats-grid {  
    grid-template-columns: 1fr;  
  }  
  
  .stat-value {  
    font-size: 28px;  
  }  
  
  .filter-card {  
    padding: var(--space-lg);  
  }  
}  

/* ===================== UTILITIES ===================== */  
.d-flex {  
  display: flex !important;  
}  

.align-items-center {  
  align-items: center !important;  
}  

.justify-content-between {  
  justify-content: space-between !important;  
}  

.gap-2 {  
  gap: var(--space-sm);  
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

/* ===== SLA (atraso) ===== */
.task.sla-warn {
  border-color: rgba(245, 158, 11, 0.6) !important;            /* alaranjado */
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15), var(--shadow-sm);
  background-image: linear-gradient(0deg, rgba(245, 158, 11, 0.08), transparent);
}

.task.sla-late {
  border-color: rgba(239, 68, 68, 0.7) !important;             /* vermelho */
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15), var(--shadow-sm);
  background-image: linear-gradient(0deg, rgba(239, 68, 68, 0.08), transparent);
}

</style>  
</head>  
<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<main class="main-content">  
  <div class="container py-3">  

    <!-- PAGE HERO -->  
    <section class="page-hero">  
      <div class="page-title-row">  
        <div class="page-title-left">  
          <div class="title-icon">  
            <i class="fas fa-tasks"></i>  
          </div>  
          <div class="page-title-text">  
            <h2>Fila de Tarefas</h2>  
            <p class="page-subtitle">  
              Gerencie e organize as tarefas dos pedidos. Arraste os cards entre colunas para atualizar o status.  
            </p>  
          </div>  
        </div>  
        <div>  
          <a href="equipes.php" class="btn btn-outline-light">  
            <i class="fas fa-users"></i> Gerenciar Equipes  
          </a>  
        </div>  
      </div>  
    </section>  

    <!-- FILTROS -->  
    <div class="filter-card">  
      <div class="filter-title">  
        <i class="fas fa-filter"></i> Filtros de Pesquisa  
      </div>  
      <form id="filtros">  
        <div class="row g-3">  
          <div class="col-md-3">  
            <label class="form-label">  
              <i class="fas fa-users"></i> Equipe  
            </label>  
            <select class="form-select" id="f_equipe">  
              <option value="">Todas as Equipes</option>  
            </select>  
          </div>  
          
          <div class="col-md-3">  
            <label class="form-label">  
              <i class="fas fa-user"></i> Funcionário  
            </label>  
            <select class="form-select" id="f_func">  
              <option value="">Todos os Funcionários</option>  
            </select>  
          </div>  
          
          <div class="col-md-2">  
            <label class="form-label">  
              <i class="fas fa-flag"></i> Status  
            </label>  
            <select class="form-select" id="f_status">  
              <option value="">Todos os Status</option>  
              <option value="pendente">Pendente</option>  
              <option value="em_andamento">Em Andamento</option>  
              <option value="concluida">Concluída</option>  
              <option value="cancelada">Cancelada</option>  
            </select>  
          </div>  
          
          <!-- Período (Data Inicial / Final) -->
          <div class="col-md-4">
            <label class="form-label">
              <i class="fas fa-calendar-alt"></i> Período (Criação da Tarefa)
            </label>
            <div class="d-flex gap-2">
              <input type="date" class="form-control" id="f_data_ini" placeholder="Início">
              <input type="date" class="form-control" id="f_data_fim" placeholder="Fim">
            </div>
          </div>

          <!-- Protocolo -->
          <div class="col-md-3">
            <label class="form-label">
              <i class="fas fa-file-alt"></i> Protocolo
            </label>
            <input type="text" class="form-control" id="f_protocolo" placeholder="Ex.: 12345/2025">
          </div>
  
        </div>  
        
        <div class="d-flex justify-content-end gap-2" style="margin-top: var(--space-lg);">  
          <button class="btn btn-secondary" type="button" id="btnLimpar">  
            <i class="fas fa-eraser"></i> Limpar Filtros  
          </button>  
          <button class="btn btn-primary" type="submit">  
            <i class="fas fa-search"></i> Aplicar Filtros  
          </button>  
        </div>  
      </form>  
    </div>  

    <!-- STATS CARDS -->  
    <div class="stats-grid" id="cardsCount">  
      <div class="stat-card pendente">  
        <div class="stat-label">  
          <i class="fas fa-clock"></i> Pendentes  
        </div>  
        <div class="stat-value" id="c_pendente">0</div>  
      </div>  
      
      <div class="stat-card em-andamento">  
        <div class="stat-label">  
          <i class="fas fa-spinner"></i> Em Andamento  
        </div>  
        <div class="stat-value" id="c_em_andamento">0</div>  
      </div>  
      
      <div class="stat-card concluida">  
        <div class="stat-label">  
          <i class="fas fa-check-circle"></i> Concluídas  
        </div>  
        <div class="stat-value" id="c_concluida">0</div>  
      </div>  
      
      <div class="stat-card cancelada">  
        <div class="stat-label">  
          <i class="fas fa-times-circle"></i> Canceladas  
        </div>  
        <div class="stat-value" id="c_cancelada">0</div>  
      </div>  
    </div>  

    <!-- KANBAN BOARD -->  
    <div class="kanban" id="kanban">  
      <!-- Coluna Pendente -->  
      <div class="kanban-col col-pendente" data-status="pendente">  
        <div class="kanban-col-header">  
          <div class="kanban-col-title">  
            <i class="fas fa-clock"></i> Pendentes  
          </div>  
          <span class="kanban-col-count" id="count_pendente">0</span>  
        </div>  
        <div class="col-body" id="col_pendente"></div>  
      </div>  
      
      <!-- Coluna Em Andamento -->  
      <div class="kanban-col col-em-andamento" data-status="em_andamento">  
        <div class="kanban-col-header">  
          <div class="kanban-col-title">  
            <i class="fas fa-spinner"></i> Em Andamento  
          </div>  
          <span class="kanban-col-count" id="count_em_andamento">0</span>  
        </div>  
        <div class="col-body" id="col_em_andamento"></div>  
      </div>  
      
      <!-- Coluna Concluída -->  
      <div class="kanban-col col-concluida" data-status="concluida">  
        <div class="kanban-col-header">  
          <div class="kanban-col-title">  
            <i class="fas fa-check-circle"></i> Concluídas  
          </div>  
          <span class="kanban-col-count" id="count_concluida">0</span>  
        </div>  
        <div class="col-body" id="col_concluida"></div>  
      </div>  
      
      <!-- Coluna Cancelada -->  
      <div class="kanban-col col-cancelada" data-status="cancelada">  
        <div class="kanban-col-header">  
          <div class="kanban-col-title">  
            <i class="fas fa-times-circle"></i> Canceladas  
          </div>  
          <span class="kanban-col-count" id="count_cancelada">0</span>  
        </div>  
        <div class="col-body" id="col_cancelada"></div>  
      </div>  
    </div>  

  </div>  
</main>  

<!-- Modal Reatribuir -->
<div class="modal fade" id="modalReatribuir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formReatribuir">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-exchange-alt"></i> Reatribuir Tarefa
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rea_tarefa_id">
        
        <div class="mb-3">
          <label class="form-label">
            <i class="fas fa-user"></i> Novo Responsável
          </label>
          <select class="form-select" id="rea_func" required>
            <option value="">Selecione um funcionário</option>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label">
            <i class="fas fa-comment"></i> Observação (Opcional)
          </label>
          <textarea class="form-control" id="rea_obs" rows="3" placeholder="Adicione uma observação sobre a reatribuição..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
          <i class="fas fa-times"></i> Cancelar
        </button>
        <button class="btn btn-primary" type="submit">
          <i class="fas fa-check"></i> Confirmar Reatribuição
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Log -->
<div class="modal fade" id="modalLog" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-history"></i> Histórico da Tarefa
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" id="logBody">
        <div class="text-center py-3">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
          </div>
          <p class="text-secondary mt-2">Carregando histórico...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
          <i class="fas fa-times"></i> Fechar
        </button>
      </div>
    </div>
  </div>
</div>

<?php include(__DIR__ . '/../rodape.php'); ?>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery-ui.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
// ===================== VARIÁVEIS GLOBAIS =====================
let MODAL_REA = null;
let MODAL_LOG = null;

// ===================== INICIALIZAÇÃO =====================
$(function(){
  // Carrega tema
  $.get('../load_mode.php', function(mode){
    $('body').removeClass('light-mode dark-mode').addClass(mode);
  });

  // Inicializa modais
  MODAL_REA = new bootstrap.Modal(document.getElementById('modalReatribuir'));
  MODAL_LOG = new bootstrap.Modal(document.getElementById('modalLog'));

  // Carrega dados iniciais
  carregarEquipes();
  bindFiltros();
  carregarContadores();
  carregarKanban();

  // Configura sortable nas colunas do Kanban
  initializeSortable();

  // Refresh automático dos contadores
  setInterval(carregarContadores, 30000); // a cada 30 segundos
});

// ===================== SORTABLE (DRAG & DROP) =====================
function initializeSortable() {
  $(".kanban .col-body").sortable({
    connectWith: ".kanban .col-body",
    placeholder: "ui-state-highlight",
    tolerance: "pointer",
    cursor: "grabbing",
    opacity: 0.8,
    revert: 200,
    start: function(event, ui) {
      ui.placeholder.height(ui.item.height());
      ui.item.addClass('ui-sortable-helper');
    },
    stop: function(event, ui) {
      ui.item.removeClass('ui-sortable-helper');
    },
    receive: function(event, ui) {
      const $card = $(ui.item);
      const id = $card.data('id');
      const newStatus = $(this).closest('.kanban-col').data('status');
      
      // Confirma mudança de status
      const statusNames = {
        'pendente': 'Pendente',
        'em_andamento': 'Em Andamento',
        'concluida': 'Concluída',
        'cancelada': 'Cancelada'
      };
      
      Swal.fire({
        title: 'Confirmar Mudança?',
        html: `Alterar status da tarefa para <strong>${statusNames[newStatus]}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-check"></i> Sim, alterar',
        cancelButtonText: '<i class="fas fa-times"></i> Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          mudarStatus(id, newStatus, '');
        } else {
          // Reverte a mudança
          carregarKanban();
        }
      });
    }
  }).disableSelection();
}

// ===================== BIND FILTROS =====================
function bindFiltros() {
  $("#filtros").on('submit', function(e) {
    e.preventDefault();
    carregarContadores();
    carregarKanban();
  });

  $("#btnLimpar").on('click', function() {
    $('#f_equipe').val('');
    $('#f_func').empty().append('<option value="">Todos os Funcionários</option>');
    $('#f_status').val('');
    $('#f_data_ini').val('');
    $('#f_data_fim').val('');
    $('#f_protocolo').val('');
    carregarContadores();
    carregarKanban();
  });

  $("#f_equipe").on('change', function() {
    carregarFuncionarios($(this).val());
  });
}

// ===================== CARREGAR EQUIPES =====================
function carregarEquipes() {
  $.getJSON('tarefas.php', { action: 'list_equipes' }, function(r) {
    const $sel = $('#f_equipe').empty().append('<option value="">Todas as Equipes</option>');
    if (r.success) {
      r.data.forEach(e => {
        const label = e.nome + (e.ativa ? '' : ' (inativa)');
        $sel.append(new Option(label, e.id));
      });
    }
  });
}

// ===================== CARREGAR FUNCIONÁRIOS =====================
function carregarFuncionarios(equipeId) {
  $.getJSON('tarefas.php', { 
    action: 'list_funcionarios', 
    equipe_id: equipeId || '' 
  }, function(r) {
    const $sel = $('#f_func').empty().append('<option value="">Todos os Funcionários</option>');
    if (r.success) {
      r.data.forEach(f => {
        const label = `${f.nome_completo} (${f.usuario})`;
        $sel.append(new Option(label, f.id));
      });
    }
  });
}

// ===================== PARÂMETROS DE FILTRO =====================
function paramsFiltro() {
  return {
    action: 'list_tarefas',
    equipe_id: $('#f_equipe').val(),
    funcionario_id: $('#f_func').val(),
    status: $('#f_status').val(),
    data_ini: $('#f_data_ini').val(),      // YYYY-MM-DD
    data_fim: $('#f_data_fim').val(),      // YYYY-MM-DD
    protocolo: $('#f_protocolo').val()
  };
}

// ===================== CARREGAR CONTADORES =====================
function carregarContadores() {
  const p = {
    action: 'counts',
    equipe_id: $('#f_equipe').val(),
    funcionario_id: $('#f_func').val(),
    data_ini: $('#f_data_ini').val(),
    data_fim: $('#f_data_fim').val(),
    protocolo: $('#f_protocolo').val()
  };

  $.getJSON('tarefas.php', p, function(r) {
    if (!r.success) return;

    // Atualiza contadores principais
    $('#c_pendente').text(r.data.pendente || 0);
    $('#c_em_andamento').text(r.data.em_andamento || 0);
    $('#c_concluida').text(r.data.concluida || 0);
    $('#c_cancelada').text(r.data.cancelada || 0);

    // Atualiza badges das colunas
    $('#count_pendente').text(r.data.pendente || 0);
    $('#count_em_andamento').text(r.data.em_andamento || 0);
    $('#count_concluida').text(r.data.concluida || 0);
    $('#count_cancelada').text(r.data.cancelada || 0);
  });
}

// ===================== CARREGAR KANBAN =====================
function carregarKanban() {
  const cols = {
    pendente: $('#col_pendente'),
    em_andamento: $('#col_em_andamento'),
    concluida: $('#col_concluida'),
    cancelada: $('#col_cancelada')
  };

  // Mostra loading em todas as colunas
  Object.values(cols).forEach($c => {
    $c.empty().append(`
      <div class="text-center text-secondary py-3">
        <div class="spinner-border spinner-border-sm" role="status">
          <span class="visually-hidden">Carregando...</span>
        </div>
        <p class="mt-2 mb-0" style="font-size: 13px;">Carregando tarefas...</p>
      </div>
    `);
  });

  $.getJSON('tarefas.php', paramsFiltro(), function(r) {
    // Limpa todas as colunas
    Object.values(cols).forEach($c => $c.empty());

    if (!r.success) {
      Object.values(cols).forEach($c => {
        $c.append('<div class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar</div>');
      });
      Swal.fire({
        icon: 'error',
        title: 'Erro ao Carregar',
        text: r.error || 'Falha ao carregar tarefas.',
        confirmButtonColor: '#6366f1'
      });
      return;
    }

    if (!r.data.length) {
      $('#col_pendente').append(`
        <div class="text-center text-secondary py-4">
          <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 12px;"></i>
          <p class="mb-0" style="font-size: 14px;">Nenhuma tarefa encontrada</p>
        </div>
      `);
      return;
    }

    // Renderiza cards nas colunas
    r.data.forEach(t => {
      const $card = $(renderCard(t)).data('id', t.id);
      const $col = cols[t.status] || $('#col_pendente');
      $col.append($card);
    });

    // Reaplica sortable após carregar novos cards
    initializeSortable();
  }).fail(function(xhr, status, error) {
    Object.values(cols).forEach($c => {
      $c.empty().append('<div class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle"></i> Erro de conexão</div>');
    });
    console.error('Erro AJAX:', error);
  });
}

  // ==== Dias úteis entre duas datas (ignora sábados e domingos) ====
  function businessDaysBetween(startDate, endDate) {
    // Normaliza para 00:00
    const start = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
    const end   = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
    if (end < start) return 0;

    let count = 0;
    const cur = new Date(start);
    while (cur <= end) {
      const day = cur.getDay(); // 0=Dom,6=Sáb
      if (day !== 0 && day !== 6) count++;
      cur.setDate(cur.getDate() + 1);
    }
    return count - 1; // se criada hoje, 0 dias úteis decorridos
  }

  // Retorna classe de SLA conforme dias úteis desde a criação
  function slaClassFromCreated(criado_em) {
    if (!criado_em) return '';
    const created = new Date(criado_em);
    const now = new Date();
    const days = businessDaysBetween(created, now);

    // >5 dias úteis = warn (alaranjado), >10 = late (vermelho)
    if (days > 10) return 'sla-late';
    if (days > 5)  return 'sla-warn';
    return '';
  }


// ===================== RENDERIZAR CARD =====================
function renderCard(t) {
  const nome = t.nome_completo 
    ? `<strong>${escapeHtml(t.nome_completo)}</strong> <span class="text-secondary">(${escapeHtml(t.usuario || '')})</span>`
    : '<em class="text-secondary">— Sem responsável —</em>';

  const obs = t.observacao 
    ? `<div class="task-obs"><i class="fas fa-sticky-note"></i> ${escapeHtml(t.observacao)}</div>`
    : '';

  const protocolo = t.protocolo 
    ? `<div class="task-protocol"><i class="fas fa-file-alt"></i> Protocolo: <strong>${escapeHtml(t.protocolo)}</strong></div>`
    : '';

  const url = `visualizar_pedido.php?id=${encodeURIComponent(t.pedido_id)}`;

  // Botões de ação baseados no status
  let actionButtons = '';
  
  if (t.status !== 'em_andamento') {
    actionButtons += `
      <button class="btn btn-sm btn-outline-light" 
              onclick="mudarStatusComConfirmacao(${t.id}, 'em_andamento')" 
              title="Iniciar tarefa">
        <i class="fas fa-play"></i>
      </button>
    `;
  }
  
  if (t.status !== 'concluida') {
    actionButtons += `
      <button class="btn btn-sm btn-outline-light" 
              onclick="mudarStatusComConfirmacao(${t.id}, 'concluida')" 
              title="Marcar como concluída">
        <i class="fas fa-check"></i>
      </button>
    `;
  }
  
  if (t.status !== 'cancelada') {
    actionButtons += `
      <button class="btn btn-sm btn-outline-light" 
              onclick="mudarStatusComConfirmacao(${t.id}, 'cancelada')" 
              title="Cancelar tarefa">
        <i class="fas fa-ban"></i>
      </button>
    `;
  }

  const slaCls = slaClassFromCreated(t.criado_em || t.criado_em); // usa criado_em
  return `
    <div class="task ${slaCls}" id="task_${t.id}">
      <div class="task-header">
        <div class="task-title">
          <a href="${url}" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-external-link-alt"></i> Pedido #${t.pedido_id}
          </a>
          ${protocolo}
        </div>
      </div>

      <div class="task-meta">
        <div class="task-meta-item">
          <i class="fas fa-users"></i>
          <span>Equipe: <strong>${escapeHtml(t.equipe_nome || 'N/A')}</strong></span>
        </div>
        <div class="task-meta-item">
          <i class="fas fa-user"></i>
          <span>Resp.: ${nome}</span>
        </div>
        <div class="task-meta-item">
          <i class="fas fa-calendar"></i>
          <span>Criado: <strong>${formatarData(t.criado_em)}</strong></span>
        </div>
        ${t.atualizado_em ? `
          <div class="task-meta-item">
            <i class="fas fa-sync"></i>
            <span>Atualizado: <strong>${formatarData(t.atualizado_em)}</strong></span>
          </div>
        ` : ''}
      </div>

      ${obs}

      <div class="task-actions">
        <div class="btn-group">
          ${actionButtons}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-outline-light" 
                  onclick="adicionarObs(${t.id})" 
                  title="Adicionar observação">
            <i class="fas fa-comment"></i>
          </button>
          <button class="btn btn-sm btn-outline-light" 
                  onclick="openReatribuir(${t.id}, ${t.equipe_id})" 
                  title="Reatribuir tarefa">
            <i class="fas fa-exchange-alt"></i>
          </button>
          <button class="btn btn-sm btn-outline-light" 
                  onclick="openLog(${t.id})" 
                  title="Ver histórico">
            <i class="fas fa-history"></i>
          </button>
        </div>
      </div>
    </div>
  `;
}

// ===================== MUDAR STATUS COM CONFIRMAÇÃO =====================
function mudarStatusComConfirmacao(id, novoStatus) {
  const statusNames = {
    'pendente': 'Pendente',
    'em_andamento': 'Em Andamento',
    'concluida': 'Concluída',
    'cancelada': 'Cancelada'
  };

  const statusIcons = {
    'pendente': 'clock',
    'em_andamento': 'spinner',
    'concluida': 'check-circle',
    'cancelada': 'times-circle'
  };

  Swal.fire({
    title: 'Confirmar Mudança?',
    html: `
      <div style="text-align: center;">
        <i class="fas fa-${statusIcons[novoStatus]}" style="font-size: 48px; color: var(--brand-primary); margin-bottom: 16px;"></i>
        <p>Alterar status da tarefa para <strong>${statusNames[novoStatus]}</strong>?</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#6366f1',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<i class="fas fa-check"></i> Sim, alterar',
    cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
    input: 'textarea',
    inputPlaceholder: 'Observação (opcional)',
    inputAttributes: {
      'aria-label': 'Adicione uma observação'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      mudarStatus(id, novoStatus, result.value || '');
    }
  });
}

// ===================== MUDAR STATUS =====================
function mudarStatus(id, novo, obs) {
  $.ajax({
    url: 'tarefas.php',
    method: 'POST',
    data: {
      action: 'update_status',
      id: id,
      status: novo,
      observacao: obs || ''
    },
    dataType: 'json',
    success: function(r) {
      if (!r.success) {
        Swal.fire({
          icon: 'error',
          title: 'Erro ao Atualizar',
          text: r.error || 'Falha ao mudar status da tarefa.',
          confirmButtonColor: '#6366f1'
        });
        carregarKanban();
        return;
      }

      Swal.fire({
        icon: 'success',
        title: 'Status Atualizado!',
        text: 'A tarefa foi atualizada com sucesso.',
        timer: 2000,
        showConfirmButton: false
      });

      carregarContadores();
      carregarKanban();
    },
    error: function(xhr, status, error) {
      console.error('Erro AJAX:', error);
      Swal.fire({
        icon: 'error',
        title: 'Erro de Conexão',
        text: 'Não foi possível comunicar com o servidor.',
        confirmButtonColor: '#6366f1'
      });
      carregarKanban();
    }
  });
}

// ===================== ADICIONAR OBSERVAÇÃO =====================
function adicionarObs(id) {
  Swal.fire({
    title: 'Adicionar Observação',
    html: '<i class="fas fa-comment" style="font-size: 48px; color: var(--brand-primary); margin-bottom: 16px;"></i>',
    input: 'textarea',
    inputPlaceholder: 'Digite sua observação aqui...',
    inputAttributes: {
      'aria-label': 'Observação',
      'rows': 4
    },
    showCancelButton: true,
    confirmButtonColor: '#6366f1',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<i class="fas fa-check"></i> Adicionar',
    cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
    inputValidator: (value) => {
      if (!value || !value.trim()) {
        return 'Por favor, digite uma observação!';
      }
    }
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: 'tarefas.php',
        method: 'POST',
        data: {
          action: 'append_note',
          id: id,
          observacao: result.value
        },
        dataType: 'json',
        success: function(r) {
          if (!r.success) {
            Swal.fire({
              icon: 'error',
              title: 'Erro',
              text: r.error || 'Falha ao salvar observação.',
              confirmButtonColor: '#6366f1'
            });
            return;
          }

          Swal.fire({
            icon: 'success',
            title: 'Observação Adicionada!',
            text: 'A observação foi salva com sucesso.',
            timer: 2000,
            showConfirmButton: false
          });

          carregarKanban();
        },
        error: function(xhr, status, error) {
          console.error('Erro AJAX:', error);
          Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Não foi possível salvar a observação.',
            confirmButtonColor: '#6366f1'
          });
        }
      });
    }
  });
}

// ===================== ABRIR MODAL DE REATRIBUIÇÃO =====================
function openReatribuir(id, equipeId) {
  $('#rea_tarefa_id').val(id);
  $('#rea_obs').val('');

  // Carrega funcionários da equipe
  $.getJSON('tarefas.php', { 
    action: 'list_funcionarios', 
    equipe_id: equipeId 
  }, function(r) {
    const $sel = $('#rea_func').empty();
    
    if (r.success && r.data.length) {
      $sel.append('<option value="">Selecione um funcionário</option>');
      r.data.forEach(f => {
        const label = `${f.nome_completo} (${f.usuario})`;
        $sel.append(new Option(label, f.id));
      });
    } else {
      $sel.append('<option value="">— Sem membros ativos —</option>');
    }

    MODAL_REA.show();
  });
}

// ===================== SUBMIT REATRIBUIÇÃO =====================
$('#formReatribuir').on('submit', function(e) {
  e.preventDefault();

  const id = $('#rea_tarefa_id').val();
  const funcionario_id = $('#rea_func').val();
  const observacao = $('#rea_obs').val();

  if (!funcionario_id) {
    Swal.fire({
      icon: 'warning',
      title: 'Atenção',
      text: 'Por favor, selecione um funcionário.',
      confirmButtonColor: '#6366f1'
    });
    return;
  }

  $.ajax({
    url: 'tarefas.php',
    method: 'POST',
    data: {
      action: 'reassign',
      id: id,
      funcionario_id: funcionario_id,
      observacao: observacao
    },
    dataType: 'json',
    success: function(r) {
      if (!r.success) {
        Swal.fire({
          icon: 'error',
          title: 'Erro na Reatribuição',
          text: r.error || 'Falha ao reatribuir tarefa.',
          confirmButtonColor: '#6366f1'
        });
        return;
      }

      MODAL_REA.hide();

      Swal.fire({
        icon: 'success',
        title: 'Tarefa Reatribuída!',
        text: 'A tarefa foi reatribuída com sucesso.',
        timer: 2000,
        showConfirmButton: false
      });

      carregarKanban();
    },
    error: function(xhr, status, error) {
      console.error('Erro AJAX:', error);
      Swal.fire({
        icon: 'error',
        title: 'Erro de Conexão',
        text: 'Não foi possível reatribuir a tarefa.',
        confirmButtonColor: '#6366f1'
      });
    }
  });
});

// ===================== ABRIR MODAL DE LOG =====================
function openLog(id) {
  $('#logBody').html(`
    <div class="text-center py-3">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Carregando...</span>
      </div>
      <p class="text-secondary mt-2">Carregando histórico...</p>
    </div>
  `);

  $.getJSON('tarefas.php', { action: 'get_log', id: id }, function(r) {
    if (!r.success) {
      $('#logBody').html(`
        <div class="text-center text-danger py-3">
          <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 12px;"></i>
          <p>Falha ao carregar histórico.</p>
        </div>
      `);
      return;
    }

    if (!r.data.length) {
      $('#logBody').html(`
        <div class="text-center text-secondary py-4">
          <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 12px;"></i>
          <p>Nenhum evento registrado.</p>
        </div>
      `);
      return;
    }

    let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
    html += `
      <thead>
        <tr>
          <th style="width: 50px;">#</th>
          <th>Ação</th>
          <th>De</th>
          <th>Para</th>
          <th>Observação</th>
          <th>Usuário</th>
          <th>Data/Hora</th>
        </tr>
      </thead>
      <tbody>
    `;

    r.data.forEach((l, i) => {
      const acaoIcons = {
        'status': 'flag',
        'reatribuicao': 'exchange-alt',
        'observacao': 'comment'
      };

      html += `
        <tr>
          <td><strong>${i + 1}</strong></td>
          <td>
            <i class="fas fa-${acaoIcons[l.acao] || 'info-circle'}" style="color: var(--brand-primary);"></i>
            ${escapeHtml(l.acao)}
          </td>
          <td><span class="badge badge-secondary">${escapeHtml(l.de_valor || '—')}</span></td>
          <td><span class="badge badge-info">${escapeHtml(l.para_valor || '—')}</span></td>
          <td>${escapeHtml(l.observacao || '—')}</td>
          <td><strong>${escapeHtml(l.usuario || '—')}</strong></td>
          <td style="white-space: nowrap;">${formatarDataHora(l.criado_em)}</td>
        </tr>
      `;
    });

    html += '</tbody></table></div>';
    $('#logBody').html(html);
  });

  MODAL_LOG.show();
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

function formatarData(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

function formatarDataHora(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}
</script>

</body>
</html>