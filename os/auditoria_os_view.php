<?php
/**
 * =====================================================================
 * auditoria_os_view.php — Montagem do HTML de detalhe da auditoria
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-10-03-auditoria
 * Usado por auditoria_os_api.php. Só funções; nada é impresso ao incluir.
 * =====================================================================
 */
require_once __DIR__ . '/auditoria_os_lib.php';

if (!function_exists('osaud_h')) {

function osaud_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function osaud_data_br(?string $v): string
{
    if (!$v) {
        return '—';
    }
    $t = strtotime($v);
    return $t ? date('d/m/Y H:i:s', $t) : $v;
}

/** Colunas exibidas em cada seção (as que existirem nos dados). */
function osaud_colunas(string $sec, array $linhas): array
{
    $pref = [
        'itens'        => ['ordem_exibicao', 'ato', 'descricao', 'quantidade', 'quantidade_liquidada', 'desconto_legal', 'base_de_calculo', 'emolumentos', 'ferc', 'fadep', 'femp', 'ferrfis', 'total', 'status'],
        'pagamentos'   => ['id', 'data_pagamento', 'forma_de_pagamento', 'total_pagamento', 'funcionario', 'observacao', 'status'],
        'comprovantes' => ['id', 'pagamento_id', 'nome_original', 'mime', 'tamanho', 'enviado_por', 'enviado_em'],
        'anexos'       => ['id', 'caminho_anexo', 'data', 'funcionario', 'status'],
        'devolucoes'   => ['id', 'data_devolucao', 'forma_devolucao', 'total_devolucao', 'funcionario', 'status'],
        'repasses'     => ['id', 'data_repasse', 'forma_repasse', 'total_repasse', 'funcionario', 'status'],
        'liquidacoes'  => ['_origem', 'ato', 'descricao', 'quantidade_liquidada', 'total', 'funcionario', 'data', 'data_liquidacao', 'selo', 'status'],
        'entregas'     => ['recebido_por', 'recebido_doc', 'entregue_por', 'entregue_em', 'data_entrega', 'observacoes'],
        'nfse'         => ['id', 'numero_nfse', 'numero', 'chave_acesso', 'status', 'valor_servico', 'valor_iss', 'criado_em', 'emitida_em', 'cancelada_em'],
        'assinaturas'  => ['tipo', 'assinado_por', 'assinante_cert', 'assinado_em', 'assinatura_codigo'],
    ];
    $existe = [];
    foreach ($linhas as $l) {
        foreach ($l as $k => $v) {
            $existe[$k] = true;
        }
    }
    $cols = array_values(array_filter($pref[$sec] ?? [], static fn($c) => isset($existe[$c])));
    if (!$cols) {
        $cols = array_slice(array_values(array_filter(array_keys($existe), static fn($c) => strpos($c, '_') !== 0)), 0, 10);
    }
    return $cols;
}

function osaud_celula(string $campo, $v): string
{
    if ($campo === '_origem') {
        return osaud_h($v ?? '—');
    }
    $txt = osaud_fmt_valor($campo, $v);
    if ($campo === 'tamanho' && is_numeric($v)) {
        $txt = number_format($v / 1024, 0, ',', '.') . ' KB';
    }
    $cls = osaud_campo_monetario($campo) ? ' class="num"' : '';
    return '<span' . $cls . '>' . nl2br(osaud_h($txt)) . '</span>';
}

/** Tabela "Campo | Antes | Depois" dos dados da O.S. */
function osaud_html_os(?array $a, ?array $d): string
{
    $a = $a ?? [];
    $d = $d ?? [];
    $ordem = ['cliente', 'cpf_cliente', 'descricao_os', 'observacoes', 'total_os', 'base_de_calculo', 'status',
              'motivo_cancelamento', 'cancelado_por', 'cancelado_em', 'criado_por', 'data_criacao'];
    $campos = array_unique(array_merge(
        array_values(array_filter($ordem, static fn($c) => array_key_exists($c, $a) || array_key_exists($c, $d))),
        array_keys($a), array_keys($d)
    ));
    $h = '<table class="aud-tab aud-tab-os"><thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead><tbody>';
    foreach ($campos as $c) {
        if ($c === 'id' || strpos($c, '_') === 0) {
            continue;
        }
        $va = $a[$c] ?? null;
        $vd = $d[$c] ?? null;
        $mud = !osaud_iguais($va, $vd);
        $h .= '<tr class="' . ($mud ? 'mudou' : 'igual') . '"><th>' . osaud_h(osaud_rotulo_campo($c)) . '</th>'
            . '<td class="' . ($mud ? 'v-antes' : '') . '">' . osaud_celula($c, $va) . '</td>'
            . '<td class="' . ($mud ? 'v-depois' : '') . '">' . osaud_celula($c, $vd) . '</td></tr>';
    }
    return $h . '</tbody></table>';
}

/**
 * Tabela unificada de uma seção: linhas incluídas (verde), removidas
 * (vermelho, riscadas), alteradas (célula antes → depois) e iguais.
 * $link(string $sec, string $lado, array $linha): ?string
 */
function osaud_html_secao(string $sec, ?array $la, ?array $ld, ?callable $link = null): string
{
    if ($la === null && $ld === null) {
        return '<p class="aud-vazio">Não registrado nesta versão.</p>';
    }
    $la = $la ?? [];
    $ld = $ld ?? [];
    $ia = [];
    foreach ($la as $r) {
        $ia[osaud_chave_linha($r)] = $r;
    }
    $id = [];
    foreach ($ld as $r) {
        $id[osaud_chave_linha($r)] = $r;
    }
    if (!$ia && !$id) {
        return '<p class="aud-vazio">Nenhum registro.</p>';
    }

    $cols = osaud_colunas($sec, array_merge($la, $ld));
    $temArquivo = in_array($sec, ['comprovantes', 'anexos'], true) && $link;

    $h = '<div class="aud-scroll"><table class="aud-tab"><thead><tr><th class="st"></th>';
    foreach ($cols as $c) {
        $h .= '<th>' . osaud_h($c === '_origem' ? 'Origem' : osaud_rotulo_campo($c)) . '</th>';
    }
    if ($temArquivo) {
        $h .= '<th></th>';
    }
    $h .= '</tr></thead><tbody>';

    $linha = function (string $estado, ?array $ra, ?array $rd) use ($cols, $temArquivo, $link, $sec) {
        $ref = $rd ?? $ra;
        $rotEstado = ['inc' => 'Incluído', 'rem' => 'Removido', 'alt' => 'Alterado', 'igual' => ''][$estado];
        $icone = ['inc' => 'fa-plus', 'rem' => 'fa-minus', 'alt' => 'fa-pencil', 'igual' => ''][$estado];
        $t = '<tr class="r-' . $estado . ($estado === 'igual' ? ' igual' : ' mudou') . '"><td class="st">'
           . ($icone ? '<i class="fa ' . $icone . '" title="' . $rotEstado . '"></i>' : '') . '</td>';
        foreach ($cols as $c) {
            $va = $ra[$c] ?? null;
            $vd = $rd[$c] ?? null;
            if ($estado === 'alt' && !osaud_iguais($va, $vd)) {
                $t .= '<td class="cel-mud"><del>' . osaud_celula($c, $va) . '</del><ins>' . osaud_celula($c, $vd) . '</ins></td>';
            } else {
                $t .= '<td>' . osaud_celula($c, $ref[$c] ?? null) . '</td>';
            }
        }
        if ($temArquivo) {
            $lado = $rd !== null ? 'depois' : 'antes';
            $url = $link($sec, $lado, $ref);
            $t .= '<td>' . ($url ? '<a class="btn-arq" href="' . osaud_h($url) . '" target="_blank" rel="noopener"><i class="fa fa-eye"></i> ver</a>' : '') . '</td>';
        }
        return $t . '</tr>';
    };

    foreach ($id as $k => $rd) {
        if (!isset($ia[$k])) {
            $h .= $linha('inc', null, $rd);
        } else {
            $h .= $linha(osaud_campos_alterados($ia[$k], $rd) ? 'alt' : 'igual', $ia[$k], $rd);
        }
    }
    foreach ($ia as $k => $ra) {
        if (!isset($id[$k])) {
            $h .= $linha('rem', $ra, null);
        }
    }
    return $h . '</tbody></table></div>';
}

/** Resumo financeiro lado a lado. */
function osaud_html_financeiro(?array $a, ?array $d): string
{
    $ta = $a ? osaud_totais($a) : ['pagamentos' => null, 'devolucoes' => null, 'repasses' => null];
    $td = $d ? osaud_totais($d) : ['pagamentos' => null, 'devolucoes' => null, 'repasses' => null];
    $osA = $a['os']['total_os'] ?? null;
    $osD = $d['os']['total_os'] ?? null;
    $saldo = static function ($os, $t) {
        if ($os === null || $t['pagamentos'] === null) {
            return null;
        }
        return $t['pagamentos'] - (float) ($t['devolucoes'] ?? 0) - (float) $os - (float) ($t['repasses'] ?? 0);
    };
    $linhas = [
        ['Total da O.S.', $osA, $osD],
        ['Pago', $ta['pagamentos'], $td['pagamentos']],
        ['Devolvido', $ta['devolucoes'], $td['devolucoes']],
        ['Repassado', $ta['repasses'], $td['repasses']],
        ['Saldo', $saldo($osA, $ta), $saldo($osD, $td)],
    ];
    $h = '<div class="aud-fin">';
    foreach ($linhas as [$rot, $va, $vd]) {
        $mud = !osaud_iguais($va === null ? null : round((float) $va, 2), $vd === null ? null : round((float) $vd, 2));
        $h .= '<div class="fin-item' . ($mud ? ' mudou' : '') . '"><span>' . osaud_h($rot) . '</span>'
            . '<b>' . ($mud ? '<del>' . osaud_brl($va) . '</del> ' : '') . osaud_brl($vd ?? $va) . '</b></div>';
    }
    return $h . '</div>';
}

/**
 * HTML completo do detalhe.
 * $ctx: titulo, subtitulo, meta (rótulo => valor), resumo (lista), antes, depois,
 *       diff, impresso (['antes'=>url|null,'depois'=>url|null]), link (callable),
 *       eventos (lista p/ grupo), aviso (string)
 */
function osaud_html_detalhe(array $ctx): string
{
    $a = $ctx['antes'];
    $d = $ctx['depois'];
    $diff = $ctx['diff'];
    $secs = osaud_secoes();
    $alteradas = array_flip(osaud_secoes_alteradas($diff));

    ob_start(); ?>
<div class="aud-det">
  <?php if (!empty($ctx['aviso'])): ?>
    <div class="aud-aviso"><i class="fa fa-info-circle"></i> <?= $ctx['aviso'] ?></div>
  <?php endif; ?>

  <div class="aud-meta">
    <?php foreach ($ctx['meta'] as $rot => $val): if ($val === null || $val === '') continue; ?>
      <div><span><?= osaud_h($rot) ?></span><b><?= $val ?></b></div>
    <?php endforeach; ?>
  </div>

  <ul class="nav aud-abas" role="tablist">
    <li><a href="#" class="ativa" data-aba="alteracoes"><i class="fa fa-random"></i> Alterações</a></li>
    <li><a href="#" data-aba="retrato"><i class="fa fa-columns"></i> O.S. completa: antes × depois</a></li>
    <li><a href="#" data-aba="impresso"><i class="fa fa-print"></i> Impresso antes × depois</a></li>
    <?php if (!empty($ctx['eventos'])): ?>
      <li><a href="#" data-aba="eventos"><i class="fa fa-list-ol"></i> Gravações (<?= count($ctx['eventos']) ?>)</a></li>
    <?php endif; ?>
  </ul>

  <!-- ===== ALTERAÇÕES ===== -->
  <div class="aud-aba" data-aba="alteracoes">
    <?php if (osaud_diff_vazio($diff)): ?>
      <p class="aud-vazio">Não há diferença entre as duas versões.</p>
    <?php else: ?>
      <ol class="aud-resumo">
        <?php foreach ($ctx['resumo'] as $l): ?>
          <li class="<?= (preg_match('/EXCLU|removid|desfeita|excluíd/u', $l) ? 'exc' : '') ?>"><?= osaud_h($l) ?></li>
        <?php endforeach; ?>
      </ol>

      <?php if ($diff['os']['campos']): ?>
        <h6 class="aud-sec-tit"><i class="fa fa-file-text-o"></i> Dados da O.S.</h6>
        <table class="aud-tab aud-tab-os"><thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead><tbody>
          <?php foreach ($diff['os']['campos'] as $c): ?>
            <tr class="mudou"><th><?= osaud_h(osaud_rotulo_campo($c['campo'])) ?></th>
              <td class="v-antes"><?= osaud_celula($c['campo'], $c['antes']) ?></td>
              <td class="v-depois"><?= osaud_celula($c['campo'], $c['depois']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>

      <?php foreach ($diff['secoes'] as $sec => $ds):
          $antesSec = []; $depoisSec = [];
          foreach ($ds['removidos'] as $r) { $antesSec[] = $r; }
          foreach ($ds['incluidos'] as $r) { $depoisSec[] = $r; }
          foreach ($ds['alterados'] as $x) { $antesSec[] = $x['antes']; $depoisSec[] = $x['depois']; }
      ?>
        <h6 class="aud-sec-tit"><i class="fa <?= $secs[$sec]['icone'] ?>"></i> <?= osaud_h($secs[$sec]['rotulo']) ?>
          <small>
            <?= $ds['incluidos'] ? '<span class="tg tg-inc">+' . count($ds['incluidos']) . '</span>' : '' ?>
            <?= $ds['removidos'] ? '<span class="tg tg-rem">−' . count($ds['removidos']) . '</span>' : '' ?>
            <?= $ds['alterados'] ? '<span class="tg tg-alt">✎' . count($ds['alterados']) . '</span>' : '' ?>
          </small></h6>
        <?= osaud_html_secao($sec, $antesSec, $depoisSec, $ctx['link'] ?? null) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ===== RETRATO COMPLETO ===== -->
  <div class="aud-aba" data-aba="retrato" hidden>
    <label class="aud-so-mud"><input type="checkbox" class="js-so-mud"> Mostrar só o que mudou</label>
    <div class="aud-retrato">
      <h6 class="aud-sec-tit"><i class="fa fa-calculator"></i> Financeiro</h6>
      <?= osaud_html_financeiro($a, $d) ?>

      <h6 class="aud-sec-tit <?= isset($alteradas['os']) ? 'tem-mud' : '' ?>"><i class="fa fa-file-text-o"></i> Dados da O.S.</h6>
      <?= osaud_html_os($a['os'] ?? null, $d['os'] ?? null) ?>

      <?php foreach ($secs as $sec => $s):
          $la = $a === null ? [] : ($a[$sec] ?? null);
          $ld = $d === null ? [] : ($d[$sec] ?? null);
          if (is_array($la) && is_array($ld) && !$la && !$ld) continue;
      ?>
        <div class="aud-bloco <?= isset($alteradas[$sec]) ? 'tem-mud' : 'sem-mud' ?>">
          <h6 class="aud-sec-tit <?= isset($alteradas[$sec]) ? 'tem-mud' : '' ?>"><i class="fa <?= $s['icone'] ?>"></i> <?= osaud_h($s['rotulo']) ?></h6>
          <?= osaud_html_secao($sec, $la, $ld, $ctx['link'] ?? null) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ===== IMPRESSO ===== -->
  <div class="aud-aba" data-aba="impresso" hidden>
    <div class="aud-imp">
      <?php foreach (['antes' => 'Antes da alteração', 'depois' => 'Depois da alteração'] as $lado => $rot):
          $url = $ctx['impresso'][$lado] ?? null; ?>
        <div class="imp-col">
          <div class="imp-cab">
            <b class="imp-<?= $lado ?>"><?= $rot ?></b>
            <?php if ($url): ?>
              <a href="<?= osaud_h($url) ?>" target="_blank" rel="noopener" class="btn-arq"><i class="fa fa-external-link"></i> abrir em nova aba</a>
            <?php endif; ?>
          </div>
          <?php if ($url): ?>
            <iframe class="imp-frame" data-src="<?= osaud_h($url) ?>" title="<?= osaud_h($rot) ?>"></iframe>
          <?php else: ?>
            <div class="imp-nada"><i class="fa fa-ban"></i><br><?= $lado === 'antes' ? 'A O.S. ainda não existia.' : 'Não há versão posterior.' ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($ctx['eventos'])): ?>
  <div class="aud-aba" data-aba="eventos" hidden>
    <table class="aud-tab"><thead><tr><th>Data/hora</th><th>Ação</th><th>O que mudou</th><th></th></tr></thead><tbody>
      <?php foreach ($ctx['eventos'] as $e): $ai = osaud_acao_info($e['acao']); ?>
        <tr><td class="nw"><?= osaud_data_br($e['criado_em']) ?></td>
          <td class="nw"><span class="acao acao-<?= $ai['cor'] ?>"><i class="fa <?= $ai['icone'] ?>"></i> <?= osaud_h($ai['rotulo']) ?></span></td>
          <td><?= nl2br(osaud_h($e['resumo'])) ?></td>
          <td><a href="#" class="btn-arq js-evento" data-id="<?= (int) $e['id'] ?>">detalhes</a></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

} // function_exists
