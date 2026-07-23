<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Relatórios */
require_once __DIR__ . '/p213_lib.php';

$cfg    = p213_config();
$classe = (int)$cfg['classe'];
$score  = p213_score($classe);
$resp   = p213_respostas();
$plano  = p213_plano_acao($classe);
$par    = p213_parametros($classe);
$prz    = p213_prazos($classe);
$etapas = p213_etapas();

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'pdf';

// ---------------------------------------------------------------------------
// Exportação CSV (abre direto no Excel: separador ";" + BOM UTF-8)
// ---------------------------------------------------------------------------
if ($tipo === 'csv') {
    p213_log('relatorio', 'csv');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="prov213_diagnostico_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Item','Etapa','Criticidade','Requisito','Base normativa','Situação',
                   'Evidência','Observação','Responsável','Data de conclusão'], ';');
    foreach (p213_catalogo_por_classe($classe) as $it) {
        $r = isset($resp[$it['cod']]) ? $resp[$it['cod']] : [];
        fputcsv($out, [
            $it['cod'], $it['etapa'], p213_criticidade($it['peso']), $it['pergunta'], $it['base'],
            p213_status_label(isset($r['status']) ? $r['status'] : 'nao_avaliado'),
            isset($r['evidencia']) ? $r['evidencia'] : '',
            isset($r['observacao']) ? $r['observacao'] : '',
            isset($r['responsavel']) ? $r['responsavel'] : '',
            !empty($r['data_conclusao']) ? date('d/m/Y', strtotime($r['data_conclusao'])) : '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------------
/** Dossiê de evidências — Anexo IV, Disposições gerais, IV a VIII. */
function rel_evidencias($cfg, $classe, $resp, $par) {
    $h = '<h2 style="text-align:center">DOSSIÊ DE EVIDÊNCIAS</h2>'
       . '<p style="text-align:center">' . p213_esc($cfg['serventia'] ?: 'Serventia extrajudicial')
       . ' — CNS ' . p213_esc($cfg['cns'] ?: '—') . '</p><hr>'
       . '<p>Forma de comprovação aplicável à Classe ' . $classe . ': <b>' . $par['comprovacao'] . '</b>. '
       . 'Para as Classes 2 e 3, este dossiê deve ser acompanhado de lista de <i>hashes</i> assinada '
       . 'digitalmente, com registro auditável de guarda, ou consolidado em documento eletrônico único '
       . 'assinado, acompanhado do respectivo relatório de hash.</p>'
       . '<table border="1" cellpadding="4" style="width:100%">'
       . '<tr><th width="8%">Item</th><th width="30%">Requisito</th><th width="14%">Situação</th>'
       . '<th width="28%">Evidência registrada</th><th width="12%">Responsável</th><th width="8%">Data</th></tr>';
    $n = 0;
    foreach (p213_catalogo_por_classe($classe) as $it) {
        $r  = isset($resp[$it['cod']]) ? $resp[$it['cod']] : [];
        $ev = isset($r['evidencia']) ? trim((string)$r['evidencia']) : '';
        if ($ev === '') continue;
        $n++;
        $h .= '<tr><td>' . p213_esc($it['cod']) . '</td>'
            . '<td>' . p213_esc(mb_substr($it['pergunta'], 0, 95)) . '</td>'
            . '<td>' . p213_status_label($r['status']) . '</td>'
            . '<td>' . p213_esc($ev) . '</td>'
            . '<td>' . p213_esc(isset($r['responsavel']) ? $r['responsavel'] : '') . '</td>'
            . '<td>' . (!empty($r['data_conclusao']) ? date('d/m/Y', strtotime($r['data_conclusao'])) : '—') . '</td></tr>';
    }
    if ($n === 0) $h .= '<tr><td colspan="6">Nenhuma evidência registrada até o momento.</td></tr>';
    $h .= '</table><p>Total de ' . $n . ' evidência(s). Guarda mínima de 5 (cinco) anos.</p>'
        . '<br><br><p style="text-align:center">_________________________________________<br><b>'
        . p213_esc($cfg['responsavel_tec'] ?: '____________________') . '</b><br>Responsável Técnico Interno</p>';
    return $h;
}

// ---------------------------------------------------------------------------
function rel_conformidade($cfg, $classe, $score, $resp, $par, $prz, $etapas) {
    $h = '<h2 style="text-align:center">RELATÓRIO DE CONFORMIDADE</h2>'
       . '<p style="text-align:center">Provimento CN-CNJ n. 213, de 20 de fevereiro de 2026</p><hr>';

    $h .= '<table border="0" cellpadding="3">'
        . '<tr><td width="30%"><b>Serventia</b></td><td>' . p213_esc($cfg['serventia'] ?: '—') . '</td></tr>'
        . '<tr><td><b>CNS</b></td><td>' . p213_esc($cfg['cns'] ?: '—') . '</td></tr>'
        . '<tr><td><b>Enquadramento</b></td><td>Classe ' . $classe . ', Subclasse ' . p213_esc($cfg['subclasse'])
        . ' (receita bruta semestral, art. 16 c/c art. 2º, XXIV, red. Prov. 243/2026)</td></tr>'
        . '<tr><td><b>Titular</b></td><td>' . p213_esc($cfg['titular'] ?: '—') . ' — ' . p213_esc($cfg['titular_qualif']) . '</td></tr>'
        . '<tr><td><b>Responsável técnico</b></td><td>' . p213_esc($cfg['responsavel_tec'] ?: '—') . '</td></tr>'
        . '<tr><td><b>Data de emissão</b></td><td>' . date('d/m/Y H:i') . '</td></tr>'
        . '</table><br>';

    $h .= '<h3>1. Índice de aderência</h3>'
        . '<p style="font-size:22pt;text-align:center"><b>' . number_format($score['geral'], 1, ',', '.') . '%</b></p>'
        . '<p style="text-align:center;font-size:8pt">' . $score['itens'] . ' requisitos aplicáveis à Classe ' . $classe
        . ' &middot; ponderação por criticidade &middot; itens não aplicáveis excluídos do denominador</p>';

    $h .= '<h3>2. Parâmetros obrigatórios da classe (Anexos I e II)</h3><table border="1" cellpadding="4">'
        . '<tr><td width="40%">RPO máximo</td><td>' . $par['rpo'] . '</td></tr>'
        . '<tr><td>RTO máximo</td><td>' . $par['rto'] . '</td></tr>'
        . '<tr><td>Cópia completa de backup</td><td>' . $par['backup_full'] . '</td></tr>'
        . '<tr><td>Conectividade de referência</td><td>' . $par['link'] . '</td></tr>'
        . '<tr><td>Teste de restauração</td><td>' . $par['teste_restauracao'] . '</td></tr>'
        . '<tr><td>Trilha de auditoria</td><td>nível ' . $par['trilha'] . ' — retenção mínima de 5 anos</td></tr>'
        . '<tr><td>Teste de intrusão</td><td>' . $par['pentest'] . '</td></tr>'
        . '<tr><td>Simulação de extração integral</td><td>' . $par['extracao'] . '</td></tr>'
        . '<tr><td>Forma de comprovação</td><td>' . $par['comprovacao'] . '</td></tr>'
        . '</table>';

    $h .= '<h3>3. Prazos</h3>'
        . '<p style="font-size:8pt">Contados da entrada em vigor do Provimento n. 243, de 21 de julho de 2026, '
        . 'que alterou os arts. 20, 22 e 23 do Provimento n. 213/2026.</p>'
        . '<table border="1" cellpadding="4">'
        . '<tr><td width="55%">Etapas 1 e 2 — implementação inicial obrigatória (art. 20)</td><td>'
        . $prz['inicial']->format('d/m/Y') . ' (' . $prz['dias_norma'] . ' dias da vigência)</td></tr>'
        . '<tr><td>Prorrogação excepcional, somatório de até 180 dias (art. 21)</td><td>até '
        . $prz['prorrogado']->format('d/m/Y') . '</td></tr>'
        . '<tr><td>Primeira avaliação técnica (art. 22, §1º, III)</td><td>'
        . $prz['avaliacao']->format('d/m/Y') . ' (12 meses da vigência)</td></tr>'
        . '<tr><td>Etapas 1 a 5 — implementação integral (art. 23)</td><td>'
        . $prz['global']->format('d/m/Y') . ' (' . $prz['meses_norma'] . ' meses da vigência)</td></tr>'
        . '</table>';

    $h .= '<h3>4. Situação por etapa</h3><table border="1" cellpadding="4">'
        . '<tr><th>Etapa</th><th>Aderência</th><th>Conformes</th><th>Parciais</th><th>Não conformes</th>'
        . '<th>Não avaliados</th><th>N/A</th><th>Apta a declarar</th></tr>';
    foreach ($etapas as $n => $nome) {
        $d = $score['etapas'][$n];
        $h .= '<tr><td>' . $n . '</td><td>' . number_format($d['pct'], 1, ',', '.') . '%</td>'
            . '<td>' . $d['conforme'] . '/' . $d['total'] . '</td><td>' . $d['parcial'] . '</td>'
            . '<td>' . $d['nao_conforme'] . '</td><td>' . $d['nao_avaliado'] . '</td>'
            . '<td>' . $d['nao_aplicavel'] . '</td>'
            . '<td>' . ($d['apto_declarar'] ? ($d['liberada'] ? 'Sim' : 'Etapa anterior pendente') : 'Não') . '</td></tr>';
    }
    $h .= '</table>';
    $h .= '<p style="font-size:8pt">A aptidão para declarar exige a inexistência de itens não conformes, parciais ou '
        . 'não avaliados na etapa, além do cumprimento integral das etapas precedentes. Anexo IV, Disposições gerais, I e II — '
        . 'as etapas são sucessivas e cumulativas, sendo vedada declaração parcial, proporcional ou condicionada.</p>';

    $h .= '<h3>5. Detalhamento dos requisitos</h3>';
    foreach ($etapas as $n => $nome) {
        $h .= '<h4>Etapa ' . $n . ' — ' . p213_esc($nome) . '</h4><table border="1" cellpadding="3">'
            . '<tr><th width="10%">Item</th><th width="52%">Requisito</th><th width="12%">Criticidade</th>'
            . '<th width="26%">Situação</th></tr>';
        foreach (p213_catalogo_por_classe($classe) as $it) {
            if ($it['etapa'] != $n) continue;
            $st = isset($resp[$it['cod']]) ? $resp[$it['cod']]['status'] : 'nao_avaliado';
            $ev = isset($resp[$it['cod']]) ? trim((string)$resp[$it['cod']]['evidencia']) : '';
            $h .= '<tr><td>' . p213_esc($it['cod']) . '</td>'
                . '<td>' . p213_esc($it['pergunta']) . '<br><i style="font-size:7pt">' . p213_esc($it['base']) . '</i></td>'
                . '<td>' . p213_criticidade($it['peso']) . '</td>'
                . '<td>' . p213_status_label($st)
                . ($ev !== '' ? '<br><i style="font-size:7pt">' . p213_esc(mb_substr($ev, 0, 140)) . '</i>' : '')
                . '</td></tr>';
        }
        $h .= '</table><br>';
    }

    $h .= '<br><p style="font-size:8pt">Este relatório sintetiza a autoavaliação registrada no sistema e não '
        . 'substitui o dossiê técnico nem as evidências documentais exigidas pelo Anexo IV. A responsabilidade pelo '
        . 'cumprimento integral dos requisitos é pessoal e indelegável do delegatário, interino ou interventor '
        . '(art. 13, §3º, e art. 14).</p>';
    return $h;
}

// ---------------------------------------------------------------------------
function rel_plano($cfg, $classe, $plano, $prz, $etapas) {
    $h = '<h2 style="text-align:center">PLANO DE AÇÃO — CONFORMIDADE AO PROVIMENTO 213/2026</h2>'
       . '<p style="text-align:center">' . p213_esc($cfg['serventia'] ?: '—') . ' — CNS ' . p213_esc($cfg['cns'] ?: '—')
       . ' — Classe ' . $classe . '</p><hr>';

    if (!$plano) {
        return $h . '<p>Nenhuma pendência registrada. Mantenha as evidências pelo prazo mínimo de 5 (cinco) anos e '
                  . 'renove anualmente a declaração do art. 17.</p>';
    }

    $h .= '<p>Total de ' . count($plano) . ' pendência(s), ordenadas por etapa e criticidade. '
        . 'Marco do art. 20 (Etapas 1 e 2): <b>' . $prz['inicial']->format('d/m/Y')
        . '</b>. Marco do art. 23 (integral): <b>' . $prz['global']->format('d/m/Y') . '</b>.</p>';

    $et = 0;
    foreach ($plano as $p) {
        if ($p['etapa'] != $et) {
            if ($et) $h .= '</table><br>';
            $et = $p['etapa'];
            $h .= '<h3>Etapa ' . $et . ' — ' . p213_esc($etapas[$et]) . '</h3>'
                . '<table border="1" cellpadding="4">'
                . '<tr><th width="9%">Item</th><th width="11%">Criticidade</th><th width="14%">Situação</th>'
                . '<th width="33%">Requisito</th><th width="33%">Providência recomendada</th></tr>';
        }
        $h .= '<tr><td>' . p213_esc($p['cod']) . '</td>'
            . '<td>' . p213_criticidade($p['peso']) . '</td>'
            . '<td>' . p213_status_label($p['status']) . '</td>'
            . '<td>' . p213_esc($p['pergunta']) . '<br><i style="font-size:7pt">' . p213_esc($p['base']) . '</i></td>'
            . '<td>' . p213_esc($p['sugestao']) . '</td></tr>';
    }
    $h .= '</table>';

    $h .= '<br><h3>Sequência recomendada</h3><ol>'
        . '<li>Fechar integralmente a <b>Etapa 1</b> (governança, PSI, MFA, ROPA, inventário, contratos) e a '
        . '<b>Etapa 2</b> (energia, aterramento com ART, ambiente físico, conectividade, PCN/PRD, endpoint, arquitetura), '
        . 'que são a implementação inicial obrigatória do art. 20.</li>'
        . '<li>Registrar a conclusão de cada etapa no Sistema Justiça Aberta, item a item, com dossiê técnico específico.</li>'
        . '<li>Evoluir para as Etapas 3 a 5 em regime progressivo de maturidade (art. 22), respeitando a ordem '
        . 'sequencial e o prazo global do art. 23.</li>'
        . '<li>Se houver inviabilidade técnica ou financeira temporária, requerer <b>antes do vencimento</b> a '
        . 'prorrogação do art. 21 (uma ou mais oportunidades, somatório de até 180 dias), com plano formal de '
        . 'adequação, cronograma, responsáveis, descrição das causas do atraso e medidas compensatórias imediatas.</li>'
        . '<li>Se a exigência for tecnicamente indisponível no mercado ou economicamente desproporcional, avaliar o '
        . 'requerimento de ressalva técnica do <b>art. 20-A</b>, instruído com laudo de profissional habilitado e, no '
        . 'mínimo, três orçamentos. A ressalva não é automática, não retroage e não alcança backup, integridade e '
        . 'autenticidade dos atos, controle de acesso e trilhas de auditoria (§4º).</li>'
        . '</ol>';
    return $h;
}

// ---------------------------------------------------------------------------
if ($tipo !== '') {
    if ($tipo === 'plano') {
        $html   = rel_plano($cfg, $classe, $plano, $prz, $etapas);
        $titulo = 'Plano de Ação';
    } elseif ($tipo === 'evidencias') {
        $html   = rel_evidencias($cfg, $classe, $resp, $par);
        $titulo = 'Dossiê de Evidências';
    } else {
        $html   = rel_conformidade($cfg, $classe, $score, $resp, $par, $prz, $etapas);
        $titulo = 'Relatório de Conformidade';
    }

    if ($modo === 'pdf' && p213_tcpdf()) {
        p213_log('relatorio', $tipo);
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Atlas — Módulo Provimento 213');
        $pdf->SetTitle($titulo);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->setPrintHeader(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);
        $css = '<style>h2{font-size:13pt}h3{font-size:10pt;color:#312e81}h4{font-size:9pt}'
             . 'th{background-color:#eef1f4;font-size:8pt}td{font-size:8pt}p{line-height:1.4}</style>';
        $pdf->writeHTML($css . $html, true, false, true, false, '');
        $pdf->Output('prov213_' . $tipo . '.pdf', 'I');
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>' . p213_esc($titulo) . '</title>'
       . '<style>body{font-family:system-ui,Arial;max-width:960px;margin:24px auto;padding:0 20px;color:#111}'
       . 'table{border-collapse:collapse;width:100%;font-size:.8rem;margin-bottom:10px}'
       . 'td,th{border:1px solid #bbb;padding:5px;vertical-align:top}th{background:#eef1f4}'
       . 'h3{color:#312e81}@media print{.noprint{display:none}}</style></head><body>'
       . '<div class="noprint" style="text-align:right"><button onclick="window.print()">Imprimir / salvar em PDF</button></div>'
       . $html . '</body></html>';
    exit;
}

$temTcpdf = p213_tcpdf();
p213_head('Relatórios — Provimento 213');
p213_hero('Relatórios', 'Documentos para o dossiê técnico e para a fiscalização correicional');
p213_nav('relatorio.php');
?>

<?php if (!$temTcpdf): ?>
  <div class="p213-alert warn">
    <i class="fa fa-exclamation-triangle"></i>
    <div>TCPDF não localizado — os relatórios abrirão em versão imprimível.</div>
  </div>
<?php endif; ?>

<div class="p213-grid g2">
  <article class="p213-doc">
    <div class="p213-doc__top">
      <h3 class="p213-doc__title"><i class="fa fa-check-square-o"></i> Relatório de conformidade</h3>
      <span class="p213-tag info"><?= number_format($score['geral'], 1, ',', '.') ?>%</span>
    </div>
    <div class="p213-doc__body">
      <p class="p213-muted" style="margin:0">
        Índice de aderência, parâmetros da Classe <?= $classe ?>, prazos dos arts. 20, 21 e 23, situação por
        etapa e detalhamento item a item com as evidências registradas.
      </p>
    </div>
    <div class="p213-actions" style="margin-top:14px">
      <a class="p213-btn p213-btn--pri p213-btn--sm" href="relatorio.php?tipo=conformidade&modo=pdf" target="_blank">
        <i class="fa fa-file-pdf-o"></i> Gerar PDF</a>
      <a class="p213-btn p213-btn--ghost p213-btn--sm" href="relatorio.php?tipo=conformidade&modo=html" target="_blank">
        <i class="fa fa-eye"></i> Pré-visualizar</a>
    </div>
  </article>

  <article class="p213-doc">
    <div class="p213-doc__top">
      <h3 class="p213-doc__title"><i class="fa fa-tasks"></i> Plano de ação</h3>
      <span class="p213-tag <?= count($plano) ? 'c3' : 'c1' ?>"><?= count($plano) ?> pendências</span>
    </div>
    <div class="p213-doc__body">
      <p class="p213-muted" style="margin:0">
        Pendências ordenadas por etapa e criticidade, com a providência recomendada para cada requisito e a
        sequência de execução em face dos marcos legais.
      </p>
    </div>
    <div class="p213-actions" style="margin-top:14px">
      <a class="p213-btn p213-btn--pri p213-btn--sm" href="relatorio.php?tipo=plano&modo=pdf" target="_blank">
        <i class="fa fa-file-pdf-o"></i> Gerar PDF</a>
      <a class="p213-btn p213-btn--ghost p213-btn--sm" href="relatorio.php?tipo=plano&modo=html" target="_blank">
        <i class="fa fa-eye"></i> Pré-visualizar</a>
    </div>
  </article>
</div>

<div class="p213-card" style="margin-top:20px">
  <div class="p213-card__head">
    <h2 class="p213-card__title"><i class="fa fa-gavel"></i> Declarações registradas</h2>
  </div>
  <div class="p213-card__body flush">
    <?php
    $res = p213_db()->query("SELECT * FROM p213_declaracoes ORDER BY etapa, criado_em DESC");
    $linhas = [];
    while ($d = $res->fetch_assoc()) $linhas[] = $d;
    ?>
    <?php if (!$linhas): ?>
      <div class="p213-empty">
        <i class="fa fa-file-o"></i>
        <p>Nenhuma declaração registrada. Use o painel para declarar a conclusão de uma etapa apta.</p>
      </div>
    <?php else: ?>
      <div class="p213-tablewrap">
        <table class="p213-table">
          <thead><tr><th style="width:70px">Etapa</th><th>Declarante</th><th>Qualificação</th>
            <th>Protocolo Justiça Aberta</th><th style="width:110px">Data</th>
            <th style="width:130px">Aderência</th></tr></thead>
          <tbody>
          <?php foreach ($linhas as $d): ?>
            <tr>
              <td class="num"><?= (int)$d['etapa'] ?></td>
              <td><strong><?= p213_esc($d['declarante']) ?></strong></td>
              <td><?= p213_esc($d['qualificacao']) ?></td>
              <td><?= p213_esc($d['protocolo_ja'] ?: '—') ?></td>
              <td><?= $d['data_registro'] ? date('d/m/Y', strtotime($d['data_registro'])) : '—' ?></td>
              <td class="num"><?= number_format((float)$d['pct_no_momento'], 1, ',', '.') ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php p213_foot(); ?>
