<?php
/**
 * ATLAS O.S. — Parametrização do município no Ambiente Nacional
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-parametrizacao-municipal
 *
 * A hipótese que esta tela testa
 * ------------------------------
 * A SEFIN não recebe a alíquota do ISSQN na DPS — ela CALCULA, a partir da
 * parametrização que a prefeitura mantém no Painel Administrativo
 * Municipal. Cada alíquota tem vigência, com data inicial e final.
 *
 * Se a vigência da alíquota do código de tributação usado pelo cartório
 * terminou, a Calculadora de Tributos fica sem alíquota aplicável no
 * momento de gerar a nota. E é aí que a coisa fecha com o que se observou:
 *
 *  - a consulta de NFS-e responde 200, porque não passa pela calculadora;
 *  - uma DPS com CPF de tomador inválido recebe 400 com código E0206,
 *    porque é barrada na validação de negócio ANTES do cálculo;
 *  - todas as demais chegam ao cálculo e derrubam a aplicação — HTTP 500
 *    genérico, sem código.
 *
 * Isso também explicaria por que o problema é só deste município: uma
 * parametrização vencida é local, não nacional.
 *
 * E, ao contrário de um defeito no ambiente nacional, isto a prefeitura
 * resolve sozinha, no Painel Administrativo Municipal.
 *
 * Acesse: .../os/nfse/nfse_parametros_municipio.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(120);

function pm_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/** Normaliza uma data vinda da API para timestamp, aceitando os formatos usuais. */
function pm_ts(?string $d): ?int
{
    $d = trim((string) $d);
    if ($d === '') { return null; }
    $t = strtotime($d);
    return $t ?: null;
}

$erro = null;
$cfg = null;
$convenio = null;
$hoje = null;
$naData = null;
$historico = null;
$dataRef = $_POST['data_ref'] ?? '';

try {
    nfse_migrar();
    $cfg = nfse_config(true);
    $codServico = nfse_formatar_cod_servico($cfg['ctrib_nac'] ?: '210101');

    // Data de referência: por padrão, a da última nota autorizada.
    if ($dataRef === '') {
        $ult = nfse_pdo()->query(
            "SELECT criado_em FROM nfse_notas WHERE status='autorizada' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $dataRef = $ult ? date('Y-m-d', strtotime($ult)) : date('Y-m-d', strtotime('-7 days'));
    }

    try { $convenio = nfse_testar_convenio(); } catch (Throwable $e) { $convenio = ['erro' => $e->getMessage()]; }
    try { $hoje = nfse_consultar_aliquota(null, date('Y-m-d')); } catch (Throwable $e) { $hoje = ['erro' => $e->getMessage()]; }
    try { $naData = nfse_consultar_aliquota(null, $dataRef); } catch (Throwable $e) { $naData = ['erro' => $e->getMessage()]; }

    try {
        $nfse = nfse_cliente($cfg);
        $historico = $nfse->contribuinte()->consultarHistoricoAliquotas($cfg['cod_municipio'], $codServico);
    } catch (Throwable $e) {
        $historico = ['erro' => $e->getMessage()];
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

/** Extrai a lista plana de alíquotas de uma resposta do SDK. */
function pm_lista($res): array
{
    $out = [];
    $aliq = null;

    if (is_object($res) && isset($res->aliquotas)) {
        $aliq = $res->aliquotas;
    } elseif (is_array($res) && isset($res['aliquotas'])) {
        $aliq = is_object($res['aliquotas']) ? ($res['aliquotas']->aliquotas ?? null) : $res['aliquotas'];
        if (is_object($aliq) && isset($aliq->aliquotas)) { $aliq = $aliq->aliquotas; }
    }

    if (!$aliq) { return $out; }

    foreach ((array) $aliq as $servico => $itens) {
        foreach ((array) $itens as $i) {
            $out[] = [
                'servico'    => is_string($servico) ? $servico : '',
                'incidencia' => is_object($i) ? ($i->incidencia ?? null) : ($i['Incidencia'] ?? null),
                'aliquota'   => is_object($i) ? ($i->aliquota ?? null)   : ($i['Aliq'] ?? null),
                'ini'        => is_object($i) ? ($i->dataInicio ?? null) : ($i['DtIni'] ?? null),
                'fim'        => is_object($i) ? ($i->dataFim ?? null)    : ($i['DtFim'] ?? null),
            ];
        }
    }

    return $out;
}

$listaHoje = is_array($hoje) && isset($hoje['erro']) ? [] : pm_lista($hoje['aliquotas'] ?? $hoje);
$listaData = is_array($naData) && isset($naData['erro']) ? [] : pm_lista($naData['aliquotas'] ?? $naData);
$listaHist = (is_array($historico) && isset($historico['erro'])) ? [] : pm_lista($historico);

/* Há vigência cobrindo hoje? */
$cobreHoje = false;
$agora = strtotime(date('Y-m-d'));
foreach ($listaHist ?: $listaHoje as $a) {
    $i = pm_ts($a['ini']); $f = pm_ts($a['fim']);
    if (($i === null || $i <= $agora) && ($f === null || $f >= $agora)) { $cobreHoje = true; break; }
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Parametrização do município — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --erro:#dc2626; --alerta:#d97706; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.6; }
    .wrap{ max-width:960px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 18px; color:var(--cinza); }
    h3{ font-size:15px; margin:0 0 10px; }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .veredito{ border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .v-ok{ background:#dcfce7; border:1px solid var(--ok); }
    .v-erro{ background:#fef2f2; border:1px solid var(--erro); }
    table{ border-collapse:collapse; width:100%; font-size:13px; }
    th,td{ border:1px solid var(--borda); padding:7px 9px; text-align:left; }
    th{ background:#f1f5f9; }
    tr.vencida td{ background:#fef2f2; }
    tr.vigente td{ background:#f0fdf4; font-weight:600; }
    input[type=date]{ padding:8px 10px; border:1px solid var(--borda); border-radius:6px; font-size:14px; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:9px 20px;
            font-size:14px; font-weight:600; cursor:pointer; }
    pre{ background:#0f172a; color:#e2e8f0; padding:10px; border-radius:6px; overflow:auto; max-height:220px;
         font-size:12px; margin:8px 0 0; white-space:pre-wrap; word-break:break-word; }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Parametrização do município</h1>
<p>A alíquota do ISSQN não vai na DPS — a SEFIN calcula a partir do que a prefeitura parametriza.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo pm_h($erro); ?></div>
<?php else: ?>

    <?php if ($listaHist || $listaHoje): ?>
        <div class="veredito <?php echo $cobreHoje ? 'v-ok' : 'v-erro'; ?>">
            <h3 style="margin:0 0 6px">
                <?php echo $cobreHoje
                    ? 'Há alíquota vigente para hoje.'
                    : 'NÃO há alíquota vigente para hoje.'; ?>
            </h3>
            <p style="margin:0">
                <?php if ($cobreHoje): ?>
                    A parametrização cobre a data atual, então a Calculadora de Tributos tem com o que
                    trabalhar. Esta hipótese cai — a causa do 500 está em outro ponto.
                <?php else: ?>
                    A vigência da alíquota do código <code><?php echo pm_h(nfse_formatar_cod_servico($cfg['ctrib_nac'] ?: '210101')); ?></code>
                    não alcança a data de hoje. Sem alíquota aplicável, a Calculadora de Tributos não
                    consegue gerar a nota — e é exatamente no cálculo que a emissão está quebrando.
                    <strong>Isso a prefeitura corrige no Painel Administrativo Municipal</strong>,
                    prorrogando ou recadastrando a vigência.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="painel">
        <h3>Histórico de vigências da alíquota</h3>
        <p style="margin:0 0 8px; color:var(--cinza)">
            Município <?php echo pm_h($cfg['cod_municipio']); ?> ·
            serviço <code><?php echo pm_h(nfse_formatar_cod_servico($cfg['ctrib_nac'] ?: '210101')); ?></code>
        </p>
        <?php if ($listaHist): ?>
            <table>
                <tr><th>Incidência</th><th>Alíquota</th><th>Início</th><th>Fim</th><th>Situação</th></tr>
                <?php foreach ($listaHist as $a):
                    $i = pm_ts($a['ini']); $f = pm_ts($a['fim']);
                    $vig = (($i === null || $i <= $agora) && ($f === null || $f >= $agora));
                    $venc = ($f !== null && $f < $agora);
                ?>
                <tr class="<?php echo $vig ? 'vigente' : ($venc ? 'vencida' : ''); ?>">
                    <td><?php echo pm_h($a['incidencia'] ?: '—'); ?></td>
                    <td><?php echo $a['aliquota'] !== null ? pm_h(number_format((float) $a['aliquota'], 2, ',', '.')) . '%' : '—'; ?></td>
                    <td><?php echo $i ? date('d/m/Y', $i) : '—'; ?></td>
                    <td><?php echo $f ? date('d/m/Y', $f) : '(sem fim)'; ?></td>
                    <td><?php echo $vig ? 'vigente hoje' : ($venc ? 'vencida' : 'futura'); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php elseif (is_array($historico) && isset($historico['erro'])): ?>
            <pre><?php echo pm_h($historico['erro']); ?></pre>
        <?php else: ?>
            <p style="margin:0">A API não devolveu histórico de alíquotas para este serviço.</p>
        <?php endif; ?>
    </div>

    <div class="painel">
        <h3>Alíquota por competência</h3>
        <form method="post" style="margin-bottom:12px">
            <label for="data_ref" style="font-weight:600">Comparar com a data:</label>
            <input type="date" id="data_ref" name="data_ref" value="<?php echo pm_h($dataRef); ?>">
            <button type="submit">Consultar</button>
            <span style="color:var(--cinza); margin-left:8px">(por padrão, o dia da última nota autorizada)</span>
        </form>

        <table>
            <tr><th style="width:30%">Competência</th><th>Retorno</th></tr>
            <tr>
                <td><strong>Hoje</strong> — <?php echo date('d/m/Y'); ?></td>
                <td><?php
                    if (is_array($hoje) && isset($hoje['erro'])) {
                        echo '<span style="color:#b91c1c">' . pm_h($hoje['erro']) . '</span>';
                    } elseif ($listaHoje) {
                        foreach ($listaHoje as $a) {
                            echo pm_h(number_format((float) $a['aliquota'], 2, ',', '.')) . '% ('
                               . pm_h($a['incidencia'] ?: '—') . ')<br>';
                        }
                    } else { echo '<span style="color:#b45309">nenhuma alíquota retornada</span>'; }
                ?></td>
            </tr>
            <tr>
                <td><?php echo pm_h(date('d/m/Y', strtotime($dataRef))); ?></td>
                <td><?php
                    if (is_array($naData) && isset($naData['erro'])) {
                        echo '<span style="color:#b91c1c">' . pm_h($naData['erro']) . '</span>';
                    } elseif ($listaData) {
                        foreach ($listaData as $a) {
                            echo pm_h(number_format((float) $a['aliquota'], 2, ',', '.')) . '% ('
                               . pm_h($a['incidencia'] ?: '—') . ')<br>';
                        }
                    } else { echo '<span style="color:#b45309">nenhuma alíquota retornada</span>'; }
                ?></td>
            </tr>
        </table>
        <p style="margin:12px 0 0; color:var(--cinza)">
            Se a data antiga devolve alíquota e hoje não devolve, achamos a causa.
        </p>
    </div>

    <div class="painel">
        <h3>Parâmetros do convênio</h3>
        <pre><?php echo pm_h(json_encode(
            (is_array($convenio) && isset($convenio['erro'])) ? $convenio : ($convenio['parametros'] ?? $convenio),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )); ?></pre>
    </div>

    <div class="painel">
        <h3>Se a alíquota estiver vencida</h3>
        <p style="margin:0 0 8px">
            O canal de atendimento da Receita para NFS-e está desativado, e a própria página orienta
            procurar a prefeitura — o que, neste caso, é o caminho certo mesmo: quem parametriza
            alíquota e vigência é o município, no
            <a href="https://www.nfse.gov.br/PainelMunicipal" target="_blank" rel="noopener">Painel
            Administrativo Municipal</a>.
        </p>
        <p style="margin:0; color:var(--cinza)">
            Procure a Secretaria de Finanças de <?php echo pm_h($cfg['cod_municipio']); ?> e peça a
            conferência da vigência da alíquota do código
            <code><?php echo pm_h(nfse_formatar_cod_servico($cfg['ctrib_nac'] ?: '210101')); ?></code>
            (serviços de registros públicos, cartorários e notariais). Leve a tabela acima: ela mostra
            a data exata em que a vigência terminou.
        </p>
    </div>

<?php endif; ?>

</div>
</body>
</html>
