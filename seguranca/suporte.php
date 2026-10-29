<?php
/**
 * ATLAS · Proteção de rotas — página da equipe de suporte (1.0.0)
 * ----------------------------------------------------------------------
 * Não aparece em nenhum menu do Atlas e não usa os usuários do sistema:
 * entra só quem tem a senha do suporte (hash em seguranca/suporte_acesso.php).
 * Mostra os pedidos recusados pela proteção e permite mudar as opções dela.
 */
require_once __DIR__ . '/guarda.php';

const SUP_MAX_FALHAS = 5;          // senhas erradas por IP…
const SUP_JANELA = 900;            // …em 15 minutos bloqueiam novas tentativas
const SUP_OCIOSO = 7200;           // sessão do suporte expira após 2 h sem uso

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
// Sessão própria do suporte (cookie separado): não mexe na sessão de quem usa o Atlas no mesmo navegador.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
session_name('ATLASSUPORTE');
session_set_cookie_params(['lifetime' => 0, 'path' => dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')) . '/', 'httponly' => true, 'samesite' => 'Strict']);
session_start();

function sup_json(array $d, int $st = 200): void
{
    http_response_code($st);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function sup_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/* ---------- limite de tentativas (por IP, em arquivo protegido, com trava) ---------- */
/** Abre o arquivo de tentativas com trava exclusiva: ler, conferir e gravar acontecem juntos. */
function sup_travar()
{
    $h = @fopen(atlas_guarda_dados() . '/suporte_falhas.php', 'c+');
    if ($h) {
        flock($h, LOCK_EX);
    }
    return $h;
}

function sup_falhas($h): array
{
    $t = $h ? (string) stream_get_contents($h, -1, 0) : '';
    $p = strpos($t, "\n");
    $j = $p === false ? [] : (json_decode(substr($t, $p + 1), true) ?: []);
    $lim = time() - SUP_JANELA;
    foreach ($j as $ip => $lista) {
        $j[$ip] = array_values(array_filter((array) $lista, function ($ts) use ($lim) { return $ts >= $lim; }));
        if (!$j[$ip]) {
            unset($j[$ip]);
        }
    }
    return $j;
}

function sup_gravar_falhas($h, array $j): void
{
    if (!$h) {
        return;
    }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, "<?php http_response_code(404); exit; ?>\n" . json_encode($j));
    fflush($h);
}

function sup_logado(): bool
{
    $s = $_SESSION['atlas_suporte'] ?? null;
    if (!is_array($s) || ($s['ate'] ?? 0) < time()) {
        unset($_SESSION['atlas_suporte']);
        return false;
    }
    $_SESSION['atlas_suporte']['ate'] = time() + SUP_OCIOSO;
    return true;
}

/* ---------- dados da tela ---------- */
function sup_dados(string $ini, string $fim): array
{
    $cfg = atlas_guarda_config();
    $iniDt = $ini . ' 00:00:00';
    $fimDt = $fim . ' 23:59:59';
    $dias = max(1, (int) ceil((time() - strtotime($iniDt)) / 86400) + 1);
    $lista = array_values(array_filter(atlas_guarda_bloqueios($dias, 20000), function ($e) use ($iniDt, $fimDt) {
        return ($e['em'] ?? '') >= $iniDt && ($e['em'] ?? '') <= $fimDt;
    }));

    $porMotivo = ['token' => 0, 'origem' => 0, 'sessao' => 0];
    $porUsuario = $porIp = $porRota = $porDia = [];
    foreach ($lista as $e) {
        $m = $e['motivo'] ?? '?';
        $porMotivo[$m] = ($porMotivo[$m] ?? 0) + 1;
        if ($m !== 'sessao') {   // "sem login" costuma ser só sessão expirada
            $u = (string) ($e['usuario'] ?? '');
            $u = $u === '' ? '(sem login)' : $u;
            $porUsuario[$u] = ($porUsuario[$u] ?? 0) + 1;
            $porIp[$e['ip'] ?? '?'] = ($porIp[$e['ip'] ?? '?'] ?? 0) + 1;
            $porRota[$e['rota'] ?? '?'] = ($porRota[$e['rota'] ?? '?'] ?? 0) + 1;
        }
        $d = substr((string) ($e['em'] ?? ''), 0, 10);
        $k = $m === 'sessao' ? 'sessao' : 'externo';
        $porDia[$d][$k] = ($porDia[$d][$k] ?? 0) + 1;
    }
    arsort($porUsuario);
    arsort($porIp);
    arsort($porRota);
    $top = function (array $a) {
        $o = [];
        foreach (array_slice($a, 0, 10, true) as $k => $v) {
            $o[] = ['k' => (string) $k, 'v' => $v];
        }
        return $o;
    };
    $serie = [];
    for ($d = new DateTimeImmutable($ini); $d <= new DateTimeImmutable($fim) && count($serie) < 400; $d = $d->modify('+1 day')) {
        $k = $d->format('Y-m-d');
        $serie[] = ['d' => $k, 'externo' => $porDia[$k]['externo'] ?? 0, 'sessao' => $porDia[$k]['sessao'] ?? 0];
    }

    // nomes completos e dados da serventia
    $nomes = [];
    $serventia = ['nome' => '', 'cns' => ''];
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        include dirname(__DIR__) . '/db_connection.php';   // $conn (mysqli)
        if (isset($conn) && $conn instanceof mysqli) {
            $r = $conn->query('SELECT razao_social, cns FROM cadastro_serventia LIMIT 1');
            if ($r && ($l = $r->fetch_assoc())) {
                $serventia = ['nome' => (string) $l['razao_social'], 'cns' => (string) $l['cns']];
            }
            $logins = array_values(array_unique(array_filter(array_merge(array_keys($porUsuario), array_column(array_slice($lista, 0, 300), 'usuario')),
                function ($u) { return is_string($u) && $u !== '' && $u[0] !== '('; })));
            if ($logins) {
                $st = $conn->prepare('SELECT usuario, nome_completo FROM funcionarios WHERE usuario IN (' . implode(',', array_fill(0, count($logins), '?')) . ')');
                $st->bind_param(str_repeat('s', count($logins)), ...$logins);
                $st->execute();
                foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $l) {
                    $nomes[$l['usuario']] = $l['nome_completo'];
                }
                $st->close();
            }
        }
    } catch (Throwable $e) {
    }

    $raiz = __DIR__;
    return [
        'serventia' => $serventia,
        'config' => [
            'modo' => $cfg['modo'], 'moldura' => (bool) $cfg['moldura'], 'ocultar_links' => (bool) $cfg['ocultar_links'],
            'estrito' => (bool) $cfg['estrito'], 'emergencia' => is_file($raiz . '/dados/DESLIGAR'),
            'contexto_seguro' => atlas_guarda__contexto_seguro(),
            'config_local' => is_file($raiz . '/config.local.php'),
            'alterado_em' => atlas_guarda_config_painel()['alterado_em'] ?? null, 'versao' => ATLAS_GUARDA,
        ],
        'totais' => ['total' => count($lista), 'externos' => count($lista) - $porMotivo['sessao'], 'usuarios' => count($porUsuario), 'ips' => count($porIp)],
        'por_motivo' => $porMotivo,
        'por_usuario' => $top($porUsuario),
        'por_ip' => $top($porIp),
        'por_rota' => $top($porRota),
        'serie' => $serie,
        'recentes' => array_slice($lista, 0, 300),
        'nomes' => $nomes,
    ];
}

/* ---------- ações ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'entrar') {
        $h = sup_travar();   // pedidos simultâneos esperam aqui, um de cada vez
        $falhas = sup_falhas($h);
        $ip = sup_ip();
        $n = count($falhas[$ip] ?? []);
        if ($n >= SUP_MAX_FALHAS) {
            sup_json(['ok' => false, 'erro' => 'Muitas tentativas erradas. Aguarde 15 minutos.'], 429);
        }
        $acesso = is_file(__DIR__ . '/suporte_acesso.php') ? include __DIR__ . '/suporte_acesso.php' : [];
        $hash = (string) ($acesso['senha_hash'] ?? '');
        if ($hash === '' || !password_verify((string) ($_POST['senha'] ?? ''), $hash)) {
            $falhas[$ip][] = time();
            sup_gravar_falhas($h, $falhas);
            usleep(500000 * ($n + 1));   // espera maior a cada erro
            sup_json(['ok' => false, 'erro' => 'Senha incorreta.'], 401);
        }
        unset($falhas[$ip]);
        sup_gravar_falhas($h, $falhas);
        session_regenerate_id(true);
        $_SESSION['atlas_suporte'] = ['ate' => time() + SUP_OCIOSO, 'csrf' => bin2hex(random_bytes(16)), 'desde' => time()];
        sup_json(['ok' => true]);
    }

    if (!sup_logado()) {
        sup_json(['ok' => false, 'erro' => 'Sessão do suporte expirada. Entre de novo.'], 401);
    }
    if (!hash_equals((string) $_SESSION['atlas_suporte']['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        sup_json(['ok' => false, 'erro' => 'Recarregue a página.'], 400);
    }
    if ($acao === 'sair') {
        unset($_SESSION['atlas_suporte']);
        sup_json(['ok' => true]);
    }
    if ($acao === 'dados') {
        $ini = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['ini'] ?? '')) ? $_POST['ini'] : atlas_guarda__agora('Y-m-d', time() - 29 * 86400);
        $fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['fim'] ?? '')) ? $_POST['fim'] : atlas_guarda__agora('Y-m-d');
        if ($fim < $ini) {
            [$ini, $fim] = [$fim, $ini];
        }
        sup_json(['ok' => true, 'dados' => sup_dados($ini, $fim)]);
    }
    if ($acao === 'salvar') {
        $v = [];
        if (!in_array($_POST['modo'] ?? '', ['bloquear', 'observar', 'desligado'], true)) {
            sup_json(['ok' => false, 'erro' => 'Modo inválido.'], 422);
        }
        $v['modo'] = $_POST['modo'];
        foreach (['moldura', 'ocultar_links', 'estrito'] as $k) {
            $v[$k] = ($_POST[$k] ?? '') === '1';
        }
        if (!atlas_guarda_salvar_config_painel($v)) {
            sup_json(['ok' => false, 'erro' => 'Sem permissão para gravar em seguranca/dados.'], 500);
        }
        sup_json(['ok' => true]);
    }
    sup_json(['ok' => false, 'erro' => 'Ação desconhecida.'], 422);
}

$logado = sup_logado();
$csrf = $logado ? $_SESSION['atlas_suporte']['csrf'] : '';
$hoje = atlas_guarda__agora('Y-m-d');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<title>Proteção · Suporte Atlas</title>
<style>
:root{--font:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;--mono:ui-monospace,SFMono-Regular,Consolas,monospace;--r:14px}
:root{--bg:#0b1020;--bg2:#0f172a;--card:#111a2e;--card2:#16223a;--line:rgba(148,163,184,.16);--line2:rgba(148,163,184,.3);
  --ink:#e6ecf5;--ink2:#9aa8be;--ink3:#6b7a91;--pri:#818cf8;--pri2:#6366f1;--pri-soft:rgba(129,140,248,.16);
  --ok:#34d399;--ok-soft:rgba(52,211,153,.14);--red:#f87171;--red-soft:rgba(248,113,113,.14);--amb:#fbbf24;--amb-soft:rgba(251,191,36,.14);--ora:#fb923c}
@media (prefers-color-scheme: light){:root:not([data-tema="escuro"]){--bg:#f3f5fa;--bg2:#fff;--card:#fff;--card2:#f6f8fc;--line:rgba(15,23,42,.09);--line2:rgba(15,23,42,.18);
  --ink:#0f172a;--ink2:#526078;--ink3:#8593a8;--pri:#4f46e5;--pri2:#4338ca;--pri-soft:rgba(79,70,229,.1);--ok:#059669;--ok-soft:rgba(5,150,105,.1);--red:#dc2626;--red-soft:rgba(220,38,38,.09);--amb:#b45309;--amb-soft:rgba(180,83,9,.1);--ora:#ea580c}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font:500 14.5px/1.5 var(--font);color:var(--ink);background:radial-gradient(1000px 500px at 85% -10%,var(--pri-soft),transparent 60%),linear-gradient(var(--bg),var(--bg2));background-attachment:fixed}
button,input,select{font:inherit;color:inherit}
button{cursor:pointer}
:focus-visible{outline:2px solid var(--pri);outline-offset:2px;border-radius:6px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:38px;padding:0 15px;border-radius:10px;border:1px solid var(--line2);background:var(--card);font-weight:600;white-space:nowrap}
.btn:hover{border-color:var(--pri)}
.btn.pri{background:linear-gradient(135deg,var(--pri2),#7c3aed);border-color:transparent;color:#fff}
.btn:disabled{opacity:.5;cursor:not-allowed}
.campo{height:42px;padding:0 12px;border-radius:10px;border:1px solid var(--line2);background:var(--card2);width:100%}
.campo:focus{outline:0;border-color:var(--pri);box-shadow:0 0 0 4px var(--pri-soft)}
svg.i{width:18px;height:18px;flex:none}

/* entrada */
.entrada{min-height:100vh;display:grid;place-items:center;padding:20px}
.entrada form{width:min(380px,100%);background:var(--card);border:1px solid var(--line);border-radius:20px;padding:30px;box-shadow:0 30px 70px -30px rgba(0,0,0,.6)}
.selo{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--pri2),#7c3aed);color:#fff;margin-bottom:16px}
.selo svg{width:24px;height:24px}
.entrada h1{margin:0;font-size:21px;font-weight:800;letter-spacing:-.02em}
.entrada p{margin:4px 0 20px;color:var(--ink2)}
.entrada label{display:block;font-size:12.5px;font-weight:700;color:var(--ink2);margin-bottom:6px}
.entrada .btn{width:100%;margin-top:14px;height:42px}
.erro{color:var(--red);font-size:13px;font-weight:600;min-height:18px;margin-top:10px}

/* painel */
.topo{position:sticky;top:0;z-index:5;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px clamp(14px,3vw,30px);background:color-mix(in srgb,var(--bg) 82%,transparent);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
.topo .selo{width:36px;height:36px;border-radius:11px;margin:0}
.topo .selo svg{width:19px;height:19px}
.topo h1{margin:0;font-size:16px;font-weight:800}
.topo small{display:block;color:var(--ink3);font-size:12px;font-weight:600}
.topo .esp{flex:1}
.per{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.per input{height:36px;width:150px}
main{max-width:1300px;margin:0 auto;padding:20px clamp(14px,3vw,30px) 60px}
.faixa{display:flex;align-items:center;gap:16px;flex-wrap:wrap;border-radius:18px;padding:18px 20px;color:#fff}
.faixa.ok{background:linear-gradient(120deg,#047857,#10b981 60%,#2dd4bf)}
.faixa.ruim{background:linear-gradient(120deg,#991b1b,#dc2626 60%,#f97316)}
.faixa.obs{background:linear-gradient(120deg,#92400e,#d97706 60%,#fbbf24)}
.faixa .ic{width:50px;height:50px;border-radius:15px;background:rgba(255,255,255,.18);display:grid;place-items:center}
.faixa .ic svg{width:26px;height:26px}
.faixa h2{margin:0;font-size:18px;font-weight:800}
.faixa p{margin:2px 0 0;opacity:.9}
.kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:14px 16px;border-left:3px solid var(--c)}
.kpi span{display:block;font-size:11.5px;font-weight:700;color:var(--ink2);text-transform:uppercase;letter-spacing:.04em}
.kpi strong{display:block;font-size:28px;font-weight:800;line-height:1.2;margin-top:4px;font-variant-numeric:tabular-nums}
.kpi small{color:var(--ink3)}
.grade{display:grid;gap:14px;margin-top:14px}
.g64{grid-template-columns:minmax(0,6fr) minmax(0,4fr)}
.g3{grid-template-columns:repeat(3,minmax(0,1fr))}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px;min-width:0}
.card h3{margin:0 0 14px;font-size:15px;font-weight:800;display:flex;justify-content:space-between;align-items:baseline;gap:10px}
.card h3 small{font-size:12px;color:var(--ink3);font-weight:600}
.graf{position:relative;height:280px}
.modos{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px}
.modo{position:relative;display:flex;flex-direction:column;gap:3px;padding:11px 12px;border:1px solid var(--line2);border-radius:12px;background:var(--card2);cursor:pointer}
.modo input{position:absolute;opacity:0}
.modo b{font-size:13.5px}
.modo small{font-size:12px;color:var(--ink2);line-height:1.35}
.modo:has(input:checked){border-color:var(--pri);box-shadow:0 0 0 3px var(--pri-soft)}
.modo:has(input:focus-visible){outline:2px solid var(--pri);outline-offset:2px}
.chave{display:flex;gap:12px;align-items:flex-start;padding:11px 13px;border:1px solid var(--line);border-radius:12px;background:var(--card2);margin-bottom:8px;cursor:pointer}
.chave input{position:absolute;opacity:0}
.chave .t{flex:none;width:40px;height:23px;border-radius:99px;background:var(--line2);position:relative;transition:background .2s;margin-top:1px}
.chave .t::after{content:"";position:absolute;top:3px;left:3px;width:17px;height:17px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3)}
.chave:has(input:checked) .t{background:linear-gradient(135deg,var(--pri2),#7c3aed)}
.chave:has(input:checked) .t::after{transform:translateX(17px)}
.chave:has(input:focus-visible){outline:2px solid var(--pri);outline-offset:2px}
.chave b{display:block;font-size:13.5px}
.chave small{display:block;color:var(--ink2);font-size:12.5px;line-height:1.4}
.aviso{padding:10px 12px;border-radius:10px;background:var(--amb-soft);font-size:12.5px;margin-bottom:10px}
.top-l{display:grid;grid-template-columns:minmax(0,1.3fr) 1fr 40px;gap:10px;align-items:center;margin-bottom:9px;font-size:13px}
.top-l span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.barra{height:8px;border-radius:99px;background:var(--card2);overflow:hidden}
.barra i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--red),var(--ora))}
.top-l b{text-align:right;font-variant-numeric:tabular-nums}
.vazio{color:var(--ink3);text-align:center;padding:26px 8px;font-size:13.5px}
.tab-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink3);font-weight:700;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap}
td{padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:top;color:var(--ink2)}
td b{color:var(--ink)}
tr:last-child td{border-bottom:0}
code{font-family:var(--mono);font-size:12px;color:var(--ink)}
.tag{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11.5px;font-weight:700;background:var(--card2);border:1px solid var(--line)}
.tag.red{background:var(--red-soft);color:var(--red);border-color:transparent}
.tag.amb{background:var(--amb-soft);color:var(--amb);border-color:transparent}
.ponto{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}
.toast{position:fixed;left:50%;bottom:20px;transform:translate(-50%,20px);opacity:0;transition:.25s;padding:10px 16px;border-radius:10px;background:var(--ink);color:var(--bg);font-weight:600;z-index:9;pointer-events:none}
.toast.on{opacity:1;transform:translate(-50%,0)}
.esq{height:110px;border-radius:var(--r);background:linear-gradient(90deg,var(--card),var(--card2),var(--card));background-size:200% 100%;animation:esq 1.2s infinite}
@keyframes esq{to{background-position:-200% 0}}
@media (max-width:980px){.g64,.g3{grid-template-columns:1fr}.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:560px){.modos{grid-template-columns:1fr}.topo h1{font-size:15px}.topo .esp{display:none}.topo>div:nth-child(2){flex:1;min-width:0}.per{order:3;width:100%;flex-wrap:nowrap}.per input{flex:1;width:auto;min-width:0;padding:0 8px}.per .btn{padding:0 12px}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<?php $escudo = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'; ?>
<?php if (!$logado): ?>
<div class="entrada">
  <form id="fEntrar" autocomplete="off">
    <div class="selo"><?= $escudo ?></div>
    <h1>Proteção do Atlas</h1>
    <p>Acesso restrito à equipe de suporte.</p>
    <label for="senha">Senha do suporte</label>
    <input class="campo" id="senha" type="password" autocomplete="current-password" required autofocus>
    <div class="erro" id="erro" role="alert"></div>
    <button class="btn pri" type="submit">Entrar</button>
  </form>
</div>
<script>
document.getElementById('fEntrar').addEventListener('submit', async function (e) {
  e.preventDefault();
  var b = this.querySelector('button'), er = document.getElementById('erro'); b.disabled = true; er.textContent = '';
  var f = new FormData(); f.append('acao', 'entrar'); f.append('senha', document.getElementById('senha').value);
  try {
    var j = await (await fetch('suporte.php', { method: 'POST', body: f, credentials: 'same-origin' })).json();
    if (j.ok) { location.reload(); return; }
    er.textContent = j.erro || 'Não foi possível entrar.';
  } catch (x) { er.textContent = 'Falha de conexão.'; }
  b.disabled = false; document.getElementById('senha').select();
});
</script>
<?php else: ?>
<header class="topo">
  <div class="selo"><?= $escudo ?></div>
  <div><h1>Proteção do Atlas · Suporte</h1><small id="serv">carregando…</small></div>
  <div class="esp"></div>
  <div class="per">
    <input class="campo" type="date" id="ini" aria-label="De">
    <input class="campo" type="date" id="fim" aria-label="Até" value="<?= htmlspecialchars($hoje) ?>">
    <button class="btn" id="atualizar">Atualizar</button>
  </div>
  <button class="btn" id="sair">Sair</button>
</header>
<main id="conteudo"><div class="esq"></div><div class="esq" style="margin-top:14px;height:300px"></div></main>
<div class="toast" id="toast" role="status"></div>
<script src="../script/chart.js"></script>
<script>
(function () {
  var CSRF = <?= json_encode($csrf) ?>, HOJE = <?= json_encode($hoje) ?>;
  var $ = function (s) { return document.querySelector(s); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function int(v) { return new Intl.NumberFormat('pt-BR').format(+v || 0); }
  function dataBR(s) { var d = String(s || '').slice(0, 10).split('-'); return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] : '—'; }
  function dhBR(s) { return s ? dataBR(s) + ' ' + String(s).slice(11, 19) : '—'; }
  function toast(m) { var t = $('#toast'); t.textContent = m; t.classList.add('on'); clearTimeout(t._t); t._t = setTimeout(function () { t.classList.remove('on'); }, 3200); }
  var d0 = new Date(HOJE + 'T12:00:00'); d0.setDate(d0.getDate() - 29); $('#ini').value = d0.toISOString().slice(0, 10);

  async function api(acao, dados) {
    var f = new FormData(); f.append('acao', acao); f.append('csrf', CSRF);
    Object.keys(dados || {}).forEach(function (k) { f.append(k, dados[k]); });
    var r = await fetch('suporte.php', { method: 'POST', body: f, credentials: 'same-origin' });
    var j = await r.json().catch(function () { return { ok: false, erro: 'Resposta inválida (HTTP ' + r.status + ')' }; });
    if (r.status === 401) { location.reload(); throw new Error(j.erro); }
    if (!j.ok) throw new Error(j.erro || 'Falha');
    return j.dados;
  }

  var MOT = { token: 'Fora das telas do sistema', origem: 'De outro endereço', sessao: 'Sem login' };
  var COR = { token: 'var(--red)', origem: 'var(--ora)', sessao: 'var(--ink3)' };
  function programa(ua) {
    ua = String(ua || ''); if (!ua) return 'programa não informado';
    var f = ua.match(/curl|wget|python[-\w]*|postman|insomnia|httpie|axios|node-fetch|okhttp|java|go-http|powershell|guzzle|php/i);
    if (f) return 'Ferramenta: ' + f[0];
    var m = ua.match(/(Edg|OPR|Chrome|Firefox|Safari)\/(\d+)/);
    return m ? ({ Edg: 'Edge', OPR: 'Opera' }[m[1]] || m[1]) + ' ' + m[2] : ua.slice(0, 40);
  }
  var grafico = null;

  async function carregar() {
    var c = $('#conteudo');
    try {
      var p = await api('dados', { ini: $('#ini').value, fim: $('#fim').value });
    } catch (e) { c.innerHTML = '<div class="card vazio">' + esc(e.message) + '</div>'; return; }
    var cfg = p.config, ext = p.totais.externos;
    $('#serv').textContent = (p.serventia.nome || 'Serventia') + (p.serventia.cns ? ' · CNS ' + p.serventia.cns : '');
    var tipo = cfg.emergencia || cfg.modo === 'desligado' ? 'ruim' : cfg.modo === 'observar' ? 'obs' : 'ok';
    var h = '<div class="faixa ' + tipo + '"><div class="ic"><?= $escudo ?></div><div><h2>' +
      (cfg.emergencia ? 'Proteção desligada pelo arquivo de emergência' : cfg.modo === 'bloquear' ? 'Proteção ativa: pedidos de fora do sistema são recusados' : cfg.modo === 'observar' ? 'Só registrando: nada está sendo recusado' : 'Proteção desligada') +
      '</h2><p>' + (cfg.emergencia ? 'Apague o arquivo seguranca/dados/DESLIGAR para religar.' : ext ? int(ext) + ' pedido' + (ext > 1 ? 's' : '') + ' por fora das telas no período.' : 'Nenhum pedido por fora das telas no período.') + '</p></div></div>';
    h += '<div class="kpis">' +
      '<div class="kpi" style="--c:var(--red)"><span>Fora das telas</span><strong>' + int(p.por_motivo.token) + '</strong><small>sem o token do sistema</small></div>' +
      '<div class="kpi" style="--c:var(--ora)"><span>De outro endereço</span><strong>' + int(p.por_motivo.origem) + '</strong><small>outro site ou ferramenta</small></div>' +
      '<div class="kpi" style="--c:var(--ink3)"><span>Sem login</span><strong>' + int(p.por_motivo.sessao) + '</strong><small>sessão expirada ou acesso direto</small></div>' +
      '<div class="kpi" style="--c:var(--pri)"><span>Usuários envolvidos</span><strong>' + int(p.totais.usuarios) + '</strong><small>' + int(p.totais.ips) + ' IP' + (p.totais.ips === 1 ? '' : 's') + '</small></div></div>';
    h += '<div class="grade g64"><div class="card"><h3>Pedidos recusados por dia</h3><div class="graf"><canvas id="gDia"></canvas></div></div>' +
      '<div class="card"><h3>Opções da proteção <small>v' + esc(cfg.versao) + '</small></h3><div class="modos" role="radiogroup" aria-label="Modo">' +
      [['bloquear', 'Bloquear', 'Recusa pedidos de fora das telas. Recomendado.'], ['observar', 'Só registrar', 'Não recusa; lista o que seria recusado.'], ['desligado', 'Desligado', 'Sem proteção nenhuma.']].map(function (m) {
        return '<label class="modo"><input type="radio" name="modo" value="' + m[0] + '"' + (cfg.modo === m[0] ? ' checked' : '') + '><b>' + m[1] + '</b><small>' + m[2] + '</small></label>';
      }).join('') + '</div>' +
      chave('oMoldura', 'Esconder o endereço das páginas', 'As telas abrem numa moldura; a barra mostra só o endereço do sistema.', cfg.moldura) +
      chave('oLinks', 'Esconder o endereço dos links', 'O canto inferior da tela não mostra para onde o link leva.', cfg.ocultar_links) +
      chave('oEstrito', 'Modo estrito', 'Também recusa consultas de ferramentas sem navegador, mesmo com login. Só tem efeito com o Atlas em HTTPS ou localhost.' + (cfg.contexto_seguro ? '' : ' <b>Nesta serventia (HTTP pelo IP) não está em efeito.</b>'), cfg.estrito) +
      (cfg.config_local ? '<div class="aviso">Esta máquina tem um seguranca/config.local.php. O que for salvo aqui vale por cima dele.</div>' : '') +
      '<button class="btn pri" id="salvar">Salvar opções</button>' + (cfg.alterado_em ? '<div style="margin-top:8px;color:var(--ink3);font-size:12px">Alterado em ' + dhBR(cfg.alterado_em) + '</div>' : '') + '</div></div>';
    function topo(t, lista, fmt) {
      var max = lista.length ? lista[0].v : 1;
      return '<div class="card"><h3>' + t + ' <small>sem "sem login"</small></h3>' + (lista.length ? lista.map(function (x) {
        return '<div class="top-l"><span title="' + esc(x.k) + '">' + fmt(x.k) + '</span><div class="barra"><i style="width:' + (x.v / max * 100) + '%"></i></div><b>' + int(x.v) + '</b></div>';
      }).join('') : '<div class="vazio">Nada no período.</div>') + '</div>';
    }
    h += '<div class="grade g3">' +
      topo('Quem tentou por fora', p.por_usuario, function (u) { var n = p.nomes[u]; return n ? '<b>' + esc(n) + '</b> <small style="color:var(--ink3)">' + esc(u) + '</small>' : esc(u); }) +
      topo('De quais IPs', p.por_ip, esc) +
      topo('Endereços mais visados', p.por_rota, function (r) { return '<code>' + esc(r) + '</code>'; }) + '</div>';
    h += '<div class="card" style="margin-top:14px"><h3>Pedidos recusados <small>' + (p.recentes.length >= 300 ? 'os 300 mais recentes' : int(p.recentes.length) + ' no período') + '</small></h3>' +
      (p.recentes.length ? '<div class="tab-wrap"><table><thead><tr><th>Quando</th><th>Motivo</th><th>Usuário</th><th>IP</th><th>Pedido</th><th>Origem / programa</th><th>Situação</th></tr></thead><tbody>' +
      p.recentes.map(function (e) {
        var n = e.usuario && p.nomes[e.usuario];
        return '<tr><td style="white-space:nowrap">' + dhBR(e.em) + '</td>' +
          '<td style="white-space:nowrap"><span class="ponto" style="background:' + (COR[e.motivo] || 'var(--ink3)') + '"></span>' + esc(MOT[e.motivo] || e.motivo) + '</td>' +
          '<td>' + (e.usuario ? (n ? '<b>' + esc(n) + '</b><br><small>' + esc(e.usuario) + '</small>' : esc(e.usuario)) : '—') + '</td>' +
          '<td>' + esc(e.ip) + '</td>' +
          '<td><span class="tag">' + esc(e.metodo) + '</span> <code>' + esc(e.rota) + (e.query ? '?' + esc(String(e.query).slice(0, 70)) : '') + '</code></td>' +
          '<td style="max-width:280px"><small style="word-break:break-all">' + esc(e.origem || 'sem origem (ferramenta externa)') + '</small><br><small title="' + esc(e.agente) + '">' + esc(programa(e.agente)) + '</small></td>' +
          '<td>' + (e.acao === 'observado' ? '<span class="tag amb">só registrado</span>' : '<span class="tag red">recusado</span>') + '</td></tr>';
      }).join('') + '</tbody></table></div>' : '<div class="vazio">Nenhum pedido recusado no período.</div>') + '</div>';
    c.innerHTML = h;

    if (window.Chart) {
      if (grafico) grafico.destroy();
      var cs = getComputedStyle(document.body);
      grafico = new Chart($('#gDia'), { type: 'bar', data: { labels: p.serie.map(function (x) { return dataBR(x.d).slice(0, 5); }), datasets: [
        { label: 'Fora das telas / outro endereço', data: p.serie.map(function (x) { return x.externo; }), backgroundColor: 'rgba(239,68,68,.8)', borderRadius: 5, stack: 's', maxBarThickness: 26 },
        { label: 'Sem login', data: p.serie.map(function (x) { return x.sessao; }), backgroundColor: 'rgba(148,163,184,.55)', borderRadius: 5, stack: 's', maxBarThickness: 26 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'top', align: 'end', labels: { color: cs.getPropertyValue('--ink2'), boxWidth: 12 } } },
          scales: { x: { stacked: true, grid: { display: false }, ticks: { color: cs.getPropertyValue('--ink3'), maxTicksLimit: 15 } },
            y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: cs.getPropertyValue('--ink3') }, grid: { color: cs.getPropertyValue('--line') } } } } });
    } else {
      $('#gDia').parentNode.innerHTML = '<div class="vazio">Gráfico indisponível.</div>';
    }
    $('#salvar').onclick = async function () {
      var b = this, modo = (document.querySelector('input[name=modo]:checked') || {}).value || 'bloquear';
      if (modo !== 'bloquear' && !confirm(modo === 'observar' ? 'Em "Só registrar" nada de fora será recusado. Continuar?' : 'Desligar deixa o sistema aberto a pedidos de fora. Continuar?')) return;
      b.disabled = true;
      try {
        await api('salvar', { modo: modo, moldura: $('#oMoldura').checked ? '1' : '0', ocultar_links: $('#oLinks').checked ? '1' : '0', estrito: $('#oEstrito').checked ? '1' : '0' });
        toast('Opções salvas. Valem a partir da próxima tela aberta.'); carregar();
      } catch (e) { toast(e.message); b.disabled = false; }
    };
  }
  function chave(id, t, s, on) { return '<label class="chave"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + '><span class="t"></span><span><b>' + t + '</b><small>' + s + '</small></span></label>'; }
  $('#atualizar').onclick = carregar;
  $('#sair').onclick = async function () { try { await api('sair'); } catch (e) {} location.reload(); };
  carregar();
})();
</script>
<?php endif; ?>
</body>
</html>
