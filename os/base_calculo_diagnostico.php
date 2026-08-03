<?php
/**
 * =====================================================================
 * base_calculo_diagnostico.php — Conferência da detecção de faixas
 * ---------------------------------------------------------------------
 * ATLAS-OS-BASECALC-BUILD: 2026-08-01-v1
 *
 * Página SOMENTE LEITURA. Varre a tabela de emolumentos e mostra, ato a
 * ato, o que o sistema entendeu da descrição:
 *
 *   - quais atos foram identificados como FAIXA DE VALOR DECLARADO;
 *   - qual faixa foi extraída de cada um (mínimo e máximo);
 *   - quais atos NÃO foram identificados, mas têm cara de faixa
 *     (contêm "R$" na descrição) — a lista de suspeitos a revisar.
 *
 * RODE ESTA PÁGINA ANTES DE LIGAR A EXIGÊNCIA DE BASE POR ATO.
 * A redação da tabela varia entre serventias; é aqui que se confirma se
 * a leitura cobre a tabela desta serventia. Nada é gravado.
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/base_calculo_lib.php';

$pdo = getDatabaseConnection();
$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$busca = trim((string) ($_GET['q'] ?? ''));
$filtro = $_GET['f'] ?? 'todos';   // todos | faixa | suspeito | simples

$atos = $pdo->query("SELECT ATO, DESCRICAO, TOTAL FROM tabela_emolumentos ORDER BY ATO")
            ->fetchAll(PDO::FETCH_ASSOC);

$comFaixa = [];
$suspeitos = [];
$simples = [];

foreach ($atos as $a) {
    $faixa = bc_extrair_faixa($a['DESCRICAO']);

    if ($faixa) {
        $a['faixa'] = $faixa;
        $comFaixa[] = $a;
        continue;
    }

    /* Sem faixa detectada, mas com dinheiro na descrição: candidato a
       revisão manual. É aqui que aparece a redação que a leitura não
       cobriu. */
    if (preg_match('/R\$|\d{1,3}\.\d{3},\d{2}/u', (string) $a['DESCRICAO'])) {
        $suspeitos[] = $a;
    } else {
        $simples[] = $a;
    }
}

/* Agrupa as faixas por prefixo do ato, para conferir a continuidade. */
$porGrupo = [];
foreach ($comFaixa as $a) {
    $g = preg_replace('/\.\d+$/', '', (string) $a['ATO']);
    $porGrupo[$g][] = $a;
}

/* Detecta buracos e sobreposições dentro de cada grupo. */
$alertas = [];
foreach ($porGrupo as $g => $lista) {
    usort($lista, static fn($x, $y) => ($x['faixa']['minimo'] ?? 0) <=> ($y['faixa']['minimo'] ?? 0));
    for ($i = 1; $i < count($lista); $i++) {
        $ant = $lista[$i - 1]['faixa'];
        $atu = $lista[$i]['faixa'];
        if ($ant['maximo'] === null || $atu['minimo'] === null) {
            continue;
        }
        $delta = round($atu['minimo'] - $ant['maximo'], 2);
        if ($delta > 0.02) {
            $alertas[] = ['tipo' => 'buraco', 'grupo' => $g,
                          'a' => $lista[$i - 1]['ATO'], 'b' => $lista[$i]['ATO'],
                          'msg' => 'intervalo descoberto entre ' . bc_brl($ant['maximo'])
                                 . ' e ' . bc_brl($atu['minimo'])];
        } elseif ($delta < -0.02) {
            $alertas[] = ['tipo' => 'sobreposicao', 'grupo' => $g,
                          'a' => $lista[$i - 1]['ATO'], 'b' => $lista[$i]['ATO'],
                          'msg' => 'faixas se sobrepõem em ' . bc_brl(abs($delta))];
        }
    }
}

/* Itens de O.S. já lançados que exigiriam base e não têm. */
$pendentes = [];
try {
    bc_migrar($pdo);
    $rs = $pdo->query(
        "SELECT i.id, i.ordem_servico_id, i.ato, i.descricao, i.base_de_calculo, o.cliente
           FROM ordens_de_servico_itens i
           JOIN ordens_de_servico o ON o.id = i.ordem_servico_id
          WHERE (i.base_de_calculo IS NULL OR i.base_de_calculo = 0)
          ORDER BY i.ordem_servico_id DESC
          LIMIT 400"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rs as $r) {
        if (bc_exige_base($r['descricao'])) {
            $pendentes[] = $r;
        }
    }
} catch (Throwable $e) {
    error_log('[bc_diag] ' . $e->getMessage());
}

/* Aplica busca/filtro para a listagem. */
$lista = match ($filtro) {
    'faixa'    => $comFaixa,
    'suspeito' => $suspeitos,
    'simples'  => $simples,
    default    => array_merge($comFaixa, $suspeitos, $simples),
};

if ($busca !== '') {
    $b = bc_normalizar($busca);
    $lista = array_values(array_filter($lista, static function ($a) use ($b) {
        return strpos(bc_normalizar($a['ATO'] . ' ' . $a['DESCRICAO']), $b) !== false;
    }));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Base de cálculo — conferência das faixas</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<style>
  .kpi{border:1px solid #e2e8f0;border-radius:12px;padding:16px;background:#fff;text-align:center;height:100%}
  .kpi .n{font-size:1.7rem;font-weight:800;color:#0f172a;line-height:1.1}
  .kpi .l{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-top:4px}
  .kpi.faixa{border-color:#5eead4;background:#f0fdfa} .kpi.faixa .n{color:#0f766e}
  .kpi.susp{border-color:#fdba74;background:#fff7ed}  .kpi.susp .n{color:#c2410c}
  .kpi.pend{border-color:#fca5a5;background:#fef2f2}  .kpi.pend .n{color:#b91c1c}
  .tag{display:inline-block;padding:2px 9px;border-radius:999px;font-size:.7rem;font-weight:700}
  .tag.f{background:#ccfbf1;color:#0f766e} .tag.s{background:#ffedd5;color:#9a3412}
  .tag.n{background:#f1f5f9;color:#64748b}
  td.desc{max-width:520px;font-size:.84rem}
  .faixa-val{font-family:ui-monospace,monospace;font-size:.8rem;color:#0f766e;white-space:nowrap}
  mark{background:#fde68a;padding:0 2px;border-radius:3px}
</style>
</head>
<body>
<?php @include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container-fluid" style="max-width:1400px">

    <h3 class="m-0">Base de cálculo — conferência das faixas</h3>
    <p class="text-muted" style="font-size:.88rem">
      Leitura da tabela de emolumentos. Página somente leitura — nada é gravado.
    </p>
    <hr>

    <div class="alert alert-info" style="font-size:.88rem">
      <b>Confira esta página antes de ligar a exigência de base por ato.</b>
      A redação da tabela varia entre serventias. O que importa aqui é a coluna
      <b>“Suspeitos”</b>: são atos que têm dinheiro na descrição mas cuja faixa o sistema
      <b>não</b> conseguiu ler. Se essa lista tiver atos que deveriam exigir base, me envie
      dois ou três exemplos da descrição para eu ajustar a leitura.
    </div>

    <div class="row mb-4">
      <div class="col-6 col-md-3 mb-2">
        <a href="?f=faixa" style="text-decoration:none"><div class="kpi faixa">
          <div class="n"><?= count($comFaixa) ?></div>
          <div class="l">Com faixa detectada</div>
        </div></a>
      </div>
      <div class="col-6 col-md-3 mb-2">
        <a href="?f=suspeito" style="text-decoration:none"><div class="kpi susp">
          <div class="n"><?= count($suspeitos) ?></div>
          <div class="l">Suspeitos (revisar)</div>
        </div></a>
      </div>
      <div class="col-6 col-md-3 mb-2">
        <a href="?f=simples" style="text-decoration:none"><div class="kpi">
          <div class="n"><?= count($simples) ?></div>
          <div class="l">Sem valor declarado</div>
        </div></a>
      </div>
      <div class="col-6 col-md-3 mb-2">
        <div class="kpi pend">
          <div class="n"><?= count($pendentes) ?></div>
          <div class="l">Itens já lançados sem base</div>
        </div>
      </div>
    </div>

    <?php if ($alertas): ?>
      <div class="alert alert-warning">
        <b><i class="fa fa-exclamation-triangle"></i> Continuidade das faixas</b>
        <p class="mb-1 mt-1" style="font-size:.85rem">
          Buracos e sobreposições entre faixas do mesmo grupo de ato. Podem ser legítimos
          (tabelas às vezes têm degraus), mas vale conferir:
        </p>
        <ul class="mb-0" style="font-size:.83rem">
          <?php foreach (array_slice($alertas, 0, 25) as $al): ?>
            <li>
              Atos <b><?= $esc($al['a']) ?></b> e <b><?= $esc($al['b']) ?></b>:
              <?= $esc($al['msg']) ?>
            </li>
          <?php endforeach; ?>
          <?php if (count($alertas) > 25): ?>
            <li><i>… e mais <?= count($alertas) - 25 ?>.</i></li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($pendentes): ?>
      <div class="alert alert-danger">
        <b><i class="fa fa-flag"></i> Itens já lançados que exigiriam base de cálculo</b>
        <p class="mb-2 mt-1" style="font-size:.85rem">
          O.S. existentes com atos de faixa de valor, mas sem base informada. A exigência vale
          para lançamentos novos; estes ficam como estão até alguém editar a O.S. Se o sistema
          de selagem precisar da base deles, informe pela tela de edição da O.S.
        </p>
        <div class="table-responsive" style="max-height:230px;overflow:auto">
          <table class="table table-sm table-bordered bg-white mb-0">
            <thead><tr><th>O.S.</th><th>Cliente</th><th>Ato</th><th>Descrição</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($pendentes, 0, 60) as $p): ?>
                <tr>
                  <td><a href="visualizar_os.php?id=<?= (int) $p['ordem_servico_id'] ?>"><?= (int) $p['ordem_servico_id'] ?></a></td>
                  <td><small><?= $esc(mb_substr((string) $p['cliente'], 0, 40)) ?></small></td>
                  <td><small><?= $esc($p['ato']) ?></small></td>
                  <td><small><?= $esc(mb_substr((string) $p['descricao'], 0, 90)) ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($pendentes) > 60): ?>
          <small class="text-muted">Mostrando 60 de <?= count($pendentes) ?>.</small>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="get" class="form-row mb-3">
      <input type="hidden" name="f" value="<?= $esc($filtro) ?>">
      <div class="col-md-6 mb-2">
        <input type="text" name="q" class="form-control" value="<?= $esc($busca) ?>"
               placeholder="Buscar por código do ato ou trecho da descrição">
      </div>
      <div class="col-md-3 mb-2">
        <select name="f" class="form-control">
          <option value="todos"    <?= $filtro==='todos'?'selected':'' ?>>Todos os atos</option>
          <option value="faixa"    <?= $filtro==='faixa'?'selected':'' ?>>Só os com faixa</option>
          <option value="suspeito" <?= $filtro==='suspeito'?'selected':'' ?>>Só os suspeitos</option>
          <option value="simples"  <?= $filtro==='simples'?'selected':'' ?>>Só os sem valor declarado</option>
        </select>
      </div>
      <div class="col-md-2 mb-2">
        <button class="btn btn-primary btn-block"><i class="fa fa-search"></i> Filtrar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm table-bordered table-striped bg-white">
        <thead>
          <tr>
            <th style="width:100px">Ato</th>
            <th>Descrição</th>
            <th style="width:130px">Leitura</th>
            <th style="width:230px">Faixa extraída</th>
            <th style="width:110px" class="text-right">Total do ato</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($lista, 0, 600) as $a):
            $f = $a['faixa'] ?? null;
            $ehSuspeito = !$f && preg_match('/R\$|\d{1,3}\.\d{3},\d{2}/u', (string) $a['DESCRICAO']);

            /* Destaca o trecho reconhecido, para conferência visual. */
            $desc = $esc($a['DESCRICAO']);
            if ($f && $f['trecho'] !== '') {
                $pos = stripos(bc_normalizar($a['DESCRICAO']), $f['trecho']);
                if ($pos !== false) {
                    $orig = mb_substr((string) $a['DESCRICAO'], $pos, mb_strlen($f['trecho']));
                    $desc = str_replace($esc($orig), '<mark>' . $esc($orig) . '</mark>', $desc);
                }
            }
        ?>
          <tr>
            <td><code><?= $esc($a['ATO']) ?></code></td>
            <td class="desc"><?= $desc ?></td>
            <td>
              <?php if ($f): ?>
                <span class="tag f">FAIXA</span>
              <?php elseif ($ehSuspeito): ?>
                <span class="tag s">SUSPEITO</span>
              <?php else: ?>
                <span class="tag n">simples</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($f): ?>
                <span class="faixa-val"><?= $esc($f['rotulo']) ?></span><br>
                <small class="text-muted">tipo: <?= $esc($f['tipo']) ?></small>
              <?php else: ?>
                <small class="text-muted">—</small>
              <?php endif; ?>
            </td>
            <td class="text-right"><?= $esc(bc_brl($a['TOTAL'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Nenhum ato encontrado.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (count($lista) > 600): ?>
      <p class="text-muted"><small>Mostrando 600 de <?= count($lista) ?>. Use a busca para refinar.</small></p>
    <?php endif; ?>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
</body>
</html>
