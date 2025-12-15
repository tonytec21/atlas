<?php
/**
 * Arquivo: pedidos_certidao/redistribuir_tarefas_pendentes.php
 *
 * Página (HTML) para:
 *  1) Listar equipes ativas (select)
 *  2) Permitir simular (dry_run) e executar redistribuição
 *  3) Mostrar resultado (JSON) formatado na tela
 *
 * Requer:
 *  - db_connection.php com getDatabaseConnection() (PDO)
 *  - A tabela equipes (id, nome, ativa)
 *  - A tabela equipe_membros (equipe_id, funcionario_id, ativo, ordem, carga_maxima_diaria)
 *  - A tabela funcionarios (id, nome_completo, usuario)
 *  - A tabela tarefas_pedido (id, pedido_id, equipe_id, funcionario_id, status, criado_em)
 *  - A tabela pedidos_certidao (id, protocolo, status)
 */

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/db_connection.php'; // getDatabaseConnection() (PDO)

/* ======================= Helpers HTML ======================= */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ======================= Core Redistribuição ======================= */
function escolherMembroBalanceado(array $membros, array $cargaAtual, array $cargaHoje, bool $respeitarMax = true): ?array {
    $melhor = null;
    $melhorCarga = null;

    foreach ($membros as $m) {
        $fid = (int)$m['funcionario_id'];
        $load  = isset($cargaAtual[$fid]) ? (int)$cargaAtual[$fid] : 0;
        $today = isset($cargaHoje[$fid])  ? (int)$cargaHoje[$fid]  : 0;

        if ($respeitarMax) {
            if ($m['carga_maxima_diaria'] !== null && $m['carga_maxima_diaria'] !== '') {
                $max = (int)$m['carga_maxima_diaria'];
                if ($max >= 0 && $today >= $max) {
                    continue;
                }
            }
        }

        if ($melhor === null) {
            $melhor = $m;
            $melhorCarga = $load;
            continue;
        }

        if ($load < $melhorCarga) {
            $melhor = $m;
            $melhorCarga = $load;
            continue;
        }

        if ($load === $melhorCarga) {
            $ordMelhor = (int)$melhor['ordem'];
            $ordAtual  = (int)$m['ordem'];
            if ($ordAtual < $ordMelhor) {
                $melhor = $m;
                $melhorCarga = $load;
            } elseif ($ordAtual === $ordMelhor) {
                if ((int)$m['id'] < (int)$melhor['id']) {
                    $melhor = $m;
                    $melhorCarga = $load;
                }
            }
        }
    }

    return $melhor;
}

function executarRedistribuicao(PDO $pdo, int $equipeId, bool $dryRun, int $limit, int $previewMax): array {
    $out = [
        'ok'                   => true,
        'dry_run'              => $dryRun,
        'parametros'           => [
            'equipe_id'   => $equipeId,
            'limit'       => $limit ?: null,
            'preview_max' => $previewMax,
        ],
        'equipe'               => null,
        'total_tarefas_alvo'   => 0,
        'total_redistribuidas' => 0,
        'pre_visualizacao'     => [],
        'avisos'               => [],
        'erros'                => [],
    ];

    // equipe
    $stEq = $pdo->prepare("SELECT id, nome, ativa FROM equipes WHERE id=? LIMIT 1");
    $stEq->execute([$equipeId]);
    $eq = $stEq->fetch(PDO::FETCH_ASSOC);
    if (!$eq || (int)$eq['ativa'] !== 1) {
        $out['ok'] = false;
        $out['erros'][] = 'Equipe inválida ou inativa.';
        return $out;
    }
    $out['equipe'] = ['id'=>(int)$eq['id'], 'nome'=>$eq['nome']];

    // membros
    $stM = $pdo->prepare("
        SELECT m.id, m.funcionario_id, m.ordem, m.carga_maxima_diaria,
               f.nome_completo, f.usuario
          FROM equipe_membros m
          JOIN funcionarios f ON f.id = m.funcionario_id
         WHERE m.equipe_id = ?
           AND m.ativo = 1
         ORDER BY m.ordem ASC, m.id ASC
    ");
    $stM->execute([$equipeId]);
    $membros = $stM->fetchAll(PDO::FETCH_ASSOC);
    if (!$membros) {
        $out['ok'] = false;
        $out['erros'][] = 'Equipe selecionada não possui membros ativos.';
        return $out;
    }

    // tarefas alvo: tarefas pendentes da equipe, com pedidos pendentes
    $sqlTarefas = "
        SELECT
            t.id              AS tarefa_id,
            t.pedido_id       AS pedido_id,
            t.funcionario_id  AS funcionario_atual,
            t.criado_em       AS tarefa_criado_em,
            p.protocolo       AS protocolo,
            p.status          AS status_pedido
        FROM tarefas_pedido t
        JOIN pedidos_certidao p ON p.id = t.pedido_id
        WHERE t.equipe_id = :equipe
          AND t.status = 'pendente'
          AND p.status = 'pendente'
        ORDER BY t.id ASC
    ";
    if ($limit > 0) $sqlTarefas .= " LIMIT " . (int)$limit;

    $stT = $pdo->prepare($sqlTarefas);
    $stT->execute([':equipe' => $equipeId]);
    $tarefas = $stT->fetchAll(PDO::FETCH_ASSOC);
    $out['total_tarefas_alvo'] = count($tarefas);

    if (!$tarefas) {
        $out['avisos'][] = 'Nenhuma tarefa pendente encontrada para redistribuir (pedidos pendentes).';
        return $out;
    }

    // contar quantas tarefas-alvo estavam em cada funcionário (para excluir da carga base)
    $alvoPorFunc = [];
    $alvoHojePorFunc = [];
    foreach ($tarefas as $t) {
        $fid = isset($t['funcionario_atual']) ? (int)$t['funcionario_atual'] : 0;
        if ($fid > 0) {
            $alvoPorFunc[$fid] = ($alvoPorFunc[$fid] ?? 0) + 1;
            if (!empty($t['tarefa_criado_em'])) {
                $d = substr((string)$t['tarefa_criado_em'], 0, 10);
                if ($d === date('Y-m-d')) {
                    $alvoHojePorFunc[$fid] = ($alvoHojePorFunc[$fid] ?? 0) + 1;
                }
            }
        }
    }

    // carga aberta (pendente/em_andamento) na equipe
    $stCarga = $pdo->prepare("
        SELECT funcionario_id, COUNT(*) AS qtd
          FROM tarefas_pedido
         WHERE equipe_id = ?
           AND status IN ('pendente','em_andamento')
           AND funcionario_id IS NOT NULL
         GROUP BY funcionario_id
    ");
    $stCarga->execute([$equipeId]);
    $cargaEquipe = $stCarga->fetchAll(PDO::FETCH_ASSOC);
    $cargaAtual = [];
    foreach ($cargaEquipe as $row) {
        $cargaAtual[(int)$row['funcionario_id']] = (int)$row['qtd'];
    }

    // carga aberta HOJE (para limite diário)
    $stCargaHoje = $pdo->prepare("
        SELECT funcionario_id, COUNT(*) AS qtd
          FROM tarefas_pedido
         WHERE equipe_id = ?
           AND status IN ('pendente','em_andamento')
           AND funcionario_id IS NOT NULL
           AND DATE(criado_em) = CURRENT_DATE()
         GROUP BY funcionario_id
    ");
    $stCargaHoje->execute([$equipeId]);
    $cargaHojeEquipe = $stCargaHoje->fetchAll(PDO::FETCH_ASSOC);
    $cargaHoje = [];
    foreach ($cargaHojeEquipe as $row) {
        $cargaHoje[(int)$row['funcionario_id']] = (int)$row['qtd'];
    }

    // excluir da carga base as tarefas-alvo
    foreach ($cargaAtual as $fid => $qtd) {
        $sub = (int)($alvoPorFunc[$fid] ?? 0);
        $novo = $qtd - $sub;
        if ($novo < 0) $novo = 0;
        $cargaAtual[$fid] = $novo;
    }
    foreach ($cargaHoje as $fid => $qtd) {
        $sub = (int)($alvoHojePorFunc[$fid] ?? 0);
        $novo = $qtd - $sub;
        if ($novo < 0) $novo = 0;
        $cargaHoje[$fid] = $novo;
    }

    $stUpd = $pdo->prepare("UPDATE tarefas_pedido SET funcionario_id = ? WHERE id = ?");

    if (!$dryRun) $pdo->beginTransaction();

    $previewCount = 0;

    foreach ($tarefas as $t) {
        $tarefaId = (int)$t['tarefa_id'];
        $pedidoId = (int)$t['pedido_id'];
        $protocolo = $t['protocolo'] ?? null;
        $fidAtual = isset($t['funcionario_atual']) ? (int)$t['funcionario_atual'] : 0;

        $m = escolherMembroBalanceado($membros, $cargaAtual, $cargaHoje, true);
        if ($m === null) {
            $out['avisos'][] = 'Todos os membros parecem no limite diário; aplicando fallback (ignorar carga_maxima_diaria).';
            $m = escolherMembroBalanceado($membros, $cargaAtual, $cargaHoje, false);
        }
        if ($m === null) {
            throw new RuntimeException('Não foi possível selecionar membro para redistribuição.');
        }

        $fidNovo = (int)$m['funcionario_id'];

        if ($previewMax === 0 || $previewCount < $previewMax) {
            $out['pre_visualizacao'][] = [
                'tarefa_id'             => $tarefaId,
                'pedido_id'             => $pedidoId,
                'protocolo'             => $protocolo,
                'status_pedido'         => $t['status_pedido'] ?? null,
                'funcionario_id_atual'  => $fidAtual > 0 ? $fidAtual : null,
                'funcionario_id_novo'   => $fidNovo,
                'novo_nome'             => $m['nome_completo'] ?? null,
                'novo_usuario'          => $m['usuario'] ?? null,
                'carga_base_novo_antes' => isset($cargaAtual[$fidNovo]) ? (int)$cargaAtual[$fidNovo] : 0,
            ];
            $previewCount++;
        }

        if (!$dryRun) {
            $stUpd->execute([$fidNovo, $tarefaId]);
            $out['total_redistribuidas']++;
        }

        $cargaAtual[$fidNovo] = (int)($cargaAtual[$fidNovo] ?? 0) + 1;
        $cargaHoje[$fidNovo]  = (int)($cargaHoje[$fidNovo] ?? 0) + 1;
    }

    if (!$dryRun) $pdo->commit();

    if ($previewMax > 0 && $out['total_tarefas_alvo'] > $previewMax) {
        $out['avisos'][] = "Pré-visualização limitada a {$previewMax} itens (use preview_max para ajustar).";
    }

    return $out;
}

/* ======================= Controller da Página ======================= */
$pdo = getDatabaseConnection();

// lista de equipes ativas
$equipes = $pdo->query("SELECT id, nome FROM equipes WHERE ativa=1 ORDER BY nome ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// se enviou form, executar (ou simular)
$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipeId   = isset($_POST['equipe_id']) ? (int)$_POST['equipe_id'] : 0;
    $dryRun     = isset($_POST['dry_run']) ? true : false;
    $limit      = isset($_POST['limit']) ? (int)$_POST['limit'] : 0;
    $previewMax = isset($_POST['preview_max']) ? (int)$_POST['preview_max'] : 300;

    if ($previewMax < 0) $previewMax = 0;

    if ($equipeId > 0) {
        $resultado = executarRedistribuicao($pdo, $equipeId, $dryRun, $limit, $previewMax);
    } else {
        $resultado = ['ok'=>false,'erro'=>'Selecione uma equipe para executar.'];
    }
}

?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redistribuir tarefas pendentes (Pedidos)</title>
    <style>
        :root{
            --bg:#0b0f14; --card:#121826; --text:#e8eef6; --muted:#9fb0c3; --border:#223049;
            --btn:#1f6feb; --btn2:#2dba4e; --danger:#ff5a5f;
            --input:#0f1522;
        }
        @media (prefers-color-scheme: light){
            :root{
                --bg:#f6f7fb; --card:#ffffff; --text:#0f172a; --muted:#5b677a; --border:#d7dde8;
                --btn:#2563eb; --btn2:#16a34a; --danger:#dc2626;
                --input:#f3f6ff;
            }
        }
        body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text);}
        .wrap{max-width:1100px;margin:0 auto;padding:22px;}
        .header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px;}
        .title{font-size:20px;font-weight:700;}
        .sub{font-size:13px;color:var(--muted);margin-top:4px;line-height:1.4;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:14px;}
        label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px;}
        select,input[type="number"]{
            width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border);
            background:var(--input);color:var(--text);outline:none;
        }
        .row{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;}
        .row2{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px;}
        .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;align-items:center;}
        .btn{border:0;border-radius:10px;padding:10px 14px;color:white;cursor:pointer;font-weight:650;}
        .btn-run{background:var(--btn2);}
        .btn-sim{background:var(--btn);}
        .pill{display:inline-flex;gap:8px;align-items:center;color:var(--muted);font-size:13px;}
        .pill input{transform:scale(1.15);}
        pre{
            margin:0;white-space:pre-wrap;word-break:break-word;
            background:rgba(0,0,0,.25);border:1px solid var(--border);
            padding:12px;border-radius:12px;color:var(--text);overflow:auto;
        }
        @media (prefers-color-scheme: light){
            pre{background:#f3f6ff;}
        }
        .warn{color:#ffd166;}
        .ok{color:#7CFF9E;}
        .bad{color:#ff8b8b;}
        .hint{font-size:12px;color:var(--muted);margin-top:8px;line-height:1.4;}
    </style>
</head>
<body>
<div class="wrap">

    <div class="header">
        <div>
            <div class="title">Redistribuir tarefas pendentes (Pedidos de Certidão)</div>
            <div class="sub">
                Selecione uma <b>equipe</b> e redistribua as <b>tarefas pendentes</b> dessa equipe entre os membros ativos atuais,
                balanceando pela menor carga aberta.
            </div>
        </div>
    </div>

    <div class="card">
        <form method="post" action="">
            <div class="row">
                <div>
                    <label>Equipe (ativa)</label>
                    <select name="equipe_id" required>
                        <option value="">-- Selecione a equipe --</option>
                        <?php foreach ($equipes as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" <?= ($resultado && isset($_POST['equipe_id']) && (int)$_POST['equipe_id']===(int)$e['id'])?'selected':''; ?>>
                                #<?= (int)$e['id'] ?> - <?= h($e['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">Dica: primeiro rode em <b>Simular (dry_run)</b> para ver o que vai acontecer.</div>
                </div>

                <div>
                    <label>Limite de tarefas (opcional)</label>
                    <input type="number" name="limit" min="0" step="1" value="<?= isset($_POST['limit']) ? (int)$_POST['limit'] : 0; ?>">
                    <div class="hint">0 = sem limite (todas as pendentes).</div>
                </div>

                <div>
                    <label>Preview máx.</label>
                    <input type="number" name="preview_max" min="0" step="1" value="<?= isset($_POST['preview_max']) ? (int)$_POST['preview_max'] : 300; ?>">
                    <div class="hint">0 = sem limite no preview.</div>
                </div>
            </div>

            <div class="actions">
                <label class="pill" title="Se marcado, NÃO grava no banco. Apenas simula.">
                    <input type="checkbox" name="dry_run" <?= (isset($_POST['dry_run']) ? 'checked' : 'checked'); ?>>
                    <span>dry_run (simular)</span>
                </label>

                <button type="submit" class="btn btn-sim">Executar / Simular</button>

                <span class="hint">
                    Para executar de verdade, <b>desmarque</b> o dry_run e clique novamente.
                </span>
            </div>
        </form>
    </div>

    <?php if ($resultado !== null): ?>
        <div class="card">
            <?php
                $ok = isset($resultado['ok']) ? (bool)$resultado['ok'] : false;
                $cls = $ok ? 'ok' : 'bad';
            ?>
            <div style="font-weight:800;margin-bottom:10px;">
                Resultado: <span class="<?= $cls; ?>"><?= $ok ? 'OK' : 'FALHOU'; ?></span>
                <?php if (isset($resultado['dry_run']) && $resultado['dry_run']): ?>
                    <span class="warn"> (SIMULAÇÃO)</span>
                <?php endif; ?>
            </div>

            <pre><?= h(json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
        </div>
    <?php endif; ?>

</div>

<script>
/* UX: se o usuário desmarca dry_run, alerta simples */
document.addEventListener('DOMContentLoaded', function(){
  const cb = document.querySelector('input[name="dry_run"]');
  if (!cb) return;
  cb.addEventListener('change', function(){
    if (!cb.checked) {
      alert('Atenção: dry_run desmarcado. Ao executar, as tarefas serão reatribuídas no banco.');
    }
  });
});
</script>
</body>
</html>
