<?php
/**
 * signum/inc/tcloud_modulo.php — ponte entre os módulos do Atlas (ofícios, notas devolutivas, O.S.…)
 * e o TCloud Assinador centralizado no Atlas Signum.
 *
 * O Signum é o ponto central:
 *  - a escolha do assinador A3 de cada usuário (Configurar do Signum: TCloud Assinador ou SERPRO);
 *  - motivo, local, nível da assinatura;
 *  - o pedido tcloudsign://local, o ticket, o acompanhamento, o teste e a instalação na estação.
 * O módulo só gera o PDF com o próprio selo (tcloud_iniciar.php) e grava o resultado (tcloud_gravar.php).
 *
 * Uso numa página de assinatura do módulo:
 *   require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';
 *   $USA_TC = asg_tcm_usa_tcloud();
 *   ... asg_tcm_banner_html(), asg_tcm_card_html([...]), asg_tcm_scripts('tcloud_iniciar.php')
 */
require_once __DIR__ . '/../config_assinatura.php';
asg_tc_lib();

/** O usuário logado assina A3 pelo TCloud Assinador? (escolha feita no Configurar do Signum; padrão: sim) */
function asg_tcm_usa_tcloud($usuario = null)
{
    // peças do Signum 1.7.0+ (se faltar alguma, o módulo segue pelo SERPRO em vez de quebrar)
    if (!function_exists('asg_tcl_iniciar_modulo') || !function_exists('asg_a3_assinador') || !function_exists('asg_csrf')
        || !defined('ASG_TC_CMD_EXECUTAR') || !defined('ASG_TC_CMD_LINUX')) return false;
    $usuario = $usuario ?? (string)($_SESSION['username'] ?? '');
    if ($usuario === '') return true;
    try { return asg_a3_assinador(asg_ucfg($usuario)) === 'tcloud'; }
    catch (Throwable $e) { return true; }
}

/** Caminho web da pasta do Signum a partir de uma página de módulo irmão (…/atlas/oficios → …/atlas/signum). */
function asg_tcm_url_signum()
{
    return '../signum';
}

/* ------------------------------------------------------------------ criação do pedido (módulo) */

/** Saída JSON padronizada dos endpoints tcloud_iniciar.php dos módulos. */
function asg_tcm_json($dados)
{
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); }
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Confere o CSRF do Signum (a página do módulo recebe o token do Signum). */
function asg_tcm_checar_csrf()
{
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
}

/**
 * Cria o pedido de assinatura para o PDF (já com o selo do módulo) e devolve o que a tela precisa.
 * @param string $pdfSelado bytes do PDF com o selo desenhado pelo módulo
 * @param string $nomeDoc   nome exibido na janela do TCloud Assinador (ex.: "Ofício 12-2026.pdf")
 * @param array  $destino   ['modulo','gravador','funcao','dados'] — ver asg_tcl_iniciar_modulo()
 */
function asg_tcm_iniciar($pdfSelado, $nomeDoc, $destino)
{
    $usuario = (string)($_SESSION['username'] ?? '');
    if ($usuario === '') throw new RuntimeException('Sessão expirada. Entre de novo no sistema.');
    asg_ensure_schema();
    $u = asg_ucfg($usuario);
    $cfg = asg_config();

    $op = [
        'motivo'    => $cfg['motivo'] ?: 'Assinatura eletrônica de documento',
        'nivel'     => ASG_TC_NIVEL,
        'aparencia' => ['visivel' => false],   // o selo visual é o do próprio módulo (já desenhado no PDF)
    ];
    if (trim((string)($u['assinante_local'] ?? '')) !== '') $op['local'] = trim($u['assinante_local']);

    $destino['script'] = (string)($_SERVER['SCRIPT_NAME'] ?? '');       // URLs do módulo na gravação
    $nome = trim((string)($u['assinante_nome'] ?? '')) ?: $usuario;
    $r = asg_tcl_iniciar_modulo($usuario, $nome, $pdfSelado, $nomeDoc, $op, $destino,
                                (string)($_SERVER['REMOTE_ADDR'] ?? ''), (string)($_SERVER['HTTP_HOST'] ?? ''));
    return ['success' => true] + $r + ['url_instalacao' => ASG_TC_URL_INSTALACAO];
}

/** Titular legível para gravar no módulo (ex.: "FULANO DE TAL (CPF 123.456.789-00)"). */
function asg_tcm_titular_texto($cert)
{
    $t = trim((string)($cert['titular'] ?? ''));
    $cpf = preg_replace('~\D~', '', (string)($cert['cpf'] ?? ''));
    if ($cpf !== '' && strlen($cpf) === 11) $cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9);
    return $t . ($cpf !== '' ? ' (' . (strlen(preg_replace('~\D~', '', $cpf)) === 14 ? 'CNPJ ' : 'CPF ') . $cpf . ')' : '');
}

/* ------------------------------------------------------------------ interface (páginas dos módulos) */

function asg_tcm_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** CSS do cartão, da faixa de instalação e da barra de assinar no celular. */
function asg_tcm_css()
{
    return <<<'CSS'
<style>
/* ---- TCloud Assinador (Atlas Signum) ---- */
.tcm-banner{ display:none; align-items:center; gap:14px; flex-wrap:wrap; background:#fffbeb; border:1px solid #fcd34d; border-radius:16px; padding:14px 18px; margin:0 0 16px; }
.tcm-banner .ic{ width:42px; height:42px; border-radius:12px; background:#f59e0b; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex:0 0 auto; }
.tcm-banner .tx{ flex:1 1 260px; min-width:0; } .tcm-banner .tx b{ display:block; color:#78350f; } .tcm-banner .tx small{ display:block; color:#92400e; font-size:.84rem; margin-top:2px; }
.tcm-btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:9px 16px; border:0; border-radius:999px; font-weight:700; font-size:.88rem; cursor:pointer; text-decoration:none; line-height:1.2; }
.tcm-btn-inst{ background:#d97706; color:#fff; } .tcm-btn-inst:hover{ background:#b45309; color:#fff; }
.tcm-btn-soft{ background:#eef2f7; color:#1e293b; } .tcm-btn-soft:hover{ background:#e2e8f0; }
.tcm-astat{ display:flex; align-items:center; gap:12px; }
.tcm-astat .lamp{ width:12px; height:12px; border-radius:50%; background:#eab308; box-shadow:0 0 0 4px rgba(234,179,8,.18); flex:0 0 auto; }
.tcm-astat.on .lamp{ background:#16a34a; box-shadow:0 0 0 4px rgba(22,163,74,.18); }
.tcm-astat.off .lamp{ background:#ef4444; box-shadow:0 0 0 4px rgba(239,68,68,.18); }
.tcm-astat .st{ font-weight:700; } .tcm-astat .hl{ color:#64748b; font-size:.82rem; }
.tcm-acoes{ display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
.tcm-mbar{ display:none; padding:12px 18px 14px; border-top:1px solid #eef1f6; }
@media(max-width:991.98px){
  .tcm-mbar{ display:block; }
  .tcm-banner{ padding:12px 14px; } .tcm-banner .tcm-btn{ flex:1 1 auto; }
}
body.dark-mode .tcm-banner{ background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.4); }
body.dark-mode .tcm-banner .tx b{ color:#fcd34d; } body.dark-mode .tcm-banner .tx small{ color:#fde68a; }
body.dark-mode .tcm-btn-soft{ background:rgba(255,255,255,.08); color:#e2e8f0; }
</style>
CSS;
}

/** Faixa "TCloud Assinador não verificado/detectado neste computador" com o botão de instalar. */
function asg_tcm_banner_html()
{
    return '<div class="tcm-banner" id="tcBanner">'
         . '<div class="ic"><i class="fa fa-download"></i></div>'
         . '<div class="tx"><b id="tcBanTit">TCloud Assinador ainda não verificado neste computador</b>'
         . '<small id="tcBanTxt">Para assinar com o seu token, ele precisa estar instalado aqui. A instalação leva menos de um minuto.</small></div>'
         . '<button type="button" class="tcm-btn tcm-btn-inst" id="tcBanInstalar"><i class="fa fa-download"></i> Instalar neste computador</button>'
         . '</div>';
}

/**
 * Cartão "TCloud Assinador" (status deste computador + Testar agora + Instalar).
 * $c: classes do módulo — ['card' => 'sig-card', 'hd' => 'hd', 'bd' => 'bd']
 */
function asg_tcm_card_html($c = [])
{
    $card = $c['card'] ?? 'sig-card'; $hd = $c['hd'] ?? 'hd'; $bd = $c['bd'] ?? 'bd';
    return '<div class="' . asg_tcm_e($card) . '">'
         . '<div class="' . asg_tcm_e($hd) . '"><i class="fa fa-cloud"></i> TCloud Assinador</div>'
         . '<div class="' . asg_tcm_e($bd) . '">'
         . '<div id="tcAstat" class="tcm-astat"><span class="lamp"></span><div><div class="st" id="tcState">Verificando…</div>'
         . '<div class="hl" id="tcHelp">Ao assinar, o TCloud Assinador abre neste computador.</div></div></div>'
         . '<div class="tcm-acoes">'
         . '<button type="button" class="tcm-btn tcm-btn-soft" id="tcTestar"><i class="fa fa-search"></i> Testar agora</button>'
         . '<button type="button" class="tcm-btn tcm-btn-inst" id="tcInstalar" style="display:none"><i class="fa fa-download"></i> Instalar nesta estação</button>'
         . '</div></div></div>';
}

/** Scripts do TCloud (JS do Signum + ligação da página). $iniciar = endpoint do módulo que cria o pedido. */
function asg_tcm_scripts($iniciar)
{
    $sg = asg_tcm_url_signum();
    $cfg = [
        'csrf' => asg_csrf(), 'endpoint' => $sg . '/tcloud_api.php', 'endpointIniciar' => (string)$iniciar,
        'urlInstalacao' => ASG_TC_URL_INSTALACAO, 'cmdExecutar' => ASG_TC_CMD_EXECUTAR,
        'cmdMac' => ASG_TC_CMD_MAC, 'cmdLinux' => ASG_TC_CMD_LINUX,
    ];
    return '<script src="' . asg_tcm_e($sg) . '/js/tcloud_signum.js?v=' . asg_tcm_e(ASG_VERSAO) . '"></script>' . "\n"
         . '<script src="' . asg_tcm_e($sg) . '/js/tcloud_modulo.js?v=' . asg_tcm_e(ASG_VERSAO) . '"></script>' . "\n"
         . '<script>TcModulo.init(' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ');</script>' . "\n";
}
