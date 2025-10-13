<?php
/**
 * Distribui TAREFAS para pedidos antigos (já protocolados) obedecendo
 * as MESMAS regras do salvar_pedido.php.
 *
 * Regras:
 *  - Só considera pedidos em ('pendente','em_andamento')
 *  - Ignora pedidos que já tenham tarefa aberta (pendente/em_andamento)
 *  - Descobre equipe pela matriz de regras (atribuicao, tipo)
 *  - Escolhe o membro com menor carga (desempate por ordem e id)
 *
 * Parâmetros:
 *  - dry_run=1  -> Simulação (não grava). Omitir/0 para executar de fato.
 */

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

// Evitar ruído fora do JSON
if (function_exists('ob_start')) { ob_start(); }

$dryRun = isset($_GET['dry_run']) ? (int)$_GET['dry_run'] : 0;

require_once __DIR__ . '/db_connection.php'; // getDatabaseConnection() (PDO)

$out = [
    'ok'                   => true,
    'dry_run'              => (bool)$dryRun,
    'total_elegiveis'      => 0,
    'total_distribuidos'   => 0,
    'pre_visualizacao'     => [],
    'sem_regra'            => [], // pedidos sem regra compatível
    'sem_membro'           => [], // equipe encontrada, mas sem membro disponível
    'ja_possuem_tarefa'    => [], // por segurança, se algo escapar do filtro
    'erros'                => []
];

/* ======================== Helpers de normalização (iguais ao salvar_pedido) ======================== */
function candidatosAtribuicao(string $atr): array {
  $a = trim($atr);
  $norm = mb_strtolower($a, 'UTF-8');

  $out = [$a];

  if (strpos($norm, 'registro civil') !== false) {
    $out[] = 'RCPN';
    $out[] = 'Registro Civil';
  }
  if (strpos($norm, 'título') !== false || strpos($norm, 'titul') !== false || strpos($norm, 'document') !== false ||
      strpos($norm, 'pessoa jurídica') !== false || strpos($norm, 'pessoas jurídicas') !== false) {
    $out[] = 'RTD/RTDPJ';
    $out[] = 'Títulos e Documentos';
    $out[] = 'Pessoas Jurídicas';
  }
  if (strpos($norm, 'imóv') !== false || strpos($norm, 'imov') !== false) {
    $out[] = 'RI';
    $out[] = 'Registro de Imóveis';
  }
  if (strpos($norm, 'nota') !== false) {
    $out[] = 'Notas';
  }

  return array_values(array_unique($out));
}

function candidatosTipo(string $tipo): array {
  $t = trim($tipo);
  $out = [$t];

  // Simplificações comuns
  $simp = $t;
  $simp = preg_replace('~^\s*(\d+ª|\d+a)\s+(de|da)\s+~iu', '', $simp);      // "2ª de", "2a de"
  $simp = preg_replace('~^\s*inteiro\s+teor\s+(de|da)\s+~iu', '', $simp);   // "Inteiro Teor de"
  $simp = preg_replace('~\s+livro\s*\d*$~iu', '', $simp);                   // " ... livro 3"
  $simp = trim($simp);
  if ($simp !== '' && $simp !== $t) $out[] = $simp;

  // palavra-chave
  $kw = $simp !== '' ? $simp : $t;
  $map = ['Ó'=>'O','ó'=>'o','ã'=>'a','â'=>'a','á'=>'a','à'=>'a','ê'=>'e','é'=>'e','í'=>'i','î'=>'i','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c'];
  $kwn = strtr(mb_strtolower($kw,'UTF-8'), $map);
  if (preg_match('~(nascimento|casamento|obito|óbito|escritura|escrituras|procura(c|ç)ao|procura(c|ç)oes|ata|testamento|oner|onus|penhor|negativa|matr(i|í)cula)~iu', $kwn, $m)) {
    $key = $m[0];
    $replacements = [
      'obito' => 'Óbito',
      'procuracao' => 'Procuração', 'procuracoes' => 'Procurações',
      'matricula' => 'Matrícula', 'onus' => 'Ônus',
    ];
    $keyShow = $replacements[$key] ?? ucfirst($key);
    $out[] = $keyShow;
  }

  return array_values(array_unique($out));
}

/* ======================== Regras de distribuição (iguais ao salvar_pedido) ======================== */
function encontrarEquipePorRegra(PDO $conn, string $atribuicao, string $tipo): ?array {
  $atrCands = candidatosAtribuicao($atribuicao);
  $tipoCands = candidatosTipo($tipo);

  // 1) match EXATO
  $sqlExact = "SELECT r.*, e.nome AS equipe_nome
               FROM equipe_regras r
               JOIN equipes e ON e.id = r.equipe_id AND e.ativa=1
               WHERE r.ativa=1 AND r.atribuicao = :a AND r.tipo = :t
               ORDER BY r.prioridade ASC, r.id ASC
               LIMIT 1";
  $stExact = $conn->prepare($sqlExact);

  foreach ($atrCands as $a) {
    foreach ($tipoCands as $t) {
      $stExact->execute([':a'=>$a, ':t'=>$t]);
      $row = $stExact->fetch(PDO::FETCH_ASSOC);
      if ($row) return $row;
    }
  }

  // 2) curinga ('*')
  $sqlStar = "SELECT r.*, e.nome AS equipe_nome
              FROM equipe_regras r
              JOIN equipes e ON e.id = r.equipe_id AND e.ativa=1
              WHERE r.ativa=1 AND r.atribuicao = :a AND r.tipo = '*'
              ORDER BY r.prioridade ASC, r.id ASC
              LIMIT 1";
  $stStar = $conn->prepare($sqlStar);
  foreach ($atrCands as $a) {
    $stStar->execute([':a'=>$a]);
    $row = $stStar->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
  }

  // 3) LIKE por palavra-chave do tipo
  $sqlLike = "SELECT r.*, e.nome AS equipe_nome
              FROM equipe_regras r
              JOIN equipes e ON e.id = r.equipe_id AND e.ativa=1
              WHERE r.ativa=1 AND r.atribuicao = :a AND r.tipo LIKE :tk
              ORDER BY r.prioridade ASC, r.id ASC
              LIMIT 1";
  $stLike = $conn->prepare($sqlLike);
  foreach ($atrCands as $a) {
    foreach ($tipoCands as $tk) {
      $stLike->execute([':a'=>$a, ':tk'=>'%'.$tk.'%']);
      $row = $stLike->fetch(PDO::FETCH_ASSOC);
      if ($row) return $row;
    }
  }

  return null;
}

function escolherMembroParaEquipe(PDO $conn, int $equipeId): ?array {
  $membros = $conn->prepare("SELECT m.id, m.funcionario_id, m.ordem, m.carga_maxima_diaria,
                                    f.nome_completo, f.usuario
                             FROM equipe_membros m
                             JOIN funcionarios f ON f.id = m.funcionario_id
                             WHERE m.equipe_id=? AND m.ativo=1
                             ORDER BY m.ordem ASC, m.id ASC");
  $membros->execute([$equipeId]);
  $arr = $membros->fetchAll(PDO::FETCH_ASSOC);
  if (!$arr) return null;

  $calcHoje = $conn->prepare("SELECT COUNT(*) FROM tarefas_pedido
                              WHERE funcionario_id=? AND status IN ('pendente','em_andamento')
                                AND DATE(criado_em)=CURRENT_DATE()");
  $calcGeral = $conn->prepare("SELECT COUNT(*) FROM tarefas_pedido
                               WHERE funcionario_id=? AND status IN ('pendente','em_andamento')");

  $melhor = null; $melhorCarga = null;
  foreach ($arr as $m) {
    $calcGeral->execute([$m['funcionario_id']]);
    $cargaTotal = (int)$calcGeral->fetchColumn();

    $calcHoje->execute([$m['funcionario_id']]);
    $cargaHoje = (int)$calcHoje->fetchColumn();

    // respeita carga_maxima_diaria se configurada
    if (!is_null($m['carga_maxima_diaria']) && $m['carga_maxima_diaria'] >= 0 && $cargaHoje >= (int)$m['carga_maxima_diaria']) {
      continue;
    }

    if ($melhor===null || $cargaTotal < $melhorCarga) {
      $melhor = $m;
      $melhorCarga = $cargaTotal;
    }
  }

  if ($melhor===null) {
    // fallback: ignora carga diaria, pega menor carga total
    foreach ($arr as $m) {
      $calcGeral->execute([$m['funcionario_id']]);
      $cargaTotal = (int)$calcGeral->fetchColumn();
      if ($melhor===null || $cargaTotal < $melhorCarga) { $melhor=$m; $melhorCarga=$cargaTotal; }
    }
  }
  return $melhor;
}

function criarTarefaParaPedido(PDO $conn, int $pedidoId, int $equipeId, ?int $funcionarioId): int {
  $st = $conn->prepare("INSERT INTO tarefas_pedido (pedido_id, equipe_id, funcionario_id, status)
                        VALUES (?,?,?, 'pendente')");
  $st->execute([$pedidoId, $equipeId, $funcionarioId]);
  return (int)$conn->lastInsertId();
}

/* ======================== Execução ======================== */
try {
    $pdo = getDatabaseConnection();

    // 1) Pedidos elegíveis (pendente/em_andamento) sem tarefa aberta
    $sql = "
      SELECT p.id, p.protocolo, p.atribuicao, p.tipo, p.status
      FROM pedidos_certidao p
      WHERE p.status IN ('pendente','em_andamento')
        AND NOT EXISTS (
          SELECT 1
            FROM tarefas_pedido t
           WHERE t.pedido_id = p.id
             AND t.status IN ('pendente','em_andamento')
        )
      ORDER BY p.id ASC
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $out['total_elegiveis'] = count($rows);

    // 2) Preparar verificação extra (se escapar do NOT EXISTS)
    $hasOpenTask = $pdo->prepare("
        SELECT COUNT(*) FROM tarefas_pedido
         WHERE pedido_id=? AND status IN ('pendente','em_andamento')
    ");

    // 3) Loop de distribuição
    foreach ($rows as $r) {
        $pedidoId   = (int)$r['id'];
        $protocolo  = $r['protocolo'];
        $atr        = trim($r['atribuicao'] ?? '');
        $tipo       = trim($r['tipo'] ?? '');
        $statusPed  = $r['status'];

        // Segurança: confirmar ausência de tarefa aberta
        $hasOpenTask->execute([$pedidoId]);
        if ((int)$hasOpenTask->fetchColumn() > 0) {
            $out['ja_possuem_tarefa'][] = ['pedido_id'=>$pedidoId,'protocolo'=>$protocolo];
            continue;
        }

        // Encontra equipe pelas regras
        $regra = encontrarEquipePorRegra($pdo, $atr, $tipo);
        if (!$regra) {
            $out['sem_regra'][] = [
                'pedido_id' => $pedidoId,
                'protocolo' => $protocolo,
                'atribuicao'=> $atr,
                'tipo'      => $tipo
            ];
            continue;
        }

        $equipeId   = (int)$regra['equipe_id'];
        $equipeNome = $regra['equipe_nome'] ?? null;

        // Escolhe membro
        $membro = escolherMembroParaEquipe($pdo, $equipeId);
        if (!$membro) {
            $out['sem_membro'][] = [
                'pedido_id'  => $pedidoId,
                'protocolo'  => $protocolo,
                'equipe_id'  => $equipeId,
                'equipe_nome'=> $equipeNome
            ];
            continue;
        }

        $funcId = (int)$membro['funcionario_id'];

        // Pré-visualização sempre
        $out['pre_visualizacao'][] = [
            'pedido_id'           => $pedidoId,
            'protocolo'           => $protocolo,
            'status_pedido'       => $statusPed,
            'equipe_id'           => $equipeId,
            'equipe_nome'         => $equipeNome,
            'funcionario_id'      => $funcId,
            'funcionario_nome'    => $membro['nome_completo'] ?? null,
            'funcionario_usuario' => $membro['usuario'] ?? null,
            'status_tarefa'       => 'pendente'
        ];

        // Inserção (se não for dry_run)
        if (!$dryRun) {
            criarTarefaParaPedido($pdo, $pedidoId, $equipeId, $funcId);
            $out['total_distribuidos']++;
        }
    }

    if (function_exists('ob_get_length') && ob_get_length()) ob_clean();
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (function_exists('ob_get_length') && ob_get_length()) ob_clean();
    echo json_encode([
        'ok'    => false,
        'erro'  => 'Falha ao distribuir tarefas: '.$e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
