<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-31-reemissao-em-lote
 * (base: 2026-07-09-integracao-emissor-nacional)
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_reemissao_lib.php';
require_once __DIR__ . '/nfse_erros.php';
nfse_migrar();
nfse_reemissao_migrar();

$pdo = nfse_pdo();

$cfgAtual  = nfse_config();
$ambAtual  = (string) ($cfgAtual['ambiente'] ?? '2');

$status = $_GET['status'] ?? '';
$busca  = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['p'] ?? 1));

$fDe        = trim((string) ($_GET['de'] ?? ''));
$fAte       = trim((string) ($_GET['ate'] ?? ''));
$fAmbiente  = trim((string) ($_GET['amb'] ?? ''));
$fFuncion   = trim((string) ($_GET['func'] ?? ''));
$fValorMin  = trim((string) ($_GET['vmin'] ?? ''));
$fValorMax  = trim((string) ($_GET['vmax'] ?? ''));
$fOrdem     = (string) ($_GET['ord'] ?? 'recentes');
$fCampo     = (string) ($_GET['campo'] ?? 'auto');
if (!in_array($fCampo, ['auto', 'os', 'dps', 'nfse', 'chave', 'tomador', 'doc'], true)) {
    $fCampo = 'auto';
}
$porPagina  = (int) ($_GET['pp'] ?? 30);
if (!in_array($porPagina, [30, 60, 100, 200], true)) { $porPagina = 30; }

/* Preserva os filtros na paginação e nos links. */
$qsBase = array_filter([
    'status' => $status, 'q' => $busca, 'de' => $fDe, 'ate' => $fAte,
    'amb' => $fAmbiente, 'func' => $fFuncion, 'vmin' => $fValorMin,
    'vmax' => $fValorMax, 'ord' => $fOrdem !== 'recentes' ? $fOrdem : '',
    'pp' => $porPagina !== 30 ? $porPagina : '',
    'campo' => $fCampo !== 'auto' ? $fCampo : '',
], static fn($v) => $v !== '' && $v !== null);

$where = [];
$params = [];

if (in_array($status, ['processando', 'autorizada', 'rejeitada', 'cancelada'], true)) {
    $where[] = 'status = :st';
    $params[':st'] = $status;
}
if ($status === 'reemitir') {
    // Somente o que ainda aguarda nova tentativa.
    $where[] = "status = 'rejeitada' AND reemitida_em IS NULL AND ambiente = :ambf
                AND NOT EXISTS (SELECT 1 FROM nfse_notas v
                                 WHERE v.ordem_servico_id = nfse_notas.ordem_servico_id
                                   AND v.ambiente = nfse_notas.ambiente
                                   AND v.status IN ('autorizada','processando','cancelada'))";
    $params[':ambf'] = $ambAtual;
}
if ($busca !== '') {
    /* ------------------------------------------------------------------
     * Onde procurar
     *
     * Procurar o termo em todos os campos ao mesmo tempo parece prestativo,
     * mas atrapalha: a chave de acesso tem 50 dígitos, então um "1116"
     * digitado para achar a O.S. 1116 casa com qualquer chave que contenha
     * essa sequência em qualquer posição — e o resultado vira uma lista de
     * notas sem relação com a busca.
     *
     * Duas mudanças resolvem:
     *   1. o usuário pode dizer explicitamente em qual campo procurar;
     *   2. no modo automático, o formato do termo decide onde faz sentido
     *      procurar. Número curto é número de documento (O.S., DPS, NFS-e),
     *      e nunca trecho de chave.
     * ---------------------------------------------------------------- */
    $somenteDigitos = preg_replace('/\D/', '', $busca);
    $ehNumero = ctype_digit($busca);

    /* A conexão usa prepares NATIVOS (ATTR_EMULATE_PREPARES = false). Nesse
       modo o mesmo placeholder não pode aparecer duas vezes na consulta — o
       driver conta os marcadores e recusa a execução com "Invalid parameter
       number". Por isso cada ocorrência recebe um nome próprio. */
    $alt = [];

    switch ($fCampo) {
        case 'os':
            $alt[] = 'ordem_servico_id = :qnum1';
            $params[':qnum1'] = (int) $somenteDigitos;
            break;

        case 'dps':
            $alt[] = 'numero_dps = :qnum1';
            $params[':qnum1'] = (int) $somenteDigitos;
            break;

        case 'nfse':
            $alt[] = 'CAST(numero_nfse AS CHAR) = :qtxt';
            $params[':qtxt'] = ltrim($somenteDigitos, '0') !== '' ? ltrim($somenteDigitos, '0') : $somenteDigitos;
            break;

        case 'chave':
            $alt[] = 'REPLACE(chave_acesso, " ", "") LIKE :q1';
            $params[':q1'] = '%' . $somenteDigitos . '%';
            break;

        case 'tomador':
            $alt[] = 'tomador_nome LIKE :q1';
            $params[':q1'] = '%' . $busca . '%';
            break;

        case 'doc':
            $alt[] = 'REPLACE(REPLACE(REPLACE(tomador_doc, ".", ""), "-", ""), "/", "") LIKE :qdig';
            $params[':qdig'] = '%' . ($somenteDigitos !== '' ? $somenteDigitos : '\x00') . '%';
            break;

        default: // automático
            if ($ehNumero && strlen($busca) <= 9) {
                /* Número curto: é documento, não chave. Comparação exata,
                   sem LIKE — é isso que elimina o ruído. */
                $alt[] = 'ordem_servico_id = :qnum1';
                $alt[] = 'numero_dps = :qnum2';
                $alt[] = 'CAST(numero_nfse AS CHAR) = :qtxt';
                $params[':qnum1'] = (int) $busca;
                $params[':qnum2'] = (int) $busca;
                $params[':qtxt']  = ltrim($busca, '0') !== '' ? ltrim($busca, '0') : $busca;
            } elseif ($ehNumero && strlen($busca) >= 10) {
                /* Número longo: chave de acesso ou CPF/CNPJ. */
                $alt[] = 'REPLACE(chave_acesso, " ", "") LIKE :q1';
                $alt[] = 'REPLACE(REPLACE(REPLACE(tomador_doc, ".", ""), "-", ""), "/", "") LIKE :qdig';
                $params[':q1']   = '%' . $busca . '%';
                $params[':qdig'] = '%' . $busca . '%';
            } else {
                /* Tem letra ou pontuação: nome do tomador, ou documento
                   digitado com máscara. */
                $alt[] = 'tomador_nome LIKE :q1';
                $params[':q1'] = '%' . $busca . '%';

                if ($somenteDigitos !== '') {
                    $alt[] = 'REPLACE(REPLACE(REPLACE(tomador_doc, ".", ""), "-", ""), "/", "") LIKE :qdig';
                    $params[':qdig'] = '%' . $somenteDigitos . '%';
                }
            }
            break;
    }

    if ($alt) {
        $where[] = '(' . implode(' OR ', $alt) . ')';
    }
}

if ($fDe !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDe)) {
    $where[] = 'criado_em >= :de';
    $params[':de'] = $fDe . ' 00:00:00';
}
if ($fAte !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fAte)) {
    $where[] = 'criado_em <= :ate';
    $params[':ate'] = $fAte . ' 23:59:59';
}
if ($fAmbiente === '1' || $fAmbiente === '2') {
    $where[] = 'ambiente = :ambsel';
    $params[':ambsel'] = $fAmbiente;
}
if ($fFuncion !== '') {
    $where[] = 'criado_por = :func';
    $params[':func'] = $fFuncion;
}
if ($fValorMin !== '' && is_numeric(str_replace(',', '.', $fValorMin))) {
    $where[] = 'valor_servico >= :vmin';
    $params[':vmin'] = (float) str_replace(',', '.', $fValorMin);
}
if ($fValorMax !== '' && is_numeric(str_replace(',', '.', $fValorMax))) {
    $where[] = 'valor_servico <= :vmax';
    $params[':vmax'] = (float) str_replace(',', '.', $fValorMax);
}

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM nfse_notas $sqlWhere");
$total->execute($params);
$total = (int) $total->fetchColumn();

$ordens = [
    'recentes'   => 'id DESC',
    'antigas'    => 'id ASC',
    'maior'      => 'valor_servico DESC, id DESC',
    'menor'      => 'valor_servico ASC, id DESC',
    'tomador'    => 'tomador_nome ASC, id DESC',
    'os'         => 'ordem_servico_id DESC',
];
$orderBy = $ordens[$fOrdem] ?? $ordens['recentes'];

$offset = ($pagina - 1) * $porPagina;
$st = $pdo->prepare("SELECT * FROM nfse_notas $sqlWhere ORDER BY $orderBy LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$notas = $st->fetchAll(PDO::FETCH_ASSOC);

$resumo = $pdo->query("SELECT status, COUNT(*) c, COALESCE(SUM(valor_iss),0) iss FROM nfse_notas GROUP BY status")
              ->fetchAll(PDO::FETCH_ASSOC);

/* Quem emitiu — alimenta o filtro por funcionário. */
$funcionarios = $pdo->query(
    "SELECT DISTINCT criado_por FROM nfse_notas
      WHERE criado_por IS NOT NULL AND criado_por <> '' ORDER BY criado_por"
)->fetchAll(PDO::FETCH_COLUMN);

/* Totais do resultado filtrado — mostram o efeito do filtro em números. */
$somaFiltro = $pdo->prepare(
    "SELECT COALESCE(SUM(valor_servico),0) serv, COALESCE(SUM(valor_iss),0) iss FROM nfse_notas $sqlWhere"
);
$somaFiltro->execute($params);
$somaFiltro = $somaFiltro->fetch(PDO::FETCH_ASSOC);

/* Fila de reemissão (O.S. distintas com rejeição ainda em aberto). */
$totalReemitir = nfse_reemissao_total($ambAtual);

/* Rejeitadas que podem ter gerado NFS-e no Ambiente Nacional mesmo assim —
   caso típico de resposta de sucesso interpretada como falha. Ficam de fora
   as rejeições com código catalogado, que não geraram nota. */
/* Duas exclusões importam aqui, e a falta delas era o que fazia o botão
   anunciar 300 registros quando não havia nada de fato a sincronizar:

   1. rejeições de O.S. que JÁ TÊM nota autorizada. A tentativa falhou, a
      seguinte deu certo — não há nada para recuperar;
   2. rejeições já conferidas numa rodada anterior (`sinc_verificada_em`).
      Sem essa marca, as rejeições genuínas voltavam à fila para sempre. */
$totalSincronizar = 0;
try {
    $stSinc = $pdo->prepare(
        "SELECT COUNT(*) FROM nfse_notas n
          WHERE n.status = 'rejeitada'
            AND n.ambiente = :amb
            AND n.id_dps IS NOT NULL AND n.id_dps <> ''
            AND (n.chave_acesso IS NULL OR n.chave_acesso = '')
            AND n.sinc_verificada_em IS NULL
            AND (n.mensagem IS NULL OR n.mensagem NOT REGEXP 'E[0-9]{3,4}')
            AND NOT EXISTS (
                SELECT 1 FROM nfse_notas v
                 WHERE v.ordem_servico_id = n.ordem_servico_id
                   AND v.ambiente = n.ambiente
                   AND v.status = 'autorizada')"
    );
    $stSinc->execute([':amb' => $ambAtual]);
    $totalSincronizar = (int) $stSinc->fetchColumn();
} catch (Throwable $e) {
    $totalSincronizar = 0;   // coluna ausente em base antiga: apenas não oferece o botão
}

/* Para cada O.S. exibida nesta página, saber se já existe nota válida —
   evita oferecer "tentar novamente" onde não há mais o que emitir. */
$osComNotaValida = [];
$osDaPagina = array_values(array_unique(array_map(static fn($n) => (int) $n['ordem_servico_id'], $notas)));
if ($osDaPagina) {
    $in = implode(',', $osDaPagina);
    $rs = $pdo->query(
        "SELECT DISTINCT ordem_servico_id FROM nfse_notas
          WHERE ordem_servico_id IN ($in)
            AND status IN ('autorizada','processando','cancelada')"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rs as $osid) {
        $osComNotaValida[(int) $osid] = true;
    }
}

/** Esta linha rejeitada ainda comporta nova tentativa? */
$podeReemitir = static function (array $n) use ($osComNotaValida, $ambAtual): bool {
    return $n['status'] === 'rejeitada'
        && empty($n['reemitida_em'])
        && (string) $n['ambiente'] === $ambAtual
        && empty($osComNotaValida[(int) $n['ordem_servico_id']]);
};

$badge = static function (string $s): string {
    return match ($s) {
        'autorizada'  => '<span class="badge badge-success">Autorizada</span>',
        'rejeitada'   => '<span class="badge badge-danger">Rejeitada</span>',
        'cancelada'   => '<span class="badge badge-secondary">Cancelada</span>',
        'processando' => '<span class="badge badge-warning">Processando</span>',
        default       => '<span class="badge badge-light">' . htmlspecialchars($s) . '</span>',
    };
};
$brl = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NFS-e emitidas</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="stylesheet" href="../ui-config.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <style>
        /* Só o que é exclusivo desta tela. O resto vem de ui-config.css. */
        #lotebox {
            text-align: left; font-size: 13px; max-height: 230px; overflow: auto;
            border: 1px solid var(--border-primary, #e5e7eb); border-radius: 10px;
            padding: 10px; margin-top: 12px;
        }
        #lotebox div { padding: 3px 0; border-bottom: 1px dashed var(--border-primary, #eef2f7); }
        .progresso {
            height: 10px; background: var(--bg-tertiary, #e5e7eb);
            border-radius: 999px; overflow: hidden; margin-top: 12px;
        }
        .progresso i {
            display: block; height: 100%; width: 0;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: width .25s;
        }
    </style>
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <div class="cfg cfg-pagina">

      <section class="cfg-hero">
        <div class="cfg-titulo">
          <div class="cfg-icone"><i class="fa fa-files-o" aria-hidden="true"></i></div>
          <div>
            <h1>NFS-e emitidas</h1>
            <p class="cfg-sub">Histórico de notas do Ambiente Nacional.</p>
          </div>
        </div>
        <div class="cfg-selos">
          <?php if ($totalSincronizar > 0): ?>
            <button type="button" class="cfg-btn cfg-btn-marca" onclick="sincronizarTodas()"
                    title="Procura no Ambiente Nacional as NFS-e que já existem lá e corrige o registro local. Nada é emitido.">
              <i class="fa fa-cloud-download"></i> Sincronizar rejeitadas (<?= (int) $totalSincronizar ?>)
            </button>
          <?php endif; ?>
          <?php if ($totalReemitir > 0): ?>
            <button type="button" class="cfg-btn cfg-btn-forte" onclick="reemitirTodas()">
              <i class="fa fa-refresh"></i> Reemitir rejeitadas (<?= (int) $totalReemitir ?>)
            </button>
          <?php endif; ?>
          <a href="nfse_config.php" class="cfg-btn cfg-btn-neutro"><i class="fa fa-cog"></i> Configuração</a>
        </div>
      </section>

      <!-- ============ INDICADORES ============ -->
      <div class="cfg-kpis">
        <?php
        $mapa = ['autorizada' => 'Autorizadas', 'rejeitada' => 'Rejeitadas', 'cancelada' => 'Canceladas', 'processando' => 'Processando'];
        $classeKpi = ['autorizada' => 'cfg-kpi-ok', 'rejeitada' => 'cfg-kpi-erro', 'cancelada' => '', 'processando' => ''];
        foreach ($mapa as $k => $rot):
            $linha = null;
            foreach ($resumo as $r) { if ($r['status'] === $k) { $linha = $r; break; } }
        ?>
          <div class="cfg-kpi <?= $classeKpi[$k] ?>">
            <div class="cfg-kpi-n"><?= (int) ($linha['c'] ?? 0) ?></div>
            <div class="cfg-kpi-r"><?= $rot ?></div>
            <?php if ($k === 'autorizada'): ?>
              <div class="cfg-kpi-x">ISS <?= $brl($linha['iss'] ?? 0) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <a href="?status=reemitir" class="cfg-kpi cfg-kpi-fila">
          <div class="cfg-kpi-n"><?= (int) $totalReemitir ?></div>
          <div class="cfg-kpi-r">A reemitir</div>
          <div class="cfg-kpi-x">O.S. na fila</div>
        </a>
      </div>

      <!-- ============ FILTROS ============ -->
      <div class="cfg-card">
        <header><i class="fa fa-search"></i><h2>Buscar</h2></header>
        <div class="cfg-corpo">
          <form method="get">
            <?php
            $campos = [
                'auto'    => 'Buscar em tudo',
                'os'      => 'Nº da O.S.',
                'dps'     => 'Nº da DPS',
                'nfse'    => 'Nº da NFS-e',
                'chave'   => 'Chave de acesso',
                'tomador' => 'Nome do tomador',
                'doc'     => 'CPF/CNPJ do tomador',
            ];
            $dicas = [
                'auto'    => 'Chave, nº da O.S., nº da DPS, nº da NFS-e, nome ou CPF/CNPJ',
                'os'      => 'Número da O.S. — ex.: 1116',
                'dps'     => 'Número da DPS — ex.: 1739',
                'nfse'    => 'Número da NFS-e — ex.: 1420',
                'chave'   => 'Chave de acesso, inteira ou em parte',
                'tomador' => 'Nome, ou parte dele',
                'doc'     => 'CPF ou CNPJ, com ou sem pontuação',
            ];
            ?>
            <div class="cfg-grid">
              <div class="c3">
                <label class="cfg-rot" for="campo">Procurar em</label>
                <select name="campo" id="campo" class="cfg-in" onchange="this.form.q.focus()">
                  <?php foreach ($campos as $k => $rot): ?>
                    <option value="<?= $k ?>" <?= $fCampo === $k ? 'selected' : '' ?>><?= $rot ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="c4">
                <label class="cfg-rot" for="q">Termo</label>
                <input type="text" name="q" id="q" class="cfg-in"
                       placeholder="<?= $esc($dicas[$fCampo] ?? $dicas['auto']) ?>"
                       value="<?= $esc($busca) ?>">
              </div>
              <div class="c2">
                <label class="cfg-rot" for="status">Status</label>
                <select name="status" id="status" class="cfg-in">
                  <option value="">Todos</option>
                  <?php foreach ($mapa as $k => $rot): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $rot ?></option>
                  <?php endforeach; ?>
                  <option value="reemitir" <?= $status === 'reemitir' ? 'selected' : '' ?>>Aguardando reemissão</option>
                </select>
              </div>
              <div class="c3">
                <label class="cfg-rot" for="ord">Ordenar por</label>
                <select name="ord" id="ord" class="cfg-in">
                  <option value="recentes" <?= $fOrdem === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
                  <option value="antigas"  <?= $fOrdem === 'antigas'  ? 'selected' : '' ?>>Mais antigas</option>
                  <option value="maior"    <?= $fOrdem === 'maior'    ? 'selected' : '' ?>>Maior valor</option>
                  <option value="menor"    <?= $fOrdem === 'menor'    ? 'selected' : '' ?>>Menor valor</option>
                  <option value="tomador"  <?= $fOrdem === 'tomador'  ? 'selected' : '' ?>>Tomador (A-Z)</option>
                  <option value="os"       <?= $fOrdem === 'os'       ? 'selected' : '' ?>>Nº da O.S.</option>
                </select>
              </div>
            </div>

            <?php
            $temAvancado = ($fDe !== '' || $fAte !== '' || $fAmbiente !== '' || $fFuncion !== ''
                            || $fValorMin !== '' || $fValorMax !== '');
            ?>
            <div id="mais" class="cfg-mais <?= $temAvancado ? 'aberto' : '' ?>">
              <div class="cfg-grid">
                <div class="c3">
                  <label class="cfg-rot" for="de">Emitidas de</label>
                  <input type="date" name="de" id="de" class="cfg-in" value="<?= $esc($fDe) ?>">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="ate">Até</label>
                  <input type="date" name="ate" id="ate" class="cfg-in" value="<?= $esc($fAte) ?>">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="amb">Ambiente</label>
                  <select name="amb" id="amb" class="cfg-in">
                    <option value="">Todos</option>
                    <option value="1" <?= $fAmbiente === '1' ? 'selected' : '' ?>>Produção</option>
                    <option value="2" <?= $fAmbiente === '2' ? 'selected' : '' ?>>Homologação</option>
                  </select>
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="func">Emitida por</label>
                  <select name="func" id="func" class="cfg-in">
                    <option value="">Todos</option>
                    <?php foreach ($funcionarios as $f): ?>
                      <option value="<?= $esc($f) ?>" <?= $fFuncion === $f ? 'selected' : '' ?>><?= $esc($f) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="vmin">Valor do serviço — de</label>
                  <input type="text" name="vmin" id="vmin" class="cfg-in" placeholder="0,00" value="<?= $esc($fValorMin) ?>">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="vmax">Até</label>
                  <input type="text" name="vmax" id="vmax" class="cfg-in" placeholder="0,00" value="<?= $esc($fValorMax) ?>">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="pp">Resultados por página</label>
                  <select name="pp" id="pp" class="cfg-in">
                    <?php foreach ([30, 60, 100, 200] as $pp): ?>
                      <option value="<?= $pp ?>" <?= $porPagina === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="cfg-mais-linha">
              <span>
                <a class="cfg-link" onclick="document.getElementById('mais').classList.toggle('aberto'); return false;">
                  <i class="fa fa-sliders"></i> Mais filtros<?= $temAvancado ? ' (ativos)' : '' ?>
                </a>
                <?php if ($temAvancado || $busca !== '' || $status !== ''): ?>
                  &nbsp;·&nbsp;
                  <a href="nfse_notas.php" class="cfg-link cfg-link-erro"><i class="fa fa-times"></i> Limpar filtros</a>
                <?php endif; ?>
              </span>
              <button class="cfg-btn cfg-btn-marca"><i class="fa fa-search"></i> Filtrar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ============ RESULTADO ============ -->
      <div class="cfg-resumo">
        <span><b><?= (int) $total ?></b> nota(s) encontrada(s)</span>
        <?php if ($total > 0): ?>
          <span>· serviço <b><?= $brl($somaFiltro['serv'] ?? 0) ?></b></span>
          <span>· ISS <b><?= $brl($somaFiltro['iss'] ?? 0) ?></b></span>
        <?php endif; ?>

        <?php if ($busca !== ''):
          /* Diz onde a busca foi feita. No modo automático o critério é
             deduzido do formato do termo, e o usuário não tem como adivinhar
             isso — então a tela conta, e oferece o caminho para restringir. */
          $ondeBuscou = $campos[$fCampo] ?? '';
          if ($fCampo === 'auto') {
              $dig = preg_replace('/\D/', '', $busca);
              if (ctype_digit($busca) && strlen($busca) <= 9) {
                  $ondeBuscou = 'nº da O.S., da DPS ou da NFS-e';
              } elseif (ctype_digit($busca) && strlen($busca) >= 10) {
                  $ondeBuscou = 'chave de acesso ou CPF/CNPJ';
              } else {
                  $ondeBuscou = 'nome' . ($dig !== '' ? ' ou CPF/CNPJ' : '') . ' do tomador';
              }
          }
        ?>
          <span class="cfg-marca-busca">buscando <b><?= $esc($busca) ?></b> em <?= $esc($ondeBuscou) ?></span>
          <?php if ($fCampo === 'auto' && $total > 1): ?>
            <span style="color:var(--txt-3)">— para restringir, escolha o campo ao lado da busca.</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="cfg-tabela-caixa">
        <div class="cfg-rolagem">
          <table class="cfg-tabela">
            <thead>
              <tr>
                <th>#</th><th>O.S.</th><th>Amb.</th><th>Série/DPS</th><th>Chave / Nº NFS-e</th>
                <th>Tomador</th><th class="num">Serviço</th><th class="num">Base</th>
                <th class="num">ISS</th><th>Status</th><th>Emitida em</th><th>Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$notas): ?>
              <tr><td colspan="12" class="cfg-vazio">
                <?= ($busca !== '' || $status !== '' || $temAvancado)
                    ? 'Nenhuma nota corresponde aos filtros. Limpe-os para ver todas.'
                    : 'Nenhuma NFS-e registrada ainda.' ?>
              </td></tr>
            <?php endif; ?>
            <?php
            $tagStatus = [
                'autorizada'  => ['cfg-tag-ok', 'Autorizada'],
                'rejeitada'   => ['cfg-tag-erro', 'Rejeitada'],
                'cancelada'   => ['cfg-tag-neutro', 'Cancelada'],
                'processando' => ['cfg-tag-aviso', 'Processando'],
            ];
            foreach ($notas as $n): ?>
              <tr>
                <td><?= (int) $n['id'] ?></td>
                <td><a href="../visualizar_os.php?id=<?= (int) $n['ordem_servico_id'] ?>"><?= (int) $n['ordem_servico_id'] ?></a></td>
                <td><?= $n['ambiente'] === '1' ? 'Prod.' : 'Homol.' ?></td>
                <td><?= $esc($n['serie']) ?>/<?= (int) $n['numero_dps'] ?></td>
                <td class="chave">
                  <?php if ($n['chave_acesso']): ?>
                    <span class="cfg-chave"><?= $esc($n['chave_acesso']) ?></span>
                    <?php if ($n['numero_nfse']): ?><br><small>Nº <?= $esc($n['numero_nfse']) ?></small><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--txt-3)">—</span>
                  <?php endif; ?>

                  <?php if ($n['status'] === 'rejeitada' && $n['mensagem']):
                        $e = nfse_erro_traduzir($n['mensagem']); ?>
                    <span class="cfg-erro-msg">
                      <?= $esc($e['titulo']) ?>
                      <?php if ($e['codigo']): ?><span class="cfg-cod"><?= $esc($e['codigo']) ?></span><?php endif; ?>
                    </span>
                    <button type="button" class="cfg-btn-texto" data-msg="<?= $esc($n['mensagem']) ?>"
                            onclick="verErro(this)">retorno técnico</button>
                  <?php endif; ?>

                  <?php if (!empty($n['reemitida_em'])): ?>
                    <br><span class="cfg-tag cfg-tag-info" style="margin-top:4px">
                      <i class="fa fa-check"></i> Reemitida
                      <?= !empty($n['reemitida_nota_id']) ? ' &rarr; nota #' . (int) $n['reemitida_nota_id'] : '' ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="nome"><?= $esc($n['tomador_nome'] ?: 'Não informado') ?></td>
                <td class="num"><?= $brl($n['valor_servico']) ?></td>
                <td class="num"><?= $brl($n['base_calculo']) ?></td>
                <td class="num"><?= $brl($n['valor_iss']) ?></td>
                <td>
                  <?php [$cls, $rot] = $tagStatus[$n['status']] ?? ['cfg-tag-neutro', $n['status']]; ?>
                  <span class="cfg-tag <?= $cls ?>"><?= $esc($rot) ?></span>
                </td>
                <td><small><?= $n['criado_em'] ? date('d/m/Y H:i', strtotime($n['criado_em'])) : '—' ?></small></td>
                <td>
                  <div class="cfg-acoes-linha">
                    <?php if (in_array($n['status'], ['autorizada', 'cancelada'], true) && $n['chave_acesso']): ?>
                      <a class="cfg-ico cfg-ico-erro" title="DANFSe (PDF)" target="_blank" href="nfse_danfse.php?nota_id=<?= (int) $n['id'] ?>"><i class="fa fa-file-pdf-o"></i></a>
                      <a class="cfg-ico cfg-ico-ok" title="Recibo (impressora térmica)" target="_blank" href="nfse_recibo.php?nota_id=<?= (int) $n['id'] ?>"><i class="fa fa-print"></i></a>
                    <?php endif; ?>
                    <?php if ($n['xml_nfse']): ?>
                      <a class="cfg-ico" title="Baixar XML" href="nfse_xml.php?nota_id=<?= (int) $n['id'] ?>"><i class="fa fa-file-code-o"></i></a>
                    <?php endif; ?>
                    <?php if ($podeReemitir($n)): ?>
                      <button class="cfg-ico cfg-ico-aviso" title="Tentar emitir novamente"
                              onclick="reemitirUma(<?= (int) $n['ordem_servico_id'] ?>)"><i class="fa fa-paper-plane"></i></button>
                    <?php endif; ?>
                    <button class="cfg-ico cfg-ico-marca" title="Sincronizar" onclick="sincronizar(<?= (int) $n['id'] ?>)"><i class="fa fa-refresh"></i></button>
                    <?php if ($n['status'] === 'autorizada'): ?>
                      <button class="cfg-ico cfg-ico-erro" title="Cancelar" onclick="cancelar(<?= (int) $n['id'] ?>)"><i class="fa fa-ban"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php $paginas = (int) ceil($total / $porPagina); if ($paginas > 1): ?>
        <nav class="cfg-paginacao">
          <?php for ($i = 1; $i <= $paginas; $i++): ?>
            <a href="?<?= http_build_query($qsBase + ['p' => $i]) ?>" class="<?= $i === $pagina ? 'atual' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="../../script/jquery-3.5.1.min.js"></script>
<script src="../../script/bootstrap.bundle.min.js"></script>
<script src="../../script/sweetalert2.js"></script>
<script>
const BRL = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function verErro(btn) {
    Swal.fire({
        icon: 'error',
        title: 'Retorno do Ambiente Nacional',
        html: '<pre style="text-align:left;white-space:pre-wrap;word-break:break-word;font-size:.78rem;' +
              'max-height:340px;overflow:auto;background:#f8fafc;padding:10px;border-radius:8px">' +
              (btn.dataset.msg || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</pre>',
        width: 760,
        confirmButtonText: 'Fechar'
    });
}

/* ------------------------------------------------------------------ *
 * Sincronização em lote
 *
 * Procura no Ambiente Nacional as NFS-e que já existem lá mas ficaram
 * gravadas aqui como rejeitadas. Só consulta e corrige: nenhuma DPS é
 * emitida e nenhum número é consumido.
 * ------------------------------------------------------------------ */
async function sincronizarTodas() {
    Swal.fire({ title: 'Levantando as rejeitadas...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    let fila;
    try {
        fila = await fetch('nfse_sincronizar_lote.php?acao=fila').then(r => r.json());
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Falha', text: 'Não foi possível levantar a fila.' });
        return;
    }

    if (!fila.ok) {
        Swal.fire({ icon: 'error', title: 'Falha', text: fila.mensagem || 'Erro ao levantar a fila.' });
        return;
    }
    if (!fila.total) {
        Swal.fire({ icon: 'info', title: 'Nada a sincronizar', text: 'Não há rejeições que possam ter gerado NFS-e.' });
        return;
    }

    const conf = await Swal.fire({
        icon: 'question',
        title: 'Sincronizar ' + fila.total + ' registro(s)?',
        html: 'Cada DPS será <b>consultada</b> no Ambiente Nacional. Onde a NFS-e existir lá, o ' +
              'registro daqui é corrigido.' +
              (fila.provaveis ? '<br><br><b>' + fila.provaveis + '</b> tiveram resposta de sucesso ' +
                                'e quase certamente já estão emitidas.' : '') +
              '<br><br><small class="text-muted">Nenhuma DPS é emitida e nenhum número é consumido. ' +
              'Consultar leva cerca de um segundo por registro.</small>',
        width: 640,
        showCancelButton: true,
        confirmButtonText: 'Sincronizar',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#0f766e'
    });
    if (!conf.isConfirmed) return;

    let recuperadas = 0, semNota = 0, falhas = 0;
    let parar = false;

    Swal.fire({
        title: 'Sincronizando...',
        html: '<div id="sincstat">Preparando…</div><div class="progresso"><i id="sincbar"></i></div>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: true,
        confirmButtonText: 'Parar',
        confirmButtonColor: '#b91c1c'
    }).then(r => { if (r.isConfirmed) { parar = true; } });

    for (let k = 0; k < fila.itens.length && !parar; k++) {
        const item = fila.itens[k];

        const stat = document.getElementById('sincstat');
        if (stat) {
            stat.innerHTML = 'O.S. <b>' + item.ordem_servico_id + '</b> · DPS ' + item.numero_dps +
                             ' (' + (k + 1) + ' de ' + fila.total + ')' +
                             '<br><small>' + recuperadas + ' recuperada(s) · ' +
                             semNota + ' sem nota · ' + falhas + ' falha(s)</small>';
        }
        const bar = document.getElementById('sincbar');
        if (bar) bar.style.width = Math.round((k / fila.total) * 100) + '%';

        try {
            const res = await fetch('nfse_sincronizar_lote.php', {
                method: 'POST',
                body: new URLSearchParams({ acao: 'uma', nota_id: item.id })
            }).then(r => r.json());

            if (res.ok) { recuperadas++; }
            else if (res.sem_nota) { semNota++; }
            else { falhas++; }
        } catch (e) {
            falhas++;
        }
    }

    const bar = document.getElementById('sincbar');
    if (bar) bar.style.width = '100%';

    await Swal.fire({
        icon: recuperadas > 0 ? 'success' : 'info',
        title: parar ? 'Interrompido' : 'Concluído',
        html: '<b>' + recuperadas + '</b> nota(s) recuperada(s).<br>' +
              '<b>' + semNota + '</b> não geraram NFS-e (rejeição real).' +
              (falhas ? '<br><b>' + falhas + '</b> não puderam ser consultadas — repita mais tarde.' : '') +
              (recuperadas ? '<br><br><small class="text-muted">Faça a sincronização antes de usar ' +
                             "\"Reemitir rejeitadas\", para não emitir nota nova onde já existe uma.</small>" : '')
    });

    location.reload();
}

function sincronizar(id) {
    Swal.fire({ title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('nfse_consultar.php?nota_id=' + id)
        .then(r => r.json())
        .then(res => Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Sincronizada' : 'Falha', text: res.mensagem })
            .then(() => { if (res.ok) location.reload(); }));
}

/* ------------------------------------------------------------------ *
 * Nova tentativa — uma O.S.
 * ------------------------------------------------------------------ */
function reemitirUma(osId) {
    Swal.fire({
        icon: 'question',
        title: 'Tentar emitir novamente?',
        html: 'Uma nova DPS será gerada e transmitida ao Ambiente Nacional para a ' +
              '<b>O.S. ' + osId + '</b>.<br><small class="text-muted">A DPS rejeitada anterior não gerou ' +
              'NFS-e, por isso a reemissão não duplica nada.</small>',
        showCancelButton: true,
        confirmButtonText: 'Sim, emitir',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#0f766e'
    }).then(r => {
        if (!r.isConfirmed) return;

        Swal.fire({ title: 'Transmitindo ao Ambiente Nacional...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('nfse_reemitir.php', {
            method: 'POST',
            body: new URLSearchParams({ acao: 'emitir', os_id: osId })
        })
            .then(r => r.json())
            .then(res => Swal.fire({
                icon: res.ok ? 'success' : 'error',
                title: res.ok ? 'NFS-e emitida' : 'Ainda não foi possível emitir',
                text: res.mensagem
            }).then(() => { if (res.ok) location.reload(); }))
            .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação com o servidor.' }));
    });
}

/* ------------------------------------------------------------------ *
 * Nova tentativa — lote
 * O laço é feito aqui, uma O.S. por requisição, para não estourar o
 * tempo de execução do PHP e para o operador acompanhar o andamento.
 * ------------------------------------------------------------------ */
async function reemitirTodas() {
    Swal.fire({ title: 'Montando a fila...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    let fila;
    try {
        fila = await fetch('nfse_reemitir.php?acao=listar').then(r => r.json());
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação com o servidor.' });
        return;
    }

    if (!fila.ok) {
        Swal.fire({ icon: 'error', title: 'Erro', text: fila.mensagem || 'Não foi possível montar a fila.' });
        return;
    }
    if (!fila.total) {
        Swal.fire({ icon: 'info', title: 'Nada a reemitir', text: 'Não há rejeições pendentes no ambiente atual.' });
        return;
    }

    const linhas = fila.itens.map(i =>
        '<div>O.S. <b>' + i.os_id + '</b> — ' + (i.tomador_nome || 'Tomador não informado') +
        ' — ' + BRL(i.valor_servico) + '</div>').join('');

    const conf = await Swal.fire({
        icon: 'question',
        title: 'Reemitir ' + fila.total + ' NFS-e?',
        html: 'Cada O.S. abaixo receberá uma <b>nova DPS</b>, transmitida em sequência ao ' +
              'Ambiente Nacional.<div id="lotebox">' + linhas + '</div>',
        width: 720,
        showCancelButton: true,
        confirmButtonText: 'Emitir todas',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#0f766e'
    });
    if (!conf.isConfirmed) return;

    let ok = 0, falha = 0;
    const erros = [];

    Swal.fire({
        title: 'Emitindo...',
        html: '<div id="lotestat">Preparando…</div><div class="progresso"><i id="lotebar"></i></div>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
    });

    for (let k = 0; k < fila.itens.length; k++) {
        const item = fila.itens[k];

        const stat = document.getElementById('lotestat');
        if (stat) {
            stat.innerHTML = 'O.S. <b>' + item.os_id + '</b> (' + (k + 1) + ' de ' + fila.total + ')' +
                             '<br><small>' + ok + ' emitida(s) · ' + falha + ' com falha</small>';
        }
        const bar = document.getElementById('lotebar');
        if (bar) bar.style.width = Math.round((k / fila.total) * 100) + '%';

        try {
            const res = await fetch('nfse_reemitir.php', {
                method: 'POST',
                body: new URLSearchParams({ acao: 'emitir', os_id: item.os_id })
            }).then(r => r.json());

            if (res.ok) { ok++; } else { falha++; erros.push('O.S. ' + item.os_id + ': ' + (res.mensagem || 'falha')); }
        } catch (e) {
            falha++;
            erros.push('O.S. ' + item.os_id + ': falha de comunicação.');
        }
    }

    const bar = document.getElementById('lotebar');
    if (bar) bar.style.width = '100%';

    await Swal.fire({
        icon: falha === 0 ? 'success' : (ok === 0 ? 'error' : 'warning'),
        title: 'Concluído',
        html: '<b>' + ok + '</b> emitida(s) com sucesso.<br><b>' + falha + '</b> ainda com falha.' +
              (erros.length ? '<div id="lotebox">' + erros.map(e =>
                    '<div>' + e.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</div>').join('') + '</div>' : ''),
        width: 720,
        confirmButtonText: 'Fechar'
    });

    location.reload();
}

function cancelar(id) {
    Swal.fire({
        title: 'Cancelar NFS-e',
        html: `
            <select id="cMotivo" class="swal2-select" style="width:90%">
                <option value="1">1 — Erro na emissão</option>
                <option value="2">2 — Serviço não prestado</option>
                <option value="9">9 — Outros</option>
            </select>
            <textarea id="xMotivo" class="swal2-textarea" placeholder="Justificativa (obrigatória para 'Outros')"></textarea>
            <div style="font-size:.78rem;color:#64748b;text-align:left;padding:0 12px">
                Fora do prazo de cancelamento direto do município, o Ambiente Nacional exige análise fiscal.
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Cancelar a nota',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#dc3545',
        preConfirm: () => {
            const c = document.getElementById('cMotivo').value;
            const x = document.getElementById('xMotivo').value.trim();
            if (c === '9' && !x) { Swal.showValidationMessage('Justificativa obrigatória para "Outros".'); return false; }
            return { c, x };
        }
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('nfse_cancelar.php', {
            method: 'POST',
            body: new URLSearchParams({ nota_id: id, c_motivo: r.value.c, x_motivo: r.value.x })
        })
            .then(r => r.json())
            .then(res => Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Cancelada' : 'Falha', text: res.mensagem })
                .then(() => { if (res.ok) location.reload(); }));
    });
}
</script>
</body>
</html>
