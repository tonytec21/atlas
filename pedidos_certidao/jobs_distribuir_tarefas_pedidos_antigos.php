<?php
/**
 * Distribui TAREFAS para pedidos antigos (já protocolados) obedecendo
 * as MESMAS regras do salvar_pedido.php, usando a lista específica (MAPEAMENTO_REGRAS).
 *
 * Regras:
 *  - Só considera pedidos em ('pendente','em_andamento')
 *  - Ignora pedidos que já tenham tarefa aberta (pendente/em_andamento)
 *  - Descobre equipe por (atribuicao, tipo) EXATOS após canonicalização pelo MAPEAMENTO_REGRAS
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

/* ======================== MAPEAMENTO ESPECÍFICO (igual ao salvar_pedido) ======================== */
const MAPEAMENTO_REGRAS = [
  "Registro Civil" => [
    "2ª via de Nascimento",
    "Inteiro Teor de Nascimento",
    "Retificação Administrativa de Nascimento",
    "Restauração de Nascimento",
    "Busca de Nascimento",
    "2ª via de Casamento",
    "Inteiro Teor de Casamento",
    "Retificação Administrativa de Casamento",
    "Restauração de Casamento",
    "Busca de Casamento",
    "Divórcio",
    "2ª via de Óbito",
    "Inteiro Teor de Óbito",
    "Retificação Administrativa de Óbito",
    "Restauração de Óbito",
    "Busca de Óbito"
  ],
  "Pessoas Jurídicas" => [
    "Estatuto",
    "Atas",
    "Outros"
  ],
  "Títulos e Documentos" => [
    "Contratos",
    "Cédulas",
    "Outros"
  ],
  "Registro de Imóveis" => [
    "Matrícula Livro 2",
    "Registro Livro 3",
    "Ônus",
    "Penhor",
    "Negativa",
    "Situação Jurídica"
  ],
  "Notas" => [
    "Escrituras",
    "Testamentos",
    "Procurações",
    "Ata Notarial"
  ]
];

/* ======================== Normalização/Canonicalização (igual ao salvar_pedido) ======================== */
function normalizar($s) {
  $s = trim((string)$s);
  $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  $s = strtolower($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}
function canonicalizarAtribuicao($atr) {
  $n = normalizar($atr);
  $map = [
    'registro civil' => 'Registro Civil',
    'rcpn' => 'Registro Civil',
    'registro civil das pessoas naturais' => 'Registro Civil',
    'pessoas juridicas' => 'Pessoas Jurídicas',
    'rtdpj' => 'Pessoas Jurídicas',
    'rtd/pj' => 'Pessoas Jurídicas',
    'rtdpj/rtpj' => 'Pessoas Jurídicas',
    'rtd/rtdpj' => 'Pessoas Jurídicas',
    'rtd' => 'Títulos e Documentos',
    'titulos e documentos' => 'Títulos e Documentos',
    'registro de imoveis' => 'Registro de Imóveis',
    'ri' => 'Registro de Imóveis',
    'notas' => 'Notas'
  ];
  if (isset($map[$n])) return $map[$n];
  foreach (array_keys(MAPEAMENTO_REGRAS) as $k) {
    if (normalizar($k) === $n) return $k;
  }
  return null;
}
function canonicalizarTipo($atrCanon, $tipo) {
  if (!$atrCanon) return null;
  $raw = trim((string)$tipo);
  $n = normalizar($raw);

  if (preg_match('~^2(a|ª)\s*(de\s*)?nascimento$~i', $raw)) {
    $cand = '2ª via de Nascimento';
  } elseif (preg_match('~^2(a|ª)\s*(de\s*)?casamento$~i', $raw)) {
    $cand = '2ª via de Casamento';
  } elseif (preg_match('~^2(a|ª)\s*(de\s*)?(obito|óbito)$~iu', $raw)) {
    $cand = '2ª via de Óbito';
  } elseif (preg_match('~^inteiro\s*teor\s*de\s*nascimento$~i', $n)) {
    $cand = 'Inteiro Teor de Nascimento';
  } elseif (preg_match('~^inteiro\s*teor\s*de\s*casamento$~i', $n)) {
    $cand = 'Inteiro Teor de Casamento';
  } elseif (preg_match('~^inteiro\s*teor\s*de\s*(obito|óbito)$~iu', $n)) {
    $cand = 'Inteiro Teor de Óbito';
  } else {
    $cand = null;
    foreach (MAPEAMENTO_REGRAS[$atrCanon] ?? [] as $opt) {
      if (normalizar($opt) === $n) { $cand = $opt; break; }
    }
  }

  if ($cand && in_array($cand, MAPEAMENTO_REGRAS[$atrCanon] ?? [], true)) {
    return $cand;
  }
  return null;
}

/* ======================== Regras de distribuição (iguais ao salvar_pedido) ======================== */
function encontrarEquipePorRegra(PDO $conn, string $atribuicao, string $tipo): ?array {
  $atrCanon  = canonicalizarAtribuicao($atribuicao);
  $tipoCanon = $atrCanon ? canonicalizarTipo($atrCanon, $tipo) : null;
  if (!$atrCanon || !$tipoCanon) return null;

  $sql = "SELECT r.*, e.nome AS equipe_nome
          FROM equipe_regras r
          JOIN equipes e ON e.id = r.equipe_id AND e.ativa = 1
          WHERE r.ativa = 1
            AND r.atribuicao = :a
            AND r.tipo       = :t
          ORDER BY r.prioridade ASC, r.id ASC
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->execute([':a' => $atrCanon, ':t' => $tipoCanon]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
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

    if (!is_null($m['carga_maxima_diaria']) && $m['carga_maxima_diaria'] >= 0 && $cargaHoje >= (int)$m['carga_maxima_diaria']) {
      continue;
    }

    if ($melhor===null || $cargaTotal < $melhorCarga) {
      $melhor = $m;
      $melhorCarga = $cargaTotal;
    }
  }

  if ($melhor===null) {
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

        // Encontra equipe pelas REGRAS EXATAS (com canonicalização)
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

        // Escolhe membro (sem filtro por papel)
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
