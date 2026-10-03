<?php
/**
 * =====================================================================
 * auditoria_os.php — Auditoria das Ordens de Serviço
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-10-03-auditoria
 * RESTRITO A ADMINISTRADOR.
 *
 * Mostra todas as alterações feitas nas O.S. — edição de dados e de atos,
 * pagamentos lançados/excluídos, comprovantes, anexos, devoluções,
 * repasses, liquidações, cancelamento, entrega, NFS-e e assinaturas — com
 * a O.S. antes e depois de cada alteração e o impresso das duas versões.
 *
 * Acesse: .../os/auditoria_os.php            (todas as O.S.)
 *         .../os/auditoria_os.php?os=123     (uma O.S.)
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/../checar_acesso_de_administrador.php');
require_once __DIR__ . '/auditoria_os_view.php';

date_default_timezone_set('America/Sao_Paulo');

$erro = null;
try {
    $pdo = osaud_pdo();
    osaud_migrar($pdo);
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

/* ------------------------------------------------------------ filtros */
$validaData = static function ($v) {
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
};
$f = [
    'os'       => max(0, (int) ($_GET['os'] ?? 0)),
    'de'       => $validaData($_GET['de'] ?? ''),
    'ate'      => $validaData($_GET['ate'] ?? ''),
    'usuario'  => trim((string) ($_GET['usuario'] ?? '')),
    'acao'     => array_key_exists((string) ($_GET['acao'] ?? ''), osaud_acoes()) ? (string) $_GET['acao'] : '',
    'secao'    => (string) ($_GET['secao'] ?? ''),
    'exclusao' => ($_GET['exclusao'] ?? '') === '1' ? '1' : '',
    'q'        => trim((string) ($_GET['q'] ?? '')),
    'aba'      => in_array($_GET['aba'] ?? '', ['linha', 'os', 'legado'], true) ? $_GET['aba'] : 'linha',
    'p'        => max(1, (int) ($_GET['p'] ?? 1)),
];
if ($f['secao'] !== 'os' && !array_key_exists($f['secao'], osaud_secoes())) {
    $f['secao'] = '';
}

function aud_url(array $f, array $mudar = []): string
{
    $q = array_merge($f, $mudar);
    if (!array_key_exists('p', $mudar)) {
        $q['p'] = 1;
    }
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    if ((int) ($q['p'] ?? 1) <= 1) {
        unset($q['p']);
    }
    if (($q['aba'] ?? '') === 'linha') {
        unset($q['aba']);
    }
    return 'auditoria_os.php' . ($q ? '?' . http_build_query($q) : '');
}

/* WHERE da auditoria nova */
$where = ['1=1'];
$par = [];
if ($f['os'])       { $where[] = 'a.os_id = :os';            $par[':os'] = $f['os']; }
if ($f['de'])       { $where[] = 'a.criado_em >= :de';       $par[':de'] = $f['de'] . ' 00:00:00'; }
if ($f['ate'])      { $where[] = 'a.criado_em <= :ate';      $par[':ate'] = $f['ate'] . ' 23:59:59'; }
if ($f['usuario'] !== '') { $where[] = 'a.usuario = :usuario'; $par[':usuario'] = $f['usuario']; }
if ($f['acao'])     { $where[] = 'a.acao = :acao';           $par[':acao'] = $f['acao']; }
if ($f['secao'])    { $where[] = 'FIND_IN_SET(:secao, a.secoes)'; $par[':secao'] = $f['secao']; }
if ($f['exclusao']) { $where[] = 'a.tem_exclusao = 1'; }
if ($f['q'] !== '') {
    $where[] = '(a.resumo LIKE :q1 OR a.motivo LIKE :q2 OR o.cliente LIKE :q3)';
    $par[':q1'] = $par[':q2'] = $par[':q3'] = '%' . $f['q'] . '%';
}
$W = implode(' AND ', $where);
$FROM = 'FROM os_auditoria a LEFT JOIN ordens_de_servico o ON o.id = a.os_id';

$porPagina = 40;
$kpi = ['eventos' => 0, 'exclusoes' => 0, 'os' => 0, 'usuarios' => 0];
$linhas = $porOs = $legado = $eventosOs = [];
$total = 0;
$nomes = [];
$usuarios = [];
$osInfo = null;
$cadeia = null;
$instalado = null;
$temLegado = false;

if (!$erro) {
    try {
        $instalado = osaud_instalado_em($pdo);

        /* nomes completos */
        try {
            foreach ($pdo->query('SELECT usuario, nome_completo FROM funcionarios') as $r) {
                $nomes[$r['usuario']] = $r['nome_completo'];
            }
        } catch (Throwable $e) {
        }
        $usuarios = $pdo->query('SELECT DISTINCT usuario FROM os_auditoria WHERE usuario IS NOT NULL ORDER BY usuario')->fetchAll(PDO::FETCH_COLUMN);

        $st = $pdo->prepare("SELECT COUNT(*) eventos, COALESCE(SUM(a.tem_exclusao),0) exclusoes,
                                    COUNT(DISTINCT a.os_id) os, COUNT(DISTINCT a.usuario) usuarios $FROM WHERE $W");
        $st->execute($par);
        $kpi = $st->fetch(PDO::FETCH_ASSOC) ?: $kpi;

        $temLegado = osaud_tabela_existe($pdo, 'logs_ordens_de_servico');

        if ($f['os']) {
            /* ======== MODO O.S. ======== */
            $st = $pdo->prepare('SELECT * FROM ordens_de_servico WHERE id = ?');
            $st->execute([$f['os']]);
            $osInfo = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            $st = $pdo->prepare("SELECT a.id, a.os_id, a.acao, a.grupo, a.usuario, a.secoes, a.tem_exclusao, a.resumo, a.motivo,
                                        a.criado_em, (a.antes IS NOT NULL) tem_antes, (a.depois IS NOT NULL) tem_depois
                                 $FROM WHERE $W ORDER BY a.id DESC LIMIT 2000");
            $st->execute($par);
            $eventosOs = $st->fetchAll(PDO::FETCH_ASSOC);
            $cadeia = osaud_verificar_cadeia($pdo, $f['os']);

            if ($temLegado) {
                $sql = 'SELECT l.id, l.ordem_de_servico_id, l.cliente, l.total_os, l.editado_por, l.data_edicao
                          FROM logs_ordens_de_servico l WHERE l.ordem_de_servico_id = ?'
                     . ($instalado ? ' AND l.data_edicao < ?' : '') . ' ORDER BY l.id DESC LIMIT 500';
                $st = $pdo->prepare($sql);
                $st->execute($instalado ? [$f['os'], $instalado] : [$f['os']]);
                $legado = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($f['aba'] === 'linha') {
            $total = (int) $kpi['eventos'];
            $st = $pdo->prepare("SELECT a.id, a.os_id, a.acao, a.grupo, a.usuario, a.secoes, a.tem_exclusao, a.resumo, a.motivo,
                                        a.criado_em, o.cliente, (a.antes IS NOT NULL) tem_antes, (a.depois IS NOT NULL) tem_depois
                                 $FROM WHERE $W ORDER BY a.id DESC LIMIT $porPagina OFFSET " . (($f['p'] - 1) * $porPagina));
            $st->execute($par);
            $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($f['aba'] === 'os') {
            $total = (int) $kpi['os'];
            $st = $pdo->prepare("SELECT a.os_id, o.cliente, o.status, o.total_os, COUNT(*) n, SUM(a.tem_exclusao) exc,
                                        MIN(a.criado_em) primeiro, MAX(a.criado_em) ultimo,
                                        GROUP_CONCAT(DISTINCT a.usuario ORDER BY a.usuario SEPARATOR ', ') usuarios
                                 $FROM WHERE $W GROUP BY a.os_id, o.cliente, o.status, o.total_os
                                 ORDER BY MAX(a.id) DESC LIMIT $porPagina OFFSET " . (($f['p'] - 1) * $porPagina));
            $st->execute($par);
            $porOs = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($f['aba'] === 'legado' && $temLegado) {
            $wl = ['1=1'];
            $pl = [];
            if ($instalado)          { $wl[] = 'l.data_edicao < :inst'; $pl[':inst'] = $instalado; }
            if ($f['de'])            { $wl[] = 'l.data_edicao >= :de';  $pl[':de'] = $f['de'] . ' 00:00:00'; }
            if ($f['ate'])           { $wl[] = 'l.data_edicao <= :ate'; $pl[':ate'] = $f['ate'] . ' 23:59:59'; }
            if ($f['usuario'] !== ''){ $wl[] = 'l.editado_por = :u';    $pl[':u'] = $f['usuario']; }
            if ($f['q'] !== '')      { $wl[] = 'l.cliente LIKE :q';     $pl[':q'] = '%' . $f['q'] . '%'; }
            $WL = implode(' AND ', $wl);
            $st = $pdo->prepare("SELECT COUNT(*) FROM logs_ordens_de_servico l WHERE $WL");
            $st->execute($pl);
            $total = (int) $st->fetchColumn();
            $st = $pdo->prepare("SELECT l.id, l.ordem_de_servico_id, l.cliente, l.total_os, l.editado_por, l.data_edicao
                                   FROM logs_ordens_de_servico l WHERE $WL ORDER BY l.id DESC
                                  LIMIT $porPagina OFFSET " . (($f['p'] - 1) * $porPagina));
            $st->execute($pl);
            $legado = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

/* ------------------------------------------------------------ helpers de tela */
function aud_nome(array $nomes, ?string $u): string
{
    if ($u === null || $u === '') {
        return '<span class="mut">—</span>';
    }
    $n = $nomes[$u] ?? null;
    return $n ? osaud_h($n) . ' <span class="mut">(' . osaud_h($u) . ')</span>' : osaud_h($u);
}

function aud_chips(?string $secoes): string
{
    if (!$secoes) {
        return '';
    }
    $rot = ['os' => 'Dados da O.S.'] + array_map(static fn($s) => $s['rotulo'], osaud_secoes());
    $h = '';
    foreach (explode(',', $secoes) as $s) {
        $h .= '<span class="chip">' . osaud_h($rot[$s] ?? $s) . '</span>';
    }
    return $h;
}

function aud_resumo_curto(string $resumo, int $max = 3): string
{
    $l = array_values(array_filter(explode("\n", $resumo), 'strlen'));
    $h = '';
    foreach (array_slice($l, 0, $max) as $x) {
        $exc = preg_match('/EXCLU|removid|desfeita|excluíd/u', $x);
        $h .= '<div class="res-l' . ($exc ? ' exc' : '') . '">' . osaud_h(mb_strimwidth($x, 0, 170, '…')) . '</div>';
    }
    if (count($l) > $max) {
        $h .= '<div class="res-mais">+ ' . (count($l) - $max) . ' alteração(ões)</div>';
    }
    return $h;
}

function aud_evento_linha(array $e, array $nomes, bool $mostrarOs = true): string
{
    $ai = osaud_acao_info($e['acao']);
    $id = (int) $e['id'];
    ob_start(); ?>
    <tr class="<?= $e['tem_exclusao'] ? 'linha-exc' : '' ?>">
      <td class="nw"><b><?= date('d/m/Y', strtotime($e['criado_em'])) ?></b><br><span class="mut"><?= date('H:i:s', strtotime($e['criado_em'])) ?></span></td>
      <?php if ($mostrarOs): ?>
        <td class="nw"><a class="os-link" href="auditoria_os.php?os=<?= (int) $e['os_id'] ?>">nº <?= (int) $e['os_id'] ?></a>
          <?php if (!empty($e['cliente'])): ?><br><span class="mut cli"><?= osaud_h(mb_strimwidth($e['cliente'], 0, 32, '…')) ?></span><?php endif; ?></td>
      <?php endif; ?>
      <td class="nw"><span class="acao acao-<?= $ai['cor'] ?>"><i class="fa <?= $ai['icone'] ?>"></i> <?= osaud_h($ai['rotulo']) ?></span>
        <?php if ($e['tem_exclusao']): ?><br><span class="tag-exc"><i class="fa fa-exclamation-triangle"></i> exclusão</span><?php endif; ?></td>
      <td><?= aud_nome($nomes, $e['usuario']) ?></td>
      <td class="res"><?= aud_resumo_curto((string) $e['resumo']) ?>
        <?php if (!empty($e['motivo'])): ?><div class="motivo"><i class="fa fa-quote-left"></i> <?= osaud_h($e['motivo']) ?></div><?php endif; ?>
        <div class="chips"><?= aud_chips($e['secoes']) ?></div></td>
      <td class="acoes nw">
        <button type="button" class="btn-aud js-evento" data-id="<?= $id ?>"><i class="fa fa-search"></i> Antes × depois</button>
        <div class="imp-links">
          <?php if ($e['tem_antes']): ?><a href="auditoria_os_impresso.php?tipo=evento&id=<?= $id ?>&lado=antes" target="_blank" rel="noopener" title="Impresso antes"><i class="fa fa-print"></i> antes</a><?php endif; ?>
          <?php if ($e['tem_depois']): ?><a href="auditoria_os_impresso.php?tipo=evento&id=<?= $id ?>&lado=depois" target="_blank" rel="noopener" title="Impresso depois"><i class="fa fa-print"></i> depois</a><?php endif; ?>
        </div>
      </td>
    </tr>
    <?php return ob_get_clean();
}

function aud_paginacao(array $f, int $total, int $porPagina): string
{
    $pags = (int) ceil($total / $porPagina);
    if ($pags <= 1) {
        return '';
    }
    $p = $f['p'];
    $h = '<nav class="aud-pag">';
    $h .= $p > 1 ? '<a href="' . osaud_h(aud_url($f, ['p' => $p - 1])) . '">‹ Anterior</a>' : '<span class="dis">‹ Anterior</span>';
    $h .= '<span class="pg">Página ' . $p . ' de ' . $pags . ' · ' . number_format($total, 0, ',', '.') . ' registros</span>';
    $h .= $p < $pags ? '<a href="' . osaud_h(aud_url($f, ['p' => $p + 1])) . '">Próxima ›</a>' : '<span class="dis">Próxima ›</span>';
    return $h . '</nav>';
}

$filtroAtivo = $f['de'] || $f['ate'] || $f['usuario'] !== '' || $f['acao'] || $f['secao'] || $f['exclusao'] || $f['q'] !== '';
?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auditoria de O.S.</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{
  --a-brand:#4f46e5; --a-brand-soft:#eef2ff; --a-text:#111827; --a-text2:#4b5563; --a-mut:#6b7280;
  --a-bg:#f6f7fb; --a-card:#ffffff; --a-line:#e5e7eb; --a-line2:#f1f5f9;
  --a-ok:#047857; --a-ok-bg:#ecfdf5; --a-del:#b91c1c; --a-del-bg:#fef2f2; --a-edit:#4338ca; --a-edit-bg:#eef2ff;
  --a-info:#0e7490; --a-info-bg:#ecfeff; --a-warn-bg:#fffbeb; --a-warn:#92400e;
  --a-ins:#dcfce7; --a-delbg:#fee2e2; --a-mud:#fef9c3;
  --a-radius:12px; --a-shadow:0 1px 2px rgba(16,24,40,.06),0 1px 3px rgba(16,24,40,.08);
}
.dark-mode{
  --a-text:#f3f4f6; --a-text2:#d1d5db; --a-mut:#9ca3af; --a-bg:#111827; --a-card:#1f2937; --a-line:#374151; --a-line2:#273244;
  --a-brand-soft:#312e81; --a-ok:#6ee7b7; --a-ok-bg:#064e3b; --a-del:#fca5a5; --a-del-bg:#450a0a; --a-edit:#c7d2fe; --a-edit-bg:#312e81;
  --a-info:#67e8f9; --a-info-bg:#164e63; --a-warn-bg:#422006; --a-warn:#fcd34d;
  --a-ins:#14532d; --a-delbg:#7f1d1d; --a-mud:#713f12;
}
.aud-wrap{color:var(--a-text);font-size:14px;padding-bottom:40px}
.aud-wrap a{color:var(--a-brand)} .dark-mode .aud-wrap a{color:#a5b4fc}
.mut{color:var(--a-mut)} .nw{white-space:nowrap}

/* hero */
.aud-hero{display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:linear-gradient(135deg,#312e81 0%,#4f46e5 55%,#7c3aed 100%);
  color:#fff;border-radius:16px;padding:22px 26px;margin:6px 0 18px;box-shadow:0 10px 24px -8px rgba(79,70,229,.45)}
.aud-hero .ico{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:26px}
.aud-hero h1{font-size:24px;font-weight:800;margin:0;letter-spacing:-.01em;color:#fff}
.aud-hero p{margin:2px 0 0;opacity:.85;font-size:13px}
.aud-hero .ver{margin-left:auto;font-size:11px;background:rgba(255,255,255,.16);padding:4px 10px;border-radius:999px;white-space:nowrap}

/* cards / filtros */
.aud-card{background:var(--a-card);border:1px solid var(--a-line);border-radius:var(--a-radius);box-shadow:var(--a-shadow);padding:16px 18px;margin-bottom:16px}
.aud-filtros{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px 12px;align-items:end}
.aud-filtros label{display:block;font-size:11px;font-weight:600;color:var(--a-text2);text-transform:uppercase;letter-spacing:.04em;margin:0 0 4px}
.aud-filtros input,.aud-filtros select{width:100%;height:36px;border:1px solid var(--a-line);border-radius:8px;padding:0 10px;background:var(--a-card);color:var(--a-text);font-size:13px}
.aud-filtros .q{grid-column:span 2}
.aud-filtros .chk{display:flex;align-items:center;gap:8px;height:36px;font-size:13px;color:var(--a-text);text-transform:none;letter-spacing:0;font-weight:500}
.aud-filtros .chk input{width:16px;height:16px}
.aud-filtros .bt{display:flex;gap:8px}
.btn-p{height:36px;padding:0 16px;border-radius:8px;border:0;background:var(--a-brand);color:#fff;font-weight:600;font-size:13px;cursor:pointer;white-space:nowrap}
.btn-s{height:36px;padding:0 14px;border-radius:8px;border:1px solid var(--a-line);background:var(--a-card);color:var(--a-text2)!important;font-size:13px;display:inline-flex;align-items:center;text-decoration:none!important;white-space:nowrap}

/* KPIs */
.aud-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}
.kpi{background:var(--a-card);border:1px solid var(--a-line);border-radius:var(--a-radius);padding:14px 16px;box-shadow:var(--a-shadow)}
.kpi span{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--a-mut);font-weight:600}
.kpi b{display:block;font-size:26px;font-weight:800;line-height:1.2;margin-top:2px;font-variant-numeric:tabular-nums}
.kpi.exc b{color:var(--a-del)}

/* abas */
.aud-tabs{display:flex;gap:4px;border-bottom:1px solid var(--a-line);margin-bottom:14px;flex-wrap:wrap}
.aud-tabs a{padding:9px 14px;font-weight:600;font-size:13px;color:var(--a-text2)!important;border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none!important}
.aud-tabs a.on{color:var(--a-brand)!important;border-bottom-color:var(--a-brand)}

/* tabela principal */
.aud-lista{width:100%;border-collapse:separate;border-spacing:0}
.aud-lista th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--a-mut);font-weight:700;padding:8px 10px;border-bottom:1px solid var(--a-line);text-align:left;background:var(--a-card);position:sticky;top:0}
.aud-lista td{padding:11px 10px;border-bottom:1px solid var(--a-line2);vertical-align:top}
.aud-lista tr:hover td{background:var(--a-line2)}
.aud-lista tr.linha-exc td:first-child{box-shadow:inset 3px 0 0 var(--a-del)}
.aud-lista .cli{font-size:12px}
.os-link{font-weight:700}
.acao{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:3px 9px;border-radius:999px}
.acao-ok{background:var(--a-ok-bg);color:var(--a-ok)} .acao-del{background:var(--a-del-bg);color:var(--a-del)}
.acao-edit{background:var(--a-edit-bg);color:var(--a-edit)} .acao-info{background:var(--a-info-bg);color:var(--a-info)}
.tag-exc{display:inline-block;margin-top:5px;font-size:11px;font-weight:700;color:var(--a-del)}
.res-l{font-size:13px;line-height:1.45} .res-l.exc{color:var(--a-del);font-weight:600}
.res-mais{font-size:12px;color:var(--a-mut);margin-top:2px}
.motivo{font-size:12px;color:var(--a-warn);background:var(--a-warn-bg);border-radius:6px;padding:4px 8px;margin-top:6px;display:inline-block}
.chips{margin-top:6px} .chip{display:inline-block;font-size:11px;padding:1px 8px;border-radius:999px;background:var(--a-line2);color:var(--a-text2);margin:0 4px 4px 0;border:1px solid var(--a-line)}
.btn-aud{border:1px solid var(--a-brand);background:var(--a-brand-soft);color:var(--a-brand);font-weight:700;font-size:12px;border-radius:8px;padding:6px 10px;cursor:pointer;white-space:nowrap}
.dark-mode .btn-aud{color:#e0e7ff}
.btn-aud:hover{background:var(--a-brand);color:#fff}
.imp-links{margin-top:6px;font-size:12px;display:flex;gap:10px}
.aud-vazio{color:var(--a-mut);padding:14px 4px;margin:0}
.aud-pag{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:14px;font-size:13px}
.aud-pag .dis{color:var(--a-mut)} .aud-pag .pg{color:var(--a-text2)}

/* O.S. selecionada */
.os-cab{display:flex;flex-wrap:wrap;gap:14px 28px;align-items:flex-start}
.os-cab .num{font-size:28px;font-weight:800;line-height:1}
.os-cab .dado span{display:block;font-size:11px;text-transform:uppercase;color:var(--a-mut);font-weight:600;letter-spacing:.04em}
.os-cab .dado b{font-size:14px}
.os-cab .lnk{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
.selo{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:5px 10px;border-radius:8px}
.selo.ok{background:var(--a-ok-bg);color:var(--a-ok)} .selo.ruim{background:var(--a-del-bg);color:var(--a-del)}
.grupo{border:1px solid var(--a-line);border-radius:10px;margin-bottom:12px;overflow:hidden}
.grupo-cab{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;padding:10px 14px;background:var(--a-line2);font-size:13px}
.grupo-cab .gt{font-weight:700}
.grupo-cab .btn-aud{margin-left:auto}
.grupo .aud-lista td{border-bottom-color:var(--a-line2)}
.sec-tit{font-size:15px;font-weight:800;margin:22px 0 10px;display:flex;align-items:center;gap:8px}

/* ===== MODAL ===== */
.aud-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:20000;display:none;align-items:flex-start;justify-content:center;padding:2vh 1.5vw;overflow:auto}
.aud-modal.on{display:flex}
.aud-modal .box{background:var(--a-card);color:var(--a-text);width:min(1500px,97vw);border-radius:14px;box-shadow:0 24px 48px -12px rgba(0,0,0,.35);display:flex;flex-direction:column;max-height:96vh}
.aud-modal .hd{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--a-line)}
.aud-modal .hd h5{margin:0;font-size:17px;font-weight:800}
.aud-modal .hd .x{margin-left:auto;border:0;background:transparent;font-size:26px;line-height:1;color:var(--a-mut);cursor:pointer}
.aud-modal .hd .voltar{border:1px solid var(--a-line);background:var(--a-card);color:var(--a-text2);border-radius:8px;padding:4px 10px;font-size:12px;cursor:pointer}
.aud-modal .bd{padding:16px 18px;overflow:auto}
.carregando{padding:40px;text-align:center;color:var(--a-mut)}

/* detalhe */
.aud-aviso{background:var(--a-info-bg);color:var(--a-info);border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:12px}
.aud-meta{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px 18px;margin-bottom:14px;padding:12px 14px;background:var(--a-line2);border-radius:10px}
.aud-meta span{display:block;font-size:10.5px;text-transform:uppercase;color:var(--a-mut);font-weight:700;letter-spacing:.05em}
.aud-meta b{font-size:13px;font-weight:600;word-break:break-word} .aud-meta .ua{font-weight:400;font-size:12px}
.aud-abas{display:flex;gap:4px;border-bottom:1px solid var(--a-line);margin-bottom:14px;flex-wrap:wrap;padding:0;list-style:none}
.aud-abas a{display:block;padding:8px 12px;font-weight:600;font-size:13px;color:var(--a-text2)!important;border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none!important}
.aud-abas a.ativa{color:var(--a-brand)!important;border-bottom-color:var(--a-brand)}
.aud-resumo{margin:0 0 14px;padding-left:22px} .aud-resumo li{padding:2px 0} .aud-resumo li.exc{color:var(--a-del);font-weight:600}
.aud-sec-tit{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin:18px 0 8px;color:var(--a-text2);display:flex;align-items:center;gap:8px}
.aud-sec-tit.tem-mud{color:var(--a-brand)} .aud-sec-tit.tem-mud::after{content:'alterado';font-size:10px;background:var(--a-mud);color:var(--a-warn);padding:1px 7px;border-radius:999px;letter-spacing:0}
.tg{font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;margin-left:4px}
.tg-inc{background:var(--a-ok-bg);color:var(--a-ok)} .tg-rem{background:var(--a-del-bg);color:var(--a-del)} .tg-alt{background:var(--a-edit-bg);color:var(--a-edit)}
.aud-scroll{overflow-x:auto}
.aud-tab{width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:6px}
.aud-tab th{background:var(--a-line2);font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--a-text2);padding:6px 8px;text-align:left;border:1px solid var(--a-line);white-space:nowrap}
.aud-tab td{padding:6px 8px;border:1px solid var(--a-line);vertical-align:top}
.aud-tab-os th{width:200px;text-transform:none;font-size:12.5px;letter-spacing:0}
.aud-tab-os td{width:calc(50% - 100px)}
.aud-tab td.st{width:26px;text-align:center}
.aud-tab .num{font-variant-numeric:tabular-nums;white-space:nowrap}
.aud-tab tr.mudou td.v-antes{background:var(--a-delbg)} .aud-tab tr.mudou td.v-depois{background:var(--a-ins)}
.aud-tab tr.r-inc td{background:var(--a-ok-bg)} .aud-tab tr.r-inc td.st{color:var(--a-ok)}
.aud-tab tr.r-rem td{background:var(--a-del-bg);text-decoration:line-through;text-decoration-color:rgba(185,28,28,.5)} .aud-tab tr.r-rem td.st{color:var(--a-del);text-decoration:none}
.aud-tab tr.r-rem td:last-child{text-decoration:none}
.aud-tab tr.r-alt td.st{color:var(--a-edit)}
.aud-tab td.cel-mud{background:var(--a-mud)}
.aud-tab td.cel-mud del{display:block;color:var(--a-del);text-decoration:line-through}
.aud-tab td.cel-mud ins{display:block;color:var(--a-ok);text-decoration:none;font-weight:700}
.aud-so-mud{font-size:13px;display:flex;align-items:center;gap:6px;margin-bottom:6px}
.aud-retrato.so-mud tr.igual,.aud-retrato.so-mud .aud-bloco.sem-mud{display:none}
.aud-fin{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}
.fin-item{border:1px solid var(--a-line);border-radius:8px;padding:8px 10px}
.fin-item span{display:block;font-size:11px;color:var(--a-mut);text-transform:uppercase;font-weight:700}
.fin-item b{font-variant-numeric:tabular-nums} .fin-item.mudou{border-color:#f59e0b;background:var(--a-mud)}
.fin-item del{color:var(--a-del);font-weight:400;font-size:12px}
.btn-arq{font-size:12px;font-weight:600;white-space:nowrap}
.aud-imp{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.imp-cab{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.imp-cab b{font-size:13px;padding:3px 10px;border-radius:999px}
.imp-antes{background:var(--a-del-bg);color:var(--a-del)} .imp-depois{background:var(--a-ok-bg);color:var(--a-ok)}
.imp-frame{width:100%;height:76vh;border:1px solid var(--a-line);border-radius:8px;background:#525659}
.imp-nada{height:76vh;border:1px dashed var(--a-line);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-direction:column;color:var(--a-mut);text-align:center}
.aud-rodape{text-align:center;color:var(--a-mut);font-size:11.5px;margin-top:22px}
@media (max-width:900px){ .aud-imp{grid-template-columns:1fr} .aud-filtros .q{grid-column:span 1} .aud-lista thead{display:none}
  .aud-lista tr{display:block;border-bottom:1px solid var(--a-line);padding:8px 0} .aud-lista td{display:block;border:0;padding:4px 6px} .os-cab .lnk{margin-left:0} }
</style>
</head>
<body>
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
<div class="container-fluid aud-wrap" style="max-width:1500px">

  <div class="aud-hero">
    <div class="ico"><i class="fa fa-shield"></i></div>
    <div>
      <h1>Auditoria de O.S.</h1>
      <p>Tudo o que foi alterado nas Ordens de Serviço — com a versão anterior, a posterior e o impresso de cada uma.</p>
    </div>
    <span class="ver">Auditoria v<?= OSAUD_VERSAO ?></span>
  </div>

  <?php if ($erro): ?>
    <div class="aud-card" style="border-color:#fecaca;color:#b91c1c"><b>Não foi possível carregar a auditoria.</b><br><?= osaud_h($erro) ?></div>
  <?php endif; ?>

  <!-- ===== FILTROS ===== -->
  <form class="aud-card" method="get" action="auditoria_os.php">
    <?php if (!$f['os'] && $f['aba'] !== 'linha'): ?><input type="hidden" name="aba" value="<?= osaud_h($f['aba']) ?>"><?php endif; ?>
    <div class="aud-filtros">
      <div><label for="f-os">Nº da O.S.</label><input id="f-os" type="number" min="1" name="os" value="<?= $f['os'] ?: '' ?>" placeholder="Todas"></div>
      <div><label for="f-de">De</label><input id="f-de" type="date" name="de" value="<?= osaud_h($f['de']) ?>"></div>
      <div><label for="f-ate">Até</label><input id="f-ate" type="date" name="ate" value="<?= osaud_h($f['ate']) ?>"></div>
      <div><label for="f-u">Usuário</label>
        <select id="f-u" name="usuario"><option value="">Todos</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= osaud_h($u) ?>" <?= $f['usuario'] === $u ? 'selected' : '' ?>><?= osaud_h(($nomes[$u] ?? null) ? $nomes[$u] . ' (' . $u . ')' : $u) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="f-a">Ação</label>
        <select id="f-a" name="acao"><option value="">Todas</option>
          <?php foreach (osaud_acoes() as $k => $a): if ($k === 'alteracao') continue; ?>
            <option value="<?= $k ?>" <?= $f['acao'] === $k ? 'selected' : '' ?>><?= osaud_h($a[0]) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="f-s">O que mudou</label>
        <select id="f-s" name="secao"><option value="">Qualquer parte</option>
          <option value="os" <?= $f['secao'] === 'os' ? 'selected' : '' ?>>Dados da O.S.</option>
          <?php foreach (osaud_secoes() as $k => $s): ?>
            <option value="<?= $k ?>" <?= $f['secao'] === $k ? 'selected' : '' ?>><?= osaud_h($s['rotulo']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="q"><label for="f-q">Buscar</label><input id="f-q" type="search" name="q" value="<?= osaud_h($f['q']) ?>" placeholder="Apresentante, ato, forma de pagamento, motivo…"></div>
      <div><label class="chk"><input type="checkbox" name="exclusao" value="1" <?= $f['exclusao'] ? 'checked' : '' ?>> Só exclusões</label></div>
      <div class="bt"><button class="btn-p" type="submit"><i class="fa fa-filter"></i> Filtrar</button>
        <?php if ($filtroAtivo || $f['os']): ?><a class="btn-s" href="auditoria_os.php">Limpar</a><?php endif; ?></div>
    </div>
  </form>

  <!-- ===== KPIs ===== -->
  <div class="aud-kpis">
    <div class="kpi"><span>Alterações registradas</span><b><?= number_format((int) $kpi['eventos'], 0, ',', '.') ?></b></div>
    <div class="kpi exc"><span>Com exclusão</span><b><?= number_format((int) $kpi['exclusoes'], 0, ',', '.') ?></b></div>
    <div class="kpi"><span>O.S. alteradas</span><b><?= number_format((int) $kpi['os'], 0, ',', '.') ?></b></div>
    <div class="kpi"><span>Usuários</span><b><?= number_format((int) $kpi['usuarios'], 0, ',', '.') ?></b></div>
  </div>

<?php if ($f['os']): /* ===================== MODO O.S. ===================== */ ?>

  <div class="aud-card">
    <div class="os-cab">
      <div><span class="mut" style="font-size:11px;font-weight:700;text-transform:uppercase">O.S.</span><div class="num">nº <?= $f['os'] ?></div></div>
      <?php if ($osInfo): ?>
        <div class="dado"><span>Apresentante</span><b><?= osaud_h($osInfo['cliente'] ?? '') ?></b></div>
        <div class="dado"><span>Total</span><b><?= osaud_brl($osInfo['total_os'] ?? 0) ?></b></div>
        <div class="dado"><span>Situação</span><b><?= osaud_h($osInfo['status'] ?? '—') ?></b></div>
        <div class="dado"><span>Criada por</span><b><?= aud_nome($nomes, $osInfo['criado_por'] ?? null) ?></b></div>
      <?php else: ?>
        <div class="dado"><span>Situação</span><b style="color:var(--a-del)">Não existe mais no banco</b></div>
      <?php endif; ?>
      <div class="lnk">
        <?php if ($cadeia && $cadeia['total']): ?>
          <span class="selo <?= $cadeia['ok'] ? 'ok' : 'ruim' ?>" title="Cada registro guarda o hash do anterior; alteração manual no banco quebra a cadeia.">
            <i class="fa <?= $cadeia['ok'] ? 'fa-lock' : 'fa-unlock' ?>"></i>
            <?= $cadeia['ok'] ? 'Registros íntegros (' . $cadeia['total'] . ')' : 'Integridade violada: registro(s) #' . implode(', #', $cadeia['falhas']) ?>
          </span>
        <?php endif; ?>
        <?php if ($osInfo): ?>
          <a class="btn-s" href="auditoria_os_impresso.php?tipo=atual&os=<?= $f['os'] ?>" target="_blank" rel="noopener"><i class="fa fa-print"></i>&nbsp;Impresso atual</a>
          <a class="btn-s" href="visualizar_os.php?id=<?= $f['os'] ?>"><i class="fa fa-external-link"></i>&nbsp;Abrir O.S.</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="aud-card">
    <div class="sec-tit" style="margin-top:0"><i class="fa fa-history"></i> Linha do tempo da O.S.</div>
    <?php if (!$eventosOs): ?>
      <p class="aud-vazio">Nenhuma alteração registrada<?= $filtroAtivo ? ' com estes filtros' : '' ?>.</p>
    <?php else:
        /* agrupa gravações consecutivas da mesma sessão de trabalho */
        $grupos = [];
        foreach ($eventosOs as $e) {
            $k = count($grupos) - 1;
            if ($k >= 0 && $e['grupo'] && $grupos[$k]['grupo'] === $e['grupo']) {
                $grupos[$k]['eventos'][] = $e;
            } else {
                $grupos[] = ['grupo' => $e['grupo'], 'eventos' => [$e]];
            }
        }
        foreach ($grupos as $g):
            $evs = $g['eventos'];
            $n = count($evs);
            $exc = array_sum(array_column($evs, 'tem_exclusao'));
    ?>
      <div class="grupo">
        <?php if ($n > 1): $ini = end($evs)['criado_em']; $fim = $evs[0]['criado_em']; ?>
          <div class="grupo-cab">
            <span class="gt"><i class="fa fa-clone"></i> Sessão de trabalho · <?= $n ?> gravações</span>
            <span><?= aud_nome($nomes, $evs[0]['usuario']) ?></span>
            <span class="mut"><?= date('d/m/Y H:i', strtotime($ini)) ?> → <?= date('H:i', strtotime($fim)) ?></span>
            <?php if ($exc): ?><span class="tag-exc" style="margin:0"><i class="fa fa-exclamation-triangle"></i> <?= $exc ?> com exclusão</span><?php endif; ?>
            <button type="button" class="btn-aud js-grupo" data-os="<?= $f['os'] ?>" data-grupo="<?= osaud_h($g['grupo']) ?>"><i class="fa fa-columns"></i> Início × fim da sessão</button>
          </div>
        <?php endif; ?>
        <table class="aud-lista"><tbody>
          <?php foreach ($evs as $e) { echo aud_evento_linha($e, $nomes, false); } ?>
        </tbody></table>
      </div>
    <?php endforeach; endif; ?>

    <?php if ($legado): ?>
      <div class="sec-tit"><i class="fa fa-archive"></i> Histórico antigo <span class="mut" style="font-size:12px;font-weight:500">— cópias feitas ao abrir a edição, antes da auditoria<?= $instalado ? ' (instalada em ' . date('d/m/Y', strtotime($instalado)) . ')' : '' ?></span></div>
      <table class="aud-lista">
        <thead><tr><th>Edição aberta em</th><th>Editado por</th><th>Apresentante na época</th><th>Total na época</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($legado as $l): ?>
          <tr><td class="nw"><?= osaud_h(osaud_data_br($l['data_edicao'])) ?></td><td><?= aud_nome($nomes, $l['editado_por']) ?></td>
            <td><?= osaud_h($l['cliente']) ?></td><td class="nw"><?= osaud_brl($l['total_os']) ?></td>
            <td class="acoes nw"><button type="button" class="btn-aud js-legado" data-id="<?= (int) $l['id'] ?>"><i class="fa fa-search"></i> Antes × depois</button>
              <div class="imp-links"><a href="auditoria_os_impresso.php?tipo=legado&id=<?= (int) $l['id'] ?>" target="_blank" rel="noopener"><i class="fa fa-print"></i> impresso da época</a></div></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php else: /* ===================== VISÃO GERAL ===================== */ ?>

  <div class="aud-tabs">
    <a href="<?= osaud_h(aud_url($f, ['aba' => 'linha'])) ?>" class="<?= $f['aba'] === 'linha' ? 'on' : '' ?>"><i class="fa fa-clock-o"></i> Linha do tempo</a>
    <a href="<?= osaud_h(aud_url($f, ['aba' => 'os'])) ?>" class="<?= $f['aba'] === 'os' ? 'on' : '' ?>"><i class="fa fa-folder-open-o"></i> Por O.S.</a>
    <?php if ($temLegado): ?>
      <a href="<?= osaud_h(aud_url($f, ['aba' => 'legado'])) ?>" class="<?= $f['aba'] === 'legado' ? 'on' : '' ?>"><i class="fa fa-archive"></i> Histórico antigo</a>
    <?php endif; ?>
  </div>

  <div class="aud-card" style="padding:6px 8px">
  <?php if ($f['aba'] === 'linha'): ?>
    <?php if (!$linhas): ?>
      <p class="aud-vazio" style="padding:18px">Nenhuma alteração registrada<?= $filtroAtivo ? ' com estes filtros' : ' ainda. A partir de agora, toda alteração feita nas O.S. aparece aqui' ?>.</p>
    <?php else: ?>
      <div class="aud-scroll"><table class="aud-lista">
        <thead><tr><th>Quando</th><th>O.S.</th><th>Ação</th><th>Usuário</th><th>O que mudou</th><th></th></tr></thead>
        <tbody><?php foreach ($linhas as $e) { echo aud_evento_linha($e, $nomes, true); } ?></tbody>
      </table></div>
    <?php endif; ?>

  <?php elseif ($f['aba'] === 'os'): ?>
    <?php if (!$porOs): ?>
      <p class="aud-vazio" style="padding:18px">Nenhuma O.S. com alterações<?= $filtroAtivo ? ' com estes filtros' : '' ?>.</p>
    <?php else: ?>
      <div class="aud-scroll"><table class="aud-lista">
        <thead><tr><th>O.S.</th><th>Apresentante</th><th>Situação</th><th>Total atual</th><th>Alterações</th><th>Exclusões</th><th>Período</th><th>Usuários</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($porOs as $r): ?>
          <tr class="<?= $r['exc'] ? 'linha-exc' : '' ?>">
            <td class="nw"><a class="os-link" href="auditoria_os.php?os=<?= (int) $r['os_id'] ?>">nº <?= (int) $r['os_id'] ?></a></td>
            <td><?= osaud_h($r['cliente'] ?? '—') ?></td>
            <td class="nw"><?= osaud_h($r['status'] ?? '—') ?></td>
            <td class="nw"><?= osaud_brl($r['total_os']) ?></td>
            <td><b><?= (int) $r['n'] ?></b></td>
            <td><?= $r['exc'] ? '<b style="color:var(--a-del)">' . (int) $r['exc'] . '</b>' : '<span class="mut">0</span>' ?></td>
            <td class="nw"><?= date('d/m/Y H:i', strtotime($r['primeiro'])) ?><br><span class="mut">até <?= date('d/m/Y H:i', strtotime($r['ultimo'])) ?></span></td>
            <td><?= osaud_h($r['usuarios']) ?></td>
            <td><a class="btn-aud" style="text-decoration:none;display:inline-block" href="auditoria_os.php?os=<?= (int) $r['os_id'] ?>"><i class="fa fa-search"></i> Auditar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>

  <?php else: ?>
    <p class="mut" style="padding:10px 10px 0;margin:0;font-size:13px">
      Antes desta auditoria, o sistema só guardava uma cópia da O.S. (dados principais e atos) quando alguém <b>abria</b> a edição.
      Aqui está esse histórico<?= $instalado ? ', até ' . date('d/m/Y H:i', strtotime($instalado)) : '' ?>. O “depois” é a cópia seguinte ou, se não houver, o estado atual.</p>
    <?php if (!$legado): ?>
      <p class="aud-vazio" style="padding:18px">Nenhum registro antigo<?= $filtroAtivo ? ' com estes filtros' : '' ?>.</p>
    <?php else: ?>
      <div class="aud-scroll"><table class="aud-lista">
        <thead><tr><th>Edição aberta em</th><th>O.S.</th><th>Editado por</th><th>Apresentante na época</th><th>Total na época</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($legado as $l): ?>
          <tr><td class="nw"><?= osaud_h(osaud_data_br($l['data_edicao'])) ?></td>
            <td class="nw"><a class="os-link" href="auditoria_os.php?os=<?= (int) $l['ordem_de_servico_id'] ?>">nº <?= (int) $l['ordem_de_servico_id'] ?></a></td>
            <td><?= aud_nome($nomes, $l['editado_por']) ?></td><td><?= osaud_h($l['cliente']) ?></td><td class="nw"><?= osaud_brl($l['total_os']) ?></td>
            <td class="acoes nw"><button type="button" class="btn-aud js-legado" data-id="<?= (int) $l['id'] ?>"><i class="fa fa-search"></i> Antes × depois</button>
              <div class="imp-links"><a href="auditoria_os_impresso.php?tipo=legado&id=<?= (int) $l['id'] ?>" target="_blank" rel="noopener"><i class="fa fa-print"></i> impresso da época</a></div></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  <?php endif; ?>
  <?= aud_paginacao($f, $total, $porPagina) ?>
  </div>

<?php endif; ?>

  <div class="aud-rodape">Auditoria de O.S. v<?= OSAUD_VERSAO ?><?= $instalado ? ' · registrando desde ' . date('d/m/Y H:i', strtotime($instalado)) : '' ?> · acesso restrito a administradores</div>
</div>
</div>

<!-- ===== MODAL DE DETALHE ===== -->
<div class="aud-modal" id="audModal" aria-hidden="true">
  <div class="box" role="dialog" aria-modal="true" aria-labelledby="audModalTit">
    <div class="hd">
      <button type="button" class="voltar" id="audVoltar" hidden><i class="fa fa-arrow-left"></i> voltar</button>
      <span class="acao" id="audModalIco"></span>
      <h5 id="audModalTit">Carregando…</h5>
      <button type="button" class="x" id="audFechar" aria-label="Fechar">&times;</button>
    </div>
    <div class="bd" id="audModalBd"><div class="carregando"><i class="fa fa-spinner fa-spin"></i> Carregando…</div></div>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var modal = document.getElementById('audModal'),
      bd = document.getElementById('audModalBd'),
      tit = document.getElementById('audModalTit'),
      ico = document.getElementById('audModalIco'),
      voltar = document.getElementById('audVoltar'),
      pilha = [];

  function abrir(url, empilhar) {
    if (empilhar && modal.dataset.url) { pilha.push(modal.dataset.url); }
    voltar.hidden = pilha.length === 0;
    modal.dataset.url = url;
    modal.classList.add('on');
    document.body.style.overflow = 'hidden';
    tit.textContent = 'Carregando…';
    ico.className = 'acao'; ico.innerHTML = '';
    bd.innerHTML = '<div class="carregando"><i class="fa fa-spinner fa-spin"></i> Carregando…</div>';
    fetch(url, {credentials: 'same-origin', cache: 'no-store'})
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { throw new Error(j.mensagem || 'Falha ao carregar.'); }
        tit.textContent = j.titulo;
        ico.className = 'acao acao-' + (j.cor || 'edit');
        ico.innerHTML = '<i class="fa ' + (j.icone || 'fa-pencil') + '"></i>';
        bd.innerHTML = j.html;
        bd.scrollTop = 0;
      })
      .catch(function (e) {
        bd.innerHTML = '<div class="carregando" style="color:#b91c1c"><i class="fa fa-exclamation-triangle"></i> ' +
          String(e.message || e).replace(/</g, '&lt;') + '</div>';
      });
  }
  function fechar() {
    modal.classList.remove('on');
    document.body.style.overflow = '';
    bd.innerHTML = '';
    modal.dataset.url = '';
    pilha = [];
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.js-evento, .js-grupo, .js-legado');
    if (b) {
      ev.preventDefault();
      var dentro = !!b.closest('#audModal');
      if (b.classList.contains('js-evento')) { abrir('auditoria_os_api.php?acao=evento&id=' + b.dataset.id, dentro); }
      else if (b.classList.contains('js-grupo')) { abrir('auditoria_os_api.php?acao=grupo&os=' + b.dataset.os + '&grupo=' + encodeURIComponent(b.dataset.grupo), dentro); }
      else { abrir('auditoria_os_api.php?acao=legado&id=' + b.dataset.id, dentro); }
      return;
    }
    var aba = ev.target.closest('.aud-abas a');
    if (aba) {
      ev.preventDefault();
      var det = aba.closest('.aud-det');
      det.querySelectorAll('.aud-abas a').forEach(function (a) { a.classList.toggle('ativa', a === aba); });
      det.querySelectorAll('.aud-aba').forEach(function (p) { p.hidden = p.dataset.aba !== aba.dataset.aba; });
      if (aba.dataset.aba === 'impresso') {
        det.querySelectorAll('iframe[data-src]').forEach(function (f) { if (!f.src) { f.src = f.dataset.src; } });
      }
    }
  });
  document.addEventListener('change', function (ev) {
    if (ev.target.classList.contains('js-so-mud')) {
      ev.target.closest('.aud-aba').querySelector('.aud-retrato').classList.toggle('so-mud', ev.target.checked);
    }
  });
  document.getElementById('audFechar').addEventListener('click', fechar);
  voltar.addEventListener('click', function () { var u = pilha.pop(); if (u) { modal.dataset.url = ''; abrir(u, false); } });
  modal.addEventListener('click', function (ev) { if (ev.target === modal) { fechar(); } });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && modal.classList.contains('on')) { fechar(); } });
})();
</script>
</body>
</html>
